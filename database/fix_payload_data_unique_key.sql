-- Fix payload_data table unique key constraint
-- Remove old unique constraint on route_code alone
-- Add composite unique constraint on (route_code, aircraft_id)
USE raimon_fleet;

-- Drop old unique key if exists (route_code alone)
ALTER TABLE `payload_data` 
DROP INDEX IF EXISTS `route_code`;

-- Add composite unique key on (route_code, aircraft_id)
-- This allows same route_code for different aircraft_id values
ALTER TABLE `payload_data` 
ADD UNIQUE KEY `route_aircraft_unique` (`route_code`, `aircraft_id`);

-- Verify the constraint
SHOW CREATE TABLE `payload_data`;

