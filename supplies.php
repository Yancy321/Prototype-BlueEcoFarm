<?php
$conn = new mysqli("localhost", "root", "", "blue_eco_farm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentPage = 'supplies';

/* =========================
    SUMMARY COUNTS
========================= */

$packagingCount = $conn->query("
    SELECT COUNT(*) as total FROM inventory_supplies 
    WHERE category = 'Packaging Material'
")->fetch_assoc()['total'] ?? 0;

$productionCount = $conn->query("
    SELECT COUNT(*) as total FROM inventory_supplies 
    WHERE category = 'Production Essential'
")->fetch_assoc()['total'] ?? 0;

$lowStockCount = $conn->query("
    SELECT COUNT(*) as total FROM inventory_supplies 
    WHERE current_stock_level <= reorder_point
")->fetch_assoc()['total'] ?? 0;

/* =========================
    FETCH SUPPLIES BY CATEGORY
========================= */

$production = $conn->query("
    SELECT * FROM inventory_supplies 
    WHERE category = 'Production Essential'
    ORDER BY FIELD(item_name,
        'Hand gloves',
        'Facemask',
        'Hairnets',
        'Alcohol',
        'Aprons',
        'Ballpen',
        'Markers',
        'Tape (General)',
        'Bond paper',
        'First aid kit'
    )
");

// Packaging sub-sections
$packagingGroups = [
    'Bags & Wraps' => [
        'icon' => '🛍️',
        'color' => '#fff3e0',
        'items' => ['Frozen bags', 'Plastic rolls', 'Shrink wrap roll', 'Ziplock bag']
    ],
    'Labels & Print' => [
        'icon' => '🏷️',
        'color' => '#e3f2fd',
        'items' => ['Sticker (Light Green)', 'Sticker (Blue Green)', 'Sticker (Green)', 'Brochure']
    ],
    'Pouches & Boxes' => [
        'icon' => '📫',
        'color' => '#f3e5f5',
        'items' => ['Packaging (Small Pouch)', 'Packaging (Large Pouch)', 'Packaging (Sample Pouch)', 'Box (Carton - Large)', 'Box (Carton - Medium)', 'Box (Carton - Small)', 'Paper bags']
    ],
    'Tape & Sealing' => [
        'icon' => '🔒',
        'color' => '#e8f5e9',
        'items' => ['Tape (Packaging)']
    ],
    'Containers' => [
        'icon' => '🫙',
        'color' => '#fce4ec',
        'items' => ['Reusable Tubs', 'Jars', 'Bottles', 'Crates']
    ],
    'Packaging Tools' => [
        'icon' => '🔧',
        'color' => '#e0f7fa',
        'items' => ['Heat sealers', 'Weighing scales', 'Plastic Funnel']
    ],
];

// Fetch all packaging items once
$packagingRaw = $conn->query("
    SELECT * FROM inventory_supplies 
    WHERE category = 'Packaging Material'
");
$packagingAll = [];
while ($r = $packagingRaw->fetch_assoc()) {
    $packagingAll[$r['item_name']] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Supplies Inventory | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar_style.css">
<style>

:root {
    --green:        #2d5a27;
    --green-soft:   #e8f5e9;
    --green-mid:    #4caf50;
    --yellow:       #f59e0b;
    --yellow-soft:  #fffbeb;
    --red:          #ef4444;
    --red-soft:     #fef2f2;
    --bg:           #f6f8f6;
    --surface:      #ffffff;
    --border:       #e8ece8;
    --text:         #1a2e1a;
    --muted:        #7a8f7a;
    --radius:       18px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
}

.main {
    margin-left: 260px;
    flex-grow: 1;
    padding: 40px 48px;
    max-width: 1200px;
}

/* ── PAGE HEADER ── */
.page-header {
    margin-bottom: 30px;
}

.page-header h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    font-weight: 400;
    color: var(--green);
    margin-bottom: 6px;
}

.page-header p {
    color: var(--muted);
    font-size: 0.95rem;
}

/* ── SUMMARY STRIP ── */
.summary-strip {
    display: flex;
    gap: 12px;
    margin-bottom: 36px;
    flex-wrap: wrap;
}

.summary-pill {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 50px;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.summary-pill .pill-icon {
    font-size: 1.1rem;
}

.summary-pill .pill-count {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text);
}

.summary-pill .pill-label {
    font-size: 0.82rem;
    color: var(--muted);
    font-weight: 500;
}

.summary-pill.alert {
    border-color: #fca5a5;
    background: var(--red-soft);
}

.summary-pill.alert .pill-count {
    color: var(--red);
}

/* ── SECTION ── */
.section {
    margin-bottom: 40px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

.section-header .section-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.section-header h3 {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--green);
}

.section-header .item-count {
    font-size: 0.78rem;
    background: var(--green-soft);
    color: var(--green);
    padding: 3px 10px;
    border-radius: 50px;
    font-weight: 600;
    margin-left: 4px;
}

/* ── CARD GRID ── */
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
}

.supply-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s, transform 0.2s;
}

.supply-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.supply-card .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.supply-card .item-name {
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.3;
    max-width: 140px;
}

/* STATUS CHIP */
.status-chip {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 50px;
    flex-shrink: 0;
    letter-spacing: 0.02em;
}

.chip-normal   { background: var(--green-soft); color: var(--green); }
.chip-low      { background: var(--yellow-soft); color: var(--yellow); }
.chip-critical { background: var(--red-soft); color: var(--red); }

/* STOCK ROW */
.stock-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.stock-number {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1;
}

.stock-unit {
    font-size: 0.78rem;
    color: var(--muted);
    margin-left: 4px;
    font-weight: 500;
}

.threshold-note {
    font-size: 0.78rem;
    color: var(--muted);
}

/* PROGRESS BAR */
.progress-track {
    height: 5px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-top: 4px;
}

.progress-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.4s ease;
}

.fill-normal   { background: var(--green-mid); }
.fill-low      { background: var(--yellow); }
.fill-critical { background: var(--red); }



/* ── SIMPLE LIST ── */
.simple-list {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}

.simple-list-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}

.simple-list-row:last-child {
    border-bottom: none;
}

.simple-list-row:hover {
    background: var(--bg);
}

.simple-list-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text);
}

.simple-list-qty {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--green);
}

.simple-list-unit {
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--muted);
}

/* ── SUBSECTIONS ── */
.subsection {
    margin-bottom: 28px;
}

.subsection-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding-left: 2px;
}

.subsection-icon {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.subsection-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

</style>
</head>

<body>

<?php include 'includes/staff_sidebar.php'; ?>

<div class="main">

    <!-- HEADER -->
    <div class="page-header">
        <h2>Supplies Inventory</h2>
        <p>Current stock levels of packaging and production materials</p>
    </div>

    <!-- SUMMARY STRIP -->
    <div class="summary-strip">

        <div class="summary-pill">
            <span class="pill-icon">🌿</span>
            <span class="pill-count"><?php echo $productionCount; ?></span>
            <span class="pill-label">Production Items</span>
        </div>

        <div class="summary-pill">
            <span class="pill-icon">📦</span>
            <span class="pill-count"><?php echo $packagingCount; ?></span>
            <span class="pill-label">Packaging Items</span>
        </div>

        <div class="summary-pill <?php echo $lowStockCount > 0 ? 'alert' : ''; ?>">
            <span class="pill-icon">⚠️</span>
            <span class="pill-count"><?php echo $lowStockCount; ?></span>
            <span class="pill-label">Low Stock Items</span>
        </div>

    </div>

    <!-- PRODUCTION ESSENTIALS -->
    <div class="section">

        <div class="section-header">
            <div class="section-icon" style="background:#e8f5e9;">🌿</div>
            <h3>Production Essentials</h3>
            <span class="item-count"><?php echo $productionCount; ?> items</span>
        </div>

        <div class="card-grid">
        <?php while($row = $production->fetch_assoc()):
            $stock      = $row['current_stock_level'];
            $threshold  = $row['reorder_point'];
            $unit       = $row['unit_of_measure'];
            $ratio      = $threshold > 0 ? min($stock / ($threshold * 2), 1) : 1;
            $pct        = round($ratio * 100);
            $showStatus = true;

            // Do not render a status label for items that are not consumed often or are not replenished immediately.
            if ($row['item_name'] === 'First aid kit') {
                $showStatus = false;
                $fillClass = 'fill-normal';
            } elseif ($stock <= $threshold * 0.5) {
                $chipClass = 'chip-critical';
                $fillClass = 'fill-critical';
                $label     = 'Critical';
            } elseif ($stock <= $threshold) {
                $chipClass = 'chip-low';
                $fillClass = 'fill-low';
                $label     = 'Low Stock';
            } else {
                $chipClass = 'chip-normal';
                $fillClass = 'fill-normal';
                $label     = 'Normal';
            }
        ?>
            <div class="supply-card">
                <div class="card-top">
                    <div class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></div>
                    <?php if ($showStatus): ?>
                        <span class="status-chip <?php echo $chipClass; ?>"><?php echo $label; ?></span>
                    <?php endif; ?>
                </div>
                <div class="stock-row">
                    <div>
                        <span class="stock-number"><?php echo number_format($stock); ?></span>
                        <span class="stock-unit"><?php echo htmlspecialchars($unit); ?></span>
                    </div>
                </div>
                <div class="progress-track">
                    <div class="progress-fill <?php echo $fillClass; ?>" style="width:<?php echo $pct; ?>%"></div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>

    </div>

    <!-- PACKAGING MATERIALS -->
    <div class="section">

        <div class="section-header">
            <div class="section-icon" style="background:#fff3e0;">📦</div>
            <h3>Packaging Materials</h3>
            <span class="item-count"><?php echo $packagingCount; ?> items</span>
        </div>

        <?php foreach ($packagingGroups as $groupName => $group): ?>

        <div class="subsection">
            <div class="subsection-header">
                <span class="subsection-icon" style="background:<?php echo $group['color']; ?>"><?php echo $group['icon']; ?></span>
                <span class="subsection-title"><?php echo $groupName; ?></span>
            </div>

            <?php
            $listGroups = ['Containers', 'Packaging Tools'];
            $useList = in_array($groupName, $listGroups);
            ?>

            <?php if ($useList): ?>
            <div class="simple-list">
            <?php foreach ($group['items'] as $itemName):
                if (!isset($packagingAll[$itemName])) continue;
                $row   = $packagingAll[$itemName];
                $stock = $row['current_stock_level'];
                $unit  = $row['unit_of_measure'];
            ?>
                <div class="simple-list-row">
                    <span class="simple-list-name"><?php echo htmlspecialchars($row['item_name']); ?></span>
                    <span class="simple-list-qty"><?php echo number_format($stock); ?> <span class="simple-list-unit"><?php echo htmlspecialchars($unit); ?></span></span>
                </div>
            <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="card-grid">
            <?php foreach ($group['items'] as $itemName):
                if (!isset($packagingAll[$itemName])) continue;
                $row       = $packagingAll[$itemName];
                $stock     = $row['current_stock_level'];
                $threshold = $row['reorder_point'];
                $unit      = $row['unit_of_measure'];
                $ratio     = $threshold > 0 ? min($stock / ($threshold * 2), 1) : 1;
                $pct       = round($ratio * 100);

                if ($stock <= $threshold * 0.5) {
                    $chipClass = 'chip-critical';
                    $fillClass = 'fill-critical';
                    $label     = 'Critical';
                } elseif ($stock <= $threshold) {
                    $chipClass = 'chip-low';
                    $fillClass = 'fill-low';
                    $label     = 'Low Stock';
                } else {
                    $chipClass = 'chip-normal';
                    $fillClass = 'fill-normal';
                    $label     = 'Normal';
                }
            ?>
                <div class="supply-card">
                    <div class="card-top">
                        <div class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></div>
                        <span class="status-chip <?php echo $chipClass; ?>"><?php echo $label; ?></span>
                    </div>
                    <div class="stock-row">
                        <div>
                            <span class="stock-number"><?php echo number_format($stock); ?></span>
                            <span class="stock-unit"><?php echo htmlspecialchars($unit); ?></span>
                        </div>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill <?php echo $fillClass; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php endforeach; ?>

    </div>

</div>

</body>
</html>