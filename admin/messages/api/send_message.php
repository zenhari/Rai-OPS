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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$receiverId = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$parentId = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

// Validate
if ($receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if ($receiverId == $current_user['id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
    exit;
}

// Ensure tables exist
try {
    ensureMessagesTablesExist();
} catch (Exception $e) {
    error_log("Error ensuring messages tables exist: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Send message
try {
    $result = sendMessage($current_user['id'], $receiverId, $subject, $message, $parentId);
    
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to send message']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
    exit;
}

$messageId = $result['id'];

// Handle file uploads
if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
    $uploadDir = '../../../uploads/messages/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowedDocTypes = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // .xlsx
    ];
    
    $files = $_FILES['attachments'];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $fileName = $files['name'][$i];
        $fileTmpName = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileType = $files['type'][$i];
        
        // Validate file type
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $isImage = in_array($fileType, $allowedImageTypes) || in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $isDocument = in_array($fileType, $allowedDocTypes) || in_array($fileExtension, ['docx', 'xlsx']);
        
        if (!$isImage && !$isDocument) {
            continue; // Skip invalid files
        }
        
        // Generate unique filename
        $uniqueFileName = uniqid() . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        $filePath = $uploadDir . $uniqueFileName;
        
        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $filePath)) {
            // Determine file category
            $fileCategory = $isImage ? 'image' : 'document';
            
            // Store relative path
            $relativePath = 'uploads/messages/' . $uniqueFileName;
            
            // Add attachment to database
            addMessageAttachment($messageId, $fileName, $relativePath, $fileType, $fileSize, $fileCategory);
        } else {
            error_log("Failed to move uploaded file: " . $fileName);
        }
    }
}

echo json_encode(['success' => true, 'message_id' => $messageId]);

