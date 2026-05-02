<?php
session_start();
if (!empty($_SESSION['user'])) {
    header('Location: index.php'); exit;
}

require_once 'src/AuthManager.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthManager();
    $user = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($user) {
        AuthManager::startSession($user);
        header('Location: index.php'); exit;
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
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f0f4f0; }
        .login-box { background:#fff; border-radius:12px; padding:2.5rem 2rem; width:100%; max-width:380px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
        .login-box .brand { text-align:center; margin-bottom:1.75rem; }
        .login-box .brand h1 { color:#2e7d32; font-size:1.4rem; }
        .login-box .brand p  { color:#888; font-size:0.85rem; margin-top:0.25rem; }
    </style>
</head>
<body>
<div class="login-box">
    <div class="brand">
        <h1>🌿 Blue Eco Farm</h1>
        <p>Inventory &amp; Forecasting System</p>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">Login</button>
    </form>
</div>
</body>
</html>
