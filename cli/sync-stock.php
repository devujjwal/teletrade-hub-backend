#!/usr/bin/env php
<?php
/**
 * Daily Stock Sync Cron Job
 * Syncs vendor stock using GetCurrentStock (getStock) API
 * 
 * Usage: php cli/sync-stock.php
 * 
 * Recommended cron schedule: Daily at 3:00 AM
 * 0 3 * * * /usr/local/bin/ea-php83 /path/to/project/cli/sync-stock.php >> /path/to/project/storage/logs/cron-sync.log 2>&1
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log
ini_set('log_errors', 1);

// Determine base path - works from both cli/ and public/cli/
$basePath = __DIR__;
if (basename($basePath) === 'cli') {
    // Check if we're in public/cli/ or cli/ at root
    $parentDir = dirname($basePath);
    if (basename($parentDir) === 'public') {
        // We're in public/cli/ - go up two levels to root
        $basePath = dirname($parentDir);
    } else {
        // We're in cli/ at root level - go up one level
        $basePath = $parentDir;
    }
} else {
    // Unexpected location - try going up two levels
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
    
    $logFile = $logDir . '/sync-stock.log';
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
    require_once $appPath . '/Utils/Language.php';
    require_once $appPath . '/Models/Product.php';
    require_once $appPath . '/Models/Category.php';
    require_once $appPath . '/Models/Brand.php';
    require_once $appPath . '/Services/VendorApiService.php';
    require_once $appPath . '/Services/PricingService.php';
    require_once $appPath . '/Services/ProductSyncService.php';
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

// Increase execution time and memory for multi-language sync
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

try {
    logMessage("Starting daily stock sync...");
    
    $syncService = new ProductSyncService();
    
    // Sync all supported languages
    // Language IDs: 1=English, 3=German, 4=French, 5=Spanish, 6=Russian, 
    // 7=Italian, 8=Turkish, 9=Romanian, 10=Slovakian, 11=Polish
    $languageIds = [1, 3, 4, 5, 6, 7, 8, 9, 10, 11];
    
    logMessage("Syncing products for " . count($languageIds) . " languages...");
    
    $startTime = microtime(true);
    $result = $syncService->syncProducts($languageIds);
    $duration = round(microtime(true) - $startTime, 2);
    
    logMessage("Sync completed in {$duration} seconds");
    logMessage("Products synced: {$result['synced']}");
    logMessage("Products added: {$result['added']}");
    logMessage("Products updated: {$result['updated']}");
    logMessage("Products disabled: {$result['disabled']}");
    
    if (!empty($result['languages'])) {
        foreach ($result['languages'] as $langCode => $langStats) {
            if (isset($langStats['error'])) {
                logMessage("Language {$langCode} error: {$langStats['error']}", 'ERROR');
            } else {
                $translated = $langStats['translated'] ?? $langStats['synced'] ?? 0;
                logMessage("Language {$langCode}: {$translated} items processed");
            }
        }
    }
    
    logMessage("Daily stock sync completed successfully");
    exit(0);
    
} catch (Exception $e) {
    logMessage("Sync failed: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}
