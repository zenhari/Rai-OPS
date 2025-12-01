-- Update payload_data table to include aircraft_id for aircraft-specific payload data
USE raimon_fleet;

-- Add aircraft_id column if it doesn't exist
ALTER TABLE `payload_data` 
ADD COLUMN IF NOT EXISTS `aircraft_id` int(11) NULL AFTER `route_code`,
ADD COLUMN IF NOT EXISTS `aircraft_registration` varchar(20) NULL AFTER `aircraft_id`;

-- Update unique key to include aircraft_id (payload data is unique per route + aircraft combination)
ALTER TABLE `payload_data` 
DROP INDEX IF EXISTS `route_code`;

ALTER TABLE `payload_data` 
ADD UNIQUE KEY `route_aircraft_unique` (`route_code`, `aircraft_id`);

-- Add index for better performance when filtering by aircraft
ALTER TABLE `payload_data` 
ADD INDEX IF NOT EXISTS `idx_aircraft_id` (`aircraft_id`);

-- Add foreign key constraint if needed (optional)
-- ALTER TABLE `payload_data` 
-- ADD CONSTRAINT `fk_payload_aircraft` 
-- FOREIGN KEY (`aircraft_id`) REFERENCES `aircraft` (`id`) ON DELETE CASCADE;
