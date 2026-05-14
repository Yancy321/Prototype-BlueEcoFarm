<?php

require_once __DIR__ . '/Database.php';

/**
 * ForecastingEngine — hybrid forecasting using:
 * 1. Weighted Moving Average (WMA) — gives more weight to recent data
 * 2. Exponential Smoothing (ES)    — smooths out noise
 * 3. Linear Regression (OLS)       — captures long-term trend
 *
 * Final prediction = weighted blend of all three methods.
 * More recent data = higher accuracy.
 */
class ForecastingEngine {

    private const MIN_DATA_POINTS = 5;
    private const ALPHA = 0.4; // Exponential smoothing factor (0–1, higher = more weight on recent)
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    /**
     * Predict outgoing stock for a product N periods ahead.
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

        $yValues = array_column($data, 'y');

        // Method 1: Weighted Moving Average (window = min(6, count))
        $wmaBase = $this->weightedMovingAverage($yValues);

        // Method 2: Exponential Smoothing
        $esBase  = $this->exponentialSmoothing($yValues);

        // Method 3: Linear Regression slope for trend
        [$slope, $intercept] = $this->linearRegression($data);
        $lastX = count($data) - 1;

        $forecasts = [];
        for ($i = 1; $i <= $periodsAhead; $i++) {
            $x = $lastX + $i;

            // OLS prediction
            $olsPred = $slope * $x + $intercept;

            // Blend: 40% WMA, 35% ES, 25% OLS
            // WMA and ES are level-based; OLS adds trend direction
            $blended = (0.40 * $wmaBase) + (0.35 * $esBase) + (0.25 * $olsPred);

            // Apply trend delta for periods beyond 1
            $trendDelta = $slope * ($i - 1);
            $predicted  = max(0, round($blended + $trendDelta, 2));

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

    // -------------------------------------------------------------------------
    // Forecasting methods
    // -------------------------------------------------------------------------

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
            $weight     = $i + 1; // weight 1..n
            $valueSum  += $slice[$i] * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $valueSum / $weightSum : 0;
    }

    /**
     * Exponential Smoothing — smooths noise, emphasises recent trend.
     * S_t = alpha * y_t + (1 - alpha) * S_{t-1}
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

    // -------------------------------------------------------------------------
    // Data layer
    // -------------------------------------------------------------------------

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
