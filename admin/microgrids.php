<?php
/**
 * Admin - Microgrids Management
 */
$pageTitle = 'Manage Microgrids';
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
            $familyId = (int) ($_POST['family_id'] ?? 0);
            $name = trim($_POST['microgrid_name'] ?? '');
            $type = $_POST['type'] ?? '';
            $capacity = (float) ($_POST['capacity_kw'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $installed = $_POST['installed_on'] ?? null;

            if ($familyId && $name && in_array($type, ['solar', 'wind']) && $capacity > 0) {
                $stmt = $db->prepare("INSERT INTO microgrids (family_id, microgrid_name, type, capacity_kw, location, installed_on) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$familyId, $name, $type, $capacity, $location, $installed ?: null]);
                $message = "Microgrid '$name' added.";
                $msgType = 'success';
            } else {
                $message = 'Please fill all required fields correctly.';
                $msgType = 'danger';
            }
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['microgrid_id'] ?? 0);
            $stmt = $db->prepare("SELECT status FROM microgrids WHERE microgrid_id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetchColumn();
            $newStatus = $current === 'active' ? 'inactive' : 'active';
            $db->prepare("UPDATE microgrids SET status = ? WHERE microgrid_id = ?")->execute([$newStatus, $id]);
            $message = "Microgrid status changed to $newStatus.";
            $msgType = 'success';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['microgrid_id'] ?? 0);
            $db->prepare("DELETE FROM microgrids WHERE microgrid_id = ?")->execute([$id]);
            $message = "Microgrid deleted.";
            $msgType = 'success';
        }
    }
    if ($msgType === 'success') {
        rotateCSRFToken();
    }
}

$microgrids = getAllMicrogrids();
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
    <h5 class="mb-0">All Microgrids</h5>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addGridModal">
        <i class="bi bi-plus-lg me-1"></i>Add Microgrid
    </button>
</div>

<div class="card dashboard-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Family</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Installed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($microgrids as $mg): ?>
                    <tr>
                        <td><?= $mg['microgrid_id'] ?></td>
                        <td><strong><?= h($mg['microgrid_name']) ?></strong></td>
                        <td><?= h($mg['family_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= $mg['type'] === 'solar' ? 'warning' : 'info' ?>">
                                <i class="bi <?= $mg['type'] === 'solar' ? 'bi-sun' : 'bi-wind' ?>"></i> <?= ucfirst($mg['type']) ?>
                            </span>
                        </td>
                        <td><?= $mg['capacity_kw'] ?> kW</td>
                        <td><span class="badge bg-<?= $mg['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($mg['status']) ?></span></td>
                        <td><?= $mg['installed_on'] ? date('d M Y', strtotime($mg['installed_on'])) : '-' ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="microgrid_id" value="<?= $mg['microgrid_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $mg['status'] === 'active' ? 'warning' : 'success' ?>" title="Toggle Status">
                                    <i class="bi <?= $mg['status'] === 'active' ? 'bi-pause' : 'bi-play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this microgrid?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="microgrid_id" value="<?= $mg['microgrid_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Microgrid Modal -->
<div class="modal fade" id="addGridModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add Microgrid</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="mb-3">
                        <label class="form-label">Family</label>
                        <select name="family_id" class="form-select" required>
                            <option value="">Select Family</option>
                            <?php foreach ($familiesList as $fam): ?>
                            <option value="<?= $fam['family_id'] ?>"><?= h($fam['family_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Microgrid Name</label>
                        <input type="text" name="microgrid_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="solar">Solar</option>
                                <option value="wind">Wind</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity (kW)</label>
                            <input type="number" name="capacity_kw" class="form-control" step="0.01" min="0.1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Installation Date</label>
                        <input type="date" name="installed_on" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Microgrid</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
