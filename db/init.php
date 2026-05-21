<?php
/**
 * Run this once to create tables and seed products.
 * Usage: php db/init.php
 */
$config = require __DIR__ . '/../config/db.php';
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Run schema (split on semicolons, skip empty)
$sql = file_get_contents(__DIR__ . '/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}
echo "Schema created.\n";
echo "Products seeded.\n";
echo "\nDone. Now run: php db/create_admin.php\n";
