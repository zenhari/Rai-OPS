-- Flight Classes Table
-- Stores flight class configurations (Economy, Premium Economy, Business, First Class, etc.)

CREATE TABLE IF NOT EXISTS `flight_classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Flight class name (e.g., Economy, Premium Economy)',
  `code` VARCHAR(10) NOT NULL COMMENT 'Flight class code (e.g., Y, PY, J, F)',
  `description` TEXT DEFAULT NULL COMMENT 'Optional description of the flight class',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default flight classes
INSERT INTO `flight_classes` (`name`, `code`, `description`, `status`) VALUES
('Economy', 'Y', 'Standard economy class', 'active'),
('Premium Economy', 'PY', 'Premium economy class with extra legroom and amenities', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

