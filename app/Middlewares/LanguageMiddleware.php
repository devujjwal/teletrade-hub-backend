<?php

require_once __DIR__ . '/../Utils/Language.php';

/**
 * Language Middleware
 * Validates and normalizes language parameters in requests
 */
class LanguageMiddleware
{
    /**
     * Process request and validate language parameter
     * Sets a normalized language code in the request
     * 
     * Accepts language as:
     * - 'lang' query parameter (code or ID)
     * - 'language' query parameter (code or ID)
     * - 'Accept-Language' header
     * 
     * @return string Normalized language code
     */
    public static function handle()
    {
        $language = null;
        
        // 1. Check query parameters (highest priority)
        if (isset($_GET['lang'])) {
            $language = $_GET['lang'];
        } elseif (isset($_GET['language'])) {
            $language = $_GET['language'];
        } elseif (isset($_GET['lang_id'])) {
            $language = $_GET['lang_id'];
        }
        
        // 2. Check POST data
        if (!$language && isset($_POST['lang'])) {
            $language = $_POST['lang'];
        } elseif (!$language && isset($_POST['language'])) {
            $language = $_POST['language'];
        }
        
        // 3. Check Accept-Language header
        if (!$language && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $language = self::parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
        
        // 4. Default to English if nothing provided
        if (!$language) {
            $language = Language::getDefaultCode();
        }
        
        // Normalize the language (converts ID to code, validates code)
        $normalizedLang = Language::normalize($language);
        
        // Store in global for easy access
        if (!isset($GLOBALS['app'])) {
            $GLOBALS['app'] = [];
        }
        $GLOBALS['app']['language'] = $normalizedLang;
        
        return $normalizedLang;
    }
    
    /**
     * Parse Accept-Language header
     * Returns the best matching language code
     * 
     * @param string $acceptLanguage Accept-Language header value
     * @return string|null Language code or null
     */
    private static function parseAcceptLanguage($acceptLanguage)
    {
        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,de;q=0.8")
        $languages = [];
        
        // Split by comma
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // Extract language code and quality
            if (preg_match('/^([a-z]{2})(?:-[A-Z]{2})?(;q=([0-9.]+))?$/i', $part, $matches)) {
                $lang = strtolower($matches[1]);
                $quality = isset($matches[3]) ? (float)$matches[3] : 1.0;
                
                $languages[$lang] = $quality;
            }
        }
        
        // Sort by quality (highest first)
        arsort($languages);
        
        // Find first supported language
        $supportedCodes = Language::getSupportedCodes();
        
        foreach ($languages as $lang => $quality) {
            if (in_array($lang, $supportedCodes)) {
                return $lang;
            }
        }
        
        return null;
    }
    
    /**
     * Get current language from global state
     * 
     * @return string Current language code
     */
    public static function getCurrentLanguage()
    {
        return $GLOBALS['app']['language'] ?? Language::getDefaultCode();
    }
    
    /**
     * Validate language parameter
     * Returns error response if invalid, null if valid
     * 
     * @param mixed $language Language to validate (code or ID)
     * @return array|null Error response or null if valid
     */
    public static function validate($language)
    {
        // Check if it's numeric (language ID)
        if (is_numeric($language)) {
            if (!Language::isValidId((int)$language)) {
                return [
                    'success' => false,
                    'message' => 'Invalid language ID',
                    'supported_languages' => Language::getAllLanguages()
                ];
            }
        } else {
            // Check if it's a language code
            if (!Language::isValidCode($language)) {
                return [
                    'success' => false,
                    'message' => 'Invalid language code',
                    'supported_languages' => Language::getAllLanguages()
                ];
            }
        }
        
        return null; // Valid
    }
    
    /**
     * Get language info for API response
     * 
     * @param string|null $langCode Language code (uses current if null)
     * @return array Language info
     */
    public static function getLanguageInfo($langCode = null)
    {
        if (!$langCode) {
            $langCode = self::getCurrentLanguage();
        }
        
        return Language::getLanguageByCode($langCode);
    }
}


