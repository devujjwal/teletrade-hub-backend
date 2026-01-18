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
     * Requires user authentication
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
            'payment_method' => 'required',
            'user_id' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        // Validate cart items
        if (empty($input['cart_items']) || !is_array($input['cart_items'])) {
            Response::error('Cart is empty', 400);
        }

        try {
            // Debug logging - log what we received
            error_log("Order creation request received: " . json_encode([
                'has_billing_address_id' => isset($input['billing_address_id']),
                'billing_address_id_value' => $input['billing_address_id'] ?? 'NOT_SET',
                'has_shipping_address_id' => isset($input['shipping_address_id']),
                'shipping_address_id_value' => $input['shipping_address_id'] ?? 'NOT_SET',
                'has_billing_address' => isset($input['billing_address']),
                'has_shipping_address' => isset($input['shipping_address']),
                'user_id' => $input['user_id'] ?? 'NOT_SET'
            ]));

            // Validate user exists
            $userModel = new User();
            $user = $userModel->getById($input['user_id']);
            if (!$user) {
                Response::error('User not found. Please login to place an order.', 401);
            }
            $userId = $input['user_id'];

            $orderModel = new Order();
            
            // Handle billing address (either ID or full data)
            $billingAddressId = null;
            $billingAddress = null;
            
            if (!empty($input['billing_address_id'])) {
                // Use existing address
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
     * SECURITY: Requires authentication and validates order ownership
     */
    public function show($orderNumber)
    {
        $order = $this->orderModel->getByOrderNumber($orderNumber);

        if (!$order) {
            Response::notFound('Order not found');
        }

        // SECURITY CRITICAL: Validate access rights
        // Only authenticated users can access their orders
        
        $userId = null;
        
        // Get authenticated user from token
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::unauthorized('Authentication required to view order');
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
            
            $userId = $session['user_id'];
        } catch (Exception $e) {
            Response::error('Authentication failed', 401);
        }
        
        // Verify user owns this order
        if (!$order['user_id'] || $order['user_id'] != intval($userId)) {
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

