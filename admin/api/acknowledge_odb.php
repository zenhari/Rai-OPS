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
    // Check if user is logged in (any logged-in user can acknowledge their own notifications)
    if (!isLoggedIn()) {
        http_response_code(401);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Access denied - Please log in']);
        ob_end_flush();
        exit();
    }

    $current_user = getCurrentUser();
    
    if (!$current_user) {
        http_response_code(401);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Access denied - User not found']);
        ob_end_flush();
        exit();
    }

    // Handle POST request
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug: Log the input
        error_log('ODB API Input: ' . print_r($input, true));
        
        $action = $input['action'] ?? '';
        $notification_id = intval($input['notification_id'] ?? 0);
        
        if ($action === 'acknowledge' && $notification_id > 0) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Check if notification exists and is active
            $notification = getODBNotificationById($notification_id);
            if (!$notification || !$notification['is_active']) {
                http_response_code(404);
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Notification not found or inactive']);
                ob_end_flush();
                exit();
            }
            
            // Check if user has already acknowledged
            if (hasUserAcknowledgedNotification($notification_id, $current_user['id'])) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Notification already acknowledged']);
                ob_end_flush();
                exit();
            }
            
            // Attempt to acknowledge
            $result = acknowledgeODBNotification($notification_id, $current_user['id'], $ip_address, $user_agent);
            
            if ($result) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Notification acknowledged successfully']);
                ob_end_flush();
            } else {
                http_response_code(500);
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to acknowledge notification - database error']);
                ob_end_flush();
            }
        } else {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid request - missing action or notification_id']);
            ob_end_flush();
        }
    } else {
        http_response_code(405);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        ob_end_flush();
    }
} catch (Exception $e) {
    error_log('ODB API Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    ob_end_flush();
}
?>
