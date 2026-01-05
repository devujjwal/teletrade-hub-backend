<?php

/**
 * Vendor API Service
 * Handles all interactions with TRIEL B2B API
 */
class VendorApiService
{
    private $baseUrl;
    private $apiKey;
    private $logEnabled;

    public function __construct()
    {
        $this->baseUrl = Env::get('VENDOR_API_BASE_URL', 'https://b2b.triel.sk/api');
        $this->apiKey = Env::get('VENDOR_API_KEY');
        $this->logEnabled = true;
    }

    /**
     * Get stock data from vendor
     */
    public function getStock($lang = 'en')
    {
        $startTime = microtime(true);
        
        try {
            // TRIEL uses lang_id: 0=EN, 1=SK, 2=DE
            $langId = $lang === 'sk' ? 1 : ($lang === 'de' ? 2 : 0);
            $response = $this->makeRequest('GET', '/getStock/', ['lang_id' => $langId, 'price_drop' => 0]);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('GetStock', 'GET', ['lang' => $lang], $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('GetStock', 'GET', ['lang' => $lang], null, 0, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reserve article
     */
    public function reserveArticle($articleId, $quantity)
    {
        $startTime = microtime(true);
        
        $payload = [
            'articleId' => $articleId,
            'quantity' => $quantity
        ];

        try {
            $response = $this->makeRequest('POST', '/ReserveArticle', $payload);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('ReserveArticle', 'POST', $payload, $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('ReserveArticle', 'POST', $payload, null, 0, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Unreserve article
     */
    public function unreserveArticle($reservationId)
    {
        $startTime = microtime(true);
        
        $payload = [
            'reservationId' => $reservationId
        ];

        try {
            $response = $this->makeRequest('POST', '/UnreserveArticle', $payload);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('UnreserveArticle', 'POST', $payload, $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('UnreserveArticle', 'POST', $payload, null, 0, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create sales order
     */
    public function createSalesOrder($orderData)
    {
        $startTime = microtime(true);

        try {
            $response = $this->makeRequest('POST', '/CreateSalesOrder', $orderData);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('CreateSalesOrder', 'POST', $orderData, $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('CreateSalesOrder', 'POST', $orderData, null, 0, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get article details
     */
    public function getArticleDetails($articleId, $lang = 'en')
    {
        $startTime = microtime(true);
        
        try {
            $response = $this->makeRequest('GET', "/GetArticle/$articleId", ['lang' => $lang]);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall("GetArticle/$articleId", 'GET', ['lang' => $lang], $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall("GetArticle/$articleId", 'GET', ['lang' => $lang], null, 0, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Make HTTP request to vendor API
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $headers = [
            'Authorization: ' . $this->apiKey,  // TRIEL doesn't use "Bearer" prefix
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // TRIEL uses --location flag
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET' && $data !== null) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Vendor API Error: $error");
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['message'] ?? 'Unknown error';
            throw new Exception("Vendor API Error (HTTP $httpCode): $errorMsg");
        }

        return json_decode($response, true);
    }

    /**
     * Log API call to database
     */
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
            // Silent fail for logging
            error_log("Failed to log vendor API call: " . $e->getMessage());
        }
    }

    /**
     * Check API health
     */
    public function checkHealth()
    {
        try {
            // Simple ping or light request
            $this->makeRequest('GET', '/health', []);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

