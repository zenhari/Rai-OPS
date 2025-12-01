<?php
require_once '../../config.php';

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

// Format dates and times - use actual_out_utc and actual_in_utc for actual times
$fltDate = !empty($flight['FltDate']) ? date('D d/m/Y', strtotime($flight['FltDate'])) : '-';

// Scheduled times - use TaskStart and TaskEnd
$scheduledTaskStart = !empty($flight['TaskStart']) ? date('H:i', strtotime($flight['TaskStart'])) : '-';
$scheduledTaskEnd = !empty($flight['TaskEnd']) ? date('H:i', strtotime($flight['TaskEnd'])) : '-';

// Actual times - use actual_out_utc and actual_in_utc (fallback to TaskStart/TaskEnd if not available)
$taskStart = !empty($flight['actual_out_utc']) ? date('H:i', strtotime($flight['actual_out_utc'])) : 
              (!empty($flight['TaskStart']) ? date('H:i', strtotime($flight['TaskStart'])) : '-');

$taskEnd = !empty($flight['actual_in_utc']) ? date('H:i', strtotime($flight['actual_in_utc'])) : 
           (!empty($flight['TaskEnd']) ? date('H:i', strtotime($flight['TaskEnd'])) : '-');

// Calculate duration
$duration = '-';
$actualDuration = '-';

// Calculate scheduled duration from TaskStart and TaskEnd
if (!empty($flight['TaskStart']) && !empty($flight['TaskEnd'])) {
    try {
        $start = new DateTime($flight['TaskStart']);
        $end = new DateTime($flight['TaskEnd']);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;
        $duration = sprintf('%.1f hours', $hours + ($minutes / 60));
    } catch (Exception $e) {
        $duration = '-';
    }
}

// Calculate actual duration from actual_out_utc and actual_in_utc (fallback to TaskStart/TaskEnd if not available)
$actualStart = !empty($flight['actual_out_utc']) ? $flight['actual_out_utc'] : ($flight['TaskStart'] ?? null);
$actualEnd = !empty($flight['actual_in_utc']) ? $flight['actual_in_utc'] : ($flight['TaskEnd'] ?? null);

if ($actualStart && $actualEnd) {
    try {
        $start = new DateTime($actualStart);
        $end = new DateTime($actualEnd);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;
        $actualDuration = sprintf('%.1f hours', $hours + ($minutes / 60));
    } catch (Exception $e) {
        $actualDuration = '-';
    }
}

// Get crew information
$pic = '';
if (!empty($flight['FirstName']) && !empty($flight['LastName'])) {
    $pic = $flight['FirstName'] . ' ' . $flight['LastName'];
} elseif (!empty($flight['CmdPilotID'])) {
    $pilot = getUserById($flight['CmdPilotID']);
    if ($pilot) {
        $pic = ($pilot['first_name'] ?? '') . ' ' . ($pilot['last_name'] ?? '');
    }
}

$otherCrew = $flight['OtherCrew'] ?? '';
$allCrew = $flight['AllCrew'] ?? '';

// Get Crew1 to Crew10 with their roles
$crewList = [];
$db = getDBConnection();

for ($i = 1; $i <= 10; $i++) {
    $crewField = "Crew{$i}";
    $roleField = "Crew{$i}_role";
    
    if (!empty($flight[$crewField])) {
        $crewId = $flight[$crewField];
        $crewRole = $flight[$roleField] ?? '';
        
        // Get user name from users table
        $user = getUserById($crewId);
        if ($user) {
            $firstName = $user['first_name'] ?? '';
            $lastName = $user['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
            
            if ($fullName) {
                $crewList[] = [
                    'name' => $fullName,
                    'role' => $crewRole
                ];
            }
        }
    }
}

// Passenger information - use correct field names from flights table
$adults = $flight['adult'] ?? ($flight['PaxAdult'] ?? 0);
$children = $flight['child'] ?? ($flight['PaxChild'] ?? 0);
$infants = $flight['infant'] ?? ($flight['PaxInfant'] ?? 0);
// Use total_pax if available, otherwise calculate
$totalPassengers = !empty($flight['total_pax']) ? floatval($flight['total_pax']) : ($adults + $children + $infants);

// Additional information - use correct field names from flights table
$pcs = $flight['pcs'] ?? 0;
$weight = $flight['weight'] ?? 0;
$upliftFuel = $flight['uplift_fuel'] ?? 0;
$upliftLbs = $flight['uplft_lbs'] ?? 0;

// Status
$status = $flight['ScheduledTaskStatus'] ?? 'Unknown';

// Format currency and numbers
function formatNumber($value) {
    if ($value === null || $value === '') return '0.00';
    return number_format(floatval($value), 2, '.', '');
}

// Prepare response
$response = [
    'flight_id' => $flight['id'] ?? $flight_id,
    'flight_number' => $flight['TaskName'] ?? ($flight['FlightNo'] ?? '-'),
    'route' => $flight['Route'] ?? '-',
    'aircraft' => $flight['Rego'] ?? '-',
    'aircraft_type' => $flight['ACType'] ?? '-',
    'status' => $status,
    'date' => $fltDate,
    'scheduled_task_start' => $scheduledTaskStart,
    'scheduled_task_end' => $scheduledTaskEnd,
    'task_start' => $taskStart,
    'task_end' => $taskEnd,
    'duration' => $duration,
    'actual_duration' => $actualDuration,
    'pic' => trim($pic),
    'all_crew' => trim($allCrew),
    'crew_list' => $crewList,
    'total_passengers' => $totalPassengers,
    'adults' => formatNumber($adults),
    'children' => formatNumber($children),
    'infants' => formatNumber($infants),
    'pcs' => formatNumber($pcs),
    'weight' => formatNumber($weight),
    'uplift_fuel' => formatNumber($upliftFuel),
    'uplift_lbs' => formatNumber($upliftLbs)
];

// Get delay information
$delays = [];
for ($i = 1; $i <= 5; $i++) {
    $delayCode = $flight['delay_diversion_codes_' . ($i > 1 ? '_' . $i : '')] ?? null;
    $delayMinutes = $flight['minutes_' . $i] ?? null;
    $delayDescription = $flight['dv93_' . $i] ?? null;
    $remark = $flight['remark_' . $i] ?? null;
    
    if ($delayCode && $delayMinutes) {
        $delays[] = [
            'code' => $delayCode,
            'minutes' => $delayMinutes,
            'description' => $delayDescription,
            'remark' => $remark
        ];
    }
}
$response['delays'] = $delays;

header('Content-Type: application/json');
echo json_encode($response);
?>

