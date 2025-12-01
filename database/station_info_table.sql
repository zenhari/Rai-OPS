-- Station Info Table
-- Create station_info table to store detailed station information from CSV import

CREATE TABLE IF NOT EXISTS `station_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- Basic Location Information
  `location_id` int(11) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `location_type` varchar(100) DEFAULT NULL,
  `location_type_id` int(11) DEFAULT NULL,
  
  -- Address Information
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `suburb_city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  
  -- GPS Coordinates
  `gps_latitude` decimal(10, 8) DEFAULT NULL,
  `gps_longitude` decimal(11, 8) DEFAULT NULL,
  `gps_coordinates` varchar(100) DEFAULT NULL,
  `gps_waypoint` varchar(50) DEFAULT NULL,
  
  -- ALA (Airport Landing Area) Information
  `ala_id` int(11) DEFAULT NULL,
  `ala_location_identifier` varchar(10) DEFAULT NULL,
  `ala_call_frequency` varchar(100) DEFAULT NULL,
  `ala_call_sign` varchar(100) DEFAULT NULL,
  `ala_call_type` varchar(100) DEFAULT NULL,
  `ala_changes` varchar(255) DEFAULT NULL,
  `ala_company_airport_categorisation` varchar(100) DEFAULT NULL,
  `ala_date_edited` date DEFAULT NULL,
  `ala_date_inspection` date DEFAULT NULL,
  `ala_descriptions` text DEFAULT NULL,
  `ala_distance` varchar(50) DEFAULT NULL,
  `ala_elevation` varchar(50) DEFAULT NULL,
  `ala_fuel_notes` text DEFAULT NULL,
  `ala_gps_waypoint_assign` varchar(50) DEFAULT NULL,
  `ala_last_updated_by_id` int(11) DEFAULT NULL,
  `ala_last_updated_by_name` varchar(100) DEFAULT NULL,
  `ala_lighting_frequency` varchar(100) DEFAULT NULL,
  `ala_lighting_notes` text DEFAULT NULL,
  `ala_lighting_type` varchar(100) DEFAULT NULL,
  `ala_navaids` text DEFAULT NULL,
  `ala_night_operations` tinyint(1) DEFAULT 0,
  `ala_obstacle_hazards` text DEFAULT NULL,
  `ala_operating_hours` varchar(100) DEFAULT NULL,
  `ala_remarks_restrictions` text DEFAULT NULL,
  `ala_track` varchar(100) DEFAULT NULL,
  `ala_type` varchar(100) DEFAULT NULL,
  `ala_update_contact` varchar(255) DEFAULT NULL,
  `ala_windsock` tinyint(1) DEFAULT 0,
  
  -- Base Information
  `base_id` int(11) DEFAULT NULL,
  `base_manager` varchar(100) DEFAULT NULL,
  `base_name` varchar(255) DEFAULT NULL,
  `base_short_name` varchar(50) DEFAULT NULL,
  
  -- Fuel Information
  `fuel_all_batch_no` varchar(50) DEFAULT NULL,
  `fuel_all_contact_auth` varchar(255) DEFAULT NULL,
  `fuel_all_controlling_auth` varchar(255) DEFAULT NULL,
  `fuel_all_type` varchar(100) DEFAULT NULL,
  `fuel_measurement` varchar(50) DEFAULT NULL,
  `fuel_min_expiry` int(11) DEFAULT NULL,
  `fuel_total_qty` decimal(10, 2) DEFAULT NULL,
  `fuel_total_qty_remaining` decimal(10, 2) DEFAULT NULL,
  `fuel_updated_at` datetime DEFAULT NULL,
  
  -- HLS (Helicopter Landing Site) Information
  `hls_id` int(11) DEFAULT NULL,
  `hls_best_approach_direction` varchar(50) DEFAULT NULL,
  `hls_best_departure_direction` varchar(50) DEFAULT NULL,
  `hls_ca_contact` varchar(255) DEFAULT NULL,
  `hls_call_frequency` varchar(100) DEFAULT NULL,
  `hls_call_sign` varchar(100) DEFAULT NULL,
  `hls_call_type` varchar(100) DEFAULT NULL,
  `hls_ca_operator` varchar(255) DEFAULT NULL,
  `hls_description` text DEFAULT NULL,
  `hls_dimensions` varchar(100) DEFAULT NULL,
  `hls_elevation` varchar(50) DEFAULT NULL,
  `hls_gps_waypoint` varchar(50) DEFAULT NULL,
  `hls_gps_waypoint_assign` varchar(50) DEFAULT NULL,
  `hls_last_updated` datetime DEFAULT NULL,
  `hls_last_updated_by_id` int(11) DEFAULT NULL,
  `hls_last_updated_by_name` varchar(100) DEFAULT NULL,
  `hls_lighting_controlled_by` varchar(255) DEFAULT NULL,
  `hls_lighting` varchar(100) DEFAULT NULL,
  `hls_lighting_contact` varchar(255) DEFAULT NULL,
  `hls_lighting_frequency` varchar(100) DEFAULT NULL,
  `hls_lighting_notes` text DEFAULT NULL,
  `hls_navaids` text DEFAULT NULL,
  `hls_night_operations` tinyint(1) DEFAULT 0,
  `hls_obstacles_hazards` text DEFAULT NULL,
  `hls_operating_hours` varchar(100) DEFAULT NULL,
  `hls_position_bearing` varchar(50) DEFAULT NULL,
  `hls_position_direction` varchar(50) DEFAULT NULL,
  `hls_position_location` varchar(255) DEFAULT NULL,
  `hls_remark_restrictions` text DEFAULT NULL,
  `hls_slope` varchar(100) DEFAULT NULL,
  `hls_type` varchar(100) DEFAULT NULL,
  `hls_update_contact` varchar(255) DEFAULT NULL,
  `hls_windsock` tinyint(1) DEFAULT 0,
  
  -- Additional Information
  `slot_coordination` varchar(100) DEFAULT NULL,
  
  -- System Fields
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  KEY `idx_location_id` (`location_id`),
  KEY `idx_location_name` (`location_name`),
  KEY `idx_location_type` (`location_type`),
  KEY `idx_ala_location_identifier` (`ala_location_identifier`),
  KEY `idx_gps_waypoint` (`gps_waypoint`),
  KEY `idx_base_id` (`base_id`),
  KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

