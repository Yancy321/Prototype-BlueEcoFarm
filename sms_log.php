<?php require_once 'includes/header.php'; ?>

<h1 class="page-title">SMS Alert Log</h1>

<div class="card">
    <div class="card-header-row">
        <h2>Dispatch History</h2>
        <button class="btn btn-primary btn-sm" onclick="loadLog(1)">↻ Refresh</button>
    </div>

    <table id="logTable">
        <thead>
            <tr>
                <th>ID</th><th>Rule ID</th><th>Recipient</th>
                <th>Message</th><th>Status</th><th>Timestamp</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
    </table>

    <div class="pagination" id="pagination"></div>
</div>

<script>
let currentPage = 1;
const perPage   = 20;

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function truncate(str, max) {
    if (!str) return '';
    return str.length > max ? str.substring(0, max) + '…' : str;
}

async function loadLog(page) {
    currentPage = page;
    const res  = await fetch(`api/sms_log.php?action=list&page=${page}&per_page=${perPage}`);
    const data = await res.json();
    const tbody = document.querySelector('#logTable tbody');

    if (data.error) {
        tbody.innerHTML = `<tr><td colspan="6" class="cell-sm" style="color:#c62828;">${escHtml(data.error)}</td></tr>`;
        return;
    }

    const rows  = data.rows || data;
    const total = data.total || rows.length;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="cell-sm" style="color:#888;">No SMS log entries yet.</td></tr>';
        renderPagination(0, page);
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const badgeClass = r.status === 'success' ? 'sms-badge-success' : 'sms-badge-failure';
        return `<tr>
            <td>${r.id}</td>
            <td>${r.alert_rule_id}</td>
            <td class="cell-sm">${escHtml(r.recipient)}</td>
            <td class="cell-sm-muted" title="${escHtml(r.message)}">${escHtml(truncate(r.message, 60))}</td>
            <td><span class="${badgeClass}">${escHtml(r.status)}</span></td>
            <td class="cell-nowrap">${escHtml(r.dispatched_at)}</td>
        </tr>`;
    }).join('');

    renderPagination(total, page);
}

function renderPagination(total, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const pag = document.getElementById('pagination');
    let html = '';
    if (page > 1)
        html += `<a href="#" onclick="loadLog(${page-1});return false;" class="btn btn-primary btn-sm">← Prev</a>`;
    html += `<span class="cell-sm" style="color:#555;padding:0.35rem 0.8rem;">Page ${page} of ${totalPages}</span>`;
    if (page < totalPages)
        html += `<a href="#" onclick="loadLog(${page+1});return false;" class="btn btn-primary btn-sm">Next →</a>`;
    pag.innerHTML = html;
}

loadLog(1);
</script>

<?php require_once 'includes/footer.php'; ?>
