-- Statistics Table for Flight Statistics
-- Stores ticket prices and fuel costs

USE raiops_data;

CREATE TABLE IF NOT EXISTS `statistics` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rego` VARCHAR(20) DEFAULT NULL COMMENT 'Aircraft registration (for ticket prices), NULL for global fuel cost',
    `ticket_price` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Ticket price in Toman',
    `fuel_cost_per_liter` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Fuel cost per liter in Toman (global setting when rego is NULL)',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_rego` (`rego`),
    KEY `idx_rego` (`rego`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: rego can be NULL only for the global fuel_cost_per_liter setting
-- For ticket prices, rego must have a value

