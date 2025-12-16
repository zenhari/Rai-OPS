-- Aircraft Seat Configurations Table
-- Stores seat configurations for aircraft with flight class assignments

CREATE TABLE IF NOT EXISTS `aircraft_seat_configurations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Configuration name (e.g., Standard 30-Seat Layout)',
  `total_seats` INT(11) NOT NULL DEFAULT 30 COMMENT 'Total number of seats',
  `rows` INT(11) NOT NULL DEFAULT 15 COMMENT 'Number of seat rows',
  `seats_per_row` INT(11) NOT NULL DEFAULT 2 COMMENT 'Number of seats per row',
  `seat_configuration` TEXT NOT NULL COMMENT 'JSON array of seat assignments with row, position, and flight_class_id',
  `created_by` INT(11) DEFAULT NULL COMMENT 'User who created this configuration',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_name` (`name`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

