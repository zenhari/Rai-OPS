-- ============================================
-- Rename Crew Fields: LSP/RSP/CSP/DH/SCC/CC to Crew1-Crew10
-- Execute this script step by step in phpMyAdmin
-- ============================================

-- ============================================
-- STEP 1: Drop Foreign Keys (if they exist)
-- Ignore errors if they don't exist
-- ============================================

ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_lsp`;
ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_rsp`;
ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_csp`;
ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_dh`;
ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_scc`;
ALTER TABLE `flights` DROP FOREIGN KEY `fk_flights_cc`;

-- ============================================
-- STEP 2: Drop Indexes (if they exist)
-- Ignore errors if they don't exist
-- ============================================

ALTER TABLE `flights` DROP INDEX `idx_flights_lsp`;
ALTER TABLE `flights` DROP INDEX `idx_flights_rsp`;
ALTER TABLE `flights` DROP INDEX `idx_flights_csp`;
ALTER TABLE `flights` DROP INDEX `idx_flights_dh`;
ALTER TABLE `flights` DROP INDEX `idx_flights_scc`;
ALTER TABLE `flights` DROP INDEX `idx_flights_cc`;

-- ============================================
-- STEP 3: Rename Fields One by One
-- Execute each ALTER TABLE separately
-- ============================================

-- Crew1 (LSP)
ALTER TABLE `flights` CHANGE COLUMN `LSP` `Crew1` int(11) DEFAULT NULL COMMENT 'Crew Member 1 ID';
ALTER TABLE `flights` CHANGE COLUMN `LSP_role` `Crew1_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 1 Role';

-- Crew2 (RSP)
ALTER TABLE `flights` CHANGE COLUMN `RSP` `Crew2` int(11) DEFAULT NULL COMMENT 'Crew Member 2 ID';
ALTER TABLE `flights` CHANGE COLUMN `RSP_role` `Crew2_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 2 Role';

-- Crew3 (CSP)
ALTER TABLE `flights` CHANGE COLUMN `CSP` `Crew3` int(11) DEFAULT NULL COMMENT 'Crew Member 3 ID';
ALTER TABLE `flights` CHANGE COLUMN `CSP_role` `Crew3_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 3 Role';

-- Crew4 (DH) - Note: DH_role doesn't exist, so we'll create Crew4_role
ALTER TABLE `flights` CHANGE COLUMN `DH` `Crew4` int(11) DEFAULT NULL COMMENT 'Crew Member 4 ID (Deadhead)';
ALTER TABLE `flights` ADD COLUMN `Crew4_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 4 Role (Deadhead)' AFTER `Crew4`;

-- Crew5 (SCC)
ALTER TABLE `flights` CHANGE COLUMN `SCC` `Crew5` int(11) DEFAULT NULL COMMENT 'Crew Member 5 ID (Senior Cabin Crew)';
ALTER TABLE `flights` CHANGE COLUMN `SCC_role` `Crew5_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 5 Role (Senior Cabin Crew)';

-- Crew6 (CC)
ALTER TABLE `flights` CHANGE COLUMN `CC` `Crew6` int(11) DEFAULT NULL COMMENT 'Crew Member 6 ID (Cabin Crew)';
ALTER TABLE `flights` CHANGE COLUMN `CC_role` `Crew6_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 6 Role (Cabin Crew)';

-- ============================================
-- STEP 4: Add New Crew7-Crew10 Fields
-- ============================================

ALTER TABLE `flights`
    ADD COLUMN `Crew7` int(11) DEFAULT NULL COMMENT 'Crew Member 7 ID' AFTER `Crew6_role`,
    ADD COLUMN `Crew7_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 7 Role' AFTER `Crew7`,
    ADD COLUMN `Crew8` int(11) DEFAULT NULL COMMENT 'Crew Member 8 ID' AFTER `Crew7_role`,
    ADD COLUMN `Crew8_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 8 Role' AFTER `Crew8`,
    ADD COLUMN `Crew9` int(11) DEFAULT NULL COMMENT 'Crew Member 9 ID' AFTER `Crew8_role`,
    ADD COLUMN `Crew9_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 9 Role' AFTER `Crew9`,
    ADD COLUMN `Crew10` int(11) DEFAULT NULL COMMENT 'Crew Member 10 ID' AFTER `Crew9_role`,
    ADD COLUMN `Crew10_role` varchar(10) DEFAULT NULL COMMENT 'Crew Member 10 Role' AFTER `Crew10`;

-- ============================================
-- STEP 5: Add Indexes
-- ============================================

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

-- ============================================
-- STEP 6: Add Foreign Key Constraints
-- ============================================

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

