<?php
/**
 * Install Activity Logs Table
 * Run this file once to create the activity_logs table
 */

require_once '../config.php';

// Check if user is admin
$current_user = getCurrentUser();
if (!$current_user || ($current_user['role'] ?? '') !== 'admin') {
    die('Access denied. Admin only.');
}

$db = getDBConnection();

// Read SQL file
$sqlFile = __DIR__ . '/activity_logs_table.sql';
if (!file_exists($sqlFile)) {
    die('SQL file not found: ' . $sqlFile);
}

$sql = file_get_contents($sqlFile);

// Execute SQL
try {
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $db->exec($statement);
        }
    }
    
    echo "Activity logs table created successfully!<br>";
    echo "<a href='../admin/full_log/activity_log.php'>Go to Activity Log</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

