<?php

require_once __DIR__ . '/../Models/Reservation.php';
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/VendorApiService.php';

/**
 * Reservation Service
 * Manages product reservations with vendor
 */
class ReservationService
{
    private $vendorApi;
    private $reservationModel;
    private $productModel;
    private $db;

    public function __construct()
    {
        $this->vendorApi = new VendorApiService();
        $this->reservationModel = new Reservation();
        $this->productModel = new Product();
        $this->db = Database::getConnection();
    }

    /**
     * Reserve products for an order
     */
    public function reserveOrderProducts($orderId, $orderItems)
    {
        $reservations = [];
        $errors = [];

        foreach ($orderItems as $item) {
            try {
                $reservation = $this->reserveProduct(
                    $orderId,
                    $item['product_id'],
                    $item['vendor_article_id'],
                    $item['quantity']
                );
                $reservations[] = $reservation;
            } catch (Exception $e) {
                $errors[] = [
                    'product_id' => $item['product_id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // If any reservation failed, unreserve all successful ones
        if (!empty($errors)) {
            foreach ($reservations as $reservation) {
                $this->unreserveProduct($reservation['id']);
            }
            
            throw new Exception('Failed to reserve all products: ' . json_encode($errors));
        }

        return $reservations;
    }

    /**
     * Reserve single product
     */
    public function reserveProduct($orderId, $productId, $vendorArticleId, $quantity)
    {
        // Create local reservation record
        $reservationId = $this->reservationModel->create([
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':vendor_article_id' => $vendorArticleId,
            ':quantity' => $quantity,
            ':status' => 'pending'
        ]);

        try {
            // Call vendor API to reserve
            $vendorResponse = $this->vendorApi->reserveArticle($vendorArticleId, $quantity);

            if (isset($vendorResponse['success']) && $vendorResponse['success']) {
                // Update reservation with vendor ID
                $vendorReservationId = $vendorResponse['reservationId'] ?? $vendorResponse['id'];
                $this->reservationModel->updateVendorReservation($reservationId, $vendorReservationId);

                // Update local stock
                $this->productModel->reserveStock($productId, $quantity);

                return [
                    'id' => $reservationId,
                    'vendor_reservation_id' => $vendorReservationId,
                    'status' => 'reserved'
                ];
            } else {
                throw new Exception($vendorResponse['message'] ?? 'Reservation failed');
            }
        } catch (Exception $e) {
            // Mark reservation as failed
            $this->reservationModel->setError($reservationId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Unreserve product
     */
    public function unreserveProduct($reservationId)
    {
        $reservation = $this->reservationModel->getById($reservationId);

        if (!$reservation) {
            throw new Exception('Reservation not found');
        }

        // Only unreserve if it was successfully reserved
        if ($reservation['status'] !== 'reserved' || !$reservation['vendor_reservation_id']) {
            return;
        }

        try {
            // Call vendor API to unreserve
            $this->vendorApi->unreserveArticle($reservation['vendor_reservation_id']);

            // Update reservation status
            $this->reservationModel->updateStatus($reservationId, 'unreserved');

            // Release local stock
            $this->productModel->releaseStock($reservation['product_id'], $reservation['quantity']);
        } catch (Exception $e) {
            // Log error but don't throw - best effort unreservation
            error_log("Failed to unreserve: " . $e->getMessage());
            $this->reservationModel->setError($reservationId, $e->getMessage());
        }
    }

    /**
     * Unreserve all products for an order
     */
    public function unreserveOrderProducts($orderId)
    {
        $reservations = $this->reservationModel->getByOrderId($orderId);

        foreach ($reservations as $reservation) {
            if ($reservation['status'] === 'reserved') {
                $this->unreserveProduct($reservation['id']);
            }
        }
    }

    /**
     * Get reservation status
     */
    public function getReservationStatus($orderId)
    {
        $reservations = $this->reservationModel->getByOrderId($orderId);

        $status = [
            'total' => count($reservations),
            'reserved' => 0,
            'failed' => 0,
            'pending' => 0,
            'ordered' => 0
        ];

        foreach ($reservations as $reservation) {
            $status[$reservation['status']]++;
        }

        $status['all_reserved'] = $status['reserved'] === $status['total'];

        return $status;
    }

    /**
     * Check if order is fully reserved
     */
    public function isOrderFullyReserved($orderId)
    {
        $status = $this->getReservationStatus($orderId);
        return $status['all_reserved'];
    }

    /**
     * Get reservations ready for sales order
     */
    public function getReservationsForSalesOrder()
    {
        return $this->reservationModel->getReadyForSalesOrder();
    }

    /**
     * Mark reservations as ordered
     */
    public function markAsOrdered($reservationIds)
    {
        return $this->reservationModel->markAsOrdered($reservationIds);
    }
}

