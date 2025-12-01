<?php
require_once '../../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Query to get all aircraft registrations
    $stmt = $pdo->prepare("SELECT registration FROM aircraft WHERE enabled = 1 ORDER BY registration");
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'registrations' => $registrations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
