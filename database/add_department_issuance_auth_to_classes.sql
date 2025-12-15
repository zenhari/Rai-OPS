-- Add Department and Issuance Authority fields to classes table
-- Database: raiops_data
-- Table: classes

-- Add department column
ALTER TABLE `classes` 
ADD COLUMN `department` VARCHAR(50) DEFAULT 'Training' COMMENT 'Department (Training or Operation)' 
AFTER `description`;

-- Add issuance_auth column
ALTER TABLE `classes` 
ADD COLUMN `issuance_auth` VARCHAR(50) DEFAULT 'completion' COMMENT 'Issuance Authority (completion or attendance)' 
AFTER `department`;

-- Update existing records to have default values if needed
UPDATE `classes` SET `department` = 'Training' WHERE `department` IS NULL;
UPDATE `classes` SET `issuance_auth` = 'completion' WHERE `issuance_auth` IS NULL;

