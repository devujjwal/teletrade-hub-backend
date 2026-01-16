<?php
/**
 * Debug script to identify missing parameters in product sync
 */

require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Models/Product.php';

// Get the expected parameters from the Product model's create method
$reflection = new ReflectionMethod('Product', 'create');
$filename = $reflection->getFileName();
$startLine = $reflection->getStartLine();
$endLine = $reflection->getEndLine();

// Read the file and extract the SQL
$file = file($filename);
$methodCode = implode('', array_slice($file, $startLine - 1, $endLine - $startLine + 1));

// Extract placeholders from INSERT statement
preg_match_all('/:(\w+)/', $methodCode, $matches);
$expectedParams = array_unique($matches[1]);

// Sort for easier comparison
sort($expectedParams);

echo "Expected parameters in Product::create() method:\n";
echo "Total: " . count($expectedParams) . "\n\n";
foreach ($expectedParams as $param) {
    echo "  :{$param}\n";
}

echo "\n\nTo fix the sync issue:\n";
echo "1. Run: cd /home2/vsmjr110/api && git pull origin main\n";
echo "2. Clear any PHP opcache if enabled\n";
echo "3. Try sync again\n";
