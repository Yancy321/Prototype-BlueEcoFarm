<div class="sidebar">

    <div class="brand">
        <div class="brand-logo">🍃</div>

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

    <div class="nav-links">

        <a href="dashboard.php"
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

        <a href="logs.php"
           class="<?php echo ($currentPage == 'logs') ? 'active' : ''; ?>">
            📝 Movement Logs
        </a>

        <a href="profile.php"
           class="<?php echo ($currentPage == 'profile') ? 'active' : ''; ?>">
            👤 Profile Settings
        </a>

    </div>

    <div style="
        margin-top:auto;
        background:#f3f4f6;
        padding:15px;
        border-radius:12px;
    ">

        <small>Today</small><br>

        <strong>
            <?php echo date('l, M j'); ?>
        </strong>

    </div>

</div>