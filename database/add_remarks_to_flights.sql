-- Adds remark fields for delay code 99 per row in flights table
-- Safe to run multiple times if you guard with conditional checks in your DB client

ALTER TABLE `flights`
    ADD COLUMN `remark_1` TEXT NULL AFTER `dv93_1`,
    ADD COLUMN `remark_2` TEXT NULL AFTER `dv93_2`,
    ADD COLUMN `remark_3` TEXT NULL AFTER `dv93_3`,
    ADD COLUMN `remark_4` TEXT NULL AFTER `dv93_4`,
    ADD COLUMN `remark_5` TEXT NULL AFTER `dv93_5`;


