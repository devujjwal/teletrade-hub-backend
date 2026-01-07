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
            order_id, product_id, product_name, product_sku, vendor_article_id,
            quantity, base_price, price, subtotal
        ) VALUES (
            :order_id, :product_id, :product_name, :product_sku, :vendor_article_id,
            :quantity, :base_price, :price, :subtotal
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }
}

