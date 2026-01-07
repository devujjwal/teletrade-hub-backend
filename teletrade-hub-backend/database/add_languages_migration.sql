-- TeleTrade Hub Multi-Language Support Migration
-- Adds support for: French, Spanish, Russian, Italian, Turkish, Romanian, Polish
-- Existing languages (English, German, Slovakian) are already in place

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- ADD NEW LANGUAGE COLUMNS TO PRODUCTS TABLE
-- =====================================================

-- Add French columns
ALTER TABLE `products`
ADD COLUMN `name_fr` VARCHAR(500) NULL AFTER `name_sk`,
ADD COLUMN `description_fr` TEXT NULL AFTER `description_sk`;

-- Add Spanish columns
ALTER TABLE `products`
ADD COLUMN `name_es` VARCHAR(500) NULL AFTER `name_fr`,
ADD COLUMN `description_es` TEXT NULL AFTER `description_fr`;

-- Add Russian columns
ALTER TABLE `products`
ADD COLUMN `name_ru` VARCHAR(500) NULL AFTER `name_es`,
ADD COLUMN `description_ru` TEXT NULL AFTER `description_es`;

-- Add Italian columns
ALTER TABLE `products`
ADD COLUMN `name_it` VARCHAR(500) NULL AFTER `name_ru`,
ADD COLUMN `description_it` TEXT NULL AFTER `description_ru`;

-- Add Turkish columns
ALTER TABLE `products`
ADD COLUMN `name_tr` VARCHAR(500) NULL AFTER `name_it`,
ADD COLUMN `description_tr` TEXT NULL AFTER `description_it`;

-- Add Romanian columns
ALTER TABLE `products`
ADD COLUMN `name_ro` VARCHAR(500) NULL AFTER `name_tr`,
ADD COLUMN `description_ro` TEXT NULL AFTER `description_tr`;

-- Add Polish columns
ALTER TABLE `products`
ADD COLUMN `name_pl` VARCHAR(500) NULL AFTER `name_ro`,
ADD COLUMN `description_pl` TEXT NULL AFTER `description_ro`;

-- =====================================================
-- ADD NEW LANGUAGE COLUMNS TO CATEGORIES TABLE
-- =====================================================

-- Add French column
ALTER TABLE `categories`
ADD COLUMN `name_fr` VARCHAR(255) NULL AFTER `name_sk`;

-- Add Spanish column
ALTER TABLE `categories`
ADD COLUMN `name_es` VARCHAR(255) NULL AFTER `name_fr`;

-- Add Russian column
ALTER TABLE `categories`
ADD COLUMN `name_ru` VARCHAR(255) NULL AFTER `name_es`;

-- Add Italian column
ALTER TABLE `categories`
ADD COLUMN `name_it` VARCHAR(255) NULL AFTER `name_ru`;

-- Add Turkish column
ALTER TABLE `categories`
ADD COLUMN `name_tr` VARCHAR(255) NULL AFTER `name_it`;

-- Add Romanian column
ALTER TABLE `categories`
ADD COLUMN `name_ro` VARCHAR(255) NULL AFTER `name_tr`;

-- Add Polish column
ALTER TABLE `categories`
ADD COLUMN `name_pl` VARCHAR(255) NULL AFTER `name_ro`;

-- =====================================================
-- ADD LANGUAGE COLUMNS TO BRANDS TABLE
-- =====================================================

-- Brands table currently doesn't have language support
-- Adding description translations for all languages

ALTER TABLE `brands`
ADD COLUMN `description_en` TEXT NULL AFTER `description`,
ADD COLUMN `description_de` TEXT NULL AFTER `description_en`,
ADD COLUMN `description_sk` TEXT NULL AFTER `description_de`,
ADD COLUMN `description_fr` TEXT NULL AFTER `description_sk`,
ADD COLUMN `description_es` TEXT NULL AFTER `description_fr`,
ADD COLUMN `description_ru` TEXT NULL AFTER `description_es`,
ADD COLUMN `description_it` TEXT NULL AFTER `description_ru`,
ADD COLUMN `description_tr` TEXT NULL AFTER `description_it`,
ADD COLUMN `description_ro` TEXT NULL AFTER `description_tr`,
ADD COLUMN `description_pl` TEXT NULL AFTER `description_ro`;

-- =====================================================
-- UPDATE FULLTEXT INDEXES TO INCLUDE NEW LANGUAGES
-- =====================================================

-- Drop existing fulltext indexes
ALTER TABLE `products` DROP INDEX IF EXISTS `search_name`;
ALTER TABLE `products` DROP INDEX IF EXISTS `search_description`;

-- Recreate fulltext indexes with all languages
ALTER TABLE `products` 
ADD FULLTEXT KEY `search_name` (
    `name`, `name_en`, `name_de`, `name_sk`, 
    `name_fr`, `name_es`, `name_ru`, `name_it`, 
    `name_tr`, `name_ro`, `name_pl`
);

ALTER TABLE `products` 
ADD FULLTEXT KEY `search_description` (
    `description`, `description_en`, `description_de`, `description_sk`,
    `description_fr`, `description_es`, `description_ru`, `description_it`,
    `description_tr`, `description_ro`, `description_pl`
);

-- =====================================================
-- UPDATE PRODUCT LIST VIEW
-- =====================================================

-- Drop and recreate the view with new language columns
DROP VIEW IF EXISTS `product_list_view`;

CREATE VIEW `product_list_view` AS
SELECT 
  p.id,
  p.vendor_article_id,
  p.sku,
  p.ean,
  p.name,
  p.name_en,
  p.name_de,
  p.name_sk,
  p.name_fr,
  p.name_es,
  p.name_ru,
  p.name_it,
  p.name_tr,
  p.name_ro,
  p.name_pl,
  p.description,
  p.description_en,
  p.description_de,
  p.description_sk,
  p.description_fr,
  p.description_es,
  p.description_ru,
  p.description_it,
  p.description_tr,
  p.description_ro,
  p.description_pl,
  p.slug,
  p.base_price,
  p.price,
  p.currency,
  p.stock_quantity,
  p.available_quantity,
  p.is_available,
  p.is_featured,
  p.color,
  p.storage,
  p.ram,
  c.id AS category_id,
  c.name AS category_name,
  c.name_en AS category_name_en,
  c.name_de AS category_name_de,
  c.name_sk AS category_name_sk,
  c.name_fr AS category_name_fr,
  c.name_es AS category_name_es,
  c.name_ru AS category_name_ru,
  c.name_it AS category_name_it,
  c.name_tr AS category_name_tr,
  c.name_ro AS category_name_ro,
  c.name_pl AS category_name_pl,
  c.slug AS category_slug,
  b.id AS brand_id,
  b.name AS brand_name,
  b.slug AS brand_slug,
  w.id AS warranty_id,
  w.name AS warranty_name,
  w.duration_months AS warranty_months,
  (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
  p.created_at,
  p.updated_at
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN brands b ON p.brand_id = b.id
LEFT JOIN warranties w ON p.warranty_id = w.id;

-- =====================================================
-- CREATE LANGUAGES REFERENCE TABLE (OPTIONAL)
-- =====================================================

-- This table can be used for language management in the future
CREATE TABLE IF NOT EXISTS `languages` (
  `id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(2) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert supported languages
INSERT INTO `languages` (`id`, `code`, `name`, `is_active`, `sort_order`) VALUES
(0, 'en', 'Default (English)', 1, 0),
(1, 'en', 'English', 1, 1),
(3, 'de', 'German', 1, 3),
(4, 'fr', 'French', 1, 4),
(5, 'es', 'Spanish', 1, 5),
(6, 'ru', 'Russian', 1, 6),
(7, 'it', 'Italian', 1, 7),
(8, 'tr', 'Turkish', 1, 8),
(9, 'ro', 'Romanian', 1, 9),
(10, 'sk', 'Slovakian', 1, 10),
(11, 'pl', 'Polish', 1, 11)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);


