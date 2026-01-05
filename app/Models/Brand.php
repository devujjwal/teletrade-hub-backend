<?php

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
    public function getAll()
    {
        $sql = "SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get brand by ID
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM brands WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get brand by slug
     */
    public function getBySlug($slug)
    {
        $sql = "SELECT * FROM brands WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
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
    public function getAllWithProductCount()
    {
        $sql = "SELECT b.*, COUNT(p.id) as product_count 
                FROM brands b
                LEFT JOIN products p ON b.id = p.brand_id AND p.is_available = 1
                WHERE b.is_active = 1
                GROUP BY b.id
                ORDER BY b.name ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}

