<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/flights/edit.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get error from session if exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['update_flight_error'])) {
    $error = 'Database error: ' . $_SESSION['update_flight_error'];
    unset($_SESSION['update_flight_error']);
}

// Get flight ID from URL
$flight_id = intval($_GET['id'] ?? 0);
if (!$flight_id) {
    header('Location: /admin/flights/index.php');
    exit();
}

// Get flight data
$flight = getFlightById($flight_id);
if (!$flight) {
    header('Location: /admin/flights/index.php');
    exit();
}

// Log page view
logActivity('view', __FILE__, [
    'page_name' => 'Edit Flight',
    'section' => 'Flight Management',
    'record_id' => $flight_id,
    'record_type' => 'flight'
]);

// Get divert station name if exists
$divertStationName = '';
if (!empty($flight['divert_station'])) {
    $divertStation = getStationByIATACode($flight['divert_station']);
    if ($divertStation) {
        $divertStationName = $divertStation['station_name'];
    }
}

// Prepare crew data for JavaScript
$availableCrew = getAllCrewMembers();
$otherCrewNames = parseCrewNames($flight['OtherCrew'] ?? '');
$allCrewNames = parseCrewNames($flight['AllCrew'] ?? '');

// Get LSP information from flights table
$lspInfo = getLSPInfoFromFlights($flight_id);

// Get all distinct registration values from flights table for combo box
$db = getDBConnection();
$regoStmt = $db->query("SELECT DISTINCT Rego FROM flights WHERE Rego IS NOT NULL AND Rego != '' ORDER BY Rego ASC");
$allRegos = $regoStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_flight') {
        // Prepare data for update
        $data = [];
        
        // Basic flight information
        if (isset($_POST['FlightID'])) $data['FlightID'] = $_POST['FlightID'];
        if (isset($_POST['FltDate'])) $data['FltDate'] = $_POST['FltDate'];
        if (isset($_POST['AircraftID'])) $data['AircraftID'] = $_POST['AircraftID'];
        if (isset($_POST['CmdPilotID'])) $data['CmdPilotID'] = $_POST['CmdPilotID'];
        if (isset($_POST['Route'])) $data['Route'] = $_POST['Route'];
        if (isset($_POST['Rego'])) $data['Rego'] = $_POST['Rego'];
        if (isset($_POST['ACType'])) $data['ACType'] = $_POST['ACType'];
        if (isset($_POST['FlightNo'])) $data['FlightNo'] = $_POST['FlightNo'];
        
        // Pilot information
        if (isset($_POST['LastName'])) $data['LastName'] = $_POST['LastName'];
        if (isset($_POST['FirstName'])) $data['FirstName'] = $_POST['FirstName'];
        
        // Flight hours
        if (isset($_POST['FlightHours'])) $data['FlightHours'] = $_POST['FlightHours'];
        if (isset($_POST['CommandHours'])) $data['CommandHours'] = $_POST['CommandHours'];
        
        // Crew information
        if (isset($_POST['OtherCrew'])) $data['OtherCrew'] = $_POST['OtherCrew'];
        if (isset($_POST['AllCrew'])) $data['AllCrew'] = $_POST['AllCrew'];
        
        // Crew1-Crew10 fields
        for ($i = 1; $i <= 10; $i++) {
            $crewField = "Crew{$i}";
            $roleField = "Crew{$i}_role";
            if (isset($_POST[$crewField])) {
                $data[$crewField] = !empty($_POST[$crewField]) ? intval($_POST[$crewField]) : null;
            }
            if (isset($_POST[$roleField])) {
                $data[$roleField] = !empty($_POST[$roleField]) ? $_POST[$roleField] : null;
            }
        }
        
        // Task information
        if (isset($_POST['ScheduledTaskID'])) $data['ScheduledTaskID'] = $_POST['ScheduledTaskID'];
        if (isset($_POST['TaskName'])) $data['TaskName'] = $_POST['TaskName'];
        if (isset($_POST['TaskStart'])) $data['TaskStart'] = $_POST['TaskStart'];
        if (isset($_POST['TaskEnd'])) $data['TaskEnd'] = $_POST['TaskEnd'];
        if (isset($_POST['actual_out_utc'])) $data['actual_out_utc'] = !empty($_POST['actual_out_utc']) ? $_POST['actual_out_utc'] : null;
        if (isset($_POST['actual_in_utc'])) $data['actual_in_utc'] = !empty($_POST['actual_in_utc']) ? $_POST['actual_in_utc'] : null;
        if (isset($_POST['FltHours'])) $data['FltHours'] = $_POST['FltHours'];
        if (isset($_POST['HomeBases'])) $data['HomeBases'] = $_POST['HomeBases'];
        if (isset($_POST['TaskDescriptionHTML'])) $data['TaskDescriptionHTML'] = $_POST['TaskDescriptionHTML'];
        
        // Scheduled information
        if (isset($_POST['ScheduledRoute'])) $data['ScheduledRoute'] = $_POST['ScheduledRoute'];
        if (isset($_POST['ScheduledTaskType'])) $data['ScheduledTaskType'] = $_POST['ScheduledTaskType'];
        if (isset($_POST['ScheduledTaskStatus'])) $data['ScheduledTaskStatus'] = $_POST['ScheduledTaskStatus'];
        if (isset($_POST['divert_station'])) $data['divert_station'] = !empty($_POST['divert_station']) ? $_POST['divert_station'] : null;
        
        // Passenger information
        if (isset($_POST['adult'])) $data['adult'] = $_POST['adult'];
        if (isset($_POST['child'])) $data['child'] = $_POST['child'];
        if (isset($_POST['infant'])) $data['infant'] = $_POST['infant'];
        if (isset($_POST['total_pax'])) $data['total_pax'] = $_POST['total_pax'];
        
        // Flight times
        if (isset($_POST['boarding'])) $data['boarding'] = $_POST['boarding'];
        if (isset($_POST['gate_closed'])) $data['gate_closed'] = $_POST['gate_closed'];
        if (isset($_POST['landed'])) $data['landed'] = $_POST['landed'];
        if (isset($_POST['off_block'])) $data['off_block'] = $_POST['off_block'];
        if (isset($_POST['on_block'])) $data['on_block'] = $_POST['on_block'];
        if (isset($_POST['return_to_ramp'])) $data['return_to_ramp'] = $_POST['return_to_ramp'];
        if (isset($_POST['ready'])) $data['ready'] = $_POST['ready'];
        if (isset($_POST['start'])) $data['start'] = $_POST['start'];
        if (isset($_POST['takeoff'])) $data['takeoff'] = $_POST['takeoff'];
        if (isset($_POST['taxi'])) $data['taxi'] = $_POST['taxi'];
        
        // Weight and fuel
        if (isset($_POST['pcs'])) $data['pcs'] = $_POST['pcs'];
        if (isset($_POST['weight'])) $data['weight'] = $_POST['weight'];
        if (isset($_POST['uplift_fuel'])) $data['uplift_fuel'] = $_POST['uplift_fuel'];
        if (isset($_POST['uplft_lbs'])) $data['uplft_lbs'] = $_POST['uplft_lbs'];
        
        // Delay and diversion codes
        if (isset($_POST['delay_diversion_codes'])) $data['delay_diversion_codes'] = $_POST['delay_diversion_codes'];
        if (isset($_POST['minutes_1'])) $data['minutes_1'] = $_POST['minutes_1'];
        if (isset($_POST['delay_diversion_codes_2'])) $data['delay_diversion_codes_2'] = $_POST['delay_diversion_codes_2'];
        if (isset($_POST['minutes_2'])) $data['minutes_2'] = $_POST['minutes_2'];
        if (isset($_POST['delay_diversion_codes_3'])) $data['delay_diversion_codes_3'] = $_POST['delay_diversion_codes_3'];
        if (isset($_POST['minutes_3'])) $data['minutes_3'] = $_POST['minutes_3'];
        if (isset($_POST['delay_diversion_codes_4'])) $data['delay_diversion_codes_4'] = $_POST['delay_diversion_codes_4'];
        if (isset($_POST['minutes_4'])) $data['minutes_4'] = $_POST['minutes_4'];
        if (isset($_POST['delay_diversion_codes_5'])) $data['delay_diversion_codes_5'] = $_POST['delay_diversion_codes_5'];
        if (isset($_POST['minutes_5'])) $data['minutes_5'] = $_POST['minutes_5'];
        
        // DV93 fields
        if (isset($_POST['dv93_1'])) $data['dv93_1'] = $_POST['dv93_1'];
        if (isset($_POST['dv93_2'])) $data['dv93_2'] = $_POST['dv93_2'];
        if (isset($_POST['dv93_3'])) $data['dv93_3'] = $_POST['dv93_3'];
        if (isset($_POST['dv93_4'])) $data['dv93_4'] = $_POST['dv93_4'];
        if (isset($_POST['dv93_5'])) $data['dv93_5'] = $_POST['dv93_5'];
        
        // DV99 remark fields (shown when code 99 is selected)
        if (isset($_POST['remark_1'])) $data['remark_1'] = $_POST['remark_1'];
        if (isset($_POST['remark_2'])) $data['remark_2'] = $_POST['remark_2'];
        if (isset($_POST['remark_3'])) $data['remark_3'] = $_POST['remark_3'];
        if (isset($_POST['remark_4'])) $data['remark_4'] = $_POST['remark_4'];
        if (isset($_POST['remark_5'])) $data['remark_5'] = $_POST['remark_5'];
        
        // Status
        $data['FlightLocked'] = isset($_POST['FlightLocked']) ? 1 : 0;
        // Validation: Check for duplicate FlightNo on the same date (excluding current flight)
        if (!empty($data['FlightNo']) && !empty($data['FltDate'])) {
            $existingFlight = checkDuplicateFlightNumberForEdit($flight_id, $data['FlightNo'], $data['FltDate']);
            if ($existingFlight) {
                $error = "Flight number '{$data['FlightNo']}' already exists on {$data['FltDate']}. Please choose a different flight number or date.";
            }
        }
        
        $data['LastUpdated'] = date('Y-m-d H:i:s');
        
        // Only proceed with update if no validation errors
        if (empty($error)) {
            // Log the data being sent for debugging
            error_log("updateFlight called for flight ID $flight_id with data: " . json_encode($data));
            
            // Get old flight data for comparison before update
            $oldFlightData = getFlightById($flight_id);
            
            // Clear any previous error from session
            if (isset($_SESSION['update_flight_error'])) {
                unset($_SESSION['update_flight_error']);
            }
            
            if (updateFlight($flight_id, $data)) {
                // Get new flight data for comparison
                $newFlightData = getFlightById($flight_id);
                
                // Collect ALL changes for Activity Log (compare all fields that were in $data)
                $changes = [];
                
                // Get all fields that were updated (from $data array)
                $updatedFields = array_keys($data);
                
                // Also check all fields from old and new data to catch any changes
                $allFields = array_unique(array_merge(array_keys($oldFlightData), array_keys($newFlightData)));
                
                foreach ($allFields as $field) {
                    // Skip internal/system fields
                    if (in_array($field, ['id', 'LastUpdated', 'created_at', 'updated_at'])) {
                        continue;
                    }
                    
                    $oldValue = $oldFlightData[$field] ?? null;
                    $newValue = $newFlightData[$field] ?? null;
                    
                    // Normalize values for comparison
                    $oldNormalized = ($oldValue === null || $oldValue === '') ? null : $oldValue;
                    $newNormalized = ($newValue === null || $newValue === '') ? null : $newValue;
                    
                    // Check if value actually changed
                    if ($oldNormalized != $newNormalized) {
                        // Format values for display
                        $oldDisplay = ($oldValue === null || $oldValue === '') ? '(empty)' : (string)$oldValue;
                        $newDisplay = ($newValue === null || $newValue === '') ? '(empty)' : (string)$newValue;
                        
                        $changes[] = [
                            'field' => $field,
                            'old' => $oldDisplay,
                            'new' => $newDisplay
                        ];
                    }
                }
                
                // Log activity
                if (!empty($changes)) {
                    logActivity('update', __FILE__, [
                        'page_name' => 'Edit Flight',
                        'section' => 'Flight Form',
                        'record_id' => $flight_id,
                        'record_type' => 'flight',
                        'changes' => $changes
                    ]);
                } else {
                    // Log even if no visible changes (some fields might have changed)
                    logActivity('update', __FILE__, [
                        'page_name' => 'Edit Flight',
                        'section' => 'Flight Form',
                        'record_id' => $flight_id,
                        'record_type' => 'flight',
                        'new_value' => 'Flight updated (no field changes detected)'
                    ]);
                }
                
                $message = 'Flight updated successfully.';
                // Refresh flight data
                $flight = getFlightById($flight_id);
            } else {
                // Check if there's a specific error message from updateFlight
                if (isset($_SESSION['update_flight_error'])) {
                    $error = 'Database error: ' . $_SESSION['update_flight_error'];
                    unset($_SESSION['update_flight_error']);
                } else {
                    $error = 'Failed to update flight. Please check the error logs for details.';
                }
                error_log("updateFlight returned false for flight ID $flight_id");
            }
        }
    }
}

function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Flight - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <!-- jQuery and Select2 for searchable dropdowns -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        /* Select2 Dark Mode Support */
        .select2-container--default .select2-selection--single {
            height: 42px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
            background-color: white !important;
        }
        
        /* Sequential Validation Styles */
        .delay-row-disabled {
            opacity: 0.5 !important;
            pointer-events: none;
        }
        
        .delay-row-disabled select,
        .delay-row-disabled input,
        .delay-row-disabled textarea {
            background-color: #f3f4f6 !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
        }
        
        .delay-row-enabled {
            opacity: 1 !important;
            pointer-events: auto;
        }
        
        .dark .select2-container--default .select2-selection--single {
            border: 1px solid #4b5563 !important;
            background-color: #374151 !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px !important;
            padding-left: 12px !important;
            color: #374151 !important;
        }
        
        .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f9fafb !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af !important;
        }
        
        .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #6b7280 !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #6b7280 transparent transparent transparent !important;
        }
        
        .dark .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #9ca3af transparent transparent transparent !important;
        }
        
        .select2-dropdown {
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
            background-color: white !important;
        }
        
        .dark .select2-dropdown {
            border: 1px solid #4b5563 !important;
            background-color: #374151 !important;
        }
        
        .select2-container--default .select2-results__option {
            padding: 8px 12px !important;
            color: #374151 !important;
            background-color: white !important;
        }
        
        .dark .select2-container--default .select2-results__option {
            color: #f9fafb !important;
            background-color: #374151 !important;
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6 !important;
            color: white !important;
        }
        
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #e5e7eb !important;
            color: #374151 !important;
        }
        
        .dark .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #4b5563 !important;
            color: #f9fafb !important;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db !important;
            border-radius: 0.25rem !important;
            padding: 4px 8px !important;
            background-color: white !important;
            color: #374151 !important;
        }
        
        .dark .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #4b5563 !important;
            background-color: #4b5563 !important;
            color: #f9fafb !important;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #3b82f6 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        .select2-container--default .select2-results__message {
            color: #6b7280 !important;
            background-color: white !important;
        }
        
        .dark .select2-container--default .select2-results__message {
            color: #9ca3af !important;
            background-color: #374151 !important;
        }
        
        .select2-container--default .select2-selection--single:focus {
            border-color: #3b82f6 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        .dark .select2-container--default .select2-selection--single:focus {
            border-color: #3b82f6 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        /* Divert Station Dropdown Styles */
        .station-item {
            background-color: #ffffff;
            transition: background-color 0.2s ease;
        }
        
        .station-item.active {
            background-color: #dbeafe !important;
            border-left: 3px solid #3b82f6;
        }
        
        .station-item:hover {
            background-color: #eff6ff;
        }
        
        .station-item:hover .station-check {
            opacity: 1 !important;
        }
        
        .station-item.active .station-check {
            opacity: 1 !important;
        }
        
        /* Dark mode support - using class */
        html.dark .station-item,
        .dark .station-item {
            background-color: #1f2937;
        }
        
        html.dark .station-item.active,
        .dark .station-item.active {
            background-color: #1e3a8a !important;
            border-left: 3px solid #60a5fa;
        }
        
        html.dark .station-item:hover,
        .dark .station-item:hover {
            background-color: #374151;
        }
        
        /* Dark mode support - using prefers-color-scheme */
        @media (prefers-color-scheme: dark) {
            html:not(.light) .station-item {
                background-color: #1f2937;
            }
            
            html:not(.light) .station-item.active {
                background-color: #1e3a8a !important;
                border-left: 3px solid #60a5fa;
            }
            
            html:not(.light) .station-item:hover {
                background-color: #374151;
            }
        }
        
        #divert_station_dropdown {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
            background-color: #ffffff;
            border-color: #d1d5db;
        }
        
        /* Dark mode for dropdown container */
        html.dark #divert_station_dropdown,
        .dark #divert_station_dropdown {
            background-color: #1f2937;
            border-color: #4b5563;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) #divert_station_dropdown {
                background-color: #1f2937;
                border-color: #4b5563;
            }
        }
        
        #divert_station_dropdown::-webkit-scrollbar {
            width: 6px;
        }
        
        #divert_station_dropdown::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }
        
        #divert_station_dropdown::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        
        /* Dark mode scrollbar */
        html.dark #divert_station_dropdown,
        .dark #divert_station_dropdown {
            scrollbar-color: #4a5568 #2d3748;
        }
        
        html.dark #divert_station_dropdown::-webkit-scrollbar-track,
        .dark #divert_station_dropdown::-webkit-scrollbar-track {
            background: #2d3748;
        }
        
        html.dark #divert_station_dropdown::-webkit-scrollbar-thumb,
        .dark #divert_station_dropdown::-webkit-scrollbar-thumb {
            background: #4a5568;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) #divert_station_dropdown {
                scrollbar-color: #4a5568 #2d3748;
            }
            
            html:not(.light) #divert_station_dropdown::-webkit-scrollbar-track {
                background: #2d3748;
            }
            
            html:not(.light) #divert_station_dropdown::-webkit-scrollbar-thumb {
                background: #4a5568;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Flight</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Update flight information for Flight ID: <?php echo safeOutput($flight['FlightID']); ?></p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Flights
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../includes/permission_banner.php'; ?>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Flight Edit Form -->
                <form method="POST" class="space-y-8" onsubmit="return prepareFormSubmission(event)">
                    <input type="hidden" name="action" value="update_flight">
                    
                    <!-- Basic Flight Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Flight Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="hidden">
                                <label for="FlightID" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight ID</label>
                                <input type="text" id="FlightID" name="FlightID" value="<?php echo safeOutput($flight['FlightID']); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="FltDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Date</label>
                                <input type="datetime-local" id="FltDate" name="FltDate" value="<?php echo $flight['FltDate'] ? date('Y-m-d\TH:i', strtotime($flight['FltDate'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="hidden">
                                <label for="AircraftID" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft ID</label>
                                <input type="number" step="0.01" id="AircraftID" name="AircraftID" value="<?php echo safeOutput($flight['AircraftID']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="Rego" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Registration</label>
                                <select id="Rego" name="Rego" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Registration --</option>
                                    <?php 
                                    $currentRego = $flight['Rego'] ?? '';
                                    foreach ($allRegos as $rego): 
                                        $regoSafe = safeOutput($rego);
                                        $selected = ($rego === $currentRego) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $regoSafe; ?>" <?php echo $selected; ?>><?php echo $regoSafe; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="ACType" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Type</label>
                                <input type="text" id="ACType" name="ACType" value="<?php echo safeOutput($flight['ACType']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Pilot Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pilot Information (PIC)</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Hidden Pilot ID field for form submission -->
                            <input type="hidden" id="CmdPilotID" name="CmdPilotID" value="<?php echo safeOutput($flight['CmdPilotID']); ?>">
                            
                            <?php if ($lspInfo): ?>
                                <!-- Display LSP Information -->
                                <div>
                                    <label for="PilotFullName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
                                    <input type="text" id="PilotFullName" name="PilotFullName" value="<?php echo safeOutput($lspInfo['full_name']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label for="PilotPosition" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Position</label>
                                    <input type="text" id="PilotPosition" name="PilotPosition" value="<?php echo safeOutput($lspInfo['position']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            <?php else: ?>
                                <!-- Fallback to original FirstName/LastName fields -->
                                <div>
                                    <label for="FirstName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Name</label>
                                    <input type="text" id="FirstName" name="FirstName" value="<?php echo safeOutput($flight['FirstName']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label for="LastName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Name</label>
                                    <input type="text" id="LastName" name="LastName" value="<?php echo safeOutput($flight['LastName']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Flight Hours -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Hours</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="FlightHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Hours</label>
                                <input type="number" step="0.01" id="FlightHours" name="FlightHours" value="<?php echo safeOutput($flight['FlightHours']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Auto-filled from selected route flight time</p>
                            </div>
                            <div>
                                <label for="CommandHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Command Hours</label>
                                <input type="number" step="0.01" id="CommandHours" name="CommandHours" value="<?php echo safeOutput($flight['CommandHours']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="FltHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Hours (Task)</label>
                                <input type="number" step="0.01" id="FltHours" name="FltHours" value="<?php echo safeOutput($flight['FltHours']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Task Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Task Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="hidden">
                                <label for="ScheduledTaskID" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scheduled Task ID</label>
                                <input type="number" step="0.01" id="ScheduledTaskID" name="ScheduledTaskID" value="<?php echo safeOutput($flight['ScheduledTaskID']); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="TaskName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Name</label>
                                <input type="text" id="TaskName" name="TaskName" value="<?php echo safeOutput($flight['TaskName']); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Auto-filled from Flight Number (only if empty)</p>
                            </div>
                            <div>
                                <label for="HomeBases" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Home Bases</label>
                                <select id="HomeBases" name="HomeBases" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Home Base --</option>
                                    <?php 
                                    $homeBases = getHomeBases();
                                    foreach ($homeBases as $homeBase): 
                                        $displayName = $homeBase['location_name'];
                                        if (!empty($homeBase['short_name'])) {
                                            $displayName .= ' (' . $homeBase['short_name'] . ')';
                                        }
                                        if (!empty($homeBase['city_suburb'])) {
                                            $displayName .= ' - ' . $homeBase['city_suburb'];
                                        }
                                        if (!empty($homeBase['state'])) {
                                            $displayName .= ', ' . $homeBase['state'];
                                        }
                                        if (!empty($homeBase['country'])) {
                                            $displayName .= ', ' . $homeBase['country'];
                                        }
                                        
                                        $isSelected = ($flight['HomeBases'] == $homeBase['location_name']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($homeBase['location_name']); ?>" 
                                                data-short-name="<?php echo htmlspecialchars($homeBase['short_name']); ?>"
                                                data-city="<?php echo htmlspecialchars($homeBase['city_suburb']); ?>"
                                                data-state="<?php echo htmlspecialchars($homeBase['state']); ?>"
                                                data-country="<?php echo htmlspecialchars($homeBase['country']); ?>"
                                                <?php echo $isSelected; ?>>
                                            <?php echo htmlspecialchars($displayName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="TaskStart_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Start Date</label>
                                <input type="date" id="TaskStart_date" name="TaskStart_date" value="<?php echo $flight['TaskStart'] ? date('Y-m-d', strtotime($flight['TaskStart'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="TaskStart_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Start Time (HHMM)</label>
                                <input type="text" id="TaskStart_time" name="TaskStart_time" value="<?php echo $flight['TaskStart'] ? date('Hi', strtotime($flight['TaskStart'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       pattern="[0-9]{4}" maxlength="4" placeholder="1125">
                                <input type="hidden" id="TaskStart" name="TaskStart" value="<?php echo $flight['TaskStart'] ? date('Y-m-d H:i:s', strtotime($flight['TaskStart'])) : ''; ?>">
                            </div>
                            <div>
                                <label for="TaskEnd_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task End Date</label>
                                <input type="date" id="TaskEnd_date" name="TaskEnd_date" value="<?php echo $flight['TaskEnd'] ? date('Y-m-d', strtotime($flight['TaskEnd'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="TaskEnd_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task End Time (HHMM)</label>
                                <input type="text" id="TaskEnd_time" name="TaskEnd_time" value="<?php echo $flight['TaskEnd'] ? date('Hi', strtotime($flight['TaskEnd'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       pattern="[0-9]{4}" maxlength="4" placeholder="1200">
                                <input type="hidden" id="TaskEnd" name="TaskEnd" value="<?php echo $flight['TaskEnd'] ? date('Y-m-d H:i:s', strtotime($flight['TaskEnd'])) : ''; ?>">
                            </div>
                            <div>
                                <label for="actual_out_utc_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Actual Out (UTC) Date</label>
                                <input type="date" id="actual_out_utc_date" name="actual_out_utc_date" value="<?php echo isset($flight['actual_out_utc']) && $flight['actual_out_utc'] ? date('Y-m-d', strtotime($flight['actual_out_utc'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="actual_out_utc_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Actual Out (UTC) Time (HHMM)</label>
                                <input type="text" id="actual_out_utc_time" name="actual_out_utc_time" value="<?php echo isset($flight['actual_out_utc']) && $flight['actual_out_utc'] ? date('Hi', strtotime($flight['actual_out_utc'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       pattern="[0-9]{4}" maxlength="4" placeholder="1119">
                                <input type="hidden" id="actual_out_utc" name="actual_out_utc" value="<?php echo isset($flight['actual_out_utc']) && $flight['actual_out_utc'] ? date('Y-m-d H:i:s', strtotime($flight['actual_out_utc'])) : ''; ?>">
                            </div>
                            <div>
                                <label for="actual_in_utc_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Actual In (UTC) Date</label>
                                <input type="date" id="actual_in_utc_date" name="actual_in_utc_date" value="<?php echo isset($flight['actual_in_utc']) && $flight['actual_in_utc'] ? date('Y-m-d', strtotime($flight['actual_in_utc'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="actual_in_utc_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Actual In (UTC) Time (HHMM)</label>
                                <input type="text" id="actual_in_utc_time" name="actual_in_utc_time" value="<?php echo isset($flight['actual_in_utc']) && $flight['actual_in_utc'] ? date('Hi', strtotime($flight['actual_in_utc'])) : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       pattern="[0-9]{4}" maxlength="4" placeholder="0017">
                                <input type="hidden" id="actual_in_utc" name="actual_in_utc" value="<?php echo isset($flight['actual_in_utc']) && $flight['actual_in_utc'] ? date('Y-m-d H:i:s', strtotime($flight['actual_in_utc'])) : ''; ?>">
                            </div>
                            <div>
                                <label for="ScheduledTaskStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scheduled Task Status</label>
                                <select id="ScheduledTaskStatus" name="ScheduledTaskStatus" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">--Select--</option>
                                    <option value="Boarding" <?php echo ($flight['ScheduledTaskStatus'] == 'Boarding') ? 'selected' : ''; ?>>Boarding</option>
                                    <option value="Cancelled" <?php echo ($flight['ScheduledTaskStatus'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="Complete" <?php echo ($flight['ScheduledTaskStatus'] == 'Complete') ? 'selected' : ''; ?>>Complete</option>
                                    <option value="Confirmed" <?php echo ($flight['ScheduledTaskStatus'] == 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="Delayed" <?php echo ($flight['ScheduledTaskStatus'] == 'Delayed') ? 'selected' : ''; ?>>Delayed</option>
                                    <option value="Diverted" <?php echo ($flight['ScheduledTaskStatus'] == 'Diverted') ? 'selected' : ''; ?>>Diverted</option>
                                    <option value="Gate Closed" <?php echo ($flight['ScheduledTaskStatus'] == 'Gate Closed') ? 'selected' : ''; ?>>Gate Closed</option>
                                    <option value="Landed" <?php echo ($flight['ScheduledTaskStatus'] == 'Landed') ? 'selected' : ''; ?>>Landed</option>
                                    <option value="Off Block" <?php echo ($flight['ScheduledTaskStatus'] == 'Off Block') ? 'selected' : ''; ?>>Off Block</option>
                                    <option value="On Block" <?php echo ($flight['ScheduledTaskStatus'] == 'On Block') ? 'selected' : ''; ?>>On Block</option>
                                    <option value="Pending" <?php echo ($flight['ScheduledTaskStatus'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Ready" <?php echo ($flight['ScheduledTaskStatus'] == 'Ready') ? 'selected' : ''; ?>>Ready</option>
                                    <option value="Return to Ramp" <?php echo ($flight['ScheduledTaskStatus'] == 'Return to Ramp') ? 'selected' : ''; ?>>Return to Ramp</option>
                                    <option value="Start" <?php echo ($flight['ScheduledTaskStatus'] == 'Start') ? 'selected' : ''; ?>>Start</option>
                                    <option value="Takeoff" <?php echo ($flight['ScheduledTaskStatus'] == 'Takeoff') ? 'selected' : ''; ?>>Takeoff</option>
                                    <option value="Taxi" <?php echo ($flight['ScheduledTaskStatus'] == 'Taxi') ? 'selected' : ''; ?>>Taxi</option>
                                </select>
                            </div>
                            
                            <!-- Divert Station Field (only shown when status is Diverted) -->
                            <div id="divert_station_container" class="relative" style="display: <?php echo ($flight['ScheduledTaskStatus'] == 'Diverted') ? 'block' : 'none'; ?>;">
                                <label for="divert_station" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-plane-arrival mr-1"></i>Divert Station <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           id="divert_station" 
                                           name="divert_station" 
                                           value="<?php echo htmlspecialchars($flight['divert_station'] ?? ''); ?>"
                                           placeholder="Search for station (IATA code or name)..."
                                           autocomplete="off"
                                           <?php echo ($flight['ScheduledTaskStatus'] == 'Diverted') ? 'required' : ''; ?>
                                           class="w-full px-3 py-2 pl-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                </div>
                                <div id="divert_station_dropdown" class="hidden absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                    <!-- Search results will be populated here -->
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-info-circle mr-1"></i>Select the station where the flight was diverted to
                                    <span id="divert_station_hint" class="ml-2 text-blue-600 dark:text-blue-400 font-medium"><?php echo !empty($divertStationName) ? 'Selected: ' . htmlspecialchars($divertStationName) : ''; ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <label for="TaskDescriptionHTML" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Description</label>
                            <div id="TaskDescriptionHTML" name="TaskDescriptionHTML" contenteditable="true" 
                                 class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white min-h-[100px]"
                                 style="min-height: 100px;"><?php echo $flight['TaskDescriptionHTML'] ?? ''; ?></div>
                            <input type="hidden" id="TaskDescriptionHTML_hidden" name="TaskDescriptionHTML" value="<?php echo htmlspecialchars($flight['TaskDescriptionHTML'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Crew Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Crew Information</h2>
                        <div class="space-y-6">
                            <!-- Airmastro Crew Assignment -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Raimon Crew Scheduling</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php
                                    // Define crew positions and their labels (Crew1-Crew10)
                                    $crewPositions = [
                                        'Crew1' => 'Crew 1 (Left Seat Pilot)',
                                        'Crew2' => 'Crew 2 (Right Seat Pilot)', 
                                        'Crew3' => 'Crew 3 (Co-Pilot)',
                                        'Crew4' => 'Crew 4 (Deadhead)',
                                        'Crew5' => 'Crew 5 (Senior Cabin Crew)',
                                        'Crew6' => 'Crew 6 (Cabin Crew)',
                                        'Crew7' => 'Crew 7',
                                        'Crew8' => 'Crew 8',
                                        'Crew9' => 'Crew 9',
                                        'Crew10' => 'Crew 10'
                                    ];
                                    
                                    foreach ($crewPositions as $field => $label):
                                        $userId = $flight[$field] ?? null;
                                        $roleField = $field . '_role';
                                        $role = $flight[$roleField] ?? '';
                                        
                                        if ($userId && $userId > 0):
                                            $user = getUserById($userId);
                                            $userName = $user ? ($user['first_name'] . ' ' . $user['last_name']) : 'Unknown User';
                                        else:
                                            $userName = 'Not Assigned';
                                        endif;
                                        
                                        // Skip displaying crew card if not assigned
                                        if ($userName !== 'Not Assigned'):
                                    ?>
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                            <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1"><?php echo htmlspecialchars($label); ?></div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($userName); ?></span>
                                                <?php if (!empty($role)): ?>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">(<?php echo htmlspecialchars($role); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; ?>
                                </div>
                            </div>
                            
                            
                            <!-- All Crew (Legacy Data) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">َAirmaestro Data (Legacy Data)</label>
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $allCrewNames = parseCrewNames($flight['AllCrew'] ?? '');
                                    if (empty($allCrewNames) || (count($allCrewNames) == 1 && empty(trim($allCrewNames[0])))):
                                    ?>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">No crew members assigned</span>
                                    <?php else: ?>
                                        <?php foreach ($allCrewNames as $crewName): 
                                            if (!empty(trim($crewName))):
                                        ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <i class="fas fa-users mr-2"></i>
                                                <?php echo htmlspecialchars(trim($crewName)); ?>
                                            </span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                    endif;
                                    ?>
                                </div>
                                
                                <!-- Hidden input for form submission -->
                                <input type="hidden" id="AllCrew" name="AllCrew" value="<?php echo safeOutput($flight['AllCrew']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Passenger Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="adult" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Adults</label>
                                <input type="number" step="1" min="0" id="adult" name="adult" value="<?php echo intval($flight['adult']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="child" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Children</label>
                                <input type="number" step="1" min="0" id="child" name="child" value="<?php echo intval($flight['child']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="infant" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Infants</label>
                                <input type="number" step="1" min="0" id="infant" name="infant" value="<?php echo intval($flight['infant']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="total_pax" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Total PAX</label>
                                <input type="text" id="total_pax" name="total_pax" value="<?php echo safeOutput($flight['total_pax']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Flight Times -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Times</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="boarding" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <span class="text-red-500">*</span> Boarding
                                </label>
                                <input type="text" id="boarding" name="boarding" value="<?php echo safeOutput($flight['boarding']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input"
                                       placeholder="HHMM"
                                       oninput="validateFlightTimeInput('boarding', 'gate_closed')">
                            </div>
                            <div>
                                <label for="gate_closed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Gate Closed
                                </label>
                                <input type="text" id="gate_closed" name="gate_closed" value="<?php echo safeOutput($flight['gate_closed']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['boarding']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('gate_closed', 'ready')">
                            </div>
                            <div>
                                <label for="ready" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ready</label>
                                <input type="text" id="ready" name="ready" value="<?php echo safeOutput($flight['ready']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['gate_closed']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('ready', 'start')">
                            </div>
                            <div>
                                <label for="start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start</label>
                                <input type="text" id="start" name="start" value="<?php echo safeOutput($flight['start']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['ready']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('start', 'off_block')">
                            </div>
                            <div>
                                <label for="off_block" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Off Block</label>
                                <input type="text" id="off_block" name="off_block" value="<?php echo safeOutput($flight['off_block']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['start']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('off_block', 'taxi')">
                            </div>
                            <div>
                                <label for="taxi" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Taxi</label>
                                <input type="text" id="taxi" name="taxi" value="<?php echo safeOutput($flight['taxi']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['off_block']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('taxi', 'takeoff')">
                            </div>
                            <div>
                                <label for="return_to_ramp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Return to Ramp</label>
                                <input type="text" id="return_to_ramp" name="return_to_ramp" value="<?php echo safeOutput($flight['return_to_ramp'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input"
                                       placeholder="HHMM">
                            </div>
                            <div>
                                <label for="takeoff" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Takeoff</label>
                                <input type="text" id="takeoff" name="takeoff" value="<?php echo safeOutput($flight['takeoff']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['taxi']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('takeoff', 'landed')">
                            </div>
                            <div>
                                <label for="landed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Landed</label>
                                <input type="text" id="landed" name="landed" value="<?php echo safeOutput($flight['landed']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['takeoff']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('landed', 'on_block')">
                            </div>
                            <div>
                                <label for="on_block" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">On Block</label>
                                <input type="text" id="on_block" name="on_block" value="<?php echo safeOutput($flight['on_block']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white flight-time-input opacity-50 cursor-not-allowed"
                                       placeholder="HHMM"
                                       <?php echo empty($flight['landed']) ? 'disabled' : ''; ?>
                                       oninput="validateFlightTimeInput('on_block', '')">
                            </div>
                        </div>
                    </div>

                    <!-- Weight and Fuel -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Weight and Fuel</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="pcs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">PCS</label>
                                <input type="number" step="1" min="0" id="pcs" name="pcs" value="<?php echo intval($flight['pcs']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Weight (kg)</label>
                                <input type="number" step="1" min="0" id="weight" name="weight" value="<?php echo intval($flight['weight']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="uplift_fuel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Uplift Fuel (L)</label>
                                <input type="number" step="0.01" id="uplift_fuel" name="uplift_fuel" value="<?php echo safeOutput($flight['uplift_fuel']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       oninput="calculateUpliftLbs()">
                            </div>
                            <div>
                                <label for="uplft_lbs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Uplift (lbs)</label>
                                <input type="text" id="uplft_lbs" name="uplft_lbs" value="<?php echo safeOutput($flight['uplft_lbs']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400"
                                       readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Delay and Diversion Codes -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Delay and Diversion Codes</h2>
                        
                        <!-- Row 1 -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                            <div class="md:col-span-4">
                                <label for="delay_diversion_codes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delay/Diversion Codes 1</label>
                                <select id="delay_diversion_codes" name="delay_diversion_codes" onchange="updateDV93Description(1, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Code --</option>
                                    <?php 
                                    $delayCodes = json_decode(file_get_contents('delay.json'), true);
                                    foreach ($delayCodes as $code): 
                                        $isSelected = ($flight['delay_diversion_codes'] == $code['code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($code['code']); ?>" 
                                                data-description="<?php echo htmlspecialchars($code['description']); ?>"
                                                data-sub-codes="<?php echo isset($code['sub_codes']) ? htmlspecialchars(json_encode($code['sub_codes'])) : ''; ?>"
                                                <?php echo $isSelected; ?>>
                                            <?php echo htmlspecialchars($code['code'] . ' - ' . $code['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2" id="sub_code_1_container" style="display: none;">
                                <label for="delay_diversion_sub_codes_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sub Code</label>
                                <select id="delay_diversion_sub_codes_1" name="delay_diversion_sub_codes_1" onchange="updateSubCodeDescription(1, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Sub Code --</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="minutes_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minutes 1</label>
                                <input type="text" id="minutes_1" name="minutes_1" value="<?php echo safeOutput($flight['minutes_1']); ?>" maxlength="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="md:col-span-4">
                                <label for="dv93_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">DV93 1</label>
                                <input type="text" id="dv93_1" name="dv93_1" value="<?php echo safeOutput($flight['dv93_1']); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                            <div class="md:col-span-6" id="remark_1_container" style="display: none;">
                                <label for="remark_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remark (for 99)</label>
                                <textarea id="remark_1" name="remark_1" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($flight['remark_1'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Row 2 -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                            <div class="md:col-span-4">
                                <label for="delay_diversion_codes_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delay/Diversion Codes 2</label>
                                <select id="delay_diversion_codes_2" name="delay_diversion_codes_2" onchange="updateDV93Description(2, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Code --</option>
                                    <?php 
                                    foreach ($delayCodes as $code): 
                                        $isSelected = ($flight['delay_diversion_codes_2'] == $code['code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($code['code']); ?>" 
                                                data-description="<?php echo htmlspecialchars($code['description']); ?>"
                                                data-sub-codes="<?php echo isset($code['sub_codes']) ? htmlspecialchars(json_encode($code['sub_codes'])) : ''; ?>"
                                                <?php echo $isSelected; ?>>
                                            <?php echo htmlspecialchars($code['code'] . ' - ' . $code['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2" id="sub_code_2_container" style="display: none;">
                                <label for="delay_diversion_sub_codes_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sub Code</label>
                                <select id="delay_diversion_sub_codes_2" name="delay_diversion_sub_codes_2" onchange="updateSubCodeDescription(2, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Sub Code --</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="minutes_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minutes 2</label>
                                <input type="text" id="minutes_2" name="minutes_2" value="<?php echo safeOutput($flight['minutes_2']); ?>" maxlength="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="md:col-span-4">
                                <label for="dv93_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">DV93 2</label>
                                <input type="text" id="dv93_2" name="dv93_2" value="<?php echo safeOutput($flight['dv93_2']); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                            <div class="md:col-span-6" id="remark_2_container" style="display: none;">
                                <label for="remark_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remark (for 99)</label>
                                <textarea id="remark_2" name="remark_2" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($flight['remark_2'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Row 3 -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                            <div class="md:col-span-4">
                                <label for="delay_diversion_codes_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delay/Diversion Codes 3</label>
                                <select id="delay_diversion_codes_3" name="delay_diversion_codes_3" onchange="updateDV93Description(3, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Code --</option>
                                    <?php 
                                    foreach ($delayCodes as $code): 
                                        $isSelected = ($flight['delay_diversion_codes_3'] == $code['code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($code['code']); ?>" 
                                                data-description="<?php echo htmlspecialchars($code['description']); ?>"
                                                data-sub-codes="<?php echo isset($code['sub_codes']) ? htmlspecialchars(json_encode($code['sub_codes'])) : ''; ?>"
                                                <?php echo $isSelected; ?>>
                                            <?php echo htmlspecialchars($code['code'] . ' - ' . $code['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2" id="sub_code_3_container" style="display: none;">
                                <label for="delay_diversion_sub_codes_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sub Code</label>
                                <select id="delay_diversion_sub_codes_3" name="delay_diversion_sub_codes_3" onchange="updateSubCodeDescription(3, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Sub Code --</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="minutes_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minutes 3</label>
                                <input type="text" id="minutes_3" name="minutes_3" value="<?php echo safeOutput($flight['minutes_3']); ?>" maxlength="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="md:col-span-4">
                                <label for="dv93_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">DV93 3</label>
                                <input type="text" id="dv93_3" name="dv93_3" value="<?php echo safeOutput($flight['dv93_3']); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                            <div class="md:col-span-6" id="remark_3_container" style="display: none;">
                                <label for="remark_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remark (for 99)</label>
                                <textarea id="remark_3" name="remark_3" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($flight['remark_3'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Row 4 -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                            <div class="md:col-span-4">
                                <label for="delay_diversion_codes_4" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delay/Diversion Codes 4</label>
                                <select id="delay_diversion_codes_4" name="delay_diversion_codes_4" onchange="updateDV93Description(4, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Code --</option>
                                    <?php 
                                    foreach ($delayCodes as $code): 
                                        $isSelected = ($flight['delay_diversion_codes_4'] == $code['code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($code['code']); ?>" 
                                                data-description="<?php echo htmlspecialchars($code['description']); ?>"
                                                data-sub-codes="<?php echo isset($code['sub_codes']) ? htmlspecialchars(json_encode($code['sub_codes'])) : ''; ?>"
                                                <?php echo $isSelected; ?>>
                                            <?php echo htmlspecialchars($code['code'] . ' - ' . $code['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2" id="sub_code_4_container" style="display: none;">
                                <label for="delay_diversion_sub_codes_4" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sub Code</label>
                                <select id="delay_diversion_sub_codes_4" name="delay_diversion_sub_codes_4" onchange="updateSubCodeDescription(4, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Sub Code --</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="minutes_4" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minutes 4</label>
                                <input type="text" id="minutes_4" name="minutes_4" value="<?php echo safeOutput($flight['minutes_4']); ?>" maxlength="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="md:col-span-4">
                                <label for="dv93_4" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">DV93 4</label>
                                <input type="text" id="dv93_4" name="dv93_4" value="<?php echo safeOutput($flight['dv93_4']); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                            <div class="md:col-span-6" id="remark_4_container" style="display: none;">
                                <label for="remark_4" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remark (for 99)</label>
                                <textarea id="remark_4" name="remark_4" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($flight['remark_4'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Row 5 -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                            <div class="md:col-span-4">
                                <label for="delay_diversion_codes_5" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delay/Diversion Codes 5</label>
                                <select id="delay_diversion_codes_5" name="delay_diversion_codes_5" onchange="updateDV93Description(5, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Code --</option>
                                    <?php 
                                    foreach ($delayCodes as $code): 
                                        $isSelected = ($flight['delay_diversion_codes_5'] == $code['code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($code['code']); ?>" 
                                                data-description="<?php echo htmlspecialchars($code['description']); ?>"
                                                data-sub-codes="<?php echo isset($code['sub_codes']) ? htmlspecialchars(json_encode($code['sub_codes'])) : ''; ?>"
                                                <?php echo $isSelected; ?>>
                                            <?php echo htmlspecialchars($code['code'] . ' - ' . $code['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2" id="sub_code_5_container" style="display: none;">
                                <label for="delay_diversion_sub_codes_5" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sub Code</label>
                                <select id="delay_diversion_sub_codes_5" name="delay_diversion_sub_codes_5" onchange="updateSubCodeDescription(5, this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Sub Code --</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="minutes_5" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minutes 5</label>
                                <input type="text" id="minutes_5" name="minutes_5" value="<?php echo safeOutput($flight['minutes_5']); ?>" maxlength="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="md:col-span-4">
                                <label for="dv93_5" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">DV93 5</label>
                                <input type="text" id="dv93_5" name="dv93_5" value="<?php echo safeOutput($flight['dv93_5']); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                            <div class="md:col-span-6" id="remark_5_container" style="display: none;">
                                <label for="remark_5" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remark (for 99)</label>
                                <textarea id="remark_5" name="remark_5" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($flight['remark_5'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Status</h2>
                        <div class="flex items-center">
                            <input type="checkbox" id="FlightLocked" name="FlightLocked" value="1" <?php echo $flight['FlightLocked'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="FlightLocked" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                Flight Locked (Cannot be edited)
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-3">
                        <a href="index.php" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Flight
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Other Crew Selection Modal -->
    <div id="otherCrewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Select Other Crew</h3>
                    <button onclick="closeOtherCrewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Search Input -->
                <div class="mb-4">
                    <input type="text" id="otherCrewSearch" placeholder="Search crew members..." 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                           onkeyup="filterOtherCrew()">
                </div>
                
                <div id="otherCrewList" class="space-y-2 max-h-60 overflow-y-auto">
                    <?php 
                    $availableCrew = getAllCrewMembers();
                    $currentOtherCrewNames = parseCrewNames($flight['OtherCrew'] ?? '');
                    foreach ($availableCrew as $crew): 
                        $crewFullName = $crew['first_name'] . ' ' . $crew['last_name'];
                        if (!in_array($crewFullName, $currentOtherCrewNames)):
                    ?>
                        <button type="button" onclick="addOtherCrew(<?php echo $crew['id']; ?>, '<?php echo htmlspecialchars($crewFullName); ?>')" 
                                class="crew-item w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200"
                                data-name="<?php echo htmlspecialchars(strtolower($crew['first_name'] . ' ' . $crew['last_name'] . ' ' . $crew['position'] . ' ' . $crew['role'])); ?>">
                            <div class="font-medium"><?php echo htmlspecialchars($crewFullName); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($crew['position'] . ' - ' . ucfirst($crew['role'])); ?></div>
                        </button>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button onclick="closeOtherCrewModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Crew Selection Modal -->
    <div id="allCrewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Select All Crew</h3>
                    <button onclick="closeAllCrewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Search Input -->
                <div class="mb-4">
                    <input type="text" id="allCrewSearch" placeholder="Search crew members..." 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                           onkeyup="filterAllCrew()">
                </div>
                
                <div id="allCrewList" class="space-y-2 max-h-60 overflow-y-auto">
                    <?php 
                    $currentAllCrewNames = parseCrewNames($flight['AllCrew'] ?? '');
                    foreach ($availableCrew as $crew): 
                        $crewFullName = $crew['first_name'] . ' ' . $crew['last_name'];
                        if (!in_array($crewFullName, $currentAllCrewNames)):
                    ?>
                        <button type="button" onclick="addAllCrew(<?php echo $crew['id']; ?>, '<?php echo htmlspecialchars($crewFullName); ?>')" 
                                class="crew-item w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200"
                                data-name="<?php echo htmlspecialchars(strtolower($crew['first_name'] . ' ' . $crew['last_name'] . ' ' . $crew['position'] . ' ' . $crew['role'])); ?>">
                            <div class="font-medium"><?php echo htmlspecialchars($crewFullName); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($crew['position'] . ' - ' . ucfirst($crew['role'])); ?></div>
                        </button>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button onclick="closeAllCrewModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // TaskDescriptionHTML contenteditable management
        document.addEventListener('DOMContentLoaded', function() {
            const taskDescriptionDiv = document.getElementById('TaskDescriptionHTML');
            const taskDescriptionHidden = document.getElementById('TaskDescriptionHTML_hidden');
            
            if (taskDescriptionDiv && taskDescriptionHidden) {
                // Update hidden input when contenteditable div changes
                taskDescriptionDiv.addEventListener('input', function() {
                    taskDescriptionHidden.value = this.innerHTML;
                });
                
                // Update hidden input when contenteditable div loses focus
                taskDescriptionDiv.addEventListener('blur', function() {
                    taskDescriptionHidden.value = this.innerHTML;
                });
                
                // Initialize hidden input with current content
                taskDescriptionHidden.value = taskDescriptionDiv.innerHTML;
            }
        });
        
        // Crew data for JavaScript
        const crewData = <?php echo json_encode($availableCrew); ?>;
        
        // Other Crew Management
        let otherCrewNames = <?php echo json_encode($otherCrewNames); ?>;
        let allCrewNames = <?php echo json_encode($allCrewNames); ?>;
        

        // Route Selection Management
        function updateFlightHoursFromRoute() {
            const routeSelect = document.getElementById('Route');
            const flightHoursInput = document.getElementById('FlightHours');
            
            if (!routeSelect || !flightHoursInput) {
                return;
            }
            
            const selectedOption = routeSelect.options[routeSelect.selectedIndex];
            
            if (selectedOption.value && selectedOption.getAttribute('data-flight-time')) {
                const flightTimeMinutes = parseFloat(selectedOption.getAttribute('data-flight-time'));
                if (!isNaN(flightTimeMinutes) && flightTimeMinutes > 0) {
                    // Convert minutes to hours (with 2 decimal places)
                    const flightHours = (flightTimeMinutes / 60).toFixed(2);
                    flightHoursInput.value = flightHours;
                    console.log(`Route: ${selectedOption.value}, Flight Time: ${flightTimeMinutes} minutes, Flight Hours: ${flightHours}`);
                }
            } else {
                // Clear flight hours if no route selected or no flight time data
                flightHoursInput.value = '';
                console.log('No route selected or no flight time data');
            }
        }

        // Task Name Management
        function updateTaskNameFromFlightNo() {
            const flightNoInput = document.getElementById('FlightNo');
            const taskNameInput = document.getElementById('TaskName');
            
            if (!flightNoInput || !taskNameInput) {
                return;
            }
            
            const flightNo = flightNoInput.value.trim();
            const currentTaskName = taskNameInput.value.trim();
            
            // Only auto-fill TaskName from FlightNo if TaskName is empty
            if (flightNo && !currentTaskName) {
                // Set Task Name to Flight Number only if TaskName is empty
                taskNameInput.value = flightNo;
                console.log(`Task Name auto-filled to: ${flightNo}`);
            } else if (!flightNo && !currentTaskName) {
                // Clear Task Name if both Flight Number and TaskName are empty
                taskNameInput.value = '';
                console.log('Task Name cleared - no Flight Number');
            } else {
                // If TaskName has existing data, preserve it
                console.log(`Task Name preserved: ${currentTaskName}`);
            }
        }

        // Flight ID Management
        function updateFlightID() {
            const flightIdInput = document.getElementById('FlightID');
            
            if (!flightIdInput) {
                return;
            }
            
            // Get next flight ID from server
            fetch('api/get_next_flight_id.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.next_flight_id) {
                        flightIdInput.value = data.next_flight_id;
                        console.log(`Flight ID updated to: ${data.next_flight_id}`);
                    } else {
                        console.error('Failed to get next flight ID:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error fetching next flight ID:', error);
                });
        }

        // Sequential Validation Functions
        function isRowComplete(rowNumber) {
            const selectElement = document.getElementById(`delay_diversion_codes${rowNumber > 1 ? '_' + rowNumber : ''}`);
            const minutesInput = document.getElementById(`minutes_${rowNumber}`);
            
            if (!selectElement || !minutesInput) {
                return false;
            }
            
            const hasCode = selectElement.value && selectElement.value !== '';
            const hasMinutes = minutesInput.value && minutesInput.value.trim() !== '';
            
            return hasCode && hasMinutes;
        }
        
        function updateRowStates() {
            for (let i = 1; i <= 5; i++) {
                const selectElement = document.getElementById(`delay_diversion_codes${i > 1 ? '_' + i : ''}`);
                const minutesInput = document.getElementById(`minutes_${i}`);
                const rowContainer = selectElement.closest('.grid');
                
                if (i === 1) {
                    // First row is always enabled
                    selectElement.disabled = false;
                    minutesInput.disabled = false;
                    if (rowContainer) {
                        rowContainer.classList.remove('delay-row-disabled');
                        rowContainer.classList.add('delay-row-enabled');
                    }
                } else {
                    // Check if previous row is complete
                    const previousRowComplete = isRowComplete(i - 1);
                    
                    if (previousRowComplete) {
                        selectElement.disabled = false;
                        minutesInput.disabled = false;
                        if (rowContainer) {
                            rowContainer.classList.remove('delay-row-disabled');
                            rowContainer.classList.add('delay-row-enabled');
                        }
                    } else {
                        selectElement.disabled = true;
                        minutesInput.disabled = true;
                        if (rowContainer) {
                            rowContainer.classList.remove('delay-row-enabled');
                            rowContainer.classList.add('delay-row-disabled');
                        }
                    }
                }
            }
        }
        
        function validateSequentialInput(rowNumber) {
            // Check if current row is complete
            if (isRowComplete(rowNumber)) {
                // Enable next row if it exists
                updateRowStates();
            } else {
                // Disable all subsequent rows
                for (let i = rowNumber + 1; i <= 5; i++) {
                    const selectElement = document.getElementById(`delay_diversion_codes${i > 1 ? '_' + i : ''}`);
                    const minutesInput = document.getElementById(`minutes_${i}`);
                    const rowContainer = selectElement?.closest('.grid');
                    
                    if (selectElement) selectElement.disabled = true;
                    if (minutesInput) minutesInput.disabled = true;
                    if (rowContainer) {
                        rowContainer.classList.remove('delay-row-enabled');
                        rowContainer.classList.add('delay-row-disabled');
                    }
                }
            }
        }

        // DV93 Description Management
        function updateDV93Description(rowNumber, selectedValue) {
            const selectElement = document.getElementById(`delay_diversion_codes${rowNumber > 1 ? '_' + rowNumber : ''}`);
            const dv93Input = document.getElementById(`dv93_${rowNumber}`);
            const subCodeContainer = document.getElementById(`sub_code_${rowNumber}_container`);
            const subCodeSelect = document.getElementById(`delay_diversion_sub_codes_${rowNumber}`);
            const remarkContainer = document.getElementById(`remark_${rowNumber}_container`);
            
            if (!selectElement || !dv93Input) {
                return;
            }
            
            if (selectedValue) {
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const description = selectedOption.getAttribute('data-description');
                const subCodes = selectedOption.getAttribute('data-sub-codes');
                
                if (description) {
                    dv93Input.value = description;
                    console.log(`DV93 ${rowNumber} updated to: ${description}`);
                }
                
                // Handle sub-codes for code 93
                if (subCodes && selectedValue === '93 (RA)') {
                    try {
                        const subCodesArray = JSON.parse(subCodes);
                        subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                        
                        subCodesArray.forEach(subCode => {
                            const option = document.createElement('option');
                            option.value = subCode.code;
                            option.textContent = `${subCode.code} - ${subCode.description}`;
                            option.setAttribute('data-description', subCode.description);
                            subCodeSelect.appendChild(option);
                        });
                        
                        subCodeContainer.style.display = 'block';
                    } catch (e) {
                        console.error('Error parsing sub-codes:', e);
                        subCodeContainer.style.display = 'none';
                    }
                } else {
                    subCodeContainer.style.display = 'none';
                    subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                }

                // Handle remarks for code 99
                if (remarkContainer) {
                    if (selectedValue === '99 (MX)') {
                        remarkContainer.style.display = 'block';
                    } else {
                        remarkContainer.style.display = 'none';
                    }
                }
                
                // Validate sequential input
                validateSequentialInput(rowNumber);
            } else {
                dv93Input.value = '';
                subCodeContainer.style.display = 'none';
                subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                if (remarkContainer) {
                    remarkContainer.style.display = 'none';
                }
                console.log(`DV93 ${rowNumber} cleared`);
                
                // Validate sequential input
                validateSequentialInput(rowNumber);
            }
        }

        // Sub Code Description Management
        function updateSubCodeDescription(rowNumber, selectedValue) {
            const subCodeSelect = document.getElementById(`delay_diversion_sub_codes_${rowNumber}`);
            const dv93Input = document.getElementById(`dv93_${rowNumber}`);
            
            if (!subCodeSelect || !dv93Input) {
                return;
            }
            
            if (selectedValue) {
                const selectedOption = subCodeSelect.options[subCodeSelect.selectedIndex];
                const description = selectedOption.getAttribute('data-description');
                
                if (description) {
                    // Update DV93 with sub-code description
                    dv93Input.value = description;
                    console.log(`Sub-code ${rowNumber} updated to: ${description}`);
                }
            } else {
                // Reset to main code description
                const mainSelect = document.getElementById(`delay_diversion_codes${rowNumber > 1 ? '_' + rowNumber : ''}`);
                if (mainSelect && mainSelect.value) {
                    const mainOption = mainSelect.options[mainSelect.selectedIndex];
                    const mainDescription = mainOption.getAttribute('data-description');
                    if (mainDescription) {
                        dv93Input.value = mainDescription;
                    }
                }
                console.log(`Sub-code ${rowNumber} cleared`);
            }
        }

        function getCrewName(crewId) {
            const crew = crewData.find(c => c.id == crewId);
            return crew ? crew.first_name + ' ' + crew.last_name : 'Unknown';
        }

        function openOtherCrewModal() {
            document.getElementById('otherCrewModal').classList.remove('hidden');
            // Clear search when opening modal
            document.getElementById('otherCrewSearch').value = '';
            filterOtherCrew();
        }

        function closeOtherCrewModal() {
            document.getElementById('otherCrewModal').classList.add('hidden');
        }

        function filterOtherCrew() {
            const searchTerm = document.getElementById('otherCrewSearch').value.toLowerCase();
            const crewItems = document.querySelectorAll('#otherCrewList .crew-item');
            
            crewItems.forEach(item => {
                const crewName = item.getAttribute('data-name');
                if (crewName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function addOtherCrew(crewId, crewName) {
            if (!otherCrewNames.includes(crewName)) {
                otherCrewNames.push(crewName);
                updateOtherCrewDisplay();
                closeOtherCrewModal();
            }
        }

        function removeOtherCrewName(crewName) {
            otherCrewNames = otherCrewNames.filter(name => name !== crewName);
            updateOtherCrewDisplay();
        }

        function updateOtherCrewDisplay() {
            const badgesContainer = document.getElementById('otherCrewBadges');
            const hiddenInput = document.getElementById('OtherCrew');
            
            // Update hidden input
            hiddenInput.value = otherCrewNames.join(', ');
            
            // Update badges display
            badgesContainer.innerHTML = '';
            otherCrewNames.forEach(crewName => {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                badge.innerHTML = `${crewName} <button type="button" onclick="removeOtherCrewName('${crewName}')" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"><i class="fas fa-times text-xs"></i></button>`;
                badgesContainer.appendChild(badge);
            });
        }

        // All Crew Management
        function openAllCrewModal() {
            document.getElementById('allCrewModal').classList.remove('hidden');
            // Clear search when opening modal
            document.getElementById('allCrewSearch').value = '';
            filterAllCrew();
        }

        function closeAllCrewModal() {
            document.getElementById('allCrewModal').classList.add('hidden');
        }

        function filterAllCrew() {
            const searchTerm = document.getElementById('allCrewSearch').value.toLowerCase();
            const crewItems = document.querySelectorAll('#allCrewList .crew-item');
            
            crewItems.forEach(item => {
                const crewName = item.getAttribute('data-name');
                if (crewName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function addAllCrew(crewId, crewName) {
            if (!allCrewNames.includes(crewName)) {
                allCrewNames.push(crewName);
                updateAllCrewDisplay();
                closeAllCrewModal();
            }
        }

        function removeAllCrewName(crewName) {
            allCrewNames = allCrewNames.filter(name => name !== crewName);
            updateAllCrewDisplay();
        }

        function updateAllCrewDisplay() {
            const badgesContainer = document.getElementById('allCrewBadges');
            const hiddenInput = document.getElementById('AllCrew');
            
            // Update hidden input
            hiddenInput.value = allCrewNames.join(', ');
            
            // Update badges display
            badgesContainer.innerHTML = '';
            allCrewNames.forEach(crewName => {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                badge.innerHTML = `${crewName} <button type="button" onclick="removeAllCrewName('${crewName}')" class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200"><i class="fas fa-times text-xs"></i></button>`;
                badgesContainer.appendChild(badge);
            });
        }

        // Calculate Uplift (lbs) from Uplift Fuel (kg)
        function calculateUpliftLbs() {
            const upliftFuel = parseFloat(document.getElementById('uplift_fuel').value) || 0;
            // Convert liters to pounds: 1 L jet fuel ≈ 0.8 kg, 1 kg = 2.20462 lbs
            // So: 1 L = 0.8 × 2.20462 = 1.763696 lbs
            const upliftLbs = upliftFuel * 0.76 * 2.20462; // Convert L to lbs (via kg)
            document.getElementById('uplft_lbs').value = upliftLbs.toFixed(2);
        }

        // Calculate Total PAX from Adults, Children, and Infants
        function calculateTotalPax() {
            const adult = parseInt(document.getElementById('adult').value) || 0;
            const child = parseInt(document.getElementById('child').value) || 0;
            const infant = parseInt(document.getElementById('infant').value) || 0;
            const totalPax = adult + child + infant;
            document.getElementById('total_pax').value = totalPax;
        }

        // Initialize fields on page load
        // Flight Time Sequential Validation
        // Define the order of flight time fields
        const flightTimeOrder = ['boarding', 'gate_closed', 'ready', 'start', 'off_block', 'taxi', 'return_to_ramp', 'takeoff', 'landed', 'on_block'];
        
        function validateFlightTimeInput(currentFieldId, nextFieldId) {
            const currentField = document.getElementById(currentFieldId);
            const nextField = nextFieldId ? document.getElementById(nextFieldId) : null;
            
            if (!currentField) return;
            
            const currentValue = currentField.value.trim();
            
            // Enable/disable next field based on current field value
            if (nextField) {
                if (currentValue && currentValue.length > 0) {
                    // Enable next field if current has value
                    nextField.disabled = false;
                    nextField.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    // Disable next field if current is empty
                    nextField.disabled = true;
                    nextField.classList.add('opacity-50', 'cursor-not-allowed');
                    nextField.value = ''; // Clear value when disabled
                    
                    // Also disable all subsequent fields
                    disableSubsequentFields(currentFieldId);
                }
            }
        }
        
        function disableSubsequentFields(fieldId) {
            const currentIndex = flightTimeOrder.indexOf(fieldId);
            if (currentIndex === -1) return;
            
            // Disable all fields after the current one (except return_to_ramp which is optional)
            for (let i = currentIndex + 1; i < flightTimeOrder.length; i++) {
                const nextFieldId = flightTimeOrder[i];
                // Skip return_to_ramp - it's optional and doesn't block subsequent fields
                if (nextFieldId === 'return_to_ramp') {
                    continue;
                }
                
                const field = document.getElementById(nextFieldId);
                if (field) {
                    field.disabled = true;
                    field.classList.add('opacity-50', 'cursor-not-allowed');
                    if (!field.value || field.value.trim() === '') {
                        field.value = '';
                    }
                }
            }
        }
        
        function initializeFlightTimeValidation() {
            // Check each field and enable/disable subsequent fields accordingly
            for (let i = 0; i < flightTimeOrder.length; i++) {
                const fieldId = flightTimeOrder[i];
                const field = document.getElementById(fieldId);
                if (field) {
                    const value = field.value.trim();
                    
                    // Skip return_to_ramp in validation chain - it's optional
                    if (fieldId === 'return_to_ramp') {
                        continue;
                    }
                    
                    // Find the next required field (skip return_to_ramp)
                    let nextIndex = i + 1;
                    while (nextIndex < flightTimeOrder.length && flightTimeOrder[nextIndex] === 'return_to_ramp') {
                        nextIndex++;
                    }
                    
                    if (nextIndex < flightTimeOrder.length) {
                        const nextFieldId = flightTimeOrder[nextIndex];
                        const nextField = document.getElementById(nextFieldId);
                        if (nextField) {
                            if (!value || value.length === 0) {
                                // If current field is empty, disable all subsequent fields (except return_to_ramp)
                                disableSubsequentFields(fieldId);
                                break;
                            } else {
                                // If current field has value, enable next required field
                                nextField.disabled = false;
                                nextField.classList.remove('opacity-50', 'cursor-not-allowed');
                            }
                        }
                    }
                }
            }
        }

        // Function to combine date and time (HHMM) into datetime string
        function combineDateTime(dateFieldId, timeFieldId, hiddenFieldId) {
            const dateField = document.getElementById(dateFieldId);
            const timeField = document.getElementById(timeFieldId);
            const hiddenField = document.getElementById(hiddenFieldId);
            
            if (!dateField || !timeField || !hiddenField) return;
            
            const dateValue = dateField.value;
            const timeValue = timeField.value.trim();
            
            if (dateValue && timeValue && timeValue.length === 4) {
                // Extract hours and minutes from HHMM format
                const hours = timeValue.substring(0, 2);
                const minutes = timeValue.substring(2, 4);
                
                // Validate hours (00-23) and minutes (00-59)
                const hoursInt = parseInt(hours);
                const minutesInt = parseInt(minutes);
                
                if (hoursInt >= 0 && hoursInt <= 23 && minutesInt >= 0 && minutesInt <= 59) {
                    // Combine date and time into datetime format: YYYY-MM-DD HH:MM:SS
                    const datetime = dateValue + ' ' + hours + ':' + minutes + ':00';
                    hiddenField.value = datetime;
                } else {
                    hiddenField.value = '';
                }
            } else {
                hiddenField.value = '';
            }
        }
        
        // Initialize datetime field handlers
        function initializeDateTimeHandlers() {
            const datetimeFields = [
                { date: 'TaskStart_date', time: 'TaskStart_time', hidden: 'TaskStart' },
                { date: 'TaskEnd_date', time: 'TaskEnd_time', hidden: 'TaskEnd' },
                { date: 'actual_out_utc_date', time: 'actual_out_utc_time', hidden: 'actual_out_utc' },
                { date: 'actual_in_utc_date', time: 'actual_in_utc_time', hidden: 'actual_in_utc' }
            ];
            
            datetimeFields.forEach(field => {
                const dateField = document.getElementById(field.date);
                const timeField = document.getElementById(field.time);
                
                if (dateField && timeField) {
                    // Update hidden field when date or time changes
                    dateField.addEventListener('change', () => combineDateTime(field.date, field.time, field.hidden));
                    timeField.addEventListener('input', () => combineDateTime(field.date, field.time, field.hidden));
                    timeField.addEventListener('blur', () => combineDateTime(field.date, field.time, field.hidden));
                    
                    // Initialize on page load
                    combineDateTime(field.date, field.time, field.hidden);
                }
            });
        }
        
        // Prepare form submission - ensure all datetime fields are updated
        function prepareFormSubmission(event) {
            // Update all datetime fields before submission
            combineDateTime('TaskStart_date', 'TaskStart_time', 'TaskStart');
            combineDateTime('TaskEnd_date', 'TaskEnd_time', 'TaskEnd');
            combineDateTime('actual_out_utc_date', 'actual_out_utc_time', 'actual_out_utc');
            combineDateTime('actual_in_utc_date', 'actual_in_utc_time', 'actual_in_utc');
            return true; // Allow form submission
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateFlightHoursFromRoute();
            updateTaskNameFromFlightNo();
            updateFlightID();
            
            // Calculate initial values
            calculateUpliftLbs();
            calculateTotalPax();
            
            // Initialize datetime handlers
            initializeDateTimeHandlers();
            
            // Add event listeners for auto-calculation
            document.getElementById('adult').addEventListener('input', calculateTotalPax);
            document.getElementById('child').addEventListener('input', calculateTotalPax);
            document.getElementById('infant').addEventListener('input', calculateTotalPax);
            
            // Initialize DV93 descriptions for all rows
            for (let i = 1; i <= 5; i++) {
                const selectElement = document.getElementById(`delay_diversion_codes${i > 1 ? '_' + i : ''}`);
                if (selectElement && selectElement.value) {
                    updateDV93Description(i, selectElement.value);
                }
            }
            
            // Initialize sequential validation
            updateRowStates();
            
            // Add event listeners for minutes input validation
            for (let i = 1; i <= 5; i++) {
                const minutesInput = document.getElementById(`minutes_${i}`);
                if (minutesInput) {
                    minutesInput.addEventListener('input', function() {
                        validateSequentialInput(i);
                    });
                    minutesInput.addEventListener('blur', function() {
                        validateSequentialInput(i);
                    });
                }
            }
            
            // Initialize Flight Time sequential validation
            initializeFlightTimeValidation();
            
            // Divert Station functionality
            initializeDivertStation();
            
            // Load station name if divert_station has value on page load
            const divertInput = document.getElementById('divert_station');
            if (divertInput && divertInput.value) {
                fetch(`/api/get_station_by_iata.php?iata=${encodeURIComponent(divertInput.value)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.station) {
                            const hint = document.getElementById('divert_station_hint');
                            if (hint) {
                                hint.textContent = 'Selected: ' + data.station.station_name;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading station name:', error);
                    });
            }
        });
        
        // Divert Station Search and Display Logic
        function initializeDivertStation() {
            const statusSelect = document.getElementById('ScheduledTaskStatus');
            const divertContainer = document.getElementById('divert_station_container');
            const divertInput = document.getElementById('divert_station');
            const divertDropdown = document.getElementById('divert_station_dropdown');
            
            if (!statusSelect || !divertContainer || !divertInput) return;
            
            // Show/hide divert station field based on status
            statusSelect.addEventListener('change', function() {
                if (this.value === 'Diverted') {
                    divertContainer.style.display = 'block';
                    divertInput.required = true;
                } else {
                    divertContainer.style.display = 'none';
                    divertInput.required = false;
                    divertInput.value = '';
                    closeDivertDropdown();
                }
            });
            
            let searchTimeout;
            let allStations = [];
            
            // Search stations on input
            divertInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 1) {
                    closeDivertDropdown();
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchStations(query);
                }, 300);
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!divertContainer.contains(e.target)) {
                    closeDivertDropdown();
                }
            });
            
            // Handle keyboard navigation
            divertInput.addEventListener('keydown', function(e) {
                const items = divertDropdown.querySelectorAll('.station-item');
                const activeItem = divertDropdown.querySelector('.station-item.active');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (activeItem) {
                        activeItem.classList.remove('active');
                        const next = activeItem.nextElementSibling;
                        if (next) {
                            next.classList.add('active');
                            next.scrollIntoView({ block: 'nearest' });
                        }
                    } else if (items.length > 0) {
                        items[0].classList.add('active');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (activeItem) {
                        activeItem.classList.remove('active');
                        const prev = activeItem.previousElementSibling;
                        if (prev) {
                            prev.classList.add('active');
                            prev.scrollIntoView({ block: 'nearest' });
                        }
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeItem) {
                        const iataCode = activeItem.getAttribute('data-iata');
                        const stationName = activeItem.getAttribute('data-name');
                        if (iataCode && stationName) {
                            selectDivertStation(iataCode, stationName);
                        }
                    }
                } else if (e.key === 'Escape') {
                    closeDivertDropdown();
                }
            });
            
            function searchStations(query) {
                fetch(`/api/search_stations.php?search=${encodeURIComponent(query)}&limit=20`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.stations) {
                            allStations = data.stations;
                            displayStations(data.stations);
                        } else {
                            closeDivertDropdown();
                        }
                    })
                    .catch(error => {
                        console.error('Error searching stations:', error);
                        closeDivertDropdown();
                    });
            }
            
            function displayStations(stations) {
                if (stations.length === 0) {
                    divertDropdown.innerHTML = '<div class="p-3 text-sm text-gray-500 dark:text-gray-400 text-center">No stations found</div>';
                    divertDropdown.classList.remove('hidden');
                    return;
                }
                
                let html = '';
                stations.forEach((station, index) => {
                    html += `
                        <div class="station-item p-3 hover:bg-blue-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0 ${index === 0 ? 'active' : ''}" 
                             data-iata="${station.iata_code}"
                             data-name="${station.station_name}"
                             onclick="selectDivertStation('${station.iata_code}', '${station.station_name.replace("'", "\\'")}')">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-white">${station.station_name}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <span class="font-semibold">${station.iata_code}</span>
                                        ${station.icao_code ? `<span class="ml-2">${station.icao_code}</span>` : ''}
                                        ${station.country ? `<span class="ml-2 text-gray-400">• ${station.country}</span>` : ''}
                                    </div>
                                </div>
                                <i class="fas fa-check-circle text-blue-600 dark:text-blue-400 opacity-0 station-check"></i>
                            </div>
                        </div>
                    `;
                });
                
                divertDropdown.innerHTML = html;
                divertDropdown.classList.remove('hidden');
                
                // Position dropdown relative to input
                const inputRect = divertInput.getBoundingClientRect();
                const containerRect = divertContainer.getBoundingClientRect();
                divertDropdown.style.position = 'absolute';
                divertDropdown.style.top = (inputRect.bottom - containerRect.top + 4) + 'px';
                divertDropdown.style.left = '0';
                divertDropdown.style.width = inputRect.width + 'px';
            }
            
            function selectDivertStation(iataCode, stationName) {
                divertInput.value = iataCode;
                closeDivertDropdown();
                
                // Show selected station name as placeholder or hint
                const hint = document.getElementById('divert_station_hint');
                if (hint) {
                    hint.textContent = 'Selected: ' + stationName;
                }
            }
            
            function closeDivertDropdown() {
                divertDropdown.classList.add('hidden');
                const activeItem = divertDropdown.querySelector('.station-item.active');
                if (activeItem) {
                    activeItem.classList.remove('active');
                }
            }
            
            // Make functions globally accessible
            window.selectDivertStation = selectDivertStation;
            window.closeDivertDropdown = closeDivertDropdown;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const otherCrewModal = document.getElementById('otherCrewModal');
            const allCrewModal = document.getElementById('allCrewModal');
            
            if (event.target === otherCrewModal) {
                closeOtherCrewModal();
            } else if (event.target === allCrewModal) {
                closeAllCrewModal();
            }
        }

    </script>
</body>
</html>
