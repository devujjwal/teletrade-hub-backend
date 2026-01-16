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
                ':fulfillment_status' => 'pending',
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

            // Add order items and separate by source
            $vendorItems = [];
            $ownItems = [];
            
            foreach ($cartItems as $item) {
                $product = $this->productModel->getById($item['product_id']);
                
                // Determine product source (default to vendor for backward compatibility)
                $productSource = $product['product_source'] ?? 'vendor';

                $this->orderModel->addItem($orderId, [
                    ':product_id' => $product['id'],
                    ':product_name' => $product['name'],
                    ':product_sku' => $product['sku'],
                    ':product_source' => $productSource,
                    ':vendor_article_id' => $product['vendor_article_id'],
                    ':quantity' => $item['quantity'],
                    ':base_price' => $product['base_price'],
                    ':price' => $product['price'],
                    ':subtotal' => $product['price'] * $item['quantity'],
                    ':fulfillment_status' => 'pending'
                ]);

                // Separate items by source for different processing
                if ($productSource === 'vendor') {
                    $vendorItems[] = [
                        'product_id' => $product['id'],
                        'vendor_article_id' => $product['vendor_article_id'],
                        'quantity' => $item['quantity']
                    ];
                } else {
                    $ownItems[] = [
                        'product_id' => $product['id'],
                        'quantity' => $item['quantity']
                    ];
                }
            }

            $this->db->commit();

            // Determine initial fulfillment status
            $fulfillmentStatus = 'pending';
            if (!empty($vendorItems) && !empty($ownItems)) {
                $fulfillmentStatus = 'pending'; // Mixed order
            } elseif (!empty($vendorItems)) {
                $fulfillmentStatus = 'vendor_pending'; // Vendor only
            } elseif (!empty($ownItems)) {
                $fulfillmentStatus = 'pending'; // Own only
            }
            
            // Update order fulfillment status
            $this->orderModel->updateFulfillmentStatus($orderId, $fulfillmentStatus);

            // Generate guest access token if this is a guest order
            $guestToken = null;
            if (!empty($orderData['guest_email']) && empty($orderData['user_id'])) {
                $guestToken = $this->generateGuestOrderToken($orderNumber, $orderData['guest_email']);
            }
            
            // Return customer-friendly response (no internal details)
            $response = [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $totals['total'],
                'status' => 'pending',
                'message' => 'Order created successfully. Please proceed with payment.'
            ];
            
            // SECURITY: Include guest token for guest orders (store securely on client)
            if ($guestToken) {
                $response['guest_token'] = $guestToken;
                $response['message'] .= ' Save your order access token to track your order.';
            }
            
            return $response;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Generate secure token for guest order access
     * SECURITY: Uses order number, email, and secret key
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
     * Process payment success
     * Handles mixed orders: reserves vendor products, deducts stock for own products
     */
    public function processPaymentSuccess($orderId, $transactionId)
    {
        $this->db->beginTransaction();
        
        try {
            // Update payment status
            $this->orderModel->updatePaymentStatus($orderId, 'paid', $transactionId);

            // Get order items
            $orderItems = $this->orderModel->getOrderItems($orderId);
            
            // Separate vendor and own items
            $vendorItems = array_filter($orderItems, function($item) {
                return ($item['product_source'] ?? 'vendor') === 'vendor';
            });
            $ownItems = array_filter($orderItems, function($item) {
                return ($item['product_source'] ?? 'vendor') === 'own';
            });
            
            $reservations = [];
            $ownItemsProcessed = [];
            $errors = [];

            // Process vendor products: Reserve via API
            if (!empty($vendorItems)) {
                try {
                    $reservations = $this->reservationService->reserveOrderProducts($orderId, $vendorItems);
                } catch (Exception $e) {
                    $errors[] = ['type' => 'vendor_reservation', 'error' => $e->getMessage()];
                    // Don't throw - continue processing own items
                }
            }

            // Process own products: Deduct stock immediately
            if (!empty($ownItems)) {
                foreach ($ownItems as $item) {
                    try {
                        // Deduct stock directly (no reservation needed)
                        $this->productModel->reserveStock($item['product_id'], $item['quantity']);
                        
                        // Update item fulfillment status
                        $this->orderItemModel->updateFulfillmentStatus(
                            $item['id'], 
                            'stock_deducted',
                            'stock_deducted_at'
                        );
                        
                        $ownItemsProcessed[] = $item['id'];
                    } catch (Exception $e) {
                        $errors[] = [
                            'type' => 'own_stock_deduction',
                            'product_id' => $item['product_id'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            // If vendor reservation failed but own items succeeded, rollback own items
            if (!empty($errors) && !empty($errors[0]['type']) && $errors[0]['type'] === 'vendor_reservation') {
                // Rollback own stock deductions
                foreach ($ownItemsProcessed as $itemId) {
                    $item = array_filter($ownItems, function($i) use ($itemId) {
                        return $i['id'] == $itemId;
                    });
                    $item = reset($item);
                    if ($item) {
                        $this->productModel->releaseStock($item['product_id'], $item['quantity']);
                    }
                }
                
                $this->db->rollBack();
                $this->orderModel->updateStatus($orderId, 'payment_pending');
                throw new Exception('Payment successful but product reservation failed. Please contact support.');
            }

            // Update order status based on what was processed
            if (!empty($vendorItems) && !empty($ownItems)) {
                // Mixed order
                if (!empty($reservations) && !empty($ownItemsProcessed)) {
                    $this->orderModel->updateStatus($orderId, 'processing');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'partially_fulfilled');
                    $this->orderModel->markOwnItemsFulfilled($orderId);
                } elseif (!empty($reservations)) {
                    $this->orderModel->updateStatus($orderId, 'reserved');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'vendor_pending');
                } elseif (!empty($ownItemsProcessed)) {
                    $this->orderModel->updateStatus($orderId, 'processing');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'own_fulfilled');
                    $this->orderModel->markOwnItemsFulfilled($orderId);
                }
            } elseif (!empty($vendorItems)) {
                // Vendor only
                if (!empty($reservations)) {
                    $this->orderModel->updateStatus($orderId, 'reserved');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'vendor_pending');
                }
            } elseif (!empty($ownItems)) {
                // Own only
                if (!empty($ownItemsProcessed)) {
                    $this->orderModel->updateStatus($orderId, 'processing');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'own_fulfilled');
                    $this->orderModel->markOwnItemsFulfilled($orderId);
                }
            }

            $this->db->commit();

            // Return customer-friendly response
            $order = $this->orderModel->getById($orderId);
            return [
                'success' => true,
                'order_number' => $order['order_number'],
                'status' => $order['status'],
                'message' => 'Payment processed successfully. Your order is being prepared.'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            
            // Payment succeeded but processing failed
            $this->orderModel->updateStatus($orderId, 'payment_pending');
            
            // Log error
            error_log("Order processing failed for order $orderId: " . $e->getMessage());
            
            throw $e;
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
     * IMPORTANT: Only processes vendor items, own items are handled separately
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
                // Check if order has vendor items
                $orderItems = $this->orderModel->getOrderItems($order['id']);
                $vendorItems = array_filter($orderItems, function($item) {
                    return ($item['product_source'] ?? 'vendor') === 'vendor';
                });
                
                // Skip if no vendor items
                if (empty($vendorItems)) {
                    continue;
                }
                
                $reservations = $this->reservationService->getReservationStatus($order['id']);
                
                // Only proceed if all vendor items are reserved
                if (!$reservations['all_reserved']) {
                    continue;
                }

                // Prepare vendor order data (only vendor items)
                $vendorOrderData = $this->prepareVendorOrderData($order, $orderItems);

                // Call vendor API
                $vendorResponse = $this->vendorApi->createSalesOrder($vendorOrderData);

                if (isset($vendorResponse['success']) && $vendorResponse['success']) {
                    $vendorOrderId = $vendorResponse['orderId'] ?? $vendorResponse['id'];
                    
                    // Update order with vendor order ID
                    $this->orderModel->updateVendorOrder($order['id'], $vendorOrderId);
                    
                    // Update fulfillment status
                    $orderAfterUpdate = $this->orderModel->getById($order['id']);
                    if ($orderAfterUpdate['fulfillment_status'] === 'own_fulfilled') {
                        // Mixed order: own items already fulfilled, vendor items now ordered
                        $this->orderModel->updateFulfillmentStatus($order['id'], 'partially_fulfilled');
                    } else {
                        // Vendor only order
                        $this->orderModel->updateFulfillmentStatus($order['id'], 'vendor_fulfilled');
                    }

                    // Mark vendor item reservations as ordered
                    $reservationIds = array_column(
                        $this->reservationService->getReservationsForSalesOrder(), 
                        'id'
                    );
                    $this->reservationService->markAsOrdered($reservationIds);
                    
                    // Update vendor item fulfillment status
                    foreach ($vendorItems as $item) {
                        $this->orderItemModel->updateFulfillmentStatus($item['id'], 'vendor_ordered');
                    }

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
     * IMPORTANT: Only includes vendor items, excludes own products
     */
    private function prepareVendorOrderData($order, $items)
    {
        // Filter to only vendor items
        $vendorItems = array_filter($items, function($item) {
            return ($item['product_source'] ?? 'vendor') === 'vendor' 
                && !empty($item['vendor_article_id']);
        });
        
        if (empty($vendorItems)) {
            throw new Exception('No vendor items to process');
        }

        $orderData = [
            'orderNumber' => $order['order_number'],
            'customerEmail' => $order['guest_email'] ?? 'customer@teletrade-hub.com',
            'items' => [],
            'shippingAddress' => $this->orderModel->getFullOrder($order['id'])['shipping_address'] ?? []
        ];

        foreach ($vendorItems as $item) {
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
     * For customer-facing responses, sanitizes internal fields
     */
    public function getOrderDetails($orderId, $isAdmin = false)
    {
        $order = $this->orderModel->getFullOrder($orderId);
        
        if (!$order) {
            return null;
        }
        
        // For customers, remove internal fields (product_source, fulfillment_status, etc.)
        if (!$isAdmin) {
            $order = $this->sanitizeOrderForCustomer($order);
        }
        
        return $order;
    }
    
    /**
     * Sanitize order response for customer-facing APIs
     * Removes internal fields like product_source, fulfillment_status, vendor_article_id
     */
    private function sanitizeOrderForCustomer($order)
    {
        // Remove internal order fields
        unset($order['fulfillment_status']);
        unset($order['vendor_order_id']);
        unset($order['vendor_order_created_at']);
        unset($order['own_items_fulfilled_at']);
        unset($order['admin_notes']);
        
        // Sanitize order items
        if (isset($order['items']) && is_array($order['items'])) {
            foreach ($order['items'] as &$item) {
                // Remove internal fields from items
                unset($item['product_source']);
                unset($item['fulfillment_status']);
                unset($item['reserved_at']);
                unset($item['stock_deducted_at']);
                unset($item['vendor_article_id']); // Internal vendor SKU, not customer-facing
            }
        }
        
        return $order;
    }
}

