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
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $events = [];

        foreach ($products as $product) {
            $pid  = (int) $product['id'];
            $name = $product['name'];

            try {
                $forecast = $this->engine->predict($pid, $daysInMonth);
                $dataPoints = count($forecast['historical']);

                // Map each forecast period to a date within the requested month
                foreach ($forecast['forecasts'] as $f) {
                    $day = $this->periodToDay($f['period'], $daysInMonth);
                    if ($day === null) {
                        continue;
                    }

                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $qty  = (int) round($f['predicted_qty']);

                    $events[] = [
                        'id'    => "forecast-{$pid}-{$date}",
                        'title' => "{$name}: " . number_format($qty, 0),
                        'start' => $date,
                        'extendedProps' => [
                            'productId'    => $pid,
                            'predictedQty' => $qty,
                            'dataPoints'   => $dataPoints,
                        ],
                    ];
                }
            } catch (RuntimeException $e) {
                // Insufficient data — return indicator event on the 1st of the month
                $date = sprintf('%04d-%02d-01', $year, $month);
                $events[] = [
                    'id'    => "forecast-{$pid}-{$date}-insufficient",
                    'title' => "{$name}: Insufficient data",
                    'start' => $date,
                    'extendedProps' => [
                        'productId'       => $pid,
                        'predictedQty'    => null,
                        'dataPoints'      => $this->countDataPoints($pid),
                        'insufficientData' => true,
                    ],
                ];
            }
        }

        // Filter: only return events whose start date is within the requested year/month
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $events = array_values(array_filter($events, function ($ev) use ($prefix) {
            return strncmp($ev['start'], $prefix, strlen($prefix)) === 0;
        }));

        return $events;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

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
