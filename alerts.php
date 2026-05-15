<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 class="page-title">SMS Alert Rules</h1>

<div id="alertBox"></div>

<!-- Add Alert Rule -->
<div class="card">
    <h2>Add Alert Rule</h2>
    <form id="formCreate" class="form-grid-auto">
        <div class="form-group form-group-inline">
            <label>Product</label>
            <select name="product_id" required>
                <option value="">— Select —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group form-group-inline">
            <label>Warehouse</label>
            <select name="warehouse_id" required>
                <option value="">— Select —</option>
                <option value="1">Farm</option>
                <option value="2">Paranaque</option>
            </select>
        </div>
        <div class="form-group form-group-inline">
            <label>Threshold (qty)</label>
            <input type="number" name="threshold" min="1" required placeholder="e.g. 50">
        </div>
        <div class="form-group form-group-inline">
            <label>Cooldown (minutes)</label>
            <input type="number" name="cooldown_minutes" min="1" value="60">
        </div>
        <div style="display:flex;align-items:flex-end;">
            <button type="submit" class="btn btn-primary btn-full">+ Add Rule</button>
        </div>
    </form>
    <div id="createError"></div>
</div>

<!-- Existing Rules -->
<div class="card">
    <h2>Existing Rules</h2>
    <div class="table-wrap">
        <table id="rulesTable">
            <thead>
                <tr>
                    <th>ID</th><th>Product</th><th>Warehouse</th><th>Threshold</th>
                    <th>Recipients</th><th>Cooldown</th><th>Active</th><th>Actions</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="8">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Manage Recipients -->
<div class="card">
    <h2>Manage Recipients by Product</h2>
    <div class="form-grid-auto" style="margin-bottom:1rem;">
        <div class="form-group form-group-inline" style="flex:1;min-width:180px;">
            <label>Select Product</label>
            <select id="recipientProductFilter">
                <option value="">— Select product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div id="recipientSection" style="display:none;">
        <div class="form-grid-auto" style="margin-bottom:1rem;">
            <div class="form-group form-group-inline" style="flex:1;min-width:180px;">
                <label>Phone Number (E.164 or PH format)</label>
                <input type="text" id="newPhone" placeholder="e.g. 09151042742 or +639151042742">
            </div>
            <div class="form-group form-group-inline" style="width:160px;">
                <label>Label (optional)</label>
                <input type="text" id="newLabel" placeholder="e.g. Manager">
            </div>
            <button class="btn btn-primary" onclick="addRecipient()">+ Add</button>
        </div>
        <div id="recipientError"></div>
        <div class="table-wrap">
            <table id="recipientsTable">
                <thead>
                    <tr><th>ID</th><th>Phone</th><th>Label</th><th>Action</th></tr>
                </thead>
                <tbody><tr><td colspan="4">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Rule Modal -->
<div id="editModal" class="modal-overlay">
    <div class="card modal-card">
        <h2>Edit Alert Rule</h2>
        <form id="formEdit">
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>Product</label>
                <select id="editProduct" required>
                    <option value="">— Select —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Warehouse</label>
                <select id="editWarehouse" required>
                    <option value="">— Select —</option>
                    <option value="1">Farm</option>
                    <option value="2">Paranaque</option>
                </select>
            </div>
            <div class="form-group">
                <label>Threshold</label>
                <input type="number" id="editThreshold" min="1" required>
            </div>
            <div class="form-group">
                <label>Cooldown (minutes)</label>
                <input type="number" id="editCooldown" min="1">
            </div>
            <div class="form-group">
                <label>Active</label>
                <select id="editActive">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
            <div id="editError"></div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const warehouseNames = {1:'Farm', 2:'Paranaque'};
let currentProductId = null;

function showAlert(msg, type='success') {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    setTimeout(() => box.innerHTML = '', 4000);
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadRules() {
    const res  = await fetch('api/alerts.php?action=list');
    const data = await res.json();
    const tbody = document.querySelector('#rulesTable tbody');
    if (!Array.isArray(data) || !data.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="cell-sub">No alert rules yet.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(r => {
        const recips = Array.isArray(r.recipients)
            ? r.recipients.map(x => escHtml(x.phone + (x.label ? ' ('+x.label+')' : ''))).join('<br>')
            : '—';
        const active = r.is_active == 1
            ? '<span class="badge badge-success">Yes</span>'
            : '<span class="badge badge-danger">No</span>';
        return `<tr>
            <td>${r.id}</td>
            <td>${escHtml(r.product_name)}</td>
            <td>${warehouseNames[r.warehouse_id]||r.warehouse_id}</td>
            <td>${r.threshold}</td>
            <td class="cell-sm">${recips}</td>
            <td>${r.cooldown_minutes} min</td>
            <td>${active}</td>
            <td>
                <button class="btn btn-primary btn-sm" style="margin-right:0.3rem;"
                    onclick='openEdit(${JSON.stringify(r).replace(/"/g,"&quot;")})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteRule(${r.id})">Delete</button>
            </td>
        </tr>`;
    }).join('');
}

document.getElementById('formCreate').addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('createError').innerHTML = '';
    const data = Object.fromEntries(new FormData(e.target).entries());
    const res  = await fetch('api/alerts.php?action=create', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    });
    const json = await res.json();
    if (json.success) { showAlert('Rule created (ID: '+json.id+')'); e.target.reset(); loadRules(); }
    else document.getElementById('createError').innerHTML = `<div class="alert alert-error">${json.error}</div>`;
});

function openEdit(r) {
    document.getElementById('editId').value        = r.id;
    document.getElementById('editProduct').value   = r.product_id;
    document.getElementById('editWarehouse').value = r.warehouse_id;
    document.getElementById('editThreshold').value = r.threshold;
    document.getElementById('editCooldown').value  = r.cooldown_minutes;
    document.getElementById('editActive').value    = r.is_active;
    document.getElementById('editError').innerHTML = '';
    document.getElementById('editModal').classList.add('open');
}
function closeModal() { document.getElementById('editModal').classList.remove('open'); }

document.getElementById('formEdit').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = {
        id: document.getElementById('editId').value,
        product_id: document.getElementById('editProduct').value,
        warehouse_id: document.getElementById('editWarehouse').value,
        threshold: document.getElementById('editThreshold').value,
        cooldown_minutes: document.getElementById('editCooldown').value,
        is_active: document.getElementById('editActive').value,
    };
    const res  = await fetch('api/alerts.php?action=update', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    });
    const json = await res.json();
    if (json.success) { closeModal(); showAlert('Rule updated.'); loadRules(); }
    else document.getElementById('editError').innerHTML = `<div class="alert alert-error">${json.error}</div>`;
});

async function deleteRule(id) {
    if (!confirm('Delete rule #'+id+'?')) return;
    const res  = await fetch('api/alerts.php?action=delete', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})
    });
    const json = await res.json();
    if (json.success) { showAlert('Rule deleted.'); loadRules(); }
    else showAlert(json.error||'Error', 'error');
}

document.getElementById('recipientProductFilter').addEventListener('change', function() {
    currentProductId = this.value || null;
    document.getElementById('recipientSection').style.display = currentProductId ? 'block' : 'none';
    if (currentProductId) loadRecipients();
});

async function loadRecipients() {
    const res  = await fetch(`api/alerts.php?action=list_recipients&product_id=${currentProductId}`);
    const data = await res.json();
    const tbody = document.querySelector('#recipientsTable tbody');
    if (!Array.isArray(data) || !data.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="cell-sub">No recipients yet.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(r => `<tr>
        <td>${r.id}</td>
        <td>${escHtml(r.phone)}</td>
        <td>${escHtml(r.label||'—')}</td>
        <td><button class="btn btn-danger btn-xs" onclick="deleteRecipient(${r.id})">Remove</button></td>
    </tr>`).join('');
}

async function addRecipient() {
    document.getElementById('recipientError').innerHTML = '';
    const phone = document.getElementById('newPhone').value.trim();
    const label = document.getElementById('newLabel').value.trim();
    if (!phone) {
        document.getElementById('recipientError').innerHTML = '<div class="alert alert-error">Phone is required.</div>';
        return;
    }
    const res  = await fetch('api/alerts.php?action=add_recipient', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({product_id: currentProductId, phone, label})
    });
    const json = await res.json();
    if (json.success) {
        document.getElementById('newPhone').value = '';
        document.getElementById('newLabel').value = '';
        loadRecipients(); loadRules();
    } else {
        document.getElementById('recipientError').innerHTML = `<div class="alert alert-error">${json.error}</div>`;
    }
}

async function deleteRecipient(id) {
    if (!confirm('Remove this recipient?')) return;
    const res  = await fetch('api/alerts.php?action=delete_recipient', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})
    });
    const json = await res.json();
    if (json.success) { loadRecipients(); loadRules(); }
    else showAlert(json.error||'Error', 'error');
}

loadRules();
</script>

<?php require_once 'includes/footer.php'; ?>
