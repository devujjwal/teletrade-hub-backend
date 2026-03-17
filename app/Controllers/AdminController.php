<?php

require_once __DIR__ . '/../Middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../Middlewares/RateLimitMiddleware.php';
require_once __DIR__ . '/../Services/OrderService.php';
require_once __DIR__ . '/../Services/ProductSyncService.php';
require_once __DIR__ . '/../Services/PricingService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/../Utils/Language.php';
require_once __DIR__ . '/../Utils/Sanitizer.php';
require_once __DIR__ . '/../Services/SupabaseStorageService.php';
require_once __DIR__ . '/../Models/OrderInvoice.php';
require_once __DIR__ . '/../Services/EmailNotificationService.php';
require_once __DIR__ . '/../Services/ContentInvalidationService.php';
require_once __DIR__ . '/../Services/ApiCacheService.php';
require_once __DIR__ . '/../Services/DiagnosticsLoggerService.php';
if (!class_exists('Database')) {
    require_once __DIR__ . '/../Config/database.php';
}
require_once __DIR__ . '/../Models/Settings.php';

/**
 * Admin Controller
 * Handles admin-related API endpoints
 */
class AdminController
{
    private $authMiddleware;
    private $rateLimiter;
    private $orderService;
    private $orderModel;
    private $productModel;
    private $categoryModel;
    private $brandModel;
    private $settingsModel;
    private $productSyncService;
    private $pricingService;
    private $supabaseStorage;
    private $orderInvoiceModel;
    private $emailNotifications;
    private $contentInvalidation;
    private $apiCache;
    private $db;

    public function __construct()
    {
        try {
            $this->authMiddleware = new AuthMiddleware();
            $this->rateLimiter = new RateLimitMiddleware();
            // Models and services are lazy-loaded to avoid errors during login
            $this->orderService = null;
            $this->orderModel = null;
            $this->productModel = null;
            $this->categoryModel = null;
            $this->brandModel = null;
            $this->settingsModel = null;
            $this->productSyncService = null;
            $this->pricingService = null;
            $this->supabaseStorage = null;
            $this->orderInvoiceModel = null;
            $this->emailNotifications = null;
            $this->contentInvalidation = null;
            $this->apiCache = new ApiCacheService();
            $this->db = Database::getConnection();
        } catch (Exception $e) {
            error_log("AdminController constructor error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            throw $e;
        }
    }

    /**
     * Get settings model instance (lazy-loaded)
     */
    private function getSettingsModel()
    {
        if ($this->settingsModel === null) {
            $this->settingsModel = new Settings();
        }
        return $this->settingsModel;
    }

    /**
     * Get order service instance (lazy-loaded)
     */
    private function getOrderService()
    {
        if ($this->orderService === null) {
            $this->orderService = new OrderService();
        }
        return $this->orderService;
    }

    /**
     * Get order model instance (lazy-loaded)
     */
    private function getOrderModel()
    {
        if ($this->orderModel === null) {
            $this->orderModel = new Order();
        }
        return $this->orderModel;
    }

    /**
     * Get product model instance (lazy-loaded)
     */
    private function getProductModel()
    {
        if ($this->productModel === null) {
            $this->productModel = new Product();
        }
        return $this->productModel;
    }

    /**
     * Get category model instance (lazy-loaded)
     */
    private function getCategoryModel()
    {
        if ($this->categoryModel === null) {
            $this->categoryModel = new Category();
        }
        return $this->categoryModel;
    }

    /**
     * Get brand model instance (lazy-loaded)
     */
    private function getBrandModel()
    {
        if ($this->brandModel === null) {
            $this->brandModel = new Brand();
        }
        return $this->brandModel;
    }

    /**
     * Get product sync service instance (lazy-loaded)
     */
    private function getProductSyncService()
    {
        if ($this->productSyncService === null) {
            $this->productSyncService = new ProductSyncService();
        }
        return $this->productSyncService;
    }

    /**
     * Check whether a sync job is already active.
     */
    private function isSyncRunning()
    {
        $lastSync = $this->getProductSyncService()->getLastSyncStatus();

        if (!$lastSync) {
            return false;
        }

        return ($lastSync['status'] ?? null) === 'in_progress' && empty($lastSync['completed_at']);
    }

    /**
     * Launch product sync in a detached background PHP process.
     */
    private function dispatchProductSync($languageIds = null)
    {
        $scriptPath = realpath(__DIR__ . '/../../bin/run-product-sync.php');

        if (!$scriptPath || !file_exists($scriptPath)) {
            throw new Exception('Background sync runner not found');
        }

        $payload = base64_encode(json_encode($languageIds ?? []));
        $command = sprintf(
            '%s %s %s > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($scriptPath),
            escapeshellarg($payload)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new Exception('Failed to start background sync process');
        }
    }

    /**
     * Get pricing service instance (lazy-loaded)
     */
    private function getPricingService()
    {
        if ($this->pricingService === null) {
            $this->pricingService = new PricingService();
        }
        return $this->pricingService;
    }

    /**
     * Get Supabase storage service instance (lazy-loaded)
     */
    private function getSupabaseStorage()
    {
        if ($this->supabaseStorage === null) {
            $this->supabaseStorage = new SupabaseStorageService();
        }
        return $this->supabaseStorage;
    }

    private function getOrderInvoiceModel()
    {
        if ($this->orderInvoiceModel === null) {
            $this->orderInvoiceModel = new OrderInvoice();
        }
        return $this->orderInvoiceModel;
    }

    private function getEmailNotifications()
    {
        if ($this->emailNotifications === null) {
            $this->emailNotifications = new EmailNotificationService();
        }
        return $this->emailNotifications;
    }

    private function getContentInvalidation()
    {
        if ($this->contentInvalidation === null) {
            $this->contentInvalidation = new ContentInvalidationService();
        }

        return $this->contentInvalidation;
    }

    private function invalidateContent(array $tags = [], array $revalidatePayload = [])
    {
        $this->getContentInvalidation()->invalidate($tags, $revalidatePayload);
    }

    /**
     * Debug: Get database diagnostics (temporary - no auth required)
     * SECURITY: Only available in development mode with secure key
     */
    public function debugDatabase()
    {
        // SECURITY: Disable in production
        if (Env::get('APP_ENV', 'production') === 'production') {
            Response::error('Not available in production', 403);
        }
        
        // SECURITY: Require secure debug key from environment
        $key = $_GET['key'] ?? '';
        $expectedKey = Env::get('DEBUG_KEY', '');
        if (empty($expectedKey) || !hash_equals($expectedKey, $key)) {
            Response::error('Unauthorized', 401);
        }

        try {
            $db = Database::getConnection();
            
            $stats = [
                'products_count' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
                'brands_count' => $db->query("SELECT COUNT(*) FROM brands")->fetchColumn(),
                'categories_count' => $db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
                'available_products' => $db->query("SELECT COUNT(*) FROM products WHERE is_available = 1")->fetchColumn(),
            ];

            // Sample products
            $stmt = $db->query("SELECT id, sku, name, name_en, brand_id, category_id, price, stock_quantity, is_available FROM products LIMIT 10");
            $sampleProducts = $stmt->fetchAll();

            // Sample brands
            $stmt = $db->query("SELECT id, name, vendor_id FROM brands LIMIT 10");
            $sampleBrands = $stmt->fetchAll();

            // Sample categories
            $stmt = $db->query("SELECT id, name, vendor_id FROM categories LIMIT 10");
            $sampleCategories = $stmt->fetchAll();

            // Search tests
            $searchTests = [];
            $searchTerms = ['iPhone', 'Samsung', 'phone', 'mobile', 'Apple', 'Google', 'Pixel'];
            foreach ($searchTerms as $term) {
                $searchPattern = '%' . $term . '%';
                $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE name LIKE ? OR sku LIKE ?");
                $stmt->execute([$searchPattern, $searchPattern]);
                $searchTests[$term] = $stmt->fetchColumn();
            }

            // Data quality check
            $dataQuality = [
                'null_names' => $db->query("SELECT COUNT(*) FROM products WHERE name IS NULL OR name = ''")->fetchColumn(),
                'null_categories' => $db->query("SELECT COUNT(*) FROM products WHERE category_id IS NULL")->fetchColumn(),
                'null_brands' => $db->query("SELECT COUNT(*) FROM products WHERE brand_id IS NULL")->fetchColumn(),
                'zero_price' => $db->query("SELECT COUNT(*) FROM products WHERE price = 0")->fetchColumn(),
            ];

            // Latest sync log
            $stmt = $db->query("SELECT * FROM vendor_sync_log ORDER BY started_at DESC LIMIT 1");
            $lastSync = $stmt->fetch();

            Response::success([
                'statistics' => $stats,
                'sample_products' => $sampleProducts,
                'sample_brands' => $sampleBrands,
                'sample_categories' => $sampleCategories,
                'search_tests' => $searchTests,
                'data_quality' => $dataQuality,
                'last_sync' => $lastSync
            ]);
        } catch (Exception $e) {
            // Detailed error for debugging (bypasses production sanitization)
            http_response_code(200); // Use 200 to bypass production error sanitization
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Database error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        } catch (Error $e) {
            // Catch PHP errors too
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'PHP Error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }

    /**
     * Debug: Check TRIEL API response (temporary - no auth required)
     */
    public function debugVendorApi()
    {
        // SECURITY: Disable in production
        if (Env::get('APP_ENV', 'production') === 'production') {
            Response::error('Not available in production', 403);
        }
        
        // SECURITY: Require secure debug key from environment
        $key = $_GET['key'] ?? '';
        $expectedKey = Env::get('DEBUG_KEY', '');
        if (empty($expectedKey) || !hash_equals($expectedKey, $key)) {
            Response::error('Unauthorized', 401);
        }

        try {
            require_once __DIR__ . '/../Services/VendorApiService.php';
            $vendorApi = new VendorApiService();
            
            $stockData = $vendorApi->getStock('en');
            
            $analysis = [
                'top_level_keys' => array_keys($stockData),
                'has_stock_key' => isset($stockData['stock']),
                'has_products_key' => isset($stockData['products']),
                'has_items_key' => isset($stockData['items']),
            ];

            // Get first product structure
            $firstProduct = null;
            if (isset($stockData['stock']) && !empty($stockData['stock'])) {
                $firstProduct = $stockData['stock'][0];
                $analysis['first_product_keys'] = array_keys($firstProduct);
            } elseif (isset($stockData['products']) && !empty($stockData['products'])) {
                $firstProduct = $stockData['products'][0];
                $analysis['first_product_keys'] = array_keys($firstProduct);
            } elseif (isset($stockData['items']) && !empty($stockData['items'])) {
                $firstProduct = $stockData['items'][0];
                $analysis['first_product_keys'] = array_keys($firstProduct);
            } elseif (isset($stockData[0])) {
                $firstProduct = $stockData[0];
                $analysis['first_product_keys'] = array_keys($firstProduct);
            }

            Response::success([
                'analysis' => $analysis,
                'first_product_sample' => $firstProduct,
                'total_products_received' => is_array($stockData) ? count($stockData) : 0
            ]);
        } catch (Exception $e) {
            Response::serverError('Vendor API error: ' . $e->getMessage());
        }
    }

    /**
     * Admin login
     */
    public function login()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                Response::error('Invalid request data', 400);
            }

            // SECURITY: Strict rate limiting for admin login (more restrictive than customer login)
            $clientIp = RateLimitMiddleware::getClientIdentifier();
            $this->rateLimiter->enforce($clientIp, 'admin_login', 3, 900); // 3 attempts per 15 minutes

            // Validate input
            $errors = Validator::validate($input, [
                'username' => 'required',
                'password' => 'required'
            ]);

            if (!empty($errors)) {
                Response::error('Validation failed', 400, $errors);
            }

            // Get admin user
            $sql = "SELECT * FROM admin_users WHERE username = :username AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':username' => Sanitizer::string($input['username'])]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($input['password'], $admin['password_hash'])) {
                // SECURITY: Generic error message to prevent username enumeration
                Response::error('Invalid credentials', 401);
            }

            // SECURITY: Clear rate limit on successful login
            $this->rateLimiter->clearLimit($clientIp, 'admin_login');

            // Update last login
            $updateSql = "UPDATE admin_users SET last_login_at = NOW() WHERE id = :id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':id' => $admin['id']]);

            // Create session token
            $session = $this->authMiddleware->createAdminSession($admin['id']);

            // SECURITY: Log admin login for audit trail
            error_log("AUDIT: Admin login successful - Username: {$admin['username']}, IP: $clientIp");

            // Remove password hash
            unset($admin['password_hash']);

            Response::success([
                'admin' => $admin,
                'token' => $session['token'],
                'expires_at' => $session['expires_at']
            ], 'Login successful');
        } catch (Exception $e) {
            // SECURITY: Log failed login attempts with full error details
            $errorDetails = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            error_log("AUDIT: Admin login failed - Username: " . ($input['username'] ?? 'unknown') . ", IP: $clientIp, Error: " . json_encode($errorDetails));
            
            // In development, show full error; in production, show generic message
            $errorMessage = (Env::get('APP_ENV') === 'development') 
                ? 'Login failed: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()
                : 'Login failed. Please try again.';
            
            Response::error($errorMessage, 500);
        } catch (Error $e) {
            // Catch PHP fatal errors
            error_log("AUDIT: Admin login PHP Error - Username: " . ($input['username'] ?? 'unknown') . ", IP: $clientIp, Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            $errorMessage = (Env::get('APP_ENV') === 'development') 
                ? 'Login failed: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()
                : 'Login failed. Please try again.';
            
            Response::error($errorMessage, 500);
        }
    }

    /**
     * Dashboard statistics
     */
    public function dashboard()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            $stats = $this->getOrderModel()->getStatistics();
            
            // Get product stats
            $availableExpr = Database::isPostgres()
                ? "CASE WHEN LOWER(COALESCE(is_available::text, '0')) IN ('1','t','true') THEN 1 ELSE 0 END"
                : 'CASE WHEN is_available = 1 THEN 1 ELSE 0 END';
            $productStatsSql = "SELECT 
                    COUNT(*) as total_products,
                    SUM({$availableExpr}) as available_products,
                    SUM(stock_quantity) as total_stock
                FROM products";
            $productStats = $this->db->query($productStatsSql)->fetch();

            // Recent orders
            $recentOrders = $this->getOrderModel()->getAll([], 1, 10);

            Response::success([
                'order_stats' => $stats,
                'product_stats' => $productStats,
                'recent_orders' => $recentOrders
            ]);
        } catch (Exception $e) {
            Response::error('Failed to load dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all orders
     */
    public function orders()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        
        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = Sanitizer::string($_GET['status']);
        }
        if (!empty($_GET['payment_status'])) {
            $filters['payment_status'] = Sanitizer::string($_GET['payment_status']);
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = Sanitizer::string($_GET['search']);
        }
        if (!empty($_GET['customer_type'])) {
            $filters['customer_type'] = Sanitizer::string($_GET['customer_type']);
        }

        $orders = $this->getOrderModel()->getAll($filters, $page, $limit);
        $total = $this->getOrderModel()->count($filters);

        Response::success([
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get shop users (customer + merchant)
     */
    public function users()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $filters = [];
        $params = [];
        $where = ["1=1"];

        if (!empty($_GET['account_type'])) {
            $filters['account_type'] = Sanitizer::string($_GET['account_type']);
            $where[] = "u.account_type = :account_type";
            $params[':account_type'] = $filters['account_type'];
        }

        if (isset($_GET['approval_status']) && $_GET['approval_status'] !== '') {
            $approvalStatus = Sanitizer::string($_GET['approval_status']);
            if ($approvalStatus === 'approved') {
                $where[] = "u.is_active = 1";
            } elseif ($approvalStatus === 'pending') {
                $where[] = "u.is_active = 0";
            }
        }

        if (!empty($_GET['search'])) {
            $where[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.mobile LIKE :search)";
            $params[':search'] = '%' . Sanitizer::string($_GET['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT u.*,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name
                FROM users u
                WHERE $whereSql
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) FROM users u WHERE $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Remove sensitive fields
        foreach ($users as &$user) {
            unset($user['password_hash']);
            $this->attachSignedRegistrationDocumentUrls($user);
        }
        unset($user);

        Response::success([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Replace stored registration document values with short-lived signed URLs.
     */
    private function attachSignedRegistrationDocumentUrls(array &$user)
    {
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
                $bucket = $this->getSupabaseStorage()->getRegistrationBucket();
                $objectPath = $this->extractStorageObjectPath((string) $user[$field], $bucket);
                if ($objectPath === null) {
                    continue;
                }

                $user[$field] = $this->getSupabaseStorage()->createSignedUrl($bucket, $objectPath, 3600);
            } catch (Exception $e) {
                error_log('Failed to sign registration document URL for admin user list: ' . $e->getMessage());
            }
        }
    }

    /**
     * Parse DB-stored object path from full storage URL or plain relative path.
     */
    private function extractStorageObjectPath($value, $bucket)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Already stored as relative object path.
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
     * Approve or unapprove shop user
     */
    public function updateUserApproval($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        $approvalStatus = $input['approval_status'] ?? null;
        if ($approvalStatus === null && isset($input['is_active'])) {
            $approvalStatus = !empty($input['is_active']) ? 'approved' : 'pending';
        }

        if (!in_array($approvalStatus, ['pending', 'approved', 'rejected'], true)) {
            Response::error('approval_status is required', 400);
        }

        $isActive = $approvalStatus === 'approved' ? 1 : 0;
        $sql = "UPDATE users SET is_active = :is_active, approval_status = :approval_status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':is_active' => $isActive,
            ':approval_status' => $approvalStatus,
            ':id' => (int)$id
        ]);

        $userSql = "SELECT * FROM users WHERE id = :id";
        $userStmt = $this->db->prepare($userSql);
        $userStmt->execute([':id' => (int)$id]);
        $user = $userStmt->fetch();

        if (!$user) {
            Response::notFound('User not found');
        }

        unset($user['password_hash']);

        if (!empty($user['email'])) {
            if ($approvalStatus === 'approved') {
                $this->getEmailNotifications()->sendAccountApproved($user['email']);
            } elseif ($approvalStatus === 'rejected') {
                $this->getEmailNotifications()->sendAccountRejected($user['email']);
            }
        }

        Response::success([
            'user' => $user
        ], $approvalStatus === 'approved' ? 'User approved successfully' : ($approvalStatus === 'rejected' ? 'User rejected successfully' : 'User marked as pending'));
    }

    /**
     * Set a user password from admin panel.
     */
    public function updateUserPassword($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $errors = Validator::validate($input, [
            'new_password' => 'required|min:8|strong_password',
            'confirm_password' => 'required',
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $sendNotificationEmail = !empty($input['send_notification_email']);
        if ($newPassword !== $confirmPassword) {
            Response::error('New passwords do not match', 400);
        }

        $userId = (int) $id;
        if ($userId <= 0) {
            Response::error('Invalid user id', 400);
        }

        try {
            $userStmt = $this->db->prepare("SELECT id, email FROM users WHERE id = :id");
            $userStmt->execute([':id' => $userId]);
            $user = $userStmt->fetch();

            if (!$user) {
                Response::notFound('User not found');
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);

            $this->db->beginTransaction();

            $updateStmt = $this->db->prepare("UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id");
            $updateStmt->execute([
                ':password_hash' => $newPasswordHash,
                ':id' => $userId,
            ]);

            // Revoke active sessions and any pending reset tokens so only the new password works.
            try {
                $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id")->execute([
                    ':user_id' => $userId,
                ]);
            } catch (Exception $e) {
                error_log('Failed to revoke user sessions after admin password update: ' . $e->getMessage());
            }

            try {
                $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id")->execute([
                    ':user_id' => $userId,
                ]);
            } catch (Exception $e) {
                error_log('Failed to clear reset tokens after admin password update: ' . $e->getMessage());
            }

            $this->db->commit();

            $adminIdentifier = $admin['username'] ?? ('admin_id:' . ($admin['id'] ?? 'unknown'));
            error_log("AUDIT: Admin {$adminIdentifier} updated password for user {$userId}");

            $emailSent = false;
            if ($sendNotificationEmail && !empty($user['email'])) {
                try {
                    $emailSent = (bool) $this->getEmailNotifications()->sendAdminSetPasswordNotification(
                        $user['email'],
                        $newPassword
                    );
                } catch (Exception $e) {
                    error_log('Failed to send admin password notification email: ' . $e->getMessage());
                    $emailSent = false;
                }
            }

            Response::success([
                'user_id' => $userId,
                'email' => $user['email'] ?? null,
                'notification_email_requested' => $sendNotificationEmail,
                'notification_email_sent' => $emailSent,
            ], 'User password updated successfully');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Admin update user password failed: ' . $e->getMessage());
            Response::error('Failed to update user password', 500);
        }
    }

    /**
     * Get order detail (admin view - includes internal fields)
     */
    public function orderDetail($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();

        // Admin gets full details including product_source, fulfillment_status, etc.
        $order = $this->getOrderService()->getOrderDetails($id, true);
        
        if (!$order) {
            Response::notFound('Order not found');
        }

        Response::success(['order' => $order]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['status'])) {
            Response::error('Status is required', 400);
        }

        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($input['status'], $allowedStatuses)) {
            Response::error('Invalid status', 400);
        }

        try {
            $newStatus = $input['status'];
            $orderModel = $this->getOrderModel();
            
            // Get current order to check current status
            $currentOrder = $orderModel->getById($id);
            if (!$currentOrder) {
                Response::notFound('Order not found');
            }
            
            // Update order status
            $orderModel->updateStatus($id, $newStatus);
            
            // Auto-update payment status based on order status
            // Logic: 
            // - pending = unpaid (customer needs to pay)
            // - processing/shipped/delivered = paid (order is being fulfilled, payment must be confirmed)
            // - cancelled = keep current payment status (could be refunded separately)
            if ($newStatus === 'pending') {
                // Setting to pending means payment not yet confirmed
                error_log("Updating payment status to 'unpaid' for order $id");
                $paymentResult = $orderModel->updatePaymentStatus($id, 'unpaid');
                error_log("Payment status update result: " . ($paymentResult ? 'success' : 'failed'));
            } elseif (in_array($newStatus, ['processing', 'shipped', 'delivered'])) {
                // These statuses indicate order is being fulfilled, so payment must be confirmed
                if ($currentOrder['payment_status'] !== 'paid') {
                    error_log("Updating payment status to 'paid' for order $id (current status: {$currentOrder['payment_status']})");
                    $paymentResult = $orderModel->updatePaymentStatus($id, 'paid');
                    error_log("Payment status update result: " . ($paymentResult ? 'success' : 'failed'));
                    
                    if (!$paymentResult) {
                        error_log("WARNING: Payment status update failed for order $id");
                    }
                } else {
                    error_log("Payment status already 'paid' for order $id, skipping update");
                }
            }
            // For 'cancelled', don't auto-change payment status

            if ($currentOrder['status'] !== $newStatus && in_array($newStatus, ['processing', 'shipped', 'delivered'], true)) {
                $fullOrder = $this->getOrderService()->getOrderDetails($id, true);
                $customerEmail = $fullOrder['customer_email'] ?? null;
                if ($customerEmail) {
                    $this->getEmailNotifications()->sendOrderStatusChanged($customerEmail, $fullOrder['order_number'], $newStatus);
                }
            }
            
            // Try to get full order details, but if it fails, just return success
            // (The status update has already succeeded at this point)
            try {
                $order = $this->getOrderService()->getOrderDetails($id, true);
                Response::success(['order' => $order], 'Order status updated');
            } catch (Exception $detailsError) {
                // Log the error but still return success since the status was updated
                error_log("UpdateOrderStatus - Failed to fetch order details after update: " . $detailsError->getMessage());
                Response::success([
                    'order' => ['id' => $id, 'status' => $newStatus]
                ], 'Order status updated');
            }
        } catch (Exception $e) {
            // Log the actual error for debugging
            error_log("UpdateOrderStatus Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to update order: ' . $e->getMessage(), 500);
        } catch (Error $e) {
            // Catch PHP errors too
            error_log("UpdateOrderStatus PHP Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to update order: ' . $e->getMessage(), 500);
        }
    }

    public function updateOrderFinancials($id)
    {
        $this->authMiddleware->verifyAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        $order = $this->getOrderModel()->getById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $shippingCost = isset($input['shipping_cost']) ? round((float) $input['shipping_cost'], 2) : (float) ($order['shipping_cost'] ?? 0);
        $finalOrderPrice = isset($input['final_order_price']) ? round((float) $input['final_order_price'], 2) : (float) ($order['final_order_price'] ?? $order['total']);
        $basePrice = round((float) $order['total'], 2);

        if ($shippingCost < 0) {
            Response::error('Shipping charges cannot be negative', 422);
        }

        if ($finalOrderPrice < $basePrice) {
            Response::error('Final order price must be greater than or equal to the base price', 422);
        }

        $this->getOrderModel()->updateFinancials($id, $shippingCost, $finalOrderPrice);
        $updatedOrder = $this->getOrderService()->getOrderDetails($id, true);

        Response::success(['order' => $updatedOrder], 'Order pricing updated');
    }

    public function uploadOrderInvoice($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();

        $order = $this->getOrderModel()->getById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        if (!isset($_FILES['invoice'])) {
            Response::error('Invoice PDF is required', 400);
        }

        $file = $_FILES['invoice'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            Response::error('Invoice upload failed', 400);
        }

        $maxSize = 10 * 1024 * 1024;
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
            Response::error('Invoice file must be 10MB or smaller', 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if ($mimeType !== 'application/pdf') {
            Response::error('Only PDF invoices are allowed', 422);
        }

        $timestamp = gmdate('YmdHis');
        $objectPath = 'invoices/' . (int) $id . '/' . $timestamp . '.pdf';
        $storedPath = $this->getSupabaseStorage()->uploadPrivateFile(
            $file['tmp_name'],
            'application/pdf',
            $this->getSupabaseStorage()->getInvoiceBucket(),
            $objectPath
        );

        $this->getOrderInvoiceModel()->create([
            ':order_id' => (int) $id,
            ':invoice_url' => $storedPath,
            ':uploaded_at' => gmdate('Y-m-d H:i:s'),
            ':uploaded_by_admin' => $admin['id'] ?? null
        ]);

        $updatedOrder = $this->getOrderService()->getOrderDetails($id, true);
        Response::success(['order' => $updatedOrder], 'Invoice uploaded successfully');
    }

    /**
     * Get all products (admin view)
     */
    public function products()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
        $lang = $_GET['lang'] ?? 'en';

        $filters = [];
        // Admin view: By default show ALL products (available and unavailable)
        // Only filter if explicitly requested
        if (isset($_GET['is_available']) && $_GET['is_available'] !== '' && $_GET['is_available'] !== 'all') {
            $filters['is_available'] = $_GET['is_available'] === '1' ? 1 : 0;
        }
        // For admin, we need to bypass the default filter in Product model
        // Pass a special flag to indicate we want all products
        if (!isset($filters['is_available'])) {
            $filters['is_available'] = 'all'; // Special value to bypass default filter
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = Sanitizer::string($_GET['search']);
        }
        // Filter by product source - must be explicitly set and valid
        if (!empty($_GET['product_source']) && in_array($_GET['product_source'], ['vendor', 'own'], true)) {
            $filters['product_source'] = $_GET['product_source']; // Use raw value, already validated
        }
        // Filter by category - check if set and not empty string
        if (isset($_GET['category_id']) && $_GET['category_id'] !== '' && $_GET['category_id'] !== 'all') {
            $categoryId = intval($_GET['category_id']);
            if ($categoryId > 0) {
                $filters['category_id'] = $categoryId;
            }
        }
        // Filter by brand - check if set and not empty string
        if (isset($_GET['brand_id']) && $_GET['brand_id'] !== '' && $_GET['brand_id'] !== 'all') {
            $brandId = intval($_GET['brand_id']);
            if ($brandId > 0) {
                $filters['brand_id'] = $brandId;
            }
        }
        // Filter by featured status
        if (isset($_GET['is_featured']) && $_GET['is_featured'] !== '' && $_GET['is_featured'] !== 'all') {
            $filters['is_featured'] = ($_GET['is_featured'] === '1' || $_GET['is_featured'] === 1) ? 1 : 0;
        }

        $products = $this->getProductModel()->getAll($filters, $page, $limit, $lang);
        foreach ($products as &$product) {
            $basePrice = isset($product['base_price']) ? floatval($product['base_price']) : 0.0;
            if ($basePrice > 0) {
                $product['customer_price'] = $this->getPricingService()->calculatePrice(
                    $basePrice,
                    $product['category_id'] ?? null,
                    $product['brand_id'] ?? null,
                    $product['id'] ?? null,
                    'customer'
                );
                $product['merchant_price'] = $this->getPricingService()->calculatePrice(
                    $basePrice,
                    $product['category_id'] ?? null,
                    $product['brand_id'] ?? null,
                    $product['id'] ?? null,
                    'merchant'
                );
            } else {
                $product['customer_price'] = floatval($product['price'] ?? 0);
                $product['merchant_price'] = floatval($product['price'] ?? 0);
            }
        }
        unset($product);
        $total = $this->getProductModel()->count($filters);

        Response::success([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get single product by ID
     */
    public function getProduct($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        try {
            $product = $this->getProductModel()->getById($id);
            
            if (!$product) {
                Response::notFound('Product not found');
            }

            $basePrice = isset($product['base_price']) ? floatval($product['base_price']) : 0.0;
            if ($basePrice > 0) {
                $product['customer_price'] = $this->getPricingService()->calculatePrice(
                    $basePrice,
                    $product['category_id'] ?? null,
                    $product['brand_id'] ?? null,
                    $product['id'] ?? null,
                    'customer'
                );
                $product['merchant_price'] = $this->getPricingService()->calculatePrice(
                    $basePrice,
                    $product['category_id'] ?? null,
                    $product['brand_id'] ?? null,
                    $product['id'] ?? null,
                    'merchant'
                );
            } else {
                $product['customer_price'] = floatval($product['price'] ?? 0);
                $product['merchant_price'] = floatval($product['price'] ?? 0);
            }
            
            Response::success(['product' => $product]);
        } catch (Exception $e) {
            error_log("Get product error: " . $e->getMessage());
            Response::error('Failed to load product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create product (for in-house products)
     */
    public function createProduct()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Validate required fields
        if (empty($input['name']) || empty($input['sku'])) {
            Response::error('Name and SKU are required', 400);
        }

        try {
            // Generate slug from SKU
            $slug = $this->generateSlug($input['sku'], 'products');
            
            // Prepare product data
            $productData = [
                ':product_source' => 'own',
                ':vendor_article_id' => null,
                ':sku' => Sanitizer::string($input['sku']),
                ':ean' => !empty($input['ean']) ? Sanitizer::string($input['ean']) : null,
                ':name' => Sanitizer::string($input['name']),
                ':slug' => $slug,
                ':description' => !empty($input['description']) ? Sanitizer::string($input['description']) : null,
                ':category_id' => !empty($input['category_id']) ? intval($input['category_id']) : null,
                ':brand_id' => !empty($input['brand_id']) ? intval($input['brand_id']) : null,
                ':warranty_id' => !empty($input['warranty_id']) ? intval($input['warranty_id']) : null,
                ':base_price' => floatval($input['base_price'] ?? 0),
                ':price' => floatval($input['price'] ?? 0),
                ':currency' => 'EUR',
                ':stock_quantity' => intval($input['stock_quantity'] ?? 0),
                ':available_quantity' => intval($input['stock_quantity'] ?? 0),
                ':reserved_quantity' => 0,
                ':reorder_point' => intval($input['reorder_point'] ?? 0),
                ':warehouse_location' => !empty($input['warehouse_location']) ? Sanitizer::string($input['warehouse_location']) : null,
                ':is_available' => isset($input['is_available']) ? ($input['is_available'] ? 1 : 0) : 1,
                ':is_featured' => isset($input['is_featured']) ? ($input['is_featured'] ? 1 : 0) : 0,
                ':weight' => !empty($input['weight']) ? floatval($input['weight']) : null,
                ':dimensions' => !empty($input['dimensions']) ? Sanitizer::string($input['dimensions']) : null,
                ':color' => !empty($input['color']) ? Sanitizer::string($input['color']) : null,
                ':storage' => !empty($input['storage']) ? Sanitizer::string($input['storage']) : null,
                ':ram' => !empty($input['ram']) ? Sanitizer::string($input['ram']) : null,
                ':specifications' => !empty($input['specifications']) ? json_encode($input['specifications']) : null,
                ':meta_title' => !empty($input['meta_title']) ? Sanitizer::string($input['meta_title']) : null,
                ':meta_description' => !empty($input['meta_description']) ? Sanitizer::string($input['meta_description']) : null,
                ':last_synced_at' => null,
            ];

            // Handle language-specific fields
            foreach (['en', 'de', 'sk', 'fr', 'es', 'ru', 'it', 'tr', 'ro', 'pl'] as $lang) {
                $productData[":name_{$lang}"] = !empty($input["name_{$lang}"]) ? Sanitizer::string($input["name_{$lang}"]) : null;
                $productData[":description_{$lang}"] = !empty($input["description_{$lang}"]) ? Sanitizer::string($input["description_{$lang}"]) : null;
            }

            $productId = $this->getProductModel()->create($productData);
            
            // Handle image uploads if provided
            if (!empty($input['images']) && is_array($input['images'])) {
                foreach ($input['images'] as $index => $imageUrl) {
                    $isPrimary = ($index === 0); // First image is primary
                    $this->getProductModel()->addImage($productId, $imageUrl, null, $isPrimary);
                }
            }
            
            $product = $this->getProductModel()->getById($productId);
            $revalidatePayload = [
                'homeChanged' => true,
                'productsListingChanged' => true,
                'revalidateAllCategoryPages' => true,
                'revalidateAllBrandPages' => true,
            ];
            if (!empty($product['slug'])) {
                $revalidatePayload['productSlugs'] = [$product['slug']];
            }
            $this->invalidateContent([
                'products',
                'home',
                'product:' . intval($productId),
                !empty($product['category_id']) ? 'category:' . intval($product['category_id']) : '',
                !empty($product['brand_id']) ? 'brand:' . intval($product['brand_id']) : '',
            ], $revalidatePayload);
            
            Response::success(['product' => $product], 'Product created successfully');
        } catch (Exception $e) {
            error_log("Create product error: " . $e->getMessage());
            Response::error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update product (supports all fields for in-house products)
     */
    public function updateProduct($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        try {
            $updateData = [];
            
            // Basic fields
            if (isset($input['name'])) {
                $updateData['name'] = Sanitizer::string($input['name']);
            }
            if (isset($input['ean'])) {
                $updateData['ean'] = !empty($input['ean']) ? Sanitizer::string($input['ean']) : null;
            }
            if (isset($input['description'])) {
                $updateData['description'] = !empty($input['description']) ? Sanitizer::string($input['description']) : null;
            }
            if (isset($input['category_id'])) {
                $updateData['category_id'] = !empty($input['category_id']) ? intval($input['category_id']) : null;
            }
            if (isset($input['brand_id'])) {
                $updateData['brand_id'] = !empty($input['brand_id']) ? intval($input['brand_id']) : null;
            }
            if (isset($input['warranty_id'])) {
                $updateData['warranty_id'] = !empty($input['warranty_id']) ? intval($input['warranty_id']) : null;
            }
            
            // Pricing
            if (isset($input['base_price'])) {
                $updateData['base_price'] = floatval($input['base_price']);
            }
            if (isset($input['price'])) {
                $updateData['price'] = floatval($input['price']);
            }
            
            // Inventory
            if (isset($input['stock_quantity'])) {
                $stockQty = intval($input['stock_quantity']);
                $updateData['stock_quantity'] = $stockQty;
                $updateData['available_quantity'] = $stockQty; // Update available quantity too
            }
            if (isset($input['reorder_point'])) {
                $updateData['reorder_point'] = intval($input['reorder_point']);
            }
            if (isset($input['warehouse_location'])) {
                $updateData['warehouse_location'] = !empty($input['warehouse_location']) ? Sanitizer::string($input['warehouse_location']) : null;
            }
            
            // Physical properties
            if (isset($input['weight'])) {
                $updateData['weight'] = !empty($input['weight']) ? floatval($input['weight']) : null;
            }
            if (isset($input['dimensions'])) {
                $updateData['dimensions'] = !empty($input['dimensions']) ? Sanitizer::string($input['dimensions']) : null;
            }
            
            // Specifications
            if (isset($input['color'])) {
                $updateData['color'] = !empty($input['color']) ? Sanitizer::string($input['color']) : null;
            }
            if (isset($input['storage'])) {
                $updateData['storage'] = !empty($input['storage']) ? Sanitizer::string($input['storage']) : null;
            }
            if (isset($input['ram'])) {
                $updateData['ram'] = !empty($input['ram']) ? Sanitizer::string($input['ram']) : null;
            }
            if (isset($input['specifications'])) {
                $updateData['specifications'] = !empty($input['specifications']) ? json_encode($input['specifications']) : null;
            }
            
            // Status
            if (isset($input['is_available'])) {
                $updateData['is_available'] = $input['is_available'] ? 1 : 0;
            }
            if (isset($input['is_featured'])) {
                $updateData['is_featured'] = $input['is_featured'] ? 1 : 0;
            }
            
            // Language-specific fields
            foreach (['en', 'de', 'sk', 'fr', 'es', 'ru', 'it', 'tr', 'ro', 'pl'] as $lang) {
                if (isset($input["name_{$lang}"])) {
                    $updateData["name_{$lang}"] = !empty($input["name_{$lang}"]) ? Sanitizer::string($input["name_{$lang}"]) : null;
                }
                if (isset($input["description_{$lang}"])) {
                    $updateData["description_{$lang}"] = !empty($input["description_{$lang}"]) ? Sanitizer::string($input["description_{$lang}"]) : null;
                }
            }

            if (empty($updateData)) {
                Response::error('No valid fields to update', 400);
            }

            $this->getProductModel()->update($id, $updateData);
            
            // Handle image updates if provided
            if (isset($input['images']) && is_array($input['images'])) {
                // Delete existing images
                $db = Database::getConnection();
                $stmt = $db->prepare("DELETE FROM product_images WHERE product_id = :product_id");
                $stmt->execute([':product_id' => $id]);
                
                // Add new images
                foreach ($input['images'] as $index => $imageUrl) {
                    $isPrimary = ($index === 0);
                    $this->getProductModel()->addImage($id, $imageUrl, null, $isPrimary);
                }
            }
            
            $product = $this->getProductModel()->getById($id);
            $revalidatePayload = [
                'homeChanged' => true,
                'productsListingChanged' => true,
                'revalidateAllCategoryPages' => true,
                'revalidateAllBrandPages' => true,
            ];
            if (!empty($product['slug'])) {
                $revalidatePayload['productSlugs'] = [$product['slug']];
            }
            $this->invalidateContent([
                'products',
                'home',
                'product:' . intval($id),
                !empty($product['category_id']) ? 'category:' . intval($product['category_id']) : '',
                !empty($product['brand_id']) ? 'brand:' . intval($product['brand_id']) : '',
            ], $revalidatePayload);
            Response::success(['product' => $product], 'Product updated successfully');
        } catch (Exception $e) {
            error_log("Update product error: " . $e->getMessage());
            Response::error('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get pricing configuration
     */
    public function getPricing()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            $rules = $this->getPricingService()->getAllRules();
            $globalMarkup = $this->getPricingService()->getGlobalMarkups();

            Response::success([
                'global_markup' => $globalMarkup,
                'rules' => $rules
            ]);
        } catch (Exception $e) {
            Response::error('Failed to load pricing: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update global markup
     */
    public function updateGlobalMarkup()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['markup_value'])) {
            Response::error('Markup value is required', 400);
        }

        try {
            $markupValue = floatval($input['markup_value']);
            $accountType = $input['account_type'] ?? 'customer';
            if (!in_array($accountType, ['customer', 'merchant'], true)) {
                Response::error('Invalid account type', 400);
            }

            $this->getPricingService()->updateGlobalMarkup($markupValue, $accountType);
            
            // Optionally recalculate all prices
            if (!empty($input['recalculate'])) {
                $updated = $this->getPricingService()->recalculateAllPrices();
                $this->invalidateContent(
                    ['products', 'home', 'categories', 'brands'],
                    [
                        'homeChanged' => true,
                        'productsListingChanged' => true,
                        'revalidateAllProductPages' => true,
                        'revalidateAllCategoryPages' => true,
                        'revalidateAllBrandPages' => true,
                    ]
                );
                Response::success([
                    'account_type' => $accountType,
                    'markup_value' => $markupValue,
                    'products_updated' => $updated
                ], 'Global markup updated and prices recalculated');
            } else {
                $this->invalidateContent(
                    ['products', 'home', 'categories', 'brands'],
                    [
                        'homeChanged' => true,
                        'productsListingChanged' => true,
                        'revalidateAllProductPages' => true,
                        'revalidateAllCategoryPages' => true,
                        'revalidateAllBrandPages' => true,
                    ]
                );
                Response::success([
                    'account_type' => $accountType,
                    'markup_value' => $markupValue
                ], 'Global markup updated');
            }
        } catch (Exception $e) {
            Response::error('Failed to update markup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update category markup
     */
    public function updateCategoryMarkup($categoryId)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['markup_value'])) {
            Response::error('Markup value is required', 400);
        }

        try {
            $markupValue = floatval($input['markup_value']);
            $markupType = $input['markup_type'] ?? 'percentage';
            $accountType = $input['account_type'] ?? 'customer';
            if (!in_array($accountType, ['customer', 'merchant'], true)) {
                Response::error('Invalid account type', 400);
            }
            
            $this->getPricingService()->setCategoryMarkup($categoryId, $markupValue, $markupType, $accountType);
            $category = $this->getCategoryModel()->getById($categoryId);
            $this->invalidateContent(
                ['products', 'home', 'category:' . intval($categoryId)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'revalidateAllProductPages' => true,
                    'categorySlugs' => !empty($category['slug']) ? [$category['slug']] : [],
                ]
            );
            
            Response::success([
                'category_id' => $categoryId,
                'account_type' => $accountType,
                'markup_value' => $markupValue,
                'markup_type' => $markupType
            ], 'Category markup updated');
        } catch (Exception $e) {
            Response::error('Failed to update category markup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update product-specific pricing for customer and/or merchant
     * This updates only the selected product via product-level pricing rules.
     */
    public function updateProductPricing($productId)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        try {
            $product = $this->getProductModel()->getById($productId);
            if (!$product) {
                Response::notFound('Product not found');
            }

            $basePrice = floatval($product['base_price'] ?? 0);
            if ($basePrice <= 0) {
                Response::error('Product base price must be greater than zero', 400);
            }

            $updated = [];

            if (isset($input['customer_price'])) {
                $customerPrice = floatval($input['customer_price']);
                if ($customerPrice < 0) {
                    Response::error('Customer price must be zero or greater', 400);
                }
                $customerMarkup = $customerPrice - $basePrice;
                $this->getPricingService()->setProductMarkup($productId, $customerMarkup, 'fixed', 'customer');
                $updated['customer_price'] = round($customerPrice, 2);
            }

            if (isset($input['merchant_price'])) {
                $merchantPrice = floatval($input['merchant_price']);
                if ($merchantPrice < 0) {
                    Response::error('Merchant price must be zero or greater', 400);
                }
                $merchantMarkup = $merchantPrice - $basePrice;
                $this->getPricingService()->setProductMarkup($productId, $merchantMarkup, 'fixed', 'merchant');
                $updated['merchant_price'] = round($merchantPrice, 2);
            }

            if (empty($updated)) {
                Response::error('At least one price (customer_price or merchant_price) is required', 400);
            }

            $this->invalidateContent(
                [
                    'products',
                    'home',
                    'product:' . intval($productId),
                    !empty($product['category_id']) ? 'category:' . intval($product['category_id']) : '',
                    !empty($product['brand_id']) ? 'brand:' . intval($product['brand_id']) : '',
                ],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'productSlugs' => !empty($product['slug']) ? [$product['slug']] : [],
                    'categorySlugs' => !empty($product['category_slug']) ? [$product['category_slug']] : [],
                    'brandSlugs' => !empty($product['brand_slug']) ? [$product['brand_slug']] : [],
                ]
            );

            Response::success([
                'product_id' => (int)$productId,
                'updated' => $updated
            ], 'Product-specific pricing updated');
        } catch (Exception $e) {
            Response::error('Failed to update product pricing: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync products from vendor
     * Supports multi-language sync
     */
    public function syncProducts()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            if ($this->isSyncRunning()) {
                Response::error('A product sync is already in progress. Please wait for it to finish.', 409);
            }

            // Support specific language IDs or sync all languages
            $languageIds = null;
            
            if (isset($_GET['languages'])) {
                // Parse comma-separated language IDs: ?languages=1,3,4
                $languageIds = array_map('intval', explode(',', $_GET['languages']));
            } elseif (isset($_GET['lang'])) {
                // Single language support (backward compatibility)
                $langId = is_numeric($_GET['lang']) ? (int)$_GET['lang'] : Language::getIdFromCode($_GET['lang']);
                $languageIds = [$langId];
            }
            
            $this->dispatchProductSync($languageIds);

            Response::success([
                'status' => 'started',
                'languages' => $languageIds,
            ], 'Product sync started successfully');
        } catch (Exception $e) {
            Response::error('Sync failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sync status
     */
    public function syncStatus()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            $lastSync = $this->getProductSyncService()->getLastSyncStatus();
            
            Response::success(['last_sync' => $lastSync]);
        } catch (Exception $e) {
            Response::error('Failed to get sync status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create vendor sales order
     */
    public function createSalesOrder()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            $result = $this->getOrderService()->createVendorSalesOrder();
            
            if ($result['success']) {
                Response::success($result, 'Vendor sales order created successfully');
            } else {
                Response::error('Failed to create sales order', 500, $result['errors']);
            }
        } catch (Exception $e) {
            Response::error('Failed to create sales order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all categories (admin)
     */
    public function getCategories()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        try {
            $categories = $this->getCategoryModel()->getAll('en');
            Response::success(['categories' => $categories]);
        } catch (Exception $e) {
            Response::error('Failed to load categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create category
     */
    public function createCategory()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name'])) {
            Response::error('Name is required', 400);
        }

        try {
            $name = Sanitizer::string($input['name']);
            
            // Auto-generate slug from name
            $slug = $this->generateSlug($name, 'categories');
            
            // Check if slug already exists
            if ($this->slugExists($slug, 'categories')) {
                Response::error('A category with a similar name already exists. Please use a different name.', 409);
            }

            $data = [
                ':vendor_id' => null,
                ':name' => $name,
                ':slug' => $slug,
                ':parent_id' => !empty($input['parent_id']) ? intval($input['parent_id']) : null,
                ':description' => $input['description'] ?? null,
                ':image_url' => null,
                ':sort_order' => 0,
                ':is_active' => 1,
            ];

            // Initialize all language fields
            foreach (['en', 'de', 'sk', 'fr', 'es', 'ru', 'it', 'tr', 'ro', 'pl'] as $lang) {
                $data[":name_{$lang}"] = $input["name_{$lang}"] ?? ($lang === 'en' ? $name : null);
            }

            $categoryId = $this->getCategoryModel()->create($data);
            $category = $this->getCategoryModel()->getById($categoryId);
            $this->invalidateContent(
                ['categories', 'products', 'home', 'category:' . intval($categoryId)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'categorySlugs' => !empty($category['slug']) ? [$category['slug']] : [],
                    'revalidateAllCategoryPages' => true,
                ]
            );
            
            Response::success(['category' => $category], 'Category created successfully');
        } catch (Exception $e) {
            // Check if it's a duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                Response::error('A category with a similar name already exists. Please use a different name.', 409);
            }
            Response::error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update category
     */
    public function updateCategory($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        try {
            $updateData = [];
            if (isset($input['name'])) {
                $updateData['name'] = Sanitizer::string($input['name']);
            }
            if (isset($input['slug'])) {
                $updateData['slug'] = Sanitizer::string($input['slug']);
            }
            if (isset($input['description'])) {
                $updateData['description'] = $input['description'];
            }

            if (empty($updateData)) {
                Response::error('No valid fields to update', 400);
            }

            $this->getCategoryModel()->update($id, $updateData);
            $category = $this->getCategoryModel()->getById($id);
            $this->invalidateContent(
                ['categories', 'products', 'home', 'category:' . intval($id)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'categorySlugs' => !empty($category['slug']) ? [$category['slug']] : [],
                    'revalidateAllCategoryPages' => true,
                ]
            );
            
            Response::success(['category' => $category], 'Category updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete category (only if not used by in-house products)
     */
    public function deleteCategory($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            // Check if category is used by in-house products
            $sql = "SELECT COUNT(*) FROM products WHERE category_id = :id AND product_source = 'own'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $ownProductCount = $stmt->fetchColumn();

            if ($ownProductCount > 0) {
                Response::error('Cannot delete category: it is used by ' . $ownProductCount . ' in-house product(s)', 400);
            }

            // Soft delete by setting is_active = 0
            $this->getCategoryModel()->update($id, ['is_active' => 0]);
            $this->invalidateContent(
                ['categories', 'products', 'home', 'category:' . intval($id)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'revalidateAllCategoryPages' => true,
                    'revalidateAllProductPages' => true,
                ]
            );
            
            Response::success([], 'Category deleted successfully');
        } catch (Exception $e) {
            Response::error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all brands (admin)
     */
    public function getBrands()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        try {
            $brands = $this->getBrandModel()->getAll('en');
            Response::success(['brands' => $brands]);
        } catch (Exception $e) {
            Response::error('Failed to load brands: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create brand
     */
    public function createBrand()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name'])) {
            Response::error('Name is required', 400);
        }

        try {
            $name = Sanitizer::string($input['name']);
            
            // Auto-generate slug from name
            $slug = $this->generateSlug($name, 'brands');
            
            // Check if slug already exists
            if ($this->slugExists($slug, 'brands')) {
                Response::error('A brand with a similar name already exists. Please use a different name.', 409);
            }

            $data = [
                ':vendor_id' => null,
                ':name' => $name,
                ':slug' => $slug,
                ':logo_url' => $input['logo_url'] ?? null,
                ':description' => $input['description'] ?? null,
                ':website' => $input['website'] ?? null,
                ':is_active' => 1,
            ];

            $brandId = $this->getBrandModel()->create($data);
            $brand = $this->getBrandModel()->getById($brandId);
            $this->invalidateContent(
                ['brands', 'products', 'home', 'brand:' . intval($brandId)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'brandSlugs' => !empty($brand['slug']) ? [$brand['slug']] : [],
                    'revalidateAllBrandPages' => true,
                ]
            );
            
            Response::success(['brand' => $brand], 'Brand created successfully');
        } catch (Exception $e) {
            // Check if it's a duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                Response::error('A brand with a similar name already exists. Please use a different name.', 409);
            }
            Response::error('Failed to create brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update brand
     */
    public function updateBrand($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        try {
            $updateData = [];
            if (isset($input['name'])) {
                $updateData['name'] = Sanitizer::string($input['name']);
            }
            if (isset($input['slug'])) {
                $updateData['slug'] = Sanitizer::string($input['slug']);
            }
            if (isset($input['description'])) {
                $updateData['description'] = $input['description'];
            }

            if (empty($updateData)) {
                Response::error('No valid fields to update', 400);
            }

            $this->getBrandModel()->update($id, $updateData);
            $brand = $this->getBrandModel()->getById($id);
            $this->invalidateContent(
                ['brands', 'products', 'home', 'brand:' . intval($id)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'brandSlugs' => !empty($brand['slug']) ? [$brand['slug']] : [],
                    'revalidateAllBrandPages' => true,
                ]
            );
            
            Response::success(['brand' => $brand], 'Brand updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate URL-friendly slug
     */
    private function generateSlug($text, $table = 'categories')
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) {
            return $table . '-' . uniqid();
        }

        // Ensure uniqueness
        $slug = $text;
        $counter = 1;
        
        while ($this->slugExists($slug, $table)) {
            $slug = $text . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists in table
     */
    private function slugExists($slug, $table = 'categories')
    {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Delete brand (only if not used by in-house products)
     */
    public function deleteBrand($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            // Check if brand is used by in-house products
            $sql = "SELECT COUNT(*) FROM products WHERE brand_id = :id AND product_source = 'own'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $ownProductCount = $stmt->fetchColumn();

            if ($ownProductCount > 0) {
                Response::error('Cannot delete brand: it is used by ' . $ownProductCount . ' in-house product(s)', 400);
            }

            // Soft delete by setting is_active = 0
            $this->getBrandModel()->update($id, ['is_active' => 0]);
            $this->invalidateContent(
                ['brands', 'products', 'home', 'brand:' . intval($id)],
                [
                    'homeChanged' => true,
                    'productsListingChanged' => true,
                    'revalidateAllBrandPages' => true,
                    'revalidateAllProductPages' => true,
                ]
            );
            
            Response::success([], 'Brand deleted successfully');
        } catch (Exception $e) {
            Response::error('Failed to delete brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get settings (admin only - returns all settings)
     */
    public function getSettings()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        try {
            $settings = $this->getSettingsModel()->getAll();
            Response::success($settings);
        } catch (Exception $e) {
            Response::error('Failed to load settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get notification system health (admin only)
     */
    public function getNotificationHealth()
    {
        $this->authMiddleware->verifyAdmin();

        try {
            $health = $this->getEmailNotifications()->getAdminNotificationHealth();
            Response::success($health);
        } catch (Exception $e) {
            Response::error('Failed to load notification health: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Diagnostics stream for cache invalidation and frontend revalidation events.
     */
    public function cacheDiagnostics()
    {
        $this->authMiddleware->verifyAdmin();

        try {
            $enabled = strtolower((string) Env::get('CACHE_DIAGNOSTICS_ENABLED', 'true')) === 'true';
            if (!$enabled) {
                Response::forbidden('Cache diagnostics are disabled');
            }

            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
            $events = DiagnosticsLoggerService::readRecent($limit);

            Response::success([
                'events' => $events,
                'count' => count($events),
                'limit' => max(1, min(500, $limit)),
                'log_file' => 'storage/logs/cache-diagnostics.log',
            ]);
        } catch (Exception $e) {
            Response::error('Failed to load cache diagnostics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get public settings (no auth required - returns only public fields)
     */
    public function getPublicSettings()
    {
        try {
            $cacheKey = $this->apiCache->buildKey(
                'settings:public',
                ['lang' => $_GET['lang'] ?? 'en'],
                [],
                ['settings_public']
            );
            $cacheTtl = $this->apiCache->getTtlTaxonomy();
            if ($this->apiCache->isEnabled() && $cacheKey !== '') {
                $entry = $this->apiCache->get($cacheKey);
                if ($entry) {
                    http_response_code(intval($entry['status_code'] ?? 200));
                    header('Content-Type: ' . ($entry['content_type'] ?? 'application/json; charset=utf-8'));
                    header('Cache-Control: public, max-age=' . intval($cacheTtl) . ', s-maxage=' . intval($cacheTtl));
                    header('X-API-Cache: HIT');
                    echo (string) ($entry['body'] ?? '');
                    exit;
                }
            }

            $allSettings = $this->getSettingsModel()->getAll();
            
            // Only return public-facing settings
            $publicSettings = [
                'site_name' => $allSettings['site_name'] ?? 'TeleTrade Hub',
                'site_email' => $allSettings['site_email'] ?? '',
                'address' => $allSettings['address'] ?? '',
                'contact_number' => $allSettings['contact_number'] ?? '',
                'whatsapp_number' => $allSettings['whatsapp_number'] ?? '',
                'facebook_url' => $allSettings['facebook_url'] ?? '',
                'twitter_url' => $allSettings['twitter_url'] ?? '',
                'instagram_url' => $allSettings['instagram_url'] ?? '',
                'youtube_url' => $allSettings['youtube_url'] ?? '',
                'bank_name' => $allSettings['bank_name'] ?? '',
                'account_holder' => $allSettings['account_holder'] ?? '',
                'iban' => $allSettings['iban'] ?? '',
                'bic' => $allSettings['bic'] ?? '',
                'bank_additional_info' => $allSettings['bank_additional_info'] ?? '',
            ];

            $payload = [
                'success' => true,
                'message' => 'Success',
                'data' => $publicSettings,
            ];
            $json = json_encode(
                $payload,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            );

            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            if ($this->apiCache->isEnabled() && $cacheKey !== '') {
                header('Cache-Control: public, max-age=' . intval($cacheTtl) . ', s-maxage=' . intval($cacheTtl));
                header('X-API-Cache: MISS');
                $this->apiCache->put($cacheKey, $json, 200, 'application/json; charset=utf-8', $cacheTtl);
            } else {
                header('X-API-Cache: BYPASS');
            }
            echo $json;
            exit;
        } catch (Exception $e) {
            Response::error('Failed to load settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update settings
     */
    public function updateSettings()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !is_array($input)) {
            Response::error('Invalid request data', 400);
        }

        try {
            // Sanitize string values
            $sanitized = [];
            foreach ($input as $key => $value) {
                if (is_string($value)) {
                    $sanitized[$key] = Sanitizer::string($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            
            $this->getSettingsModel()->updateMultiple($sanitized);
            $updatedSettings = $this->getSettingsModel()->getAll();
            $this->invalidateContent(
                ['settings_public', 'home'],
                [
                    'homeChanged' => true,
                ]
            );
            
            Response::success($updatedSettings, 'Settings updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Change admin password
     */
    public function changePassword()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        if (empty($input['current_password']) || empty($input['new_password'])) {
            Response::error('Current password and new password are required', 400);
        }

        try {
            // Verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM admin_users WHERE id = :id");
            $stmt->execute([':id' => $admin['id']]);
            $result = $stmt->fetch();
            
            if (!$result || !password_verify($input['current_password'], $result['password_hash'])) {
                Response::error('Current password is incorrect', 401);
            }

            // Validate new password
            if (strlen($input['new_password']) < 6) {
                Response::error('New password must be at least 6 characters long', 400);
            }

            // Hash and update new password
            $newPasswordHash = password_hash($input['new_password'], PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE admin_users SET password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':password_hash' => $newPasswordHash,
                ':id' => $admin['id']
            ]);

            Response::success(null, 'Password changed successfully');
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            Response::error('Failed to change password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Build a strong temporary password for emergency resets.
     */
    private function generateTemporaryPassword($length = 16)
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $special = '!@#$%^&*()-_=+';
        $all = $upper . $lower . $digits . $special;

        $password = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $special[random_int(0, strlen($special) - 1)]
        ];

        for ($i = count($password); $i < $length; $i++) {
            $password[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($password);
        return implode('', $password);
    }

    /**
     * Reset admin password to a temporary generated password.
     */
    public function resetPasswordToDefault()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        try {
            $temporaryPassword = $this->generateTemporaryPassword();
            $passwordHash = password_hash($temporaryPassword, PASSWORD_BCRYPT);
            
            $stmt = $this->db->prepare("UPDATE admin_users SET password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':id' => $admin['id']
            ]);

            Response::success([
                'temporary_password' => $temporaryPassword
            ], 'Password reset successfully. Save this temporary password now.');
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            Response::error('Failed to reset password: ' . $e->getMessage(), 500);
        }
    }
}
