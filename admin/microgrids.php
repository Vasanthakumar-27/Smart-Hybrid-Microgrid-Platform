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
$supportsLocationName = hasTableColumn('microgrids', 'location_name');
$supportsLatitude = hasTableColumn('microgrids', 'latitude');
$supportsLongitude = hasTableColumn('microgrids', 'longitude');
$supportsExpected = hasTableColumn('microgrids', 'expected_generation_kw');

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
            $locationName = trim($_POST['location_name'] ?? '');
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            $expectedGeneration = (float) ($_POST['expected_generation_kw'] ?? 0);
            $installed = $_POST['installed_on'] ?? null;

            if ($familyId && $name && in_array($type, ['solar', 'wind']) && $capacity > 0) {
                $columns = ['family_id', 'microgrid_name', 'type', 'capacity_kw', 'location', 'installed_on'];
                $values = [$familyId, $name, $type, $capacity, $location, $installed ?: null];

                if ($supportsLocationName) {
                    $columns[] = 'location_name';
                    $values[] = $locationName ?: null;
                }
                if ($supportsLatitude) {
                    $columns[] = 'latitude';
                    $values[] = $latitude !== '' ? (float) $latitude : null;
                }
                if ($supportsLongitude) {
                    $columns[] = 'longitude';
                    $values[] = $longitude !== '' ? (float) $longitude : null;
                }
                if ($supportsExpected) {
                    $columns[] = 'expected_generation_kw';
                    $values[] = $expectedGeneration > 0 ? $expectedGeneration : null;
                }

                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $sql = "INSERT INTO microgrids (" . implode(',', $columns) . ") VALUES (" . $placeholders . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
                $message = "Microgrid '$name' added.";
                $msgType = 'success';
            } else {
                $message = 'Please fill all required fields correctly.';
                $msgType = 'danger';
            }
        } elseif ($action === 'set_status') {
            $id = (int) ($_POST['microgrid_id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'active';
            if (!in_array($newStatus, ['active', 'inactive', 'maintenance'], true)) {
                $newStatus = 'active';
            }
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

$microgrids = getAllMicrogridsWithHealth();
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
                        <th>Microgrid</th>
                        <th>Type</th>
                        <th>Family</th>
                        <th>Status</th>
                        <th>Battery</th>
                        <th>Health</th>
                        <th>Capacity</th>
                        <th>Installed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($microgrids as $mg): ?>
                    <?php
                    $status = $mg['operational_status'];
                    $statusClass = 'secondary';
                    if ($status === 'active') $statusClass = 'success';
                    if ($status === 'maintenance') $statusClass = 'warning';
                    if ($status === 'fault') $statusClass = 'danger';
                    ?>
                    <tr>
                        <td><?= $mg['microgrid_id'] ?></td>
                        <td><strong><?= h($mg['microgrid_name']) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= $mg['type'] === 'solar' ? 'warning' : 'info' ?>">
                                <i class="bi <?= $mg['type'] === 'solar' ? 'bi-sun' : 'bi-wind' ?>"></i> <?= ucfirst($mg['type']) ?>
                            </span>
                        </td>
                        <td><?= h($mg['family_name']) ?></td>
                        <td>
                            <div><span class="badge bg-<?= $statusClass ?>"><?= ucfirst($status) ?></span></div>
                            <small class="text-muted">Configured: <?= h(ucfirst($mg['status'])) ?></small>
                        </td>
                        <td><?= $mg['latest_battery_level'] !== null ? number_format((float) $mg['latest_battery_level'], 0) . '%' : 'N/A' ?></td>
                        <td>
                            <span class="fw-semibold"><?= number_format((float) $mg['health_score'], 1) ?>%</span>
                            <div class="progress mt-1" style="height:6px; width:100px;">
                                <div class="progress-bar <?= $mg['health_score'] < 60 ? 'bg-danger' : ($mg['health_score'] < 80 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= max(0, min(100, (float) $mg['health_score'])) ?>%;"></div>
                            </div>
                        </td>
                        <td>
                            <?= $mg['capacity_kw'] ?> kW
                            <?php if ($supportsExpected && !empty($mg['expected_generation_kw'])): ?>
                            <br><small class="text-muted">Expected: <?= number_format((float) $mg['expected_generation_kw'], 2) ?> kW</small>
                            <?php endif; ?>
                        </td>
                        <td><?= $mg['installed_on'] ? date('d M Y', strtotime($mg['installed_on'])) : '-' ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="microgrid_id" value="<?= $mg['microgrid_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" title="Set status">
                                    <option value="active" <?= $mg['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $mg['status'] === 'inactive' ? 'selected' : '' ?>>Offline</option>
                                    <option value="maintenance" <?= $mg['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                </select>
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
                    <?php if ($supportsLocationName): ?>
                    <div class="mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="location_name" class="form-control" placeholder="Site label for map tracking">
                    </div>
                    <?php endif; ?>
                    <?php if ($supportsLatitude || $supportsLongitude): ?>
                    <div class="row">
                        <?php if ($supportsLatitude): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="number" name="latitude" class="form-control" step="0.000001" min="-90" max="90">
                        </div>
                        <?php endif; ?>
                        <?php if ($supportsLongitude): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="number" name="longitude" class="form-control" step="0.000001" min="-180" max="180">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($supportsExpected): ?>
                    <div class="mb-3">
                        <label class="form-label">Expected Generation (kW)</label>
                        <input type="number" name="expected_generation_kw" class="form-control" step="0.01" min="0" placeholder="Optional expected output">
                    </div>
                    <?php endif; ?>
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
