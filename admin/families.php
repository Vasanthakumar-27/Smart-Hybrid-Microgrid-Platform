<?php
/**
 * Admin - Families Management
 */
$pageTitle = 'Manage Families';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$message = '';
$msgType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $message = 'Invalid security token. Please try again.';
        $msgType = 'danger';
    } else {
        if ($action === 'add') {
            $name = trim($_POST['family_name'] ?? '');
            $location = trim($_POST['location'] ?? '');
            if ($name && $location) {
                $stmt = $db->prepare("INSERT INTO families (family_name, location) VALUES (?, ?)");
                $stmt->execute([$name, $location]);
                $message = "Family '$name' added successfully.";
                $msgType = 'success';
            } else {
                $message = 'All fields are required.';
                $msgType = 'danger';
            }
        } elseif ($action === 'edit') {
            $id = (int) ($_POST['family_id'] ?? 0);
            $name = trim($_POST['family_name'] ?? '');
            $location = trim($_POST['location'] ?? '');
            if ($id && $name && $location) {
                $stmt = $db->prepare("UPDATE families SET family_name = ?, location = ? WHERE family_id = ?");
                $stmt->execute([$name, $location, $id]);
                $message = "Family updated successfully.";
                $msgType = 'success';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['family_id'] ?? 0);
            if ($id > 1) { // Don't delete Admin family
                $stmt = $db->prepare("DELETE FROM families WHERE family_id = ?");
                $stmt->execute([$id]);
                $message = "Family deleted.";
                $msgType = 'success';
            }
        }
    }
    // Regenerate CSRF token only after successful action
    if ($msgType === 'success') {
        rotateCSRFToken();
    }
}

$families = getAllFamilies();
$csrf = generateCSRFToken();
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
    <?= h($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">All Families</h5>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFamilyModal">
        <i class="bi bi-plus-lg me-1"></i>Add Family
    </button>
</div>

<div class="card dashboard-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Family Name</th>
                        <th>Location</th>
                        <th>Microgrids</th>
                        <th>Users</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($families as $f): ?>
                    <tr>
                        <td><?= $f['family_id'] ?></td>
                        <td><strong><?= h($f['family_name']) ?></strong></td>
                        <td><?= h($f['location']) ?></td>
                        <td><span class="badge bg-primary"><?= $f['microgrid_count'] ?></span></td>
                        <td><span class="badge bg-secondary"><?= $f['user_count'] ?></span></td>
                        <td><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" onclick="editFamily(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($f['family_id'] > 1): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this family and all associated data?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="family_id" value="<?= $f['family_id'] ?>">
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

<!-- Add Family Modal -->
<div class="modal fade" id="addFamilyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add New Family</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="mb-3">
                        <label class="form-label">Family Name</label>
                        <input type="text" name="family_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Family</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Family Modal -->
<div class="modal fade" id="editFamilyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Family</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="family_id" id="editFamilyId">
                    <div class="mb-3">
                        <label class="form-label">Family Name</label>
                        <input type="text" name="family_name" id="editFamilyName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="editFamilyLocation" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editFamily(f) {
    document.getElementById('editFamilyId').value = f.family_id;
    document.getElementById('editFamilyName').value = f.family_name;
    document.getElementById('editFamilyLocation').value = f.location;
    new bootstrap.Modal(document.getElementById('editFamilyModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
