<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get IATA code from query parameter
$iataCode = isset($_GET['iata']) ? trim($_GET['iata']) : '';

if (empty($iataCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'IATA code is required']);
    exit();
}

// Get station by IATA code
$station = getStationByIATACode($iataCode);

if (!$station) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Station not found']);
    exit();
}

// Return station data
echo json_encode([
    'success' => true,
    'station' => [
        'id' => $station['id'],
        'station_name' => $station['station_name'] ?? '',
        'iata_code' => $station['iata_code'] ?? '',
        'icao_code' => $station['icao_code'] ?? '',
        'latitude' => $station['latitude'] ?? null,
        'longitude' => $station['longitude'] ?? null,
        'address_line1' => $station['address_line1'] ?? '',
        'address_line2' => $station['address_line2'] ?? '',
        'city_suburb' => $station['city_suburb'] ?? '',
        'state' => $station['state'] ?? '',
        'postcode' => $station['postcode'] ?? '',
        'country' => $station['country'] ?? ''
    ]
]);

