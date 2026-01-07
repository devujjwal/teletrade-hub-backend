<?php

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/VendorApiService.php';
require_once __DIR__ . '/PricingService.php';

/**
 * Product Sync Service
 * Synchronizes products from vendor to local database
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
     * Perform full product sync
     */
    public function syncProducts($lang = 'en')
    {
        $this->startSyncLog();

        try {
            // Get stock data from vendor
            $stockData = $this->vendorApi->getStock($lang);

            if (empty($stockData) || !isset($stockData['stock'])) {
                throw new Exception('Invalid stock data received from vendor');
            }

            // TRIEL returns data in 'stock' array, not 'products'
            $products = $stockData['stock'];
            $stats = [
                'synced' => 0,
                'added' => 0,
                'updated' => 0,
                'disabled' => 0
            ];

            // Process each product
            foreach ($products as $vendorProduct) {
                try {
                    $this->syncSingleProduct($vendorProduct, $stats);
                } catch (Exception $e) {
                    error_log("Failed to sync product {$vendorProduct['id']}: " . $e->getMessage());
                    continue;
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
     * Sync single product
     */
    private function syncSingleProduct($vendorProduct, &$stats)
    {
        // Normalize vendor data
        $normalized = $this->normalizeVendorProduct($vendorProduct);

        // Extract images - TRIEL provides both 'images' array and single 'image' field
        $productImages = [];
        if (!empty($vendorProduct['images']) && is_array($vendorProduct['images'])) {
            $productImages = $vendorProduct['images'];
        } elseif (!empty($vendorProduct['image'])) {
            $productImages = [$vendorProduct['image']];
        }

        // Check if product exists
        $existingProduct = $this->productModel->getByVendorArticleId($normalized[':vendor_article_id']);

        if ($existingProduct) {
            // Update existing product
            $this->productModel->update($existingProduct['id'], $normalized);
            $stats['updated']++;
            
            // Update images
            $this->syncProductImages($existingProduct['id'], $productImages);
        } else {
            // Create new product
            $productId = $this->productModel->create($normalized);
            $stats['added']++;
            
            // Add images
            $this->syncProductImages($productId, $productImages);
        }

        $stats['synced']++;
    }

    /**
     * Normalize vendor product data (TRIEL format)
     */
    private function normalizeVendorProduct($vendorProduct)
    {
        // TRIEL returns: name (brand), model (product), sku, price, in_stock, color, properties{}, category, cat_name
        $properties = $vendorProduct['properties'] ?? [];
        
        // Get or create brand (TRIEL 'name' field is the brand)
        $brandId = null;
        if (!empty($vendorProduct['name'])) {
            $brandId = $this->getOrCreateBrand($vendorProduct['name']);
        }

        // Get or create category from 'cat_name' or 'category' field
        $categoryId = null;
        $categoryName = $vendorProduct['cat_name'] ?? $vendorProduct['category'] ?? null;
        if (!empty($categoryName)) {
            $categoryId = $this->getOrCreateCategory([
                'id' => $vendorProduct['category'] ?? $categoryName,
                'name' => $categoryName,
                'name_en' => $categoryName
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
        
        // Generate slug
        $slug = $this->generateSlug($productName);

        // Stock quantity
        $stockQuantity = intval($vendorProduct['in_stock'] ?? 0);
        
        // Extract specs from properties and model name
        $storage = $this->extractStorage($productName, $properties);
        $ram = $this->extractRAM($productName, $properties);
        $ean = $properties['ean'] ?? $vendorProduct['ean'] ?? null;

        return [
            ':vendor_article_id' => $vendorProduct['sku'], // TRIEL uses 'sku' as unique ID
            ':sku' => $vendorProduct['sku'],
            ':ean' => $ean,
            ':name' => $productName,
            ':name_de' => null,
            ':name_en' => $productName,
            ':name_sk' => null,
            ':description' => null,
            ':description_de' => null,
            ':description_en' => null,
            ':description_sk' => null,
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
            ':color' => $vendorProduct['color'] ?? null,
            ':storage' => $storage,
            ':ram' => $ram,
            ':specifications' => !empty($properties) ? json_encode($properties) : null,
            ':slug' => $slug,
            ':last_synced_at' => date('Y-m-d H:i:s')
        ];
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
                return $existing['id'];
            }
        }

        // Create new category
        $slug = $this->generateSlug($name);
        $data = [
            ':vendor_id' => $vendorId,
            ':name' => $name,
            ':name_de' => is_array($categoryData) ? ($categoryData['name_de'] ?? null) : null,
            ':name_en' => is_array($categoryData) ? ($categoryData['name_en'] ?? null) : null,
            ':name_sk' => is_array($categoryData) ? ($categoryData['name_sk'] ?? null) : null,
            ':slug' => $slug,
            ':parent_id' => null,
            ':description' => null,
            ':image_url' => null,
            ':sort_order' => 0,
            ':is_active' => 1
        ];

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
     * Disable products that are no longer available
     */
    private function disableUnavailableProducts($vendorProducts)
    {
        // TRIEL uses 'sku' as the unique identifier
        $vendorIds = array_column($vendorProducts, 'sku');
        
        if (empty($vendorIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
        $sql = "UPDATE products SET is_available = 0 
                WHERE vendor_article_id NOT IN ($placeholders) 
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

