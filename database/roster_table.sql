-- Roster Table
-- Stores shift code assignments for crew members on specific dates

CREATE TABLE IF NOT EXISTS `roster` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User ID from users table',
  `date` date NOT NULL COMMENT 'Date of the shift assignment',
  `shift_code_id` int(11) DEFAULT NULL COMMENT 'Shift code ID from shift_code table',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created this assignment',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated this assignment',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date` (`user_id`, `date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_date` (`date`),
  KEY `idx_shift_code_id` (`shift_code_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_updated_by` (`updated_by`),
  CONSTRAINT `fk_roster_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roster_shift_code` FOREIGN KEY (`shift_code_id`) REFERENCES `shift_code` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roster_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roster_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roster assignments for crew members';

