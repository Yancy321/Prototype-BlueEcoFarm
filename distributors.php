<?php require_once 'includes/header.php'; ?>

<h1 class="page-title">Distributors</h1>

<div id="alertBox"></div>

<!-- Add Distributor -->
<div class="card card-narrow">
    <h2>Add Distributor</h2>
    <form id="formCreate" class="form-grid-2">
        <div class="form-group form-group-inline">
            <label>Name <span class="required-star">*</span></label>
            <input type="text" name="name" required placeholder="e.g. Juan Dela Cruz">
        </div>
        <div class="form-group form-group-inline">
            <label>Phone Number <span class="required-star">*</span></label>
            <input type="text" name="phone" required placeholder="e.g. 09171234567">
        </div>
        <div class="form-group form-group-inline form-full">
            <label>Notes (optional)</label>
            <input type="text" name="notes" placeholder="e.g. Handles Luzon area">
        </div>
        <div class="form-full">
            <button type="submit" class="btn btn-primary btn-full">+ Add Distributor</button>
        </div>
    </form>
    <div id="createError"></div>
</div>

<!-- Distributors Table -->
<div class="card">
    <h2>Distributor List</h2>
    <p class="text-muted-sm">Active distributors will receive an SMS notification whenever new stock arrives.</p>
    <div class="table-wrap">
        <table id="distributorsTable">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Phone</th>
                    <th>Notes</th><th>Active</th><th>Actions</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
    <div class="card modal-card" style="width:480px;">
        <h2>Edit Distributor</h2>
        <form id="formEdit">
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="editName" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" id="editPhone" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="editNotes">
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
function showAlert(msg, type = 'success') {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    setTimeout(() => box.innerHTML = '', 4000);
}
function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadDistributors() {
    const res  = await fetch('api/distributors.php?action=list');
    const data = await res.json();
    const tbody = document.querySelector('#distributorsTable tbody');
    if (!Array.isArray(data) || !data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="cell-sub">No distributors yet. Add one above.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(d => `
        <tr>
            <td>${d.id}</td>
            <td>${escHtml(d.name)}</td>
            <td>${escHtml(d.phone)}</td>
            <td>${escHtml(d.notes || '—')}</td>
            <td>${d.is_active == 1
                ? '<span class="badge badge-success">Yes</span>'
                : '<span class="badge badge-danger">No</span>'}</td>
            <td>
                <button class="btn btn-primary btn-sm" style="margin-right:0.3rem;"
                    onclick='openEdit(${JSON.stringify(d).replace(/"/g,"&quot;")})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteDistributor(${d.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('formCreate').addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('createError').innerHTML = '';
    const data = Object.fromEntries(new FormData(e.target).entries());
    const res  = await fetch('api/distributors.php?action=create', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const json = await res.json();
    if (json.success) {
        showAlert('Distributor added successfully.');
        e.target.reset(); loadDistributors();
    } else {
        document.getElementById('createError').innerHTML = `<div class="alert alert-error">${escHtml(json.error)}</div>`;
    }
});

function openEdit(d) {
    document.getElementById('editId').value     = d.id;
    document.getElementById('editName').value   = d.name;
    document.getElementById('editPhone').value  = d.phone;
    document.getElementById('editNotes').value  = d.notes || '';
    document.getElementById('editActive').value = d.is_active;
    document.getElementById('editError').innerHTML = '';
    document.getElementById('editModal').classList.add('open');
}
function closeModal() { document.getElementById('editModal').classList.remove('open'); }

document.getElementById('formEdit').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = {
        id:        document.getElementById('editId').value,
        name:      document.getElementById('editName').value,
        phone:     document.getElementById('editPhone').value,
        notes:     document.getElementById('editNotes').value,
        is_active: document.getElementById('editActive').value,
    };
    const res  = await fetch('api/distributors.php?action=update', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const json = await res.json();
    if (json.success) { closeModal(); showAlert('Distributor updated.'); loadDistributors(); }
    else document.getElementById('editError').innerHTML = `<div class="alert alert-error">${escHtml(json.error)}</div>`;
});

async function deleteDistributor(id) {
    if (!confirm('Delete this distributor?')) return;
    const res  = await fetch('api/distributors.php?action=delete', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id})
    });
    const json = await res.json();
    if (json.success) { showAlert('Distributor deleted.'); loadDistributors(); }
    else showAlert(json.error || 'Error', 'error');
}

loadDistributors();
</script>

<?php require_once 'includes/footer.php'; ?>
