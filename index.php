<?php require_once 'includes/header.php'; ?>

<h1 class="page-title">Inventory Dashboard</h1>

<div class="filter-bar">
    <label for="warehouseFilter">Warehouse:</label>
    <select id="warehouseFilter">
        <option value="">All Warehouses</option>
        <option value="1">Farm</option>
        <option value="2">Paranaque</option>
    </select>
</div>

<div class="card">
    <h2>Current Stock Levels</h2>
    <div id="stockGrid" class="stock-grid">
        <p>Loading...</p>
    </div>
</div>

<div class="charts-row">
    <div class="card">
        <h2>Stock In Over Time</h2>
        <canvas id="chartIn"></canvas>
    </div>
    <div class="card">
        <h2>Stock Out Over Time</h2>
        <canvas id="chartOut"></canvas>
    </div>
</div>

<!-- Batch Number Search -->
<div class="card" style="margin-top:1.5rem;">
    <h2>Search by Batch Number</h2>
    <div style="display:flex;gap:0.75rem;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;">
        <input type="text" id="batchInput" placeholder="Enter batch number e.g. BATCH-2026-001"
            style="flex:1;min-width:220px;padding:0.6rem 0.85rem;border:1.5px solid #d0dcd0;border-radius:8px;font-size:0.92rem;">
        <button class="btn btn-primary" onclick="searchBatch()">Search</button>
        <button class="btn btn-secondary" onclick="clearBatch()">Clear</button>
    </div>
    <div id="batchResult" style="display:none;">
        <div id="batchSummary" style="margin-bottom:0.75rem;font-size:0.88rem;color:#555;"></div>
        <div class="table-wrap">
            <table id="batchTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Batch No.</th>
                        <th>Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <div id="batchEmpty" style="display:none;color:#888;font-size:0.9rem;padding:0.5rem 0;">
        No records found for that batch number.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const COLORS = [
    '#2e7d32','#1565c0','#6a1b9a','#e65100','#00695c','#ad1457'
];

let chartIn = null, chartOut = null;

async function loadDashboard() {
    const wid = document.getElementById('warehouseFilter').value;
    const qs  = wid ? `?warehouse_id=${wid}` : '';

    // Stock totals
    const totals = await fetch(`api/stock.php?action=get_totals${wid ? '&warehouse_id='+wid : ''}`).then(r => r.json());
    renderStockGrid(totals, wid);

    // Chart data
    const chartData = await fetch(`api/chart_data.php${qs}`).then(r => r.json());
    renderCharts(chartData);
}

function renderStockGrid(totals, wid) {
    const grid = document.getElementById('stockGrid');
    if (!totals.length) { grid.innerHTML = '<p>No stock data yet.</p>'; return; }

    const map = {};
    totals.forEach(row => {
        const name = row.name;
        if (!map[name]) map[name] = { farm: 0, paranaque: 0 };
        if (row.warehouse_id == 1) map[name].farm     += (parseInt(row.current_stock) || 0);
        if (row.warehouse_id == 2) map[name].paranaque += (parseInt(row.current_stock) || 0);
    });

    let html = '';
    Object.entries(map).forEach(([product, stocks]) => {
        if (!wid) {
            // All warehouses — show one combined tile
            const total = stocks.farm + stocks.paranaque;
            html += `<div class="stock-tile">
                <div class="product-name">${product}</div>
                <div class="stock-qty">${total}</div>
                <div class="warehouse-label">All Warehouses</div>
            </div>`;
        } else if (wid == 1) {
            html += `<div class="stock-tile">
                <div class="product-name">${product}</div>
                <div class="stock-qty">${stocks.farm}</div>
                <div class="warehouse-label">Farm</div>
            </div>`;
        } else if (wid == 2) {
            html += `<div class="stock-tile">
                <div class="product-name">${product}</div>
                <div class="stock-qty">${stocks.paranaque}</div>
                <div class="warehouse-label">Paranaque</div>
            </div>`;
        }
    });
    grid.innerHTML = html;
}

function renderCharts(data) {
    const products = Object.keys(data);
    const allDatesIn  = new Set();
    const allDatesOut = new Set();

    products.forEach(p => {
        (data[p].incoming || []).forEach(d => allDatesIn.add(d.date));
        (data[p].outgoing || []).forEach(d => allDatesOut.add(d.date));
    });

    const labelsIn  = [...allDatesIn].sort();
    const labelsOut = [...allDatesOut].sort();

    const datasetsIn = products.map((p, i) => {
        const map = {};
        (data[p].incoming || []).forEach(d => map[d.date] = d.qty);
        return {
            label: p,
            data: labelsIn.map(d => map[d] || 0),
            borderColor: COLORS[i % COLORS.length],
            backgroundColor: COLORS[i % COLORS.length] + '33',
            tension: 0.3, fill: false,
        };
    });

    const datasetsOut = products.map((p, i) => {
        const map = {};
        (data[p].outgoing || []).forEach(d => map[d.date] = d.qty);
        return {
            label: p,
            data: labelsOut.map(d => map[d] || 0),
            borderColor: COLORS[i % COLORS.length],
            backgroundColor: COLORS[i % COLORS.length] + '33',
            tension: 0.3, fill: false,
        };
    });

    if (chartIn)  chartIn.destroy();
    if (chartOut) chartOut.destroy();

    chartIn  = new Chart(document.getElementById('chartIn'),  { type: 'line', data: { labels: labelsIn,  datasets: datasetsIn  }, options: { responsive: true } });
    chartOut = new Chart(document.getElementById('chartOut'), { type: 'line', data: { labels: labelsOut, datasets: datasetsOut }, options: { responsive: true } });
}

document.getElementById('warehouseFilter').addEventListener('change', loadDashboard);
loadDashboard();

// Batch search
async function searchBatch() {
    const batch = document.getElementById('batchInput').value.trim();
    if (!batch) return;

    const res  = await fetch(`api/stock.php?action=search_batch&batch_number=${encodeURIComponent(batch)}`);
    const rows = await res.json();

    document.getElementById('batchResult').style.display = 'none';
    document.getElementById('batchEmpty').style.display  = 'none';

    if (!Array.isArray(rows) || rows.length === 0) {
        document.getElementById('batchEmpty').style.display = 'block';
        return;
    }

    const totalQty = rows.reduce((s, r) => s + parseInt(r.quantity), 0);
    document.getElementById('batchSummary').innerHTML =
        `Found <strong>${rows.length}</strong> record(s) for batch <strong>${batch}</strong> — Total quantity: <strong>${totalQty}</strong>`;

    const tbody = document.querySelector('#batchTable tbody');
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${r.id}</td>
            <td>${r.product_name}</td>
            <td>${r.warehouse_id == 1 ? 'Farm' : 'Paranaque'}</td>
            <td><span style="color:${r.record_type==='incoming'?'#2e7d32':'#c62828'};font-weight:600;">${r.record_type}</span></td>
            <td>${r.quantity}</td>
            <td><span style="background:#e8f5e9;color:#1b5e20;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.8rem;font-weight:600;">${r.batch_number}</span></td>
            <td>${r.transaction_date}</td>
            <td>${r.notes || '—'}</td>
        </tr>
    `).join('');

    document.getElementById('batchResult').style.display = 'block';
}

function clearBatch() {
    document.getElementById('batchInput').value = '';
    document.getElementById('batchResult').style.display = 'none';
    document.getElementById('batchEmpty').style.display  = 'none';
}

document.getElementById('batchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') searchBatch();
});
</script>

<?php require_once 'includes/footer.php'; ?>
