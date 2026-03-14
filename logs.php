<?php
/**
 * System Logs Page
 */
$pageTitle = 'System Logs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();
$limit = min((int) ($_GET['limit'] ?? 100), 300);
$logs = isAdmin() ? getRecentSystemLogs(null, $limit) : getRecentSystemLogs($familyId, $limit);
?>

<div class="card dashboard-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>System Events Timeline</h6>
        <form method="GET" class="d-flex align-items-center gap-2 m-0">
            <label class="text-muted small mb-0">Rows</label>
            <select class="form-select form-select-sm" style="width:auto" name="limit" onchange="this.form.submit()">
                <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200</option>
                <option value="300" <?= $limit === 300 ? 'selected' : '' ?>>300</option>
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <?php if (isAdmin()): ?><th>Family</th><?php endif; ?>
                        <th>Event</th>
                        <th>Severity</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="<?= isAdmin() ? '5' : '4' ?>" class="text-center text-muted py-4">No logs yet</td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small><?= date('d M Y H:i:s', strtotime($log['timestamp'])) ?></small></td>
                        <?php if (isAdmin()): ?><td><?= h($log['family_name'] ?? '-') ?></td><?php endif; ?>
                        <td><span class="badge bg-dark"><?= h(str_replace('_', ' ', $log['event_type'])) ?></span></td>
                        <td><span class="badge <?= getSeverityClass($log['severity']) ?>"><?= h($log['severity']) ?></span></td>
                        <td><?= h($log['message']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
