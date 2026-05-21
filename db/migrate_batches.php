<?php
/**
 * Creates batches table and migrates batch_number from stock_records.
 * Run once: http://localhost/blue-eco-farm/db/migrate_batches.php
 */
require_once __DIR__ . '/../src/Database.php';
$pdo = Database::getInstance();
echo "<pre style='font-family:monospace;font-size:13px;'>";

// 1. Create batches table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS batches (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        batch_number      VARCHAR(50) NOT NULL UNIQUE,
        product_id        INT NOT NULL,
        manufactured_date DATE NULL,
        notes             TEXT NULL,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    echo "OK: batches table created.\n";
} catch (PDOException $e) { echo "NOTE: " . $e->getMessage() . "\n"; }

// 2. Add batch_id column to stock_records
try {
    $pdo->exec("ALTER TABLE stock_records ADD COLUMN batch_id INT NULL AFTER batch_number");
    echo "OK: batch_id column added to stock_records.\n";
} catch (PDOException $e) { echo "NOTE: " . $e->getMessage() . "\n"; }

// 3. Migrate existing batch_number values into batches table
$rows = $pdo->query("SELECT DISTINCT product_id, batch_number FROM stock_records WHERE batch_number IS NOT NULL AND batch_number != ''")->fetchAll();
$migrated = 0;
foreach ($rows as $row) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO batches (batch_number, product_id) VALUES (?, ?)");
        $stmt->execute([$row['batch_number'], $row['product_id']]);
        $migrated++;
    } catch (PDOException $e) {}
}
echo "OK: Migrated {$migrated} batch(es) to batches table.\n";

// 4. Update stock_records.batch_id from batches
$updated = $pdo->exec("
    UPDATE stock_records sr
    JOIN batches b ON b.batch_number = sr.batch_number
    SET sr.batch_id = b.id
    WHERE sr.batch_number IS NOT NULL
");
echo "OK: Updated {$updated} stock_records with batch_id.\n";

// 5. Add FK constraint
try {
    $pdo->exec("ALTER TABLE stock_records ADD CONSTRAINT fk_sr_batch FOREIGN KEY (batch_id) REFERENCES batches(id)");
    echo "OK: FK constraint added.\n";
} catch (PDOException $e) { echo "NOTE: " . $e->getMessage() . "\n"; }

echo "\nDone. Delete this file.\n</pre>";
