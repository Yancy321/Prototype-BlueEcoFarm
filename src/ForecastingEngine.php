<?php

require_once __DIR__ . '/Database.php';

class ForecastingEngine {

    private const MIN_DATA_POINTS = 5;
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    /**
     * Predict outgoing stock for a product N periods ahead.
     * Uses OLS Linear Regression on historical outgoing stock data.
     *
     * @throws RuntimeException if fewer than 5 data points exist
     */
    public function predict(int $productId, int $periodsAhead = 1): array {
        $data = $this->getHistoricalData($productId);
        $count = count($data);

        if ($count < self::MIN_DATA_POINTS) {
            $needed = self::MIN_DATA_POINTS - $count;
            throw new RuntimeException(
                "Insufficient data: need " . self::MIN_DATA_POINTS . " records, have {$count}. Need {$needed} more.",
                422
            );
        }

        [$slope, $intercept] = $this->linearRegression($data);

        $lastIndex = count($data) - 1;
        $forecasts = [];
        for ($i = 1; $i <= $periodsAhead; $i++) {
            $x = $lastIndex + $i;
            $forecasts[] = [
                'period' => $x,
                'predicted_qty' => max(0, round($slope * $x + $intercept, 2)),
            ];
        }

        return [
            'product_id'  => $productId,
            'historical'  => $data,
            'forecasts'   => $forecasts,
            'slope'       => round($slope, 4),
            'intercept'   => round($intercept, 4),
        ];
    }

    /**
     * Fetch historical outgoing stock data for a product (aggregated by date).
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

        // Convert to indexed (x, y) pairs
        $result = [];
        foreach ($rows as $i => $row) {
            $result[] = ['x' => $i, 'y' => (int)$row['qty'], 'date' => $row['date']];
        }
        return $result;
    }

    /**
     * Ordinary Least Squares linear regression.
     * Returns [slope, intercept].
     */
    private function linearRegression(array $data): array {
        $n = count($data);
        $sumX = $sumY = $sumXY = $sumX2 = 0.0;

        foreach ($data as $point) {
            $x = $point['x'];
            $y = $point['y'];
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
}
