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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    
    if ($notificationId > 0) {
        if (markNotificationAsRead($notificationId, $current_user['id'])) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

