<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">Stock Management</h1>

<div id="alertBox"></div>

<!-- Toast notification -->
<div id="toast" style="
    display:none;
    position:fixed;
    top:80px;
    right:24px;
    z-index:9999;
    min-width:280px;
    max-width:360px;
    background:#fff;
    border-radius:12px;
    box-shadow:0 8px 32px rgba(0,0,0,0.18);
    padding:1rem 1.25rem;
    display:flex;
    align-items:center;
    gap:0.85rem;
    animation:slideInToast .35s cubic-bezier(.4,0,.2,1);
    border-left:5px solid #2e7d32;
">
    <div id="toastIcon" style="font-size:1.4rem;flex-shrink:0;"></div>
    <div style="flex:1;">
        <div id="toastTitle" style="font-weight:700;font-size:0.92rem;color:#1b5e20;"></div>
        <div id="toastMsg" style="font-size:0.82rem;color:#666;margin-top:0.15rem;"></div>
    </div>
    <button onclick="hideToast()" style="background:none;border:none;cursor:pointer;color:#aaa;font-size:1.1rem;padding:0;line-height:1;">&times;</button>
</div>

<!-- Loading overlay -->
<div id="loadingOverlay" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(3px);
    z-index:8888;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:1rem;
">
    <div style="
        width:52px;height:52px;
        border:4px solid #c8e6c9;
        border-top-color:#2e7d32;
        border-radius:50%;
        animation:spinLoader .7s linear infinite;
    "></div>
    <div id="loadingText" style="color:#2e7d32;font-weight:600;font-size:0.95rem;">Processing...</div>
</div>

<style>
@keyframes slideInToast {
    from { opacity:0; transform:translateX(40px); }
    to   { opacity:1; transform:translateX(0); }
}
@keyframes slideOutToast {
    from { opacity:1; transform:translateX(0); }
    to   { opacity:0; transform:translateX(40px); }
}
@keyframes spinLoader {
    to { transform:rotate(360deg); }
}
#toast { display:none; }
#toast.show { display:flex; }
#loadingOverlay.show { display:flex; }
</style>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem;align-items:start;">

    <!-- STOCK IN -->
    <div class="card">
        <h2 style="color:#2e7d32;border-bottom:2px solid #2e7d32;padding-bottom:0.5rem;margin-bottom:1rem;">Stock In</h2>
        <form id="formIn">
            <div class="form-group">
                <label>Product</label>
                <select name="product_id" required>
                    <option value="">— Select product —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Warehouse</label>
                <select name="warehouse_id" required>
                    <option value="">— Select —</option>
                    <option value="1">Farm</option>
                    <option value="2">Paranaque</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Batch Number (optional)</label>
                <input type="text" name="batch_number" placeholder="e.g. BATCH-2026-001">
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">+ Add Stock</button>
        </form>
    </div>

    <!-- STOCK OUT -->
    <div class="card">
        <h2 style="color:#c62828;border-bottom:2px solid #c62828;padding-bottom:0.5rem;margin-bottom:1rem;">Stock Out</h2>
        <div id="availableStock" style="font-size:0.9rem;color:#555;margin-bottom:0.75rem;min-height:1.2rem;"></div>
        <form id="formOut">
            <div class="form-group">
                <label>Product</label>
                <select name="product_id" required id="outProduct">
                    <option value="">— Select product —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Warehouse</label>
                <select name="warehouse_id" required id="outWarehouse">
                    <option value="">— Select —</option>
                    <option value="1">Farm</option>
                    <option value="2">Paranaque</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Batch Number (optional)</label>
                <input type="text" name="batch_number" placeholder="e.g. BATCH-2026-001">
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-danger" style="width:100%;">− Record Outgoing</button>
        </form>
    </div>

    <!-- UPDATE RECORD -->
    <div class="card">
        <h2 style="color:#6a1b9a;border-bottom:2px solid #6a1b9a;padding-bottom:0.5rem;margin-bottom:1rem;">Update Record</h2>
        <div style="display:flex;gap:0.5rem;align-items:flex-end;margin-bottom:1rem;">
            <div class="form-group" style="margin:0;flex:1;">
                <label>Record ID</label>
                <input type="number" id="updRecordId" placeholder="Enter ID" min="1">
            </div>
            <button class="btn btn-primary" style="background:#6a1b9a;" onclick="loadRecord()">Load</button>
        </div>
        <form id="formUpd" style="display:none;">
            <input type="hidden" id="updId">
            <div id="updInfo" style="padding:0.65rem;background:#f3e5f5;border-radius:6px;font-size:0.85rem;margin-bottom:1rem;"></div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" id="updQty" min="1" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" id="updDate" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="updNotes" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-primary" style="background:#6a1b9a;flex:1;">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="deleteRecord()" style="flex:1;">Delete</button>
            </div>
        </form>
    </div>

</div>

<!-- Recent Records -->
<div class="card" style="margin-top:2rem;">
    <h2>Recent Stock Records</h2>
    <table id="recentTable" style="margin-top:1rem;">
        <thead>
            <tr><th>ID</th><th>Product</th><th>Warehouse</th><th>Type</th><th>Qty</th><th>Batch No.</th><th>Date</th><th>Notes</th></tr>
        </thead>
        <tbody><tr><td colspan="7">Loading...</td></tr></tbody>
    </table>
</div>

<script>
let toastTimer = null;

function showLoading(text = 'Processing...') {
    document.getElementById('loadingText').textContent = text;
    document.getElementById('loadingOverlay').classList.add('show');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('show');
}

function showToast(title, msg = '', type = 'success') {
    const toast    = document.getElementById('toast');
    const icon     = document.getElementById('toastIcon');
    const titleEl  = document.getElementById('toastTitle');
    const msgEl    = document.getElementById('toastMsg');

    const styles = {
        success: { border: '#2e7d32', icon: '&#10003;', color: '#1b5e20' },
        error:   { border: '#c62828', icon: '&#10007;', color: '#b71c1c' },
        info:    { border: '#1565c0', icon: '&#8505;',  color: '#0d47a1' },
    };
    const s = styles[type] || styles.success;

    toast.style.borderLeftColor = s.border;
    icon.innerHTML  = `<span style="width:32px;height:32px;border-radius:50%;background:${s.border}22;display:flex;align-items:center;justify-content:center;color:${s.border};font-weight:700;font-size:1rem;">${s.icon}</span>`;
    titleEl.style.color = s.color;
    titleEl.innerHTML   = title;
    msgEl.textContent   = msg;

    toast.classList.remove('show');
    void toast.offsetWidth; // reflow to restart animation
    toast.classList.add('show');

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 4000);
}

function hideToast() {
    const toast = document.getElementById('toast');
    toast.style.animation = 'slideOutToast .3s ease forwards';
    setTimeout(() => {
        toast.classList.remove('show');
        toast.style.animation = '';
    }, 300);
}

function showAlert(msg, type = 'success') {
    // legacy compat — redirect to toast
    showToast(type === 'success' ? 'Success' : 'Error', msg, type);
}

// Stock In
document.getElementById('formIn').addEventListener('submit', async function(e) {
    e.preventDefault();
    showLoading('Adding stock...');
    const data = Object.fromEntries(new FormData(e.target).entries());
    const res  = await fetch('api/stock.php?action=add_incoming', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const json = await res.json();
    hideLoading();
    if (json.success) {
        showToast('Stock Added Successfully', 'Record ID: ' + json.id, 'success');
        e.target.reset();
        loadRecent();
    } else {
        showToast('Failed to Add Stock', json.error || 'An error occurred', 'error');
    }
});

// Stock Out — show available
async function updateAvailable() {
    const pid = document.getElementById('outProduct').value;
    const wid = document.getElementById('outWarehouse').value;
    const box = document.getElementById('availableStock');
    if (!pid || !wid) { box.textContent = ''; return; }
    const res = await fetch(`api/stock.php?action=get_totals&warehouse_id=${wid}`).then(r => r.json());
    const row = res.find(r => r.id == pid && r.warehouse_id == wid);
    const qty = row ? (parseInt(row.current_stock) || 0) : 0;
    box.innerHTML = `Available: <strong>${qty}</strong>`;
}
document.getElementById('outProduct').addEventListener('change', updateAvailable);
document.getElementById('outWarehouse').addEventListener('change', updateAvailable);

document.getElementById('formOut').addEventListener('submit', async function(e) {
    e.preventDefault();
    showLoading('Recording outgoing stock...');
    const data = Object.fromEntries(new FormData(e.target).entries());
    const res  = await fetch('api/stock.php?action=add_outgoing', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const json = await res.json();
    hideLoading();
    if (json.success) {
        showToast('Stock Out Recorded', 'Record ID: ' + json.id, 'success');
        e.target.reset();
        document.getElementById('availableStock').textContent = '';
        loadRecent();
    } else {
        showToast('Failed to Record', json.error || 'An error occurred', 'error');
    }
});

// Load record for update
async function loadRecord() {
    const id = document.getElementById('updRecordId').value;
    if (!id) return;
    showLoading('Loading record...');
    const res  = await fetch(`api/stock.php?action=get_record&id=${id}`);
    const json = await res.json();
    hideLoading();
    if (json.error) { showToast('Not Found', json.error, 'error'); return; }
    document.getElementById('updId').value    = json.id;
    document.getElementById('updQty').value   = json.quantity;
    document.getElementById('updDate').value  = json.transaction_date;
    document.getElementById('updNotes').value = json.notes || '';
    document.getElementById('updInfo').innerHTML =
        `<strong>${json.product_name}</strong> &nbsp;|&nbsp;
         ${json.warehouse_id == 1 ? 'Farm' : 'Paranaque'} &nbsp;|&nbsp;
         <span style="color:${json.record_type==='incoming'?'#2e7d32':'#c62828'}">${json.record_type}</span>`;
    document.getElementById('formUpd').style.display = 'block';
}

document.getElementById('formUpd').addEventListener('submit', async function(e) {
    e.preventDefault();
    showLoading('Saving changes...');
    const data = {
        record_id: document.getElementById('updId').value,
        quantity:  document.getElementById('updQty').value,
        date:      document.getElementById('updDate').value,
        notes:     document.getElementById('updNotes').value,
    };
    const res  = await fetch('api/stock.php?action=update', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const json = await res.json();
    hideLoading();
    if (json.success) {
        showToast('Record Updated', 'Changes saved successfully', 'success');
        document.getElementById('formUpd').style.display = 'none';
        loadRecent();
    } else {
        showToast('Update Failed', json.error || 'An error occurred', 'error');
    }
});

async function deleteRecord() {
    const id = document.getElementById('updId').value;
    if (!id || !confirm('Delete record #' + id + '?')) return;
    showLoading('Deleting record...');
    const res  = await fetch('api/stock.php?action=delete', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({record_id: id})
    });
    const json = await res.json();
    hideLoading();
    if (json.success) {
        showToast('Record Deleted', 'Record #' + id + ' has been removed', 'success');
        document.getElementById('formUpd').style.display = 'none';
        document.getElementById('updRecordId').value = '';
        loadRecent();
    } else {
        showToast('Delete Failed', json.error || 'An error occurred', 'error');
    }
}

// Recent records
async function loadRecent() {
    const rows = await fetch('api/stock.php?action=get_recent').then(r => r.json());
    const tbody = document.querySelector('#recentTable tbody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8">No records yet.</td></tr>'; return; }
    tbody.innerHTML = rows.map(r =>
        `<tr>
            <td>${r.id}</td>
            <td>${r.product_name}</td>
            <td>${r.warehouse_id == 1 ? 'Farm' : 'Paranaque'}</td>
            <td><span style="color:${r.record_type==='incoming'?'#2e7d32':'#c62828'};font-weight:600;">${r.record_type}</span></td>
            <td>${r.quantity}</td>
            <td>${r.batch_number || '—'}</td>
            <td>${r.transaction_date}</td>
            <td>${r.notes || ''}</td>
        </tr>`
    ).join('');
}

loadRecent();
</script>

<?php require_once 'includes/footer.php'; ?>
