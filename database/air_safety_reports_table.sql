-- Air Safety Reports Table
-- جدول گزارش‌های ایمنی هوایی

CREATE TABLE IF NOT EXISTS `air_safety_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_number` varchar(50) NOT NULL,
  `report_date` date NOT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected') DEFAULT 'draft',
  
  -- Aircraft Information
  `aircraft_type` varchar(100) DEFAULT NULL,
  `aircraft_registration` varchar(20) DEFAULT NULL,
  `operator` varchar(100) DEFAULT NULL,
  
  -- Flight Information
  `flight_number` varchar(20) DEFAULT NULL,
  `departure_airport` varchar(10) DEFAULT NULL,
  `destination_airport` varchar(10) DEFAULT NULL,
  `diversion_airport` varchar(10) DEFAULT NULL,
  `place_of_occurrence` varchar(100) DEFAULT NULL,
  `occurrence_date` date DEFAULT NULL,
  `occurrence_time_utc` time DEFAULT NULL,
  `technical_log_seq_no` varchar(50) DEFAULT NULL,
  
  -- Purpose of Flight
  `purpose_flight` enum('schedule','non_schedule','charter','cargo','test_flight','re_position','vip','training','ferry','towing') DEFAULT NULL,
  
  -- Flight Phase
  `flight_phase` text DEFAULT NULL,
  `passenger_crew` varchar(255) DEFAULT NULL,
  `flight_rules` enum('VFR','IFR') DEFAULT NULL,
  `altitude_flight_level` varchar(50) DEFAULT NULL,
  `aircraft_speed_kts` int(11) DEFAULT NULL,
  `aircraft_takeoff_weight` int(11) DEFAULT NULL,
  `fault_report_code` varchar(100) DEFAULT NULL,
  
  -- Consequence
  `consequence` text DEFAULT NULL,
  
  -- Configuration at Event
  `config_autopilot` varchar(100) DEFAULT NULL,
  `config_autothrust` varchar(100) DEFAULT NULL,
  `config_gear` varchar(100) DEFAULT NULL,
  `config_flaps` varchar(100) DEFAULT NULL,
  `config_slats` varchar(100) DEFAULT NULL,
  `config_spoilers` varchar(100) DEFAULT NULL,
  
  -- Environmental Details
  `wind_direction` varchar(10) DEFAULT NULL,
  `wind_speed_kts` int(11) DEFAULT NULL,
  `cloud_type` varchar(50) DEFAULT NULL,
  `cloud_height_ft` int(11) DEFAULT NULL,
  `precipitation_type` varchar(50) DEFAULT NULL,
  `precipitation_quantity` varchar(50) DEFAULT NULL,
  `visibility` varchar(50) DEFAULT NULL,
  `icing_severity` varchar(50) DEFAULT NULL,
  `turbulence_severity` varchar(50) DEFAULT NULL,
  `oat_c` int(11) DEFAULT NULL,
  `runway_state` varchar(50) DEFAULT NULL,
  `runway_category` varchar(50) DEFAULT NULL,
  `qnh_hpa` int(11) DEFAULT NULL,
  `windshear_severity` varchar(50) DEFAULT NULL,
  `light_conditions` varchar(50) DEFAULT NULL,
  `runway_type` varchar(50) DEFAULT NULL,
  
  -- Flight Phase
  `flight_phase` text DEFAULT NULL,
  `passenger_crew` varchar(255) DEFAULT NULL,
  `flight_rules` enum('VFR','IFR') DEFAULT NULL,
  `altitude_flight_level` varchar(50) DEFAULT NULL,
  `aircraft_speed_kts` int(11) DEFAULT NULL,
  `aircraft_takeoff_weight` int(11) DEFAULT NULL,
  `fault_report_code` varchar(100) DEFAULT NULL,
  
  -- Consequence
  `consequence` text DEFAULT NULL,
  
  -- Configuration at Event
  `config_autopilot` varchar(100) DEFAULT NULL,
  `config_autothrust` varchar(100) DEFAULT NULL,
  `config_gear` varchar(100) DEFAULT NULL,
  `config_flaps` varchar(100) DEFAULT NULL,
  `config_slats` varchar(100) DEFAULT NULL,
  `config_spoilers` varchar(100) DEFAULT NULL,
  
  -- Environmental Details
  `wind_direction` varchar(10) DEFAULT NULL,
  `wind_speed_kts` int(11) DEFAULT NULL,
  `cloud_type` varchar(50) DEFAULT NULL,
  `cloud_height_ft` int(11) DEFAULT NULL,
  `precipitation_type` varchar(50) DEFAULT NULL,
  `precipitation_quantity` varchar(50) DEFAULT NULL,
  `visibility` varchar(50) DEFAULT NULL,
  `icing_severity` varchar(50) DEFAULT NULL,
  `turbulence_severity` varchar(50) DEFAULT NULL,
  `oat_c` int(11) DEFAULT NULL,
  `runway_state` varchar(50) DEFAULT NULL,
  `runway_category` varchar(50) DEFAULT NULL,
  `qnh_hpa` int(11) DEFAULT NULL,
  `windshear_severity` varchar(50) DEFAULT NULL,
  `light_conditions` varchar(50) DEFAULT NULL,
  `runway_type` varchar(50) DEFAULT NULL,
  
  -- Pilot Information
  `pilot_name` varchar(100) DEFAULT NULL,
  `pilot_license` varchar(50) DEFAULT NULL,
  `pilot_rating` varchar(50) DEFAULT NULL,
  `total_flight_hours` int(11) DEFAULT NULL,
  `hours_on_type` int(11) DEFAULT NULL,
  `hours_last_90_days` int(11) DEFAULT NULL,
  
  -- Occurrence Details
  `occurrence_type` set('flight','air_nav','technical','bird_strike','load_control','other') DEFAULT NULL,
  `occurrence_other` varchar(100) DEFAULT NULL,
  `severity_risk` enum('nil','low','medium','high') DEFAULT NULL,
  `avoiding_action_taken` enum('yes','no') DEFAULT NULL,
  `minimum_vertical_separation` varchar(50) DEFAULT NULL,
  
  -- Description
  `short_description` text DEFAULT NULL,
  `detailed_description` text DEFAULT NULL,
  `action_taken` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  
  -- Additional Information
  `weather_conditions` text DEFAULT NULL,
  `aircraft_condition` text DEFAULT NULL,
  `crew_condition` text DEFAULT NULL,
  `other_aircraft_involved` text DEFAULT NULL,
  
  -- System Fields
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_number` (`report_number`),
  KEY `idx_report_date` (`report_date`),
  KEY `idx_status` (`status`),
  KEY `idx_aircraft_registration` (`aircraft_registration`),
  KEY `idx_flight_number` (`flight_number`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_occurrence_date` (`occurrence_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `air_safety_reports` (
  `report_number`, `report_date`, `status`, `aircraft_type`, `aircraft_registration`, 
  `operator`, `flight_number`, `departure_airport`, `destination_airport`, 
  `place_of_occurrence`, `occurrence_date`, `occurrence_time_utc`, 
  `purpose_flight`, `pilot_name`, `pilot_license`, `total_flight_hours`, 
  `hours_on_type`, `occurrence_type`, `severity_risk`, `avoiding_action_taken`,
  `short_description`, `created_by`
) VALUES (
  'ASR-2025-001', '2025-01-15', 'draft', 'Boeing 737-800', 'EP-ABC', 
  'Raimon Airways', 'RM-123', 'OIII', 'OIKB', 
  'Near Tehran Airport', '2025-01-15', '14:30:00', 
  'commercial', 'John Smith', 'PPL-12345', 2500, 
  500, 'flight', 'medium', 'yes',
  'Near miss with another aircraft during approach', 1
);
