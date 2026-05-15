<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 class="page-title">Forecast Calendar</h1>

<!-- Filter + Legend bar -->
<div class="cal-toolbar">
    <div class="cal-filter">
        <label for="productFilter">Product:</label>
        <select id="productFilter">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span id="loadingIndicator" class="cal-loading">Loading…</span>
    </div>
    <div class="cal-legend">
        <span class="cal-legend-item">
            <span class="cal-legend-dot cal-legend-dot--high"></span> High demand
        </span>
        <span class="cal-legend-item">
            <span class="cal-legend-dot cal-legend-dot--normal"></span> Normal
        </span>
        <span class="cal-legend-item">
            <span class="cal-legend-dot cal-legend-dot--low"></span> Low demand
        </span>
        <span class="cal-legend-item">
            <span class="cal-legend-dot cal-legend-dot--nodata"></span> No data
        </span>
    </div>
</div>

<!-- Calendar -->
<div class="card cal-card">
    <div id="calendar"></div>
</div>

<!-- Event detail panel -->
<div id="eventPanel" class="cal-panel">
    <div class="cal-panel-header">
        <h3 id="panelTitle" class="cal-panel-title"></h3>
        <div id="panelBadge"></div>
    </div>
    <div id="panelBody" class="cal-panel-body"></div>
    <button class="btn btn-primary cal-panel-btn" onclick="closePanel()">Close</button>
</div>
<div id="panelOverlay" class="cal-overlay" onclick="closePanel()"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
let calendar;
let currentProductId = '';

document.addEventListener('DOMContentLoaded', function () {
    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
        events: function (info, successCallback, failureCallback) {
            const mid   = new Date((info.start.getTime() + info.end.getTime()) / 2);
            const year  = mid.getFullYear();
            const month = mid.getMonth() + 1;
            document.getElementById('loadingIndicator').style.display = 'inline';
            let url = `api/calendar.php?action=events&year=${year}&month=${month}`;
            if (currentProductId) url += `&product_id=${encodeURIComponent(currentProductId)}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    if (!Array.isArray(data)) { successCallback([]); return; }
                    successCallback(data.map(ev => ({
                        id:            ev.id,
                        title:         ev.title,
                        start:         ev.start,
                        extendedProps: ev.extendedProps || {},
                        classNames:    [`demand-${ev.extendedProps?.level ?? 'normal'}`],
                    })));
                })
                .catch(err => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    failureCallback(err);
                });
        },
        eventClick: function (info) {
            const props = info.event.extendedProps;
            const name  = props.productName ?? info.event.title;
            document.getElementById('panelTitle').textContent = name;

            const level    = props.level ?? 'normal';
            const badgeMap = {
                high:         { text: 'High Demand',       cls: 'cal-panel-badge--high' },
                normal:       { text: 'Normal Demand',     cls: 'cal-panel-badge--normal' },
                low:          { text: 'Low Demand',        cls: 'cal-panel-badge--low' },
                insufficient: { text: 'Insufficient Data', cls: 'cal-panel-badge--insufficient' },
            };
            const b = badgeMap[level] ?? badgeMap.normal;
            document.getElementById('panelBadge').innerHTML =
                `<span class="cal-panel-badge ${b.cls}">${b.text}</span>`;

            if (props.insufficientData) {
                document.getElementById('panelBody').innerHTML =
                    `<p><strong>Data available:</strong> ${props.dataPoints ?? 0} / 5 needed</p>
                     <p class="cal-panel-advice">Record more outgoing transactions to enable forecasting.</p>`;
            } else {
                const qty    = (props.predictedQty ?? 0).toLocaleString();
                const avg    = (props.recentAvg ?? 0).toLocaleString();
                const advice = level === 'high'
                    ? 'Demand is above average — consider restocking early.'
                    : level === 'low'
                    ? 'Demand is below average — normal restocking is sufficient.'
                    : 'Demand is within normal range.';
                document.getElementById('panelBody').innerHTML =
                    `<p><strong>Predicted demand:</strong> <span class="cal-panel-qty">${qty} units</span></p>
                     <p><strong>Recent average:</strong> ${avg} units</p>
                     <p><strong>Based on:</strong> ${props.dataPoints ?? 0} records</p>
                     <div class="cal-panel-advice">${advice}</div>`;
            }

            document.getElementById('eventPanel').classList.add('open');
            document.getElementById('panelOverlay').classList.add('open');
        },
    });

    calendar.render();

    document.getElementById('productFilter').addEventListener('change', function () {
        currentProductId = this.value;
        calendar.refetchEvents();
    });
});

function closePanel() {
    document.getElementById('eventPanel').classList.remove('open');
    document.getElementById('panelOverlay').classList.remove('open');
}
</script>

<?php require_once 'includes/footer.php'; ?>
