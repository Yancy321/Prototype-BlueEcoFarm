<?php
/**
 * staff_sidebar.php — Blue Eco Farm Staff Portal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sidebarUser    = $_SESSION['user'] ?? [];
$sidebarName    = !empty($sidebarUser['full_name'])
    ? $sidebarUser['full_name']
    : (!empty($sidebarUser['username']) ? $sidebarUser['username'] : 'Staff');
$sidebarRole    = ucfirst($sidebarUser['role'] ?? 'staff');
$sidebarInitial = strtoupper(substr($sidebarName, 0, 1));
?>

<div class="sidebar">

    <!-- BRAND -->
    <div class="brand">
        <div class="brand-logo">🌿</div>
        <div>
            <h3 class="staff-brand-title">Blue Eco Farm</h3>
            <p class="staff-brand-sub">Staff Operations</p>
        </div>
    </div>

    <!-- NAVIGATION -->
    <div class="nav-links">
        <a href="staff_dashboard.php" class="<?= ($currentPage == 'dashboard') ? 'active' : '' ?>">
            🏠 Home
        </a>
        <a href="stock_in.php" class="<?= ($currentPage == 'stock') ? 'active' : '' ?>">
            📦 Stock In/Out
        </a>
        <a href="transfer.php" class="<?= ($currentPage == 'transfers') ? 'active' : '' ?>">
            ⇄ Transfers
        </a>
        <a href="supplies.php" class="<?= ($currentPage == 'supplies') ? 'active' : '' ?>">
            📋 Supplies
        </a>
    </div>

    <!-- DATE WIDGET -->
    <div class="staff-date-widget">
        <small>Today</small><br>
        <strong><?= date('l, M j') ?></strong>
    </div>

    <!-- USER PROFILE -->
    <div class="staff-user-section">
        <div class="staff-user-row">
            <div class="staff-avatar"><?= $sidebarInitial ?></div>
            <div class="staff-user-info">
                <div class="staff-name"><?= htmlspecialchars($sidebarName) ?></div>
                <div class="staff-role"><?= htmlspecialchars($sidebarRole) ?></div>
            </div>
        </div>
        <a href="logout.php" class="staff-logout">Log out</a>
    </div>

</div>
