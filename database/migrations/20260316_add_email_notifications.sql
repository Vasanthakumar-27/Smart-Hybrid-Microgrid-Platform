-- ============================================================================
-- Database Migration: Add Email Notification Preferences
-- ============================================================================
-- Created: 2026-03-16
-- Purpose: Add email notification preferences to users table
-- 
-- This migration adds the following:
-- 1. Email notification preference columns to users table
-- 2. Email notification queue table for async delivery (optional)
-- 3. Email template preference selection
-- ============================================================================

-- Add email notification preference columns to users table
ALTER TABLE users ADD COLUMN email_notifications_enabled TINYINT(1) DEFAULT 1 COMMENT 'Enable email notifications for this user' AFTER email;
ALTER TABLE users ADD COLUMN email_notify_critical TINYINT(1) DEFAULT 1 COMMENT 'Notify user on critical alerts' AFTER email_notifications_enabled;
ALTER TABLE users ADD COLUMN email_notify_warning TINYINT(1) DEFAULT 1 COMMENT 'Notify user on warning alerts' AFTER email_notify_critical;
ALTER TABLE users ADD COLUMN email_notify_info TINYINT(1) DEFAULT 0 COMMENT 'Notify user on info alerts' AFTER email_notify_warning;
ALTER TABLE users ADD COLUMN email_digest_frequency VARCHAR(20) DEFAULT 'immediate' COMMENT 'Digest frequency: immediate, daily, weekly' AFTER email_notify_info;
ALTER TABLE users ADD COLUMN email_preferences_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated email preferences' AFTER email_digest_frequency;

-- Create email notification queue table (for async/batch delivery)
CREATE TABLE IF NOT EXISTS email_notification_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_id INT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body_text LONGTEXT,
    body_html LONGTEXT,
    metadata JSON,
    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending' COMMENT 'Email delivery status',
    attempts INT DEFAULT 0 COMMENT 'Delivery attempt count',
    next_retry TIMESTAMP NULL COMMENT 'Next retry timestamp',
    error_message TEXT COMMENT 'Last error message if failed',
    sent_at TIMESTAMP NULL COMMENT 'When email was successfully sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (alert_id) REFERENCES alerts(alert_id) ON DELETE SET NULL,
    
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_alert_id (alert_id),
    INDEX idx_created_at (created_at),
    INDEX idx_next_retry (next_retry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pending and failed email notifications for retry/tracking';

-- Create email notification log table (for audit trail)
CREATE TABLE IF NOT EXISTS email_notification_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    alert_id INT,
    recipient_email VARCHAR(255) NOT NULL,
    alert_type VARCHAR(50),
    alert_severity VARCHAR(20),
    alert_message TEXT,
    email_subject VARCHAR(255),
    status ENUM('sent', 'failed', 'blocked', 'bounced', 'complained') DEFAULT 'sent',
    send_method VARCHAR(20) COMMENT 'php, smtp, api',
    delivery_time_ms INT COMMENT 'Time taken to send in milliseconds',
    response_code VARCHAR(10) COMMENT 'SMTP response code or status',
    error_reason TEXT COMMENT 'Why email failed',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (alert_id) REFERENCES alerts(alert_id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_alert_id (alert_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_recipient_email (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log of all email notifications sent/failed';

-- Add index to alerts table for notification lookups
ALTER TABLE alerts ADD INDEX idx_status_timestamp (status, timestamp);
ALTER TABLE alerts ADD INDEX idx_severity_timestamp (severity, timestamp);

-- Update users table to ensure email field exists (if not already)
-- (This is redundant but safe if migration is run multiple times)

-- Create view for recent unprocessed alerts (useful for batch notifications)
CREATE OR REPLACE VIEW pending_alerts_for_notification AS
SELECT 
    a.alert_id,
    a.family_id,
    a.microgrid_id,
    a.alert_type,
    a.severity,
    a.message,
    a.timestamp,
    f.family_name,
    m.microgrid_name,
    u.user_id,
    u.email,
    u.name,
    u.email_notifications_enabled,
    u.email_notify_critical,
    u.email_notify_warning,
    u.email_notify_info,
    u.email_digest_frequency
FROM alerts a
JOIN families f ON a.family_id = f.family_id
LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id
JOIN family_users fu ON f.family_id = fu.family_id
JOIN users u ON fu.user_id = u.user_id
WHERE
    a.status = 'active'
    AND u.email_notifications_enabled = 1
    AND a.timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND NOT EXISTS (
        SELECT 1 FROM email_notification_log
        WHERE email_notification_log.alert_id = a.alert_id
        AND email_notification_log.user_id = u.user_id
    );

-- Commit migration
