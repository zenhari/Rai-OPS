-- Create a single journey_log table to store all journey log data
-- This replaces the three separate tables: journey_log_entries, journey_log_flights, journey_log_crew

CREATE TABLE IF NOT EXISTS `journey_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pilot_name` varchar(255) NOT NULL,
  `selected_date` date NOT NULL,
  `log_date` date DEFAULT NULL COMMENT 'Legacy field, use selected_date',
  
  -- Aircraft & Sector Information
  `aircraft_type` varchar(50) DEFAULT NULL,
  `aircraft_registration` varchar(20) DEFAULT NULL,
  `flight_date` date DEFAULT NULL,
  `sector_aircraft_type` varchar(50) DEFAULT NULL,
  `sector_aircraft_reg` varchar(20) DEFAULT NULL,
  `sector_date` date DEFAULT NULL,
  `sector1_cm1` tinyint(1) DEFAULT 0,
  `sector1_cm2` tinyint(1) DEFAULT 0,
  `sector2_cm1` tinyint(1) DEFAULT 0,
  `sector2_cm2` tinyint(1) DEFAULT 0,
  `sector3_cm1` tinyint(1) DEFAULT 0,
  `sector3_cm2` tinyint(1) DEFAULT 0,
  `sector4_cm1` tinyint(1) DEFAULT 0,
  `sector4_cm2` tinyint(1) DEFAULT 0,
  `sector_number` int(11) DEFAULT NULL,
  
  -- Commander Comments & Signature
  `commander_comments` text DEFAULT NULL,
  `commander_signature` varchar(255) DEFAULT NULL,
  
  -- Flight Data (stored as JSON for multiple flights)
  `flights_data` longtext DEFAULT NULL COMMENT 'JSON array of flight data (up to 20 flights)',
  
  -- Crew Data (stored as JSON for multiple crew members)
  `crew_data` longtext DEFAULT NULL COMMENT 'JSON array of crew data (up to 20 crew members)',
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_pilot_name` (`pilot_name`),
  KEY `idx_selected_date` (`selected_date`),
  KEY `idx_log_date` (`log_date`),
  KEY `idx_pilot_date` (`pilot_name`, `selected_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

