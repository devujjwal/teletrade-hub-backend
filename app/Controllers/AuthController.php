<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Middlewares/RateLimitMiddleware.php';
require_once __DIR__ . '/../Services/SupabaseStorageService.php';
require_once __DIR__ . '/../Services/EmailNotificationService.php';

/**
 * Auth Controller
 * Handles customer authentication
 */
class AuthController
{
    private const FORGOT_PASSWORD_SUCCESS_MESSAGE = "We've sent a password reset link to your email address. Please check your inbox and follow the instructions to reset your password.";
    private $userModel;
    private $rateLimiter;
    private $supabaseStorage;
    private $emailNotifications;

    public function __construct()
    {
        $this->userModel = new User();
        $this->rateLimiter = new RateLimitMiddleware();
        $this->supabaseStorage = new SupabaseStorageService();
        $this->emailNotifications = new EmailNotificationService();
    }

    /**
     * Register new customer
     */
    public function register()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxSizeBytes = $this->parsePhpSizeToBytes((string)ini_get('post_max_size'));

        if (
            $isMultipart &&
            $contentLength > 0 &&
            empty($_POST) &&
            empty($_FILES) &&
            $postMaxSizeBytes !== null &&
            $contentLength > $postMaxSizeBytes
        ) {
            $postMaxSizeMb = round($postMaxSizeBytes / (1024 * 1024), 2);
            Response::error(
                'Uploaded payload is too large. Please reduce document size or contact support.',
                413,
                [
                    'general' => ["Maximum total upload size is {$postMaxSizeMb}MB."],
                    'max_total_upload_size_mb' => [(string)$postMaxSizeMb]
                ]
            );
        }

        $input = $isMultipart
            ? $_POST
            : json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Normalize common inputs before validation and duplicate checks.
        $input['email'] = trim((string)($input['email'] ?? ''));
        $input['phone'] = trim((string)($input['phone'] ?? ''));
        $input['mobile'] = trim((string)($input['mobile'] ?? ''));
        if ($input['phone'] === '') {
            $input['phone'] = null;
        }

        // SECURITY: Rate limiting to prevent registration spam.
        // Include email in the identifier to reduce false lockouts for shared IPs/NAT environments.
        $clientIp = RateLimitMiddleware::getClientIdentifier();
        $normalizedEmailForRateLimit = strtolower((string)($input['email'] ?? ''));
        $rateLimitIdentifier = $normalizedEmailForRateLimit !== ''
            ? $clientIp . '|' . $normalizedEmailForRateLimit
            : $clientIp;
        $this->rateLimiter->enforce($rateLimitIdentifier, 'customer_register', 8, 3600); // 8 attempts per hour per IP+email

        $accountType = $input['account_type'] ?? 'customer';

        // Validate common fields
        $errors = Validator::validate($input, [
            'account_type' => 'required|in:customer,merchant',
            'email' => 'required|email',
            'password' => 'required|min:8|strong_password',
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'address' => 'required|max:255',
            'postal_code' => 'required|max:20',
            'city' => 'required|max:100',
            'country' => 'required|max:100',
            'phone' => 'phone|max:50',
            'mobile' => 'required|phone|max:50'
        ]);

        if ($input['mobile'] && !preg_match('/^\+?[0-9][0-9\s()\-]{6,19}$/', $input['mobile'])) {
            $errors['mobile'][] = 'The mobile must be a valid phone number.';
        }
        if (!empty($input['phone']) && !preg_match('/^\+?[0-9][0-9\s()\-]{6,19}$/', (string)$input['phone'])) {
            $errors['phone'][] = 'The phone must be a valid phone number.';
        }

        // Validate merchant-specific fields
        if ($accountType === 'merchant') {
            $merchantErrors = Validator::validate($input, [
                'tax_number' => 'required|max:100',
                'vat_number' => 'required|max:100',
                'delivery_address' => 'required|max:255',
                'delivery_postal_code' => 'required|max:20',
                'delivery_city' => 'required|max:100',
                'delivery_country' => 'required|max:100',
                'account_holder' => 'required|max:200',
                'bank_name' => 'required|max:200',
                'iban' => 'required|max:50',
                'bic' => 'required|max:30'
            ]);
            $errors = array_merge_recursive($errors, $merchantErrors);
        }

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        // Check if email already exists
        if ($this->userModel->emailExists($input['email'])) {
            Response::error('You are already registered. You will be able to login once our team approves your account.', 409);
        }

        // Check if phone/mobile already exists
        if (!empty($input['phone']) && $this->userModel->phoneExists($input['phone'])) {
            Response::error('You are already registered. You will be able to login once our team approves your account.', 409);
        }
        if ($this->userModel->phoneExists($input['mobile'])) {
            Response::error('You are already registered. You will be able to login once our team approves your account.', 409);
        }

        $documents = [];
        $validatedDocuments = [];
        $requiredDocuments = $accountType === 'merchant'
            ? ['id_card_file', 'passport_file', 'business_registration_certificate_file', 'vat_certificate_file', 'tax_number_certificate_file']
            : ['id_card_file', 'passport_file'];

        foreach ($requiredDocuments as $field) {
            if (!$isMultipart || !isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors[$field][] = "The $field file is required.";
                continue;
            }

            $validation = $this->validateRegistrationDocument($_FILES[$field]);
            if (!$validation) {
                $errors[$field][] = "The $field file must be PDF, JPG, or PNG and up to 10MB.";
                continue;
            }

            $validatedDocuments[$field] = $validation;
        }

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        $db = null;
        $uploadedObjectPaths = [];

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            // Hash password
            $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);

            // Create user
            $userId = $this->userModel->create([
                ':account_type' => $accountType,
                ':email' => Sanitizer::email($input['email']),
                ':password_hash' => $passwordHash,
                ':first_name' => Sanitizer::string($input['first_name']),
                ':last_name' => Sanitizer::string($input['last_name']),
                ':address' => Sanitizer::string($input['address']),
                ':postal_code' => Sanitizer::string($input['postal_code']),
                ':city' => Sanitizer::string($input['city']),
                ':country' => Sanitizer::string($input['country']),
                ':phone' => !empty($input['phone']) ? Sanitizer::string((string)$input['phone']) : null,
                ':mobile' => Sanitizer::string($input['mobile']),
                ':tax_number' => Sanitizer::string($input['tax_number'] ?? ''),
                ':vat_number' => Sanitizer::string($input['vat_number'] ?? ''),
                ':delivery_address' => Sanitizer::string($input['delivery_address'] ?? ''),
                ':delivery_postal_code' => Sanitizer::string($input['delivery_postal_code'] ?? ''),
                ':delivery_city' => Sanitizer::string($input['delivery_city'] ?? ''),
                ':delivery_country' => Sanitizer::string($input['delivery_country'] ?? ''),
                ':account_holder' => Sanitizer::string($input['account_holder'] ?? ''),
                ':bank_name' => Sanitizer::string($input['bank_name'] ?? ''),
                ':iban' => Sanitizer::string($input['iban'] ?? ''),
                ':bic' => Sanitizer::string($input['bic'] ?? ''),
                ':id_card_file' => null,
                ':passport_file' => null,
                ':business_registration_certificate_file' => null,
                ':vat_certificate_file' => null,
                ':tax_number_certificate_file' => null,
                ':is_active' => 0,
                ':approval_status' => 'pending'
            ]);

            foreach ($requiredDocuments as $field) {
                $uploadResult = $this->uploadRegistrationDocument(
                    $_FILES[$field],
                    $accountType,
                    $field,
                    $validatedDocuments[$field]['mime']
                );

                if (!$uploadResult) {
                    throw new Exception("Document upload failed for {$field}");
                }

                $documents[$field] = $uploadResult['url'];
                $uploadedObjectPaths[] = $uploadResult['objectPath'];
            }

            $this->updateRegistrationDocuments($userId, $documents);

            $db->commit();
            $user = $this->userModel->getById($userId);

            $this->attachSignedRegistrationDocumentUrls($user);
            $this->emailNotifications->sendAccountCreated($user['email']);

            // Dispatch admin notification independently so customer flow is never blocked.
            $adminPayload = [
                'id' => $user['id'] ?? null,
                'account_type' => $user['account_type'] ?? ($input['account_type'] ?? 'customer'),
                'first_name' => $user['first_name'] ?? ($input['first_name'] ?? ''),
                'last_name' => $user['last_name'] ?? ($input['last_name'] ?? ''),
                'email' => $user['email'] ?? ($input['email'] ?? ''),
                'phone' => $user['phone'] ?? ($input['phone'] ?? ''),
                'mobile' => $user['mobile'] ?? ($input['mobile'] ?? ''),
                'address' => $user['address'] ?? ($input['address'] ?? ''),
                'postal_code' => $user['postal_code'] ?? ($input['postal_code'] ?? ''),
                'city' => $user['city'] ?? ($input['city'] ?? ''),
                'country' => $user['country'] ?? ($input['country'] ?? ''),
                'tax_number' => $user['tax_number'] ?? ($input['tax_number'] ?? ''),
                'vat_number' => $user['vat_number'] ?? ($input['vat_number'] ?? ''),
                'delivery_address' => $user['delivery_address'] ?? ($input['delivery_address'] ?? ''),
                'delivery_postal_code' => $user['delivery_postal_code'] ?? ($input['delivery_postal_code'] ?? ''),
                'delivery_city' => $user['delivery_city'] ?? ($input['delivery_city'] ?? ''),
                'delivery_country' => $user['delivery_country'] ?? ($input['delivery_country'] ?? ''),
                'account_holder' => $user['account_holder'] ?? ($input['account_holder'] ?? ''),
                'bank_name' => $user['bank_name'] ?? ($input['bank_name'] ?? ''),
                'iban' => $user['iban'] ?? ($input['iban'] ?? ''),
                'bic' => $user['bic'] ?? ($input['bic'] ?? ''),
                'created_at' => $user['created_at'] ?? date('c')
            ];

            register_shutdown_function(function () use ($adminPayload) {
                try {
                    $service = new EmailNotificationService();
                    $service->sendAdminRegistrationNotification($adminPayload);
                } catch (Throwable $e) {
                    error_log('Admin registration notification failed: ' . $e->getMessage());
                }
            });

            // Remove password hash from response
            unset($user['password_hash']);

            Response::success([
                'user' => $user,
                'message' => 'Registration submitted successfully. Your account is pending admin approval and we will notify you by email once it is verified.'
            ], 'Registration submitted successfully. Your account is pending admin approval and we will notify you by email once it is verified.', 201);
        } catch (PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Registration DB Error: ' . $e->getMessage());

            $sqlState = $e->errorInfo[0] ?? (string)$e->getCode();
            $dbError = strtolower($e->errorInfo[2] ?? $e->getMessage());

            // Handle duplicate keys gracefully instead of surfacing as a generic 500.
            if ($sqlState === '23000') {
                if (strpos($dbError, 'email') !== false) {
                    Response::error('You are already registered. You will be able to login once our team approves your account.', 409);
                }
                if (strpos($dbError, 'phone') !== false || strpos($dbError, 'mobile') !== false) {
                    Response::error('You are already registered. You will be able to login once our team approves your account.', 409);
                }
            }

            Response::error('Registration failed due to a database error.', 500);
        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($uploadedObjectPaths as $objectPath) {
                try {
                    $this->supabaseStorage->deleteFile($this->supabaseStorage->getRegistrationBucket(), $objectPath);
                } catch (Exception $cleanupError) {
                    error_log('Registration cleanup upload delete failed: ' . $cleanupError->getMessage());
                }
            }
            error_log('Registration Error: ' . $e->getMessage());
            Response::error('Registration failed. Please try again later.', 500);
        }
    }

    /**
     * Validate registration document file and return mime metadata
     */
    private function validateRegistrationDocument($file)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
            return null;
        }

        $allowedMimeToExt = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!isset($allowedMimeToExt[$mimeType])) {
            return null;
        }

        if (!isset($allowedMimeToExt[$mimeType])) {
            return null;
        }

        return [
            'mime' => $mimeType,
            'extension' => $allowedMimeToExt[$mimeType]
        ];
    }

    /**
     * Convert php.ini shorthand size values (e.g. 8M, 1G) to bytes
     */
    private function parsePhpSizeToBytes($size)
    {
        $trimmed = trim((string)$size);
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)$/i', $trimmed, $matches)) {
            return null;
        }

        $value = (float)$matches[1];
        $unit = strtoupper($matches[2] ?? '');

        switch ($unit) {
            case 'G':
                $value *= 1024;
                // fallthrough
            case 'M':
                $value *= 1024;
                // fallthrough
            case 'K':
                $value *= 1024;
                break;
            default:
                break;
        }

        return (int)round($value);
    }

    /**
     * Upload registration document to Supabase and return URL + object path
     */
    private function uploadRegistrationDocument($file, $accountType, $fieldName, $mimeType)
    {
        $allowedMimeToExt = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];

        if (!isset($allowedMimeToExt[$mimeType])) {
            return null;
        }

        $extension = $allowedMimeToExt[$mimeType];
        $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', $fieldName);
        try {
            $randomPart = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return null;
        }
        $filename = $safeField . '_' . date('YmdHis') . '_' . $randomPart . '.' . $extension;
        $objectPath = 'registration/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $accountType) . '/' . $filename;

        try {
            $url = $this->supabaseStorage->uploadFile(
                $file['tmp_name'],
                $mimeType,
                $this->supabaseStorage->getRegistrationBucket(),
                $objectPath
            );
            return [
                'url' => $url,
                'objectPath' => $objectPath
            ];
        } catch (Exception $e) {
            error_log('Registration document upload failed: ' . $e->getMessage());
            return null;
        }
    }

    private function updateRegistrationDocuments($userId, $documents)
    {
        $db = Database::getConnection();
        $sql = "UPDATE users
                SET id_card_file = :id_card_file,
                    passport_file = :passport_file,
                    business_registration_certificate_file = :business_registration_certificate_file,
                    vat_certificate_file = :vat_certificate_file,
                    tax_number_certificate_file = :tax_number_certificate_file
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id' => $userId,
            ':id_card_file' => $documents['id_card_file'] ?? null,
            ':passport_file' => $documents['passport_file'] ?? null,
            ':business_registration_certificate_file' => $documents['business_registration_certificate_file'] ?? null,
            ':vat_certificate_file' => $documents['vat_certificate_file'] ?? null,
            ':tax_number_certificate_file' => $documents['tax_number_certificate_file'] ?? null
        ]);
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
            $email = trim((string)($input['email'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $user = $this->userModel->getByEmail($email);

            if (!$user) {
                Response::error('No account was found for this email address. Please recheck the email and try again.', 404);
            }

            if (!password_verify($password, $user['password_hash'])) {
                Response::error('The password you entered is incorrect. Please try again.', 401);
            }

            $approvalStatus = $user['approval_status'] ?? ($user['is_active'] ? 'approved' : 'pending');
            if ($approvalStatus === 'rejected') {
                Response::error('Your account has been rejected. Please contact support for further details.', 403);
            }

            if (!$user['is_active']) {
                Response::error('Your account is pending admin approval. We will notify you by email as soon as it has been verified.', 403);
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
                
                if (Database::isPostgres()) {
                    $sql = "INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) 
                            VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)
                            ON CONFLICT (user_id) DO UPDATE SET
                                token = EXCLUDED.token,
                                ip_address = EXCLUDED.ip_address,
                                user_agent = EXCLUDED.user_agent,
                                expires_at = EXCLUDED.expires_at,
                                updated_at = CURRENT_TIMESTAMP";
                } else {
                    $sql = "INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) 
                            VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)
                            ON DUPLICATE KEY UPDATE 
                                token = VALUES(token), 
                                expires_at = VALUES(expires_at),
                                updated_at = CURRENT_TIMESTAMP";
                }
                
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
     * Request password reset link
     */
    public function forgotPassword()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $clientIp = RateLimitMiddleware::getClientIdentifier();
        $this->rateLimiter->enforce($clientIp, 'forgot_password_ip', 5, 900); // 5 attempts per 15 minutes

        $errors = Validator::validate($input, [
            'email' => 'required|email'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        $email = strtolower(Sanitizer::email((string)($input['email'] ?? '')));
        if ($email === '' || !Validator::email($email)) {
            Response::error('Validation failed', 400, [
                'email' => ['The email must be a valid email address.']
            ]);
        }

        $this->rateLimiter->enforce($clientIp . ':' . hash('sha256', $email), 'forgot_password_email', 3, 900); // 3 attempts per email/IP in 15 minutes

        $user = $this->userModel->getByEmail($email);
        if (!$user) {
            Response::error('No user exists with this email address.', 404);
        }

        $db = Database::getConnection();

        try {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = $this->getPasswordResetExpiryTimestamp();
            $resetUrl = $this->buildResetPasswordUrl($token);

            $db->beginTransaction();

            $clearStmt = $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id");
            $clearStmt->execute([':user_id' => $user['id']]);

            $insertSql = "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip, user_agent)
                          VALUES (:user_id, :token_hash, :expires_at, :requested_ip, :user_agent)";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                ':user_id' => $user['id'],
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
                ':requested_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)
            ]);

            $emailSent = $this->emailNotifications->sendPasswordResetLink(
                $email,
                $resetUrl,
                $this->getPasswordResetExpiryMinutes()
            );

            if (!$emailSent) {
                throw new Exception('Failed to send password reset email');
            }

            $db->commit();
            Response::success([], self::FORGOT_PASSWORD_SUCCESS_MESSAGE);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Forgot password failed: ' . $e->getMessage());
            Response::error('Unable to process forgot password request at this time. Please try again later.', 500);
        }
    }

    /**
     * Verify password reset token validity
     */
    public function verifyResetPasswordToken()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $errors = Validator::validate($input, [
            'token' => 'required|min:32|max:512'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        $token = trim((string)($input['token'] ?? ''));
        if ($token === '') {
            Response::error('Validation failed', 400, [
                'token' => ['The token field is required.']
            ]);
        }

        $tokenHash = hash('sha256', $token);
        $tokenRecord = $this->findValidPasswordResetToken($tokenHash);

        if (!$tokenRecord) {
            Response::error('Invalid or expired password reset token.', 400);
        }

        Response::success([], 'Reset token is valid.');
    }

    /**
     * Update password using reset token
     */
    public function resetPassword()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $clientIp = RateLimitMiddleware::getClientIdentifier();
        $this->rateLimiter->enforce($clientIp, 'reset_password', 10, 900); // 10 attempts per 15 minutes

        $errors = Validator::validate($input, [
            'token' => 'required|min:32|max:512',
            'new_password' => 'required|min:8|strong_password',
            'confirm_password' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        $token = trim((string)($input['token'] ?? ''));
        $newPassword = (string)($input['new_password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');

        if ($newPassword !== $confirmPassword) {
            Response::error('New passwords do not match', 400);
        }

        if ($token === '') {
            Response::error('Validation failed', 400, [
                'token' => ['The token field is required.']
            ]);
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::getConnection();
        $now = gmdate('Y-m-d H:i:s');

        try {
            $db->beginTransaction();

            $tokenSql = "SELECT id, user_id
                         FROM password_reset_tokens
                         WHERE token_hash = :token_hash
                           AND used_at IS NULL
                           AND expires_at > :now
                         LIMIT 1
                         FOR UPDATE";
            $tokenStmt = $db->prepare($tokenSql);
            $tokenStmt->execute([
                ':token_hash' => $tokenHash,
                ':now' => $now
            ]);
            $tokenRecord = $tokenStmt->fetch();

            if (!$tokenRecord) {
                $db->rollBack();
                Response::error('Invalid or expired password reset token.', 400);
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateUserStmt = $db->prepare("UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id");
            $updateUserStmt->execute([
                ':password_hash' => $newPasswordHash,
                ':updated_at' => $now,
                ':id' => $tokenRecord['user_id']
            ]);

            $markUsedStmt = $db->prepare("UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id");
            $markUsedStmt->execute([
                ':used_at' => $now,
                ':id' => $tokenRecord['id']
            ]);

            $invalidateStmt = $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id AND id != :id");
            $invalidateStmt->execute([
                ':user_id' => $tokenRecord['user_id'],
                ':id' => $tokenRecord['id']
            ]);

            // Invalidate active sessions so old sessions cannot keep using old auth state.
            $sessionsStmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
            $sessionsStmt->execute([':user_id' => $tokenRecord['user_id']]);

            $db->commit();
            Response::success([], 'Password has been reset successfully.');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Reset password failed: ' . $e->getMessage());
            Response::error('Unable to reset password at this time. Please try again later.', 500);
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

        $this->attachSignedRegistrationDocumentUrls($user);

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

        // Only phone can be updated from self-service account page.
        $errors = Validator::validate($input, [
            'phone' => 'sometimes|phone|max:50'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        try {
            $updateData = [];
            if (isset($input['phone'])) {
                $phone = trim((string)$input['phone']);
                if ($phone !== '' && !preg_match('/^\+?[0-9][0-9\s()\-]{6,19}$/', $phone)) {
                    Response::error('Validation failed', 400, [
                        'phone' => ['The phone must be a valid phone number.']
                    ]);
                }
                $updateData[':phone'] = $phone === '' ? null : Sanitizer::string($phone);
            }

            if (empty($updateData)) {
                Response::error('No phone value provided', 400);
            }

            $this->userModel->update($user['id'], $updateData);

            // Get updated user
            $updatedUser = $this->userModel->getById($user['id']);
            $this->attachSignedRegistrationDocumentUrls($updatedUser);
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

    private function attachSignedRegistrationDocumentUrls(&$user)
    {
        if (!is_array($user)) {
            return;
        }

        $fields = [
            'id_card_file',
            'passport_file',
            'business_registration_certificate_file',
            'vat_certificate_file',
            'tax_number_certificate_file'
        ];

        foreach ($fields as $field) {
            if (empty($user[$field])) {
                continue;
            }

            try {
                $bucket = $this->supabaseStorage->getRegistrationBucket();
                $objectPath = $this->extractStorageObjectPath((string)$user[$field], $bucket);
                if ($objectPath === null) {
                    continue;
                }
                $user[$field] = $this->supabaseStorage->createSignedUrl($bucket, $objectPath, 3600);
            } catch (Exception $e) {
                error_log('Failed to sign user registration document URL: ' . $e->getMessage());
            }
        }
    }

    private function findValidPasswordResetToken($tokenHash)
    {
        $db = Database::getConnection();
        $sql = "SELECT id, user_id
                FROM password_reset_tokens
                WHERE token_hash = :token_hash
                  AND used_at IS NULL
                  AND expires_at > :now
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':now' => gmdate('Y-m-d H:i:s')
        ]);

        return $stmt->fetch() ?: null;
    }

    private function getPasswordResetExpiryMinutes()
    {
        $minutes = (int)Env::get('PASSWORD_RESET_TOKEN_EXPIRY_MINUTES', 30);
        if ($minutes < 5) {
            return 5;
        }
        if ($minutes > 120) {
            return 120;
        }
        return $minutes;
    }

    private function getPasswordResetExpiryTimestamp()
    {
        return gmdate('Y-m-d H:i:s', time() + ($this->getPasswordResetExpiryMinutes() * 60));
    }

    private function buildResetPasswordUrl($token)
    {
        $frontendUrl = trim((string)Env::get('FRONTEND_URL', ''));
        if ($frontendUrl === '') {
            $corsOrigins = trim((string)Env::get('CORS_ALLOWED_ORIGINS', ''));
            if ($corsOrigins !== '') {
                $originParts = preg_split('/[,\s]+/', $corsOrigins);
                $frontendUrl = trim((string)($originParts[0] ?? ''));
            }
        }

        if ($frontendUrl === '') {
            $frontendUrl = 'http://localhost:3000';
        }

        return rtrim($frontendUrl, '/') . '/reset-password?token=' . rawurlencode($token);
    }

    private function extractStorageObjectPath($value, $bucket)
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        if (strpos($raw, 'http://') !== 0 && strpos($raw, 'https://') !== 0) {
            return ltrim($raw, '/');
        }

        $parsedPath = parse_url($raw, PHP_URL_PATH);
        if (!is_string($parsedPath) || $parsedPath === '') {
            return null;
        }

        $normalizedPath = ltrim($parsedPath, '/');
        $bucketVariants = [rawurlencode($bucket), $bucket];
        $prefixes = [];

        foreach ($bucketVariants as $bucketName) {
            $prefixes[] = 'storage/v1/object/public/' . $bucketName . '/';
            $prefixes[] = 'storage/v1/object/sign/' . $bucketName . '/';
            $prefixes[] = 'storage/v1/object/' . $bucketName . '/';
        }

        foreach ($prefixes as $prefix) {
            if (strpos($normalizedPath, $prefix) === 0) {
                $objectPath = substr($normalizedPath, strlen($prefix));
                return ltrim(rawurldecode($objectPath), '/');
            }
        }

        return null;
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
