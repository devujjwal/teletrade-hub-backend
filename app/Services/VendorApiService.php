<?php

require_once __DIR__ . '/../Utils/Language.php';

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
     * 
     * @param int|string $lang Language ID (0-11) or code ('en', 'de', etc.)
     * @return array Stock data from vendor
     */
    public function getStock($lang = 1)
    {
        $startTime = microtime(true);
        
        try {
            // Convert language code to ID if needed
            // Vendor API accepts language IDs: 0=Default, 1=English, 3=German, etc.
            $langId = is_numeric($lang) ? (int)$lang : Language::getIdFromCode($lang);
            
            $response = $this->makeRequest('GET', '/getStock/', ['lang_id' => $langId, 'price_drop' => 0]);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('GetStock', 'GET', ['lang_id' => $langId], $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall('GetStock', 'GET', ['lang_id' => $langId ?? $lang], null, 0, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reserve article
     */
    public function reserveArticle($sku, $warehouse, $quantity)
    {
        $startTime = microtime(true);
        
        // TRIEL RESTful API might use different parameter names
        // Try multiple formats to be compatible
        $payload = [
            'sku' => $sku,
            'warehouse' => $warehouse,
            'quantity' => $quantity,
            // Also include old API format just in case
            'gensoft_id' => $sku,
            'amount' => $quantity
        ];

        try {
            $response = $this->makeRequest('POST', '/reserveArticle/', $payload);
            
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
            'reservation_id' => $reservationId  // Old API uses reservation_id
        ];

        try {
            $response = $this->makeRequest('POST', '/removeReservedArticle/', $payload);
            
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
    public function createSalesOrder($reservations, $payWith = 'Wire', $insurance = 'no')
    {
        $startTime = microtime(true);
        
        // TRIEL format: reservations array, payWith, insurance
        $orderData = [
            'reservations' => json_encode($reservations),  // JSON encoded array of reservation IDs
            'payWith' => $payWith,        // 'Wire' or 'OnDelivery'
            'insurance' => $insurance     // 'yes' or 'no'
        ];

        try {
            $response = $this->makeRequest('POST', '/createSalesOrder/', $orderData);
            
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
     * 
     * @param string $articleId Article/SKU identifier
     * @param int|string $lang Language ID (0-11) or code ('en', 'de', etc.)
     * @return array Article details
     */
    public function getArticleDetails($articleId, $lang = 1)
    {
        $startTime = microtime(true);
        
        try {
            // Convert language code to ID if needed
            $langId = is_numeric($lang) ? (int)$lang : Language::getIdFromCode($lang);
            
            $response = $this->makeRequest('GET', "/GetArticle/$articleId", ['lang_id' => $langId]);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall("GetArticle/$articleId", 'GET', ['lang_id' => $langId], $response, 200, $duration);
            
            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiCall("GetArticle/$articleId", 'GET', ['lang_id' => $langId ?? $lang], null, 0, $duration, $e->getMessage());
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
                // TRIEL API uses form-encoded data for POST (not JSON)
                $postFields = http_build_query($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                // Update content type for form data
                $headers[1] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
            $errorMsg = $errorData['message'] ?? $errorData['error_msg'] ?? 'Unknown error';
            throw new Exception("Vendor API Error (HTTP $httpCode): $errorMsg");
        }

        // Try JSON decode first (RESTful API)
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            return $decoded;
        }
        
        // SECURITY FIX: Removed unserialize() - CRITICAL VULNERABILITY
        // Unserialize can lead to remote code execution if vendor response is compromised
        // If vendor doesn't return JSON, log error and fail gracefully
        error_log("Vendor API returned non-JSON response for $endpoint: " . substr($response, 0, 200));
        
        // Return raw response with warning
        return [
            'error' => 'Invalid response format from vendor API',
            'raw' => substr($response, 0, 500) // Limit raw response size
        ];
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

