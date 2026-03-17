<?php

if (!class_exists('Env')) {
    require_once __DIR__ . '/../Config/env.php';
}
require_once __DIR__ . '/DiagnosticsLoggerService.php';

/**
 * Triggers on-demand ISR revalidation in the frontend app.
 */
class FrontendRevalidateService
{
    private $url;
    private $secret;

    public function __construct()
    {
        Env::load();
        $this->url = trim((string) Env::get('FRONTEND_REVALIDATE_URL', ''));
        $this->secret = trim((string) Env::get('FRONTEND_REVALIDATE_SECRET', ''));
    }

    public function isConfigured()
    {
        return $this->url !== '' && $this->secret !== '';
    }

    public function trigger(array $payload)
    {
        if (!$this->isConfigured()) {
            DiagnosticsLoggerService::log('frontend.revalidate.skipped', [
                'reason' => 'not_configured',
            ]);
            return false;
        }

        if (!function_exists('curl_init')) {
            error_log('Frontend revalidation skipped: cURL extension not available');
            DiagnosticsLoggerService::log('frontend.revalidate.skipped', [
                'reason' => 'curl_missing',
            ]);
            return false;
        }

        $body = json_encode(array_merge($payload, [
            'secret' => $this->secret,
            'triggered_at' => gmdate('c'),
        ]));

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
        ]);

        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($responseBody === false || $error) {
            error_log('Frontend revalidation request failed: ' . $error);
            DiagnosticsLoggerService::log('frontend.revalidate.failed', [
                'reason' => 'curl_error',
                'error' => $error,
            ]);
            return false;
        }

        if ($status < 200 || $status >= 300) {
            error_log('Frontend revalidation request returned HTTP ' . $status . ': ' . $responseBody);
            DiagnosticsLoggerService::log('frontend.revalidate.failed', [
                'reason' => 'http_error',
                'status' => $status,
                'response' => is_string($responseBody) ? mb_substr($responseBody, 0, 500) : '',
            ]);
            return false;
        }

        DiagnosticsLoggerService::log('frontend.revalidate.success', [
            'status' => $status,
            'payload_keys' => array_keys($payload),
        ]);

        return true;
    }
}
