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
            $stmt = $pdo->query("SELECT * FROM distributors ORDER BY name ASC");
            echo json_encode($stmt->fetchAll());
            break;
        }

        case 'create': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $name  = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');
            if ($name === '')  jsonError("Field name is required");
            if ($phone === '') jsonError("Field phone is required");

            // Normalize Philippine number
            $phone = normalizePhone($phone);

            $stmt = $pdo->prepare(
                "INSERT INTO distributors (name, phone, notes, is_active) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $phone, $data['notes'] ?? null, 1]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;
        }

        case 'update': {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id   = (int)($data['id'] ?? 0);
            if (!$id) jsonError("Field id is required");

            $fields = []; $params = [];
            if (isset($data['name'])      && $data['name'] !== '')  { $fields[] = 'name = ?';      $params[] = trim($data['name']); }
            if (isset($data['phone'])     && $data['phone'] !== '') { $fields[] = 'phone = ?';     $params[] = normalizePhone(trim($data['phone'])); }
            if (isset($data['notes']))                               { $fields[] = 'notes = ?';     $params[] = $data['notes']; }
            if (isset($data['is_active']))                           { $fields[] = 'is_active = ?'; $params[] = (int)(bool)$data['is_active']; }

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
