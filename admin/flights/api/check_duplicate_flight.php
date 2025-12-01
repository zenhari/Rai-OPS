<?php
require_once '../../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['flightNo']) || !isset($input['flightDate'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$flightNo = trim($input['flightNo']);
$flightDate = trim($input['flightDate']);

if (empty($flightNo) || empty($flightDate)) {
    echo json_encode(['exists' => false]);
    exit();
}

try {
    // Check for duplicate flight number on the same date
    $existingFlight = checkDuplicateFlightNumber($flightNo, $flightDate);
    
    if ($existingFlight) {
        echo json_encode([
            'exists' => true,
            'flight' => [
                'id' => $existingFlight['id'],
                'flightNo' => $existingFlight['FlightNo'],
                'flightDate' => $existingFlight['FltDate']
            ]
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (Exception $e) {
    error_log("Error in check_duplicate_flight.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
