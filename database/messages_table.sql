-- Messages Table
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL COMMENT 'User ID who sent the message',
  `receiver_id` int(11) NOT NULL COMMENT 'User ID who receives the message',
  `subject` varchar(255) DEFAULT NULL COMMENT 'Message subject',
  `message` text NOT NULL COMMENT 'Message content',
  `parent_id` int(11) DEFAULT NULL COMMENT 'Parent message ID for replies',
  `is_read` tinyint(1) DEFAULT 0 COMMENT 'Whether receiver has read the message',
  `read_at` datetime DEFAULT NULL COMMENT 'When message was read',
  `sender_deleted` tinyint(1) DEFAULT 0 COMMENT 'Deleted by sender',
  `receiver_deleted` tinyint(1) DEFAULT 0 COMMENT 'Deleted by receiver',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User messages system';

-- Message Attachments Table
CREATE TABLE IF NOT EXISTS `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
  `file_path` varchar(500) NOT NULL COMMENT 'Path to stored file',
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type',
  `file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `file_category` enum('image','document','other') DEFAULT 'other' COMMENT 'File category',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_file_category` (`file_category`),
  FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Message attachments (images, documents)';
