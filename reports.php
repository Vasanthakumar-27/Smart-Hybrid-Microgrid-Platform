<?php
/**
 * Reports Page - Daily/Weekly/Monthly with CSV export
 */
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();
if (isAdmin()) {
    if (isset($_GET['family_id']) && (int) $_GET['family_id'] > 0) {
        $familyId = (int) $_GET['family_id'];
    } else {
        $db = getDB();
        $fallbackFamily = $db->query("SELECT family_id FROM families ORDER BY family_id LIMIT 1")->fetchColumn();
        $familyId = $fallbackFamily ? (int) $fallbackFamily : 0;
    }
}

$period = $_GET['period'] ?? 'monthly';
$period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'monthly';

$rows = [];
if ($period === 'daily') {
    $rows = getDailyGeneration($familyId, 30);
} elseif ($period === 'weekly') {
    $rows = getWeeklyTrends($familyId, 12);
} else {
    $rows = getMonthlyReports($familyId, 12);
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="microgrid_' . $period . '_report.csv"');
    $out = fopen('php://output', 'w');

    if ($period === 'daily') {
        fputcsv($out, ['Date', 'Type', 'Energy (kWh)']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['date'], $r['type'], $r['total_kwh']]);
        }
    } elseif ($period === 'weekly') {
        fputcsv($out, ['Week Number', 'Week Start', 'Energy (kWh)']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['week_num'], $r['week_start'], $r['total_kwh']]);
        }
    } else {
        fputcsv($out, ['Month', 'Type', 'Energy (kWh)']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['month_label'], $r['type'], $r['total_kwh']]);
        }
    }

    fclose($out);
    exit;
}
?>

<?php if (isAdmin()): ?>
<div class="mb-3">
    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
        <label class="text-muted mb-0">Family</label>
        <select name="family_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <?php
            $db = getDB();
            $families = $db->query("SELECT family_id, family_name FROM families ORDER BY family_name")->fetchAll();
            foreach ($families as $f):
            ?>
            <option value="<?= $f['family_id'] ?>" <?= $f['family_id'] == $familyId ? 'selected' : '' ?>><?= h($f['family_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="text-muted mb-0 ms-2">Period</label>
        <select name="period" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
            <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
            <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
        </select>
    </form>
</div>
<?php else: ?>
<div class="mb-3 d-flex align-items-center gap-2">
    <a class="btn btn-sm <?= $period === 'daily' ? 'btn-primary' : 'btn-outline-light' ?>" href="?period=daily">Daily</a>
    <a class="btn btn-sm <?= $period === 'weekly' ? 'btn-primary' : 'btn-outline-light' ?>" href="?period=weekly">Weekly</a>
    <a class="btn btn-sm <?= $period === 'monthly' ? 'btn-primary' : 'btn-outline-light' ?>" href="?period=monthly">Monthly</a>
</div>
<?php endif; ?>

<div class="d-flex gap-2 mb-3">
    <a class="btn btn-sm btn-success" href="?period=<?= urlencode($period) ?>&family_id=<?= (int) $familyId ?>&export=csv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
    <button class="btn btn-sm btn-outline-light" onclick="window.print()"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF (Print)</button>
</div>

<div class="card dashboard-card">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-table me-2"></i><?= ucfirst($period) ?> Report</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <?php if ($period === 'daily'): ?>
                    <tr><th>Date</th><th>Type</th><th>Energy (kWh)</th></tr>
                    <?php elseif ($period === 'weekly'): ?>
                    <tr><th>Week Number</th><th>Week Start</th><th>Energy (kWh)</th></tr>
                    <?php else: ?>
                    <tr><th>Month</th><th>Type</th><th>Energy (kWh)</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No report rows available</td></tr>
                    <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <?php if ($period === 'daily'): ?>
                    <tr>
                        <td><?= h($r['date']) ?></td>
                        <td><?= h(ucfirst($r['type'])) ?></td>
                        <td><?= number_format((float) $r['total_kwh'], 3) ?></td>
                    </tr>
                    <?php elseif ($period === 'weekly'): ?>
                    <tr>
                        <td><?= h($r['week_num']) ?></td>
                        <td><?= h($r['week_start']) ?></td>
                        <td><?= number_format((float) $r['total_kwh'], 3) ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><?= h($r['month_label']) ?></td>
                        <td><?= h(ucfirst($r['type'])) ?></td>
                        <td><?= number_format((float) $r['total_kwh'], 3) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
