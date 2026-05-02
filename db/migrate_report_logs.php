<?php
require_once __DIR__ . '/../src/Database.php';
$pdo = Database::getInstance();
echo "<pre style='font-family:monospace;font-size:13px;'>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_logs (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        generated_by INT NULL,
        date_from    DATE NOT NULL,
        date_to      DATE NOT NULL,
        warehouse_id TINYINT NULL,
        product_id   INT NULL,
        filename     VARCHAR(150) NOT NULL,
        generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (product_id)   REFERENCES products(id) ON DELETE SET NULL
    )");
    echo "OK: report_logs table created.\n";
} catch (PDOException $e) {
    echo "NOTE: " . $e->getMessage() . "\n";
}

echo "\nDone. Delete this file.\n</pre>";
