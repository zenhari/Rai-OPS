<?php
require_once '../../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get POST data
$crewUserId = isset($_POST['crew_user_id']) ? intval($_POST['crew_user_id']) : 0;
$pickupLocation = isset($_POST['pickup_location']) ? trim($_POST['pickup_location']) : '';
$dropoffLocation = isset($_POST['dropoff_location']) ? trim($_POST['dropoff_location']) : '';

if (empty($dropoffLocation)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing dropoff location']);
    exit;
}

// Get pickup coordinates
$pickupLat = null;
$pickupLng = null;

if (!empty($pickupLocation)) {
    // Check if pickup location is coordinates (lat,lng format)
    if (preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*$/', $pickupLocation)) {
        $coords = explode(',', $pickupLocation);
        $pickupLat = trim($coords[0]);
        $pickupLng = trim($coords[1]);
    } else {
        // Try to get station by IATA code
        $pickupStation = getStationByIATACode(trim($pickupLocation));
        if ($pickupStation) {
            $pickupLat = $pickupStation['latitude'] ?? null;
            $pickupLng = $pickupStation['longitude'] ?? null;
        }
    }
}

// If pickup location not provided or not found, try to get from crew user
if ((!$pickupLat || !$pickupLng) && $crewUserId > 0) {
    $crewUser = getUserById($crewUserId);
    if ($crewUser) {
        $pickupLat = $crewUser['latitude'] ?? null;
        $pickupLng = $crewUser['longitude'] ?? null;
    }
}

if (!$pickupLat || !$pickupLng || !is_numeric($pickupLat) || !is_numeric($pickupLng)) {
    http_response_code(400);
    echo json_encode(['error' => 'Pickup coordinates not available']);
    exit;
}

// Validate coordinates
if ((float)$pickupLat < -90 || (float)$pickupLat > 90 || (float)$pickupLng < -180 || (float)$pickupLng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid pickup coordinates']);
    exit;
}

// Get dropoff coordinates
$dropoffLat = null;
$dropoffLng = null;

// Check if dropoff location is coordinates (lat,lng format)
if (preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*$/', $dropoffLocation)) {
    $coords = explode(',', $dropoffLocation);
    $dropoffLat = trim($coords[0]);
    $dropoffLng = trim($coords[1]);
} else {
    // Try to get station by IATA code
    $dropoffStation = getStationByIATACode(trim($dropoffLocation));
    if ($dropoffStation) {
        $dropoffLat = $dropoffStation['latitude'] ?? null;
        $dropoffLng = $dropoffStation['longitude'] ?? null;
    }
}

if (!$dropoffLat || !$dropoffLng || !is_numeric($dropoffLat) || !is_numeric($dropoffLng)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dropoff coordinates not available']);
    exit;
}

// Validate coordinates
if ((float)$dropoffLat < -90 || (float)$dropoffLat > 90 || (float)$dropoffLng < -180 || (float)$dropoffLng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid dropoff coordinates']);
    exit;
}

// Calculate trip duration
$tripDuration = calculateTripDuration($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, 'car', true);

if (!$tripDuration) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to calculate trip duration']);
    exit;
}

// Format duration and distance in English
function formatDurationEnglish($seconds) {
    if (!is_numeric($seconds) || $seconds < 0) {
        return '0 min';
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($minutes > 0 || $hours == 0) {
        $parts[] = $minutes . 'm';
    }
    
    return implode(' ', $parts);
}

function formatDistanceEnglish($meters) {
    if (!is_numeric($meters) || $meters < 0) {
        return '0 km';
    }
    
    $kilometers = $meters / 1000;
    
    if ($kilometers < 1) {
        return round($meters) . ' m';
    }
    
    if ($kilometers < 10) {
        return number_format($kilometers, 1) . ' km';
    } else {
        return round($kilometers) . ' km';
    }
}

// Return result
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'duration' => $tripDuration['duration'],
    'duration_text' => formatDurationEnglish($tripDuration['duration']),
    'distance' => $tripDuration['distance'],
    'distance_text' => formatDistanceEnglish($tripDuration['distance'])
]);

