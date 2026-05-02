<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">Transfer Stock: Farm → Paranaque</h1>

<div class="card" style="max-width:520px;">
    <div id="alertBox"></div>
    <div id="farmStock" style="margin-bottom:1rem;font-size:0.9rem;color:#555;"></div>
    <form id="transferForm">
        <div class="form-group">
            <label for="product_id">Product</label>
            <select id="product_id" name="product_id" required>
                <option value="">— Select product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="quantity">Quantity to Transfer</label>
            <input type="number" id="quantity" name="quantity" min="1" required>
        </div>
        <div class="form-group">
            <label for="date">Transfer Date</label>
            <input type="date" id="date" name="date" required value="<?= date('Y-m-d') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Transfer Stock</button>
    </form>
</div>

<div class="card" style="margin-top:1.5rem;">
    <h2>Recent Transfers</h2>
    <table id="transferTable">
        <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>Date</th><th>From</th><th>To</th></tr></thead>
        <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
    </table>
</div>

<script>
async function loadFarmStock() {
    const pid = document.getElementById('product_id').value;
    const box = document.getElementById('farmStock');
    if (!pid) { box.textContent = ''; return; }
    const res = await fetch(`api/stock.php?action=get_totals&warehouse_id=1`).then(r => r.json());
    const row = res.find(r => r.id == pid && r.warehouse_id == 1);
    const qty = row ? (parseInt(row.current_stock) || 0) : 0;
    box.textContent = `Farm available stock: ${qty}`;
}

async function loadTransfers() {
    const rows = await fetch('api/transfer.php?action=list').then(r => r.json());
    const tbody = document.querySelector('#transferTable tbody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6">No transfers yet.</td></tr>'; return; }
    tbody.innerHTML = rows.map(r =>
        `<tr><td>${r.id}</td><td>${r.product_name}</td><td>${r.quantity}</td><td>${r.transfer_date}</td>
         <td>Farm</td><td>Paranaque</td></tr>`
    ).join('');
}

document.getElementById('product_id').addEventListener('change', loadFarmStock);

document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('alertBox');
    const data = {
        product_id: document.getElementById('product_id').value,
        quantity:   document.getElementById('quantity').value,
        date:       document.getElementById('date').value,
    };
    const res = await fetch('api/transfer.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        alertBox.innerHTML = '<div class="alert alert-success">Transfer completed (ID: ' + json.id + ')</div>';
        e.target.reset();
        document.getElementById('date').value = new Date().toISOString().split('T')[0];
        document.getElementById('farmStock').textContent = '';
        loadTransfers();
    } else {
        alertBox.innerHTML = '<div class="alert alert-error">' + (json.error || 'Error') + '</div>';
    }
});

loadTransfers();
</script>

<?php require_once 'includes/footer.php'; ?>
