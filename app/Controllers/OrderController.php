<?php

require_once __DIR__ . '/../Services/OrderService.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * Order Controller
 * Handles order-related API endpoints
 */
class OrderController
{
    private $orderService;
    private $orderModel;

    public function __construct()
    {
        $this->orderService = new OrderService();
        $this->orderModel = new Order();
    }

    /**
     * Create new order
     */
    public function create()
    {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Validate required fields
        $errors = Validator::validate($input, [
            'cart_items' => 'required',
            'billing_address' => 'required',
            'payment_method' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        // Validate billing address
        $addressErrors = Validator::validate($input['billing_address'], [
            'first_name' => 'required',
            'last_name' => 'required',
            'address_line1' => 'required',
            'city' => 'required',
            'postal_code' => 'required',
            'country' => 'required',
            'phone' => 'required'
        ]);

        if (!empty($addressErrors)) {
            Response::error('Invalid billing address', 400, $addressErrors);
        }

        // Validate cart items
        if (empty($input['cart_items']) || !is_array($input['cart_items'])) {
            Response::error('Cart is empty', 400);
        }

        try {
            // Validate and sanitize user_id
            // Only set user_id if it's provided AND the user exists in database
            $userId = null;
            if (!empty($input['user_id'])) {
                $userModel = new User();
                $user = $userModel->getById($input['user_id']);
                if ($user) {
                    $userId = $input['user_id'];
                }
            }

            // Sanitize addresses
            $billingAddress = [
                ':user_id' => $userId,
                ':first_name' => Sanitizer::string($input['billing_address']['first_name']),
                ':last_name' => Sanitizer::string($input['billing_address']['last_name']),
                ':company' => Sanitizer::string($input['billing_address']['company'] ?? ''),
                ':address_line1' => Sanitizer::string($input['billing_address']['address_line1']),
                ':address_line2' => Sanitizer::string($input['billing_address']['address_line2'] ?? ''),
                ':city' => Sanitizer::string($input['billing_address']['city']),
                ':state' => Sanitizer::string($input['billing_address']['state'] ?? ''),
                ':postal_code' => Sanitizer::string($input['billing_address']['postal_code']),
                ':country' => strtoupper(substr($input['billing_address']['country'], 0, 2)),
                ':phone' => Sanitizer::string($input['billing_address']['phone']),
                ':is_default' => 0
            ];

            $shippingAddress = null;
            if (!empty($input['shipping_address'])) {
                $shippingAddress = [
                    ':user_id' => $userId,
                    ':first_name' => Sanitizer::string($input['shipping_address']['first_name']),
                    ':last_name' => Sanitizer::string($input['shipping_address']['last_name']),
                    ':company' => Sanitizer::string($input['shipping_address']['company'] ?? ''),
                    ':address_line1' => Sanitizer::string($input['shipping_address']['address_line1']),
                    ':address_line2' => Sanitizer::string($input['shipping_address']['address_line2'] ?? ''),
                    ':city' => Sanitizer::string($input['shipping_address']['city']),
                    ':state' => Sanitizer::string($input['shipping_address']['state'] ?? ''),
                    ':postal_code' => Sanitizer::string($input['shipping_address']['postal_code']),
                    ':country' => strtoupper(substr($input['shipping_address']['country'], 0, 2)),
                    ':phone' => Sanitizer::string($input['shipping_address']['phone']),
                    ':is_default' => 0
                ];
            }

            // Create order
            $orderData = [
                'user_id' => $userId,
                'guest_email' => $input['guest_email'] ?? null,
                'payment_method' => Sanitizer::string($input['payment_method']),
                'notes' => Sanitizer::string($input['notes'] ?? '')
            ];

            $result = $this->orderService->createOrder(
                $orderData,
                $input['cart_items'],
                $billingAddress,
                $shippingAddress
            );

            Response::success($result, 'Order created successfully', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Get order details
     * SECURITY: Validate order ownership or guest email access
     */
    public function show($orderNumber)
    {
        $order = $this->orderModel->getByOrderNumber($orderNumber);

        if (!$order) {
            Response::notFound('Order not found');
        }

        // SECURITY CRITICAL: Validate access rights
        // Allow access if:
        // 1. Authenticated user owns the order (user_id matches)
        // 2. Guest order accessed with correct email (TODO: implement email verification link)
        // 3. Admin accessing (via separate admin endpoint)
        
        $userId = $_GET['user_id'] ?? null;
        $guestEmail = $_GET['guest_email'] ?? null;
        
        $hasAccess = false;
        
        // Registered user access
        if ($userId && $order['user_id'] == $userId) {
            $hasAccess = true;
        }
        // Guest access (basic check - should be enhanced with token/link in production)
        elseif ($guestEmail && $order['guest_email'] && 
                strtolower(trim($order['guest_email'])) === strtolower(trim($guestEmail))) {
            $hasAccess = true;
        }
        
        if (!$hasAccess) {
            Response::forbidden('You do not have permission to access this order');
        }

        // Get order details (sanitized for customer - no internal fields)
        $fullOrder = $this->orderService->getOrderDetails($order['id'], false);

        Response::success(['order' => $fullOrder]);
    }

    /**
     * Process payment success callback
     */
    public function paymentSuccess($orderNumber)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $order = $this->orderModel->getByOrderNumber($orderNumber);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $transactionId = $input['transaction_id'] ?? null;

        try {
            $result = $this->orderService->processPaymentSuccess($order['id'], $transactionId);
            Response::success($result, 'Payment processed successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Process payment failure callback
     */
    public function paymentFailed($orderNumber)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $order = $this->orderModel->getByOrderNumber($orderNumber);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $reason = $input['reason'] ?? 'Payment failed';

        try {
            $this->orderService->processPaymentFailure($order['id'], $reason);
            Response::success(null, 'Payment failure processed');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}

