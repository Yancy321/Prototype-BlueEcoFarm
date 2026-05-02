<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/InventoryManager.php';
require_once __DIR__ . '/../src/SmsService.php';

$action  = $_GET['action'] ?? '';
$manager = new InventoryManager(new SmsService());

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function requireField($value, string $name): void {
    if ($value === null || $value === '') {
        jsonError("Field {$name} is required");
    }
}

try {
    switch ($action) {

        case 'add_incoming': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['product_id'] ?? '', 'product_id');
            requireField($data['warehouse_id'] ?? '', 'warehouse_id');
            requireField($data['quantity'] ?? '', 'quantity');
            requireField($data['date'] ?? '', 'date');
            $id = $manager->addIncoming(
                (int)$data['product_id'],
                (int)$data['warehouse_id'],
                (int)$data['quantity'],
                $data['date'],
                $data['notes'] ?? '',
                $data['batch_number'] ?? ''
            );
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        }

        case 'add_outgoing': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['product_id'] ?? '', 'product_id');
            requireField($data['warehouse_id'] ?? '', 'warehouse_id');
            requireField($data['quantity'] ?? '', 'quantity');
            requireField($data['date'] ?? '', 'date');
            $id = $manager->addOutgoing(
                (int)$data['product_id'],
                (int)$data['warehouse_id'],
                (int)$data['quantity'],
                $data['date'],
                $data['notes'] ?? '',
                $data['batch_number'] ?? ''
            );
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        }

        case 'update': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['record_id'] ?? '', 'record_id');
            requireField($data['quantity'] ?? '', 'quantity');
            requireField($data['date'] ?? '', 'date');
            $manager->updateRecord(
                (int)$data['record_id'],
                (int)$data['quantity'],
                $data['date'],
                $data['notes'] ?? ''
            );
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['record_id'] ?? '', 'record_id');
            $manager->softDelete((int)$data['record_id']);
            echo json_encode(['success' => true]);
            break;
        }

        case 'get_totals': {
            $pdo = Database::getInstance();
            $warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;

            // Build stock per product per warehouse correctly:
            // 1. Direct stock records (incoming - outgoing) per warehouse
            // 2. Plus transfers received (destination) per warehouse
            // 3. Minus transfers sent (source) per warehouse
            // Use UNION to ensure warehouses that only have transfers (no direct records) still appear

            $warehouseFilter = $warehouseId ? "AND wh.warehouse_id = {$warehouseId}" : "";

            $sql = "
                SELECT p.id, p.name,
                    COALESCE(sr_in.qty, 0) - COALESCE(sr_out.qty, 0)
                    + COALESCE(tr_in.qty, 0) - COALESCE(tr_out.qty, 0) AS current_stock,
                    wh.warehouse_id
                FROM products p
                JOIN (
                    SELECT 1 AS warehouse_id UNION SELECT 2
                ) wh ON 1=1
                LEFT JOIN (
                    SELECT product_id, warehouse_id, SUM(quantity) AS qty
                    FROM stock_records WHERE record_type='incoming' AND is_deleted=0
                    GROUP BY product_id, warehouse_id
                ) sr_in ON sr_in.product_id = p.id AND sr_in.warehouse_id = wh.warehouse_id
                LEFT JOIN (
                    SELECT product_id, warehouse_id, SUM(quantity) AS qty
                    FROM stock_records WHERE record_type='outgoing' AND is_deleted=0
                    GROUP BY product_id, warehouse_id
                ) sr_out ON sr_out.product_id = p.id AND sr_out.warehouse_id = wh.warehouse_id
                LEFT JOIN (
                    SELECT product_id, destination_warehouse_id AS warehouse_id, SUM(quantity) AS qty
                    FROM transfers GROUP BY product_id, destination_warehouse_id
                ) tr_in ON tr_in.product_id = p.id AND tr_in.warehouse_id = wh.warehouse_id
                LEFT JOIN (
                    SELECT product_id, source_warehouse_id AS warehouse_id, SUM(quantity) AS qty
                    FROM transfers GROUP BY product_id, source_warehouse_id
                ) tr_out ON tr_out.product_id = p.id AND tr_out.warehouse_id = wh.warehouse_id
                WHERE 1=1 {$warehouseFilter}
                ORDER BY p.id, wh.warehouse_id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;
        }

        case 'get_record': {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) jsonError("Field id is required");
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT sr.*, p.name AS product_name
                 FROM stock_records sr
                 JOIN products p ON p.id = sr.product_id
                 WHERE sr.id = ? AND sr.is_deleted = 0"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonError("Stock record {$id} not found", 404);
            echo json_encode($row);
            break;
        }

        case 'search_batch': {
            $batch = trim($_GET['batch_number'] ?? '');
            if ($batch === '') jsonError("batch_number is required");
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT sr.*, p.name AS product_name, b.batch_number AS batch_no,
                        b.manufactured_date, b.notes AS batch_notes
                 FROM stock_records sr
                 JOIN products p ON p.id = sr.product_id
                 LEFT JOIN batches b ON b.id = sr.batch_id
                 WHERE (sr.batch_number = ? OR b.batch_number = ?) AND sr.is_deleted = 0
                 ORDER BY sr.transaction_date DESC, sr.id DESC"
            );
            $stmt->execute([$batch, $batch]);
            echo json_encode($stmt->fetchAll());
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
