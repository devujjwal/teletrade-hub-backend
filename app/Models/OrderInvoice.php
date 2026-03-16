<?php

/**
 * Order Invoice Model
 */
class OrderInvoice
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create($data)
    {
        $sql = "INSERT INTO order_invoices (
                    order_id,
                    invoice_url,
                    uploaded_at,
                    uploaded_by_admin
                ) VALUES (
                    :order_id,
                    :invoice_url,
                    :uploaded_at,
                    :uploaded_by_admin
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $this->db->lastInsertId();
    }

    public function getLatestForOrder($orderId)
    {
        $sql = "SELECT * FROM order_invoices
                WHERE order_id = :order_id
                ORDER BY uploaded_at DESC, id DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);

        return $stmt->fetch();
    }
}
