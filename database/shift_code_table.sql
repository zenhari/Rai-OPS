-- Shift Code Table
-- Create shift_code table for storing shift code configurations


CREATE TABLE IF NOT EXISTS `shift_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- Basic Information
  `code` varchar(3) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `text_color` varchar(7) DEFAULT '#000000',
  `background_color` varchar(7) DEFAULT '#FFFFFF',
  `base` varchar(100) DEFAULT 'Common',
  `department` varchar(100) DEFAULT 'Common',
  `category` enum('Duty', 'Non-Duty', 'Standby', 'Leave', 'Aircraft') DEFAULT 'Duty',
  
  -- Duties (stored as JSON array)
  `duties` text DEFAULT NULL COMMENT 'JSON array of duty periods with start and end times',
  `sleeping_accommodation` tinyint(1) DEFAULT 0,
  `duties_non_cumulative` tinyint(1) DEFAULT 0,
  
  -- Flying Duty Period (stored as JSON array)
  `flying_duty_periods` text DEFAULT NULL COMMENT 'JSON array of flying duty periods with start and end times',
  `flight_hours` decimal(5,2) DEFAULT 0.00,
  `sectors` int(11) DEFAULT 0,
  
  -- Work Practice
  `work_practice` varchar(255) DEFAULT NULL,
  `shift_periods` text DEFAULT NULL COMMENT 'JSON array of shift periods with start and end times',
  
  -- Additional Information
  `al` decimal(5,2) DEFAULT 0.00,
  `fl` decimal(5,2) DEFAULT 0.00,
  `start_of_new_tour` tinyint(1) DEFAULT 0,
  `enable_bulk_duty_update` tinyint(1) DEFAULT 0,
  `allowed_in_timesheet` tinyint(1) DEFAULT 1,
  `show_in_scheduler_quick_create` tinyint(1) DEFAULT 0,
  `enabled` tinyint(1) DEFAULT 1,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_category` (`category`),
  KEY `idx_base` (`base`),
  KEY `idx_department` (`department`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shift code configurations for roster management';

