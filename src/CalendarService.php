<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ForecastingEngine.php';

/**
 * CalendarService — maps ForecastingEngine predictions to FullCalendar event objects.
 */
class CalendarService
{
    private PDO $pdo;
    private ForecastingEngine $engine;

    public function __construct(?PDO $pdo = null, ?ForecastingEngine $engine = null)
    {
        $this->pdo    = $pdo    ?? Database::getInstance();
        $this->engine = $engine ?? new ForecastingEngine();
    }

    /**
     * Return FullCalendar-compatible event objects for the given year/month.
     *
     * @param int      $year      4-digit year
     * @param int      $month     1–12
     * @param int|null $productId Optional product filter
     * @return array   Array of FullCalendar event objects
     */
    public function getForecastEvents(int $year, int $month, ?int $productId = null): array
    {
        $products = $this->getProducts($productId);
        $events   = [];

        foreach ($products as $product) {
            $pid  = (int) $product['id'];
            $name = $product['name'];

            try {
                // Predict 1 period ahead (= next month's demand)
                $forecast   = $this->engine->predict($pid, 1);
                $dataPoints = count($forecast['historical']);
                $qty        = (int) round($forecast['forecasts'][0]['predicted_qty']);

                // Place the event on the 1st of the requested month
                $date = sprintf('%04d-%02d-01', $year, $month);

                // Determine urgency level based on recent average
                $recentAvg = $this->getRecentAverage($pid);
                $level = 'normal';
                if ($qty > $recentAvg * 1.2) $level = 'high';
                elseif ($qty < $recentAvg * 0.8) $level = 'low';

                $events[] = [
                    'id'    => "forecast-{$pid}-{$year}-{$month}",
                    'title' => "{$name}: ~{$qty} units",
                    'start' => $date,
                    'extendedProps' => [
                        'productId'    => $pid,
                        'productName'  => $name,
                        'predictedQty' => $qty,
                        'dataPoints'   => $dataPoints,
                        'recentAvg'    => round($recentAvg),
                        'level'        => $level,
                    ],
                ];
            } catch (RuntimeException $e) {
                $date = sprintf('%04d-%02d-01', $year, $month);
                $events[] = [
                    'id'    => "forecast-{$pid}-{$year}-{$month}-insufficient",
                    'title' => "{$name}: Need more data",
                    'start' => $date,
                    'extendedProps' => [
                        'productId'        => $pid,
                        'productName'      => $name,
                        'predictedQty'     => null,
                        'dataPoints'       => $this->countDataPoints($pid),
                        'insufficientData' => true,
                        'level'            => 'insufficient',
                    ],
                ];
            }
        }

        return $events;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Get average outgoing qty over the last 3 data points for a product.
     */
    private function getRecentAverage(int $productId): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT SUM(quantity) AS qty
             FROM stock_records
             WHERE product_id = ? AND record_type = 'outgoing' AND is_deleted = 0
             GROUP BY transaction_date
             ORDER BY transaction_date DESC
             LIMIT 3"
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($rows)) return 0;
        return array_sum($rows) / count($rows);
    }

    /**
     * Fetch all products or a single product.
     */
    private function getProducts(?int $productId): array
    {
        if ($productId !== null) {
            $stmt = $this->pdo->prepare("SELECT id, name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            return $stmt->fetchAll();
        }

        return $this->pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
    }

    /**
     * Map a forecast period index to a day-of-month (1–daysInMonth).
     * We distribute the daysInMonth forecast periods evenly across the month.
     * Period index is 1-based within the forecast array.
     */
    private function periodToDay(int $period, int $daysInMonth): ?int
    {
        // period is the absolute x index from ForecastingEngine
        // We use modulo to map it to a day within the month
        $day = ($period % $daysInMonth) + 1;
        if ($day < 1 || $day > $daysInMonth) {
            return null;
        }
        return $day;
    }

    /**
     * Count historical data points for a product (for insufficient-data events).
     * Counts distinct transaction dates since ForecastingEngine groups by date.
     */
    private function countDataPoints(int $productId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT transaction_date) AS cnt
             FROM stock_records
             WHERE product_id = ? AND record_type = 'outgoing' AND is_deleted = 0"
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }
}
