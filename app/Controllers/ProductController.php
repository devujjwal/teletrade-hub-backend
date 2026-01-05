<?php

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';

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
    }

    /**
     * Get products list with filters
     */
    public function index()
    {
        $lang = $_GET['lang'] ?? 'en';
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
            'filters' => $filterOptions
        ]);
    }

    /**
     * Get single product
     */
    public function show($id)
    {
        $lang = $_GET['lang'] ?? 'en';
        $product = $this->productModel->getById($id, $lang);

        if (!$product) {
            Response::notFound('Product not found');
        }

        Response::success(['product' => $product]);
    }

    /**
     * Search products
     */
    public function search()
    {
        $query = Sanitizer::string($_GET['q'] ?? '');
        $lang = $_GET['lang'] ?? 'en';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        if (empty($query)) {
            Response::error('Search query is required', 400);
        }

        $filters = ['search' => $query];
        $products = $this->productModel->getAll($filters, $page, $limit, $lang);
        $total = $this->productModel->count($filters);

        Response::success([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'query' => $query
        ]);
    }

    /**
     * Get all categories
     */
    public function categories()
    {
        $lang = $_GET['lang'] ?? 'en';
        $categories = $this->categoryModel->getAllWithProductCount($lang);

        Response::success(['categories' => $categories]);
    }

    /**
     * Get products by category
     */
    public function productsByCategory($categoryId)
    {
        $lang = $_GET['lang'] ?? 'en';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        $category = $this->categoryModel->getById($categoryId, $lang);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $filters = ['category_id' => $categoryId, 'is_available' => 1];
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
            ]
        ]);
    }

    /**
     * Get all brands
     */
    public function brands()
    {
        $brands = $this->brandModel->getAllWithProductCount();
        Response::success(['brands' => $brands]);
    }

    /**
     * Get products by brand
     */
    public function productsByBrand($brandId)
    {
        $lang = $_GET['lang'] ?? 'en';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        $brand = $this->brandModel->getById($brandId);
        if (!$brand) {
            Response::notFound('Brand not found');
        }

        $filters = ['brand_id' => $brandId, 'is_available' => 1];
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
            ]
        ]);
    }
}

