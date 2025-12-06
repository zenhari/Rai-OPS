-- Activity Logs Table
-- This table stores all user activities: page views, edits, creates, deletes

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` enum('view','create','update','delete','login','logout','export','print') NOT NULL DEFAULT 'view',
  `page_path` varchar(255) NOT NULL,
  `page_name` varchar(255) DEFAULT NULL,
  `section` varchar(255) DEFAULT NULL COMMENT 'Section of the page (e.g., form name, table name)',
  `field_name` varchar(255) DEFAULT NULL COMMENT 'Field that was changed',
  `old_value` text DEFAULT NULL COMMENT 'Previous value (for updates)',
  `new_value` text DEFAULT NULL COMMENT 'New value (for updates/creates)',
  `record_id` int(11) DEFAULT NULL COMMENT 'ID of the record being modified',
  `record_type` varchar(100) DEFAULT NULL COMMENT 'Type of record (e.g., flight, user, box)',
  `changes_summary` text DEFAULT NULL COMMENT 'JSON summary of all changes',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_page_path` (`page_path`),
  KEY `idx_record` (`record_type`, `record_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_action` (`user_id`, `action_type`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

