<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/PdfGenerator.php';
require_once __DIR__ . '/../src/AuthManager.php';

$action = $_GET['action'] ?? '';

function jsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

try {
    if ($action !== 'generate') {
        jsonError('Unknown action', 400);
    }

    $dateFrom    = trim($_GET['date_from']    ?? '');
    $dateTo      = trim($_GET['date_to']      ?? '');
    $warehouseId = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== ''
        ? (int)$_GET['warehouse_id'] : null;
    $productId   = isset($_GET['product_id']) && $_GET['product_id'] !== ''
        ? (int)$_GET['product_id'] : null;

    if ($dateFrom === '' || $dateTo === '') {
        jsonError('date_from and date_to are required', 400);
    }

    $fromTs = strtotime($dateFrom);
    $toTs   = strtotime($dateTo);

    if ($fromTs === false || $toTs === false) {
        jsonError('Invalid date format; expected YYYY-MM-DD', 400);
    }

    if ($fromTs > $toTs) {
        jsonError('date_from must not be after date_to', 400);
    }

    $generator = new PdfGenerator();
    $pdfBytes  = $generator->generateStockMovementReport($dateFrom, $dateTo, $warehouseId, $productId);

    $filename = "stock-report-{$dateFrom}-to-{$dateTo}.pdf";

    // Log the report generation
    try {
        $pdo  = Database::getInstance();
        $user = AuthManager::currentUser();
        $stmt = $pdo->prepare(
            "INSERT INTO report_logs (generated_by, date_from, date_to, warehouse_id, product_id, filename)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $user ? $user['id'] : null,
            $dateFrom,
            $dateTo,
            $warehouseId,
            $productId,
            $filename,
        ]);
    } catch (Throwable $logErr) {
        error_log('report_logs insert failed: ' . $logErr->getMessage());
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBytes));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $pdfBytes;
    exit;

} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError('Internal server error', 500);
}
