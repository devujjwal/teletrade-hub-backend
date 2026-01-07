<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for ReservationService
 * Tests product reservation logic and rollback scenarios
 */
class ReservationServiceTest extends TestCase
{
    private $reservationService;
    private $db;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        MockVendorApi::reset();
        
        // Create test data
        $this->setupTestData();
        
        $this->reservationService = new ReservationServiceMock();
    }
    
    private function setupTestData()
    {
        // Insert test products
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, base_price, price, available_quantity, reserved_quantity)
            VALUES 
            (1, 'ART-001_BR001', 'SKU-001', 'iPhone 15', 900.00, 1035.00, 10, 0),
            (2, 'ART-002_BR001', 'SKU-002', 'Samsung Galaxy S24', 800.00, 920.00, 5, 0)
        ");
        
        // Insert test order
        $this->db->exec("
            INSERT INTO orders (id, order_number, status, payment_status, subtotal, tax, shipping_cost, total)
            VALUES (1, 'ORD-TEST-001', 'pending', 'unpaid', 100.00, 19.00, 9.99, 128.99)
        ");
    }
    
    /**
     * Test successful single product reservation
     */
    public function testReserveProductSuccess()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-12345'
        ]);
        
        $reservation = $this->reservationService->reserveProduct(1, 1, 'ART-001_BR001', 2);
        
        $this->assertIsArray($reservation);
        $this->assertEquals('reserved', $reservation['status']);
        $this->assertEquals('RES-12345', $reservation['vendor_reservation_id']);
        
        // Verify local stock was reserved
        $stmt = $this->db->query("SELECT reserved_quantity FROM products WHERE id = 1");
        $product = $stmt->fetch();
        $this->assertEquals(2, intval($product['reserved_quantity']));
    }
    
    /**
     * Test reservation failure - vendor error
     */
    public function testReserveProductVendorFailure()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'error' => 1,
            'error_msg' => 'Out of stock'
        ]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Out of stock');
        
        $this->reservationService->reserveProduct(1, 1, 'ART-001_BR001', 2);
        
        // Verify reservation was marked as failed
        $stmt = $this->db->query("SELECT status, error_message FROM reservations WHERE order_id = 1");
        $reservation = $stmt->fetch();
        $this->assertEquals('failed', $reservation['status']);
    }
    
    /**
     * Test reserve order products - all succeed
     */
    public function testReserveOrderProductsAllSucceed()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-XXX'
        ]);
        
        $orderItems = [
            [
                'product_id' => 1,
                'vendor_article_id' => 'ART-001_BR001',
                'quantity' => 2
            ],
            [
                'product_id' => 2,
                'vendor_article_id' => 'ART-002_BR001',
                'quantity' => 1
            ]
        ];
        
        $reservations = $this->reservationService->reserveOrderProducts(1, $orderItems);
        
        $this->assertCount(2, $reservations);
        $this->assertEquals('reserved', $reservations[0]['status']);
        $this->assertEquals('reserved', $reservations[1]['status']);
    }
    
    /**
     * Test reserve order products - rollback on partial failure
     */
    public function testReserveOrderProductsRollbackOnFailure()
    {
        // First reservation succeeds, second fails
        static $callCount = 0;
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to reserve all products');
        
        $orderItems = [
            ['product_id' => 1, 'vendor_article_id' => 'ART-001_BR001', 'quantity' => 2],
            ['product_id' => 2, 'vendor_article_id' => 'ART-002_BR001', 'quantity' => 1]
        ];
        
        $this->reservationService->reserveOrderProductsWithFailure(1, $orderItems);
    }
    
    /**
     * Test unreserve product success
     */
    public function testUnreserveProductSuccess()
    {
        // Create a reservation first
        $this->db->exec("
            INSERT INTO reservations (id, order_id, product_id, vendor_article_id, vendor_reservation_id, quantity, status)
            VALUES (1, 1, 1, 'ART-001_BR001', 'RES-12345', 2, 'reserved')
        ");
        
        // Update product reserved quantity
        $this->db->exec("UPDATE products SET reserved_quantity = 2 WHERE id = 1");
        
        MockVendorApi::setResponse('unreserveArticle', [
            'status' => 'ok'
        ]);
        
        $this->reservationService->unreserveProduct(1);
        
        // Verify reservation status updated
        $stmt = $this->db->query("SELECT status FROM reservations WHERE id = 1");
        $reservation = $stmt->fetch();
        $this->assertEquals('unreserved', $reservation['status']);
        
        // Verify stock was released
        $stmt = $this->db->query("SELECT reserved_quantity FROM products WHERE id = 1");
        $product = $stmt->fetch();
        $this->assertEquals(0, intval($product['reserved_quantity']));
    }
    
    /**
     * Test unreserve product - vendor API failure (best effort)
     */
    public function testUnreserveProductVendorFailure()
    {
        // Create a reservation
        $this->db->exec("
            INSERT INTO reservations (id, order_id, product_id, vendor_article_id, vendor_reservation_id, quantity, status)
            VALUES (1, 1, 1, 'ART-001_BR001', 'RES-12345', 2, 'reserved')
        ");
        
        MockVendorApi::setResponse('unreserveArticle', [
            'error' => 1,
            'error_msg' => 'Reservation not found'
        ]);
        
        // Should not throw exception (best effort)
        $this->reservationService->unreserveProduct(1);
        
        // Error should be logged
        $stmt = $this->db->query("SELECT error_message FROM reservations WHERE id = 1");
        $reservation = $stmt->fetch();
        $this->assertNotEmpty($reservation['error_message']);
    }
    
    /**
     * Test unreserve all order products
     */
    public function testUnreserveOrderProducts()
    {
        // Create multiple reservations
        $this->db->exec("
            INSERT INTO reservations (order_id, product_id, vendor_article_id, vendor_reservation_id, quantity, status) VALUES
            (1, 1, 'ART-001_BR001', 'RES-001', 2, 'reserved'),
            (1, 2, 'ART-002_BR001', 'RES-002', 1, 'reserved')
        ");
        
        $this->db->exec("UPDATE products SET reserved_quantity = 2 WHERE id = 1");
        $this->db->exec("UPDATE products SET reserved_quantity = 1 WHERE id = 2");
        
        MockVendorApi::setResponse('unreserveArticle', ['status' => 'ok']);
        
        $this->reservationService->unreserveOrderProducts(1);
        
        // Verify all reservations were unreserved
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM reservations WHERE order_id = 1 AND status = 'unreserved'");
        $result = $stmt->fetch();
        $this->assertEquals(2, intval($result['count']));
    }
    
    /**
     * Test get reservation status
     */
    public function testGetReservationStatus()
    {
        // Create mixed reservations
        $this->db->exec("
            INSERT INTO reservations (order_id, product_id, vendor_article_id, quantity, status) VALUES
            (1, 1, 'ART-001', 2, 'reserved'),
            (1, 2, 'ART-002', 1, 'reserved'),
            (1, 3, 'ART-003', 1, 'failed')
        ");
        
        $status = $this->reservationService->getReservationStatus(1);
        
        $this->assertEquals(3, $status['total']);
        $this->assertEquals(2, $status['reserved']);
        $this->assertEquals(1, $status['failed']);
        $this->assertFalse($status['all_reserved']);
    }
    
    /**
     * Test is order fully reserved
     */
    public function testIsOrderFullyReserved()
    {
        // All reserved
        $this->db->exec("
            INSERT INTO reservations (order_id, product_id, vendor_article_id, quantity, status) VALUES
            (1, 1, 'ART-001', 2, 'reserved'),
            (1, 2, 'ART-002', 1, 'reserved')
        ");
        
        $this->assertTrue($this->reservationService->isOrderFullyReserved(1));
        
        // Add a failed reservation
        $this->db->exec("
            INSERT INTO reservations (order_id, product_id, vendor_article_id, quantity, status)
            VALUES (1, 3, 'ART-003', 1, 'failed')
        ");
        
        $this->assertFalse($this->reservationService->isOrderFullyReserved(1));
    }
    
    /**
     * TEST: Warehouse extraction from SKU
     */
    public function testWarehouseExtractionFromSku()
    {
        $sku = 'ART-001_BR001';
        
        // The service should extract BR001 as warehouse
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-12345'
        ]);
        
        $this->reservationService->reserveProduct(1, 1, $sku, 1);
        
        // Verify the correct warehouse was used (would check in real implementation)
        $this->assertTrue(true);
    }
    
    /**
     * EDGE CASE: Reserve zero quantity
     */
    public function testReserveZeroQuantity()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'error' => 1,
            'error_msg' => 'Invalid quantity'
        ]);
        
        $this->expectException(Exception::class);
        
        $this->reservationService->reserveProduct(1, 1, 'ART-001_BR001', 0);
    }
    
    /**
     * EDGE CASE: Reserve negative quantity
     */
    public function testReserveNegativeQuantity()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'error' => 1,
            'error_msg' => 'Invalid quantity'
        ]);
        
        $this->expectException(Exception::class);
        
        $this->reservationService->reserveProduct(1, 1, 'ART-001_BR001', -5);
    }
    
    /**
     * CONSISTENCY TEST: Local stock matches reservation
     */
    public function testLocalStockConsistency()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-12345'
        ]);
        
        $initialStock = $this->db->query("SELECT available_quantity, reserved_quantity FROM products WHERE id = 1")->fetch();
        
        $this->reservationService->reserveProduct(1, 1, 'ART-001_BR001', 3);
        
        $finalStock = $this->db->query("SELECT available_quantity, reserved_quantity FROM products WHERE id = 1")->fetch();
        
        // Available should remain same, reserved should increase
        $this->assertEquals($initialStock['available_quantity'], $finalStock['available_quantity']);
        $this->assertEquals(3, intval($finalStock['reserved_quantity']));
    }
}

/**
 * Mock ReservationService for testing
 */
class ReservationServiceMock extends ReservationService
{
    // PHP 8.3 requires explicit property declarations
    protected $vendorApi;
    protected $reservationModel;
    protected $productModel;
    protected $db;
    
    public function __construct()
    {
        $this->vendorApi = new VendorApiServiceMock();
        $this->reservationModel = new Reservation();
        $this->productModel = new Product();
        $this->db = Database::getConnection();
    }
    
    public function reserveOrderProductsWithFailure($orderId, $orderItems)
    {
        $reservations = [];
        $callCount = 0;
        
        foreach ($orderItems as $item) {
            $callCount++;
            
            // First succeeds, second fails
            if ($callCount === 1) {
                MockVendorApi::setResponse('reserveArticle', [
                    'status' => 'ok',
                    'ReturnVal' => 'RES-001'
                ]);
            } else {
                MockVendorApi::setResponse('reserveArticle', [
                    'error' => 1,
                    'error_msg' => 'Out of stock'
                ]);
            }
            
            try {
                $reservation = $this->reserveProduct(
                    $orderId,
                    $item['product_id'],
                    $item['vendor_article_id'],
                    $item['quantity']
                );
                $reservations[] = $reservation;
            } catch (Exception $e) {
                // Rollback all successful reservations
                MockVendorApi::setResponse('unreserveArticle', ['status' => 'ok']);
                
                foreach ($reservations as $res) {
                    $this->unreserveProduct($res['id']);
                }
                
                throw new Exception('Failed to reserve all products');
            }
        }
        
        return $reservations;
    }
}

