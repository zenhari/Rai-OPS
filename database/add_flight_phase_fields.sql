-- Add Flight Phase and related fields to air_safety_reports table
-- اضافه کردن فیلدهای Flight Phase و مرتبط به جدول air_safety_reports

-- Update purpose_flight enum to include new values
ALTER TABLE `air_safety_reports` 
MODIFY COLUMN `purpose_flight` ENUM('schedule','non_schedule','charter','cargo','test_flight','re_position','vip','training','ferry','towing') DEFAULT NULL;

-- Add Flight Phase fields
ALTER TABLE `air_safety_reports` 
ADD COLUMN `flight_phase` TEXT DEFAULT NULL AFTER `purpose_flight`,
ADD COLUMN `passenger_crew` VARCHAR(255) DEFAULT NULL AFTER `flight_phase`,
ADD COLUMN `flight_rules` ENUM('VFR','IFR') DEFAULT NULL AFTER `passenger_crew`,
ADD COLUMN `altitude_flight_level` VARCHAR(50) DEFAULT NULL AFTER `flight_rules`,
ADD COLUMN `aircraft_speed_kts` INT(11) DEFAULT NULL AFTER `altitude_flight_level`,
ADD COLUMN `aircraft_takeoff_weight` INT(11) DEFAULT NULL AFTER `aircraft_speed_kts`,
ADD COLUMN `fault_report_code` VARCHAR(100) DEFAULT NULL AFTER `aircraft_takeoff_weight`;

-- Add Consequence field
ALTER TABLE `air_safety_reports` 
ADD COLUMN `consequence` TEXT DEFAULT NULL AFTER `fault_report_code`;

-- Add Configuration at Event fields
ALTER TABLE `air_safety_reports` 
ADD COLUMN `config_autopilot` VARCHAR(100) DEFAULT NULL AFTER `consequence`,
ADD COLUMN `config_autothrust` VARCHAR(100) DEFAULT NULL AFTER `config_autopilot`,
ADD COLUMN `config_gear` VARCHAR(100) DEFAULT NULL AFTER `config_autothrust`,
ADD COLUMN `config_flaps` VARCHAR(100) DEFAULT NULL AFTER `config_gear`,
ADD COLUMN `config_slats` VARCHAR(100) DEFAULT NULL AFTER `config_flaps`,
ADD COLUMN `config_spoilers` VARCHAR(100) DEFAULT NULL AFTER `config_slats`;

-- Add Environmental Details fields
ALTER TABLE `air_safety_reports` 
ADD COLUMN `wind_direction` VARCHAR(10) DEFAULT NULL AFTER `config_spoilers`,
ADD COLUMN `wind_speed_kts` INT(11) DEFAULT NULL AFTER `wind_direction`,
ADD COLUMN `cloud_type` VARCHAR(50) DEFAULT NULL AFTER `wind_speed_kts`,
ADD COLUMN `cloud_height_ft` INT(11) DEFAULT NULL AFTER `cloud_type`,
ADD COLUMN `precipitation_type` VARCHAR(50) DEFAULT NULL AFTER `cloud_height_ft`,
ADD COLUMN `precipitation_quantity` VARCHAR(50) DEFAULT NULL AFTER `precipitation_type`,
ADD COLUMN `visibility` VARCHAR(50) DEFAULT NULL AFTER `precipitation_quantity`,
ADD COLUMN `icing_severity` VARCHAR(50) DEFAULT NULL AFTER `visibility`,
ADD COLUMN `turbulence_severity` VARCHAR(50) DEFAULT NULL AFTER `icing_severity`,
ADD COLUMN `oat_c` INT(11) DEFAULT NULL AFTER `turbulence_severity`,
ADD COLUMN `runway_state` VARCHAR(50) DEFAULT NULL AFTER `oat_c`,
ADD COLUMN `runway_category` VARCHAR(50) DEFAULT NULL AFTER `runway_state`,
ADD COLUMN `qnh_hpa` INT(11) DEFAULT NULL AFTER `runway_category`,
ADD COLUMN `windshear_severity` VARCHAR(50) DEFAULT NULL AFTER `qnh_hpa`,
ADD COLUMN `light_conditions` VARCHAR(50) DEFAULT NULL AFTER `windshear_severity`,
ADD COLUMN `runway_type` VARCHAR(50) DEFAULT NULL AFTER `light_conditions`;
