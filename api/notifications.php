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
            $stmt = $pdo->query(
                "SELECT n.*, p.name AS product_name
                 FROM system_notifications n
                 JOIN products p ON p.id = n.product_id
                 ORDER BY n.created_at DESC
                 LIMIT 50"
            );
            echo json_encode($stmt->fetchAll());
            break;
        }

        case 'unread_count': {
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_notifications WHERE is_read = 0");
            echo json_encode(['count' => (int)$stmt->fetchColumn()]);
            break;
        }

        case 'mark_read': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id   = isset($data['id']) ? (int)$data['id'] : null;
            if ($id) {
                $pdo->prepare("UPDATE system_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
            } else {
                $pdo->exec("UPDATE system_notifications SET is_read = 1");
            }
            echo json_encode(['success' => true]);
            break;
        }

        default:
            jsonError("Unknown action", 400);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError("Internal server error", 500);
}
