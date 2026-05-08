<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
$recentEntries = $pdo->query("SELECT sr.id, p.name, sr.quantity, sr.warehouse_id, sr.transaction_date, sr.created_at FROM stock_records sr JOIN products p ON sr.product_id = p.id WHERE sr.record_type='outgoing' AND sr.is_deleted = 0 AND DATE(sr.transaction_date) = CURDATE() ORDER BY sr.created_at DESC LIMIT 5")->fetchAll();
require_once 'includes/header.php';
?>

<style>
    .page-header-panel { display:flex; flex-direction:column; gap:1rem; margin-bottom:1.75rem; }
    .page-header-panel h1 { margin:0; font-size:2rem; color:#1e4620; }
    .page-header-panel p { margin:0.35rem 0 0; color:#556f52; max-width:640px; }
    .page-tabs { display:inline-flex; gap:0.8rem; background:#fff; border:1px solid #dce7dd; border-radius:999px; padding:0.35rem; box-shadow:0 1px 6px rgba(18, 52, 28, 0.08); width:fit-content; }
    .page-tab { display:inline-flex; align-items:center; justify-content:center; padding:0.8rem 1.4rem; border-radius:999px; text-decoration:none; color:#4f7942; font-weight:600; border:1px solid transparent; transition:all .2s ease; }
    .page-tab.active { background:#1b5e20; color:#fff; border-color:#1b5e20; }
    .page-tab:hover { background:#edf6ee; }
    .analytics-card { background:#fff; border:1px solid #dce7dd; border-radius:24px; padding:1.5rem; box-shadow:0 12px 30px rgba(34, 60, 32, 0.08); max-width:1024px; }
    .analytics-card .card-header { display:flex; flex-direction:column; gap:1rem; margin-bottom:1.25rem; }
    .analytics-card .card-header h2 { margin:0; font-size:1.25rem; color:#1b5e20; }
    .analytics-card .card-header p { margin:0.35rem 0 0; color:#63795f; font-size:0.95rem; }
    .stock-grid { display:grid; grid-template-columns:1fr 340px; gap:1.5rem; }
    .stock-grid .stock-form { display:grid; gap:1.15rem; }
    .stock-grid .recent-panel { background:#f9fafb; border:1px solid #e5ece4; border-radius:20px; padding:1.25rem; }
    .recent-panel h3 { margin:0 0 0.35rem 0; font-size:1.05rem; color:#1b5e20; }
    .recent-panel p { margin:0 0 1rem; color:#5f7461; font-size:0.95rem; }
    .entry-item { display:flex; justify-content:space-between; gap:1rem; padding:1rem; border-radius:16px; background:#fff; border:1px solid #e7f1e9; margin-bottom:0.75rem; }
    .entry-item:last-child { margin-bottom:0; }
    .entry-meta { display:grid; gap:0.2rem; }
    .entry-meta .entry-name { font-weight:700; color:#1f4721; }
    .entry-meta .entry-sub { font-size:0.88rem; color:#596f5c; }
    .entry-right { text-align:right; display:grid; gap:0.2rem; }
    .entry-qty { font-weight:700; color:#d32f2f; }
    .entry-date { font-size:0.82rem; color:#7a8b7a; }
    .form-group { display:flex; flex-direction:column; gap:0.5rem; }
    .form-group label { font-weight:600; color:#2f4f34; }
    .form-group input, .form-group select, .form-group textarea { padding:0.9rem 1rem; border:1px solid #cdd9d0; border-radius:12px; background:#fbfdf9; color:#21322a; font-size:0.95rem; }
    .form-group textarea { resize:vertical; min-height:88px; }
    .form-group .available-stock { font-size:0.9rem; color:#556f52; margin-top:0.35rem; font-weight:500; }
    .form-actions { display:flex; justify-content:flex-end; margin-top:0.85rem; }
    .btn-primary { background:#2e7d32; color:#fff; border:none; border-radius:12px; padding:0.95rem 1.35rem; font-weight:700; cursor:pointer; transition:background .2s ease; }
    .btn-primary:hover { background:#256122; }
    .alert-box { margin-bottom:1rem; }
    .alert-success, .alert-error { padding:0.95rem 1rem; border-radius:12px; font-weight:600; }
    .alert-success { background:#e8f5e9; color:#1e4620; border:1px solid #c8e6c9; }
    .alert-error { background:#fce8e6; color:#7f1d1d; border:1px solid #f5c2c7; }
    @media (max-width: 920px) { .stock-grid { grid-template-columns:1fr; } }
</style>

<div class="page-header-panel">
    <div>
        <h1>Stock Management</h1>
        <p>Manage inventory intake and dispatch.</p>
    </div>
    <div class="page-tabs">
        <a href="stock_in.php" class="page-tab">Stock In (Production)</a>
        <a href="stock_out.php" class="page-tab active">Stock Out (Dispatch)</a>
    </div>
</div>

<div class="analytics-card">
    <div class="card-header">
        <div>
            <h2>Record Stock Out</h2>
            <p>Log outgoing dispatch from warehouse inventory.</p>
        </div>
    </div>

    <div id="alertBox" class="alert-box"></div>

    <div class="stock-grid">
        <div class="stock-form">
            <form id="stockOutForm" class="form-layout">
                <div class="form-group">
                    <label for="product_id">Product</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">— Select product —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="availableStock" class="available-stock"></div>
                </div>
                <div class="form-group">
                    <label for="warehouse_id">Warehouse</label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="">— Select warehouse —</option>
                        <option value="1">Farm</option>
                        <option value="2">Paranaque</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="batch_number">Batch Number (optional)</label>
                    <input type="text" id="batch_number" name="batch_number" placeholder="e.g. BATCH-2026-001">
                </div>
                <div class="form-group">
                    <label for="notes">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Record Outgoing</button>
                </div>
            </form>
        </div>

        <aside class="recent-panel">
            <h3>Recent Entries (Today)</h3>
            <p>Latest outgoing dispatch records for the current day.</p>
            <?php if (!empty($recentEntries)): ?>
                <?php foreach ($recentEntries as $entry): ?>
                    <div class="entry-item">
                        <div class="entry-meta">
                            <div class="entry-name"><?= htmlspecialchars($entry['name']) ?></div>
                            <div class="entry-sub"><?= $entry['warehouse_id'] === 1 ? 'Farm' : 'Paranaque' ?> • <?= date('g:i A', strtotime($entry['created_at'])) ?></div>
                        </div>
                        <div class="entry-right">
                            <div class="entry-qty">-<?= number_format($entry['quantity']) ?> kg</div>
                            <div class="entry-date"><?= date('M j', strtotime($entry['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="entry-item" style="justify-content:center; color:#63795f; background:#f4fbf4; border-color:#dbebeda;">
                    No dispatches recorded today.
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script>
async function updateAvailableStock() {
    const pid = document.getElementById('product_id').value;
    const wid = document.getElementById('warehouse_id').value;
    const box = document.getElementById('availableStock');
    if (!pid || !wid) { box.textContent = ''; return; }
    const res = await fetch(`api/stock.php?action=get_totals&warehouse_id=${wid}`).then(r => r.json());
    const row = res.find(r => r.id == pid && r.warehouse_id == wid);
    const qty = row ? (parseInt(row.current_stock) || 0) : 0;
    box.textContent = `Available stock: ${qty} kg`;
}

document.getElementById('product_id').addEventListener('change', updateAvailableStock);
document.getElementById('warehouse_id').addEventListener('change', updateAvailableStock);

document.getElementById('stockOutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('alertBox');
    const data = {
        product_id:   document.getElementById('product_id').value,
        warehouse_id: document.getElementById('warehouse_id').value,
        quantity:     document.getElementById('quantity').value,
        date:         document.getElementById('date').value,
        batch_number: document.getElementById('batch_number').value,
        notes:        document.getElementById('notes').value,
    };
    const res = await fetch('api/stock.php?action=add_outgoing', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        alertBox.innerHTML = '<div class="alert alert-success">Outgoing stock recorded (ID: ' + json.id + ')</div>';
        e.target.reset();
        document.getElementById('date').value = new Date().toISOString().split('T')[0];
        document.getElementById('availableStock').textContent = '';
    } else {
        alertBox.innerHTML = '<div class="alert alert-error">' + (json.error || 'Error') + '</div>';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
