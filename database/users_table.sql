-- Raimon Fleet Management System
-- Users Table Creation
-- Database: raimon_fleet

CREATE DATABASE IF NOT EXISTS `raimon_fleet` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `raimon_fleet`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- Personal Information (Required Fields)
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  
  -- Professional Information
  `asic_number` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `employment_history` text DEFAULT NULL,
  `probationary_date` date DEFAULT NULL,
  `standalone_period_wp` varchar(100) DEFAULT NULL,
  `performance_review` text DEFAULT NULL,
  
  -- Address Information
  `address_line_1` varchar(255) DEFAULT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `suburb_city` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  
  -- Contact Information
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `alternative_mobile` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alternate_email` varchar(100) DEFAULT NULL,
  
  -- Emergency Contact Information
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact_email` varchar(100) DEFAULT NULL,
  `emergency_contact_alternate_email` varchar(100) DEFAULT NULL,
  
  -- Travel Documents
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_nationality` varchar(100) DEFAULT NULL,
  `passport_expiry_date` date DEFAULT NULL,
  `driver_licence_number` varchar(50) DEFAULT NULL,
  `frequent_flyer_number` varchar(50) DEFAULT NULL,
  `other_award_scheme_name` varchar(100) DEFAULT NULL,
  `other_award_scheme_number` varchar(50) DEFAULT NULL,
  
  -- Leave and Entitlements
  `individual_leave_entitlements` text DEFAULT NULL,
  `using_standalone_annual_leave` tinyint(1) DEFAULT 0,
  `leave_days` int(11) DEFAULT 0,
  
  -- Roles and Groups
  `roles_groups` text DEFAULT NULL,
  `selected_roles_groups` text DEFAULT NULL,
  
  -- Communication Preferences
  `receive_scheduled_emails` tinyint(1) DEFAULT 1,
  
  -- Profile Picture
  `picture` varchar(255) DEFAULT NULL,
  
  -- System Fields
  `role` enum('admin','manager','employee','pilot','crew') DEFAULT 'employee',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `status` (`status`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Admin User
INSERT INTO `users` (
  `first_name`, `last_name`, `position`, `username`, `password`,
  `email`, `mobile`, `role`, `status`
) VALUES (
  'Admin', 'User', 'System Administrator', 'admin', 
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
  'admin@raimonairways.com', '09123456789', 'admin', 'active'
);

-- Insert Sample Manager User
INSERT INTO `users` (
  `first_name`, `last_name`, `position`, `username`, `password`,
  `email`, `mobile`, `role`, `status`
) VALUES (
  'John', 'Smith', 'Fleet Manager', 'manager', 
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
  'manager@raimonairways.com', '09123456788', 'manager', 'active'
);

-- Insert Sample Pilot User
INSERT INTO `users` (
  `first_name`, `last_name`, `position`, `username`, `password`,
  `email`, `mobile`, `asic_number`, `passport_number`, `passport_nationality`,
  `driver_licence_number`, `role`, `status`
) VALUES (
  'Captain', 'Johnson', 'Senior Pilot', 'pilot1', 
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
  'pilot1@raimonairways.com', '09123456787', 'ASIC123456', 'P1234567', 'Australian',
  'DL1234567', 'pilot', 'active'
);

-- Insert Sample Crew User
INSERT INTO `users` (
  `first_name`, `last_name`, `position`, `username`, `password`,
  `email`, `mobile`, `asic_number`, `role`, `status`
) VALUES (
  'Sarah', 'Wilson', 'Flight Attendant', 'crew1', 
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
  'crew1@raimonairways.com', '09123456786', 'ASIC123457', 'crew', 'active'
);

-- Create indexes for better performance
CREATE INDEX idx_users_name ON users(first_name, last_name);
CREATE INDEX idx_users_position ON users(position);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_users_last_login ON users(last_login);

-- Add comments to table
ALTER TABLE `users` COMMENT = 'Users table for Raimon Fleet Management System';
