<?php
/**
 * Energy Analytics Page
 */
$pageTitle = 'Energy Analytics';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();
if (isAdmin()) {
    if (isset($_GET['family_id'])) {
        $familyId = (int) $_GET['family_id'];
    } else {
        $db = getDB();
        $firstFamily = $db->query("SELECT f.family_id FROM families f JOIN microgrids m ON f.family_id = m.family_id GROUP BY f.family_id ORDER BY f.family_id LIMIT 1")->fetchColumn();
        if ($firstFamily) $familyId = (int) $firstFamily;
    }
}

$todayEnergy = getTotalEnergy($familyId, 'today');
$weekEnergy = getTotalEnergy($familyId, 'week');
$monthEnergy = getTotalEnergy($familyId, 'month');
$yearEnergy = getTotalEnergy($familyId, 'year');
$sourceData = getEnergyBySource($familyId, 'month');

$solarTotal = 0;
$windTotal = 0;
foreach ($sourceData as $s) {
    if ($s['type'] === 'solar') $solarTotal = (float) $s['total'];
    if ($s['type'] === 'wind')  $windTotal = (float) $s['total'];
}
$grandTotal = $solarTotal + $windTotal;
$solarPct = $grandTotal > 0 ? round(($solarTotal / $grandTotal) * 100, 1) : 0;
$windPct  = $grandTotal > 0 ? round(($windTotal / $grandTotal) * 100, 1) : 0;
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

<!-- Energy Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
            <div class="stat-value"><?= number_format($todayEnergy, 2) ?></div>
            <div class="stat-label">Today (kWh)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="stat-value"><?= number_format($weekEnergy, 2) ?></div>
            <div class="stat-label">This Week (kWh)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-value"><?= number_format($monthEnergy, 2) ?></div>
            <div class="stat-label">This Month (kWh)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-info">
            <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value"><?= number_format($yearEnergy, 2) ?></div>
            <div class="stat-label">This Year (kWh)</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Source Contribution -->
    <div class="col-lg-5">
        <div class="card dashboard-card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Source Contribution (This Month)</h6></div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-7">
                        <canvas id="sourcePie" height="220"></canvas>
                    </div>
                    <div class="col-5">
                        <div class="source-legend">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="legend-dot" style="background:#f59e0b"></span>
                                    <span class="text-muted">Solar</span>
                                </div>
                                <div class="fs-5 fw-bold"><?= number_format($solarTotal, 2) ?> kWh</div>
                                <div class="text-warning"><?= $solarPct ?>%</div>
                            </div>
                            <div>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="legend-dot" style="background:#3b82f6"></span>
                                    <span class="text-muted">Wind</span>
                                </div>
                                <div class="fs-5 fw-bold"><?= number_format($windTotal, 2) ?> kWh</div>
                                <div class="text-info"><?= $windPct ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Generation -->
    <div class="col-lg-7">
        <div class="card dashboard-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Daily Generation</h6>
                <select id="dailyDays" class="form-select form-select-sm" style="width:auto">
                    <option value="7">7 Days</option>
                    <option value="14">14 Days</option>
                    <option value="30" selected>30 Days</option>
                    <option value="60">60 Days</option>
                </select>
            </div>
            <div class="card-body">
                <canvas id="dailyBarChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Weekly Trends -->
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Weekly Trends</h6></div>
            <div class="card-body">
                <canvas id="weeklyChart" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Reports -->
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Monthly Reports</h6></div>
            <div class="card-body">
                <canvas id="monthlyChart" height="280"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const familyId = <?= $familyId ?>;
    const baseUrl = '<?= BASE_URL ?>api/analytics.php';

    // Source Contribution Pie
    const solarVal = <?= $solarTotal ?>;
    const windVal = <?= $windTotal ?>;
    if (solarVal + windVal > 0) {
        new Chart(document.getElementById('sourcePie'), {
            type: 'doughnut',
            data: {
                labels: ['Solar', 'Wind'],
                datasets: [{ data: [solarVal, windVal], backgroundColor: ['#f59e0b', '#3b82f6'], borderWidth: 0, hoverOffset: 10 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { display: false } }
            }
        });
    } else {
        document.getElementById('sourcePie').parentElement.innerHTML = '<div class="text-center text-muted py-5">No generation data</div>';
    }

    // Daily Generation Stacked Bar
    let dailyChart;
    function loadDaily(days) {
        fetch(baseUrl + '?action=daily_generation&days=' + days + '&family_id=' + familyId)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const dates = [...new Set(res.data.map(d => d.date))];
            const solar = dates.map(dt => { const r = res.data.find(d => d.date === dt && d.type === 'solar'); return r ? parseFloat(r.total_kwh) : 0; });
            const wind = dates.map(dt => { const r = res.data.find(d => d.date === dt && d.type === 'wind'); return r ? parseFloat(r.total_kwh) : 0; });
            const labels = dates.map(d => new Date(d).toLocaleDateString('en-IN', {day:'numeric',month:'short'}));

            if (dailyChart) dailyChart.destroy();
            dailyChart = new Chart(document.getElementById('dailyBarChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Solar', data: solar, backgroundColor: '#f59e0b', borderRadius: 4, borderSkipped: false },
                        { label: 'Wind', data: wind, backgroundColor: '#3b82f6', borderRadius: 4, borderSkipped: false }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1', usePointStyle: true } } },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 45 } },
                        y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                    }
                }
            });
        });
    }
    loadDaily(30);
    document.getElementById('dailyDays').addEventListener('change', function() { loadDaily(this.value); });

    // Weekly Trends
    fetch(baseUrl + '?action=weekly_trends&family_id=' + familyId)
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.data.length) return;
        new Chart(document.getElementById('weeklyChart'), {
            type: 'line',
            data: {
                labels: res.data.map(d => {
                    const dt = new Date(d.week_start);
                    return dt.toLocaleDateString('en-IN', {day:'numeric',month:'short'});
                }),
                datasets: [{
                    label: 'Weekly Energy (kWh)',
                    data: res.data.map(d => parseFloat(d.total_kwh)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#10b981',
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#cbd5e1' } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    });

    // Monthly Reports (grouped bar: solar vs wind)
    fetch(baseUrl + '?action=monthly_reports&family_id=' + familyId)
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.data.length) return;
        const months = [...new Set(res.data.map(d => d.month))];
        const mLabels = months.map(m => {
            const row = res.data.find(d => d.month === m);
            return row ? row.month_label : m;
        });
        const solar = months.map(m => { const r = res.data.find(d => d.month === m && d.type === 'solar'); return r ? parseFloat(r.total_kwh) : 0; });
        const wind = months.map(m => { const r = res.data.find(d => d.month === m && d.type === 'wind'); return r ? parseFloat(r.total_kwh) : 0; });

        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: mLabels,
                datasets: [
                    { label: 'Solar', data: solar, backgroundColor: '#f59e0b', borderRadius: 4 },
                    { label: 'Wind', data: wind, backgroundColor: '#3b82f6', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#cbd5e1', usePointStyle: true } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
