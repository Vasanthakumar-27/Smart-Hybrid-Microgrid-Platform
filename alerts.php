<?php
/**
 * Alerts & Fault Detection Page
 */
$pageTitle = 'Alerts & Faults';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();
$statusFilter = $_GET['status'] ?? null;

if (isAdmin()) {
    $db = getDB();
    // Admin sees all alerts
    $sql = "SELECT a.*, f.family_name, m.microgrid_name FROM alerts a
            JOIN families f ON a.family_id = f.family_id
            LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id";
    $params = [];
    if ($statusFilter) {
        $sql .= " WHERE a.status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY a.timestamp DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $alertsList = $stmt->fetchAll();

    // Alert counts
    $counts = $db->query("SELECT status, COUNT(*) as cnt FROM alerts GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
} else {
    $alertsList = getAlerts($familyId, $statusFilter, 100);
    $db = getDB();
    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM alerts WHERE family_id = ? GROUP BY status");
    $stmt->execute([$familyId]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$activeCount = $counts['active'] ?? 0;
$ackCount = $counts['acknowledged'] ?? 0;
$resolvedCount = $counts['resolved'] ?? 0;
$totalCount = $activeCount + $ackCount + $resolvedCount;
$csrf = generateCSRFToken();
?>

<!-- Alert Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="?status=" class="text-decoration-none">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-bell"></i></div>
                <div class="stat-value"><?= $totalCount ?></div>
                <div class="stat-label">Total Alerts</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="?status=active" class="text-decoration-none">
            <div class="stat-card stat-danger">
                <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
                <div class="stat-value"><?= $activeCount ?></div>
                <div class="stat-label">Active</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="?status=acknowledged" class="text-decoration-none">
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="bi bi-eye"></i></div>
                <div class="stat-value"><?= $ackCount ?></div>
                <div class="stat-label">Acknowledged</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="?status=resolved" class="text-decoration-none">
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $resolvedCount ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </a>
    </div>
</div>

<!-- Alerts Table -->
<div class="card dashboard-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $statusFilter ? ucfirst($statusFilter) . ' Alerts' : 'All Alerts' ?>
        </h6>
        <div class="d-flex gap-2">
            <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-light' : 'btn-outline-light' ?>">All</a>
            <a href="?status=active" class="btn btn-sm <?= $statusFilter === 'active' ? 'btn-danger' : 'btn-outline-danger' ?>">Active</a>
            <a href="?status=acknowledged" class="btn btn-sm <?= $statusFilter === 'acknowledged' ? 'btn-warning' : 'btn-outline-warning' ?>">Acknowledged</a>
            <a href="?status=resolved" class="btn btn-sm <?= $statusFilter === 'resolved' ? 'btn-success' : 'btn-outline-success' ?>">Resolved</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="px-3 pt-2 pb-2" style="border-bottom:1px solid rgba(255,255,255,0.06)">
            <input type="text" id="alertSearch" class="form-control form-control-sm" placeholder="Search by type, message or microgrid…" oninput="filterAlerts(this.value)" style="background:rgba(15,23,42,0.4); border-color:rgba(255,255,255,0.1); color:#e2e8f0; border-radius:8px;">
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0" id="alertsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if (isAdmin()): ?><th>Family</th><?php endif; ?>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Message</th>
                        <th>Microgrid</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alertsList)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No alerts found</td></tr>
                    <?php else: ?>
                    <?php foreach ($alertsList as $alert): ?>
                    <tr>
                        <td>#<?= $alert['alert_id'] ?></td>
                        <?php if (isAdmin()): ?><td><?= h($alert['family_name'] ?? '-') ?></td><?php endif; ?>
                        <td>
                            <span class="badge bg-dark"><?= h(str_replace('_', ' ', $alert['alert_type'])) ?></span>
                        </td>
                        <td><span class="badge <?= getSeverityClass($alert['severity']) ?>"><?= ucfirst($alert['severity']) ?></span></td>
                        <td class="alert-msg-cell"><?= h($alert['message']) ?></td>
                        <td><?= h($alert['microgrid_name'] ?? 'System') ?></td>
                        <td><span class="badge <?= getStatusClass($alert['status']) ?>"><?= ucfirst($alert['status']) ?></span></td>
                        <td><small><?= date('d M Y H:i', strtotime($alert['timestamp'])) ?></small></td>
                        <td>
                            <?php if ($alert['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="alertAction(<?= $alert['alert_id'] ?>, 'acknowledge')" title="Acknowledge">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="alertAction(<?= $alert['alert_id'] ?>, 'resolve')" title="Resolve">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php elseif ($alert['status'] === 'acknowledged'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="alertAction(<?= $alert['alert_id'] ?>, 'resolve')" title="Resolve">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-muted small">Resolved<?= $alert['resolved_at'] ? '<br>' . date('d M H:i', strtotime($alert['resolved_at'])) : '' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Alert Type Legend -->
<div class="card dashboard-card mt-4">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Alert Type Reference</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-danger me-2">Critical</span>
                    <span class="text-muted">Requires immediate attention</span>
                </div>
                <ul class="text-muted small">
                    <li><strong>Overvoltage</strong> — Voltage exceeds safe threshold</li>
                    <li><strong>Overcharge</strong> — Battery charge > 100%</li>
                    <li><strong>High Temperature</strong> — Dangerous heat level</li>
                </ul>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-warning text-dark me-2">Warning</span>
                    <span class="text-muted">Should be monitored</span>
                </div>
                <ul class="text-muted small">
                    <li><strong>Battery Low</strong> — SoC below 25%</li>
                    <li><strong>Undervoltage</strong> — Voltage below minimum</li>
                    <li><strong>Sensor Fault</strong> — Possible sensor malfunction</li>
                </ul>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-info me-2">Info</span>
                    <span class="text-muted">For information only</span>
                </div>
                <ul class="text-muted small">
                    <li><strong>System Events</strong> — General notifications</li>
                    <li><strong>Maintenance</strong> — Scheduled maintenance alerts</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function alertAction(alertId, action) {
    if (!confirm(action === 'resolve' ? 'Mark this alert as resolved?' : 'Acknowledge this alert?')) return;

    fetch('<?= BASE_URL ?>api/alerts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= $csrf ?>' },
        body: JSON.stringify({ action: action, alert_id: alertId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert('Error: ' + (res.error || 'Unknown error'));
        }
    })
    .catch(err => alert('Network error'));
}

function filterAlerts(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('#alertsTable tbody tr').forEach(row => {
        row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
