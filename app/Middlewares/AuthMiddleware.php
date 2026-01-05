<?php

/**
 * Authentication Middleware
 * Validates admin authentication tokens
 */
class AuthMiddleware
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Verify admin authentication
     */
    public function verifyAdmin()
    {
        // Get authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader)) {
            Response::unauthorized('Authorization token required');
        }

        // Extract token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::unauthorized('Invalid authorization format');
        }

        $token = $matches[1];

        // Validate token
        $admin = $this->validateToken($token);

        if (!$admin) {
            Response::unauthorized('Invalid or expired token');
        }

        return $admin;
    }

    /**
     * Validate authentication token
     */
    private function validateToken($token)
    {
        $sql = "SELECT au.*, asess.expires_at 
                FROM admin_sessions asess
                JOIN admin_users au ON asess.admin_user_id = au.id
                WHERE asess.token = :token 
                AND asess.expires_at > NOW()
                AND au.is_active = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch();

        if ($result) {
            unset($result['password_hash']);
            return $result;
        }

        return null;
    }

    /**
     * Create admin session
     */
    public function createAdminSession($adminUserId, $tokenExpiry = null)
    {
        if (!$tokenExpiry) {
            $tokenExpiry = intval(Env::get('ADMIN_TOKEN_EXPIRY', 86400)); // 24 hours
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $tokenExpiry);

        $sql = "INSERT INTO admin_sessions (
            admin_user_id, token, ip_address, user_agent, expires_at
        ) VALUES (
            :admin_user_id, :token, :ip_address, :user_agent, :expires_at
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':admin_user_id' => $adminUserId,
            ':token' => $token,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':expires_at' => $expiresAt
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Revoke token
     */
    public function revokeToken($token)
    {
        $sql = "DELETE FROM admin_sessions WHERE token = :token";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':token' => $token]);
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions()
    {
        $sql = "DELETE FROM admin_sessions WHERE expires_at < NOW()";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }
}

