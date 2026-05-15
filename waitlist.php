<?php
require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$currentPage = 'waitlist';

/* SESSION */
$userId        = $_SESSION['user']['id'] ?? 0;
$distRow       = $conn->query("SELECT * FROM distributors WHERE user_id = $userId")->fetch_assoc();
$distributorId = $distRow['id'] ?? 1;
$businessName  = $distRow['business_name'] ?? ($_SESSION['user']['full_name'] ?? 'Distributor');
$tier          = $distRow['tier'] ?? 'Silver';

$success = $error = '';

/* HANDLE JOIN WAITLIST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_product_id'])) {
    $productId = (int)$_POST['join_product_id'];
    // Check if already on waitlist
    $existing = $conn->query("
        SELECT id FROM stock_waitlist WHERE distributor_id = $distributorId AND product_id = $productId
    ")->fetch_assoc();
    if ($existing) {
        $error = 'You are already on the waitlist for this product.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO stock_waitlist (distributor_id, product_id, requested_at, is_notified)
            VALUES (?, ?, NOW(), 0)
        ");
        $stmt->bind_param("ii", $distributorId, $productId);
        $stmt->execute() ? $success = 'You have been added to the waitlist!' : $error = 'Failed to join waitlist.';
        $stmt->close();
    }
}

/* HANDLE REMOVE FROM WAITLIST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $removeId = (int)$_POST['remove_id'];
    $conn->query("DELETE FROM stock_waitlist WHERE id = $removeId AND distributor_id = $distributorId");
    $success = 'Removed from waitlist.';
}

/* MY WAITLIST ENTRIES */
$myWaitlist = $conn->query("
    SELECT sw.id, sw.requested_at, sw.is_notified,
           p.id as product_id, p.name as product_name, p.pack_size, p.form,
           COALESCE(
               SUM(CASE WHEN sr.record_type='incoming' THEN sr.quantity ELSE 0 END) -
               SUM(CASE WHEN sr.record_type='outgoing'  THEN sr.quantity ELSE 0 END)
           , 0) AS available_stock
    FROM stock_waitlist sw
    LEFT JOIN products p ON sw.product_id = p.id
    LEFT JOIN stock_records sr ON p.id = sr.product_id AND sr.is_deleted = 0
    WHERE sw.distributor_id = $distributorId
    GROUP BY sw.id
    ORDER BY sw.requested_at DESC
");
$waitlistRows = [];
while ($r = $myWaitlist->fetch_assoc()) $waitlistRows[] = $r;

/* PRODUCTS NOT ON MY WAITLIST (to join) */
$myProductIds = array_column($waitlistRows, 'product_id');
$excludeIds   = $myProductIds ? implode(',', $myProductIds) : '0';

$availableToJoin = $conn->query("
    SELECT p.id, p.name, p.pack_size, p.form,
        COALESCE(
            SUM(CASE WHEN sr.record_type='incoming' THEN sr.quantity ELSE 0 END) -
            SUM(CASE WHEN sr.record_type='outgoing'  THEN sr.quantity ELSE 0 END)
        , 0) AS available_stock
    FROM products p
    LEFT JOIN stock_records sr ON p.id = sr.product_id AND sr.is_deleted = 0
    WHERE p.id NOT IN ($excludeIds)
    GROUP BY p.id
    ORDER BY available_stock ASC, p.name ASC
");
$joinRows = [];
while ($r = $availableToJoin->fetch_assoc()) $joinRows[] = $r;

/* STATS */
$totalWL       = count($waitlistRows);
$notifiedCount = count(array_filter($waitlistRows, fn($r) => $r['is_notified']));
$waitingCount  = $totalWL - $notifiedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Waitlist | Blue Eco Farm</title>
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
body { font-family: 'DM Sans', sans-serif; background: var(--green-tint); color: var(--text-main); display: flex; min-height: 100vh; }
.main { margin-left: var(--sidebar-w); flex: 1; padding: 40px 44px; }

.page-header { margin-bottom: 32px; }
.page-header h2 { font-family: 'DM Serif Display', serif; font-size: 1.75rem; color: var(--text-main); margin-bottom: 4px; }
.page-header p { color: var(--text-muted); font-size: .9rem; }

/* STAT CARDS */
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px; }
.stat-card { background: var(--white); border-radius: var(--radius); padding: 22px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: box-shadow .2s, transform .2s; }
.stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.stat-icon { width: 40px; height: 40px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
.stat-value { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--text-main); line-height: 1; margin-bottom: 5px; }
.stat-label { font-size: .82rem; font-weight: 600; color: var(--text-main); margin-bottom: 3px; }
.stat-sub   { font-size: .78rem; color: var(--text-muted); }

/* LAYOUT */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

/* PANEL */
.panel { background: var(--white); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
.panel-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.panel-header h4 { font-size: .95rem; font-weight: 700; }
.panel-header span { font-size: .82rem; color: var(--text-muted); }

/* TABLE */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: #f7fbf5; padding: 11px 20px; text-align: left; font-size: .78rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
.data-table td { padding: 13px 20px; border-top: 1px solid var(--border); font-size: .87rem; vertical-align: middle; }
.data-table tr:hover td { background: var(--green-tint); }

/* BADGE */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
.badge-Pending   { background: #fff7e6; color: #b45309; }
.badge-Fulfilled { background: #e8f5e4; color: #2d5a27; }

/* STOCK PILL */
.stock-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
.pill-ok   { background: #e8f5e4; color: #2d5a27; }
.pill-low  { background: #fff7e6; color: #b45309; }
.pill-zero { background: #fee2e2; color: #991b1b; }
.dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; display: inline-block; }

/* BUTTONS */
.btn-remove { padding: 5px 12px; background: transparent; border: 1px solid #fca5a5; color: #dc2626; border-radius: 7px; font-size: .78rem; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background .2s; }
.btn-remove:hover { background: #fee2e2; }
.btn-join { padding: 5px 14px; background: var(--green-mid); border: none; color: #fff; border-radius: 7px; font-size: .78rem; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background .2s; }
.btn-join:hover { background: var(--green-dark); }

/* PRODUCT JOIN CARD */
.join-item { display: flex; align-items: center; justify-content: space-between; padding: 13px 22px; border-top: 1px solid var(--border); transition: background .15s; }
.join-item:first-child { border-top: none; }
.join-item:hover { background: var(--green-tint); }
.join-name { font-weight: 600; font-size: .88rem; }
.join-meta { font-size: .76rem; color: var(--text-muted); text-transform: capitalize; margin-top: 2px; }
.join-right { display: flex; align-items: center; gap: 10px; }

/* ALERT */
.alert { padding: 13px 18px; border-radius: var(--radius-sm); font-size: .88rem; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e4; color: #2d5a27; border: 1px solid #b7ddb0; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* EMPTY */
.empty-state { text-align: center; padding: 48px 20px; color: var(--text-muted); font-size: .88rem; }
.empty-icon  { font-size: 2rem; margin-bottom: 10px; }

/* SECTION TITLE */
.section-title { font-family: 'DM Serif Display', serif; font-size: 1.1rem; color: var(--text-main); margin-bottom: 16px; margin-top: 32px; }
</style>
</head>
<body>
<?php include 'includes/distributor_sidebar.php'; ?>
<div class="main">

    <div class="page-header">
        <h2>My Waitlist</h2>
        <p>Get notified when out-of-stock products become available.</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fce7f3;"></div>
            <div class="stat-value"><?= $totalWL ?></div>
            <div class="stat-label">Total Waitlist Items</div>
            <div class="stat-sub">Products you're tracking</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff7e6;"></div>
            <div class="stat-value"><?= $waitingCount ?></div>
            <div class="stat-label">Awaiting Notification</div>
            <div class="stat-sub">Still out of stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f5e4;"></div>
            <div class="stat-value"><?= $notifiedCount ?></div>
            <div class="stat-label">Notified</div>
            <div class="stat-sub">Back in stock alerts sent</div>
        </div>
    </div>

    <div class="two-col">

        <!-- MY WAITLIST -->
        <div class="panel">
            <div class="panel-header">
                <h4>My Waitlist Entries</h4>
                <span><?= $totalWL ?> item<?= $totalWL !== 1 ? 's' : '' ?></span>
            </div>
            <?php if ($waitlistRows): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Stock</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($waitlistRows as $w):
                    $stock = (int)$w['available_stock'];
                    $pillClass = $stock <= 0 ? 'pill-zero' : ($stock <= 10 ? 'pill-low' : 'pill-ok');
                    $pillLabel = $stock <= 0 ? 'Out of stock' : ($stock <= 10 ? $stock . ' left' : $stock . ' avail.');
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($w['product_name']) ?></strong><br>
                        <span style="font-size:.76rem;color:var(--text-muted);text-transform:capitalize;"><?= $w['pack_size'] ?> · <?= $w['form'] ?></span>
                    </td>
                    <td>
                        <span class="stock-pill <?= $pillClass ?>">
                            <span class="dot"></span> <?= $pillLabel ?>
                        </span>
                    </td>
                    <td style="color:var(--text-muted);font-size:.83rem;">
                        <?= date('M j, Y', strtotime($w['requested_at'])) ?>
                    </td>
                    <td>
                        <?php if ($w['is_notified']): ?>
                            <span class="badge badge-Fulfilled">Notified ✓</span>
                        <?php else: ?>
                            <span class="badge badge-Pending">Waiting</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Remove this from your waitlist?')">
                            <input type="hidden" name="remove_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="btn-remove">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"></div>
                <p>You're not on any waitlists yet.<br>Join one from the list on the right.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- JOIN WAITLIST -->
        <div class="panel">
            <div class="panel-header">
                <h4>Join a Waitlist</h4>
                <span><?= count($joinRows) ?> product<?= count($joinRows) !== 1 ? 's' : '' ?></span>
            </div>
            <?php if ($joinRows): ?>
                <?php foreach ($joinRows as $p):
                    $stock = (int)$p['available_stock'];
                    $pillClass = $stock <= 0 ? 'pill-zero' : ($stock <= 10 ? 'pill-low' : 'pill-ok');
                    $pillLabel = $stock <= 0 ? 'Out of stock' : ($stock <= 10 ? $stock . ' left' : $stock . ' avail.');
                ?>
                <div class="join-item">
                    <div>
                        <div class="join-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="join-meta"><?= $p['pack_size'] ?> · <?= $p['form'] ?></div>
                    </div>
                    <div class="join-right">
                        <span class="stock-pill <?= $pillClass ?>">
                            <span class="dot"></span> <?= $pillLabel ?>
                        </span>
                        <form method="POST">
                            <input type="hidden" name="join_product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn-join">+ Join</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"></div>
                <p>You're already on the waitlist for all available products!</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</body>
</html>