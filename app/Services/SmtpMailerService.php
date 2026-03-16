<?php

require_once __DIR__ . '/../Config/env.php';

/**
 * Lightweight SMTP mailer for transactional notifications.
 */
class SmtpMailerService
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $timeout;

    public function __construct()
    {
        $googleEmail = (string) Env::get('GOOGLE_APP_EMAIL', '');
        $googlePassword = (string) Env::get('GOOGLE_APP_PASSWORD', '');
        $useGoogleAppSmtp = $googleEmail !== '' && $googlePassword !== '';

        if ($useGoogleAppSmtp) {
            $this->host = 'smtp.gmail.com';
            $this->port = 587;
            $this->username = $googleEmail;
            $this->password = $googlePassword;
            $this->fromEmail = (string) Env::get('SMTP_FROM_EMAIL', $googleEmail);
        } else {
            $this->host = (string) Env::get('SMTP_HOST', '');
            $this->port = (int) Env::get('SMTP_PORT', 587);
            $this->username = (string) Env::get('SMTP_USER', '');
            $this->password = (string) Env::get('SMTP_PASSWORD', '');
            $this->fromEmail = (string) Env::get('SMTP_FROM_EMAIL', '');
        }

        $this->fromName = (string) Env::get('SMTP_FROM_NAME', 'TeleTrade Hub');
        $this->timeout = 15;
    }

    public function isConfigured()
    {
        return $this->host !== ''
            && $this->port > 0
            && $this->username !== ''
            && $this->password !== ''
            && $this->fromEmail !== '';
    }

    public function send($toEmail, $subject, $message)
    {
        if (!$this->isConfigured()) {
            throw new Exception('SMTP is not configured');
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid recipient email address');
        }

        $transportHost = $this->port === 465 ? 'ssl://' . $this->host : $this->host;
        $socket = @stream_socket_client(
            $transportHost . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new Exception('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO teletrade-hub.local', [250]);

            if ($this->port !== 465) {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Failed to enable TLS for SMTP connection');
                }
                $this->command($socket, 'EHLO teletrade-hub.local', [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($this->username), [334]);
            $this->command($socket, base64_encode($this->password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $this->fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $headers = [
                'From: ' . $this->formatAddress($this->fromEmail, $this->fromName),
                'To: ' . $this->formatAddress($toEmail, $toEmail),
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit'
            ];

            $body = implode("\r\n", $headers) . "\r\n\r\n" . $this->escapeBody($message) . "\r\n.";
            $this->command($socket, $body, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function command($socket, $command, $expectedCodes)
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, $expectedCodes)
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new Exception('Empty response from SMTP server');
        }

        $status = (int) substr($response, 0, 3);
        if (!in_array($status, $expectedCodes, true)) {
            throw new Exception('SMTP error: ' . trim($response));
        }

        return $response;
    }

    private function formatAddress($email, $name)
    {
        $safeName = addcslashes($name, '"\\');
        return '"' . $safeName . '" <' . $email . '>';
    }

    private function encodeHeader($value)
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function escapeBody($body)
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $body));
        $normalized = preg_replace('/^\./m', '..', $normalized);
        return str_replace("\n", "\r\n", $normalized);
    }
}
