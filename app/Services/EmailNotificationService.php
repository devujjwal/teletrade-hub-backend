<?php

require_once __DIR__ . '/SmtpMailerService.php';

/**
 * Transactional email notifications for registration and orders.
 */
class EmailNotificationService
{
    private const DEFAULT_ADMIN_RECIPIENTS = [
        'info@telecotrade.com',
        'tctradingek@gmail.com'
    ];

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

    public function sendPasswordResetLink($email, $resetUrl, $expiryMinutes)
    {
        $minutes = max(1, (int)$expiryMinutes);
        $subject = 'Reset Your Password – TeleTrade Hub';
        $body = implode("\n", [
            'We received a request to reset your TeleTrade Hub account password.',
            '',
            'Use the link below to choose a new password:',
            $resetUrl,
            '',
            'This link will expire in ' . $minutes . ' minutes and can only be used once.',
            '',
            'If you did not request this reset, you can safely ignore this email.',
        ]);

        return $this->send($email, $subject, $body);
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

    public function sendAdminRegistrationNotification(array $userData)
    {
        $subject = sprintf(
            'New User Registration: %s %s',
            (string) ($userData['first_name'] ?? ''),
            (string) ($userData['last_name'] ?? '')
        );

        $lines = [
            'A new user has registered on TeleTrade Hub.',
            '',
            'Registration Details:',
            'User ID: ' . ($userData['id'] ?? 'N/A'),
            'Account Type: ' . ($userData['account_type'] ?? 'N/A'),
            'Name: ' . trim((string) ($userData['first_name'] ?? '') . ' ' . (string) ($userData['last_name'] ?? '')),
            'Email: ' . ($userData['email'] ?? 'N/A'),
            'Phone: ' . ((isset($userData['phone']) && $userData['phone'] !== '') ? $userData['phone'] : 'N/A'),
            'Mobile: ' . ((isset($userData['mobile']) && $userData['mobile'] !== '') ? $userData['mobile'] : 'N/A'),
            'Address: ' . ($userData['address'] ?? 'N/A'),
            'Postal Code: ' . ($userData['postal_code'] ?? 'N/A'),
            'City: ' . ($userData['city'] ?? 'N/A'),
            'Country: ' . ($userData['country'] ?? 'N/A'),
            'Tax Number: ' . (($userData['tax_number'] ?? '') !== '' ? $userData['tax_number'] : 'N/A'),
            'VAT Number: ' . (($userData['vat_number'] ?? '') !== '' ? $userData['vat_number'] : 'N/A'),
            'Delivery Address: ' . (($userData['delivery_address'] ?? '') !== '' ? $userData['delivery_address'] : 'N/A'),
            'Delivery Postal Code: ' . (($userData['delivery_postal_code'] ?? '') !== '' ? $userData['delivery_postal_code'] : 'N/A'),
            'Delivery City: ' . (($userData['delivery_city'] ?? '') !== '' ? $userData['delivery_city'] : 'N/A'),
            'Delivery Country: ' . (($userData['delivery_country'] ?? '') !== '' ? $userData['delivery_country'] : 'N/A'),
            'Account Holder: ' . (($userData['account_holder'] ?? '') !== '' ? $userData['account_holder'] : 'N/A'),
            'Bank Name: ' . (($userData['bank_name'] ?? '') !== '' ? $userData['bank_name'] : 'N/A'),
            'IBAN: ' . (($userData['iban'] ?? '') !== '' ? $userData['iban'] : 'N/A'),
            'BIC: ' . (($userData['bic'] ?? '') !== '' ? $userData['bic'] : 'N/A'),
            'Registered At: ' . ($userData['created_at'] ?? date('c'))
        ];

        return $this->sendToAdmins($subject, implode("\n", $lines), 'registration');
    }

    public function sendAdminOrderPlacedNotification(array $orderData)
    {
        $orderNumber = $orderData['order_number'] ?? ($orderData['id'] ?? 'N/A');
        $customerName = trim((string) ($orderData['customer_name'] ?? ''));
        $customerEmail = (string) ($orderData['customer_email'] ?? 'N/A');
        $orderTotal = $orderData['final_order_price'] ?? $orderData['total'] ?? 'N/A';
        $currency = (string) ($orderData['currency'] ?? 'EUR');
        $createdAt = (string) ($orderData['created_at'] ?? date('c'));

        $items = $orderData['items'] ?? [];
        $itemLines = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $itemLines[] = sprintf(
                    '- %s | SKU: %s | Qty: %s | Unit: %s | Subtotal: %s',
                    (string) ($item['product_name'] ?? 'Unknown'),
                    (string) ($item['product_sku'] ?? 'N/A'),
                    (string) ($item['quantity'] ?? '0'),
                    (string) ($item['price'] ?? '0'),
                    (string) ($item['subtotal'] ?? '0')
                );
            }
        }
        if (empty($itemLines)) {
            $itemLines[] = '- No order items found';
        }

        $subject = 'New Order Placed: ' . $orderNumber;
        $message = implode("\n", [
            'A new order has been placed on TeleTrade Hub.',
            '',
            'Order Details:',
            'Order ID: ' . ($orderData['id'] ?? ($orderData['order_id'] ?? 'N/A')),
            'Order Number: ' . $orderNumber,
            'Customer Name: ' . ($customerName !== '' ? $customerName : 'N/A'),
            'Customer Email: ' . $customerEmail,
            'Order Value: ' . $orderTotal . ' ' . $currency,
            'Order Date/Time: ' . $createdAt,
            '',
            'Ordered Items:',
            implode("\n", $itemLines)
        ]);

        return $this->sendToAdmins($subject, $message, 'order');
    }

    public function getAdminNotificationHealth()
    {
        $recipients = $this->getAdminRecipients();

        return [
            'smtp_configured' => $this->mailer->isConfigured(),
            'admin_recipients' => $recipients,
            'recipient_count' => count($recipients)
        ];
    }

    private function sendToAdmins($subject, $message, $context = 'admin')
    {
        $recipients = $this->getAdminRecipients();
        $allDelivered = true;

        foreach ($recipients as $recipient) {
            $sent = $this->send($recipient, $subject, $message);
            if (!$sent) {
                $allDelivered = false;
                error_log(sprintf(
                    'Admin notification delivery failed (%s) for recipient %s',
                    $context,
                    $recipient
                ));
            }
        }

        return $allDelivered;
    }

    private function getAdminRecipients()
    {
        $configured = trim((string) Env::get('ADMIN_NOTIFICATION_EMAILS', ''));
        $fromEnv = [];

        if ($configured !== '') {
            $parts = preg_split('/[,\s;]+/', $configured);
            foreach ($parts as $email) {
                $email = trim((string) $email);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $fromEnv[] = strtolower($email);
                }
            }
        }

        $all = array_merge(self::DEFAULT_ADMIN_RECIPIENTS, $fromEnv);
        $all = array_values(array_unique(array_map('strtolower', $all)));

        return array_values(array_filter($all, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        }));
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
