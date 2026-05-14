<?php
$config = require __DIR__ . '/../config/db.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['username'], $config['password']);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $tables) . "\n";
