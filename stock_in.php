<?php
require_once 'src/AuthManager.php';
AuthManager::requireLogin();

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentPage = 'stock';

// Fetch products for the dropdown
$products = $conn->query("SELECT id, name FROM products ORDER BY id");

// Fetch today's recent entries
$recentEntries = $conn->query("
    SELECT 
        sr.id, p.name, sr.quantity, sr.warehouse_id, sr.created_at 
    FROM stock_records sr 
    JOIN products p ON sr.product_id = p.id 
    WHERE sr.record_type='incoming' 
      AND sr.is_deleted = 0 
      AND DATE(sr.transaction_date) = CURDATE() 
    ORDER BY sr.created_at DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock In | Blue Eco Farm</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar_style.css">
    <style>
        /* USE THE EXACT DASHBOARD CSS */
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
            margin-left:260px;
            flex-grow:1;
            padding:48px;
        }

        /* PAGE HEADER */
        header h2 { margin: 0; font-size: 1.8rem; }
        header p { color: var(--text-muted); margin: 5px 0 25px; }

        /* TABS (Consistent with dashboard look) */
        .page-tabs { display: flex; gap: 10px; margin-bottom: 30px; }
        .page-tab { 
            padding: 10px 20px; 
            background: #fff; 
            border: 1px solid var(--border-color); 
            border-radius: 10px; 
            text-decoration: none; 
            color: var(--text-main); 
            font-weight: 600;
        }
        .page-tab.active { background: var(--primary-green); color: #fff; border-color: var(--primary-green); }

        /* GRID LAYOUT LIKE DASHBOARD CARDS */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }

        .card {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        /* FORM STYLING */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        .form-group select, .form-group input, .form-group textarea {
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
            width: 100%;
            transition: opacity 0.2s;
        }

        /* RECENT LIST STYLING */
        .recent-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recent-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

<?php include 'includes/staff_sidebar.php'; ?>

<div class="main">
    <header>
        <h2>Stock Management</h2>
        <p>Manage inventory intake and dispatch.</p>
    </header>

    <div class="page-tabs">
        <a href="stock_in.php" class="page-tab active">Stock In (Production)</a>
        <a href="stock_out.php" class="page-tab">Stock Out (Dispatch)</a>
    </div>

    <div class="content-grid">
        <div class="card">
            <h3 style="margin-top:0;">Record Stock In</h3>
            <p class="sub-label" style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Log new harvest or production into Farm inventory.</p>
            
            <div id="alertBox"></div>

            <form id="stockInForm">
                <div class="form-group">
                    <label>Product</label>
                    <select name="product_id" required>
                        <option value="">— Select product —</option>
                        <?php while($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Warehouse</label>
                    <select name="warehouse_id" required>
                        <option value="1">Farm</option>
                        <option value="2">Paranaque</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" required placeholder="0">
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Batch Number</label>
                    <input type="text" name="batch_number" placeholder="Optional batch ID">
                </div>

                <button class="btn-primary" type="submit">Add Stock Entry</button>
            </form>
        </div>

        <aside>
            <h3 style="margin: 0 0 15px 0;">Recent Entries (Today)</h3>
            <div class="card" style="padding: 0;">
                <?php if ($recentEntries->num_rows > 0): ?>
                    <?php while($entry = $recentEntries->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($entry['name']) ?></div>
                                <small style="color:var(--text-muted);"><?= $entry['warehouse_id'] == 1 ? 'Farm' : 'Paranaque' ?></small>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:700; color:var(--primary-green);">+<?= $entry['quantity'] ?></div>
                                <small style="color:var(--text-muted);"><?= date('g:i A', strtotime($entry['created_at'])) ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="padding:20px; color:var(--text-muted); text-align:center;">No entries recorded today.</div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<script>
document.getElementById('stockInForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const alertBox = document.getElementById('alertBox');

    // Convert FormData to JSON for the API
    const data = Object.fromEntries(form.entries());

    try {
        const res = await fetch('api/stock.php?action=add_incoming', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' }
        });

        const json = await res.json();

        if (json.success) {
            alertBox.innerHTML = "<div style='background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:10px; margin-bottom:20px;'>✓ Stock added successfully</div>";
            this.reset();
            setTimeout(() => location.reload(), 1000);
        } else {
            alertBox.innerHTML = "<div style='background:#ffebee; color:#c62828; padding:15px; border-radius:10px; margin-bottom:20px;'>Error: " + json.error + "</div>";
        }
    } catch (e) {
        alertBox.innerHTML = "<div style='background:#ffebee; color:#c62828; padding:15px; border-radius:10px; margin-bottom:20px;'>Connection error.</div>";
    }
});
</script>

</body>
</html>