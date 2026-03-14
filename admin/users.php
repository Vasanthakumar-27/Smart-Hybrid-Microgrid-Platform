<?php
/**
 * Admin - Users Management
 */
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $message = 'Invalid security token.';
        $msgType = 'danger';
    } else {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $familyId = (int) ($_POST['family_id'] ?? 0);

            if ($username && $password && $fullName) {
                // Check unique username
                $check = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $message = 'Username already exists.';
                    $msgType = 'danger';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, family_id, full_name, email) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hash, $role, $familyId ?: null, $fullName, $email ?: null]);
                    $message = "User '$username' created.";
                    $msgType = 'success';
                }
            } else {
                $message = 'Username, password, and full name are required.';
                $msgType = 'danger';
            }
        } elseif ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId > 1 && $userId !== getCurrentUserId()) {
                $db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
                $message = 'User deleted.';
                $msgType = 'success';
            }
        }
    }
    if ($msgType === 'success') {
        rotateCSRFToken();
    }
}

$users = $db->query("SELECT u.*, f.family_name FROM users u LEFT JOIN families f ON u.family_id = f.family_id ORDER BY u.role, u.username")->fetchAll();
$familiesList = $db->query("SELECT family_id, family_name FROM families ORDER BY family_name")->fetchAll();
$csrf = generateCSRFToken();
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
    <?= h($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">All Users</h5>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-lg me-1"></i>Add User
    </button>
</div>

<div class="card dashboard-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Family</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['user_id'] ?></td>
                        <td><strong><?= h($u['username']) ?></strong></td>
                        <td><?= h($u['full_name']) ?></td>
                        <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= h($u['family_name'] ?? '-') ?></td>
                        <td><?= h($u['email'] ?? '-') ?></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['user_id'] > 1 && $u['user_id'] !== getCurrentUserId()): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="4">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Family</label>
                            <select name="family_id" class="form-select">
                                <option value="">No Family</option>
                                <?php foreach ($familiesList as $fam): ?>
                                <option value="<?= $fam['family_id'] ?>"><?= h($fam['family_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
