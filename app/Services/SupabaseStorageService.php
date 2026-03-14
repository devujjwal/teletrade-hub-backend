<?php

require_once __DIR__ . '/../Config/env.php';

class SupabaseStorageService
{
    private $supabaseUrl;
    private $serviceRoleKey;
    private $registrationBucket;
    private $productsBucket;
    private static $bucketChecked = [];

    public function __construct()
    {
        $this->supabaseUrl = rtrim((string) Env::get('SUPABASE_URL', ''), '/');
        $this->serviceRoleKey = (string) Env::get('SUPABASE_SECRET', Env::get('SUPABASE_SERVICE_ROLE_KEY', ''));
        $this->registrationBucket = (string) Env::get('SUPABASE_STORAGE_BUCKET_REGISTRATION', 'registration-documents');
        $this->productsBucket = (string) Env::get('SUPABASE_STORAGE_BUCKET_PRODUCTS', 'product-images');
    }

    public function getRegistrationBucket()
    {
        return $this->registrationBucket;
    }

    public function getProductsBucket()
    {
        return $this->productsBucket;
    }

    public function uploadFile($tmpFilePath, $mimeType, $bucket, $objectPath)
    {
        $this->validateConfig();
        $this->ensureBucketExists($bucket, true);

        $fileContents = @file_get_contents($tmpFilePath);
        if ($fileContents === false) {
            throw new Exception('Unable to read uploaded file');
        }

        $endpoint = $this->supabaseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . ltrim($objectPath, '/');
        $response = $this->request('POST', $endpoint, $fileContents, [
            'Content-Type: ' . $mimeType,
            'x-upsert: false'
        ]);

        if ($response['status'] >= 300) {
            $details = $this->decodeJson($response['body']);
            $errorMessage = $details['message'] ?? $details['error'] ?? 'Supabase upload failed';
            throw new Exception($errorMessage);
        }

        return $this->supabaseUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . ltrim($objectPath, '/');
    }

    public function deleteFile($bucket, $objectPath)
    {
        $this->validateConfig();
        $endpoint = $this->supabaseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . ltrim($objectPath, '/');
        $response = $this->request('DELETE', $endpoint);

        // 404 is safe to ignore during cleanup.
        if ($response['status'] >= 300 && $response['status'] !== 404) {
            $details = $this->decodeJson($response['body']);
            $errorMessage = $details['message'] ?? $details['error'] ?? 'Supabase delete failed';
            throw new Exception($errorMessage);
        }
    }

    public function createSignedUrl($bucket, $objectPath, $expiresIn = 3600)
    {
        $this->validateConfig();
        $normalizedPath = ltrim((string) $objectPath, '/');
        if ($normalizedPath === '') {
            throw new Exception('Object path is required for signed URL');
        }

        $endpoint = $this->supabaseUrl . '/storage/v1/object/sign/' . rawurlencode($bucket) . '/' . $normalizedPath;
        $payload = json_encode([
            'expiresIn' => max(60, (int) $expiresIn)
        ]);

        $response = $this->request('POST', $endpoint, $payload, [
            'Content-Type: application/json'
        ]);

        if ($response['status'] >= 300) {
            $details = $this->decodeJson($response['body']);
            $errorMessage = $details['message'] ?? $details['error'] ?? 'Unable to create signed URL';
            throw new Exception($errorMessage);
        }

        $details = $this->decodeJson($response['body']);
        $signedUrlPath = $details['signedURL'] ?? $details['signedUrl'] ?? null;
        if (!$signedUrlPath) {
            throw new Exception('Invalid signed URL response from Supabase');
        }

        if (strpos($signedUrlPath, 'http://') === 0 || strpos($signedUrlPath, 'https://') === 0) {
            return $signedUrlPath;
        }

        return $this->supabaseUrl . '/storage/v1' . (strpos($signedUrlPath, '/') === 0 ? $signedUrlPath : '/' . $signedUrlPath);
    }

    private function ensureBucketExists($bucket, $isPublic)
    {
        if (isset(self::$bucketChecked[$bucket])) {
            return;
        }

        $getEndpoint = $this->supabaseUrl . '/storage/v1/bucket/' . rawurlencode($bucket);
        $getResponse = $this->request('GET', $getEndpoint);

        if ($getResponse['status'] === 200) {
            self::$bucketChecked[$bucket] = true;
            return;
        }

        if ($getResponse['status'] !== 404) {
            $details = $this->decodeJson($getResponse['body']);
            $errorMessage = $details['message'] ?? $details['error'] ?? 'Unable to access Supabase bucket';
            throw new Exception($errorMessage);
        }

        $createEndpoint = $this->supabaseUrl . '/storage/v1/bucket';
        $payload = json_encode([
            'id' => $bucket,
            'name' => $bucket,
            'public' => (bool) $isPublic
        ]);

        $createResponse = $this->request('POST', $createEndpoint, $payload, ['Content-Type: application/json']);
        if ($createResponse['status'] >= 300 && $createResponse['status'] !== 409) {
            $details = $this->decodeJson($createResponse['body']);
            $errorMessage = $details['message'] ?? $details['error'] ?? 'Unable to create Supabase bucket';
            throw new Exception($errorMessage);
        }

        self::$bucketChecked[$bucket] = true;
    }

    private function request($method, $url, $body = null, $headers = [])
    {
        $requestHeaders = array_merge([
            'Authorization: Bearer ' . $this->serviceRoleKey,
            'apikey: ' . $this->serviceRoleKey
        ], $headers);

        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $requestHeaders) . "\r\n",
                'ignore_errors' => true,
                'timeout' => 30
            ]
        ];

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        $context = stream_context_create($options);
        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?: [])
            : [];

        if ($responseBody === false && empty($responseHeaders)) {
            throw new Exception('Unable to connect to Supabase Storage');
        }

        $status = 0;
        if (!empty($responseHeaders[0]) && preg_match('#\s(\d{3})\s#', $responseHeaders[0], $matches)) {
            $status = (int) $matches[1];
        }

        return [
            'status' => $status,
            'body' => $responseBody === false ? '' : $responseBody
        ];
    }

    private function decodeJson($json)
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function validateConfig()
    {
        if ($this->supabaseUrl === '' || $this->serviceRoleKey === '') {
            throw new Exception('Supabase Storage is not configured. Set SUPABASE_URL and SUPABASE_SECRET.');
        }
    }
}
