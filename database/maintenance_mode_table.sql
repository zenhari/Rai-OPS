-- Maintenance Mode Table
CREATE TABLE IF NOT EXISTS `maintenance_mode` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `is_active` TINYINT(1) DEFAULT 0 COMMENT '1 = Active, 0 = Inactive',
  `end_datetime` DATETIME DEFAULT NULL COMMENT 'End date and time for maintenance',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maintenance mode settings';

-- Insert default inactive record
INSERT INTO `maintenance_mode` (`is_active`, `end_datetime`) VALUES (0, NULL);

