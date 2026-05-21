<?php
/**
 * SMS Debug Test — run once then delete.
 * http://localhost/blue-eco-farm/test_sms.php
 */
require_once 'src/Database.php';
require_once 'src/SmsService.php';

$pdo = Database::getInstance();
echo "<pre style='font-family:monospace;font-size:13px;'>";

// 1. Check alert_rules table
echo "=== ALERT RULES ===\n";
$rules = $pdo->query("SELECT * FROM alert_rules")->fetchAll();
if (empty($rules)) {
    echo "NO RULES FOUND in alert_rules table!\n";
} else {
    foreach ($rules as $r) {
        echo "ID:{$r['id']} product_id:{$r['product_id']} warehouse_id:{$r['warehouse_id']} threshold:{$r['threshold']} recipients:{$r['recipients']} active:{$r['is_active']}\n";
    }
}

// 2. Check current stock for product in rule
if (!empty($rules)) {
    $r = $rules[0];
    $pid = (int)$r['product_id'];
    $wid = (int)$r['warehouse_id'];
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN record_type='incoming' THEN quantity ELSE 0 END),0)
          - COALESCE(SUM(CASE WHEN record_type='outgoing' THEN quantity ELSE 0 END),0)
        AS stock
        FROM stock_records WHERE product_id=? AND warehouse_id=? AND is_deleted=0
    ");
    $stmt->execute([$pid, $wid]);
    $stock = (int)$stmt->fetchColumn();
    echo "\n=== CURRENT STOCK ===\n";
    echo "Product ID $pid at Warehouse $wid: $stock units\n";
    echo "Rule threshold: {$r['threshold']}\n";
    echo "Will trigger? " . ($stock <= (int)$r['threshold'] ? "YES" : "NO — stock ($stock) is above threshold ({$r['threshold']})") . "\n";
}

// 3. Test direct SMS send
echo "\n=== DIRECT SMS TEST ===\n";
$config = require 'config/integrations.php';
echo "Provider: {$config['sms']['provider']}\n";
echo "API Key: " . substr($config['sms']['api_key'], 0, 8) . "...\n";

$sms = new SmsService();
echo "\nSending test SMS...\n";
try {
    $result = $sms->send('+639151042742', '[Blue Eco Farm] Test SMS - system is working.');
    echo "Result: " . ($result ? "SUCCESS" : "FAILED (no exception)") . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

// 4. Check sms_alert_log
echo "\n=== SMS LOG ===\n";
$logs = $pdo->query("SELECT * FROM sms_alert_log ORDER BY id DESC LIMIT 5")->fetchAll();
if (empty($logs)) {
    echo "No log entries.\n";
} else {
    foreach ($logs as $l) {
        echo "ID:{$l['id']} rule:{$l['alert_rule_id']} to:{$l['recipient']} status:{$l['status']} error:" . ($l['error_detail'] ?? 'none') . "\n";
    }
}

echo "</pre>";
