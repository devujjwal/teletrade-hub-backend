<?php

/**
 * Health Check Controller
 */
class HealthController
{
    /**
     * API root
     */
    public function index()
    {
        Response::success([
            'name' => 'TeleTrade Hub API',
            'version' => '1.0.0',
            'owner' => 'Telecommunication Trading e.K.',
            'status' => 'operational'
        ], 'Welcome to TeleTrade Hub API');
    }

    /**
     * Health check endpoint
     */
    public function check()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => []
        ];

        // Check database connection
        try {
            $db = Database::getConnection();
            $stmt = $db->query('SELECT 1');
            $health['checks']['database'] = 'connected';
        } catch (Exception $e) {
            $health['checks']['database'] = 'failed';
            $health['status'] = 'unhealthy';
        }

        // Check storage directory
        $logDir = __DIR__ . '/../../storage/logs';
        $health['checks']['storage'] = is_writable($logDir) ? 'writable' : 'not_writable';

        Response::success($health, 'Health check completed');
    }
}

