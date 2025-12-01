-- Add Journey Log fields to flights table
-- ICAO Annex 6 & EASA ORO.MLR.105 compliant flight timing fields

ALTER TABLE `flights` 
ADD COLUMN `actual_out_utc` DATETIME NULL COMMENT 'Standardized block-out time (TaskStart)' AFTER `TaskEnd`,
ADD COLUMN `actual_off_utc` DATETIME NULL COMMENT 'Take-off (wheels-up)' AFTER `actual_out_utc`,
ADD COLUMN `actual_on_utc` DATETIME NULL COMMENT 'Landing (wheels-down)' AFTER `actual_off_utc`,
ADD COLUMN `actual_in_utc` DATETIME NULL COMMENT 'Block-in time (TaskEnd)' AFTER `actual_on_utc`,
ADD COLUMN `block_time_min` INT NULL COMMENT 'Duration (minutes) between OUT and IN' AFTER `actual_in_utc`,
ADD COLUMN `air_time_min` INT NULL COMMENT 'Duration (minutes) between OFF and ON' AFTER `block_time_min`,
ADD COLUMN `calc_warn` TINYINT(1) DEFAULT 0 COMMENT 'Logical sequence warning flag' AFTER `air_time_min`;

-- Add indexes for better performance
ALTER TABLE `flights` 
ADD INDEX `idx_actual_out_utc` (`actual_out_utc`),
ADD INDEX `idx_actual_in_utc` (`actual_in_utc`),
ADD INDEX `idx_calc_warn` (`calc_warn`);

-- Create view for Journey Log reporting
CREATE OR REPLACE VIEW `v_flight_journey_log` AS
SELECT 
    f.FltDate,
    f.FlightNo,
    f.Rego AS tail_no,
    f.Route,
    f.FirstName,
    f.LastName,
    COALESCE(f.TaskStart, f.actual_out_utc) AS TaskStart,
    COALESCE(f.TaskEnd, f.actual_in_utc) AS TaskEnd,
    f.actual_out_utc,
    f.actual_off_utc,
    f.actual_on_utc,
    f.actual_in_utc,
    f.block_time_min,
    f.air_time_min,
    f.calc_warn,
    f.ACType,
    f.uplift_fuel,
    f.weight,
    CONCAT(f.FirstName, ' ', f.LastName) AS PIC
FROM flights f
WHERE f.FltDate IS NOT NULL;
