<?php
/**
 * Data Retention & Cleanup Management
 * 
 * Implements configurable data retention policies for logs, alerts, readings, etc.
 * Can be run via cron job or web interface
 * 
 * Configuration via .env:
 *   RETENTION_ENABLED - Enable/disable retention policies
 *   RETENTION_ALERT_DAYS - Keep alerts for N days (default: 90)
 *   RETENTION_READING_DAYS - Keep energy readings for N days (default: 365)
 *   RETENTION_BATTERY_DAYS - Keep battery status for N days (default: 90)
 *   RETENTION_LOG_DAYS - Keep system logs for N days (default: 30)
 *   RETENTION_EMAIL_LOG_DAYS - Keep email logs for N days (default: 180)
 *   RETENTION_DRY_RUN - Preview deletions without actually deleting
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/logger.php';

class DataRetention {
    protected static $logger = null;
    protected static $config = [];
    protected static $dryRun = false;

    /**
     * Initialize retention configuration from .env
     */
    public static function init(bool $dryRun = false): void {
        Env::load();

        self::$config = [
            'enabled'           => Env::getBool('RETENTION_ENABLED', true),
            'alert_days'        => Env::getInt('RETENTION_ALERT_DAYS', 90),
            'reading_days'      => Env::getInt('RETENTION_READING_DAYS', 365),
            'battery_days'      => Env::getInt('RETENTION_BATTERY_DAYS', 90),
            'log_days'          => Env::getInt('RETENTION_LOG_DAYS', 30),
            'email_log_days'    => Env::getInt('RETENTION_EMAIL_LOG_DAYS', 180),
            'notification_log_days' => Env::getInt('RETENTION_NOTIFICATION_LOG_DAYS', 90),
            'dry_run'           => $dryRun || Env::getBool('RETENTION_DRY_RUN', false),
        ];

        self::$dryRun = self::$config['dry_run'];
        self::$logger = new Logger('DataRetention');
    }

    /**
     * Run all retention policies
     */
    public static function runAll(): array {
        self::init();

        if (!self::$config['enabled']) {
            return ['success' => false, 'message' => 'Data retention is disabled'];
        }

        $results = [
            'success' => true,
            'dry_run' => self::$dryRun,
            'timestamp' => date('Y-m-d H:i:s'),
            'policies' => [],
            'total_deleted' => 0,
        ];

        $results['policies']['resolved_alerts'] = self::cleanResolvedAlerts();
        $results['policies']['old_readings'] = self::cleanOldReadings();
        $results['policies']['old_battery_status'] = self::cleanOldBatteryStatus();
        $results['policies']['system_logs'] = self::cleanSystemLogs();
        $results['policies']['email_logs'] = self::cleanEmailLogs();
        $results['policies']['notification_logs'] = self::cleanNotificationLogs();
        $results['policies']['queue_failures'] = self::cleanFailedQueue();

        // Calculate totals
        foreach ($results['policies'] as $policy) {
            if (isset($policy['deleted'])) {
                $results['total_deleted'] += $policy['deleted'];
            }
        }

        $message = sprintf(
            "Data retention cleanup completed. Deleted %d total rows. (Dry run: %s)",
            $results['total_deleted'],
            self::$dryRun ? 'YES' : 'NO'
        );

        self::$logger->info($message, $results);

        return $results;
    }

    /**
     * Delete resolved alerts older than retention period
     */
    protected static function cleanResolvedAlerts(): array {
        $days = self::$config['alert_days'];
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Count records that would be deleted
        $stmt = $db->prepare("SELECT COUNT(*) FROM alerts WHERE status = 'resolved' AND timestamp < ?");
        $stmt->execute([$cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'resolved_alerts', 'deleted' => 0, 'reason' => 'No old resolved alerts'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("DELETE FROM alerts WHERE status = 'resolved' AND timestamp < ?");
            $stmt->execute([$cutoffDate]);
        }

        $policy = [
            'name' => 'resolved_alerts',
            'deleted' => $count,
            'reason' => "Deleted alerts resolved before {$cutoffDate} ({$days} days retention)",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("Alert cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Delete old energy readings beyond retention period
     */
    protected static function cleanOldReadings(): array {
        $days = self::$config['reading_days'];
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Count records that would be deleted
        $stmt = $db->prepare("SELECT COUNT(*) FROM energy_readings WHERE timestamp < ?");
        $stmt->execute([$cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'old_readings', 'deleted' => 0, 'reason' => 'No old energy readings'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("DELETE FROM energy_readings WHERE timestamp < ?");
            $stmt->execute([$cutoffDate]);
        }

        $policy = [
            'name' => 'old_readings',
            'deleted' => $count,
            'reason' => "Deleted energy readings before {$cutoffDate} ({$days} days retention)",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("Energy readings cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Delete old battery status records
     */
    protected static function cleanOldBatteryStatus(): array {
        $days = self::$config['battery_days'];
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Count records that would be deleted
        $stmt = $db->prepare("SELECT COUNT(*) FROM battery_status WHERE timestamp < ?");
        $stmt->execute([$cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'old_battery_status', 'deleted' => 0, 'reason' => 'No old battery status records'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("DELETE FROM battery_status WHERE timestamp < ?");
            $stmt->execute([$cutoffDate]);
        }

        $policy = [
            'name' => 'old_battery_status',
            'deleted' => $count,
            'reason' => "Deleted battery status before {$cutoffDate} ({$days} days retention)",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("Battery status cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Delete old system logs
     */
    protected static function cleanSystemLogs(): array {
        $days = self::$config['log_days'];
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Preserve critical/warning logs longer than info logs
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM system_logs
            WHERE timestamp < ?
            AND (severity = 'info' OR timestamp < DATE_SUB(?, INTERVAL 60 DAY))
        ");
        $stmt->execute([$cutoffDate, $cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'system_logs', 'deleted' => 0, 'reason' => 'No old system logs'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("
                DELETE FROM system_logs
                WHERE timestamp < ?
                AND (severity = 'info' OR timestamp < DATE_SUB(?, INTERVAL 60 DAY))
            ");
            $stmt->execute([$cutoffDate, $cutoffDate]);
        }

        $policy = [
            'name' => 'system_logs',
            'deleted' => $count,
            'reason' => "Deleted info logs before {$cutoffDate} and critical/warning logs before -60 days",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("System logs cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Delete old email logs
     */
    protected static function cleanEmailLogs(): array {
        // Skip if email_notification_log doesn't exist (migration not applied)
        if (!self::tableExists('email_notification_log')) {
            return ['name' => 'email_logs', 'deleted' => 0, 'reason' => 'Table not found (migration not applied)'];
        }

        $days = self::$config['email_log_days'];
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Keep failed emails longer for diagnostic purposes
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM email_notification_log
            WHERE created_at < ?
            AND (status = 'sent' OR created_at < DATE_SUB(?, INTERVAL 90 DAY))
        ");
        $stmt->execute([$cutoffDate, $cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'email_logs', 'deleted' => 0, 'reason' => 'No old email logs'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("
                DELETE FROM email_notification_log
                WHERE created_at < ?
                AND (status = 'sent' OR created_at < DATE_SUB(?, INTERVAL 90 DAY))
            ");
            $stmt->execute([$cutoffDate, $cutoffDate]);
        }

        $policy = [
            'name' => 'email_logs',
            'deleted' => $count,
            'reason' => "Deleted sent emails before {$cutoffDate} and failed emails before -90 days",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("Email logs cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Delete old notification logs
     */
    protected static function cleanNotificationLogs(): array {
        // Skip if email_notification_queue doesn't exist (migration not applied)
        if (!self::tableExists('email_notification_queue')) {
            return ['name' => 'notification_logs', 'deleted' => 0, 'reason' => 'Table not found (migration not applied)'];
        }

        $days = self::$config['notification_log_days'];
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Count only successful notifications for cleanup
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM email_notification_queue
            WHERE status = 'sent' AND sent_at < ?
        ");
        $stmt->execute([$cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'notification_logs', 'deleted' => 0, 'reason' => 'No old notification queue entries'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("DELETE FROM email_notification_queue WHERE status = 'sent' AND sent_at < ?");
            $stmt->execute([$cutoffDate]);
        }

        $policy = [
            'name' => 'notification_logs',
            'deleted' => $count,
            'reason' => "Deleted sent notifications before {$cutoffDate} ({$days} days retention)",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("Notification queue cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Delete permanently failed queue entries older than 30 days
     */
    protected static function cleanFailedQueue(): array {
        // Skip if email_notification_queue doesn't exist (migration not applied)
        if (!self::tableExists('email_notification_queue')) {
            return ['name' => 'queue_failures', 'deleted' => 0, 'reason' => 'Table not found (migration not applied)'];
        }

        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));

        // Count records that would be deleted
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM email_notification_queue
            WHERE attempts >= 5
            AND status IN ('failed', 'bounced')
            AND created_at < ?
        ");
        $stmt->execute([$cutoffDate]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return ['name' => 'queue_failures', 'deleted' => 0, 'reason' => 'No old failed queue entries'];
        }

        if (!self::$dryRun) {
            $stmt = $db->prepare("
                DELETE FROM email_notification_queue
                WHERE attempts >= 5
                AND status IN ('failed', 'bounced')
                AND created_at < ?
            ");
            $stmt->execute([$cutoffDate]);
        }

        $policy = [
            'name' => 'queue_failures',
            'deleted' => $count,
            'reason' => "Deleted permanently failed queue entries (>5 attempts) before {$cutoffDate}",
            'dry_run' => self::$dryRun,
        ];

        self::$logger->info("Failed queue cleanup: {$count} rows", $policy);

        return $policy;
    }

    /**
     * Check if table exists in database
     */
    protected static function tableExists(string $tableName): bool {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            try {
                require_once __DIR__ . '/../config/database.php';
                $db = getDB();
            } catch (Exception $e) {
                return false;
            }
        }

        try {
            $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get retention statistics
     */
    public static function getStats(): array {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
        }

        return [
            'alerts' => [
                'total' => (int) $db->query("SELECT COUNT(*) FROM alerts")->fetchColumn(),
                'active' => (int) $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'")->fetchColumn(),
                'old_resolved' => (int) $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'resolved' AND timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)")->fetchColumn(),
            ],
            'energy_readings' => [
                'total' => (int) $db->query("SELECT COUNT(*) FROM energy_readings")->fetchColumn(),
                'old' => (int) $db->query("SELECT COUNT(*) FROM energy_readings WHERE timestamp < DATE_SUB(NOW(), INTERVAL 365 DAY)")->fetchColumn(),
            ],
            'battery_status' => [
                'total' => (int) $db->query("SELECT COUNT(*) FROM battery_status")->fetchColumn(),
                'old' => (int) $db->query("SELECT COUNT(*) FROM battery_status WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)")->fetchColumn(),
            ],
            'system_logs' => [
                'total' => (int) $db->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
                'old' => (int) $db->query("SELECT COUNT(*) FROM system_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            ],
        ];
    }
}
