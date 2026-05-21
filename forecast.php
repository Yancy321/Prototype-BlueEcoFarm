<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">Sales Forecast (Linear Regression)</h1>

<div class="card" style="max-width:400px;margin-bottom:1.5rem;">
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
        <label for="periods">Periods to Forecast</label>
        <input type="number" id="periods" value="3" min="1" max="12">
    </div>
    <button class="btn btn-primary" onclick="loadForecast()">Generate Forecast</button>
</div>

<div id="alertBox"></div>

<div class="card" id="forecastCard" style="display:none;">
    <h2 id="forecastTitle"></h2>
    <canvas id="forecastChart"></canvas>
    <div id="forecastTable" style="margin-top:1.5rem;"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let forecastChart = null;

async function loadForecast() {
    const pid     = document.getElementById('product_id').value;
    const periods = document.getElementById('periods').value;
    const alertBox = document.getElementById('alertBox');
    alertBox.innerHTML = '';

    if (!pid) { alertBox.innerHTML = '<div class="alert alert-error">Please select a product.</div>'; return; }

    const res = await fetch(`api/forecast.php?action=predict&product_id=${pid}&periods=${periods}`);
    const json = await res.json();

    if (json.error) {
        alertBox.innerHTML = '<div class="alert alert-error">' + json.error + '</div>';
        document.getElementById('forecastCard').style.display = 'none';
        return;
    }

    const productName = document.getElementById('product_id').selectedOptions[0].text;
    document.getElementById('forecastTitle').textContent = `Forecast: ${productName}`;
    document.getElementById('forecastCard').style.display = 'block';

    // Build chart labels and datasets
    const histLabels = json.historical.map(d => d.date);
    const histData   = json.historical.map(d => d.y);
    const forecastLabels = json.forecasts.map(d => 'Period +' + (d.period - (json.historical.length - 1)));
    const forecastData   = json.forecasts.map(d => d.predicted_qty);

    const allLabels = [...histLabels, ...forecastLabels];
    const histFull  = [...histData,   ...new Array(forecastLabels.length).fill(null)];
    const forecastFull = [...new Array(histLabels.length).fill(null), ...forecastData];

    if (forecastChart) forecastChart.destroy();
    forecastChart = new Chart(document.getElementById('forecastChart'), {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: [
                {
                    label: 'Historical Outgoing',
                    data: histFull,
                    borderColor: '#2e7d32',
                    backgroundColor: '#2e7d3233',
                    tension: 0.3,
                    spanGaps: false,
                },
                {
                    label: 'Forecast',
                    data: forecastFull,
                    borderColor: '#e65100',
                    backgroundColor: '#e6510033',
                    borderDash: [6, 4],
                    tension: 0.3,
                    spanGaps: false,
                },
            ],
        },
        options: { responsive: true },
    });

    // Forecast table
    let tableHtml = '<table><thead><tr><th>Period</th><th>Predicted Qty</th></tr></thead><tbody>';
    json.forecasts.forEach(f => {
        tableHtml += `<tr><td>+${f.period - (json.historical.length - 1)}</td><td>${f.predicted_qty}</td></tr>`;
    });
    tableHtml += '</tbody></table>';
    document.getElementById('forecastTable').innerHTML = tableHtml;
}
</script>

<?php require_once 'includes/footer.php'; ?>
