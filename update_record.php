<?php require_once 'includes/header.php'; ?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">Update Stock Record</h1>

<div class="card" style="max-width:520px;">
    <div id="alertBox"></div>

    <!-- Step 1: look up record -->
    <div id="lookupSection">
        <div class="form-group">
            <label for="record_id_lookup">Record ID</label>
            <input type="number" id="record_id_lookup" placeholder="Enter record ID" min="1">
        </div>
        <button class="btn btn-primary" onclick="loadRecord()">Load Record</button>
    </div>

    <!-- Step 2: edit form (hidden until record loaded) -->
    <form id="updateForm" style="display:none;margin-top:1.5rem;">
        <input type="hidden" id="record_id" name="record_id">
        <div id="recordInfo" style="margin-bottom:1rem;padding:0.75rem;background:#e8f5e9;border-radius:6px;font-size:0.9rem;"></div>
        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" required>
        </div>
        <div class="form-group">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" required>
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<script>
async function loadRecord() {
    const id = document.getElementById('record_id_lookup').value;
    if (!id) return;
    const alertBox = document.getElementById('alertBox');

    // Fetch all records and find by id (simple approach)
    const res = await fetch(`api/stock.php?action=get_record&id=${id}`).then(r => r.json());
    if (res.error) {
        alertBox.innerHTML = '<div class="alert alert-error">' + res.error + '</div>';
        return;
    }
    alertBox.innerHTML = '';
    document.getElementById('record_id').value = res.id;
    document.getElementById('quantity').value  = res.quantity;
    document.getElementById('date').value      = res.transaction_date;
    document.getElementById('notes').value     = res.notes || '';
    document.getElementById('recordInfo').innerHTML =
        `<strong>Product:</strong> ${res.product_name} &nbsp;|&nbsp;
         <strong>Warehouse:</strong> ${res.warehouse_id == 1 ? 'Farm' : 'Paranaque'} &nbsp;|&nbsp;
         <strong>Type:</strong> ${res.record_type}`;
    document.getElementById('updateForm').style.display = 'block';
}

document.getElementById('updateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('alertBox');
    const data = {
        record_id: document.getElementById('record_id').value,
        quantity:  document.getElementById('quantity').value,
        date:      document.getElementById('date').value,
        notes:     document.getElementById('notes').value,
    };
    const res = await fetch('api/stock.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    if (json.success) {
        alertBox.innerHTML = '<div class="alert alert-success">Record updated successfully.</div>';
    } else {
        alertBox.innerHTML = '<div class="alert alert-error">' + (json.error || 'Error') + '</div>';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
