<?php
require_once 'src/Database.php';
$pdo      = Database::getInstance();
$products = $pdo->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1rem;color:#2e7d32;">Forecast Calendar</h1>

<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <label style="font-weight:600;font-size:0.9rem;">Filter by Product:</label>
        <select id="productFilter" style="padding:0.45rem 0.7rem;border-radius:6px;border:1px solid #ccc;font-size:0.9rem;">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span id="loadingIndicator" style="font-size:0.85rem;color:#888;display:none;">Loading events…</span>
    </div>
</div>

<div class="card">
    <div id="calendar"></div>
</div>

<!-- Event detail panel -->
<div id="eventPanel" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 4px 24px rgba(0,0,0,0.2);
     z-index:300;min-width:280px;max-width:400px;">
    <h3 id="panelTitle" style="color:#2e7d32;margin-bottom:0.75rem;font-size:1rem;"></h3>
    <div id="panelBody" style="font-size:0.9rem;line-height:1.7;"></div>
    <button class="btn btn-primary" style="margin-top:1rem;width:100%;" onclick="closePanel()">Close</button>
</div>
<div id="panelOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:299;"
     onclick="closePanel()"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
/* Insufficient-data events styled gray */
.fc-event.insufficient-data {
    background-color: #9e9e9e !important;
    border-color: #757575 !important;
    color: #fff !important;
    font-style: italic;
}
/* Normal forecast events use green theme */
.fc-event.forecast-event {
    background-color: #2e7d32 !important;
    border-color: #1b5e20 !important;
    color: #fff !important;
}
#calendar {
    min-height: 600px;
}
</style>

<script>
let calendar;
let currentProductId = '';

function buildEventsUrl(info) {
    const start = new Date(info.start);
    const year  = start.getFullYear();
    const month = start.getMonth() + 1; // FullCalendar gives start of visible range
    let url = `api/calendar.php?action=events&year=${year}&month=${month}`;
    if (currentProductId) url += `&product_id=${encodeURIComponent(currentProductId)}`;
    return url;
}

document.addEventListener('DOMContentLoaded', function() {
    const calEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth',
        },
        events: function(info, successCallback, failureCallback) {
            const start = new Date(info.start);
            const year  = start.getFullYear();
            // FullCalendar's range start for a month view is the first visible day,
            // which may be in the previous month. Use the middle of the range instead.
            const mid   = new Date((info.start.getTime() + info.end.getTime()) / 2);
            const month = mid.getMonth() + 1;

            document.getElementById('loadingIndicator').style.display = 'inline';

            let url = `api/calendar.php?action=events&year=${year}&month=${month}`;
            if (currentProductId) url += `&product_id=${encodeURIComponent(currentProductId)}`;

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    if (!Array.isArray(data)) { successCallback([]); return; }

                    const events = data.map(ev => ({
                        id:            ev.id,
                        title:         ev.title,
                        start:         ev.start,
                        extendedProps: ev.extendedProps || {},
                        classNames:    ev.extendedProps && ev.extendedProps.insufficientData
                            ? ['insufficient-data']
                            : ['forecast-event'],
                    }));
                    successCallback(events);
                })
                .catch(err => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    console.error('Calendar fetch error:', err);
                    failureCallback(err);
                });
        },
        eventClick: function(info) {
            const ev    = info.event;
            const props = ev.extendedProps;

            document.getElementById('panelTitle').textContent = ev.title;

            if (props.insufficientData) {
                document.getElementById('panelBody').innerHTML =
                    `<p><strong>Status:</strong> <span style="color:#888;">Insufficient historical data</span></p>
                     <p><strong>Data Points Available:</strong> ${props.dataPoints ?? 0}</p>
                     <p style="color:#888;font-size:0.85rem;margin-top:0.5rem;">
                         At least 5 historical outgoing records are needed to generate a forecast.
                     </p>`;
            } else {
                document.getElementById('panelBody').innerHTML =
                    `<p><strong>Date:</strong> ${ev.startStr}</p>
                     <p><strong>Predicted Quantity:</strong> ${(props.predictedQty ?? 0).toLocaleString()}</p>
                     <p><strong>Historical Data Points:</strong> ${props.dataPoints ?? 0}</p>`;
            }

            document.getElementById('eventPanel').style.display   = 'block';
            document.getElementById('panelOverlay').style.display = 'block';
        },
        eventDidMount: function(info) {
            // Ensure class names are applied
            const props = info.event.extendedProps;
            if (props.insufficientData) {
                info.el.classList.add('insufficient-data');
                info.el.classList.remove('forecast-event');
            } else {
                info.el.classList.add('forecast-event');
            }
        },
    });

    calendar.render();

    // Product filter change
    document.getElementById('productFilter').addEventListener('change', function() {
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
