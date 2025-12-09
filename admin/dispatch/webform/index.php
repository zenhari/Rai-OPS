<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/dispatch/webform/index.php');

$current_user = getCurrentUser();

// Get stations for dropdown
$stations = getStations();

// Handle flight selection
$selectedDate = isset($_GET['date']) ? $_GET['date'] : (isset($_POST['search_date']) ? $_POST['search_date'] : '');
$selectedTaskName = isset($_GET['task_name']) ? $_GET['task_name'] : '';
$actionType = isset($_GET['action']) ? $_GET['action'] : ''; // 'document' or 'follow'

// Get flights for selected date
$flights = [];
if (!empty($selectedDate)) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT DISTINCT TaskName, Route, Rego
            FROM flights
            WHERE DATE(FltDate) = ?
            AND TaskName IS NOT NULL
            AND TaskName != ''
            ORDER BY TaskName ASC
        ");
        $stmt->execute([$selectedDate]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching flights: " . $e->getMessage());
    }
}

// If flight is selected, get flight details
$selectedFlight = null;
$crewMembers = [];
if (!empty($selectedTaskName) && !empty($selectedDate)) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT TaskName, Route, Rego, FltDate, TaskStart, TaskEnd,
                   Crew1, Crew2, Crew3, Crew4, Crew5, Crew6, Crew7, Crew8, Crew9, Crew10,
                   Crew1_role, Crew2_role, Crew3_role, Crew4_role, Crew5_role,
                   Crew6_role, Crew7_role, Crew8_role, Crew9_role, Crew10_role
            FROM flights
            WHERE DATE(FltDate) = ? AND TaskName = ?
            LIMIT 1
        ");
        $stmt->execute([$selectedDate, $selectedTaskName]);
        $selectedFlight = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get crew member details
        if ($selectedFlight) {
            for ($i = 1; $i <= 10; $i++) {
                $crewId = $selectedFlight["Crew{$i}"] ?? null;
                $crewRole = $selectedFlight["Crew{$i}_role"] ?? '';
                
                if (!empty($crewId) && is_numeric($crewId)) {
                    try {
                        $userStmt = $db->prepare("
                            SELECT id, first_name, last_name
                            FROM users
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $userStmt->execute([$crewId]);
                        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user) {
                            $crewMembers[] = [
                                'crew_number' => $i,
                                'id' => $user['id'],
                                'first_name' => $user['first_name'],
                                'last_name' => $user['last_name'],
                                'role' => $crewRole
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching crew member {$i}: " . $e->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching flight details: " . $e->getMessage());
    }
}

// Parse Route to get origin and destination
$origin = '';
$destination = '';
$taskStartDate = '';
$taskStartTime = '';
$taskEndDate = '';
$taskEndTime = '';

if ($selectedFlight && !empty($selectedFlight['Route'])) {
    $routeParts = explode('-', trim($selectedFlight['Route']));
    if (count($routeParts) >= 2) {
        $origin = trim($routeParts[0]);
        $destination = trim($routeParts[1]);
    }
}

// Parse TaskStart and TaskEnd
if ($selectedFlight && !empty($selectedFlight['TaskStart'])) {
    $taskStart = new DateTime($selectedFlight['TaskStart']);
    $taskStartDate = $taskStart->format('Y-m-d');
    $taskStartTime = $taskStart->format('Hi');
}

if ($selectedFlight && !empty($selectedFlight['TaskEnd'])) {
    $taskEnd = new DateTime($selectedFlight['TaskEnd']);
    $taskEndDate = $taskEnd->format('Y-m-d');
    $taskEndTime = $taskEnd->format('Hi');
}

// Get MEL/CDL items from API
$melItems = [];
if ($selectedFlight) {
    try {
        $apiUrl = 'http://etl.raimonairways.net/api/mel_items.php';
        $apiKey = '91d692cf-6b08-4fce-a2e2-fa9505192faa';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'key: ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([]),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $httpCode === 200) {
            $apiData = json_decode($response, true);
            if (isset($apiData['data']) && is_array($apiData['data'])) {
                // Filter items where reference_revno_date is not "Nill | Nill" or "NIL | Nill" (case-insensitive)
                foreach ($apiData['data'] as $item) {
                    $referenceRevnoDate = $item['reference_revno_date'] ?? '';
                    
                    // Split by "|" and check if both parts are "Nill" or "NIL" (case-insensitive)
                    $parts = explode('|', $referenceRevnoDate);
                    if (count($parts) === 2) {
                        $part1 = trim($parts[0]);
                        $part2 = trim($parts[1]);
                        
                        // Check if both parts are "Nill" or "NIL" (case-insensitive)
                        $part1IsNill = (strcasecmp($part1, 'Nill') === 0 || strcasecmp($part1, 'NIL') === 0);
                        $part2IsNill = (strcasecmp($part2, 'Nill') === 0 || strcasecmp($part2, 'NIL') === 0);
                        
                        // Only add if NOT both parts are "Nill" or "NIL"
                        if (!($part1IsNill && $part2IsNill)) {
                            $melItems[] = $item;
                        }
                    } else {
                        // If format is unexpected, include it anyway
                        $melItems[] = $item;
                    }
                }
            }
        } else {
            error_log("MEL/CDL API Error: HTTP $httpCode - $curlError");
        }
    } catch (Exception $e) {
        error_log("Error fetching MEL/CDL items: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Handover - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { 
            font-family: 'Roboto', sans-serif; 
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .print-content, .print-content * {
                visibility: visible;
            }
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        .form-table {
            border-collapse: collapse;
            width: 100%;
        }
        .form-table td, .form-table th {
            border: 1px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 14px;
            text-align: left;
            vertical-align: middle;
        }
        .dark .form-table td, .dark .form-table th {
            border-color: #4b5563;
        }
        .form-table th {
            background-color: #f9fafb;
            font-weight: 600;
            text-align: center;
            font-size: 13px;
            letter-spacing: 0.05em;
        }
        .dark .form-table th {
            background-color: #e5e7eb;
            color: #111827;
        }
        .checkbox-cell {
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #3b82f6;
        }
        @media print {
            .form-table {
                font-size: 10px;
            }
            .form-table td, .form-table th {
                padding: 6px 8px;
            }
        }
        /* Modern table styles */
        .modern-table {
            min-width: 100%;
        }
        .modern-table thead {
            background-color: #f9fafb;
        }
        .dark .modern-table thead {
            background-color: #374151;
        }
        .modern-table thead th {
            padding: 12px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .dark .modern-table thead th {
            color: #d1d5db;
        }
        .modern-table tbody {
            background-color: #ffffff;
        }
        .dark .modern-table tbody {
            background-color: #1f2937;
        }
        .modern-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .dark .modern-table tbody tr {
            border-bottom-color: #4b5563;
        }
        .modern-table tbody tr:hover {
            background-color: #f9fafb;
        }
        .dark .modern-table tbody tr:hover {
            background-color: #374151;
        }
        .modern-table tbody td {
            padding: 16px 24px;
            white-space: nowrap;
            font-size: 14px;
            color: #111827;
        }
        .dark .modern-table tbody td {
            color: #f9fafb;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 no-print">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Dispatch Handover</h1>
                            <p class="text-base text-gray-600 dark:text-gray-400 mt-2 font-medium">Flight dispatch handover form</p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="window.print()" 
                                    class="inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow-md">
                                <i class="fas fa-print mr-2.5"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-8">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <?php if (empty($selectedFlight)): ?>
                <!-- Flight Selection Page -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Flight</h2>
                    
                    <!-- Date Selection Form -->
                    <form method="POST" action="" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="search_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Date *</label>
                                <input type="date" id="search_date" name="search_date" value="<?php echo htmlspecialchars($selectedDate); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <i class="fas fa-search mr-2"></i>Search Flights
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (!empty($selectedDate)): ?>
                        <?php if (empty($flights)): ?>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-4">
                                <p class="text-sm text-yellow-800 dark:text-yellow-200">No flights found for the selected date.</p>
                            </div>
                        <?php else: ?>
                            <!-- Flights List -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Name (Flight Number)</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rego</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($flights as $flight): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($flight['TaskName']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($flight['Rego'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                <div class="flex justify-center space-x-2">
                                                    <a href="?date=<?php echo urlencode($selectedDate); ?>&task_name=<?php echo urlencode($flight['TaskName']); ?>&action=document" 
                                                       class="inline-flex items-center px-3 py-1.5 border border-blue-300 dark:border-blue-600 text-sm font-medium rounded-md text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40">
                                                        <i class="fas fa-file-alt mr-1.5"></i>Complete Documents
                                                    </a>
                                                    <a href="?date=<?php echo urlencode($selectedDate); ?>&task_name=<?php echo urlencode($flight['TaskName']); ?>&action=follow" 
                                                       class="inline-flex items-center px-3 py-1.5 border border-green-300 dark:border-green-600 text-sm font-medium rounded-md text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40">
                                                        <i class="fas fa-plane mr-1.5"></i>Follow Flight
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Basic Flight Information Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6 no-print">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Flight Information</h2>
                    
                    <!-- Row 1: Flight Date, Flight Number, and Aircraft Register -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="FltDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Date *</label>
                            <input type="date" id="FltDate" name="FltDate" value="<?php echo htmlspecialchars($selectedDate); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label for="FlightNo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Number</label>
                            <input type="text" id="FlightNo" name="FlightNo" value="<?php echo htmlspecialchars($selectedFlight['TaskName'] ?? ''); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                        </div>
                        <div>
                            <label for="Rego" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Register</label>
                            <input type="text" id="Rego" name="Rego" value="<?php echo htmlspecialchars($selectedFlight['Rego'] ?? ''); ?>" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                        </div>
                    </div>
                    
                    <!-- Row 2: Origin, Destination, and Route Preview -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="origin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Departure</label>
                            <select id="origin" name="origin" onchange="updateRoutePreview()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select Origin --</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo htmlspecialchars($station['iata_code']); ?>" <?php echo ($origin && $station['iata_code'] == $origin) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station['iata_code']); ?> - <?php echo htmlspecialchars($station['station_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="destination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destination</label>
                            <select id="destination" name="destination" onchange="updateRoutePreview()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select Destination --</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo htmlspecialchars($station['iata_code']); ?>" <?php echo ($destination && $station['iata_code'] == $destination) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station['iata_code']); ?> - <?php echo htmlspecialchars($station['station_name']); ?>
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
                    
                    <!-- Row 2.5: TYPE OF FLIGHT -->
                    <div class="mb-6">
                        <label for="TypeOfFlight" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">TYPE OF FLIGHT</label>
                        <div class="relative">
                            <input type="text" id="TypeOfFlight" name="TypeOfFlight" readonly 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white cursor-pointer focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                   onclick="toggleFlightTypeOptions()"
                                   placeholder="-- Select Type of Flight --">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                            <div id="flightTypeOptions" class="options absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto hidden">
                                <div class="option px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-900 dark:text-white" data-value="Revenue" data-text="Revenue" onclick="selectOption(this, 'TypeOfFlight')">Revenue</div>
                                <div class="option px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-900 dark:text-white" data-value="Navigation Flight" data-text="Navigation Flight" onclick="selectOption(this, 'TypeOfFlight')">Navigation Flight</div>
                                <div class="option px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-900 dark:text-white" data-value="Training Flight" data-text="Training Flight" onclick="selectOption(this, 'TypeOfFlight')">Training Flight</div>
                                <div class="option px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-900 dark:text-white" data-value="Positioning Flight" data-text="Positioning Flight" onclick="selectOption(this, 'TypeOfFlight')">Positioning Flight</div>
                                <div class="option px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-900 dark:text-white" data-value="Ferry Flight" data-text="Ferry Flight" onclick="selectOption(this, 'TypeOfFlight')">Ferry Flight</div>
                                <div class="option px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-900 dark:text-white" data-value="Re-Posisioning" data-text="Re-Posisioning" onclick="selectOption(this, 'TypeOfFlight')">Re-Posisioning</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Row 3: Task Start and Task End -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Start</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" id="TaskStartDate" name="TaskStartDate" value="<?php echo htmlspecialchars($taskStartDate); ?>" onchange="updateTaskStart()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <input type="text" id="TaskStartTime" name="TaskStartTime" value="<?php echo htmlspecialchars($taskStartTime); ?>" placeholder="HHMM" maxlength="4" pattern="[0-9]{4}" oninput="formatTimeInput(this); updateTaskStart();" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <input type="hidden" id="TaskStart" name="TaskStart">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task End</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" id="TaskEndDate" name="TaskEndDate" value="<?php echo htmlspecialchars($taskEndDate); ?>" onchange="updateTaskEnd()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <input type="text" id="TaskEndTime" name="TaskEndTime" value="<?php echo htmlspecialchars($taskEndTime); ?>" placeholder="HHMM" maxlength="4" pattern="[0-9]{4}" oninput="formatTimeInput(this); updateTaskEnd();" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <input type="hidden" id="TaskEnd" name="TaskEnd">
                        </div>
                    </div>
                    
                    <!-- Row 4: FLIGHT RULES and FLIGHT PERM -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">FLIGHT RULES</label>
                            <div class="flex items-center space-x-6">
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="flight_rules_I" value="I" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">I</span>
                                </label>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="flight_rules_Y" value="Y" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Y</span>
                                </label>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="flight_rules_V" value="V" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">V</span>
                                </label>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="flight_rules_Z" value="Z" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Z</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">FLIGHT PERM</label>
                            <div class="flex items-center">
                                <input type="checkbox" name="flight_perm" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Row 5: WX analyzed -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">WX analyzed</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="wx_dep_ad" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DEP AD</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="wx_dstn_ad" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DSTN AD</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="wx_dstn_altn" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DSTN ALTN</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="wx_other_altn" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">OTHER ALTN</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Row 6: NOTAM -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">NOTAM</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="notam_dep_ad" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DEP AD</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="notam_dstn_ad" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DSTN AD</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="notam_dstn_altn" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DSTN ALTN</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="notam_other_altn" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm text-gray-700 dark:text-gray-300">OTHER ALTN</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Row 7: MEL/CDL Reference -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">MEL/CDL Reference</label>
                        <?php if (!empty($melItems)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ETL Number</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Report Number</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MEL Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference Rev No Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MEL/CDL Limitation</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($melItems as $index => $item): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($item['etl_number'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($item['report_number'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($item['mel_cat'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($item['reference_revno_date'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="text" 
                                                       name="mel_limitation[<?php echo htmlspecialchars($item['id'] ?? $index); ?>]" 
                                                       id="mel_limitation_<?php echo htmlspecialchars($item['id'] ?? $index); ?>"
                                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm"
                                                       placeholder="Enter limitation...">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400 text-center">No MEL/CDL items found or API error occurred.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Crew Information -->
                <?php if (!empty($crewMembers)): ?>
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6 no-print">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Crew Information</h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($crewMembers as $crew): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($crew['role'] ?: 'N/A'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($crewMembers)): ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No crew members assigned to this flight.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update Route Preview
        function updateRoutePreview() {
            const origin = document.getElementById('origin').value;
            const destination = document.getElementById('destination').value;
            const routePreview = document.getElementById('route-preview-container');
            
            if (origin && destination) {
                routePreview.innerHTML = `<span class="text-sm font-medium text-gray-900 dark:text-white">${origin} - ${destination}</span>`;
            } else {
                routePreview.innerHTML = '<span class="text-sm">Select origin and destination to see route preview</span>';
            }
        }

        // Update Task Name from Flight Number
        function updateTaskNameFromFlightNo() {
            const flightNo = document.getElementById('FlightNo').value;
            // You can add logic here to update task name if needed
            console.log('Flight Number changed:', flightNo);
        }

        // Format Time Input (HHMM)
        function formatTimeInput(input) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 4) {
                value = value.substring(0, 4);
            }
            input.value = value;
        }


        // Update Task Start (combine date and time)
        function updateTaskStart() {
            const date = document.getElementById('TaskStartDate').value;
            const time = document.getElementById('TaskStartTime').value;
            const taskStart = document.getElementById('TaskStart');
            
            if (date && time && time.length === 4) {
                const hour = time.substring(0, 2);
                const minute = time.substring(2, 4);
                taskStart.value = date + ' ' + hour + ':' + minute + ':00';
            } else if (date) {
                taskStart.value = date + ' 00:00:00';
            } else {
                taskStart.value = '';
            }
        }

        // Update Task End (combine date and time)
        function updateTaskEnd() {
            const date = document.getElementById('TaskEndDate').value;
            const time = document.getElementById('TaskEndTime').value;
            const taskEnd = document.getElementById('TaskEnd');
            
            if (date && time && time.length === 4) {
                const hour = time.substring(0, 2);
                const minute = time.substring(2, 4);
                taskEnd.value = date + ' ' + hour + ':' + minute + ':00';
            } else if (date) {
                taskEnd.value = date + ' 00:00:00';
            } else {
                taskEnd.value = '';
            }
        }

        // Toggle Flight Type Options dropdown
        function toggleFlightTypeOptions() {
            const options = document.getElementById('flightTypeOptions');
            if (options) {
                options.classList.toggle('hidden');
            }
        }

        // Select option from dropdown
        function selectOption(element, fieldName) {
            const value = element.getAttribute('data-value');
            const text = element.getAttribute('data-text');
            const input = document.getElementById(fieldName);
            const options = document.getElementById('flightTypeOptions');
            
            if (input) {
                input.value = text;
            }
            
            if (options) {
                options.classList.add('hidden');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const flightTypeInput = document.getElementById('TypeOfFlight');
            const flightTypeOptions = document.getElementById('flightTypeOptions');
            
            if (flightTypeInput && flightTypeOptions && 
                !flightTypeInput.contains(event.target) && 
                !flightTypeOptions.contains(event.target)) {
                flightTypeOptions.classList.add('hidden');
            }
        });

        // Initialize: Set Task End Date to Task Start Date when Task Start Date changes
        document.addEventListener('DOMContentLoaded', function() {
            const taskStartDate = document.getElementById('TaskStartDate');
            const taskEndDate = document.getElementById('TaskEndDate');
            
            if (taskStartDate && taskEndDate) {
                taskStartDate.addEventListener('change', function() {
                    if (taskStartDate.value) {
                        taskEndDate.value = taskStartDate.value;
                        updateTaskEnd();
                    }
                });
            }
            
            // Initialize route preview if origin and destination are already selected
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            if (origin && destination && origin.value && destination.value) {
                updateRoutePreview();
            }
        });
    </script>
</body>
</html>
