-- Create catering table
CREATE TABLE IF NOT EXISTS `catering` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT 'Catering type: Economy, VIP, CIP, or Custom',
  `custom_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Custom catering name (only when name = Custom)',
  `passenger_food` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Passenger Food and Beverages cost',
  `equipment` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Catering Equipment cost',
  `transportation` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Catering Transportation cost',
  `storage` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Catering Storage cost',
  `waste` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Catering Waste cost',
  `quality_inspection` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Quality Inspection cost',
  `packaging` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Packaging cost',
  `special_services` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Special Services cost',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catering cost configurations';

