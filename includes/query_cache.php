<?php
/**
 * Query Result Caching System
 * 
 * Implements in-memory caching for frequently-executed database queries
 * Supports multiple backends: File, APCu (in-memory), Redis (distributed)
 * 
 * Usage:
 *   // Cache analytics query for 5 minutes
 *   $key = 'family_2_monthly_stats';
 *   $stats = QueryCache::get($key);
 *   
 *   if ($stats === null) {
 *       $stats = $db->query("SELECT SUM(energy_kwh) FROM energy_readings...")->fetchAll();
 *       QueryCache::set($key, $stats, 300); // 5 minutes
 *   }
 */

class QueryCache {
    private static $driver = null;
    private static $config = [];
    
    /**
     * Initialize caching system
     */
    public static function init($driver = null, $config = []) {
        self::$driver = $driver ?? self::getOptimalDriver();
        self::$config = array_merge([
            'default_ttl' => 3600, // 1 hour default
            'prefix' => 'qc_',
            'enabled' => true
        ], $config);
    }
    
    /**
     * Detect best available caching backend
     */
    private static function getOptimalDriver() {
        // Redis preferred (distributed cache)
        if (extension_loaded('redis') && isset($_ENV['REDIS_HOST'])) {
            return 'redis';
        }
        
        // APCu for single-server deployments (fastest)
        if (extension_loaded('apcu')) {
            return 'apcu';
        }
        
        // File-based fallback (always available)
        return 'file';
    }
    
    /**
     * Get cached value
     */
    public static function get($key) {
        if (empty(self::$config)) self::init();
        if (!self::$config['enabled']) return null;
        
        $key = self::$config['prefix'] . $key;
        
        switch (self::$driver) {
            case 'redis':
                return self::getRedis($key);
            case 'apcu':
                return self::getApcu($key);
            default:
                return self::getFile($key);
        }
    }
    
    /**
     * Set cache value
     */
    public static function set($key, $value, $ttl = null) {
        if (empty(self::$config)) self::init();
        if (!self::$config['enabled']) return false;
        
        $key = self::$config['prefix'] . $key;
        $ttl = $ttl ?? self::$config['default_ttl'];
        
        switch (self::$driver) {
            case 'redis':
                return self::setRedis($key, $value, $ttl);
            case 'apcu':
                return self::setApcu($key, $value, $ttl);
            default:
                return self::setFile($key, $value, $ttl);
        }
    }
    
    /**
     * Delete cache entry
     */
    public static function delete($key) {
        if (empty(self::$config)) self::init();
        $key = self::$config['prefix'] . $key;
        
        switch (self::$driver) {
            case 'redis':
                $redis = new Redis();
                $redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', $_ENV['REDIS_PORT'] ?? 6379);
                return $redis->del($key);
            case 'apcu':
                return apcu_delete($key);
            default:
                return @unlink(self::getCacheFilePath($key));
        }
    }
    
    /**
     * Clear all cache
     */
    public static function flush() {
        switch (self::$driver) {
            case 'redis':
                $redis = new Redis();
                $redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', $_ENV['REDIS_PORT'] ?? 6379);
                $redis->flushDb();
                break;
            case 'apcu':
                apcu_clear_cache();
                break;
            default:
                $pattern = __DIR__ . '/../cache/qc_*.json';
                foreach (glob($pattern) as $file) {
                    @unlink($file);
                }
        }
    }
    
    // ========================================================================
    // Backend implementations
    // ========================================================================
    
    private static function getRedis($key) {
        try {
            $redis = new Redis();
            $redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', $_ENV['REDIS_PORT'] ?? 6379);
            $value = $redis->get($key);
            return $value ? unserialize($value) : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private static function setRedis($key, $value, $ttl) {
        try {
            $redis = new Redis();
            $redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', $_ENV['REDIS_PORT'] ?? 6379);
            return $redis->setex($key, $ttl, serialize($value));
        } catch (Exception $e) {
            return false;
        }
    }
    
    private static function getApcu($key) {
        $value = apcu_fetch($key, $success);
        return $success ? $value : null;
    }
    
    private static function setApcu($key, $value, $ttl) {
        return apcu_store($key, $value, $ttl);
    }
    
    private static function getFile($key) {
        $path = self::getCacheFilePath($key);
        if (!file_exists($path)) return null;
        
        $data = json_decode(file_get_contents($path), true);
        
        // Check if expired
        if ($data['expires'] < time()) {
            @unlink($path);
            return null;
        }
        
        return $data['value'];
    }
    
    private static function setFile($key, $value, $ttl) {
        $path = self::getCacheFilePath($key);
        
        // Ensure cache directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = [
            'key' => $key,
            'value' => $value,
            'created' => time(),
            'expires' => time() + $ttl
        ];
        
        return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
    
    private static function getCacheFilePath($key) {
        return __DIR__ . '/../cache/' . $key . '.json';
    }
}

/**
 * Query Cache Helper Trait
 * Use in classes that execute database queries
 */
trait CacheableQueries {
    
    /**
     * Execute query with caching
     * 
     * Example:
     *   $stats = $this->cachedQuery(
     *       'daily_stats_family_2',
     *       "SELECT DATE(timestamp) as date, SUM(energy_kwh) FROM energy_readings 
     *        WHERE family_id = 2 AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     *        GROUP BY DATE(timestamp)",
     *       3600  // Cache for 1 hour
     *   );
     */
    protected function cachedQuery($cache_key, $sql, $ttl = 3600) {
        // Try to get from cache
        $result = QueryCache::get($cache_key);
        if ($result !== null) {
            return $result;
        }
        
        // Execute query
        $result = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Store in cache
        QueryCache::set($cache_key, $result, $ttl);
        
        return $result;
    }
    
    /**
     * Invalidate related cache entries
     * 
     * Call this after INSERT/UPDATE/DELETE to invalidate stale cache
     * 
     * Example:
     *   private function invalidateAlertCache($family_id) {
     *       QueryCache::delete("alerts_family_{$family_id}");
     *       QueryCache::delete("alert_stats_family_{$family_id}");
     *   }
     */
    protected function invalidateCache($pattern) {
        // Simple pattern-based invalidation (file-based only)
        // For Redis, use: redis->del(redis->keys($pattern))
        $glob_pattern = __DIR__ . '/../cache/' . str_replace('*', '*', $pattern) . '.json';
        foreach (glob($glob_pattern) as $file) {
            @unlink($file);
        }
    }
}

?>
