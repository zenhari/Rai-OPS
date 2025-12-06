<?php
/**
 * Install script for recency_items table
 * Run this file once to create the recency_items table
 */

require_once '../config.php';

$sqlFile = __DIR__ . '/recency_items_table.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found: $sqlFile\n");
}

try {
    $db = getDBConnection();
    $sql = file_get_contents($sqlFile);
    
    // Split SQL statements by semicolon
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            $db->exec($statement);
        }
    }
    
    echo "Successfully created recency_items table!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

