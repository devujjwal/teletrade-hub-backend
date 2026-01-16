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
            $productStats = $this->db->query("
                SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_products,
                    SUM(stock_quantity) as total_stock
                FROM products
            ")->fetch();

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
                $orderModel->updatePaymentStatus($id, 'unpaid');
            } elseif (in_array($newStatus, ['processing', 'shipped', 'delivered'])) {
                // These statuses indicate order is being fulfilled, so payment must be confirmed
                if ($currentOrder['payment_status'] !== 'paid') {
                    $orderModel->updatePaymentStatus($id, 'paid');
                }
            }
            // For 'cancelled', don't auto-change payment status
            
            // Admin gets full order details
            $order = $this->getOrderService()->getOrderDetails($id, true);
            Response::success(['order' => $order], 'Order status updated');
        } catch (Exception $e) {
            Response::error('Failed to update order: ' . $e->getMessage(), 500);
        }
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
            $globalMarkup = $this->getPricingService()->getGlobalMarkup();

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
            $this->getPricingService()->updateGlobalMarkup($markupValue);
            
            // Optionally recalculate all prices
            if (!empty($input['recalculate'])) {
                $updated = $this->getPricingService()->recalculateAllPrices();
                Response::success([
                    'markup_value' => $markupValue,
                    'products_updated' => $updated
                ], 'Global markup updated and prices recalculated');
            } else {
                Response::success(['markup_value' => $markupValue], 'Global markup updated');
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
            
            $this->getPricingService()->setCategoryMarkup($categoryId, $markupValue, $markupType);
            
            Response::success([
                'category_id' => $categoryId,
                'markup_value' => $markupValue,
                'markup_type' => $markupType
            ], 'Category markup updated');
        } catch (Exception $e) {
            Response::error('Failed to update category markup: ' . $e->getMessage(), 500);
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
            
            // Sync products (null = all languages)
            $stats = $this->getProductSyncService()->syncProducts($languageIds);
            
            Response::success($stats, 'Product sync completed successfully');
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
     * Get public settings (no auth required - returns only public fields)
     */
    public function getPublicSettings()
    {
        try {
            $allSettings = $this->getSettingsModel()->getAll();
            
            // Only return public-facing settings
            $publicSettings = [
                'site_name' => $allSettings['site_name'] ?? 'TeleTrade Hub',
                'site_email' => $allSettings['site_email'] ?? '',
                'address' => $allSettings['address'] ?? '',
                'contact_number' => $allSettings['contact_number'] ?? '',
                'whatsapp_number' => $allSettings['whatsapp_number'] ?? '',
                'bank_name' => $allSettings['bank_name'] ?? '',
                'account_holder' => $allSettings['account_holder'] ?? '',
                'iban' => $allSettings['iban'] ?? '',
                'bic' => $allSettings['bic'] ?? '',
                'bank_additional_info' => $allSettings['bank_additional_info'] ?? '',
            ];
            
            Response::success($publicSettings);
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
     * Reset admin password to default (Ujjwal@2026)
     */
    public function resetPasswordToDefault()
    {
        $admin = $this->authMiddleware->verifyAdmin();
        
        try {
            $defaultPassword = 'Ujjwal@2026';
            $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
            
            $stmt = $this->db->prepare("UPDATE admin_users SET password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':id' => $admin['id']
            ]);

            Response::success(null, 'Password reset to default successfully');
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            Response::error('Failed to reset password: ' . $e->getMessage(), 500);
        }
    }
}

