-- Add Ground Found fields to air_safety_reports table
-- اضافه کردن فیلدهای Ground Found به جدول air_safety_reports

ALTER TABLE `air_safety_reports`
ADD COLUMN `ground_found_name` VARCHAR(255) DEFAULT NULL AFTER `bird_strike_damage_description`,
ADD COLUMN `ground_found_location` VARCHAR(255) DEFAULT NULL AFTER `ground_found_name`,
ADD COLUMN `ground_found_shift` VARCHAR(100) DEFAULT NULL AFTER `ground_found_location`,
ADD COLUMN `ground_found_type` TEXT DEFAULT NULL AFTER `ground_found_shift`,
ADD COLUMN `ground_found_component_description` VARCHAR(255) DEFAULT NULL AFTER `ground_found_type`,
ADD COLUMN `ground_found_part_no` VARCHAR(100) DEFAULT NULL AFTER `ground_found_component_description`,
ADD COLUMN `ground_found_serial_no` VARCHAR(100) DEFAULT NULL AFTER `ground_found_part_no`,
ADD COLUMN `ground_found_atc_chapter` VARCHAR(100) DEFAULT NULL AFTER `ground_found_serial_no`,
ADD COLUMN `ground_found_tag_no` VARCHAR(100) DEFAULT NULL AFTER `ground_found_atc_chapter`;
