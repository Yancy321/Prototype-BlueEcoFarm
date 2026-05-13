<?php
/**
 * staff_sidebar.php — Blue Eco Farm Staff Portal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sidebarUser = $_SESSION['user'] ?? [];

/* =========================
   SAFE FALLBACK (FIX)
========================= */
$sidebarName = !empty($sidebarUser['full_name']) 
    ? $sidebarUser['full_name'] 
    : (!empty($sidebarUser['username']) ? $sidebarUser['username'] : 'Staff');

$sidebarRole = ucfirst($sidebarUser['role'] ?? 'staff');
$sidebarInitial = strtoupper(substr($sidebarName, 0, 1));
?>

<div class="sidebar">

    <!-- BRAND -->
    <div class="brand">
        <div class="brand-logo">🌿</div>

        <div>
            <h3 style="margin:0;">Blue Eco Farm</h3>

            <p style="
                margin:0;
                font-size:0.8rem;
                color:var(--text-muted);
            ">
                Staff Operations
            </p>
        </div>
    </div>

    <!-- NAVIGATION -->
    <div class="nav-links">

        <a href="staff_dashboard.php"
           class="<?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
            🏠 Home
        </a>

        <a href="stock_in.php"
           class="<?php echo ($currentPage == 'stock') ? 'active' : ''; ?>">
            📦 Stock In/Out
        </a>

        <a href="transfer.php"
           class="<?php echo ($currentPage == 'transfers') ? 'active' : ''; ?>">
            ⇄ Transfers
        </a>

        <a href="supplies.php"
           class="<?php echo ($currentPage == 'supplies') ? 'active' : ''; ?>">
            📋 Supplies
        </a>

    </div>

    <!-- DATE WIDGET -->
    <div style="
        background:#f3f4f6;
        padding:15px;
        border-radius:12px;
        margin-bottom:12px;
    ">
        <small>Today</small><br>
        <strong><?php echo date('l, M j'); ?></strong>
    </div>

    <!-- USER PROFILE -->
    <div style="
        border-top:1px solid #e5e7eb;
        padding-top:14px;
    ">

        <div style="
            display:flex;
            align-items:center;
            gap:10px;
        ">

            <!-- Avatar -->
            <div style="
                width:36px;
                height:36px;
                background:#2d5a27;
                border-radius:50%;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:0.9rem;
                font-weight:700;
                color:#fff;
                flex-shrink:0;
            ">
                <?php echo $sidebarInitial; ?>
            </div>

            <!-- Name & Role -->
            <div style="overflow:hidden;">
                <div style="
                    font-size:0.85rem;
                    font-weight:600;
                    color:#1f2937;
                    white-space:nowrap;
                    overflow:hidden;
                    text-overflow:ellipsis;
                ">
                    <?php echo htmlspecialchars($sidebarName); ?>
                </div>

                <div style="
                    font-size:0.72rem;
                    color:#6b7280;
                ">
                    <?php echo htmlspecialchars($sidebarRole); ?>
                </div>
            </div>

        </div>

        <!-- LOGOUT -->
        <a href="logout.php" style="
            display:flex;
            align-items:center;
            gap:8px;
            margin-top:12px;
            padding-top:10px;
            border-top:1px solid #f3f4f6;
            color:#9ca3af;
            font-size:0.8rem;
            text-decoration:none;
            transition:color .2s;
        " 
        onmouseover="this.style.color='#374151'"
        onmouseout="this.style.color='#9ca3af'">
            Log out
        </a>

    </div>

</div>