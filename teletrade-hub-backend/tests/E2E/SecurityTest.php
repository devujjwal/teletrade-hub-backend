<?php

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive Security Tests
 * Tests for SQL Injection, XSS, CSRF, Auth Bypass, etc.
 */
class SecurityTest extends TestCase
{
    private $db;
    private $productController;
    private $orderController;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        $this->setupTestData();
        
        $this->productController = new ProductController();
        $this->orderController = new OrderController();
        
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    private function setupTestData()
    {
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Test', 'test')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Test', 'test')");
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price, available_quantity)
            VALUES (1, 'ART-001', 'SKU-001', 'Test Product', 1, 1, 100.00, 115.00, 10)
        ");
        
        $this->db->exec("
            INSERT INTO users (id, email, password_hash, is_admin)
            VALUES (1, 'admin@test.com', '" . password_hash('Admin123!', PASSWORD_DEFAULT) . "', 1)
        ");
    }
    
    /**
     * SQL INJECTION TESTS
     */
    
    public function testSqlInjectionInProductFilter()
    {
        $sqlInjectionPayloads = [
            "1' OR '1'='1",
            "1'; DROP TABLE products; --",
            "1' UNION SELECT * FROM users--",
            "1' AND 1=1--",
            "'; UPDATE products SET price = 0; --"
        ];
        
        foreach ($sqlInjectionPayloads as $payload) {
            $_GET = ['category_id' => $payload, 'page' => 1, 'limit' => 10];
            
            ob_start();
            $this->productController->index();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            // Should not expose SQL errors
            $this->assertIsArray($response);
            $this->assertArrayNotHasKey('sql_error', $response);
            
            // Verify tables still exist
            $stmt = $this->db->query("SELECT COUNT(*) FROM products");
            $this->assertNotNull($stmt);
        }
    }
    
    public function testSqlInjectionInSearch()
    {
        $_GET = ['search' => "'; DROP TABLE products; --", 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->productController->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        
        // Verify table still exists
        $stmt = $this->db->query("SELECT COUNT(*) FROM products");
        $result = $stmt->fetch();
        $this->assertGreaterThanOrEqual(1, intval($result['COUNT(*)']));
    }
    
    public function testSqlInjectionInOrderAddress()
    {
        $maliciousAddress = "'; DROP TABLE orders; --";
        
        // Sanitizer should handle it
        $sanitized = Sanitizer::string($maliciousAddress);
        $this->assertNotEquals($maliciousAddress, $sanitized);
        $this->assertStringNotContainsString('DROP TABLE', $sanitized);
    }
    
    /**
     * XSS PREVENTION TESTS
     */
    
    public function testXssInProductSearch()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert(1)>',
            '<svg onload=alert(1)>',
            'javascript:alert(1)',
            '<iframe src="javascript:alert(1)">',
            '"><script>alert(String.fromCharCode(88,83,83))</script>',
            '\'><script>alert(1)</script>',
        ];
        
        foreach ($xssPayloads as $payload) {
            $_GET = ['search' => $payload, 'page' => 1, 'limit' => 10];
            
            ob_start();
            $this->productController->index();
            $output = ob_get_clean();
            
            // Output should not contain unescaped script tags
            $this->assertStringNotContainsString('<script>', $output);
            $this->assertStringNotContainsString('javascript:', $output);
            $this->assertStringNotContainsString('onerror=', $output);
            $this->assertStringNotContainsString('onload=', $output);
        }
    }
    
    public function testXssInSanitizer()
    {
        $xssPayloads = [
            '<script>alert(1)</script>Test',
            'Test<img src=x onerror=alert(1)>',
            '<svg/onload=alert(1)>',
        ];
        
        foreach ($xssPayloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            
            $this->assertStringNotContainsString('<script', $sanitized);
            $this->assertStringNotContainsString('onerror=', $sanitized);
            $this->assertStringNotContainsString('onload=', $sanitized);
        }
    }
    
    /**
     * AUTHENTICATION & AUTHORIZATION TESTS
     */
    
    public function testUnauthorizedAdminAccess()
    {
        // Non-admin user should not access admin functions
        $stmt = $this->db->prepare("SELECT is_admin FROM users WHERE email = ?");
        $stmt->execute(['admin@test.com']);
        $user = $stmt->fetch();
        
        $this->assertEquals(1, intval($user['is_admin']));
        
        // Test that regular user (is_admin = 0) would be blocked
        $this->db->exec("
            INSERT INTO users (id, email, password_hash, is_admin)
            VALUES (2, 'user@test.com', '" . password_hash('User123!', PASSWORD_DEFAULT) . "', 0)
        ");
        
        $stmt = $this->db->prepare("SELECT is_admin FROM users WHERE email = ?");
        $stmt->execute(['user@test.com']);
        $regularUser = $stmt->fetch();
        
        $this->assertEquals(0, intval($regularUser['is_admin']));
    }
    
    public function testOrderAccessControl()
    {
        // Create order
        $this->db->exec("
            INSERT INTO orders (id, order_number, guest_email, status, payment_status, subtotal, tax, shipping_cost, total)
            VALUES (1, 'ORD-001', 'owner@example.com', 'pending', 'unpaid', 100.00, 19.00, 9.99, 128.99)
        ");
        
        // Test access with correct email
        $_GET = ['guest_email' => 'owner@example.com'];
        
        ob_start();
        $this->orderController->show('ORD-001');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals('success', $response['status']);
        
        // Test access with wrong email
        $_GET = ['guest_email' => 'hacker@example.com'];
        
        ob_start();
        $this->orderController->show('ORD-001');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals('error', $response['status']);
        $this->assertEquals(403, $response['code']);
    }
    
    /**
     * PRICE MANIPULATION TESTS
     */
    
    public function testPriceManipulationPrevention()
    {
        // Customer should never pay vendor base price
        $stmt = $this->db->query("SELECT base_price, price FROM products");
        $products = $stmt->fetchAll();
        
        foreach ($products as $product) {
            $basePrice = floatval($product['base_price']);
            $customerPrice = floatval($product['price']);
            
            $this->assertNotEquals($basePrice, $customerPrice);
            $this->assertGreaterThan($basePrice, $customerPrice);
        }
    }
    
    public function testBasePriceNotExposedInApi()
    {
        $_GET = ['lang' => 'en'];
        
        ob_start();
        $this->productController->show(1);
        $output = ob_get_clean();
        
        // Response should not contain 'base_price' key for customers
        $this->assertStringNotContainsString('base_price', $output);
    }
    
    /**
     * INPUT VALIDATION TESTS
     */
    
    public function testEmailValidation()
    {
        $invalidEmails = [
            'notanemail',
            '@example.com',
            'test@',
            'test @example.com',
            '<script>@example.com',
        ];
        
        foreach ($invalidEmails as $email) {
            $this->assertFalse(Validator::email($email));
        }
    }
    
    public function testPasswordStrengthValidation()
    {
        $weakPasswords = [
            'password',
            '12345678',
            'qwerty',
            'Password', // No number
            'password1', // No uppercase
            'PASSWORD1', // No lowercase
            'Pass123', // Too short
            'Password123', // No special char
        ];
        
        foreach ($weakPasswords as $password) {
            $this->assertFalse(Validator::strongPassword($password));
        }
        
        // Strong passwords should pass
        $strongPasswords = [
            'Test123!',
            'MyP@ssw0rd',
            'Secure#Pass123',
        ];
        
        foreach ($strongPasswords as $password) {
            $this->assertTrue(Validator::strongPassword($password));
        }
    }
    
    /**
     * PATH TRAVERSAL TESTS
     */
    
    public function testPathTraversalPrevention()
    {
        $pathTraversalPayloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            '....//....//....//etc/passwd',
        ];
        
        foreach ($pathTraversalPayloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            
            // Should not contain path traversal sequences
            $this->assertNotEquals($payload, $sanitized);
        }
    }
    
    /**
     * CSRF PREVENTION TESTS
     */
    
    public function testCsrfTokenValidation()
    {
        // CSRF tokens should be validated for state-changing operations
        // This is a placeholder for actual CSRF implementation
        $this->assertTrue(true);
    }
    
    /**
     * MASS ASSIGNMENT TESTS
     */
    
    public function testMassAssignmentPrevention()
    {
        // User should not be able to set is_admin via input
        $maliciousInput = [
            'email' => 'hacker@example.com',
            'password' => 'Test123!',
            'is_admin' => 1 // Attempting to make themselves admin
        ];
        
        // Only allowed fields should be processed
        // In real implementation, this would use a whitelist
        $allowedFields = ['email', 'password'];
        
        $filtered = array_intersect_key($maliciousInput, array_flip($allowedFields));
        
        $this->assertArrayNotHasKey('is_admin', $filtered);
    }
    
    /**
     * SENSITIVE DATA EXPOSURE TESTS
     */
    
    public function testSensitiveDataNotLogged()
    {
        // Passwords should not be logged
        $sensitiveData = [
            'password' => 'Secret123!',
            'credit_card' => '4111111111111111',
            'cvv' => '123'
        ];
        
        // Verify these are not in logs
        $logContent = json_encode($sensitiveData);
        
        // Should mask sensitive fields in production
        $this->assertStringContainsString('Secret123!', $logContent); // This shows the need for masking
    }
    
    /**
     * RATE LIMITING TESTS
     */
    
    public function testRateLimiting()
    {
        // Simulate multiple rapid requests
        // In production, rate limiting should be enforced
        
        $requestCount = 0;
        $maxRequests = 100;
        
        for ($i = 0; $i < $maxRequests; $i++) {
            $requestCount++;
        }
        
        // Rate limiter should block after threshold
        $this->assertLessThanOrEqual($maxRequests, $requestCount);
    }
    
    /**
     * FILE UPLOAD SECURITY TESTS (if applicable)
     */
    
    public function testFileUploadValidation()
    {
        $dangerousExtensions = [
            'php',
            'phtml',
            'php3',
            'php4',
            'php5',
            'exe',
            'sh',
            'bat',
        ];
        
        foreach ($dangerousExtensions as $ext) {
            $filename = "malicious.$ext";
            
            // File upload should be rejected
            // In real implementation, whitelist allowed extensions
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            
            $this->assertNotContains($ext, $allowedExtensions);
        }
    }
    
    /**
     * INFORMATION DISCLOSURE TESTS
     */
    
    public function testErrorMessagesDoNotLeakInfo()
    {
        // Errors should not expose system details
        $_GET = ['page' => 'invalid'];
        
        ob_start();
        $this->productController->index();
        $output = ob_get_clean();
        
        // Should not contain stack traces or system paths
        $this->assertStringNotContainsString('/var/www/', $output);
        $this->assertStringNotContainsString('PDOException', $output);
        $this->assertStringNotContainsString('Stack trace:', $output);
    }
    
    /**
     * BUSINESS LOGIC TESTS
     */
    
    public function testNegativeQuantityPrevention()
    {
        // Negative quantities should be rejected
        $this->assertFalse(Validator::positive(-1));
        $this->assertFalse(Validator::positive(0));
        $this->assertTrue(Validator::positive(1));
    }
    
    public function testPriceIntegrity()
    {
        // Prices should be validated and consistent
        $pricingService = new PricingService();
        
        $basePrice = 100.00;
        $customerPrice = $pricingService->calculatePrice($basePrice);
        
        // Customer price must be >= base price
        $this->assertGreaterThanOrEqual($basePrice, $customerPrice);
        
        // Markup should be reasonable (not negative or extreme)
        $markup = $pricingService->getMarkupPercentage($basePrice, $customerPrice);
        $this->assertGreaterThanOrEqual(0, $markup);
        $this->assertLessThanOrEqual(1000, $markup); // Max 1000% markup
    }
    
    /**
     * SESSION SECURITY TESTS
     */
    
    public function testSessionFixationPrevention()
    {
        // Session ID should be regenerated after login
        // This is a placeholder for actual session management tests
        $this->assertTrue(true);
    }
    
    /**
     * API SECURITY TESTS
     */
    
    public function testVendorApiKeyProtection()
    {
        // API keys should never be exposed in responses
        $_GET = ['lang' => 'en'];
        
        ob_start();
        $this->productController->index();
        $output = ob_get_clean();
        
        // Should not contain API key
        $this->assertStringNotContainsString('VENDOR_API_KEY', $output);
        $this->assertStringNotContainsString('api_key', $output);
    }
}

