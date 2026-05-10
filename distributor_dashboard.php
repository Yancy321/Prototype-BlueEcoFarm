<?php

require_once 'src/AuthManager.php';

AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentPage = 'dashboard';

/* =========================
    USER SESSION
========================= */

$userId = $_SESSION['user']['id'] ?? 0;
$dname = $_SESSION['user']['full_name'] ?? 'Distributor';

$distRow = $conn->query("SELECT * FROM distributors WHERE user_id = $userId")->fetch_assoc();
$distributorId = $distRow['id'] ?? 1;
$businessName  = $distRow['business_name'] ?? $dname;
$tier          = $distRow['tier'] ?? 'Silver';
$region        = $distRow['region'] ?? '';

/* =========================
    STATS — using real tables
========================= */

// Total advance orders (excluding cancelled)
$totalOrders = $conn->query("
    SELECT COUNT(*) as t FROM advance_orders WHERE distributor_id = $distributorId AND status != 'Cancelled'
")->fetch_assoc()['t'] ?? 0;

// Pending advance orders
$pendingOrders = $conn->query("
    SELECT COUNT(*) as t FROM advance_orders WHERE distributor_id = $distributorId AND status = 'Pending'
")->fetch_assoc()['t'] ?? 0;

// Fulfilled advance orders
$fulfilledOrders = $conn->query("
    SELECT COUNT(*) as t FROM advance_orders WHERE distributor_id = $distributorId AND status = 'Fulfilled'
")->fetch_assoc()['t'] ?? 0;

// Waitlist entries
$waitlistCount = $conn->query("
    SELECT COUNT(*) as t FROM stock_waitlist WHERE distributor_id = $distributorId
")->fetch_assoc()['t'] ?? 0;

// Unnotified waitlist (pending notification)
$unnotifiedWaitlist = $conn->query("
    SELECT COUNT(*) as t FROM stock_waitlist WHERE distributor_id = $distributorId AND is_notified = 0
")->fetch_assoc()['t'] ?? 0;

/* =========================
    RECENT ADVANCE ORDERS
========================= */
$recentOrders = $conn->query("
    SELECT ao.id, ao.quantity, ao.order_date, ao.target_delivery_date, ao.status,
           p.name as product_name, p.pack_size, p.form
    FROM advance_orders ao
    LEFT JOIN products p ON ao.product_id = p.id
    WHERE ao.distributor_id = $distributorId
    ORDER BY ao.order_date DESC
    LIMIT 5
");

/* =========================
    WAITLIST
========================= */
$waitlistItems = $conn->query("
    SELECT sw.id, sw.requested_at, sw.is_notified,
           p.name as product_name, p.pack_size, p.form
    FROM stock_waitlist sw
    LEFT JOIN products p ON sw.product_id = p.id
    WHERE sw.distributor_id = $distributorId
    ORDER BY sw.requested_at DESC
    LIMIT 5
");

/* =========================
    LIVE AVAILABLE PRODUCTS
========================= */
$liveProducts = $conn->query("
    SELECT p.id, p.name, p.pack_size, p.form,
        SUM(CASE WHEN sr.record_type='incoming' THEN sr.quantity ELSE 0 END) -
        SUM(CASE WHEN sr.record_type='outgoing'  THEN sr.quantity ELSE 0 END) AS available_stock
    FROM products p
    LEFT JOIN stock_records sr ON p.id = sr.product_id AND sr.is_deleted = 0
    GROUP BY p.id
    ORDER BY p.pack_size DESC, p.form ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Partner Portal | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<style>
:root {
    --green-dark:  #1e3a1a;
    --green-mid:   #2d5a27;
    --green-light: #4a8c42;
    --green-tint:  #f4faf2;
    --accent:      #6abf5e;
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
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--green-tint);
    color: var(--text-main);
    display: flex;
    min-height: 100vh;
}

/* ── MAIN CONTENT ── */
.main {
    margin-left: var(--sidebar-w);
    flex: 1;
    padding: 40px 44px;
}

/* ── PAGE HEADER ── */
.page-header { margin-bottom: 32px; }
.page-header h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.75rem;
    color: var(--text-main);
    margin-bottom: 4px;
}
.page-header p { color: var(--text-muted); font-size: .9rem; }

/* ── STAT CARDS ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}
.stat-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: 22px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    transition: box-shadow .2s, transform .2s;
}
.stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.stat-icon {
    width: 40px; height: 40px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 14px;
}
.stat-value {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    color: var(--text-main);
    line-height: 1;
    margin-bottom: 5px;
}
.stat-label { font-size: .82rem; font-weight: 600; color: var(--text-main); margin-bottom: 3px; }
.stat-sub   { font-size: .78rem; color: var(--text-muted); }

/* ── SECTION TITLE ── */
.section-title {
    font-family: 'DM Serif Display', serif;
    font-size: 1.15rem;
    color: var(--text-main);
    margin-bottom: 16px;
}

/* ── QUICK ACTIONS ── */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}
.action-card {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    transition: box-shadow .2s, transform .2s;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
}
.action-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.action-card .arrow {
    position: absolute; top: 18px; right: 18px;
    color: var(--text-light); font-size: .85rem; text-decoration: none;
}
.action-icon {
    width: 42px; height: 42px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.action-title { font-size: .93rem; font-weight: 700; color: var(--text-main); }
.action-desc  { font-size: .8rem; color: var(--text-muted); line-height: 1.45; margin-bottom: 10px; }
.btn-go {
    display: inline-block;
    padding: 8px 18px;
    background: var(--green-mid);
    color: #fff;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 600;
    text-decoration: none;
    width: fit-content;
    transition: background .2s;
}
.btn-go:hover { background: var(--green-dark); }

/* ── TWO COLUMN ── */
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}

/* ── PANEL ── */
.panel {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.panel-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.panel-header h4 { font-size: .95rem; font-weight: 700; }
.panel-header a  { font-size: .8rem; color: var(--green-mid); text-decoration: none; font-weight: 600; }
.panel-header a:hover { text-decoration: underline; }

/* ── DATA TABLE ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    background: #f7fbf5;
    padding: 12px 22px;
    text-align: left;
    font-size: .78rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.data-table td { padding: 13px 22px; border-top: 1px solid var(--border); font-size: .87rem; }
.data-table tr:hover td { background: var(--green-tint); }

/* ── STATUS BADGES ── */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
.badge-Pending   { background: #fff7e6; color: #b45309; }
.badge-Approved  { background: #e0f2fe; color: #0369a1; }
.badge-Fulfilled { background: #e8f5e4; color: #2d5a27; }
.badge-Cancelled { background: #fee2e2; color: #991b1b; }

/* ── PRODUCT GRID ── */
.product-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    padding: 16px;
}
.product-item {
    background: var(--green-tint);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.product-item.low-stock { border-color: #fca5a5; background: #fff5f5; }
.product-name  { font-size: .87rem; font-weight: 600; color: var(--text-main); }
.product-meta  { font-size: .74rem; color: var(--text-muted); margin-top: 2px; text-transform: capitalize; }
.product-stock { font-family: 'DM Serif Display', serif; font-size: 1.25rem; color: var(--green-mid); text-align: right; }
.product-stock.zero { color: #dc2626; }
.product-stock-label { font-size: .7rem; color: var(--text-muted); text-align: right; }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 36px 20px; color: var(--text-muted); font-size: .88rem; }
.empty-icon  { font-size: 2rem; margin-bottom: 10px; }
</style>
</head>
<body>

<?php include 'includes/distributor_sidebar.php'; ?>

<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h2>Welcome back, <?= htmlspecialchars(explode(' ', $businessName)[0]) ?>!</h2>
        <p>Here's your partner overview for <?= date('F j, Y') ?>.</p>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f5e4;">📦</div>
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-sub">All advance orders placed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff7e6;">🕒</div>
            <div class="stat-value"><?= number_format($pendingOrders) ?></div>
            <div class="stat-label">Pending Orders</div>
            <div class="stat-sub">Awaiting approval</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2fe;">✅</div>
            <div class="stat-value"><?= number_format($fulfilledOrders) ?></div>
            <div class="stat-label">Fulfilled Orders</div>
            <div class="stat-sub">Successfully delivered</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fce7f3;">⏳</div>
            <div class="stat-value"><?= number_format($waitlistCount) ?></div>
            <div class="stat-label">Waitlist Items</div>
            <div class="stat-sub"><?= $unnotifiedWaitlist ?> pending notification</div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <h3 class="section-title">Quick Actions</h3>
    <div class="actions-grid">
        <div class="action-card" onclick="window.location='place_order.php'">
            <a href="place_order.php" class="arrow">❯</a>
            <div class="action-icon" style="background:#e8f5e4;">🛒</div>
            <div class="action-title">Place Advance Order</div>
            <div class="action-desc">Reserve products ahead of time with a target delivery date.</div>
            <a href="place_order.php" class="btn-go" onclick="event.stopPropagation()">Order Now</a>
        </div>
        <div class="action-card" onclick="window.location='waitlist.php'">
            <a href="waitlist.php" class="arrow">❯</a>
            <div class="action-icon" style="background:#fff7e6;">⏳</div>
            <div class="action-title">Join Waitlist</div>
            <div class="action-desc">Get notified when out-of-stock products become available.</div>
            <a href="waitlist.php" class="btn-go" onclick="event.stopPropagation()">View Waitlist</a>
        </div>
        <div class="action-card" onclick="window.location='my_orders.php'">
            <a href="my_orders.php" class="arrow">❯</a>
            <div class="action-icon" style="background:#e0f2fe;">📋</div>
            <div class="action-title">Track My Orders</div>
            <div class="action-desc">View status updates on all your advance orders.</div>
            <a href="my_orders.php" class="btn-go" onclick="event.stopPropagation()">View Orders</a>
        </div>
    </div>

    <!-- RECENT ORDERS + PRODUCT AVAILABILITY -->
    <div class="two-col">

        <!-- RECENT ADVANCE ORDERS -->
        <div class="panel">
            <div class="panel-header">
                <h4>Recent Advance Orders</h4>
                <a href="my_orders.php">View all →</a>
            </div>
            <?php
            $orderRows = [];
            if ($recentOrders) while ($r = $recentOrders->fetch_assoc()) $orderRows[] = $r;
            ?>
            <?php if ($orderRows): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Delivery</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orderRows as $o): ?>
                <tr>
                    <td><strong>#<?= str_pad($o['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($o['product_name']) ?>
                    </td>
                    <td><?= number_format($o['quantity']) ?></td>
                    <td><?= date('M j', strtotime($o['target_delivery_date'])) ?></td>
                    <td><span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No orders yet.<br>Place your first advance order!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- LIVE PRODUCT AVAILABILITY -->
        <div class="panel">
            <div class="panel-header">
                <h4>🟢 Product Availability</h4>
                <a href="place_order.php">Order →</a>
            </div>
            <?php
            $prodRows = [];
            if ($liveProducts) while ($r = $liveProducts->fetch_assoc()) $prodRows[] = $r;
            ?>
            <?php if ($prodRows): ?>
            <div class="product-grid">
                <?php foreach ($prodRows as $p):
                    $stock    = (int)$p['available_stock'];
                    $lowStock = $stock <= 10;
                ?>
                <div class="product-item <?= $lowStock ? 'low-stock' : '' ?>">
                    <div>
                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-meta"><?= $p['pack_size'] ?> · <?= $p['form'] ?></div>
                    </div>
                    <div>
                        <div class="product-stock <?= $stock <= 0 ? 'zero' : '' ?>">
                            <?= number_format($stock) ?>
                        </div>
                        <div class="product-stock-label">
                            <?= $stock <= 0 ? 'out of stock' : 'available' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🌿</div>
                <p>No products found.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- WAITLIST -->
    <h3 class="section-title">My Waitlist</h3>
    <div class="panel" style="margin-bottom:40px;">
        <div class="panel-header">
            <h4>Waitlist Entries</h4>
            <a href="waitlist.php">Manage →</a>
        </div>
        <?php
        $wlRows = [];
        if ($waitlistItems) while ($r = $waitlistItems->fetch_assoc()) $wlRows[] = $r;
        ?>
        <?php if ($wlRows): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Requested</th>
                    <th>Notified</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($wlRows as $w): ?>
            <tr>
                <td><strong><?= htmlspecialchars($w['product_name']) ?></strong></td>
                <td style="text-transform:capitalize;"><?= $w['pack_size'] ?> · <?= $w['form'] ?></td>
                <td><?= date('M j, Y', strtotime($w['requested_at'])) ?></td>
                <td>
                    <?php if ($w['is_notified']): ?>
                        <span class="badge badge-Fulfilled">Notified ✓</span>
                    <?php else: ?>
                        <span class="badge badge-Pending">Waiting</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">⏳</div>
            <p>You're not on any waitlists yet.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>