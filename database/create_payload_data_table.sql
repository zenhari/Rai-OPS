-- Create payload_data table for storing route payload weights at different temperatures
USE raimon_fleet;

CREATE TABLE IF NOT EXISTS `payload_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_code` varchar(50) NOT NULL,
  `temperature_20` decimal(10,2) NULL DEFAULT NULL COMMENT 'Payload weight in pounds at 20°C',
  `temperature_25` decimal(10,2) NULL DEFAULT NULL COMMENT 'Payload weight in pounds at 25°C',
  `temperature_35` decimal(10,2) NULL DEFAULT NULL COMMENT 'Payload weight in pounds at 35°C',
  `temperature_40` decimal(10,2) NULL DEFAULT NULL COMMENT 'Payload weight in pounds at 40°C',
  `notes` text NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `route_code` (`route_code`),
  INDEX `idx_route_code` (`route_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
