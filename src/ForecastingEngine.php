<?php

require_once __DIR__ . '/Database.php';

/**
 * ForecastingEngine
 * -----------------
 * Primary:  Facebook Prophet via a local Python microservice (port 5001).
 *           Prophet handles seasonality, trend changes, and confidence intervals.
 *
 * Fallback: Hybrid statistical model (WMA + Exponential Smoothing + OLS)
 *           used automatically when the Prophet service is unreachable.
 *
 * To start the Prophet service:
 *   cd prophet_service && python prophet_service.py
 */
class ForecastingEngine {

    private const MIN_DATA_POINTS  = 3;
    private const ALPHA            = 0.4;   // Exponential smoothing factor
    private const PROPHET_URL      = 'http://127.0.0.1:5001/forecast';
    private const PROPHET_TIMEOUT  = 10;    // seconds

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Predict outgoing stock for a product N periods ahead.
     *
     * Returns an array with keys:
     *   product_id, historical, forecasts, slope, intercept, method
     *
     * 'forecasts' items always contain: period, predicted_qty
     * Prophet forecasts additionally contain: lower, upper, forecast_date
     *
     * @throws RuntimeException if fewer than MIN_DATA_POINTS exist
     */
    public function predict(int $productId, int $periodsAhead = 1): array {
        $data  = $this->getHistoricalData($productId);
        $count = count($data);

        if ($count < self::MIN_DATA_POINTS) {
            $needed = self::MIN_DATA_POINTS - $count;
            throw new RuntimeException(
                "Insufficient data: need " . self::MIN_DATA_POINTS . " records, have {$count}. Need {$needed} more.",
                422
            );
        }

        // Try Prophet first
        $prophetResult = $this->callProphet($data, $periodsAhead);
        if ($prophetResult !== null) {
            return array_merge([
                'product_id' => $productId,
                'historical' => $data,
                'slope'      => null,
                'intercept'  => null,
            ], $prophetResult);
        }

        // Fallback: hybrid statistical model
        return $this->hybridPredict($productId, $data, $periodsAhead);
    }

    // =========================================================================
    // Prophet (primary)
    // =========================================================================

    /**
     * Call the Python Prophet microservice.
     * Returns the decoded response array on success, null on any failure.
     */
    private function callProphet(array $data, int $periodsAhead): ?array {
        // Build the payload Prophet expects
        $historical = array_map(fn($d) => [
            'date' => $d['date'],
            'qty'  => $d['y'],
        ], $data);

        $payload = json_encode([
            'historical' => $historical,
            'periods'    => $periodsAhead,
        ]);

        $ch = curl_init(self::PROPHET_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => self::PROPHET_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200 || !$response) {
            error_log("Prophet service unavailable ({$httpCode}): {$curlErr}. Falling back to hybrid model.");
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded['forecasts'])) {
            error_log("Prophet returned invalid JSON. Falling back to hybrid model.");
            return null;
        }

        return $decoded; // contains 'forecasts' and 'method' => 'prophet'
    }

    // =========================================================================
    // Hybrid fallback (WMA + ES + OLS)
    // =========================================================================

    private function hybridPredict(int $productId, array $data, int $periodsAhead): array {
        $yValues = array_column($data, 'y');

        $wmaBase = $this->weightedMovingAverage($yValues);
        $esBase  = $this->exponentialSmoothing($yValues);
        [$slope, $intercept] = $this->linearRegression($data);
        $lastX = count($data) - 1;

        $forecasts = [];
        for ($i = 1; $i <= $periodsAhead; $i++) {
            $x       = $lastX + $i;
            $olsPred = $slope * $x + $intercept;

            // Blend: 40% WMA, 35% ES, 25% OLS
            $blended     = (0.40 * $wmaBase) + (0.35 * $esBase) + (0.25 * $olsPred);
            $trendDelta  = $slope * ($i - 1);
            $predicted   = max(0, round($blended + $trendDelta, 2));

            $forecasts[] = [
                'period'        => $x,
                'predicted_qty' => $predicted,
            ];
        }

        return [
            'product_id' => $productId,
            'historical' => $data,
            'forecasts'  => $forecasts,
            'slope'      => round($slope, 4),
            'intercept'  => round($intercept, 4),
            'method'     => 'hybrid_wma_es_ols',
        ];
    }

    // =========================================================================
    // Statistical helpers
    // =========================================================================

    /**
     * Weighted Moving Average — recent values get higher weights.
     * Window = min(6, n). Weights: 1, 2, 3, ... n (linear).
     */
    private function weightedMovingAverage(array $values): float {
        $window = min(6, count($values));
        $slice  = array_slice($values, -$window);
        $n      = count($slice);

        $weightSum = 0;
        $valueSum  = 0;
        for ($i = 0; $i < $n; $i++) {
            $weight     = $i + 1;
            $valueSum  += $slice[$i] * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $valueSum / $weightSum : 0;
    }

    /**
     * Exponential Smoothing — S_t = alpha * y_t + (1 - alpha) * S_{t-1}
     */
    private function exponentialSmoothing(array $values): float {
        $alpha    = self::ALPHA;
        $smoothed = (float) $values[0];

        for ($i = 1; $i < count($values); $i++) {
            $smoothed = $alpha * $values[$i] + (1 - $alpha) * $smoothed;
        }

        return $smoothed;
    }

    /**
     * Ordinary Least Squares linear regression.
     * Returns [slope, intercept].
     */
    private function linearRegression(array $data): array {
        $n = count($data);
        $sumX = $sumY = $sumXY = $sumX2 = 0.0;

        foreach ($data as $point) {
            $x      = $point['x'];
            $y      = $point['y'];
            $sumX  += $x;
            $sumY  += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denom = ($n * $sumX2 - $sumX * $sumX);
        if ($denom == 0) {
            return [0, $sumY / $n];
        }

        $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [$slope, $intercept];
    }

    // =========================================================================
    // Data layer
    // =========================================================================

    /**
     * Fetch historical outgoing stock aggregated by date, ordered ASC.
     */
    private function getHistoricalData(int $productId): array {
        $stmt = $this->pdo->prepare(
            "SELECT transaction_date AS date, SUM(quantity) AS qty
             FROM stock_records
             WHERE product_id = ? AND record_type = 'outgoing' AND is_deleted = 0
             GROUP BY transaction_date
             ORDER BY transaction_date ASC"
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $i => $row) {
            $result[] = ['x' => $i, 'y' => (int) $row['qty'], 'date' => $row['date']];
        }
        return $result;
    }
}
