<?php
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">SMS Alert Log</h1>

<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
        <h2 style="margin:0;">Dispatch History</h2>
        <button class="btn btn-primary" style="padding:0.4rem 1rem;font-size:0.85rem;" onclick="loadLog(1)">↻ Refresh</button>
    </div>

    <table id="logTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Rule ID</th>
                <th>Recipient</th>
                <th>Message</th>
                <th>Status</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
    </table>

    <div class="pagination" id="pagination" style="margin-top:1rem;"></div>
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
        tbody.innerHTML = `<tr><td colspan="6" style="color:#c62828;">${escHtml(data.error)}</td></tr>`;
        return;
    }

    const rows = data.rows || data;
    const total = data.total || rows.length;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="color:#888;">No SMS log entries yet.</td></tr>';
        renderPagination(0, page);
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const statusColor = r.status === 'success' ? '#2e7d32' : '#c62828';
        const statusBg    = r.status === 'success' ? '#e8f5e9' : '#ffebee';
        const badge = `<span style="background:${statusBg};color:${statusColor};padding:0.2rem 0.6rem;border-radius:12px;font-size:0.8rem;font-weight:600;">${escHtml(r.status)}</span>`;
        return `<tr>
            <td>${r.id}</td>
            <td>${r.alert_rule_id}</td>
            <td style="font-size:0.85rem;">${escHtml(r.recipient)}</td>
            <td style="font-size:0.8rem;color:#555;" title="${escHtml(r.message)}">${escHtml(truncate(r.message, 60))}</td>
            <td>${badge}</td>
            <td style="font-size:0.85rem;white-space:nowrap;">${escHtml(r.dispatched_at)}</td>
        </tr>`;
    }).join('');

    renderPagination(total, page);
}

function renderPagination(total, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const pag = document.getElementById('pagination');

    let html = '';
    if (page > 1) {
        html += `<a href="#" onclick="loadLog(${page - 1});return false;" class="btn btn-primary" style="padding:0.35rem 0.8rem;font-size:0.85rem;">← Prev</a>`;
    }
    html += `<span style="padding:0.35rem 0.8rem;font-size:0.85rem;color:#555;">Page ${page} of ${totalPages}</span>`;
    if (page < totalPages) {
        html += `<a href="#" onclick="loadLog(${page + 1});return false;" class="btn btn-primary" style="padding:0.35rem 0.8rem;font-size:0.85rem;">Next →</a>`;
    }
    pag.innerHTML = html;
}

loadLog(1);
</script>

<?php require_once 'includes/footer.php'; ?>
