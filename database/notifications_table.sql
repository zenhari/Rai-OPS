-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_role` varchar(50) DEFAULT NULL COMMENT 'Specific role name from roles table',
  `target_user_id` int(11) DEFAULT NULL COMMENT 'Specific user ID (if targeting individual user)',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `type` enum('info','warning','success','error') DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` datetime DEFAULT NULL COMMENT 'When notification expires',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the notification',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target_role` (`target_role`),
  KEY `idx_target_user_id` (`target_user_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System notifications for users';

-- User Notification Read Status Table
CREATE TABLE IF NOT EXISTS `user_notification_read` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_notification_user` (`notification_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_notification_id` (`notification_id`),
  FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track which users have read which notifications';

