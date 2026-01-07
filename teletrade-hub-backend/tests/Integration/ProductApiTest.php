<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for Product API
 * Tests ProductController endpoints with database
 */
class ProductApiTest extends TestCase
{
    private $controller;
    private $db;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        $this->setupTestData();
        $this->controller = new ProductController();
        
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    private function setupTestData()
    {
        // Insert categories
        $this->db->exec("
            INSERT INTO categories (id, name, slug) VALUES 
            (1, 'Smartphones', 'smartphones'),
            (2, 'Tablets', 'tablets'),
            (3, 'Laptops', 'laptops')
        ");
        
        // Insert brands
        $this->db->exec("
            INSERT INTO brands (id, name, slug, logo_url) VALUES 
            (1, 'Apple', 'apple', 'https://example.com/apple.png'),
            (2, 'Samsung', 'samsung', 'https://example.com/samsung.png'),
            (3, 'Huawei', 'huawei', 'https://example.com/huawei.png')
        ");
        
        // Insert products
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price, available_quantity, is_available, is_featured, color, storage, ram) VALUES 
            (1, 'ART-001', 'SKU-001', 'iPhone 15 Pro', 1, 1, 900.00, 1035.00, 10, 1, 1, 'Black', '256GB', '8GB'),
            (2, 'ART-002', 'SKU-002', 'iPhone 15', 1, 1, 800.00, 920.00, 5, 1, 0, 'White', '128GB', '6GB'),
            (3, 'ART-003', 'SKU-003', 'Samsung Galaxy S24', 1, 2, 700.00, 805.00, 8, 1, 1, 'Blue', '256GB', '8GB'),
            (4, 'ART-004', 'SKU-004', 'iPad Pro', 2, 1, 1000.00, 1150.00, 3, 1, 0, 'Silver', '512GB', '8GB'),
            (5, 'ART-005', 'SKU-005', 'MacBook Pro', 3, 1, 2000.00, 2300.00, 2, 1, 1, 'Space Gray', '1TB', '16GB'),
            (6, 'ART-006', 'SKU-006', 'Out of Stock Phone', 1, 1, 500.00, 575.00, 0, 0, 0, 'Black', '64GB', '4GB')
        ");
    }
    
    /**
     * Test GET /products - List all products
     */
    public function testListProducts()
    {
        $_GET = ['page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('products', $response['data']);
        $this->assertArrayHasKey('pagination', $response['data']);
        
        // Should have 5 available products (excluding out of stock)
        $this->assertGreaterThanOrEqual(5, count($response['data']['products']));
    }
    
    /**
     * Test GET /products - Filter by category
     */
    public function testFilterByCategory()
    {
        $_GET = ['category_id' => 1, 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // All products should be from category 1 (Smartphones)
        foreach ($response['data']['products'] as $product) {
            $this->assertEquals(1, intval($product['category_id']));
        }
    }
    
    /**
     * Test GET /products - Filter by brand
     */
    public function testFilterByBrand()
    {
        $_GET = ['brand_id' => 1, 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // All products should be from brand 1 (Apple)
        foreach ($response['data']['products'] as $product) {
            $this->assertEquals(1, intval($product['brand_id']));
        }
    }
    
    /**
     * Test GET /products - Filter by price range
     */
    public function testFilterByPriceRange()
    {
        $_GET = ['min_price' => 500, 'max_price' => 1000, 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // All products should be within price range
        foreach ($response['data']['products'] as $product) {
            $price = floatval($product['price']);
            $this->assertGreaterThanOrEqual(500, $price);
            $this->assertLessThanOrEqual(1000, $price);
        }
    }
    
    /**
     * Test GET /products - Filter by attributes
     */
    public function testFilterByAttributes()
    {
        $_GET = ['color' => 'Black', 'storage' => '256GB', 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // All products should match filters
        foreach ($response['data']['products'] as $product) {
            if (isset($product['color'])) {
                $this->assertEquals('Black', $product['color']);
            }
            if (isset($product['storage'])) {
                $this->assertEquals('256GB', $product['storage']);
            }
        }
    }
    
    /**
     * Test GET /products - Only featured products
     */
    public function testFilterFeaturedProducts()
    {
        $_GET = ['is_featured' => 1, 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // All products should be featured
        foreach ($response['data']['products'] as $product) {
            $this->assertEquals(1, intval($product['is_featured']));
        }
    }
    
    /**
     * Test GET /products - Pagination
     */
    public function testPagination()
    {
        // Page 1
        $_GET = ['page' => 1, 'limit' => 2];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals(1, $response['data']['pagination']['page']);
        $this->assertEquals(2, $response['data']['pagination']['limit']);
        $this->assertCount(2, $response['data']['products']);
        
        // Page 2
        $_GET = ['page' => 2, 'limit' => 2];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response2 = json_decode($output, true);
        
        $this->assertEquals(2, $response2['data']['pagination']['page']);
        
        // Products should be different
        $this->assertNotEquals(
            $response['data']['products'][0]['id'],
            $response2['data']['products'][0]['id']
        );
    }
    
    /**
     * Test GET /products - Search functionality
     */
    public function testSearchProducts()
    {
        $_GET = ['search' => 'iPhone', 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // All products should contain 'iPhone' in name
        foreach ($response['data']['products'] as $product) {
            $this->assertStringContainsStringIgnoringCase('iPhone', $product['name']);
        }
    }
    
    /**
     * Test GET /products/:id - Get single product
     */
    public function testGetSingleProduct()
    {
        $_GET = ['lang' => 'en'];
        
        ob_start();
        $this->controller->show(1);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('product', $response['data']);
        $this->assertEquals(1, intval($response['data']['product']['id']));
        $this->assertEquals('iPhone 15 Pro', $response['data']['product']['name']);
    }
    
    /**
     * Test GET /products/:id - Product not found
     */
    public function testGetNonExistentProduct()
    {
        $_GET = ['lang' => 'en'];
        
        ob_start();
        $this->controller->show(999);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertEquals(404, $response['code']);
    }
    
    /**
     * Test GET /categories - List all categories
     */
    public function testListCategories()
    {
        $_GET = ['lang' => 'en'];
        
        ob_start();
        $this->controller->categories();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('categories', $response['data']);
        $this->assertCount(3, $response['data']['categories']);
    }
    
    /**
     * Test GET /brands - List all brands
     */
    public function testListBrands()
    {
        ob_start();
        $this->controller->brands();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('brands', $response['data']);
        $this->assertCount(3, $response['data']['brands']);
    }
    
    /**
     * Test GET /categories/:id/products
     */
    public function testGetProductsByCategory()
    {
        $_GET = ['lang' => 'en', 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->productsByCategory(1);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('category', $response['data']);
        $this->assertArrayHasKey('products', $response['data']);
        $this->assertEquals('Smartphones', $response['data']['category']['name']);
    }
    
    /**
     * Test GET /brands/:id/products
     */
    public function testGetProductsByBrand()
    {
        $_GET = ['lang' => 'en', 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->productsByBrand(1);
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('brand', $response['data']);
        $this->assertArrayHasKey('products', $response['data']);
        $this->assertEquals('Apple', $response['data']['brand']['name']);
    }
    
    /**
     * SECURITY TEST: XSS in search query
     */
    public function testXssInSearchQuery()
    {
        $_GET = ['search' => '<script>alert("XSS")</script>', 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        // Should not contain unescaped script tag
        $this->assertStringNotContainsString('<script>', $output);
    }
    
    /**
     * SECURITY TEST: SQL Injection in filters
     */
    public function testSqlInjectionInFilters()
    {
        $_GET = ['category_id' => "1' OR '1'='1", 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should handle gracefully, not expose error
        $this->assertIsArray($response);
    }
    
    /**
     * EDGE CASE: Negative page number
     */
    public function testNegativePageNumber()
    {
        $_GET = ['page' => -1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should default to page 1
        $this->assertEquals(1, $response['data']['pagination']['page']);
    }
    
    /**
     * EDGE CASE: Excessive limit
     */
    public function testExcessiveLimit()
    {
        $_GET = ['page' => 1, 'limit' => 10000];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should cap at maximum (100)
        $this->assertLessThanOrEqual(100, $response['data']['pagination']['limit']);
    }
    
    /**
     * TEST: Multi-language support
     */
    public function testMultiLanguageSupport()
    {
        $languages = ['en', 'de', 'sk'];
        
        foreach ($languages as $lang) {
            $_GET = ['lang' => $lang, 'page' => 1, 'limit' => 10];
            
            ob_start();
            $this->controller->index();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            $this->assertEquals('success', $response['status']);
        }
    }
    
    /**
     * TEST: Price sorting
     */
    public function testPriceSorting()
    {
        $_GET = ['sort' => 'price', 'order' => 'asc', 'page' => 1, 'limit' => 10];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify ascending order
        $prices = array_column($response['data']['products'], 'price');
        $sortedPrices = $prices;
        sort($sortedPrices);
        
        $this->assertEquals($sortedPrices, $prices);
    }
}

