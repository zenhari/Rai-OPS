<?php
require_once '../../../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['icao_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing ICAO code']);
    exit();
}

$icaoCode = trim($input['icao_code']);

if (empty($icaoCode)) {
    echo json_encode(['success' => false, 'error' => 'ICAO code is required']);
    exit();
}

try {
    $stationInfo = getStationInfoByALAIdentifier($icaoCode);
    
    if ($stationInfo) {
        echo json_encode([
            'success' => true,
            'data' => $stationInfo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No station info found for this ICAO code'
        ]);
    }
} catch (Exception $e) {
    error_log("Error in get_station_info.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

