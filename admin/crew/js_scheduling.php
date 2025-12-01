<?php
// Suppress PHP warnings/notices for AJAX requests to prevent JSON corruption
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
}

require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/crew/scheduling.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get date parameters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDateFormatted = date('l, M j, Y', strtotime($selectedDate));

// Get crew data
$users = getUsersForCrewSelection();
$assignedUsers = getUsersAssignedToFlights($selectedDate); // Users actually assigned to flights on selected date
$flightsGrouped = getFlightsGroupedByDate($selectedDate, $selectedDate);
$cockpitRoles = getAllCockpitRoles(); // Get cockpit roles from database
$cabinRoles = getAllCabinRoles(); // Get cabin roles from database

// Combine all roles from both tables for all crew position combos
$allRoles = array_merge($cockpitRoles, $cabinRoles);
// Sort by sort_order and label for consistent display
usort($allRoles, function($a, $b) {
    if ($a['sort_order'] != $b['sort_order']) {
        return $a['sort_order'] - $b['sort_order'];
    }
    return strcmp($a['label'] ?? $a['code'] ?? '', $b['label'] ?? $b['code'] ?? '');
});

// Get ticket information for each assigned user
$userTickets = [];
foreach ($assignedUsers as $user) {
    if (!empty($user['mobile'])) {
        $tickets = checkPassengerTickets($user['mobile'], $selectedDate);
        if (!empty($tickets)) {
            $userTickets[$user['id']] = $tickets;
        }
    }
}

// Handle AJAX crew assignment updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'duplicate_crew') {
        // Start output buffering
        ob_start();
        
        try {
            $sourceFlightId = intval($_POST['source_flight_id'] ?? 0);
            $targetFlightIds = json_decode($_POST['target_flight_ids'] ?? '[]', true);
            
            if ($sourceFlightId <= 0 || empty($targetFlightIds) || !is_array($targetFlightIds)) {
                $error = 'Invalid source flight ID or target flights.';
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit();
                }
            } else {
                // Get source flight crew data
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT Crew1, Crew2, Crew3, Crew4, Crew5, Crew6, Crew7, Crew8, Crew9, Crew10, 
                                             Crew1_role, Crew2_role, Crew3_role, Crew4_role, Crew5_role, 
                                             Crew6_role, Crew7_role, Crew8_role, Crew9_role, Crew10_role 
                                      FROM flights WHERE id = ?");
                $stmt->execute([$sourceFlightId]);
                $sourceCrew = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$sourceCrew) {
                    $error = 'Source flight not found.';
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        ob_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $error]);
                        exit();
                    }
                } else {
                    // Prepare crew data for update
                    $crewData = [];
                    for ($i = 1; $i <= 10; $i++) {
                        $crewField = "Crew{$i}";
                        $roleField = "Crew{$i}_role";
                        if (isset($sourceCrew[$crewField])) {
                            $crewData[$crewField] = $sourceCrew[$crewField];
                        }
                        if (isset($sourceCrew[$roleField])) {
                            $crewData[$roleField] = $sourceCrew[$roleField];
                        }
                    }
                    
                    // Update each target flight
                    $successCount = 0;
                    $failCount = 0;
                    
                    foreach ($targetFlightIds as $targetFlightId) {
                        $targetFlightId = intval($targetFlightId);
                        if ($targetFlightId > 0 && $targetFlightId != $sourceFlightId) {
                            if (updateCrewAssignment($targetFlightId, $crewData)) {
                                $successCount++;
                            } else {
                                $failCount++;
                            }
                        }
                    }
                    
                    if ($successCount > 0) {
                        $message = "Crew assignments copied to {$successCount} flight(s) successfully.";
                        if ($failCount > 0) {
                            $message .= " Failed to copy to {$failCount} flight(s).";
                        }
                        
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            ob_clean();
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => $message]);
                            exit();
                        }
                    } else {
                        $error = 'Failed to copy crew assignments to any flight.';
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            ob_clean();
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'error' => $error]);
                            exit();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
                exit();
            }
        }
        
        ob_end_clean();
    } elseif ($_POST['action'] === 'update_crew') {
        // Start output buffering to prevent any PHP errors from corrupting JSON response
        ob_start();
        
        try {
            $flightId = intval($_POST['flight_id'] ?? 0);
            $crewData = [];
            
            // Generate Crew1-Crew10 fields dynamically
            $crewFields = [];
            $roleFields = [];
            for ($i = 1; $i <= 10; $i++) {
                $crewFields[] = "Crew{$i}";
                $roleFields[] = "Crew{$i}_role";
            }
            
            // Process crew fields - allow empty values (for removal)
            foreach ($crewFields as $field) {
                // Check if field exists in POST (even if empty)
                if (array_key_exists($field, $_POST)) {
                    // Allow empty string or null to clear the field
                    $value = $_POST[$field];
                    if ($value === '' || $value === null) {
                        $crewData[$field] = null;
                    } else {
                        $crewData[$field] = $value;
                    }
                }
            }
            
            // Process role fields - allow empty values (for removal)
            foreach ($roleFields as $field) {
                // Check if field exists in POST (even if empty)
                if (array_key_exists($field, $_POST)) {
                    // Allow empty string or null to clear the field
                    $value = $_POST[$field];
                    if ($value === '' || $value === null) {
                        $crewData[$field] = null;
                    } else {
                        $crewData[$field] = $value;
                    }
                }
            }
            
            // Check if we have valid flight ID and at least one field to update
            if ($flightId > 0 && count($crewData) > 0) {
                if (updateCrewAssignment($flightId, $crewData)) {
                    $message = 'Crew assignment updated successfully.';
                    // For AJAX requests, return JSON response
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        ob_clean(); // Clear any output
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => $message]);
                        exit();
                    }
                } else {
                    $error = 'Failed to update crew assignment.';
                    // For AJAX requests, return JSON response
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        ob_clean(); // Clear any output
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $error]);
                        exit();
                    }
                }
            } else {
                $error = 'Invalid flight ID or no crew data provided.';
                // For AJAX requests, return JSON response
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    ob_clean(); // Clear any output
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit();
                }
            }
        } catch (Exception $e) {
            // For AJAX requests, return JSON response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                ob_clean(); // Clear any output
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
                exit();
            }
        }
        
        // End output buffering for non-AJAX requests
        ob_end_clean();
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crew Scheduling - <?php echo PROJECT_NAME; ?></title>
    <script src="../../assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .crew-table { min-width: 1400px; }
        .crew-cell { min-width: 120px; }
        
        /* Autocomplete dropdown styles */
        .autocomplete-wrapper {
            position: relative;
        }
        .autocomplete-input {
            width: 100%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            background-color: white;
            color: #111827;
        }
        .dark .autocomplete-input {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #f9fafb !important;
        }
        .autocomplete-input::placeholder {
            color: #9ca3af;
        }
        .dark .autocomplete-input::placeholder {
            color: #9ca3af;
        }
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .dark .autocomplete-dropdown {
            background-color: #374151;
            border-color: #4b5563;
        }
        .autocomplete-item {
            padding: 0.5rem;
            cursor: pointer;
            font-size: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .dark .autocomplete-item {
            border-bottom-color: #4b5563;
        }
        .autocomplete-item:hover {
            background-color: #f3f4f6;
        }
        .dark .autocomplete-item:hover {
            background-color: #4b5563;
        }
        .autocomplete-item.selected {
            background-color: #3b82f6;
            color: white;
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Crew Scheduling</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1"><?php echo $selectedDateFormatted; ?></p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <button onclick="openUserDetailModal()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-users mr-2"></i>
                                User Details
                            </button>
                            <div class="flex items-center space-x-2">
                                <label for="date-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">Date:</label>
                                <input type="date" id="date-select" value="<?php echo $selectedDate; ?>" 
                                       class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                       onchange="changeDate(this.value)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6 flex flex-col overflow-hidden">
                <?php include '../../includes/permission_banner.php'; ?>
                
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

                <!-- Flights by Date -->
                <?php if (empty($flightsGrouped)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <i class="fas fa-plane text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Flights Found</h3>
                        <p class="text-gray-500 dark:text-gray-400">No flights scheduled for <?php echo $selectedDateFormatted; ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($flightsGrouped as $date => $flights): ?>
                        <div class="mb-8 flex flex-col flex-1 min-h-0">
                            <!-- Date Header -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-4 flex-shrink-0">
                                <h2 class="text-xl font-semibold text-blue-900 dark:text-blue-100">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <?php echo date('l, F j, Y', strtotime($date)); ?>
                                </h2>
                                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                    <?php echo count($flights); ?> flight(s) scheduled
                                </p>
                            </div>

                            <!-- Flights Table -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 flex-1 flex flex-col min-h-0 overflow-hidden">
                                <div class="overflow-auto flex-1">
                                <table class="min-w-full crew-table">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-12"></th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task End</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Hours</th>
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider crew-cell">Crew<?php echo $i; ?></th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($flights as $flight): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <!-- Dup Button -->
                                                <td class="px-2 py-4 whitespace-nowrap">
                                                    <button onclick="openDuplicateModal(<?php echo $flight['id']; ?>, '<?php echo htmlspecialchars($flight['FlightNo'] ?? 'N/A'); ?>')" 
                                                            class="p-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors"
                                                            title="Duplicate Crew to other flights">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </td>
                                                <!-- Flight Info -->
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($flight['FlightNo'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($flight['TaskName'] ?? 'N/A'); ?>
                                                    </div>
                                                </td>

                                                <!-- Aircraft -->
                                                <td class="px-4 py-4 whitespace-nowrap cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700" 
                                                    onclick="showAircraftDetails(<?php echo htmlspecialchars(json_encode($flight)); ?>)">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($flight['aircraft_registration'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php 
                                                        $manufacturer = $flight['manufacturer'] ?? '';
                                                        $aircraft_type = $flight['aircraft_type'] ?? '';
                                                        $aircraft_info = trim($manufacturer . ' ' . $aircraft_type);
                                                        echo htmlspecialchars($aircraft_info ?: 'N/A'); 
                                                        ?>
                                                    </div>
                                                    <?php if (!empty($flight['serial_number'])): ?>
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                                        S/N: <?php echo htmlspecialchars($flight['serial_number']); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($flight['base_location'])): ?>
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                                        Base: <?php echo htmlspecialchars($flight['base_location']); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($flight['aircraft_status'])): ?>
                                                    <div class="text-xs">
                                                        <?php
                                                        $status = $flight['aircraft_status'];
                                                        $status_color = match($status) {
                                                            'active' => 'text-green-600 dark:text-green-400',
                                                            'inactive' => 'text-gray-600 dark:text-gray-400',
                                                            'maintenance' => 'text-yellow-600 dark:text-yellow-400',
                                                            'retired' => 'text-red-600 dark:text-red-400',
                                                            default => 'text-gray-600 dark:text-gray-400'
                                                        };
                                                        ?>
                                                        <span class="<?php echo $status_color; ?>">
                                                            <?php echo ucfirst(htmlspecialchars($status)); ?>
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Route -->
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                                    </div>
                                                </td>

                                                <!-- Task Start -->
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <?php echo $flight['TaskStart'] ? date('H:i', strtotime($flight['TaskStart'])) : 'N/A'; ?>
                                                    </div>
                                                </td>

                                                <!-- Task End -->
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <?php echo $flight['TaskEnd'] ? date('H:i', strtotime($flight['TaskEnd'])) : 'N/A'; ?>
                                                    </div>
                                                </td>

                                                <!-- Flight Hours -->
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($flight['FltHours'] ?? 'N/A'); ?>
                                                    </div>
                                                </td>

                                                <?php for ($i = 1; $i <= 10; $i++): 
                                                    $crewField = "Crew{$i}";
                                                    $crewRoleField = "Crew{$i}_role";
                                                    $crewValue = $flight[$crewField] ?? null;
                                                    $crewRoleValue = $flight[$crewRoleField] ?? null;
                                                    
                                                    // Find selected user name
                                                    $selectedUserName = '';
                                                    if (isset($crewValue) && $crewValue) {
                                                        foreach ($users as $user) {
                                                            if ($user['id'] == $crewValue) {
                                                                $selectedUserName = $user['first_name'] . ' ' . $user['last_name'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                ?>
                                                    <!-- Crew<?php echo $i; ?> -->
                                                <td class="px-4 py-4 crew-cell">
                                                    <div class="space-y-1">
                                                        <!-- Crew Autocomplete -->
                                                        <div class="autocomplete-wrapper">
                                                            <input type="text" 
                                                                   id="crew_<?php echo $flight['id']; ?>_<?php echo $crewField; ?>"
                                                                   class="autocomplete-input dark:bg-gray-700 dark:text-white dark:border-gray-600" 
                                                                   placeholder="Select Crew<?php echo $i; ?>"
                                                                   value="<?php echo htmlspecialchars($selectedUserName); ?>"
                                                                   data-flight-id="<?php echo $flight['id']; ?>"
                                                                   data-field="<?php echo $crewField; ?>"
                                                                   data-selected-id="<?php echo $crewValue ?? ''; ?>"
                                                                   autocomplete="off"
                                                                   oninput="filterCrewOptions(this); clearCrew(this)"
                                                                   onfocus="showCrewDropdown(this)"
                                                                   onblur="hideCrewDropdown(this); clearCrew(this)"
                                                                   onkeydown="handleCrewKeydown(event, this)"
                                                                   ondblclick="this.value=''; clearCrew(this)">
                                                            <div class="autocomplete-dropdown" id="crew_dropdown_<?php echo $flight['id']; ?>_<?php echo $crewField; ?>">
                                                                <div class="autocomplete-item" 
                                                                     data-id=""
                                                                     data-name=""
                                                                     onclick="clearCrewInput('<?php echo $flight['id']; ?>', '<?php echo $crewField; ?>')"
                                                                     style="color: #ef4444; font-weight: 500;">
                                                                    <i class="fas fa-times mr-1"></i> Clear
                                                                </div>
                                                                <?php foreach ($users as $user): ?>
                                                                    <div class="autocomplete-item" 
                                                                         data-id="<?php echo $user['id']; ?>"
                                                                         data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                                         onclick="selectCrew(this, '<?php echo $flight['id']; ?>', '<?php echo $crewField; ?>')">
                                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Role Autocomplete -->
                                                        <div class="autocomplete-wrapper">
                                                            <input type="text" 
                                                                   id="role_<?php echo $flight['id']; ?>_<?php echo $crewRoleField; ?>"
                                                                   class="autocomplete-input dark:bg-gray-700 dark:text-white dark:border-gray-600" 
                                                                   placeholder="Role"
                                                                   value="<?php echo htmlspecialchars($crewRoleValue ?? ''); ?>"
                                                                   data-flight-id="<?php echo $flight['id']; ?>"
                                                                   data-field="<?php echo $crewRoleField; ?>"
                                                                   autocomplete="off"
                                                                   oninput="filterRoleOptions(this)"
                                                                   onfocus="showRoleDropdown(this)"
                                                                   onblur="hideRoleDropdown(this)"
                                                                   onkeydown="handleRoleKeydown(event, this)">
                                                            <div class="autocomplete-dropdown" id="role_dropdown_<?php echo $flight['id']; ?>_<?php echo $crewRoleField; ?>">
                                                                <div class="autocomplete-item" 
                                                                     data-value=""
                                                                     onclick="selectRole(this, '<?php echo $flight['id']; ?>', '<?php echo $crewRoleField; ?>')">
                                                                    (Clear)
                                                                </div>
                                                                <?php foreach ($allRoles as $role): ?>
                                                                    <?php if ($role['is_active']): ?>
                                                                        <div class="autocomplete-item" 
                                                                             data-value="<?php echo htmlspecialchars($role['code']); ?>"
                                                                             onclick="selectRole(this, '<?php echo $flight['id']; ?>', '<?php echo $crewRoleField; ?>')">
                                                                            <?php echo htmlspecialchars($role['code']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php endfor; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Aircraft Details Modal -->
    <div id="aircraftModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Aircraft Details</h3>
                    <button onclick="closeAircraftModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="aircraftDetails" class="space-y-4">
                    <!-- Aircraft details will be populated here -->
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeAircraftModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Duplicate Crew Modal -->
    <div id="duplicateCrewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fas fa-copy mr-2"></i>
                        Duplicate Crew Assignment
                    </h3>
                    <button onclick="closeDuplicateModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Select one or more flights to copy crew assignments from flight: 
                        <span id="sourceFlightNo" class="font-semibold text-gray-900 dark:text-white"></span>
                    </p>
                </div>
                
                <div class="mb-4 flex items-center space-x-4">
                    <button onclick="selectAllFlights()" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                        Select All
                    </button>
                    <button onclick="deselectAllFlights()" class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">
                        Deselect All
                    </button>
                    <span id="selectedCount" class="text-sm text-gray-600 dark:text-gray-400">0 selected</span>
                </div>
                
                <div class="overflow-y-auto max-h-96 border border-gray-200 dark:border-gray-700 rounded">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Flight</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Aircraft</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Route</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Task Start</th>
                            </tr>
                        </thead>
                        <tbody id="duplicateFlightsList" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Flights will be populated here -->
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button onclick="closeDuplicateModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button onclick="duplicateCrew()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-copy mr-2"></i>
                        Copy Crew
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div id="userDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">User Details - Assigned Crew Members for <?php echo $selectedDateFormatted; ?></h3>
                    <button onclick="closeUserDetailModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <div class="flex items-center space-x-4">
                        <div class="flex-1">
                            <input type="text" id="userSearchInput" placeholder="Search users..." 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div class="flex items-center space-x-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400">Filter by Role:</label>
                            <select id="roleFilter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Roles</option>
                                <option value="pilot">Pilot</option>
                                <option value="crew">Crew</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tickets (<?php echo date('M j', strtotime($selectedDate . ' -3 days')); ?> - <?php echo date('M j', strtotime($selectedDate . ' +3 days')); ?>)</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($assignedUsers as $user): ?>
                            <tr class="user-row" data-role="<?php echo htmlspecialchars($user['role_name'] ?? 'employee'); ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <div class="space-y-1">
                                        <?php if (!empty($user['email'])): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-envelope text-xs text-gray-400 mr-2"></i>
                                            <span class="text-xs"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($user['mobile'])): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-phone text-xs text-gray-400 mr-2"></i>
                                            <span class="text-xs"><?php echo htmlspecialchars(formatMobileNumber($user['mobile'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    <?php if (isset($userTickets[$user['id']]) && !empty($userTickets[$user['id']])): ?>
                                        <div class="space-y-2">
                                            <?php foreach ($userTickets[$user['id']] as $ticket): ?>
                                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-2">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-xs font-medium text-blue-800 dark:text-blue-200">
                                                        <?php echo htmlspecialchars($ticket['flight_no']); ?>
                                                    </span>
                                                    <span class="text-xs text-blue-600 dark:text-blue-300">
                                                        <?php echo htmlspecialchars($ticket['departure_date']); ?>
                                                    </span>
                                                </div>
                                                <div class="text-xs text-blue-700 dark:text-blue-300">
                                                    <i class="fas fa-plane text-xs mr-1"></i>
                                                    <?php echo htmlspecialchars($ticket['origin']); ?> → <?php echo htmlspecialchars($ticket['destination']); ?>
                                                </div>
                                                <?php if (!empty($ticket['pnr'])): ?>
                                                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                    PNR: <?php echo htmlspecialchars($ticket['pnr']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 italic">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            No tickets found
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <p>Total Assigned Users for <?php echo $selectedDateFormatted; ?>: <span id="totalUsers"><?php echo count($assignedUsers); ?></span> | 
                       Showing: <span id="showingUsers"><?php echo count($assignedUsers); ?></span></p>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeUserDetailModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Note: DH functionality has been removed. Crew4 is now a single user field like other crew fields.
        // All crew fields (Crew1-Crew10) now work the same way - single user selection.

        // Store current source flight ID for duplication
        let currentSourceFlightId = null;
        let allFlightsForDate = <?php echo json_encode($flightsGrouped[$selectedDate] ?? []); ?>;
        
        function changeDate(date) {
            window.location.href = '?date=' + date;
        }
        
        // Duplicate Crew Modal Functions
        function openDuplicateModal(flightId, flightNo) {
            currentSourceFlightId = flightId;
            document.getElementById('sourceFlightNo').textContent = flightNo;
            
            // Filter flights for the same date (exclude source flight)
            const flightsList = allFlightsForDate.filter(f => f.id != flightId);
            const tbody = document.getElementById('duplicateFlightsList');
            tbody.innerHTML = '';
            
            if (flightsList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No other flights found for this date</td></tr>';
            } else {
                flightsList.forEach(flight => {
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                    row.innerHTML = `
                        <td class="px-4 py-2">
                            <input type="checkbox" class="flight-checkbox" value="${flight.id}" onchange="updateSelectedCount()">
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${flight.FlightNo || 'N/A'}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${flight.aircraft_registration || 'N/A'}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${flight.Route || 'N/A'}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${flight.TaskStart ? new Date(flight.TaskStart).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'}) : 'N/A'}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            updateSelectedCount();
            document.getElementById('duplicateCrewModal').classList.remove('hidden');
        }
        
        function closeDuplicateModal() {
            document.getElementById('duplicateCrewModal').classList.add('hidden');
            currentSourceFlightId = null;
            // Uncheck all checkboxes
            document.querySelectorAll('.flight-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
        }
        
        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.flight-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }
        
        function selectAllFlights() {
            document.querySelectorAll('.flight-checkbox').forEach(cb => {
                cb.checked = true;
            });
            document.getElementById('selectAllCheckbox').checked = true;
            updateSelectedCount();
        }
        
        function deselectAllFlights() {
            document.querySelectorAll('.flight-checkbox').forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('selectAllCheckbox').checked = false;
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.flight-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = checked + ' selected';
        }
        
        function duplicateCrew() {
            if (!currentSourceFlightId) {
                showMessage('No source flight selected', 'error');
                return;
            }
            
            const selectedFlights = Array.from(document.querySelectorAll('.flight-checkbox:checked'))
                .map(cb => parseInt(cb.value));
            
            if (selectedFlights.length === 0) {
                showMessage('Please select at least one flight', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'duplicate_crew');
            formData.append('source_flight_id', currentSourceFlightId);
            formData.append('target_flight_ids', JSON.stringify(selectedFlights));
            
            // Show loading
            const copyButton = document.querySelector('#duplicateCrewModal button[onclick="duplicateCrew()"]');
            const originalText = copyButton.innerHTML;
            copyButton.disabled = true;
            copyButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Copying...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Invalid response format');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    showMessage(data.message || `Crew assignments copied to ${selectedFlights.length} flight(s) successfully`, 'success');
                    closeDuplicateModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(data.error || 'Failed to copy crew assignments', 'error');
                    copyButton.disabled = false;
                    copyButton.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error duplicating crew:', error);
                showMessage('Error copying crew assignments: ' + error.message, 'error');
                copyButton.disabled = false;
                copyButton.innerHTML = originalText;
            });
        }
        
        // Close duplicate modal when clicking outside
        document.getElementById('duplicateCrewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDuplicateModal();
            }
        });

        function showMessage(message, type) {
            // Remove existing message
            const existingMessage = document.querySelector('.dynamic-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            // Create new message element
            const messageDiv = document.createElement('div');
            messageDiv.className = 'dynamic-message mb-6 rounded-md p-4';
            
            if (type === 'success') {
                messageDiv.className += ' bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700';
                messageDiv.innerHTML = `
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">${message}</p>
                        </div>
                    </div>
                `;
            } else {
                messageDiv.className += ' bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700';
                messageDiv.innerHTML = `
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">${message}</p>
                        </div>
                    </div>
                `;
            }

            // Insert message at the top of content
            const content = document.querySelector('.flex-1.p-6');
            const firstChild = content.firstElementChild;
            content.insertBefore(messageDiv, firstChild);

            // Auto-remove message after 5 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }

        // Autocomplete functions for Crew
        let selectedCrewIndex = -1;
        
        function filterCrewOptions(input) {
            const dropdown = document.getElementById('crew_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
            const filter = input.value.toLowerCase();
            const items = dropdown.querySelectorAll('.autocomplete-item');
            let visibleCount = 0;
            
            items.forEach((item, index) => {
                // Always show Clear option
                if (item.textContent.includes('Clear')) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    const name = item.dataset.name.toLowerCase();
                    if (name.includes(filter)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
            
            if (visibleCount > 0) {
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
            
            selectedCrewIndex = -1;
        }
        
        function showCrewDropdown(input) {
            const dropdown = document.getElementById('crew_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
            dropdown.style.display = 'block';
            filterCrewOptions(input);
        }
        
        function hideCrewDropdown(input) {
            // Delay to allow click event to fire
            setTimeout(() => {
                const dropdown = document.getElementById('crew_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
                dropdown.style.display = 'none';
            }, 200);
        }
        
        function selectCrew(item, flightId, field) {
            const input = document.getElementById('crew_' + flightId + '_' + field);
            const userId = item.dataset.id;
            const userName = item.dataset.name;
            
            input.value = userName;
            input.dataset.selectedId = userId;
            
            const dropdown = document.getElementById('crew_dropdown_' + flightId + '_' + field);
            dropdown.style.display = 'none';
            
            // Update crew assignment
            updateCrew(flightId, field, userId);
        }
        
        // Allow clearing crew by double-clicking or typing empty
        function clearCrew(input) {
            if (input.value === '') {
                input.dataset.selectedId = '';
                updateCrew(input.dataset.flightId, input.dataset.field, '');
            }
        }
        
        function clearCrewInput(flightId, field) {
            const input = document.getElementById('crew_' + flightId + '_' + field);
            input.value = '';
            input.dataset.selectedId = '';
            const dropdown = document.getElementById('crew_dropdown_' + flightId + '_' + field);
            dropdown.style.display = 'none';
            updateCrew(flightId, field, '');
        }
        
        function handleCrewKeydown(event, input) {
            const dropdown = document.getElementById('crew_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
            const items = Array.from(dropdown.querySelectorAll('.autocomplete-item:not([style*="display: none"])'));
            
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                selectedCrewIndex = Math.min(selectedCrewIndex + 1, items.length - 1);
                updateCrewSelection(items);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                selectedCrewIndex = Math.max(selectedCrewIndex - 1, -1);
                updateCrewSelection(items);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (selectedCrewIndex >= 0 && items[selectedCrewIndex]) {
                    selectCrew(items[selectedCrewIndex], input.dataset.flightId, input.dataset.field);
                }
            } else if (event.key === 'Escape') {
                dropdown.style.display = 'none';
            }
        }
        
        function updateCrewSelection(items) {
            items.forEach((item, index) => {
                if (index === selectedCrewIndex) {
                    item.classList.add('selected');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('selected');
                }
            });
        }
        
        // Autocomplete functions for Role
        let selectedRoleIndex = -1;
        
        function filterRoleOptions(input) {
            const dropdown = document.getElementById('role_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
            const filter = input.value.toLowerCase();
            const items = dropdown.querySelectorAll('.autocomplete-item');
            let visibleCount = 0;
            
            items.forEach((item) => {
                const value = item.dataset.value.toLowerCase();
                if (value.includes(filter)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            if (visibleCount > 0 && input.value.length > 0) {
                dropdown.style.display = 'block';
            } else if (input.value.length === 0) {
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
            
            selectedRoleIndex = -1;
        }
        
        function showRoleDropdown(input) {
            const dropdown = document.getElementById('role_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
            dropdown.style.display = 'block';
            filterRoleOptions(input);
        }
        
        function hideRoleDropdown(input) {
            setTimeout(() => {
                const dropdown = document.getElementById('role_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
                dropdown.style.display = 'none';
            }, 200);
        }
        
        function selectRole(item, flightId, field) {
            const input = document.getElementById('role_' + flightId + '_' + field);
            const roleValue = item.dataset.value;
            
            input.value = roleValue;
            
            const dropdown = document.getElementById('role_dropdown_' + flightId + '_' + field);
            dropdown.style.display = 'none';
            
            // Update role assignment
            updateCrewRole(flightId, field, roleValue);
        }
        
        function handleRoleKeydown(event, input) {
            const dropdown = document.getElementById('role_dropdown_' + input.dataset.flightId + '_' + input.dataset.field);
            const items = Array.from(dropdown.querySelectorAll('.autocomplete-item:not([style*="display: none"])'));
            
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                selectedRoleIndex = Math.min(selectedRoleIndex + 1, items.length - 1);
                updateRoleSelection(items);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                selectedRoleIndex = Math.max(selectedRoleIndex - 1, -1);
                updateRoleSelection(items);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (selectedRoleIndex >= 0 && items[selectedRoleIndex]) {
                    selectRole(items[selectedRoleIndex], input.dataset.flightId, input.dataset.field);
                }
            } else if (event.key === 'Escape') {
                dropdown.style.display = 'none';
            }
        }
        
        function updateRoleSelection(items) {
            items.forEach((item, index) => {
                if (index === selectedRoleIndex) {
                    item.classList.add('selected');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('selected');
                }
            });
        }
        
        function updateCrew(flightId, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_crew');
            formData.append('flight_id', flightId);
            // Always send the field, even if empty (for clearing)
            formData.append(field, value === null || value === undefined ? '' : String(value));

            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, read as text to see what the error is
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Invalid response format');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage(data.message || 'Crew assignment updated successfully', 'success');
                } else {
                    // Show error message
                    showMessage(data.error || 'Failed to update crew assignment', 'error');
                }
                // Reload page to show updated data
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error updating crew:', error);
                showMessage('Error updating crew assignment: ' + error.message, 'error');
            });
        }

        function updateCrewRole(flightId, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_crew');
            formData.append('flight_id', flightId);
            // Always send the field, even if empty (for clearing)
            formData.append(field, value === null || value === undefined ? '' : String(value));

            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, read as text to see what the error is
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Invalid response format');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage(data.message || 'Crew role updated successfully', 'success');
                } else {
                    // Show error message
                    showMessage(data.error || 'Failed to update crew role', 'error');
                }
                // Reload page to show updated data
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error updating crew role:', error);
                showMessage('Error updating crew role: ' + error.message, 'error');
            });
        }

        // Aircraft Details Modal Functions
        function showAircraftDetails(flight) {
            const modal = document.getElementById('aircraftModal');
            const detailsContainer = document.getElementById('aircraftDetails');
            
            // Build aircraft details HTML
            let detailsHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Basic Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Registration:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.aircraft_registration || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Manufacturer:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.manufacturer || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Aircraft Type:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.aircraft_type || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Serial Number:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.serial_number || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Category:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.aircraft_category || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                                <span class="text-sm font-medium ${getStatusColor(flight.aircraft_status)}">${flight.aircraft_status ? flight.aircraft_status.charAt(0).toUpperCase() + flight.aircraft_status.slice(1) : 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Location & Ownership -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Location & Ownership</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Base Location:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.base_location || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Owner:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.aircraft_owner || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Operator:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.aircraft_operator || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Responsible Personnel:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.responsible_personnel || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Manufacture Date:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.date_of_manufacture || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Capabilities -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Capabilities</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">NVFR:</span>
                                <span class="text-sm font-medium ${flight.nvfr ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">${flight.nvfr ? 'Yes' : 'No'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">IFR:</span>
                                <span class="text-sm font-medium ${flight.ifr ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">${flight.ifr ? 'Yes' : 'No'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">SPIFR:</span>
                                <span class="text-sm font-medium ${flight.spifr ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">${flight.spifr ? 'Yes' : 'No'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Airframe Type:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.airframe_type || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Engine Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Engine Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Engine Type:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.engine_type || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Number of Engines:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.number_of_engines || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Engine Model:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.engine_model || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Engine Serial:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${flight.engine_serial_number || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add additional information if available
            if (flight.avionics || flight.other_avionics_information || flight.internal_configuration || flight.external_configuration) {
                detailsHTML += `
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Additional Information</h4>
                        <div class="space-y-3">
                            ${flight.avionics ? `
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Avionics:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1">${flight.avionics}</p>
                                </div>
                            ` : ''}
                            ${flight.other_avionics_information ? `
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Other Avionics:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1">${flight.other_avionics_information}</p>
                                </div>
                            ` : ''}
                            ${flight.internal_configuration ? `
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Internal Configuration:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1">${flight.internal_configuration}</p>
                                </div>
                            ` : ''}
                            ${flight.external_configuration ? `
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">External Configuration:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1">${flight.external_configuration}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }

            detailsContainer.innerHTML = detailsHTML;
            modal.classList.remove('hidden');
        }

        function closeAircraftModal() {
            document.getElementById('aircraftModal').classList.add('hidden');
        }

        function getStatusColor(status) {
            switch(status) {
                case 'active': return 'text-green-600 dark:text-green-400';
                case 'inactive': return 'text-gray-600 dark:text-gray-400';
                case 'maintenance': return 'text-yellow-600 dark:text-yellow-400';
                case 'retired': return 'text-red-600 dark:text-red-400';
                default: return 'text-gray-600 dark:text-gray-400';
            }
        }

        // Close modal when clicking outside
        document.getElementById('aircraftModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAircraftModal();
            }
        });
        
        // User Detail Modal Functions
        function openUserDetailModal() {
            document.getElementById('userDetailModal').classList.remove('hidden');
        }
        
        function closeUserDetailModal() {
            document.getElementById('userDetailModal').classList.add('hidden');
        }
        
        // Close user detail modal when clicking outside
        document.getElementById('userDetailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserDetailModal();
            }
        });
        
        // All users for reference
        let allUsers = <?php echo json_encode($users); ?>;

        // User Detail Modal Search and Filter Functions
        function filterUsers() {
            const searchInput = document.getElementById('userSearchInput');
            const roleFilter = document.getElementById('roleFilter');
            const userRows = document.querySelectorAll('.user-row');
            const showingUsersSpan = document.getElementById('showingUsers');
            
            const searchTerm = searchInput.value.toLowerCase();
            const selectedRole = roleFilter.value;
            
            let visibleCount = 0;
            
            userRows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const role = row.getAttribute('data-role');
                
                const matchesSearch = name.includes(searchTerm);
                const matchesRole = !selectedRole || role === selectedRole;
                
                if (matchesSearch && matchesRole) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            showingUsersSpan.textContent = visibleCount;
        }
        
        // Add event listeners for search and filter
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('userSearchInput');
            const roleFilter = document.getElementById('roleFilter');
            
            if (searchInput) {
                searchInput.addEventListener('input', filterUsers);
            }
            
            if (roleFilter) {
                roleFilter.addEventListener('change', filterUsers);
            }
        });
    </script>
</body>
</html>
