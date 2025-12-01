-- Aircraft Table
CREATE TABLE IF NOT EXISTS `aircraft` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- Basic Information
  `registration` varchar(20) NOT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `aircraft_category` varchar(50) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `base_location` varchar(100) DEFAULT NULL,
  `responsible_personnel` varchar(100) DEFAULT NULL,
  `aircraft_owner` varchar(100) DEFAULT NULL,
  `aircraft_operator` varchar(100) DEFAULT NULL,
  `date_of_manufacture` date DEFAULT NULL,
  `aircraft_type` varchar(100) DEFAULT NULL,
  
  -- Flight Capabilities
  `nvfr` tinyint(1) DEFAULT 0,
  `ifr` tinyint(1) DEFAULT 0,
  `spifr` tinyint(1) DEFAULT 0,
  
  -- Engine Information
  `engine_type` varchar(100) DEFAULT NULL,
  `number_of_engines` int(11) DEFAULT 1,
  `engine_model` varchar(100) DEFAULT NULL,
  `engine_serial_number` varchar(100) DEFAULT NULL,
  
  -- Avionics
  `avionics` text DEFAULT NULL,
  `other_avionics_information` text DEFAULT NULL,
  
  -- Configuration
  `internal_configuration` text DEFAULT NULL,
  `external_configuration` text DEFAULT NULL,
  `airframe_type` varchar(100) DEFAULT NULL,
  
  -- System Fields
  `enabled` tinyint(1) DEFAULT 1,
  `status` enum('active', 'inactive', 'maintenance', 'retired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `registration` (`registration`),
  KEY `idx_status` (`status`),
  KEY `idx_manufacturer` (`manufacturer`),
  KEY `idx_aircraft_type` (`aircraft_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Aircraft Data
INSERT INTO `aircraft` (`registration`, `serial_number`, `aircraft_category`, `manufacturer`, `base_location`, `responsible_personnel`, `aircraft_owner`, `aircraft_operator`, `date_of_manufacture`, `aircraft_type`, `nvfr`, `ifr`, `spifr`, `engine_type`, `number_of_engines`, `engine_model`, `engine_serial_number`, `avionics`, `other_avionics_information`, `internal_configuration`, `external_configuration`, `airframe_type`, `enabled`, `status`) VALUES
('VH-ABC', '12345', 'Commercial', 'Boeing', 'Sydney Airport', 'John Smith', 'Raimon Airways', 'Raimon Airways', '2020-01-15', '737-800', 1, 1, 1, 'Turbofan', 2, 'CFM56-7B24', 'CFM123456', 'Garmin G1000, Autopilot, GPS', 'Additional navigation equipment', '180 passenger seats, Business class configuration', 'Standard livery, Winglets', 'Narrow-body', 1, 'active'),
('VH-DEF', '67890', 'Commercial', 'Airbus', 'Melbourne Airport', 'Sarah Johnson', 'Raimon Airways', 'Raimon Airways', '2019-06-20', 'A320-200', 1, 1, 0, 'Turbofan', 2, 'CFM56-5B4', 'CFM789012', 'Honeywell Primus, Autopilot', 'Weather radar, TCAS', '150 passenger seats, Economy configuration', 'Standard livery', 'Narrow-body', 1, 'active'),
('VH-GHI', '11111', 'Cargo', 'Boeing', 'Brisbane Airport', 'Mike Wilson', 'Raimon Cargo', 'Raimon Cargo', '2018-03-10', '737-800F', 1, 1, 1, 'Turbofan', 2, 'CFM56-7B26', 'CFM345678', 'Basic avionics, GPS', 'Cargo loading system', 'Cargo hold configuration', 'Cargo livery', 'Narrow-body', 1, 'active'),
('VH-JKL', '22222', 'Private', 'Cessna', 'Perth Airport', 'David Brown', 'Private Owner', 'Raimon Airways', '2021-09-05', 'Citation CJ3+', 1, 1, 1, 'Turbofan', 2, 'Williams FJ44-3A', 'WIL901234', 'Garmin G3000, Autopilot', 'Weather radar, TAWS', '8 passenger seats, Executive configuration', 'Private livery', 'Business Jet', 1, 'active'),
('VH-MNO', '33333', 'Commercial', 'Boeing', 'Adelaide Airport', 'Lisa Davis', 'Raimon Airways', 'Raimon Airways', '2017-12-18', '737-700', 1, 1, 0, 'Turbofan', 2, 'CFM56-7B20', 'CFM567890', 'Honeywell Primus, GPS', 'Basic navigation equipment', '126 passenger seats, Economy configuration', 'Standard livery', 'Narrow-body', 1, 'maintenance');
