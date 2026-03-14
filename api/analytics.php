<?php
/**
 * Internal API - Analytics Data (AJAX)
 * Serves JSON data for dashboard Chart.js visualizations
 * Requires session auth (not API key)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$familyId = isAdmin() && isset($_GET['family_id']) ? (int) $_GET['family_id'] : getCurrentFamilyId();

if (!$familyId) {
    http_response_code(400);
    echo json_encode(['error' => 'No family context']);
    exit;
}

$db = getDB();

switch ($action) {

    case 'daily_generation':
        $days = min((int) ($_GET['days'] ?? 30), 365);
        echo json_encode(['success' => true, 'data' => getDailyGeneration($familyId, $days)]);
        break;

    case 'source_contribution':
        $period = $_GET['period'] ?? 'month';
        echo json_encode(['success' => true, 'data' => getEnergyBySource($familyId, $period)]);
        break;

    case 'weekly_trends':
        $weeks = min((int) ($_GET['weeks'] ?? 12), 52);
        echo json_encode(['success' => true, 'data' => getWeeklyTrends($familyId, $weeks)]);
        break;

    case 'monthly_reports':
        $months = min((int) ($_GET['months'] ?? 12), 36);
        echo json_encode(['success' => true, 'data' => getMonthlyReports($familyId, $months)]);
        break;

    case 'savings':
        echo json_encode(['success' => true, 'data' => calculateSavings($familyId)]);
        break;

    case 'realtime':
        // Get latest reading for each microgrid
        $sql = "SELECT m.microgrid_id, m.microgrid_name, m.type, m.capacity_kw,
                       m.status as configured_status,
                       er.voltage, er.current_amp, er.power_kw, er.energy_kwh, er.temperature, er.timestamp
                FROM microgrids m
                LEFT JOIN energy_readings er ON er.reading_id = (
                    SELECT reading_id FROM energy_readings
                    WHERE microgrid_id = m.microgrid_id
                    ORDER BY timestamp DESC LIMIT 1
                )
                WHERE m.family_id = ? AND m.status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$familyId]);
        $grids = $stmt->fetchAll();

        foreach ($grids as &$g) {
            $g['operational_status'] = getMicrogridOperationalStatus($g, $g['timestamp'] ? $g : null);
            $g['health'] = getMicrogridHealthScore($g);
        }
        unset($g);

        $battery = getLatestBatteryStatus($familyId);
        $alerts = getActiveAlerts($familyId);
        $flow = getEnergyFlowSnapshot($familyId);

        echo json_encode([
            'success'   => true,
            'microgrids'=> $grids,
            'battery'   => $battery,
            'alerts'    => $alerts,
            'flow'      => $flow,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        break;

    case 'health_summary':
        echo json_encode(['success' => true, 'data' => getFamilyMicrogridHealthSummary($familyId)]);
        break;

    case 'energy_flow':
        echo json_encode(['success' => true, 'data' => getEnergyFlowSnapshot($familyId)]);
        break;

    case 'system_logs':
        $limit = min((int) ($_GET['limit'] ?? 50), 200);
        if (isAdmin() && empty($_GET['family_id'])) {
            echo json_encode(['success' => true, 'data' => getRecentSystemLogs(null, $limit)]);
        } else {
            echo json_encode(['success' => true, 'data' => getRecentSystemLogs($familyId, $limit)]);
        }
        break;

    case 'battery_history':
        $hours = min((int) ($_GET['hours'] ?? 24), 168);
        $sql = "SELECT battery_level, voltage, remaining_kwh, charge_status, temperature, timestamp
                FROM battery_status
                WHERE family_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY timestamp ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$familyId, $hours]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'platform_stats':
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin only']);
            break;
        }
        echo json_encode(['success' => true, 'data' => getPlatformStats()]);
        break;

    case 'all_families_energy':
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin only']);
            break;
        }
        $sql = "SELECT f.family_id, f.family_name,
                       COALESCE(SUM(er.energy_kwh), 0) as total_kwh
                FROM families f
                LEFT JOIN microgrids m ON f.family_id = m.family_id
                LEFT JOIN energy_readings er ON m.microgrid_id = er.microgrid_id
                    AND er.timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                GROUP BY f.family_id
                ORDER BY total_kwh DESC";
        echo json_encode(['success' => true, 'data' => $db->query($sql)->fetchAll()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Valid: daily_generation, source_contribution, weekly_trends, monthly_reports, savings, realtime, battery_history, health_summary, energy_flow, system_logs, platform_stats, all_families_energy']);
}
