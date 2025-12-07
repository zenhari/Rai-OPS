-- Class Management System Tables
-- Database: raiops_data

-- Table: classes
-- Stores class/course information
CREATE TABLE IF NOT EXISTS `classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Course/Class name',
  `duration` VARCHAR(100) DEFAULT NULL COMMENT 'Course duration (e.g., "40 hours", "2 weeks")',
  `instructor_id` INT(11) DEFAULT NULL COMMENT 'Instructor user ID',
  `location` VARCHAR(255) DEFAULT NULL COMMENT 'Class location',
  `material_file` VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded material file (PDF/DOCX)',
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'completed') DEFAULT 'active',
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_instructor_id` (`instructor_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: class_schedules
-- Stores schedule information for each class (days of week, time, dates)
CREATE TABLE IF NOT EXISTS `class_schedules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `class_id` INT(11) NOT NULL,
  `day_of_week` ENUM('saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday') NOT NULL,
  `start_time` TIME NOT NULL COMMENT 'Class start time',
  `end_time` TIME NOT NULL COMMENT 'Class end time',
  `start_date` DATE DEFAULT NULL COMMENT 'First occurrence date',
  `end_date` DATE DEFAULT NULL COMMENT 'Last occurrence date',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_day_of_week` (`day_of_week`),
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: class_assignments
-- Stores assignments of classes to users or roles
CREATE TABLE IF NOT EXISTS `class_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `class_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL COMMENT 'Assigned to specific user',
  `role_id` INT(11) DEFAULT NULL COMMENT 'Assigned to all users with this role',
  `assigned_by` INT(11) NOT NULL,
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_class` (`class_id`, `user_id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_assigned_by` (`assigned_by`),
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CHECK (`user_id` IS NOT NULL OR `role_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

