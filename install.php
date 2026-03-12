<?php
/**
 * MicroGrid Pro — Installation & Setup Wizard
 *
 * Run this ONCE via browser after placing the project in XAMPP htdocs:
 *   http://localhost/microgrid-platform/install.php
 *
 * This script will:
 * 1. Create the database and all tables
 * 2. Insert seed data with properly hashed passwords
 * 3. Set up sample families, microgrids, tariff, and API keys
 */

// Prevent re-running accidentally
session_start();

$dbHost    = 'localhost';
$dbUser    = 'root';
$dbPass    = '';
$dbName    = 'microgrid_platform';
$dbCharset = 'utf8mb4';

$steps   = [];
$errors  = [];
$success = false;

// ============================================================================
// Handle installation
// ============================================================================
$autorun = isset($_GET['autorun']); // allow PowerShell setup.ps1 to trigger silently
if ($autorun || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install']))) {
    // CSRF check — skip for autorun (localhost-only, no user session available)
    if (!$autorun && (!isset($_POST['csrf_token']) || !isset($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $_POST['csrf_token']))) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        try {
            // Step 1: Connect to MySQL (without database)
            $pdo = new PDO(
                "mysql:host=$dbHost;charset=$dbCharset",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $steps[] = 'Connected to MySQL server.';

            // Step 2: Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            $steps[] = "Database '$dbName' created/selected.";

            // Step 3: Create tables
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS families (
                    family_id     INT AUTO_INCREMENT PRIMARY KEY,
                    family_name   VARCHAR(100) NOT NULL,
                    location      VARCHAR(255) NOT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    user_id       INT AUTO_INCREMENT PRIMARY KEY,
                    username      VARCHAR(50)  NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
                    family_id     INT NULL,
                    full_name     VARCHAR(100) NOT NULL,
                    email         VARCHAR(150) DEFAULT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS microgrids (
                    microgrid_id  INT AUTO_INCREMENT PRIMARY KEY,
                    family_id     INT NOT NULL,
                    microgrid_name VARCHAR(100) NOT NULL,
                    type          ENUM('solar','wind') NOT NULL,
                    capacity_kw   DECIMAL(10,2) NOT NULL,
                    location      VARCHAR(255) DEFAULT NULL,
                    installed_on  DATE DEFAULT NULL,
                    status        ENUM('active','inactive','maintenance') DEFAULT 'active',
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS energy_readings (
                    reading_id    BIGINT AUTO_INCREMENT PRIMARY KEY,
                    microgrid_id  INT NOT NULL,
                    voltage       DECIMAL(8,2)  NOT NULL,
                    current_amp   DECIMAL(8,3)  NOT NULL,
                    power_kw      DECIMAL(10,3) NOT NULL,
                    energy_kwh    DECIMAL(10,3) DEFAULT 0,
                    temperature   DECIMAL(6,2)  DEFAULT NULL,
                    timestamp     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_microgrid_time (microgrid_id, timestamp),
                    FOREIGN KEY (microgrid_id) REFERENCES microgrids(microgrid_id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS battery_status (
                    battery_id       INT AUTO_INCREMENT PRIMARY KEY,
                    family_id        INT NOT NULL,
                    battery_name     VARCHAR(100) DEFAULT 'Main Battery',
                    capacity_kwh     DECIMAL(10,2) NOT NULL DEFAULT 10.00,
                    battery_level    DECIMAL(5,2)  NOT NULL,
                    voltage          DECIMAL(8,2)  NOT NULL,
                    remaining_kwh    DECIMAL(10,3) DEFAULT 0,
                    charge_status    ENUM('charging','discharging','idle','full') DEFAULT 'idle',
                    temperature      DECIMAL(6,2)  DEFAULT NULL,
                    timestamp        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_family_time (family_id, timestamp),
                    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS alerts (
                    alert_id      BIGINT AUTO_INCREMENT PRIMARY KEY,
                    family_id     INT NOT NULL,
                    microgrid_id  INT DEFAULT NULL,
                    alert_type    ENUM('overvoltage','overcharge','battery_low','high_temperature','sensor_fault','undervoltage','system_error') NOT NULL,
                    severity      ENUM('info','warning','critical') DEFAULT 'warning',
                    message       VARCHAR(500) NOT NULL,
                    status        ENUM('active','acknowledged','resolved') DEFAULT 'active',
                    timestamp     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    resolved_at   DATETIME DEFAULT NULL,
                    INDEX idx_family_status (family_id, status),
                    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE,
                    FOREIGN KEY (microgrid_id) REFERENCES microgrids(microgrid_id) ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS energy_consumption (
                    consumption_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    family_id      INT NOT NULL,
                    consumed_kwh   DECIMAL(10,3) NOT NULL,
                    timestamp      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_family_time (family_id, timestamp),
                    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tariff_settings (
                    tariff_id       INT AUTO_INCREMENT PRIMARY KEY,
                    tariff_name     VARCHAR(100) NOT NULL,
                    rate_per_kwh    DECIMAL(8,4) NOT NULL,
                    currency        VARCHAR(10) DEFAULT 'INR',
                    effective_from  DATE NOT NULL,
                    effective_to    DATE DEFAULT NULL,
                    is_active       TINYINT(1) DEFAULT 1
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS api_keys (
                    key_id        INT AUTO_INCREMENT PRIMARY KEY,
                    family_id     INT NOT NULL,
                    api_key       VARCHAR(64) NOT NULL UNIQUE,
                    description   VARCHAR(255) DEFAULT NULL,
                    is_active     TINYINT(1) DEFAULT 1,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");

            $steps[] = 'All 9 tables created successfully.';

            // Step 4: Check if data already exists
            $existingUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($existingUsers > 0) {
                $steps[] = 'Seed data already exists — skipping inserts.';
                $success = true;
            } else {
                // Step 5: Generate proper bcrypt password hashes
                $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
                $userHash  = password_hash('user123', PASSWORD_BCRYPT);
                $steps[] = 'Password hashes generated with bcrypt.';

                // Step 6: Insert families
                $pdo->exec("INSERT INTO families (family_id, family_name, location) VALUES (1, 'Admin Family', 'Platform HQ')");
                $pdo->exec("INSERT INTO families (family_name, location) VALUES
                    ('Sharma Family', 'Block A, Green Valley'),
                    ('Patel Family', 'Block B, Green Valley'),
                    ('Kumar Family', 'Block C, Solar Heights')
                ");
                $steps[] = '4 families created (Admin + 3 sample).';

                // Step 7: Insert users with correct hashes
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, family_id, full_name, email) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(['admin', $adminHash, 'admin', 1, 'System Administrator', 'admin@microgrid.local']);
                $stmt->execute(['sharma', $userHash, 'user', 2, 'Rajesh Sharma', 'sharma@mail.com']);
                $stmt->execute(['patel',  $userHash, 'user', 3, 'Priya Patel', 'patel@mail.com']);
                $stmt->execute(['kumar',  $userHash, 'user', 4, 'Amit Kumar', 'kumar@mail.com']);
                $steps[] = '4 users created (admin/admin123, sharma|patel|kumar/user123).';

                // Step 8: Insert microgrids
                $pdo->exec("INSERT INTO microgrids (family_id, microgrid_name, type, capacity_kw, location, installed_on) VALUES
                    (2, 'Sharma Solar Panel A', 'solar', 5.00, 'Rooftop', '2025-01-15'),
                    (2, 'Sharma Wind Turbine', 'wind', 3.00, 'Garden', '2025-02-10'),
                    (3, 'Patel Solar Array', 'solar', 8.00, 'Rooftop', '2025-01-20'),
                    (3, 'Patel Wind Unit', 'wind', 2.50, 'Backyard', '2025-03-05'),
                    (4, 'Kumar Solar System', 'solar', 6.00, 'Terrace', '2025-04-01'),
                    (4, 'Kumar Wind Generator', 'wind', 4.00, 'Hilltop', '2025-04-15')
                ");
                $steps[] = '6 microgrids created (2 per family: solar + wind).';

                // Step 9: Insert tariff
                $pdo->exec("INSERT INTO tariff_settings (tariff_name, rate_per_kwh, currency, effective_from) VALUES
                    ('Standard Grid Tariff', 7.50, 'INR', '2025-01-01')
                ");
                $steps[] = 'Default tariff set (₹7.50/kWh).';

                // Step 10: Insert API keys
                $pdo->exec("INSERT INTO api_keys (family_id, api_key, description) VALUES
                    (2, 'sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', 'Sharma IoT Devices'),
                    (3, 'sk_patel_q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2', 'Patel IoT Devices'),
                    (4, 'sk_kumar_g3h4i5j6k7l8m9n0o1p2q3r4s5t6u7v8', 'Kumar IoT Devices')
                ");
                $steps[] = '3 API keys created for IoT devices.';

                $success = true;
                $steps[] = 'Installation complete!';
            }

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// If called by setup.ps1 (?autorun=1), return JSON and exit immediately
if ($autorun) {
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true,  'message' => 'installed', 'steps' => $steps]);
    } else {
        echo json_encode(['success' => false, 'errors' => $errors]);
    }
    exit;
}

// Generate CSRF token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['install_csrf'] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — MicroGrid Pro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            max-width: 640px;
            width: 100%;
            background: #1e293b;
            border-radius: 16px;
            border: 1px solid #334155;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 2rem;
            text-align: center;
        }
        .header h1 { font-size: 1.8rem; font-weight: 700; color: #fff; }
        .header p { color: rgba(255,255,255,0.85); margin-top: 0.3rem; font-size: 0.95rem; }
        .body { padding: 2rem; }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid #334155;
            font-size: 0.9rem;
        }
        .step:last-child { border-bottom: none; }
        .step .icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 2px; }
        .step.ok .icon { color: #10b981; }
        .step.err .icon { color: #ef4444; }
        .error-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #fca5a5;
            font-size: 0.9rem;
        }
        .success-box {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        .success-box h3 { color: #10b981; margin-bottom: 0.8rem; font-size: 1.2rem; }
        .cred-table {
            width: 100%;
            margin: 1rem 0;
            border-collapse: collapse;
        }
        .cred-table th, .cred-table td {
            padding: 0.5rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #334155;
            font-size: 0.85rem;
        }
        .cred-table th { color: #94a3b8; font-weight: 600; }
        .cred-table td { color: #e2e8f0; }
        .cred-table code {
            background: #0f172a;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Consolas', monospace;
            color: #10b981;
            font-size: 0.85rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-outline {
            background: transparent;
            border: 1px solid #10b981;
            color: #10b981;
        }
        .center { text-align: center; margin-top: 1.5rem; }
        .info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #93c5fd;
            line-height: 1.6;
        }
        .info strong { color: #60a5fa; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>&#9889; MicroGrid Pro — Setup</h1>
        <p>Smart Hybrid Microgrid Monitoring Platform</p>
    </div>
    <div class="body">
        <?php if (!$success && empty($steps)): ?>
            <!-- Pre-install screen -->
            <div class="info">
                <strong>Prerequisites:</strong><br>
                &#8226; XAMPP with Apache &amp; MySQL running<br>
                &#8226; Project folder at <code>htdocs/microgrid-platform/</code><br>
                &#8226; MySQL accessible on <code>localhost</code> (root, no password)
            </div>

            <p style="margin-bottom: 1rem; font-size: 0.9rem; color: #94a3b8;">
                This will create the <strong>microgrid_platform</strong> database, all tables, and seed data
                including demo users. Click below to proceed.
            </p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="install" value="1">
                <div class="center">
                    <button type="submit" class="btn">&#128268; Run Installation</button>
                </div>
            </form>

        <?php else: ?>
            <!-- Post-install results -->
            <?php foreach ($errors as $err): ?>
                <div class="error-box">&#10060; <?= $err ?></div>
            <?php endforeach; ?>

            <?php foreach ($steps as $step): ?>
                <div class="step ok">
                    <span class="icon">&#10004;</span>
                    <span><?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="success-box">
                    <h3>&#10004; Installation Successful!</h3>
                    <p style="color: #94a3b8; margin-bottom: 1rem; font-size: 0.85rem;">
                        Use these credentials to log in:
                    </p>
                    <table class="cred-table">
                        <tr><th>Role</th><th>Username</th><th>Password</th></tr>
                        <tr><td>Admin</td><td><code>admin</code></td><td><code>admin123</code></td></tr>
                        <tr><td>User</td><td><code>sharma</code></td><td><code>user123</code></td></tr>
                        <tr><td>User</td><td><code>patel</code></td><td><code>user123</code></td></tr>
                        <tr><td>User</td><td><code>kumar</code></td><td><code>user123</code></td></tr>
                    </table>
                    <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.75rem;">
                        Next step: Run <strong>seed_demo_data.php</strong> to generate 30 days of realistic IoT data.
                    </p>
                </div>
                <div class="center" style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php" class="btn">&#128274; Go to Login</a>
                    <a href="database/seed_demo_data.php" class="btn btn-outline">&#127793; Seed Demo Data</a>
                </div>
            <?php else: ?>
                <div class="center">
                    <a href="install.php" class="btn btn-outline">&#128260; Try Again</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
