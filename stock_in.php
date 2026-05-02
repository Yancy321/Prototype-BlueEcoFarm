<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">Add Incoming Stock</h1>

<div class="card" style="max-width:520px;">
    <div id="alertBox"></div>
    <form id="stockInForm">
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
        <button type="submit" class="btn btn-primary">Add Stock</button>
    </form>
</div>

<script>
document.getElementById('stockInForm').addEventListener('submit', async function(e) {
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
    const res = await fetch('api/stock.php?action=add_incoming', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        alertBox.innerHTML = '<div class="alert alert-success">Stock added successfully (ID: ' + json.id + ')</div>';
        e.target.reset();
        document.getElementById('date').value = new Date().toISOString().split('T')[0];
    } else {
        alertBox.innerHTML = '<div class="alert alert-error">' + (json.error || 'Error') + '</div>';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
