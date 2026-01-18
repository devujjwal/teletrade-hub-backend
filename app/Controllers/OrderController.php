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
            'payment_method' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
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

            $orderModel = new Order();
            
            // Handle billing address (either ID or full data)
            $billingAddressId = null;
            $billingAddress = null;
            
            if (!empty($input['billing_address_id'])) {
                // Use existing address
                if (!$userId) {
                    Response::error('Cannot use saved address for guest checkout', 400);
                }
                if (!$orderModel->validateAddressOwnership($input['billing_address_id'], $userId)) {
                    Response::error('Invalid billing address', 403);
                }
                $billingAddressId = $input['billing_address_id'];
            } else if (!empty($input['billing_address'])) {
                // Validate full address data
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

                // Prepare address data for creation
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
            } else {
                Response::error('Billing address is required', 400);
            }

            // Handle shipping address (either ID or full data)
            $shippingAddressId = null;
            $shippingAddress = null;
            
            if (!empty($input['shipping_address_id'])) {
                // Use existing address
                if (!$userId) {
                    Response::error('Cannot use saved address for guest checkout', 400);
                }
                if (!$orderModel->validateAddressOwnership($input['shipping_address_id'], $userId)) {
                    Response::error('Invalid shipping address', 403);
                }
                $shippingAddressId = $input['shipping_address_id'];
            } else if (!empty($input['shipping_address'])) {
                // Prepare shipping address data for creation
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
            // If neither shipping_address_id nor shipping_address provided, use billing address

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
                $billingAddressId,
                $billingAddress,
                $shippingAddressId,
                $shippingAddress
            );

            Response::success($result, 'Order created successfully', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * List customer's orders
     * Requires authentication
     */
    public function list()
    {
        // Get authenticated user
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::unauthorized('Authentication required');
        }
        
        $token = $matches[1];
        
        // Validate token and get user
        try {
            $db = Database::getConnection();
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
            
            // Get user's orders
            $userModel = new User();
            $orders = $userModel->getOrders($session['user_id'], 100); // Limit to 100 orders
            
            // Get details for each order
            $ordersWithDetails = [];
            foreach ($orders as $order) {
                $orderDetails = $this->orderService->getOrderDetails($order['id'], false);
                $ordersWithDetails[] = $orderDetails;
            }
            
            Response::success(['data' => $ordersWithDetails]);
        } catch (Exception $e) {
            Response::error('Failed to fetch orders: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get order details
     * SECURITY: Validate order ownership or guest token access
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
        // 2. Guest order accessed with valid token (generated from order details)
        // 3. Admin accessing (via separate admin endpoint)
        
        $hasAccess = false;
        $userId = null;
        
        // Try to get authenticated user from token
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            
            // Validate token and get user
            try {
                $db = Database::getConnection();
                $sql = "SELECT us.user_id
                        FROM user_sessions us
                        WHERE us.token = :token 
                        AND us.expires_at > NOW()";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([':token' => $token]);
                $session = $stmt->fetch();
                
                if ($session) {
                    $userId = $session['user_id'];
                }
            } catch (Exception $e) {
                // Token validation failed, continue to check guest access
            }
        }
        
        // Check access rights
        // Registered user access
        if ($userId && $order['user_id'] && $order['user_id'] == intval($userId)) {
            $hasAccess = true;
        }
        // Guest access with token (from query params)
        else {
            $guestToken = $_GET['guest_token'] ?? null;
            $guestEmail = $_GET['guest_email'] ?? null; // Fallback for backward compatibility
            
            if ($guestToken && $order['guest_email']) {
                $expectedToken = $this->generateGuestOrderToken($order['order_number'], $order['guest_email']);
                if (hash_equals($expectedToken, $guestToken)) {
                    $hasAccess = true;
                }
            }
            // Guest access with email (fallback - less secure)
            elseif ($guestEmail && $order['guest_email']) {
                // SECURITY: Use timing-safe comparison
                if (hash_equals(
                    strtolower(trim($order['guest_email'])), 
                    strtolower(trim($guestEmail))
                )) {
                    $hasAccess = true;
                }
            }
        }
        
        if (!$hasAccess) {
            Response::forbidden('You do not have permission to access this order');
        }

        // Get order details (sanitized for customer - no internal fields)
        $fullOrder = $this->orderService->getOrderDetails($order['id'], false);

        Response::success(['order' => $fullOrder]);
    }
    
    /**
     * Generate secure token for guest order access
     * SECURITY: Uses order number, email, and secret key
     * Must match the implementation in OrderService
     */
    private function generateGuestOrderToken($orderNumber, $guestEmail)
    {
        if (!class_exists('Env')) {
            require_once __DIR__ . '/../Config/env.php';
        }
        
        $secret = Env::get('APP_KEY', 'default-secret-change-in-production');
        $data = $orderNumber . '|' . strtolower(trim($guestEmail)) . '|' . $secret;
        return hash_hmac('sha256', $data, $secret);
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

