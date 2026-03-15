<?php
/**
 * Analytics Functions with Query Caching
 * 
 * Example implementation showing how to cache expensive analytics queries
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/query_cache.php';

// Initialize caching
QueryCache::init(); // Auto-detects best backend

class Analytics {
    use CacheableQueries;
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get 30-day energy statistics (cached 1 hour)
     */
    public function get30DayStats($family_id) {
        return $this->cachedQuery(
            "stats_30day_family_{$family_id}",
            "SELECT 
                DATE(timestamp) as date,
                SUM(energy_kwh) as total_kwh,
                AVG(power_kw) as avg_power,
                MAX(power_kw) as peak_power,
                COUNT(*) as reading_count
            FROM energy_readings inner_readings
            WHERE family_id = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date DESC",
            3600, // 1 hour cache
            [$family_id]
        );
    }
    
    /**
     * Get monthly consumption summary (cached 6 hours)
     */
    public function getMonthlyConsumption($family_id, $months = 12) {
        return $this->cachedQuery(
            "consumption_monthly_family_{$family_id}_{$months}m",
            "SELECT 
                YEAR(timestamp) as year,
                MONTH(timestamp) as month,
                SUM(consumed_kwh) as total_consumed,
                SUM(consumed_kwh * rate) as total_cost
            FROM energy_consumption ec
            LEFT JOIN tariff_settings ts ON 1=1
            WHERE family_id = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            AND ts.is_active = 1
            GROUP BY YEAR(timestamp), MONTH(timestamp)
            ORDER BY year DESC, month DESC",
            21600, // 6 hours cache
            [$family_id, $months]
        );
    }
    
    /**
     * Get alert statistics (cached 30 minutes)
     */
    public function getAlertStats($family_id) {
        return $this->cachedQuery(
            "alerts_stats_family_{$family_id}",
            "SELECT 
                severity,
                alert_type,
                status,
                COUNT(*) as count,
                MAX(timestamp) as last_occurrence
            FROM alerts
            WHERE family_id = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY severity, alert_type, status",
            1800, // 30 minutes
            [$family_id]
        );
    }
    
    /**
     * Get battery health metrics (cached 10 minutes)
     */
    public function getBatteryHealth($family_id) {
        return $this->cachedQuery(
            "battery_health_family_{$family_id}",
            "SELECT 
                battery_name,
                capacity_kwh,
                AVG(battery_level) as avg_level,
                MIN(battery_level) as min_level,
                MAX(battery_level) as max_level,
                STDDEV(battery_level) as level_volatility,
                AVG(temperature) as avg_temp,
                MAX(temperature) as max_temp,
                COUNT(DISTINCT DATE(timestamp)) as days_monitored
            FROM battery_status
            WHERE family_id = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY battery_id",
            600, // 10 minutes
            [$family_id]
        );
    }
    
    /**
     * Platform-wide statistics (cached 1 hour)
     */
    public function getPlatformStats() {
        return $this->cachedQuery(
            "platform_stats_overall",
            "SELECT 
                (SELECT COUNT(DISTINCT family_id) FROM users WHERE role='user') as total_families,
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM microgrids WHERE status='active') as active_microgrids,
                (SELECT COALESCE(SUM(capacity_kw), 0) FROM microgrids) as total_capacity_kw,
                (SELECT COUNT(*) FROM alerts WHERE status='active') as active_alerts,
                (SELECT COUNT(*) FROM alerts WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as alerts_24h,
                (SELECT COALESCE(SUM(energy_kwh), 0) FROM energy_readings WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as generation_24h_kwh,
                (SELECT COALESCE(SUM(consumed_kwh), 0) FROM energy_consumption WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as consumption_24h_kwh",
            3600 // 1 hour
        );
    }
    
    // Fix the cachedQuery implementation to pass parameters
    protected function cachedQuery($cache_key, $sql, $ttl = 3600, $params = []) {
        $result = QueryCache::get($cache_key);
        if ($result !== null) {
            return $result;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        QueryCache::set($cache_key, $result, $ttl);
        return $result;
    }
}

?>
