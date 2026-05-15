<?php
session_start();
require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$userRole    = $_SESSION['user']['role'] ?? 'staff';
$currentPage = 'transfer';
$products    = $conn->query("SELECT id, name FROM products ORDER BY id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Warehouse Transfers | Blue Eco Farm</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if ($userRole === 'admin'): ?>
    <?php include 'includes/header.php'; ?>
<?php else: ?>
    <?php include 'includes/staff_sidebar.php'; ?>
    <div class="main-content" id="mainContent">
<?php endif; ?>

<h1 class="page-title">Warehouse Transfers</h1>
<p style="color:#6b7c69;margin-bottom:1.5rem;">Move inventory between Farm and Paranaque locations.</p>

<div class="card">
    <h2>Stock Transfer</h2>
    <div id="alertBox"></div>
    <form id="transferForm" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
        <div class="form-group">
            <label>Product</label>
            <select id="product_id" required>
                <option value="">Select product</option>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Quantity</label>
            <input type="number" id="quantity" min="1" required>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" id="transfer_date">
        </div>
        <div style="grid-column:span 3;text-align:right;">
            <button class="btn btn-primary">Execute Transfer</button>
        </div>
    </form>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin:1.5rem 0 0.75rem;">
    <h3>Transfer History</h3>
    <input id="searchInput" class="filter-bar" style="width:250px;padding:0.45rem 0.75rem;" placeholder="Search product...">
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr><th>ID</th><th>Product</th><th>Quantity</th><th>Date</th><th>From</th><th>To</th></tr>
        </thead>
        <tbody id="transferBody">
            <tr><td colspan="6" style="text-align:center;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<?php if ($userRole !== 'admin'): ?>
    </div><!-- close main-content -->
<?php endif; ?>

<script>
document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];
let allTransfers = [];

async function loadTransfers() {
    const res = await fetch('api/transfer.php?action=list');
    allTransfers = await res.json();
    render(allTransfers);
}

function render(data) {
    const tbody = document.getElementById('transferBody');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#aaa;">No records</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(r => `
        <tr>
            <td>TRF-${String(r.id).padStart(4,'0')}</td>
            <td>${r.product_name}</td>
            <td>${r.quantity} kg</td>
            <td>${new Date(r.transfer_date).toLocaleDateString()}</td>
            <td>Farm</td>
            <td>Paranaque</td>
        </tr>
    `).join('');
}

document.getElementById('searchInput').addEventListener('input', function() {
    const val = this.value.toLowerCase();
    render(allTransfers.filter(r => r.product_name.toLowerCase().includes(val)));
});

document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = {
        product_id: document.getElementById('product_id').value,
        quantity:   document.getElementById('quantity').value,
        date:       document.getElementById('transfer_date').value
    };
    const res  = await fetch('api/transfer.php?action=create', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const json = await res.json();
    const alertBox = document.getElementById('alertBox');
    if (json.success) {
        alertBox.innerHTML = '<div class="alert alert-success">Transfer successful</div>';
        this.reset();
        loadTransfers();
    } else {
        alertBox.innerHTML = `<div class="alert alert-error">${json.error}</div>`;
    }
});

loadTransfers();
</script>
</body>
</html>
