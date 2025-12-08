<?php
require_once '../../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (!$userId || !$date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get all flights for this user on this date
    // Check all crew fields (Crew1-Crew10)
    $sql = "
        SELECT f.* 
        FROM flights f
        WHERE DATE(f.FltDate) = ?
        AND (
            f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR f.Crew4 = ? OR f.Crew5 = ?
            OR f.Crew6 = ? OR f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR f.Crew10 = ?
        )
        ORDER BY f.TaskStart ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $date,
        $userId, $userId, $userId, $userId, $userId,
        $userId, $userId, $userId, $userId, $userId
    ]);
    
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'flights' => $flights,
        'count' => count($flights)
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching flight details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>

