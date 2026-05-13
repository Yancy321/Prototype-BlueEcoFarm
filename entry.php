<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --green-deep:   #1a3a16;
        --green-dark:   #2d5a27;
        --green-mid:    #4a8c42;
        --green-light:  #e8f0e6;
        --green-pale:   #f4faf2;
        --blue-dark:    #0f3d6e;
        --blue-mid:     #1a6bbf;
        --blue-light:   #e6f1fb;
        --cream:        #fafdf8;
        --text-dark:    #1a2e18;
        --text-muted:   #6b7c69;
        --border:       #dde8db;
    }

    html, body {
        height: 100%;
    }

    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--green-light);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow-x: hidden;
    }

    /* ── BACKGROUND PATTERN ── */
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background-image:
            radial-gradient(circle at 15% 20%, rgba(74,140,66,.10) 0%, transparent 50%),
            radial-gradient(circle at 85% 75%, rgba(45,90,39,.08) 0%, transparent 45%),
            radial-gradient(circle at 50% 50%, rgba(255,255,255,.3) 0%, transparent 60%);
        pointer-events: none;
        z-index: 0;
    }

    /* leaf deco */
    body::after {
        content: '';
        position: fixed;
        bottom: -60px; right: -60px;
        width: 340px; height: 340px;
        background: radial-gradient(circle, rgba(45,90,39,.09) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
        z-index: 0;
    }

    .page {
        position: relative;
        z-index: 1;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 20px;
        gap: 0;
    }

    /* ── HEADER ── */
    .header {
        text-align: center;
        margin-bottom: 44px;
        animation: fadeUp .5s ease both;
    }

    .logo-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 72px; height: 72px;
        background: var(--green-dark);
        border-radius: 20px;
        font-size: 2rem;
        margin-bottom: 16px;
        box-shadow: 0 4px 20px rgba(45,90,39,.25);
    }

    .header h1 {
        font-family: 'DM Serif Display', serif;
        font-size: 2rem;
        color: var(--green-deep);
        font-weight: 400;
        line-height: 1.15;
        margin-bottom: 6px;
    }

    .header h1 em {
        font-style: italic;
        color: var(--green-mid);
    }

    .header p {
        font-size: .9rem;
        color: var(--text-muted);
        font-weight: 400;
    }

    /* ── PROMPT ── */
    .prompt-label {
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #9aab98;
        text-align: center;
        margin-bottom: 16px;
        animation: fadeUp .5s .1s ease both;
    }

    /* ── ROLE CARDS ── */
    .roles {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        width: 100%;
        max-width: 540px;
        animation: fadeUp .5s .15s ease both;
    }

    .role-card {
        position: relative;
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: 18px;
        padding: 28px 22px 24px;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        transition: border-color .2s, box-shadow .2s, transform .15s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        overflow: hidden;
    }

    .role-card::before {
        content: '';
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity .2s;
        border-radius: inherit;
    }

    .role-card.green::before { background: var(--green-pale); }
    .role-card.blue::before  { background: var(--blue-light); }

    .role-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(30,58,26,.13);
    }
    .role-card:hover::before { opacity: 1; }
    .role-card.green:hover { border-color: var(--green-mid); }
    .role-card.blue:hover  { border-color: var(--blue-mid); }

    .role-card:active { transform: translateY(-1px); }

    .role-icon-wrap {
        position: relative;
        z-index: 1;
        width: 62px; height: 62px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.7rem;
        transition: transform .2s;
    }

    .role-card:hover .role-icon-wrap { transform: scale(1.08); }

    .green .role-icon-wrap { background: var(--green-light); }
    .blue  .role-icon-wrap { background: var(--blue-light); }

    .role-card-title {
        position: relative;
        z-index: 1;
        font-weight: 600;
        font-size: 1rem;
        color: var(--text-dark);
        line-height: 1.2;
    }

    .role-card-sub {
        position: relative;
        z-index: 1;
        font-size: .77rem;
        color: var(--text-muted);
        line-height: 1.5;
    }

    .role-badge {
        position: relative;
        z-index: 1;
        display: inline-block;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 3px 10px;
        border-radius: 20px;
        margin-top: 2px;
    }

    .green .role-badge {
        background: rgba(74,140,66,.13);
        color: var(--green-dark);
    }

    .blue .role-badge {
        background: rgba(26,107,191,.12);
        color: var(--blue-dark);
    }

    .role-arrow {
        position: absolute;
        bottom: 14px; right: 18px;
        font-size: .85rem;
        color: var(--border);
        transition: color .2s, transform .2s;
        z-index: 1;
    }

    .role-card:hover .role-arrow {
        transform: translate(2px, -2px);
    }

    .green:hover .role-arrow { color: var(--green-mid); }
    .blue:hover  .role-arrow  { color: var(--blue-mid); }

    /* ── INFO PANELS (shown after selection) ── */
    .info-panel {
        display: none;
        width: 100%;
        max-width: 540px;
        margin-top: 16px;
        border-radius: 14px;
        padding: 18px 20px;
        animation: slideIn .25s ease both;
    }

    .info-panel.green-panel {
        background: var(--green-pale);
        border: 1px solid #c4dfc0;
    }

    .info-panel.blue-panel {
        background: var(--blue-light);
        border: 1px solid #b5d4f4;
    }

    .info-panel.show { display: flex; flex-direction: column; gap: 12px; }

    .panel-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: .83rem;
    }

    .panel-dot {
        width: 20px; height: 20px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .65rem; font-weight: 700;
        flex-shrink: 0; margin-top: 1px;
    }

    .green-panel .panel-dot { background: rgba(74,140,66,.18); color: var(--green-dark); }
    .blue-panel  .panel-dot { background: rgba(26,107,191,.18); color: var(--blue-dark); }
    .panel-dot.done { background: rgba(45,90,39,.2); }

    .panel-text { color: #4a5e48; line-height: 1.55; }
    .panel-text strong { color: var(--text-dark); }
    .blue-panel .panel-text { color: #1e3f6e; }
    .blue-panel .panel-text strong { color: var(--blue-dark); }

    /* ── CTA BUTTONS ── */
    .cta-wrap {
        width: 100%;
        max-width: 540px;
        margin-top: 10px;
        animation: fadeUp .2s ease both;
    }

    .btn-cta {
        display: block;
        width: 100%;
        padding: 13px;
        border: none;
        border-radius: 12px;
        font-family: 'DM Sans', sans-serif;
        font-size: .95rem;
        font-weight: 600;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        transition: background .2s, transform .1s;
    }

    .btn-cta:active { transform: scale(.98); }
    .btn-green { background: var(--green-dark); color: #fff; }
    .btn-green:hover { background: var(--green-deep); }
    .btn-blue  { background: var(--blue-mid); color: #fff; }
    .btn-blue:hover  { background: var(--blue-dark); }

    /* ── FOOTER NOTE ── */
    .footer-note {
        margin-top: 36px;
        font-size: .78rem;
        color: #9aab98;
        text-align: center;
        animation: fadeUp .5s .3s ease both;
    }

    .footer-note a { color: var(--green-mid); text-decoration: none; font-weight: 600; }
    .footer-note a:hover { text-decoration: underline; }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 480px) {
        .roles { grid-template-columns: 1fr; max-width: 320px; }
        .header h1 { font-size: 1.65rem; }
        .cta-wrap { max-width: 320px; }
        .info-panel { max-width: 320px; }
    }
</style>
</head>
<body>

<div class="page">

    <!-- HEADER -->
    <div class="header">
        <div class="logo-mark">🌿</div>
        <h1>Blue <em>Eco</em> Farm</h1>
        <p>Distributor &amp; Staff Portal</p>
    </div>

    <!-- PROMPT -->
    <p class="prompt-label">How would you like to continue?</p>

    <!-- ROLE CARDS -->
    <div class="roles">

        <div class="role-card green" id="card-dist" onclick="select('dist')" role="button" tabindex="0" aria-label="I am a distributor">
            <div class="role-icon-wrap"><i class="bi bi-truck" style="font-size:1.7rem;color:#2d5a27"></i></div>
            <div class="role-card-title">Distributor</div>
            <div class="role-card-sub">New partner? Apply for a distributor account.</div>
            <div class="role-badge">Apply now</div>
            <span class="role-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </div>

        <div class="role-card blue" id="card-staff" onclick="select('staff')" role="button" tabindex="0" aria-label="I am a staff or admin">
            <div class="role-icon-wrap"><i class="bi bi-person-badge" style="font-size:1.7rem;color:#1a6bbf"></i></div>
            <div class="role-card-title">Staff / Admin</div>
            <div class="role-card-sub">Already have an account from admin? Log in here.</div>
            <div class="role-badge">Log in</div>
            <span class="role-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </div>

    </div>

    <!-- INFO PANEL: Distributor -->
    <div class="info-panel green-panel" id="panel-dist">
        <div class="panel-row">
            <div class="panel-dot done"><i class="bi bi-check"></i></div>
            <div class="panel-text"><strong>Fill out the registration form</strong> — provide your business and account details.</div>
        </div>
        <div class="panel-row">
            <div class="panel-dot">2</div>
            <div class="panel-text"><strong>Wait for admin review</strong> — your application will be checked by the Blue Eco Farm admin.</div>
        </div>
        <div class="panel-row">
            <div class="panel-dot">3</div>
            <div class="panel-text"><strong>Access granted</strong> — once approved, you can log in to the distributor portal.</div>
        </div>
    </div>

    <!-- INFO PANEL: Staff -->
    <div class="info-panel blue-panel" id="panel-staff">
        <div class="panel-row">
            <div class="panel-dot done" style="background:rgba(26,107,191,.18);color:var(--blue-dark)"><i class="bi bi-check"></i></div>
            <div class="panel-text"><strong>No registration needed.</strong> Your account was already created by the admin.</div>
        </div>
        <div class="panel-row">
            <div class="panel-dot" style="background:rgba(26,107,191,.18);color:var(--blue-dark)"><i class="bi bi-arrow-right"></i></div>
            <div class="panel-text">Just head to the login page and use your credentials provided by the admin.</div>
        </div>
    </div>

    <!-- CTA BUTTON -->
    <div class="cta-wrap" id="cta-dist" style="display:none">
        <a href="registration.php" class="btn-cta btn-green">Register</a>
    </div>

    <div class="cta-wrap" id="cta-staff" style="display:none">
        <a href="login.php" class="btn-cta btn-blue">Go to Login</a>
    </div>

    <!-- FOOTER -->
    <p class="footer-note">
        Already a distributor with an approved account? <a href="login.php">Log in here</a>
    </p>

</div>

<script>
    let current = null;

    function select(role) {
        // deselect previous
        document.querySelectorAll('.role-card').forEach(c => {
            c.style.borderColor = '';
            c.style.boxShadow   = '';
        });
        document.querySelectorAll('.info-panel').forEach(p => p.classList.remove('show'));
        document.querySelectorAll('.cta-wrap').forEach(b => b.style.display = 'none');

        if (current === role) {
            current = null;
            return;
        }
        current = role;

        const card  = document.getElementById('card-' + role);
        const panel = document.getElementById('panel-' + role);
        const cta   = document.getElementById('cta-' + role);

        if (role === 'dist') {
            card.style.borderColor = 'var(--green-mid)';
            card.style.boxShadow   = '0 6px 24px rgba(74,140,66,.18)';
        } else {
            card.style.borderColor = 'var(--blue-mid)';
            card.style.boxShadow   = '0 6px 24px rgba(26,107,191,.18)';
        }

        panel.classList.add('show');
        cta.style.display = 'block';

        // scroll to panel on mobile
        if (window.innerWidth < 600) {
            setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        }
    }

    // keyboard support
    document.querySelectorAll('.role-card').forEach(card => {
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const role = card.id.replace('card-', '');
                select(role);
            }
        });
    });
</script>
</body>
</html>