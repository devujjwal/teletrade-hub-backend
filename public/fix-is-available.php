<?php
/**
 * Fix is_available flag based on stock_quantity
 * SECURITY: Requires secure key from environment
 * This script ensures is_available is in sync with actual stock quantities
 * 
 * Usage: php fix-is-available.php (CLI) or ?key=YOUR_SYNC_KEY (web)
 */

// Load environment first
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';

Env::load();

// SECURITY: If accessed via web, require authentication
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    $expectedKey = Env::get('SYNC_KEY', '');
    if (empty($expectedKey) || !hash_equals($expectedKey, $key)) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized access']));
    }
}

try {
    $db = Database::getInstance();
    
    echo "Fixing is_available flags...\n\n";
    
    // Update products: set is_available = 1 where stock_quantity > 0
    $sql1 = "UPDATE products 
             SET is_available = 1 
             WHERE stock_quantity > 0 
             AND is_available = 0";
    $stmt1 = $db->prepare($sql1);
    $stmt1->execute();
    $updated1 = $stmt1->rowCount();
    echo "✓ Set is_available = 1 for {$updated1} products with stock\n";
    
    // Update products: set is_available = 0 where stock_quantity = 0
    $sql2 = "UPDATE products 
             SET is_available = 0 
             WHERE stock_quantity = 0 
             AND is_available = 1";
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute();
    $updated2 = $stmt2->rowCount();
    echo "✓ Set is_available = 0 for {$updated2} products without stock\n";
    
    echo "\nDone! Total products updated: " . ($updated1 + $updated2) . "\n";
    
    // Show sample of products with their status
    echo "\nSample of products (first 10):\n";
    $sql3 = "SELECT id, sku, name, stock_quantity, is_available 
             FROM products 
             ORDER BY id 
             LIMIT 10";
    $stmt3 = $db->query($sql3);
    $products = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nID\tSKU\t\tStock\tAvailable\tName\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($products as $p) {
        $nameShort = mb_substr($p['name'], 0, 30);
        echo "{$p['id']}\t{$p['sku']}\t{$p['stock_quantity']}\t{$p['is_available']}\t\t{$nameShort}...\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

