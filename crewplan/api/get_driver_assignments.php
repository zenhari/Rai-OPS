<?php
require_once '../../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['flight_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing flight_id']);
    exit();
}

$flightId = intval($input['flight_id']);
$assignmentDate = isset($input['assignment_date']) ? $input['assignment_date'] : null;

if ($flightId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid flight_id']);
    exit();
}

try {
    // Get driver assignments for the flight
    $assignments = getDriverAssignmentsForFlight($flightId, $assignmentDate);
    
    // Format assignments for response
    $formattedAssignments = [];
    foreach ($assignments as $assignment) {
        // Process picture path
        $picturePath = null;
        if (!empty($assignment['driver_picture'])) {
            $picture = $assignment['driver_picture'];
            // Remove crewplan/ if exists
            $picture = str_replace('crewplan/', '', $picture);
            $picture = str_replace('crewplan\\', '', $picture);
            // If path doesn't start with uploads/, add it
            if (strpos($picture, 'uploads/') !== 0) {
                $picture = 'uploads/profile/' . basename($picture);
            }
            $picturePath = '../' . $picture;
        }
        
        $formattedAssignments[] = [
            'id' => $assignment['id'],
            'driver_id' => $assignment['driver_id'],
            'driver_first_name' => $assignment['driver_first_name'] ?? '',
            'driver_last_name' => $assignment['driver_last_name'] ?? '',
            'driver_mobile' => $assignment['driver_mobile'] ?? null,
            'driver_phone' => $assignment['driver_phone'] ?? null,
            'driver_picture' => $picturePath,
            'crew_user_id' => $assignment['crew_user_id'],
            'crew_first_name' => $assignment['crew_first_name'] ?? '',
            'crew_last_name' => $assignment['crew_last_name'] ?? '',
            'crew_position' => $assignment['crew_position'] ?? '',
            'pickup_location' => $assignment['pickup_location'] ?? '',
            'dropoff_location' => $assignment['dropoff_location'] ?? '',
            'pickup_time' => $assignment['pickup_time'] ?? null,
            'estimated_duration' => $assignment['estimated_duration'] ?? null,
            'estimated_distance' => $assignment['estimated_distance'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'assignments' => $formattedAssignments
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_driver_assignments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>

