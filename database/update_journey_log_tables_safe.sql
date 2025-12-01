-- Safe update script for journey_log_entries table
-- This script checks for existing columns and only adds missing ones
-- Run each ALTER TABLE statement separately and ignore errors for existing columns

-- Step 1: Add selected_date (if it doesn't exist, you'll get an error which you can ignore)
ALTER TABLE `journey_log_entries` 
ADD COLUMN `selected_date` date DEFAULT NULL COMMENT 'Selected date for journey log' AFTER `pilot_name`;

-- Step 2: Add aircraft_type
ALTER TABLE `journey_log_entries` 
ADD COLUMN `aircraft_type` varchar(50) DEFAULT NULL COMMENT 'Aircraft type' AFTER `selected_date`;

-- Step 3: Add aircraft_registration
ALTER TABLE `journey_log_entries` 
ADD COLUMN `aircraft_registration` varchar(20) DEFAULT NULL COMMENT 'Aircraft registration' AFTER `aircraft_type`;

-- Step 4: Add flight_date
ALTER TABLE `journey_log_entries` 
ADD COLUMN `flight_date` date DEFAULT NULL COMMENT 'Flight date' AFTER `aircraft_registration`;

-- Step 5: Add sector_aircraft_type
ALTER TABLE `journey_log_entries` 
ADD COLUMN `sector_aircraft_type` varchar(50) DEFAULT NULL COMMENT 'Sector aircraft type' AFTER `flight_date`;

-- Step 6: Add sector_aircraft_reg
ALTER TABLE `journey_log_entries` 
ADD COLUMN `sector_aircraft_reg` varchar(20) DEFAULT NULL COMMENT 'Sector aircraft registration' AFTER `sector_aircraft_type`;

-- Step 7: Add sector_date
ALTER TABLE `journey_log_entries` 
ADD COLUMN `sector_date` date DEFAULT NULL COMMENT 'Sector date' AFTER `sector_aircraft_reg`;

-- Step 8: Add sector checkboxes
ALTER TABLE `journey_log_entries` 
ADD COLUMN `sector1_cm1` tinyint(1) DEFAULT 0 COMMENT 'Sector 1 CM1 checkbox' AFTER `sector_date`,
ADD COLUMN `sector1_cm2` tinyint(1) DEFAULT 0 COMMENT 'Sector 1 CM2 checkbox' AFTER `sector1_cm1`,
ADD COLUMN `sector2_cm1` tinyint(1) DEFAULT 0 COMMENT 'Sector 2 CM1 checkbox' AFTER `sector1_cm2`,
ADD COLUMN `sector2_cm2` tinyint(1) DEFAULT 0 COMMENT 'Sector 2 CM2 checkbox' AFTER `sector2_cm1`,
ADD COLUMN `sector3_cm1` tinyint(1) DEFAULT 0 COMMENT 'Sector 3 CM1 checkbox' AFTER `sector2_cm2`,
ADD COLUMN `sector3_cm2` tinyint(1) DEFAULT 0 COMMENT 'Sector 3 CM2 checkbox' AFTER `sector3_cm1`,
ADD COLUMN `sector4_cm1` tinyint(1) DEFAULT 0 COMMENT 'Sector 4 CM1 checkbox' AFTER `sector3_cm2`,
ADD COLUMN `sector4_cm2` tinyint(1) DEFAULT 0 COMMENT 'Sector 4 CM2 checkbox' AFTER `sector4_cm1`;

-- Step 9: Add sector_number
ALTER TABLE `journey_log_entries` 
ADD COLUMN `sector_number` int(11) DEFAULT NULL COMMENT 'Sector number' AFTER `sector4_cm2`;

-- Step 10: Add commander_comments
ALTER TABLE `journey_log_entries` 
ADD COLUMN `commander_comments` text DEFAULT NULL COMMENT 'Commander comments' AFTER `sector_number`;

-- Step 11: Add commander_signature
ALTER TABLE `journey_log_entries` 
ADD COLUMN `commander_signature` varchar(255) DEFAULT NULL COMMENT 'Commander signature' AFTER `commander_comments`;

-- Create journey_log_flights table for storing flight data
CREATE TABLE IF NOT EXISTS `journey_log_flights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journey_log_id` int(11) NOT NULL COMMENT 'Foreign key to journey_log_entries.id',
  `flight_number` int(11) NOT NULL COMMENT 'Flight number (1-20)',
  `flight_no` varchar(50) DEFAULT NULL COMMENT 'Flight number',
  `pc_fo` varchar(255) DEFAULT NULL COMMENT 'PC/FO',
  `from_airport` varchar(10) DEFAULT NULL COMMENT 'From airport code',
  `to_airport` varchar(10) DEFAULT NULL COMMENT 'To airport code',
  `ofb` varchar(10) DEFAULT NULL COMMENT 'Off block time',
  `onb` varchar(10) DEFAULT NULL COMMENT 'On block time',
  `block_time` varchar(10) DEFAULT NULL COMMENT 'Block time',
  `atd` varchar(10) DEFAULT NULL COMMENT 'Actual time of departure',
  `ata` varchar(10) DEFAULT NULL COMMENT 'Actual time of arrival',
  `air_time` varchar(10) DEFAULT NULL COMMENT 'Air time',
  `atl_no` varchar(10) DEFAULT NULL COMMENT 'ATL number',
  `off_block` varchar(10) DEFAULT NULL COMMENT 'Off block time (4 digits)',
  `takeoff` varchar(10) DEFAULT NULL COMMENT 'Takeoff time (4 digits)',
  `landing` varchar(10) DEFAULT NULL COMMENT 'Landing time (4 digits)',
  `on_block` varchar(10) DEFAULT NULL COMMENT 'On block time (4 digits)',
  `trip_time` varchar(10) DEFAULT NULL COMMENT 'Trip time (HHMM format)',
  `flight_time` varchar(10) DEFAULT NULL COMMENT 'Flight time (HHMM format)',
  `night_time` varchar(10) DEFAULT NULL COMMENT 'Night time',
  `uplift_ltr` decimal(10,2) DEFAULT NULL COMMENT 'Uplift fuel in liters',
  `ramp_fuel` decimal(10,2) DEFAULT NULL COMMENT 'Ramp fuel',
  `arr_fuel` decimal(10,2) DEFAULT NULL COMMENT 'Arrival fuel',
  `total_fuel` decimal(10,2) DEFAULT NULL COMMENT 'Total fuel',
  `fuel_page_no` varchar(20) DEFAULT NULL COMMENT 'Fuel page number',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_journey_log_id` (`journey_log_id`),
  KEY `idx_flight_number` (`flight_number`),
  CONSTRAINT `fk_journey_log_flights` FOREIGN KEY (`journey_log_id`) REFERENCES `journey_log_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create journey_log_crew table for storing crew data
CREATE TABLE IF NOT EXISTS `journey_log_crew` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journey_log_id` int(11) NOT NULL COMMENT 'Foreign key to journey_log_entries.id',
  `crew_number` int(11) NOT NULL COMMENT 'Crew number (1-20)',
  `crew_rank` varchar(20) DEFAULT NULL COMMENT 'Crew rank (CAPT, FO, etc.)',
  `crew_name` varchar(255) DEFAULT NULL COMMENT 'Crew member name',
  `crew_national_id` varchar(50) DEFAULT NULL COMMENT 'Crew national ID',
  `reporting_hr` int(11) DEFAULT NULL COMMENT 'Reporting hour',
  `reporting_min` int(11) DEFAULT NULL COMMENT 'Reporting minute',
  `eng_shutdown_hr` int(11) DEFAULT NULL COMMENT 'Engine shutdown hour',
  `eng_shutdown_min` int(11) DEFAULT NULL COMMENT 'Engine shutdown minute',
  `fdp_time` varchar(10) DEFAULT NULL COMMENT 'FDP time',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_journey_log_id` (`journey_log_id`),
  KEY `idx_crew_number` (`crew_number`),
  CONSTRAINT `fk_journey_log_crew` FOREIGN KEY (`journey_log_id`) REFERENCES `journey_log_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

