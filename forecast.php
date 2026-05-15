<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 class="page-title">Demand Forecast</h1>

<!-- Controls -->
<div class="forecast-controls-card">
    <div class="forecast-controls-grid">
        <div class="form-group">
            <label for="product_id">Product</label>
            <select id="product_id">
                <option value="">— Select product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="periods">Periods ahead <span class="label-hint">(months)</span></label>
            <input type="number" id="periods" value="3" min="1" max="12">
        </div>
    </div>
    <button class="btn btn-primary forecast-btn" onclick="loadForecast()">
        Generate Forecast
    </button>
</div>

<div id="alertBox"></div>

<!-- Results -->
<div id="forecastCard" class="forecast-hidden">

    <!-- Model badge + title row -->
    <div class="forecast-title-row">
        <h2 id="forecastTitle" class="forecast-title"></h2>
        <span id="modelBadge" class="model-badge"></span>
    </div>

    <!-- Summary cards -->
    <div id="summaryCards" class="forecast-summary-grid"></div>

    <!-- Chart -->
    <div class="card forecast-chart-card">
        <div class="forecast-chart-header">
            <div class="forecast-legend">
                <span class="legend-item">
                    <span class="legend-line legend-line--actual"></span>Actual
                </span>
                <span class="legend-item">
                    <span class="legend-line legend-line--forecast"></span>Forecast
                </span>
                <span class="legend-item legend-item--prophet" id="legendConfidence">
                    <span class="legend-band"></span>80% Confidence
                </span>
            </div>
        </div>
        <canvas id="forecastChart"></canvas>
    </div>

    <!-- Forecast table -->
    <div class="card">
        <h2 class="forecast-table-title">Monthly Breakdown</h2>
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
        document.getElementById('forecastCard').classList.add('forecast-hidden');
        return;
    }

    const isProphet     = json.method === 'prophet';
    const productName   = document.getElementById('product_id').selectedOptions[0].text;
    const forecastCard  = document.getElementById('forecastCard');
    const modelBadge    = document.getElementById('modelBadge');
    const legendConf    = document.getElementById('legendConfidence');

    document.getElementById('forecastTitle').textContent =
        `${productName} — ${periods} month forecast`;

    // Model badge
    modelBadge.textContent  = isProphet ? '🔮 Facebook Prophet' : '📐 Hybrid Statistical';
    modelBadge.className    = 'model-badge ' + (isProphet ? 'model-badge--prophet' : 'model-badge--hybrid');

    // Show/hide confidence legend
    legendConf.style.display = isProphet ? '' : 'none';

    forecastCard.classList.remove('forecast-hidden');

    const forecastQtys = json.forecasts.map(f => f.predicted_qty);
    const avgForecast  = forecastQtys.reduce((a, b) => a + b, 0) / forecastQtys.length;
    const maxForecast  = Math.max(...forecastQtys);
    const histAvg      = json.historical.reduce((a, b) => a + b.y, 0) / json.historical.length;
    const trendPct     = histAvg > 0 ? Math.round(((avgForecast - histAvg) / histAvg) * 100) : 0;
    const trendLabel   = trendPct > 0 ? `+${trendPct}% vs history` : `${trendPct}% vs history`;
    const trendColor   = trendPct > 5 ? '#c62828' : trendPct < -5 ? '#1565c0' : '#2e7d32';

    // Summary cards
    document.getElementById('summaryCards').innerHTML = `
        <div class="card forecast-stat-card">
            <div class="forecast-stat-label">Data Points</div>
            <div class="forecast-stat-value forecast-stat-value--green">${json.historical.length}</div>
            <div class="forecast-stat-sub">records used</div>
        </div>
        <div class="card forecast-stat-card">
            <div class="forecast-stat-label">Avg Forecast</div>
            <div class="forecast-stat-value forecast-stat-value--orange">${Math.round(avgForecast)}</div>
            <div class="forecast-stat-sub">units / month</div>
        </div>
        <div class="card forecast-stat-card">
            <div class="forecast-stat-label">Peak Month</div>
            <div class="forecast-stat-value forecast-stat-value--blue">${Math.round(maxForecast)}</div>
            <div class="forecast-stat-sub">units (highest)</div>
        </div>
        <div class="card forecast-stat-card">
            <div class="forecast-stat-label">Trend</div>
            <div class="forecast-stat-value" style="font-size:1.3rem;color:${trendColor};">${trendLabel}</div>
            <div class="forecast-stat-sub">demand change</div>
        </div>
    `;

    // Chart datasets
    const histLabels     = json.historical.map(d => d.date);
    const histData       = json.historical.map(d => d.y);
    const forecastLabels = json.forecasts.map((f, i) =>
        f.forecast_date ? f.forecast_date : `Month +${i + 1}`
    );
    const forecastData   = json.forecasts.map(d => Math.round(d.predicted_qty));
    const allLabels      = [...histLabels, ...forecastLabels];
    const histFull       = [...histData, ...new Array(forecastLabels.length).fill(null)];
    const forecastFull   = [...new Array(histLabels.length).fill(null), ...forecastData];

    const datasets = [
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
    ];

    // Prophet confidence interval bands
    if (isProphet && json.forecasts[0]?.upper !== undefined) {
        const upperFull = [...new Array(histLabels.length).fill(null),
                           ...json.forecasts.map(f => Math.round(f.upper))];
        const lowerFull = [...new Array(histLabels.length).fill(null),
                           ...json.forecasts.map(f => Math.round(f.lower))];

        datasets.push({
            label: 'Upper bound',
            data: upperFull,
            borderColor: 'rgba(230,81,0,0.25)',
            backgroundColor: 'rgba(230,81,0,0.08)',
            borderDash: [3, 3],
            pointRadius: 0,
            fill: '+1',
            spanGaps: false,
        });
        datasets.push({
            label: 'Lower bound',
            data: lowerFull,
            borderColor: 'rgba(230,81,0,0.25)',
            backgroundColor: 'rgba(230,81,0,0.08)',
            borderDash: [3, 3],
            pointRadius: 0,
            fill: false,
            spanGaps: false,
        });
    }

    if (forecastChart) forecastChart.destroy();
    forecastChart = new Chart(document.getElementById('forecastChart'), {
        type: 'line',
        data: { labels: allLabels, datasets },
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
                ${isProphet ? '<th>80% Confidence Range</th>' : ''}
                <th>vs Historical Avg</th>
                <th>Action</th>
            </tr></thead><tbody>`;

    json.forecasts.forEach((f, i) => {
        const qty      = Math.round(f.predicted_qty);
        const diff     = qty - Math.round(histAvg);
        const label    = f.forecast_date ? f.forecast_date : `Month +${i + 1}`;
        const diffLabel = diff > 0
            ? `<span class="forecast-diff forecast-diff--high">+${diff} above avg</span>`
            : diff < 0
            ? `<span class="forecast-diff forecast-diff--low">${diff} below avg</span>`
            : `<span class="forecast-diff forecast-diff--avg">On average</span>`;
        const action = qty > histAvg * 1.15
            ? '<span class="forecast-action forecast-action--restock">Restock early</span>'
            : qty < histAvg * 0.85
            ? '<span class="forecast-action forecast-action--normal">Normal restock</span>'
            : '<span class="forecast-action forecast-action--schedule">On schedule</span>';

        const confidenceCell = isProphet && f.lower !== undefined
            ? `<td class="forecast-confidence">${Math.round(f.lower)} – ${Math.round(f.upper)} units</td>`
            : '';

        tableHtml += `<tr>
            <td><strong>${label}</strong></td>
            <td class="forecast-qty">${qty.toLocaleString()} units</td>
            ${confidenceCell}
            <td>${diffLabel}</td>
            <td>${action}</td>
        </tr>`;
    });

    tableHtml += '</tbody></table></div>';
    document.getElementById('forecastTable').innerHTML = tableHtml;
}
</script>

<?php require_once 'includes/footer.php'; ?>
