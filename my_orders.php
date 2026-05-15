<?php
require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$currentPage = 'my_orders';

/* SESSION */
$userId        = $_SESSION['user']['id'] ?? 0;
$distRow       = $conn->query("SELECT * FROM distributors WHERE user_id = $userId")->fetch_assoc();
$distributorId = $distRow['id'] ?? 1;
$businessName  = $distRow['business_name'] ?? ($_SESSION['user']['full_name'] ?? 'Distributor');

/* HANDLE CANCELLATION */
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $check = $conn->query("SELECT id, status FROM advance_orders WHERE id = $cancelId AND distributor_id = $distributorId")->fetch_assoc();
    if ($check && $check['status'] === 'Pending') {
        $conn->query("UPDATE advance_orders SET status = 'Cancelled' WHERE id = $cancelId");
        $success = 'Order #' . str_pad($cancelId, 4, '0', STR_PAD_LEFT) . ' has been cancelled.';
    } else {
        $error = 'Only pending orders can be cancelled.';
    }
}

/* FILTERS */
$statusFilter  = $_GET['status'] ?? 'all';
$validStatuses = ['Pending', 'Approved', 'Fulfilled', 'Cancelled'];
$whereStatus   = in_array($statusFilter, $validStatuses) ? "AND ao.status = '$statusFilter'" : '';

/* STATS */
$stats = [];
foreach (['Pending','Approved','Fulfilled','Cancelled'] as $s) {
    $stats[$s] = $conn->query("SELECT COUNT(*) as t FROM advance_orders WHERE distributor_id = $distributorId AND status = '$s'")->fetch_assoc()['t'] ?? 0;
}
$totalOrders = $stats['Pending'] + $stats['Approved'] + $stats['Fulfilled'];

/* ORDERS */
$orders = $conn->query("
    SELECT ao.id, ao.quantity, ao.order_date, ao.target_delivery_date, ao.status,
           p.name AS product_name, p.pack_size, p.form
    FROM advance_orders ao
    LEFT JOIN products p ON ao.product_id = p.id
    WHERE ao.distributor_id = $distributorId $whereStatus
    ORDER BY ao.order_date DESC
");
$orderRows = [];
while ($r = $orders->fetch_assoc()) $orderRows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/distributor_sidebar.php'; ?>
<div class="orders-main">

    <div class="page-header">
        <div>
            <h2>My Orders</h2>
            <p>Track and manage all your advance orders.</p>
        </div>
        <a href="place_order.php" class="btn-orders-primary">Place New Order</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <a href="my_orders.php" class="stat-card <?= $statusFilter === 'all' ? 'active' : '' ?>">
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-label">All Orders</div>
        </a>
        <a href="?status=Pending" class="stat-card <?= $statusFilter === 'Pending' ? 'active' : '' ?>">
            <div class="stat-value stat-value--pending"><?= $stats['Pending'] ?></div>
            <div class="stat-label">Pending</div>
        </a>
        <a href="?status=Approved" class="stat-card <?= $statusFilter === 'Approved' ? 'active' : '' ?>">
            <div class="stat-value stat-value--approved"><?= $stats['Approved'] ?></div>
            <div class="stat-label">Approved</div>
        </a>
        <a href="?status=Fulfilled" class="stat-card <?= $statusFilter === 'Fulfilled' ? 'active' : '' ?>">
            <div class="stat-value stat-value--fulfilled"><?= $stats['Fulfilled'] ?></div>
            <div class="stat-label">Delivered</div>
        </a>
        <a href="?status=Cancelled" class="stat-card <?= $statusFilter === 'Cancelled' ? 'active' : '' ?>">
            <div class="stat-value stat-value--cancelled"><?= $stats['Cancelled'] ?></div>
            <div class="stat-label">Cancelled</div>
        </a>
    </div>

    <!-- ORDERS TABLE -->
    <div class="panel">
        <div class="panel-header">
            <h4><?= $statusFilter === 'all' ? 'All Orders' : htmlspecialchars($statusFilter) . ' Orders' ?></h4>
            <span><?= count($orderRows) ?> record<?= count($orderRows) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if ($orderRows): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Pack / Form</th>
                    <th>Quantity</th>
                    <th>Order Date</th>
                    <th>Target Delivery</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orderRows as $o): ?>
            <tr>
                <td><span class="order-id">#<?= str_pad($o['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                <td><strong><?= htmlspecialchars($o['product_name']) ?></strong></td>
                <td class="cell-pack">
                    <?= htmlspecialchars($o['pack_size']) ?> · <?= htmlspecialchars($o['form']) ?>
                </td>
                <td><?= number_format($o['quantity']) ?></td>
                <td><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
                <td><?= date('M j, Y', strtotime($o['target_delivery_date'])) ?></td>
                <td><span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                <td>
                    <?php if ($o['status'] === 'Pending'): ?>
                    <form method="POST" onsubmit="return confirm('Cancel order #<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?>?')">
                        <input type="hidden" name="cancel_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="btn-cancel">Cancel</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <p>No <?= $statusFilter !== 'all' ? strtolower(htmlspecialchars($statusFilter)) . ' ' : '' ?>orders found.<br>
            <a href="place_order.php">Place your first advance order →</a></p>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
