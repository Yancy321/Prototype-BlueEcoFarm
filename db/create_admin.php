<?php
/**
 * Run once to create the first admin account.
 * Usage: php db/create_admin.php
 */
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthManager.php';

$auth = new AuthManager();
try {
    $id = $auth->register('admin', 'Admin@2024', 'System Administrator', 'admin');
    echo "Admin account created (ID: {$id})\n";
    echo "Username: admin\n";
    echo "Password: Admin@2024\n";
    echo "Change the password after first login via Users page.\n";
} catch (InvalidArgumentException $e) {
    echo "Note: " . $e->getMessage() . "\n";
}
