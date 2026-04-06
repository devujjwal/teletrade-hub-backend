<?php

require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/OrderItem.php';
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/ReservationService.php';
require_once __DIR__ . '/VendorApiService.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/OrderInvoice.php';
require_once __DIR__ . '/SupabaseStorageService.php';

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
    private $userModel;
    private $orderInvoiceModel;
    private $supabaseStorage;
    private $db;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
        $this->productModel = new Product();
        $this->reservationService = new ReservationService();
        $this->vendorApi = new VendorApiService();
        $this->userModel = new User();
        $this->orderInvoiceModel = new OrderInvoice();
        $this->supabaseStorage = new SupabaseStorageService();
        $this->db = Database::getConnection();
    }

    /**
     * Create new order from cart
     * Accepts either address IDs (for existing addresses) or address data (for new addresses)
     */
    public function createOrder($orderData, $cartItems, $billingAddressId = null, $billingAddress = null, $shippingAddressId = null, $shippingAddress = null)
    {
        // Backward compatibility for older call sites/tests that passed address payloads
        // as positional arguments before explicit address ID support was introduced.
        if (is_array($billingAddressId) && $billingAddress === null) {
            $billingAddress = $billingAddressId;
            $billingAddressId = null;
        }
        if (is_array($shippingAddressId) && $shippingAddress === null) {
            $shippingAddress = $shippingAddressId;
            $shippingAddressId = null;
        }

        // Start transaction
        $this->db->beginTransaction();

        try {
            // Validate stock availability
            $this->validateStockAvailability($cartItems);

            // Calculate totals
            $totals = $this->calculateTotals($cartItems);

            // Handle billing address - use existing ID or create new
            if ($billingAddressId) {
                // Use existing address
                $finalBillingAddressId = $billingAddressId;
            } else if ($billingAddress) {
                // Create new address
                $finalBillingAddressId = $this->orderModel->createAddress($billingAddress);
            } else {
                throw new Exception('Billing address is required');
            }

            // Handle shipping address - use existing ID, create new, or use billing
            if ($shippingAddressId) {
                // Use existing address
                $finalShippingAddressId = $shippingAddressId;
            } else if ($shippingAddress) {
                // Create new address
                $finalShippingAddressId = $this->orderModel->createAddress($shippingAddress);
            } else {
                // Use billing address as shipping
                $finalShippingAddressId = $finalBillingAddressId;
            }

            // Generate order number
            $orderNumber = $this->orderModel->generateOrderNumber();

            // Create order
            $orderId = $this->orderModel->create([
                ':order_number' => $orderNumber,
                ':user_id' => $orderData['user_id'],
                ':guest_email' => null,
                ':status' => 'pending',
                ':payment_status' => 'unpaid',
                ':fulfillment_status' => 'pending',
                ':payment_method' => $orderData['payment_method'] ?? null,
                ':subtotal' => $totals['subtotal'],
                ':tax' => $totals['tax'],
                ':shipping_cost' => 0,
                ':total' => $totals['total'],
                ':final_order_price' => null,
                ':currency' => 'EUR',
                ':billing_address_id' => $finalBillingAddressId,
                ':shipping_address_id' => $finalShippingAddressId,
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

            // Reserve vendor products immediately on order creation.
            $reservations = [];
            $ownItemsProcessed = [];
            $errors = [];

            if (!empty($vendorItems)) {
                try {
                    $reservations = $this->reservationService->reserveOrderProducts($orderId, $vendorItems);
                } catch (Exception $e) {
                    $errors[] = ['type' => 'vendor_reservation', 'error' => $e->getMessage()];
                }
            }

            if (!empty($ownItems)) {
                foreach ($ownItems as $item) {
                    try {
                        $this->productModel->reserveStock($item['product_id'], $item['quantity']);
                        $orderItems = $this->orderModel->getOrderItems($orderId);
                        foreach ($orderItems as $orderItem) {
                            if (
                                intval($orderItem['product_id']) === intval($item['product_id']) &&
                                ($orderItem['product_source'] ?? 'vendor') === 'own'
                            ) {
                                $this->orderItemModel->updateFulfillmentStatus(
                                    $orderItem['id'],
                                    'stock_deducted',
                                    'stock_deducted_at'
                                );
                                $ownItemsProcessed[] = [
                                    'product_id' => $item['product_id'],
                                    'quantity' => $item['quantity']
                                ];
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        $errors[] = [
                            'type' => 'own_stock_deduction',
                            'product_id' => $item['product_id'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            if (!empty($errors) && !empty($errors[0]['type']) && $errors[0]['type'] === 'vendor_reservation') {
                foreach ($ownItemsProcessed as $processedItem) {
                    $this->productModel->releaseStock($processedItem['product_id'], $processedItem['quantity']);
                }
                $vendorErrorMessage = $errors[0]['error'] ?? 'Reservation failed';
                throw new Exception('Failed to reserve vendor products. ' . $vendorErrorMessage);
            }

            if (!empty($vendorItems) && !empty($ownItems)) {
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
                if (!empty($reservations)) {
                    $this->orderModel->updateStatus($orderId, 'reserved');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'vendor_pending');
                }
            } elseif (!empty($ownItems)) {
                if (!empty($ownItemsProcessed)) {
                    $this->orderModel->updateStatus($orderId, 'processing');
                    $this->orderModel->updateFulfillmentStatus($orderId, 'own_fulfilled');
                    $this->orderModel->markOwnItemsFulfilled($orderId);
                }
            }

            $this->db->commit();
            
            // Return customer-friendly response (no internal details)
            $order = $this->orderModel->getById($orderId);
            return [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $totals['total'],
                'status' => $order['status'] ?? 'pending',
                'message' => 'Order created successfully. Your proforma invoice will be shared shortly.'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
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
                return ($item['product_source'] ?? 'vendor') === 'vendor'
                    && ($item['fulfillment_status'] ?? 'pending') === 'pending';
            });
            $ownItems = array_filter($orderItems, function($item) {
                return ($item['product_source'] ?? 'vendor') === 'own'
                    && ($item['fulfillment_status'] ?? 'pending') === 'pending';
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
                        
                        $ownItemsProcessed[] = [
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity']
                        ];
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
                foreach ($ownItemsProcessed as $processedItem) {
                    $this->productModel->releaseStock($processedItem['product_id'], $processedItem['quantity']);
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
        // Unreserve any products that might have been reserved
        $this->reservationService->unreserveOrderProducts($orderId, true);

        $this->releaseOwnProductStock($orderId);

        // Update payment status
        $this->orderModel->updatePaymentStatus($orderId, 'failed');
        $this->orderModel->updateStatus($orderId, 'cancelled');
        $this->orderModel->updateFulfillmentStatus($orderId, 'pending');
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
        $cancellableStatuses = ['pending', 'payment_pending', 'reserved', 'processing'];
        if (!in_array($order['status'], $cancellableStatuses)) {
            throw new Exception('Order cannot be cancelled at this stage');
        }

        // Unreserve products
        $this->reservationService->unreserveOrderProducts($orderId, true);

        $this->releaseOwnProductStock($orderId);

        // Update order status
        $this->orderModel->updateStatus($orderId, 'cancelled');
        $this->orderModel->updateFulfillmentStatus($orderId, 'pending');

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
                
                $orderReservations = $this->reservationService->getReservationsForSalesOrderByOrder($order['id']);
                if (empty($orderReservations)) {
                    continue;
                }

                $vendorReservationIds = array_values(array_filter(array_column($orderReservations, 'vendor_reservation_id')));
                if (empty($vendorReservationIds)) {
                    continue;
                }

                // Call vendor API
                $vendorResponse = $this->vendorApi->createSalesOrder($vendorReservationIds);

                if ($this->isVendorApiSuccess($vendorResponse)) {
                    $vendorOrderId = $vendorResponse['orderId']
                        ?? $vendorResponse['id']
                        ?? $vendorResponse['ReturnVal']
                        ?? ('SO-' . $order['order_number']);
                    
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

                    // Mark only this order's reservations as ordered
                    $reservationIds = array_column($orderReservations, 'id');
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

    private function isVendorApiSuccess($response)
    {
        return (isset($response['success']) && $response['success'] === true)
            || (isset($response['status']) && $response['status'] === 'ok')
            || (isset($response['error']) && intval($response['error']) === 0);
    }

    /**
     * Restore own-product stock that was held for this order.
     */
    private function releaseOwnProductStock($orderId)
    {
        $orderItems = $this->orderItemModel->getByOrderId($orderId);

        foreach ($orderItems as $item) {
            if (($item['product_source'] ?? 'vendor') !== 'own') {
                continue;
            }

            if (!in_array($item['fulfillment_status'] ?? 'pending', ['stock_deducted', 'fulfilled'], true)) {
                continue;
            }

            $this->releaseOwnStockSafely((int) $item['product_id'], (int) $item['quantity']);
            $this->orderItemModel->updateFulfillmentStatus($item['id'], 'pending');
        }
    }

    /**
     * Avoid double releasing own stock if a previous cancellation partially completed.
     */
    private function releaseOwnStockSafely($productId, $quantity)
    {
        $product = $this->productModel->getById($productId);
        if (!$product) {
            return;
        }

        $reservedQuantity = max(0, (int) ($product['reserved_quantity'] ?? 0));
        if ($reservedQuantity === 0) {
            return;
        }

        $releaseQuantity = min($quantity, $reservedQuantity);
        if ($releaseQuantity > 0) {
            $this->productModel->releaseStock($productId, $releaseQuantity);
        }
    }

    /**
     * Validate stock availability
     */
    private function validateStockAvailability($cartItems)
    {
        foreach ($cartItems as $item) {
            $product = $this->productModel->getById($item['product_id']);
            $productLabel = trim((string) ($product['name'] ?? ''));
            if ($productLabel === '') {
                $productLabel = 'Selected product';
            }

            if (!$product) {
                throw new Exception("{$productLabel} is no longer available");
            }

            if (!$product['is_available']) {
                throw new Exception("{$productLabel} is not available");
            }

            if ($product['available_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for {$productLabel}");
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

        $total = $subtotal + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Get setting value
     */
    private function getSetting($key, $default = null)
    {
        $sql = "SELECT value FROM settings WHERE key = :key";
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
        
        $user = null;
        if (!empty($order['user_id'])) {
            $user = $this->userModel->getById($order['user_id']);
        }

        if ($user) {
            $order['customer_email'] = $user['email'] ?? null;
            $order['customer_phone'] = $user['mobile'] ?? ($user['phone'] ?? null);
            $order['customer_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        } else {
            $order['customer_email'] = $order['guest_email'] ?? null;
        }

        $invoice = $this->orderInvoiceModel->getLatestForOrder($orderId);
        if ($invoice) {
            $invoice['signed_url'] = $this->createInvoiceSignedUrl($invoice['invoice_url']);
            $order['invoice'] = $invoice;
        } else {
            $order['invoice'] = null;
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
        unset($order['guest_email']);
        
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

        if (isset($order['invoice']) && is_array($order['invoice'])) {
            unset($order['invoice']['invoice_url']);
            unset($order['invoice']['uploaded_by_admin']);
        }
        
        return $order;
    }

    private function createInvoiceSignedUrl($invoicePath)
    {
        if (!$invoicePath) {
            return null;
        }

        try {
            return $this->supabaseStorage->createSignedUrl(
                $this->supabaseStorage->getInvoiceBucket(),
                ltrim((string) $invoicePath, '/'),
                3600
            );
        } catch (Exception $e) {
            error_log('Failed to create invoice signed URL: ' . $e->getMessage());
            return null;
        }
    }
}
