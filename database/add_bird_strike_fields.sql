-- Add Bird Strike fields to air_safety_reports table
-- اضافه کردن فیلدهای Bird Strike به جدول air_safety_reports

ALTER TABLE `air_safety_reports`
ADD COLUMN `bird_strike_type_of_birds` VARCHAR(255) DEFAULT NULL AFTER `runway_type`,
ADD COLUMN `bird_strike_nr_seen` VARCHAR(20) DEFAULT NULL AFTER `bird_strike_type_of_birds`,
ADD COLUMN `bird_strike_nr_struck` VARCHAR(20) DEFAULT NULL AFTER `bird_strike_nr_seen`,
ADD COLUMN `bird_strike_damage_description` TEXT DEFAULT NULL AFTER `bird_strike_nr_struck`;
