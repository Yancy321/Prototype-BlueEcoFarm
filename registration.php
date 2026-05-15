<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: distributor_dashboard.php');
    exit;
}

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$error   = '';
$success = false;
$submittedName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName      = trim($_POST['full_name']      ?? '');
    $username      = trim($_POST['username']       ?? '');
    $password      = $_POST['password']            ?? '';
    $confirmPass   = $_POST['confirm_password']    ?? '';
    $businessName  = trim($_POST['business_name']  ?? '');
    $region        = trim($_POST['region']         ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');

    if (!$fullName || !$username || !$password || !$confirmPass || !$businessName) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username is already taken. Please choose another.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $conn->begin_transaction();
            try {
                $stmtUser = $conn->prepare("
                    INSERT INTO users (username, password, full_name, role, created_at)
                    VALUES (?, ?, ?, 'distributor', NOW())
                ");
                $stmtUser->bind_param("sss", $username, $hashed, $fullName);
                $stmtUser->execute();
                $newUserId = $conn->insert_id;
                $stmtUser->close();

                $stmtDist = $conn->prepare("
                    INSERT INTO distributors (user_id, business_name, tier, status, region, contact_number, phone, is_active)
                    VALUES (?, ?, 'Silver', 'pending', ?, ?, ?, 0)
                ");
                $stmtDist->bind_param("issss", $newUserId, $businessName, $region, $contactNumber, $contactNumber);
                $stmtDist->execute();
                $stmtDist->close();

                $conn->commit();
                $success      = true;
                $submittedName = $fullName;

            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Registration failed. Please try again.';
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DM Sans', sans-serif;
        background: #e8f0e6;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 8px 40px rgba(30,58,26,.13);
        padding: 40px 44px;
        width: 100%;
        max-width: 460px;
    }

    /* BRAND */
    .brand { text-align: center; margin-bottom: 28px; }
    .brand-logo {
        display: inline-flex; align-items: center; justify-content: center;
        width: 52px; height: 52px; background: #2d5a27;
        border-radius: 14px; margin-bottom: 10px;
        font-size: 1.4rem; color: #fff;
    }
    .brand h1 {
        font-family: 'DM Serif Display', serif;
        font-size: 1.6rem; color: #2d5a27; font-weight: 400;
    }
    .brand p { font-size: .82rem; color: #6b7c69; margin-top: 2px; }

    /* SECTION LABEL */
    .section-label {
        font-size: .72rem; font-weight: 700;
        color: #9aab98; text-transform: uppercase;
        letter-spacing: .6px; margin: 20px 0 12px;
    }

    /* FORM */
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { margin-bottom: 14px; }

    label {
        display: block; font-size: .82rem;
        font-weight: 600; color: #1a2e18; margin-bottom: 5px;
    }
    label .req { color: #dc2626; margin-left: 2px; }

    input[type="text"],
    input[type="password"] {
        width: 100%; padding: 10px 13px;
        border: 1.5px solid #dde8db; border-radius: 10px;
        font-family: 'DM Sans', sans-serif; font-size: .88rem;
        color: #1a2e18; background: #f9fdf8; outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    input[type="text"]:focus,
    input[type="password"]:focus {
        border-color: #4a8c42;
        box-shadow: 0 0 0 3px rgba(74,140,66,.12);
        background: #fff;
    }
    input::placeholder { color: #b0bfae; }

    /* PASSWORD */
    .pass-wrap { position: relative; }
    .pass-wrap input { padding-right: 40px; }
    .pass-toggle {
        position: absolute; right: 11px; top: 50%;
        transform: translateY(-50%);
        background: none; border: none; cursor: pointer;
        color: #9aab98; font-size: 1.05rem; padding: 0; line-height: 1;
        display: flex; align-items: center;
    }
    .pass-toggle:hover { color: #4a8c42; }

    /* STRENGTH */
    .strength-bar {
        height: 4px; border-radius: 4px;
        background: #e2ece0; margin-top: 6px; overflow: hidden;
    }
    .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
    .strength-text { font-size: .72rem; color: #9aab98; margin-top: 3px; }

    .rule { border: none; border-top: 1px solid #e2ece0; margin: 20px 0; }

    /* TIER NOTE */
    .tier-note {
        display: flex; align-items: center; gap: 8px;
        background: #f4faf2; border: 1px solid #d4edcf;
        border-radius: 9px; padding: 9px 13px;
        font-size: .78rem; color: #4a7a44; margin-bottom: 18px;
    }
    .tier-note i { font-size: 1rem; flex-shrink: 0; }

    /* ALERT */
    .alert {
        padding: 11px 15px; border-radius: 10px;
        font-size: .85rem; font-weight: 500; margin-bottom: 18px;
        display: flex; align-items: flex-start; gap: 8px;
    }
    .alert i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* BUTTON */
    .btn-register {
        width: 100%; padding: 12px;
        background: #2d5a27; color: #fff;
        border: none; border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: .95rem; font-weight: 600;
        cursor: pointer; margin-top: 4px;
        transition: background .2s, transform .1s;
    }
    .btn-register:hover  { background: #1e3a1a; }
    .btn-register:active { transform: scale(.98); }

    /* ── SUCCESS / PENDING STATE ── */
    .success-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 4px 0 8px;
    }

    /* animated check circle */
    .success-icon-ring {
        width: 76px; height: 76px;
        border-radius: 50%;
        background: #f0faf0;
        border: 2px solid #c4dfc0;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 20px;
        animation: popIn .4s cubic-bezier(.175,.885,.32,1.275) both;
    }
    .success-icon-ring i {
        font-size: 2.2rem;
        color: #2d5a27;
    }

    @keyframes popIn {
        from { transform: scale(.5); opacity: 0; }
        to   { transform: scale(1);  opacity: 1; }
    }

    .success-wrap h3 {
        font-family: 'DM Serif Display', serif;
        font-size: 1.35rem; color: #1a2e18;
        font-weight: 400; margin-bottom: 8px;
    }

    .success-wrap .sub {
        font-size: .85rem; color: #6b7c69;
        line-height: 1.7; margin-bottom: 16px;
        max-width: 340px;
    }

    /* status badge */
    .status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: #fffbeb; color: #92400e;
        border: 1px solid #fde68a;
        border-radius: 20px; padding: 5px 14px;
        font-size: .75rem; font-weight: 700;
        letter-spacing: .3px;
        margin-bottom: 24px;
    }
    .status-badge i { font-size: .85rem; }

    /* steps timeline */
    .steps-box {
        width: 100%;
        background: #f7fbf5;
        border: 1px solid #ddeedd;
        border-radius: 14px;
        padding: 18px 20px;
        text-align: left;
        margin-bottom: 24px;
    }

    .steps-heading {
        font-size: .7rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .7px;
        color: #9aab98; margin-bottom: 14px;
    }

    .step-row {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        position: relative;
        padding-bottom: 14px;
    }

    .step-row:last-child { padding-bottom: 0; }

    /* vertical connector line */
    .step-row:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 14px;
        top: 28px;
        bottom: 0;
        width: 1.5px;
        background: #ddeedd;
    }

    .step-node {
        width: 28px; height: 28px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        font-size: .8rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .step-node.done {
        background: #d1f0cb;
        color: #2d5a27;
    }
    .step-node.done i { font-size: 1rem; }

    .step-node.pending {
        background: #e2ece0;
        color: #6b7c69;
        font-size: .78rem;
    }

    .step-content { padding-top: 3px; }
    .step-content strong {
        display: block;
        font-size: .85rem;
        color: #1a2e18;
        margin-bottom: 2px;
    }
    .step-content span {
        font-size: .78rem;
        color: #6b7c69;
        line-height: 1.5;
    }

    /* back button */
    .btn-back {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 10px 26px;
        background: transparent; color: #2d5a27;
        border: 1.5px solid #4a8c42; border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: .88rem; font-weight: 600;
        text-decoration: none;
        transition: background .2s;
    }
    .btn-back:hover { background: #f0faf0; }
    .btn-back i { font-size: 1rem; }

    /* LOGIN LINK */
    .login-link {
        text-align: center; margin-top: 18px;
        font-size: .83rem; color: #6b7c69;
    }
    .login-link a { color: #2d5a27; font-weight: 600; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">

    <div class="brand">
        <h1>Blue Eco Farm</h1>
        <p>Distributor Registration</p>
    </div>

    <?php if ($success): ?>

    <!-- ── SUCCESS / PENDING STATE ── -->
    <div class="success-wrap">

        <div class="success-icon-ring">
            <i class="bi bi-check-lg"></i>
        </div>

        <h3>Application Submitted!</h3>
        <p class="sub">
            Thank you, <strong><?= htmlspecialchars($submittedName) ?></strong>. Your distributor account has been created and is now awaiting admin approval before you can log in.
        </p>

        <div class="status-badge">
            <i class="bi bi-hourglass-split"></i>
            Pending Approval
        </div>

        <div class="steps-box">
            <div class="steps-heading">What happens next</div>

            <div class="step-row">
                <div class="step-node done">
                    <i class="bi bi-check"></i>
                </div>
                <div class="step-content">
                    <strong>Account registered</strong>
                    <span>Your information has been saved successfully.</span>
                </div>
            </div>

            <div class="step-row">
    <div class="step-node pending">2</div>
    <div class="step-content">
        <strong>Admin review</strong>
        <span>The Blue Eco Farm admin will review your application. You can <a href="check_status.php" style="color:#2d5a27;font-weight:600;">check your status here</a> anytime.</span>
    </div>
</div>

            <div class="step-row">
                <div class="step-node pending">3</div>
                <div class="step-content">
                    <strong>Access granted</strong>
                    <span>Once approved, you can log in to the distributor portal.</span>
                </div>
            </div>
        </div>

        <a href="login.php" class="btn-back">
            <i class="bi bi-arrow-left"></i>
            Back to Login
        </a>

    </div>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>

            <div class="section-label">Account Information</div>

            <div class="form-group">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" placeholder="e.g. Juan dela Cruz"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" autocomplete="name">
            </div>

            <div class="form-group">
                <label>Username <span class="req">*</span></label>
                <input type="text" name="username" placeholder="e.g. jdelacruz"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password <span class="req">*</span></label>
                    <div class="pass-wrap">
                        <input type="password" name="password" id="password"
                               placeholder="Min. 6 characters" autocomplete="new-password">
                        <button type="button" class="pass-toggle" id="togglePwd" onclick="togglePass('password', 'togglePwd')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <div class="strength-text" id="strengthText"></div>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="req">*</span></label>
                    <div class="pass-wrap">
                        <input type="password" name="confirm_password" id="confirmPassword"
                               placeholder="Re-enter password" autocomplete="new-password">
                        <button type="button" class="pass-toggle" id="toggleCfm" onclick="togglePass('confirmPassword', 'toggleCfm')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <hr class="rule">

            <div class="section-label">Business Information</div>

            <div class="form-group">
                <label>Business Name <span class="req">*</span></label>
                <input type="text" name="business_name" placeholder="e.g. Dela Cruz Agricultural Supply"
                       value="<?= htmlspecialchars($_POST['business_name'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Region</label>
                    <input type="text" name="region" placeholder="e.g. Calabarzon"
                           value="<?= htmlspecialchars($_POST['region'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" placeholder="+639XXXXXXXXX"
                           value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                </div>
            </div>

            <div class="tier-note">
                <i class="bi bi-patch-check"></i>
                <span>New accounts start at <strong>Silver</strong> tier and may be upgraded by an admin.</span>
            </div>

            <button type="submit" class="btn-register">Submit Application</button>
        </form>

    <?php endif; ?>

    <div class="login-link">
        Already have an account? <a href="login.php">Log in here</a>
    </div>

</div>
<script>
function togglePass(inputId, btnId) {
    const input = document.getElementById(inputId);
    const icon  = document.querySelector('#' + btnId + ' i');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
}

const pwdInput = document.getElementById('password');
if (pwdInput) {
    pwdInput.addEventListener('input', function () {
        const val  = this.value;
        const fill = document.getElementById('strengthFill');
        const text = document.getElementById('strengthText');
        let score  = 0;
        if (val.length >= 6)           score++;
        if (val.length >= 10)          score++;
        if (/[A-Z]/.test(val))         score++;
        if (/[0-9]/.test(val))         score++;
        if (/[^A-Za-z0-9]/.test(val))  score++;
        const levels = [
            { pct: '0%',   color: '#e2ece0', label: '' },
            { pct: '25%',  color: '#dc2626', label: 'Weak' },
            { pct: '50%',  color: '#f59e0b', label: 'Fair' },
            { pct: '75%',  color: '#4a8c42', label: 'Good' },
            { pct: '100%', color: '#2d5a27', label: 'Strong' },
        ];
        const lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 4)];
        fill.style.width      = lvl.pct;
        fill.style.background = lvl.color;
        text.textContent      = lvl.label;
    });
}
</script>
</body>
</html>