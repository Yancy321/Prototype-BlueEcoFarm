<?php
require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$currentPage = 'place_order';

/* SESSION */
$userId        = $_SESSION['user']['id'] ?? 0;
$distRow       = $conn->query("SELECT * FROM distributors WHERE user_id = $userId")->fetch_assoc();
$distributorId = $distRow['id'] ?? 1;
$businessName  = $distRow['business_name'] ?? ($_SESSION['user']['full_name'] ?? 'Distributor');

/* HANDLE FORM SUBMISSION */
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId    = (int)($_POST['product_id'] ?? 0);
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $deliveryDate = $conn->real_escape_string($_POST['target_delivery_date'] ?? '');

    if (!$productId || $quantity <= 0 || !$deliveryDate) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($deliveryDate) <= time()) {
        $error = 'Delivery date must be in the future.';
    } else {
        // Check available stock
        $stockQuery = $conn->prepare("
            SELECT COALESCE(
                SUM(CASE WHEN record_type='incoming' THEN quantity ELSE 0 END) -
                SUM(CASE WHEN record_type='outgoing'  THEN quantity ELSE 0 END)
            , 0) AS available_stock
            FROM stock_records
            WHERE product_id = ? AND is_deleted = 0
        ");
        $stockQuery->bind_param("i", $productId);
        $stockQuery->execute();
        $stockResult = $stockQuery->get_result()->fetch_assoc();
        $availableStock = (int)($stockResult['available_stock'] ?? 0);
        $stockQuery->close();

        if ($quantity > $availableStock) {
            $error = "Cannot place order. Only {$availableStock} units are currently available.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO advance_orders (distributor_id, product_id, quantity, order_date, target_delivery_date, status)
                VALUES (?, ?, ?, CURDATE(), ?, 'Pending')
            ");
            $stmt->bind_param("iiis", $distributorId, $productId, $quantity, $deliveryDate);
            if ($stmt->execute()) {
                $success = 'Order #' . str_pad($stmt->insert_id, 4, '0', STR_PAD_LEFT) . ' placed successfully!';
            } else {
                $error = 'Failed to place order. Please try again.';
            }
            $stmt->close();
        }
    }
}

/* PRODUCTS WITH STOCK */
$products = $conn->query("
    SELECT p.id, p.name, p.pack_size, p.form,
        COALESCE(
            SUM(CASE WHEN sr.record_type='incoming' THEN sr.quantity ELSE 0 END) -
            SUM(CASE WHEN sr.record_type='outgoing'  THEN sr.quantity ELSE 0 END)
        , 0) AS available_stock
    FROM products p
    LEFT JOIN stock_records sr ON p.id = sr.product_id AND sr.is_deleted = 0
    GROUP BY p.id
    ORDER BY p.name ASC
");
$productRows = [];
while ($r = $products->fetch_assoc()) $productRows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Place Order | Blue Eco Farm</title>
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

.page-header { margin-bottom: 32px; }
.page-header h2 { font-family: 'DM Serif Display', serif; font-size: 1.75rem; color: var(--text-main); margin-bottom: 4px; }
.page-header p { color: var(--text-muted); font-size: .9rem; }

.order-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; align-items: start; }

.panel { background: var(--white); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
.panel-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.panel-header h4 { font-size: .95rem; font-weight: 700; }
.panel-body { padding: 24px; }

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: .82rem; font-weight: 600; color: var(--text-main); margin-bottom: 7px; text-transform: uppercase; letter-spacing: .4px; }
.form-label span { color: #dc2626; margin-left: 2px; }
.form-control { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--text-main); background: #fff; transition: border-color .2s, box-shadow .2s; outline: none; appearance: none; }
.form-control:focus { border-color: var(--green-light); box-shadow: 0 0 0 3px rgba(74,140,66,.12); }
select.form-control { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7c69' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
.form-hint { font-size: .78rem; color: var(--text-muted); margin-top: 5px; transition: color .2s; }

.qty-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.btn-submit { width: 100%; padding: 13px; background: var(--green-mid); color: #fff; border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 700; cursor: pointer; transition: background .2s, transform .1s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--green-dark); transform: translateY(-1px); }

.alert { padding: 14px 18px; border-radius: var(--radius-sm); font-size: .88rem; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e4; color: #2d5a27; border: 1px solid #b7ddb0; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.info-card { background: var(--green-tint); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 20px; font-size: .83rem; color: var(--text-muted); line-height: 1.6; }
.info-card strong { color: var(--text-main); }

.avail-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 24px; border-top: 1px solid var(--border); font-size: .86rem; transition: background .15s; }
.avail-item:first-child { border-top: none; }
.avail-item:hover { background: var(--green-tint); }
.avail-name { font-weight: 600; color: var(--text-main); }
.avail-meta { font-size: .75rem; color: var(--text-muted); text-transform: capitalize; margin-top: 1px; }
.avail-qty  { font-family: 'DM Serif Display', serif; font-size: 1.1rem; color: var(--green-mid); }
.avail-qty.zero { color: #dc2626; }
.stock-dot  { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
.dot-ok   { background: #4a8c42; }
.dot-low  { background: #f59e0b; }
.dot-zero { background: #dc2626; }
</style>
</head>
<body>
<?php include 'includes/distributor_sidebar.php'; ?>
<div class="main">
    <div class="page-header">
        <h2>Place Advance Order</h2>
        <p>Reserve products ahead of time. Orders are subject to admin approval.</p>
    </div>

    <div class="order-layout">

        <!-- FORM -->
        <div class="panel">
            <div class="panel-header">
                <span>🛒</span>
                <h4>New Advance Order</h4>
            </div>
            <div class="panel-body">
                <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="info-card">
                    <strong>How advance orders work:</strong><br>
                    Select a product, specify quantity and desired delivery date. Your order will be reviewed and confirmed by our team once submitted.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Product <span>*</span></label>
                        <select name="product_id" class="form-control" required id="productSelect" onchange="updateStockHint(this)">
                            <option value="">— Select a product —</option>
                            <?php foreach ($productRows as $p):
                                $stock = (int)$p['available_stock'];
                                $sel   = (isset($_POST['product_id']) && $_POST['product_id'] == $p['id']) ? 'selected' : '';
                            ?>
                            <option value="<?= $p['id'] ?>" data-stock="<?= $stock ?>" <?= $sel ?>>
                                <?= htmlspecialchars($p['name']) ?> (<?= $p['pack_size'] ?> · <?= $p['form'] ?>) — <?= $stock ?> available
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint" id="stockHint">Select a product to see current availability.</div>
                    </div>

                    <div class="qty-row">
                        <div class="form-group">
                            <label class="form-label">Quantity <span>*</span></label>
                            <input type="number" name="quantity" class="form-control" min="1"
                                placeholder="e.g. 100"
                                value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Delivery Date <span>*</span></label>
                            <input type="date" name="target_delivery_date" class="form-control"
                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                value="<?= htmlspecialchars($_POST['target_delivery_date'] ?? '') ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Submit Order</button>
                </form>
            </div>
        </div>

        <!-- AVAILABILITY SIDEBAR -->
        <div class="panel">
            <div class="panel-header">
                <span>🟢</span>
                <h4>Current Availability</h4>
            </div>
            <?php foreach ($productRows as $p):
                $stock = (int)$p['available_stock'];
                $dot   = $stock <= 0 ? 'dot-zero' : ($stock <= 10 ? 'dot-low' : 'dot-ok');
            ?>
            <div class="avail-item">
                <div>
                    <div class="avail-name"><span class="stock-dot <?= $dot ?>"></span><?= htmlspecialchars($p['name']) ?></div>
                    <div class="avail-meta"><?= $p['pack_size'] ?> · <?= $p['form'] ?></div>
                </div>
                <div class="avail-qty <?= $stock <= 0 ? 'zero' : '' ?>"><?= number_format($stock) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
<script>
function updateStockHint(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const hint = document.getElementById('stockHint');
    if (!opt.value) { hint.textContent = 'Select a product to see current availability.'; hint.style.color = ''; return; }
    const stock = parseInt(opt.dataset.stock, 10);
    if (stock <= 0) {
        hint.textContent = '⚠️ Out of stock. You can still place an advance order.';
        hint.style.color = '#dc2626';
    } else if (stock <= 10) {
        hint.textContent = `⚡ Low stock — only ${stock} units available.`;
        hint.style.color = '#b45309';
    } else {
        hint.textContent = `✅ ${stock} units currently available.`;
        hint.style.color = '#2d5a27';
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('productSelect');
    if (sel.value) updateStockHint(sel);
});
</script>
</body>
</html>