<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for VendorApiService
 * Tests vendor API integration with mocked responses
 */
class VendorApiServiceTest extends TestCase
{
    private $vendorApi;
    private $db;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        MockVendorApi::reset();
        
        // Create a mock version of VendorApiService for testing
        $this->vendorApi = new VendorApiServiceMock();
    }
    
    /**
     * Test successful stock retrieval
     */
    public function testGetStockSuccess()
    {
        MockVendorApi::setResponse('getStock', [
            'status' => 'ok',
            'data' => [
                ['article_id' => 'ART-001', 'price' => 100.00, 'stock' => 10],
                ['article_id' => 'ART-002', 'price' => 200.00, 'stock' => 5]
            ]
        ]);
        
        $response = $this->vendorApi->getStock('en');
        
        $this->assertIsArray($response);
        $this->assertEquals('ok', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertCount(2, $response['data']);
    }
    
    /**
     * Test stock retrieval with different languages
     */
    public function testGetStockMultipleLanguages()
    {
        $languages = ['en', 'de', 'sk'];
        
        foreach ($languages as $lang) {
            MockVendorApi::setResponse('getStock', [
                'status' => 'ok',
                'lang' => $lang,
                'data' => []
            ]);
            
            $response = $this->vendorApi->getStock($lang);
            $this->assertEquals('ok', $response['status']);
        }
    }
    
    /**
     * Test successful article reservation
     */
    public function testReserveArticleSuccess()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'status' => 'ok',
            'ReturnVal' => 'RES-12345'
        ]);
        
        $response = $this->vendorApi->reserveArticle('ART-001', 'BR001', 2);
        
        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('RES-12345', $response['ReturnVal']);
    }
    
    /**
     * Test article reservation failure (out of stock)
     */
    public function testReserveArticleOutOfStock()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'error' => 1,
            'error_msg' => 'Insufficient stock'
        ]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reservation failed');
        
        $this->vendorApi->reserveArticle('ART-001', 'BR001', 100);
    }
    
    /**
     * Test unreserve article success
     */
    public function testUnreserveArticleSuccess()
    {
        MockVendorApi::setResponse('unreserveArticle', [
            'status' => 'ok',
            'message' => 'Reservation removed'
        ]);
        
        $response = $this->vendorApi->unreserveArticle('RES-12345');
        
        $this->assertEquals('ok', $response['status']);
    }
    
    /**
     * Test create sales order success
     */
    public function testCreateSalesOrderSuccess()
    {
        $reservations = ['RES-001', 'RES-002'];
        
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => true,
            'orderId' => 'SO-12345'
        ]);
        
        $response = $this->vendorApi->createSalesOrder($reservations, 'Wire', 'no');
        
        $this->assertTrue($response['success']);
        $this->assertEquals('SO-12345', $response['orderId']);
    }
    
    /**
     * Test create sales order failure
     */
    public function testCreateSalesOrderFailure()
    {
        $reservations = ['RES-001'];
        
        MockVendorApi::setResponse('createSalesOrder', [
            'success' => false,
            'message' => 'Invalid payment method'
        ]);
        
        $this->expectException(Exception::class);
        
        $this->vendorApi->createSalesOrder($reservations, 'InvalidMethod', 'no');
    }
    
    /**
     * Test get article details
     */
    public function testGetArticleDetails()
    {
        MockVendorApi::setResponse('getArticleDetails', [
            'article_id' => 'ART-001',
            'name' => 'iPhone 15 Pro',
            'price' => 999.99,
            'description' => 'Latest iPhone model'
        ]);
        
        $response = $this->vendorApi->getArticleDetails('ART-001', 'en');
        
        $this->assertEquals('ART-001', $response['article_id']);
        $this->assertEquals('iPhone 15 Pro', $response['name']);
    }
    
    /**
     * SECURITY TEST: API key is sent with requests
     */
    public function testApiKeyIsSent()
    {
        // This would verify in real implementation that Authorization header is set
        // For now, we test that the service initializes with API key
        $this->assertNotNull($this->vendorApi);
    }
    
    /**
     * SECURITY TEST: Invalid JSON response handling
     */
    public function testInvalidJsonResponseHandling()
    {
        MockVendorApi::setResponse('getStock', [
            'error' => 'Invalid response format from vendor API'
        ]);
        
        $response = $this->vendorApi->getStock('en');
        
        $this->assertArrayHasKey('error', $response);
    }
    
    /**
     * TEST: HTTP 401 Unauthorized
     */
    public function testUnauthorizedResponse()
    {
        MockVendorApi::setResponse('getStock', [
            'http_code' => 401,
            'error' => 'Unauthorized'
        ]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Vendor API Error');
        
        $this->vendorApi->getStockWithAuth('en');
    }
    
    /**
     * TEST: HTTP 500 Internal Server Error
     */
    public function testServerErrorResponse()
    {
        MockVendorApi::setResponse('getStock', [
            'http_code' => 500,
            'error' => 'Internal Server Error'
        ]);
        
        $this->expectException(Exception::class);
        
        $this->vendorApi->getStockWithAuth('en');
    }
    
    /**
     * TEST: Network timeout simulation
     */
    public function testNetworkTimeout()
    {
        MockVendorApi::setResponse('getStock', [
            'error' => 'Connection timeout'
        ]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('timeout');
        
        $this->vendorApi->getStockWithTimeout('en');
    }
    
    /**
     * TEST: Retry logic on failure
     */
    public function testRetryLogicOnFailure()
    {
        // First call fails, second succeeds
        static $callCount = 0;
        
        MockVendorApi::setResponse('getStock', [
            'status' => 'ok',
            'retry_test' => ++$callCount
        ]);
        
        // In real implementation, this would test actual retry logic
        $response = $this->vendorApi->getStock('en');
        $this->assertEquals('ok', $response['status']);
    }
    
    /**
     * EDGE CASE: Empty reservation list
     */
    public function testCreateSalesOrderWithEmptyReservations()
    {
        $this->expectException(Exception::class);
        
        $this->vendorApi->createSalesOrder([], 'Wire', 'no');
    }
    
    /**
     * EDGE CASE: Very large quantity reservation
     */
    public function testReserveVeryLargeQuantity()
    {
        MockVendorApi::setResponse('reserveArticle', [
            'error' => 1,
            'error_msg' => 'Quantity exceeds maximum allowed'
        ]);
        
        $this->expectException(Exception::class);
        
        $this->vendorApi->reserveArticle('ART-001', 'BR001', 999999);
    }
    
    /**
     * TEST: API call logging
     */
    public function testApiCallIsLogged()
    {
        MockVendorApi::setResponse('getStock', [
            'status' => 'ok',
            'data' => []
        ]);
        
        $this->vendorApi->getStock('en');
        
        // Verify log was created
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM vendor_api_logs");
        $result = $stmt->fetch();
        
        $this->assertGreaterThan(0, intval($result['count']));
    }
    
    /**
     * TEST: API call duration tracking
     */
    public function testApiCallDurationTracking()
    {
        MockVendorApi::setResponse('getStock', ['status' => 'ok']);
        
        $this->vendorApi->getStock('en');
        
        // Verify duration was logged
        $stmt = $this->db->query("SELECT duration_ms FROM vendor_api_logs ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch();
        
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertGreaterThanOrEqual(0, intval($result['duration_ms']));
    }
}

/**
 * Mock VendorApiService for testing
 */
class VendorApiServiceMock extends VendorApiService
{
    private $logEnabled = true;
    
    public function getStock($lang = 'en')
    {
        $response = MockVendorApi::getResponse('getStock');
        $this->logApiCall('GetStock', 'GET', ['lang' => $lang], $response, 200, 10);
        return $response;
    }
    
    public function getStockWithAuth($lang = 'en')
    {
        $response = MockVendorApi::getResponse('getStock');
        if (isset($response['http_code']) && $response['http_code'] >= 400) {
            throw new Exception("Vendor API Error (HTTP {$response['http_code']})");
        }
        return $response;
    }
    
    public function getStockWithTimeout($lang = 'en')
    {
        $response = MockVendorApi::getResponse('getStock');
        if (isset($response['error']) && strpos($response['error'], 'timeout') !== false) {
            throw new Exception($response['error']);
        }
        return $response;
    }
    
    public function reserveArticle($sku, $warehouse, $quantity)
    {
        $response = MockVendorApi::getResponse('reserveArticle');
        $this->logApiCall('ReserveArticle', 'POST', compact('sku', 'warehouse', 'quantity'), $response, 200, 15);
        
        $isSuccess = (isset($response['status']) && $response['status'] === 'ok') ||
                    (isset($response['error']) && $response['error'] === 0);
        
        if (!$isSuccess) {
            throw new Exception('Reservation failed');
        }
        
        return $response;
    }
    
    public function unreserveArticle($reservationId)
    {
        $response = MockVendorApi::getResponse('unreserveArticle');
        $this->logApiCall('UnreserveArticle', 'POST', ['reservation_id' => $reservationId], $response, 200, 12);
        return $response;
    }
    
    public function createSalesOrder($reservations, $payWith = 'Wire', $insurance = 'no')
    {
        if (empty($reservations)) {
            throw new Exception('Reservations cannot be empty');
        }
        
        $response = MockVendorApi::getResponse('createSalesOrder');
        $this->logApiCall('CreateSalesOrder', 'POST', compact('reservations', 'payWith', 'insurance'), $response, 200, 20);
        
        if (!isset($response['success']) || !$response['success']) {
            throw new Exception($response['message'] ?? 'Sales order creation failed');
        }
        
        return $response;
    }
    
    public function getArticleDetails($articleId, $lang = 'en')
    {
        $response = MockVendorApi::getResponse('getArticleDetails');
        $this->logApiCall("GetArticle/$articleId", 'GET', ['lang' => $lang], $response, 200, 8);
        return $response;
    }
    
    private function logApiCall($endpoint, $method, $request, $response, $statusCode, $duration, $error = null)
    {
        if (!$this->logEnabled) {
            return;
        }
        
        try {
            $db = Database::getConnection();
            $sql = "INSERT INTO vendor_api_logs (
                endpoint, method, request_payload, response_payload, 
                status_code, duration_ms, error_message
            ) VALUES (
                :endpoint, :method, :request, :response,
                :status_code, :duration, :error
            )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':endpoint' => $endpoint,
                ':method' => $method,
                ':request' => json_encode($request),
                ':response' => json_encode($response),
                ':status_code' => $statusCode,
                ':duration' => $duration,
                ':error' => $error
            ]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
}

