<?php
require_once '../../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user = getCurrentUser();
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Ensure tables exist
ensureNotificationsTablesExist();

// Get unread notifications only
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
$notifications = getUserNotifications($current_user['id'], $current_user['role'] ?? 'employee', $unreadOnly);

// Mark as read if requested
if (isset($_POST['mark_read']) && is_numeric($_POST['mark_read'])) {
    markNotificationAsRead(intval($_POST['mark_read']), $current_user['id']);
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'count' => count($notifications)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

