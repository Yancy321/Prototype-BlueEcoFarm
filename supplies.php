<?php
require_once 'src/AuthManager.php';
AuthManager::requireStaff();
require_once 'src/Database.php';
$pdo = Database::getInstance();
$currentPage = 'supplies.php';

$packagingCount  = (int)$pdo->query("SELECT COUNT(*) FROM inventory_supplies WHERE category = 'Packaging Material'")->fetchColumn();
$productionCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_supplies WHERE category = 'Production Essential'")->fetchColumn();
$lowStockCount   = (int)$pdo->query("SELECT COUNT(*) FROM inventory_supplies WHERE current_stock_level <= reorder_point")->fetchColumn();
$production      = $pdo->query("SELECT * FROM inventory_supplies WHERE category = 'Production Essential' ORDER BY item_name ASC")->fetchAll();

$packagingGroups = [
    'Bags & Wraps'    => ['items' => ['Frozen bags','Plastic rolls','Shrink wrap roll','Ziplock bag']],
    'Labels & Print'  => ['items' => ['Sticker (Light Green)','Sticker (Blue Green)','Sticker (Green)','Brochure']],
    'Pouches & Boxes' => ['items' => ['Packaging (Small Pouch)','Packaging (Large Pouch)','Packaging (Sample Pouch)','Box (Carton - Large)','Box (Carton - Medium)','Box (Carton - Small)','Paper bags']],
    'Tape & Sealing'  => ['items' => ['Tape (Packaging)']],
    'Containers'      => ['items' => ['Reusable Tubs','Jars','Bottles','Crates']],
    'Packaging Tools' => ['items' => ['Heat sealers','Weighing scales','Plastic Funnel']],
];

$packagingAll = [];
foreach ($pdo->query("SELECT * FROM inventory_supplies WHERE category = 'Packaging Material'")->fetchAll() as $r) {
    $packagingAll[$r['item_name']] = $r;
}

require_once 'includes/header.php';
?>

<h1 class="page-title">Supplies Inventory</h1>

<!-- Edit stock modal -->
<div id="editModal" class="modal-overlay">
    <div class="card" style="width:340px;max-width:95vw;margin:0;">
        <h2 style="margin-bottom:1rem;font-size:1rem;">Update Stock</h2>
        <div style="font-weight:600;margin-bottom:1rem;" id="editItemName"></div>
        <input type="hidden" id="editItemId">
        <div class="form-group">
            <label>Action</label>
            <select id="editType">
                <option value="add">Add stock</option>
                <option value="subtract">Remove stock</option>
                <option value="set">Set exact amount</option>
            </select>
        </div>
        <div class="form-group">
            <label>Amount</label>
            <input type="number" id="editAmount" min="0" placeholder="e.g. 10">
        </div>
        <div id="editError"></div>
        <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
            <button class="btn btn-primary" style="flex:1;" onclick="saveStock()">Save</button>
            <button class="btn btn-secondary" style="flex:1;" onclick="closeEdit()">Cancel</button>
        </div>
    </div>
</div>

<!-- Stat summary -->
<div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div class="card" style="padding:0.75rem 1.25rem;display:flex;align-items:center;gap:0.6rem;">
        <span style="font-size:1.3rem;font-weight:700;color:#1b5e20;"><?= $productionCount ?></span>
        <span style="font-size:0.82rem;color:#888;">Production Items</span>
    </div>
    <div class="card" style="padding:0.75rem 1.25rem;display:flex;align-items:center;gap:0.6rem;">
        <span style="font-size:1.3rem;font-weight:700;color:#1b5e20;"><?= $packagingCount ?></span>
        <span style="font-size:0.82rem;color:#888;">Packaging Items</span>
    </div>
    <div class="card" style="padding:0.75rem 1.25rem;display:flex;align-items:center;gap:0.6rem;<?= $lowStockCount > 0 ? 'border-color:#fca5a5;background:#fff5f5;' : '' ?>">
        <span style="font-size:1.3rem;font-weight:700;color:<?= $lowStockCount > 0 ? '#c62828' : '#1b5e20' ?>;"><?= $lowStockCount ?></span>
        <span style="font-size:0.82rem;color:#888;">Low Stock</span>
    </div>
</div>

<!-- Production Essentials -->
<div class="card" style="margin-bottom:1.5rem;">
    <h2 style="margin-bottom:1rem;">Production Essentials</h2>
    <div class="supply-grid">
    <?php foreach ($production as $row):
        $stock = (int)$row['current_stock_level'];
        $thr   = (int)$row['reorder_point'];
        $unit  = $row['unit_of_measure'];
        $pct   = $thr > 0 ? min(round($stock / ($thr * 2) * 100), 100) : 100;
        if ($stock <= $thr * 0.5)  { $chip = 'schip-crit'; $fill = '#c62828'; $label = 'Critical'; }
        elseif ($stock <= $thr)    { $chip = 'schip-low';  $fill = '#f59e0b'; $label = 'Low'; }
        else                       { $chip = 'schip-ok';   $fill = '#4caf50'; $label = 'OK'; }
    ?>
        <div class="supply-card" id="card-<?= $row['id'] ?>">
            <div class="supply-card-header">
                <div class="supply-card-name"><?= htmlspecialchars($row['item_name']) ?></div>
                <span class="schip <?= $chip ?>"><?= $label ?></span>
            </div>
            <div class="supply-card-qty" id="qty-<?= $row['id'] ?>">
                <?= number_format($stock) ?>
                <span class="supply-card-unit"><?= htmlspecialchars($unit) ?></span>
            </div>
            <div class="prog-track">
                <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $fill ?>;"></div>
            </div>
            <div class="supply-card-footer">
                <div class="supply-reorder-label">Reorder at <?= $thr ?></div>
                <button class="supply-update-btn" onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes($row['item_name']) ?>')">+ Update</button>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Packaging Materials -->
<div class="card">
    <h2 style="margin-bottom:0.5rem;">Packaging Materials</h2>
    <?php foreach ($packagingGroups as $groupName => $group): ?>
        <div class="subsec-label"><?= $groupName ?></div>
        <div class="supply-grid">
        <?php foreach ($group['items'] as $itemName):
            if (!isset($packagingAll[$itemName])) continue;
            $row   = $packagingAll[$itemName];
            $stock = (int)$row['current_stock_level'];
            $thr   = (int)$row['reorder_point'];
            $unit  = $row['unit_of_measure'];
            $pct   = $thr > 0 ? min(round($stock / ($thr * 2) * 100), 100) : 100;
            if ($stock <= $thr * 0.5)  { $chip = 'schip-crit'; $fill = '#c62828'; $label = 'Critical'; }
            elseif ($stock <= $thr)    { $chip = 'schip-low';  $fill = '#f59e0b'; $label = 'Low'; }
            else                       { $chip = 'schip-ok';   $fill = '#4caf50'; $label = 'OK'; }
        ?>
            <div class="supply-card" id="card-<?= $row['id'] ?>">
                <div class="supply-card-header">
                    <div class="supply-card-name"><?= htmlspecialchars($row['item_name']) ?></div>
                    <span class="schip <?= $chip ?>"><?= $label ?></span>
                </div>
                <div class="supply-card-qty" id="qty-<?= $row['id'] ?>">
                    <?= number_format($stock) ?>
                    <span class="supply-card-unit"><?= htmlspecialchars($unit) ?></span>
                </div>
                <div class="prog-track">
                    <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $fill ?>;"></div>
                </div>
                <div class="supply-card-footer">
                    <div class="supply-reorder-label">Reorder at <?= $thr ?></div>
                    <button class="supply-update-btn" onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes($row['item_name']) ?>')">+ Update</button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
let currentEditId = null;

function openEdit(id, name) {
    currentEditId = id;
    document.getElementById('editItemId').value         = id;
    document.getElementById('editItemName').textContent = name;
    document.getElementById('editAmount').value         = '';
    document.getElementById('editError').innerHTML      = '';
    document.getElementById('editType').value           = 'add';
    document.getElementById('editModal').classList.add('open');
    document.getElementById('editAmount').focus();
}
function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
    currentEditId = null;
}
async function saveStock() {
    const id     = currentEditId;
    const type   = document.getElementById('editType').value;
    const amount = parseInt(document.getElementById('editAmount').value);
    const errEl  = document.getElementById('editError');
    errEl.innerHTML = '';
    if (isNaN(amount) || amount < 0) {
        errEl.innerHTML = '<div class="alert alert-error">Please enter a valid amount.</div>';
        return;
    }
    const url  = type === 'set' ? 'api/supplies.php?action=update_stock' : 'api/supplies.php?action=adjust_stock';
    const body = type === 'set' ? JSON.stringify({id, stock:amount}) : JSON.stringify({id, amount, type});
    const res  = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body});
    const json = await res.json();
    if (json.success) { closeEdit(); location.reload(); }
    else errEl.innerHTML = `<div class="alert alert-error">${json.error}</div>`;
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
</script>

<?php require_once 'includes/footer.php'; ?>
