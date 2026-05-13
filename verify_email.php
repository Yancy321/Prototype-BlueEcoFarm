<?php

session_start();

require_once 'src/AuthManager.php';

$error = '';
$success = '';
$user = null;

// Check if user is logged in, otherwise check session
if (!isset($_SESSION['verify_user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['verify_user_id'];
$auth = new AuthManager();

// Get user info
$user = $auth->getUserById($userId);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['is_verified']) {
    session_destroy();
    header('Location: login.php?verified=1');
    exit;
}

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if (strlen($code) !== 6) {
        $error = 'Please enter a 6-digit code.';
    } else {

        if ($auth->verifyEmailWithCode($userId, $code)) {

            $success = 'Email verified successfully! Redirecting to login...';
            session_destroy();

            echo '<meta http-equiv="refresh" content="2; url=login.php?verified=1">';

        } else {

            $error = 'Invalid or expired code. Please try again or resend.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — Blue Eco Farm</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f0f4f0;
        }

        .verify-box {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .verify-box .brand {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .verify-box .brand h1 {
            color: #2e7d32;
            font-size: 1.4rem;
        }

        .verify-box .brand p {
            color: #888;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .code-input {
            font-size: 32px;
            letter-spacing: 8px;
            text-align: center;
            font-weight: bold;
            font-family: monospace;
        }

        .info-text {
            text-align: center;
            color: #666;
            margin: 1rem 0;
            font-size: 0.9rem;
        }

    </style>
</head>

<body>

<div class="verify-box">

    <div class="brand">
        <h1>🌿 Blue Eco Farm</h1>
        <p>Verify Your Email</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="info-text">
        <p>We've sent a 6-digit verification code to:</p>
        <p><strong><?= htmlspecialchars($user['email']) ?></strong></p>
    </div>

    <form method="POST">

        <div class="form-group">
            <label for="code">Verification Code</label>

            <input
                type="text"
                id="code"
                name="code"
                class="code-input"
                placeholder="000000"
                maxlength="6"
                pattern="\d{6}"
                required
                autofocus
            >
        </div>

        <button
            type="submit"
            class="btn btn-primary"
            style="width:100%; margin-top:0.5rem;"
        >
            Verify Email
        </button>

    </form>

    <div class="info-text" style="margin-top: 1.5rem; border-top: 1px solid #eee; padding-top: 1.5rem;">
        <p style="color: #888; font-size: 0.85rem;">Didn't receive the code? Check your spam folder or contact support.</p>
    </div>

</div>

</body>
</html>
