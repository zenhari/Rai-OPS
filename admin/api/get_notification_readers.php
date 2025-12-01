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

if (isset($_GET['notification_id'])) {
    $notificationId = intval($_GET['notification_id']);
    
    if ($notificationId > 0) {
        // Get notification
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($notification) {
            $readers = getNotificationReaders($notificationId);
            
            echo json_encode([
                'success' => true,
                'readers' => $readers,
                'notification' => $notification
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
}

