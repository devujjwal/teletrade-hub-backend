<?php

/**
 * Simple Image Upload Endpoint
 * Handles product image uploads for admin
 */

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Utils/Response.php';
require_once __DIR__ . '/../app/Middlewares/AuthMiddleware.php';

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
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        Response::error('No image uploaded or upload error', 400);
    }
    
    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        Response::error('Invalid file type. Only JPEG, PNG, and WebP are allowed', 400);
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        Response::error('File too large. Maximum size is 5MB', 400);
    }
    
    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . '/uploads/products';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '_' . time() . '.' . $extension;
    $filepath = $uploadsDir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        Response::error('Failed to save uploaded file', 500);
    }
    
    // Return URL (relative path for database storage)
    $imageUrl = '/uploads/products/' . $filename;
    
    Response::success(['url' => $imageUrl], 'Image uploaded successfully');
    
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    Response::error('Upload failed: ' . $e->getMessage(), 500);
}
