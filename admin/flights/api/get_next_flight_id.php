<?php
require_once '../../../config.php';

header('Content-Type: application/json');

try {
    $nextFlightId = getNextFlightID();
    
    echo json_encode([
        'success' => true,
        'next_flight_id' => $nextFlightId
    ]);
} catch (Exception $e) {
    error_log("Error in get_next_flight_id.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get next flight ID',
        'next_flight_id' => 1
    ]);
}
?>
