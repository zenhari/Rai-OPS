-- Add fix_time column to routes table
-- This field stores the fix time in minutes for each route

ALTER TABLE `routes` ADD COLUMN `fix_time` INT(11) NULL AFTER `flight_time_minutes`;

-- Add index for better performance when filtering by fix_time
ALTER TABLE `routes` ADD INDEX `idx_fix_time` (`fix_time`);

-- Add comment to the column
ALTER TABLE `routes` MODIFY COLUMN `fix_time` INT(11) NULL COMMENT 'Fix time in minutes for this route';
