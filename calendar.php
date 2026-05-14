<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 class="page-title">Forecast Calendar</h1>

<!-- Filter + Legend bar -->
<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            <label style="font-weight:600;font-size:0.88rem;">Product:</label>
            <select id="productFilter" style="padding:0.4rem 0.65rem;border-radius:6px;border:1px solid #ccc;font-size:0.88rem;">
                <option value="">All Products</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <span id="loadingIndicator" style="font-size:0.82rem;color:#888;display:none;">Loading…</span>
        </div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:0.78rem;color:#555;">
            <span style="display:flex;align-items:center;gap:5px;">
                <span style="width:12px;height:12px;border-radius:2px;background:#c62828;display:inline-block;"></span> High demand
            </span>
            <span style="display:flex;align-items:center;gap:5px;">
                <span style="width:12px;height:12px;border-radius:2px;background:#2e7d32;display:inline-block;"></span> Normal
            </span>
            <span style="display:flex;align-items:center;gap:5px;">
                <span style="width:12px;height:12px;border-radius:2px;background:#1565c0;display:inline-block;"></span> Low demand
            </span>
            <span style="display:flex;align-items:center;gap:5px;">
                <span style="width:12px;height:12px;border-radius:2px;background:#9e9e9e;display:inline-block;"></span> No data
            </span>
        </div>
    </div>
</div>

<!-- Calendar -->
<div class="card">
    <div id="calendar"></div>
</div>

<!-- Event detail panel -->
<div id="eventPanel" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 8px 32px rgba(0,0,0,0.18);
     z-index:300;min-width:290px;max-width:400px;width:90%;">
    <div style="margin-bottom:0.85rem;padding-bottom:0.75rem;border-bottom:1px solid #eee;">
        <h3 id="panelTitle" style="margin:0 0 0.35rem;font-size:1rem;color:#1b5e20;"></h3>
        <div id="panelBadge"></div>
    </div>
    <div id="panelBody" style="font-size:0.88rem;line-height:1.8;"></div>
    <button class="btn btn-primary" style="margin-top:1rem;width:100%;" onclick="closePanel()">Close</button>
</div>
<div id="panelOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:299;"
     onclick="closePanel()"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
.fc-event.demand-high        { background-color:#c62828!important;border-color:#b71c1c!important;color:#fff!important;font-weight:600; }
.fc-event.demand-normal      { background-color:#2e7d32!important;border-color:#1b5e20!important;color:#fff!important; }
.fc-event.demand-low         { background-color:#1565c0!important;border-color:#0d47a1!important;color:#fff!important; }
.fc-event.demand-insufficient{ background-color:#9e9e9e!important;border-color:#757575!important;color:#fff!important;font-style:italic; }
#calendar { min-height:600px; }
.fc-daygrid-event { padding:2px 4px!important;font-size:0.78rem!important; }
</style>

<script>
let calendar;
let currentProductId = '';

document.addEventListener('DOMContentLoaded', function () {
    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth' },
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
                high:         { text:'⚠️ High Demand',      color:'#c62828', bg:'#ffebee' },
                normal:       { text:'✅ Normal Demand',     color:'#2e7d32', bg:'#e8f5e9' },
                low:          { text:'📦 Low Demand',        color:'#1565c0', bg:'#e3f2fd' },
                insufficient: { text:'⚪ Insufficient Data', color:'#757575', bg:'#f5f5f5' },
            };
            const b = badgeMap[level] ?? badgeMap.normal;
            document.getElementById('panelBadge').innerHTML =
                `<span style="background:${b.bg};color:${b.color};padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">${b.text}</span>`;

            if (props.insufficientData) {
                document.getElementById('panelBody').innerHTML =
                    `<p><strong>Data available:</strong> ${props.dataPoints ?? 0} / 5 needed</p>
                     <p style="color:#888;font-size:0.82rem;">Record more outgoing transactions to enable forecasting.</p>`;
            } else {
                const qty    = (props.predictedQty ?? 0).toLocaleString();
                const avg    = (props.recentAvg ?? 0).toLocaleString();
                const advice = level === 'high'
                    ? 'Demand is above average — consider restocking early.'
                    : level === 'low'
                    ? 'Demand is below average — normal restocking is sufficient.'
                    : 'Demand is within normal range.';
                document.getElementById('panelBody').innerHTML =
                    `<p><strong>Predicted demand:</strong> <span style="font-size:1.15rem;font-weight:700;color:#e65100;">${qty} units</span></p>
                     <p><strong>Recent average:</strong> ${avg} units</p>
                     <p><strong>Based on:</strong> ${props.dataPoints ?? 0} records</p>
                     <div style="margin-top:0.6rem;padding:0.6rem 0.75rem;background:#f9fdf9;border-radius:7px;border-left:3px solid #2e7d32;font-size:0.82rem;color:#333;">${advice}</div>`;
            }
            document.getElementById('eventPanel').style.display   = 'block';
            document.getElementById('panelOverlay').style.display = 'block';
        },
    });
    calendar.render();
    document.getElementById('productFilter').addEventListener('change', function () {
        currentProductId = this.value;
        calendar.refetchEvents();
    });
});

function closePanel() {
    document.getElementById('eventPanel').style.display   = 'none';
    document.getElementById('panelOverlay').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
