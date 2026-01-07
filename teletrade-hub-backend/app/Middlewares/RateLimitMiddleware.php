<?php

/**
 * Rate Limiting Middleware
 * Prevents brute force attacks and API abuse
 */
class RateLimitMiddleware
{
    private $db;
    private $cacheFile;
    
    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->cacheFile = __DIR__ . '/../../storage/rate_limit_cache.json';
    }
    
    /**
     * Check rate limit for endpoint
     * 
     * @param string $identifier IP address or user identifier
     * @param string $action Action being rate limited (login, api, etc)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if within limits, false if exceeded
     */
    public function checkLimit($identifier, $action, $maxAttempts = 5, $windowSeconds = 300)
    {
        $key = $this->generateKey($identifier, $action);
        $attempts = $this->getAttempts($key);
        
        // Clean old attempts
        $cutoffTime = time() - $windowSeconds;
        $attempts = array_filter($attempts, function($timestamp) use ($cutoffTime) {
            return $timestamp > $cutoffTime;
        });
        
        if (count($attempts) >= $maxAttempts) {
            $this->logRateLimitExceeded($identifier, $action);
            return false;
        }
        
        // Record this attempt
        $attempts[] = time();
        $this->saveAttempts($key, $attempts);
        
        return true;
    }
    
    /**
     * Check and enforce rate limit (throws 429 if exceeded)
     */
    public function enforce($identifier, $action, $maxAttempts = 5, $windowSeconds = 300)
    {
        if (!$this->checkLimit($identifier, $action, $maxAttempts, $windowSeconds)) {
            $retryAfter = $windowSeconds;
            header("Retry-After: $retryAfter");
            Response::error(
                'Too many attempts. Please try again later.',
                429,
                ['retry_after' => $retryAfter]
            );
        }
    }
    
    /**
     * Clear rate limit for identifier
     */
    public function clearLimit($identifier, $action)
    {
        $key = $this->generateKey($identifier, $action);
        $this->saveAttempts($key, []);
    }
    
    /**
     * Generate cache key
     */
    private function generateKey($identifier, $action)
    {
        return hash('sha256', $action . ':' . $identifier);
    }
    
    /**
     * Get attempts from cache
     */
    private function getAttempts($key)
    {
        $cache = $this->loadCache();
        return $cache[$key] ?? [];
    }
    
    /**
     * Save attempts to cache
     */
    private function saveAttempts($key, $attempts)
    {
        $cache = $this->loadCache();
        $cache[$key] = $attempts;
        
        // Clean cache of old entries
        $this->cleanCache($cache);
        
        $this->saveCache($cache);
    }
    
    /**
     * Load cache from file
     */
    private function loadCache()
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }
        
        $content = file_get_contents($this->cacheFile);
        $cache = json_decode($content, true);
        
        return is_array($cache) ? $cache : [];
    }
    
    /**
     * Save cache to file
     */
    private function saveCache($cache)
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->cacheFile, json_encode($cache), LOCK_EX);
    }
    
    /**
     * Clean old entries from cache
     */
    private function cleanCache(&$cache)
    {
        $cutoffTime = time() - 3600; // Keep entries for 1 hour
        
        foreach ($cache as $key => $attempts) {
            $cache[$key] = array_filter($attempts, function($timestamp) use ($cutoffTime) {
                return $timestamp > $cutoffTime;
            });
            
            // Remove empty entries
            if (empty($cache[$key])) {
                unset($cache[$key]);
            }
        }
    }
    
    /**
     * Log rate limit exceeded event
     */
    private function logRateLimitExceeded($identifier, $action)
    {
        error_log("Rate limit exceeded - Action: $action, Identifier: $identifier, IP: " . 
                  ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    /**
     * Get client identifier (IP address)
     */
    public static function getClientIdentifier()
    {
        // Check for IP behind proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

