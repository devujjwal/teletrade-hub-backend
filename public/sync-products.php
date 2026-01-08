<?php
/**
 * Product Sync Script
 * SECURITY: Requires secure key from environment
 * 
 * Usage: https://api.vs-mjrinfotech.com/sync-products.php?key=YOUR_SYNC_KEY
 */

// Load environment first
require_once __DIR__ . '/../app/Config/env.php';
Env::load();

// SECURITY: Require secure sync key from environment
$key = $_GET['key'] ?? '';
$expectedKey = Env::get('SYNC_KEY', '');
if (empty($expectedKey) || !hash_equals($expectedKey, $key)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access']));
}

// Load dependencies
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Response.php';
require_once __DIR__ . '/../app/Utils/Validator.php';
require_once __DIR__ . '/../app/Utils/Sanitizer.php';
require_once __DIR__ . '/../app/Utils/Language.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Models/Category.php';
require_once __DIR__ . '/../app/Models/Brand.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/ProductSyncService.php';

// Set headers
header('Content-Type: text/html; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

// Environment already loaded above

// Increase execution time and memory for multi-language sync
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

// Enable output buffering with minimal buffer size for real-time output
@ob_end_flush();
@ob_implicit_flush(true);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Product Sync - Multi-Language</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        .warning { color: #dcdcaa; }
        pre { background: #2d2d2d; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h1>Multi-Language Product Sync</h1>
";
flush();

try {
    $syncService = new ProductSyncService();
    
    // Check if specific languages requested
    $languageIds = null;
    if (isset($_GET['languages'])) {
        // Parse comma-separated language IDs: ?languages=1,3,4
        $languageIds = array_map('intval', explode(',', $_GET['languages']));
        echo "<p class='info'>Syncing specific languages: " . implode(', ', $languageIds) . "</p>";
    } else {
        echo "<p class='info'>Syncing all supported languages...</p>";
    }
    
    echo "<pre>";
    flush();
    
    $startTime = microtime(true);
    $result = $syncService->syncProducts($languageIds);
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "</pre>";
    
    echo "<h2 class='success'>✓ Sync Completed Successfully</h2>";
    echo "<p>Duration: {$duration} seconds</p>";
    
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li class='success'>Products Synced: {$result['synced']}</li>";
    echo "<li class='success'>Products Added: {$result['added']}</li>";
    echo "<li class='success'>Products Updated: {$result['updated']}</li>";
    echo "<li class='warning'>Products Disabled: {$result['disabled']}</li>";
    echo "</ul>";
    
    if (!empty($result['languages'])) {
        echo "<h3>Language Details:</h3>";
        echo "<ul>";
        foreach ($result['languages'] as $langCode => $langStats) {
            if (isset($langStats['error'])) {
                echo "<li class='error'>{$langCode}: ERROR - {$langStats['error']}</li>";
            } else {
                $translated = $langStats['translated'] ?? $langStats['synced'] ?? 0;
                echo "<li class='success'>{$langCode}: {$translated} items processed</li>";
            }
        }
        echo "</ul>";
    }
    
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>✗ Sync Failed</h2>";
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre class='error'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";


