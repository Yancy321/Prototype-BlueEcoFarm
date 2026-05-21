<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';

$pdo = Database::getInstance();
$warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;

$params = [];
$warehouseFilter = '';
if ($warehouseId) {
    $warehouseFilter = 'AND sr.warehouse_id = ?';
    $params[] = $warehouseId;
}

// Time-series: daily totals per product per type
$sql = "SELECT p.name AS product_name,
               sr.record_type,
               sr.transaction_date,
               SUM(sr.quantity) AS total_qty
        FROM stock_records sr
        JOIN products p ON p.id = sr.product_id
        WHERE sr.is_deleted = 0 {$warehouseFilter}
        GROUP BY p.name, sr.record_type, sr.transaction_date
        ORDER BY sr.transaction_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Organise into { product_name: { incoming: [{date,qty}], outgoing: [{date,qty}] } }
$result = [];
foreach ($rows as $row) {
    $name = $row['product_name'];
    $type = $row['record_type'];
    if (!isset($result[$name])) {
        $result[$name] = ['incoming' => [], 'outgoing' => []];
    }
    $result[$name][$type][] = [
        'date' => $row['transaction_date'],
        'qty'  => (int)$row['total_qty'],
    ];
}

echo json_encode($result);
