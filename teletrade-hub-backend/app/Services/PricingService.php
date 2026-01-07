<?php

/**
 * Pricing Service
 * Handles markup calculation and pricing rules
 */
class PricingService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Calculate customer price with markup
     */
    public function calculatePrice($basePrice, $categoryId = null, $brandId = null, $productId = null)
    {
        $markup = $this->getApplicableMarkup($categoryId, $brandId, $productId);
        
        if ($markup['type'] === 'percentage') {
            $price = $basePrice * (1 + ($markup['value'] / 100));
        } else {
            $price = $basePrice + $markup['value'];
        }
        
        // Round to 2 decimal places to avoid floating point precision issues
        return round($price, 2);
    }

    /**
     * Get applicable markup rule (highest priority)
     */
    public function getApplicableMarkup($categoryId = null, $brandId = null, $productId = null)
    {
        // Priority: product > category > brand > global
        $sql = "SELECT * FROM pricing_rules WHERE is_active = 1";
        $conditions = [];
        $params = [];

        // Build conditions based on available IDs
        if ($productId !== null) {
            $conditions[] = "(rule_type = 'product' AND entity_id = :product_id)";
            $params[':product_id'] = $productId;
        }
        
        if ($categoryId !== null) {
            $conditions[] = "(rule_type = 'category' AND entity_id = :category_id)";
            $params[':category_id'] = $categoryId;
        }
        
        if ($brandId !== null) {
            $conditions[] = "(rule_type = 'brand' AND entity_id = :brand_id)";
            $params[':brand_id'] = $brandId;
        }
        
        $conditions[] = "(rule_type = 'global')";

        if (!empty($conditions)) {
            $sql .= " AND (" . implode(' OR ', $conditions) . ")";
        }

        $sql .= " ORDER BY priority DESC, id DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rule = $stmt->fetch();

        if ($rule) {
            return [
                'type' => $rule['markup_type'],
                'value' => floatval($rule['markup_value'])
            ];
        }

        // Default markup
        return [
            'type' => 'percentage',
            'value' => floatval(Env::get('DEFAULT_MARKUP_PERCENTAGE', 15.0))
        ];
    }

    /**
     * Get all pricing rules
     */
    public function getAllRules()
    {
        $sql = "SELECT pr.*,
                CASE 
                    WHEN pr.rule_type = 'category' THEN c.name
                    WHEN pr.rule_type = 'brand' THEN b.name
                    WHEN pr.rule_type = 'product' THEN p.name
                    ELSE 'Global'
                END as entity_name
                FROM pricing_rules pr
                LEFT JOIN categories c ON pr.rule_type = 'category' AND pr.entity_id = c.id
                LEFT JOIN brands b ON pr.rule_type = 'brand' AND pr.entity_id = b.id
                LEFT JOIN products p ON pr.rule_type = 'product' AND pr.entity_id = p.id
                ORDER BY pr.priority DESC, pr.rule_type, pr.id";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get global markup
     */
    public function getGlobalMarkup()
    {
        $sql = "SELECT * FROM pricing_rules WHERE rule_type = 'global' AND is_active = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }

    /**
     * Update global markup
     */
    public function updateGlobalMarkup($markupValue)
    {
        $sql = "UPDATE pricing_rules SET 
                markup_value = :markup_value
                WHERE rule_type = 'global'";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':markup_value' => $markupValue]);
    }

    /**
     * Create or update category markup
     */
    public function setCategoryMarkup($categoryId, $markupValue, $markupType = 'percentage')
    {
        // Check if rule exists
        $sql = "SELECT id FROM pricing_rules 
                WHERE rule_type = 'category' AND entity_id = :category_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category_id' => $categoryId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing rule
            $sql = "UPDATE pricing_rules SET 
                    markup_value = :markup_value,
                    markup_type = :markup_type
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $existing['id'],
                ':markup_value' => $markupValue,
                ':markup_type' => $markupType
            ]);
        } else {
            // Create new rule
            $sql = "INSERT INTO pricing_rules (
                rule_type, entity_id, markup_type, markup_value, priority, is_active
            ) VALUES (
                'category', :entity_id, :markup_type, :markup_value, 10, 1
            )";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':entity_id' => $categoryId,
                ':markup_type' => $markupType,
                ':markup_value' => $markupValue
            ]);
        }
    }

    /**
     * Create or update brand markup
     */
    public function setBrandMarkup($brandId, $markupValue, $markupType = 'percentage')
    {
        // Check if rule exists
        $sql = "SELECT id FROM pricing_rules 
                WHERE rule_type = 'brand' AND entity_id = :brand_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':brand_id' => $brandId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing rule
            $sql = "UPDATE pricing_rules SET 
                    markup_value = :markup_value,
                    markup_type = :markup_type
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $existing['id'],
                ':markup_value' => $markupValue,
                ':markup_type' => $markupType
            ]);
        } else {
            // Create new rule
            $sql = "INSERT INTO pricing_rules (
                rule_type, entity_id, markup_type, markup_value, priority, is_active
            ) VALUES (
                'brand', :entity_id, :markup_type, :markup_value, 5, 1
            )";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':entity_id' => $brandId,
                ':markup_type' => $markupType,
                ':markup_value' => $markupValue
            ]);
        }
    }

    /**
     * Delete pricing rule
     */
    public function deleteRule($ruleId)
    {
        $sql = "DELETE FROM pricing_rules WHERE id = :id AND rule_type != 'global'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $ruleId]);
    }

    /**
     * Recalculate all product prices
     */
    public function recalculateAllPrices()
    {
        $sql = "SELECT id, base_price, category_id, brand_id FROM products";
        $stmt = $this->db->query($sql);
        $products = $stmt->fetchAll();

        $updated = 0;

        foreach ($products as $product) {
            $newPrice = $this->calculatePrice(
                $product['base_price'],
                $product['category_id'],
                $product['brand_id'],
                $product['id']
            );

            $updateSql = "UPDATE products SET price = :price WHERE id = :id";
            $updateStmt = $this->db->prepare($updateSql);
            if ($updateStmt->execute([':id' => $product['id'], ':price' => $newPrice])) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Get markup percentage from rule
     */
    public function getMarkupPercentage($basePrice, $customerPrice)
    {
        if ($basePrice == 0) {
            return 0;
        }
        return (($customerPrice - $basePrice) / $basePrice) * 100;
    }

    /**
     * Get profit margin
     */
    public function getProfitMargin($basePrice, $customerPrice)
    {
        if ($customerPrice == 0) {
            return 0;
        }
        return (($customerPrice - $basePrice) / $customerPrice) * 100;
    }
}

