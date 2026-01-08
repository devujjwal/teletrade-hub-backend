<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Middlewares/RateLimitMiddleware.php';

/**
 * Auth Controller
 * Handles customer authentication
 */
class AuthController
{
    private $userModel;
    private $rateLimiter;

    public function __construct()
    {
        $this->userModel = new User();
        $this->rateLimiter = new RateLimitMiddleware();
    }

    /**
     * Register new customer
     */
    public function register()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // SECURITY: Rate limiting to prevent registration spam
        $clientIp = RateLimitMiddleware::getClientIdentifier();
        $this->rateLimiter->enforce($clientIp, 'customer_register', 3, 3600); // 3 attempts per hour

        // Validate input with strong password requirement
        $errors = Validator::validate($input, [
            'email' => 'required|email',
            'password' => 'required|min:8|strong_password',
            'first_name' => 'required',
            'last_name' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        // Check if email already exists
        if ($this->userModel->emailExists($input['email'])) {
            // SECURITY: Generic error to prevent email enumeration
            Response::error('Registration failed. Please try again.', 400);
        }

        try {
            // Hash password
            $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);

            // Create user
            $userId = $this->userModel->create([
                ':email' => Sanitizer::email($input['email']),
                ':password_hash' => $passwordHash,
                ':first_name' => Sanitizer::string($input['first_name']),
                ':last_name' => Sanitizer::string($input['last_name']),
                ':phone' => Sanitizer::string($input['phone'] ?? ''),
                ':is_active' => 1
            ]);

            $user = $this->userModel->getById($userId);

            // Remove password hash from response
            unset($user['password_hash']);

            Response::success([
                'user' => $user,
                'message' => 'Registration successful'
            ], 'Registration successful', 201);
        } catch (Exception $e) {
            Response::error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Customer login
     */
    public function login()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // SECURITY: Rate limiting to prevent brute force attacks
        $clientIp = RateLimitMiddleware::getClientIdentifier();
        $this->rateLimiter->enforce($clientIp, 'customer_login', 5, 900); // 5 attempts per 15 minutes

        // Validate input
        $errors = Validator::validate($input, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        try {
            $user = $this->userModel->getByEmail($input['email']);

            if (!$user || !password_verify($input['password'], $user['password_hash'])) {
                // SECURITY: Generic error message to prevent user enumeration
                Response::error('Invalid email or password', 401);
            }

            if (!$user['is_active']) {
                Response::error('Account is disabled', 403);
            }

            // SECURITY: Clear rate limit on successful login
            $this->rateLimiter->clearLimit($clientIp, 'customer_login');

            // Generate a simple token for customer (similar to admin)
            // For now, use a basic token. In production, implement proper session management
            $token = bin2hex(random_bytes(32));
            
            // Store session in database if user_sessions table exists
            // Otherwise, just return the token for client-side storage
            try {
                $db = Database::getConnection();
                $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
                
                $sql = "INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) 
                        VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)
                        ON DUPLICATE KEY UPDATE 
                            token = VALUES(token), 
                            expires_at = VALUES(expires_at),
                            updated_at = CURRENT_TIMESTAMP";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user['id'],
                    ':token' => $token,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    ':expires_at' => $expiresAt
                ]);
            } catch (Exception $e) {
                // If user_sessions table doesn't exist, just continue without storing
                error_log("Customer session storage failed (table may not exist): " . $e->getMessage());
            }

            // Remove password hash from response
            unset($user['password_hash']);

            Response::success([
                'user' => $user,
                'token' => $token,
                'message' => 'Login successful'
            ]);
        } catch (Exception $e) {
            Response::error('Login failed: ' . $e->getMessage(), 500);
        }
    }
}

