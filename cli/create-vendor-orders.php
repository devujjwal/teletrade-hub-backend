#!/usr/bin/env php
<?php
/**
 * Daily Vendor Sales Order Creation Cron Job
 * Creates sales orders at vendor using CreateSalesOrder API based on reservations
 * 
 * Usage: php cli/create-vendor-orders.php
 * 
 * Recommended cron schedule: Daily at 17:00 (5:00 PM)
 * 0 17 * * * /usr/local/bin/ea-php83 /path/to/project/cli/create-vendor-orders.php >> /path/to/project/storage/logs/cron-vendor-orders.log 2>&1
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log
ini_set('log_errors', 1);

// Determine base path - works from both cli/ and public/cli/
$basePath = __DIR__;
if (basename($basePath) === 'cli') {
    // Script is in cli/ at root level
    $basePath = dirname($basePath);
} else {
    // Script might be in public/cli/ - go up two levels
    $basePath = dirname(dirname($basePath));
}

// Set working directory
chdir($basePath);

// Logging function (define early for error reporting)
function logMessage($message, $level = 'INFO') {
    global $basePath;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    echo $logMessage;
    
    // Ensure logs directory exists
    $logDir = $basePath . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/vendor-orders.log';
    @error_log($logMessage, 3, $logFile);
}

// Log startup
logMessage("Script started from: " . __DIR__);
logMessage("Base path determined: " . $basePath);

// Check if app directory exists
$appPath = $basePath . '/app';
if (!is_dir($appPath)) {
    logMessage("ERROR: app directory not found at: {$appPath}", 'ERROR');
    logMessage("Current directory: " . getcwd(), 'ERROR');
    logMessage("Script directory: " . __DIR__, 'ERROR');
    exit(1);
}

// Load dependencies
try {
    require_once $appPath . '/Config/env.php';
    logMessage("Loaded env.php");
} catch (Exception $e) {
    logMessage("ERROR loading env.php: " . $e->getMessage(), 'ERROR');
    exit(1);
}

try {
    require_once $appPath . '/Config/database.php';
    logMessage("Loaded database.php");
} catch (Exception $e) {
    logMessage("ERROR loading database.php: " . $e->getMessage(), 'ERROR');
    exit(1);
}

try {
    require_once $appPath . '/Models/Order.php';
    require_once $appPath . '/Models/OrderItem.php';
    require_once $appPath . '/Models/Product.php';
    require_once $appPath . '/Services/OrderService.php';
    require_once $appPath . '/Services/ReservationService.php';
    require_once $appPath . '/Services/VendorApiService.php';
    logMessage("Loaded all dependencies");
} catch (Exception $e) {
    logMessage("ERROR loading dependencies: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}

// Initialize environment
try {
    Env::load();
    logMessage("Environment loaded");
} catch (Exception $e) {
    logMessage("ERROR loading environment: " . $e->getMessage(), 'ERROR');
    exit(1);
}

// Increase execution time
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

try {
    logMessage("Starting daily vendor sales order creation...");
    
    $orderService = new OrderService();
    
    $startTime = microtime(true);
    $result = $orderService->createVendorSalesOrder();
    $duration = round(microtime(true) - $startTime, 2);
    
    logMessage("Vendor order creation completed in {$duration} seconds");
    logMessage("Orders processed: {$result['orders_processed']}");
    
    if (!empty($result['processed_orders'])) {
        logMessage("Successfully processed orders: " . implode(', ', $result['processed_orders']));
    }
    
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $error) {
            logMessage("Order {$error['order_number']} error: {$error['error']}", 'ERROR');
        }
    }
    
    if ($result['success']) {
        logMessage("Daily vendor sales order creation completed successfully");
        exit(0);
    } else {
        logMessage("Vendor order creation completed with errors", 'WARNING');
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("Vendor order creation failed: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}
