<?php
session_start();

// Database Connection
$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Sidebar Logic: Check session to see which sidebar to load
// This ensures the page works for whoever is logged in.
$userRole = $_SESSION['role'] ?? ''; 
$currentPage = 'transfer'; 

// 2. Data Fetching
$products = $conn->query("SELECT id, name FROM products ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Transfer | Blue Eco Farm</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar_style.css">
    <style>
        /* DASHBOARD STYLING */
        :root{
            --primary-green:#2d5a27;
            --light-bg:#f8faf9;
            --border-color:#e5e7eb;
            --text-main:#1f2937;
            --text-muted:#6b7280;
        }

        *{ box-sizing:border-box; }

        body{
            margin:0;
            font-family:'Inter',sans-serif;
            background:var(--light-bg);
            color:var(--text-main);
            display:flex;
        }

        .main{
            margin-left:260px; /* Space for the sidebar */
            flex-grow:1;
            padding:48px;
        }

        header h2 { margin: 0; font-size: 1.8rem; }
        header p { color: var(--text-muted); margin: 5px 0 32px; }

        .card {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        /* FORM LAYOUT */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            align-items: flex-end;
        }

        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        /* TABLE - Matching Dashboard */
        .inventory-table {
            width:100%;
            background:#fff;
            border-radius:16px;
            border:1px solid var(--border-color);
            border-collapse:collapse;
            overflow:hidden;
        }

        .inventory-table th {
            background:#f9fafb;
            text-align:left;
            padding:16px;
            font-size:0.85rem;
            color:var(--text-muted);
        }

        .inventory-table td {
            padding:16px;
            border-top:1px solid var(--border-color);
        }

        .highlight-id { font-weight: 600; color: var(--primary-green); }
    </style>
</head>
<body>

<?php 
    // LOAD SIDEBAR BASED ON SESSION ROLE
    if ($userRole === 'admin') {
        include 'includes/sidebar.php'; 
    } else {
        include 'includes/staff_sidebar.php'; 
    }
?>

<div class="main">
    <header>
        <h2>Warehouse Transfers</h2>
        <p>Log inventory movement between Farm and Paranaque locations.</p>
    </header>

    <div class="card">
        <h3 style="margin-top:0; margin-bottom:20px;">Execute New Transfer</h3>
        <div id="alertBox"></div>
        
        <form id="transferForm" class="form-grid">
            <div class="form-group">
                <label>Product</label>
                <select name="product_id" id="product_id" required>
                    <option value="">— Select product —</option>
                    <?php while($p = $products->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Quantity (kg)</label>
                <input type="number" name="quantity" id="quantity" min="1" placeholder="0" required>
            </div>

            <div class="form-group">
                <label>Transfer Date</label>
                <input type="date" name="date" id="transfer_date" required>
            </div>

            <div style="grid-column: span 3; text-align: right; margin-top: 10px;">
                <button type="submit" class="btn-primary">Transfer Stock</button>
            </div>
        </form>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0;">Recent Transfer History</h3>
        <input type="text" id="searchInput" placeholder="Search by product..." 
               style="padding:10px; border-radius:8px; border:1px solid var(--border-color); width:250px;">
    </div>

    <table class="inventory-table">
        <thead>
            <tr>
                <th>Reference ID</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Date</th>
                <th>Source</th>
                <th>Destination</th>
            </tr>
        </thead>
        <tbody id="transferBody">
            <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--text-muted);">Loading history...</td></tr>
        </tbody>
    </table>
</div>

<script>
let allTransfers = [];
document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];

async function loadTransfers() {
    try {
        const res = await fetch('api/transfer.php?action=list');
        allTransfers = await res.json();
        renderTable(allTransfers);
    } catch (err) {
        document.getElementById('transferBody').innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Failed to load data.</td></tr>`;
    }
}

function renderTable(transfers) {
    const tbody = document.getElementById('transferBody');
    if (!transfers || transfers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;">No transfers found.</td></tr>`;
        return;
    }
    tbody.innerHTML = transfers.map(r => `
        <tr>
            <td><span class="highlight-id">TRF-${String(r.id).padStart(4, '0')}</span></td>
            <td><strong>${r.product_name}</strong></td>
            <td>${r.quantity} kg</td>
            <td>${new Date(r.transfer_date).toLocaleDateString()}</td>
            <td>Farm</td>
            <td>Paranaque</td>
        </tr>
    `).join('');
}

document.getElementById('searchInput').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    const filtered = allTransfers.filter(r => r.product_name.toLowerCase().includes(term));
    renderTable(filtered);
});

document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('alertBox');
    const data = {
        product_id: document.getElementById('product_id').value,
        quantity: document.getElementById('quantity').value,
        date: document.getElementById('transfer_date').value,
    };
    const res = await fetch('api/transfer.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        alertBox.innerHTML = '<div style="background:#e8f5e9; color:#2d5a27; padding:15px; border-radius:10px; margin-bottom:20px;">✓ Transfer successful</div>';
        this.reset();
        document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];
        loadTransfers();
    } else {
        alertBox.innerHTML = `<div style="background:#ffebee; color:#c62828; padding:15px; border-radius:10px; margin-bottom:20px;">${json.error}</div>`;
    }
});

loadTransfers();
</script>
</body>
</html>