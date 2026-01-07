<?php

require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/OrderItem.php';
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/ReservationService.php';
require_once __DIR__ . '/VendorApiService.php';

/**
 * Order Service
 * Handles order creation and management
 */
class OrderService
{
    private $orderModel;
    private $orderItemModel;
    private $productModel;
    private $reservationService;
    private $vendorApi;
    private $db;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
        $this->productModel = new Product();
        $this->reservationService = new ReservationService();
        $this->vendorApi = new VendorApiService();
        $this->db = Database::getConnection();
    }

    /**
     * Create new order from cart
     */
    public function createOrder($orderData, $cartItems, $billingAddress, $shippingAddress)
    {
        // Start transaction
        $this->db->beginTransaction();

        try {
            // Validate stock availability
            $this->validateStockAvailability($cartItems);

            // Calculate totals
            $totals = $this->calculateTotals($cartItems);

            // Create billing address
            $billingAddressId = $this->orderModel->createAddress($billingAddress);

            // Create shipping address (or use same as billing)
            $shippingAddressId = $shippingAddress 
                ? $this->orderModel->createAddress($shippingAddress)
                : $billingAddressId;

            // Generate order number
            $orderNumber = $this->orderModel->generateOrderNumber();

            // Create order
            $orderId = $this->orderModel->create([
                ':order_number' => $orderNumber,
                ':user_id' => $orderData['user_id'] ?? null,
                ':guest_email' => $orderData['guest_email'] ?? null,
                ':status' => 'pending',
                ':payment_status' => 'unpaid',
                ':payment_method' => $orderData['payment_method'] ?? null,
                ':subtotal' => $totals['subtotal'],
                ':tax' => $totals['tax'],
                ':shipping_cost' => $totals['shipping'],
                ':total' => $totals['total'],
                ':currency' => 'EUR',
                ':billing_address_id' => $billingAddressId,
                ':shipping_address_id' => $shippingAddressId,
                ':notes' => $orderData['notes'] ?? null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            // Add order items
            $itemsForReservation = [];
            foreach ($cartItems as $item) {
                $product = $this->productModel->getById($item['product_id']);

                $this->orderModel->addItem($orderId, [
                    ':product_id' => $product['id'],
                    ':product_name' => $product['name'],
                    ':product_sku' => $product['sku'],
                    ':vendor_article_id' => $product['vendor_article_id'],
                    ':quantity' => $item['quantity'],
                    ':base_price' => $product['base_price'],
                    ':price' => $product['price'],
                    ':subtotal' => $product['price'] * $item['quantity']
                ]);

                $itemsForReservation[] = [
                    'product_id' => $product['id'],
                    'vendor_article_id' => $product['vendor_article_id'],
                    'quantity' => $item['quantity']
                ];
            }

            $this->db->commit();

            return [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $totals['total'],
                'items_for_reservation' => $itemsForReservation
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process payment success
     */
    public function processPaymentSuccess($orderId, $transactionId)
    {
        try {
            // Update payment status
            $this->orderModel->updatePaymentStatus($orderId, 'paid', $transactionId);

            // Get order items
            $orderItems = $this->orderModel->getOrderItems($orderId);

            // Reserve products with vendor
            $reservations = $this->reservationService->reserveOrderProducts($orderId, $orderItems);

            // Update order status
            $this->orderModel->updateStatus($orderId, 'reserved');

            return [
                'success' => true,
                'reservations' => $reservations
            ];
        } catch (Exception $e) {
            // Payment succeeded but reservation failed
            $this->orderModel->updateStatus($orderId, 'payment_pending');
            
            // Log error
            error_log("Reservation failed for order $orderId: " . $e->getMessage());
            
            throw new Exception('Payment successful but product reservation failed. Please contact support.');
        }
    }

    /**
     * Process payment failure
     */
    public function processPaymentFailure($orderId, $reason = null)
    {
        // Update payment status
        $this->orderModel->updatePaymentStatus($orderId, 'failed');
        $this->orderModel->updateStatus($orderId, 'cancelled');

        // Unreserve any products that might have been reserved
        $this->reservationService->unreserveOrderProducts($orderId);
    }

    /**
     * Cancel order
     */
    public function cancelOrder($orderId, $reason = null)
    {
        $order = $this->orderModel->getById($orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        // Can only cancel certain statuses
        $cancellableStatuses = ['pending', 'payment_pending', 'reserved'];
        if (!in_array($order['status'], $cancellableStatuses)) {
            throw new Exception('Order cannot be cancelled at this stage');
        }

        // Unreserve products
        $this->reservationService->unreserveOrderProducts($orderId);

        // Update order status
        $this->orderModel->updateStatus($orderId, 'cancelled');

        // If payment was made, mark for refund
        if ($order['payment_status'] === 'paid') {
            $this->orderModel->updatePaymentStatus($orderId, 'refunded');
        }
    }

    /**
     * Create vendor sales order for reserved items
     */
    public function createVendorSalesOrder()
    {
        // Get all reserved orders ready for vendor submission
        $orders = $this->orderModel->getReadyForVendorSubmission();

        if (empty($orders)) {
            return [
                'success' => true,
                'message' => 'No orders ready for vendor submission',
                'orders_processed' => 0
            ];
        }

        $processedOrders = [];
        $errors = [];

        foreach ($orders as $order) {
            try {
                $reservations = $this->reservationService->getReservationStatus($order['id']);
                
                if (!$reservations['all_reserved']) {
                    continue;
                }

                // Get order items
                $items = $this->orderModel->getOrderItems($order['id']);

                // Prepare vendor order data
                $vendorOrderData = $this->prepareVendorOrderData($order, $items);

                // Call vendor API
                $vendorResponse = $this->vendorApi->createSalesOrder($vendorOrderData);

                if (isset($vendorResponse['success']) && $vendorResponse['success']) {
                    $vendorOrderId = $vendorResponse['orderId'] ?? $vendorResponse['id'];
                    
                    // Update order with vendor order ID
                    $this->orderModel->updateVendorOrder($order['id'], $vendorOrderId);

                    // Mark reservations as ordered
                    $reservationIds = array_column(
                        $this->reservationService->getReservationsForSalesOrder(), 
                        'id'
                    );
                    $this->reservationService->markAsOrdered($reservationIds);

                    $processedOrders[] = $order['order_number'];
                } else {
                    throw new Exception($vendorResponse['message'] ?? 'Vendor order creation failed');
                }
            } catch (Exception $e) {
                $errors[] = [
                    'order_number' => $order['order_number'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => empty($errors),
            'orders_processed' => count($processedOrders),
            'processed_orders' => $processedOrders,
            'errors' => $errors
        ];
    }

    /**
     * Prepare vendor order data
     */
    private function prepareVendorOrderData($order, $items)
    {
        $orderData = [
            'orderNumber' => $order['order_number'],
            'customerEmail' => $order['guest_email'] ?? 'customer@teletrade-hub.com',
            'items' => [],
            'shippingAddress' => $this->orderModel->getFullOrder($order['id'])['shipping_address'] ?? []
        ];

        foreach ($items as $item) {
            $orderData['items'][] = [
                'articleId' => $item['vendor_article_id'],
                'quantity' => $item['quantity']
            ];
        }

        return $orderData;
    }

    /**
     * Validate stock availability
     */
    private function validateStockAvailability($cartItems)
    {
        foreach ($cartItems as $item) {
            $product = $this->productModel->getById($item['product_id']);

            if (!$product || !$product['is_available']) {
                throw new Exception("Product {$item['product_id']} is not available");
            }

            if ($product['available_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for product {$product['name']}");
            }
        }
    }

    /**
     * Calculate order totals
     */
    private function calculateTotals($cartItems)
    {
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $product = $this->productModel->getById($item['product_id']);
            $subtotal += $product['price'] * $item['quantity'];
        }

        // Get tax rate from settings
        $taxRate = $this->getSetting('tax_rate', 19.0) / 100;
        $tax = $subtotal * $taxRate;

        // Get shipping cost
        $shippingThreshold = floatval($this->getSetting('free_shipping_threshold', 100));
        $shipping = $subtotal >= $shippingThreshold 
            ? 0 
            : floatval($this->getSetting('shipping_cost', 9.99));

        $total = $subtotal + $tax + $shipping;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Get setting value
     */
    private function getSetting($key, $default = null)
    {
        $sql = "SELECT value FROM settings WHERE `key` = :key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch();
        
        return $result ? $result['value'] : $default;
    }

    /**
     * Get order details
     */
    public function getOrderDetails($orderId)
    {
        return $this->orderModel->getFullOrder($orderId);
    }
}

