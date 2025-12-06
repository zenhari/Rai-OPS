<?php
// Start output buffering to prevent any accidental output
ob_start();

// Set content type to JSON first (before any output)
header('Content-Type: application/json');

// Disable error display (log errors instead)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../config.php';

// Clear any output that might have been generated
ob_clean();

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        ob_end_flush();
        exit();
    }

    $query = trim($_GET['q'] ?? '');

    if (empty($query) || strlen($query) < 2) {
        ob_clean();
        echo json_encode(['success' => true, 'users' => []]);
        ob_end_flush();
        exit();
    }

    $db = getDBConnection();
    $searchTerm = '%' . $query . '%';
    
    // Check if users table has role_id or role column
    $checkStmt = $db->query("SHOW COLUMNS FROM users LIKE 'role%'");
    $roleColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasRoleId = in_array('role_id', $roleColumns);
    
    if ($hasRoleId) {
        // Use role_id with JOIN to roles table
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.position,
                COALESCE(r.name, 'employee') as role
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.status = 'active'
            AND (
                u.first_name LIKE ? OR
                u.last_name LIKE ? OR
                u.email LIKE ? OR
                u.position LIKE ? OR
                CONCAT(u.first_name, ' ', u.last_name) LIKE ?
            )
            ORDER BY u.first_name, u.last_name
            LIMIT 20
        ");
    } else {
        // Use role enum column directly
        $stmt = $db->prepare("
            SELECT 
                id,
                first_name,
                last_name,
                email,
                position,
                COALESCE(role, 'employee') as role
            FROM users
            WHERE status = 'active'
            AND (
                first_name LIKE ? OR
                last_name LIKE ? OR
                email LIKE ? OR
                position LIKE ? OR
                CONCAT(first_name, ' ', last_name) LIKE ?
            )
            ORDER BY first_name, last_name
            LIMIT 20
        ");
    }
    
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode(['success' => true, 'users' => $users]);
    ob_end_flush();
} catch (PDOException $e) {
    error_log("Error searching users: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error searching users', 'error' => $e->getMessage()]);
    ob_end_flush();
} catch (Exception $e) {
    error_log("Error in search_users.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    ob_end_flush();
}

