-- ============================================================================
-- Database Indexing Optimization
-- Created: 2026-03-15
-- Purpose: Add strategic indexes for common query patterns
-- Impact: 2-10x faster queries on energy readings, alerts, batteries
-- ============================================================================

-- Check current indexes before applying
-- SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='microgrid_platform';

-- ============================================================================
-- 1. USERS TABLE - Role and Family Filtering
-- ============================================================================
-- Improves: Finding users by role, finding family admins, user lookups

ALTER TABLE users ADD INDEX idx_family_id (family_id);
ALTER TABLE users ADD INDEX idx_role (role);
ALTER TABLE users ADD INDEX idx_family_role (family_id, role);

-- ============================================================================
-- 2. MICROGRIDS TABLE - Type and Status Filtering
-- ============================================================================
-- Improves: Finding solar/wind systems, status-based queries, family microgrids

ALTER TABLE microgrids ADD INDEX idx_family_id (family_id);
ALTER TABLE microgrids ADD INDEX idx_type (type);
ALTER TABLE microgrids ADD INDEX idx_status (status);
ALTER TABLE microgrids ADD INDEX idx_family_type (family_id, type);
ALTER TABLE microgrids ADD INDEX idx_family_status (family_id, status);

-- ============================================================================
-- 3. ENERGY_READINGS TABLE - Critical for time-series queries
-- ============================================================================
-- Improves: Recent readings queries, date range queries, power analytics

-- Note: idx_microgrid_time already exists, but improved with DESC for time queries
ALTER TABLE energy_readings ADD INDEX idx_microgrid_time_desc (microgrid_id, timestamp DESC);
ALTER TABLE energy_readings ADD INDEX idx_timestamp (timestamp);
ALTER TABLE energy_readings ADD INDEX idx_power (power_kw);

-- ============================================================================
-- 4. BATTERY_STATUS TABLE - Critical for battery analytics
-- ============================================================================
-- Improves: Recent battery status, low battery alerts, family battery queries

ALTER TABLE battery_status ADD INDEX idx_family_id (family_id);
-- idx_family_time already exists
ALTER TABLE battery_status ADD INDEX idx_charge_status (charge_status);
ALTER TABLE battery_status ADD INDEX idx_battery_level (battery_level);

-- ============================================================================
-- 5. ALERTS TABLE - Common and critical filtering
-- ============================================================================
-- Improves: Active alerts per family/microgrid, severity filtering, date ranges

-- idx_family_status already exists
ALTER TABLE alerts ADD INDEX idx_microgrid_id (microgrid_id);
ALTER TABLE alerts ADD INDEX idx_severity (severity);
ALTER TABLE alerts ADD INDEX idx_alert_type (alert_type);
ALTER TABLE alerts ADD INDEX idx_family_timestamp (family_id, timestamp DESC);
ALTER TABLE alerts ADD INDEX idx_family_severity (family_id, severity);
ALTER TABLE alerts ADD INDEX idx_family_alert_type (family_id, alert_type);

-- ============================================================================
-- 6. ENERGY_CONSUMPTION TABLE - Analytics queries
-- ============================================================================
-- Improves: Daily/weekly/monthly consumption aggregations

ALTER TABLE energy_consumption ADD INDEX idx_family_id (family_id);
-- idx_family_time already exists
ALTER TABLE energy_consumption ADD INDEX idx_consumed_kwh (consumed_kwh);

-- ============================================================================
-- 7. API_KEYS TABLE - Device authentication
-- ============================================================================
-- Improves: API key lookups, family API key searches

ALTER TABLE api_keys ADD INDEX idx_family_id (family_id);
ALTER TABLE api_keys ADD INDEX idx_active (is_active);

-- ============================================================================
-- 8. SYSTEM_LOGS TABLE - Enhanced logging queries
-- ============================================================================
-- Improves: Audit trail searches, event type filtering

-- idx_logs_time and idx_logs_family already exist
ALTER TABLE system_logs ADD INDEX idx_event_type (event_type);
ALTER TABLE system_logs ADD INDEX idx_family_timestamp (family_id, timestamp DESC);
ALTER TABLE system_logs ADD INDEX idx_microgrid_id (microgrid_id);

-- ============================================================================
-- 9. OPTIONAL: Full-Text Search (if searching log messages)
-- ============================================================================
-- Uncomment to enable full-text search on system logs and alerts
-- ALTER TABLE system_logs ADD FULLTEXT INDEX ft_message (message);
-- ALTER TABLE alerts ADD FULLTEXT INDEX ft_message (message);

-- ============================================================================
-- 10. Optional Index Maintenance
-- ============================================================================
-- NOTE: Run these after applying indexes to ensure optimal performance
-- 
-- Analyze tables to update statistics:
-- ANALYZE TABLE users;
-- ANALYZE TABLE microgrids;
-- ANALYZE TABLE energy_readings;
-- ANALYZE TABLE battery_status;
-- ANALYZE TABLE alerts;
-- ANALYZE TABLE energy_consumption;
-- ANALYZE TABLE api_keys;
-- ANALYZE TABLE system_logs;
--
-- Check table size and fragmentation:
-- SELECT 
--     table_name, 
--     round(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
--     data_free / 1024 / 1024 as fragmentation_mb
-- FROM information_schema.TABLES 
-- WHERE table_schema = 'microgrid_platform'
-- ORDER BY size_mb DESC;
--
-- Optimize tables if fragmented:
-- OPTIMIZE TABLE users;
-- OPTIMIZE TABLE microgrids;
-- OPTIMIZE TABLE energy_readings;
-- OPTIMIZE TABLE alerts;
-- etc.

-- ============================================================================
-- Verification Queries
-- ============================================================================
-- After applying this migration, verify indexes were created:
-- 
-- SELECT 
--     TABLE_NAME, 
--     INDEX_NAME, 
--     COLUMN_NAME, 
--     SEQ_IN_INDEX
-- FROM information_schema.STATISTICS 
-- WHERE TABLE_SCHEMA = 'microgrid_platform' 
--   AND TABLE_NAME IN ('users', 'microgrids', 'energy_readings', 'battery_status', 'alerts', 'energy_consumption', 'api_keys', 'system_logs')
-- ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
--
-- Check index usage:
-- SELECT 
--     OBJECT_SCHEMA, 
--     OBJECT_NAME, 
--     COUNT_READ, 
--     COUNT_INSERT, 
--     COUNT_UPDATE, 
--     COUNT_DELETE
-- FROM performance_schema.table_io_waits_summary_by_index_usage 
-- WHERE OBJECT_SCHEMA = 'microgrid_platform'
-- ORDER BY COUNT_READ DESC;

