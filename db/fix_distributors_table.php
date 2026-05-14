<?php
/**
 * Fix distributors table to match full schema.
 * Usage: php db/fix_distributors_table.php
 */
$config = require __DIR__ . '/../config/db.php';
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$columns = [
    "user_id"        => "ALTER TABLE distributors ADD COLUMN user_id INT DEFAULT NULL AFTER id",
    "business_name"  => "ALTER TABLE distributors ADD COLUMN business_name VARCHAR(255) DEFAULT NULL AFTER user_id",
    "tier"           => "ALTER TABLE distributors ADD COLUMN tier ENUM('Silver','Gold','Platinum') DEFAULT 'Silver'",
    "status"         => "ALTER TABLE distributors ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'",
    "region"         => "ALTER TABLE distributors ADD COLUMN region VARCHAR(100) DEFAULT NULL",
    "contact_number" => "ALTER TABLE distributors ADD COLUMN contact_number VARCHAR(20) DEFAULT NULL",
];

// Get existing columns
$existing = $pdo->query("SHOW COLUMNS FROM distributors")->fetchAll(PDO::FETCH_COLUMN);

foreach ($columns as $col => $sql) {
    if (!in_array($col, $existing)) {
        $pdo->exec($sql);
        echo "Added column: $col\n";
    } else {
        echo "Column already exists: $col\n";
    }
}

// Add foreign key for user_id if not exists
try {
    $pdo->exec("ALTER TABLE distributors ADD CONSTRAINT fk_dist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "Added foreign key: user_id -> users.id\n";
} catch (Exception $e) {
    echo "Foreign key already exists or skipped.\n";
}

echo "\nDone. distributors table is now compatible.\n";
