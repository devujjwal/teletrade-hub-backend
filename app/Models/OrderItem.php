<?php

/**
 * OrderItem Model
 */
class OrderItem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get items by order ID
     */
    public function getByOrderId($orderId)
    {
        $sql = "SELECT * FROM order_items WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Get item by ID
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM order_items WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create order item
     */
    public function create($data)
    {
        $sql = "INSERT INTO order_items (
            order_id, product_id, product_name, product_sku, product_source, vendor_article_id,
            quantity, base_price, price, subtotal, fulfillment_status
        ) VALUES (
            :order_id, :product_id, :product_name, :product_sku, :product_source, :vendor_article_id,
            :quantity, :base_price, :price, :subtotal, :fulfillment_status
        )";

        // Default fulfillment_status if not provided
        if (!isset($data[':fulfillment_status'])) {
            $data[':fulfillment_status'] = 'pending';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update item fulfillment status
     */
    public function updateFulfillmentStatus($itemId, $status, $timestampField = null)
    {
        $sql = "UPDATE order_items SET fulfillment_status = :status";
        
        if ($timestampField === 'reserved_at') {
            $sql .= ", reserved_at = CURRENT_TIMESTAMP";
        } elseif ($timestampField === 'stock_deducted_at') {
            $sql .= ", stock_deducted_at = CURRENT_TIMESTAMP";
        } elseif ($timestampField === 'shipped_at') {
            $sql .= ", shipped_at = CURRENT_TIMESTAMP";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $itemId,
            ':status' => $status
        ]);
    }
    
    /**
     * Get items by product source
     */
    public function getByOrderIdAndSource($orderId, $productSource)
    {
        $sql = "SELECT * FROM order_items 
                WHERE order_id = :order_id AND product_source = :product_source";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_source' => $productSource
        ]);
        return $stmt->fetchAll();
    }
}

