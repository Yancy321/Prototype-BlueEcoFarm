<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<style>
  .page-header-panel {
    background: linear-gradient(135deg, #1b5e20 0%, #2d5a27 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
  }

  .page-header-panel h1 {
    font-size: 2rem;
    margin: 0 0 0.5rem 0;
    font-weight: 700;
  }

  .page-header-panel p {
    margin: 0;
    font-size: 0.95rem;
    opacity: 0.9;
  }

  .transfer-form-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
  }

  .transfer-form-section h2 {
    font-size: 1.3rem;
    color: #1b5e20;
    margin: 0 0 1.25rem 0;
  }

  .form-group {
    margin-bottom: 1rem;
  }

  .form-group label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #444;
    margin-bottom: 0.35rem;
  }

  .form-group input,
  .form-group select {
    width: 100%;
    padding: 0.6rem 0.85rem;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 0.95rem;
    box-sizing: border-box;
  }

  .btn-transfer {
    background: #2d5a27;
    color: white;
    border: none;
    padding: 0.75rem 1.75rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.3s ease;
  }

  .btn-transfer:hover {
    background: #1b5e20;
    transform: translateY(-2px);
  }

  #alertBox { margin-bottom: 1rem; }

  .transfer-list-section h2 {
    font-size: 1.3rem;
    color: #1b5e20;
    margin: 0 0 1rem 0;
  }

  /* Table Styles */
  .table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .search-box {
    padding: 0.65rem 1rem;
    border: 1px solid #ccc;
    border-radius: 8px;
    width: 280px;
    font-size: 0.95rem;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  }

  th {
    background: #1b5e20;
    color: white;
    padding: 1rem 0.9rem;
    text-align: left;
  }

  td {
    padding: 1rem 0.9rem;
    border-bottom: 1px solid #eee;
  }

  tr:last-child td {
    border-bottom: none;
  }

  .highlight-id {
    font-weight: 700;
    color: #1b5e20;
  }

  .no-results {
    text-align: center;
    padding: 3rem;
    color: #999;
    font-style: italic;
  }
</style>

<div class="page-header-panel">
  <div>
    <h1>Warehouse Transfers</h1>
    <p>Track inventory movement between Farm and Paranaque locations</p>
  </div>
</div>

<!-- Transfer Form -->
<div class="transfer-form-section">
  <h2>Transfer Stock: Farm → Paranaque</h2>
  <div id="alertBox"></div>
  <form id="transferForm">
    <div class="form-group">
      <label for="product_id">Product</label>
      <select id="product_id" name="product_id" required>
        <option value="">— Select product —</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="quantity">Quantity to Transfer</label>
      <input type="number" id="quantity" name="quantity" min="1" placeholder="0" required>
    </div>

    <div class="form-group">
      <label for="transfer_date">Transfer Date</label>
      <input type="date" id="transfer_date" name="transfer_date" required>
    </div>

    <button type="submit" class="btn-transfer">Transfer Stock</button>
  </form>
</div>

<!-- Recent Transfers with Filter -->
<div class="transfer-list-section">
  <h2>Recent Transfers</h2>
  
  <div class="table-header">
    <input type="text" id="searchInput" class="search-box" placeholder="Search by Product or ID...">
    <button onclick="clearFilter()" style="padding: 0.65rem 1.2rem; background:#f1f1f1; border:none; border-radius:8px; cursor:pointer;">
      Clear Filter
    </button>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Date</th>
        <th>From</th>
        <th>To</th>
      </tr>
    </thead>
    <tbody id="transferBody">
      <tr><td colspan="6" style="text-align:center; padding: 3rem; color: #999;">Loading transfers...</td></tr>
    </tbody>
  </table>
</div>

<script>
// Global variable to store all transfers
let allTransfers = [];

// Set today's date as default
document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];

async function loadTransfers() {
  try {
    const res = await fetch('api/transfer.php?action=list');
    allTransfers = await res.json();

    renderTable(allTransfers);
  } catch (err) {
    console.error(err);
    document.getElementById('transferBody').innerHTML = 
      `<tr><td colspan="6" class="no-results">Failed to load transfers.</td></tr>`;
  }
}

function renderTable(transfers) {
  const tbody = document.getElementById('transferBody');

  if (!transfers || transfers.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" class="no-results">No transfers found.</td></tr>`;
    return;
  }

  tbody.innerHTML = transfers.map(r => `
    <tr>
      <td><span class="highlight-id">TRF-${String(r.id).padStart(4, '0')}</span></td>
      <td>${r.product_name}</td>
      <td>${r.quantity} kg</td>
      <td>${new Date(r.transfer_date).toLocaleDateString('en-US')}</td>
      <td>Farm</td>
      <td>Paranaque</td>
    </tr>
  `).join('');
}

// Search Filter
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase().trim();
  
  if (!searchTerm) {
    renderTable(allTransfers);
    return;
  }

  const filtered = allTransfers.filter(r => 
    r.product_name.toLowerCase().includes(searchTerm) ||
    `trf-${String(r.id).padStart(4, '0')}`.includes(searchTerm)
  );

  renderTable(filtered);
});

function clearFilter() {
  document.getElementById('searchInput').value = '';
  renderTable(allTransfers);
}

// Form Submit (unchanged)
document.getElementById('transferForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const alertBox = document.getElementById('alertBox');
  const data = {
    product_id: document.getElementById('product_id').value,
    quantity:   document.getElementById('quantity').value,
    date:       document.getElementById('transfer_date').value,
  };
  const res  = await fetch('api/transfer.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  const json = await res.json();
  if (json.success) {
    alertBox.innerHTML = '<div class="alert alert-success">Transfer completed (ID: ' + json.id + ')</div>';
    this.reset();
    document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];
    loadTransfers();   // Refresh table after new transfer
  } else {
    alertBox.innerHTML = '<div class="alert alert-error">' + (json.error || 'Error') + '</div>';
  }
});

// Initial load
loadTransfers();
</script>

<?php require_once 'includes/footer.php'; ?>