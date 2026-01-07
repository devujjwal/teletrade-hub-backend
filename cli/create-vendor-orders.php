#!/usr/bin/env php
<?php
/**
 * Daily Vendor Sales Order Creation Cron Job
 * Creates sales orders at vendor using CreateSalesOrder API based on reservations
 * 
 * Usage: php cli/create-vendor-orders.php
 * 
 * Recommended cron schedule: Daily at 17:00 (5:00 PM)
 * 0 17 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/create-vendor-orders.php >> /var/log/teletrade-vendor-orders.log 2>&1
 */

// Set working directory
chdir(__DIR__ . '/..');

// Load dependencies
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Models/Order.php';
require_once __DIR__ . '/../app/Models/OrderItem.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Services/OrderService.php';
require_once __DIR__ . '/../app/Services/ReservationService.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';

// Initialize environment
Env::load();

// Increase execution time
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Logging function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    echo $logMessage;
    error_log($logMessage, 3, __DIR__ . '/../storage/logs/vendor-orders.log');
}

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

