-- ============================================================================
-- Smart Hybrid Microgrid Monitoring, Governance & Optimization Platform
-- Database Schema - MySQL (XAMPP)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS microgrid_platform;
USE microgrid_platform;

-- ============================================================================
-- 1. FAMILIES TABLE
-- ============================================================================
CREATE TABLE families (
    family_id     INT AUTO_INCREMENT PRIMARY KEY,
    family_name   VARCHAR(100) NOT NULL,
    location      VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================================
-- 2. USERS TABLE (with RBAC)
-- ============================================================================
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
    family_id     INT NULL,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- 3. MICROGRIDS TABLE
-- ============================================================================
CREATE TABLE microgrids (
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
) ENGINE=InnoDB;

-- ============================================================================
-- 4. ENERGY READINGS TABLE (IoT sensor data)
-- ============================================================================
CREATE TABLE energy_readings (
    reading_id    BIGINT AUTO_INCREMENT PRIMARY KEY,
    microgrid_id  INT NOT NULL,
    voltage       DECIMAL(8,2)  NOT NULL COMMENT 'Volts',
    current_amp   DECIMAL(8,3)  NOT NULL COMMENT 'Amperes',
    power_kw      DECIMAL(10,3) NOT NULL COMMENT 'Kilowatts',
    energy_kwh    DECIMAL(10,3) DEFAULT 0 COMMENT 'kWh generated in interval',
    temperature   DECIMAL(6,2)  DEFAULT NULL COMMENT 'Celsius',
    timestamp     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_microgrid_time (microgrid_id, timestamp),
    FOREIGN KEY (microgrid_id) REFERENCES microgrids(microgrid_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 5. BATTERY STATUS TABLE
-- ============================================================================
CREATE TABLE battery_status (
    battery_id       INT AUTO_INCREMENT PRIMARY KEY,
    family_id        INT NOT NULL,
    battery_name     VARCHAR(100) DEFAULT 'Main Battery',
    capacity_kwh     DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    battery_level    DECIMAL(5,2)  NOT NULL COMMENT 'State of Charge %',
    voltage          DECIMAL(8,2)  NOT NULL COMMENT 'Volts',
    remaining_kwh    DECIMAL(10,3) DEFAULT 0 COMMENT 'Remaining energy kWh',
    charge_status    ENUM('charging','discharging','idle','full') DEFAULT 'idle',
    temperature      DECIMAL(6,2)  DEFAULT NULL COMMENT 'Celsius',
    timestamp        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_family_time (family_id, timestamp),
    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 6. ALERTS TABLE (Fault Detection)
-- ============================================================================
CREATE TABLE alerts (
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
) ENGINE=InnoDB;

-- ============================================================================
-- 7. ENERGY CONSUMPTION TABLE
-- ============================================================================
CREATE TABLE energy_consumption (
    consumption_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    family_id      INT NOT NULL,
    consumed_kwh   DECIMAL(10,3) NOT NULL,
    timestamp      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_family_time (family_id, timestamp),
    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 8. TARIFF SETTINGS (for financial savings)
-- ============================================================================
CREATE TABLE tariff_settings (
    tariff_id       INT AUTO_INCREMENT PRIMARY KEY,
    tariff_name     VARCHAR(100) NOT NULL,
    rate_per_kwh    DECIMAL(8,4) NOT NULL COMMENT 'Currency per kWh',
    currency        VARCHAR(10) DEFAULT 'INR',
    effective_from  DATE NOT NULL,
    effective_to    DATE DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ============================================================================
-- 9. API KEYS TABLE (for IoT device authentication)
-- ============================================================================
CREATE TABLE api_keys (
    key_id        INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    api_key       VARCHAR(64) NOT NULL UNIQUE,
    description   VARCHAR(255) DEFAULT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(family_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SEED DATA
-- ============================================================================
-- IMPORTANT: For proper password hashing, use install.php (browser-based setup)
-- instead of running this SQL directly. install.php generates bcrypt hashes
-- at runtime so login credentials work correctly.
--
-- If you prefer SQL-only setup, generate hashes first:
--   php database/generate_hash.php admin123
--   php database/generate_hash.php user123
-- Then replace the placeholder hashes below with the generated values.
-- ============================================================================

-- Default admin user (password: admin123)
INSERT INTO families (family_id, family_name, location) VALUES
(1, 'Admin Family', 'Platform HQ');

-- Hash generated via bcrypt (verified: password_verify('admin123', hash) === true)
INSERT INTO users (username, password_hash, role, family_id, full_name, email) VALUES
('admin', '$2y$10$oFyrPVzL0UL4GzWb4XcykesW/12.Eh5FZXBF8o.yoqN08g2twW/ei', 'admin', 1, 'System Administrator', 'admin@microgrid.local');

-- Sample families
INSERT INTO families (family_name, location) VALUES
('Sharma Family', 'Block A, Green Valley'),
('Patel Family', 'Block B, Green Valley'),
('Kumar Family', 'Block C, Solar Heights');

-- Sample family users (password: user123)
-- Hash generated via bcrypt (verified: password_verify('user123', hash) === true)
INSERT INTO users (username, password_hash, role, family_id, full_name, email) VALUES
('sharma', '$2y$10$2R4fplZJv4lrVAXGrQFOFe/Ehwv3c.BLo7WpMOY3jYDYOZxE5Qe8e', 'user', 2, 'Rajesh Sharma', 'sharma@mail.com'),
('patel', '$2y$10$2R4fplZJv4lrVAXGrQFOFe/Ehwv3c.BLo7WpMOY3jYDYOZxE5Qe8e', 'user', 3, 'Priya Patel', 'patel@mail.com'),
('kumar', '$2y$10$2R4fplZJv4lrVAXGrQFOFe/Ehwv3c.BLo7WpMOY3jYDYOZxE5Qe8e', 'user', 4, 'Amit Kumar', 'kumar@mail.com');

-- Sample microgrids
INSERT INTO microgrids (family_id, microgrid_name, type, capacity_kw, location, installed_on) VALUES
(2, 'Sharma Solar Panel A', 'solar', 5.00, 'Rooftop', '2025-01-15'),
(2, 'Sharma Wind Turbine', 'wind', 3.00, 'Garden', '2025-02-10'),
(3, 'Patel Solar Array', 'solar', 8.00, 'Rooftop', '2025-01-20'),
(3, 'Patel Wind Unit', 'wind', 2.50, 'Backyard', '2025-03-05'),
(4, 'Kumar Solar System', 'solar', 6.00, 'Terrace', '2025-04-01'),
(4, 'Kumar Wind Generator', 'wind', 4.00, 'Hilltop', '2025-04-15');

-- Default tariff
INSERT INTO tariff_settings (tariff_name, rate_per_kwh, currency, effective_from) VALUES
('Standard Grid Tariff', 7.50, 'INR', '2025-01-01');

-- Sample API keys
INSERT INTO api_keys (family_id, api_key, description) VALUES
(2, 'sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', 'Sharma IoT Devices'),
(3, 'sk_patel_q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2', 'Patel IoT Devices'),
(4, 'sk_kumar_g3h4i5j6k7l8m9n0o1p2q3r4s5t6u7v8', 'Kumar IoT Devices');
