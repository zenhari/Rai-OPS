<?php
require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Get flight ID from request
$flight_id = isset($input['flight_id']) ? intval($input['flight_id']) : 0;

if (!$flight_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Flight ID is required']);
    exit();
}

// Get flight data to verify it exists
$flight = getFlightById($flight_id);
if (!$flight) {
    http_response_code(404);
    echo json_encode(['error' => 'Flight not found']);
    exit();
}

// Prepare data for update
$data = [];

// Basic flight information
if (isset($input['Route'])) $data['Route'] = $input['Route'];
if (isset($input['Rego'])) $data['Rego'] = $input['Rego'];
if (isset($input['ACType'])) $data['ACType'] = $input['ACType'];
if (isset($input['TaskName'])) {
    $data['TaskName'] = $input['TaskName'];
    // Also update FlightNo if TaskName is provided
    $data['FlightNo'] = $input['TaskName'];
}

// Task times
if (isset($input['TaskStart'])) $data['TaskStart'] = $input['TaskStart'];
if (isset($input['TaskEnd'])) $data['TaskEnd'] = $input['TaskEnd'];

// Actual times
if (isset($input['actual_out_utc'])) $data['actual_out_utc'] = $input['actual_out_utc'];
if (isset($input['actual_in_utc'])) $data['actual_in_utc'] = $input['actual_in_utc'];

// Passenger information
if (isset($input['adult'])) $data['adult'] = floatval($input['adult']);
if (isset($input['child'])) $data['child'] = floatval($input['child']);
if (isset($input['infant'])) $data['infant'] = floatval($input['infant']);
if (isset($input['total_pax'])) $data['total_pax'] = floatval($input['total_pax']);

// Weight and fuel
if (isset($input['pcs'])) $data['pcs'] = floatval($input['pcs']);
if (isset($input['weight'])) $data['weight'] = floatval($input['weight']);
if (isset($input['uplift_fuel'])) $data['uplift_fuel'] = floatval($input['uplift_fuel']);
if (isset($input['uplft_lbs'])) $data['uplft_lbs'] = intval($input['uplft_lbs']);

// Status
if (isset($input['ScheduledTaskStatus'])) $data['ScheduledTaskStatus'] = $input['ScheduledTaskStatus'];

// Flight times
if (isset($input['boarding'])) $data['boarding'] = $input['boarding'];
if (isset($input['gate_closed'])) $data['gate_closed'] = $input['gate_closed'];
if (isset($input['ready'])) $data['ready'] = $input['ready'];
if (isset($input['start'])) $data['start'] = $input['start'];
if (isset($input['taxi'])) $data['taxi'] = $input['taxi'];
if (isset($input['takeoff'])) $data['takeoff'] = $input['takeoff'];
if (isset($input['landed'])) $data['landed'] = $input['landed'];
if (isset($input['off_block'])) $data['off_block'] = $input['off_block'];
if (isset($input['on_block'])) $data['on_block'] = $input['on_block'];
if (isset($input['return_to_ramp'])) $data['return_to_ramp'] = $input['return_to_ramp'];

// Delay and diversion codes
for ($i = 1; $i <= 5; $i++) {
    $codeField = $i === 1 ? 'delay_diversion_codes' : "delay_diversion_codes_$i";
    $subCodeField = "delay_diversion_sub_codes_$i";
    $minutesField = "minutes_$i";
    $dv93Field = "dv93_$i";
    $remarkField = "remark_$i";
    
    if (isset($input[$codeField])) $data[$codeField] = $input[$codeField];
    if (isset($input[$subCodeField])) $data[$subCodeField] = $input[$subCodeField];
    if (isset($input[$minutesField])) $data[$minutesField] = $input[$minutesField];
    if (isset($input[$dv93Field])) $data[$dv93Field] = $input[$dv93Field];
    if (isset($input[$remarkField])) $data[$remarkField] = $input[$remarkField];
}

// Update timestamp
$data['LastUpdated'] = date('Y-m-d H:i:s');

// Update flight
if (empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'No data to update']);
    exit();
}

if (updateFlight($flight_id, $data)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Flight updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update flight']);
}
?>

