<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for OrderService
 * Tests order creation, payment processing, and totals calculation
 */
class OrderServiceTest extends TestCase
{
    private $orderService;
    private $db;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        MockVendorApi::reset();
        
        $this->setupTestData();
        $this->orderService = new OrderService();
    }
    
    private function setupTestData()
    {
        // Insert test categories and brands
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Smartphones', 'smartphones')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Apple', 'apple')");
        
        // Insert test products
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price, available_quantity, is_available)
            VALUES 
            (1, 'ART-001_BR001', 'SKU-001', 'iPhone 15 Pro', 1, 1, 900.00, 1035.00, 10, 1),
            (2, 'ART-002_BR001', 'SKU-002', 'iPhone 15', 1, 1, 800.00, 920.00, 5, 1),
            (3, 'ART-003_BR001', 'SKU-003', 'Out of Stock', 1, 1, 500.00, 575.00, 0, 0)
        ");
    }
    
    /**
     * Test successful order creation
     */
    public function testCreateOrderSuccess()
    {
        $orderData = [
            'guest_email' => 'customer@example.com',
            'payment_method' => 'credit_card',
            'notes' => 'Please deliver after 5 PM'
        ];
        
        $cartItems = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 1]
        ];
        
        $billingAddress = [
            ':user_id' => null,
            ':first_name' => 'John',
            ':last_name' => 'Doe',
            ':company' => '',
            ':address_line1' => '123 Main St',
            ':address_line2' => '',
            ':city' => 'Berlin',
            ':state' => '',
            ':postal_code' => '10115',
            ':country' => 'DE',
            ':phone' => '+49123456789',
            ':is_default' => 0
        ];
        
        $result = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('order_number', $result);
        $this->assertArrayHasKey('total', $result);
        
        // Verify order was created in database
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$result['order_id']]);
        $order = $stmt->fetch();
        
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order['status']);
        $this->assertEquals('unpaid', $order['payment_status']);
    }
    
    /**
     * Test order creation with unavailable product
     */
    public function testCreateOrderUnavailableProduct()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 3, 'quantity' => 1]]; // Out of stock product
        $billingAddress = $this->getTestAddress();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not available');
        
        $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
    }
    
    /**
     * Test order creation with insufficient stock
     */
    public function testCreateOrderInsufficientStock()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 2, 'quantity' => 100]]; // More than available
        $billingAddress = $this->getTestAddress();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient stock');
        
        $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
    }
    
    /**
     * Test order totals calculation
     */
    public function testOrderTotalsCalculation()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        
        // Product 1: 1035.00 * 1 = 1035.00
        // Product 2: 920.00 * 1 = 920.00
        // Subtotal: 1955.00
        // Tax (19%): 371.45
        // Shipping: 9.99 (total < 100, so shipping applies)
        // Total: 2336.44
        
        $cartItems = [
            ['product_id' => 1, 'quantity' => 1],
            ['product_id' => 2, 'quantity' => 1]
        ];
        
        $billingAddress = $this->getTestAddress();
        
        $result = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        
        // Verify totals
        $stmt = $this->db->prepare("SELECT subtotal, tax, shipping_cost, total FROM orders WHERE id = ?");
        $stmt->execute([$result['order_id']]);
        $order = $stmt->fetch();
        
        $this->assertEquals(1955.00, floatval($order['subtotal']));
        $this->assertEquals(371.45, floatval($order['tax']));
        $this->assertEquals(9.99, floatval($order['shipping_cost']));
        $this->assertEquals(2336.44, floatval($order['total']));
    }
    
    /**
     * Test free shipping threshold
     */
    public function testFreeShippingThreshold()
    {
        // Update free shipping threshold
        $this->db->exec("UPDATE settings SET value = '50' WHERE key = 'free_shipping_threshold'");
        
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 1, 'quantity' => 1]]; // Total will be > 50
        $billingAddress = $this->getTestAddress();
        
        $result = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        
        $stmt = $this->db->prepare("SELECT shipping_cost FROM orders WHERE id = ?");
        $stmt->execute([$result['order_id']]);
        $order = $stmt->fetch();
        
        // Shipping should be 0 (free)
        $this->assertEquals(0.00, floatval($order['shipping_cost']));
    }
    
    /**
     * Test payment success processing
     */
    public function testProcessPaymentSuccess()
    {
        // Create order first
        $orderId = $this->createTestOrder();
        
        // Mock successful reservation
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-12345'
        ]);
        
        $result = $this->orderService->processPaymentSuccess($orderId, 'TXN-123456');
        
        $this->assertTrue($result['success']);
        
        // Verify order status updated
        $stmt = $this->db->prepare("SELECT status, payment_status, transaction_id FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        $this->assertEquals('reserved', $order['status']);
        $this->assertEquals('paid', $order['payment_status']);
        $this->assertEquals('TXN-123456', $order['transaction_id']);
    }
    
    /**
     * Test payment success but reservation fails
     */
    public function testProcessPaymentSuccessReservationFails()
    {
        $orderId = $this->createTestOrder();
        
        // Mock failed reservation
        MockVendorApi::setResponse('reserveArticle', [
            'error' => 1,
            'error_msg' => 'Out of stock'
        ]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('reservation failed');
        
        $this->orderService->processPaymentSuccess($orderId, 'TXN-123456');
        
        // Verify order status
        $stmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        $this->assertEquals('payment_pending', $order['status']);
    }
    
    /**
     * Test payment failure processing
     */
    public function testProcessPaymentFailure()
    {
        $orderId = $this->createTestOrder();
        
        MockVendorApi::setResponse('unreserveArticle', ['status' => 'ok']);
        
        $this->orderService->processPaymentFailure($orderId, 'Insufficient funds');
        
        // Verify order status
        $stmt = $this->db->prepare("SELECT status, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        $this->assertEquals('cancelled', $order['status']);
        $this->assertEquals('failed', $order['payment_status']);
    }
    
    /**
     * Test order cancellation
     */
    public function testCancelOrder()
    {
        $orderId = $this->createTestOrder();
        
        MockVendorApi::setResponse('unreserveArticle', ['status' => 'ok']);
        
        $this->orderService->cancelOrder($orderId, 'Customer requested cancellation');
        
        // Verify status
        $stmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        $this->assertEquals('cancelled', $order['status']);
    }
    
    /**
     * Test cannot cancel shipped order
     */
    public function testCannotCancelShippedOrder()
    {
        $orderId = $this->createTestOrder();
        
        // Update to shipped status
        $this->db->exec("UPDATE orders SET status = 'shipped' WHERE id = $orderId");
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('cannot be cancelled');
        
        $this->orderService->cancelOrder($orderId);
    }
    
    /**
     * Test order items are created correctly
     */
    public function testOrderItemsCreated()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 1]
        ];
        $billingAddress = $this->getTestAddress();
        
        $result = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        
        // Verify order items
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
        $stmt->execute([$result['order_id']]);
        $count = $stmt->fetch();
        
        $this->assertEquals(2, intval($count['count']));
        
        // Verify item details
        $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
        $stmt->execute([$result['order_id']]);
        $items = $stmt->fetchAll();
        
        $this->assertEquals(1, intval($items[0]['product_id']));
        $this->assertEquals(2, intval($items[0]['quantity']));
        $this->assertEquals(1035.00, floatval($items[0]['price']));
        $this->assertEquals(2070.00, floatval($items[0]['subtotal']));
    }
    
    /**
     * Test duplicate order number prevention
     */
    public function testOrderNumberUniqueness()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 1, 'quantity' => 1]];
        $billingAddress = $this->getTestAddress();
        
        $result1 = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        $result2 = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        
        $this->assertNotEquals($result1['order_number'], $result2['order_number']);
    }
    
    /**
     * SECURITY TEST: Base price not exposed to customer
     */
    public function testBasePriceNotExposed()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 1, 'quantity' => 1]];
        $billingAddress = $this->getTestAddress();
        
        $result = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        
        // Verify customer pays marked-up price, not base price
        $stmt = $this->db->prepare("SELECT base_price, price FROM order_items WHERE order_id = ?");
        $stmt->execute([$result['order_id']]);
        $item = $stmt->fetch();
        
        $this->assertNotEquals($item['base_price'], $item['price']);
        $this->assertGreaterThan($item['base_price'], $item['price']);
    }
    
    /**
     * EDGE CASE: Empty cart
     */
    public function testEmptyCart()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [];
        $billingAddress = $this->getTestAddress();
        
        $this->expectException(Exception::class);
        
        $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
    }
    
    /**
     * EDGE CASE: Negative quantity
     */
    public function testNegativeQuantity()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 1, 'quantity' => -1]];
        $billingAddress = $this->getTestAddress();
        
        $this->expectException(Exception::class);
        
        $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
    }
    
    /**
     * Helper: Create test order
     */
    private function createTestOrder()
    {
        $orderData = ['guest_email' => 'test@example.com', 'payment_method' => 'credit_card'];
        $cartItems = [['product_id' => 1, 'quantity' => 1]];
        $billingAddress = $this->getTestAddress();
        
        $result = $this->orderService->createOrder($orderData, $cartItems, $billingAddress, null);
        return $result['order_id'];
    }
    
    /**
     * Helper: Get test address
     */
    private function getTestAddress()
    {
        return [
            ':user_id' => null,
            ':first_name' => 'John',
            ':last_name' => 'Doe',
            ':company' => '',
            ':address_line1' => '123 Main St',
            ':address_line2' => '',
            ':city' => 'Berlin',
            ':state' => '',
            ':postal_code' => '10115',
            ':country' => 'DE',
            ':phone' => '+49123456789',
            ':is_default' => 0
        ];
    }
}

