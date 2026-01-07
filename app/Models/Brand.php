<?php

require_once __DIR__ . '/../Utils/Language.php';

/**
 * Brand Model
 */
class Brand
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get all active brands
     */
    public function getAll($lang = 'en')
    {
        $sql = "SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        $brands = $stmt->fetchAll();
        return $this->applyLanguage($brands, $lang);
    }

    /**
     * Get brand by ID
     */
    public function getById($id, $lang = 'en')
    {
        $sql = "SELECT * FROM brands WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $brand = $stmt->fetch();
        
        if (!$brand) {
            return null;
        }
        
        return $this->applyLanguage([$brand], $lang)[0];
    }

    /**
     * Get brand by slug (case-insensitive)
     */
    public function getBySlug($slug, $lang = 'en')
    {
        $sql = "SELECT * FROM brands WHERE LOWER(slug) = LOWER(:slug) AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $brand = $stmt->fetch();
        
        if (!$brand) {
            return null;
        }
        
        return $this->applyLanguage([$brand], $lang)[0];
    }

    /**
     * Get brand by vendor ID
     */
    public function getByVendorId($vendorId)
    {
        $sql = "SELECT * FROM brands WHERE vendor_id = :vendor_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':vendor_id' => $vendorId]);
        return $stmt->fetch();
    }

    /**
     * Create brand
     */
    public function create($data)
    {
        $sql = "INSERT INTO brands (vendor_id, name, slug, logo_url, description, website, is_active)
                VALUES (:vendor_id, :name, :slug, :logo_url, :description, :website, :is_active)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Update brand
     */
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $sql = "UPDATE brands SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get brands with product count
     */
    public function getAllWithProductCount($lang = 'en')
    {
        $sql = "SELECT b.*, COUNT(p.id) as product_count 
                FROM brands b
                LEFT JOIN products p ON b.id = p.brand_id AND p.is_available = 1
                WHERE b.is_active = 1
                GROUP BY b.id
                ORDER BY b.name ASC";
        
        $stmt = $this->db->query($sql);
        $brands = $stmt->fetchAll();
        return $this->applyLanguage($brands, $lang);
    }
    
    /**
     * Apply language-specific fields with fallback support
     */
    private function applyLanguage($brands, $lang)
    {
        // Normalize language (supports both ID and code)
        $langCode = Language::normalize($lang);
        
        // Get fallback chain (e.g., ['fr', 'en'])
        $fallbackChain = Language::getFallbackChain($langCode);
        
        foreach ($brands as &$brand) {
            // Try each language in the fallback chain for description
            foreach ($fallbackChain as $fallbackLang) {
                $descLang = "description_{$fallbackLang}";
                if (!empty($brand[$descLang])) {
                    $brand['description'] = $brand[$descLang];
                    break;
                }
            }
            
            // Add language metadata
            $brand['language'] = $langCode;
        }
        
        return $brands;
    }
}

