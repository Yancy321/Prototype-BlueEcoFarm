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
            // Return all distributors with a resolved display name and phone
            $stmt = $pdo->query("
                SELECT
                    d.id,
                    COALESCE(d.business_name, u.full_name, 'Unknown') AS name,
                    COALESCE(NULLIF(d.phone,''), d.contact_number)     AS phone,
                    d.notes,
                    d.is_active,
                    d.status,
                    d.tier,
                    d.region
                FROM distributors d
                LEFT JOIN users u ON u.id = d.user_id
                ORDER BY name ASC
            ");
            echo json_encode($stmt->fetchAll());
            break;
        }

        case 'create': {
            // Admin manually adds a distributor (no user account needed)
            $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $name  = trim($data['name']  ?? '');
            $phone = trim($data['phone'] ?? '');
            if ($name  === '') jsonError("Field name is required");
            if ($phone === '') jsonError("Field phone is required");

            $phone = normalizePhone($phone);

            // Insert with a placeholder user_id = 0 (no linked account)
            $stmt = $pdo->prepare(
                "INSERT INTO distributors (user_id, business_name, phone, contact_number, notes, is_active, status, tier)
                 VALUES (0, ?, ?, ?, ?, 1, 'approved', 'Silver')"
            );
            $stmt->execute([$name, $phone, $phone, $data['notes'] ?? null]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;
        }

        case 'update': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id   = (int)($data['id'] ?? 0);
            if (!$id) jsonError("Field id is required");

            $fields = []; $params = [];
            if (isset($data['name'])  && $data['name'] !== '')  {
                $fields[] = 'business_name = ?';
                $params[] = trim($data['name']);
            }
            if (isset($data['phone']) && $data['phone'] !== '') {
                $normalized = normalizePhone(trim($data['phone']));
                $fields[] = 'phone = ?';          $params[] = $normalized;
                $fields[] = 'contact_number = ?'; $params[] = $normalized;
            }
            if (isset($data['notes'])) {
                $fields[] = 'notes = ?';
                $params[] = $data['notes'];
            }
            if (isset($data['is_active'])) {
                $isActive = (int)(bool)$data['is_active'];
                $fields[] = 'is_active = ?';
                $params[] = $isActive;
                // Keep status in sync
                $fields[] = 'status = ?';
                $params[] = $isActive ? 'approved' : 'pending';
            }

            if (empty($fields)) jsonError("No fields to update");
            $params[] = $id;
            $pdo->prepare("UPDATE distributors SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id   = (int)($data['id'] ?? 0);
            if (!$id) jsonError("Field id is required");
            $pdo->prepare("DELETE FROM distributors WHERE id = ?")->execute([$id]);
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

function normalizePhone(string $phone): string {
    $phone = preg_replace('/[\s\-()]/', '', $phone);
    if (preg_match('/^\+[1-9]\d{7,14}$/', $phone)) return $phone;
    if (preg_match('/^09\d{9}$/', $phone))          return '+63' . substr($phone, 1);
    if (preg_match('/^9\d{9}$/', $phone))           return '+63' . $phone;
    if (preg_match('/^639\d{9}$/', $phone))         return '+' . $phone;
    return $phone;
}
