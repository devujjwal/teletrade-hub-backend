<?php

/**
 * Reservation Model
 */
class Reservation
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create reservation
     */
    public function create($data)
    {
        $sql = "INSERT INTO reservations (
            order_id, product_id, vendor_article_id, quantity, status
        ) VALUES (
            :order_id, :product_id, :vendor_article_id, :quantity, :status
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Get reservation by ID
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM reservations WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get reservations by order ID
     */
    public function getByOrderId($orderId)
    {
        $sql = "SELECT * FROM reservations WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Update reservation status
     */
    public function updateStatus($id, $status, $vendorResponse = null)
    {
        $sql = "UPDATE reservations SET 
                status = :status,
                vendor_response = :vendor_response,
                reserved_at = CASE WHEN :status = 'reserved' THEN NOW() ELSE reserved_at END,
                unreserved_at = CASE WHEN :status = 'unreserved' THEN NOW() ELSE unreserved_at END,
                ordered_at = CASE WHEN :status = 'ordered' THEN NOW() ELSE ordered_at END
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':vendor_response' => $vendorResponse ? json_encode($vendorResponse) : null
        ]);
    }

    /**
     * Update with vendor reservation ID
     */
    public function updateVendorReservation($id, $vendorReservationId, $status = 'reserved')
    {
        $sql = "UPDATE reservations SET 
                vendor_reservation_id = :vendor_reservation_id,
                status = :status,
                reserved_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':vendor_reservation_id' => $vendorReservationId,
            ':status' => $status
        ]);
    }

    /**
     * Set error
     */
    public function setError($id, $errorMessage)
    {
        $sql = "UPDATE reservations SET 
                status = 'failed',
                error_message = :error_message
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':error_message' => $errorMessage
        ]);
    }

    /**
     * Get reservations ready for sales order
     */
    public function getReadyForSalesOrder()
    {
        $sql = "SELECT r.*, p.vendor_article_id, p.name as product_name
                FROM reservations r
                JOIN products p ON r.product_id = p.id
                WHERE r.status = 'reserved'
                AND r.vendor_reservation_id IS NOT NULL
                ORDER BY r.reserved_at ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Mark as ordered
     */
    public function markAsOrdered($reservationIds)
    {
        if (empty($reservationIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $sql = "UPDATE reservations SET 
                status = 'ordered',
                ordered_at = NOW()
                WHERE id IN ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($reservationIds);
    }

    /**
     * Get reservations by status
     */
    public function getByStatus($status, $limit = 100)
    {
        $sql = "SELECT * FROM reservations WHERE status = :status ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

