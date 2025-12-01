<?php
require_once __DIR__ . '/../../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has access
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if (!checkPageAccessEnhanced('admin/users/personnel_recency/index.php')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get record ID from request
$recordId = intval($_GET['id'] ?? 0);

if (!$recordId) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit();
}

try {
    // Get the record
    $record = getPersonnelRecencyById($recordId);
    
    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit();
    }
    
    // Return the record data
    echo json_encode([
        'success' => true,
        'record' => $record
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving record: ' . $e->getMessage()
    ]);
}
?>
