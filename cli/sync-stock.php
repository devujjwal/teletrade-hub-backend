#!/usr/bin/env php
<?php
/**
 * Daily Stock Sync Cron Job
 * Syncs vendor stock using GetCurrentStock (getStock) API
 * 
 * Usage: php cli/sync-stock.php
 * 
 * Recommended cron schedule: Daily at 3:00 AM
 * 0 3 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/sync-stock.php >> /var/log/teletrade-sync.log 2>&1
 */

// Set working directory
chdir(__DIR__ . '/..');

// Load dependencies
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Language.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Models/Category.php';
require_once __DIR__ . '/../app/Models/Brand.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/ProductSyncService.php';

// Initialize environment
Env::load();

// Increase execution time and memory for multi-language sync
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

// Logging function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    echo $logMessage;
    error_log($logMessage, 3, __DIR__ . '/../storage/logs/sync-stock.log');
}

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

