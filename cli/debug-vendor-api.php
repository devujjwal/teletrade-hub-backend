#!/usr/bin/env php
<?php
/**
 * Debug Vendor API Response
 * Checks what the vendor API actually returns
 */

// Determine base path
$basePath = __DIR__;
if (basename($basePath) === 'cli') {
    $parentDir = dirname($basePath);
    if (basename($parentDir) === 'public') {
        $basePath = dirname($parentDir);
    } else {
        $basePath = $parentDir;
    }
} else {
    $basePath = dirname(dirname($basePath));
}

chdir($basePath);

require_once $basePath . '/app/Config/env.php';
require_once $basePath . '/app/Config/database.php';
require_once $basePath . '/app/Services/VendorApiService.php';

Env::load();

echo "=== Vendor API Debug Tool ===\n\n";
echo "Base Path: {$basePath}\n";
echo "API Base URL: " . Env::get('VENDOR_API_BASE_URL', 'https://b2b.triel.sk/api') . "\n";
echo "API Key: " . (Env::get('VENDOR_API_KEY') ? substr(Env::get('VENDOR_API_KEY'), 0, 20) . '...' : 'NOT SET') . "\n\n";

try {
    $vendorApi = new VendorApiService();
    
    echo "Testing getStock() for English (lang_id=1)...\n";
    echo str_repeat("=", 60) . "\n";
    
    $response = $vendorApi->getStock(1);
    
    echo "Response Type: " . gettype($response) . "\n";
    echo "Response Keys: " . (is_array($response) ? implode(', ', array_keys($response)) : 'N/A') . "\n";
    echo "Response Size: " . strlen(json_encode($response)) . " bytes\n\n";
    
    if (isset($response['stock'])) {
        echo "✓ 'stock' key exists\n";
        echo "Stock array count: " . (is_array($response['stock']) ? count($response['stock']) : 'N/A') . "\n";
        
        if (is_array($response['stock']) && count($response['stock']) > 0) {
            echo "\nFirst product sample:\n";
            echo json_encode($response['stock'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "⚠ Stock array is empty!\n";
        }
    } else {
        echo "✗ 'stock' key NOT found in response\n";
        echo "\nFull response structure:\n";
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    // Check vendor_api_logs
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Checking vendor_api_logs table...\n";
    
    $db = Database::getConnection();
    $stmt = $db->query("SELECT id, endpoint, method, response_code, created_at, 
                        SUBSTRING(response_body, 1, 500) as response_preview
                        FROM vendor_api_logs 
                        WHERE endpoint LIKE '%getStock%' 
                        ORDER BY created_at DESC 
                        LIMIT 5");
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "No API logs found\n";
    } else {
        echo "Recent API calls:\n";
        foreach ($logs as $log) {
            echo "  ID: {$log['id']}, Code: {$log['response_code']}, Time: {$log['created_at']}\n";
            if ($log['response_preview']) {
                $preview = json_decode($log['response_preview'], true);
                if ($preview && isset($preview['stock'])) {
                    echo "    Stock count: " . (is_array($preview['stock']) ? count($preview['stock']) : 'N/A') . "\n";
                } else {
                    echo "    Response preview: " . substr($log['response_preview'], 0, 200) . "...\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Debug complete.\n";

