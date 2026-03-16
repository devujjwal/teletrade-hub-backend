<?php

require_once __DIR__ . '/../Config/env.php';

class SupabaseStorageService
{
    private $supabaseUrl;
    private $serviceRoleKey;
    private $registrationBucket;
    private $productsBucket;
    private $invoiceBucket;
    private static $bucketChecked = [];

    public function __construct()
    {
        $this->supabaseUrl = rtrim((string) Env::get('SUPABASE_URL', ''), '/');
        $this->serviceRoleKey = (string) Env::get('SUPABASE_SECRET', Env::get('SUPABASE_SERVICE_ROLE_KEY', ''));
        $this->registrationBucket = (string) Env::get('SUPABASE_STORAGE_BUCKET_REGISTRATION', 'registration-documents');
        $this->productsBucket = (string) Env::get('SUPABASE_STORAGE_BUCKET_PRODUCTS', 'product-images');
        $this->invoiceBucket = (string) Env::get('SUPABASE_STORAGE_BUCKET_INVOICES', 'order-invoices');
    }

    public function getRegistrationBucket()
    {
        return $this->registrationBucket;
    }

    public function getProductsBucket()
    {
        return $this->productsBucket;
    }

    public function getInvoiceBucket()
    {
        return $this->invoiceBucket;
    }

    public function uploadFile($tmpFilePath, $mimeType, $bucket, $objectPath)
    {
        $this->validateConfig();
        $this->ensureBucketExists($bucket, true);
        $this->uploadObject($tmpFilePath, $mimeType, $bucket, $objectPath);

        return $this->supabaseUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . ltrim($objectPath, '/');
    }

    public function uploadPrivateFile($tmpFilePath, $mimeType, $bucket, $objectPath)
    {
        $this->validateConfig();
        $this->ensureBucketExists($bucket, false);
        $this->uploadObject($tmpFilePath, $mimeType, $bucket, $objectPath);

        return ltrim($objectPath, '/');
    }

    private function uploadObject($tmpFilePath, $mimeType, $bucket, $objectPath)
    {
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
        if (!function_exists('curl_init')) {
            throw new Exception('cURL extension is required for Supabase Storage requests');
        }

        $requestHeaders = array_merge([
            'Authorization: Bearer ' . $this->serviceRoleKey,
            'apikey: ' . $this->serviceRoleKey
        ], $headers);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Unable to connect to Supabase Storage' . ($error ? ': ' . $error : ''));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($headerSize <= 0) {
            throw new Exception('Unable to connect to Supabase Storage');
        }

        return [
            'status' => $status,
            'body' => substr($response, $headerSize)
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
