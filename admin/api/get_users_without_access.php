<?php
require_once '../../config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
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
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch users']);
}
?>
