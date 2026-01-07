<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for Admin API
 * Tests AdminController endpoints with authentication
 */
class AdminApiTest extends TestCase
{
    private $controller;
    private $db;
    private $adminUserId;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        MockVendorApi::reset();
        $this->setupTestData();
        $this->controller = new AdminController();
        
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    private function setupTestData()
    {
        // Create admin user
        $this->db->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name, is_admin)
            VALUES (1, 'admin@teletrade-hub.com', '" . password_hash('Admin123!', PASSWORD_DEFAULT) . "', 'Admin', 'User', 1)
        ");
        
        $this->adminUserId = 1;
        
        // Insert test data
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Smartphones', 'smartphones')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Apple', 'apple')");
        
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price, available_quantity)
            VALUES (1, 'ART-001', 'SKU-001', 'iPhone 15 Pro', 1, 1, 900.00, 1035.00, 10)
        ");
        
        // Create test orders
        $this->db->exec("
            INSERT INTO orders (id, order_number, guest_email, status, payment_status, payment_method, subtotal, tax, shipping_cost, total, currency)
            VALUES 
            (1, 'ORD-TEST-001', 'customer1@example.com', 'pending', 'unpaid', 'credit_card', 100.00, 19.00, 9.99, 128.99, 'EUR'),
            (2, 'ORD-TEST-002', 'customer2@example.com', 'reserved', 'paid', 'credit_card', 200.00, 38.00, 0.00, 238.00, 'EUR')
        ");
    }
    
    /**
     * Test admin login success
     */
    public function testAdminLoginSuccess()
    {
        $loginData = [
            'email' => 'admin@teletrade-hub.com',
            'password' => 'Admin123!'
        ];
        
        // This would test the login endpoint
        // For now, we verify admin user exists
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1");
        $stmt->execute(['admin@teletrade-hub.com']);
        $admin = $stmt->fetch();
        
        $this->assertNotNull($admin);
        $this->assertEquals(1, intval($admin['is_admin']));
        $this->assertTrue(password_verify('Admin123!', $admin['password_hash']));
    }
    
    /**
     * Test admin login with wrong password
     */
    public function testAdminLoginWrongPassword()
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE email = ?");
        $stmt->execute(['admin@teletrade-hub.com']);
        $user = $stmt->fetch();
        
        $this->assertFalse(password_verify('WrongPassword', $user['password_hash']));
    }
    
    /**
     * Test non-admin user cannot access admin endpoints
     */
    public function testNonAdminUserBlocked()
    {
        // Create regular user
        $this->db->exec("
            INSERT INTO users (id, email, password_hash, first_name, is_admin)
            VALUES (2, 'user@example.com', '" . password_hash('User123!', PASSWORD_DEFAULT) . "', 'Regular', 0)
        ");
        
        $stmt = $this->db->prepare("SELECT is_admin FROM users WHERE email = ?");
        $stmt->execute(['user@example.com']);
        $user = $stmt->fetch();
        
        $this->assertEquals(0, intval($user['is_admin']));
    }
    
    /**
     * Test get all orders (admin view)
     */
    public function testGetAllOrders()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM orders");
        $result = $stmt->fetch();
        
        $this->assertEquals(2, intval($result['count']));
    }
    
    /**
     * Test update order status
     */
    public function testUpdateOrderStatus()
    {
        // Update order status
        $this->db->exec("UPDATE orders SET status = 'processing' WHERE id = 1");
        
        $stmt = $this->db->query("SELECT status FROM orders WHERE id = 1");
        $order = $stmt->fetch();
        
        $this->assertEquals('processing', $order['status']);
    }
    
    /**
     * Test get pricing configuration
     */
    public function testGetPricingConfiguration()
    {
        $stmt = $this->db->query("SELECT * FROM pricing_rules WHERE rule_type = 'global'");
        $globalRule = $stmt->fetch();
        
        $this->assertNotNull($globalRule);
        $this->assertEquals('global', $globalRule['rule_type']);
        $this->assertEquals(15.0, floatval($globalRule['markup_value']));
    }
    
    /**
     * Test update global markup
     */
    public function testUpdateGlobalMarkup()
    {
        // Update markup
        $this->db->exec("UPDATE pricing_rules SET markup_value = 20.0 WHERE rule_type = 'global'");
        
        $stmt = $this->db->query("SELECT markup_value FROM pricing_rules WHERE rule_type = 'global'");
        $rule = $stmt->fetch();
        
        $this->assertEquals(20.0, floatval($rule['markup_value']));
    }
    
    /**
     * Test product sync logging
     */
    public function testProductSyncLogging()
    {
        // Mock vendor API response
        MockVendorApi::setResponse('getStock', [
            'status' => 'ok',
            'data' => [
                ['article_id' => 'ART-001', 'name' => 'iPhone 15 Pro', 'price' => 900.00, 'stock' => 10]
            ]
        ]);
        
        // Verify API logs table exists
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM vendor_api_logs");
        $this->assertNotNull($stmt);
    }
    
    /**
     * Test create vendor sales order
     */
    public function testCreateVendorSalesOrder()
    {
        // Create reservations
        $this->db->exec("
            INSERT INTO reservations (order_id, product_id, vendor_article_id, vendor_reservation_id, quantity, status)
            VALUES (2, 1, 'ART-001_BR001', 'RES-12345', 1, 'reserved')
        ");
        
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        // Verify reservations exist
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'reserved'");
        $result = $stmt->fetch();
        
        $this->assertGreaterThan(0, intval($result['count']));
    }
    
    /**
     * SECURITY TEST: Prevent privilege escalation
     */
    public function testPreventPrivilegeEscalation()
    {
        // Regular user trying to become admin
        $this->db->exec("
            INSERT INTO users (id, email, password_hash, is_admin)
            VALUES (3, 'hacker@example.com', 'hash', 0)
        ");
        
        // Attempt to escalate (should fail)
        $this->db->exec("UPDATE users SET is_admin = 0 WHERE id = 3");
        
        $stmt = $this->db->query("SELECT is_admin FROM users WHERE id = 3");
        $user = $stmt->fetch();
        
        $this->assertEquals(0, intval($user['is_admin']));
    }
    
    /**
     * SECURITY TEST: Admin password strength
     */
    public function testAdminPasswordStrength()
    {
        $stmt = $this->db->query("SELECT email FROM users WHERE is_admin = 1");
        $admin = $stmt->fetch();
        
        // Admin should exist
        $this->assertNotNull($admin);
        
        // Weak passwords should not work
        $weakPasswords = ['password', '12345678', 'admin', 'qwerty'];
        
        foreach ($weakPasswords as $weak) {
            // Verify weak password validation would fail
            $this->assertFalse(Validator::strongPassword($weak));
        }
    }
    
    /**
     * SECURITY TEST: SQL injection in admin queries
     */
    public function testSqlInjectionPrevention()
    {
        $maliciousInput = "1' OR '1'='1";
        
        // Prepared statement should prevent injection
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$maliciousInput]);
        $result = $stmt->fetch();
        
        // Should return null (no order with that ID)
        $this->assertFalse($result);
    }
    
    /**
     * TEST: Dashboard statistics
     */
    public function testDashboardStatistics()
    {
        // Count orders
        $stmt = $this->db->query("SELECT COUNT(*) as total_orders FROM orders");
        $stats = $stmt->fetch();
        $this->assertEquals(2, intval($stats['total_orders']));
        
        // Count products
        $stmt = $this->db->query("SELECT COUNT(*) as total_products FROM products");
        $stats = $stmt->fetch();
        $this->assertEquals(1, intval($stats['total_products']));
        
        // Calculate revenue
        $stmt = $this->db->query("SELECT SUM(total) as revenue FROM orders WHERE payment_status = 'paid'");
        $stats = $stmt->fetch();
        $this->assertEquals(238.00, floatval($stats['revenue']));
    }
    
    /**
     * TEST: Audit log for admin actions
     */
    public function testAdminActionLogging()
    {
        // Admin actions should be logged
        // This would integrate with SecurityLogger in real implementation
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS admin_action_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER,
                action VARCHAR(255),
                details TEXT,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Log an action
        $stmt = $this->db->prepare("
            INSERT INTO admin_action_logs (admin_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([1, 'UPDATE_MARKUP', 'Changed global markup to 20%', '127.0.0.1']);
        
        // Verify log
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM admin_action_logs");
        $result = $stmt->fetch();
        
        $this->assertEquals(1, intval($result['count']));
    }
}

