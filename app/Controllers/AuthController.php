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

    /**
     * Get current authenticated user
     */
    public function me()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        // Remove password hash from response
        unset($user['password_hash']);

        Response::success(['user' => $user]);
    }

    /**
     * Update user profile
     */
    public function updateProfile()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Validate input
        $errors = Validator::validate($input, [
            'first_name' => 'sometimes|required',
            'last_name' => 'sometimes|required',
            'phone' => 'sometimes|string'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        try {
            $updateData = [];
            if (isset($input['first_name'])) {
                $updateData[':first_name'] = Sanitizer::string($input['first_name']);
            }
            if (isset($input['last_name'])) {
                $updateData[':last_name'] = Sanitizer::string($input['last_name']);
            }
            if (isset($input['phone'])) {
                $updateData[':phone'] = Sanitizer::string($input['phone']);
            }

            if (empty($updateData)) {
                Response::error('No fields to update', 400);
            }

            $this->userModel->update($user['id'], $updateData);

            // Get updated user
            $updatedUser = $this->userModel->getById($user['id']);
            unset($updatedUser['password_hash']);

            Response::success(['user' => $updatedUser], 'Profile updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Validate input
        $errors = Validator::validate($input, [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        // Check if new passwords match
        if ($input['new_password'] !== $input['confirm_password']) {
            Response::error('New passwords do not match', 400);
        }

        // Verify current password
        if (!password_verify($input['current_password'], $user['password_hash'])) {
            Response::error('Current password is incorrect', 400);
        }

        try {
            // Hash new password
            $newPasswordHash = password_hash($input['new_password'], PASSWORD_DEFAULT);

            // Update password
            $db = Database::getConnection();
            $sql = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':password_hash' => $newPasswordHash,
                ':id' => $user['id']
            ]);

            Response::success([], 'Password changed successfully');
        } catch (Exception $e) {
            Response::error('Failed to change password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::success([], 'Already logged out');
        }

        try {
            // Get token
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            
            if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                
                // Delete session
                $db = Database::getConnection();
                $sql = "DELETE FROM user_sessions WHERE token = :token";
                $stmt = $db->prepare($sql);
                $stmt->execute([':token' => $token]);
            }

            Response::success([], 'Logged out successfully');
        } catch (Exception $e) {
            Response::error('Failed to logout: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user addresses
     */
public function getAddresses()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        try {
            $addresses = $this->userModel->getAddresses($user['id']);
            Response::success(['addresses' => $addresses]);
        } catch (Exception $e) {
            Response::error('Failed to fetch addresses: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new address
     */
    public function createAddress()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Check that at least street or address_line1 is provided
        if (empty($input['street']) && empty($input['address_line1'])) {
            Response::error('Street address is required', 400);
        }

        // Validate input (both street formats are optional since we check above)
        $errors = Validator::validate($input, [
            'label' => 'sometimes|string',
            'street' => 'sometimes|string',
            'address_line1' => 'sometimes|string',
            'street2' => 'sometimes|string',
            'address_line2' => 'sometimes|string',
            'city' => 'required|string',
            'state' => 'sometimes|string',
            'postal_code' => 'required|string',
            'country' => 'required|string',
            'is_default' => 'sometimes|boolean'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        try {
            $db = Database::getConnection();

            // If this is set as default, unset other defaults
            if (!empty($input['is_default'])) {
                $sql = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':user_id' => $user['id']]);
            }

            // Support both old and new address formats
            $street = $input['street'] ?? $input['address_line1'] ?? '';
            $street2 = $input['street2'] ?? $input['address_line2'] ?? '';
            
            $sql = "INSERT INTO addresses (user_id, label, street, street2, city, state, postal_code, country, is_default, address_line1, address_line2)
                    VALUES (:user_id, :label, :street, :street2, :city, :state, :postal_code, :country, :is_default, :address_line1, :address_line2)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id' => $user['id'],
                ':label' => Sanitizer::string($input['label'] ?? ''),
                ':street' => Sanitizer::string($street),
                ':street2' => Sanitizer::string($street2),
                ':city' => Sanitizer::string($input['city']),
                ':state' => Sanitizer::string($input['state'] ?? ''),
                ':postal_code' => Sanitizer::string($input['postal_code']),
                ':country' => Sanitizer::string($input['country']),
                ':is_default' => !empty($input['is_default']) ? 1 : 0,
                ':address_line1' => Sanitizer::string($street), // For backward compatibility
                ':address_line2' => Sanitizer::string($street2) // For backward compatibility
            ]);

            $addressId = $db->lastInsertId();
            $address = $this->getAddressById($addressId);

            Response::success(['address' => $address], 'Address created successfully', 201);
        } catch (Exception $e) {
            Response::error('Failed to create address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update address
     */
    public function updateAddress($addressId)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        // Verify address belongs to user
        $address = $this->getAddressById($addressId);
        if (!$address || $address['user_id'] != $user['id']) {
            Response::error('Address not found', 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        try {
            $db = Database::getConnection();

            // If this is set as default, unset other defaults
            if (!empty($input['is_default'])) {
                $sql = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id AND id != :address_id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':user_id' => $user['id'], ':address_id' => $addressId]);
            }

            $fields = [];
            $params = [':id' => $addressId];

            // Support both new (street/street2) and old (address_line1/address_line2) formats
            if (isset($input['street']) || isset($input['address_line1'])) {
                $street = $input['street'] ?? $input['address_line1'] ?? '';
                $sanitizedStreet = Sanitizer::string($street);
                $fields[] = "street = :street";
                $fields[] = "address_line1 = :address_line1";
                $params[':street'] = $sanitizedStreet;
                $params[':address_line1'] = $sanitizedStreet; // Keep in sync
            }
            
            if (isset($input['street2']) || isset($input['address_line2'])) {
                $street2 = $input['street2'] ?? $input['address_line2'] ?? '';
                $sanitizedStreet2 = Sanitizer::string($street2);
                $fields[] = "street2 = :street2";
                $fields[] = "address_line2 = :address_line2";
                $params[':street2'] = $sanitizedStreet2;
                $params[':address_line2'] = $sanitizedStreet2; // Keep in sync
            }

            $allowedFields = ['label', 'city', 'state', 'postal_code', 'country', 'is_default'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $field === 'is_default' 
                        ? (!empty($input[$field]) ? 1 : 0)
                        : Sanitizer::string($input[$field]);
                }
            }

            if (empty($fields)) {
                Response::error('No fields to update', 400);
            }

            $sql = "UPDATE addresses SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            
            if (!$stmt->execute($params)) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception('Database error: ' . ($errorInfo[2] ?? 'Unknown error'));
            }

            $updatedAddress = $this->getAddressById($addressId);
            Response::success(['address' => $updatedAddress], 'Address updated successfully');
        } catch (Exception $e) {
            error_log("Address update error: " . $e->getMessage());
            Response::error('Failed to update address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete address
     */
    public function deleteAddress($addressId)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }

        // Verify address belongs to user
        $address = $this->getAddressById($addressId);
        if (!$address || $address['user_id'] != $user['id']) {
            Response::error('Address not found', 404);
        }

        try {
            $db = Database::getConnection();
            $sql = "DELETE FROM addresses WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $addressId]);

            Response::success([], 'Address deleted successfully');
        } catch (Exception $e) {
            Response::error('Failed to delete address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get current authenticated user from token
     */
    private function getCurrentUser()
    {
        $headers = getallheaders();
        // Check both Authorization and authorization (case-insensitive)
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            return null;
        }

        // Extract token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];

        // Validate token from user_sessions table
        try {
            $db = Database::getConnection();
            $sql = "SELECT us.user_id, u.* 
                    FROM user_sessions us
                    JOIN users u ON us.user_id = u.id
                    WHERE us.token = :token 
                    AND us.expires_at > NOW()
                    AND u.is_active = 1";

            $stmt = $db->prepare($sql);
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch();

            return $user ?: null;
        } catch (Exception $e) {
            error_log("Error validating customer token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get address by ID
     */
    private function getAddressById($addressId)
    {
        try {
            $db = Database::getConnection();
            $sql = "SELECT * FROM addresses WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $addressId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
}

