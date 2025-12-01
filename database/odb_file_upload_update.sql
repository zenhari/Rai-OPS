-- Add file_path column to odb_notifications table for file upload functionality
ALTER TABLE `odb_notifications` ADD COLUMN `file_path` VARCHAR(500) DEFAULT NULL AFTER `expires_at`;

-- Add index for file_path for better performance
ALTER TABLE `odb_notifications` ADD INDEX `idx_file_path` (`file_path`);
