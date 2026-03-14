<?php
/**
 * User Profile Management
 */
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$userId = getCurrentUserId();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $msg = 'Invalid security token.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if ($fullName === '') {
                $msg = 'Full name is required.';
                $msgType = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$fullName, $email ?: null, $userId]);
                $_SESSION['full_name'] = $fullName;
                $msg = 'Profile updated successfully.';
                $msgType = 'success';
                logSystemEvent(getCurrentFamilyId(), $userId, null, 'profile_update', 'User updated profile details', 'info');
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($currentPassword, $hash)) {
                $msg = 'Current password is incorrect.';
                $msgType = 'danger';
            } elseif (strlen($newPassword) < 6) {
                $msg = 'New password must be at least 6 characters.';
                $msgType = 'danger';
            } elseif ($newPassword !== $confirmPassword) {
                $msg = 'New passwords do not match.';
                $msgType = 'danger';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$newHash, $userId]);
                $msg = 'Password changed successfully.';
                $msgType = 'success';
                logSystemEvent(getCurrentFamilyId(), $userId, null, 'password_change', 'User changed account password', 'warning');
            }
        }
    }

    if ($msgType === 'success') {
        rotateCSRFToken();
    }
}

$stmt = $db->prepare("SELECT u.*, f.family_name FROM users u LEFT JOIN families f ON u.family_id = f.family_id WHERE u.user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$csrf = generateCSRFToken();
?>

<?php if ($msg): ?>
<div class="alert alert-<?= h($msgType) ?> alert-dismissible fade show">
    <?= h($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Profile Details</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required value="<?= h($user['full_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= h($user['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?= h(ucfirst($user['role'])) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Family</label>
                        <input type="text" class="form-control" value="<?= h($user['family_name'] ?? 'N/A') ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-warning">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
