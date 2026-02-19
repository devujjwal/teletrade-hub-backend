<?php

require_once __DIR__ . '/../Services/ShippingService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Middlewares/AuthMiddleware.php';

/**
 * Shipping Controller
 * Handles shipping and tracking related API endpoints
 */
class ShippingController
{
    private $shippingService;
    private $orderModel;

    public function __construct()
    {
        $this->shippingService = new ShippingService();
        $this->orderModel = new Order();
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
            $tracking = $this->shippingService->trackPackage($trackingNumber);
            Response::success($tracking);
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
}
