<?php

/**
 * Language Utility
 * Handles language ID mapping and validation
 */
class Language
{
    /**
     * Language mapping: ID => [code, name]
     */
    private const LANGUAGES = [
        0  => ['code' => 'en', 'name' => 'Default (English)'],
        1  => ['code' => 'en', 'name' => 'English'],
        3  => ['code' => 'de', 'name' => 'German'],
        4  => ['code' => 'fr', 'name' => 'French'],
        5  => ['code' => 'es', 'name' => 'Spanish'],
        6  => ['code' => 'ru', 'name' => 'Russian'],
        7  => ['code' => 'it', 'name' => 'Italian'],
        8  => ['code' => 'tr', 'name' => 'Turkish'],
        9  => ['code' => 'ro', 'name' => 'Romanian'],
        10 => ['code' => 'sk', 'name' => 'Slovakian'],
        11 => ['code' => 'pl', 'name' => 'Polish'],
    ];

    /**
     * Default language
     */
    private const DEFAULT_LANGUAGE = 'en';
    private const DEFAULT_LANGUAGE_ID = 1;

    /**
     * Get language code from ID
     * 
     * @param int|string $languageId Language ID (0-11)
     * @return string Language code (e.g., 'en', 'de', 'fr')
     */
    public static function getCodeFromId($languageId)
    {
        $id = (int)$languageId;
        
        if (isset(self::LANGUAGES[$id])) {
            return self::LANGUAGES[$id]['code'];
        }
        
        return self::DEFAULT_LANGUAGE;
    }

    /**
     * Get language ID from code
     * 
     * @param string $languageCode Language code (e.g., 'en', 'de', 'fr')
     * @return int Language ID
     */
    public static function getIdFromCode($languageCode)
    {
        $code = strtolower(trim($languageCode));
        
        foreach (self::LANGUAGES as $id => $lang) {
            if ($lang['code'] === $code) {
                return $id;
            }
        }
        
        return self::DEFAULT_LANGUAGE_ID;
    }

    /**
     * Validate language ID
     * 
     * @param int|string $languageId Language ID to validate
     * @return bool True if valid
     */
    public static function isValidId($languageId)
    {
        $id = (int)$languageId;
        return isset(self::LANGUAGES[$id]);
    }

    /**
     * Validate language code
     * 
     * @param string $languageCode Language code to validate
     * @return bool True if valid
     */
    public static function isValidCode($languageCode)
    {
        $code = strtolower(trim($languageCode));
        
        foreach (self::LANGUAGES as $lang) {
            if ($lang['code'] === $code) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all supported languages
     * 
     * @return array All supported languages
     */
    public static function getAllLanguages()
    {
        $languages = [];
        
        foreach (self::LANGUAGES as $id => $lang) {
            $languages[] = [
                'id' => $id,
                'code' => $lang['code'],
                'name' => $lang['name']
            ];
        }
        
        return $languages;
    }

    /**
     * Get language info by ID
     * 
     * @param int $languageId Language ID
     * @return array|null Language info or null if not found
     */
    public static function getLanguageById($languageId)
    {
        $id = (int)$languageId;
        
        if (isset(self::LANGUAGES[$id])) {
            return [
                'id' => $id,
                'code' => self::LANGUAGES[$id]['code'],
                'name' => self::LANGUAGES[$id]['name']
            ];
        }
        
        return null;
    }

    /**
     * Get language info by code
     * 
     * @param string $languageCode Language code
     * @return array|null Language info or null if not found
     */
    public static function getLanguageByCode($languageCode)
    {
        $code = strtolower(trim($languageCode));
        
        foreach (self::LANGUAGES as $id => $lang) {
            if ($lang['code'] === $code) {
                return [
                    'id' => $id,
                    'code' => $lang['code'],
                    'name' => $lang['name']
                ];
            }
        }
        
        return null;
    }

    /**
     * Normalize language parameter (ID or code) to code
     * 
     * @param int|string $language Language ID or code
     * @return string Language code
     */
    public static function normalize($language)
    {
        // If it's numeric, treat as ID
        if (is_numeric($language)) {
            return self::getCodeFromId((int)$language);
        }
        
        // If it's a string, validate and return code
        $code = strtolower(trim($language));
        return self::isValidCode($code) ? $code : self::DEFAULT_LANGUAGE;
    }

    /**
     * Get default language code
     * 
     * @return string Default language code
     */
    public static function getDefaultCode()
    {
        return self::DEFAULT_LANGUAGE;
    }

    /**
     * Get default language ID
     * 
     * @return int Default language ID
     */
    public static function getDefaultId()
    {
        return self::DEFAULT_LANGUAGE_ID;
    }

    /**
     * Get all supported language codes
     * 
     * @return array Array of language codes
     */
    public static function getSupportedCodes()
    {
        $codes = [];
        foreach (self::LANGUAGES as $lang) {
            if (!in_array($lang['code'], $codes)) {
                $codes[] = $lang['code'];
            }
        }
        return $codes;
    }

    /**
     * Get column suffix for language
     * For backward compatibility with existing database structure
     * 
     * @param string $languageCode Language code
     * @return string Column suffix (e.g., '_en', '_de')
     */
    public static function getColumnSuffix($languageCode)
    {
        $code = strtolower(trim($languageCode));
        return self::isValidCode($code) ? "_{$code}" : "_en";
    }

    /**
     * Get fallback chain for a language
     * Returns array of language codes to try in order
     * 
     * @param string $languageCode Primary language code
     * @return array Array of language codes in fallback order
     */
    public static function getFallbackChain($languageCode)
    {
        $code = strtolower(trim($languageCode));
        
        // Primary language, then English as fallback
        $chain = [];
        
        if (self::isValidCode($code) && $code !== self::DEFAULT_LANGUAGE) {
            $chain[] = $code;
        }
        
        $chain[] = self::DEFAULT_LANGUAGE;
        
        return $chain;
    }
}


