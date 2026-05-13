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
$totalOrders = $stats['Pending'] + $stats['Approved'] + $stats['Fulfilled']; // Exclude cancelled orders

/* ORDERS — exact columns that exist in the table */
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
<style>
:root {
    --green-dark:  #1e3a1a;
    --green-mid:   #2d5a27;
    --green-light: #4a8c42;
    --green-tint:  #f4faf2;
    --white:       #ffffff;
    --border:      #e2ece0;
    --text-main:   #1a2e18;
    --text-muted:  #6b7c69;
    --text-light:  #9aab98;
    --sidebar-w:   260px;
    --shadow-sm:   0 2px 8px rgba(30,58,26,.07);
    --shadow-md:   0 4px 20px rgba(30,58,26,.10);
    --radius:      16px;
    --radius-sm:   10px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--green-tint); color: var(--text-main); display: flex; min-height: 100vh; }
.main { margin-left: var(--sidebar-w); flex: 1; padding: 40px 44px; }

.page-header { margin-bottom: 32px; display: flex; align-items: flex-start; justify-content: space-between; }
.page-header h2 { font-family: 'DM Serif Display', serif; font-size: 1.75rem; color: var(--text-main); margin-bottom: 4px; }
.page-header p { color: var(--text-muted); font-size: .9rem; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: var(--green-mid); color: #fff; border-radius: var(--radius-sm); font-size: .87rem; font-weight: 600; text-decoration: none; transition: background .2s; }
.btn-primary:hover { background: var(--green-dark); }

/* STAT CARDS */
.stat-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--white); border-radius: var(--radius); padding: 18px 20px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); cursor: pointer; transition: box-shadow .2s, transform .2s, border-color .2s; text-decoration: none; display: block; }
.stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.stat-card.active { border-color: var(--green-mid); box-shadow: 0 0 0 2px rgba(45,90,39,.15); }
.stat-value { font-family: 'DM Serif Display', serif; font-size: 1.8rem; color: var(--text-main); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: .78rem; font-weight: 600; color: var(--text-muted); }

/* PANEL */
.panel { background: var(--white); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
.panel-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.panel-header h4 { font-size: .95rem; font-weight: 700; }
.panel-header span { font-size: .83rem; color: var(--text-muted); }

/* TABLE */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: #f7fbf5; padding: 12px 20px; text-align: left; font-size: .78rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
.data-table td { padding: 14px 20px; border-top: 1px solid var(--border); font-size: .87rem; vertical-align: middle; }
.data-table tr:hover td { background: var(--green-tint); }

/* BADGE */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
.badge-Pending   { background: #fff7e6; color: #b45309; }
.badge-Approved  { background: #e0f2fe; color: #0369a1; }
.badge-Fulfilled { background: #e8f5e4; color: #2d5a27; }
.badge-Cancelled { background: #fee2e2; color: #991b1b; }

/* CANCEL BTN */
.btn-cancel { padding: 5px 12px; background: transparent; border: 1px solid #fca5a5; color: #dc2626; border-radius: 7px; font-size: .78rem; font-weight: 600; cursor: pointer; transition: background .2s; font-family: 'DM Sans', sans-serif; }
.btn-cancel:hover { background: #fee2e2; }

/* EMPTY */
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: .88rem; }
.empty-icon  { font-size: 2.5rem; margin-bottom: 12px; }
.empty-state a { color: var(--green-mid); font-weight: 600; text-decoration: none; }

/* ALERT */
.alert { padding: 13px 18px; border-radius: var(--radius-sm); font-size: .88rem; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e4; color: #2d5a27; border: 1px solid #b7ddb0; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.order-id { font-weight: 700; font-family: 'DM Serif Display', serif; }
</style>
</head>
<body>
<?php include 'includes/distributor_sidebar.php'; ?>
<div class="main">

    <div class="page-header">
        <div>
            <h2>My Orders</h2>
            <p>Track and manage all your advance orders.</p>
        </div>
        <a href="place_order.php" class="btn-primary">🛒 Place New Order</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <a href="my_orders.php" class="stat-card <?= $statusFilter === 'all' ? 'active' : '' ?>">
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-label">All Orders</div>
        </a>
        <a href="?status=Pending" class="stat-card <?= $statusFilter === 'Pending' ? 'active' : '' ?>">
            <div class="stat-value" style="color:#b45309"><?= $stats['Pending'] ?></div>
            <div class="stat-label">🕒 Pending</div>
        </a>
        <a href="?status=Approved" class="stat-card <?= $statusFilter === 'Approved' ? 'active' : '' ?>">
            <div class="stat-value" style="color:#0369a1"><?= $stats['Approved'] ?></div>
            <div class="stat-label">✔ Approved</div>
        </a>
        <a href="?status=Fulfilled" class="stat-card <?= $statusFilter === 'Fulfilled' ? 'active' : '' ?>">
            <div class="stat-value" style="color:#2d5a27"><?= $stats['Fulfilled'] ?></div>
            <div class="stat-label">✅ Delivered</div>
        </a>
        <a href="?status=Cancelled" class="stat-card <?= $statusFilter === 'Cancelled' ? 'active' : '' ?>">
            <div class="stat-value" style="color:#991b1b"><?= $stats['Cancelled'] ?></div>
            <div class="stat-label">✗ Cancelled</div>
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
                <td style="color:var(--text-muted);font-size:.83rem;text-transform:capitalize;">
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
            <div class="empty-icon">📋</div>
            <p>No <?= $statusFilter !== 'all' ? strtolower(htmlspecialchars($statusFilter)) . ' ' : '' ?>orders found.<br>
            <a href="place_order.php">Place your first advance order →</a></p>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>