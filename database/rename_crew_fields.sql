-- Rename crew fields from LSP/RSP/CSP/DH/SCC/CC to Crew1-Crew10 format
-- This script renames the crew fields to a more generic Crew1-Crew10 format
-- IMPORTANT: Execute this script section by section if you encounter errors

-- ============================================
-- STEP 1: Drop existing foreign key constraints FIRST (before indexes)
-- ============================================

SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND constraint_name = 'fk_flights_lsp');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_lsp`', 'SELECT ''FK fk_flights_lsp does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND constraint_name = 'fk_flights_rsp');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_rsp`', 'SELECT ''FK fk_flights_rsp does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND constraint_name = 'fk_flights_csp');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_csp`', 'SELECT ''FK fk_flights_csp does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND constraint_name = 'fk_flights_dh');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_dh`', 'SELECT ''FK fk_flights_dh does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND constraint_name = 'fk_flights_scc');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_scc`', 'SELECT ''FK fk_flights_scc does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND constraint_name = 'fk_flights_cc');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_cc`', 'SELECT ''FK fk_flights_cc does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- STEP 2: Drop existing indexes AFTER foreign keys are dropped
-- ============================================

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND index_name = 'idx_flights_lsp');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP INDEX `idx_flights_lsp`', 'SELECT ''Index idx_flights_lsp does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND index_name = 'idx_flights_rsp');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP INDEX `idx_flights_rsp`', 'SELECT ''Index idx_flights_rsp does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND index_name = 'idx_flights_csp');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP INDEX `idx_flights_csp`', 'SELECT ''Index idx_flights_csp does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND index_name = 'idx_flights_dh');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP INDEX `idx_flights_dh`', 'SELECT ''Index idx_flights_dh does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND index_name = 'idx_flights_scc');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP INDEX `idx_flights_scc`', 'SELECT ''Index idx_flights_scc does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'flights' 
               AND index_name = 'idx_flights_cc');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE `flights` DROP INDEX `idx_flights_cc`', 'SELECT ''Index idx_flights_cc does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rename existing fields to Crew1-Crew3 format
ALTER TABLE `flights` 
    CHANGE COLUMN `LSP` `Crew1` int(11) DEFAULT NULL COMMENT 'Crew Member 1 ID',
    CHANGE COLUMN `LSP_role` `Crew1_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 1 Role',
    CHANGE COLUMN `RSP` `Crew2` int(11) DEFAULT NULL COMMENT 'Crew Member 2 ID',
    CHANGE COLUMN `RSP_role` `Crew2_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 2 Role',
    CHANGE COLUMN `CSP` `Crew3` int(11) DEFAULT NULL COMMENT 'Crew Member 3 ID',
    CHANGE COLUMN `CSP_role` `Crew3_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 3 Role';

-- Rename DH to Crew4 (Deadhead becomes Crew4)
ALTER TABLE `flights`
    CHANGE COLUMN `DH` `Crew4` int(11) DEFAULT NULL COMMENT 'Crew Member 4 ID (Deadhead)',
    CHANGE COLUMN `DH_role` `Crew4_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 4 Role (Deadhead)';

-- Rename SCC to Crew5
ALTER TABLE `flights`
    CHANGE COLUMN `SCC` `Crew5` int(11) DEFAULT NULL COMMENT 'Crew Member 5 ID (Senior Cabin Crew)',
    CHANGE COLUMN `SCC_role` `Crew5_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 5 Role (Senior Cabin Crew)';

-- Rename CC to Crew6
ALTER TABLE `flights`
    CHANGE COLUMN `CC` `Crew6` int(11) DEFAULT NULL COMMENT 'Crew Member 6 ID (Cabin Crew)',
    CHANGE COLUMN `CC_role` `Crew6_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 6 Role (Cabin Crew)';

-- Add new Crew7-Crew10 fields
ALTER TABLE `flights`
    ADD COLUMN `Crew7` int(11) DEFAULT NULL COMMENT 'Crew Member 7 ID' AFTER `Crew6_role`,
    ADD COLUMN `Crew7_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 7 Role' AFTER `Crew7`,
    ADD COLUMN `Crew8` int(11) DEFAULT NULL COMMENT 'Crew Member 8 ID' AFTER `Crew7_role`,
    ADD COLUMN `Crew8_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 8 Role' AFTER `Crew8`,
    ADD COLUMN `Crew9` int(11) DEFAULT NULL COMMENT 'Crew Member 9 ID' AFTER `Crew8_role`,
    ADD COLUMN `Crew9_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 9 Role' AFTER `Crew9`,
    ADD COLUMN `Crew10` int(11) DEFAULT NULL COMMENT 'Crew Member 10 ID' AFTER `Crew9_role`,
    ADD COLUMN `Crew10_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 10 Role' AFTER `Crew10`;

-- Add indexes for Crew1-Crew10
ALTER TABLE `flights` ADD INDEX `idx_flights_crew1` (`Crew1`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew2` (`Crew2`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew3` (`Crew3`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew4` (`Crew4`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew5` (`Crew5`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew6` (`Crew6`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew7` (`Crew7`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew8` (`Crew8`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew9` (`Crew9`);
ALTER TABLE `flights` ADD INDEX `idx_flights_crew10` (`Crew10`);

-- Add foreign key constraints for Crew1-Crew10
ALTER TABLE `flights` 
    ADD CONSTRAINT `fk_flights_crew1` FOREIGN KEY (`Crew1`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew2` FOREIGN KEY (`Crew2`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew3` FOREIGN KEY (`Crew3`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew4` FOREIGN KEY (`Crew4`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew5` FOREIGN KEY (`Crew5`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew6` FOREIGN KEY (`Crew6`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew7` FOREIGN KEY (`Crew7`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew8` FOREIGN KEY (`Crew8`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew9` FOREIGN KEY (`Crew9`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_flights_crew10` FOREIGN KEY (`Crew10`) REFERENCES `users` (`id`) ON DELETE SET NULL;

