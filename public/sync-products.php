<?php
/**
 * Temporary Product Sync Script
 * Workaround for ModSecurity blocking POST requests
 * 
 * Usage: https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345
 * 
 * DELETE THIS FILE AFTER FIRST SYNC!
 */

// Simple password protection
$key = $_GET['key'] ?? '';
if ($key !== 'SECURE_KEY_12345') {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access']));
}

// Load dependencies
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Response.php';
require_once __DIR__ . '/../app/Utils/Validator.php';
require_once __DIR__ . '/../app/Utils/Sanitizer.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Models/Category.php';
require_once __DIR__ . '/../app/Models/Brand.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/ProductSyncService.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Initialize environment
Env::load();

try {
    echo "Starting product sync...\n";
    flush();
    
    $syncService = new ProductSyncService();
    $lang = $_GET['lang'] ?? 'en';
    
    $result = $syncService->syncProducts($lang);
    
    echo json_encode([
        'success' => true,
        'message' => 'Product sync completed successfully',
        'data' => $result
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

