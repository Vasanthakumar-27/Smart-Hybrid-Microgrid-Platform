<?php
/**
 * Main Dashboard — Smart Microgrid Platform
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();
$role = getCurrentRole();

if ($role === 'admin') {
    $stats = getPlatformStats();
    $allAlerts = getAllActiveAlerts();
    $allMicrogrids = getAllMicrogridsWithHealth();
    $adminLogs = getRecentSystemLogs(null, 8);
} else {
    $microgrids = getFamilyMicrogrids($familyId);
    $battery = getLatestBatteryStatus($familyId);
    $savings = calculateSavings($familyId);
    $activeAlerts = getActiveAlerts($familyId);
    $sourceData = getEnergyBySource($familyId, 'month');
    $todayEnergy = getTotalEnergy($familyId, 'today');
    $monthEnergy = getTotalEnergy($familyId, 'month');
    $flow = getEnergyFlowSnapshot($familyId);
    $healthSummary = getFamilyMicrogridHealthSummary($familyId);

    $totalPower = $flow['solar_kw'] + $flow['wind_kw'];
    $solarPower = $flow['solar_kw'];
    $windPower = $flow['wind_kw'];
    $batteryPct = $battery['battery_level'] ?? 0;

    $solarTotal = 0.0;
    $windTotal = 0.0;
    foreach ($sourceData as $item) {
        if ($item['type'] === 'solar') {
            $solarTotal = (float) $item['total'];
        } elseif ($item['type'] === 'wind') {
            $windTotal = (float) $item['total'];
        }
    }
    $totalRenewable = max($solarTotal + $windTotal, 0.0001);
    $solarPct = round(($solarTotal / $totalRenewable) * 100, 1);
    $windPct = round(($windTotal / $totalRenewable) * 100, 1);
}
?>

<?php if ($role === 'admin'): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-houses"></i></div>
            <div class="stat-value"><?= $stats['families'] ?></div>
            <div class="stat-label">Families</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></div>
            <div class="stat-value"><?= $stats['microgrids'] ?></div>
            <div class="stat-label">Active Microgrids</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-lightning"></i></div>
            <div class="stat-value"><?= number_format($stats['total_capacity'], 1) ?></div>
            <div class="stat-label">Total Capacity (kW)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-danger">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= $stats['alerts'] ?></div>
            <div class="stat-label">Active Alerts</div>
        </div>
    </div>
</div>

<div class="card dashboard-card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-tools me-2"></i>Admin Controls</h6>
    </div>
    <div class="card-body">
        <div class="admin-actions-grid">
            <a class="btn btn-outline-light" href="<?= BASE_URL ?>admin/families.php"><i class="bi bi-house-add me-2"></i>Create Family</a>
            <a class="btn btn-outline-light" href="<?= BASE_URL ?>admin/microgrids.php"><i class="bi bi-grid-3x3-gap me-2"></i>Add Microgrid</a>
            <a class="btn btn-outline-light" href="<?= BASE_URL ?>admin/users.php"><i class="bi bi-person-plus me-2"></i>Assign User</a>
            <a class="btn btn-outline-light" href="<?= BASE_URL ?>monitor.php"><i class="bi bi-activity me-2"></i>View Microgrid Status</a>
            <a class="btn btn-outline-light" href="<?= BASE_URL ?>alerts.php"><i class="bi bi-bell me-2"></i>Manage Alerts</a>
            <a class="btn btn-outline-light" href="<?= BASE_URL ?>reports.php"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card dashboard-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Energy Generation by Family (This Month)</h6>
            </div>
            <div class="card-body">
                <canvas id="adminFamilyChart" height="280"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card dashboard-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Alerts</h6>
            </div>
            <div class="card-body p-0">
                <div class="alert-list">
                    <?php if (empty($allAlerts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-3"></i><br>No active alerts
                    </div>
                    <?php else: ?>
                    <?php foreach (array_slice($allAlerts, 0, 8) as $alert): ?>
                    <div class="alert-item">
                        <span class="badge <?= getSeverityClass($alert['severity']) ?>"><?= h($alert['severity']) ?></span>
                        <div class="alert-item-body">
                            <div class="alert-msg"><?= h($alert['message']) ?></div>
                            <small class="text-muted"><?= h($alert['family_name']) ?> • <?= date('d M H:i', strtotime($alert['timestamp'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-table me-2"></i>Microgrid Table</h6>
                <small class="text-muted">Active • Offline • Maintenance • Fault</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Microgrid</th>
                                <th>Type</th>
                                <th>Family</th>
                                <th>Status</th>
                                <th>Battery</th>
                                <th>Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($allMicrogrids, 0, 14) as $mg): ?>
                            <?php
                            $status = $mg['operational_status'];
                            $statusClass = 'secondary';
                            if ($status === 'active') $statusClass = 'success';
                            if ($status === 'maintenance') $statusClass = 'warning';
                            if ($status === 'fault') $statusClass = 'danger';
                            ?>
                            <tr>
                                <td><strong><?= h($mg['microgrid_name']) ?></strong></td>
                                <td><span class="badge bg-<?= $mg['type'] === 'solar' ? 'warning' : 'primary' ?>"><?= ucfirst($mg['type']) ?></span></td>
                                <td><?= h($mg['family_name']) ?></td>
                                <td><span class="badge bg-<?= $statusClass ?>"><?= ucfirst($status) ?></span></td>
                                <td><?= $mg['latest_battery_level'] !== null ? number_format((float) $mg['latest_battery_level'], 0) . '%' : 'N/A' ?></td>
                                <td>
                                    <span class="fw-semibold"><?= number_format((float) $mg['health_score'], 1) ?>%</span>
                                    <div class="progress mt-1" style="height:6px; width:110px;">
                                        <div class="progress-bar <?= $mg['health_score'] < 60 ? 'bg-danger' : ($mg['health_score'] < 80 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= max(0, min(100, (float) $mg['health_score'])) ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allMicrogrids)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No microgrids configured</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>System Logs</h6></div>
            <div class="card-body p-0">
                <div class="alert-list" style="max-height:360px;">
                    <?php if (empty($adminLogs)): ?>
                    <div class="text-center text-muted py-4">No logs yet</div>
                    <?php else: ?>
                    <?php foreach ($adminLogs as $log): ?>
                    <div class="alert-item">
                        <span class="badge <?= getSeverityClass($log['severity']) ?>"><?= h($log['severity']) ?></span>
                        <div class="alert-item-body">
                            <div class="alert-msg"><?= h($log['message']) ?></div>
                            <small class="text-muted"><?= date('d M H:i', strtotime($log['timestamp'])) ?><?php if (!empty($log['family_name'])): ?> • <?= h($log['family_name']) ?><?php endif; ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= BASE_URL ?>api/analytics.php?action=all_families_energy')
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;
        const labels = res.data.map(d => d.family_name);
        const values = res.data.map(d => parseFloat(d.total_kwh));
        new Chart(document.getElementById('adminFamilyChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Energy Generated (kWh)',
                    data: values,
                    backgroundColor: ['#EFB036', '#3B6790', '#16a34a', '#dc2626', '#22c55e', '#f59e0b'],
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    });
});
</script>

<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-lightning"></i></div>
            <div class="stat-value"><?= number_format($totalPower, 2) ?></div>
            <div class="stat-label">Total Power (kW)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-sun"></i></div>
            <div class="stat-value"><?= number_format($solarPower, 2) ?></div>
            <div class="stat-label">Solar Power (kW)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-wind"></i></div>
            <div class="stat-value"><?= number_format($windPower, 2) ?></div>
            <div class="stat-label">Wind Power (kW)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card <?= !empty($battery) && $batteryPct < 25 ? 'stat-danger' : 'stat-info' ?>">
            <div class="stat-icon"><i class="bi bi-battery-charging"></i></div>
            <div class="stat-value"><?= !empty($battery) ? number_format((float) $batteryPct, 0) . '%' : 'N/A' ?></div>
            <div class="stat-label">Battery SoC</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-shuffle me-2"></i>Energy Flow Visualization</h6></div>
            <div class="card-body">
                <div class="flow-grid">
                    <div class="flow-node solar-node">
                        <i class="bi bi-sun"></i>
                        <div>Solar</div>
                        <strong><?= number_format($flow['solar_kw'], 2) ?> kW</strong>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-node battery-node">
                        <i class="bi bi-battery-charging"></i>
                        <div>Battery</div>
                        <strong><?= number_format((float) ($flow['battery_level'] ?? 0), 0) ?>%</strong>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-node load-node">
                        <i class="bi bi-lightning-charge"></i>
                        <div>Load</div>
                        <strong><?= number_format($flow['load_kw'], 2) ?> kW</strong>
                    </div>
                </div>

                <div class="flow-grid mt-3">
                    <div class="flow-node wind-node">
                        <i class="bi bi-wind"></i>
                        <div>Wind</div>
                        <strong><?= number_format($flow['wind_kw'], 2) ?> kW</strong>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-node battery-node">
                        <i class="bi bi-arrow-repeat"></i>
                        <div><?= h(ucfirst($flow['battery_state'])) ?></div>
                        <strong><?= number_format($flow['source_to_battery_kw'], 2) ?> kW</strong>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-node load-node">
                        <i class="bi bi-house"></i>
                        <div>Consumption</div>
                        <strong><?= number_format($flow['battery_to_load_kw'], 2) ?> kW</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>Microgrid Health Score</h6></div>
            <div class="card-body">
                <?php if (empty($healthSummary)): ?>
                <div class="text-muted text-center py-5">No microgrid health data yet</div>
                <?php else: ?>
                <?php foreach (array_slice($healthSummary, 0, 4) as $hs): ?>
                <div class="health-row">
                    <div class="d-flex justify-content-between">
                        <span><?= h($hs['microgrid_name']) ?></span>
                        <strong><?= number_format($hs['health_score'], 1) ?>% Healthy</strong>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar <?= $hs['health_score'] < 60 ? 'bg-danger' : ($hs['health_score'] < 80 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= max(0, min(100, $hs['health_score'])) ?>%;"></div>
                    </div>
                    <small class="text-muted">Eff <?= number_format($hs['efficiency'], 1) ?>% • Uptime <?= number_format($hs['uptime'], 1) ?>% • Battery <?= number_format($hs['battery_health'], 1) ?>%</small>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Daily Energy Generation</h6>
                <select id="genDays" class="form-select form-select-sm" style="width:auto">
                    <option value="7">7 Days</option>
                    <option value="14">14 Days</option>
                    <option value="30" selected>30 Days</option>
                </select>
            </div>
            <div class="card-body">
                <canvas id="dailyGenChart" height="260"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card dashboard-card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>User Savings</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2"><span>Today's Savings</span><strong class="text-accent-green"><?= formatCurrency($savings['daily_savings'], $savings['currency']) ?></strong></div>
                <div class="d-flex justify-content-between align-items-center mb-2"><span>Monthly Savings</span><strong class="text-accent-blue"><?= formatCurrency($savings['monthly_savings'], $savings['currency']) ?></strong></div>
                <div class="d-flex justify-content-between align-items-center mb-2"><span>Today's Energy</span><strong><?= number_format($todayEnergy, 2) ?> kWh</strong></div>
                <div class="d-flex justify-content-between align-items-center"><span>Monthly Energy</span><strong><?= number_format($monthEnergy, 2) ?> kWh</strong></div>
            </div>
        </div>

        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Active Alerts</h6>
                <a href="<?= BASE_URL ?>alerts.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="alert-list">
                    <?php if (empty($activeAlerts)): ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-check-circle fs-3 text-success"></i><br>All systems normal</div>
                    <?php else: ?>
                    <?php foreach (array_slice($activeAlerts, 0, 6) as $alert): ?>
                    <div class="alert-item">
                        <span class="badge <?= getSeverityClass($alert['severity']) ?>"><?= h(ucwords(str_replace('_', ' ', $alert['alert_type']))) ?></span>
                        <div class="alert-item-body">
                            <div class="alert-msg"><?= h($alert['message']) ?></div>
                            <small class="text-muted"><?= date('d M H:i', strtotime($alert['timestamp'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card dashboard-card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Solar vs Wind Contribution</h6></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="sourceChart" height="250"></canvas>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                <div class="d-flex justify-content-between text-muted">
                    <span>Solar: <?= $solarPct ?>%</span>
                    <span>Wind: <?= $windPct ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card dashboard-card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-table me-2"></i>Microgrid Status Table</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Microgrid</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Battery</th>
                                <th>Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($healthSummary as $row): ?>
                            <?php
                            $statusClass = 'secondary';
                            if ($row['status'] === 'active') $statusClass = 'success';
                            if ($row['status'] === 'maintenance') $statusClass = 'warning';
                            if ($row['status'] === 'fault') $statusClass = 'danger';
                            ?>
                            <tr>
                                <td><?= h($row['microgrid_name']) ?></td>
                                <td><span class="badge bg-<?= $row['type'] === 'solar' ? 'warning' : 'primary' ?>"><?= ucfirst($row['type']) ?></span></td>
                                <td><span class="badge bg-<?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td><?= $row['battery_level'] !== null ? number_format((float) $row['battery_level'], 0) . '%' : 'N/A' ?></td>
                                <td><?= number_format((float) $row['health_score'], 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($healthSummary)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No microgrids configured</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sourceData = <?= json_encode($sourceData) ?>;
    const srcLabels = sourceData.map(d => d.type.charAt(0).toUpperCase() + d.type.slice(1));
    const srcValues = sourceData.map(d => parseFloat(d.total));
    const srcColors = sourceData.map(d => d.type === 'solar' ? '#EFB036' : '#3B6790');

    if (srcValues.some(v => v > 0)) {
        new Chart(document.getElementById('sourceChart'), {
            type: 'doughnut',
            data: {
                labels: srcLabels,
                datasets: [{ data: srcValues, backgroundColor: srcColors, borderWidth: 0, hoverOffset: 8 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { position: 'bottom', labels: { color: '#cbd5e1', usePointStyle: true } } }
            }
        });
    } else {
        document.getElementById('sourceChart').parentElement.innerHTML = '<div class="text-muted text-center py-5">No generation data yet</div>';
    }

    function loadDailyChart(days) {
        fetch('<?= BASE_URL ?>api/analytics.php?action=daily_generation&days=' + days + '&family_id=<?= $familyId ?>')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const dates = [...new Set(res.data.map(d => d.date))];
            const solar = dates.map(dt => {
                const row = res.data.find(d => d.date === dt && d.type === 'solar');
                return row ? parseFloat(row.total_kwh) : 0;
            });
            const wind = dates.map(dt => {
                const row = res.data.find(d => d.date === dt && d.type === 'wind');
                return row ? parseFloat(row.total_kwh) : 0;
            });

            const ctx = document.getElementById('dailyGenChart');
            if (window.dailyChart) window.dailyChart.destroy();
            window.dailyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates.map(d => { const dt = new Date(d); return dt.toLocaleDateString('en-IN', {day:'numeric',month:'short'}); }),
                    datasets: [
                        { label: 'Solar', data: solar, backgroundColor: '#EFB036', borderRadius: 4, borderSkipped: false },
                        { label: 'Wind', data: wind, backgroundColor: '#3B6790', borderRadius: 4, borderSkipped: false }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1', usePointStyle: true } } },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 45 } },
                        y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' },
                             title: { display: true, text: 'kWh', color: '#94a3b8' } }
                    }
                }
            });
        });
    }

    loadDailyChart(30);
    document.getElementById('genDays').addEventListener('change', function() { loadDailyChart(this.value); });

    setInterval(function() {
        fetch('<?= BASE_URL ?>api/analytics.php?action=realtime&family_id=<?= $familyId ?>').catch(() => {});
    }, 10000);
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
