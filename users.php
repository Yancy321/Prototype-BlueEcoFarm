<?php
require_once 'src/AuthManager.php';
AuthManager::requireAdmin();

require_once 'src/Database.php';

$auth    = new AuthManager();
$pdo     = Database::getInstance();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        $auth->register($_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['full_name'] ?? '', $_POST['role'] ?? 'staff');
        $success = "User created successfully.";
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)($_POST['user_id'] ?? 0);
    if ($delId === AuthManager::currentUser()['id']) {
        $error = "You cannot delete your own account.";
    } else {
        $auth->deleteUser($delId);
        $success = "User deleted.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_distributor_status') {
    $distId    = (int)($_POST['distributor_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['approved', 'rejected']) && $distId > 0) {
        $stmt = $pdo->prepare("UPDATE distributors SET status = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $newStatus === 'approved' ? 1 : 0, $distId]);
        $success = "Distributor status updated to " . ucfirst($newStatus) . ".";
    } else {
        $error = "Invalid action.";
    }
}

$distributorApps = $pdo->query("
    SELECT d.id AS dist_id, u.id AS user_id, u.full_name, u.username, u.created_at,
           COALESCE(d.business_name, d.name, '—') AS business_name,
           COALESCE(d.region, '—') AS region,
           COALESCE(d.contact_number, d.phone, '—') AS contact_number,
           COALESCE(d.tier, 'Silver') AS tier,
           COALESCE(d.status, 'pending') AS status
    FROM distributors d
    JOIN users u ON u.id = d.user_id
    ORDER BY FIELD(d.status, 'pending', 'approved', 'rejected'), u.created_at DESC
")->fetchAll();

$pendingCount = count(array_filter($distributorApps, fn($r) => $r['status'] === 'pending'));
$users        = $auth->listUsers();

require_once 'includes/header.php';
?>

<h1 class="page-title">User Management</h1>

<div id="alertBox">
    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<!-- Distributor Applications -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header-row">
        <h2>
            Distributor Applications
            <?php if ($pendingCount > 0): ?>
                <span class="pending-badge"><?= $pendingCount ?> pending</span>
            <?php endif; ?>
        </h2>
        <div class="filter-tabs" style="margin-bottom:0;">
            <button class="btn btn-secondary btn-sm" onclick="filterDist('all')">All</button>
            <button class="btn btn-secondary btn-sm" onclick="filterDist('pending')">Pending</button>
            <button class="btn btn-secondary btn-sm" onclick="filterDist('approved')">Approved</button>
            <button class="btn btn-secondary btn-sm" onclick="filterDist('rejected')">Rejected</button>
        </div>
    </div>

    <?php if (empty($distributorApps)): ?>
        <p class="empty-state">No distributor applications yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table id="distTable">
            <thead>
                <tr>
                    <th>Applicant</th><th>Business</th><th>Region</th>
                    <th>Contact</th><th>Tier</th><th>Applied</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($distributorApps as $d): ?>
                <tr class="dist-row" data-status="<?= $d['status'] ?>">
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($d['full_name']) ?></div>
                        <small class="cell-sub">@<?= htmlspecialchars($d['username']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($d['business_name']) ?></td>
                    <td><?= htmlspecialchars($d['region']) ?></td>
                    <td><?= htmlspecialchars($d['contact_number']) ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($d['tier']) ?></span></td>
                    <td class="cell-sub"><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                    <td>
                        <?php if ($d['status'] === 'pending'): ?>
                            <span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;">Pending</span>
                        <?php elseif ($d['status'] === 'approved'): ?>
                            <span class="badge badge-success">Approved</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($d['status'] === 'pending'): ?>
                            <div class="action-flex">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_distributor_status">
                                    <input type="hidden" name="distributor_id" value="<?= $d['dist_id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-primary btn-sm"
                                        onclick="return confirm('Approve this distributor?')">Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_distributor_status">
                                    <input type="hidden" name="distributor_id" value="<?= $d['dist_id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Reject this distributor?')">Reject</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="dash-none">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create User -->
<div class="card card-narrow" style="margin-bottom:1.5rem;">
    <h2>Create New Account</h2>
    <form method="POST" class="form-grid-2">
        <input type="hidden" name="action" value="create">
        <div class="form-group form-group-inline">
            <label>Full Name</label>
            <input type="text" name="full_name" required placeholder="e.g. Juan Dela Cruz">
        </div>
        <div class="form-group form-group-inline">
            <label>Username</label>
            <input type="text" name="username" required placeholder="e.g. juan">
        </div>
        <div class="form-group form-group-inline">
            <label>Password</label>
            <input type="password" name="password" required minlength="6" placeholder="Min. 6 characters">
        </div>
        <div class="form-group form-group-inline">
            <label>Role</label>
            <select name="role">
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="form-full">
            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </div>
    </form>
</div>

<!-- All Users -->
<div class="card">
    <h2>All Accounts</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Full Name</th><th>Username</th>
                    <th>Role</th><th>Created</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <span class="badge badge-<?= $u['role'] === 'admin' ? 'success' : 'info' ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td><?= $u['created_at'] ?></td>
                    <td>
                        <?php if ($u['id'] !== AuthManager::currentUser()['id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete this user?')">Delete</button>
                            </form>
                        <?php else: ?>
                            <span class="dash-none">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterDist(filter) {
    document.querySelectorAll('.dist-row').forEach(row => {
        row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
