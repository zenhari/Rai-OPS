<?php
require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
$longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Latitude and longitude are required']);
    exit;
}

// Validate coordinates
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
    exit;
}

try {
    $pdo = getDBConnection();
    $user = getCurrentUser();
    
    // Get IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Prepare data
    $accuracy = isset($input['accuracy']) && $input['accuracy'] !== null ? floatval($input['accuracy']) : null;
    $altitude = isset($input['altitude']) && $input['altitude'] !== null ? floatval($input['altitude']) : null;
    $altitudeAccuracy = isset($input['altitude_accuracy']) && $input['altitude_accuracy'] !== null ? floatval($input['altitude_accuracy']) : null;
    $heading = isset($input['heading']) && $input['heading'] !== null ? floatval($input['heading']) : null;
    $speed = isset($input['speed']) && $input['speed'] !== null ? floatval($input['speed']) : null;
    $deviceType = isset($input['device_type']) ? trim($input['device_type']) : 'unknown';
    $userAgent = isset($input['user_agent']) ? trim($input['user_agent']) : null;
    
    // Validate device type
    $allowedDeviceTypes = ['mobile', 'tablet', 'laptop', 'desktop', 'unknown'];
    if (!in_array($deviceType, $allowedDeviceTypes)) {
        $deviceType = 'unknown';
    }
    
    // Insert location
    $stmt = $pdo->prepare("INSERT INTO user_locations 
        (user_id, latitude, longitude, accuracy, altitude, altitude_accuracy, heading, speed, device_type, user_agent, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $user['id'],
        $latitude,
        $longitude,
        $accuracy,
        $altitude,
        $altitudeAccuracy,
        $heading,
        $speed,
        $deviceType,
        $userAgent,
        $ipAddress
    ]);
    
    // Log activity
    logActivity('create', 'admin/api/save_location.php', [
        'page_name' => 'Save Location',
        'section' => 'User Location',
        'latitude' => $latitude,
        'longitude' => $longitude
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Location saved successfully',
        'location_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log("Error saving location: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save location'
    ]);
}

