<?php

/**
 * Order Model
 */
class Order
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create new order
     */
    public function create($data)
    {
        $sql = "INSERT INTO orders (
            order_number, user_id, guest_email, status, payment_status, fulfillment_status, payment_method,
            subtotal, tax, shipping_cost, total, currency, billing_address_id,
            shipping_address_id, notes, ip_address, user_agent
        ) VALUES (
            :order_number, :user_id, :guest_email, :status, :payment_status, :fulfillment_status, :payment_method,
            :subtotal, :tax, :shipping_cost, :total, :currency, :billing_address_id,
            :shipping_address_id, :notes, :ip_address, :user_agent
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Get order by ID
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM orders WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get order by order number
     */
    public function getByOrderNumber($orderNumber)
    {
        $sql = "SELECT * FROM orders WHERE order_number = :order_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_number' => $orderNumber]);
        return $stmt->fetch();
    }

    /**
     * Get order with items and addresses
     */
    public function getFullOrder($id)
    {
        $order = $this->getById($id);
        
        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems($id);
        $order['billing_address'] = $this->getAddress($order['billing_address_id']);
        $order['shipping_address'] = $this->getAddress($order['shipping_address_id']);

        return $order;
    }

    /**
     * Get order items
     */
    public function getOrderItems($orderId)
    {
        $sql = "SELECT * FROM order_items WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Add order item
     */
    public function addItem($orderId, $data)
    {
        $sql = "INSERT INTO order_items (
            order_id, product_id, product_name, product_sku, product_source, vendor_article_id,
            quantity, base_price, price, subtotal, fulfillment_status
        ) VALUES (
            :order_id, :product_id, :product_name, :product_sku, :product_source, :vendor_article_id,
            :quantity, :base_price, :price, :subtotal, :fulfillment_status
        )";

        $data[':order_id'] = $orderId;
        // Default fulfillment_status if not provided
        if (!isset($data[':fulfillment_status'])) {
            $data[':fulfillment_status'] = 'pending';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Update order status
     */
    public function updateStatus($id, $status)
    {
        $sql = "UPDATE orders SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $paymentStatus, $transactionId = null)
    {
        $sql = "UPDATE orders SET 
                payment_status = :payment_status,
                payment_transaction_id = :transaction_id,
                paid_at = CASE WHEN :payment_status = 'paid' THEN CURRENT_TIMESTAMP ELSE paid_at END
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':payment_status' => $paymentStatus,
            ':transaction_id' => $transactionId
        ]);
    }

    /**
     * Update vendor order info
     */
    public function updateVendorOrder($id, $vendorOrderId)
    {
        $sql = "UPDATE orders SET 
                vendor_order_id = :vendor_order_id,
                vendor_order_created_at = CURRENT_TIMESTAMP,
                status = 'vendor_ordered'
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':vendor_order_id' => $vendorOrderId
        ]);
    }

    /**
     * Get all orders with filters
     */
    public function getAll($filters = [], $page = 1, $limit = 20)
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT o.*, 
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as customer_name,
                u.email as customer_email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE 1=1";

        if (!empty($filters['status'])) {
            $sql .= " AND o.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $sql .= " AND o.payment_status = :payment_status";
            $params[':payment_status'] = $filters['payment_status'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND o.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (o.order_number LIKE :search OR o.guest_email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count orders
     */
    public function count($filters = [])
    {
        $params = [];
        $sql = "SELECT COUNT(*) FROM orders WHERE 1=1";

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Get address by ID
     */
    private function getAddress($id)
    {
        if (!$id) {
            return null;
        }

        $sql = "SELECT * FROM addresses WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create address
     */
    public function createAddress($data)
    {
        $sql = "INSERT INTO addresses (
            user_id, first_name, last_name, company, address_line1, address_line2,
            city, state, postal_code, country, phone, is_default
        ) VALUES (
            :user_id, :first_name, :last_name, :company, :address_line1, :address_line2,
            :city, :state, :postal_code, :country, :phone, :is_default
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Generate unique order number
     */
    public function generateOrderNumber()
    {
        $prefix = 'TT';
        $timestamp = date('ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        return $prefix . $timestamp . $random;
    }

    /**
     * Get orders ready for vendor submission
     * Returns orders with status 'reserved' that have vendor items
     * Only includes orders with vendor products that are reserved and not yet ordered
     */
    public function getReadyForVendorSubmission()
    {
        $sql = "SELECT DISTINCT o.* FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                WHERE o.status = 'reserved'
                AND o.payment_status = 'paid'
                AND oi.product_source = 'vendor'
                AND oi.fulfillment_status = 'reserved'
                AND o.vendor_order_id IS NULL
                ORDER BY o.paid_at ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get order statistics
     */
    public function getStatistics()
    {
        $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
                SUM(CASE WHEN DATE(created_at) = CURDATE() AND payment_status = 'paid' THEN total ELSE 0 END) as today_revenue
                FROM orders";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
}

