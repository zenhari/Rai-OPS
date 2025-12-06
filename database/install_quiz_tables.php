<?php
require_once __DIR__ . '/../config.php';

$db = getDBConnection();

echo "Installing Quiz System Tables...\n\n";

// Table: quiz_sets
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `quiz_sets` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL,
      `description` TEXT,
      `time_limit` INT(11) NOT NULL COMMENT 'Time limit in minutes',
      `passing_score` DECIMAL(5,2) NOT NULL COMMENT 'Passing score percentage',
      `created_by` INT(11) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_created_by` (`created_by`),
      FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: quiz_sets\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table quiz_sets already exists\n";
    } else {
        echo "✗ Error creating quiz_sets: " . $e->getMessage() . "\n";
    }
}

// Table: quiz_set_questions
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `quiz_set_questions` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `quiz_set_id` INT(11) NOT NULL,
      `question_id` INT(11) NOT NULL,
      `order_number` INT(11) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_quiz_question` (`quiz_set_id`, `question_id`),
      KEY `idx_quiz_set_id` (`quiz_set_id`),
      KEY `idx_question_id` (`question_id`),
      FOREIGN KEY (`quiz_set_id`) REFERENCES `quiz_sets` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: quiz_set_questions\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table quiz_set_questions already exists\n";
    } else {
        echo "✗ Error creating quiz_set_questions: " . $e->getMessage() . "\n";
    }
}

// Table: quiz_assignments
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `quiz_assignments` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `quiz_set_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `assigned_by` INT(11) NOT NULL,
      `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `due_date` DATETIME,
      `status` ENUM('assigned', 'in_progress', 'completed', 'expired') NOT NULL DEFAULT 'assigned',
      PRIMARY KEY (`id`),
      KEY `idx_quiz_set_id` (`quiz_set_id`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_assigned_by` (`assigned_by`),
      KEY `idx_status` (`status`),
      FOREIGN KEY (`quiz_set_id`) REFERENCES `quiz_sets` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: quiz_assignments\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table quiz_assignments already exists\n";
    } else {
        echo "✗ Error creating quiz_assignments: " . $e->getMessage() . "\n";
    }
}

// Table: quiz_attempts
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `quiz_attempts` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `quiz_assignment_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `completed_at` TIMESTAMP NULL,
      `time_spent` INT(11) COMMENT 'Time spent in seconds',
      `score` DECIMAL(5,2) COMMENT 'Final score percentage',
      `total_questions` INT(11) NOT NULL,
      `correct_answers` INT(11) DEFAULT 0,
      `status` ENUM('in_progress', 'completed', 'timeout') NOT NULL DEFAULT 'in_progress',
      PRIMARY KEY (`id`),
      KEY `idx_quiz_assignment_id` (`quiz_assignment_id`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_status` (`status`),
      FOREIGN KEY (`quiz_assignment_id`) REFERENCES `quiz_assignments` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: quiz_attempts\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table quiz_attempts already exists\n";
    } else {
        echo "✗ Error creating quiz_attempts: " . $e->getMessage() . "\n";
    }
}

// Table: quiz_answers
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `quiz_answers` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `quiz_attempt_id` INT(11) NOT NULL,
      `question_id` INT(11) NOT NULL,
      `selected_option` CHAR(1) COMMENT 'a, b, c, or d',
      `is_correct` TINYINT(1) DEFAULT 0,
      `is_marked` TINYINT(1) DEFAULT 0 COMMENT 'User marked for review',
      `answered_at` TIMESTAMP NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_attempt_question` (`quiz_attempt_id`, `question_id`),
      KEY `idx_quiz_attempt_id` (`quiz_attempt_id`),
      KEY `idx_question_id` (`question_id`),
      FOREIGN KEY (`quiz_attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Created table: quiz_answers\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table quiz_answers already exists\n";
    } else {
        echo "✗ Error creating quiz_answers: " . $e->getMessage() . "\n";
    }
}

echo "\nInstallation complete!\n";
