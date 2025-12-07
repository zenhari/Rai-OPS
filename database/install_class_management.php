<?php
require_once __DIR__ . '/../config.php';

$db = getDBConnection();

echo "Installing Class Management System Tables...\n\n";

// Table: classes
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `classes` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL COMMENT 'Course/Class name',
      `duration` VARCHAR(100) DEFAULT NULL COMMENT 'Course duration (e.g., \"40 hours\", \"2 weeks\")',
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: classes\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table classes already exists\n";
    } else {
        echo "✗ Error creating classes: " . $e->getMessage() . "\n";
    }
}

// Table: class_schedules
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `class_schedules` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: class_schedules\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table class_schedules already exists\n";
    } else {
        echo "✗ Error creating class_schedules: " . $e->getMessage() . "\n";
    }
}

// Table: class_assignments
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `class_assignments` (
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
      FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: class_assignments\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table class_assignments already exists\n";
    } else {
        echo "✗ Error creating class_assignments: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Class Management System tables installed successfully!\n";
echo "You can now access the Class Management pages.\n";

