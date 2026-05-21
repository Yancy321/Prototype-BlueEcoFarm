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

        case 'list': {
            $page    = max(1, (int)($_GET['page']     ?? 1));
            $perPage = max(1, (int)($_GET['per_page'] ?? 20));
            $offset  = ($page - 1) * $perPage;

            // Total count
            $countStmt = $pdo->query("SELECT COUNT(*) FROM sms_alert_log");
            $total = (int) $countStmt->fetchColumn();

            // Paginated rows joined with alert_rules for product_id / warehouse_id context
            $stmt = $pdo->prepare(
                "SELECT
                    l.id,
                    l.alert_rule_id,
                    l.recipient,
                    l.message,
                    l.status,
                    l.error_detail,
                    l.dispatched_at,
                    ar.product_id,
                    ar.warehouse_id
                 FROM sms_alert_log l
                 JOIN alert_rules ar ON ar.id = l.alert_rule_id
                 ORDER BY l.dispatched_at DESC
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll();

            echo json_encode(['data' => $data, 'total' => $total]);
            break;
        }

        default:
            jsonError("Unknown action", 400);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError("Internal server error", 500);
}
