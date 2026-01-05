<?php

/**
 * HTTP Response Utility
 * Standardized JSON responses
 */
class Response
{
    /**
     * Send JSON success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        exit;
    }

    /**
     * Send JSON error response
     */
    public static function error($message = 'Error occurred', $statusCode = 400, $errors = null)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        exit;
    }

    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found')
    {
        self::error($message, 404);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access')
    {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden')
    {
        self::error($message, 403);
    }

    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error')
    {
        self::error($message, 500);
    }

    /**
     * Set CORS headers
     */
    public static function setCorsHeaders()
    {
        $allowedOrigins = explode(',', Env::get('CORS_ALLOWED_ORIGINS', '*'));
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

