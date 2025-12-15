-- User Locations Table
-- This table stores user location history with device type and timestamp

CREATE TABLE IF NOT EXISTS `user_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'User ID from users table',
  `latitude` DECIMAL(10, 8) NOT NULL COMMENT 'Latitude coordinate',
  `longitude` DECIMAL(11, 8) NOT NULL COMMENT 'Longitude coordinate',
  `accuracy` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Location accuracy in meters',
  `altitude` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Altitude in meters',
  `altitude_accuracy` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Altitude accuracy in meters',
  `heading` DECIMAL(5, 2) DEFAULT NULL COMMENT 'Heading in degrees',
  `speed` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Speed in meters per second',
  `device_type` ENUM('mobile', 'tablet', 'laptop', 'desktop', 'unknown') DEFAULT 'unknown' COMMENT 'Device type',
  `user_agent` TEXT DEFAULT NULL COMMENT 'User agent string',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When location was recorded',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_device_type` (`device_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User location history for tracking user positions';

