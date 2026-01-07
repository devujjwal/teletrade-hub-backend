<?php

/**
 * Input Sanitization Utility
 */
class Sanitizer
{
    /**
     * Sanitize string (remove HTML/PHP tags)
     */
    public static function string($value)
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize email
     */
    public static function email($email)
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize integer
     */
    public static function integer($value)
    {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize float
     */
    public static function float($value)
    {
        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitize URL
     */
    public static function url($url)
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitize array of data
     */
    public static function array(array $data, array $rules)
    {
        $sanitized = [];

        foreach ($rules as $field => $type) {
            if (!isset($data[$field])) {
                continue;
            }

            $value = $data[$field];

            switch ($type) {
                case 'string':
                    $sanitized[$field] = self::string($value);
                    break;
                
                case 'email':
                    $sanitized[$field] = self::email($value);
                    break;
                
                case 'integer':
                case 'int':
                    $sanitized[$field] = self::integer($value);
                    break;
                
                case 'float':
                case 'double':
                case 'decimal':
                    $sanitized[$field] = self::float($value);
                    break;
                
                case 'url':
                    $sanitized[$field] = self::url($value);
                    break;
                
                default:
                    $sanitized[$field] = self::string($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize SQL LIKE parameter
     */
    public static function likeSql($value)
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}

