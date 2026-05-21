<?php
require_once 'src/AuthManager.php';
AuthManager::requireAdmin();

require_once 'src/Database.php';

$auth  = new AuthManager();
$error = '';
$success = '';

$conn = new mysqli("localhost", "root", "", "blue_eco_farm");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* =========================
   CREATE USER
========================= */
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

/* =========================
   DELETE USER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)($_POST['user_id'] ?? 0);

    if ($delId === AuthManager::currentUser()['id']) {
        $error = "You cannot delete your own account.";
    } else {
        $auth->deleteUser($delId);
        $success = "User deleted.";
    }
}

/* =========================
   APPROVE / REJECT DISTRIBUTOR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_distributor_status') {

    $distId    = (int)($_POST['distributor_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, ['approved', 'rejected']) && $distId > 0) {

        $stmt = $conn->prepare("UPDATE distributors SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $distId);
        $stmt->execute();
        $stmt->close();

        $success = "Distributor status updated.";
    } else {
        $error = "Invalid action.";
    }
}

/* =========================
   FETCH DATA
========================= */
$pending = $conn->query("
    SELECT d.id AS dist_id, u.id AS user_id, u.full_name, u.username, u.created_at,
           d.business_name, d.region, d.contact_number, d.tier, d.status
    FROM distributors d
    JOIN users u ON u.id = d.user_id
    ORDER BY
        FIELD(d.status, 'pending', 'approved', 'rejected'),
        u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pendingCount = count(array_filter($pending, fn($r) => $r['status'] === 'pending'));

$users = $auth->listUsers();

require_once 'includes/header.php';
?>

<!-- BOOTSTRAP + ICONS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<div class="container-fluid py-4">

    <!-- TITLE -->
    <div class="mb-4">
        <h2 class="fw-bold">User Management</h2>
        <p class="text-muted">Manage system users and distributor applications</p>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- =========================
         DISTRIBUTOR APPLICATIONS
    ========================== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <h5 class="fw-semibold">
                    <i class="bi bi-people-fill text-success"></i>
                    Distributor Applications

                    <?php if ($pendingCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $pendingCount ?> pending</span>
                    <?php endif; ?>
                </h5>

                <!-- FILTER -->
                <div class="btn-group">
                    <button class="btn btn-outline-secondary active" onclick="filterDist('all',this)">All</button>
                    <button class="btn btn-outline-warning" onclick="filterDist('pending',this)">Pending</button>
                    <button class="btn btn-outline-success" onclick="filterDist('approved',this)">Approved</button>
                    <button class="btn btn-outline-danger" onclick="filterDist('rejected',this)">Rejected</button>
                </div>
            </div>

            <?php if (empty($pending)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-2"></i>
                    <p>No distributor applications</p>
                </div>
            <?php else: ?>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Applicant</th>
                            <th>Business</th>
                            <th>Region</th>
                            <th>Contact</th>
                            <th>Tier</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($pending as $d): ?>
                        <tr class="dist-row" data-status="<?= $d['status'] ?>">

                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($d['full_name']) ?></div>
                                <small class="text-muted">@<?= htmlspecialchars($d['username']) ?></small>
                            </td>

                            <td><?= htmlspecialchars($d['business_name']) ?></td>
                            <td><?= htmlspecialchars($d['region'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($d['contact_number'] ?: '-') ?></td>

                            <td><span class="badge bg-secondary"><?= $d['tier'] ?></span></td>

                            <td class="text-muted small">
                                <?= date('M j, Y', strtotime($d['created_at'])) ?>
                            </td>

                            <!-- STATUS -->
                            <td>
                                <?php if ($d['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-hourglass-split"></i> Pending
                                    </span>
                                <?php elseif ($d['status'] === 'approved'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Approved
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- ACTION -->
                            <td>
                                <?php if ($d['status'] === 'pending'): ?>
                                    <div class="d-flex gap-2">

                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_distributor_status">
                                            <input type="hidden" name="distributor_id" value="<?= $d['dist_id'] ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button class="btn btn-success btn-sm">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_distributor_status">
                                            <input type="hidden" name="distributor_id" value="<?= $d['dist_id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="btn btn-danger btn-sm">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>

                                    </div>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- =========================
         CREATE USER
    ========================== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <h5 class="fw-semibold mb-3">
                <i class="bi bi-person-plus-fill text-primary"></i>
                Create New Account
            </h5>

            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="row g-3">

                    <div class="col-md-6">
                        <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>

                    <div class="col-md-6">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>

                    <div class="col-md-6">
                        <input type="password" name="password" class="form-control" placeholder="Password" required minlength="6">
                    </div>

                    <div class="col-md-6">
                        <select name="role" class="form-select">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="distributor">Distributor</option>
                        </select>
                    </div>

                </div>

                <button class="btn btn-primary mt-3">
                    <i class="bi bi-save"></i> Create Account
                </button>
            </form>

        </div>
    </div>

    <!-- =========================
         ALL USERS
    ========================== -->
    <div class="card shadow-sm border-0">
        <div class="card-body">

            <h5 class="fw-semibold mb-3">
                <i class="bi bi-people"></i> All Accounts
            </h5>

            <div class="table-responsive">
                <table class="table align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= $u['role'] ?></span></td>
                            <td class="text-muted small"><?= $u['created_at'] ?></td>

                            <td>
                                <?php if ($u['id'] !== AuthManager::currentUser()['id']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

<!-- FILTER SCRIPT -->
<script>
function filterDist(filter, btn) {

    document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.dist-row').forEach(row => {
        if (filter === 'all' || row.dataset.status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>