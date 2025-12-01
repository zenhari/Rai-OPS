<?php
require_once '../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_ids']) || !is_array($input['user_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid user_ids']);
    exit();
}

$userIds = array_filter($input['user_ids'], 'is_numeric'); // Filter only numeric IDs

if (empty($userIds)) {
    echo json_encode(['success' => true, 'users' => []]);
    exit();
}

try {
    // Get user details for each ID
    $userDetails = [];
    foreach ($userIds as $userId) {
        $user = getUserById($userId);
        if ($user) {
            // Extract only the fields we need
            $userDetails[$userId] = [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'position' => $user['position'],
                'picture' => isset($user['picture']) ? $user['picture'] : null,
                'mobile' => isset($user['mobile']) ? $user['mobile'] : null,
                'phone' => isset($user['phone']) ? $user['phone'] : null
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'users' => $userDetails
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_crew_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
