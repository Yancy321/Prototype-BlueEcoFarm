<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">PDF Stock Movement Report</h1>

<div class="card" style="max-width:640px;">
    <h2>Generate Report</h2>
    <p style="font-size:0.9rem;color:#555;margin-bottom:1.25rem;">
        Select a date range and optional filters, then click "Generate Report" to download the PDF.
    </p>

    <form id="reportForm">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label>Date From <span style="color:#c62828;">*</span></label>
                <input type="date" id="dateFrom" name="date_from" required value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group">
                <label>Date To <span style="color:#c62828;">*</span></label>
                <input type="date" id="dateTo" name="date_to" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label>Warehouse (optional)</label>
                <select id="warehouseId" name="warehouse_id">
                    <option value="">All Warehouses</option>
                    <option value="1">Farm</option>
                    <option value="2">Paranaque</option>
                </select>
            </div>
            <div class="form-group">
                <label>Product (optional)</label>
                <select id="productId" name="product_id">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="formError" style="margin-bottom:0.75rem;"></div>

        <button type="submit" class="btn btn-primary" style="width:100%;padding:0.75rem;">
            📄 Generate Report
        </button>
    </form>
</div>

<script>
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo   = document.getElementById('dateTo').value;
    const errEl    = document.getElementById('formError');

    errEl.innerHTML = '';

    if (!dateFrom || !dateTo) {
        errEl.innerHTML = '<div class="alert alert-error">Both date fields are required.</div>';
        return;
    }

    if (dateFrom > dateTo) {
        errEl.innerHTML = '<div class="alert alert-error">Date From must not be after Date To.</div>';
        return;
    }

    const warehouseId = document.getElementById('warehouseId').value;
    const productId   = document.getElementById('productId').value;

    let url = `api/reports.php?action=generate&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}`;
    if (warehouseId) url += `&warehouse_id=${encodeURIComponent(warehouseId)}`;
    if (productId)   url += `&product_id=${encodeURIComponent(productId)}`;

    window.open(url, '_blank');
});
</script>

<?php require_once 'includes/footer.php'; ?>
