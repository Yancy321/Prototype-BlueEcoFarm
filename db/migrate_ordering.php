<?php
/**
 * Migration: Create advance_orders, stock_waitlist, inventory_supplies tables.
 * Usage: php db/migrate_ordering.php
 */
$config = require __DIR__ . '/../config/db.php';
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$statements = [
    "CREATE TABLE IF NOT EXISTS advance_orders (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        distributor_id       INT DEFAULT NULL,
        product_id           INT DEFAULT NULL,
        quantity             INT NOT NULL,
        order_date           DATE NOT NULL,
        target_delivery_date DATE NOT NULL,
        status               ENUM('Pending','Approved','Fulfilled','Cancelled') DEFAULT 'Pending',
        FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE SET NULL,
        FOREIGN KEY (product_id)     REFERENCES products(id)     ON DELETE SET NULL
    )",

    "CREATE TABLE IF NOT EXISTS stock_waitlist (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        distributor_id INT DEFAULT NULL,
        product_id     INT DEFAULT NULL,
        is_notified    TINYINT(1) DEFAULT 0,
        requested_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id)     REFERENCES products(id)     ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS inventory_supplies (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        item_name           VARCHAR(255) NOT NULL,
        category            ENUM('Packaging Material','Production Essential') NOT NULL,
        current_stock_level INT DEFAULT 0,
        unit_of_measure     VARCHAR(50) DEFAULT NULL,
        reorder_point       INT DEFAULT 10
    )",

    "CREATE TABLE IF NOT EXISTS sales_records (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        product_id    INT DEFAULT NULL,
        quantity_sold INT DEFAULT NULL,
        sale_date     DATE DEFAULT NULL,
        total_price   DECIMAL(10,2) DEFAULT NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    )",
];

foreach ($statements as $sql) {
    $pdo->exec($sql);
    // Extract table name for feedback
    preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
    echo "Created table: " . ($m[1] ?? '?') . "\n";
}

echo "\nDone. Ordering system tables are ready.\n";
