<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: distributor_dashboard.php');
    exit;
}

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result  = null;   // null = not searched yet
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (!$username) {
        $error = 'Please enter your username.';
    } else {
        // Join users + distributors to get status, name, business, submitted date
        $stmt = $conn->prepare("
            SELECT
                u.full_name,
                u.created_at   AS submitted_at,
                d.business_name,
                d.tier,
                d.status
            FROM users u
            JOIN distributors d ON d.user_id = u.id
            WHERE u.username = ?
              AND u.role = 'distributor'
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = 'No distributor application found for that username.';
        } else {
            $result = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Application Status | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --green-deep:  #1a3a16;
        --green-dark:  #2d5a27;
        --green-mid:   #4a8c42;
        --green-light: #e8f0e6;
        --green-pale:  #f4faf2;
        --text-dark:   #1a2e18;
        --text-muted:  #6b7c69;
        --border:      #dde8db;
    }

    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--green-light);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 16px;
        position: relative;
        overflow-x: hidden;
    }

    body::before {
        content: '';
        position: fixed; inset: 0;
        background-image:
            radial-gradient(circle at 10% 15%, rgba(74,140,66,.10) 0%, transparent 50%),
            radial-gradient(circle at 90% 80%, rgba(45,90,39,.07) 0%, transparent 45%);
        pointer-events: none;
        z-index: 0;
    }

    .page {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 440px;
        display: flex;
        flex-direction: column;
        gap: 0;
        animation: fadeUp .45s ease both;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── BRAND ── */
    .brand {
        text-align: center;
        margin-bottom: 28px;
    }

    .brand-logo {
        display: inline-flex; align-items: center; justify-content: center;
        width: 54px; height: 54px;
        background: var(--green-dark);
        border-radius: 15px;
        font-size: 1.5rem; color: #fff;
        margin-bottom: 12px;
        box-shadow: 0 4px 18px rgba(45,90,39,.22);
    }

    .brand h1 {
        font-family: 'DM Serif Display', serif;
        font-size: 1.55rem; color: var(--green-deep);
        font-weight: 400; line-height: 1.2;
    }

    .brand p {
        font-size: .82rem; color: var(--text-muted);
        margin-top: 4px;
    }

    /* ── CARD ── */
    .card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 8px 40px rgba(30,58,26,.12);
        padding: 34px 38px 38px;
    }

    .card-title {
        font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .7px;
        color: #9aab98; margin-bottom: 18px;
    }

    /* ── INPUT ── */
    .input-wrap {
        position: relative;
        margin-bottom: 14px;
    }

    .input-wrap i {
        position: absolute; left: 13px; top: 50%;
        transform: translateY(-50%);
        color: #b0bfae; font-size: 1rem;
        pointer-events: none;
    }

    .input-wrap input {
        width: 100%;
        padding: 11px 13px 11px 38px;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: .9rem; color: var(--text-dark);
        background: #f9fdf8; outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    .input-wrap input:focus {
        border-color: var(--green-mid);
        box-shadow: 0 0 0 3px rgba(74,140,66,.12);
        background: #fff;
    }

    .input-wrap input::placeholder { color: #b0bfae; }

    /* ── BUTTON ── */
    .btn-check {
        width: 100%;
        padding: 12px;
        background: var(--green-dark);
        color: #fff;
        border: none; border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: .92rem; font-weight: 600;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: background .2s, transform .1s;
    }
    .btn-check:hover  { background: var(--green-deep); }
    .btn-check:active { transform: scale(.98); }
    .btn-check i { font-size: 1rem; }

    /* ── ALERT ── */
    .alert {
        display: flex; align-items: flex-start; gap: 9px;
        padding: 11px 14px; border-radius: 10px;
        font-size: .84rem; font-weight: 500;
        margin-bottom: 16px;
    }
    .alert i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
    .alert-error {
        background: #fee2e2; color: #991b1b;
        border: 1px solid #fca5a5;
    }

    /* ── RESULT CARD ── */
    .result {
        margin-top: 24px;
        border-radius: 14px;
        border: 1.5px solid var(--border);
        overflow: hidden;
        animation: slideIn .3s ease both;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* status header band */
    .result-header {
        padding: 20px 22px 18px;
        display: flex; align-items: center; gap: 14px;
    }

    .result-header.pending  { background: #fffbeb; border-bottom: 1px solid #fde68a; }
    .result-header.approved { background: #f0faf0; border-bottom: 1px solid #c4dfc0; }
    .result-header.rejected { background: #fff1f1; border-bottom: 1px solid #fca5a5; }

    .status-icon {
        width: 46px; height: 46px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; flex-shrink: 0;
    }

    .pending  .status-icon { background: #fef3c7; color: #b45309; }
    .approved .status-icon { background: #d1f0cb; color: var(--green-dark); }
    .rejected .status-icon { background: #fee2e2; color: #991b1b; }

    .status-label-wrap {}

    .status-label {
        font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .6px;
        margin-bottom: 2px;
    }

    .pending  .status-label { color: #92400e; }
    .approved .status-label { color: var(--green-dark); }
    .rejected .status-label { color: #991b1b; }

    .status-name {
        font-size: 1rem; font-weight: 600;
        color: var(--text-dark);
    }

    /* details rows */
    .result-body {
        padding: 18px 22px;
        background: #fff;
        display: flex; flex-direction: column; gap: 12px;
    }

    .detail-row {
        display: flex; align-items: flex-start; gap: 11px;
        font-size: .84rem;
    }

    .detail-row i {
        font-size: 1rem; color: #b0bfae;
        flex-shrink: 0; margin-top: 1px;
    }

    .detail-label {
        font-size: .72rem; color: var(--text-muted);
        display: block; margin-bottom: 1px;
    }

    .detail-value {
        font-weight: 600; color: var(--text-dark);
    }

    .tier-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: .75rem; font-weight: 700;
        background: #e8f0e6; color: var(--green-dark);
    }
    .tier-pill i { font-size: .8rem; }

    /* message box per status */
    .status-msg {
        margin: 0 22px 20px;
        padding: 12px 15px;
        border-radius: 10px;
        font-size: .82rem;
        line-height: 1.65;
        display: flex; align-items: flex-start; gap: 9px;
    }
    .status-msg i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    .msg-pending  { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .msg-approved { background: var(--green-pale); color: #2d5a27; border: 1px solid #c4dfc0; }
    .msg-rejected { background: #fff1f1; color: #991b1b; border: 1px solid #fca5a5; }

    /* ── FOOTER LINKS ── */
    .footer-links {
        text-align: center;
        margin-top: 22px;
        font-size: .82rem;
        color: var(--text-muted);
        display: flex; flex-direction: column; gap: 6px;
    }
    .footer-links a {
        color: var(--green-dark); font-weight: 600;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .footer-links a:hover { text-decoration: underline; }
    .footer-links a i { font-size: .9rem; }

    .divider-dot { color: #c4d4c2; margin: 0 6px; }

    @media (max-width: 480px) {
        .card { padding: 28px 22px 32px; }
    }
</style>
</head>
<body>

<div class="page">

    <!-- BRAND -->
    <div class="brand">
        <div class="brand-logo">🌿</div>
        <h1>Blue Eco Farm</h1>
        <p>Distributor Application Status</p>
    </div>

    <!-- LOOKUP CARD -->
    <div class="card">
        <div class="card-title">Check your application</div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="input-wrap">
                <i class="bi bi-person"></i>
                <input
                    type="text"
                    name="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    autocomplete="username"
                    autofocus
                >
            </div>
            <button type="submit" class="btn-check">
                <i class="bi bi-search"></i>
                Check Status
            </button>
        </form>

        <!-- ── RESULT ── -->
        <?php if ($result): ?>
        <?php
            $status   = $result['status'];   // pending | approved | rejected
            $submitted = date('F j, Y', strtotime($result['submitted_at']));

            $iconMap = [
                'pending'  => 'bi-hourglass-split',
                'approved' => 'bi-check-circle',
                'rejected' => 'bi-x-circle',
            ];
            $labelMap = [
                'pending'  => 'Pending Review',
                'approved' => 'Approved',
                'rejected' => 'Not Approved',
            ];
            $msgMap = [
                'pending'  => [
                    'icon' => 'bi-info-circle',
                    'class' => 'msg-pending',
                    'text' => 'Your application is currently under review by the Blue Eco Farm admin. Please check back later or wait to be contacted.',
                ],
                'approved' => [
                    'icon' => 'bi-check-circle',
                    'class' => 'msg-approved',
                    'text' => 'Your application has been approved! You can now log in to the distributor portal using your username and password.',
                ],
                'rejected' => [
                    'icon' => 'bi-x-circle',
                    'class' => 'msg-rejected',
                    'text' => 'Unfortunately your application was not approved at this time. Please contact the Blue Eco Farm admin for more information.',
                ],
            ];
        ?>
        <div class="result">

            <!-- STATUS HEADER -->
            <div class="result-header <?= $status ?>">
                <div class="status-icon">
                    <i class="bi <?= $iconMap[$status] ?>"></i>
                </div>
                <div class="status-label-wrap">
                    <div class="status-label"><?= $labelMap[$status] ?></div>
                    <div class="status-name"><?= htmlspecialchars($result['full_name']) ?></div>
                </div>
            </div>

            <!-- DETAILS -->
            <div class="result-body">
                <div class="detail-row">
                    <i class="bi bi-building"></i>
                    <div>
                        <span class="detail-label">Business name</span>
                        <span class="detail-value"><?= htmlspecialchars($result['business_name']) ?></span>
                    </div>
                </div>
                <div class="detail-row">
                    <i class="bi bi-award"></i>
                    <div>
                        <span class="detail-label">Distributor tier</span>
                        <span class="tier-pill">
                            <i class="bi bi-patch-check"></i>
                            <?= htmlspecialchars($result['tier']) ?>
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <i class="bi bi-calendar3"></i>
                    <div>
                        <span class="detail-label">Application submitted</span>
                        <span class="detail-value"><?= $submitted ?></span>
                    </div>
                </div>
            </div>

            <!-- STATUS MESSAGE -->
            <div class="status-msg <?= $msgMap[$status]['class'] ?>">
                <i class="bi <?= $msgMap[$status]['icon'] ?>"></i>
                <?= $msgMap[$status]['text'] ?>
            </div>

        </div>
        <?php endif; ?>

    </div>

    <!-- FOOTER LINKS -->
    <div class="footer-links">
        <?php if ($result && $result['status'] === 'approved'): ?>
            <div>
                <a href="login.php"><i class="bi bi-box-arrow-in-right"></i> Go to Login</a>
            </div>
        <?php endif; ?>
        <div>
            <a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
            <span class="divider-dot">·</span>
            <a href="registration.php">Register a new account</a>
        </div>
    </div>

</div>

</body>
</html>