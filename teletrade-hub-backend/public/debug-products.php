<?php
/**
 * Debug Products - Show actual database content
 * 
 * Usage: https://api.vs-mjrinfotech.com/debug-products.php?key=SECURE_KEY_12345
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

// Set headers
header('Content-Type: text/html; charset=utf-8');

// Initialize environment
Env::load();

try {
    $db = Database::getConnection();
    
    echo "<html><head><meta charset='utf-8'><title>Product Debug</title>";
    echo "<style>body{font-family:monospace;padding:20px;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#4CAF50;color:white;} tr:nth-child(even){background-color:#f2f2f2;} .section{margin:30px 0;} h2{color:#4CAF50;border-bottom:2px solid #4CAF50;padding-bottom:5px;}</style>";
    echo "</head><body>";
    
    echo "<h1>🔍 Product Database Debug</h1>";
    
    // Database Statistics
    echo "<div class='section'>";
    echo "<h2>📊 Database Statistics</h2>";
    $stats = [
        'products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'brands' => $db->query("SELECT COUNT(*) FROM brands")->fetchColumn(),
        'categories' => $db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
        'product_images' => $db->query("SELECT COUNT(*) FROM product_images")->fetchColumn(),
        'available_products' => $db->query("SELECT COUNT(*) FROM products WHERE is_available = 1")->fetchColumn(),
    ];
    echo "<table>";
    foreach ($stats as $label => $count) {
        echo "<tr><th>" . ucfirst(str_replace('_', ' ', $label)) . "</th><td>$count</td></tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // Sample Products
    echo "<div class='section'>";
    echo "<h2>📦 Sample Products (First 10)</h2>";
    $stmt = $db->query("SELECT id, vendor_article_id, sku, name, name_en, name_de, name_sk, brand_id, category_id, price, stock_quantity, is_available FROM products LIMIT 10");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "<p style='color:red;'>❌ No products found in database!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>SKU</th><th>Name</th><th>Name_EN</th><th>Brand ID</th><th>Category ID</th><th>Price</th><th>Stock</th><th>Available</th></tr>";
        foreach ($products as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['sku']}</td>";
            echo "<td>" . htmlspecialchars($p['name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($p['name_en'] ?? 'NULL') . "</td>";
            echo "<td>" . ($p['brand_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($p['category_id'] ?? 'NULL') . "</td>";
            echo "<td>{$p['price']}</td>";
            echo "<td>{$p['stock_quantity']}</td>";
            echo "<td>" . ($p['is_available'] ? '✅' : '❌') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Brands
    echo "<div class='section'>";
    echo "<h2>🏷️ Brands</h2>";
    $stmt = $db->query("SELECT id, vendor_id, name, slug FROM brands LIMIT 20");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($brands)) {
        echo "<p style='color:red;'>❌ No brands found!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Vendor ID</th><th>Name</th><th>Slug</th></tr>";
        foreach ($brands as $b) {
            echo "<tr>";
            echo "<td>{$b['id']}</td>";
            echo "<td>" . htmlspecialchars($b['vendor_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($b['name']) . "</td>";
            echo "<td>{$b['slug']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Categories
    echo "<div class='section'>";
    echo "<h2>📂 Categories</h2>";
    $stmt = $db->query("SELECT id, vendor_id, name, name_en, name_de, name_sk, slug FROM categories LIMIT 20");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categories)) {
        echo "<p style='color:orange;'>⚠️ No categories found! This is expected if vendor API doesn't provide categories.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Vendor ID</th><th>Name</th><th>Name_EN</th><th>Slug</th></tr>";
        foreach ($categories as $c) {
            echo "<tr>";
            echo "<td>{$c['id']}</td>";
            echo "<td>" . htmlspecialchars($c['vendor_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($c['name']) . "</td>";
            echo "<td>" . htmlspecialchars($c['name_en'] ?? 'NULL') . "</td>";
            echo "<td>{$c['slug']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Search Test
    echo "<div class='section'>";
    echo "<h2>🔎 Search Test</h2>";
    $searchTerms = ['iPhone', 'Samsung', 'phone', 'apple', 'mobile'];
    
    echo "<table>";
    echo "<tr><th>Search Term</th><th>Results Count</th><th>Sample Result</th></tr>";
    
    foreach ($searchTerms as $term) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE name LIKE :search OR sku LIKE :search OR ean LIKE :search");
        $stmt->execute([':search' => "%$term%"]);
        $count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT name FROM products WHERE name LIKE :search OR sku LIKE :search OR ean LIKE :search LIMIT 1");
        $stmt->execute([':search' => "%$term%"]);
        $sample = $stmt->fetchColumn();
        
        echo "<tr>";
        echo "<td><strong>$term</strong></td>";
        echo "<td>" . ($count > 0 ? "✅ $count" : "❌ 0") . "</td>";
        echo "<td>" . ($sample ? htmlspecialchars($sample) : "N/A") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // Product with NULL or empty names
    echo "<div class='section'>";
    echo "<h2>⚠️ Data Quality Check</h2>";
    
    $nullNames = $db->query("SELECT COUNT(*) FROM products WHERE name IS NULL OR name = ''")->fetchColumn();
    $nullCategories = $db->query("SELECT COUNT(*) FROM products WHERE category_id IS NULL")->fetchColumn();
    $nullBrands = $db->query("SELECT COUNT(*) FROM products WHERE brand_id IS NULL")->fetchColumn();
    $zeroPrice = $db->query("SELECT COUNT(*) FROM products WHERE price = 0 OR base_price = 0")->fetchColumn();
    
    echo "<table>";
    echo "<tr><th>Issue</th><th>Count</th><th>Status</th></tr>";
    echo "<tr><td>Products with NULL/Empty Name</td><td>$nullNames</td><td>" . ($nullNames > 0 ? '❌ ISSUE' : '✅ OK') . "</td></tr>";
    echo "<tr><td>Products without Category</td><td>$nullCategories</td><td>" . ($nullCategories > 0 ? '⚠️ WARNING' : '✅ OK') . "</td></tr>";
    echo "<tr><td>Products without Brand</td><td>$nullBrands</td><td>" . ($nullBrands > 0 ? '⚠️ WARNING' : '✅ OK') . "</td></tr>";
    echo "<tr><td>Products with Zero Price</td><td>$zeroPrice</td><td>" . ($zeroPrice > 0 ? '❌ ISSUE' : '✅ OK') . "</td></tr>";
    echo "</table>";
    echo "</div>";
    
    // Latest Sync Log
    echo "<div class='section'>";
    echo "<h2>📝 Latest Sync Log</h2>";
    $stmt = $db->query("SELECT * FROM vendor_sync_log ORDER BY started_at DESC LIMIT 1");
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($log) {
        echo "<table>";
        foreach ($log as $key => $value) {
            if ($key !== 'id') {
                echo "<tr><th>" . ucfirst(str_replace('_', ' ', $key)) . "</th><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p>No sync log found.</p>";
    }
    echo "</div>";
    
    echo "<hr><p style='text-align:center;color:#888;'>Generated: " . date('Y-m-d H:i:s') . "</p>";
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

