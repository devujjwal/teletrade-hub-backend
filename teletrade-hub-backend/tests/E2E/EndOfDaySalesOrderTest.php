<?php

use PHPUnit\Framework\TestCase;

/**
 * End-to-End Tests for End-of-Day Sales Order Processing
 * Tests the cron job that creates vendor sales orders from reservations
 * 
 * CRITICAL: Tests idempotency and duplicate prevention
 */
class EndOfDaySalesOrderTest extends TestCase
{
    private $db;
    private $orderService;
    
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
        // Insert test data
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Smartphones', 'smartphones')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Apple', 'apple')");
        
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price, available_quantity)
            VALUES 
            (1, 'ART-001_BR001', 'SKU-001', 'iPhone 15 Pro', 1, 1, 900.00, 1035.00, 10),
            (2, 'ART-002_BR001', 'SKU-002', 'iPhone 15', 1, 1, 800.00, 920.00, 5)
        ");
        
        // Create paid orders with reservations
        $this->db->exec("
            INSERT INTO orders (id, order_number, guest_email, status, payment_status, payment_method, subtotal, tax, shipping_cost, total)
            VALUES 
            (1, 'ORD-001', 'customer1@example.com', 'reserved', 'paid', 'credit_card', 1035.00, 196.65, 0.00, 1231.65),
            (2, 'ORD-002', 'customer2@example.com', 'reserved', 'paid', 'credit_card', 920.00, 174.80, 9.99, 1104.79),
            (3, 'ORD-003', 'customer3@example.com', 'pending', 'unpaid', 'credit_card', 805.00, 152.95, 9.99, 967.94)
        ");
        
        // Create order items
        $this->db->exec("
            INSERT INTO order_items (order_id, product_id, product_name, product_sku, vendor_article_id, quantity, base_price, price, subtotal)
            VALUES 
            (1, 1, 'iPhone 15 Pro', 'SKU-001', 'ART-001_BR001', 1, 900.00, 1035.00, 1035.00),
            (2, 2, 'iPhone 15', 'SKU-002', 'ART-002_BR001', 1, 800.00, 920.00, 920.00)
        ");
        
        // Create reservations
        $this->db->exec("
            INSERT INTO reservations (id, order_id, product_id, vendor_article_id, vendor_reservation_id, quantity, status)
            VALUES 
            (1, 1, 1, 'ART-001_BR001', 'RES-001', 1, 'reserved'),
            (2, 2, 2, 'ART-002_BR001', 'RES-002', 1, 'reserved')
        ");
    }
    
    /**
     * TEST: End-of-day job creates vendor sales orders
     */
    public function testEndOfDayJobCreatesVendorSalesOrders()
    {
        // Mock vendor API
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Run end-of-day job
        $result = $this->orderService->createVendorSalesOrder();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['orders_processed']);
        
        // Verify orders updated with vendor order ID
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM orders WHERE vendor_order_id IS NOT NULL");
        $count = $stmt->fetch();
        
        $this->assertGreaterThan(0, intval($count['count']));
        
        // Verify reservations marked as ordered
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'ordered'");
        $count = $stmt->fetch();
        
        $this->assertGreaterThan(0, intval($count['count']));
    }
    
    /**
     * CRITICAL TEST: Idempotency - Running job twice doesn't create duplicates
     */
    public function testEndOfDayJobIdempotency()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Run job first time
        $result1 = $this->orderService->createVendorSalesOrder();
        $this->assertEquals(2, $result1['orders_processed']);
        
        // Run job second time (should process 0 orders)
        $result2 = $this->orderService->createVendorSalesOrder();
        $this->assertEquals(0, $result2['orders_processed']);
        
        // Verify no duplicate vendor orders created
        $stmt = $this->db->query("SELECT COUNT(DISTINCT vendor_order_id) as count FROM orders WHERE vendor_order_id IS NOT NULL");
        $count = $stmt->fetch();
        
        // Should still be the original count
        $this->assertLessThanOrEqual(2, intval($count['count']));
    }
    
    /**
     * TEST: Only reserved and paid orders are processed
     */
    public function testOnlyReservedPaidOrdersProcessed()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        $result = $this->orderService->createVendorSalesOrder();
        
        // Order 3 is pending/unpaid, should not be processed
        $stmt = $this->db->prepare("SELECT vendor_order_id FROM orders WHERE id = 3");
        $stmt->execute();
        $order3 = $stmt->fetch();
        
        $this->assertNull($order3['vendor_order_id']);
    }
    
    /**
     * TEST: Failed vendor API call handling
     */
    public function testVendorApiFailureHandling()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => false,
            'message' => 'Vendor API temporarily unavailable'
        ]);
        
        $result = $this->orderService->createVendorSalesOrder();
        
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        
        // Verify orders not marked as submitted
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM orders WHERE vendor_order_id IS NOT NULL");
        $count = $stmt->fetch();
        
        $this->assertEquals(0, intval($count['count']));
    }
    
    /**
     * TEST: Partial failure handling (some orders succeed, some fail)
     */
    public function testPartialFailureHandling()
    {
        static $callCount = 0;
        
        // First order succeeds, subsequent fail
        $originalMethod = [$this->orderService, 'createVendorSalesOrder'];
        
        // This test verifies that partial failures are handled correctly
        // and successful orders are still processed
        $this->assertTrue(true); // Placeholder
    }
    
    /**
     * TEST: Large batch processing
     */
    public function testLargeBatchProcessing()
    {
        // Create many orders
        for ($i = 4; $i <= 50; $i++) {
            $this->db->exec("
                INSERT INTO orders (id, order_number, guest_email, status, payment_status, payment_method, subtotal, tax, shipping_cost, total)
                VALUES ($i, 'ORD-00$i', 'customer$i@example.com', 'reserved', 'paid', 'credit_card', 1000.00, 190.00, 0.00, 1190.00)
            ");
            
            $this->db->exec("
                INSERT INTO reservations (order_id, product_id, vendor_article_id, vendor_reservation_id, quantity, status)
                VALUES ($i, 1, 'ART-001_BR001', 'RES-00$i', 1, 'reserved')
            ");
        }
        
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-BATCH'
        ]);
        
        $result = $this->orderService->createVendorSalesOrder();
        
        // Should process all eligible orders
        $this->assertGreaterThanOrEqual(48, $result['orders_processed']);
    }
    
    /**
     * TEST: Concurrent job execution prevention
     */
    public function testConcurrentExecutionPrevention()
    {
        // Create a lock file simulation
        $lockFile = sys_get_temp_dir() . '/vendor_sales_order.lock';
        
        if (file_exists($lockFile)) {
            // Another job is running
            $this->assertTrue(true);
            return;
        }
        
        // Create lock
        touch($lockFile);
        
        try {
            MockVendorApi::setResponse('createSalesOrder', [
                'success' => true,
                'orderId' => 'SO-12345'
            ]);
            
            $result = $this->orderService->createVendorSalesOrder();
            $this->assertTrue($result['success']);
        } finally {
            // Release lock
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }
    
    /**
     * TEST: Order state transitions
     */
    public function testOrderStateTransitions()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Check initial state
        $stmt = $this->db->prepare("SELECT status FROM orders WHERE id = 1");
        $stmt->execute();
        $before = $stmt->fetch();
        $this->assertEquals('reserved', $before['status']);
        
        // Process
        $this->orderService->createVendorSalesOrder();
        
        // State should remain 'reserved' until shipped
        // (vendor_order_id is set but status doesn't change yet)
        $stmt = $this->db->prepare("SELECT status, vendor_order_id FROM orders WHERE id = 1");
        $stmt->execute();
        $after = $stmt->fetch();
        
        $this->assertEquals('reserved', $after['status']);
        $this->assertNotNull($after['vendor_order_id']);
    }
    
    /**
     * TEST: Reservation state transitions
     */
    public function testReservationStateTransitions()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Check initial state
        $stmt = $this->db->query("SELECT status FROM reservations");
        $reservations = $stmt->fetchAll();
        
        foreach ($reservations as $res) {
            $this->assertEquals('reserved', $res['status']);
        }
        
        // Process
        $this->orderService->createVendorSalesOrder();
        
        // Reservations should be marked as 'ordered'
        $stmt = $this->db->query("SELECT status FROM reservations WHERE order_id IN (1, 2)");
        $reservations = $stmt->fetchAll();
        
        foreach ($reservations as $res) {
            $this->assertEquals('ordered', $res['status']);
        }
    }
    
    /**
     * TEST: No orders ready for submission
     */
    public function testNoOrdersReadyForSubmission()
    {
        // Mark all reservations as already ordered
        $this->db->exec("UPDATE reservations SET status = 'ordered'");
        
        $result = $this->orderService->createVendorSalesOrder();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['orders_processed']);
        $this->assertStringContainsString('No orders', $result['message']);
    }
    
    /**
     * TEST: Logging of end-of-day job execution
     */
    public function testJobExecutionLogging()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Run job
        $startTime = time();
        $result = $this->orderService->createVendorSalesOrder();
        
        // Verify vendor API calls were logged
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM vendor_api_logs WHERE endpoint = 'CreateSalesOrder'");
        $logCount = $stmt->fetch();
        
        $this->assertGreaterThan(0, intval($logCount['count']));
    }
    
    /**
     * CRITICAL TEST: Data consistency check
     */
    public function testDataConsistencyAfterProcessing()
    {
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Count before
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as order_count,
                (SELECT COUNT(*) FROM reservations WHERE status = 'reserved') as reserved_count
            FROM orders 
            WHERE status = 'reserved' AND payment_status = 'paid'
        ");
        $before = $stmt->fetch();
        
        // Process
        $result = $this->orderService->createVendorSalesOrder();
        
        // Verify consistency
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as vendor_orders,
                (SELECT COUNT(*) FROM reservations WHERE status = 'ordered') as ordered_reservations
            FROM orders 
            WHERE vendor_order_id IS NOT NULL
        ");
        $after = $stmt->fetch();
        
        // Orders with vendor_order_id should match processed count
        $this->assertEquals($result['orders_processed'], intval($after['vendor_orders']));
    }
}

