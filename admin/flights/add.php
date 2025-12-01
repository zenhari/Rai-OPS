<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/flights/add.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get aircraft data for dropdown
$aircraft = getAllAircraft();

// Get routes data for dropdown
$routes = getRoutes();

// Get stations data for dropdown
$stations = getStations();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_flight') {
        // Prepare data for insertion - handle all fields with proper NULL handling
        $data = [];
        
        // Define all possible fields from the flights table
        $allFields = [
            // Basic flight information
            'FltDate', 'AircraftID', 'CmdPilotID', 'Route', 'Rego', 'ACType', 'FlightNo',
            // Pilot information
            'LastName', 'FirstName',
            // Flight hours
            'FlightHours', 'CommandHours', 'FltHours',
            // Crew information
            'OtherCrew', 'AllCrew',
            // Task information
            'ScheduledTaskID', 'TaskName', 'TaskStart', 'TaskEnd', 'HomeBases', 'TaskDescriptionHTML',
            // Scheduled information
            'ScheduledRoute', 'ScheduledTaskType', 'ScheduledTaskStatus',
            // Passenger information
            'adult', 'child', 'infant', 'total_pax',
            // Flight times
            'boarding', 'gate_closed', 'landed', 'off_block', 'on_block', 'ready', 'start', 'takeoff', 'taxi',
            // Weight and fuel
            'pcs', 'weight', 'uplift_fuel', 'uplft_lbs',
            // Delay/Diversion fields
            'delay_diversion_codes', 'minutes_1', 'delay_diversion_codes_2', 'minutes_2', 
            'delay_diversion_codes_3', 'minutes_3', 'delay_diversion_codes_4', 'minutes_4', 
            'delay_diversion_codes_5', 'minutes_5', 'dv93_1', 'dv93_2', 'dv93_3', 'dv93_4', 'dv93_5',
            // Crew assignment fields (Crew1-Crew10)
            'Crew1', 'Crew1_role', 'Crew2', 'Crew2_role', 'Crew3', 'Crew3_role', 'Crew4', 'Crew4_role',
            'Crew5', 'Crew5_role', 'Crew6', 'Crew6_role', 'Crew7', 'Crew7_role', 'Crew8', 'Crew8_role',
            'Crew9', 'Crew9_role', 'Crew10', 'Crew10_role',
            // Actual times
            'actual_out_utc', 'actual_off_utc', 'actual_on_utc', 'actual_in_utc', 'block_time_min', 'air_time_min',
            // Other fields
            'TypeID', 'ScheduleTaskID', 'InsertedByID', 'calc_warn'
        ];
        
        // Process each field - send NULL for empty fields, actual value for filled fields
        foreach ($allFields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $data[$field] = $_POST[$field];
            } else {
                $data[$field] = null; // Explicitly set NULL for empty fields
            }
        }
        
        // Special handling for Route field - combine origin and destination
        if (isset($_POST['origin']) && isset($_POST['destination']) && 
            !empty($_POST['origin']) && !empty($_POST['destination'])) {
            $data['Route'] = $_POST['origin'] . '-' . $_POST['destination'];
        } else {
            $data['Route'] = null;
        }
        
        // Validation: Check for duplicate FlightNo on the same date
        if (!empty($data['FlightNo']) && !empty($data['FltDate'])) {
            $existingFlight = checkDuplicateFlightNumber($data['FlightNo'], $data['FltDate']);
            if ($existingFlight) {
                $error = "Flight number '{$data['FlightNo']}' already exists on {$data['FltDate']}. Please choose a different flight number or date.";
            }
        }
        
        // Special handling for boolean fields
        $data['FlightLocked'] = isset($_POST['FlightLocked']) ? 1 : 0;
        $data['calc_warn'] = isset($_POST['calc_warn']) ? 1 : 0;
        
        // Set timestamps
        $data['LastUpdated'] = date('Y-m-d H:i:s');
        
        // Only proceed with saving if no validation errors
        if (empty($error)) {
            // Set InsertedByID to current user
            $data['InsertedByID'] = $current_user['id'] ?? null;
            
            // Auto-assign next FlightID
            $data['FlightID'] = getNextFlightID();
            
            // Store filled fields for display after save
            $filledFields = [];
            foreach ($data as $field => $value) {
                if ($value !== null && $value !== '') {
                    $filledFields[$field] = $value;
                }
            }
            
            if (createFlight($data)) {
                $message = 'Flight added successfully.';
                // Store filled fields in session for display
                $_SESSION['last_added_flight_fields'] = $filledFields;
                // Stay on the same page - no redirect, no modal
            } else {
                // Get the latest flight error for detailed information
                $latestError = getLatestFlightError();
                if ($latestError) {
                    $error = 'Failed to add flight. Error: ' . $latestError['error_message'];
                    // Log additional details for debugging
                    error_log('Flight creation failed: ' . json_encode($latestError));
                } else {
                    $error = 'Failed to add flight. Please check the logs for more details.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Flight - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add New Flight</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create a new flight record</p>
                        </div>
                        <div class="flex space-x-3">
                            <!-- Dark Mode Toggle -->
                            <button onclick="toggleDarkMode()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i id="dark-mode-icon" class="fas fa-moon"></i>
                            </button>
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

                <?php if (isset($_SESSION['last_added_flight_fields']) && !empty($_SESSION['last_added_flight_fields'])): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800 dark:text-green-200 mb-2">Flight Added Successfully!</h3>
                                <p class="text-sm text-green-700 dark:text-green-300 mb-3">The following fields were saved:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                    <?php foreach ($_SESSION['last_added_flight_fields'] as $field => $value): ?>
                                        <div class="text-xs">
                                            <span class="font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($field); ?>:</span>
                                            <span class="text-green-700 dark:text-green-300"><?php echo htmlspecialchars($value); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                    // Clear the session data after displaying
                    unset($_SESSION['last_added_flight_fields']); 
                    ?>
                <?php endif; ?>

                <!-- Flight Add Form -->
                <form method="POST" class="space-y-8">
                    <input type="hidden" name="action" value="add_flight">
                    
                    <!-- Basic Flight Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Flight Information</h2>
                        
                        <!-- Row 1: Flight Date and Flight Number -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="FltDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Date *</label>
                                <input type="date" id="FltDate" name="FltDate" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="FlightNo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Number</label>
                                <input type="text" id="FlightNo" name="FlightNo" onchange="updateTaskNameFromFlightNo()"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Row 2: Origin, Destination, and Route Preview -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label for="origin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Departure</label>
                                <select id="origin" name="origin"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Origin --</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo htmlspecialchars($station['iata_code']); ?>">
                                            <?php echo htmlspecialchars($station['iata_code'] . ' - ' . $station['station_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="destination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destination</label>
                                <select id="destination" name="destination"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Destination --</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo htmlspecialchars($station['iata_code']); ?>">
                                            <?php echo htmlspecialchars($station['iata_code'] . ' - ' . $station['station_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Route Preview</label>
                                <div id="route-preview-container" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400 min-h-[42px] flex items-center">
                                    <span class="text-sm">Select origin and destination to see route preview</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Task Start and Task End -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Start</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="date" id="TaskStartDate" name="TaskStartDate"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <input type="text" id="TaskStartTime" name="TaskStartTime" placeholder="HHMM" maxlength="4" pattern="[0-9]{4}"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="formatTimeInput(this)">
                                </div>
                                <input type="hidden" id="TaskStart" name="TaskStart">
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Task End</label>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="MultiDay" name="MultiDay" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                               onchange="toggleMultiDay()">
                                        <label for="MultiDay" class="ml-2 block text-xs text-gray-600 dark:text-gray-400">
                                            MultiDay
                                        </label>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="date" id="TaskEndDate" name="TaskEndDate" disabled
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white bg-gray-50 dark:bg-gray-600">
                                    <input type="text" id="TaskEndTime" name="TaskEndTime" placeholder="HHMM" maxlength="4" pattern="[0-9]{4}"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="formatTimeInput(this)">
                                </div>
                                <input type="hidden" id="TaskEnd" name="TaskEnd">
                            </div>
                        </div>
                    </div>

                    <!-- Aircraft Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Aircraft Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="AircraftID" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Aircraft *</label>
                                <select id="AircraftID" name="AircraftID" required onchange="updateAircraftInfo()"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select an aircraft...</option>
                                    <?php foreach ($aircraft as $ac): ?>
                                        <option value="<?php echo $ac['id']; ?>" 
                                                data-registration="<?php echo htmlspecialchars($ac['registration']); ?>"
                                                data-aircraft-type="<?php echo htmlspecialchars($ac['aircraft_type']); ?>">
                                            <?php echo htmlspecialchars($ac['registration'] . ' - ' . $ac['manufacturer'] . ' ' . $ac['aircraft_type']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="Rego" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Registration</label>
                                <input type="text" id="Rego" name="Rego" readonly
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                            <div>
                                <label for="ACType" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Type</label>
                                <input type="text" id="ACType" name="ACType" readonly
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            </div>
                        </div>
                    </div>

                    <!-- Pilot Information - Hidden -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pilot Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="CmdPilotID" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pilot ID</label>
                                <input type="number" step="0.01" id="CmdPilotID" name="CmdPilotID"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="FirstName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Name</label>
                                <input type="text" id="FirstName" name="FirstName"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="LastName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Name</label>
                                <input type="text" id="LastName" name="LastName"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        
                        <!-- Crew Scheduling Notice -->
                        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-500 dark:text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        Crew Scheduling Required
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <p>After defining the flight, please proceed to <strong>Crew Scheduling</strong> to assign crew members to their respective positions (Crew1-Crew10).</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Flight Hours -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Hours</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="FlightHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Hours</label>
                                <input type="number" step="0.01" id="FlightHours" name="FlightHours"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="CommandHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Command Hours</label>
                                <input type="number" step="0.01" id="CommandHours" name="CommandHours"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="FltHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Hours (Task)</label>
                                <input type="number" step="0.01" id="FltHours" name="FltHours"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Crew Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Crew Information</h2>
                        <div class="space-y-6">
                            <!-- Other Crew -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Other Crew</label>
                                <div class="space-y-3">
                                    <!-- Selected Crew Badges -->
                                    <div id="otherCrewBadges" class="flex flex-wrap gap-2">
                                        <!-- Badges will be added here dynamically -->
                                    </div>
                                    
                                    <!-- Add Crew Button -->
                                    <button type="button" onclick="openOtherCrewModal()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Crew Member
                                    </button>
                                    
                                    <!-- Hidden input for form submission -->
                                    <input type="hidden" id="OtherCrew" name="OtherCrew" value="">
                                </div>
                            </div>
                            
                            <!-- All Crew -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">All Crew</label>
                                <div class="space-y-3">
                                    <!-- Selected Crew Badges -->
                                    <div id="allCrewBadges" class="flex flex-wrap gap-2">
                                        <!-- Badges will be added here dynamically -->
                                    </div>
                                    
                                    <!-- Add Crew Button -->
                                    <button type="button" onclick="openAllCrewModal()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Crew Member
                                    </button>
                                    
                                    <!-- Hidden input for form submission -->
                                    <input type="hidden" id="AllCrew" name="AllCrew" value="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Task Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Task Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="ScheduledTaskID" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scheduled Task ID</label>
                                <input type="number" step="0.01" id="ScheduledTaskID" name="ScheduledTaskID"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="TaskName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Name</label>
                                <input type="text" id="TaskName" name="TaskName"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Auto-filled from Flight Number</p>
                            </div>
                            <div>
                                <label for="HomeBases" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Home Bases</label>
                                <input type="text" id="HomeBases" name="HomeBases"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div class="mt-6">
                            <label for="TaskDescriptionHTML" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Description</label>
                            <textarea id="TaskDescriptionHTML" name="TaskDescriptionHTML"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                        </div>
                    </div>

                    <!-- Passenger Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Passenger Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="adult" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Adults</label>
                                <input type="number" step="0.01" id="adult" name="adult"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="child" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Children</label>
                                <input type="number" step="0.01" id="child" name="child"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="infant" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Infants</label>
                                <input type="number" step="0.01" id="infant" name="infant"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="total_pax" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Total PAX</label>
                                <input type="text" id="total_pax" name="total_pax"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Flight Times -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Times</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="boarding" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Boarding</label>
                                <input type="text" id="boarding" name="boarding"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="gate_closed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gate Closed</label>
                                <input type="text" id="gate_closed" name="gate_closed"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="landed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Landed</label>
                                <input type="text" id="landed" name="landed"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="off_block" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Off Block</label>
                                <input type="text" id="off_block" name="off_block"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="on_block" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">On Block</label>
                                <input type="text" id="on_block" name="on_block"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="ready" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ready</label>
                                <input type="text" id="ready" name="ready"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start</label>
                                <input type="text" id="start" name="start"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="takeoff" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Takeoff</label>
                                <input type="text" id="takeoff" name="takeoff"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="taxi" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Taxi</label>
                                <input type="text" id="taxi" name="taxi"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Weight and Fuel -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Weight and Fuel</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="pcs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">PCS</label>
                                <input type="number" step="0.01" id="pcs" name="pcs"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Weight (kg)</label>
                                <input type="number" step="0.01" id="weight" name="weight"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="uplift_fuel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Uplift Fuel (L)</label>
                                <input type="number" step="0.01" id="uplift_fuel" name="uplift_fuel"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="uplft_lbs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Uplift (lbs)</label>
                                <input type="text" id="uplft_lbs" name="uplft_lbs"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 hidden">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Status</h2>
                        <div class="flex items-center">
                            <input type="checkbox" id="FlightLocked" name="FlightLocked" value="1"
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
                            Add Flight
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
                    foreach ($availableCrew as $crew): 
                    ?>
                        <button type="button" onclick="addOtherCrew(<?php echo $crew['id']; ?>, '<?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?>')" 
                                class="crew-item w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200"
                                data-name="<?php echo htmlspecialchars(strtolower($crew['first_name'] . ' ' . $crew['last_name'] . ' ' . $crew['position'] . ' ' . $crew['role'])); ?>">
                            <div class="font-medium"><?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($crew['position'] . ' - ' . ucfirst($crew['role'])); ?></div>
                        </button>
                    <?php endforeach; ?>
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
                    foreach ($availableCrew as $crew): 
                    ?>
                        <button type="button" onclick="addAllCrew(<?php echo $crew['id']; ?>, '<?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?>')" 
                                class="crew-item w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200"
                                data-name="<?php echo htmlspecialchars(strtolower($crew['first_name'] . ' ' . $crew['last_name'] . ' ' . $crew['position'] . ' ' . $crew['role'])); ?>">
                            <div class="font-medium"><?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($crew['position'] . ' - ' . ucfirst($crew['role'])); ?></div>
                        </button>
                    <?php endforeach; ?>
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
        // Time input formatting function
        function formatTimeInput(input) {
            // Remove any non-numeric characters
            let value = input.value.replace(/\D/g, '');
            
            // Limit to 4 digits
            if (value.length > 4) {
                value = value.substring(0, 4);
            }
            
            // Update the input value
            input.value = value;
            
            // Update the corresponding hidden datetime field
            updateDateTimeField(input);
        }
        
        // Function to update hidden datetime fields
        function updateDateTimeField(timeInput) {
            const isTaskStart = timeInput.id === 'TaskStartTime';
            const isTaskEnd = timeInput.id === 'TaskEndTime';
            
            if (isTaskStart) {
                const dateInput = document.getElementById('TaskStartDate');
                const hiddenInput = document.getElementById('TaskStart');
                const timeValue = timeInput.value;
                const dateValue = dateInput.value;
                
                if (dateValue && timeValue && timeValue.length === 4) {
                    // Format time as HH:MM
                    const formattedTime = timeValue.substring(0, 2) + ':' + timeValue.substring(2, 4);
                    hiddenInput.value = dateValue + 'T' + formattedTime;
                } else {
                    hiddenInput.value = '';
                }
            } else if (isTaskEnd) {
                const dateInput = document.getElementById('TaskEndDate');
                const hiddenInput = document.getElementById('TaskEnd');
                const timeValue = timeInput.value;
                const dateValue = dateInput.value;
                
                if (dateValue && timeValue && timeValue.length === 4) {
                    // Format time as HH:MM
                    const formattedTime = timeValue.substring(0, 2) + ':' + timeValue.substring(2, 4);
                    hiddenInput.value = dateValue + 'T' + formattedTime;
                } else {
                    hiddenInput.value = '';
                }
            }
        }
        
        // Add event listeners for date inputs
        document.addEventListener('DOMContentLoaded', function() {
            const taskStartDate = document.getElementById('TaskStartDate');
            const taskEndDate = document.getElementById('TaskEndDate');
            
            if (taskStartDate) {
                taskStartDate.addEventListener('change', function() {
                    const timeInput = document.getElementById('TaskStartTime');
                    if (timeInput && timeInput.value) {
                        updateDateTimeField(timeInput);
                    }
                });
            }
            
            if (taskEndDate) {
                taskEndDate.addEventListener('change', function() {
                    const timeInput = document.getElementById('TaskEndTime');
                    if (timeInput && timeInput.value) {
                        updateDateTimeField(timeInput);
                    }
                });
            }
        });
        
        // Crew data for JavaScript
        const crewData = <?php echo json_encode($availableCrew); ?>;
        
        // Other Crew Management
        let otherCrewIds = [];
        let allCrewIds = [];

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
            if (!otherCrewIds.includes(crewId.toString())) {
                otherCrewIds.push(crewId.toString());
                updateOtherCrewDisplay();
                closeOtherCrewModal();
            }
        }

        function removeOtherCrew(crewId) {
            otherCrewIds = otherCrewIds.filter(id => id !== crewId.toString());
            updateOtherCrewDisplay();
        }

        function updateOtherCrewDisplay() {
            const badgesContainer = document.getElementById('otherCrewBadges');
            const hiddenInput = document.getElementById('OtherCrew');
            
            // Update hidden input
            hiddenInput.value = otherCrewIds.join(',');
            
            // Update badges display
            badgesContainer.innerHTML = '';
            otherCrewIds.forEach(crewId => {
                const crewName = getCrewName(crewId);
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                badge.innerHTML = `${crewName} <button type="button" onclick="removeOtherCrew(${crewId})" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"><i class="fas fa-times text-xs"></i></button>`;
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
            if (!allCrewIds.includes(crewId.toString())) {
                allCrewIds.push(crewId.toString());
                updateAllCrewDisplay();
                closeAllCrewModal();
            }
        }

        function removeAllCrew(crewId) {
            allCrewIds = allCrewIds.filter(id => id !== crewId.toString());
            updateAllCrewDisplay();
        }

        function updateAllCrewDisplay() {
            const badgesContainer = document.getElementById('allCrewBadges');
            const hiddenInput = document.getElementById('AllCrew');
            
            // Update hidden input
            hiddenInput.value = allCrewIds.join(',');
            
            // Update badges display
            badgesContainer.innerHTML = '';
            allCrewIds.forEach(crewId => {
                const crewName = getCrewName(crewId);
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                badge.innerHTML = `${crewName} <button type="button" onclick="removeAllCrew(${crewId})" class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200"><i class="fas fa-times text-xs"></i></button>`;
                badgesContainer.appendChild(badge);
            });
        }

        // Update aircraft information when aircraft is selected
        function updateAircraftInfo() {
            const aircraftSelect = document.getElementById('AircraftID');
            const registrationInput = document.getElementById('Rego');
            const aircraftTypeInput = document.getElementById('ACType');
            
            const selectedOption = aircraftSelect.options[aircraftSelect.selectedIndex];
            
            if (selectedOption.value) {
                registrationInput.value = selectedOption.getAttribute('data-registration');
                aircraftTypeInput.value = selectedOption.getAttribute('data-aircraft-type');
            } else {
                registrationInput.value = '';
                aircraftTypeInput.value = '';
            }
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

        // Task Name Management
        function updateTaskNameFromFlightNo() {
            const flightNoInput = document.getElementById('FlightNo');
            const taskNameInput = document.getElementById('TaskName');
            
            if (!flightNoInput || !taskNameInput) {
                return;
            }
            
            const flightNo = flightNoInput.value.trim();
            
            if (flightNo) {
                // Set Task Name to Flight Number
                taskNameInput.value = flightNo;
                console.log(`Task Name updated to: ${flightNo}`);
                
                // Check for duplicate flight number
                checkDuplicateFlightNumber();
            } else {
                // Clear Task Name if Flight Number is empty
                taskNameInput.value = '';
                console.log('Task Name cleared - no Flight Number');
                // Clear any duplicate warning
                clearDuplicateWarning();
            }
        }
        
        // Check for duplicate flight number
        function checkDuplicateFlightNumber() {
            const flightNoInput = document.getElementById('FlightNo');
            const flightDateInput = document.getElementById('FltDate');
            
            if (!flightNoInput || !flightDateInput) {
                return;
            }
            
            const flightNo = flightNoInput.value.trim();
            const flightDate = flightDateInput.value;
            
            if (flightNo && flightDate) {
                // Make AJAX request to check for duplicates
                fetch('api/check_duplicate_flight.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        flightNo: flightNo,
                        flightDate: flightDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        showDuplicateWarning(flightNo, flightDate);
                    } else {
                        clearDuplicateWarning();
                    }
                })
                .catch(error => {
                    console.error('Error checking duplicate flight:', error);
                });
            }
        }
        
        // Show duplicate warning
        function showDuplicateWarning(flightNo, flightDate) {
            clearDuplicateWarning(); // Clear any existing warning
            
            const flightNoInput = document.getElementById('FlightNo');
            const warningDiv = document.createElement('div');
            warningDiv.id = 'duplicate-warning';
            warningDiv.className = 'mt-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md';
            warningDiv.innerHTML = `
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800 dark:text-red-200">
                            Warning: Flight number '${flightNo}' already exists on ${flightDate}
                        </p>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                            Please choose a different flight number or date.
                        </p>
                    </div>
                </div>
            `;
            
            flightNoInput.parentNode.appendChild(warningDiv);
            
            // Add red border to input
            flightNoInput.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
        }
        
        // Clear duplicate warning
        function clearDuplicateWarning() {
            const warningDiv = document.getElementById('duplicate-warning');
            if (warningDiv) {
                warningDiv.remove();
            }
            
            // Remove red border from input
            const flightNoInput = document.getElementById('FlightNo');
            if (flightNoInput) {
                flightNoInput.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            }
        }

        // Toggle MultiDay functionality
        function toggleMultiDay() {
            const multiDayCheckbox = document.getElementById('MultiDay');
            const taskEndDate = document.getElementById('TaskEndDate');
            const flightDate = document.getElementById('FltDate');
            
            if (!multiDayCheckbox || !taskEndDate) {
                return;
            }
            
            if (multiDayCheckbox.checked) {
                // Enable TaskEndDate when MultiDay is checked
                taskEndDate.disabled = false;
                taskEndDate.classList.remove('bg-gray-50', 'dark:bg-gray-600');
            } else {
                // Disable TaskEndDate and reset to FltDate when MultiDay is unchecked
                taskEndDate.disabled = true;
                taskEndDate.classList.add('bg-gray-50', 'dark:bg-gray-600');
                
                // Reset TaskEndDate to FltDate value if FltDate is set
                if (flightDate && flightDate.value) {
                    taskEndDate.value = flightDate.value;
                    // Update TaskEnd hidden field if time is set
                    const taskEndTime = document.getElementById('TaskEndTime');
                    if (taskEndTime && taskEndTime.value) {
                        updateDateTimeField(taskEndTime);
                    }
                }
            }
        }

        
        // Route preview functionality
        function updateRoutePreview() {
            const originSelect = document.getElementById('origin');
            const destinationSelect = document.getElementById('destination');
            const originValue = originSelect.value;
            const destinationValue = destinationSelect.value;
            const previewContainer = document.getElementById('route-preview-container');
            
            if (originValue && destinationValue) {
                previewContainer.innerHTML = `
                    <div class="flex items-center text-sm text-blue-700 dark:text-blue-300">
                        <i class="fas fa-route mr-2"></i>
                        <span class="font-medium">Route:</span>
                        <span class="ml-2 font-mono bg-blue-100 dark:bg-blue-800 px-2 py-1 rounded">${originValue}-${destinationValue}</span>
                    </div>
                `;
                previewContainer.className = 'w-full px-3 py-2 border border-blue-300 dark:border-blue-600 rounded-md shadow-sm bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 min-h-[42px] flex items-center';
            } else {
                previewContainer.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">Select origin and destination to see route preview</span>';
                previewContainer.className = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400 min-h-[42px] flex items-center';
            }
        }
        
        // Add event listeners for route preview
        document.addEventListener('DOMContentLoaded', function() {
            const originSelect = document.getElementById('origin');
            const destinationSelect = document.getElementById('destination');
            
            // Initialize Select2 for origin and destination dropdowns
            if (originSelect) {
                $(originSelect).select2({
                    placeholder: '-- Select Origin --',
                    allowClear: true,
                    width: '100%',
                    templateResult: function(option) {
                        if (!option.id) {
                            return option.text;
                        }
                        // Custom template for better display
                        return $('<span>' + option.text + '</span>');
                    },
                    templateSelection: function(option) {
                        return option.text;
                    }
                });
                
                // Add change event listener for route preview
                $(originSelect).on('change', function() {
                    updateRoutePreview();
                });
            }
            
            if (destinationSelect) {
                $(destinationSelect).select2({
                    placeholder: '-- Select Destination --',
                    allowClear: true,
                    width: '100%',
                    templateResult: function(option) {
                        if (!option.id) {
                            return option.text;
                        }
                        // Custom template for better display
                        return $('<span>' + option.text + '</span>');
                    },
                    templateSelection: function(option) {
                        return option.text;
                    }
                });
                
                // Add change event listener for route preview
                $(destinationSelect).on('change', function() {
                    updateRoutePreview();
                });
            }
            
            // Add event listener for flight date changes
            const flightDateInput = document.getElementById('FltDate');
            if (flightDateInput) {
                flightDateInput.addEventListener('change', function() {
                    // Auto-fill TaskStartDate and TaskEndDate with FltDate
                    const taskStartDate = document.getElementById('TaskStartDate');
                    const taskEndDate = document.getElementById('TaskEndDate');
                    const multiDayCheckbox = document.getElementById('MultiDay');
                    
                    if (taskStartDate && this.value) {
                        taskStartDate.value = this.value;
                        // Update TaskStart hidden field if time is set
                        const taskStartTime = document.getElementById('TaskStartTime');
                        if (taskStartTime && taskStartTime.value) {
                            updateDateTimeField(taskStartTime);
                        }
                    }
                    
                    // Only auto-fill TaskEndDate if MultiDay is not checked
                    if (taskEndDate && this.value && (!multiDayCheckbox || !multiDayCheckbox.checked)) {
                        taskEndDate.value = this.value;
                        // Ensure TaskEndDate is disabled when MultiDay is not checked
                        taskEndDate.disabled = true;
                        taskEndDate.classList.add('bg-gray-50', 'dark:bg-gray-600');
                        // Update TaskEnd hidden field if time is set
                        const taskEndTime = document.getElementById('TaskEndTime');
                        if (taskEndTime && taskEndTime.value) {
                            updateDateTimeField(taskEndTime);
                        }
                    }
                    
                    // Check for duplicate if flight number is also filled
                    const flightNoInput = document.getElementById('FlightNo');
                    if (flightNoInput && flightNoInput.value.trim()) {
                        checkDuplicateFlightNumber();
                    }
                });
            }
            
            // Initialize TaskEndDate as disabled on page load
            const taskEndDateInput = document.getElementById('TaskEndDate');
            if (taskEndDateInput) {
                taskEndDateInput.disabled = true;
            }
            
            // Reset form after successful submission if message is shown
            <?php if ($message && empty($error)): ?>
                // Clear form after successful submission
                setTimeout(function() {
                    const form = document.querySelector('form');
                    if (form) {
                        form.reset();
                        
                        // Reset Select2 dropdowns
                        if (typeof $ !== 'undefined' && $.fn.select2) {
                            $('#origin').val(null).trigger('change');
                            $('#destination').val(null).trigger('change');
                        }
                        
                        // Clear route preview
                        const routePreview = document.getElementById('route-preview-container');
                        if (routePreview) {
                            routePreview.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">Select origin and destination to see route preview</span>';
                            routePreview.className = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400 min-h-[42px] flex items-center';
                        }
                        
                        // Clear aircraft info
                        updateAircraftInfo();
                        
                        // Clear other crew and all crew
                        otherCrewIds = [];
                        allCrewIds = [];
                        updateOtherCrewDisplay();
                        updateAllCrewDisplay();
                        
                        // Clear duplicate warning if exists
                        clearDuplicateWarning();
                    }
                }, 3000); // Reset form after 3 seconds
            <?php endif; ?>
        });
        
        // Dark Mode Toggle Functionality
        function toggleDarkMode() {
            const html = document.documentElement;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun';
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Initialize dark mode based on system preference or saved preference
        function initDarkMode() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (savedDarkMode === 'true' || (savedDarkMode === null && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
                if (darkModeIcon) darkModeIcon.className = 'fas fa-sun';
            } else {
                document.documentElement.classList.remove('dark');
                if (darkModeIcon) darkModeIcon.className = 'fas fa-moon';
            }
        }

        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('darkMode') === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                    const darkModeIcon = document.getElementById('dark-mode-icon');
                    if (darkModeIcon) darkModeIcon.className = 'fas fa-sun';
                } else {
                    document.documentElement.classList.remove('dark');
                    const darkModeIcon = document.getElementById('dark-mode-icon');
                    if (darkModeIcon) darkModeIcon.className = 'fas fa-moon';
                }
            }
        });
    </script>
</body>
</html>
