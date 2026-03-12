<?php
/**
 * Helper functions for the Microgrid Platform
 */

require_once __DIR__ . '/../config/database.php';

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

    $familyId = $grid['family_id'];
    $alerts = [];

    // Overvoltage check (>400V for solar, >500V for wind)
    $voltageThreshold = $grid['type'] === 'solar' ? 400 : 500;
    if ($reading['voltage'] > $voltageThreshold) {
        $alerts[] = ['overvoltage', 'critical', "Overvoltage detected: {$reading['voltage']}V on {$grid['microgrid_name']}"];
    }

    // Undervoltage check (<100V)
    if ($reading['voltage'] < 100 && $reading['voltage'] > 0) {
        $alerts[] = ['undervoltage', 'warning', "Low voltage: {$reading['voltage']}V on {$grid['microgrid_name']}"];
    }

    // High temperature (>80°C)
    if (isset($reading['temperature']) && $reading['temperature'] > 80) {
        $alerts[] = ['high_temperature', 'critical', "High temperature: {$reading['temperature']}°C on {$grid['microgrid_name']}"];
    }

    // Sensor fault (zero readings)
    if ($reading['voltage'] == 0 && $reading['current_amp'] == 0) {
        $alerts[] = ['sensor_fault', 'warning', "Possible sensor fault on {$grid['microgrid_name']} - zero readings"];
    }

    // Insert alerts
    $insertStmt = $db->prepare("INSERT INTO alerts (family_id, microgrid_id, alert_type, severity, message) VALUES (?, ?, ?, ?, ?)");
    foreach ($alerts as $alert) {
        $insertStmt->execute([$familyId, $microgridId, $alert[0], $alert[1], $alert[2]]);
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

    $insertStmt = $db->prepare("INSERT INTO alerts (family_id, alert_type, severity, message) VALUES (?, ?, ?, ?)");
    foreach ($alerts as $alert) {
        $insertStmt->execute([$familyId, $alert[0], $alert[1], $alert[2]]);
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
