<?php

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/../Services/PricingService.php';
require_once __DIR__ . '/../Services/ApiCacheService.php';
require_once __DIR__ . '/../Services/DiagnosticsLoggerService.php';
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
    private $apiCache;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        $this->brandModel = new Brand();
        $this->pricingService = new PricingService();
        $this->apiCache = new ApiCacheService();
        
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
        } elseif (!empty($_GET['category'])) {
            $category = $this->categoryModel->getBySlug(Sanitizer::string($_GET['category']), $lang);
            $filters['category_id'] = $category ? intval($category['id']) : -1;
        }
        if (!empty($_GET['brand_id'])) {
            $filters['brand_id'] = intval($_GET['brand_id']);
        } elseif (!empty($_GET['brand'])) {
            $brand = $this->brandModel->getBySlug(Sanitizer::string($_GET['brand']), $lang);
            $filters['brand_id'] = $brand ? intval($brand['id']) : -1;
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

        $viewer = $this->resolveViewerContext();
        $cacheTags = ['products'];
        if (!empty($filters['category_id']) && intval($filters['category_id']) > 0) {
            $cacheTags[] = 'category:' . intval($filters['category_id']);
        }
        if (!empty($filters['brand_id']) && intval($filters['brand_id']) > 0) {
            $cacheTags[] = 'brand:' . intval($filters['brand_id']);
        }

        $cacheKey = $this->apiCache->buildKey('products:index', [
            'lang' => $lang,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);
        $cacheTtl = $this->apiCache->getTtlProducts();
        $this->serveCached($cacheKey, $cacheTtl);

        // Get products
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $products = $this->applyViewerPricing($products, $viewer);
        $total = $this->productModel->count($filters);

        // Get filter options
        $filterOptions = $this->productModel->getFilterOptions();

        $this->respondSuccess([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => $filterOptions,
            'language' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
    }

    /**
     * Get single product by slug
     */
    public function show($slug)
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $viewer = $this->resolveViewerContext();
        $cacheTags = ['products', 'product:slug:' . strtolower((string) $slug)];
        $cacheKey = $this->apiCache->buildKey('products:show', [
            'lang' => $lang,
            'slug' => $slug,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);
        $cacheTtl = $this->apiCache->getTtlProducts();
        $this->serveCached($cacheKey, $cacheTtl);

        $product = $this->productModel->getBySlug($slug, $lang);

        if (!$product) {
            Response::notFound('Product not found');
        }

        $product = $this->applyViewerPricing([$product], $viewer)[0];
        $cacheTags[] = 'product:' . intval($product['id']);
        if (!empty($product['category_id'])) {
            $cacheTags[] = 'category:' . intval($product['category_id']);
        }
        if (!empty($product['brand_id'])) {
            $cacheTags[] = 'brand:' . intval($product['brand_id']);
        }

        $cacheKey = $this->apiCache->buildKey('products:show', [
            'lang' => $lang,
            'slug' => $slug,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);

        $this->respondSuccess([
            'product' => $product,
            'language' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
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

            $viewer = $this->resolveViewerContext();
            $filters = ['search' => $query];
            // By default, only show available products (in stock)
            if (!isset($_GET['is_available']) || $_GET['is_available'] !== 'all') {
                $filters['is_available'] = 1;
            }
            $cacheKey = $this->apiCache->buildKey('products:search', [
                'q' => $query,
                'lang' => $lang,
                'page' => $page,
                'limit' => $limit,
                'is_available' => $filters['is_available'] ?? 'all',
            ], [
                'account_type' => $viewer['account_type'] ?? 'customer',
                'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
            ], ['products']);
            $cacheTtl = $this->apiCache->getTtlProducts();
            $this->serveCached($cacheKey, $cacheTtl);

            $products = $this->productModel->getAll($filters, $page, $limit, $lang);
            $products = $this->applyViewerPricing($products, $viewer);
            $total = $this->productModel->count($filters);

            // Return empty results instead of error when no products found
            $this->respondSuccess([
                'products' => $products,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => max(1, ceil($total / $limit))
                ],
                'query' => $query,
                'language' => LanguageMiddleware::getLanguageInfo()
            ], 'Success', 200, $cacheKey, $cacheTtl);
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
        $cacheKey = $this->apiCache->buildKey('products:categories', [
            'lang' => $lang,
        ], [], ['categories']);
        $cacheTtl = $this->apiCache->getTtlTaxonomy();
        $this->serveCached($cacheKey, $cacheTtl);

        $categories = $this->categoryModel->getAllWithProductCount($lang);

        $this->respondSuccess([
            'categories' => $categories,
            'language' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
    }

    /**
     * Get products by category slug
     */
    public function productsByCategory($categorySlug)
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $viewer = $this->resolveViewerContext();
        $cacheTags = ['products', 'category:slug:' . strtolower((string) $categorySlug)];
        $cacheKey = $this->apiCache->buildKey('products:by_category', [
            'lang' => $lang,
            'slug' => $categorySlug,
            'page' => $page,
            'limit' => $limit,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);
        $cacheTtl = $this->apiCache->getTtlProducts();
        $this->serveCached($cacheKey, $cacheTtl);

        $category = $this->categoryModel->getBySlug($categorySlug, $lang);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $filters = ['category_id' => $category['id'], 'is_available' => 1];
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $products = $this->applyViewerPricing($products, $viewer);
        $total = $this->productModel->count($filters);
        $cacheTags[] = 'category:' . intval($category['id']);

        $cacheKey = $this->apiCache->buildKey('products:by_category', [
            'lang' => $lang,
            'slug' => $categorySlug,
            'page' => $page,
            'limit' => $limit,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);

        $this->respondSuccess([
            'category' => $category,
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'language' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
    }

    /**
     * Get all brands
     */
    public function brands()
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $cacheKey = $this->apiCache->buildKey('products:brands', [
            'lang' => $lang,
        ], [], ['brands']);
        $cacheTtl = $this->apiCache->getTtlTaxonomy();
        $this->serveCached($cacheKey, $cacheTtl);

        $brands = $this->brandModel->getAllWithProductCount($lang);
        $this->respondSuccess([
            'brands' => $brands,
            'language' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
    }

    /**
     * Get products by brand slug
     */
    public function productsByBrand($brandSlug)
    {
        $lang = LanguageMiddleware::getCurrentLanguage();
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $viewer = $this->resolveViewerContext();
        $cacheTags = ['products', 'brand:slug:' . strtolower((string) $brandSlug)];
        $cacheKey = $this->apiCache->buildKey('products:by_brand', [
            'lang' => $lang,
            'slug' => $brandSlug,
            'page' => $page,
            'limit' => $limit,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);
        $cacheTtl = $this->apiCache->getTtlProducts();
        $this->serveCached($cacheKey, $cacheTtl);

        $brand = $this->brandModel->getBySlug($brandSlug, $lang);
        if (!$brand) {
            Response::notFound('Brand not found');
        }

        $filters = ['brand_id' => $brand['id'], 'is_available' => 1];
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $products = $this->applyViewerPricing($products, $viewer);
        $total = $this->productModel->count($filters);
        $cacheTags[] = 'brand:' . intval($brand['id']);

        $cacheKey = $this->apiCache->buildKey('products:by_brand', [
            'lang' => $lang,
            'slug' => $brandSlug,
            'page' => $page,
            'limit' => $limit,
        ], [
            'account_type' => $viewer['account_type'] ?? 'customer',
            'show_base_price' => !empty($viewer['show_base_price']) ? 1 : 0,
        ], $cacheTags);

        $this->respondSuccess([
            'brand' => $brand,
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'language' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
    }
    
    /**
     * Get all supported languages
     */
    public function languages()
    {
        $cacheKey = $this->apiCache->buildKey('products:languages', [
            'lang' => LanguageMiddleware::getCurrentLanguage(),
        ], [], ['languages']);
        $cacheTtl = $this->apiCache->getTtlTaxonomy();
        $this->serveCached($cacheKey, $cacheTtl);

        $languages = Language::getAllLanguages();
        
        $this->respondSuccess([
            'languages' => $languages,
            'current' => LanguageMiddleware::getLanguageInfo()
        ], 'Success', 200, $cacheKey, $cacheTtl);
    }

    private function serveCached($cacheKey, $ttl)
    {
        if (!$this->apiCache->isEnabled() || $cacheKey === '') {
            return;
        }

        $entry = $this->apiCache->get($cacheKey);
        if (!$entry) {
            return;
        }

        if (class_exists('DiagnosticsLoggerService')) {
            DiagnosticsLoggerService::log('api_cache.hit', ['key' => $cacheKey]);
        }
        http_response_code(intval($entry['status_code'] ?? 200));
        header('Content-Type: ' . ($entry['content_type'] ?? 'application/json; charset=utf-8'));
        header('Cache-Control: public, max-age=' . intval($ttl) . ', s-maxage=' . intval($ttl));
        header('X-API-Cache: HIT');
        echo (string) ($entry['body'] ?? '');
        exit;
    }

    private function respondSuccess($data = null, $message = 'Success', $statusCode = 200, $cacheKey = '', $ttl = 0)
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        $json = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        if ($this->apiCache->isEnabled() && $cacheKey !== '' && $statusCode >= 200 && $statusCode < 300) {
            header('Cache-Control: public, max-age=' . intval($ttl) . ', s-maxage=' . intval($ttl));
            header('X-API-Cache: MISS');
            $this->apiCache->put($cacheKey, $json, $statusCode, 'application/json; charset=utf-8', $ttl);
            if (class_exists('DiagnosticsLoggerService')) {
                DiagnosticsLoggerService::log('api_cache.store', ['key' => $cacheKey, 'ttl' => intval($ttl)]);
            }
        } else {
            header('X-API-Cache: BYPASS');
        }

        echo $json;
        exit;
    }
}
