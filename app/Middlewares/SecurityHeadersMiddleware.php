<?php

/**
 * Security Headers Middleware
 * Adds security headers to all responses
 */
class SecurityHeadersMiddleware
{
    /**
     * Apply security headers
     */
    public static function apply()
    {
        // Prevent clickjacking attacks
        header("X-Frame-Options: DENY");
        
        // Prevent MIME-sniffing attacks
        header("X-Content-Type-Options: nosniff");
        
        // Enable XSS protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // TODO: Remove unsafe-inline and unsafe-eval in production
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        header("Content-Security-Policy: $csp");
        
        // Strict Transport Security (HSTS) - Only if using HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
        
        // Feature Policy / Permissions Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Set secure CORS headers
     */
    public static function setCorsHeaders()
    {
        $allowedOrigins = explode(',', Env::get('CORS_ALLOWED_ORIGINS', ''));
        $allowedOrigins = array_filter(array_map('trim', $allowedOrigins));
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // SECURITY: Never use wildcard (*) in production with credentials
        if (in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
        } elseif (Env::get('APP_ENV') === 'development') {
            // Allow wildcard only in development
            header("Access-Control-Allow-Origin: *");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
        header("Access-Control-Max-Age: 86400");
        header("Vary: Origin"); // Important for caching
    }
}

