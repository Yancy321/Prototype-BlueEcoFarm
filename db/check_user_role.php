<?php
$config = require __DIR__ . '/../config/db.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['username'], $config['password']);
$users = $pdo->query("SELECT id, username, full_name, role FROM users ORDER BY id DESC LIMIT 20")->fetchAll();
foreach ($users as $u) {
    echo "ID:{$u['id']} | {$u['username']} | {$u['full_name']} | role:{$u['role']}\n";
}
