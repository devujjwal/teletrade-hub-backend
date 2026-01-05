<?php

require_once __DIR__ . '/../Middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../Services/OrderService.php';
require_once __DIR__ . '/../Services/ProductSyncService.php';
require_once __DIR__ . '/../Services/PricingService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/Product.php';

/**
 * Admin Controller
 * Handles admin-related API endpoints
 */
class AdminController
{
    private $authMiddleware;
    private $orderService;
    private $orderModel;
    private $productModel;
    private $productSyncService;
    private $pricingService;
    private $db;

    public function __construct()
    {
        $this->authMiddleware = new AuthMiddleware();
        $this->orderService = new OrderService();
        $this->orderModel = new Order();
        $this->productModel = new Product();
        $this->productSyncService = new ProductSyncService();
        $this->pricingService = new PricingService();
        $this->db = Database::getConnection();
    }

    /**
     * Admin login
     */
    public function login()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid request data', 400);
        }

        // Validate input
        $errors = Validator::validate($input, [
            'username' => 'required',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }

        try {
            // Get admin user
            $sql = "SELECT * FROM admin_users WHERE username = :username AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':username' => $input['username']]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($input['password'], $admin['password_hash'])) {
                Response::error('Invalid username or password', 401);
            }

            // Update last login
            $updateSql = "UPDATE admin_users SET last_login_at = NOW() WHERE id = :id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':id' => $admin['id']]);

            // Create session token
            $session = $this->authMiddleware->createAdminSession($admin['id']);

            // Remove password hash
            unset($admin['password_hash']);

            Response::success([
                'admin' => $admin,
                'token' => $session['token'],
                'expires_at' => $session['expires_at']
            ], 'Login successful');
        } catch (Exception $e) {
            Response::error('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Dashboard statistics
     */
    public function dashboard()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            $stats = $this->orderModel->getStatistics();
            
            // Get product stats
            $productStats = $this->db->query("
                SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_products,
                    SUM(stock_quantity) as total_stock
                FROM products
            ")->fetch();

            // Recent orders
            $recentOrders = $this->orderModel->getAll([], 1, 10);

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

        $orders = $this->orderModel->getAll($filters, $page, $limit);
        $total = $this->orderModel->count($filters);

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
     * Get order detail
     */
    public function orderDetail($id)
    {
        $admin = $this->authMiddleware->verifyAdmin();

        $order = $this->orderService->getOrderDetails($id);
        
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
            $this->orderModel->updateStatus($id, $input['status']);
            
            $order = $this->orderService->getOrderDetails($id);
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
        if (isset($_GET['is_available'])) {
            $filters['is_available'] = $_GET['is_available'] === '1' ? 1 : 0;
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = Sanitizer::string($_GET['search']);
        }

        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $total = $this->productModel->count($filters);

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
     * Update product
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
            
            if (isset($input['is_available'])) {
                $updateData['is_available'] = $input['is_available'] ? 1 : 0;
            }
            if (isset($input['is_featured'])) {
                $updateData['is_featured'] = $input['is_featured'] ? 1 : 0;
            }
            if (isset($input['price'])) {
                $updateData['price'] = floatval($input['price']);
            }

            if (empty($updateData)) {
                Response::error('No valid fields to update', 400);
            }

            $this->productModel->update($id, $updateData);
            
            $product = $this->productModel->getById($id);
            Response::success(['product' => $product], 'Product updated');
        } catch (Exception $e) {
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
            $rules = $this->pricingService->getAllRules();
            $globalMarkup = $this->pricingService->getGlobalMarkup();

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
            $this->pricingService->updateGlobalMarkup($markupValue);
            
            // Optionally recalculate all prices
            if (!empty($input['recalculate'])) {
                $updated = $this->pricingService->recalculateAllPrices();
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
            
            $this->pricingService->setCategoryMarkup($categoryId, $markupValue, $markupType);
            
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
     */
    public function syncProducts()
    {
        $admin = $this->authMiddleware->verifyAdmin();

        try {
            $lang = $_GET['lang'] ?? 'en';
            $stats = $this->productSyncService->syncProducts($lang);
            
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
            $lastSync = $this->productSyncService->getLastSyncStatus();
            
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
            $result = $this->orderService->createVendorSalesOrder();
            
            if ($result['success']) {
                Response::success($result, 'Vendor sales order created successfully');
            } else {
                Response::error('Failed to create sales order', 500, $result['errors']);
            }
        } catch (Exception $e) {
            Response::error('Failed to create sales order: ' . $e->getMessage(), 500);
        }
    }
}

