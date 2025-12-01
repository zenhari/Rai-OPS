USE raiops_data;

-- Create route_prices table to store pricing information for each route
CREATE TABLE IF NOT EXISTS `route_prices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `route_id` INT(11) NOT NULL,
    `fuel_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Fuel cost in Toman',
    `maintenance_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Maintenance cost in Toman',
    `crew_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Crew cost in Toman',
    `ground_handling_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Ground handling cost in Toman',
    `airport_fees` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Airport fees in Toman',
    `navigation_fees` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Navigation fees in Toman',
    `insurance_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Insurance cost in Toman',
    `other_costs` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Other costs in Toman',
    `total_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Total calculated cost in Toman',
    `profit_margin_percent` DECIMAL(5, 2) DEFAULT NULL COMMENT 'Profit margin percentage',
    `final_price` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Final price with profit margin in Toman',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_route_price` (`route_id`),
    KEY `idx_route_id` (`route_id`),
    CONSTRAINT `fk_route_prices_route` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

