<?php
require_once '../../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get GET parameter
$crewUserId = isset($_GET['crew_user_id']) ? intval($_GET['crew_user_id']) : 0;

if (empty($crewUserId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameter: crew_user_id']);
    exit;
}

// Get crew user details
$crewUser = getUserById($crewUserId);
if (!$crewUser) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Crew user not found']);
    exit;
}

// Combine address lines
$address = trim(($crewUser['address_line_1'] ?? '') . ' ' . ($crewUser['address_line_2'] ?? ''));
$address = trim($address) ?: null;

// Get coordinates
$latitude = $crewUser['latitude'] ?? null;
$longitude = $crewUser['longitude'] ?? null;

// Return result
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'address' => $address,
    'latitude' => $latitude,
    'longitude' => $longitude
]);

