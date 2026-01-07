<?php
/**
 * Diagnostic Script - Check TRIEL API Response Structure
 * This will show us the actual format of the API response
 * 
 * Usage: https://api.vs-mjrinfotech.com/check-api-response.php?key=SECURE_KEY_12345
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
require_once __DIR__ . '/../app/Services/VendorApiService.php';

// Set headers
header('Content-Type: application/json');

// Initialize environment
Env::load();

try {
    echo "<h1>TRIEL API Response Structure</h1>";
    echo "<h2>Testing getStock API call...</h2>";
    
    $vendorApi = new VendorApiService();
    $stockData = $vendorApi->getStock('en');
    
    echo "<h3>Full Response Structure:</h3>";
    echo "<pre>";
    print_r($stockData);
    echo "</pre>";
    
    echo "<hr>";
    echo "<h3>Response Keys:</h3>";
    echo "<pre>";
    if (is_array($stockData)) {
        echo "Top-level keys: " . implode(', ', array_keys($stockData)) . "\n\n";
        
        // Check for products/stock/items array
        if (isset($stockData['stock'])) {
            echo "Found 'stock' key\n";
            if (is_array($stockData['stock']) && !empty($stockData['stock'])) {
                echo "First product in stock array:\n";
                print_r($stockData['stock'][0]);
                echo "\nFirst product keys: " . implode(', ', array_keys($stockData['stock'][0])) . "\n";
            }
        } elseif (isset($stockData['products'])) {
            echo "Found 'products' key\n";
            if (is_array($stockData['products']) && !empty($stockData['products'])) {
                echo "First product:\n";
                print_r($stockData['products'][0]);
                echo "\nFirst product keys: " . implode(', ', array_keys($stockData['products'][0])) . "\n";
            }
        } elseif (isset($stockData['items'])) {
            echo "Found 'items' key\n";
            if (is_array($stockData['items']) && !empty($stockData['items'])) {
                echo "First item:\n";
                print_r($stockData['items'][0]);
                echo "\nFirst item keys: " . implode(', ', array_keys($stockData['items'][0])) . "\n";
            }
        } else {
            echo "No 'stock', 'products', or 'items' key found. Checking if response IS the array:\n";
            if (isset($stockData[0])) {
                echo "First element:\n";
                print_r($stockData[0]);
                if (is_array($stockData[0])) {
                    echo "\nFirst element keys: " . implode(', ', array_keys($stockData[0])) . "\n";
                }
            }
        }
    }
    echo "</pre>";
    
    echo "<hr>";
    echo "<h3>Check Database - Current Products:</h3>";
    $db = Database::getConnection();
    $stmt = $db->query("SELECT id, vendor_article_id, sku, name, brand_id, category_id, price, stock_quantity FROM products LIMIT 5");
    $products = $stmt->fetchAll();
    echo "<pre>";
    print_r($products);
    echo "</pre>";
    
    echo "<hr>";
    echo "<h3>Check Database - Brands:</h3>";
    $stmt = $db->query("SELECT * FROM brands LIMIT 10");
    $brands = $stmt->fetchAll();
    echo "<pre>";
    print_r($brands);
    echo "</pre>";
    
    echo "<hr>";
    echo "<h3>Check Database - Categories:</h3>";
    $stmt = $db->query("SELECT * FROM categories LIMIT 10");
    $categories = $stmt->fetchAll();
    echo "<pre>";
    print_r($categories);
    echo "</pre>";
    
    echo "<hr>";
    echo "<h3>Check Latest Vendor API Log:</h3>";
    $stmt = $db->query("SELECT * FROM vendor_api_logs WHERE endpoint = 'GetStock' ORDER BY created_at DESC LIMIT 1");
    $log = $stmt->fetch();
    if ($log) {
        echo "<pre>";
        echo "Endpoint: " . $log['endpoint'] . "\n";
        echo "Status Code: " . $log['status_code'] . "\n";
        echo "Duration: " . $log['duration_ms'] . "ms\n";
        echo "Error: " . ($log['error_message'] ?? 'None') . "\n\n";
        
        if ($log['response_payload']) {
            $response = json_decode($log['response_payload'], true);
            echo "Response Keys: " . implode(', ', array_keys($response)) . "\n";
            echo "\nFull Response:\n";
            print_r($response);
        }
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>";
    echo "<pre>";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
    echo "</pre>";
}

