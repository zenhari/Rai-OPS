<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/airsar_report/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$data = [];
$count = 0;

// Get filter parameters
$filterStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$filterEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Function to fetch data from external API
function fetchAirsarData($startDate, $endDate) {
    $url = 'http://etl.raimonairways.net/api/operation_camo_etl.php';
    $token = '69040872-3ba8-4681-a9ec-273c832f3ca0';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // --location flag
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); // --request GET
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlError || $curlErrno) {
        error_log("Airsar API cURL Error #{$curlErrno}: {$curlError}");
        error_log("Airsar API URL: {$url}");
        error_log("Airsar API HTTP Code: {$httpCode}");
        if ($response) {
            error_log("Airsar API Response: " . substr($response, 0, 500));
        }
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Airsar API HTTP Error Code: {$httpCode}");
        error_log("Airsar API Response: " . substr($response, 0, 500));
        return false;
    }
    
    if (empty($response)) {
        error_log("Airsar API: Empty response received");
        return false;
    }
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Airsar API JSON Error: " . json_last_error_msg());
        error_log("Airsar API Raw Response: " . substr($response, 0, 500));
        return false;
    }
    
    if (!$decodedResponse) {
        error_log("Airsar API: Failed to decode JSON response");
        error_log("Airsar API Raw Response: " . substr($response, 0, 500));
        return false;
    }
    
    if (!isset($decodedResponse['success']) || !$decodedResponse['success']) {
        $errorMsg = $decodedResponse['message'] ?? 'Unknown error';
        error_log("Airsar API Response Error: {$errorMsg}");
        return false;
    }
    
    // Filter data by created_at date range
    $filteredData = [];
    if (isset($decodedResponse['data']) && is_array($decodedResponse['data'])) {
        foreach ($decodedResponse['data'] as $item) {
            if (isset($item['created_at'])) {
                $createdDate = date('Y-m-d', strtotime($item['created_at']));
                if ($createdDate >= $startDate && $createdDate <= $endDate) {
                    $filteredData[] = $item;
                }
            }
        }
    }
    
    return [
        'success' => true,
        'data' => $filteredData,
        'count' => count($filteredData)
    ];
}

// Fetch data
$apiResponse = fetchAirsarData($filterStartDate, $filterEndDate);

if ($apiResponse === false) {
    $error = 'Failed to fetch data from API. Please check the error logs or try again later.';
} else {
    $data = $apiResponse['data'];
    $count = $apiResponse['count'];
    
    if ($count == 0) {
        $message = 'No data found for the selected date range.';
    }
}

// Helper function to extract Resources Text from etl_number
function getResourcesText($etlNumber) {
    if (empty($etlNumber)) {
        return '';
    }
    // Extract part before the last dash (e.g., "EP-NEA-1004" -> "EP-NEA")
    $parts = explode('-', $etlNumber);
    if (count($parts) >= 2) {
        array_pop($parts); // Remove last part
        return implode('-', $parts);
    }
    return $etlNumber;
}

// Helper function to get Task Start Date from created_at
function getTaskStartDate($createdAt) {
    if (empty($createdAt)) {
        return '';
    }
    try {
        $date = new DateTime($createdAt);
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        return '';
    }
}

// Helper function to get Task End Date
// If Task End Time is before Task Start Time, add one day to Task Start Date
// Otherwise, use Task Start Date
function getTaskEndDate($createdAt, $blockOffTime, $blockOnTime) {
    if (empty($createdAt)) {
        return '';
    }
    
    try {
        $startDate = new DateTime($createdAt);
        $startDateOnly = $startDate->format('Y-m-d');
        
        // If times are empty, return start date
        if (empty($blockOffTime) || empty($blockOnTime)) {
            return $startDateOnly;
        }
        
        // Parse times (format: HH:MM:SS)
        $startTime = strtotime($blockOffTime);
        $endTime = strtotime($blockOnTime);
        
        // If end time is before start time, add one day
        if ($endTime < $startTime) {
            $startDate->modify('+1 day');
            return $startDate->format('Y-m-d');
        }
        
        return $startDateOnly;
    } catch (Exception $e) {
        return '';
    }
}

// Excel Export
if ($action === 'excel') {
    $filename = 'Airsar_Report_' . $filterStartDate;
    if ($filterStartDate != $filterEndDate) {
        $filename .= '_to_' . $filterEndDate;
    }
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";
    
    // Start table
    echo '<table border="1">';
    
    // Header row
    echo '<tr>';
    echo '<th>Flight Number</th>';
    echo '<th>Task Start Date (UTC)</th>';
    echo '<th>Task Start Time (UTC)</th>';
    echo '<th>Task End Date (UTC)</th>';
    echo '<th>Task End Time (UTC)</th>';
    echo '<th>Departure</th>';
    echo '<th>Arrival</th>';
    echo '<th>Resources Text</th>';
    echo '<th>Crew1</th>';
    echo '<th>Crew2</th>';
    echo '<th>Crew3</th>';
    echo '</tr>';
    
    // Data rows
    foreach ($data as $item) {
        $taskStartDate = getTaskStartDate($item['created_at'] ?? '');
        $taskEndDate = getTaskEndDate(
            $item['created_at'] ?? '',
            $item['block_off_time'] ?? '',
            $item['block_on_time'] ?? ''
        );
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['flight_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($taskStartDate) . '</td>';
        echo '<td>' . htmlspecialchars($item['block_off_time'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($taskEndDate) . '</td>';
        echo '<td>' . htmlspecialchars($item['block_on_time'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['origin'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['destination'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(getResourcesText($item['etl_number'] ?? '')) . '</td>';
        echo '<td style="text-transform: uppercase; font-weight: 500;">' . htmlspecialchars(strtoupper($item['pic'] ?? '')) . '</td>';
        echo '<td style="text-transform: uppercase; font-weight: 500;">' . htmlspecialchars(strtoupper($item['fo'] ?? '')) . '</td>';
        echo '<td style="text-transform: uppercase; font-weight: 500;">' . htmlspecialchars(strtoupper($item['obs'] ?? '')) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airsar Report (ETL) - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
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
            table {
                font-size: 10px;
            }
            th, td {
                padding: 4px 6px;
            }
        }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-chart-line mr-2"></i>
                                Airsar Report (ETL)
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Operations data from ETL system</p>
                        </div>
                        <div class="flex space-x-3 no-print">
                            <?php if (count($data) > 0): ?>
                            <a href="?start_date=<?php echo urlencode($filterStartDate); ?>&end_date=<?php echo urlencode($filterEndDate); ?>&action=excel" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-file-excel mr-2 text-green-600 dark:text-green-400"></i>
                                Download Excel
                            </a>
                            <?php endif; ?>
                            <button onclick="window.print()" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-print mr-2"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Messages -->
                <?php if ($message): ?>
                <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-2"></i>
                        <p class="text-blue-800 dark:text-blue-200"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-2"></i>
                        <p class="text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Data Count -->
                <?php if ($count > 0): ?>
                <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
                        <p class="text-green-800 dark:text-green-200">
                            Found <strong><?php echo $count; ?></strong> record(s) for the selected date range.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Report Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden print-content">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 no-print">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Airsar Report (ETL)</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Operations data from ETL system</p>
                            </div>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center whitespace-nowrap">
                                    <i class="fas fa-filter mr-2"></i>
                                    <span class="hidden sm:inline">Filter by Date Range:</span>
                                    <span class="sm:hidden">Date Range:</span>
                                </label>
                                <form method="GET" action="" class="flex items-center gap-2 w-full sm:w-auto">
                                    <input type="date" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="<?php echo htmlspecialchars($filterStartDate); ?>"
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm flex-1 sm:flex-none"
                                           onchange="if (this.value > document.getElementById('end_date').value) { document.getElementById('end_date').value = this.value; } this.form.submit();">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">to</span>
                                    <input type="date" 
                                           id="end_date" 
                                           name="end_date" 
                                           value="<?php echo htmlspecialchars($filterEndDate); ?>"
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm flex-1 sm:flex-none"
                                           onchange="if (this.value < document.getElementById('start_date').value) { this.value = document.getElementById('start_date').value; } this.form.submit();">
                                    <button type="button" onclick="clearDateFilter()" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200 whitespace-nowrap">
                                        <i class="fas fa-times mr-1"></i>
                                        Clear
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start Date (UTC)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start Time (UTC)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task End Date (UTC)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task End Time (UTC)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Departure</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Arrival</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resources Text</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew1</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew2</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew3</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($data)): ?>
                                    <tr>
                                        <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            <?php if ($filterStartDate == $filterEndDate): ?>
                                                No data found for <?php echo date('Y-m-d', strtotime($filterStartDate)); ?>
                                            <?php else: ?>
                                                No data found for the date range <?php echo date('Y-m-d', strtotime($filterStartDate)); ?> to <?php echo date('Y-m-d', strtotime($filterEndDate)); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($data as $item): 
                                        $taskStartDate = getTaskStartDate($item['created_at'] ?? '');
                                        $taskEndDate = getTaskEndDate(
                                            $item['created_at'] ?? '',
                                            $item['block_off_time'] ?? '',
                                            $item['block_on_time'] ?? ''
                                        );
                                    ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($item['flight_number'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($taskStartDate); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($item['block_off_time'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($taskEndDate); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($item['block_on_time'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($item['origin'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($item['destination'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars(getResourcesText($item['etl_number'] ?? '')); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 uppercase font-medium">
                                            <?php echo htmlspecialchars(strtoupper($item['pic'] ?? '')); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 uppercase font-medium">
                                            <?php echo htmlspecialchars(strtoupper($item['fo'] ?? '')); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 uppercase font-medium">
                                            <?php echo htmlspecialchars(strtoupper($item['obs'] ?? '')); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clearDateFilter() {
            const today = new Date().toISOString().split('T')[0];
            const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            document.getElementById('start_date').value = weekAgo;
            document.getElementById('end_date').value = today;
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>
