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

    /**
     * Create user_sessions table
     * Remove this endpoint after initial setup
     */
    public function createUserSessionsTable()
    {
        try {
            $db = Database::getConnection();
            
            $sql = "CREATE TABLE IF NOT EXISTS `user_sessions` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT UNSIGNED NOT NULL,
              `token` VARCHAR(255) NOT NULL,
              `ip_address` VARCHAR(45) NULL,
              `user_agent` VARCHAR(500) NULL,
              `expires_at` TIMESTAMP NOT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `token` (`token`),
              UNIQUE KEY `unique_user` (`user_id`),
              KEY `expires_at` (`expires_at`),
              FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($sql);
            
            Response::success([], 'user_sessions table created successfully');
        } catch (Exception $e) {
            Response::error('Error creating table: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update addresses table to support user address book
     * Remove this endpoint after initial setup
     */
    public function updateAddressesTable()
    {
        try {
            $db = Database::getConnection();
            $results = [];
            
            // Add label column
            try {
                $db->exec("ALTER TABLE addresses ADD COLUMN label VARCHAR(100) NULL COMMENT 'Address label (Home, Office, etc.)' AFTER user_id");
                $results['label'] = 'added';
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $results['label'] = 'already_exists';
                } else {
                    throw $e;
                }
            }
            
            // Add street column
            try {
                $db->exec("ALTER TABLE addresses ADD COLUMN street VARCHAR(255) NULL COMMENT 'Street address' AFTER company");
                $results['street'] = 'added';
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $results['street'] = 'already_exists';
                } else {
                    throw $e;
                }
            }
            
            // Add street2 column
            try {
                $db->exec("ALTER TABLE addresses ADD COLUMN street2 VARCHAR(255) NULL COMMENT 'Address line 2' AFTER street");
                $results['street2'] = 'added';
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $results['street2'] = 'already_exists';
                } else {
                    throw $e;
                }
            }
            
            // Update street to NOT NULL after adding (if new records need it)
            try {
                $db->exec("UPDATE addresses SET street = COALESCE(street, address_line1, '') WHERE street IS NULL");
                $db->exec("ALTER TABLE addresses MODIFY COLUMN street VARCHAR(255) NOT NULL COMMENT 'Street address'");
                $results['street_not_null'] = 'updated';
            } catch (Exception $e) {
                $results['street_not_null'] = 'skipped: ' . $e->getMessage();
            }
            
            // Make first_name, last_name, phone optional
            try {
                $db->exec("ALTER TABLE addresses MODIFY COLUMN first_name VARCHAR(100) NULL");
                $results['first_name_nullable'] = 'updated';
            } catch (Exception $e) {
                $results['first_name_nullable'] = 'error: ' . $e->getMessage();
            }
            
            try {
                $db->exec("ALTER TABLE addresses MODIFY COLUMN last_name VARCHAR(100) NULL");
                $results['last_name_nullable'] = 'updated';
            } catch (Exception $e) {
                $results['last_name_nullable'] = 'error: ' . $e->getMessage();
            }
            
            try {
                $db->exec("ALTER TABLE addresses MODIFY COLUMN phone VARCHAR(50) NULL");
                $results['phone_nullable'] = 'updated';
            } catch (Exception $e) {
                $results['phone_nullable'] = 'error: ' . $e->getMessage();
            }
            
            // Change country to support full country names
            try {
                $db->exec("ALTER TABLE addresses MODIFY COLUMN country VARCHAR(100) NOT NULL COMMENT 'Country name or ISO code'");
                $results['country_varchar'] = 'updated';
            } catch (Exception $e) {
                $results['country_varchar'] = 'error: ' . $e->getMessage();
            }
            
            Response::success($results, 'addresses table updated successfully');
        } catch (Exception $e) {
            Response::error('Error updating table: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Debug authentication
     * Remove this endpoint after debugging
     */
    public function debugAuth()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        $debug = [
            'auth_header' => $authHeader ? 'present' : 'missing',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'request_uri' => $_SERVER['REQUEST_URI'],
        ];
        
        if (empty($authHeader)) {
            Response::error('No Authorization header found', 401, $debug);
        }
        
        // Extract token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::error('Invalid authorization format (expected: Bearer {token})', 401, $debug);
        }
        
        $token = $matches[1];
        $debug['token'] = substr($token, 0, 10) . '...' . substr($token, -10);
        
        // Validate token from user_sessions table
        try {
            $db = Database::getConnection();
            
            $sql = "SELECT 
                        us.id as session_id,
                        us.user_id, 
                        us.expires_at,
                        us.created_at,
                        TIMESTAMPDIFF(SECOND, NOW(), us.expires_at) as seconds_until_expiry,
                        u.email,
                        u.first_name,
                        u.last_name,
                        u.is_active
                    FROM user_sessions us
                    JOIN users u ON us.user_id = u.id
                    WHERE us.token = :token";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':token' => $token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                Response::error('Token not found in database', 401, array_merge($debug, [
                    'hint' => 'User may need to log in again'
                ]));
            }
            
            // Check if expired
            if ($session['seconds_until_expiry'] <= 0) {
                Response::error('Token has expired', 401, array_merge($debug, [
                    'session' => $session,
                    'hint' => 'User needs to log in again'
                ]));
            }
            
            // Check if user is active
            if ($session['is_active'] != 1) {
                Response::error('User account is not active', 403, $debug);
            }
            
            // All checks passed
            Response::success([
                'user' => [
                    'id' => $session['user_id'],
                    'email' => $session['email'],
                    'name' => trim($session['first_name'] . ' ' . $session['last_name'])
                ],
                'session' => [
                    'created_at' => $session['created_at'],
                    'expires_at' => $session['expires_at'],
                    'seconds_until_expiry' => $session['seconds_until_expiry'],
                    'hours_until_expiry' => round($session['seconds_until_expiry'] / 3600, 2)
                ]
            ], 'Authentication is valid');
            
        } catch (Exception $e) {
            Response::error('Database error: ' . $e->getMessage(), 500, $debug);
        }
    }
}

