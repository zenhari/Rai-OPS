-- Add latitude and longitude columns to users table
-- This allows storing GPS coordinates for user addresses

ALTER TABLE `users` 
ADD COLUMN `latitude` DECIMAL(10, 8) NULL DEFAULT NULL AFTER `address_line_2`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL DEFAULT NULL AFTER `latitude`;

-- Add index for better performance when searching by location
CREATE INDEX `idx_users_location` ON `users`(`latitude`, `longitude`);

