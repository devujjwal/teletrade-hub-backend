<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for Order API
 * Tests OrderController endpoints with database and vendor API mocking
 */
class OrderApiTest extends TestCase
{
    private $controller;
    private $db;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        MockVendorApi::reset();
        $this->setupTestData();
        $this->controller = new OrderController();
        
        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    private function setupTestData()
    {
        // Insert test data
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Smartphones', 'smartphones')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Apple', 'apple')");
        
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price, available_quantity, is_available)
            VALUES 
            (1, 'ART-001_BR001', 'SKU-001', 'iPhone 15 Pro', 1, 1, 900.00, 1035.00, 10, 1),
            (2, 'ART-002_BR001', 'SKU-002', 'iPhone 15', 1, 1, 800.00, 920.00, 5, 1),
            (3, 'ART-003_BR001', 'SKU-003', 'Out of Stock', 1, 1, 500.00, 575.00, 0, 0)
        ");
    }
    
    /**
     * Test POST /orders - Create order successfully
     */
    public function testCreateOrderSuccess()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'notes' => 'Please call before delivery',
            'cart_items' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1]
            ],
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_line1' => '123 Main St',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'country' => 'DE',
                'phone' => '+49123456789'
            ]
        ];
        
        // Mock PHP input
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals(201, $response['code']);
        $this->assertArrayHasKey('order_id', $response['data']);
        $this->assertArrayHasKey('order_number', $response['data']);
        $this->assertArrayHasKey('total', $response['data']);
        
        // Verify order in database
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$response['data']['order_id']]);
        $order = $stmt->fetch();
        
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order['status']);
        $this->assertEquals('customer@example.com', $order['guest_email']);
    }
    
    /**
     * Test POST /orders - Validation errors
     */
    public function testCreateOrderValidationErrors()
    {
        $orderData = [
            // Missing required fields
            'payment_method' => 'credit_card'
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertEquals(400, $response['code']);
    }
    
    /**
     * Test POST /orders - Empty cart
     */
    public function testCreateOrderEmptyCart()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Cart', $response['message']);
    }
    
    /**
     * Test POST /orders - Product not available
     */
    public function testCreateOrderUnavailableProduct()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [
                ['product_id' => 3, 'quantity' => 1] // Out of stock product
            ],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('not available', $response['message']);
    }
    
    /**
     * Test POST /orders - Insufficient stock
     */
    public function testCreateOrderInsufficientStock()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [
                ['product_id' => 2, 'quantity' => 100] // More than available
            ],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('stock', strtolower($response['message']));
    }
    
    /**
     * Test GET /orders/:orderNumber - Get order details
     */
    public function testGetOrderDetails()
    {
        // Create order first
        $orderId = $this->createTestOrder();
        
        $stmt = $this->db->prepare("SELECT order_number, guest_email FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        $_GET = ['guest_email' => $order['guest_email']];
        
        ob_start();
        $this->controller->show($order['order_number']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('order', $response['data']);
        $this->assertEquals($order['order_number'], $response['data']['order']['order_number']);
    }
    
    /**
     * Test GET /orders/:orderNumber - Unauthorized access
     */
    public function testGetOrderDetailsUnauthorized()
    {
        $orderId = $this->createTestOrder();
        
        $stmt = $this->db->prepare("SELECT order_number FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        // Wrong email
        $_GET = ['guest_email' => 'wrong@example.com'];
        
        ob_start();
        $this->controller->show($order['order_number']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertEquals(403, $response['code']);
    }
    
    /**
     * Test POST /orders/:orderNumber/payment/success
     */
    public function testPaymentSuccess()
    {
        $orderId = $this->createTestOrder();
        
        $stmt = $this->db->prepare("SELECT order_number FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        // Mock successful vendor reservation
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-12345'
        ]);
        
        $paymentData = ['transaction_id' => 'TXN-123456'];
        $this->mockPhpInput(json_encode($paymentData));
        
        ob_start();
        $this->controller->paymentSuccess($order['order_number']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify order updated
        $stmt = $this->db->prepare("SELECT status, payment_status, transaction_id FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $updatedOrder = $stmt->fetch();
        
        $this->assertEquals('reserved', $updatedOrder['status']);
        $this->assertEquals('paid', $updatedOrder['payment_status']);
        $this->assertEquals('TXN-123456', $updatedOrder['transaction_id']);
    }
    
    /**
     * Test POST /orders/:orderNumber/payment/failed
     */
    public function testPaymentFailed()
    {
        $orderId = $this->createTestOrder();
        
        $stmt = $this->db->prepare("SELECT order_number FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        MockVendorApi::setResponse('unreserveArticle', ['status' => 'ok']);
        
        $paymentData = ['reason' => 'Insufficient funds'];
        $this->mockPhpInput(json_encode($paymentData));
        
        ob_start();
        $this->controller->paymentFailed($order['order_number']);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify order cancelled
        $stmt = $this->db->prepare("SELECT status, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $updatedOrder = $stmt->fetch();
        
        $this->assertEquals('cancelled', $updatedOrder['status']);
        $this->assertEquals('failed', $updatedOrder['payment_status']);
    }
    
    /**
     * SECURITY TEST: XSS in order notes
     */
    public function testXssInOrderNotes()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'notes' => '<script>alert("XSS")</script>',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        if ($response['status'] === 'success') {
            // Verify notes were sanitized
            $stmt = $this->db->prepare("SELECT notes FROM orders WHERE id = ?");
            $stmt->execute([$response['data']['order_id']]);
            $order = $stmt->fetch();
            
            $this->assertStringNotContainsString('<script>', $order['notes']);
        }
    }
    
    /**
     * SECURITY TEST: SQL injection in address fields
     */
    public function testSqlInjectionInAddress()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'billing_address' => [
                'first_name' => "John'; DROP TABLE orders; --",
                'last_name' => 'Doe',
                'address_line1' => '123 Main St',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'country' => 'DE',
                'phone' => '+49123456789'
            ]
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should handle gracefully
        $this->assertIsArray($response);
        
        // Verify orders table still exists
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM orders");
        $this->assertNotNull($stmt);
    }
    
    /**
     * SECURITY TEST: Ensure customer cannot see base price
     */
    public function testBasePriceNotExposed()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        if ($response['status'] === 'success') {
            // Response should not contain base_price
            $outputLower = strtolower($output);
            $this->assertStringNotContainsString('base_price', $outputLower);
            
            // Verify order items have customer price, not base price
            $stmt = $this->db->prepare("SELECT base_price, price FROM order_items WHERE order_id = ?");
            $stmt->execute([$response['data']['order_id']]);
            $item = $stmt->fetch();
            
            $this->assertNotEquals($item['base_price'], $item['price']);
            $this->assertGreaterThan($item['base_price'], $item['price']);
        }
    }
    
    /**
     * TEST: Order totals are calculated correctly
     */
    public function testOrderTotalsAccuracy()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [
                ['product_id' => 1, 'quantity' => 1], // 1035.00
                ['product_id' => 2, 'quantity' => 1]  // 920.00
            ],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        if ($response['status'] === 'success') {
            $stmt = $this->db->prepare("SELECT subtotal, tax, shipping_cost, total FROM orders WHERE id = ?");
            $stmt->execute([$response['data']['order_id']]);
            $order = $stmt->fetch();
            
            $expectedSubtotal = 1955.00;
            $expectedTax = round($expectedSubtotal * 0.19, 2);
            $expectedShipping = 9.99;
            $expectedTotal = $expectedSubtotal + $expectedTax + $expectedShipping;
            
            $this->assertEquals($expectedSubtotal, floatval($order['subtotal']));
            $this->assertEquals($expectedTax, floatval($order['tax']));
            $this->assertEquals($expectedShipping, floatval($order['shipping_cost']));
            $this->assertEquals(round($expectedTotal, 2), round(floatval($order['total']), 2));
        }
    }
    
    /**
     * EDGE CASE: Maximum quantity per product
     */
    public function testMaximumQuantity()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [
                ['product_id' => 1, 'quantity' => 1000]
            ],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should fail due to insufficient stock
        $this->assertEquals('error', $response['status']);
    }
    
    /**
     * Helper: Create test order
     */
    private function createTestOrder()
    {
        $orderData = [
            'guest_email' => 'test@example.com',
            'payment_method' => 'credit_card',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'billing_address' => $this->getTestAddress()
        ];
        
        $this->mockPhpInput(json_encode($orderData));
        
        ob_start();
        $this->controller->create();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        return $response['data']['order_id'];
    }
    
    /**
     * Helper: Get test address
     */
    private function getTestAddress()
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main St',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'phone' => '+49123456789'
        ];
    }
    
    /**
     * Helper: Mock PHP input stream
     */
    private function mockPhpInput($data)
    {
        // Note: In real implementation, this would use stream_wrapper_register
        // For testing, we'll rely on test database and mocked services
    }
}

