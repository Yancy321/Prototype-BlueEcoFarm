<?php
require_once __DIR__ . '/../src/Database.php';
$pdo = Database::getInstance();
echo "<pre style='font-family:monospace;font-size:13px;'>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS forecast_logs (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        product_id      INT NOT NULL,
        periods         INT NOT NULL,
        predicted_qty   DECIMAL(10,2) NOT NULL,
        data_points     INT NOT NULL,
        forecast_date   DATE NOT NULL,
        generated_by    INT NULL,
        generated_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id)   REFERENCES products(id),
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "OK: forecast_logs table created.\n";
} catch (PDOException $e) {
    echo "NOTE: " . $e->getMessage() . "\n";
}

echo "\nDone. Delete this file.\n</pre>";
