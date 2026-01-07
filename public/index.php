<?php

/**
 * TeleTrade Hub API
 * Entry Point
 * 
 * Telecommunication Trading e.K.
 * Production-grade e-commerce platform
 */

// Error reporting (disable in production)
$debug = getenv('APP_DEBUG') === 'true';
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'Europe/Berlin');

// Load environment
require_once __DIR__ . '/../app/Config/env.php';
Env::load();

// Load database configuration (for production)
require_once __DIR__ . '/../app/Config/database.php';

// Apply security headers
require_once __DIR__ . '/../app/Middlewares/SecurityHeadersMiddleware.php';
SecurityHeadersMiddleware::apply();

// Set CORS headers
require_once __DIR__ . '/../app/Utils/Response.php';
SecurityHeadersMiddleware::setCorsHeaders();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load router and dispatch
try {
    $router = require_once __DIR__ . '/../routes/api.php';
    $router->dispatch();
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage());
    
    if ($debug) {
        Response::serverError($e->getMessage());
    } else {
        Response::serverError("An unexpected error occurred. Please try again later.");
    }
}

