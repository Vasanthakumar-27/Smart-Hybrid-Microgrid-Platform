-- MicroGrid Pro - 2FA Support Migration
-- Adds two-factor authentication (TOTP) support to the users table

-- Add 2FA columns to users table if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `two_fa_enabled` TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS `two_fa_secret` VARCHAR(32) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS `two_fa_backup_codes` TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS `two_fa_enabled_at` TIMESTAMP NULL DEFAULT NULL;

-- Create index for quick 2FA lookups
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_two_fa_enabled (`two_fa_enabled`);

-- Add 2FA verification log table (optional: for audit trail)
CREATE TABLE IF NOT EXISTS `two_fa_verification_log` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `verification_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) DEFAULT 1,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE,
  INDEX idx_user_verification (user_id, verification_time)
);
