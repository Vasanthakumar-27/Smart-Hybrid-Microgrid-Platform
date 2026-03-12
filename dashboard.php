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
} else {
    $microgrids = getFamilyMicrogrids($familyId);
    $battery = getLatestBatteryStatus($familyId);
    $savings = calculateSavings($familyId);
    $activeAlerts = getActiveAlerts($familyId);
    $sourceData = getEnergyBySource($familyId, 'month');
    $todayEnergy = getTotalEnergy($familyId, 'today');
    $monthEnergy = getTotalEnergy($familyId, 'month');
}
?>

<?php if ($role === 'admin'): ?>
<!-- ==================== ADMIN DASHBOARD ==================== -->
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

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Energy Generation by Family (This Month)</h6>
            </div>
            <div class="card-body">
                <canvas id="adminFamilyChart" height="280"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
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
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-globe me-2"></i>Platform Summary</h6></div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="fs-3 fw-bold text-primary"><?= number_format($stats['total_energy'], 1) ?></div>
                        <div class="text-muted">Total Energy (kWh)</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-3 fw-bold text-success"><?= $stats['users'] ?></div>
                        <div class="text-muted">Total Users</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-3 fw-bold text-warning"><?= $stats['microgrids'] ?></div>
                        <div class="text-muted">Active Microgrids</div>
                    </div>
                    <div class="col-md-3">
                        <?php
                        $tariff = getCurrentTariff();
                        $totalSavings = $stats['total_energy'] * $tariff['rate_per_kwh'];
                        ?>
                        <div class="fs-3 fw-bold text-info"><?= formatCurrency($totalSavings, $tariff['currency']) ?></div>
                        <div class="text-muted">Est. Total Savings</div>
                    </div>
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
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
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
<!-- ==================== USER DASHBOARD ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-lightning"></i></div>
            <div class="stat-value"><?= number_format($todayEnergy, 1) ?></div>
            <div class="stat-label">Today's Energy (kWh)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-value"><?= number_format($monthEnergy, 1) ?></div>
            <div class="stat-label">This Month (kWh)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-piggy-bank"></i></div>
            <div class="stat-value"><?= formatCurrency($savings['monthly_savings'], $savings['currency']) ?></div>
            <div class="stat-label">Monthly Savings</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card <?= !empty($battery) && $battery['battery_level'] < 25 ? 'stat-danger' : 'stat-info' ?>">
            <div class="stat-icon"><i class="bi bi-battery-charging"></i></div>
            <div class="stat-value"><?= !empty($battery) ? number_format($battery['battery_level'], 0) . '%' : 'N/A' ?></div>
            <div class="stat-label">Battery Level</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Microgrid Status Cards -->
    <div class="col-lg-8">
        <div class="card dashboard-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Microgrid Status</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($microgrids as $mg): ?>
                    <?php $latest = getLatestReading($mg['microgrid_id']); ?>
                    <div class="col-md-6">
                        <div class="microgrid-card <?= $mg['type'] ?>">
                            <div class="mg-header">
                                <i class="bi <?= $mg['type'] === 'solar' ? 'bi-sun' : 'bi-wind' ?>"></i>
                                <div>
                                    <div class="mg-name"><?= h($mg['microgrid_name']) ?></div>
                                    <small class="mg-type"><?= ucfirst($mg['type']) ?> • <?= $mg['capacity_kw'] ?> kW</small>
                                </div>
                                <span class="badge bg-<?= $mg['status'] === 'active' ? 'success' : 'secondary' ?> ms-auto"><?= ucfirst($mg['status']) ?></span>
                            </div>
                            <?php if ($latest): ?>
                            <div class="mg-readings">
                                <div class="mg-reading">
                                    <span class="reading-label">Voltage</span>
                                    <span class="reading-value"><?= number_format($latest['voltage'], 1) ?> V</span>
                                </div>
                                <div class="mg-reading">
                                    <span class="reading-label">Current</span>
                                    <span class="reading-value"><?= number_format($latest['current_amp'], 2) ?> A</span>
                                </div>
                                <div class="mg-reading">
                                    <span class="reading-label">Power</span>
                                    <span class="reading-value"><?= number_format($latest['power_kw'], 2) ?> kW</span>
                                </div>
                                <div class="mg-reading">
                                    <span class="reading-label">Temp</span>
                                    <span class="reading-value"><?= $latest['temperature'] ? number_format($latest['temperature'], 1) . '°C' : 'N/A' ?></span>
                                </div>
                            </div>
                            <small class="text-muted">Last: <?= date('d M H:i', strtotime($latest['timestamp'])) ?></small>
                            <?php else: ?>
                            <div class="text-muted text-center py-3">No readings yet</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($microgrids)): ?>
                    <div class="text-center text-muted py-4">No microgrids configured. Contact admin.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Source Contribution Pie -->
    <div class="col-lg-4">
        <div class="card dashboard-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Source Contribution</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="sourceChart" height="260"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Daily Generation Chart -->
    <div class="col-lg-8">
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

    <!-- Active Alerts -->
    <div class="col-lg-4">
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Active Alerts</h6>
                <a href="<?= BASE_URL ?>alerts.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="alert-list">
                    <?php if (empty($activeAlerts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-3 text-success"></i><br>All systems normal
                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Source Contribution Pie Chart
    const sourceData = <?= json_encode($sourceData) ?>;
    const srcLabels = sourceData.map(d => d.type.charAt(0).toUpperCase() + d.type.slice(1));
    const srcValues = sourceData.map(d => parseFloat(d.total));
    const srcColors = sourceData.map(d => d.type === 'solar' ? '#f59e0b' : '#3b82f6');

    if (srcValues.some(v => v > 0)) {
        new Chart(document.getElementById('sourceChart'), {
            type: 'doughnut',
            data: {
                labels: srcLabels,
                datasets: [{
                    data: srcValues,
                    backgroundColor: srcColors,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#cbd5e1', padding: 15, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                                const pct = ((ctx.raw / total) * 100).toFixed(1);
                                return ctx.label + ': ' + ctx.raw.toFixed(2) + ' kWh (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('sourceChart').parentElement.innerHTML = '<div class="text-muted text-center py-5">No generation data yet</div>';
    }

    // Daily Generation Chart
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
                        { label: 'Solar', data: solar, backgroundColor: '#f59e0b', borderRadius: 4, borderSkipped: false },
                        { label: 'Wind', data: wind, backgroundColor: '#3b82f6', borderRadius: 4, borderSkipped: false }
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
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
