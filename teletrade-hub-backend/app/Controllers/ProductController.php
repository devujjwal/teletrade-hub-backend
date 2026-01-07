<?php

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/../Middlewares/LanguageMiddleware.php';

/**
 * Product Controller
 * Handles product-related API endpoints
 */
class ProductController
{
    private $productModel;
    private $categoryModel;
    private $brandModel;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        $this->brandModel = new Brand();
        
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
        if (isset($_GET['is_available'])) {
            $filters['is_available'] = $_GET['is_available'] === '1' ? 1 : 0;
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
            $products = $this->productModel->getAll($filters, $page, $limit, $lang);
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
            // Bypass production error sanitization for debugging
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Search error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'query' => $_GET['q'] ?? null
            ]);
            exit;
        } catch (Error $e) {
            // Catch PHP errors
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'PHP Error in search',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
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

        $brand = $this->brandModel->getBySlug($brandSlug);
        if (!$brand) {
            Response::notFound('Brand not found');
        }

        $filters = ['brand_id' => $brand['id'], 'is_available' => 1];
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
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

