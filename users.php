<?php
require_once 'src/AuthManager.php';
AuthManager::requireAdmin();

require_once 'src/Database.php';
$auth  = new AuthManager();
$error = '';
$success = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        $userId = $auth->register(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['full_name'] ?? '',
            $_POST['email'] ?? '',
            $_POST['role'] ?? 'staff'
        );

        require_once 'src/Mailer.php';

        $user = $auth->getUserByUsername($_POST['username']);
        $code = $auth->generateVerificationCode($userId);

        Mailer::sendVerificationEmail($user['email'], $user['full_name'], $code);
        Mailer::sendAccountCreatedEmail($user['email'], $user['full_name'], $user['username']);

        $success = "User '{$_POST['username']}' created. Verification email sent.";
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)($_POST['user_id'] ?? 0);
    if ($delId === AuthManager::currentUser()['id']) {
        $error = "You cannot delete your own account.";
    } else {
        $auth->deleteUser($delId);
        $success = "User deleted.";
    }
}

$users = $auth->listUsers();
require_once 'includes/header.php';
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">User Management</h1>

<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Create user form -->
<div class="card" style="max-width:520px;margin-bottom:2rem;">
    <h2>Create New Account</h2>
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required>
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password <span style="font-weight:400;color:#888;">(min 6 chars)</span></label>
            <input type="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role">
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>
</div>

<!-- Users table -->
<div class="card">
    <h2>All Accounts</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= $u['role'] ?></td>
                <td>
                    <?php if ($u['is_verified']): ?>
                        <span style="color:#2e7d32;font-weight:bold;">✓ Verified</span>
                    <?php else: ?>
                        <span style="color:#ff9800;font-weight:bold;">⏳ Pending</span>
                    <?php endif; ?>
                </td>
                <td><?= $u['created_at'] ?></td>
                <td>
                    <?php if ($u['id'] !== AuthManager::currentUser()['id']): ?>
                    <form method="POST" onsubmit="return confirm('Delete this user?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:0.3rem 0.8rem;font-size:0.8rem;">Delete</button>
                    </form>
                    <?php else: ?>
                        <span style="color:#aaa;font-size:0.85rem;">You</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
