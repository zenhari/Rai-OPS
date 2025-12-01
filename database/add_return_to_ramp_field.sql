-- Add return_to_ramp field to flights table
-- This field stores the time when aircraft returns to ramp in HHMM format (4 digits)

ALTER TABLE `flights` 
ADD COLUMN `return_to_ramp` VARCHAR(10) NULL COMMENT 'Return to Ramp time in HHMM format (4 digits)' AFTER `on_block`;

