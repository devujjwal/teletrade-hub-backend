<?php

require_once __DIR__ . '/../Models/User.php';

/**
 * Auth Controller
 * Handles customer authentication
 */
class AuthController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
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

        // Validate input
        $errors = Validator::validate($input, [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'first_name' => 'required',
            'last_name' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        // Check if email already exists
        if ($this->userModel->emailExists($input['email'])) {
            Response::error('Email already registered', 400);
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
                Response::error('Invalid email or password', 401);
            }

            if (!$user['is_active']) {
                Response::error('Account is disabled', 403);
            }

            // Remove password hash from response
            unset($user['password_hash']);

            Response::success([
                'user' => $user,
                'message' => 'Login successful'
            ]);
        } catch (Exception $e) {
            Response::error('Login failed: ' . $e->getMessage(), 500);
        }
    }
}

