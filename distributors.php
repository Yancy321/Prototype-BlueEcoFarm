<?php require_once 'includes/header.php'; ?>

<h1 class="page-title">Distributors</h1>

<div id="alertBox"></div>

<!-- Add Distributor -->
<div class="card" style="max-width:640px;">
    <h2>Add Distributor</h2>
    <form id="formCreate" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Name <span style="color:#c62828;">*</span></label>
            <input type="text" name="name" required placeholder="e.g. Juan Dela Cruz">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Phone Number <span style="color:#c62828;">*</span></label>
            <input type="text" name="phone" required placeholder="e.g. 09171234567">
        </div>
        <div class="form-group" style="margin:0;grid-column:1/-1;">
            <label>Notes (optional)</label>
            <input type="text" name="notes" placeholder="e.g. Handles Luzon area">
        </div>
        <div style="grid-column:1/-1;">
            <button type="submit" class="btn btn-primary" style="width:100%;">+ Add Distributor</button>
        </div>
    </form>
    <div id="createError" style="margin-top:0.75rem;"></div>
</div>

<!-- Distributors Table -->
<div class="card">
    <h2>Distributor List</h2>
    <p style="font-size:0.88rem;color:#666;margin-bottom:1rem;">
        Active distributors will receive an SMS notification whenever new stock arrives.
    </p>
    <div class="table-wrap">
        <table id="distributorsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Notes</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;align-items:center;justify-content:center;">
    <div class="card" style="width:480px;max-width:95vw;margin:0;">
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
            <div id="editError" style="margin-bottom:0.75rem;"></div>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeModal()">Cancel</button>
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
        tbody.innerHTML = '<tr><td colspan="6" style="color:#888;">No distributors yet. Add one above.</td></tr>';
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
                <button class="btn btn-primary" style="padding:0.3rem 0.65rem;font-size:0.8rem;margin-right:0.3rem;"
                    onclick='openEdit(${JSON.stringify(d).replace(/"/g,"&quot;")})'>Edit</button>
                <button class="btn btn-danger" style="padding:0.3rem 0.65rem;font-size:0.8rem;"
                    onclick="deleteDistributor(${d.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('formCreate').addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('createError').innerHTML = '';
    const data = Object.fromEntries(new FormData(e.target).entries());
    const res  = await fetch('api/distributors.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        showAlert('Distributor added successfully.');
        e.target.reset();
        loadDistributors();
    } else {
        document.getElementById('createError').innerHTML =
            `<div class="alert alert-error">${escHtml(json.error)}</div>`;
    }
});

function openEdit(d) {
    document.getElementById('editId').value     = d.id;
    document.getElementById('editName').value   = d.name;
    document.getElementById('editPhone').value  = d.phone;
    document.getElementById('editNotes').value  = d.notes || '';
    document.getElementById('editActive').value = d.is_active;
    document.getElementById('editError').innerHTML = '';
    document.getElementById('editModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

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
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        closeModal();
        showAlert('Distributor updated.');
        loadDistributors();
    } else {
        document.getElementById('editError').innerHTML =
            `<div class="alert alert-error">${escHtml(json.error)}</div>`;
    }
});

async function deleteDistributor(id) {
    if (!confirm('Delete this distributor?')) return;
    const res  = await fetch('api/distributors.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
    const json = await res.json();
    if (json.success) { showAlert('Distributor deleted.'); loadDistributors(); }
    else showAlert(json.error || 'Error', 'error');
}

loadDistributors();
</script>

<?php require_once 'includes/footer.php'; ?>
