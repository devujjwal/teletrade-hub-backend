<?php

/**
 * Security Logger
 * Centralized logging for security events
 */
class SecurityLogger
{
    private static $logFile = __DIR__ . '/../../storage/logs/security.log';
    
    /**
     * Log authentication event
     */
    public static function logAuth($event, $identifier, $success = true, $details = [])
    {
        self::log('AUTH', $event, [
            'identifier' => $identifier,
            'success' => $success,
            'ip' => self::getClientIp(),
            'user_agent' => self::getUserAgent(),
            'details' => $details
        ]);
    }
    
    /**
     * Log admin action
     */
    public static function logAdminAction($action, $adminUser, $details = [])
    {
        self::log('ADMIN', $action, [
            'admin_user' => $adminUser,
            'ip' => self::getClientIp(),
            'user_agent' => self::getUserAgent(),
            'details' => $details
        ]);
    }
    
    /**
     * Log security violation
     */
    public static function logSecurityViolation($type, $details = [])
    {
        self::log('SECURITY_VIOLATION', $type, [
            'ip' => self::getClientIp(),
            'user_agent' => self::getUserAgent(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'details' => $details
        ], 'CRITICAL');
    }
    
    /**
     * Log vendor API call
     */
    public static function logVendorApi($endpoint, $method, $statusCode, $duration, $error = null)
    {
        self::log('VENDOR_API', $endpoint, [
            'method' => $method,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'error' => $error
        ]);
    }
    
    /**
     * Log order event
     */
    public static function logOrder($event, $orderNumber, $details = [])
    {
        self::log('ORDER', $event, [
            'order_number' => $orderNumber,
            'details' => $details
        ]);
    }
    
    /**
     * Generic log method
     */
    private static function log($category, $event, $data = [], $level = 'INFO')
    {
        // Don't log sensitive data in production
        $data = self::sanitizeLogData($data);
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'category' => $category,
            'event' => $event,
            'data' => $data
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Write to log file
        error_log($logLine, 3, self::$logFile);
        
        // Also log critical events to system error log
        if ($level === 'CRITICAL') {
            error_log("[$level] $category: $event - " . json_encode($data));
        }
    }
    
    /**
     * Sanitize log data to prevent sensitive info leakage
     * SECURITY: Never log passwords, tokens, or credit card numbers
     */
    private static function sanitizeLogData($data)
    {
        $sensitiveKeys = [
            'password', 'password_hash', 'token', 'api_key', 
            'secret', 'credit_card', 'cvv', 'card_number',
            'authorization', 'session_id'
        ];
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $lowerKey = strtolower($key);
                
                // Check if key contains sensitive information
                foreach ($sensitiveKeys as $sensitiveKey) {
                    if (strpos($lowerKey, $sensitiveKey) !== false) {
                        $data[$key] = '***REDACTED***';
                        break;
                    }
                }
                
                // Recursively sanitize nested arrays
                if (is_array($value)) {
                    $data[$key] = self::sanitizeLogData($value);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get user agent
     */
    private static function getUserAgent()
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 200);
    }
    
    /**
     * Rotate logs (call this daily via cron)
     */
    public static function rotateLogs()
    {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
        $fileSize = filesize(self::$logFile);
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($fileSize > $maxSize) {
            $timestamp = date('Y-m-d_His');
            $archivePath = self::$logFile . ".$timestamp";
            rename(self::$logFile, $archivePath);
            
            // Compress old log
            if (function_exists('gzencode')) {
                $content = file_get_contents($archivePath);
                file_put_contents($archivePath . '.gz', gzencode($content));
                unlink($archivePath);
            }
        }
        
        // Delete logs older than 90 days
        $logDir = dirname(self::$logFile);
        $files = glob($logDir . '/security.log.*');
        $cutoffTime = time() - (90 * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
}

