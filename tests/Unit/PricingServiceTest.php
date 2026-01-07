<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for PricingService
 * Tests pricing calculations and markup rules
 */
class PricingServiceTest extends TestCase
{
    private $pricingService;
    private $db;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->db = TestDatabase::getConnection();
        $this->pricingService = new PricingService();
    }
    
    /**
     * Test basic percentage markup calculation
     */
    public function testBasicPercentageMarkup()
    {
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice);
        
        // With 15% markup (default), should be 115.00
        $this->assertEquals(115.00, $customerPrice);
    }
    
    /**
     * Test fixed amount markup calculation
     */
    public function testFixedAmountMarkup()
    {
        // Create fixed markup rule
        $this->db->exec("DELETE FROM pricing_rules");
        $this->db->exec("
            INSERT INTO pricing_rules (rule_type, markup_type, markup_value, priority, is_active)
            VALUES ('global', 'fixed', 10.00, 0, 1)
        ");
        
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice);
        
        // With 10.00 fixed markup, should be 110.00
        $this->assertEquals(110.00, $customerPrice);
    }
    
    /**
     * Test category-specific markup priority
     */
    public function testCategoryMarkupPriority()
    {
        // Insert category
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Smartphones', 'smartphones')");
        
        // Create category markup (higher priority)
        $this->db->exec("
            INSERT INTO pricing_rules (rule_type, entity_id, markup_type, markup_value, priority, is_active)
            VALUES ('category', 1, 'percentage', 20.00, 10, 1)
        ");
        
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice, 1);
        
        // Category markup (20%) should override global (15%)
        $this->assertEquals(120.00, $customerPrice);
    }
    
    /**
     * Test brand-specific markup
     */
    public function testBrandMarkup()
    {
        // Insert brand
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Apple', 'apple')");
        
        // Create brand markup
        $this->db->exec("
            INSERT INTO pricing_rules (rule_type, entity_id, markup_type, markup_value, priority, is_active)
            VALUES ('brand', 1, 'percentage', 25.00, 5, 1)
        ");
        
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice, null, 1);
        
        // Brand markup (25%) should apply
        $this->assertEquals(125.00, $customerPrice);
    }
    
    /**
     * Test product-specific markup (highest priority)
     */
    public function testProductMarkupHighestPriority()
    {
        // Insert category and brand
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Smartphones', 'smartphones')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Apple', 'apple')");
        
        // Create multiple markup rules
        $this->db->exec("
            INSERT INTO pricing_rules (rule_type, entity_id, markup_type, markup_value, priority, is_active) VALUES
            ('category', 1, 'percentage', 20.00, 10, 1),
            ('brand', 1, 'percentage', 25.00, 5, 1),
            ('product', 1, 'percentage', 30.00, 20, 1)
        ");
        
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice, 1, 1, 1);
        
        // Product markup (30%) should have highest priority
        $this->assertEquals(130.00, $customerPrice);
    }
    
    /**
     * Test get applicable markup returns correct rule
     */
    public function testGetApplicableMarkup()
    {
        $markup = $this->pricingService->getApplicableMarkup();
        
        $this->assertIsArray($markup);
        $this->assertArrayHasKey('type', $markup);
        $this->assertArrayHasKey('value', $markup);
        $this->assertEquals('percentage', $markup['type']);
        $this->assertEquals(15.0, $markup['value']);
    }
    
    /**
     * Test update global markup
     */
    public function testUpdateGlobalMarkup()
    {
        $result = $this->pricingService->updateGlobalMarkup(20.0);
        $this->assertTrue($result);
        
        $globalMarkup = $this->pricingService->getGlobalMarkup();
        $this->assertEquals(20.0, floatval($globalMarkup['markup_value']));
    }
    
    /**
     * Test set category markup (create new)
     */
    public function testSetCategoryMarkupCreate()
    {
        // Insert category
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Tablets', 'tablets')");
        
        $result = $this->pricingService->setCategoryMarkup(1, 18.0, 'percentage');
        $this->assertTrue($result);
        
        // Verify it was created
        $stmt = $this->db->prepare("SELECT * FROM pricing_rules WHERE rule_type = 'category' AND entity_id = 1");
        $stmt->execute();
        $rule = $stmt->fetch();
        
        $this->assertNotNull($rule);
        $this->assertEquals(18.0, floatval($rule['markup_value']));
    }
    
    /**
     * Test set category markup (update existing)
     */
    public function testSetCategoryMarkupUpdate()
    {
        // Insert category and initial rule
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Tablets', 'tablets')");
        $this->db->exec("
            INSERT INTO pricing_rules (rule_type, entity_id, markup_type, markup_value, priority, is_active)
            VALUES ('category', 1, 'percentage', 15.0, 10, 1)
        ");
        
        $result = $this->pricingService->setCategoryMarkup(1, 22.0, 'percentage');
        $this->assertTrue($result);
        
        // Verify it was updated
        $stmt = $this->db->prepare("SELECT * FROM pricing_rules WHERE rule_type = 'category' AND entity_id = 1");
        $stmt->execute();
        $rule = $stmt->fetch();
        
        $this->assertEquals(22.0, floatval($rule['markup_value']));
    }
    
    /**
     * Test set brand markup
     */
    public function testSetBrandMarkup()
    {
        // Insert brand
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Samsung', 'samsung')");
        
        $result = $this->pricingService->setBrandMarkup(1, 17.5, 'percentage');
        $this->assertTrue($result);
        
        // Verify it was created
        $stmt = $this->db->prepare("SELECT * FROM pricing_rules WHERE rule_type = 'brand' AND entity_id = 1");
        $stmt->execute();
        $rule = $stmt->fetch();
        
        $this->assertNotNull($rule);
        $this->assertEquals(17.5, floatval($rule['markup_value']));
    }
    
    /**
     * Test calculate markup percentage
     */
    public function testGetMarkupPercentage()
    {
        $basePrice = 100.00;
        $customerPrice = 115.00;
        
        $markup = $this->pricingService->getMarkupPercentage($basePrice, $customerPrice);
        $this->assertEquals(15.0, $markup);
    }
    
    /**
     * Test calculate profit margin
     */
    public function testGetProfitMargin()
    {
        $basePrice = 100.00;
        $customerPrice = 125.00;
        
        $margin = $this->pricingService->getProfitMargin($basePrice, $customerPrice);
        $this->assertEquals(20.0, $margin);
    }
    
    /**
     * Test recalculate all product prices
     */
    public function testRecalculateAllPrices()
    {
        // Insert test products
        $this->db->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'Test', 'test')");
        $this->db->exec("INSERT INTO brands (id, name, slug) VALUES (1, 'Test Brand', 'test-brand')");
        
        $this->db->exec("
            INSERT INTO products (id, vendor_article_id, sku, name, category_id, brand_id, base_price, price)
            VALUES (1, 'TEST-001', 'SKU-001', 'Product 1', 1, 1, 100.00, 100.00),
                   (2, 'TEST-002', 'SKU-002', 'Product 2', 1, 1, 200.00, 200.00)
        ");
        
        $updated = $this->pricingService->recalculateAllPrices();
        $this->assertEquals(2, $updated);
        
        // Verify prices were updated
        $stmt = $this->db->query("SELECT id, price FROM products ORDER BY id");
        $products = $stmt->fetchAll();
        
        $this->assertEquals(115.00, floatval($products[0]['price'])); // 100 + 15%
        $this->assertEquals(230.00, floatval($products[1]['price'])); // 200 + 15%
    }
    
    /**
     * Test inactive rules are ignored
     */
    public function testInactiveRulesIgnored()
    {
        // Create inactive rule
        $this->db->exec("
            INSERT INTO pricing_rules (rule_type, entity_id, markup_type, markup_value, priority, is_active)
            VALUES ('category', 1, 'percentage', 50.00, 10, 0)
        ");
        
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice, 1);
        
        // Should use default 15%, not the inactive 50%
        $this->assertEquals(115.00, $customerPrice);
    }
    
    /**
     * SECURITY TEST: Ensure vendor base price != customer price
     */
    public function testVendorPriceNotEqualToCustomerPrice()
    {
        $basePrice = 100.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice);
        
        // CRITICAL: Customer should NEVER pay vendor price directly
        $this->assertNotEquals($basePrice, $customerPrice);
        $this->assertGreaterThan($basePrice, $customerPrice);
    }
    
    /**
     * EDGE CASE: Zero base price
     */
    public function testZeroBasePriceHandling()
    {
        $basePrice = 0.00;
        $customerPrice = $this->pricingService->calculatePrice($basePrice);
        
        // Should handle gracefully
        $this->assertEquals(0.00, $customerPrice);
    }
    
    /**
     * EDGE CASE: Very large price
     */
    public function testLargePriceHandling()
    {
        $basePrice = 999999.99;
        $customerPrice = $this->pricingService->calculatePrice($basePrice);
        
        // Should calculate correctly
        $expected = 999999.99 * 1.15;
        $this->assertEquals($expected, $customerPrice);
    }
    
    /**
     * EDGE CASE: Decimal precision
     */
    public function testDecimalPrecision()
    {
        $basePrice = 99.99;
        $customerPrice = $this->pricingService->calculatePrice($basePrice);
        
        // Should maintain proper decimal precision
        $expected = round(99.99 * 1.15, 2);
        $this->assertEquals($expected, round($customerPrice, 2));
    }
}

