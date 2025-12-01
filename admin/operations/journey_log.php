<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/journey_log.php');

$current_user = getCurrentUser();

// Handle form submissions
if ($_POST['action'] ?? '' === 'save_log') {
    $pilotName = $_POST['pilot_name'] ?? '';
    $logData = $_POST['log_data'] ?? '';
    $selectedDate = $_POST['selected_date'] ?? '';
    
    
    if (!empty($pilotName) && !empty($logData) && !empty($selectedDate)) {
        $logDataArray = json_decode($logData, true);
        
        // Save to pilot_journey_logs table (JSON format)
        $jsonSaved = savePilotJourneyLog($pilotName, $logDataArray);
        
        // Save form data to journey_log_entries table (structured format)
        $formData = $logDataArray['form_data'] ?? [];
        $structuredSaved = saveJourneyLogFormData($pilotName, $selectedDate, $formData);
        
        if ($jsonSaved && $structuredSaved) {
            $successMessage = "Journey log saved successfully for pilot: " . htmlspecialchars($pilotName) . " on " . $selectedDate;
        } else {
            $errorMessage = "Failed to save journey log. JSON: " . ($jsonSaved ? "OK" : "Failed") . ", Structured: " . ($structuredSaved ? "OK" : "Failed");
            // Add more details for debugging
            if (!$structuredSaved) {
                $errorMessage .= "<br><br><strong>Error Details:</strong><br>";
                // Try to get the last error from log file
                $logFile = __DIR__ . '/../../logs/journey_log_errors.log';
                if (file_exists($logFile)) {
                    $logContent = file_get_contents($logFile);
                    
                    // Try to parse the last JSON entry
                    $lines = explode("\n", $logContent);
                    $lastEntry = '';
                    $inEntry = false;
                    for ($i = count($lines) - 1; $i >= 0; $i--) {
                        if (strpos($lines[$i], '---') === 0) {
                            if ($inEntry) break;
                            $inEntry = true;
                        } elseif ($inEntry) {
                            $lastEntry = $lines[$i] . "\n" . $lastEntry;
                        }
                    }
                    
                    if (!empty($lastEntry)) {
                        // Try to extract JSON from the log entry
                        $jsonStart = strpos($lastEntry, '{');
                        if ($jsonStart !== false) {
                            $jsonStr = substr($lastEntry, $jsonStart);
                            $errorData = json_decode($jsonStr, true);
                            
                            if ($errorData && isset($errorData['error_message'])) {
                                $errorMessage .= "<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 5px; margin-bottom: 10px;'>";
                                $errorMessage .= "<strong>Error:</strong> " . htmlspecialchars($errorData['error_message']) . "<br>";
                                if (isset($errorData['file'])) {
                                    $errorMessage .= "<strong>File:</strong> " . htmlspecialchars($errorData['file']) . " (Line: " . ($errorData['line'] ?? 'N/A') . ")<br>";
                                }
                                $errorMessage .= "</div>";
                            }
                            
                            // Show full error details in a collapsible section
                            $errorMessage .= "<details style='margin-top: 10px;'>";
                            $errorMessage .= "<summary style='cursor: pointer; color: #0066cc; font-weight: bold;'>Click to view full error details</summary>";
                            $errorMessage .= "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 11px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; margin-top: 10px;'>" . htmlspecialchars(json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                            $errorMessage .= "</details>";
                        } else {
                            // Fallback to showing raw log content
                            $lastLines = array_slice($lines, -30);
                            $errorMessage .= "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 11px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;'>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
                        }
                    } else {
                        $lastLines = array_slice($lines, -30);
                        $errorMessage .= "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 11px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;'>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
                    }
                } else {
                    // Check PHP error log location
                    $phpErrorLog = ini_get('error_log');
                    if ($phpErrorLog && file_exists($phpErrorLog)) {
                        // Try to get last few lines from PHP error log
                        $phpLogContent = @file_get_contents($phpErrorLog);
                        if ($phpLogContent) {
                            $phpLines = explode("\n", $phpLogContent);
                            $lastPhpLines = array_slice($phpLines, -10);
                            $errorMessage .= "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 11px; max-height: 200px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;'>" . htmlspecialchars(implode("\n", $lastPhpLines)) . "</pre>";
                        } else {
                            $errorMessage .= "<p>Please check PHP error log at: " . htmlspecialchars($phpErrorLog) . "</p>";
                        }
                    } else {
                        $errorMessage .= "<p>Error log file not found. Please check server error logs.</p>";
                        $errorMessage .= "<p>Log file expected at: " . htmlspecialchars($logFile) . "</p>";
                    }
                }
            }
        }
    } else {
        $errorMessage = "Missing required data: pilot name, log data, or selected date.";
    }
}

// Get parameters
$selectedDate = $_GET['selected_date'] ?? '';
$selectedPilot = $_GET['selected_pilot'] ?? '';
$step = $_GET['step'] ?? '1'; // 1: Select Date, 2: Select Pilot, 3: Show Journey Log

// Handle PDF export
if ($_GET['export'] ?? '' === 'pdf') {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="journey_log_' . $selectedDate . '_' . str_replace(' ', '_', $selectedPilot) . '.pdf"');
    
    // Generate PDF content (simplified version)
    $pdfContent = generatePDFContent($selectedDate, $selectedDate, $selectedPilot);
    echo $pdfContent;
    exit;
}

// Get data based on current step
$availablePilots = [];
$pilotData = [];
$savedLogs = [];

if ($step >= '2' && !empty($selectedDate)) {
    // Get available pilots for the selected date
    $availablePilots = getAvailablePilotsForDate($selectedDate);
}

if ($step >= '3' && !empty($selectedDate) && !empty($selectedPilot)) {
    // Get journey log data for selected pilot
    $pilotData = getPilotJourneyLogData($selectedDate, $selectedDate, $selectedPilot);
    $savedLogs = getSavedPilotJourneyLogs($selectedPilot);
    
    // Get crew members from flights for this pilot and date
    $db = getDBConnection();
    
    // First, get the pilot's user ID if they exist in users table
    $pilotUserId = null;
    $pilotNamePattern = "%{$selectedPilot}%";
    $stmt = $db->prepare("
        SELECT id FROM users 
        WHERE CONCAT(first_name, ' ', last_name) LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$pilotNamePattern]);
    $pilotUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pilotUser) {
        $pilotUserId = $pilotUser['id'];
    }
    
    // Get flights for this pilot and date
    $whereConditions = [
        "DATE(f.FltDate) = ?",
        "f.TaskStart IS NOT NULL",
        "f.TaskEnd IS NOT NULL"
    ];
    $params = [$selectedDate];
    
    if ($pilotUserId) {
        $whereConditions[] = "(
            CONCAT(f.FirstName, ' ', f.LastName) LIKE ?
            OR f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR f.Crew4 = ? OR f.Crew5 = ?
            OR f.Crew6 = ? OR f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR f.Crew10 = ?
        )";
        $params = array_merge($params, [
            $pilotNamePattern,
            $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId,
            $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId
        ]);
    } else {
        $whereConditions[] = "CONCAT(f.FirstName, ' ', f.LastName) LIKE ?";
        $params[] = $pilotNamePattern;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    $stmt = $db->prepare("
        SELECT 
            f.id,
            f.ACType,
            f.Rego,
            f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
            f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
            f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
            f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
        FROM flights f
        WHERE {$whereClause}
        ORDER BY f.TaskStart ASC
    ");
    $stmt->execute($params);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get aircraft type and registration from flights
    $aircraftType = '';
    $aircraftReg = '';
    if (!empty($flights)) {
        // Get the most common ACType and Rego (or first one if all are same)
        $acTypes = array_filter(array_column($flights, 'ACType'));
        $regos = array_filter(array_column($flights, 'Rego'));
        
        if (!empty($acTypes)) {
            // Get the most frequent ACType
            $acTypeCounts = array_count_values($acTypes);
            arsort($acTypeCounts);
            $aircraftType = key($acTypeCounts);
        }
        
        if (!empty($regos)) {
            // Get the most frequent Rego
            $regoCounts = array_count_values($regos);
            arsort($regoCounts);
            $aircraftReg = key($regoCounts);
        }
    }
    
    // Collect all crew IDs
    $crewIds = [];
    $crewData = []; // Store crew data with role
    foreach ($flights as $flight) {
        for ($i = 1; $i <= 10; $i++) {
            $crewField = "Crew{$i}";
            $roleField = "Crew{$i}_role";
            if (!empty($flight[$crewField])) {
                $crewId = $flight[$crewField];
                $crewRole = $flight[$roleField] ?? '';
                if (!isset($crewData[$crewId])) {
                    $crewIds[$crewId] = true;
                    $crewData[$crewId] = [
                        'id' => $crewId,
                        'role' => $crewRole,
                        'flights' => []
                    ];
                }
                // Store role if not empty (prefer non-empty role)
                if (!empty($crewRole) && empty($crewData[$crewId]['role'])) {
                    $crewData[$crewId]['role'] = $crewRole;
                }
            }
        }
    }
    
    // Get crew member details from users table
    $crewMembers = [];
    if (!empty($crewIds)) {
        $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, national_id
            FROM users
            WHERE id IN ($placeholders)
        ");
        $stmt->execute(array_keys($crewIds));
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $crewId = $user['id'];
            if (isset($crewData[$crewId])) {
                $crewMembers[] = [
                    'id' => $crewId,
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'national_id' => $user['national_id'] ?? '',
                    'role' => $crewData[$crewId]['role'] ?? ''
                ];
            }
        }
    }
} else {
    $crewMembers = [];
}

// Function to generate PDF content
function generatePDFContent($startDate, $endDate, $pilotName) {
    $pilotData = getPilotJourneyLogData($startDate, $endDate, $pilotName);
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Journey Log Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 20px; }
            .pilot-section { margin-bottom: 30px; page-break-inside: avoid; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #000; padding: 4px; text-align: center; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .summary { margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>RAIMON AIRWAYS</h1>
            <h2>TECHNICAL & JOURNEY LOG</h2>
            <p>Date Range: ' . $startDate . ' to ' . $endDate . '</p>
        </div>';
    
    foreach ($pilotData as $pilotName => $pilotInfo) {
        $html .= '<div class="pilot-section">
            <h3>PILOT: ' . htmlspecialchars($pilotName) . '</h3>
            <p>Flights: ' . $pilotInfo['flight_count'] . ' | Block Time: ' . number_format($pilotInfo['total_block_time'] / 60, 1) . 'h | Air Time: ' . number_format($pilotInfo['total_air_time'] / 60, 1) . 'h</p>
            <table>
                <thead>
                    <tr>
                        <th>LEG No</th><th>Flight No</th><th>PC FO</th><th>FROM</th><th>TO</th>
                        <th>OFB</th><th>ONB</th><th>Block Time</th><th>ATD</th><th>ATA</th><th>Air Time</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($pilotInfo['flights'] as $index => $flight) {
            $html .= '<tr>
                <td>' . ($index + 1) . '</td>
                <td>' . htmlspecialchars($flight['TaskName'] ?: $flight['FlightNo'] ?: 'N/A') . '</td>
                <td>' . htmlspecialchars($flight['FirstName'] . ' ' . $flight['LastName']) . '</td>
                <td>' . (explode('-', $flight['Route'])[0] ?? '') . '</td>
                <td>' . (explode('-', $flight['Route'])[1] ?? '') . '</td>
                <td>' . ($flight['actual_out_utc'] ? date('H:i', strtotime($flight['actual_out_utc'])) : '') . '</td>
                <td>' . ($flight['actual_in_utc'] ? date('H:i', strtotime($flight['actual_in_utc'])) : '') . '</td>
                <td>' . ($flight['block_time_min'] ? number_format($flight['block_time_min'] / 60, 1) . 'h' : '') . '</td>
                <td>' . ($flight['actual_off_utc'] ? date('H:i', strtotime($flight['actual_off_utc'])) : '') . '</td>
                <td>' . ($flight['actual_on_utc'] ? date('H:i', strtotime($flight['actual_on_utc'])) : '') . '</td>
                <td>' . ($flight['air_time_min'] ? number_format($flight['air_time_min'] / 60, 1) . 'h' : '') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table></div>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical & Journey Log - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .log-form { 
            background: white; 
            border: 2px solid #374151; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .log-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
        }
        .section-title {
            background: #f3f4f6;
            border-left: 4px solid #3b82f6;
            font-weight: 600;
        }
        .table-header {
            background: #e5e7eb;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 0.875rem;
        }
        .time-input {
            width: 80px;
            text-align: center;
        }
        .fuel-input {
            width: 100px;
        }
        .oil-input {
            width: 60px;
        }
        .parameter-input {
            width: 70px;
        }
        @media print {
            .no-print { display: none !important; }
            .log-form { box-shadow: none; border: 1px solid #000; }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 no-print">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Technical & Journey Log</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">ICAO Annex 6 & EASA ORO.MLR.105 compliant flight timing records</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <!-- Dark Mode Toggle -->
                            <button onclick="toggleDarkMode()" 
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-moon dark:hidden"></i>
                                <i class="fas fa-sun hidden dark:inline"></i>
                            </button>
                            
                            <?php if ($step == '3'): ?>
                            <button onclick="saveJourneyLog()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Save Log
                            </button>
                            <button onclick="downloadPDF()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                                <i class="fas fa-file-pdf mr-2"></i>
                                Download PDF
                            </button>
                            <button onclick="printLog()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                                <i class="fas fa-print mr-2"></i>
                                Print Log
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Success/Error Messages -->
                <?php if (isset($successMessage)): ?>
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>
                <!-- Step-by-Step Selection -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg mb-6 no-print">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Journey Log Selection</h3>
                        <div class="flex items-center mt-2">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full <?php echo $step >= '1' ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600'; ?>">
                                    1
                                </div>
                                <span class="ml-2 text-sm <?php echo $step >= '1' ? 'text-blue-600 font-medium' : 'text-gray-500'; ?>">Select Date</span>
                            </div>
                            <div class="flex-1 h-0.5 mx-4 <?php echo $step >= '2' ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full <?php echo $step >= '2' ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600'; ?>">
                                    2
                                </div>
                                <span class="ml-2 text-sm <?php echo $step >= '2' ? 'text-blue-600 font-medium' : 'text-gray-500'; ?>">Select Pilot</span>
                            </div>
                            <div class="flex-1 h-0.5 mx-4 <?php echo $step >= '3' ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full <?php echo $step >= '3' ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600'; ?>">
                                    3
                                </div>
                                <span class="ml-2 text-sm <?php echo $step >= '3' ? 'text-blue-600 font-medium' : 'text-gray-500'; ?>">View Log</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if ($step == '1'): ?>
                            <!-- Step 1: Select Date -->
                            <form method="GET" class="max-w-md">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Select Date
                                    </label>
                                    <input type="date" name="selected_date" value="<?php echo htmlspecialchars($selectedDate); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <button type="submit" name="step" value="2"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-arrow-right mr-2"></i>
                                    Next: Select Pilot
                                </button>
                            </form>
                        <?php elseif ($step == '2' && !empty($availablePilots)): ?>
                            <!-- Step 2: Select Pilot -->
                            <div class="mb-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    Available pilots for <strong><?php echo date('M j, Y', strtotime($selectedDate)); ?></strong>:
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($availablePilots as $pilot): ?>
                                        <a href="?step=3&selected_date=<?php echo urlencode($selectedDate); ?>&selected_pilot=<?php echo urlencode($pilot['pilot_name']); ?>"
                                           class="block p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h4 class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($pilot['pilot_name']); ?></h4>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        <?php echo $pilot['flight_count']; ?> flights | 
                                                        <?php echo number_format($pilot['total_block_time'] / 60, 1); ?>h block time
                                                    </p>
                                                </div>
                                                <i class="fas fa-chevron-right text-gray-400"></i>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <a href="?step=1" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Date Selection
                            </a>
                        <?php elseif ($step == '2' && empty($availablePilots)): ?>
                            <!-- No pilots found -->
                            <div class="text-center py-8">
                                <i class="fas fa-user-slash text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 dark:text-gray-400">No pilots found for the selected date.</p>
                                <a href="?step=1" class="inline-flex items-center px-4 py-2 mt-4 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Date Selection
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics (only show in step 3) -->
                <?php if ($step == '3' && !empty($pilotData)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6 no-print">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-users text-blue-600 dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Selected Pilot</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($selectedPilot); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-plane text-green-600 dark:text-green-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Flights</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo !empty($pilotData) ? array_sum(array_column($pilotData, 'flight_count')) : 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-purple-600 dark:text-purple-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Block Time</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo !empty($pilotData) ? number_format(array_sum(array_column($pilotData, 'total_block_time')) / 60, 1) : '0.0'; ?>h</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-cloud text-yellow-600 dark:text-yellow-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Air Time</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo !empty($pilotData) ? number_format(array_sum(array_column($pilotData, 'total_air_time')) / 60, 1) : '0.0'; ?>h</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Technical & Journey Log Form (only show in step 3) -->
                <?php if ($step == '3' && !empty($selectedPilot)): ?>
                <div class="mb-8">
                    <!-- Date Header -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-4">
                        <h2 class="text-xl font-semibold text-blue-900 dark:text-blue-100">
                            <i class="fas fa-calendar-day mr-2"></i>
                            <?php echo date('l, F j, Y', strtotime($selectedDate)); ?>
                        </h2>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                            Journey Log for: <?php echo htmlspecialchars($selectedPilot); ?>
                        </p>
                    </div>

                    <!-- Form Content -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Form Header -->
                        <div class="bg-gradient-to-r from-gray-800 to-gray-900 px-6 py-4">
                            <div class="flex items-center justify-between">
                            <div>
                                    <h2 class="text-xl font-bold text-white">JOURNEY LOG/CAPTAIN'S BRIEF</h2>
                                    <p class="text-sm text-gray-300 mt-1">RAIMON AIRWAYS - Technical & Journey Log</p>
                            </div>
                            <div class="text-right">
                                    <div class="text-white text-sm">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($selectedPilot); ?>
                                    </div>
                                    <div class="text-gray-300 text-xs">
                                        <?php echo date('M j, Y', strtotime($selectedDate)); ?>
                                    </div>
                            </div>
                        </div>
                    </div>

                        <form id="journeyLogForm" class="log-form h-full bg-gray-50 dark:bg-gray-900">
                        <input type="hidden" name="pilot_name" value="<?php echo htmlspecialchars($selectedPilot); ?>">
                        <input type="hidden" name="selected_date" value="<?php echo $selectedDate; ?>">

                        <!-- Aircraft & Sector Table -->
                        <div class="mb-8">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-route mr-2 text-blue-600"></i>
                                    AIRCRAFT & SECTOR INFORMATION
                                </h3>
                            </div>
                            
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">AC Type</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">AC Reg</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SECTOR 1</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SECTOR 2</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SECTOR 3</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SECTOR 4</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">NO.</th>
                                            </tr>
                                        </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <input type="text" name="sector_aircraft_type" value="<?php echo htmlspecialchars($aircraftType ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center bg-gray-50 dark:bg-gray-700" readonly>
                                                </td>
                                            <td colspan="2" class="px-4 py-4 whitespace-nowrap">
                                                <input type="text" name="sector_aircraft_reg" value="<?php echo htmlspecialchars($aircraftReg ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center bg-gray-50 dark:bg-gray-700" readonly>
                                                </td>
                                            <td colspan="2" class="px-4 py-4 whitespace-nowrap">
                                                <input type="date" name="sector_date" value="<?php echo $selectedDate; ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" readonly>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector1_cm1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM1</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector1_cm2" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM2</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector2_cm1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM1</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector2_cm2" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM2</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector3_cm1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM1</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector3_cm2" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM2</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector4_cm1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM1</span>
                                                </label>
                                                </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <label class="flex items-center justify-center space-x-2">
                                                    <input type="checkbox" name="sector4_cm2" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="text-sm text-gray-900 dark:text-white">CM2</span>
                                                </label>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="sector_number" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0" min="1">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        <!-- Detailed Flight Data Table -->
                        <div class="mb-8">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-chart-line mr-2 text-blue-600"></i>
                                    DETAILED FLIGHT DATA
                                </h3>
                            </div>

                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <td colspan="2" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">FLIGHT DATA</td>
                                            <td colspan="2" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">SECTOR</td>
                                            <td colspan="8" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">BLOCK & FLIGHT TIME</td>
                                            <td colspan="4" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">
                                                FUEL KG ☐ / LBS □
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ATL NO.</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FLIGHT NO.</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FROM</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">TO</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">OFF BLOCK</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">T/O</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">LAND</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ON BLOCK</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">TRIP TIME</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FLIGHT TIME</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">NIGHT TIME</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">UP LIFT LTR</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">RAMP FUEL</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ARR FUEL</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">TOTAL</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FUEL PAGE NO.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php 
                                        // Get flights directly from database for DETAILED FLIGHT DATA
                                        $db = getDBConnection();
                                        
                                        // First, get the pilot's user ID if they exist in users table
                                        $pilotUserId = null;
                                        $pilotNamePattern = "%{$selectedPilot}%";
                                        $stmt = $db->prepare("
                                            SELECT id FROM users 
                                            WHERE CONCAT(first_name, ' ', last_name) LIKE ?
                                            LIMIT 1
                                        ");
                                        $stmt->execute([$pilotNamePattern]);
                                        $pilotUser = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($pilotUser) {
                                            $pilotUserId = $pilotUser['id'];
                                        }
                                        
                                        // Get flights for this pilot and date with all required fields
                                        $whereConditions = [
                                            "DATE(f.FltDate) = ?",
                                            "f.TaskStart IS NOT NULL",
                                            "f.TaskEnd IS NOT NULL"
                                        ];
                                        $params = [$selectedDate];
                                        
                                        if ($pilotUserId) {
                                            $whereConditions[] = "(
                                                CONCAT(f.FirstName, ' ', f.LastName) LIKE ?
                                                OR f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR f.Crew4 = ? OR f.Crew5 = ?
                                                OR f.Crew6 = ? OR f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR f.Crew10 = ?
                                            )";
                                            $params = array_merge($params, [
                                                $pilotNamePattern,
                                                $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId,
                                                $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId, $pilotUserId
                                            ]);
                                        } else {
                                            $whereConditions[] = "CONCAT(f.FirstName, ' ', f.LastName) LIKE ?";
                                            $params[] = $pilotNamePattern;
                                        }
                                        
                                        $whereClause = implode(' AND ', $whereConditions);
                                        $stmt = $db->prepare("
                                            SELECT 
                                                f.id,
                                                f.TaskName,
                                                f.FlightNo,
                                                f.Route,
                                                f.off_block,
                                                f.takeoff,
                                                f.landed,
                                                f.on_block,
                                                f.uplift_fuel
                                            FROM flights f
                                            WHERE {$whereClause}
                                            ORDER BY f.TaskStart ASC
                                            LIMIT 20
                                        ");
                                        $stmt->execute($params);
                                        $allFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Helper function to convert time from 1931 to 19:31 (for calculation)
                                        function convertTimeFormat($timeStr) {
                                            if (empty($timeStr)) return '';
                                            // Remove any non-numeric characters
                                            $timeStr = preg_replace('/[^0-9]/', '', $timeStr);
                                            // If length is 4, format as HH:MM
                                            if (strlen($timeStr) == 4) {
                                                return substr($timeStr, 0, 2) . ':' . substr($timeStr, 2, 2);
                                            }
                                            // If length is 3, assume it's HMM and pad with 0
                                            if (strlen($timeStr) == 3) {
                                                return '0' . substr($timeStr, 0, 1) . ':' . substr($timeStr, 1, 2);
                                            }
                                            return $timeStr;
                                        }
                                        
                                        // Helper function to normalize time format (remove non-numeric, ensure 4 digits)
                                        function normalizeTimeFormat($timeStr) {
                                            if (empty($timeStr)) return '';
                                            // Remove any non-numeric characters
                                            $timeStr = preg_replace('/[^0-9]/', '', $timeStr);
                                            // If length is 4, return as is
                                            if (strlen($timeStr) == 4) {
                                                return $timeStr;
                                            }
                                            // If length is 3, pad with 0 at the beginning
                                            if (strlen($timeStr) == 3) {
                                                return '0' . $timeStr;
                                            }
                                            // If length is less than 3, pad with zeros
                                            if (strlen($timeStr) < 3) {
                                                return str_pad($timeStr, 4, '0', STR_PAD_LEFT);
                                            }
                                            return $timeStr;
                                        }
                                        
                                        // Helper function to calculate time difference in HHMM format (e.g., 0125 for 1h 25m)
                                        function calculateTimeDifference($startTime, $endTime) {
                                            if (empty($startTime) || empty($endTime)) return '';
                                            
                                            // Convert to HH:MM format if needed for calculation
                                            $start = convertTimeFormat($startTime);
                                            $end = convertTimeFormat($endTime);
                                            
                                            if (empty($start) || empty($end)) return '';
                                            
                                            try {
                                                // Parse times
                                                $startParts = explode(':', $start);
                                                $endParts = explode(':', $end);
                                                
                                                if (count($startParts) != 2 || count($endParts) != 2) return '';
                                                
                                                $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
                                                $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
                                                
                                                // Handle next day (if end time is earlier than start time)
                                                if ($endMinutes < $startMinutes) {
                                                    $endMinutes += 24 * 60; // Add 24 hours
                                                }
                                                
                                                $diffMinutes = $endMinutes - $startMinutes;
                                                $hours = floor($diffMinutes / 60);
                                                $minutes = $diffMinutes % 60;
                                                
                                                // Return in HHMM format (e.g., 0125 for 1h 25m)
                                                return sprintf('%02d%02d', $hours, $minutes);
                                            } catch (Exception $e) {
                                                return '';
                                            }
                                        }
                                        
                                        // Ensure minimum 1 row and maximum 20 rows
                                        $rowCount = max(1, min(count($allFlights), 20));
                                        
                                        for ($i = 1; $i <= $rowCount; $i++): 
                                            $flight = isset($allFlights[$i-1]) ? $allFlights[$i-1] : null;
                                            
                                            // Get time values from database
                                            $offBlock = $flight ? ($flight['off_block'] ?? '') : '';
                                            $takeoff = $flight ? ($flight['takeoff'] ?? '') : '';
                                            $landed = $flight ? ($flight['landed'] ?? '') : '';
                                            $onBlock = $flight ? ($flight['on_block'] ?? '') : '';
                                            
                                            // Normalize to 4-digit format (e.g., 1931)
                                            $offBlockFormatted = normalizeTimeFormat($offBlock);
                                            $takeoffFormatted = normalizeTimeFormat($takeoff);
                                            $landedFormatted = normalizeTimeFormat($landed);
                                            $onBlockFormatted = normalizeTimeFormat($onBlock);
                                            
                                            // Calculate TRIP TIME (OFF BLOCK to ON BLOCK) in HHMM format
                                            $tripTime = calculateTimeDifference($offBlock, $onBlock);
                                            
                                            // Calculate FLIGHT TIME (T/O to LAND) in HHMM format
                                            $flightTime = calculateTimeDifference($takeoff, $landed);
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="atl_no_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="<?php echo $i; ?>" value="<?php echo $i; ?>">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="flight_no_<?php echo $i; ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="FL<?php echo $i; ?>" value="<?php echo $flight ? htmlspecialchars($flight['TaskName'] ?: $flight['FlightNo'] ?: '') : ''; ?>">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <?php 
                                                $routeParts = $flight && !empty($flight['Route']) ? explode('-', $flight['Route']) : [];
                                                $fromAirport = !empty($routeParts) ? trim($routeParts[0]) : '';
                                                ?>
                                                <input type="text" name="from_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="XXX" value="<?php echo htmlspecialchars($fromAirport); ?>">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <?php 
                                                $toAirport = !empty($routeParts) ? trim($routeParts[count($routeParts) - 1]) : '';
                                                ?>
                                                <input type="text" name="to_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="YYY" value="<?php echo htmlspecialchars($toAirport); ?>">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="off_block_<?php echo $i; ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0000" value="<?php echo htmlspecialchars($offBlockFormatted); ?>" maxlength="4" pattern="[0-9]{4}">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="takeoff_<?php echo $i; ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0000" value="<?php echo htmlspecialchars($takeoffFormatted); ?>" maxlength="4" pattern="[0-9]{4}">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="landing_<?php echo $i; ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0000" value="<?php echo htmlspecialchars($landedFormatted); ?>" maxlength="4" pattern="[0-9]{4}">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="on_block_<?php echo $i; ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0000" value="<?php echo htmlspecialchars($onBlockFormatted); ?>" maxlength="4" pattern="[0-9]{4}">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="trip_time_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0000" value="<?php echo htmlspecialchars($tripTime); ?>" maxlength="4" pattern="[0-9]{4}">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="flight_time_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0000" value="<?php echo htmlspecialchars($flightTime); ?>" maxlength="4" pattern="[0-9]{4}">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="night_time_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0:00">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="uplift_ltr_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0" step="0.1" value="<?php echo $flight && isset($flight['uplift_fuel']) ? $flight['uplift_fuel'] : ''; ?>">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="ramp_fuel_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0" step="0.1">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="arr_fuel_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0" step="0.1">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="total_fuel_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0" step="0.1">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="fuel_page_no_<?php echo $i; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0">
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>

                        <!-- Crew Detail & Commander Comments Table -->
                        <div class="mb-8">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-users mr-2 text-blue-600"></i>
                                    CREW DETAIL & COMMANDER COMMENTS
                                </h3>
                            </div>

                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <td colspan="8" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">CREW DETAIL</td>
                                            <td colspan="2" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">REPORTING</td>
                                            <td colspan="2" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">ENG SHUTDOWN</td>
                                            <td class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">FDP</td>
                                            <td colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">COMMANDER COMMENTS</td>
                                        </tr>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">RANK</th>
                                            <th colspan="5" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">CREW NAME</th>
                                            <th colspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">NATIONAL ID</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">HR</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MIN</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">HR</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MIN</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">TIME</th>
                                            <th colspan="3" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">COMMENTS</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php 
                                        // Get crew members from flights for this pilot and date
                                        // Ensure minimum 1 row and maximum 20 rows
                                        $crewRowCount = max(1, min(count($crewMembers ?? []), 20));
                                        
                                        // If no crew members found, show empty rows
                                        if (empty($crewMembers)) {
                                            $crewRowCount = 1;
                                        }
                                        
                                        for ($i = 0; $i < $crewRowCount; $i++): 
                                            $crewMember = isset($crewMembers[$i]) ? $crewMembers[$i] : null;
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <?php if ($crewMember): ?>
                                                    <input type="text" name="crew_rank_<?php echo $i + 1; ?>" value="<?php echo htmlspecialchars(strtoupper($crewMember['role'])); ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center bg-gray-50 dark:bg-gray-700" readonly>
                                                <?php else: ?>
                                                    <select name="crew_rank_<?php echo $i + 1; ?>" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center">
                                                        <option value="">Select Rank</option>
                                                        <?php
                                                        // Get cabin roles from database
                                                        $db = getDBConnection();
                                                        $stmt = $db->prepare("
                                                            SELECT code, label 
                                                            FROM cockpit_roles
                                                            WHERE is_active = 1 
                                                            ORDER BY sort_order, label
                                                        ");
                                                        $stmt->execute();
                                                        $cabinRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                        
                                                        foreach ($cabinRoles as $role) {
                                                            $code = htmlspecialchars($role['code']);
                                                            $label = htmlspecialchars($role['label']);
                                                            echo '<option value="' . $code . '">' . $code . ' - ' . $label . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                            <td colspan="5" class="px-4 py-4 whitespace-nowrap text-center">
                                                <?php if ($crewMember): ?>
                                                    <input type="text" name="crew_name_<?php echo $i + 1; ?>" value="<?php echo htmlspecialchars($crewMember['name']); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center bg-gray-50 dark:bg-gray-700" readonly>
                                                <?php else: ?>
                                                    <select name="crew_name_<?php echo $i + 1; ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center">
                                                        <option value="">Select Crew Member</option>
                                                        <?php
                                                        // Get flight crew members from database
                                                        $db = getDBConnection();
                                                        $stmt = $db->prepare("
                                                            SELECT id, first_name, last_name, position 
                                                            FROM users 
                                                            WHERE flight_crew = 1 
                                                            AND status = 'active' 
                                                            ORDER BY first_name, last_name
                                                        ");
                                                        $stmt->execute();
                                                        $allCrewMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                        
                                                        foreach ($allCrewMembers as $member) {
                                                            $fullName = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
                                                            $position = htmlspecialchars($member['position']);
                                                            echo '<option value="' . $fullName . '" data-position="' . $position . '">' . $fullName . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                            <td colspan="2" class="px-4 py-4 whitespace-nowrap text-center">
                                                <?php if ($crewMember): ?>
                                                    <input type="text" name="crew_national_id_<?php echo $i + 1; ?>" value="<?php echo htmlspecialchars($crewMember['national_id']); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center bg-gray-50 dark:bg-gray-700" readonly>
                                                <?php else: ?>
                                                    <input type="text" name="crew_national_id_<?php echo $i + 1; ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="ID Number">
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="reporting_hr_<?php echo $i + 1; ?>" class="w-12 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="00" min="0" max="23">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="reporting_min_<?php echo $i + 1; ?>" class="w-12 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="00" min="0" max="59">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="eng_shutdown_hr_<?php echo $i + 1; ?>" class="w-12 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="00" min="0" max="23">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="number" name="eng_shutdown_min_<?php echo $i + 1; ?>" class="w-12 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="00" min="0" max="59">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <input type="text" name="fdp_time_<?php echo $i + 1; ?>" class="w-16 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center" placeholder="0:00">
                                            </td>
                                            <?php if ($i == 0): ?>
                                            <td colspan="3" rowspan="<?php echo $crewRowCount; ?>" class="px-4 py-4 align-top">
                                                <textarea name="commander_comments" rows="<?php echo max(10, $crewRowCount * 2); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white h-full resize-none" placeholder="Enter commander comments, observations, and any important notes here..."></textarea>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Footer with definitions and signature -->
                            <div class="mt-4 flex justify-between items-end">
                                <div class="text-sm text-gray-600">
                                    <p>*CVR : COMMANDER VOYAGE REPORT FILED</p>
                                    <p>**ASR: AIR SAFETY REPORT</p>
                                    </div>
                                <div class="text-right">
                                    <div class="border border-gray-300 bg-gray-50 px-4 py-2 w-48 text-center">
                                        <span class="text-sm font-medium">SIGN BY CMD</span>
                                    </div>
                                    <input type="text" name="commander_signature" class="input-field w-48 text-center mt-2" placeholder="Commander Name">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script>
        // Dark Mode Functions
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            
            if (isDark) {
                html.classList.remove('dark');
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                localStorage.setItem('darkMode', 'true');
            }
        }
        
        function initDarkMode() {
            const savedMode = localStorage.getItem('darkMode');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedMode === 'true' || (savedMode === null && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        
        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
        });
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('darkMode') === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });

        function printLog() {
            window.print();
        }

        function saveJourneyLog() {
            const pilotName = '<?php echo addslashes($selectedPilot); ?>';
            const selectedDate = '<?php echo $selectedDate; ?>';
            
            if (!pilotName) {
                alert('No pilot selected');
                return;
            }
            
            if (!selectedDate) {
                alert('No date selected');
                return;
            }
            
            // Collect all form data
            const logData = {
                pilot_name: pilotName,
                selected_date: selectedDate,
                form_data: collectFormData(),
                timestamp: new Date().toISOString()
            };
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            
            // Create input elements individually to avoid template literal issues
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'save_log';
            form.appendChild(actionInput);
            
            const pilotInput = document.createElement('input');
            pilotInput.type = 'hidden';
            pilotInput.name = 'pilot_name';
            pilotInput.value = pilotName;
            form.appendChild(pilotInput);
            
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'selected_date';
            dateInput.value = selectedDate;
            form.appendChild(dateInput);
            
            const logDataInput = document.createElement('input');
            logDataInput.type = 'hidden';
            logDataInput.name = 'log_data';
            logDataInput.value = JSON.stringify(logData);
            form.appendChild(logDataInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function downloadPDF() {
            // Get current parameters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            
            // Create download link
            const downloadUrl = window.location.pathname + '?' + params.toString();
            window.open(downloadUrl, '_blank');
        }

        function collectFormData() {
            const formData = {};
            const inputs = document.querySelectorAll('.log-form input, .log-form select, .log-form textarea');
            
            console.log('Found inputs:', inputs.length);
            
            inputs.forEach(input => {
                if (input.name || input.id) {
                    const key = input.name || input.id;
                    if (input.type === 'checkbox') {
                        formData[key] = input.checked;
                    } else {
                    formData[key] = input.value;
                    }
                    console.log('Collected:', key, '=', formData[key]);
                }
            });
            
            console.log('Total form data collected:', Object.keys(formData).length);
            return formData;
        }

        // Auto-calculate block time and air time
        function calculateTimes() {
            const timeInputs = document.querySelectorAll('input[type="time"]');
            timeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const row = this.closest('tr');
                    if (row) {
                        const ofbInput = row.querySelector('input[type="time"]:nth-of-type(1)');
                        const onbInput = row.querySelector('input[type="time"]:nth-of-type(2)');
                        const atdInput = row.querySelector('input[type="time"]:nth-of-type(3)');
                        const ataInput = row.querySelector('input[type="time"]:nth-of-type(4)');
                        
                        if (ofbInput && onbInput && ofbInput.value && onbInput.value) {
                            const blockTime = calculateTimeDifference(ofbInput.value, onbInput.value);
                            const blockTimeInput = row.querySelector('input[placeholder*="Block"]') || row.querySelector('input[value*="h"]');
                            if (blockTimeInput) {
                                blockTimeInput.value = blockTime + 'h';
                            }
                        }
                        
                        if (atdInput && ataInput && atdInput.value && ataInput.value) {
                            const airTime = calculateTimeDifference(atdInput.value, ataInput.value);
                            const airTimeInput = row.querySelector('input[placeholder*="Air"]') || row.querySelector('input[value*="h"]');
                            if (airTimeInput) {
                                airTimeInput.value = airTime + 'h';
                            }
                        }
                    }
                });
            });
        }

        function calculateTimeDifference(startTime, endTime) {
            const start = new Date('2000-01-01 ' + startTime);
            const end = new Date('2000-01-01 ' + endTime);
            
            if (end < start) {
                end.setDate(end.getDate() + 1); // Next day
            }
            
            const diffMs = end - start;
            const diffHours = diffMs / (1000 * 60 * 60);
            return diffHours.toFixed(1);
        }

        // Initialize calculations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            calculateTimes();
            initializeCrewSelection();
        });

        // Initialize crew member selection functionality
        function initializeCrewSelection() {
            const crewSelects = document.querySelectorAll('select[name^="crew_name_"]');
            
            crewSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const position = selectedOption.getAttribute('data-position');
                    const row = this.closest('tr');
                    const rankSelect = row.querySelector('select[name^="crew_rank_"]');
                    
                    if (rankSelect && position) {
                        // Map position to rank code
                        let rankCode = '';
                        if (position.toLowerCase().includes('captain') || position.toLowerCase().includes('commander')) {
                            rankCode = 'CAPT';
                        } else if (position.toLowerCase().includes('first officer') || position.toLowerCase().includes('co-pilot')) {
                            rankCode = 'FO';
                        } else if (position.toLowerCase().includes('flight engineer')) {
                            rankCode = 'FE';
                        } else if (position.toLowerCase().includes('flight attendant') || position.toLowerCase().includes('cabin crew')) {
                            rankCode = 'FA';
                        } else {
                            rankCode = position.substring(0, 4).toUpperCase();
                        }
                        
                        // Find and select the matching option
                        const options = rankSelect.querySelectorAll('option');
                        for (let option of options) {
                            if (option.value === rankCode) {
                                rankSelect.value = rankCode;
                                break;
                            }
                        }
                    }
                });
            });
        }
    </script>
</body>
</html>
