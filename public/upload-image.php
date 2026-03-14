<?php

/**
 * Simple Image Upload Endpoint
 * Handles product image uploads for admin
 */

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Response.php';
require_once __DIR__ . '/../app/Utils/Sanitizer.php';
require_once __DIR__ . '/../app/Middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../app/Services/SupabaseStorageService.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Verify admin authentication
    $authMiddleware = new AuthMiddleware();
    $admin = $authMiddleware->verifyAdmin();
    
    error_log("Upload request received from admin: " . ($admin['username'] ?? 'unknown'));
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        Response::error('No image uploaded or upload error', 400);
    }
    
    $file = $_FILES['image'];
    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!isset($allowedMimeToExt[$mimeType])) {
        Response::error('Invalid file type. Only JPEG, PNG, and WebP are allowed', 400);
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        Response::error('File too large. Maximum size is 5MB', 400);
    }
    
    if (($file['size'] ?? 0) <= 0 || !is_uploaded_file($file['tmp_name'])) {
        Response::error('Invalid upload payload', 400);
    }

    $extension = $allowedMimeToExt[$mimeType];
    $filename = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $objectPath = 'products/' . $filename;

    $storage = new SupabaseStorageService();
    $imageUrl = $storage->uploadFile(
        $file['tmp_name'],
        $mimeType,
        $storage->getProductsBucket(),
        $objectPath
    );
    
    Response::success(['url' => $imageUrl], 'Image uploaded successfully');
    
} catch (Exception $e) {
    error_log("Upload error (Exception): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    Response::error('Upload failed: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Upload error (Error): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    Response::error('Upload failed: ' . $e->getMessage(), 500);
}
