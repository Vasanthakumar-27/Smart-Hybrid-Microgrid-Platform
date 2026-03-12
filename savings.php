<?php
/**
 * Financial Savings Page
 */
$pageTitle = 'Financial Savings';
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

$savings = calculateSavings($familyId);
$tariff = getCurrentTariff();
$sourceData = getEnergyBySource($familyId, 'all');

$solarKwh = 0;
$windKwh = 0;
foreach ($sourceData as $s) {
    if ($s['type'] === 'solar') $solarKwh = (float) $s['total'];
    if ($s['type'] === 'wind')  $windKwh = (float) $s['total'];
}
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

<!-- Savings Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
            <div class="stat-value"><?= formatCurrency($savings['daily_savings'], $savings['currency']) ?></div>
            <div class="stat-label">Today's Savings</div>
            <small class="text-muted"><?= formatEnergy($savings['daily_kwh']) ?> generated</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-value"><?= formatCurrency($savings['monthly_savings'], $savings['currency']) ?></div>
            <div class="stat-label">Monthly Savings</div>
            <small class="text-muted"><?= formatEnergy($savings['monthly_kwh']) ?> generated</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-trophy"></i></div>
            <div class="stat-value"><?= formatCurrency($savings['total_savings'], $savings['currency']) ?></div>
            <div class="stat-label">Total Savings (All Time)</div>
            <small class="text-muted"><?= formatEnergy($savings['total_kwh']) ?> generated</small>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Savings Breakdown -->
    <div class="col-lg-5">
        <div class="card dashboard-card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Savings Breakdown</h6></div>
            <div class="card-body">
                <div class="savings-formula mb-4 p-3 rounded" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2);">
                    <div class="text-muted small mb-1">Savings Formula</div>
                    <div class="fs-5 text-success">
                        Energy (kWh) × Tariff Rate = Savings
                    </div>
                    <div class="mt-2 text-muted small">
                        Current Rate: <strong><?= formatCurrency($tariff['rate_per_kwh'], $tariff['currency']) ?></strong>/kWh
                    </div>
                </div>

                <table class="table table-dark mb-0">
                    <thead>
                        <tr><th>Source</th><th class="text-end">Energy</th><th class="text-end">Savings</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="bi bi-sun text-warning me-1"></i> Solar</td>
                            <td class="text-end"><?= formatEnergy($solarKwh) ?></td>
                            <td class="text-end"><?= formatCurrency($solarKwh * $savings['rate'], $savings['currency']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-wind text-info me-1"></i> Wind</td>
                            <td class="text-end"><?= formatEnergy($windKwh) ?></td>
                            <td class="text-end"><?= formatCurrency($windKwh * $savings['rate'], $savings['currency']) ?></td>
                        </tr>
                        <tr class="table-active fw-bold">
                            <td>Total</td>
                            <td class="text-end"><?= formatEnergy($savings['total_kwh']) ?></td>
                            <td class="text-end text-success"><?= formatCurrency($savings['total_savings'], $savings['currency']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Savings Trend Chart -->
    <div class="col-lg-7">
        <div class="card dashboard-card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Savings Trend</h6></div>
            <div class="card-body">
                <canvas id="savingsChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Savings by Source Comparison -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Source Savings Distribution</h6></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="savingsPie" height="280"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Environmental Impact</h6></div>
            <div class="card-body">
                <?php
                    $co2Saved = $savings['total_kwh'] * 0.82; // kg CO2 per kWh (India grid avg)
                    $treesEquiv = $co2Saved / 21.77; // kg CO2 absorbed per tree per year
                ?>
                <div class="row text-center g-4 py-3">
                    <div class="col-6">
                        <i class="bi bi-cloud text-info fs-1"></i>
                        <div class="fs-4 fw-bold mt-2"><?= number_format($co2Saved, 1) ?> kg</div>
                        <div class="text-muted">CO₂ Emissions Avoided</div>
                    </div>
                    <div class="col-6">
                        <i class="bi bi-tree text-success fs-1"></i>
                        <div class="fs-4 fw-bold mt-2"><?= number_format($treesEquiv, 1) ?></div>
                        <div class="text-muted">Trees Equivalent (yearly)</div>
                    </div>
                </div>
                <div class="alert alert-success mt-3 mb-0" style="background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2); color: #6ee7b7;">
                    <i class="bi bi-leaf me-2"></i>
                    By using renewable energy, you have avoided <strong><?= number_format($co2Saved, 1) ?> kg</strong> of CO₂ emissions — that's equivalent to planting <strong><?= number_format($treesEquiv, 0) ?> trees</strong>!
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const familyId = <?= $familyId ?>;
    const rate = <?= $savings['rate'] ?>;

    // Monthly Savings Trend
    fetch('<?= BASE_URL ?>api/analytics.php?action=monthly_reports&family_id=' + familyId)
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.data.length) {
            document.getElementById('savingsChart').parentElement.innerHTML = '<div class="text-muted text-center py-5">No data available</div>';
            return;
        }
        const months = [...new Set(res.data.map(d => d.month))];
        const labels = months.map(m => {
            const row = res.data.find(d => d.month === m);
            return row ? row.month_label : m;
        });
        const totalSavings = months.map(m => {
            const rows = res.data.filter(d => d.month === m);
            const kwh = rows.reduce((sum, r) => sum + parseFloat(r.total_kwh), 0);
            return (kwh * rate).toFixed(2);
        });

        new Chart(document.getElementById('savingsChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Savings (<?= $savings['currency'] ?>)',
                    data: totalSavings,
                    backgroundColor: 'rgba(16,185,129,0.6)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#cbd5e1' } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' },
                         ticks: { color: '#94a3b8', callback: v => '<?= $savings['currency'] === 'INR' ? '₹' : htmlspecialchars($savings['currency'], ENT_QUOTES, 'UTF-8') . ' ' ?>' + v } }
                }
            }
        });
    });

    // Savings Pie by Source
    const solarSav = <?= round($solarKwh * $savings['rate'], 2) ?>;
    const windSav = <?= round($windKwh * $savings['rate'], 2) ?>;
    if (solarSav + windSav > 0) {
        new Chart(document.getElementById('savingsPie'), {
            type: 'doughnut',
            data: {
                labels: ['Solar Savings', 'Wind Savings'],
                datasets: [{ data: [solarSav, windSav], backgroundColor: ['#f59e0b', '#3b82f6'], borderWidth: 0, hoverOffset: 10 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#cbd5e1', padding: 15, usePointStyle: true } },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + ctx.raw.toFixed(2) } }
                }
            }
        });
    } else {
        document.getElementById('savingsPie').parentElement.innerHTML = '<div class="text-muted text-center py-5">No savings data yet</div>';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
