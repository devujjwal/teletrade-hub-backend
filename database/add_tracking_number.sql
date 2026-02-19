-- Add tracking_number and shipping_carrier fields to orders table
-- Migration: Add UPS shipping tracking support

ALTER TABLE `orders` 
ADD COLUMN `tracking_number` VARCHAR(100) NULL COMMENT 'UPS or other carrier tracking number' AFTER `shipping_address_id`,
ADD COLUMN `shipping_carrier` VARCHAR(50) NULL DEFAULT 'UPS' COMMENT 'Shipping carrier name (UPS, FedEx, etc.)' AFTER `tracking_number`,
ADD COLUMN `shipped_at` TIMESTAMP NULL COMMENT 'When the order was shipped' AFTER `shipping_carrier`,
ADD INDEX `idx_tracking_number` (`tracking_number`),
ADD INDEX `idx_shipping_carrier` (`shipping_carrier`);
