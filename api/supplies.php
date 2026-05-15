<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';

$action = $_GET['action'] ?? '';

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

try {
    $pdo = Database::getInstance();

    switch ($action) {

        case 'update_stock': {
            $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id    = (int)($data['id'] ?? 0);
            $stock = (int)($data['stock'] ?? -1);
            if (!$id)       jsonError("Field id is required");
            if ($stock < 0) jsonError("Stock cannot be negative");

            $stmt = $pdo->prepare("UPDATE inventory_supplies SET current_stock_level = ? WHERE id = ?");
            $stmt->execute([$stock, $id]);
            echo json_encode(['success' => true]);
            break;
        }

        case 'adjust_stock': {
            // Add or subtract from current stock
            $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id     = (int)($data['id'] ?? 0);
            $amount = (int)($data['amount'] ?? 0);
            $type   = $data['type'] ?? 'add'; // 'add' or 'subtract'
            if (!$id) jsonError("Field id is required");

            $stmt = $pdo->prepare("SELECT current_stock_level FROM inventory_supplies WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonError("Item not found", 404);

            $newStock = $type === 'subtract'
                ? max(0, $row['current_stock_level'] - $amount)
                : $row['current_stock_level'] + $amount;

            $pdo->prepare("UPDATE inventory_supplies SET current_stock_level = ? WHERE id = ?")->execute([$newStock, $id]);
            echo json_encode(['success' => true, 'new_stock' => $newStock]);
            break;
        }

        default:
            jsonError("Unknown action", 400);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError("Internal server error", 500);
}
