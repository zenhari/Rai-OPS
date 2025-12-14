-- Fix column sizes for hiring table
-- Some columns need to be larger to accommodate API data

ALTER TABLE `hiring` 
  MODIFY COLUMN `travel_readiness` VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN `marital_status` VARCHAR(100) DEFAULT NULL,
  MODIFY COLUMN `job_type` VARCHAR(100) DEFAULT NULL,
  MODIFY COLUMN `status` VARCHAR(255) DEFAULT NULL;

