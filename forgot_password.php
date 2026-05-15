<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: distributor_dashboard.php');
    exit;
}

require_once 'src/EmailService.php';

$step    = $_SESSION['otp_step']  ?? 'email'; // email → verify → done
$error   = '';
$success = '';

// ── STEP 1: Submit email ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $svc  = new EmailService();
            $sent = $svc->sendPasswordResetOtp($email);

            if ($sent) {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_step']  = 'verify';
                $step    = 'verify';
                $success = 'A 6-digit code has been sent to your email.';
            } else {
                $error = 'No distributor account found with that email address.';
            }
        } catch (Throwable $e) {
            error_log('OTP send failed: ' . $e->getMessage());
            $error = 'Failed to send email. Please check your email configuration or try again later.';
        }
    }
}

// ── STEP 2: Verify OTP ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {
    $otp   = trim($_POST['otp'] ?? '');
    $email = $_SESSION['otp_email'] ?? '';

    if (!$otp || strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error = 'Please enter the 6-digit code.';
        $step  = 'verify';
    } elseif (!$email) {
        $error = 'Session expired. Please start again.';
        $step  = 'email';
    } else {
        $svc    = new EmailService();
        $userId = $svc->verifyOtp($email, $otp);

        if ($userId) {
            $_SESSION['otp_user_id'] = $userId;
            $_SESSION['otp_step']    = 'reset';
            $step = 'reset';
        } else {
            $error = 'Invalid or expired code. Please try again.';
            $step  = 'verify';
        }
    }
}

// ── STEP 3: Reset password ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $userId      = $_SESSION['otp_user_id']   ?? null;

    if (!$userId) {
        $error = 'Session expired. Please start again.';
        $step  = 'email';
    } elseif (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
        $step  = 'reset';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match.';
        $step  = 'reset';
    } else {
        try {
            $svc = new EmailService();
            $svc->resetPassword((int)$userId, $newPass);
            $svc->markOtpUsed();

            // Clear OTP session data
            unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id']);
            $_SESSION['otp_step'] = 'done';
            $step = 'done';
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $step  = 'reset';
        }
    }
}

$step = $_SESSION['otp_step'] ?? $step;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | Blue Eco Farm</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #e8f0e6; min-height: 100vh;
    display: flex; align-items: center; justify-content: center; padding: 32px 16px;
}
.card {
    background: #fff; border-radius: 18px;
    box-shadow: 0 8px 40px rgba(30,58,26,.13);
    padding: 40px 44px; width: 100%; max-width: 420px;
}
.brand { text-align: center; margin-bottom: 28px; }
.brand h1 { font-family: 'DM Serif Display', serif; font-size: 1.5rem; color: #2d5a27; font-weight: 400; }
.brand p  { font-size: .82rem; color: #6b7c69; margin-top: 3px; }
.step-title { font-size: 1rem; font-weight: 700; color: #1a2e18; margin-bottom: 6px; }
.step-sub   { font-size: .83rem; color: #6b7c69; margin-bottom: 22px; line-height: 1.5; }
.form-group { margin-bottom: 16px; }
label { display: block; font-size: .82rem; font-weight: 600; color: #1a2e18; margin-bottom: 5px; }
input[type="email"],
input[type="text"],
input[type="password"] {
    width: 100%; padding: 11px 13px;
    border: 1.5px solid #dde8db; border-radius: 10px;
    font-family: 'DM Sans', sans-serif; font-size: .9rem;
    color: #1a2e18; background: #f9fdf8; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
input:focus { border-color: #4a8c42; box-shadow: 0 0 0 3px rgba(74,140,66,.12); background: #fff; }
input::placeholder { color: #b0bfae; }
.otp-input {
    text-align: center; font-size: 1.8rem; font-weight: 700;
    letter-spacing: 0.4em; font-family: 'Courier New', monospace;
    color: #2d5a27;
}
.pass-wrap { position: relative; }
.pass-wrap input { padding-right: 40px; }
.pass-toggle {
    position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #9aab98; font-size: 1rem; padding: 0;
}
.pass-toggle:hover { color: #4a8c42; }
.btn-submit {
    width: 100%; padding: 12px; background: #2d5a27; color: #fff;
    border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif;
    font-size: .95rem; font-weight: 600; cursor: pointer; margin-top: 4px;
    transition: background .2s;
}
.btn-submit:hover { background: #1e3a1a; }
.alert {
    padding: 11px 14px; border-radius: 10px; font-size: .85rem;
    font-weight: 500; margin-bottom: 16px;
    display: flex; align-items: flex-start; gap: 8px;
}
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.alert-success { background: #e8f5e4; color: #2d5a27; border: 1px solid #b7ddb0; }
.back-link { text-align: center; margin-top: 18px; font-size: .83rem; color: #6b7c69; }
.back-link a { color: #2d5a27; font-weight: 600; text-decoration: none; }
.back-link a:hover { text-decoration: underline; }
.resend-link { font-size: .8rem; color: #6b7c69; text-align: center; margin-top: 12px; }
.resend-link a { color: #2d5a27; font-weight: 600; text-decoration: none; cursor: pointer; }
/* Progress dots */
.steps-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 28px; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: #dde8db; }
.dot.active { background: #2d5a27; }
/* Success state */
.success-wrap { text-align: center; padding: 8px 0; }
.success-icon { font-size: 3rem; color: #2d5a27; margin-bottom: 16px; }
.success-wrap h3 { font-family: 'DM Serif Display', serif; font-size: 1.3rem; color: #1a2e18; font-weight: 400; margin-bottom: 8px; }
.success-wrap p  { font-size: .85rem; color: #6b7c69; line-height: 1.6; margin-bottom: 20px; }
.btn-login {
    display: inline-block; padding: 11px 32px;
    background: #2d5a27; color: #fff; border-radius: 10px;
    font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
    text-decoration: none; transition: background .2s;
}
.btn-login:hover { background: #1e3a1a; }
</style>
</head>
<body>
<div class="card">

    <div class="brand">
        <h1>Blue Eco Farm</h1>
        <p>Distributor Password Reset</p>
    </div>

    <?php if ($step !== 'done'): ?>
    <!-- Progress dots -->
    <div class="steps-dots">
        <div class="dot <?= $step === 'email'  ? 'active' : '' ?>"></div>
        <div class="dot <?= $step === 'verify' ? 'active' : '' ?>"></div>
        <div class="dot <?= $step === 'reset'  ? 'active' : '' ?>"></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($step === 'email'): ?>
    <!-- STEP 1: Enter email -->
    <div class="step-title">Forgot your password?</div>
    <p class="step-sub">Enter the email address linked to your distributor account and we'll send you a reset code.</p>
    <form method="POST">
        <input type="hidden" name="action" value="send_otp">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="you@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>
        </div>
        <button type="submit" class="btn-submit">Send Reset Code</button>
    </form>

    <?php elseif ($step === 'verify'): ?>
    <!-- STEP 2: Enter OTP -->
    <div class="step-title">Enter your code</div>
    <p class="step-sub">We sent a 6-digit code to <strong><?= htmlspecialchars($_SESSION['otp_email'] ?? '') ?></strong>. It expires in 15 minutes.</p>
    <form method="POST">
        <input type="hidden" name="action" value="verify_otp">
        <div class="form-group">
            <label>6-Digit Code</label>
            <input type="text" name="otp" class="otp-input" maxlength="6"
                   placeholder="000000" autocomplete="one-time-code" autofocus required>
        </div>
        <button type="submit" class="btn-submit">Verify Code</button>
    </form>
    <div class="resend-link">
        Didn't receive it? <a href="forgot_password.php" onclick="sessionStorage.clear()">Start over</a>
    </div>

    <?php elseif ($step === 'reset'): ?>
    <!-- STEP 3: New password -->
    <div class="step-title">Set a new password</div>
    <p class="step-sub">Choose a strong password for your account.</p>
    <form method="POST">
        <input type="hidden" name="action" value="reset_password">
        <div class="form-group">
            <label>New Password</label>
            <div class="pass-wrap">
                <input type="password" name="new_password" id="newPwd"
                       placeholder="Min. 6 characters" autofocus required>
                <button type="button" class="pass-toggle" onclick="togglePwd('newPwd', this)">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <div class="pass-wrap">
                <input type="password" name="confirm_password" id="cfmPwd"
                       placeholder="Re-enter password" required>
                <button type="button" class="pass-toggle" onclick="togglePwd('cfmPwd', this)">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-submit">Reset Password</button>
    </form>

    <?php elseif ($step === 'done'): ?>
    <!-- DONE -->
    <div class="success-wrap">
        <div class="success-icon"><i class="bi bi-shield-check"></i></div>
        <h3>Password Reset!</h3>
        <p>Your password has been updated successfully. You can now log in with your new password.</p>
        <a href="login.php" class="btn-login">Go to Login</a>
    </div>
    <?php endif; ?>

    <?php if ($step !== 'done'): ?>
    <div class="back-link">
        <a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
    <?php endif; ?>

</div>
<script>
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>
</body>
</html>
