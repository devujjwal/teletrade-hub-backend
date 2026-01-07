<?php

require_once __DIR__ . '/../Utils/Language.php';

/**
 * Product Model
 */
class Product
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get all products with filters
     */
    public function getAll($filters = [], $page = 1, $limit = 20, $lang = 'en')
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT * FROM product_list_view WHERE 1=1";

        // Filter by category
        if (!empty($filters['category_id'])) {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        // Filter by brand
        if (!empty($filters['brand_id'])) {
            $sql .= " AND brand_id = :brand_id";
            $params[':brand_id'] = $filters['brand_id'];
        }

        // Filter by availability
        if (isset($filters['is_available'])) {
            $sql .= " AND is_available = :is_available";
            $params[':is_available'] = $filters['is_available'];
        }

        // Filter by price range
        if (!empty($filters['min_price'])) {
            $sql .= " AND price >= :min_price";
            $params[':min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $sql .= " AND price <= :max_price";
            $params[':max_price'] = $filters['max_price'];
        }

        // Filter by color
        if (!empty($filters['color'])) {
            $sql .= " AND color = :color";
            $params[':color'] = $filters['color'];
        }

        // Filter by storage
        if (!empty($filters['storage'])) {
            $sql .= " AND storage = :storage";
            $params[':storage'] = $filters['storage'];
        }

        // Filter by RAM
        if (!empty($filters['ram'])) {
            $sql .= " AND ram = :ram";
            $params[':ram'] = $filters['ram'];
        }

        // Filter by warranty
        if (!empty($filters['warranty_id'])) {
            $sql .= " AND warranty_id = :warranty_id";
            $params[':warranty_id'] = $filters['warranty_id'];
        }

        // Search query
        if (!empty($filters['search'])) {
            $searchValue = '%' . $filters['search'] . '%';
            $sql .= " AND (name LIKE :search_name OR sku LIKE :search_sku OR ean LIKE :search_ean)";
            $params[':search_name'] = $searchValue;
            $params[':search_sku'] = $searchValue;
            $params[':search_ean'] = $searchValue;
        }

        // Featured products
        if (!empty($filters['is_featured'])) {
            $sql .= " AND is_featured = 1";
        }

        // Sorting - SECURITY: Use whitelist to prevent SQL injection
        $allowedSort = ['price', 'name', 'created_at', 'stock_quantity'];
        $sortBy = in_array($filters['sort'] ?? '', $allowedSort, true) ? $filters['sort'] : 'created_at';
        $sortOrder = strtoupper($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY " . $sortBy . " " . $sortOrder;

        // Pagination
        $sql .= " LIMIT :limit OFFSET :offset";

        // Add pagination to params
        $params[':limit'] = (int)$limit;
        $params[':offset'] = (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $products = $stmt->fetchAll();

        // Apply language-specific fields
        return $this->applyLanguage($products, $lang);
    }

    /**
     * Get total count with filters
     */
    public function count($filters = [])
    {
        $params = [];
        $sql = "SELECT COUNT(*) FROM products WHERE 1=1";

        if (!empty($filters['category_id'])) {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        if (!empty($filters['brand_id'])) {
            $sql .= " AND brand_id = :brand_id";
            $params[':brand_id'] = $filters['brand_id'];
        }
        if (isset($filters['is_available'])) {
            $sql .= " AND is_available = :is_available";
            $params[':is_available'] = $filters['is_available'];
        }
        if (!empty($filters['search'])) {
            $searchValue = '%' . $filters['search'] . '%';
            $sql .= " AND (name LIKE :search_name OR sku LIKE :search_sku OR ean LIKE :search_ean)";
            $params[':search_name'] = $searchValue;
            $params[':search_sku'] = $searchValue;
            $params[':search_ean'] = $searchValue;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Get product by ID
     */
    public function getById($id, $lang = 'en')
    {
        $sql = "SELECT * FROM product_list_view WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        // Get all images
        $product['images'] = $this->getImages($id);

        return $this->applyLanguage([$product], $lang)[0];
    }

    /**
     * Get product by slug (case-insensitive)
     * Falls back to vendor_article_id or SKU if slug not found
     */
    public function getBySlug($slug, $lang = 'en')
    {
        // First try by slug
        $sql = "SELECT * FROM product_list_view WHERE LOWER(slug) = LOWER(:slug)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch();

        // If not found by slug, try by vendor_article_id (in case slug is actually a SKU)
        if (!$product) {
            $sql = "SELECT * FROM product_list_view WHERE vendor_article_id = :slug OR sku = :slug";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':slug' => $slug]);
            $product = $stmt->fetch();
        }

        if (!$product) {
            return null;
        }

        // Get all images
        $product['images'] = $this->getImages($product['id']);

        return $this->applyLanguage([$product], $lang)[0];
    }

    /**
     * Get product by vendor article ID
     */
    public function getByVendorArticleId($vendorArticleId)
    {
        $sql = "SELECT * FROM products WHERE vendor_article_id = :vendor_article_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':vendor_article_id' => $vendorArticleId]);
        return $stmt->fetch();
    }

    /**
     * Get product images
     */
    public function getImages($productId)
    {
        $sql = "SELECT * FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    /**
     * Create new product
     * Supports all language-specific columns
     */
    public function create($data)
    {
        $sql = "INSERT INTO products (
            vendor_article_id, sku, ean, name, 
            name_en, name_de, name_sk, name_fr, name_es, name_ru, name_it, name_tr, name_ro, name_pl,
            description, description_en, description_de, description_sk, 
            description_fr, description_es, description_ru, description_it, description_tr, description_ro, description_pl,
            category_id, brand_id, warranty_id, base_price, price, currency,
            stock_quantity, available_quantity, is_available, weight, dimensions,
            color, storage, ram, specifications, slug, last_synced_at
        ) VALUES (
            :vendor_article_id, :sku, :ean, :name, 
            :name_en, :name_de, :name_sk, :name_fr, :name_es, :name_ru, :name_it, :name_tr, :name_ro, :name_pl,
            :description, :description_en, :description_de, :description_sk, 
            :description_fr, :description_es, :description_ru, :description_it, :description_tr, :description_ro, :description_pl,
            :category_id, :brand_id, :warranty_id, :base_price, :price, :currency,
            :stock_quantity, :available_quantity, :is_available, :weight, :dimensions,
            :color, :storage, :ram, :specifications, :slug, :last_synced_at
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Update product
     * SECURITY: Whitelist allowed fields to prevent mass assignment
     */
    public function update($id, $data)
    {
        // Whitelist of updatable fields (includes all language-specific columns)
        $allowedFields = [
            'vendor_article_id', 'sku', 'ean', 'name', 
            'name_en', 'name_de', 'name_sk', 'name_fr', 'name_es', 'name_ru', 
            'name_it', 'name_tr', 'name_ro', 'name_pl',
            'description', 'description_en', 'description_de', 'description_sk', 
            'description_fr', 'description_es', 'description_ru', 'description_it', 
            'description_tr', 'description_ro', 'description_pl',
            'category_id', 'brand_id', 'warranty_id', 'base_price', 'price', 'currency',
            'stock_quantity', 'available_quantity', 'is_available', 'is_featured', 'weight', 
            'dimensions', 'color', 'storage', 'ram', 'specifications', 'slug', 'last_synced_at'
        ];
        
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            // Remove : prefix if present
            $cleanKey = ltrim($key, ':');
            
            // Only allow whitelisted fields
            if (in_array($cleanKey, $allowedFields, true)) {
                $fields[] = "`$cleanKey` = :$cleanKey";
                $params[":$cleanKey"] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update stock quantity
     */
    public function updateStock($id, $quantity, $reserved = 0)
    {
        $sql = "UPDATE products SET 
                stock_quantity = :quantity,
                available_quantity = :quantity - :reserved,
                reserved_quantity = :reserved,
                is_available = CASE WHEN :quantity > 0 THEN 1 ELSE 0 END
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':quantity' => $quantity,
            ':reserved' => $reserved
        ]);
    }

    /**
     * Reserve stock
     */
    public function reserveStock($id, $quantity)
    {
        $sql = "UPDATE products SET 
                reserved_quantity = reserved_quantity + :quantity,
                available_quantity = available_quantity - :quantity
                WHERE id = :id AND available_quantity >= :quantity";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':quantity' => $quantity]);
    }

    /**
     * Release reserved stock
     */
    public function releaseStock($id, $quantity)
    {
        $sql = "UPDATE products SET 
                reserved_quantity = reserved_quantity - :quantity,
                available_quantity = available_quantity + :quantity
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':quantity' => $quantity]);
    }

    /**
     * Add product image
     */
    public function addImage($productId, $imageUrl, $altText = null, $isPrimary = false)
    {
        $sql = "INSERT INTO product_images (product_id, image_url, alt_text, is_primary)
                VALUES (:product_id, :image_url, :alt_text, :is_primary)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':product_id' => $productId,
            ':image_url' => $imageUrl,
            ':alt_text' => $altText,
            ':is_primary' => $isPrimary ? 1 : 0
        ]);
    }

    /**
     * Apply language-specific fields
     * Uses Language utility for proper language handling
     */
    private function applyLanguage($products, $lang)
    {
        // Normalize language (supports both ID and code)
        $langCode = Language::normalize($lang);
        
        // Get fallback chain (e.g., ['fr', 'en'])
        $fallbackChain = Language::getFallbackChain($langCode);
        
        foreach ($products as &$product) {
            // Apply language-specific name with fallback
            $product['name'] = $this->getTranslatedField($product, 'name', $fallbackChain);
            
            // Apply language-specific description with fallback
            $product['description'] = $this->getTranslatedField($product, 'description', $fallbackChain);
            
            // Apply category name if present
            if (isset($product['category_name'])) {
                $product['category_name'] = $this->getTranslatedField(
                    $product, 
                    'category_name', 
                    $fallbackChain
                );
            }
            
            // Apply color translation from specifications if available
            if (!empty($product['specifications'])) {
                $specs = is_string($product['specifications']) 
                    ? json_decode($product['specifications'], true) 
                    : $product['specifications'];
                
                if (is_array($specs) && isset($specs['color_translations'])) {
                    // Try to get color for current language, fallback to English
                    $colorTranslations = $specs['color_translations'];
                    foreach ($fallbackChain as $fallbackLang) {
                        if (isset($colorTranslations[$fallbackLang])) {
                            $product['color'] = $colorTranslations[$fallbackLang];
                            break;
                        }
                    }
                }
            }
            
            // Add language metadata
            $product['language'] = $langCode;
        }
        
        return $products;
    }
    
    /**
     * Get translated field value with fallback chain
     * 
     * @param array $data Data array containing fields
     * @param string $fieldBase Base field name (e.g., 'name', 'description')
     * @param array $fallbackChain Array of language codes to try
     * @return mixed Translated value or base field value
     */
    private function getTranslatedField($data, $fieldBase, $fallbackChain)
    {
        // Try each language in the fallback chain
        foreach ($fallbackChain as $langCode) {
            $fieldName = "{$fieldBase}_{$langCode}";
            if (!empty($data[$fieldName])) {
                return $data[$fieldName];
            }
        }
        
        // Fallback to base field if no translation found
        return $data[$fieldBase] ?? '';
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        return [
            'colors' => $this->getUniqueValues('color'),
            'storage' => $this->getUniqueValues('storage'),
            'ram' => $this->getUniqueValues('ram'),
            'price_range' => $this->getPriceRange()
        ];
    }

    /**
     * Get unique values for a field
     * SECURITY: Whitelist allowed fields to prevent SQL injection
     */
    private function getUniqueValues($field)
    {
        // Whitelist allowed fields
        $allowedFields = ['color', 'storage', 'ram'];
        if (!in_array($field, $allowedFields, true)) {
            return [];
        }
        
        $sql = "SELECT DISTINCT `$field` FROM products WHERE `$field` IS NOT NULL AND is_available = 1 ORDER BY `$field`";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get price range
     */
    private function getPriceRange()
    {
        $sql = "SELECT MIN(price) as min, MAX(price) as max FROM products WHERE is_available = 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
}

