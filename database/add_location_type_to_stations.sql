-- Add location_type field to stations table
-- This field will store whether a station is International or Domestic

USE raiops_data;

-- Add location_type column to stations table
ALTER TABLE `stations` 
ADD COLUMN `location_type` ENUM('International', 'Domestic') DEFAULT 'Domestic' AFTER `country`;

-- Update all existing stations to 'Domestic' by default
UPDATE `stations` SET `location_type` = 'Domestic' WHERE `location_type` IS NULL;

-- Update all stations that are used in routes to 'Domestic'
-- This ensures all stations in existing routes are set to Domestic
UPDATE `stations` s
INNER JOIN `routes` r ON (s.id = r.origin_station_id OR s.id = r.destination_station_id)
SET s.`location_type` = 'Domestic'
WHERE s.`location_type` IS NULL OR s.`location_type` = '';

