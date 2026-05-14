<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 class="page-title">Demand Forecast</h1>

<!-- Controls -->
<div class="card" style="max-width:560px;margin-bottom:1.5rem;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label for="product_id">Product</label>
            <select id="product_id">
                <option value="">— Select product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label for="periods">Periods ahead <span style="font-weight:400;color:#888;font-size:0.78rem;">(months)</span></label>
            <input type="number" id="periods" value="3" min="1" max="12">
        </div>
    </div>
    <button class="btn btn-primary" onclick="loadForecast()" style="width:100%;margin-top:1rem;padding:0.75rem;">
        Generate Forecast
    </button>
</div>

<div id="alertBox"></div>

<!-- Results -->
<div id="forecastCard" style="display:none;">

    <!-- Summary cards -->
    <div id="summaryCards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;"></div>

    <!-- Chart -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
            <h2 id="forecastTitle" style="margin:0;font-size:1rem;"></h2>
            <div style="display:flex;gap:1.25rem;font-size:0.8rem;color:#555;">
                <span style="display:flex;align-items:center;gap:5px;">
                    <span style="width:18px;height:3px;background:#2e7d32;display:inline-block;border-radius:2px;"></span>
                    Actual
                </span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <span style="width:18px;height:3px;background:#e65100;display:inline-block;border-radius:2px;border-top:2px dashed #e65100;"></span>
                    Forecast
                </span>
            </div>
        </div>
        <canvas id="forecastChart"></canvas>
    </div>

    <!-- Forecast table -->
    <div class="card">
        <h2 style="margin-bottom:1rem;font-size:1rem;">Monthly Breakdown</h2>
        <div id="forecastTable"></div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let forecastChart = null;

async function loadForecast() {
    const pid      = document.getElementById('product_id').value;
    const periods  = parseInt(document.getElementById('periods').value) || 3;
    const alertBox = document.getElementById('alertBox');
    alertBox.innerHTML = '';

    if (!pid) {
        alertBox.innerHTML = '<div class="alert alert-error">Please select a product.</div>';
        return;
    }

    const res  = await fetch(`api/forecast.php?action=predict&product_id=${pid}&periods=${periods}`);
    const json = await res.json();

    if (json.error) {
        alertBox.innerHTML = `<div class="alert alert-error">${json.error}</div>`;
        document.getElementById('forecastCard').style.display = 'none';
        return;
    }

    const productName = document.getElementById('product_id').selectedOptions[0].text;
    document.getElementById('forecastTitle').textContent = `${productName} — ${periods} month forecast`;
    document.getElementById('forecastCard').style.display = 'block';

    const forecastQtys = json.forecasts.map(f => f.predicted_qty);
    const avgForecast  = forecastQtys.reduce((a, b) => a + b, 0) / forecastQtys.length;
    const maxForecast  = Math.max(...forecastQtys);
    const histAvg      = json.historical.reduce((a, b) => a + b.y, 0) / json.historical.length;
    const trendPct     = histAvg > 0 ? Math.round(((avgForecast - histAvg) / histAvg) * 100) : 0;
    const trendLabel   = trendPct > 0 ? `+${trendPct}% vs history` : `${trendPct}% vs history`;
    const trendColor   = trendPct > 5 ? '#c62828' : trendPct < -5 ? '#1565c0' : '#2e7d32';

    // Summary cards
    document.getElementById('summaryCards').innerHTML = `
        <div class="card" style="text-align:center;padding:1rem;">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:0.3rem;">Data Points</div>
            <div style="font-size:1.8rem;font-weight:700;color:#1b5e20;">${json.historical.length}</div>
            <div style="font-size:0.75rem;color:#aaa;">records used</div>
        </div>
        <div class="card" style="text-align:center;padding:1rem;">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:0.3rem;">Avg Forecast</div>
            <div style="font-size:1.8rem;font-weight:700;color:#e65100;">${Math.round(avgForecast)}</div>
            <div style="font-size:0.75rem;color:#aaa;">units / month</div>
        </div>
        <div class="card" style="text-align:center;padding:1rem;">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:0.3rem;">Peak Month</div>
            <div style="font-size:1.8rem;font-weight:700;color:#1565c0;">${Math.round(maxForecast)}</div>
            <div style="font-size:0.75rem;color:#aaa;">units (highest)</div>
        </div>
        <div class="card" style="text-align:center;padding:1rem;">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:0.3rem;">Trend</div>
            <div style="font-size:1.3rem;font-weight:700;color:${trendColor};">${trendLabel}</div>
            <div style="font-size:0.75rem;color:#aaa;">demand change</div>
        </div>
    `;

    // Chart
    const histLabels     = json.historical.map(d => d.date);
    const histData       = json.historical.map(d => d.y);
    const forecastLabels = json.forecasts.map((_, i) => `Month +${i + 1}`);
    const forecastData   = json.forecasts.map(d => Math.round(d.predicted_qty));
    const allLabels      = [...histLabels, ...forecastLabels];
    const histFull       = [...histData, ...new Array(forecastLabels.length).fill(null)];
    const forecastFull   = [...new Array(histLabels.length).fill(null), ...forecastData];

    if (forecastChart) forecastChart.destroy();
    forecastChart = new Chart(document.getElementById('forecastChart'), {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: [
                {
                    label: 'Actual outgoing',
                    data: histFull,
                    borderColor: '#2e7d32',
                    backgroundColor: 'rgba(46,125,50,0.07)',
                    tension: 0.3,
                    pointRadius: 4,
                    spanGaps: false,
                    fill: true,
                },
                {
                    label: 'Forecast',
                    data: forecastFull,
                    borderColor: '#e65100',
                    backgroundColor: 'rgba(230,81,0,0.07)',
                    borderDash: [6, 4],
                    tension: 0.3,
                    pointRadius: 5,
                    pointStyle: 'rectRot',
                    spanGaps: false,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} units`
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Units', color: '#aaa', font: { size: 11 } } },
                x: { title: { display: true, text: 'Period', color: '#aaa', font: { size: 11 } } }
            }
        },
    });

    // Table
    let tableHtml = `
        <div class="table-wrap"><table>
            <thead><tr>
                <th>Period</th>
                <th>Predicted Demand</th>
                <th>vs Historical Avg</th>
                <th>Action</th>
            </tr></thead><tbody>`;

    json.forecasts.forEach((f, i) => {
        const qty  = Math.round(f.predicted_qty);
        const diff = qty - Math.round(histAvg);
        const diffLabel = diff > 0
            ? `<span style="color:#c62828;font-weight:600;">+${diff} above avg</span>`
            : diff < 0
            ? `<span style="color:#1565c0;font-weight:600;">${diff} below avg</span>`
            : `<span style="color:#2e7d32;">On average</span>`;
        const action = qty > histAvg * 1.15
            ? '<span style="color:#c62828;font-weight:600;">Restock early</span>'
            : qty < histAvg * 0.85
            ? '<span style="color:#1565c0;">Normal restock</span>'
            : '<span style="color:#2e7d32;">On schedule</span>';

        tableHtml += `<tr>
            <td><strong>Month +${i + 1}</strong></td>
            <td style="font-weight:700;color:#e65100;font-size:1rem;">${qty.toLocaleString()} units</td>
            <td>${diffLabel}</td>
            <td>${action}</td>
        </tr>`;
    });

    tableHtml += '</tbody></table></div>';
    document.getElementById('forecastTable').innerHTML = tableHtml;
}
</script>

<?php require_once 'includes/footer.php'; ?>
