<?php
require_once 'src/AuthManager.php';
AuthManager::requireStaff();

require_once 'src/Database.php';
$pdo = Database::getInstance();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId   = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if ($orderId > 0 && in_array($newStatus, ['Approved', 'Fulfilled', 'Cancelled'])) {
        $stmt = $pdo->prepare("UPDATE advance_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        $success = "Order #" . str_pad($orderId, 4, '0', STR_PAD_LEFT) . " updated to {$newStatus}.";
    } else {
        $error = "Invalid action.";
    }
}

$statusFilter  = $_GET['status'] ?? 'all';
$validStatuses = ['Pending', 'Approved', 'Fulfilled', 'Cancelled'];
$whereStatus   = in_array($statusFilter, $validStatuses)
    ? "AND ao.status = " . $pdo->quote($statusFilter) : '';

$orders = $pdo->query("
    SELECT ao.id, ao.quantity, ao.order_date, ao.target_delivery_date, ao.status,
           p.name AS product_name, p.pack_size, p.form,
           COALESCE(d.business_name, d.name, u.full_name, 'Unknown') AS distributor_name,
           u.username
    FROM advance_orders ao
    LEFT JOIN products p     ON p.id = ao.product_id
    LEFT JOIN distributors d ON d.id = ao.distributor_id
    LEFT JOIN users u        ON u.id = d.user_id
    WHERE 1=1 {$whereStatus}
    ORDER BY FIELD(ao.status,'Pending','Approved','Fulfilled','Cancelled'), ao.order_date DESC
")->fetchAll();

$stats = [];
foreach (['Pending','Approved','Fulfilled','Cancelled'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM advance_orders WHERE status = ?");
    $stmt->execute([$s]);
    $stats[$s] = (int)$stmt->fetchColumn();
}
$pendingCount = $stats['Pending'];

require_once 'includes/header.php';
?>

<h1 class="page-title">
    Distributor Orders
    <?php if ($pendingCount > 0): ?>
        <span class="pending-badge"><?= $pendingCount ?> pending</span>
    <?php endif; ?>
</h1>

<div id="alertBox">
    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="filter-tabs">
    <?php
    $tabs = ['all' => 'All', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Fulfilled' => 'Fulfilled', 'Cancelled' => 'Cancelled'];
    foreach ($tabs as $val => $label):
        $cls = $statusFilter === $val ? 'btn-primary' : 'btn-secondary';
    ?>
        <a href="?status=<?= $val ?>" class="btn <?= $cls ?> btn-sm" style="text-decoration:none;">
            <?= $label ?>
            <?php if ($val !== 'all' && isset($stats[$val])): ?>
                <span style="opacity:0.75;">(<?= $stats[$val] ?>)</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Distributor</th><th>Product</th><th>Qty</th>
                    <th>Order Date</th><th>Target Delivery</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="empty-state">No orders found.</td></tr>
            <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong>#<?= str_pad($o['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($o['distributor_name']) ?></div>
                        <small class="cell-sub">@<?= htmlspecialchars($o['username'] ?? '—') ?></small>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($o['product_name']) ?></div>
                        <small class="cell-sub cell-capitalize"><?= $o['pack_size'] ?> · <?= $o['form'] ?></small>
                    </td>
                    <td><?= number_format($o['quantity']) ?></td>
                    <td><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
                    <td><?= date('M j, Y', strtotime($o['target_delivery_date'])) ?></td>
                    <td>
                        <span class="order-status-<?= $o['status'] ?>"><?= $o['status'] ?></span>
                    </td>
                    <td>
                        <div class="action-flex">
                        <?php if ($o['status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="status" value="Approved">
                                <button type="submit" class="btn btn-primary btn-sm"
                                    onclick="return confirm('Approve this order?')">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="status" value="Cancelled">
                                <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Cancel this order?')">Cancel</button>
                            </form>
                        <?php elseif ($o['status'] === 'Approved'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="status" value="Fulfilled">
                                <button type="submit" class="btn btn-primary btn-sm"
                                    onclick="return confirm('Mark as fulfilled?')">Fulfill</button>
                            </form>
                        <?php else: ?>
                            <span class="dash-none">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
