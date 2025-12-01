<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/etl_report/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$etlData = null;
$selectedDate = date('Y-m-d');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'fetch_etl_data') {
        $selectedDate = $_POST['date'] ?? date('Y-m-d');
        
        // Fetch ETL data from external API
        $etlData = fetchETLData($selectedDate);
        
        if ($etlData === false) {
            $error = 'Failed to fetch ETL data from external API.';
        } elseif (empty($etlData)) {
            $message = 'No ETL data found for the selected date.';
        } else {
            $message = 'ETL data fetched successfully.';
        }
    }
}

function fetchETLData($date) {
    $baseUrl = 'etl.raimonairways.net/api/rai-fleet/maintenace.php';
    $token = '75ad6a01-b7a2-49bc-8bde-38061fac8a13';
    
    $data = [
        'date' => $date,
        'token' => $token
    ];
    
    // Try multiple URL configurations: https with SSL, https without SSL, and http
    $urls = [
        ['url' => 'https://' . $baseUrl, 'ssl_verify' => true],
        ['url' => 'https://' . $baseUrl, 'ssl_verify' => false],
        ['url' => 'http://' . $baseUrl, 'ssl_verify' => false]
    ];
    
    $lastError = null;
    
    foreach ($urls as $urlConfig) {
        $url = $urlConfig['url'];
        $sslVerify = $urlConfig['ssl_verify'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Support all encodings
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        // If successful, return the response
        if (!$curlError && $httpCode === 200 && !empty($response)) {
            $decodedResponse = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE && 
                $decodedResponse && 
                isset($decodedResponse['success']) && 
                $decodedResponse['success']) {
                error_log("ETL API Success: Connected via $url (Time: {$totalTime}s)");
                return $decodedResponse;
            }
        }
        
        // Log error for this attempt
        $errorMsg = "ETL API Attempt failed for $url: ";
        if ($curlError) {
            $errorMsg .= "cURL Error #$curlErrno: $curlError";
        } elseif ($httpCode !== 200) {
            $errorMsg .= "HTTP Code: $httpCode";
        } else {
            $errorMsg .= "Invalid response or JSON error";
        }
        $errorMsg .= " (Time: {$totalTime}s)";
        error_log($errorMsg);
        
        $lastError = $errorMsg;
    }
    
    // All attempts failed
    error_log("ETL API All connection attempts failed. Last error: " . $lastError);
    return false;
}

function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

function formatMaintenanceDetails($details) {
    if (empty($details)) return 'No maintenance details';
    
    $formatted = [];
    foreach ($details as $detail) {
        $maintenance = [];
        
        // Engine status
        if (!empty($detail['engine'])) {
            $engine = json_decode($detail['engine'], true);
            if ($engine) {
                $engineStatus = [];
                foreach ($engine as $key => $value) {
                    if ($value == '1') {
                        $engineStatus[] = ucfirst($key);
                    }
                }
                if (!empty($engineStatus)) {
                    $maintenance[] = 'Engine: ' . implode(', ', $engineStatus);
                }
            }
        }
        
        // System status
        if (!empty($detail['sys'])) {
            $sys = json_decode($detail['sys'], true);
            if ($sys) {
                $sysStatus = [];
                foreach ($sys as $key => $value) {
                    if ($value == '1') {
                        $sysStatus[] = ucfirst($key);
                    }
                }
                if (!empty($sysStatus)) {
                    $maintenance[] = 'System: ' . implode(', ', $sysStatus);
                }
            }
        }
        
        // Daily maintenance
        if (!empty($detail['daily']) && $detail['daily']) {
            $dailyTime = $detail['hour_daily_time'] . ':' . $detail['minute_daily_time'];
            $maintenance[] = "Daily: {$dailyTime} at {$detail['daily_station']}";
        }
        
        // Preflight maintenance
        if (!empty($detail['preflight']) && $detail['preflight']) {
            $preflightTime = $detail['hour_preflight_time'] . ':' . $detail['minute_preflight_time'];
            $maintenance[] = "Preflight: {$preflightTime} at {$detail['preflight_station']}";
        }
        
        if (!empty($maintenance)) {
            $formatted[] = implode(' | ', $maintenance);
        }
    }
    
    return !empty($formatted) ? implode('<br>', $formatted) : 'No maintenance details';
}

function formatOperationsDetails($details) {
    if (empty($details)) return 'No operations details';
    
    $formatted = [];
    foreach ($details as $detail) {
        $operation = [];
        
        if (!empty($detail['flight_number'])) {
            $operation[] = "Flight: {$detail['flight_number']}";
        }
        
        if (!empty($detail['origin']) && !empty($detail['destination'])) {
            $operation[] = "Route: {$detail['origin']} - {$detail['destination']}";
        }
        
        if (!empty($detail['pic_details'])) {
            $pic = $detail['pic_details'];
            $operation[] = "PIC: {$pic['first_name']} {$pic['last_name']}";
        }
        
        if (!empty($detail['fo_details'])) {
            $fo = $detail['fo_details'];
            $operation[] = "FO: {$fo['first_name']} {$fo['last_name']}";
        }
        
        if (!empty($detail['on_board_litre'])) {
            $operation[] = "Fuel: {$detail['on_board_litre']}L";
        }
        
        if (!empty($operation)) {
            $formatted[] = implode(' | ', $operation);
        }
    }
    
    return !empty($formatted) ? implode('<br>', $formatted) : 'No operations details';
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETL Report - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ETL Report</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Electronic Technical Log Report from External API</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="../" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Fleet
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
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

                <!-- Date Selection Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Date</h2>
                    <form method="POST" class="flex items-end space-x-4">
                        <input type="hidden" name="action" value="fetch_etl_data">
                        <div class="flex-1">
                            <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Report Date</label>
                            <input type="date" id="date" name="date" value="<?php echo safeOutput($selectedDate); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-sync mr-2"></i>
                            Fetch ETL Data
                        </button>
                    </form>
                </div>

                <!-- ETL Data Display -->
                <?php if ($etlData): ?>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar-day text-blue-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Report Date</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo safeOutput($etlData['date']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-list text-green-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Records</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo safeOutput($etlData['total_records']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-plane text-purple-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Aircraft Count</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo safeOutput($etlData['aircraft_count']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ETL Data Table -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">ETL Data Details</h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Detailed ETL information for each aircraft</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="exportETLData()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-download mr-2"></i>
                                        Export
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ETL Number</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Maintenance</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Operations</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($etlData['data'])): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No ETL data found for the selected date.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $recordIndex = 0;
                                        foreach ($etlData['data'] as $aircraftReg => $records): ?>
                                            <?php foreach ($records as $index => $record): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0">
                                                                <i class="fas fa-plane text-blue-500"></i>
                                                            </div>
                                                            <div class="ml-3">
                                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                                    <?php echo safeOutput($record['aircraft_reg']); ?>
                                                                </div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                    Registration
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <?php echo safeOutput($record['e_atl_number']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            ETL Number
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $record['status'] === 'open' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                                            <i class="fas fa-circle text-xs mr-1"></i>
                                                            <?php echo safeOutput(ucfirst($record['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0">
                                                                <i class="fas fa-user text-gray-400"></i>
                                                            </div>
                                                            <div class="ml-3">
                                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                                    <?php echo safeOutput($record['name']); ?>
                                                                </div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                    <?php echo safeOutput($record['user']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs">
                                                            <?php 
                                                            $maintenance = formatMaintenanceDetails($record['maintenance_details']);
                                                            echo strlen($maintenance) > 100 ? substr(strip_tags($maintenance), 0, 100) . '...' : $maintenance;
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs">
                                                            <?php 
                                                            $operations = formatOperationsDetails($record['operations_details']);
                                                            echo strlen($operations) > 100 ? substr(strip_tags($operations), 0, 100) . '...' : $operations;
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                                            <?php echo date('H:i', strtotime($record['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button onclick="openETLDetailsModal(<?php echo $recordIndex; ?>)" 
                                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3" 
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button onclick="printETLRecord(<?php echo $recordIndex; ?>)" 
                                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" 
                                                                title="Print">
                                                            <i class="fas fa-print"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php $recordIndex++; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Data State -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                        <i class="fas fa-chart-line text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No ETL Data</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">Select a date and click "Fetch ETL Data" to load the report.</p>
                        <div class="text-sm text-gray-400 dark:text-gray-500">
                            <p>ETL data is fetched from external API:</p>
                            <p class="font-mono">http://etl.raimonairways.net/api/rai-fleet/maintenace.php</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ETL Details Modal -->
    <div id="etlDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">ETL Record Details</h3>
                    <button onclick="closeETLDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="etlDetailsContent" class="space-y-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="closeETLDetailsModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                    <button onclick="printETLRecordFromModal()"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                        <i class="fas fa-print mr-2"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentETLRecord = null;
        let etlRecords = <?php echo json_encode($etlData['data'] ?? []); ?>;

        function openETLDetailsModal(recordIndex) {
            try {
                // Flatten the etlRecords array to get all records
                const allRecords = [];
                Object.values(etlRecords).forEach(records => {
                    allRecords.push(...records);
                });
                
                if (recordIndex >= 0 && recordIndex < allRecords.length) {
                    currentETLRecord = allRecords[recordIndex];
                    populateETLDetailsModal(currentETLRecord);
                    document.getElementById('etlDetailsModal').classList.remove('hidden');
                } else {
                    throw new Error('Invalid record index');
                }
            } catch (error) {
                console.error('Error opening ETL details modal:', error);
                console.error('Record index:', recordIndex);
                console.error('Available records:', etlRecords);
                alert('Error opening ETL details. Please try again.');
            }
        }

        function closeETLDetailsModal() {
            document.getElementById('etlDetailsModal').classList.add('hidden');
            currentETLRecord = null;
        }

        function populateETLDetailsModal(record) {
            const content = document.getElementById('etlDetailsContent');
            
            // Basic Information
            const basicInfo = `
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Aircraft Registration</label>
                            <p class="text-sm text-gray-900 dark:text-white">${record.aircraft_reg || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">ETL Number</label>
                            <p class="text-sm text-gray-900 dark:text-white">${record.e_atl_number || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${record.status === 'open' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}">
                                <i class="fas fa-circle text-xs mr-1"></i>
                                ${record.status ? record.status.charAt(0).toUpperCase() + record.status.slice(1) : 'N/A'}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Created At</label>
                            <p class="text-sm text-gray-900 dark:text-white">${record.created_at || 'N/A'}</p>
                        </div>
                    </div>
                </div>
            `;

            // User Information
            const userInfo = `
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">User Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                            <p class="text-sm text-gray-900 dark:text-white">${record.name || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">User ID</label>
                            <p class="text-sm text-gray-900 dark:text-white">${record.user || 'N/A'}</p>
                        </div>
                    </div>
                </div>
            `;

            // Maintenance Details
            let maintenanceInfo = `
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Maintenance Details</h4>
            `;
            
            if (record.maintenance_details && record.maintenance_details.length > 0) {
                record.maintenance_details.forEach((detail, index) => {
                    maintenanceInfo += `
                        <div class="mb-4 p-3 bg-white dark:bg-gray-800 rounded border">
                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Maintenance Record ${index + 1}</h5>
                    `;
                    
                    // Engine details
                    if (detail.engine) {
                        try {
                            const engine = JSON.parse(detail.engine);
                            maintenanceInfo += `
                                <div class="mb-2">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Engine Status</label>
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        ${Object.entries(engine).map(([key, value]) => 
                                            `<span class="inline-block px-2 py-1 rounded text-xs mr-1 ${value == '1' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'}">${key}: ${value}</span>`
                                        ).join('')}
                                    </div>
                                </div>
                            `;
                        } catch (e) {
                            maintenanceInfo += `<p class="text-sm text-gray-500">Engine: ${detail.engine}</p>`;
                        }
                    }
                    
                    // System details
                    if (detail.sys) {
                        try {
                            const sys = JSON.parse(detail.sys);
                            maintenanceInfo += `
                                <div class="mb-2">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">System Status</label>
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        ${Object.entries(sys).map(([key, value]) => 
                                            `<span class="inline-block px-2 py-1 rounded text-xs mr-1 ${value == '1' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'}">${key}: ${value}</span>`
                                        ).join('')}
                                    </div>
                                </div>
                            `;
                        } catch (e) {
                            maintenanceInfo += `<p class="text-sm text-gray-500">System: ${detail.sys}</p>`;
                        }
                    }
                    
                    // Daily maintenance
                    if (detail.daily && detail.daily == '1') {
                        maintenanceInfo += `
                            <div class="mb-2">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Daily Maintenance</label>
                                <p class="text-sm text-gray-900 dark:text-white">
                                    Time: ${detail.hour_daily_time || '00'}:${detail.minute_daily_time || '00'} | 
                                    Station: ${detail.daily_station || 'N/A'}
                                </p>
                            </div>
                        `;
                    }
                    
                    // Preflight maintenance
                    if (detail.preflight && detail.preflight == '1') {
                        maintenanceInfo += `
                            <div class="mb-2">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Preflight Maintenance</label>
                                <p class="text-sm text-gray-900 dark:text-white">
                                    Time: ${detail.hour_preflight_time || '00'}:${detail.minute_preflight_time || '00'} | 
                                    Station: ${detail.preflight_station || 'N/A'}
                                </p>
                            </div>
                        `;
                    }
                    
                    maintenanceInfo += `</div>`;
                });
            } else {
                maintenanceInfo += `<p class="text-sm text-gray-500">No maintenance details available</p>`;
            }
            maintenanceInfo += `</div>`;

            // Operations Details
            let operationsInfo = `
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Operations Details</h4>
            `;
            
            if (record.operations_details && record.operations_details.length > 0) {
                record.operations_details.forEach((detail, index) => {
                    operationsInfo += `
                        <div class="mb-4 p-3 bg-white dark:bg-gray-800 rounded border">
                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Operation ${index + 1}</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    `;
                    
                    if (detail.flight_number) {
                        operationsInfo += `
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Flight Number</label>
                                <p class="text-sm text-gray-900 dark:text-white">${detail.flight_number}</p>
                            </div>
                        `;
                    }
                    
                    if (detail.origin && detail.destination) {
                        operationsInfo += `
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Route</label>
                                <p class="text-sm text-gray-900 dark:text-white">${detail.origin} → ${detail.destination}</p>
                            </div>
                        `;
                    }
                    
                    if (detail.pic_details) {
                        operationsInfo += `
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">PIC (Pilot in Command)</label>
                                <p class="text-sm text-gray-900 dark:text-white">${detail.pic_details.first_name} ${detail.pic_details.last_name}</p>
                            </div>
                        `;
                    }
                    
                    if (detail.fo_details) {
                        operationsInfo += `
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">FO (First Officer)</label>
                                <p class="text-sm text-gray-900 dark:text-white">${detail.fo_details.first_name} ${detail.fo_details.last_name}</p>
                            </div>
                        `;
                    }
                    
                    if (detail.on_board_litre) {
                        operationsInfo += `
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Fuel On Board</label>
                                <p class="text-sm text-gray-900 dark:text-white">${detail.on_board_litre} Liters</p>
                            </div>
                        `;
                    }
                    
                    operationsInfo += `</div></div>`;
                });
            } else {
                operationsInfo += `<p class="text-sm text-gray-500">No operations details available</p>`;
            }
            operationsInfo += `</div>`;

            content.innerHTML = basicInfo + userInfo + maintenanceInfo + operationsInfo;
        }

        function printETLRecord(recordIndex) {
            try {
                // Flatten the etlRecords array to get all records
                const allRecords = [];
                Object.values(etlRecords).forEach(records => {
                    allRecords.push(...records);
                });
                
                if (recordIndex >= 0 && recordIndex < allRecords.length) {
                    const record = allRecords[recordIndex];
                    printETLRecordFromModal(record);
                } else {
                    throw new Error('Invalid record index');
                }
            } catch (error) {
                console.error('Error printing ETL record:', error);
                alert('Error printing ETL record. Please try again.');
            }
        }

        function printETLRecordFromModal(record = null) {
            const recordToPrint = record || currentETLRecord;
            if (!recordToPrint) return;
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>ETL Record - ${recordToPrint.aircraft_reg}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .section { margin-bottom: 20px; }
                        .section h3 { background-color: #f5f5f5; padding: 10px; margin: 0 0 10px 0; }
                        .field { margin-bottom: 10px; }
                        .field label { font-weight: bold; }
                        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>ETL Record Details</h1>
                        <p><strong>Aircraft:</strong> ${recordToPrint.aircraft_reg} | <strong>ETL Number:</strong> ${recordToPrint.e_atl_number} | <strong>Date:</strong> ${recordToPrint.created_at}</p>
                    </div>
                    
                    <div class="section">
                        <h3>Basic Information</h3>
                        <div class="grid">
                            <div class="field">
                                <label>Aircraft Registration:</label> ${recordToPrint.aircraft_reg || 'N/A'}
                            </div>
                            <div class="field">
                                <label>ETL Number:</label> ${recordToPrint.e_atl_number || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Status:</label> ${recordToPrint.status || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Created At:</label> ${recordToPrint.created_at || 'N/A'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>User Information</h3>
                        <div class="grid">
                            <div class="field">
                                <label>Name:</label> ${recordToPrint.name || 'N/A'}
                            </div>
                            <div class="field">
                                <label>User ID:</label> ${recordToPrint.user || 'N/A'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>Maintenance Details</h3>
                        ${recordToPrint.maintenance_details ? recordToPrint.maintenance_details.map((detail, index) => `
                            <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;">
                                <h4>Maintenance Record ${index + 1}</h4>
                                ${detail.engine ? `<p><strong>Engine:</strong> ${detail.engine}</p>` : ''}
                                ${detail.sys ? `<p><strong>System:</strong> ${detail.sys}</p>` : ''}
                                ${detail.daily ? `<p><strong>Daily:</strong> ${detail.hour_daily_time}:${detail.minute_daily_time} at ${detail.daily_station}</p>` : ''}
                                ${detail.preflight ? `<p><strong>Preflight:</strong> ${detail.hour_preflight_time}:${detail.minute_preflight_time} at ${detail.preflight_station}</p>` : ''}
                            </div>
                        `).join('') : '<p>No maintenance details available</p>'}
                    </div>
                    
                    <div class="section">
                        <h3>Operations Details</h3>
                        ${recordToPrint.operations_details ? recordToPrint.operations_details.map((detail, index) => `
                            <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;">
                                <h4>Operation ${index + 1}</h4>
                                ${detail.flight_number ? `<p><strong>Flight Number:</strong> ${detail.flight_number}</p>` : ''}
                                ${detail.origin && detail.destination ? `<p><strong>Route:</strong> ${detail.origin} → ${detail.destination}</p>` : ''}
                                ${detail.pic_details ? `<p><strong>PIC:</strong> ${detail.pic_details.first_name} ${detail.pic_details.last_name}</p>` : ''}
                                ${detail.fo_details ? `<p><strong>FO:</strong> ${detail.fo_details.first_name} ${detail.fo_details.last_name}</p>` : ''}
                                ${detail.on_board_litre ? `<p><strong>Fuel:</strong> ${detail.on_board_litre} Liters</p>` : ''}
                            </div>
                        `).join('') : '<p>No operations details available</p>'}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function exportETLData() {
            // Simple CSV export functionality
            const table = document.querySelector('table');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = Array.from(cols).map(col => {
                    return '"' + col.textContent.replace(/"/g, '""') + '"';
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'etl_report_<?php echo $selectedDate; ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('etlDetailsModal');
            if (event.target === modal) {
                closeETLDetailsModal();
            }
        }
    </script>
</body>
</html>
