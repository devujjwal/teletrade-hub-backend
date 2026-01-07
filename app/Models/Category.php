<?php

require_once __DIR__ . '/../Utils/Language.php';

/**
 * Category Model
 */
class Category
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get all active categories
     */
    public function getAll($lang = 'en')
    {
        $sql = "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
        $stmt = $this->db->query($sql);
        $categories = $stmt->fetchAll();
        return $this->applyLanguage($categories, $lang);
    }

    /**
     * Get category by ID
     */
    public function getById($id, $lang = 'en')
    {
        $sql = "SELECT * FROM categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            return null;
        }

        return $this->applyLanguage([$category], $lang)[0];
    }

    /**
     * Get category by slug (case-insensitive)
     */
    public function getBySlug($slug, $lang = 'en')
    {
        $sql = "SELECT * FROM categories WHERE LOWER(slug) = LOWER(:slug) AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $category = $stmt->fetch();
        
        if (!$category) {
            return null;
        }

        return $this->applyLanguage([$category], $lang)[0];
    }

    /**
     * Get category by vendor ID
     */
    public function getByVendorId($vendorId)
    {
        $sql = "SELECT * FROM categories WHERE vendor_id = :vendor_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':vendor_id' => $vendorId]);
        return $stmt->fetch();
    }

    /**
     * Create category
     */
    public function create($data)
    {
        $sql = "INSERT INTO categories (
            vendor_id, name, name_en, name_de, name_sk, name_fr, name_es, name_ru, 
            name_it, name_tr, name_ro, name_pl, slug, parent_id,
            description, image_url, sort_order, is_active
        ) VALUES (
            :vendor_id, :name, :name_en, :name_de, :name_sk, :name_fr, :name_es, :name_ru,
            :name_it, :name_tr, :name_ro, :name_pl, :slug, :parent_id,
            :description, :image_url, :sort_order, :is_active
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Update category
     */
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get categories with product count
     */
    public function getAllWithProductCount($lang = 'en')
    {
        $sql = "SELECT c.*, COUNT(p.id) as product_count 
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.is_available = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.name ASC";
        
        $stmt = $this->db->query($sql);
        $categories = $stmt->fetchAll();
        return $this->applyLanguage($categories, $lang);
    }

    /**
     * Get hierarchical categories
     */
    public function getHierarchical($lang = 'en')
    {
        $categories = $this->getAll($lang);
        return $this->buildTree($categories);
    }

    /**
     * Build category tree
     */
    private function buildTree($categories, $parentId = null)
    {
        $tree = [];
        
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $category['children'] = $this->buildTree($categories, $category['id']);
                $tree[] = $category;
            }
        }
        
        return $tree;
    }

    /**
     * Apply language-specific fields with fallback support
     */
    private function applyLanguage($categories, $lang)
    {
        // Normalize language (supports both ID and code)
        $langCode = Language::normalize($lang);
        
        // Get fallback chain (e.g., ['fr', 'en'])
        $fallbackChain = Language::getFallbackChain($langCode);
        
        foreach ($categories as &$category) {
            // Try each language in the fallback chain
            $nameFound = false;
            foreach ($fallbackChain as $fallbackLang) {
                $nameLang = "name_{$fallbackLang}";
                if (!empty($category[$nameLang])) {
                    $category['name'] = $category[$nameLang];
                    $nameFound = true;
                    break;
                }
            }
            
            // Add language metadata
            $category['language'] = $langCode;
        }
        
        return $categories;
    }
}

