USE raimon_fleet;

-- Routes Table
CREATE TABLE IF NOT EXISTS `routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_code` varchar(20) NOT NULL,
  `route_name` varchar(255) NOT NULL,
  `origin_station_id` int(11) NOT NULL,
  `destination_station_id` int(11) NOT NULL,
  `distance_nm` decimal(8,2) DEFAULT NULL,
  `flight_time_minutes` int(11) DEFAULT NULL,
  `aircraft_types` text DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `status` enum('active', 'inactive', 'suspended') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `route_code` (`route_code`),
  KEY `idx_origin_station` (`origin_station_id`),
  KEY `idx_destination_station` (`destination_station_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`origin_station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`destination_station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Routes Data
INSERT INTO `routes` (`route_code`, `route_name`, `origin_station_id`, `destination_station_id`, `distance_nm`, `flight_time_minutes`, `aircraft_types`, `frequency`, `status`, `notes`) VALUES
('THR-ISF', 'Tehran to Isfahan', 1, 5, 215.5, 45, '737-800,A320-200', 'Daily', 'active', 'Main domestic route'),
('THR-MHD', 'Tehran to Mashhad', 1, 25, 445.2, 75, '737-800,A320-200', 'Multiple daily', 'active', 'High frequency route'),
('THR-SYZ', 'Tehran to Shiraz', 1, 34, 485.8, 80, '737-800,A320-200', 'Daily', 'active', 'Popular tourist route'),
('THR-TBZ', 'Tehran to Tabriz', 1, 36, 320.1, 55, '737-800,A320-200', 'Daily', 'active', 'Northern route'),
('ISF-SYZ', 'Isfahan to Shiraz', 5, 34, 185.3, 35, '737-800,A320-200', 'Daily', 'active', 'Short domestic route'),
('MHD-SYZ', 'Mashhad to Shiraz', 25, 34, 625.4, 105, '737-800,A320-200', 'Daily', 'active', 'Long domestic route'),
('THR-AWZ', 'Tehran to Ahvaz', 1, 52, 380.2, 65, '737-800,A320-200', 'Daily', 'active', 'Southwestern route'),
('THR-KSH', 'Tehran to Kermanshah', 1, 159, 285.7, 50, '737-800,A320-200', 'Daily', 'active', 'Western route'),
('THR-ZAH', 'Tehran to Zahedan', 1, 37, 685.9, 115, '737-800,A320-200', 'Daily', 'active', 'Southeastern route'),
('THR-BND', 'Tehran to Bandar Abbas', 1, 47, 520.3, 85, '737-800,A320-200', 'Daily', 'active', 'Southern coastal route');

