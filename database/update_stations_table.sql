-- Remove status column and add new fields to stations table
ALTER TABLE `stations` DROP COLUMN `status`;

-- Add new fields
ALTER TABLE `stations` ADD COLUMN `short_name` VARCHAR(10) NULL AFTER `iata_code`;
ALTER TABLE `stations` ADD COLUMN `timezone` VARCHAR(100) NULL AFTER `short_name`;
ALTER TABLE `stations` ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `timezone`;
ALTER TABLE `stations` ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`;
ALTER TABLE `stations` ADD COLUMN `magnetic_variation` VARCHAR(20) NULL AFTER `longitude`;
ALTER TABLE `stations` ADD COLUMN `address_line1` VARCHAR(100) NULL AFTER `magnetic_variation`;
ALTER TABLE `stations` ADD COLUMN `address_line2` VARCHAR(100) NULL AFTER `address_line1`;
ALTER TABLE `stations` ADD COLUMN `city_suburb` VARCHAR(100) NULL AFTER `address_line2`;
ALTER TABLE `stations` ADD COLUMN `state` VARCHAR(3) NULL AFTER `city_suburb`;
ALTER TABLE `stations` ADD COLUMN `postcode` VARCHAR(10) NULL AFTER `state`;
ALTER TABLE `stations` ADD COLUMN `country` VARCHAR(100) NULL AFTER `postcode`;
ALTER TABLE `stations` ADD COLUMN `owned_by_base` VARCHAR(100) NULL AFTER `country`;
ALTER TABLE `stations` ADD COLUMN `slot_coordination` VARCHAR(50) NULL AFTER `owned_by_base`;

-- Add site properties fields (checkboxes from HTML)
ALTER TABLE `stations` ADD COLUMN `is_ala` TINYINT(1) NOT NULL DEFAULT 0 AFTER `slot_coordination`;
ALTER TABLE `stations` ADD COLUMN `is_fuel_depot` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_ala`;
ALTER TABLE `stations` ADD COLUMN `is_base_office` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_fuel_depot`;
ALTER TABLE `stations` ADD COLUMN `is_customs_immigration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_base_office`;
ALTER TABLE `stations` ADD COLUMN `is_fixed_base_operators` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_customs_immigration`;
ALTER TABLE `stations` ADD COLUMN `is_hls` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_fixed_base_operators`;
ALTER TABLE `stations` ADD COLUMN `is_maintenance_engineering` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_hls`;

-- Add indexes for better performance
ALTER TABLE `stations` ADD INDEX `idx_short_name` (`short_name`);
ALTER TABLE `stations` ADD INDEX `idx_country` (`country`);
ALTER TABLE `stations` ADD INDEX `idx_owned_by_base` (`owned_by_base`);
