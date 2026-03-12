<?php
/**
 * Battery Monitoring Page
 */
$pageTitle = 'Battery Storage';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();
if (isAdmin()) {
    if (isset($_GET['family_id'])) {
        $familyId = (int) $_GET['family_id'];
    } else {
        $db = getDB();
        $firstFamily = $db->query("SELECT f.family_id FROM families f JOIN battery_status b ON f.family_id = b.family_id GROUP BY f.family_id ORDER BY f.family_id LIMIT 1")->fetchColumn();
        if ($firstFamily) $familyId = (int) $firstFamily;
    }
}

$battery = getLatestBatteryStatus($familyId);
?>

<?php if (isAdmin()): ?>
<div class="mb-3">
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="text-muted">Family:</label>
        <select name="family_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <?php
            $db = getDB();
            $fams = $db->query("SELECT family_id, family_name FROM families ORDER BY family_name")->fetchAll();
            foreach ($fams as $f):
            ?>
            <option value="<?= $f['family_id'] ?>" <?= $f['family_id'] == $familyId ? 'selected' : '' ?>><?= h($f['family_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($battery) && (float)$battery['battery_level'] < 25): ?>
<div class="d-flex align-items-center gap-2 mb-3" style="background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.35); color:#fca5a5; border-radius:12px; padding:0.9rem 1.1rem;" role="alert">
    <i class="bi bi-battery-exclamation fs-4 flex-shrink-0"></i>
    <div>
        <strong>Low Battery Warning</strong> &mdash; Battery is at <strong><?= number_format((float)$battery['battery_level'], 0) ?>%</strong>.
        <?= $battery['charge_status'] === 'charging' ? 'Currently charging.' : 'Consider reducing load or enabling grid charging.' ?>
    </div>
</div>
<?php endif; ?>

<!-- Battery Status Panel -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card dashboard-card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-battery-charging me-2"></i>Battery Status</h6></div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <?php if (!empty($battery)): ?>
                <?php
                    $level = (float) $battery['battery_level'];
                    $color = $level > 60 ? '#10b981' : ($level > 25 ? '#f59e0b' : '#ef4444');
                ?>
                <div class="battery-visual mb-3">
                    <div class="battery-shell">
                        <div class="battery-cap"></div>
                        <div class="battery-body">
                            <div class="battery-fill" style="height: <?= min($level, 100) ?>%; background: <?= $color ?>;">
                                <span class="battery-pct"><?= number_format($level, 0) ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <div class="mb-2">
                        <span class="badge bg-<?= $battery['charge_status'] === 'charging' ? 'success' : ($battery['charge_status'] === 'discharging' ? 'warning' : 'secondary') ?> fs-6">
                            <i class="bi <?= $battery['charge_status'] === 'charging' ? 'bi-lightning' : ($battery['charge_status'] === 'discharging' ? 'bi-arrow-down' : 'bi-pause') ?>"></i>
                            <?= ucfirst($battery['charge_status']) ?>
                        </span>
                    </div>
                    <table class="table table-dark table-sm mb-0">
                        <tr><td class="text-muted">Voltage</td><td class="text-end"><?= number_format($battery['voltage'], 1) ?> V</td></tr>
                        <tr><td class="text-muted">Remaining</td><td class="text-end"><?= number_format($battery['remaining_kwh'], 2) ?> kWh</td></tr>
                        <tr><td class="text-muted">Capacity</td><td class="text-end"><?= number_format($battery['capacity_kwh'], 1) ?> kWh</td></tr>
                        <?php if ($battery['temperature']): ?>
                        <tr><td class="text-muted">Temperature</td><td class="text-end"><?= number_format($battery['temperature'], 1) ?>°C</td></tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Last Updated</td><td class="text-end"><?= date('d M H:i', strtotime($battery['timestamp'])) ?></td></tr>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-muted text-center py-4">
                    <i class="bi bi-battery fs-1"></i><br>No battery data available
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card dashboard-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Battery Level History</h6>
                <select id="batteryHours" class="form-select form-select-sm" style="width:auto">
                    <option value="6">6 Hours</option>
                    <option value="12">12 Hours</option>
                    <option value="24" selected>24 Hours</option>
                    <option value="48">48 Hours</option>
                    <option value="168">7 Days</option>
                </select>
            </div>
            <div class="card-body">
                <canvas id="batteryChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Battery Metrics Row -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Voltage History</h6></div>
            <div class="card-body">
                <canvas id="voltageChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-thermometer-half me-2"></i>Temperature History</h6></div>
            <div class="card-body">
                <canvas id="tempChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let battChart, voltChart, tempChart;
    const familyId = <?= $familyId ?>;

    function loadBatteryData(hours) {
        fetch('<?= BASE_URL ?>api/analytics.php?action=battery_history&hours=' + hours + '&family_id=' + familyId)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data.length) {
                ['batteryChart','voltageChart','tempChart'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.parentElement.innerHTML = '<div class="text-muted text-center py-5">No battery data for this period</div>';
                });
                return;
            }

            const labels = res.data.map(d => {
                const dt = new Date(d.timestamp);
                return dt.toLocaleString('en-IN', {day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
            });
            const levels = res.data.map(d => parseFloat(d.battery_level));
            const volts = res.data.map(d => parseFloat(d.voltage));
            const temps = res.data.map(d => d.temperature ? parseFloat(d.temperature) : null);

            // Battery Level Chart
            if (battChart) battChart.destroy();
            battChart = new Chart(document.getElementById('batteryChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'SoC (%)',
                        data: levels,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 1,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748b', maxTicksLimit: 10 } },
                        y: { min: 0, max: 100, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', callback: v => v+'%' } }
                    }
                }
            });

            // Voltage Chart
            if (voltChart) voltChart.destroy();
            voltChart = new Chart(document.getElementById('voltageChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Voltage (V)',
                        data: volts,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 1,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748b', maxTicksLimit: 8 } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                    }
                }
            });

            // Temperature Chart
            if (tempChart) tempChart.destroy();
            const validTemps = temps.filter(t => t !== null);
            if (validTemps.length > 0) {
                tempChart = new Chart(document.getElementById('tempChart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Temperature (°C)',
                            data: temps,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 1,
                            spanGaps: true,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#cbd5e1' } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: '#64748b', maxTicksLimit: 8 } },
                            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', callback: v => v+'°C' } }
                        }
                    }
                });
            } else {
                document.getElementById('tempChart').parentElement.innerHTML = '<div class="text-muted text-center py-5">No temperature data</div>';
            }
        });
    }

    loadBatteryData(24);
    document.getElementById('batteryHours').addEventListener('change', function() { loadBatteryData(this.value); });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
