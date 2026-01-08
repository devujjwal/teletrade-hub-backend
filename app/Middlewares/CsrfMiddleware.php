<?php

if (!class_exists('Env')) {
    require_once __DIR__ . '/../Config/env.php';
}

/**
 * CSRF Protection Middleware
 * Implements Cross-Site Request Forgery protection for state-changing operations
 */
class CsrfMiddleware
{
    /**
     * Generate CSRF token
     */
    public static function generateToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Use hash_equals for timing-safe comparison
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Verify CSRF token from request
     * Checks X-CSRF-Token header or csrf_token parameter
     */
    public static function verify()
    {
        // Skip CSRF check for GET, HEAD, OPTIONS requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        
        // Skip CSRF check for API endpoints using token-based auth (Bearer tokens)
        // CSRF is primarily for cookie-based sessions
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (!empty($authHeader) && preg_match('/Bearer\s+/i', $authHeader)) {
            // Token-based auth is CSRF-resistant
            return true;
        }
        
        // Get token from header or POST data
        $token = null;
        if (isset($headers['X-CSRF-Token'])) {
            $token = $headers['X-CSRF-Token'];
        } elseif (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        if (empty($token)) {
            Response::error('CSRF token missing', 403);
            return false;
        }
        
        if (!self::validateToken($token)) {
            Response::error('Invalid CSRF token', 403);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get CSRF token for response
     */
    public static function getToken()
    {
        return self::generateToken();
    }
}

