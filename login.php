<?php

session_start();

require_once 'src/AuthManager.php';

/* =========================
   Redirect if already logged in
========================= */

if (!empty($_SESSION['user'])) {
    AuthManager::redirectByRole($_SESSION['user']);
}

$error = '';

/* =========================
   Login Process
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $auth = new AuthManager();

    $user = $auth->login(
        $_POST['username'] ?? '',
        $_POST['password'] ?? ''
    );

    if ($user) {

        // Check approval status for distributor accounts
        if ($user['role'] === 'distributor') {

            $conn = new mysqli("localhost", "root", "", "blue_eco_farm");

            $stmt = $conn->prepare("SELECT status FROM distributors WHERE user_id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $dist = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $conn->close();

            if (!$dist) {

                $error = 'Distributor profile not found. Please contact the admin.';

            } elseif ($dist['status'] === 'pending') {

                $error = 'Your account is awaiting admin approval. Please check back later.';

            } elseif ($dist['status'] === 'rejected') {

                $error = 'Your application has been rejected. Please contact the admin for more information.';

            } else {

                // Approved — start session and redirect
                AuthManager::startSession($user);
                AuthManager::redirectByRole($user);
            }

        } else {

            // Admin / Staff — no approval needed
            AuthManager::startSession($user);
            AuthManager::redirectByRole($user);
        }

    } else {

        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue Eco Farm — Login</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f0f4f0;
        }

        .login-box {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .login-box .brand {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .login-box .brand h1 {
            color: #2e7d32;
            font-size: 1.4rem;
        }

        .login-box .brand p {
            color: #888;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Pending / rejected notice styling */
        .alert-warning {
            background: #fff7e6;
            color: #b45309;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .85rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .alert-rejected {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .85rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .register-link {
            text-align: center;
            margin-top: 1.1rem;
            font-size: .83rem;
            color: #888;
        }

        .register-link a {
            color: #2e7d32;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover { text-decoration: underline; }

        .status-link {
            text-align: center;
            margin-top: 6px;
            font-size: .83rem;
            color: #888;
        }

        .status-link a {
            color: #2e7d32;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-link a:hover { text-decoration: underline; }
        .status-link a i { font-size: .9rem; }

    </style>
</head>

<body>

<div class="login-box">

    <div class="brand">
        <h1>Blue Eco Farm</h1>
        <p>Inventory &amp; Forecasting System</p>
    </div>

    <?php if ($error): ?>

        <?php if (str_contains($error, 'awaiting')): ?>
            <div class="alert-warning">
                <i class="bi bi-hourglass-split"></i>
                <?= htmlspecialchars($error) ?>
                <a href="check_status.php" style="margin-left:4px; color:#92400e; font-weight:600; white-space:nowrap;">Check status</a>
            </div>

        <?php elseif (str_contains($error, 'rejected')): ?>
            <div class="alert-rejected">
                <i class="bi bi-x-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>

        <?php else: ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="username">Username</label>

            <input
                type="text"
                id="username"
                name="username"
                required
                autofocus
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        <button
            type="submit"
            class="btn btn-primary"
            style="width:100%; margin-top:0.5rem;"
        >
            Login
        </button>

    </form>

    <div class="register-link">
        New distributor? <a href="registration.php">Apply for an account</a>
    </div>

    <div class="status-link">
        Already applied? <a href="check_status.php"><i class="bi bi-search"></i> Check your application status</a>
    </div>

</div>

</body>
</html>