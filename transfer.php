<?php
session_start();

require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   FIXED ROLE HANDLING
========================= */
$userRole = $_SESSION['user']['role'] ?? 'staff';
$currentPage = 'transfer';

/* =========================
   DATA
========================= */
$products = $conn->query("SELECT id, name FROM products ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Warehouse Transfers | Blue Eco Farm</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --green:#2d5a27;
    --bg:#f4faf2;
    --border:#e2ece0;
    --text:#1a2e18;
    --muted:#6b7c69;
    --sidebar-w:260px;
}

body {
    margin:0;
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
}

/* =========================
   LAYOUT SYSTEM (FIXED)
========================= */
.app-layout {
    display:flex;
}

/* MAIN CONTENT */
.main {
    margin-left:var(--sidebar-w);
    flex:1;
    padding:40px 44px;
}

/* HEADER */
.page-header h2 {
    margin:0;
    font-size:1.8rem;
}

.page-header p {
    color:var(--muted);
    margin-top:6px;
}

/* CARD */
.card {
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    padding:22px;
    margin-top:20px;
}

/* FORM */
.form-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
}

.form-group label {
    display:block;
    font-weight:600;
    margin-bottom:6px;
    font-size:.9rem;
}

input, select {
    width:100%;
    padding:10px;
    border:1px solid var(--border);
    border-radius:10px;
}

/* BUTTON */
.btn {
    background:var(--green);
    color:#fff;
    border:none;
    padding:12px 18px;
    border-radius:10px;
    font-weight:700;
    cursor:pointer;
}

/* TABLE */
.table {
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:16px;
    overflow:hidden;
    border:1px solid var(--border);
}

.table th {
    background:#f7fbf5;
    text-align:left;
    padding:14px;
    font-size:.8rem;
    color:var(--muted);
}

.table td {
    padding:14px;
    border-top:1px solid var(--border);
}

/* SEARCH */
.search {
    padding:10px;
    border:1px solid var(--border);
    border-radius:10px;
    width:250px;
}
</style>
</head>

<body>

<div class="app-layout">

    <!-- =========================
         FIXED ROLE SIDEBAR SWITCH
    ========================= -->
    <?php if ($userRole === 'admin'): ?>
        <?php include 'includes/header.php'; ?>
    <?php else: ?>
        <?php include 'includes/staff_sidebar.php'; ?>
    <?php endif; ?>

    <!-- MAIN CONTENT -->
    <div class="main">

        <div class="page-header">
            <h2>Warehouse Transfers</h2>
            <p>Move inventory between Farm and Paranaque locations.</p>
        </div>

        <!-- FORM -->
        <div class="card">

            <h3>Stock Transfer</h3>

            <div id="alertBox"></div>

            <form id="transferForm" class="form-grid">

                <div class="form-group">
                    <label>Product</label>
                    <select id="product_id" required>
                        <option value="">Select product</option>
                        <?php while($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
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

                <div style="grid-column:span 3; text-align:right;">
                    <button class="btn">Execute Transfer</button>
                </div>

            </form>
        </div>

        <!-- SEARCH + TABLE -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:25px;">
            <h3>Transfer History</h3>
            <input id="searchInput" class="search" placeholder="Search product...">
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Date</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>

            <tbody id="transferBody">
                <tr>
                    <td colspan="6" style="text-align:center;">Loading...</td>
                </tr>
            </tbody>
        </table>

    </div>
</div>

<script>
document.getElementById('transfer_date').value =
    new Date().toISOString().split('T')[0];

let allTransfers = [];

/* LOAD */
async function loadTransfers() {
    const res = await fetch('api/transfer.php?action=list');
    allTransfers = await res.json();
    render(allTransfers);
}

/* RENDER */
function render(data) {
    const tbody = document.getElementById('transferBody');

    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No records</td></tr>`;
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

/* SEARCH */
document.getElementById('searchInput').addEventListener('input', function() {
    const val = this.value.toLowerCase();
    const filtered = allTransfers.filter(r =>
        r.product_name.toLowerCase().includes(val)
    );
    render(filtered);
});

/* SUBMIT */
document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const data = {
        product_id: document.getElementById('product_id').value,
        quantity: document.getElementById('quantity').value,
        date: document.getElementById('transfer_date').value
    };

    const res = await fetch('api/transfer.php?action=create', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(data)
    });

    const json = await res.json();

    const alertBox = document.getElementById('alertBox');

    if (json.success) {
        alertBox.innerHTML = `<div style="color:green;margin:10px 0;">Transfer successful</div>`;
        this.reset();
        loadTransfers();
    } else {
        alertBox.innerHTML = `<div style="color:red;margin:10px 0;">${json.error}</div>`;
    }
});

loadTransfers();
</script>

</body>
</html>