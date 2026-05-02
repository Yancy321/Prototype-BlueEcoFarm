<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ForecastingEngine.php';
require_once __DIR__ . '/../src/CalendarService.php';

$action = $_GET['action'] ?? '';

function jsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

try {
    if ($action !== 'events') {
        jsonError('Unknown action', 400);
    }

    $year  = isset($_GET['year'])  && $_GET['year']  !== '' ? (int)$_GET['year']  : (int)date('Y');
    $month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : (int)date('n');

    $productId = isset($_GET['product_id']) && $_GET['product_id'] !== ''
        ? (int)$_GET['product_id'] : null;

    // Basic range validation
    if ($month < 1 || $month > 12) {
        jsonError('month must be between 1 and 12', 400);
    }
    if ($year < 2000 || $year > 2100) {
        jsonError('year must be between 2000 and 2100', 400);
    }

    $service = new CalendarService();
    $events  = $service->getForecastEvents($year, $month, $productId);

    echo json_encode($events);

} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError('Internal server error', 500);
}
