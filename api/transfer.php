<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/TransferManager.php';
require_once __DIR__ . '/../src/SmsService.php';

$action  = $_GET['action'] ?? '';
$manager = new TransferManager(new SmsService());

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

try {
    switch ($action) {

        case 'create': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (empty($data['product_id'])) jsonError("Field product_id is required");
            if (empty($data['quantity']))   jsonError("Field quantity is required");
            if (empty($data['date']))       jsonError("Field date is required");
            $id = $manager->transfer(
                (int)$data['product_id'],
                (int)$data['quantity'],
                $data['date']
            );
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        }

        case 'list': {
            echo json_encode($manager->listTransfers());
            break;
        }

        default:
            jsonError("Unknown action", 400);
    }
} catch (InvalidArgumentException $e) {
    jsonError($e->getMessage(), 400);
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError("Internal server error", 500);
}
