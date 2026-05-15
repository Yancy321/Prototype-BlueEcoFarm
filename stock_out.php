<?php
require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$currentPage = 'stock';

$products = $conn->query("SELECT id, name FROM products ORDER BY id");

$recentEntries = $conn->query("
    SELECT sr.id, p.name, sr.quantity, sr.warehouse_id, sr.created_at
    FROM stock_records sr
    JOIN products p ON sr.product_id = p.id
    WHERE sr.record_type='outgoing' AND sr.is_deleted = 0
      AND DATE(sr.transaction_date) = CURDATE()
    ORDER BY sr.created_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Out | Blue Eco Farm</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar_style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { margin:0; font-family:'Inter',sans-serif; background:#f8faf9; color:#1f2937; display:flex; }
        .main { margin-left:260px; flex-grow:1; padding:48px; }
        .page-tabs { display:flex; gap:10px; margin-bottom:30px; }
        .page-tab { padding:10px 20px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; text-decoration:none; color:#1f2937; font-weight:600; }
        .page-tab.active { background:#2d5a27; color:#fff; border-color:#2d5a27; }
        .content-grid { display:grid; grid-template-columns:1fr 350px; gap:24px; }
        .stock-card { background:#fff; padding:24px; border-radius:16px; border:1px solid #e5e7eb; }
        .available-stock { font-size:0.85rem; color:#2d5a27; margin-top:5px; font-weight:600; }
        .recent-item { padding:15px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .recent-item:last-child { border-bottom:none; }
    </style>
</head>
<body class="stock-page-body">

<?php include 'includes/staff_sidebar.php'; ?>

<div class="main">
    <header>
        <h2 style="margin:0;font-size:1.8rem;">Stock Management</h2>
        <p style="color:#6b7280;margin:5px 0 25px;">Manage inventory intake and dispatch.</p>
    </header>

    <div class="page-tabs">
        <a href="stock_in.php" class="page-tab">Stock In (Production)</a>
        <a href="stock_out.php" class="page-tab active">Stock Out (Dispatch)</a>
    </div>

    <div class="content-grid">
        <div class="stock-card">
            <h3 style="margin-top:0;">Record Stock Out</h3>
            <p style="color:#6b7280;font-size:0.9rem;margin-bottom:20px;">Log outgoing dispatch from warehouse inventory.</p>

            <div id="alertBox"></div>

            <form id="stockOutForm">
                <div class="form-group">
                    <label>Product</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">— Select product —</option>
                        <?php while ($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div id="availableStock" class="available-stock"></div>
                </div>
                <div class="form-group">
                    <label>Warehouse Source</label>
                    <select name="warehouse_id" id="warehouse_id" required>
                        <option value="">— Select warehouse —</option>
                        <option value="1">Farm</option>
                        <option value="2">Paranaque</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity to Dispatch</label>
                    <input type="number" name="quantity" id="quantity" min="1" required placeholder="0">
                </div>
                <div class="form-group">
                    <label>Dispatch Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Customer name or reason for dispatch..."></textarea>
                </div>
                <button class="btn btn-primary" style="width:100%;" type="submit">Record Outgoing</button>
            </form>
        </div>

        <aside>
            <h3 style="margin:0 0 15px 0;">Today's Dispatches</h3>
            <div class="stock-card" style="padding:0;">
                <?php if ($recentEntries->num_rows > 0): ?>
                    <?php while ($entry = $recentEntries->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($entry['name']) ?></div>
                                <small style="color:#6b7280;"><?= $entry['warehouse_id'] == 1 ? 'Farm' : 'Paranaque' ?></small>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:700;color:#d32f2f;">-<?= $entry['quantity'] ?></div>
                                <small style="color:#6b7280;"><?= date('g:i A', strtotime($entry['created_at'])) ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="padding:20px;color:#6b7280;text-align:center;">No dispatches today.</div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<script>
async function checkStock() {
    const pid = document.getElementById('product_id').value;
    const wid = document.getElementById('warehouse_id').value;
    const box = document.getElementById('availableStock');
    if (!pid || !wid) { box.textContent = ''; return; }
    try {
        const res = await fetch(`api/stock.php?action=get_totals&warehouse_id=${wid}`).then(r => r.json());
        const row = res.find(r => r.id == pid);
        const qty = row ? (parseInt(row.current_stock) || 0) : 0;
        box.textContent = `Current Stock in Warehouse: ${qty}`;
    } catch(e) { console.error(e); }
}
document.getElementById('product_id').addEventListener('change', checkStock);
document.getElementById('warehouse_id').addEventListener('change', checkStock);

document.getElementById('stockOutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('alertBox');
    const data = Object.fromEntries(new FormData(this).entries());
    try {
        const res  = await fetch('api/stock.php?action=add_outgoing', {
            method: 'POST', body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' }
        });
        const json = await res.json();
        if (json.success) {
            alertBox.innerHTML = '<div class="alert alert-success">✓ Dispatch recorded successfully</div>';
            this.reset();
            setTimeout(() => location.reload(), 1000);
        } else {
            alertBox.innerHTML = `<div class="alert alert-error">Error: ${json.error}</div>`;
        }
    } catch (e) {
        alertBox.innerHTML = '<div class="alert alert-error">Connection error.</div>';
    }
});
</script>
</body>
</html>
