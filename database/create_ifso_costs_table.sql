-- Create ifso_costs table
CREATE TABLE IF NOT EXISTS `ifso_costs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `monthly_prepayment` DECIMAL(15,2) NOT NULL DEFAULT 500000000.00 COMMENT 'Monthly prepayment amount (default: 500,000,000)',
  `salaries` DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'IFSO Salaries total amount',
  `salaries_count` INT(11) NULL DEFAULT NULL COMMENT 'Number of IFSO employees receiving salaries',
  `training` DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'IFSO Training total cost',
  `training_count` INT(11) NULL DEFAULT NULL COMMENT 'Number of employees receiving training',
  `transport` DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'IFSO Transport total cost',
  `transport_count` INT(11) NULL DEFAULT NULL COMMENT 'Number of employees receiving transport',
  `ifso_premium` DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'IFSO Premium/Benefits cost',
  `ifso_premium_count` INT(11) NULL DEFAULT NULL COMMENT 'Number of employees receiving premium',
  `monthly_accommodation` DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'Monthly Accommodation total cost',
  `monthly_accommodation_count` INT(11) NULL DEFAULT NULL COMMENT 'Number of employees receiving accommodation',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IFSO Costs management';

