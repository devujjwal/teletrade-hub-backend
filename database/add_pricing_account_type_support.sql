-- Run this on existing databases to support separate customer/merchant markups.
ALTER TABLE `pricing_rules`
  ADD COLUMN `account_type` ENUM('customer', 'merchant') NOT NULL DEFAULT 'customer' AFTER `rule_type`,
  ADD KEY `idx_pricing_rules_lookup` (`rule_type`, `entity_id`, `account_type`);

-- Ensure merchant global markup exists (copy customer global if present, else default 15%).
INSERT INTO `pricing_rules` (`rule_type`, `account_type`, `entity_id`, `markup_type`, `markup_value`, `priority`, `is_active`)
SELECT 'global', 'merchant', NULL, pr.markup_type, pr.markup_value, pr.priority, pr.is_active
FROM `pricing_rules` pr
WHERE pr.rule_type = 'global' AND pr.account_type = 'customer'
ORDER BY pr.id DESC
LIMIT 1;

INSERT INTO `pricing_rules` (`rule_type`, `account_type`, `entity_id`, `markup_type`, `markup_value`, `priority`, `is_active`)
SELECT 'global', 'merchant', NULL, 'percentage', 15.00, 0, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `pricing_rules` WHERE rule_type = 'global' AND account_type = 'merchant'
);
