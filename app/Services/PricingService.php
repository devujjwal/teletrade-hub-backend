<?php

/**
 * Pricing Service
 * Handles markup calculation and pricing rules
 */
class PricingService
{
    private $db;
    private $markupCache = [];
    private $ruleMapCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Calculate customer price with markup
     */
    public function calculatePrice($basePrice, $categoryId = null, $brandId = null, $productId = null, $accountType = 'customer')
    {
        $markup = $this->getApplicableMarkup($categoryId, $brandId, $productId, $accountType);
        
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
    public function getApplicableMarkup($categoryId = null, $brandId = null, $productId = null, $accountType = 'customer')
    {
        $cacheKey = implode(':', [
            $accountType,
            $categoryId ?? 'null',
            $brandId ?? 'null',
            $productId ?? 'null'
        ]);
        if (isset($this->markupCache[$cacheKey])) {
            return $this->markupCache[$cacheKey];
        }

        $ruleMaps = $this->getRuleMapsForAccount($accountType);
        $result = $ruleMaps['global'];

        if ($productId !== null) {
            $productRule = $ruleMaps['product'][intval($productId)] ?? null;
            if ($productRule !== null) {
                $result = $productRule;
                $this->markupCache[$cacheKey] = $result;
                return $result;
            }
        }
        if ($categoryId !== null) {
            $categoryRule = $ruleMaps['category'][intval($categoryId)] ?? null;
            if ($categoryRule !== null) {
                $result = $categoryRule;
                $this->markupCache[$cacheKey] = $result;
                return $result;
            }
        }
        if ($brandId !== null) {
            $brandRule = $ruleMaps['brand'][intval($brandId)] ?? null;
            if ($brandRule !== null) {
                $result = $brandRule;
                $this->markupCache[$cacheKey] = $result;
                return $result;
            }
        }

        $this->markupCache[$cacheKey] = $result;
        return $result;
    }

    private function getRuleMapsForAccount($accountType)
    {
        $cacheKey = strtolower((string) $accountType);
        if (isset($this->ruleMapCache[$cacheKey])) {
            return $this->ruleMapCache[$cacheKey];
        }

        $maps = [
            'product' => [],
            'category' => [],
            'brand' => [],
            'global' => [
                'type' => 'percentage',
                'value' => floatval(Env::get('DEFAULT_MARKUP_PERCENTAGE', 15.0))
            ],
        ];

        $sql = "SELECT rule_type, entity_id, markup_type, markup_value
                FROM pricing_rules
                WHERE is_active = 1
                AND (account_type = :account_type OR account_type IS NULL)
                AND rule_type IN ('product', 'category', 'brand', 'global')
                ORDER BY
                    CASE WHEN account_type = :account_type THEN 0 ELSE 1 END,
                    priority DESC,
                    id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':account_type' => $accountType,
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $ruleType = (string) ($row['rule_type'] ?? '');
            $ruleValue = [
                'type' => (string) ($row['markup_type'] ?? 'percentage'),
                'value' => floatval($row['markup_value'] ?? 0),
            ];

            if ($ruleType === 'global') {
                $maps['global'] = $ruleValue;
                continue;
            }

            $entityId = intval($row['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            if (!isset($maps[$ruleType][$entityId])) {
                $maps[$ruleType][$entityId] = $ruleValue;
            }
        }

        $this->ruleMapCache[$cacheKey] = $maps;
        return $maps;
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
                ORDER BY pr.account_type, pr.priority DESC, pr.rule_type, pr.id";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get global markup
     */
    public function getGlobalMarkup($accountType = 'customer')
    {
        $sql = "SELECT * FROM pricing_rules 
                WHERE rule_type = 'global' 
                AND is_active = 1 
                AND account_type = :account_type
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':account_type' => $accountType]);
        return $stmt->fetch();
    }

    /**
     * Get global markups for both customer and merchant
     */
    public function getGlobalMarkups()
    {
        return [
            'customer' => $this->getGlobalMarkup('customer'),
            'merchant' => $this->getGlobalMarkup('merchant'),
        ];
    }

    /**
     * Update global markup
     */
    public function updateGlobalMarkup($markupValue, $accountType = 'customer')
    {
        $sql = "SELECT id FROM pricing_rules WHERE rule_type = 'global' AND account_type = :account_type LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':account_type' => $accountType]);
        $existing = $stmt->fetch();

        if ($existing) {
            $sql = "UPDATE pricing_rules SET 
                    markup_value = :markup_value,
                    markup_type = 'percentage'
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':markup_value' => $markupValue,
                ':id' => $existing['id']
            ]);
        }

        $sql = "INSERT INTO pricing_rules (
                    rule_type, entity_id, markup_type, markup_value, priority, is_active, account_type
                ) VALUES (
                    'global', NULL, 'percentage', :markup_value, 0, 1, :account_type
                )";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':markup_value' => $markupValue,
            ':account_type' => $accountType
        ]);
    }

    /**
     * Create or update category markup
     */
    public function setCategoryMarkup($categoryId, $markupValue, $markupType = 'percentage', $accountType = 'customer')
    {
        // Check if rule exists
        $sql = "SELECT id FROM pricing_rules 
                WHERE rule_type = 'category' AND entity_id = :category_id AND account_type = :account_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':category_id' => $categoryId,
            ':account_type' => $accountType
        ]);
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
                rule_type, entity_id, markup_type, markup_value, priority, is_active, account_type
            ) VALUES (
                'category', :entity_id, :markup_type, :markup_value, 10, 1, :account_type
            )";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':entity_id' => $categoryId,
                ':markup_type' => $markupType,
                ':markup_value' => $markupValue,
                ':account_type' => $accountType
            ]);
        }
    }

    /**
     * Create or update brand markup
     */
    public function setBrandMarkup($brandId, $markupValue, $markupType = 'percentage', $accountType = 'customer')
    {
        // Check if rule exists
        $sql = "SELECT id FROM pricing_rules 
                WHERE rule_type = 'brand' AND entity_id = :brand_id AND account_type = :account_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':brand_id' => $brandId,
            ':account_type' => $accountType
        ]);
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
                rule_type, entity_id, markup_type, markup_value, priority, is_active, account_type
            ) VALUES (
                'brand', :entity_id, :markup_type, :markup_value, 5, 1, :account_type
            )";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':entity_id' => $brandId,
                ':markup_type' => $markupType,
                ':markup_value' => $markupValue,
                ':account_type' => $accountType
            ]);
        }
    }

    /**
     * Create or update product-specific markup
     */
    public function setProductMarkup($productId, $markupValue, $markupType = 'fixed', $accountType = 'customer')
    {
        $sql = "SELECT id FROM pricing_rules
                WHERE rule_type = 'product' AND entity_id = :product_id AND account_type = :account_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':product_id' => $productId,
            ':account_type' => $accountType
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
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
        }

        $sql = "INSERT INTO pricing_rules (
                    rule_type, entity_id, markup_type, markup_value, priority, is_active, account_type
                ) VALUES (
                    'product', :entity_id, :markup_type, :markup_value, 100, 1, :account_type
                )";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':entity_id' => $productId,
            ':markup_type' => $markupType,
            ':markup_value' => $markupValue,
            ':account_type' => $accountType
        ]);
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
