<?php

/**
 * HTTP Response Utility
 * Standardized JSON responses
 */
class Response
{
    /**
     * Send JSON success response
     * SECURITY: JSON encoding options prevent XSS
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // SECURITY: JSON_HEX_* options prevent XSS in JSON responses
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        
        exit;
    }

    /**
     * Send JSON error response
     * SECURITY: Sanitize error messages in production
     */
    public static function error($message = 'Error occurred', $statusCode = 400, $errors = null)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // SECURITY: In production, sanitize error messages to prevent info disclosure
        $isProduction = Env::get('APP_ENV') !== 'development';
        if ($isProduction && $statusCode >= 500) {
            $message = 'An internal error occurred. Please contact support.';
            $errors = null; // Don't expose error details in production
        }
        
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        // SECURITY: JSON_HEX_* options prevent XSS in JSON responses
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        
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

}

