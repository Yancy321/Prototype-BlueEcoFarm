<?php
require_once __DIR__ . '/../src/AuthManager.php';
AuthManager::requireStaff();
$currentUser = AuthManager::currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue Eco Farm — Inventory System</title>
    <link rel="stylesheet" href="/blue-eco-farm/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">&#9776;</button>
    <div class="topbar-brand">Blue Eco Farm</div>
    <div class="topbar-user">
        <button class="notif-btn" onclick="toggleNotifPanel()" title="Notifications">
            &#128276;
            <span class="notif-badge" id="notifBadge"></span>
        </button>
        <div class="user-avatar">&#9679;</div>
        <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<!-- Notification Panel -->
<div id="notifPanel">
    <div class="notif-header">
        <h3>Low Stock Alerts</h3>
        <span class="notif-mark-all" onclick="markAllRead()">Mark all as read</span>
    </div>
    <div class="notif-list" id="notifList">
        <div class="notif-empty">Loading...</div>
    </div>
</div>

<div class="layout">

    <aside class="sidebar" id="sidebar">
        <nav class="sidenav">

            <div class="nav-group">
                <span class="nav-group-label">Overview</span>
                <a href="index.php" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Dashboard
                </a>
            </div>

            <div class="nav-group">
                <span class="nav-group-label">Inventory</span>
                <a href="stocks.php" class="nav-item <?= $currentPage === 'stocks.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Stocks
                </a>
                <a href="transfer.php" class="nav-item <?= $currentPage === 'transfer.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Transfer
                </a>
                <a href="supplies.php" class="nav-item <?= $currentPage === 'supplies.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Supplies
                </a>
                <a href="transaction_log.php" class="nav-item <?= $currentPage === 'transaction_log.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Transaction Log
                </a>
            </div>

            <div class="nav-group">
                <span class="nav-group-label">Analytics</span>
                <a href="forecast.php" class="nav-item <?= $currentPage === 'forecast.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Forecast
                </a>
                <a href="calendar.php" class="nav-item <?= $currentPage === 'calendar.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Calendar
                </a>
                <a href="reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Reports
                </a>
            </div>

            <div class="nav-group">
                <span class="nav-group-label">Notifications</span>
                <a href="distributors.php" class="nav-item <?= $currentPage === 'distributors.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Distributors
                </a>
                <a href="orders.php" class="nav-item <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Distributor Orders
                </a>
            </div>

            <?php if ($currentUser['role'] === 'admin'): ?>
            <div class="nav-group">
                <span class="nav-group-label">Admin</span>
                <a href="users.php" class="nav-item <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Users
                </a>
            </div>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="main-content" id="mainContent">

<script>
// ── Sidebar toggle ──────────────────────────────────────
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('expanded');
}

// ── Notification bell ───────────────────────────────────
let notifPanelOpen = false;

async function loadNotifCount() {
    try {
        const res  = await fetch('api/notifications.php?action=unread_count');
        const data = await res.json();
        const badge = document.getElementById('notifBadge');
        if (data.count > 0) {
            badge.textContent = data.count > 99 ? '99+' : data.count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    } catch(e) {}
}

async function loadNotifications() {
    try {
        const res  = await fetch('api/notifications.php?action=list');
        const data = await res.json();
        const list = document.getElementById('notifList');

        if (!Array.isArray(data) || !data.length) {
            list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            return;
        }

        list.innerHTML = data.map(n => `
            <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="markRead(${n.id}, this)">
                <div class="notif-msg">&#9888; ${escHtml(n.message)}</div>
                <div class="notif-time">${n.created_at}</div>
            </div>
        `).join('');
    } catch(e) {}
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    notifPanelOpen = !notifPanelOpen;
    panel.classList.toggle('open', notifPanelOpen);
    if (notifPanelOpen) loadNotifications();
}

async function markRead(id, el) {
    el.classList.remove('unread');
    await fetch('api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
    loadNotifCount();
}

async function markAllRead() {
    await fetch('api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
    });
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    loadNotifCount();
}

// Close panel when clicking outside
document.addEventListener('click', function(e) {
    const panel = document.getElementById('notifPanel');
    const btn   = document.querySelector('.notif-btn');
    if (notifPanelOpen && !panel.contains(e.target) && !btn.contains(e.target)) {
        notifPanelOpen = false;
        panel.classList.remove('open');
    }
});

// Poll for new notifications every 60 seconds
loadNotifCount();
setInterval(loadNotifCount, 60000);
</script>
