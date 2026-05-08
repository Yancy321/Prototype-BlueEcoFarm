<?php
$conn = new mysqli("localhost", "root", "", "blue_eco_farm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentPage = 'dashboard';

/* =========================
    DATA - ALL TIME
========================= */

// Total records in system
$totalAllTime = $conn->query("
    SELECT COUNT(*) as total 
    FROM stock_records 
    WHERE is_deleted = 0
")->fetch_assoc()['total'] ?? 0;

// Total transfers
$transfersCount = $conn->query("
    SELECT COUNT(*) as total 
    FROM transfers
")->fetch_assoc()['total'] ?? 0;

// Total active alert rules
$alerts = $conn->query("
    SELECT COUNT(*) as total 
    FROM alert_rules 
    WHERE is_active = 1
")->fetch_assoc()['total'] ?? 0;

/* =========================
    INVENTORY STATUS
========================= */

$inventory = $conn->query("
    SELECT 
        p.name,
        
        SUM(
            CASE 
                WHEN sr.record_type='incoming' 
                AND sr.warehouse_id=1 
                THEN sr.quantity 
                ELSE 0 
            END
        ) -
        
        SUM(
            CASE 
                WHEN sr.record_type='outgoing' 
                AND sr.warehouse_id=1 
                THEN sr.quantity 
                ELSE 0 
            END
        ) AS farm_stock,

        SUM(
            CASE 
                WHEN sr.record_type='incoming' 
                AND sr.warehouse_id=2 
                THEN sr.quantity 
                ELSE 0 
            END
        ) -
        
        SUM(
            CASE 
                WHEN sr.record_type='outgoing' 
                AND sr.warehouse_id=2 
                THEN sr.quantity 
                ELSE 0 
            END
        ) AS paranaque_stock

    FROM products p

    LEFT JOIN stock_records sr 
        ON p.id = sr.product_id 
        AND sr.is_deleted = 0

    GROUP BY p.id
");

/* =========================
    CHART DATA
    ALL VARIANTS / PRODUCTS
========================= */

$chartQuery = $conn->query("
    SELECT 
        sr.transaction_date as date,
        sr.record_type,
        p.name as product_name,
        SUM(sr.quantity) as total_qty

    FROM stock_records sr

    LEFT JOIN products p 
        ON sr.product_id = p.id

    WHERE sr.is_deleted = 0

    GROUP BY 
        sr.transaction_date,
        sr.record_type,
        p.name

    ORDER BY sr.transaction_date ASC
");

$chartData = [];

while($row = $chartQuery->fetch_assoc()) {
    $chartData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard | Blue Eco Farm</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar_style.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

:root{
    --primary-green:#2d5a27;
    --light-bg:#f8faf9;
    --border-color:#e5e7eb;
    --text-main:#1f2937;
    --text-muted:#6b7280;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:var(--light-bg);
    color:var(--text-main);
    display:flex;
}

/* MAIN */

.main{
    margin-left:260px;
    flex-grow:1;
    padding:48px;
}

/* CARDS */

.cards{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:24px;
    margin-bottom:32px;
}

.card{
    background:#fff;
    padding:24px;
    border-radius:16px;
    border:1px solid var(--border-color);
}

.card h1{
    font-size:2.2rem;
    margin:10px 0 5px 0;
}

.card .label{
    font-weight:600;
}

.card .sub-label{
    font-size:0.85rem;
    color:var(--text-muted);
}

/* ACTIONS */

.actions-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:20px;
    margin-bottom:32px;
}

.action-card{
    background:#fff;
    padding:30px 24px;
    border-radius:16px;
    border:1px solid var(--border-color);
    text-decoration:none;
    color:inherit;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-weight:600;
}

/* TABLE */

.inventory-table{
    width:100%;
    background:#fff;
    border-radius:16px;
    border:1px solid var(--border-color);
    border-collapse:collapse;
    overflow:hidden;
}

.inventory-table th{
    background:#f9fafb;
    text-align:left;
    padding:16px;
    font-size:0.85rem;
    color:var(--text-muted);
}

.inventory-table td{
    padding:16px;
    border-top:1px solid var(--border-color);
}

/* CHARTS */

.charts-container{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:24px;
    margin-top:32px;
}

/* FILTER */

.filter-container{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:20px;
}

#warehouseFilter{
    padding:10px 16px;
    border-radius:10px;
    border:1px solid var(--border-color);
    background:white;
    font-size:0.9rem;
    cursor:pointer;
}

</style>
</head>

<body>

<?php include 'includes/staff_sidebar.php'; ?>

<!-- MAIN -->

<div class="main">

    <header>
        <h2>Staff Dashboard</h2>
        <p>Welcome back! Here's your operational overview.</p>
    </header>

    <!-- CARDS -->

    <div class="cards">

        <div class="card">
            <div style="background:#e8f5e9; width:35px; height:35px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                📈
            </div>

            <h1><?php echo $totalAllTime; ?></h1>

            <div class="label">Total Records</div>
            <div class="sub-label">All-time system entries</div>
        </div>

        <div class="card">
            <div style="background:#e3f2fd; width:35px; height:35px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                🕒
            </div>

            <h1><?php echo $transfersCount; ?></h1>

            <div class="label">Total Transfers</div>
            <div class="sub-label">Warehouse movements</div>
        </div>

        <div class="card">
            <div style="background:#fff3e0; width:35px; height:35px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                ⚠️
            </div>

            <h1><?php echo $alerts; ?></h1>

            <div class="label">Active Rules</div>
            <div class="sub-label">Monitoring stock thresholds</div>
        </div>

    </div>

    <!-- QUICK ACTIONS -->

    <h3>Quick Actions</h3>

    <div class="actions-grid">

        <a href="stock_in.php" class="action-card">
            <span>📦 Stock In</span>
            <span>❯</span>
        </a>

        <a href="stock_out.php" class="action-card">
            <span>📈 Stock Out</span>
            <span>❯</span>
        </a>

        <a href="transfer.php" class="action-card">
            <span>🕒 Warehouse Transfer</span>
            <span>❯</span>
        </a>

    </div>

    <!-- FILTER -->

    <h3 style="margin-bottom:10px;">Inventory Status</h3>

    <div class="filter-container">

        <span style="font-weight:500; color:var(--text-muted);">
            Warehouse:
        </span>

        <select id="warehouseFilter" onchange="filterWarehouse()">
            <option value="all">All Warehouses</option>
            <option value="farm">Farm</option>
            <option value="paranaque">Paranaque</option>
        </select>

    </div>

    <!-- TABLE -->

    <table class="inventory-table">

        <thead>
            <tr>
                <th>Product Name</th>
                <th class="col-farm">Farm Stock</th>
                <th class="col-paranaque">Paranaque Stock</th>
                <th class="col-total">Total Balance</th>
            </tr>
        </thead>

        <tbody>

        <?php while($row = $inventory->fetch_assoc()) { 

            $total = ($row['farm_stock'] ?? 0) + 
                     ($row['paranaque_stock'] ?? 0);

        ?>

            <tr>

                <td>
                    <strong>
                        <?php echo htmlspecialchars($row['name']); ?>
                    </strong>
                </td>

                <td class="col-farm">
                    <?php echo number_format($row['farm_stock'] ?? 0); ?>
                </td>

                <td class="col-paranaque">
                    <?php echo number_format($row['paranaque_stock'] ?? 0); ?>
                </td>

                <td class="col-total" style="font-weight:600; color:var(--primary-green);">
                    <?php echo number_format($total); ?>
                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

    <h3 style="color: var(--primary-green); font-weight: 700; margin-top: 40px; margin-bottom: 20px;">Inventory Report</h3>

        <div style="margin-bottom: 20px; color: var(--text-muted); font-size: 0.9rem;">
            <p>Report generated on <?php echo date('F j, Y'); ?>. This analytics report provides key insights into inventory performance, stock levels, and operational trends.</p>
        </div>

    <!-- CHARTS -->

    
    <div class="charts-container">

        <div class="card">
            <h4 style="margin:0 0 15px 0;">
                Stock In Trends
            </h4>

            <canvas id="stockInChart" height="150"></canvas>
        </div>

        <div class="card">
            <h4 style="margin:0 0 15px 0;">
                Stock Out Trends
            </h4>

            <canvas id="stockOutChart" height="150"></canvas>
        </div>

    </div>

    </div>

</div>

<script>

/* =========================
    CHART DATA
========================= */

const data = <?php echo json_encode($chartData); ?>;

// UNIQUE DATES
const labels = [...new Set(data.map(d => d.date))];

// UNIQUE PRODUCTS
const products = [...new Set(data.map(d => d.product_name))];

/* =========================
    RANDOM COLORS
========================= */

function randomColor() {
    return `hsl(${Math.floor(Math.random() * 360)}, 70%, 50%)`;
}

/* =========================
    CREATE DATASETS
========================= */

function createDatasets(type) {

    return products.map(product => {

        const color = randomColor();

        return {

            label: product,

            data: labels.map(label => {

                const found = data.find(d =>
                    d.date === label &&
                    d.record_type === type &&
                    d.product_name === product
                );

                return found ? found.total_qty : 0;

            }),

            borderColor: color,
            backgroundColor: color + '33',
            fill: false,
            tension: 0.3,
            borderWidth: 2
        };

    });

}

/* =========================
    STOCK IN CHART
========================= */

new Chart(document.getElementById('stockInChart'), {

    type: 'line',

    data: {
        labels: labels,
        datasets: createDatasets('incoming')
    },

    options: {

        responsive: true,

        plugins: {
            legend: {
                position: 'bottom'
            }
        },

        scales: {
            y: {
                beginAtZero: true
            }
        }

    }

});

/* =========================
    STOCK OUT CHART
========================= */

new Chart(document.getElementById('stockOutChart'), {

    type: 'line',

    data: {
        labels: labels,
        datasets: createDatasets('outgoing')
    },

    options: {

        responsive: true,

        plugins: {
            legend: {
                position: 'bottom'
            }
        },

        scales: {
            y: {
                beginAtZero: true
            }
        }

    }

});

/* =========================
    FILTER LOGIC
========================= */

function filterWarehouse() {

    const filterValue = document.getElementById('warehouseFilter').value;

    const farmCols = document.querySelectorAll('.col-farm');
    const paranaqueCols = document.querySelectorAll('.col-paranaque');
    const totalCols = document.querySelectorAll('.col-total');

    if(filterValue === 'farm'){

        farmCols.forEach(el => el.style.display = '');
        paranaqueCols.forEach(el => el.style.display = 'none');
        totalCols.forEach(el => el.style.display = 'none');

    }

    else if(filterValue === 'paranaque'){

        farmCols.forEach(el => el.style.display = 'none');
        paranaqueCols.forEach(el => el.style.display = '');
        totalCols.forEach(el => el.style.display = 'none');

    }

    else{

        farmCols.forEach(el => el.style.display = '');
        paranaqueCols.forEach(el => el.style.display = '');
        totalCols.forEach(el => el.style.display = '');

    }

}

</script>

</body>
</html>