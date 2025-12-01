-- Add national_id column to users table
-- This allows storing National ID for user identification

ALTER TABLE `users` 
ADD COLUMN `national_id` VARCHAR(50) NULL DEFAULT NULL AFTER `asic_number`;

-- Add index for better performance when searching by national ID
CREATE INDEX `idx_users_national_id` ON `users`(`national_id`);

