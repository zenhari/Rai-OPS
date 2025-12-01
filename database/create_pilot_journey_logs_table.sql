-- Create pilot_journey_logs table for storing pilot journey log data
CREATE TABLE IF NOT EXISTS `pilot_journey_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pilot_name` varchar(255) NOT NULL,
  `log_date` date NOT NULL,
  `log_data` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pilot_date` (`pilot_name`, `log_date`),
  KEY `idx_pilot_name` (`pilot_name`),
  KEY `idx_log_date` (`log_date`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
