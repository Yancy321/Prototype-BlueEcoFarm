<?php
/**
 * sidebar.php — Blue Eco Farm Partner Portal
 * Reusable sidebar include.
 */
?>
<link rel="stylesheet" href="assets/css/style.css">

<aside class="dist-sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon"></div>
        <div>
            <div class="brand-name">Blue Eco Farm</div>
            <div class="brand-sub">Partner Portal</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <div class="nav-section-label">Main</div>

        <a href="distributor_dashboard.php"
           class="nav-item <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
            <span class="nav-icon"></span>
            <span class="nav-label">Home</span>
        </a>

        <div class="nav-section-label">Orders</div>

        <a href="place_order.php"
           class="nav-item <?= ($currentPage === 'place_order') ? 'active' : '' ?>">
            <span class="nav-icon"></span>
            <span class="nav-label">Place Order</span>
        </a>

        <a href="my_orders.php"
           class="nav-item <?= ($currentPage === 'orders') ? 'active' : '' ?>">
            <span class="nav-icon"></span>
            <span class="nav-label">My Orders</span>
            <?php if (!empty($pendingOrders) && $pendingOrders > 0): ?>
                <span class="nav-badge"><?= (int)$pendingOrders ?></span>
            <?php endif; ?>
        </a>

        <a href="waitlist.php"
           class="nav-item <?= ($currentPage === 'waitlist') ? 'active' : '' ?>">
            <span class="nav-icon"></span>
            <span class="nav-label">Waitlist</span>
            <?php if (!empty($unnotifiedWaitlist) && $unnotifiedWaitlist > 0): ?>
                <span class="nav-badge"><?= (int)$unnotifiedWaitlist ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-divider"></div>

    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="dist-info">
            <div class="dist-avatar">
                <?= strtoupper(substr($businessName ?? 'D', 0, 1)) ?>
            </div>
            <div class="dist-info-text">
                <div class="dist-name"><?= htmlspecialchars($businessName ?? 'Distributor') ?></div>
                <div class="dist-meta">
                    <?php if (!empty($region)): ?>
                        <span class="dist-region"><?= htmlspecialchars($region) ?></span>
                    <?php endif; ?>
                    <span class="tier-badge tier-<?= htmlspecialchars($tier ?? 'Silver') ?>">
                        <?= htmlspecialchars($tier ?? 'Silver') ?>
                    </span>
                </div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout">
            <span></span> Log out
        </a>
    </div>

</aside>