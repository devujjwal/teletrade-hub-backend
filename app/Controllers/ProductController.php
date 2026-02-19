<?php

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/../Services/PricingService.php';
require_once __DIR__ . '/../Middlewares/LanguageMiddleware.php';
if (!class_exists('Env')) {
    require_once __DIR__ . '/../Config/env.php';
}

/**
 * Product Controller
 * Handles product-related API endpoints
 */
class ProductController
{
    private $productModel;
    private $categoryModel;
    private $brandModel;
    private $pricingService;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        $this->brandModel = new Brand();
        $this->pricingService = new PricingService();
        
        // Initialize language middleware
        LanguageMiddleware::handle();
    }

    /**
     * Get products list with filters
     */
    public function index()
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        // Build filters
        $filters = [];
        
        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = intval($_GET['category_id']);
        }
        if (!empty($_GET['brand_id'])) {
            $filters['brand_id'] = intval($_GET['brand_id']);
        }
        if (!empty($_GET['min_price'])) {
            $filters['min_price'] = floatval($_GET['min_price']);
        }
        if (!empty($_GET['max_price'])) {
            $filters['max_price'] = floatval($_GET['max_price']);
        }
        if (!empty($_GET['color'])) {
            $filters['color'] = Sanitizer::string($_GET['color']);
        }
        if (!empty($_GET['storage'])) {
            $filters['storage'] = Sanitizer::string($_GET['storage']);
        }
        if (!empty($_GET['ram'])) {
            $filters['ram'] = Sanitizer::string($_GET['ram']);
        }
        if (!empty($_GET['warranty_id'])) {
            $filters['warranty_id'] = intval($_GET['warranty_id']);
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = Sanitizer::string($_GET['search']);
        }
        // By default, only show available products (in stock)
        // This applies to ALL product queries including featured products, related products, etc.
        // Allow override via query parameter:
        // - is_available=1 (default): only in-stock products
        // - is_available=0: only out-of-stock products
        // - is_available=all: show all products (including out of stock)
        $isAvailableParam = $_GET['is_available'] ?? null;
        if ($isAvailableParam !== null && $isAvailableParam !== '') {
            if ($isAvailableParam === 'all') {
                // Don't set is_available filter to show all products
                // This will be handled by not setting the filter
            } else {
                $filters['is_available'] = ($isAvailableParam === '1' || $isAvailableParam === 1) ? 1 : 0;
            }
        } else {
            // Default: only show in-stock products (is_available = 1)
            // This applies to all queries including featured products
            $filters['is_available'] = 1;
        }
        if (!empty($_GET['is_featured'])) {
            $filters['is_featured'] = 1;
        }
        if (!empty($_GET['sort'])) {
            $filters['sort'] = Sanitizer::string($_GET['sort']);
        }
        if (!empty($_GET['order'])) {
            $filters['order'] = Sanitizer::string($_GET['order']);
        }

        // Get products
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $viewer = $this->resolveViewerContext();
        $products = $this->applyViewerPricing($products, $viewer);
        $total = $this->productModel->count($filters);

        // Get filter options
        $filterOptions = $this->productModel->getFilterOptions();

        Response::success([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => $filterOptions,
            'language' => LanguageMiddleware::getLanguageInfo()
        ]);
    }

    /**
     * Get single product by slug
     */
    public function show($slug)
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $product = $this->productModel->getBySlug($slug, $lang);

        if (!$product) {
            Response::notFound('Product not found');
        }

        $viewer = $this->resolveViewerContext();
        $product = $this->applyViewerPricing([$product], $viewer)[0];

        Response::success([
            'product' => $product,
            'language' => LanguageMiddleware::getLanguageInfo()
        ]);
    }

    /**
     * Search products
     */
    public function search()
    {
        try {
            $query = Sanitizer::string($_GET['q'] ?? '');
            $lang = LanguageMiddleware::getCurrentLanguage();
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

            if (empty($query)) {
                Response::error('Search query is required', 400);
            }

            $filters = ['search' => $query];
            // By default, only show available products (in stock)
            if (!isset($_GET['is_available']) || $_GET['is_available'] !== 'all') {
                $filters['is_available'] = 1;
            }
            $products = $this->productModel->getAll($filters, $page, $limit, $lang);
            $viewer = $this->resolveViewerContext();
            $products = $this->applyViewerPricing($products, $viewer);
            $total = $this->productModel->count($filters);

            // Return empty results instead of error when no products found
            Response::success([
                'products' => $products,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => max(1, ceil($total / $limit))
                ],
                'query' => $query,
                'language' => LanguageMiddleware::getLanguageInfo()
            ]);
        } catch (Exception $e) {
            // SECURITY: Sanitize error messages in production
            $isDebug = Env::get('APP_DEBUG', 'false') === 'true';
            
            error_log("Product search error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            if ($isDebug) {
                // Only expose detailed errors in debug mode
                Response::error('Search error: ' . $e->getMessage(), 500, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'query' => Sanitizer::string($_GET['q'] ?? '')
                ]);
            } else {
                // Generic error message in production
                Response::error('Search temporarily unavailable. Please try again later.', 500);
            }
        } catch (Error $e) {
            // SECURITY: Sanitize PHP errors in production
            $isDebug = Env::get('APP_DEBUG', 'false') === 'true';
            
            error_log("PHP Error in product search: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            if ($isDebug) {
                Response::error('PHP Error in search: ' . $e->getMessage(), 500, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            } else {
                Response::error('An error occurred. Please try again later.', 500);
            }
        }
    }

    /**
     * Resolve viewer context from bearer token
     */
    private function resolveViewerContext()
    {
        $context = [
            'is_authenticated' => false,
            'account_type' => 'customer',
            'show_base_price' => false,
        ];

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $context;
        }

        $token = $matches[1];
        $db = Database::getConnection();

        // Customer token
        $sql = "SELECT u.account_type
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.token = :token
                AND us.expires_at > NOW()
                AND u.is_active = 1
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        if ($user) {
            $context['is_authenticated'] = true;
            $context['account_type'] = $user['account_type'] === 'merchant' ? 'merchant' : 'customer';
            return $context;
        }

        // Admin token (super admin sees base price)
        $sql = "SELECT au.role
                FROM admin_sessions asess
                JOIN admin_users au ON asess.admin_user_id = au.id
                WHERE asess.token = :token
                AND asess.expires_at > NOW()
                AND au.is_active = 1
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $admin = $stmt->fetch();
        if ($admin) {
            $context['is_authenticated'] = true;
            $context['account_type'] = 'customer';
            $context['show_base_price'] = ($admin['role'] ?? '') === 'super_admin';
        }

        return $context;
    }

    /**
     * Apply pricing for current viewer:
     * - customer markup for customers
     * - merchant markup for merchants
     * - base price for super_admin
     */
    private function applyViewerPricing(array $products, array $viewer)
    {
        foreach ($products as &$product) {
            $basePrice = floatval($product['base_price'] ?? 0);
            if (!empty($viewer['show_base_price'])) {
                $product['price'] = round($basePrice, 2);
                continue;
            }

            $product['price'] = $this->pricingService->calculatePrice(
                $basePrice,
                $product['category_id'] ?? null,
                $product['brand_id'] ?? null,
                $product['id'] ?? null,
                $viewer['account_type'] ?? 'customer'
            );
        }
        unset($product);

        return $products;
    }

    /**
     * Get all categories
     */
    public function categories()
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $categories = $this->categoryModel->getAllWithProductCount($lang);

        Response::success([
            'categories' => $categories,
            'language' => LanguageMiddleware::getLanguageInfo()
        ]);
    }

    /**
     * Get products by category slug
     */
    public function productsByCategory($categorySlug)
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        $category = $this->categoryModel->getBySlug($categorySlug, $lang);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $filters = ['category_id' => $category['id'], 'is_available' => 1];
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $viewer = $this->resolveViewerContext();
        $products = $this->applyViewerPricing($products, $viewer);
        $total = $this->productModel->count($filters);

        Response::success([
            'category' => $category,
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'language' => LanguageMiddleware::getLanguageInfo()
        ]);
    }

    /**
     * Get all brands
     */
    public function brands()
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $brands = $this->brandModel->getAllWithProductCount($lang);
        Response::success([
            'brands' => $brands,
            'language' => LanguageMiddleware::getLanguageInfo()
        ]);
    }

    /**
     * Get products by brand slug
     */
    public function productsByBrand($brandSlug)
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        $brand = $this->brandModel->getBySlug($brandSlug, $lang);
        if (!$brand) {
            Response::notFound('Brand not found');
        }

        $filters = ['brand_id' => $brand['id'], 'is_available' => 1];
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $viewer = $this->resolveViewerContext();
        $products = $this->applyViewerPricing($products, $viewer);
        $total = $this->productModel->count($filters);

        Response::success([
            'brand' => $brand,
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'language' => LanguageMiddleware::getLanguageInfo()
        ]);
    }
    
    /**
     * Get all supported languages
     */
    public function languages()
    {
        $languages = Language::getAllLanguages();
        
        Response::success([
            'languages' => $languages,
            'current' => LanguageMiddleware::getLanguageInfo()
        ]);
    }
}
