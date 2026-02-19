<?php

/**
 * Shipping Service
 * Handles UPS shipping integration and tracking
 */
class ShippingService
{
    private $db;
    private $clientId;
    private $clientSecret;
    private $environment;
    private $baseUrl;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->clientId = Env::get('UPS_CLIENT_ID');
        $this->clientSecret = Env::get('UPS_CLIENT_SECRET');
        $this->environment = Env::get('UPS_ENVIRONMENT', 'test');
        
        // Set base URL based on environment
        $this->baseUrl = $this->environment === 'production' 
            ? 'https://onlinetools.ups.com' 
            : 'https://wwwcie.ups.com';
    }

    /**
     * Track a package using UPS Tracking API
     * 
     * @param string $trackingNumber UPS tracking number
     * @return array Tracking information
     * @throws Exception
     */
    public function trackPackage($trackingNumber)
    {
        if (empty($trackingNumber)) {
            throw new Exception('Tracking number is required');
        }

        // Get access token
        $accessToken = $this->getAccessToken();
        
        // Make tracking API request
        $url = $this->baseUrl . '/api/track/v1/details/' . $trackingNumber;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'transId: ' . uniqid('TT-', true),
                'transactionSrc: TeleTradeHub'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('UPS API request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['response']['errors'][0]['message'] ?? 'Unknown error';
            throw new Exception('UPS API error: ' . $errorMessage);
        }

        $data = json_decode($response, true);
        
        return $this->parseTrackingResponse($data);
    }

    /**
     * Get UPS OAuth access token
     * 
     * @return string Access token
     * @throws Exception
     */
    private function getAccessToken()
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('UPS credentials not configured. Please set UPS_CLIENT_ID and UPS_CLIENT_SECRET in .env');
        }

        $url = $this->baseUrl . '/security/v1/oauth/token';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'x-merchant-id: ' . $this->clientId
            ],
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('Failed to get UPS access token: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('UPS authentication failed. Please check your credentials.');
        }

        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception('Invalid response from UPS authentication');
        }

        return $data['access_token'];
    }

    /**
     * Parse UPS tracking response into standardized format
     * 
     * @param array $response UPS API response
     * @return array Parsed tracking data
     */
    private function parseTrackingResponse($response)
    {
        $tracking = [
            'tracking_number' => '',
            'status' => 'unknown',
            'status_description' => '',
            'carrier' => 'UPS',
            'estimated_delivery' => null,
            'delivered' => false,
            'delivered_at' => null,
            'activities' => []
        ];

        if (!isset($response['trackResponse']['shipment'][0])) {
            return $tracking;
        }

        $shipment = $response['trackResponse']['shipment'][0];
        
        // Get tracking number
        if (isset($shipment['package'][0]['trackingNumber'])) {
            $tracking['tracking_number'] = $shipment['package'][0]['trackingNumber'];
        }

        // Get status
        if (isset($shipment['package'][0]['activity'][0]['status']['type'])) {
            $statusType = $shipment['package'][0]['activity'][0]['status']['type'];
            $tracking['status'] = strtolower($statusType);
        }

        // Get status description
        if (isset($shipment['package'][0]['activity'][0]['status']['description'])) {
            $tracking['status_description'] = $shipment['package'][0]['activity'][0]['status']['description'];
        }

        // Get delivery date
        if (isset($shipment['package'][0]['deliveryDate'])) {
            $deliveryDate = $shipment['package'][0]['deliveryDate'];
            if (isset($deliveryDate['date'])) {
                $tracking['estimated_delivery'] = $deliveryDate['date'];
            }
        }

        // Check if delivered
        if (isset($shipment['package'][0]['activity'][0]['status']['type'])) {
            $tracking['delivered'] = (strtolower($shipment['package'][0]['activity'][0]['status']['type']) === 'd');
            if ($tracking['delivered'] && isset($shipment['package'][0]['activity'][0]['date'])) {
                $tracking['delivered_at'] = $shipment['package'][0]['activity'][0]['date'];
            }
        }

        // Get activity history
        if (isset($shipment['package'][0]['activity'])) {
            foreach ($shipment['package'][0]['activity'] as $activity) {
                $tracking['activities'][] = [
                    'date' => $activity['date'] ?? null,
                    'time' => $activity['time'] ?? null,
                    'location' => $activity['location']['address']['city'] ?? 'Unknown',
                    'description' => $activity['status']['description'] ?? '',
                    'type' => $activity['status']['type'] ?? ''
                ];
            }
        }

        return $tracking;
    }

    /**
     * Update order with tracking information
     * 
     * @param int $orderId Order ID
     * @param string $trackingNumber Tracking number
     * @param string $carrier Shipping carrier (default: UPS)
     * @return bool Success
     */
    public function updateOrderTracking($orderId, $trackingNumber, $carrier = 'UPS')
    {
        $sql = "UPDATE orders 
                SET tracking_number = :tracking_number, 
                    shipping_carrier = :carrier,
                    shipped_at = NOW(),
                    status = 'shipped',
                    updated_at = NOW()
                WHERE id = :order_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':tracking_number' => $trackingNumber,
            ':carrier' => $carrier,
            ':order_id' => $orderId
        ]);
    }

    /**
     * Get tracking information for an order
     * 
     * @param int $orderId Order ID
     * @return array|null Tracking information or null if not available
     */
    public function getOrderTracking($orderId)
    {
        $order = $this->db->prepare("SELECT tracking_number, shipping_carrier FROM orders WHERE id = :id");
        $order->execute([':id' => $orderId]);
        $orderData = $order->fetch();

        if (!$orderData || empty($orderData['tracking_number'])) {
            return null;
        }

        try {
            return $this->trackPackage($orderData['tracking_number']);
        } catch (Exception $e) {
            // Log error but return basic info
            error_log('Failed to track package: ' . $e->getMessage());
            return [
                'tracking_number' => $orderData['tracking_number'],
                'carrier' => $orderData['shipping_carrier'] ?? 'UPS',
                'status' => 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate shipping cost (placeholder for future UPS Rate API integration)
     * 
     * @param array $shippingAddress Shipping address
     * @param array $items Order items with weight/dimensions
     * @param string $serviceType Service type (ground, express, etc.)
     * @return float Shipping cost
     */
    public function calculateShippingCost($shippingAddress, $items, $serviceType = 'ground')
    {
        // TODO: Integrate UPS Rate API for real-time shipping calculations
        // For now, return default shipping cost from settings
        $sql = "SELECT value FROM settings WHERE `key` = :key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':key' => 'shipping_cost']);
        $result = $stmt->fetch();
        
        $defaultShipping = $result ? floatval($result['value']) : 9.99;
        
        return $defaultShipping;
    }
}
