<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/ForecastingEngine.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthManager.php';

$action = $_GET['action'] ?? '';

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

try {
    if ($action !== 'predict') {
        jsonError("Unknown action", 400);
    }

    if (empty($_GET['product_id'])) {
        jsonError("Field product_id is required");
    }

    $productId    = (int)$_GET['product_id'];
    $periodsAhead = isset($_GET['periods']) ? (int)$_GET['periods'] : 3;

    $engine = new ForecastingEngine();
    $result = $engine->predict($productId, $periodsAhead);

    // Log each forecast period
    try {
        $pdo  = Database::getInstance();
        $user = AuthManager::currentUser();
        $userId = $user ? $user['id'] : null;
        $dataPoints = count($result['historical'] ?? []);

        $stmt = $pdo->prepare(
            "INSERT INTO forecast_logs (product_id, periods, predicted_qty, data_points, forecast_date, generated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($result['forecasts'] ?? [] as $f) {
            $forecastDate = date('Y-m-d', strtotime("+{$f['period']} months"));
            $stmt->execute([
                $productId,
                $f['period'],
                round($f['predicted_qty'], 2),
                $dataPoints,
                $forecastDate,
                $userId,
            ]);
        }
    } catch (Throwable $logErr) {
        error_log('forecast_logs insert failed: ' . $logErr->getMessage());
    }

    echo json_encode($result);

} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError("Internal server error", 500);
}
