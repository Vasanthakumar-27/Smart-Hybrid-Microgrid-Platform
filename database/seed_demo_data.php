<?php
/**
 * Demo Data Seeder — Generates realistic IoT readings for demonstration
 *
 * Run via browser: http://localhost/microgrid-platform/database/seed_demo_data.php
 * Or CLI: php database/seed_demo_data.php
 *
 * This generates 30 days of realistic energy readings, battery status, and alerts.
 */

require_once __DIR__ . '/../config/database.php';

// Allow running from both CLI and browser
$isCLI = php_sapi_name() === 'cli';
function out($msg) {
    global $isCLI;
    echo $isCLI ? $msg . "\n" : "<p>$msg</p>";
}

if (!$isCLI) {
    echo '<!DOCTYPE html><html><head><title>Seed Demo Data</title>
    <style>body{font-family:monospace;background:#1e293b;color:#10b981;padding:2rem;} p{margin:0.3rem 0;}</style>
    </head><body><h2>🌱 Seeding Demo Data...</h2>';
}

$db = getDB();

// Check if already seeded
$count = (int) $db->query("SELECT COUNT(*) FROM energy_readings")->fetchColumn();
if ($count > 100) {
    out("⚠️ Database already has $count readings. To re-seed, truncate tables first.");
    out("Run: TRUNCATE energy_readings; TRUNCATE battery_status; TRUNCATE alerts; TRUNCATE energy_consumption;");
    if (!$isCLI) echo '</body></html>';
    exit;
}

out("Starting demo data generation...");

// Get all microgrids
$microgrids = $db->query("SELECT microgrid_id, family_id, type, capacity_kw FROM microgrids")->fetchAll();
if (empty($microgrids)) {
    out("❌ No microgrids found. Run schema.sql first.");
    exit;
}

$families = $db->query("SELECT family_id FROM families WHERE family_id > 1")->fetchAll(PDO::FETCH_COLUMN);

// ============================================================================
// Generate Energy Readings (30 days, every 15 minutes)
// ============================================================================
out("📊 Generating energy readings for " . count($microgrids) . " microgrids over 30 days...");

$readingStmt = $db->prepare("INSERT INTO energy_readings (microgrid_id, voltage, current_amp, power_kw, energy_kwh, temperature, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?)");

$totalReadings = 0;
$startDate = new DateTime('-30 days');
$endDate = new DateTime();

foreach ($microgrids as $mg) {
    $current = clone $startDate;
    $isSolar = $mg['type'] === 'solar';
    $capacity = (float) $mg['capacity_kw'];

    while ($current <= $endDate) {
        $hour = (int) $current->format('G');
        $month = (int) $current->format('n');

        // Solar pattern: generates during daylight (6am-6pm), peak at noon
        // Wind pattern: varies throughout day, slightly more at night
        if ($isSolar) {
            if ($hour >= 6 && $hour <= 18) {
                $hourFactor = 1.0 - abs($hour - 12) / 7.0; // peak at noon
                $cloudFactor = 0.5 + mt_rand(0, 50) / 100; // 50-100% cloud variability
                $powerFraction = $hourFactor * $cloudFactor;
            } else {
                $powerFraction = 0;
            }
            // Base voltage for solar panels
            $baseVoltage = 320;
        } else {
            // Wind is more random, slightly higher at night
            $windBase = 0.15 + mt_rand(0, 60) / 100;
            $nightBoost = ($hour < 6 || $hour > 20) ? 0.15 : 0;
            $gustFactor = mt_rand(0, 100) < 10 ? 0.3 : 0; // 10% chance of gusts
            $powerFraction = $windBase + $nightBoost + $gustFactor;
            $powerFraction = min($powerFraction, 1.0);
            $baseVoltage = 380;
        }

        $power = round($capacity * $powerFraction * (0.9 + mt_rand(0, 20) / 100), 3);
        $power = max(0, $power);
        $energyKwh = round($power * 0.25, 3); // 15-minute interval → 0.25 hours
        $voltage = $power > 0 ? round($baseVoltage + mt_rand(-20, 30) + $powerFraction * 30, 2) : round($baseVoltage * 0.3 + mt_rand(0, 20), 2);
        $currentAmp = $voltage > 0 ? round(($power * 1000) / $voltage, 3) : 0;

        // Temperature simulation
        $baseTemp = 25 + sin(($hour - 6) * M_PI / 12) * 15; // daytime heating
        $temp = round($baseTemp + mt_rand(-3, 5) + ($isSolar && $power > 0 ? $power * 2 : 0), 2);
        $temp = max(10, min(75, $temp));

        $timestamp = $current->format('Y-m-d H:i:s');

        $readingStmt->execute([
            $mg['microgrid_id'],
            $voltage,
            $currentAmp,
            $power,
            $energyKwh,
            $temp,
            $timestamp
        ]);

        $totalReadings++;
        $current->modify('+15 minutes');
    }

    out("  ✅ {$mg['type']} microgrid #{$mg['microgrid_id']} — readings generated");
}

out("📊 Total energy readings: $totalReadings");

// ============================================================================
// Generate Battery Status (every 30 minutes for 30 days)
// ============================================================================
out("🔋 Generating battery status data...");

$batteryStmt = $db->prepare("INSERT INTO battery_status (family_id, battery_name, capacity_kwh, battery_level, voltage, remaining_kwh, charge_status, temperature, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($families as $fid) {
    $current = clone $startDate;
    $soc = 70.0; // start at 70%
    $capacity = 10 + mt_rand(0, 10); // 10-20 kWh

    while ($current <= $endDate) {
        $hour = (int) $current->format('G');

        // Charging during solar hours, discharging at night
        if ($hour >= 8 && $hour <= 16) {
            $delta = mt_rand(5, 25) / 10; // charge 0.5 - 2.5%
            $soc = min(100, $soc + $delta);
            $status = $soc >= 99 ? 'full' : 'charging';
        } elseif ($hour >= 18 || $hour <= 5) {
            $delta = mt_rand(3, 15) / 10; // discharge 0.3 - 1.5%
            $soc = max(5, $soc - $delta);
            $status = $soc <= 5 ? 'idle' : 'discharging';
        } else {
            $status = 'idle';
            $soc += mt_rand(-5, 5) / 10;
            $soc = max(5, min(100, $soc));
        }

        $remaining = round($capacity * $soc / 100, 3);
        $voltage = round(44 + ($soc / 100) * 8 + mt_rand(-5, 5) / 10, 2); // 44V-52V range
        $temp = round(25 + ($status === 'charging' ? 8 : 3) + mt_rand(-3, 5), 2);

        $batteryStmt->execute([
            $fid,
            'Main Battery',
            $capacity,
            round($soc, 2),
            $voltage,
            $remaining,
            $status,
            $temp,
            $current->format('Y-m-d H:i:s')
        ]);

        $current->modify('+30 minutes');
    }

    out("  ✅ Family #$fid battery history generated");
}

// ============================================================================
// Generate Sample Alerts
// ============================================================================
out("⚠️ Generating sample alerts...");

$alertStmt = $db->prepare("INSERT INTO alerts (family_id, microgrid_id, alert_type, severity, message, status, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?)");

$sampleAlerts = [
    ['alert_type' => 'overvoltage', 'severity' => 'critical', 'message' => 'Overvoltage detected: 425V on Solar Panel A', 'status' => 'resolved'],
    ['alert_type' => 'battery_low', 'severity' => 'warning', 'message' => 'Battery level dropped below 20%', 'status' => 'resolved'],
    ['alert_type' => 'high_temperature', 'severity' => 'critical', 'message' => 'High temperature: 78°C on Solar Array', 'status' => 'acknowledged'],
    ['alert_type' => 'sensor_fault', 'severity' => 'warning', 'message' => 'Possible sensor fault — zero readings for 30 minutes', 'status' => 'active'],
    ['alert_type' => 'undervoltage', 'severity' => 'warning', 'message' => 'Low voltage detected: 95V on Wind Turbine', 'status' => 'active'],
    ['alert_type' => 'overcharge', 'severity' => 'critical', 'message' => 'Battery overcharge warning: SoC at 102%', 'status' => 'active'],
    ['alert_type' => 'battery_low', 'severity' => 'critical', 'message' => 'Battery critically low: 8%', 'status' => 'active'],
    ['alert_type' => 'high_temperature', 'severity' => 'warning', 'message' => 'Wind generator temperature elevated: 65°C', 'status' => 'acknowledged'],
];

foreach ($families as $fid) {
    $familyGrids = array_filter($microgrids, function ($mg) use ($fid) { return $mg['family_id'] == $fid; });
    $gridIds = array_column($familyGrids, 'microgrid_id');

    foreach ($sampleAlerts as $i => $alert) {
        $ts = (new DateTime('-' . mt_rand(1, 28) . ' days -' . mt_rand(0, 23) . ' hours'))->format('Y-m-d H:i:s');
        $gridId = !empty($gridIds) ? $gridIds[array_rand($gridIds)] : null;

        $alertStmt->execute([
            $fid,
            $gridId,
            $alert['alert_type'],
            $alert['severity'],
            $alert['message'],
            $alert['status'],
            $ts
        ]);
    }
    out("  ✅ Family #$fid alerts generated");
}

// ============================================================================
// Generate Energy Consumption Data
// ============================================================================
out("🏠 Generating energy consumption data...");

$consumeStmt = $db->prepare("INSERT INTO energy_consumption (family_id, consumed_kwh, timestamp) VALUES (?, ?, ?)");

foreach ($families as $fid) {
    $current = clone $startDate;
    while ($current <= $endDate) {
        $hour = (int) $current->format('G');
        // Home consumption pattern
        $baseConsumption = 0.5; // base kWh per hour
        if ($hour >= 7 && $hour <= 9)  $baseConsumption = 1.5; // morning peak
        if ($hour >= 18 && $hour <= 22) $baseConsumption = 2.0; // evening peak
        if ($hour >= 0 && $hour <= 5)   $baseConsumption = 0.3; // night low

        $consumed = round($baseConsumption * (0.8 + mt_rand(0, 40) / 100), 3);
        $consumeStmt->execute([$fid, $consumed, $current->format('Y-m-d H:i:s')]);
        $current->modify('+1 hour');
    }
    out("  ✅ Family #$fid consumption data generated");
}

// ============================================================================
// Done
// ============================================================================
out("");
out("============================================");
out("✅ Demo data seeding complete!");
out("============================================");
out("📊 Energy readings: $totalReadings");
out("🔋 Battery status entries generated for " . count($families) . " families");
out("⚠️ " . (count($sampleAlerts) * count($families)) . " sample alerts created");
out("🏠 Consumption data for " . count($families) . " families");
out("");
out("You can now log in at: http://localhost/microgrid-platform/");
out("  Admin: admin / admin123");
out("  User:  sharma / user123");

if (!$isCLI) echo '</body></html>';
