<?php

if (!class_exists('Database')) {
    require_once __DIR__ . '/../Config/database.php';
}

/**
 * Settings Model
 * Handles application settings stored as key-value pairs
 */
class Settings
{
    private $db;

    public function __construct()
    {
        try {
            $this->db = Database::getConnection();
        } catch (Exception $e) {
            error_log("Settings Model: Database connection failed - " . $e->getMessage());
            throw new Exception("Failed to initialize Settings model: " . $e->getMessage());
        }
    }

    /**
     * Get all settings as associative array
     */
    public function getAll()
    {
        $sql = "SELECT `key`, `value`, `type` FROM settings";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($rows as $row) {
            $value = $this->castValue($row['value'], $row['type']);
            $settings[$row['key']] = $value;
        }
        
        return $settings;
    }

    /**
     * Get a single setting value
     */
    public function get($key, $default = null)
    {
        $sql = "SELECT `value`, `type` FROM settings WHERE `key` = :key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return $default;
        }
        
        return $this->castValue($row['value'], $row['type']);
    }

    /**
     * Set a setting value
     */
    public function set($key, $value, $type = 'string', $description = null)
    {
        $sql = "INSERT INTO settings (`key`, `value`, `type`, `description`) 
                VALUES (:key, :value, :type, :description)
                ON DUPLICATE KEY UPDATE 
                    `value` = VALUES(`value`),
                    `type` = VALUES(`type`),
                    `description` = COALESCE(VALUES(`description`), `description`),
                    `updated_at` = CURRENT_TIMESTAMP";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':key' => $key,
            ':value' => $this->stringifyValue($value, $type),
            ':type' => $type,
            ':description' => $description
        ]);
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple($settings)
    {
        $this->db->beginTransaction();
        
        try {
            foreach ($settings as $key => $value) {
                // Determine type from value
                $type = $this->detectType($value);
                $this->set($key, $value, $type);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Cast value based on type
     */
    private function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return intval($value);
            case 'boolean':
                return $value === 'true' || $value === '1' || $value === 1 || $value === true;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Convert value to string for storage
     */
    private function stringifyValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }

    /**
     * Detect type from value
     */
    private function detectType($value)
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        return 'string';
    }
}

