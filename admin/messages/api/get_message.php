<?php
require_once '../../../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user = getCurrentUser();
$messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($messageId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

// Ensure tables exist
ensureMessagesTablesExist();

// Get message
$message = getMessageById($messageId, $current_user['id']);

if (!$message) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

// Mark as read if user is receiver
if ($message['receiver_id'] == $current_user['id']) {
    markMessageAsRead($messageId, $current_user['id']);
}

echo json_encode(['success' => true, 'message' => $message]);

