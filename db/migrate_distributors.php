<?php
/**
 * Migration: Add distributors and system_notifications tables.
 * Usage: php db/migrate_distributors.php
 */
$config = require __DIR__ . '/../config/db.php';
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$statements = [
    // Distributors table
    "CREATE TABLE IF NOT EXISTS distributors (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        phone      VARCHAR(20) NOT NULL,
        notes      TEXT NULL,
        is_active  TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // In-system low stock notifications
    "CREATE TABLE IF NOT EXISTS system_notifications (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        type        ENUM('low_stock') NOT NULL DEFAULT 'low_stock',
        product_id  INT NOT NULL,
        warehouse_id TINYINT NOT NULL,
        message     TEXT NOT NULL,
        is_read     TINYINT(1) NOT NULL DEFAULT 0,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",

    // Log of SMS sent to distributors
    "CREATE TABLE IF NOT EXISTS distributor_sms_log (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        distributor_id  INT NOT NULL,
        recipient       VARCHAR(20) NOT NULL,
        message         TEXT NOT NULL,
        status          ENUM('success','failure') NOT NULL,
        error_detail    TEXT,
        dispatched_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE
    )",
];

foreach ($statements as $sql) {
    $pdo->exec($sql);
}

echo "Migration complete: distributors and system_notifications tables created.\n";
