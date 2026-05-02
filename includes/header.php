<?php
require_once __DIR__ . '/../src/AuthManager.php';
AuthManager::requireLogin();
$currentUser = AuthManager::currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue Eco Farm — Inventory System</title>
    <link rel="stylesheet" href="/blue-eco-farm/assets/css/style.css">
    <style>
        body { margin:0; font-family:'Segoe UI',sans-serif; background:#eef4ee; }

        .topbar {
            position: fixed !important;
            top:0; left:0; right:0;
            height: 64px;
            background: linear-gradient(135deg,#1b5e20,#2e7d32,#388e3c) !important;
            display: flex !important;
            align-items: center;
            padding: 0 1.75rem;
            gap: 1rem;
            z-index: 9999 !important;
            box-shadow: 0 3px 12px rgba(0,0,0,0.3);
        }
        .topbar-brand { color:#fff; font-size:1.25rem; font-weight:700; flex:1; letter-spacing:0.02em; }
        .sidebar-toggle { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); color:#c8e6c9; font-size:1.3rem; cursor:pointer; padding:0.35rem 0.7rem; border-radius:6px; transition:background .2s; }
        .sidebar-toggle:hover { background:rgba(255,255,255,0.25); }
        .topbar-user { display:flex; align-items:center; gap:0.75rem; }
        .user-avatar { width:34px; height:34px; background:rgba(255,255,255,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#c8e6c9; font-size:0.95rem; border:1px solid rgba(255,255,255,0.2); }
        .user-name { color:#e8f5e9; font-weight:500; font-size:0.92rem; }
        .logout-btn { color:#ef9a9a; text-decoration:none; padding:0.3rem 0.8rem; border:1px solid rgba(239,154,154,0.4); border-radius:20px; font-size:0.82rem; transition:background .2s; }
        .logout-btn:hover { background:rgba(239,154,154,0.15); }

        .layout { display:flex !important; padding-top:64px; min-height:100vh; }

        .sidebar {
            position: fixed !important;
            top: 64px !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 255px !important;
            background: #fff !important;
            border-right: 1px solid #dceadc;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000 !important;
            box-shadow: 3px 0 16px rgba(0,0,0,0.08);
            transition: transform .3s ease;
        }
        .sidebar.collapsed { transform: translateX(-255px) !important; }

        .sidenav { padding:1.25rem 0 3rem; }
        .nav-group { margin-bottom:0.25rem; }
        .nav-group-label {
            display:block;
            font-size:0.68rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:0.1em;
            color:#b0bfb0;
            padding:1rem 1.5rem 0.35rem;
        }
        .nav-item {
            display:flex !important;
            align-items:center;
            gap:0.75rem;
            padding:0.8rem 1.5rem;
            color:#4a5a4a;
            text-decoration:none;
            font-size:0.95rem;
            font-weight:500;
            border-left:3px solid transparent;
            transition:background .2s,color .2s,border-color .2s,padding-left .2s;
        }
        .nav-item:hover { background:#f1f8f1; color:#2e7d32; border-left-color:#81c784; padding-left:1.8rem; }
        .nav-item.active { background:linear-gradient(90deg,#e8f5e9,#f4faf4); color:#1b5e20; border-left-color:#2e7d32; font-weight:700; }
        .nav-dot { width:7px; height:7px; border-radius:50%; background:#c8e6c9; flex-shrink:0; transition:background .2s; }
        .nav-item:hover .nav-dot { background:#2e7d32; }
        .nav-item.active .nav-dot { background:#2e7d32; }

        .main-content {
            flex:1 !important;
            margin-left:255px !important;
            padding:2.25rem 2.5rem !important;
            min-width:0;
            transition:margin-left .3s ease;
        }
        .main-content.expanded { margin-left:0 !important; }
    </style>
</head>
<body>

<header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">&#9776;</button>
    <div class="topbar-brand">Blue Eco Farm</div>
    <div class="topbar-user">
        <div class="user-avatar">&#9679;</div>
        <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

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
                <span class="nav-group-label">Alerts</span>
                <a href="alerts.php" class="nav-item <?= $currentPage === 'alerts.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> Alert Rules
                </a>
                <a href="sms_log.php" class="nav-item <?= $currentPage === 'sms_log.php' ? 'active' : '' ?>">
                    <span class="nav-dot"></span> SMS Log
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
