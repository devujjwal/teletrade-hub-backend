<?php

require_once __DIR__ . '/../Utils/Language.php';

/**
 * Vendor API Service
 * Handles all interactions with TRIEL B2B API
 */
class VendorApiService
{
    private const LOG_RETENTION_DAYS = 30;
    private const MAX_LOG_ROWS = 10000;

    private $baseUrl;
    private $restfulBaseUrl;
    private $apiKey;
    private $logEnabled;
    private static $retentionPruned = false;

    public function __construct()
    {
        $this->baseUrl = Env::get('VENDOR_API_BASE_URL', 'https://b2b.triel.sk/api');
        $this->restfulBaseUrl = Env::get('VENDOR_RESTFUL_API_BASE_URL', 'https://restful.triel.sk');
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
        
        // Vendor Postman docs specify reserveArticle/new with multipart form-data.
        $payload = [
            'sku' => $sku,
            'qty' => (string) $quantity,
            // Warranty is optional in vendor docs; send the default empty value.
            'warranty' => ''
        ];

        try {
            $response = $this->makeRequest(
                'POST',
                $this->restfulBaseUrl . '/reserveArticle/new/',
                $payload,
                ['multipart' => true]
            );
            
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
            'reservation_id' => $reservationId
        ];

        try {
            $response = $this->makeRequest(
                'POST',
                $this->restfulBaseUrl . '/reserveArticle/remove/',
                $payload,
                ['multipart' => true]
            );
            
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
    public function createSalesOrder($reservations, $payWith = 'WireTransfer', $insurance = 'no')
    {
        $startTime = microtime(true);
        
        // Vendor Postman docs specify multipart form-data with reservation[] and pay_method.
        $orderData = [];
        foreach ($reservations as $reservationId) {
            $orderData['reservation[]'][] = (string) $reservationId;
        }
        $orderData['pay_method'] = $payWith;
        $orderData['insurance'] = $insurance;
        $orderData['drop_shipping'] = '0';

        try {
            $response = $this->makeRequest(
                'POST',
                $this->restfulBaseUrl . '/createSalesOrder/',
                $orderData,
                ['multipart' => true]
            );
            
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
    private function makeRequest($method, $endpoint, $data = null, $options = [])
    {
        $url = preg_match('#^https?://#i', $endpoint) ? $endpoint : $this->baseUrl . $endpoint;
        $isMultipart = !empty($options['multipart']);

        $ch = curl_init();

        $headers = [
            'Authorization: ' . $this->apiKey,  // TRIEL doesn't use "Bearer" prefix
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
                $postFields = $isMultipart ? $data : http_build_query($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                if (!$isMultipart) {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                }
            }
        } elseif ($method === 'GET' && $data !== null) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

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

        $trimmedResponse = trim((string) $response);
        if ($trimmedResponse === '') {
            return ['status' => 'ok'];
        }

        // Some vendor endpoints may return a plain reservation/order ID instead of JSON.
        if (!str_starts_with($trimmedResponse, '<')) {
            return [
                'status' => 'ok',
                'raw' => substr($trimmedResponse, 0, 500),
                'ReturnVal' => $trimmedResponse,
                'reservationId' => $trimmedResponse,
                'id' => $trimmedResponse
            ];
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
            $this->synchronizePostgresSequence($db, 'vendor_api_logs', 'id');
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
            $this->pruneApiLogs($db);
        } catch (Exception $e) {
            // Silent fail for logging
            error_log("Failed to log vendor API call: " . $e->getMessage());
        }
    }

    /**
     * Retain only recent and useful vendor API logs.
     * Cleanup runs once per process so normal API traffic stays lightweight.
     */
    private function pruneApiLogs(PDO $db)
    {
        if (self::$retentionPruned) {
            return;
        }

        self::$retentionPruned = true;

        if (Database::isPostgres()) {
            $stmt = $db->prepare(
                "DELETE FROM vendor_api_logs
                 WHERE created_at < NOW() - (:retention_days || ' days')::INTERVAL"
            );
            $stmt->execute([':retention_days' => self::LOG_RETENTION_DAYS]);

            $stmt = $db->prepare(
                "DELETE FROM vendor_api_logs
                 WHERE id IN (
                     SELECT id
                     FROM vendor_api_logs
                     ORDER BY created_at DESC, id DESC
                     OFFSET :max_rows
                 )"
            );
            $stmt->bindValue(':max_rows', self::MAX_LOG_ROWS, PDO::PARAM_INT);
            $stmt->execute();
            return;
        }

        $stmt = $db->prepare(
            "DELETE FROM vendor_api_logs
             WHERE created_at < DATE_SUB(NOW(), INTERVAL :retention_days DAY)"
        );
        $stmt->bindValue(':retention_days', self::LOG_RETENTION_DAYS, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $db->prepare(
            "DELETE FROM vendor_api_logs
             WHERE id NOT IN (
                 SELECT id FROM (
                     SELECT id
                     FROM vendor_api_logs
                     ORDER BY created_at DESC, id DESC
                     LIMIT :max_rows
                 ) AS retained_logs
             )"
        );
        $stmt->bindValue(':max_rows', self::MAX_LOG_ROWS, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Repair imported PostgreSQL identity/serial sequences before inserts.
     */
    private function synchronizePostgresSequence(PDO $db, $table, $column)
    {
        if (!Database::isPostgres()) {
            return;
        }

        $sequenceStmt = $db->prepare("SELECT pg_get_serial_sequence(:table_name, :column_name)");
        $sequenceStmt->execute([
            ':table_name' => $table,
            ':column_name' => $column
        ]);
        $sequenceName = $sequenceStmt->fetchColumn();

        if (!$sequenceName) {
            return;
        }

        $sql = "SELECT setval(
                    :sequence_name,
                    COALESCE((SELECT MAX({$column}) FROM {$table}), 0) + 1,
                    false
                )";
        $stmt = $db->prepare($sql);
        $stmt->execute([':sequence_name' => $sequenceName]);
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
