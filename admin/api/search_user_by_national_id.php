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

if (empty($nationalId)) {
    echo json_encode(['success' => false, 'message' => 'National ID is required']);
    ob_end_flush();
    exit();
}

try {
    $db = getDBConnection();
    
    // Search user by national_id
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
    WHERE national_id = ? 
    AND status = 'active'
    LIMIT 1");
    
    $stmt->execute([$nationalId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Format date_of_birth for date input (YYYY-MM-DD)
        if (!empty($user['date_of_birth'])) {
            $user['date_of_birth'] = date('Y-m-d', strtotime($user['date_of_birth']));
        }
        
        // Use phone if available, otherwise use mobile
        $user['phone_number'] = !empty($user['phone']) ? $user['phone'] : (!empty($user['mobile']) ? $user['mobile'] : '');
        
        // Combine first_name and last_name
        $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found with this National ID'
        ]);
    }
    
    ob_end_flush();
} catch (PDOException $e) {
    error_log("Error searching user by national_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error searching user']);
    ob_end_flush();
} catch (Exception $e) {
    error_log("General error in search_user_by_national_id.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
    ob_end_flush();
}

