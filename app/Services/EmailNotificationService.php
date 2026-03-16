<?php

require_once __DIR__ . '/SmtpMailerService.php';

/**
 * Transactional email notifications for registration and orders.
 */
class EmailNotificationService
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new SmtpMailerService();
    }

    public function sendAccountCreated($email)
    {
        return $this->send(
            $email,
            'Account Created – TeleTrade Hub',
            "Your account has been created successfully.\n\nYour account will be reviewed and approved subject to document verification.\n\nYou will receive another email once your account is approved. After approval you will be able to login and place orders."
        );
    }

    public function sendAccountApproved($email)
    {
        return $this->send(
            $email,
            'Account Approved',
            'Your TeleTrade Hub account has been approved. You can now login and start placing orders.'
        );
    }

    public function sendAccountRejected($email)
    {
        return $this->send(
            $email,
            'Account Rejected',
            'Unfortunately your account could not be approved. Please contact support for further details.'
        );
    }

    public function sendOrderPlaced($email)
    {
        return $this->send(
            $email,
            'Order Received',
            "Your order has been successfully received.\n\nYou will shortly receive a Proforma Invoice containing the final price including shipping charges.\n\nPlease clear the invoice within 24 hours and confirm payment via email or WhatsApp. Your order will be processed after confirmation."
        );
    }

    public function sendOrderStatusChanged($email, $orderNumber, $status)
    {
        $statusMap = [
            'processing' => [
                'subject' => 'Order Update – Processing',
                'body' => "Your order {$orderNumber} is now processing.\n\nWe have received your payment confirmation and your order is being prepared."
            ],
            'shipped' => [
                'subject' => 'Order Update – Shipped',
                'body' => "Your order {$orderNumber} has been shipped.\n\nWe will keep you informed until it reaches you."
            ],
            'delivered' => [
                'subject' => 'Order Update – Delivered',
                'body' => "Your order {$orderNumber} has been delivered.\n\nThank you for shopping with TeleTrade Hub."
            ],
        ];

        if (!isset($statusMap[$status])) {
            return false;
        }

        return $this->send($email, $statusMap[$status]['subject'], $statusMap[$status]['body']);
    }

    private function send($email, $subject, $message)
    {
        if (!$this->mailer->isConfigured()) {
            error_log('EmailNotificationService skipped send because SMTP is not configured');
            return false;
        }

        try {
            return $this->mailer->send($email, $subject, $message);
        } catch (Exception $e) {
            error_log('Email notification failed: ' . $e->getMessage());
            return false;
        }
    }
}
