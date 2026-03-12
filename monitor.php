<?php
/**
 * Live Monitoring Page — Real-Time Microgrid Data
 */
$pageTitle = 'Live Monitor';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$familyId = getCurrentFamilyId();

if (isAdmin()) {
    if (isset($_GET['family_id'])) {
        $familyId = (int) $_GET['family_id'];
    } else {
        // Auto-select first family with microgrids so admin doesn't see empty data
        $db = getDB();
        $firstFamily = $db->query("SELECT f.family_id FROM families f JOIN microgrids m ON f.family_id = m.family_id GROUP BY f.family_id ORDER BY f.family_id LIMIT 1")->fetchColumn();
        if ($firstFamily) $familyId = (int) $firstFamily;
    }
}

$microgrids = getFamilyMicrogrids($familyId);
$battery = getLatestBatteryStatus($familyId);
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <?php if (isAdmin()): ?>
    <form method="GET" class="d-flex align-items-center gap-2 m-0">
        <label class="text-muted mb-0">Monitor Family:</label>
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
    <?php else: ?><div></div><?php endif; ?>
    <div class="d-flex align-items-center gap-3">
        <div id="liveStatusWrapper" class="d-flex align-items-center gap-2" style="color:var(--accent-green)">
            <span class="live-dot"></span>
            <small class="live-text fw-semibold">LIVE</small>
        </div>
        <small class="text-muted" id="lastUpdateTime">Connecting...</small>
        <select id="refreshInterval" class="form-select form-select-sm" style="width:auto" title="Auto-refresh interval">
            <option value="5000">5s</option>
            <option value="10000" selected>10s</option>
            <option value="30000">30s</option>
            <option value="60000">60s</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="manualRefresh" title="Refresh now"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
</div>

<!-- Real-Time Status Bar -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="bi bi-activity"></i></div>
            <div class="stat-value" id="totalPower">--</div>
            <div class="stat-label">Total Power (kW)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="bi bi-sun"></i></div>
            <div class="stat-value" id="solarPower">--</div>
            <div class="stat-label">Solar Power (kW)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="bi bi-wind"></i></div>
            <div class="stat-value" id="windPower">--</div>
            <div class="stat-label">Wind Power (kW)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-icon"><i class="bi bi-battery-charging"></i></div>
            <div class="stat-value" id="batteryPct"><?= !empty($battery) ? number_format($battery['battery_level'], 0) . '%' : 'N/A' ?></div>
            <div class="stat-label">Battery SoC</div>
        </div>
    </div>
</div>

<!-- Microgrid Live Panels -->
<div class="row g-3 mb-4">
    <?php foreach ($microgrids as $mg): ?>
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">
                    <i class="bi <?= $mg['type'] === 'solar' ? 'bi-sun text-warning' : 'bi-wind text-info' ?> me-2"></i>
                    <?= h($mg['microgrid_name']) ?>
                </h6>
                <span class="badge bg-<?= $mg['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($mg['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-3">
                        <div class="monitor-value" id="v_<?= $mg['microgrid_id'] ?>">--</div>
                        <div class="monitor-label">Voltage (V)</div>
                    </div>
                    <div class="col-3">
                        <div class="monitor-value" id="a_<?= $mg['microgrid_id'] ?>">--</div>
                        <div class="monitor-label">Current (A)</div>
                    </div>
                    <div class="col-3">
                        <div class="monitor-value" id="p_<?= $mg['microgrid_id'] ?>">--</div>
                        <div class="monitor-label">Power (kW)</div>
                    </div>
                    <div class="col-3">
                        <div class="monitor-value" id="t_<?= $mg['microgrid_id'] ?>">--</div>
                        <div class="monitor-label">Temp (°C)</div>
                    </div>
                </div>
                <canvas id="chart_<?= $mg['microgrid_id'] ?>" height="150"></canvas>
                <small class="text-muted" id="ts_<?= $mg['microgrid_id'] ?>">Waiting for data...</small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($microgrids)): ?>
    <div class="col-12 text-center text-muted py-5">
        <i class="bi bi-grid-3x3-gap fs-1"></i><br>No microgrids configured for this family.
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const charts = {};
    const maxPoints = 20;
    const familyId = <?= $familyId ?>;

    // Initialize line charts for each microgrid
    <?php foreach ($microgrids as $mg): ?>
    (function() {
        const ctx = document.getElementById('chart_<?= $mg['microgrid_id'] ?>');
        charts[<?= $mg['microgrid_id'] ?>] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Power (kW)',
                    data: [],
                    borderColor: '<?= $mg['type'] === 'solar' ? '#f59e0b' : '#3b82f6' ?>',
                    backgroundColor: '<?= $mg['type'] === 'solar' ? 'rgba(245,158,11,0.1)' : 'rgba(59,130,246,0.1)' ?>',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: true, grid: { display: false }, ticks: { color: '#64748b', maxTicksLimit: 6 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    })();
    <?php endforeach; ?>

    // Fetch real-time data
    function fetchRealtime() {
        fetch('<?= BASE_URL ?>api/analytics.php?action=realtime&family_id=' + familyId)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;

            let totalP = 0, solarP = 0, windP = 0;
            const now = new Date().toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});

            res.microgrids.forEach(mg => {
                const power = parseFloat(mg.power_kw) || 0;
                totalP += power;
                if (mg.type === 'solar') solarP += power;
                else windP += power;

                // Update values
                const id = mg.microgrid_id;
                const el = (prefix) => document.getElementById(prefix + '_' + id);
                if (el('v')) el('v').textContent = mg.voltage ? parseFloat(mg.voltage).toFixed(1) : '--';
                if (el('a')) el('a').textContent = mg.current_amp ? parseFloat(mg.current_amp).toFixed(2) : '--';
                if (el('p')) el('p').textContent = mg.power_kw ? parseFloat(mg.power_kw).toFixed(2) : '--';
                if (el('t')) el('t').textContent = mg.temperature ? parseFloat(mg.temperature).toFixed(1) : '--';
                if (el('ts')) el('ts').textContent = mg.timestamp ? 'Last: ' + mg.timestamp : 'No data';

                // Update chart
                if (charts[id]) {
                    const chart = charts[id];
                    chart.data.labels.push(now);
                    chart.data.datasets[0].data.push(power);
                    if (chart.data.labels.length > maxPoints) {
                        chart.data.labels.shift();
                        chart.data.datasets[0].data.shift();
                    }
                    chart.update('none');
                }
            });

            document.getElementById('totalPower').textContent = totalP.toFixed(2);
            document.getElementById('solarPower').textContent = solarP.toFixed(2);
            document.getElementById('windPower').textContent = windP.toFixed(2);

            if (res.battery && res.battery.battery_level !== undefined) {
                document.getElementById('batteryPct').textContent = parseFloat(res.battery.battery_level).toFixed(0) + '%';
            }
            // Update live status indicator
            const liveWrap = document.getElementById('liveStatusWrapper');
            if (liveWrap) {
                liveWrap.classList.remove('live-offline');
                liveWrap.style.color = 'var(--accent-green)';
                liveWrap.querySelector('.live-text').textContent = 'LIVE';
            }
            const nowStr = new Date().toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
            const ltu = document.getElementById('lastUpdateTime');
            if (ltu) ltu.textContent = 'Updated ' + nowStr;
        })
        .catch(err => {
            console.warn('Realtime fetch error:', err);
            const liveWrap = document.getElementById('liveStatusWrapper');
            if (liveWrap) {
                liveWrap.classList.add('live-offline');
                liveWrap.style.color = 'var(--accent-red)';
                liveWrap.querySelector('.live-text').textContent = 'OFFLINE';
            }
        });
    }

    fetchRealtime();
    let refreshTimer = setInterval(fetchRealtime, 10000);

    document.getElementById('refreshInterval').addEventListener('change', function() {
        clearInterval(refreshTimer);
        refreshTimer = setInterval(fetchRealtime, parseInt(this.value));
    });

    document.getElementById('manualRefresh').addEventListener('click', fetchRealtime);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
