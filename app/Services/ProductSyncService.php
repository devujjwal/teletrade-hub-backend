<?php

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/VendorApiService.php';
require_once __DIR__ . '/PricingService.php';
require_once __DIR__ . '/../Utils/Language.php';

/**
 * Product Sync Service
 * Synchronizes products from vendor to local database
 * Supports multi-language sync
 */
class ProductSyncService
{
    private $vendorApi;
    private $productModel;
    private $categoryModel;
    private $brandModel;
    private $pricingService;
    private $db;
    private $syncLogId;

    public function __construct()
    {
        $this->vendorApi = new VendorApiService();
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        $this->brandModel = new Brand();
        $this->pricingService = new PricingService();
        $this->db = Database::getConnection();
    }

    /**
     * Perform full product sync across all supported languages
     * 
     * @param array $languageIds Array of language IDs to sync (default: all supported)
     * @return array Sync statistics
     */
    public function syncProducts($languageIds = null)
    {
        $this->startSyncLog();

        try {
            // If no specific languages provided, sync all supported languages
            if ($languageIds === null) {
                $languageIds = [1, 3, 4, 5, 6, 7, 8, 9, 10, 11]; // All except 0 (default)
            }
            
            $stats = [
                'synced' => 0,
                'added' => 0,
                'updated' => 0,
                'disabled' => 0,
                'languages' => []
            ];

            // First, sync in English (base language) to create/update products
            echo "Syncing products in English (base language)...\n";
            $baseStats = $this->syncProductsForLanguage(1); // 1 = English
            $stats['synced'] = $baseStats['synced'];
            $stats['added'] = $baseStats['added'];
            $stats['updated'] = $baseStats['updated'];
            $stats['languages']['en'] = $baseStats;
            
            $products = $baseStats['products'] ?? [];

            // Then sync other languages to populate translation columns
            foreach ($languageIds as $langId) {
                if ($langId == 1) continue; // Skip English, already done
                
                $langCode = Language::getCodeFromId($langId);
                $langInfo = Language::getLanguageById($langId);
                
                echo "Syncing translations for {$langInfo['name']} (ID: {$langId})...\n";
                
                try {
                    $langStats = $this->syncTranslationsForLanguage($langId, $products);
                    $stats['languages'][$langCode] = $langStats;
                } catch (Exception $e) {
                    error_log("Failed to sync language {$langCode}: " . $e->getMessage());
                    $stats['languages'][$langCode] = ['error' => $e->getMessage()];
                }
            }

            // Disable products that are no longer in vendor stock
            $stats['disabled'] = $this->disableUnavailableProducts($products);

            $this->completeSyncLog('completed', $stats);

            return $stats;
        } catch (Exception $e) {
            $this->completeSyncLog('failed', null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Sync products for a specific language (creates/updates products)
     * Used for base language sync
     */
    private function syncProductsForLanguage($languageId)
    {
        $stockData = $this->vendorApi->getStock($languageId);

        // Check for API errors
        if (isset($stockData['error']) && $stockData['error'] != 0) {
            $errorMsg = $stockData['error_msg'] ?? 'Unknown vendor API error';
            throw new Exception("Vendor API error: {$errorMsg}");
        }

        if (empty($stockData) || !isset($stockData['stock'])) {
            throw new Exception('Invalid stock data received from vendor');
        }

        $products = $stockData['stock'];
        $productCount = is_array($products) ? count($products) : 0;
        echo "Found {$productCount} products in vendor response\n";
        
        if ($productCount == 0) {
            echo "WARNING: No products to sync!\n";
            return [
                'synced' => 0,
                'added' => 0,
                'updated' => 0,
                'products' => []
            ];
        }
        
        $stats = [
            'synced' => 0,
            'added' => 0,
            'updated' => 0,
            'products' => []
        ];

        // Process each product
        $errorCount = 0;
        foreach ($products as $vendorProduct) {
            try {
                $this->syncSingleProduct($vendorProduct, $stats, $languageId);
                $stats['products'][] = $vendorProduct;
            } catch (Exception $e) {
                $errorCount++;
                $productId = $vendorProduct['sku'] ?? $vendorProduct['id'] ?? 'unknown';
                $errorMsg = "Failed to sync product {$productId}: " . $e->getMessage();
                error_log($errorMsg);
                echo "ERROR: {$errorMsg}\n";
                // Log first 5 errors in detail
                if ($errorCount <= 5) {
                    echo "Stack trace: " . $e->getTraceAsString() . "\n";
                }
                continue;
            }
        }
        
        if ($errorCount > 0) {
            echo "Total errors during sync: {$errorCount}\n";
        }

        return $stats;
    }
    
    /**
     * Sync translations for a specific language (updates only language columns)
     * Used for non-base language sync
     */
    private function syncTranslationsForLanguage($languageId, $baseProducts)
    {
        $langCode = Language::getCodeFromId($languageId);
        $stockData = $this->vendorApi->getStock($languageId);

        if (empty($stockData) || !isset($stockData['stock'])) {
            throw new Exception('Invalid stock data received from vendor');
        }

        $products = $stockData['stock'];
        $stats = ['translated' => 0, 'skipped' => 0];

        // Create SKU map for quick lookup
        $productMap = [];
        foreach ($products as $product) {
            $sku = $product['sku'] ?? null;
            if ($sku) {
                $productMap[$sku] = $product;
            }
        }

        // Update translations for existing products
        foreach ($baseProducts as $baseProduct) {
            $sku = $baseProduct['sku'] ?? null;
            if (!$sku || !isset($productMap[$sku])) {
                $stats['skipped']++;
                continue;
            }

            $translatedProduct = $productMap[$sku];
            
            try {
                // Get existing product from database
                $existingProduct = $this->productModel->getByVendorArticleId($sku);
                if (!$existingProduct) {
                    $stats['skipped']++;
                    continue;
                }

                // Extract translated fields
                $properties = $translatedProduct['properties'] ?? [];
                $productName = $properties['full_name'] ?? ($translatedProduct['model'] ?? null);
                // Handle case where cat_name might be empty but category has value
                $categoryName = !empty($translatedProduct['cat_name']) ? $translatedProduct['cat_name'] : ($translatedProduct['category'] ?? null);
                
                // Get color from vendor product (language-specific)
                $translatedColor = $translatedProduct['color'] ?? null;
                
                // Update language-specific columns
                $updateData = [
                    "name_{$langCode}" => $productName,
                    "description_{$langCode}" => null, // Vendor doesn't provide description
                ];
                
                // Store color translation in specifications JSON
                // We'll store color translations in the specifications JSON field
                $specifications = json_decode($existingProduct['specifications'] ?? '{}', true);
                if (!is_array($specifications)) {
                    $specifications = [];
                }
                
                // Store color translations in specifications
                if ($translatedColor) {
                    if (!isset($specifications['color_translations'])) {
                        $specifications['color_translations'] = [];
                    }
                    $specifications['color_translations'][$langCode] = $translatedColor;
                    
                    // If this is the base language (English), also update the main color field
                    if ($langCode === 'en' && empty($existingProduct['color'])) {
                        $updateData['color'] = $translatedColor;
                    }
                    
                    $updateData['specifications'] = json_encode($specifications);
                }

                $this->productModel->update($existingProduct['id'], $updateData);
                
                // Update category translation if exists
                if ($categoryName && $existingProduct['category_id']) {
                    $this->updateCategoryTranslation($existingProduct['category_id'], $langCode, $categoryName);
                }
                
                $stats['translated']++;
            } catch (Exception $e) {
                error_log("Failed to update translation for SKU {$sku} in {$langCode}: " . $e->getMessage());
                $stats['skipped']++;
            }
        }

        return $stats;
    }
    
    /**
     * Update category translation
     */
    private function updateCategoryTranslation($categoryId, $langCode, $name)
    {
        try {
            $this->categoryModel->update($categoryId, [
                "name_{$langCode}" => $name
            ]);
        } catch (Exception $e) {
            error_log("Failed to update category translation: " . $e->getMessage());
        }
    }

    /**
     * Sync single product
     * IMPORTANT: Only syncs vendor products, skips own products
     */
    private function syncSingleProduct($vendorProduct, &$stats, $languageId = 1)
    {
        $sku = $vendorProduct['sku'] ?? 'unknown';
        
        try {
            // Normalize vendor data
            $normalized = $this->normalizeVendorProduct($vendorProduct, $languageId);
        } catch (Exception $e) {
            throw new Exception("Failed to normalize product {$sku}: " . $e->getMessage());
        }
        
        // CRITICAL: Ensure product_source is set to 'vendor' for all synced products
        $normalized[':product_source'] = 'vendor';

        // Extract images - TRIEL provides both 'images' array and single 'image' field
        $productImages = [];
        if (!empty($vendorProduct['images']) && is_array($vendorProduct['images'])) {
            $productImages = $vendorProduct['images'];
        } elseif (!empty($vendorProduct['image'])) {
            $productImages = [$vendorProduct['image']];
        }

        // Check if product exists
        $existingProduct = $this->productModel->getByVendorArticleId($normalized[':vendor_article_id']);
        
        // CRITICAL: Skip if existing product is own product (protect own products from sync)
        if ($existingProduct && isset($existingProduct['product_source']) && $existingProduct['product_source'] === 'own') {
            // Log warning but don't sync - own products should not be modified by vendor sync
            error_log("Skipping sync for own product SKU: {$normalized[':sku']} (vendor_article_id: {$normalized[':vendor_article_id']})");
            return; // Exit early - don't modify own products
        }

        if ($existingProduct) {
            // Ensure slug exists for existing products (in case it was created before slug generation)
            if (empty($normalized[':slug']) && empty($existingProduct['slug']) && $languageId == 1) {
                $normalized[':slug'] = $this->generateSlug($normalized[':name']);
            }
            
            // Merge color translations if updating with new language data
            if (!empty($normalized[':specifications']) && !empty($existingProduct['specifications'])) {
                $existingSpecs = json_decode($existingProduct['specifications'], true);
                $newSpecs = json_decode($normalized[':specifications'], true);
                
                if (is_array($existingSpecs) && is_array($newSpecs)) {
                    // Merge color translations
                    if (isset($newSpecs['color_translations'])) {
                        if (!isset($existingSpecs['color_translations'])) {
                            $existingSpecs['color_translations'] = [];
                        }
                        $existingSpecs['color_translations'] = array_merge(
                            $existingSpecs['color_translations'],
                            $newSpecs['color_translations']
                        );
                    }
                    // Merge other properties
                    $existingSpecs = array_merge($existingSpecs, $newSpecs);
                    $normalized[':specifications'] = json_encode($existingSpecs);
                }
            }
            
            // Update existing product
            try {
                $this->productModel->update($existingProduct['id'], $normalized);
                $stats['updated']++;
            } catch (Exception $e) {
                throw new Exception("Failed to update product {$sku} (ID: {$existingProduct['id']}): " . $e->getMessage());
            }
            
            // Update images
            $this->syncProductImages($existingProduct['id'], $productImages);
        } else {
            // Create new product
            try {
                $productId = $this->productModel->create($normalized);
                if (!$productId) {
                    throw new Exception("Product creation returned no ID for SKU: {$sku}");
                }
                $stats['added']++;
            } catch (Exception $e) {
                throw new Exception("Failed to create product {$sku}: " . $e->getMessage());
            }
            
            // Add images
            $this->syncProductImages($productId, $productImages);
        }

        $stats['synced']++;
    }

    /**
     * Normalize vendor product data (TRIEL format)
     * 
     * @param array $vendorProduct Product data from vendor API
     * @param int $languageId Language ID used for the API call
     * @return array Normalized product data ready for database
     */
    private function normalizeVendorProduct($vendorProduct, $languageId = 1)
    {
        // TRIEL returns: name (brand), model (product), sku, price, in_stock, color, properties{}, category, cat_name
        $properties = $vendorProduct['properties'] ?? [];
        $langCode = Language::getCodeFromId($languageId);
        
        // Get or create brand (TRIEL 'name' field is the brand)
        $brandId = null;
        if (!empty($vendorProduct['name'])) {
            $brandId = $this->getOrCreateBrand($vendorProduct['name']);
        }

        // Get or create category from 'cat_name' or 'category' field
        // Handle case where cat_name might be empty but category has value
        $categoryId = null;
        $categoryName = !empty($vendorProduct['cat_name']) ? $vendorProduct['cat_name'] : ($vendorProduct['category'] ?? null);
        if (!empty($categoryName)) {
            $categoryId = $this->getOrCreateCategory([
                'id' => $vendorProduct['category'] ?? $categoryName,
                'name' => $categoryName,
                "name_{$langCode}" => $categoryName
            ]);
        }

        // Warranty - extract from properties
        $warrantyId = null;
        if (!empty($properties['warranty'])) {
            $warrantyId = $this->getOrCreateWarranty([
                'name' => $properties['warranty'],
                'months' => 12 // Default, can be parsed from warranty string
            ]);
        }

        // Base price from vendor
        $basePrice = floatval($vendorProduct['price'] ?? 0);

        // Calculate customer price with markup
        $customerPrice = $this->pricingService->calculatePrice($basePrice, $categoryId, $brandId);

        // Product name - use full_name from properties, fallback to model field
        $productName = $properties['full_name'] ?? ($vendorProduct['model'] ?? 'Unnamed Product');
        
        // Generate slug (only for English/base language)
        $slug = ($languageId == 1) ? $this->generateSlug($productName) : null;

        // Stock quantity
        $stockQuantity = intval($vendorProduct['in_stock'] ?? 0);
        
        // Extract specs from properties and model name
        $storage = $this->extractStorage($productName, $properties);
        $ram = $this->extractRAM($productName, $properties);
        $ean = $properties['ean'] ?? $vendorProduct['ean'] ?? null;

        // Build specifications JSON with color translations
        $specifications = $properties;
        if (!is_array($specifications)) {
            $specifications = [];
        }
        
        // Store color translation in specifications
        $colorValue = $vendorProduct['color'] ?? null;
        if ($colorValue) {
            if (!isset($specifications['color_translations'])) {
                $specifications['color_translations'] = [];
            }
            $specifications['color_translations'][$langCode] = $colorValue;
        }
        
        // Build data array with language-specific columns
        $data = [
            ':product_source' => 'vendor', // CRITICAL: Always set to vendor for synced products
            ':vendor_article_id' => $vendorProduct['sku'], // TRIEL uses 'sku' as unique ID
            ':sku' => $vendorProduct['sku'],
            ':ean' => $ean,
            ':name' => $productName,
            ':category_id' => $categoryId,
            ':brand_id' => $brandId,
            ':warranty_id' => $warrantyId,
            ':base_price' => $basePrice,
            ':price' => $customerPrice,
            ':currency' => 'EUR',
            ':stock_quantity' => $stockQuantity,
            ':available_quantity' => $stockQuantity,
            ':is_available' => $stockQuantity > 0 ? 1 : 0,
            ':weight' => null,
            ':dimensions' => null,
            // Store base color (English) in main color field, translations in specifications
            ':color' => ($langCode === 'en' && $colorValue) ? $colorValue : null,
            ':storage' => $storage,
            ':ram' => $ram,
            ':specifications' => !empty($specifications) ? json_encode($specifications) : null,
            ':last_synced_at' => date('Y-m-d H:i:s')
        ];
        
        // Add slug only for base language
        if ($slug) {
            $data[':slug'] = $slug;
        }
        
        // Set main description field (required by SQL)
        if (!isset($data[':description'])) {
            $data[':description'] = null;
        }
        
        // Initialize all language-specific columns to NULL
        $supportedLanguages = ['en', 'de', 'sk', 'fr', 'es', 'ru', 'it', 'tr', 'ro', 'pl'];
        foreach ($supportedLanguages as $lang) {
            if (!isset($data[":name_{$lang}"])) {
                $data[":name_{$lang}"] = null;
            }
            if (!isset($data[":description_{$lang}"])) {
                $data[":description_{$lang}"] = null;
            }
        }
        
        // Initialize new fields for own products (not used in vendor sync, but needed for schema)
        if (!isset($data[':reserved_quantity'])) {
            $data[':reserved_quantity'] = 0;
        }
        if (!isset($data[':reorder_point'])) {
            $data[':reorder_point'] = 0;
        }
        if (!isset($data[':warehouse_location'])) {
            $data[':warehouse_location'] = null;
        }
        
        // Set current language-specific name column
        $data[":name_{$langCode}"] = $productName;
        $data[":description_{$langCode}"] = null; // Vendor doesn't provide descriptions

        return $data;
    }

    /**
     * Get or create category
     */
    private function getOrCreateCategory($categoryData)
    {
        $vendorId = is_array($categoryData) ? ($categoryData['id'] ?? null) : $categoryData;
        $name = is_array($categoryData) ? ($categoryData['name'] ?? 'Uncategorized') : $categoryData;

        // Try to find existing category
        if ($vendorId) {
            $existing = $this->categoryModel->getByVendorId($vendorId);
            if ($existing) {
                // Update with new language data if provided
                if (is_array($categoryData)) {
                    $updateData = [];
                    foreach (['en', 'de', 'sk', 'fr', 'es', 'ru', 'it', 'tr', 'ro', 'pl'] as $lang) {
                        if (isset($categoryData["name_{$lang}"])) {
                            $updateData["name_{$lang}"] = $categoryData["name_{$lang}"];
                        }
                    }
                    if (!empty($updateData)) {
                        $this->categoryModel->update($existing['id'], $updateData);
                    }
                }
                return $existing['id'];
            }
        }

        // Create new category
        $slug = $this->generateSlug($name);
        $data = [
            ':vendor_id' => $vendorId,
            ':name' => $name,
            ':slug' => $slug,
            ':parent_id' => null,
            ':description' => null,
            ':image_url' => null,
            ':sort_order' => 0,
            ':is_active' => 1
        ];
        
        // Add all language fields
        foreach (['en', 'de', 'sk', 'fr', 'es', 'ru', 'it', 'tr', 'ro', 'pl'] as $lang) {
            $data[":name_{$lang}"] = is_array($categoryData) ? ($categoryData["name_{$lang}"] ?? null) : null;
        }

        return $this->categoryModel->create($data);
    }

    /**
     * Get or create brand
     */
    private function getOrCreateBrand($brandData)
    {
        $vendorId = is_array($brandData) ? ($brandData['id'] ?? null) : $brandData;
        $name = is_array($brandData) ? ($brandData['name'] ?? 'Unknown Brand') : $brandData;

        // Try to find existing brand
        if ($vendorId) {
            $existing = $this->brandModel->getByVendorId($vendorId);
            if ($existing) {
                return $existing['id'];
            }
        }

        // Create new brand
        $slug = $this->generateSlug($name);
        $data = [
            ':vendor_id' => $vendorId,
            ':name' => $name,
            ':slug' => $slug,
            ':logo_url' => is_array($brandData) ? ($brandData['logo'] ?? null) : null,
            ':description' => null,
            ':website' => null,
            ':is_active' => 1
        ];

        return $this->brandModel->create($data);
    }

    /**
     * Get or create warranty
     */
    private function getOrCreateWarranty($warrantyData)
    {
        $db = Database::getConnection();
        
        $name = is_array($warrantyData) ? ($warrantyData['name'] ?? 'Standard Warranty') : $warrantyData;
        $months = is_array($warrantyData) ? ($warrantyData['months'] ?? 12) : 12;

        // Try to find existing warranty
        $sql = "SELECT id FROM warranties WHERE name = :name";
        $stmt = $db->prepare($sql);
        $stmt->execute([':name' => $name]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing['id'];
        }

        // Create new warranty
        $sql = "INSERT INTO warranties (name, duration_months, description) VALUES (:name, :months, :description)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':months' => $months,
            ':description' => is_array($warrantyData) ? ($warrantyData['description'] ?? null) : null
        ]);

        return $db->lastInsertId();
    }

    /**
     * Sync product images
     */
    private function syncProductImages($productId, $images)
    {
        // TRIEL returns images as array OR single image string
        if (empty($images)) {
            return;
        }

        // Convert single image to array
        if (!is_array($images)) {
            $images = [$images];
        }

        // Delete existing images
        $db = Database::getConnection();
        $sql = "DELETE FROM product_images WHERE product_id = :product_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':product_id' => $productId]);

        // Add new images
        foreach ($images as $index => $imageUrl) {
            if (!empty($imageUrl)) {
                $this->productModel->addImage(
                    $productId,
                    $imageUrl,
                    null,
                    $index === 0 // First image is primary
                );
            }
        }
    }

    /**
     * Extract storage capacity from product name
     */
    private function extractStorage($productName, $properties)
    {
        // Check properties first
        if (!empty($properties['prod_storage'])) {
            return $properties['prod_storage'];
        }

        // Extract from product name: "256GB", "1TB", "512 GB", etc.
        if (preg_match('/(\d+)\s*(GB|TB)/i', $productName, $matches)) {
            return $matches[1] . strtoupper($matches[2]);
        }

        return null;
    }

    /**
     * Extract RAM from product name
     */
    private function extractRAM($productName, $properties)
    {
        // Check properties first
        if (!empty($properties['prod_memory']) || !empty($properties['ram'])) {
            return $properties['prod_memory'] ?? $properties['ram'];
        }

        // Extract from product name if it contains RAM info
        // Example: "8GB RAM", "16 GB", etc.
        if (preg_match('/(\d+)\s*GB\s*(RAM|Memory)/i', $productName, $matches)) {
            return $matches[1] . 'GB';
        }

        return null;
    }

    /**
     * Disable vendor products that are no longer available
     * IMPORTANT: Only disables VENDOR products, never touches own products
     */
    private function disableUnavailableProducts($vendorProducts)
    {
        // TRIEL uses 'sku' as the unique identifier
        $vendorIds = array_column($vendorProducts, 'sku');
        
        if (empty($vendorIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
        // CRITICAL: Only disable VENDOR products, never own products
        $sql = "UPDATE products SET is_available = 0 
                WHERE product_source = 'vendor'
                AND vendor_article_id NOT IN ($placeholders) 
                AND vendor_article_id IS NOT NULL
                AND is_available = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($vendorIds);
        
        return $stmt->rowCount();
    }

    /**
     * Generate URL-friendly slug
     */
    private function generateSlug($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) {
            return 'product-' . uniqid();
        }

        // Ensure uniqueness
        $slug = $text;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $text . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists($slug)
    {
        $sql = "SELECT COUNT(*) FROM products WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Start sync log
     */
    private function startSyncLog()
    {
        $sql = "INSERT INTO vendor_sync_log (sync_type, status, started_at) 
                VALUES ('full', 'in_progress', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $this->syncLogId = $this->db->lastInsertId();
    }

    /**
     * Complete sync log
     */
    private function completeSyncLog($status, $stats = null, $error = null)
    {
        if (!$this->syncLogId) {
            return;
        }

        $sql = "UPDATE vendor_sync_log SET 
                status = :status,
                products_synced = :synced,
                products_added = :added,
                products_updated = :updated,
                products_disabled = :disabled,
                error_message = :error,
                completed_at = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $this->syncLogId,
            ':status' => $status,
            ':synced' => $stats['synced'] ?? 0,
            ':added' => $stats['added'] ?? 0,
            ':updated' => $stats['updated'] ?? 0,
            ':disabled' => $stats['disabled'] ?? 0,
            ':error' => $error
        ]);
    }

    /**
     * Get last sync status
     */
    public function getLastSyncStatus()
    {
        $sql = "SELECT * FROM vendor_sync_log ORDER BY started_at DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
}

