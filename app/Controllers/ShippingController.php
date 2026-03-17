<?php

require_once __DIR__ . '/../Services/ShippingService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../Services/ApiCacheService.php';

/**
 * Shipping Controller
 * Handles shipping and tracking related API endpoints
 */
class ShippingController
{
    private $shippingService;
    private $orderModel;
    private $apiCache;

    public function __construct()
    {
        $this->shippingService = new ShippingService();
        $this->orderModel = new Order();
        $this->apiCache = new ApiCacheService();
    }

    /**
     * Track a package by tracking number
     * GET /api/shipping/track/{trackingNumber}
     */
    public function track($trackingNumber)
    {
        if (empty($trackingNumber)) {
            Response::error('Tracking number is required', 400);
        }

        try {
            $cacheKey = $this->apiCache->buildKey('shipping:track', [
                'tracking_number' => $trackingNumber,
            ], [], ['shipping_track:' . strtolower((string) $trackingNumber)]);
            $cacheTtl = $this->apiCache->getTtlTracking();
            $this->serveCached($cacheKey, $cacheTtl);

            $tracking = $this->shippingService->trackPackage($trackingNumber);
            $this->respondSuccess($tracking, 'Success', 200, $cacheKey, $cacheTtl);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Get tracking information for an order
     * GET /api/shipping/orders/{orderId}/tracking
     */
    public function getOrderTracking($orderId)
    {
        // Validate order exists
        $order = $this->orderModel->getById($orderId);
        
        if (!$order) {
            Response::error('Order not found', 404);
        }

        // Check authentication - user must own the order or be admin
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::unauthorized('Authentication required');
        }
        
        $token = $matches[1];
        $db = Database::getConnection();
        
        // Check if admin
        $authMiddleware = new AuthMiddleware();
        $admin = null;
        try {
            $admin = $authMiddleware->verifyAdmin();
        } catch (Exception $e) {
            // Not admin, check user session
        }
        
        // If not admin, verify user owns the order
        if (!$admin) {
            $sql = "SELECT us.user_id
                    FROM user_sessions us
                    WHERE us.token = :token 
                    AND us.expires_at > NOW()";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':token' => $token]);
            $session = $stmt->fetch();
            
            if (!$session) {
                Response::unauthorized('Invalid or expired token');
            }
            
            if ($order['user_id'] != $session['user_id']) {
                Response::error('Unauthorized', 403);
            }
        }

        try {
            $tracking = $this->shippingService->getOrderTracking($orderId);
            
            if (!$tracking) {
                Response::error('No tracking information available for this order', 404);
            }
            
            Response::success($tracking);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Update order with tracking number (Admin only)
     * POST /api/admin/shipping/orders/{orderId}/tracking
     */
    public function updateOrderTracking($orderId)
    {
        // Admin only
        $authMiddleware = new AuthMiddleware();
        $admin = $authMiddleware->verifyAdmin();

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $errors = Validator::validate($input, [
            'tracking_number' => 'required|string',
            'carrier' => 'optional|string'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        $order = $this->orderModel->getById($orderId);
        
        if (!$order) {
            Response::error('Order not found', 404);
        }

        $trackingNumber = Sanitizer::string($input['tracking_number']);
        $carrier = Sanitizer::string($input['carrier'] ?? 'UPS');

        try {
            $success = $this->shippingService->updateOrderTracking($orderId, $trackingNumber, $carrier);
            
            if ($success) {
                Response::success([
                    'message' => 'Tracking information updated',
                    'order_id' => $orderId,
                    'tracking_number' => $trackingNumber,
                    'carrier' => $carrier
                ]);
            } else {
                Response::error('Failed to update tracking information', 500);
            }
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Calculate shipping cost (for checkout)
     * POST /api/shipping/calculate
     */
    public function calculate()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $errors = Validator::validate($input, [
            'shipping_address' => 'required|array',
            'items' => 'required|array'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        try {
            $shippingAddress = $input['shipping_address'];
            $items = $input['items'];
            $serviceType = Sanitizer::string($input['service_type'] ?? 'ground');

            $cost = $this->shippingService->calculateShippingCost($shippingAddress, $items, $serviceType);
            
            Response::success([
                'shipping_cost' => $cost,
                'service_type' => $serviceType,
                'currency' => 'EUR'
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    private function serveCached($cacheKey, $ttl)
    {
        if (!$this->apiCache->isEnabled() || $cacheKey === '') {
            return;
        }

        $entry = $this->apiCache->get($cacheKey);
        if (!$entry) {
            return;
        }

        http_response_code(intval($entry['status_code'] ?? 200));
        header('Content-Type: ' . ($entry['content_type'] ?? 'application/json; charset=utf-8'));
        header('Cache-Control: public, max-age=' . intval($ttl) . ', s-maxage=' . intval($ttl));
        header('X-API-Cache: HIT');
        echo (string) ($entry['body'] ?? '');
        exit;
    }

    private function respondSuccess($data = null, $message = 'Success', $statusCode = 200, $cacheKey = '', $ttl = 0)
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        $json = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        if ($this->apiCache->isEnabled() && $cacheKey !== '' && $statusCode >= 200 && $statusCode < 300) {
            header('Cache-Control: public, max-age=' . intval($ttl) . ', s-maxage=' . intval($ttl));
            header('X-API-Cache: MISS');
            $this->apiCache->put($cacheKey, $json, $statusCode, 'application/json; charset=utf-8', $ttl);
        } else {
            header('X-API-Cache: BYPASS');
        }

        echo $json;
        exit;
    }
}
