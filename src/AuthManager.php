<?php

require_once __DIR__ . '/Database.php';

class AuthManager {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    /** Attempt login. Returns user row on success, null on failure. */
    public function login(string $username, string $password): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    /** Register a new user. Throws on duplicate username. */
    public function register(string $username, string $password, string $fullName, string $role = 'staff'): int {
        if (strlen($password) < 6) {
            throw new InvalidArgumentException("Password must be at least 6 characters.");
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([trim($username), $hash, trim($fullName), $role]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                throw new InvalidArgumentException("Username already exists.");
            }
            throw $e;
        }
    }

    /** List all users (without passwords). */
    public function listUsers(): array {
        return $this->pdo->query(
            "SELECT id, username, full_name, role, created_at FROM users ORDER BY id"
        )->fetchAll();
    }

    /** Delete a user by ID. */
    public function deleteUser(int $id): void {
        $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    }

    /** Start session and store user. */
    public static function startSession(array $user): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ];
    }

    /** Check if a user is logged in. Redirects to login if not. */
    public static function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user'])) {
            header('Location: login.php');
            exit;
        }
    }

    /** Check if logged-in user is admin. */
    public static function requireAdmin(): void {
        self::requireLogin();
        if ($_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            die('<p style="font-family:sans-serif;padding:2rem;">Access denied — admins only.</p>');
        }
    }

    public static function currentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user'] ?? null;
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
