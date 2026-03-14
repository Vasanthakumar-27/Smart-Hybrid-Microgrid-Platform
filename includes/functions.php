<?php
/**
 * Helper functions for the Microgrid Platform
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Clamp a value between min and max
 */
function clampFloat(float $value, float $min = 0.0, float $max = 100.0): float {
    return max($min, min($max, $value));
}

/**
 * Ensure system log table exists for operational events
 */
function ensureSystemLogsTable(): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS system_logs (
        log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        family_id INT NULL,
        user_id INT NULL,
        microgrid_id INT NULL,
        event_type VARCHAR(60) NOT NULL,
        severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
        message VARCHAR(500) NOT NULL,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logs_time (timestamp),
        INDEX idx_logs_family (family_id),
        INDEX idx_logs_event (event_type)
    ) ENGINE=InnoDB");
}

/**
 * Write a system event log
 */
function logSystemEvent(?int $familyId, ?int $userId, ?int $microgridId, string $eventType, string $message, string $severity = 'info'): void {
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'info';
    }

    ensureSystemLogsTable();
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO system_logs (family_id, user_id, microgrid_id, event_type, severity, message, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$familyId, $userId, $microgridId, $eventType, $severity, $message]);
}

/**
 * Get recent system logs
 */
function getRecentSystemLogs(?int $familyId = null, int $limit = 100): array {
    ensureSystemLogsTable();
    $db = getDB();
    $limit = max(1, min($limit, 500));

    if ($familyId) {
        $sql = "SELECT l.*, f.family_name, m.microgrid_name, u.username
                FROM system_logs l
                LEFT JOIN families f ON l.family_id = f.family_id
                LEFT JOIN microgrids m ON l.microgrid_id = m.microgrid_id
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.family_id = ?
                ORDER BY l.timestamp DESC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$familyId, $limit]);
        return $stmt->fetchAll();
    }

    $sql = "SELECT l.*, f.family_name, m.microgrid_name, u.username
            FROM system_logs l
            LEFT JOIN families f ON l.family_id = f.family_id
            LEFT JOIN microgrids m ON l.microgrid_id = m.microgrid_id
            LEFT JOIN users u ON l.user_id = u.user_id
            ORDER BY l.timestamp DESC
            LIMIT ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// ============================================================================
// ENERGY & ANALYTICS FUNCTIONS
// ============================================================================

/**
 * Get total energy generated for a family in a period
 */
function getTotalEnergy(int $familyId, string $period = 'today'): float {
    $db = getDB();
    $dateFilter = getDateFilter($period);
    $sql = "SELECT COALESCE(SUM(er.energy_kwh), 0) as total
            FROM energy_readings er
            JOIN microgrids m ON er.microgrid_id = m.microgrid_id
            WHERE m.family_id = ? AND er.timestamp >= ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId, $dateFilter]);
    return (float) $stmt->fetchColumn();
}

/**
 * Get energy by source type for a family
 */
function getEnergyBySource(int $familyId, string $period = 'month'): array {
    $db = getDB();
    $dateFilter = getDateFilter($period);
    $sql = "SELECT m.type, COALESCE(SUM(er.energy_kwh), 0) as total
            FROM microgrids m
            LEFT JOIN energy_readings er ON er.microgrid_id = m.microgrid_id AND er.timestamp >= ?
            WHERE m.family_id = ?
            GROUP BY m.type";
    $stmt = $db->prepare($sql);
    $stmt->execute([$dateFilter, $familyId]);
    return $stmt->fetchAll();
}

/**
 * Get daily energy generation for chart (last N days)
 */
function getDailyGeneration(int $familyId, int $days = 30): array {
    $db = getDB();
    $sql = "SELECT DATE(er.timestamp) as date, m.type,
                   COALESCE(SUM(er.energy_kwh), 0) as total_kwh
            FROM energy_readings er
            JOIN microgrids m ON er.microgrid_id = m.microgrid_id
            WHERE m.family_id = ? AND er.timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(er.timestamp), m.type
            ORDER BY date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId, $days]);
    return $stmt->fetchAll();
}

/**
 * Get weekly energy trends
 */
function getWeeklyTrends(int $familyId, int $weeks = 12): array {
    $db = getDB();
    $sql = "SELECT YEARWEEK(er.timestamp, 1) as week_num,
                   MIN(DATE(er.timestamp)) as week_start,
                   COALESCE(SUM(er.energy_kwh), 0) as total_kwh
            FROM energy_readings er
            JOIN microgrids m ON er.microgrid_id = m.microgrid_id
            WHERE m.family_id = ? AND er.timestamp >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            GROUP BY YEARWEEK(er.timestamp, 1)
            ORDER BY week_num ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId, $weeks]);
    return $stmt->fetchAll();
}

/**
 * Get monthly energy reports
 */
function getMonthlyReports(int $familyId, int $months = 12): array {
    $db = getDB();
    $sql = "SELECT DATE_FORMAT(er.timestamp, '%Y-%m') as month,
                   DATE_FORMAT(er.timestamp, '%b %Y') as month_label,
                   m.type,
                   COALESCE(SUM(er.energy_kwh), 0) as total_kwh
            FROM energy_readings er
            JOIN microgrids m ON er.microgrid_id = m.microgrid_id
            WHERE m.family_id = ? AND er.timestamp >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(er.timestamp, '%Y-%m'), m.type
            ORDER BY month ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId, $months]);
    return $stmt->fetchAll();
}

// ============================================================================
// FINANCIAL SAVINGS FUNCTIONS
// ============================================================================

/**
 * Get current active tariff rate
 */
function getCurrentTariff(): array {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM tariff_settings WHERE is_active = 1 ORDER BY effective_from DESC LIMIT 1");
    return $stmt->fetch() ?: ['rate_per_kwh' => 7.50, 'currency' => 'INR'];
}

/**
 * Calculate energy savings for a family
 */
function calculateSavings(int $familyId, string $period = 'month'): array {
    $tariff = getCurrentTariff();
    $rate = (float) $tariff['rate_per_kwh'];
    $currency = $tariff['currency'];

    $todayEnergy  = getTotalEnergy($familyId, 'today');
    $monthEnergy  = getTotalEnergy($familyId, 'month');
    $totalEnergy  = getTotalEnergy($familyId, 'all');

    return [
        'daily_kwh'     => round($todayEnergy, 2),
        'monthly_kwh'   => round($monthEnergy, 2),
        'total_kwh'     => round($totalEnergy, 2),
        'daily_savings'  => round($todayEnergy * $rate, 2),
        'monthly_savings'=> round($monthEnergy * $rate, 2),
        'total_savings'  => round($totalEnergy * $rate, 2),
        'rate'           => $rate,
        'currency'       => $currency,
    ];
}

// ============================================================================
// BATTERY FUNCTIONS
// ============================================================================

/**
 * Get latest battery status for a family
 */
function getLatestBatteryStatus(int $familyId): array {
    $db = getDB();
    $sql = "SELECT bs.* FROM battery_status bs
            INNER JOIN (
                SELECT family_id, MAX(timestamp) as max_ts
                FROM battery_status
                WHERE family_id = ?
                GROUP BY family_id
            ) latest ON bs.family_id = latest.family_id AND bs.timestamp = latest.max_ts
            WHERE bs.family_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId, $familyId]);
    return $stmt->fetch() ?: [];
}

/**
 * Estimate battery health score using SoC, voltage ratio, and temperature
 */
function getBatteryHealthScore(int $familyId): float {
    $battery = getLatestBatteryStatus($familyId);
    if (!$battery) {
        return 0.0;
    }

    $soc = clampFloat((float) ($battery['battery_level'] ?? 0));
    $capacity = max((float) ($battery['capacity_kwh'] ?? 0), 0.1);
    $remaining = max((float) ($battery['remaining_kwh'] ?? 0), 0.0);
    $voltage = max((float) ($battery['voltage'] ?? 0), 0.0);
    $temperature = isset($battery['temperature']) ? (float) $battery['temperature'] : null;

    $remainingRatio = clampFloat(($remaining / $capacity) * 100.0);
    $voltageScore = clampFloat(($voltage / 54.0) * 100.0);

    $tempScore = 100.0;
    if ($temperature !== null) {
        if ($temperature > 60) {
            $tempScore = 20;
        } elseif ($temperature > 50) {
            $tempScore = 55;
        } elseif ($temperature > 40) {
            $tempScore = 75;
        }
    }

    $health = (0.5 * $soc) + (0.2 * $remainingRatio) + (0.2 * $voltageScore) + (0.1 * $tempScore);
    return round(clampFloat($health), 1);
}

// ============================================================================
// ALERT / FAULT DETECTION FUNCTIONS
// ============================================================================

/**
 * Get active alerts for a family
 */
function getActiveAlerts(int $familyId): array {
    $db = getDB();
    $sql = "SELECT a.*, m.microgrid_name FROM alerts a
            LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id
            WHERE a.family_id = ? AND a.status = 'active'
            ORDER BY a.timestamp DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId]);
    return $stmt->fetchAll();
}

/**
 * Get all alerts for a family with filters
 */
function getAlerts(int $familyId, ?string $status = null, int $limit = 50): array {
    $db = getDB();
    $sql = "SELECT a.*, m.microgrid_name FROM alerts a
            LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id
            WHERE a.family_id = ?";
    $params = [$familyId];
    if ($status) {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY a.timestamp DESC LIMIT ?";
    $params[] = $limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Check readings and generate alerts if thresholds exceeded
 */
function checkAndGenerateAlerts(int $microgridId, array $reading): void {
    $db = getDB();
    // Get microgrid details
    $stmt = $db->prepare("SELECT * FROM microgrids WHERE microgrid_id = ?");
    $stmt->execute([$microgridId]);
    $grid = $stmt->fetch();
    if (!$grid) return;

    // Skip automated alerting while in maintenance mode
    if (($grid['status'] ?? '') === 'maintenance') {
        return;
    }

    $familyId = $grid['family_id'];
    $alerts = [];

    $hasRecentActiveAlert = function (string $alertType, int $minutes = 15) use ($db, $microgridId): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM alerts
                              WHERE microgrid_id = ?
                                AND alert_type = ?
                                AND status = 'active'
                                AND timestamp >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$microgridId, $alertType, $minutes]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // Overvoltage check (>400V for solar, >500V for wind)
    $voltageThreshold = $grid['type'] === 'solar' ? 400 : 500;
    if ($reading['voltage'] > $voltageThreshold && !$hasRecentActiveAlert('overvoltage')) {
        $alerts[] = ['overvoltage', 'critical', "Overvoltage detected: {$reading['voltage']}V on {$grid['microgrid_name']}"];
    }

    // Undervoltage check (<100V)
    if ($reading['voltage'] < 100 && $reading['voltage'] > 0 && !$hasRecentActiveAlert('undervoltage')) {
        $alerts[] = ['undervoltage', 'warning', "Low voltage: {$reading['voltage']}V on {$grid['microgrid_name']}"];
    }

    // High temperature (>80°C)
    if (isset($reading['temperature']) && $reading['temperature'] > 80 && !$hasRecentActiveAlert('high_temperature')) {
        $alerts[] = ['high_temperature', 'critical', "High temperature: {$reading['temperature']}°C on {$grid['microgrid_name']}"];
    }

    // Sensor fault (zero readings)
    if ($reading['voltage'] == 0 && $reading['current_amp'] == 0 && !$hasRecentActiveAlert('sensor_fault')) {
        $alerts[] = ['sensor_fault', 'warning', "Possible sensor fault on {$grid['microgrid_name']} - zero readings"];
    }

    // Predictive rules based on previous reading
    $stmt = $db->prepare("SELECT voltage, current_amp, power_kw, timestamp
                          FROM energy_readings
                          WHERE microgrid_id = ?
                          ORDER BY timestamp DESC
                          LIMIT 2");
    $stmt->execute([$microgridId]);
    $recent = $stmt->fetchAll();

    if (count($recent) >= 2) {
        $latest = $recent[0];
        $prev = $recent[1];

        $prevVoltage = max((float) $prev['voltage'], 0.1);
        $prevCurrent = max(abs((float) $prev['current_amp']), 0.1);
        $latestVoltage = (float) $latest['voltage'];
        $latestCurrent = (float) $latest['current_amp'];
        $latestPower = (float) $latest['power_kw'];
        $prevPower = max((float) $prev['power_kw'], 0.05);

        $voltageDropPct = (($prevVoltage - $latestVoltage) / $prevVoltage) * 100.0;
        $currentSpikePct = (($latestCurrent - $prevCurrent) / $prevCurrent) * 100.0;
        $powerDropPct = (($prevPower - $latestPower) / $prevPower) * 100.0;

        if ($voltageDropPct >= 20 && $currentSpikePct >= 35 && !$hasRecentActiveAlert('system_error')) {
            $alerts[] = ['system_error', 'critical', "Possible inverter fault: voltage dropped suddenly with current spike on {$grid['microgrid_name']}"];
        }

        if ($grid['type'] === 'solar' && $powerDropPct >= 45 && $latestVoltage > 150 && !$hasRecentActiveAlert('system_error')) {
            $alerts[] = ['system_error', 'warning', "Panel dirty warning: solar output is below expected level on {$grid['microgrid_name']}"];
        }

        if ($grid['type'] === 'wind' && $powerDropPct >= 50 && !$hasRecentActiveAlert('system_error')) {
            $alerts[] = ['system_error', 'warning', "Wind turbine abnormal output detected on {$grid['microgrid_name']}"];
        }
    }

    // Insert alerts
    $insertStmt = $db->prepare("INSERT INTO alerts (family_id, microgrid_id, alert_type, severity, message) VALUES (?, ?, ?, ?, ?)");
    foreach ($alerts as $alert) {
        $insertStmt->execute([$familyId, $microgridId, $alert[0], $alert[1], $alert[2]]);
        logSystemEvent($familyId, null, $microgridId, 'alert_generated', $alert[2], $alert[1]);
    }
}

/**
 * Check battery and generate alerts
 */
function checkBatteryAlerts(int $familyId, array $batteryData): void {
    $db = getDB();
    $alerts = [];

    // Battery low (<15%)
    if ($batteryData['battery_level'] < 15) {
        $alerts[] = ['battery_low', 'critical', "Battery critically low: {$batteryData['battery_level']}%"];
    } elseif ($batteryData['battery_level'] < 25) {
        $alerts[] = ['battery_low', 'warning', "Battery low: {$batteryData['battery_level']}%"];
    }

    // Overcharge (>100%)
    if ($batteryData['battery_level'] > 100) {
        $alerts[] = ['overcharge', 'critical', "Battery overcharge detected: {$batteryData['battery_level']}%"];
    }

    // High temperature
    if (isset($batteryData['temperature']) && $batteryData['temperature'] > 60) {
        $alerts[] = ['high_temperature', 'warning', "Battery temperature high: {$batteryData['temperature']}°C"];
    }

    // Predictive battery drain warning
    $stmt = $db->prepare("SELECT battery_level
                          FROM battery_status
                          WHERE family_id = ?
                          ORDER BY timestamp DESC
                          LIMIT 2");
    $stmt->execute([$familyId]);
    $rows = $stmt->fetchAll();
    if (count($rows) >= 2) {
        $drop = (float) $rows[1]['battery_level'] - (float) $rows[0]['battery_level'];
        if ($drop >= 8) {
            $alerts[] = ['battery_low', 'warning', "Predictive alert: battery draining fast ({$drop}% drop since last update)"];
        }
    }

    $insertStmt = $db->prepare("INSERT INTO alerts (family_id, alert_type, severity, message) VALUES (?, ?, ?, ?)");
    foreach ($alerts as $alert) {
        $insertStmt->execute([$familyId, $alert[0], $alert[1], $alert[2]]);
        logSystemEvent($familyId, null, null, 'battery_alert', $alert[2], $alert[1]);
    }
}

// ============================================================================
// MICROGRID FUNCTIONS
// ============================================================================

/**
 * Get microgrids for a family
 */
function getFamilyMicrogrids(int $familyId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM microgrids WHERE family_id = ? ORDER BY type, microgrid_name");
    $stmt->execute([$familyId]);
    return $stmt->fetchAll();
}

/**
 * Get latest readings for a microgrid
 */
function getLatestReading(int $microgridId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM energy_readings WHERE microgrid_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$microgridId]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Determine operational status with data freshness and fault awareness
 */
function getMicrogridOperationalStatus(array $microgrid, ?array $latestReading): string {
    if (($microgrid['status'] ?? '') === 'maintenance') {
        return 'maintenance';
    }

    if (($microgrid['status'] ?? '') === 'inactive') {
        return 'offline';
    }

    if (!$latestReading || empty($latestReading['timestamp'])) {
        return 'offline';
    }

    $lastTs = strtotime($latestReading['timestamp']);
    if ($lastTs === false || (time() - $lastTs) > 300) {
        return 'offline';
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM alerts WHERE microgrid_id = ? AND status = 'active' AND severity = 'critical'");
    $stmt->execute([(int) $microgrid['microgrid_id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 'fault';
    }

    return 'active';
}

/**
 * Compute microgrid efficiency percentage
 */
function getMicrogridEfficiency(int $microgridId, float $capacityKw, string $type): float {
    $db = getDB();
    $stmt = $db->prepare("SELECT COALESCE(AVG(power_kw), 0) FROM energy_readings
                          WHERE microgrid_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$microgridId]);
    $avgPower = (float) $stmt->fetchColumn();

    $expectedFactor = $type === 'solar' ? 0.75 : 0.65;
    $expectedPower = max($capacityKw * $expectedFactor, 0.1);
    $efficiency = ($avgPower / $expectedPower) * 100.0;
    return round(clampFloat($efficiency), 1);
}

/**
 * Compute microgrid uptime percentage for last 24h
 */
function getMicrogridUptime(int $microgridId): float {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM energy_readings
                          WHERE microgrid_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$microgridId]);
    $sampleCount = (int) $stmt->fetchColumn();

    // Expected samples for 10-second live updates are much higher; for mixed IoT feeds
    // we use a conservative expected cadence of one sample every 5 minutes.
    $expected = 288;
    $uptime = ($sampleCount / $expected) * 100.0;
    return round(clampFloat($uptime), 1);
}

/**
 * Compute microgrid health score based on efficiency, uptime, and battery condition
 */
function getMicrogridHealthScore(array $microgrid): array {
    $familyId = (int) $microgrid['family_id'];
    $efficiency = getMicrogridEfficiency((int) $microgrid['microgrid_id'], (float) $microgrid['capacity_kw'], (string) $microgrid['type']);
    $uptime = getMicrogridUptime((int) $microgrid['microgrid_id']);
    $batteryHealth = getBatteryHealthScore($familyId);

    $health = (0.4 * $efficiency) + (0.3 * $uptime) + (0.3 * $batteryHealth);
    return [
        'efficiency' => round($efficiency, 1),
        'uptime' => round($uptime, 1),
        'battery_health' => round($batteryHealth, 1),
        'health_score' => round(clampFloat($health), 1),
    ];
}

/**
 * Family health summary for all microgrids
 */
function getFamilyMicrogridHealthSummary(int $familyId): array {
    $rows = [];
    $microgrids = getFamilyMicrogrids($familyId);
    foreach ($microgrids as $mg) {
        $latest = getLatestReading((int) $mg['microgrid_id']);
        $metrics = getMicrogridHealthScore($mg);
        $rows[] = [
            'microgrid_id' => (int) $mg['microgrid_id'],
            'microgrid_name' => $mg['microgrid_name'],
            'type' => $mg['type'],
            'status' => getMicrogridOperationalStatus($mg, $latest),
            'battery_level' => getLatestBatteryStatus((int) $mg['family_id'])['battery_level'] ?? null,
            'efficiency' => $metrics['efficiency'],
            'uptime' => $metrics['uptime'],
            'battery_health' => $metrics['battery_health'],
            'health_score' => $metrics['health_score'],
        ];
    }

    usort($rows, function ($a, $b) {
        return $b['health_score'] <=> $a['health_score'];
    });

    return $rows;
}

/**
 * Get real-time source split and load estimates for energy flow visualization
 */
function getEnergyFlowSnapshot(int $familyId): array {
    $db = getDB();
    $sql = "SELECT m.type, COALESCE(SUM(er.power_kw), 0) AS total_power
            FROM microgrids m
            LEFT JOIN energy_readings er ON er.reading_id = (
                SELECT er2.reading_id FROM energy_readings er2
                WHERE er2.microgrid_id = m.microgrid_id
                ORDER BY er2.timestamp DESC LIMIT 1
            )
            WHERE m.family_id = ? AND m.status IN ('active','maintenance')
            GROUP BY m.type";
    $stmt = $db->prepare($sql);
    $stmt->execute([$familyId]);
    $rows = $stmt->fetchAll();

    $solar = 0.0;
    $wind = 0.0;
    foreach ($rows as $r) {
        if ($r['type'] === 'solar') {
            $solar = (float) $r['total_power'];
        } elseif ($r['type'] === 'wind') {
            $wind = (float) $r['total_power'];
        }
    }

    $battery = getLatestBatteryStatus($familyId);
    $batteryLevel = (float) ($battery['battery_level'] ?? 0);
    $chargeStatus = $battery['charge_status'] ?? 'idle';

    // Use latest consumption reading as load proxy
    $stmt = $db->prepare("SELECT consumed_kwh FROM energy_consumption WHERE family_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$familyId]);
    $latestConsumed = (float) ($stmt->fetchColumn() ?: 0.0);
    $estimatedLoadKw = max(($solar + $wind) * 0.75, $latestConsumed * 4.0);

    return [
        'solar_kw' => round($solar, 2),
        'wind_kw' => round($wind, 2),
        'battery_level' => round($batteryLevel, 1),
        'battery_state' => $chargeStatus,
        'load_kw' => round($estimatedLoadKw, 2),
        'battery_to_load_kw' => $chargeStatus === 'discharging' ? round(max($estimatedLoadKw - ($solar + $wind), 0), 2) : 0.0,
        'source_to_battery_kw' => $chargeStatus === 'charging' ? round(max(($solar + $wind) * 0.25, 0), 2) : 0.0,
    ];
}

// ============================================================================
// ADMIN FUNCTIONS
// ============================================================================

/**
 * Get all families with stats
 */
function getAllFamilies(): array {
    $db = getDB();
    $sql = "SELECT f.*,
                   COUNT(DISTINCT m.microgrid_id) as microgrid_count,
                   COUNT(DISTINCT u.user_id) as user_count
            FROM families f
            LEFT JOIN microgrids m ON f.family_id = m.family_id
            LEFT JOIN users u ON f.family_id = u.family_id AND u.role = 'user'
            GROUP BY f.family_id
            ORDER BY f.family_name";
    return $db->query($sql)->fetchAll();
}

/**
 * Get all microgrids (admin)
 */
function getAllMicrogrids(): array {
    $db = getDB();
    $sql = "SELECT m.*, f.family_name FROM microgrids m
            JOIN families f ON m.family_id = f.family_id
            ORDER BY f.family_name, m.type";
    return $db->query($sql)->fetchAll();
}

/**
 * Get all microgrids with calculated live status and health metrics
 */
function getAllMicrogridsWithHealth(): array {
    $rows = getAllMicrogrids();
    $result = [];
    foreach ($rows as $mg) {
        $latest = getLatestReading((int) $mg['microgrid_id']);
        $status = getMicrogridOperationalStatus($mg, $latest);
        $health = getMicrogridHealthScore($mg);
        $battery = getLatestBatteryStatus((int) $mg['family_id']);
        $result[] = array_merge($mg, [
            'operational_status' => $status,
            'health_score' => $health['health_score'],
            'efficiency_score' => $health['efficiency'],
            'uptime_score' => $health['uptime'],
            'battery_health_score' => $health['battery_health'],
            'latest_battery_level' => $battery['battery_level'] ?? null,
            'last_seen' => $latest['timestamp'] ?? null,
        ]);
    }
    return $result;
}

/**
 * Get platform stats for admin dashboard
 */
function getPlatformStats(): array {
    $db = getDB();
    $stats = [];
    $stats['families']   = (int) $db->query("SELECT COUNT(*) FROM families")->fetchColumn();
    $stats['microgrids'] = (int) $db->query("SELECT COUNT(*) FROM microgrids WHERE status = 'active'")->fetchColumn();
    $stats['users']      = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['alerts']     = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'")->fetchColumn();
    $stats['total_capacity'] = (float) $db->query("SELECT COALESCE(SUM(capacity_kw), 0) FROM microgrids WHERE status = 'active'")->fetchColumn();
    $stats['total_energy']   = (float) $db->query("SELECT COALESCE(SUM(energy_kwh), 0) FROM energy_readings")->fetchColumn();
    return $stats;
}

/**
 * Get all active alerts (admin)
 */
function getAllActiveAlerts(): array {
    $db = getDB();
    $sql = "SELECT a.*, f.family_name, m.microgrid_name FROM alerts a
            JOIN families f ON a.family_id = f.family_id
            LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id
            WHERE a.status = 'active'
            ORDER BY a.severity DESC, a.timestamp DESC";
    return $db->query($sql)->fetchAll();
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Get date filter based on period string
 */
function getDateFilter(string $period): string {
    switch ($period) {
        case 'today':   return date('Y-m-d 00:00:00');
        case 'week':    return date('Y-m-d 00:00:00', strtotime('-7 days'));
        case 'month':   return date('Y-m-01 00:00:00');
        case 'year':    return date('Y-01-01 00:00:00');
        case 'all':     return '2000-01-01 00:00:00';
        default:        return date('Y-m-d 00:00:00');
    }
}

/**
 * Sanitize output for HTML
 */
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format number with units
 */
function formatEnergy(float $value, string $unit = 'kWh'): string {
    return number_format($value, 2) . ' ' . $unit;
}

/**
 * Format currency
 */
function formatCurrency(float $value, string $currency = 'INR'): string {
    $symbols = ['INR' => '₹', 'USD' => '$', 'EUR' => '€'];
    $sym = $symbols[$currency] ?? $currency . ' ';
    return $sym . number_format($value, 2);
}

/**
 * Get severity badge CSS class
 */
function getSeverityClass(string $severity): string {
    switch ($severity) {
        case 'critical': return 'badge-danger';
        case 'warning':  return 'badge-warning';
        case 'info':     return 'badge-info';
        default:         return 'badge-secondary';
    }
}

/**
 * Get status badge CSS class
 */
function getStatusClass(string $status): string {
    switch ($status) {
        case 'active':       return 'badge-danger';
        case 'acknowledged': return 'badge-warning';
        case 'resolved':     return 'badge-success';
        default:             return 'badge-secondary';
    }
}

/**
 * Check if a table contains a specific column.
 */
function hasTableColumn(string $tableName, string $columnName): bool {
    static $cache = [];
    $cacheKey = $tableName . '.' . $columnName;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*)
                          FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = ?
                            AND COLUMN_NAME = ?");
    $stmt->execute([$tableName, $columnName]);
    $exists = (int) $stmt->fetchColumn() > 0;
    $cache[$cacheKey] = $exists;
    return $exists;
}
