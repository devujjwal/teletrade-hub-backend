<?php

/**
 * Input Validation Utility
 */
class Validator
{
    /**
     * Validate email address
     */
    public static function email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate required field
     */
    public static function required($value)
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return !empty($value);
    }

    /**
     * Validate minimum length
     */
    public static function minLength($value, $min)
    {
        return strlen($value) >= $min;
    }

    /**
     * Validate maximum length
     */
    public static function maxLength($value, $max)
    {
        return strlen($value) <= $max;
    }

    /**
     * Validate numeric value
     */
    public static function numeric($value)
    {
        return is_numeric($value);
    }

    /**
     * Validate positive number
     */
    public static function positive($value)
    {
        return is_numeric($value) && $value > 0;
    }

    /**
     * Validate integer
     */
    public static function integer($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate URL
     */
    public static function url($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate phone number (basic)
     */
    public static function phone($phone)
    {
        return preg_match('/^[0-9+\-\s()]+$/', $phone);
    }

    /**
     * Validate against allowed values
     */
    public static function in($value, array $allowed)
    {
        return in_array($value, $allowed, true);
    }
    
    /**
     * Validate password strength
     * SECURITY: Enforce strong password policy
     */
    public static function strongPassword($password)
    {
        // Minimum 8 characters
        if (strlen($password) < 8) {
            return false;
        }
        
        // At least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // At least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // At least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // At least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate data against rules
     * Returns array of errors or empty array if valid
     */
    public static function validate(array $data, array $rules)
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $fieldRules = explode('|', $ruleSet);

            foreach ($fieldRules as $rule) {
                $params = [];
                
                // Parse rule with parameters (e.g., "min:5")
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramStr) = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                switch ($rule) {
                    case 'required':
                        if (!self::required($value)) {
                            $errors[$field][] = "The $field field is required.";
                        }
                        break;
                    
                    case 'email':
                        if ($value && !self::email($value)) {
                            $errors[$field][] = "The $field must be a valid email address.";
                        }
                        break;
                    
                    case 'min':
                        if ($value && !self::minLength($value, $params[0])) {
                            $errors[$field][] = "The $field must be at least {$params[0]} characters.";
                        }
                        break;
                    
                    case 'max':
                        if ($value && !self::maxLength($value, $params[0])) {
                            $errors[$field][] = "The $field must not exceed {$params[0]} characters.";
                        }
                        break;
                    
                    case 'numeric':
                        if ($value && !self::numeric($value)) {
                            $errors[$field][] = "The $field must be a number.";
                        }
                        break;
                    
                    case 'positive':
                        if ($value && !self::positive($value)) {
                            $errors[$field][] = "The $field must be a positive number.";
                        }
                        break;
                    
                    case 'integer':
                        if ($value && !self::integer($value)) {
                            $errors[$field][] = "The $field must be an integer.";
                        }
                        break;
                    
                    case 'phone':
                        if ($value && !self::phone($value)) {
                            $errors[$field][] = "The $field must be a valid phone number.";
                        }
                        break;
                    
                    case 'in':
                        if ($value && !self::in($value, $params)) {
                            $errors[$field][] = "The $field must be one of: " . implode(', ', $params);
                        }
                        break;
                    
                    case 'strong_password':
                        if ($value && !self::strongPassword($value)) {
                            $errors[$field][] = "The $field must contain at least 8 characters with uppercase, lowercase, number, and special character.";
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}

