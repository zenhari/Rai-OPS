<?php
ob_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../config.php';

ob_clean();

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

$nationalId = trim($_GET['national_id'] ?? '');
$firstName = trim($_GET['first_name'] ?? '');
$lastName = trim($_GET['last_name'] ?? '');

// At least one search term is required
if (empty($nationalId) && empty($firstName) && empty($lastName)) {
    echo json_encode(['success' => false, 'message' => 'At least one search term is required']);
    ob_end_flush();
    exit();
}

try {
    $db = getDBConnection();
    
    // Build dynamic query based on provided search terms
    $conditions = [];
    $params = [];
    
    if (!empty($nationalId)) {
        $conditions[] = "national_id LIKE ?";
        $params[] = '%' . $nationalId . '%';
    }
    
    if (!empty($firstName)) {
        $conditions[] = "first_name LIKE ?";
        $params[] = '%' . $firstName . '%';
    }
    
    if (!empty($lastName)) {
        $conditions[] = "last_name LIKE ?";
        $params[] = '%' . $lastName . '%';
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    // Search user by national_id, first_name, or last_name
    $stmt = $db->prepare("SELECT 
        id,
        first_name,
        last_name,
        email,
        phone,
        mobile,
        date_of_birth,
        national_id
    FROM users 
    WHERE $whereClause
    AND status = 'active'
    ORDER BY first_name, last_name
    LIMIT 50");
    
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($users) {
        // Format users data
        foreach ($users as &$user) {
            // Format date_of_birth for date input (YYYY-MM-DD)
            if (!empty($user['date_of_birth'])) {
                $user['date_of_birth'] = date('Y-m-d', strtotime($user['date_of_birth']));
            }
            
            // Use phone if available, otherwise use mobile
            $user['phone_number'] = !empty($user['phone']) ? $user['phone'] : (!empty($user['mobile']) ? $user['mobile'] : '');
            
            // Combine first_name and last_name
            $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No users found',
            'users' => []
        ]);
    }
    
    ob_end_flush();
} catch (PDOException $e) {
    error_log("Error searching users: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error searching users']);
    ob_end_flush();
} catch (Exception $e) {
    error_log("General error in search_user_for_certificate.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
    ob_end_flush();
}

