<?php
/**
 * Centralized Logging System
 * Provides structured logging for errors, warnings, and info messages
 * Logs to file with rotation and severity levels
 */

class Logger {
    private static $initialized = false;
    private static $logFile = '';
    private static $logLevel = 'warning';
    private static $maxFileSize = 10485760;  // 10MB

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private static $levelPriority = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    /**
     * Initialize logger (call once at application startup)
     */
    public static function init(?string $logFile = null, ?string $level = null): void {
        if (self::$initialized) {
            return;
        }

        // Load from environment or use defaults
        self::$logFile = $logFile ?? Env::get('LOG_FILE',  sys_get_temp_dir() . '/microgrid_platform.log');
        self::$logLevel = $level ?? Env::get('LOG_LEVEL', 'warning');

        // Create log directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        self::$initialized = true;

        // Log startup
        self::info('Logger initialized', [
            'environment' => Env::get('APP_ENV', 'production'),
            'log_level' => self::$logLevel,
            'log_file' => self::$logFile
        ]);
    }

    /**
     * Debug level logging (lowest priority)
     */
    public static function debug(string $message, ?array $context = null): void {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Info level logging (general information)
     */
    public static function info(string $message, ?array $context = null): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Warning level logging
     */
    public static function warning(string $message, ?array $context = null): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Error level logging
     */
    public static function error(string $message, mixed $exceptionOrContext = null): void {
        $context = [];
        
        if ($exceptionOrContext instanceof Throwable) {
            $context['exception_class'] = get_class($exceptionOrContext);
            $context['exception_message'] = $exceptionOrContext->getMessage();
            $context['file'] = $exceptionOrContext->getFile();
            $context['line'] = $exceptionOrContext->getLine();
            $context['code'] = $exceptionOrContext->getCode();
        } elseif (is_array($exceptionOrContext)) {
            $context = $exceptionOrContext;
        }

        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Critical level logging (highest priority)
     */
    public static function critical(string $message, ?array $context = null): void {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Internal logging function
     */
    private static function log(string $level, string $message, ?array $context = null): void {
        // Check if we should log this level
        if (!self::$initialized) {
            self::init();
        }

        $levelPriority = self::$levelPriority[$level] ?? 1;
        $configuredPriority = self::$levelPriority[self::$logLevel] ?? 2;

        if ($levelPriority < $configuredPriority) {
            return;  // Don't log if below configured level
        }

        // Build log entry
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $contextJson = $context ? json_encode($context) : '';

        $logEntry = sprintf(
            "[%s] [%s] [PID:%d] %s %s\n",
            $timestamp,
            $level,
            $pid,
            $message,
            $contextJson
        );

        // Write to file (atomic with lock)
        self::writeLogFile($logEntry);

        // Also write to PHP error log if critical/error
        if ($levelPriority >= self::$levelPriority['ERROR']) {
            error_log($message . ' ' . $contextJson);
        }
    }

    /**
     * Write to log file with rotation if needed
     */
    private static function writeLogFile(string $entry): void {
        try {
            // Check if rotation needed
            if (file_exists(self::$logFile) && filesize(self::$logFile) > self::$maxFileSize) {
                self::rotateLogFile();
            }

            // Write with file lock
            file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Fallback to error_log if file write fails
            error_log('Logger write failed: ' . $e->getMessage());
        }
    }

    /**
     * Rotate log file when it gets too large
     */
    private static function rotateLogFile(): void {
        $timestamp = date('YmdHis');
        $rotated = self::$logFile . '.' . $timestamp;

        if (rename(self::$logFile, $rotated)) {
            // Gzip old file if gzip available
            if (function_exists('gzopen')) {
                shell_exec("gzip $rotated");
            }

            // Clean up old files (keep last 10 rotated files)
            $logDir = dirname(self::$logFile);
            $pattern = basename(self::$logFile) . '.*';
            $files = glob($logDir . '/' . $pattern);

            if (count($files) > 10) {
                usort($files, function ($a, $b) {
                    return filemtime($b) - filemtime($a);
                });

                for ($i = 10; $i < count($files); $i++) {
                    unlink($files[$i]);
                }
            }
        }
    }

    /**
     * Get log file path
     */
    public static function getLogFile(): string {
        return self::$logFile;
    }

    /**
     * Tail log file (last N lines)
     */
    public static function tail(int $lines = 50): array {
        if (!file_exists(self::$logFile)) {
            return [];
        }

        $file = new SplFileObject(self::$logFile);
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $file->seek(max(0, $lastLine - $lines));

        $result = [];
        foreach ($file as $line) {
            if (trim($line)) {
                $result[] = $line;
            }
        }

        return $result;
    }
}

// Auto-initialize logger when included
if (!function_exists('loadEnv') || function_exists('getEnv')) {
    Logger::init();
}
