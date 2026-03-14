USE microgrid_platform;

ALTER TABLE microgrids
    ADD COLUMN IF NOT EXISTS location_name VARCHAR(150) DEFAULT NULL AFTER location,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) DEFAULT NULL AFTER location_name,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude,
    ADD COLUMN IF NOT EXISTS expected_generation_kw DECIMAL(10,2) DEFAULT NULL AFTER longitude;

CREATE TABLE IF NOT EXISTS system_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NULL,
    user_id INT NULL,
    microgrid_id INT NULL,
    event_type VARCHAR(60) NOT NULL,
    severity ENUM('info','warning','critical') DEFAULT 'info',
    message VARCHAR(500) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_time (timestamp),
    INDEX idx_logs_family (family_id),
    INDEX idx_logs_event (event_type)
) ENGINE=InnoDB;
