<?php
/**
 * sidebar.php — Blue Eco Farm Partner Portal
 * Reusable sidebar include.
 *
 * USAGE: include 'sidebar.php';
 *
 * Expects these variables to already be set (usually from session/DB queries):
 *   $currentPage        — string slug of active page:
 *                         'dashboard' | 'place_order' | 'orders' | 'waitlist' | 'profile'
 *   $businessName       — string, distributor business name
 *   $tier               — string, e.g. 'Silver' | 'Gold' | 'Platinum'
 *   $region             — string, distributor region
 *   $pendingOrders      — int, count of pending advance orders (for badge)
 *   $unnotifiedWaitlist — int, count of unnotified waitlist entries (for badge)
 */
?>
<style>
/* ===========================
   SIDEBAR — Blue Eco Farm
=========================== */
:root {
    --green-dark:  #1e3a1a;
    --green-mid:   #2d5a27;
    --green-light: #4a8c42;
    --accent:      #6abf5e;
    --sidebar-w:   260px;
}

.sidebar {
    width: var(--sidebar-w);
    background: var(--green-dark);
    position: fixed;
    top: 0; left: 0; bottom: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
}

/* ── Brand ── */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 28px 24px 24px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    flex-shrink: 0;
}
.brand-icon {
    width: 40px; height: 40px;
    background: var(--green-light);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.brand-name {
    font-family: 'DM Serif Display', serif;
    font-size: 1.05rem;
    color: #fff;
    line-height: 1.2;
}
.brand-sub {
    font-size: .72rem;
    color: var(--accent);
    font-weight: 500;
    letter-spacing: .5px;
    margin-top: 2px;
}

/* ── Navigation ── */
.sidebar-nav {
    flex: 1;
    padding: 20px 0;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 24px;
    color: rgba(255,255,255,.6);
    text-decoration: none;
    font-size: .9rem;
    font-weight: 500;
    transition: background .2s, color .2s;
    border-left: 3px solid transparent;
}
.nav-item:hover {
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.9);
}
.nav-item.active {
    background: rgba(106,191,94,.12);
    color: var(--accent);
    border-left-color: var(--accent);
}
.nav-icon {
    width: 22px;
    text-align: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.nav-label { flex: 1; }
.nav-badge {
    margin-left: auto;
    background: var(--accent);
    color: var(--green-dark);
    font-size: .7rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    flex-shrink: 0;
}

/* ── Divider & Section Labels ── */
.nav-divider {
    height: 1px;
    background: rgba(255,255,255,.07);
    margin: 10px 24px;
}
.nav-section-label {
    padding: 10px 24px 4px;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: rgba(255,255,255,.25);
    text-transform: uppercase;
}

/* ── Footer / User Info ── */
.sidebar-footer {
    padding: 16px 24px 20px;
    border-top: 1px solid rgba(255,255,255,.08);
    flex-shrink: 0;
}
.dist-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.dist-avatar {
    width: 36px; height: 36px;
    background: var(--green-light);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.dist-name {
    font-size: .85rem;
    font-weight: 600;
    color: rgba(255,255,255,.85);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dist-meta {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 2px;
}
.dist-region {
    font-size: .72rem;
    color: rgba(255,255,255,.35);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tier-badge {
    display: inline-block;
    font-size: .65rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    flex-shrink: 0;
}
.tier-Silver   { background: #c0c0c0; color: #333; }
.tier-Gold     { background: #f5c842; color: #5a3e00; }
.tier-Platinum { background: #b5d5f5; color: #1a3a5c; }

.sidebar-logout {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 8px 0 0;
    border-top: 1px solid rgba(255,255,255,.06);
    color: rgba(255,255,255,.35);
    font-size: .8rem;
    text-decoration: none;
    transition: color .2s;
}
.sidebar-logout:hover { color: rgba(255,255,255,.7); }
</style>

<aside class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">🌿</div>
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
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Home</span>
        </a>

        <div class="nav-section-label">Orders</div>

        <a href="place_order.php"
           class="nav-item <?= ($currentPage === 'place_order') ? 'active' : '' ?>">
            <span class="nav-icon">🛒</span>
            <span class="nav-label">Place Order</span>
        </a>

        <a href="my_orders.php"
           class="nav-item <?= ($currentPage === 'orders') ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-label">My Orders</span>
            <?php if (!empty($pendingOrders) && $pendingOrders > 0): ?>
                <span class="nav-badge"><?= (int)$pendingOrders ?></span>
            <?php endif; ?>
        </a>

        <a href="waitlist.php"
           class="nav-item <?= ($currentPage === 'waitlist') ? 'active' : '' ?>">
            <span class="nav-icon">⏳</span>
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
            <div style="overflow:hidden;">
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
            <span>🚪</span> Log out
        </a>
    </div>

</aside>