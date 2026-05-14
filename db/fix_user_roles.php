<?php
$config = require __DIR__ . '/../config/db.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Fix any remaining empty roles — default to staff
$pdo->exec("UPDATE users SET role = 'staff' WHERE role = '' OR role IS NULL");
echo "Fixed remaining empty roles to 'staff'.\n";

$users = $pdo->query("SELECT id, username, full_name, role FROM users ORDER BY id")->fetchAll();
echo "\nCurrent users:\n";
foreach ($users as $u) {
    echo "  ID:{$u['id']} | {$u['username']} | {$u['role']}\n";
}
