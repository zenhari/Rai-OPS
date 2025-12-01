-- Add flight_crew field to users table
-- This field indicates if a user is a flight crew member

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `flight_crew` tinyint(1) DEFAULT 0 COMMENT 'Indicates if user is a flight crew member' 
AFTER `status`;

-- Add index for better performance
ALTER TABLE `users` 
ADD INDEX IF NOT EXISTS `idx_flight_crew` (`flight_crew`);

