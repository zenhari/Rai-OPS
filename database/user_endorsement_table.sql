-- User Endorsement Table
-- This table stores aircraft type endorsements and role assignments for flight crew members
-- Each endorsement links a user to an aircraft type with specific cockpit or cabin roles

CREATE TABLE IF NOT EXISTS `user_endorsement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `aircraft_type` varchar(100) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `role_type` enum('cockpit','cabin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_aircraft_type` (`aircraft_type`),
  KEY `idx_role_code` (`role_code`),
  KEY `idx_role_type` (`role_type`),
  KEY `idx_user_aircraft` (`user_id`, `aircraft_type`),
  CONSTRAINT `user_endorsement_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aircraft type endorsements and role assignments for flight crew members';

