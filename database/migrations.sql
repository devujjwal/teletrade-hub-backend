-- TeleTrade Hub Database Schema
-- MySQL Database Schema for Production
-- Owner: Telecommunication Trading e.K.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` VARCHAR(100) NULL,
  `name` VARCHAR(255) NOT NULL,
  `name_de` VARCHAR(255) NULL,
  `name_en` VARCHAR(255) NULL,
  `name_sk` VARCHAR(255) NULL,
  `slug` VARCHAR(255) NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `description` TEXT NULL,
  `image_url` VARCHAR(500) NULL,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  KEY `is_active` (`is_active`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brands
CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` VARCHAR(100) NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `logo_url` VARCHAR(500) NULL,
  `description` TEXT NULL,
  `website` VARCHAR(500) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranties
CREATE TABLE IF NOT EXISTS `warranties` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `duration_months` INT UNSIGNED NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_article_id` VARCHAR(100) NOT NULL,
  `sku` VARCHAR(100) NOT NULL,
  `ean` VARCHAR(50) NULL,
  `name` VARCHAR(500) NOT NULL,
  `name_de` VARCHAR(500) NULL,
  `name_en` VARCHAR(500) NULL,
  `name_sk` VARCHAR(500) NULL,
  `description` TEXT NULL,
  `description_de` TEXT NULL,
  `description_en` TEXT NULL,
  `description_sk` TEXT NULL,
  `category_id` INT UNSIGNED NULL,
  `brand_id` INT UNSIGNED NULL,
  `warranty_id` INT UNSIGNED NULL,
  `base_price` DECIMAL(10, 2) NOT NULL COMMENT 'Vendor price without markup',
  `price` DECIMAL(10, 2) NOT NULL COMMENT 'Customer price with markup',
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `stock_quantity` INT UNSIGNED DEFAULT 0,
  `available_quantity` INT UNSIGNED DEFAULT 0 COMMENT 'Stock available for sale',
  `reserved_quantity` INT UNSIGNED DEFAULT 0,
  `is_available` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `weight` DECIMAL(8, 2) NULL COMMENT 'Weight in kg',
  `dimensions` VARCHAR(100) NULL COMMENT 'L x W x H in cm',
  `color` VARCHAR(100) NULL,
  `storage` VARCHAR(50) NULL COMMENT 'Storage capacity (e.g., 256GB)',
  `ram` VARCHAR(50) NULL COMMENT 'RAM (e.g., 8GB)',
  `specifications` JSON NULL COMMENT 'Additional specifications',
  `meta_title` VARCHAR(255) NULL,
  `meta_description` TEXT NULL,
  `slug` VARCHAR(500) NOT NULL,
  `last_synced_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_article_id` (`vendor_article_id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `slug` (`slug`),
  KEY `ean` (`ean`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  KEY `warranty_id` (`warranty_id`),
  KEY `is_available` (`is_available`),
  KEY `is_featured` (`is_featured`),
  KEY `price` (`price`),
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`warranty_id`) REFERENCES `warranties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Images
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `alt_text` VARCHAR(255) NULL,
  `sort_order` INT DEFAULT 0,
  `is_primary` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `is_primary` (`is_primary`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pricing Rules
CREATE TABLE IF NOT EXISTS `pricing_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_type` ENUM('global', 'category', 'brand', 'product') NOT NULL,
  `entity_id` INT UNSIGNED NULL COMMENT 'Category, Brand, or Product ID',
  `markup_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
  `markup_value` DECIMAL(10, 2) NOT NULL,
  `priority` INT DEFAULT 0 COMMENT 'Higher priority rules override lower',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rule_type` (`rule_type`, `entity_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default global markup
INSERT INTO `pricing_rules` (`rule_type`, `markup_type`, `markup_value`, `priority`, `is_active`) 
VALUES ('global', 'percentage', 15.00, 0, 1)
ON DUPLICATE KEY UPDATE `rule_type` = `rule_type`;

-- =====================================================
-- USER & AUTH TABLES
-- =====================================================

-- Users (Customers)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `email_verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(200) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'manager') DEFAULT 'admin',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default admin user (password: Admin@123456)
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`)
VALUES ('admin', 'admin@teletrade-hub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', 1)
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Admin Sessions
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `admin_user_id` (`admin_user_id`),
  KEY `expires_at` (`expires_at`),
  FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ORDER TABLES
-- =====================================================

-- Addresses
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `company` VARCHAR(200) NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) NULL,
  `postal_code` VARCHAR(20) NOT NULL,
  `country` VARCHAR(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2',
  `phone` VARCHAR(50) NOT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `guest_email` VARCHAR(255) NULL,
  `status` ENUM('pending', 'payment_pending', 'paid', 'processing', 'reserved', 'vendor_ordered', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
  `payment_status` ENUM('unpaid', 'pending', 'paid', 'failed', 'refunded') DEFAULT 'unpaid',
  `payment_method` VARCHAR(50) NULL,
  `payment_transaction_id` VARCHAR(255) NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `tax` DECIMAL(10, 2) DEFAULT 0.00,
  `shipping_cost` DECIMAL(10, 2) DEFAULT 0.00,
  `total` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `billing_address_id` INT UNSIGNED NULL,
  `shipping_address_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `admin_notes` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `paid_at` TIMESTAMP NULL,
  `vendor_order_created_at` TIMESTAMP NULL,
  `vendor_order_id` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `payment_status` (`payment_status`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`billing_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(500) NOT NULL,
  `product_sku` VARCHAR(100) NOT NULL,
  `vendor_article_id` VARCHAR(100) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `base_price` DECIMAL(10, 2) NOT NULL COMMENT 'Vendor price',
  `price` DECIMAL(10, 2) NOT NULL COMMENT 'Customer price',
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VENDOR INTEGRATION TABLES
-- =====================================================

-- Reservations
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `vendor_article_id` VARCHAR(100) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `vendor_reservation_id` VARCHAR(100) NULL,
  `status` ENUM('pending', 'reserved', 'unreserved', 'failed', 'ordered') DEFAULT 'pending',
  `reserved_at` TIMESTAMP NULL,
  `unreserved_at` TIMESTAMP NULL,
  `ordered_at` TIMESTAMP NULL,
  `vendor_response` JSON NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `reserved_at` (`reserved_at`),
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor Sync Log
CREATE TABLE IF NOT EXISTS `vendor_sync_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_type` ENUM('full', 'incremental') DEFAULT 'full',
  `status` ENUM('started', 'in_progress', 'completed', 'failed') DEFAULT 'started',
  `products_synced` INT UNSIGNED DEFAULT 0,
  `products_added` INT UNSIGNED DEFAULT 0,
  `products_updated` INT UNSIGNED DEFAULT 0,
  `products_disabled` INT UNSIGNED DEFAULT 0,
  `error_message` TEXT NULL,
  `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL,
  `duration_seconds` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor API Logs
CREATE TABLE IF NOT EXISTS `vendor_api_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `endpoint` VARCHAR(255) NOT NULL,
  `method` VARCHAR(10) NOT NULL,
  `request_payload` JSON NULL,
  `response_payload` JSON NULL,
  `status_code` INT NULL,
  `duration_ms` INT UNSIGNED NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `endpoint` (`endpoint`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SYSTEM TABLES
-- =====================================================

-- Settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  `type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
  `description` VARCHAR(500) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
('site_name', 'TeleTrade Hub', 'string', 'Site name'),
('site_email', 'info@teletrade-hub.com', 'string', 'Contact email'),
('currency', 'EUR', 'string', 'Default currency'),
('tax_rate', '19.00', 'string', 'VAT rate percentage'),
('shipping_cost', '9.99', 'string', 'Default shipping cost'),
('free_shipping_threshold', '100.00', 'string', 'Free shipping above this amount'),
('vendor_sync_enabled', 'true', 'boolean', 'Enable automatic vendor sync'),
('vendor_sync_frequency', '86400', 'integer', 'Sync frequency in seconds (86400 = 24h)'),
('vendor_sales_order_time', '02:00', 'string', 'Time to create daily vendor sales order')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional indexes for common queries
ALTER TABLE `products` ADD FULLTEXT KEY `search_name` (`name`, `name_en`, `name_de`, `name_sk`);
ALTER TABLE `products` ADD FULLTEXT KEY `search_description` (`description`, `description_en`, `description_de`, `description_sk`);

-- =====================================================
-- VIEWS
-- =====================================================

-- View for product listing with related data
CREATE OR REPLACE VIEW `product_list_view` AS
SELECT 
  p.id,
  p.vendor_article_id,
  p.sku,
  p.ean,
  p.name,
  p.name_en,
  p.name_de,
  p.name_sk,
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

