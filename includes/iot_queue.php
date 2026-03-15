<?php
/**
 * IoT Message Queue System
 * 
 * Implements FIFO queue for asynchronous processing of IoT device readings.
 * Decouples device ingestion from database writes, enabling:
 * - High-throughput device connections (1000s of devices)
 * - Batch processing for database performance
 * - Automatic retries for failed writes
 * - Message persistence
 * 
 * Database table required (created by migration):
 *   CREATE TABLE iot_message_queue (
 *       id BIGINT AUTO_INCREMENT PRIMARY KEY,
 *       device_id VARCHAR(100),
 *       message_type ENUM('reading', 'battery', 'alert'),
 *       payload JSON,
 *       status ENUM('pending', 'processing', 'success', 'failed'),
 *       retry_count INT DEFAULT 0,
 *       error_message TEXT,
 *       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *       processed_at TIMESTAMP NULL,
 *       INDEX idx_status_created (status, created_at),
 *       INDEX idx_device_type (device_id, message_type)
 *   );
 */

require_once __DIR__ . '/../config/database.php';

class IoTMessageQueue {
    private static $db = null;
    private static $max_retries = 3;
    private static $batch_size = 100;
    
    public static function init() {
        self::$db = getDB();
    }
    
    /**
     * Enqueue an IoT message (from device API)
     */
    public static function enqueue($device_id, $message_type, $payload) {
        try {
            $stmt = self::$db->prepare("
                INSERT INTO iot_message_queue 
                (device_id, message_type, payload, status) 
                VALUES (?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $device_id,
                $message_type,  // 'reading', 'battery', 'alert'
                json_encode($payload)
            ]);
            
            return self::$db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to enqueue IoT message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process pending messages (called by CLI worker or cron)
     */
    public static function processMessages() {
        try {
            // Get pending messages (FIFO)
            $stmt = self::$db->prepare("
                SELECT id, device_id, message_type, payload, retry_count
                FROM iot_message_queue
                WHERE status = 'pending'
                ORDER BY created_at ASC
                LIMIT ?
            ");
            
            $stmt->execute([self::$batch_size]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            foreach ($messages as $msg) {
                if (self::processMessage($msg)) {
                    $processed++;
                }
            }
            
            return [
                'processed' => $processed,
                'pending' => self::getPendingCount(),
                'failed' => self::getFailedCount()
            ];
        } catch (Exception $e) {
            error_log("Error processing IoT queue: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process single message
     */
    private static function processMessage($msg) {
        $msg_id = $msg['id'];
        $device_id = $msg['device_id'];
        $message_type = $msg['message_type'];
        $payload = json_decode($msg['payload'], true);
        $retry_count = $msg['retry_count'];
        
        try {
            // Mark as processing
            self::updateStatus($msg_id, 'processing');
            
            // Route message
            switch ($message_type) {
                case 'reading':
                    self::processEnergyReading($device_id, $payload);
                    break;
                case 'battery':
                    self::processBatteryStatus($device_id, $payload);
                    break;
                case 'alert':
                    self::processDeviceAlert($device_id, $payload);
                    break;
                default:
                    throw new Exception("Unknown message type: $message_type");
            }
            
            // Mark as success
            self::updateStatus($msg_id, 'success');
            return true;
            
        } catch (Exception $e) {
            error_log("Error processing message {$msg_id}: " . $e->getMessage());
            
            // Retry logic
            if ($retry_count < self::$max_retries) {
                self::updateStatus($msg_id, 'pending', $retry_count + 1);
                return false;
            } else {
                self::updateStatus($msg_id, 'failed', $retry_count, $e->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Process energy reading message
     */
    private static function processEnergyReading($device_id, $payload) {
        // Get microgrid from device mapping
        $stmt = self::$db->prepare("
            SELECT microgrid_id FROM device_mappings WHERE api_key = ?
        ");
        $stmt->execute([$device_id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("Device not found: $device_id");
        }
        
        $microgrid_id = $result['microgrid_id'];
        
        // Insert reading
        $stmt = self::$db->prepare("
            INSERT INTO energy_readings 
            (microgrid_id, voltage, current_amp, power_kw, energy_kwh, temperature, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $microgrid_id,
            $payload['voltage'] ?? 0,
            $payload['current'] ?? 0,
            $payload['power'] ?? 0,
            $payload['energy'] ?? 0,
            $payload['temperature'] ?? null,
            $payload['timestamp'] ?? date('Y-m-d H:i:s')
        ]);
        
        // Trigger alert check
        checkAndGenerateAlerts($microgrid_id);
    }
    
    /**
     * Process battery status message
     */
    private static function processBatteryStatus($device_id, $payload) {
        // Get family from device mapping
        $stmt = self::$db->prepare("
            SELECT family_id FROM device_mappings WHERE api_key = ?
        ");
        $stmt->execute([$device_id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("Device not found: $device_id");
        }
        
        $family_id = $result['family_id'];
        
        // Insert battery status
        $stmt = self::$db->prepare("
            INSERT INTO battery_status 
            (family_id, battery_level, voltage, charge_status, temperature, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $family_id,
            $payload['level'] ?? 0,
            $payload['voltage'] ?? 0,
            $payload['charge_status'] ?? 'idle',
            $payload['temperature'] ?? null,
            $payload['timestamp'] ?? date('Y-m-d H:i:s')
        ]);
        
        // Check battery alerts
        checkBatteryAlerts($family_id);
    }
    
    /**
     * Process device alert message
     */
    private static function processDeviceAlert($device_id, $payload) {
        $stmt = self::$db->prepare("
            SELECT family_id, microgrid_id FROM device_mappings WHERE api_key = ?
        ");
        $stmt->execute([$device_id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("Device not found: $device_id");
        }
        
        // Create alert
        $stmt = self::$db->prepare("
            INSERT INTO alerts 
            (family_id, microgrid_id, alert_type, severity, message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $result['family_id'],
            $result['microgrid_id'],
            $payload['type'] ?? 'system_error',
            $payload['severity'] ?? 'warning',
            $payload['message'] ?? 'Device alert'
        ]);
        
        // Send notification if enabled
        if (function_exists('sendAlertNotifications')) {
            sendAlertNotifications($result['family_id']);
        }
    }
    
    /**
     * Update message status
     */
    private static function updateStatus($msg_id, $status, $retry_count = null, $error = null) {
        $query = "UPDATE iot_message_queue SET status = ?";
        $params = [$status];
        
        if ($retry_count !== null) {
            $query .= ", retry_count = ?";
            $params[] = $retry_count;
        }
        
        if ($error) {
            $query .= ", error_message = ?";
            $params[] = substr($error, 0, 255);
        }
        
        if ($status === 'success') {
            $query .= ", processed_at = NOW()";
        }
        
        $query .= " WHERE id = ?";
        $params[] = $msg_id;
        
        $stmt = self::$db->prepare($query);
        $stmt->execute($params);
    }
    
    /**
     * Get queue statistics
     */
    public static function getStats() {
        $stmt = self::$db->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                CASE 
                    WHEN status = 'pending' THEN MIN(created_at)
                    ELSE NULL
                END as oldest_pending
            FROM iot_message_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending message count
     */
    public static function getPendingCount() {
        $stmt = self::$db->query("SELECT COUNT(*) FROM iot_message_queue WHERE status = 'pending'");
        return $stmt->fetchColumn();
    }
    
    /**
     * Get failed message count
     */
    public static function getFailedCount() {
        $stmt = self::$db->query("SELECT COUNT(*) FROM iot_message_queue WHERE status = 'failed'");
        return $stmt->fetchColumn();
    }
    
    /**
     * Retry failed messages (Exponential backoff)
     */
    public static function retryFailed() {
        $stmt = self::$db->prepare("
            UPDATE iot_message_queue 
            SET status = 'pending', retry_count = 0
            WHERE status = 'failed' 
            AND retry_count < ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 100
        ");
        
        $stmt->execute([self::$max_retries]);
        return $stmt->rowCount();
    }
    
    /**
     * Cleanup old messages (> 30 days, successful only)
     */
    public static function cleanup() {
        $stmt = self::$db->prepare("
            DELETE FROM iot_message_queue
            WHERE status = 'success'
            AND processed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            LIMIT 10000
        ");
        
        $stmt->execute();
        return $stmt->rowCount();
    }
}

?>
