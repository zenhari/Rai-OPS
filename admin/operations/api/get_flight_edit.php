<?php
require_once '../../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get flight ID from request
$flight_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$flight_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Flight ID is required']);
    exit();
}

// Get flight data
$flight = getFlightById($flight_id);

if (!$flight) {
    http_response_code(404);
    echo json_encode(['error' => 'Flight not found']);
    exit();
}

// Get delay codes from JSON file
$delayCodesPath = '../../../admin/flights/delay.json';
$delayCodes = [];
if (file_exists($delayCodesPath)) {
    $delayCodes = json_decode(file_get_contents($delayCodesPath), true) ?: [];
}

// Prepare response with all editable fields
$response = [
    'flight_id' => $flight['id'] ?? $flight_id,
    'FlightNo' => $flight['FlightNo'] ?? '',
    'Route' => $flight['Route'] ?? '',
    'Rego' => $flight['Rego'] ?? '',
    'ACType' => $flight['ACType'] ?? '',
    'TaskName' => $flight['TaskName'] ?? '',
    'TaskStart' => $flight['TaskStart'] ?? '',
    'TaskEnd' => $flight['TaskEnd'] ?? '',
    'actual_out_utc' => $flight['actual_out_utc'] ?? '',
    'actual_in_utc' => $flight['actual_in_utc'] ?? '',
    'ScheduledTaskStatus' => $flight['ScheduledTaskStatus'] ?? '',
    'adult' => $flight['adult'] ?? 0,
    'child' => $flight['child'] ?? 0,
    'infant' => $flight['infant'] ?? 0,
    'total_pax' => $flight['total_pax'] ?? 0,
    'pcs' => $flight['pcs'] ?? 0,
    'weight' => $flight['weight'] ?? 0,
    'uplift_fuel' => $flight['uplift_fuel'] ?? 0,
    'uplft_lbs' => $flight['uplft_lbs'] ?? 0,
    'boarding' => $flight['boarding'] ?? '',
    'gate_closed' => $flight['gate_closed'] ?? '',
    'ready' => $flight['ready'] ?? '',
    'start' => $flight['start'] ?? '',
    'taxi' => $flight['taxi'] ?? '',
    'takeoff' => $flight['takeoff'] ?? '',
    'landed' => $flight['landed'] ?? '',
    'off_block' => $flight['off_block'] ?? '',
    'on_block' => $flight['on_block'] ?? '',
    'return_to_ramp' => $flight['return_to_ramp'] ?? '',
    'delay_diversion_codes' => $flight['delay_diversion_codes'] ?? '',
    'delay_diversion_codes_2' => $flight['delay_diversion_codes_2'] ?? '',
    'delay_diversion_codes_3' => $flight['delay_diversion_codes_3'] ?? '',
    'delay_diversion_codes_4' => $flight['delay_diversion_codes_4'] ?? '',
    'delay_diversion_codes_5' => $flight['delay_diversion_codes_5'] ?? '',
    'delay_diversion_sub_codes_1' => $flight['delay_diversion_sub_codes_1'] ?? '',
    'delay_diversion_sub_codes_2' => $flight['delay_diversion_sub_codes_2'] ?? '',
    'delay_diversion_sub_codes_3' => $flight['delay_diversion_sub_codes_3'] ?? '',
    'delay_diversion_sub_codes_4' => $flight['delay_diversion_sub_codes_4'] ?? '',
    'delay_diversion_sub_codes_5' => $flight['delay_diversion_sub_codes_5'] ?? '',
    'minutes_1' => $flight['minutes_1'] ?? '',
    'minutes_2' => $flight['minutes_2'] ?? '',
    'minutes_3' => $flight['minutes_3'] ?? '',
    'minutes_4' => $flight['minutes_4'] ?? '',
    'minutes_5' => $flight['minutes_5'] ?? '',
    'dv93_1' => $flight['dv93_1'] ?? '',
    'dv93_2' => $flight['dv93_2'] ?? '',
    'dv93_3' => $flight['dv93_3'] ?? '',
    'dv93_4' => $flight['dv93_4'] ?? '',
    'dv93_5' => $flight['dv93_5'] ?? '',
    'remark_1' => $flight['remark_1'] ?? '',
    'remark_2' => $flight['remark_2'] ?? '',
    'remark_3' => $flight['remark_3'] ?? '',
    'remark_4' => $flight['remark_4'] ?? '',
    'remark_5' => $flight['remark_5'] ?? '',
    'delay_codes' => $delayCodes
];

header('Content-Type: application/json');
echo json_encode($response);
?>

