<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';

$action = $_GET['action'] ?? '';

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

function validateE164(string $phone): bool {
    return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
}

/**
 * Normalize a Philippine number to E.164 (+63xxxxxxxxx).
 * Accepts: 09xxxxxxxxx, 9xxxxxxxxx, 639xxxxxxxxx, +639xxxxxxxxx
 */
function normalizePhone(string $phone): string {
    $phone = preg_replace('/[\s\-()]/', '', $phone);
    // Already E.164
    if (preg_match('/^\+[1-9]\d{7,14}$/', $phone)) return $phone;
    // 09xxxxxxxxx (11 digits)
    if (preg_match('/^09\d{9}$/', $phone)) return '+63' . substr($phone, 1);
    // 9xxxxxxxxx (10 digits, no leading 0)
    if (preg_match('/^9\d{9}$/', $phone)) return '+63' . $phone;
    // 639xxxxxxxxx (12 digits, no +)
    if (preg_match('/^639\d{9}$/', $phone)) return '+' . $phone;
    return $phone; // return as-is, will fail E.164 check
}

function validateAlertRuleFields(array $data, bool $requireAll = true): void {
    if ($requireAll) {
        requireField($data['product_id'] ?? '', 'product_id');
        requireField($data['warehouse_id'] ?? '', 'warehouse_id');
        requireField($data['threshold'] ?? '', 'threshold');
        requireField($data['recipients'] ?? '', 'recipients');
    }

    if (isset($data['threshold']) && $data['threshold'] !== '') {
        if ((int)$data['threshold'] <= 0) {
            jsonError("Field threshold must be greater than 0");
        }
    }

    if (isset($data['warehouse_id']) && $data['warehouse_id'] !== '') {
        if (!in_array((int)$data['warehouse_id'], [1, 2], true)) {
            jsonError("Field warehouse_id must be 1 or 2");
        }
    }

    if (isset($data['product_id']) && $data['product_id'] !== '') {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([(int)$data['product_id']]);
        if (!$stmt->fetch()) {
            jsonError("Product {$data['product_id']} not found", 404);
        }
    }

    if (isset($data['recipients']) && $data['recipients'] !== '') {
        $recipients = is_array($data['recipients'])
            ? $data['recipients']
            : json_decode($data['recipients'], true);

        if (!is_array($recipients) || count($recipients) === 0) {
            jsonError("Field recipients must be a non-empty array of phone numbers");
        }

        foreach ($recipients as $phone) {
            $normalized = normalizePhone((string)$phone);
            if (!validateE164($normalized)) {
                jsonError("Invalid phone number format for {$phone}; expected E.164 (e.g. +639171234567)");
            }
        }
    }
}

try {
    $pdo = Database::getInstance();

    switch ($action) {

        case 'list': {
            $stmt = $pdo->query(
                "SELECT ar.*, p.name AS product_name
                 FROM alert_rules ar
                 JOIN products p ON p.id = ar.product_id
                 ORDER BY ar.id ASC"
            );
            $rows = $stmt->fetchAll();
            // Attach recipients from alert_recipients table
            foreach ($rows as &$row) {
                $rs = $pdo->prepare("SELECT id, phone, label FROM alert_recipients WHERE product_id = ?");
                $rs->execute([$row['product_id']]);
                $row['recipients'] = $rs->fetchAll();
            }
            unset($row);
            echo json_encode($rows);
            break;
        }

        case 'create': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['product_id'] ?? '', 'product_id');
            requireField($data['warehouse_id'] ?? '', 'warehouse_id');
            requireField($data['threshold'] ?? '', 'threshold');

            if (isset($data['warehouse_id']) && !in_array((int)$data['warehouse_id'], [1,2], true))
                jsonError("warehouse_id must be 1 or 2");
            if ((int)($data['threshold'] ?? 0) <= 0)
                jsonError("threshold must be greater than 0");

            $pdo2 = Database::getInstance();
            $chk = $pdo2->prepare("SELECT id FROM products WHERE id = ?");
            $chk->execute([(int)$data['product_id']]);
            if (!$chk->fetch()) jsonError("Product not found", 404);

            $cooldown = isset($data['cooldown_minutes']) && $data['cooldown_minutes'] !== ''
                ? (int)$data['cooldown_minutes'] : 60;
            $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

            $stmt = $pdo->prepare(
                "INSERT INTO alert_rules (product_id, warehouse_id, threshold, recipients, cooldown_minutes, is_active)
                 VALUES (?, ?, ?, '[]', ?, ?)"
            );
            $stmt->execute([
                (int)$data['product_id'],
                (int)$data['warehouse_id'],
                (int)$data['threshold'],
                $cooldown,
                $isActive,
            ]);
            $id = (int)$pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        }

        case 'update': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['id'] ?? '', 'id');
            $id = (int)$data['id'];
            $stmt = $pdo->prepare("SELECT id FROM alert_rules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonError("Alert rule {$id} not found", 404);

            $fields = []; $params = [];
            if (isset($data['product_id']) && $data['product_id'] !== '') {
                $fields[] = 'product_id = ?'; $params[] = (int)$data['product_id'];
            }
            if (isset($data['warehouse_id']) && $data['warehouse_id'] !== '') {
                if (!in_array((int)$data['warehouse_id'], [1,2], true)) jsonError("warehouse_id must be 1 or 2");
                $fields[] = 'warehouse_id = ?'; $params[] = (int)$data['warehouse_id'];
            }
            if (isset($data['threshold']) && $data['threshold'] !== '') {
                if ((int)$data['threshold'] <= 0) jsonError("threshold must be greater than 0");
                $fields[] = 'threshold = ?'; $params[] = (int)$data['threshold'];
            }
            if (isset($data['cooldown_minutes']) && $data['cooldown_minutes'] !== '') {
                $fields[] = 'cooldown_minutes = ?'; $params[] = (int)$data['cooldown_minutes'];
            }
            if (isset($data['is_active'])) {
                $fields[] = 'is_active = ?'; $params[] = (int)(bool)$data['is_active'];
            }
            if (empty($fields)) jsonError("No fields provided to update");
            $params[] = $id;
            $pdo->prepare("UPDATE alert_rules SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['id'] ?? '', 'id');
            $id = (int)$data['id'];
            $stmt = $pdo->prepare("SELECT id FROM alert_rules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonError("Alert rule {$id} not found", 404);
            $pdo->prepare("DELETE FROM alert_rules WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        }

        case 'list_recipients': {
            $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
            if (!$productId) jsonError("product_id is required");
            $stmt = $pdo->prepare("SELECT id, phone, label FROM alert_recipients WHERE product_id = ? ORDER BY id ASC");
            $stmt->execute([$productId]);
            echo json_encode($stmt->fetchAll());
            break;
        }

        case 'add_recipient': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['product_id'] ?? '', 'product_id');
            requireField($data['phone'] ?? '', 'phone');
            $phone = normalizePhone((string)$data['phone']);
            if (!validateE164($phone)) jsonError("Invalid phone number format; expected E.164 (e.g. +639171234567)");
            $stmt = $pdo->prepare("INSERT INTO alert_recipients (product_id, phone, label) VALUES (?, ?, ?)");
            $stmt->execute([(int)$data['product_id'], $phone, $data['label'] ?? null]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;
        }

        case 'delete_recipient': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            requireField($data['id'] ?? '', 'id');
            $pdo->prepare("DELETE FROM alert_recipients WHERE id = ?")->execute([(int)$data['id']]);
            echo json_encode(['success' => true]);
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
