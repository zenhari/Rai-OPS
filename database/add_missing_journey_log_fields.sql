-- Add missing fields to journey_log_entries table for complete journey log functionality

-- Engine Parameters fields (for ENGINE, ELECTRICAL AND CABIN PARAMETERS section)
ALTER TABLE `journey_log_entries` 
ADD COLUMN `pl_parameter` varchar(20) DEFAULT NULL COMMENT 'PL parameter',
ADD COLUMN `ias_m_parameter` varchar(20) DEFAULT NULL COMMENT 'IAS/M parameter', 
ADD COLUMN `ioat_parameter` varchar(20) DEFAULT NULL COMMENT 'IOAT parameter',
ADD COLUMN `eng_parameter` varchar(20) DEFAULT NULL COMMENT 'Eng parameter',
ADD COLUMN `n1_percent` varchar(20) DEFAULT NULL COMMENT 'N1(%) parameter',
ADD COLUMN `itt_parameter` varchar(20) DEFAULT NULL COMMENT 'ITT parameter',
ADD COLUMN `n2_percent` varchar(20) DEFAULT NULL COMMENT 'N2(%) parameter',
ADD COLUMN `ff_parameter` varchar(20) DEFAULT NULL COMMENT 'FF parameter',
ADD COLUMN `op_parameter` varchar(20) DEFAULT NULL COMMENT 'OP parameter',
ADD COLUMN `ot_parameter` varchar(20) DEFAULT NULL COMMENT 'OT parameter',
ADD COLUMN `a_parameter` varchar(20) DEFAULT NULL COMMENT 'A parameter',
ADD COLUMN `alt_ft` varchar(20) DEFAULT NULL COMMENT 'Alt (ft) parameter',
ADD COLUMN `dp_psi` varchar(20) DEFAULT NULL COMMENT 'DP psi parameter';

-- Technical Log fields (for TECHNICAL LOG section)
ALTER TABLE `journey_log_entries`
ADD COLUMN `technical_leg_number` int(11) DEFAULT NULL COMMENT 'Technical log leg number',
ADD COLUMN `technical_remarks_defects` text DEFAULT NULL COMMENT 'Technical remarks/defects',
ADD COLUMN `actions_taken_technical` text DEFAULT NULL COMMENT 'Actions taken for technical issues',
ADD COLUMN `sign_auth_technical` varchar(255) DEFAULT NULL COMMENT 'Sign & Auth for technical log';

-- Release to Service field (for footer section)
ALTER TABLE `journey_log_entries`
ADD COLUMN `release_to_service` text DEFAULT NULL COMMENT 'Maintenance release to service details';

-- Add indexes for better performance
ALTER TABLE `journey_log_entries`
ADD INDEX `idx_technical_leg` (`technical_leg_number`),
ADD INDEX `idx_engine_params` (`eng_parameter`, `n1_percent`, `n2_percent`);
