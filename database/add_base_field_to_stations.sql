-- Add Base field to stations table
-- This field indicates whether a station is a base station or not

ALTER TABLE `stations` ADD COLUMN `is_base` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

-- Add index for better performance when filtering base stations
ALTER TABLE `stations` ADD INDEX `idx_is_base` (`is_base`);

-- Optional: Update existing stations to mark some as base stations
-- Uncomment and modify the following lines as needed:
-- UPDATE `stations` SET `is_base` = 1 WHERE `iata_code` IN ('IKA', 'THR', 'MHD');
