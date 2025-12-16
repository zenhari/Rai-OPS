<?php
require_once '../../config.php';

// Check if user is logged in and has access to role_permission page
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied - Not logged in']);
    exit();
}

// Check if user has access to role_permission page (same access control as the main page)
if (!checkPageAccessEnhanced('admin/role_permission.php')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied - Insufficient permissions']);
    exit();
}

$pagePath = $_GET['page_path'] ?? '';

if (empty($pagePath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Page path is required']);
    exit();
}

try {
    $users = getUsersWithoutIndividualAccess($pagePath);
    echo json_encode(['users' => $users]);
} catch (Exception $e) {
    error_log("Error in get_users_without_access.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch users: ' . $e->getMessage()]);
}
?>
