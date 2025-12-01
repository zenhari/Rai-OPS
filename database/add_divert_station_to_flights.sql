-- Add divert_station field to flights table
-- This field stores the IATA code of the station where the flight was diverted to

USE raiops_data;

ALTER TABLE `flights` 
ADD COLUMN `divert_station` VARCHAR(10) NULL DEFAULT NULL AFTER `ScheduledTaskStatus`;

-- Add index for better performance when searching by divert station
CREATE INDEX `idx_flights_divert_station` ON `flights`(`divert_station`);

