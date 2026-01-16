<?php
/**
 * Debug order creation to identify the exact error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Utils/Response.php';
require_once __DIR__ . '/../app/Utils/Validator.php';
require_once __DIR__ . '/../app/Utils/Sanitizer.php';
require_once __DIR__ . '/../app/Models/Order.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Services/OrderService.php';

header('Content-Type: text/plain');

echo "=== ORDER CREATION DEBUG ===\n\n";

try {
    // Test data
    $cartItems = [
        ['product_id' => 3, 'quantity' => 1]
    ];
    
    $billingAddress = [
        ':user_id' => 1,
        ':first_name' => 'Ujjwal',
        ':last_name' => 'Paul',
        ':company' => '',
        ':address_line1' => 'E-3/320 Sector-H',
        ':address_line2' => 'LDA Colony',
        ':city' => 'Berlin',
        ':state' => 'Berlin',
        ':postal_code' => '70224',
        ':country' => 'Germany',
        ':phone' => '09582801721',
        ':is_default' => 0
    ];
    
    $orderData = [
        'user_id' => 1,
        'payment_method' => 'bank_transfer',
        'notes' => 'Test order'
    ];
    
    echo "1. Testing database connection...\n";
    $db = Database::getConnection();
    echo "   ✅ Database connected\n\n";
    
    echo "2. Checking if product exists...\n";
    $productModel = new Product();
    $product = $productModel->getById(3);
    if ($product) {
        echo "   ✅ Product found: {$product['name']}\n";
        echo "   SKU: {$product['sku']}\n";
        echo "   Price: €{$product['price']}\n\n";
    } else {
        echo "   ❌ Product ID 3 not found!\n";
        echo "   Available products:\n";
        $stmt = $db->query("SELECT id, sku, name FROM products LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "     - ID {$row['id']}: {$row['sku']} - {$row['name']}\n";
        }
        exit;
    }
    
    echo "3. Creating test order...\n";
    $orderService = new OrderService();
    
    $result = $orderService->createOrder(
        $orderData,
        $cartItems,
        $billingAddress,
        null // Same billing and shipping
    );
    
    echo "   ✅ ORDER CREATED SUCCESSFULLY!\n\n";
    echo "Order Details:\n";
    echo "  - Order Number: {$result['order_number']}\n";
    echo "  - Total: €{$result['total']}\n";
    echo "  - Status: {$result['status']}\n";
    echo "  - Payment Status: {$result['payment_status']}\n\n";
    
    echo "✅ ALL TESTS PASSED!\n";
    echo "Order creation is working correctly.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
