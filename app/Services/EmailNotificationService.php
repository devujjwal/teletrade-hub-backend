<?php

require_once __DIR__ . '/../Config/env.php';
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

    private const SUPPORT_EMAIL = 'tctradingek@gmail.com';
    private const SUPPORT_PHONE = '+491737109267';

    private $mailer;

    public function __construct()
    {
        $this->mailer = new SmtpMailerService();
    }

    public function sendAccountCreated($email)
    {
        $subject = 'Account Created - TeleTrade Hub';
        $title = 'Welcome to TeleTrade Hub';
        $lines = [
            'Your account has been created successfully.',
            'Our team will review your account and documents before approval.',
            'You will receive another email once your account is approved and ready for orders.'
        ];

        return $this->sendTemplatedEmail($email, $subject, $title, $lines);
    }

    public function sendAccountApproved($email)
    {
        $subject = 'Account Approved - TeleTrade Hub';
        $title = 'Your Account Is Approved';
        $lines = [
            'Great news. Your TeleTrade Hub account has been approved.',
            'You can now sign in and start placing orders.'
        ];

        return $this->sendTemplatedEmail($email, $subject, $title, $lines, [
            'label' => 'Sign In',
            'url' => $this->joinUrl($this->getFrontendBaseUrl(), '/login')
        ]);
    }

    public function sendAccountRejected($email)
    {
        $subject = 'Account Rejected - TeleTrade Hub';
        $title = 'Account Review Update';
        $lines = [
            'Unfortunately your account could not be approved at this time.',
            'Please contact our support team if you need help with next steps.'
        ];

        return $this->sendTemplatedEmail($email, $subject, $title, $lines, null, [
            'Support email: ' . self::SUPPORT_EMAIL,
            'WhatsApp / phone: ' . self::SUPPORT_PHONE,
        ]);
    }

    public function sendOrderPlaced($email)
    {
        $subject = 'Order Received - TeleTrade Hub';
        $title = 'We Received Your Order';
        $lines = [
            'Thank you. Your order has been successfully received.',
            'You will shortly receive a proforma invoice with the final price including shipping charges.',
            'Please clear the invoice within 24 hours and confirm payment via email or WhatsApp. We will process your order right after confirmation.'
        ];

        return $this->sendTemplatedEmail($email, $subject, $title, $lines);
    }

    public function sendPasswordResetLink($email, $resetUrl, $expiryMinutes)
    {
        $minutes = max(1, (int) $expiryMinutes);
        $subject = 'Reset Your Password - TeleTrade Hub';
        $title = 'Password Reset Request';
        $lines = [
            'We received a request to reset your TeleTrade Hub account password.',
            'Use the button below to choose a new password.',
            'This link expires in ' . $minutes . ' minutes and can only be used once.'
        ];

        return $this->sendTemplatedEmail($email, $subject, $title, $lines, [
            'label' => 'Reset Password',
            'url' => $resetUrl
        ], [
            'If the button does not work, copy and paste this link into your browser:',
            $resetUrl,
            'If you did not request this reset, you can safely ignore this email.'
        ]);
    }

    public function sendAdminSetPasswordNotification($email, $temporaryPassword)
    {
        $subject = 'Your Password Was Reset by Admin - TeleTrade Hub';
        $title = 'Password Reset by Support Team';
        $lines = [
            'Your TeleTrade Hub account password has been reset by our admin team.',
            'Use the temporary password below to sign in, then change it immediately from account settings.'
        ];

        return $this->sendTemplatedEmail($email, $subject, $title, $lines, null, [
            'Temporary password: ' . $temporaryPassword,
            'If you did not request this, contact support immediately.'
        ]);
    }

    public function sendOrderStatusChanged($email, $orderNumber, $status)
    {
        $statusMap = [
            'processing' => [
                'subject' => 'Order Update - Processing',
                'title' => 'Your Order Is Processing',
                'lines' => [
                    'Order ' . $orderNumber . ' is now processing.',
                    'We have received your payment confirmation and started fulfillment.'
                ]
            ],
            'shipped' => [
                'subject' => 'Order Update - Shipped',
                'title' => 'Your Order Has Shipped',
                'lines' => [
                    'Order ' . $orderNumber . ' has been shipped.',
                    'We will keep you informed until delivery.'
                ]
            ],
            'delivered' => [
                'subject' => 'Order Update - Delivered',
                'title' => 'Your Order Was Delivered',
                'lines' => [
                    'Order ' . $orderNumber . ' has been delivered.',
                    'Thank you for shopping with TeleTrade Hub.'
                ]
            ],
        ];

        if (!isset($statusMap[$status])) {
            return false;
        }

        $config = $statusMap[$status];
        return $this->sendTemplatedEmail($email, $config['subject'], $config['title'], $config['lines']);
    }

    public function sendAdminRegistrationNotification(array $userData)
    {
        $subject = sprintf(
            'New User Registration: %s %s',
            (string) ($userData['first_name'] ?? ''),
            (string) ($userData['last_name'] ?? '')
        );

        $details = [
            'User ID' => (string) ($userData['id'] ?? 'N/A'),
            'Account Type' => (string) ($userData['account_type'] ?? 'N/A'),
            'Name' => trim((string) ($userData['first_name'] ?? '') . ' ' . (string) ($userData['last_name'] ?? '')),
            'Email' => (string) ($userData['email'] ?? 'N/A'),
            'Phone' => (string) (((isset($userData['phone']) && $userData['phone'] !== '') ? $userData['phone'] : 'N/A')),
            'Mobile' => (string) (((isset($userData['mobile']) && $userData['mobile'] !== '') ? $userData['mobile'] : 'N/A')),
            'Address' => (string) ($userData['address'] ?? 'N/A'),
            'Postal Code' => (string) ($userData['postal_code'] ?? 'N/A'),
            'City' => (string) ($userData['city'] ?? 'N/A'),
            'Country' => (string) ($userData['country'] ?? 'N/A'),
            'Tax Number' => (string) (($userData['tax_number'] ?? '') !== '' ? $userData['tax_number'] : 'N/A'),
            'VAT Number' => (string) (($userData['vat_number'] ?? '') !== '' ? $userData['vat_number'] : 'N/A'),
            'Delivery Address' => (string) (($userData['delivery_address'] ?? '') !== '' ? $userData['delivery_address'] : 'N/A'),
            'Delivery Postal Code' => (string) (($userData['delivery_postal_code'] ?? '') !== '' ? $userData['delivery_postal_code'] : 'N/A'),
            'Delivery City' => (string) (($userData['delivery_city'] ?? '') !== '' ? $userData['delivery_city'] : 'N/A'),
            'Delivery Country' => (string) (($userData['delivery_country'] ?? '') !== '' ? $userData['delivery_country'] : 'N/A'),
            'Account Holder' => (string) (($userData['account_holder'] ?? '') !== '' ? $userData['account_holder'] : 'N/A'),
            'Bank Name' => (string) (($userData['bank_name'] ?? '') !== '' ? $userData['bank_name'] : 'N/A'),
            'IBAN' => (string) (($userData['iban'] ?? '') !== '' ? $userData['iban'] : 'N/A'),
            'BIC' => (string) (($userData['bic'] ?? '') !== '' ? $userData['bic'] : 'N/A'),
            'Registered At' => (string) ($userData['created_at'] ?? date('c')),
        ];

        $title = 'New User Registration Received';
        $lines = ['A new user has registered on TeleTrade Hub.'];

        return $this->sendToAdmins($subject, $title, $lines, $details, 'registration');
    }

    public function sendAdminOrderPlacedNotification(array $orderData)
    {
        $orderNumber = (string) ($orderData['order_number'] ?? ($orderData['id'] ?? 'N/A'));
        $customerName = trim((string) ($orderData['customer_name'] ?? ''));
        $customerEmail = (string) ($orderData['customer_email'] ?? 'N/A');
        $orderTotal = (string) ($orderData['final_order_price'] ?? $orderData['total'] ?? 'N/A');
        $currency = (string) ($orderData['currency'] ?? 'EUR');
        $createdAt = (string) ($orderData['created_at'] ?? date('c'));

        $subject = 'New Order Placed: ' . $orderNumber;
        $title = 'New Order Received';
        $lines = ['A new order has been placed on TeleTrade Hub.'];

        $details = [
            'Order ID' => (string) ($orderData['id'] ?? ($orderData['order_id'] ?? 'N/A')),
            'Order Number' => $orderNumber,
            'Customer Name' => $customerName !== '' ? $customerName : 'N/A',
            'Customer Email' => $customerEmail,
            'Order Value' => $orderTotal . ' ' . $currency,
            'Order Date/Time' => $createdAt,
        ];

        $items = $orderData['items'] ?? [];
        $itemRows = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $itemRows[] = [
                    'name' => (string) ($item['product_name'] ?? 'Unknown'),
                    'sku' => (string) ($item['product_sku'] ?? 'N/A'),
                    'qty' => (string) ($item['quantity'] ?? '0'),
                    'unit' => (string) ($item['price'] ?? '0'),
                    'subtotal' => (string) ($item['subtotal'] ?? '0'),
                ];
            }
        }

        if (empty($itemRows)) {
            $itemRows[] = [
                'name' => 'No order items found',
                'sku' => 'N/A',
                'qty' => '0',
                'unit' => '0',
                'subtotal' => '0',
            ];
        }

        return $this->sendToAdmins($subject, $title, $lines, $details, 'order', $itemRows);
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

    private function sendToAdmins($subject, $title, array $lines, array $details, $context = 'admin', array $itemRows = [])
    {
        $recipients = $this->getAdminRecipients();
        $allDelivered = true;

        foreach ($recipients as $recipient) {
            $sent = $this->sendTemplatedEmail($recipient, $subject, $title, $lines, null, [], $details, $itemRows);
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

    private function sendTemplatedEmail(
        $email,
        $subject,
        $title,
        array $lines,
        $cta = null,
        array $extraLines = [],
        array $details = [],
        array $itemRows = []
    ) {
        if (!$this->mailer->isConfigured()) {
            error_log('EmailNotificationService skipped send because SMTP is not configured');
            return false;
        }

        $html = $this->renderHtmlTemplate($subject, $title, $lines, $cta, $extraLines, $details, $itemRows);
        $text = $this->renderTextTemplate($subject, $title, $lines, $cta, $extraLines, $details, $itemRows);

        try {
            return $this->mailer->send($email, $subject, $html, true, $text);
        } catch (Exception $e) {
            error_log('Email notification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function renderHtmlTemplate($subject, $title, array $lines, $cta, array $extraLines, array $details, array $itemRows)
    {
        $logoUrl = $this->joinUrl($this->getFrontendBaseUrl(), '/branding/site-logo-dark.png');
        $websiteUrl = $this->getFrontendBaseUrl();

        $introHtml = '';
        foreach ($lines as $line) {
            $introHtml .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.65;color:#243b55;">' . $this->escapeHtml($line) . '</p>';
        }

        $ctaHtml = '';
        if (is_array($cta) && !empty($cta['url']) && !empty($cta['label'])) {
            $ctaHtml = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 20px;">'
                . '<tr><td style="border-radius:8px;background:#0c4a8a;">'
                . '<a href="' . $this->escapeHtml($cta['url']) . '" target="_blank" rel="noopener noreferrer" '
                . 'style="display:inline-block;padding:12px 20px;font-size:16px;font-weight:600;line-height:1;color:#ffffff;text-decoration:none;">'
                . $this->escapeHtml($cta['label'])
                . '</a></td></tr></table>';
        }

        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml .= '<h3 style="margin:20px 0 10px;font-size:18px;line-height:1.4;color:#0f2a44;">Details</h3>';
            $detailsHtml .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 16px;">';
            foreach ($details as $label => $value) {
                $detailsHtml .= '<tr>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;background:#f7fbff;font-size:14px;line-height:1.4;color:#3a4a5e;font-weight:600;width:38%;">' . $this->escapeHtml((string) $label) . '</td>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;background:#ffffff;font-size:14px;line-height:1.4;color:#213348;">' . $this->escapeHtml((string) $value) . '</td>'
                    . '</tr>';
            }
            $detailsHtml .= '</table>';
        }

        $itemsHtml = '';
        if (!empty($itemRows)) {
            $itemsHtml .= '<h3 style="margin:20px 0 10px;font-size:18px;line-height:1.4;color:#0f2a44;">Order Items</h3>';
            $itemsHtml .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 16px;">'
                . '<tr>'
                . '<th align="left" style="padding:8px 10px;border:1px solid #dfe8f2;background:#f0f6ff;font-size:13px;color:#3a4a5e;">Product</th>'
                . '<th align="left" style="padding:8px 10px;border:1px solid #dfe8f2;background:#f0f6ff;font-size:13px;color:#3a4a5e;">SKU</th>'
                . '<th align="left" style="padding:8px 10px;border:1px solid #dfe8f2;background:#f0f6ff;font-size:13px;color:#3a4a5e;">Qty</th>'
                . '<th align="left" style="padding:8px 10px;border:1px solid #dfe8f2;background:#f0f6ff;font-size:13px;color:#3a4a5e;">Unit</th>'
                . '<th align="left" style="padding:8px 10px;border:1px solid #dfe8f2;background:#f0f6ff;font-size:13px;color:#3a4a5e;">Subtotal</th>'
                . '</tr>';

            foreach ($itemRows as $row) {
                $itemsHtml .= '<tr>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;font-size:13px;color:#213348;">' . $this->escapeHtml((string) ($row['name'] ?? '')) . '</td>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;font-size:13px;color:#213348;">' . $this->escapeHtml((string) ($row['sku'] ?? '')) . '</td>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;font-size:13px;color:#213348;">' . $this->escapeHtml((string) ($row['qty'] ?? '')) . '</td>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;font-size:13px;color:#213348;">' . $this->escapeHtml((string) ($row['unit'] ?? '')) . '</td>'
                    . '<td style="padding:8px 10px;border:1px solid #dfe8f2;font-size:13px;color:#213348;">' . $this->escapeHtml((string) ($row['subtotal'] ?? '')) . '</td>'
                    . '</tr>';
            }

            $itemsHtml .= '</table>';
        }

        $extraHtml = '';
        foreach ($extraLines as $line) {
            $extraHtml .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#4d6078;">' . $this->escapeHtml($line) . '</p>';
        }

        return '<!doctype html>'
            . '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<title>' . $this->escapeHtml($subject) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#eef3f8;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef3f8;padding:20px 10px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:700px;background:#ffffff;border-collapse:collapse;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="padding:18px 24px;border-bottom:1px solid #e6edf5;background:#ffffff;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td align="left" style="vertical-align:middle;">'
            . '<a href="' . $this->escapeHtml($websiteUrl) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:#0f2a44;font-size:22px;font-weight:700;">'
            . '<img src="' . $this->escapeHtml($logoUrl) . '" alt="TeleTrade Hub" style="height:44px;max-width:260px;display:block;border:0;">'
            . '</a></td>'
            . '<td align="right" style="vertical-align:middle;font-size:12px;line-height:1.4;color:#7b8da3;">Trusted telecom partner</td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:28px 24px 22px;">'
            . '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.35;color:#0f2a44;">' . $this->escapeHtml($title) . '</h1>'
            . $introHtml
            . $ctaHtml
            . $detailsHtml
            . $itemsHtml
            . $extraHtml
            . '</td></tr>'
            . '<tr><td style="padding:18px 24px;border-top:1px solid #e6edf5;background:#f8fbff;">'
            . '<p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#2d3f55;font-weight:600;">Telecommunication Trading e.K.</p>'
            . '<p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#4b5d74;">Email: <a href="mailto:' . self::SUPPORT_EMAIL . '" style="color:#0c4a8a;text-decoration:none;">' . self::SUPPORT_EMAIL . '</a></p>'
            . '<p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#4b5d74;">WhatsApp / phone: <a href="https://wa.me/491737109267" style="color:#0c4a8a;text-decoration:none;">' . self::SUPPORT_PHONE . '</a></p>'
            . '<p style="margin:8px 0 0;font-size:12px;line-height:1.6;color:#7b8da3;">This is an automated notification from TeleTrade Hub.</p>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';
    }

    private function renderTextTemplate($subject, $title, array $lines, $cta, array $extraLines, array $details, array $itemRows)
    {
        $parts = [
            'TeleTrade Hub',
            '================',
            $title,
            ''
        ];

        foreach ($lines as $line) {
            $parts[] = $line;
        }

        if (is_array($cta) && !empty($cta['url']) && !empty($cta['label'])) {
            $parts[] = '';
            $parts[] = $cta['label'] . ': ' . $cta['url'];
        }

        if (!empty($details)) {
            $parts[] = '';
            $parts[] = 'Details:';
            foreach ($details as $label => $value) {
                $parts[] = '- ' . $label . ': ' . $value;
            }
        }

        if (!empty($itemRows)) {
            $parts[] = '';
            $parts[] = 'Order Items:';
            foreach ($itemRows as $row) {
                $parts[] = '- ' . ($row['name'] ?? '')
                    . ' | SKU: ' . ($row['sku'] ?? '')
                    . ' | Qty: ' . ($row['qty'] ?? '')
                    . ' | Unit: ' . ($row['unit'] ?? '')
                    . ' | Subtotal: ' . ($row['subtotal'] ?? '');
            }
        }

        if (!empty($extraLines)) {
            $parts[] = '';
            foreach ($extraLines as $line) {
                $parts[] = $line;
            }
        }

        $parts[] = '';
        $parts[] = 'Contact';
        $parts[] = 'Email: ' . self::SUPPORT_EMAIL;
        $parts[] = 'WhatsApp / phone: ' . self::SUPPORT_PHONE;

        return implode("\n", $parts);
    }

    private function getFrontendBaseUrl()
    {
        $url = trim((string) Env::get('FRONTEND_URL', ''));
        if ($url === '') {
            $url = trim((string) Env::get('APP_URL', ''));
        }

        if ($url === '') {
            $url = 'https://teletrade-hub.com';
        }

        return rtrim($url, '/');
    }

    private function joinUrl($baseUrl, $path)
    {
        return rtrim((string) $baseUrl, '/') . '/' . ltrim((string) $path, '/');
    }

    private function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
