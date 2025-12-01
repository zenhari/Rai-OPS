<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/users/office_time.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// API Configuration
define('ICLOCK_API_URL', 'https://portal.raimonairways.net/api/emp.php');
define('ICLOCK_API_AUTH_TOKEN', 'a74784f6-9e9b-4aad-afe4-80f1bb492555');
define('ICLOCK_API_DATA_TOKEN', '1454402b-34c7-40cb-ae52-0e67eb0fd553');

// Get date range parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$empCode = $_GET['emp_code'] ?? '';

// Function to fetch transactions from API with POST
function fetchOfficeTimeTransactions($empCode = '', $startTime = '', $endTime = '') {
    $url = ICLOCK_API_URL;
    
    // Prepare POST data
    $postData = [
        'emp_code' => $empCode,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'token' => ICLOCK_API_DATA_TOKEN
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . ICLOCK_API_AUTH_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("Office Time API cURL Error: " . $curlError);
        return ['success' => false, 'error' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        error_log("Office Time API HTTP Error: " . $httpCode);
        return ['success' => false, 'error' => 'HTTP error: ' . $httpCode, 'response' => $response];
    }
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Office Time API JSON Error: " . json_last_error_msg());
        return ['success' => false, 'error' => 'Invalid JSON response'];
    }
    
    return [
        'success' => true,
        'data' => $decodedResponse['data'] ?? $decodedResponse ?? [],
        'count' => $decodedResponse['count'] ?? count($decodedResponse['data'] ?? $decodedResponse ?? []),
        'next' => $decodedResponse['next'] ?? null,
        'previous' => $decodedResponse['previous'] ?? null
    ];
}

// Function to get all transactions with POST
function getAllOfficeTimeTransactions($empCode = '', $startDate = '', $endDate = '') {
    $startTime = $startDate . ' 00:00:00';
    $endTime = $endDate . ' 23:59:59';
    
    // Prepare POST data
    $postData = [
        'emp_code' => $empCode,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'token' => ICLOCK_API_DATA_TOKEN
    ];
    
    $url = ICLOCK_API_URL;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . ICLOCK_API_AUTH_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError || $httpCode !== 200) {
        error_log("Office Time API Error: " . ($curlError ?: "HTTP {$httpCode}"));
        return [];
    }
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Office Time API JSON Error: " . json_last_error_msg());
        return [];
    }
    
    // Handle different response formats
    if (isset($decodedResponse['data']) && is_array($decodedResponse['data'])) {
        return $decodedResponse['data'];
    } elseif (is_array($decodedResponse)) {
        return $decodedResponse;
    }
    
    return [];
}

// Fetch transactions if date range is provided
$transactions = [];
$transactionsByDate = [];
$punchCounts = [];

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($startDate) && !empty($endDate)) {
    try {
        $transactions = getAllOfficeTimeTransactions($empCode, $startDate, $endDate);
        
        // Get user information for each emp_code and map transactions
        $empCodes = array_unique(array_filter(array_column($transactions, 'emp_code'), function($code) {
            return !empty($code) && is_string($code);
        }));
        $userMap = [];
        
        if (!empty($empCodes)) {
            $db = getDBConnection();
            $placeholders = implode(',', array_fill(0, count($empCodes), '?'));
            $stmt = $db->prepare("SELECT id, first_name, last_name, asic_number, flight_crew FROM users WHERE asic_number IN ($placeholders)");
            
            // Convert to indexed array to ensure proper binding
            $empCodesArray = array_values($empCodes);
            $stmt->execute($empCodesArray);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                if (!empty($user['asic_number'])) {
                    $userMap[$user['asic_number']] = [
                        'id' => $user['id'],
                        'first_name' => $user['first_name'] ?? '',
                        'last_name' => $user['last_name'] ?? '',
                        'flight_crew' => $user['flight_crew'] ?? 0
                    ];
                }
            }
        }
        
        // Process transactions and group by date and user
        foreach ($transactions as $transaction) {
            $transactionEmpCode = $transaction['emp_code'] ?? '';
            $punchTime = $transaction['punch_time'] ?? '';
            
            if (empty($punchTime)) {
                continue;
            }
            
            // Extract date from punch_time
            try {
                $punchDate = date('Y-m-d', strtotime($punchTime));
            } catch (Exception $e) {
                error_log("Invalid punch_time format: " . $punchTime);
                continue;
            }
            
            // Get user info from map
            $userInfo = $userMap[$transactionEmpCode] ?? null;
            
            if ($userInfo) {
                $userId = $userInfo['id'];
                $userKey = $userId . '_' . $punchDate;
                
                if (!isset($transactionsByDate[$userKey])) {
                    $transactionsByDate[$userKey] = [
                        'user_id' => $userId,
                        'first_name' => $userInfo['first_name'],
                        'last_name' => $userInfo['last_name'],
                        'emp_code' => $transactionEmpCode,
                        'date' => $punchDate,
                        'flight_crew' => $userInfo['flight_crew'] ?? 0,
                        'punches' => []
                    ];
                }
                
                $transactionsByDate[$userKey]['punches'][] = [
                    'id' => $transaction['id'] ?? '',
                    'punch_time' => $punchTime,
                    'punch_state' => $transaction['punch_state'] ?? '',
                    'terminal_alias' => $transaction['terminal_alias'] ?? '',
                    'area_alias' => $transaction['area_alias'] ?? '',
                    'verify_type' => $transaction['verify_type'] ?? '',
                    'temperature' => $transaction['temperature'] ?? '0.0'
                ];
            }
        }
        
        // Count punches per day
        foreach ($transactionsByDate as $key => $data) {
            $punchCounts[$key] = count($data['punches']);
        }
        
    } catch (Exception $e) {
        $error = 'Error fetching office time data: ' . $e->getMessage();
        error_log("Office Time Error: " . $e->getMessage());
    }
}

// Get all users with asic_number for dropdown
$db = getDBConnection();
$stmt = $db->query("SELECT id, first_name, last_name, asic_number FROM users WHERE asic_number IS NOT NULL AND asic_number != '' ORDER BY first_name, last_name");
$usersWithAsic = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Time - <?php echo PROJECT_NAME; ?></title>
    <script src="../../assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Office Time</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Employee attendance and punch time records</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
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

                <!-- Search Filters -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="emp_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Employee Code (Optional)
                            </label>
                            <select id="emp_code" name="emp_code" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Employees</option>
                                <?php foreach ($usersWithAsic as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['asic_number']); ?>" <?php echo ($empCode === $user['asic_number']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['asic_number'] . ' - ' . $user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Start Date
                            </label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                End Date
                            </label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results Table -->
                <?php 
                // Group punches by employee and date
                $groupedPunches = [];
                $maxPunchCount = 0;
                
                if (!empty($transactionsByDate)) {
                    foreach ($transactionsByDate as $key => $record) {
                        if (!empty($record['punches']) && is_array($record['punches'])) {
                            // Sort punches: Check In first, then Check Out, both sorted by time
                            $sortedPunches = $record['punches'];
                            usort($sortedPunches, function($a, $b) {
                                $stateA = $a['punch_state'] ?? '';
                                $stateB = $b['punch_state'] ?? '';
                                
                                // Check In (state '0') comes before Check Out (state '1')
                                if ($stateA !== $stateB) {
                                    if ($stateA === '0') return -1; // Check In first
                                    if ($stateB === '0') return 1;  // Check In first
                                    return 0;
                                }
                                
                                // Same state, sort by time
                                $timeA = strtotime($a['punch_time'] ?? '');
                                $timeB = strtotime($b['punch_time'] ?? '');
                                if ($timeA === false || $timeB === false) {
                                    return 0;
                                }
                                return $timeA - $timeB;
                            });
                            
                            $groupKey = $record['emp_code'] . '_' . $record['date'];
                            $groupedPunches[$groupKey] = [
                                'first_name' => $record['first_name'],
                                'last_name' => $record['last_name'],
                                'emp_code' => $record['emp_code'],
                                'date' => $record['date'],
                                'flight_crew' => $record['flight_crew'] ?? 0,
                                'punches' => $sortedPunches
                            ];
                            
                            $punchCount = count($sortedPunches);
                            if ($punchCount > $maxPunchCount) {
                                $maxPunchCount = $punchCount;
                            }
                        }
                    }
                }
                
                // Sort grouped punches by date (newest first), then by name
                uasort($groupedPunches, function($a, $b) {
                    if ($a['date'] !== $b['date']) {
                        return strcmp($b['date'], $a['date']); // Newest date first
                    }
                    return strcmp($a['last_name'], $b['last_name']);
                });
                
                if (!empty($groupedPunches)): 
                ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Office Time Records
                                </h2>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    Total: <?php echo count($groupedPunches); ?> record<?php echo count($groupedPunches) !== 1 ? 's' : ''; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <?php for ($i = 1; $i <= $maxPunchCount; $i++): ?>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Punch Time <?php echo $i; ?>
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php 
                                    foreach ($groupedPunches as $group): 
                                        $punchDateStr = date('M j, Y', strtotime($group['date']));
                                        $isFlightCrew = ($group['flight_crew'] == 1);
                                        $rowClass = $isFlightCrew 
                                            ? 'bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30' 
                                            : 'hover:bg-gray-50 dark:hover:bg-gray-700';
                                    ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($group['emp_code']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $punchDateStr; ?>
                                                </div>
                                            </td>
                                            <?php 
                                            // Display punch times in columns
                                            for ($i = 0; $i < $maxPunchCount; $i++): 
                                                $punch = $group['punches'][$i] ?? null;
                                                if ($punch && !empty($punch['punch_time'])):
                                                    $punchDateTime = strtotime($punch['punch_time']);
                                                    if ($punchDateTime !== false):
                                                        $punchTimeStr = date('H:i:s', $punchDateTime);
                                                        $punchType = ($punch['punch_state'] === '0') ? 'Check In' : 'Check Out';
                                                        $punchTypeClass = ($punch['punch_state'] === '0') ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '';
                                                        $isCheckIn = ($punch['punch_state'] === '0');
                                                        $terminalAlias = htmlspecialchars($punch['terminal_alias'] ?? 'N/A');
                                            ?>
                                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                                    <div class="flex flex-col items-center gap-1.5">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <?php echo $punchTimeStr; ?>
                                                        </div>
                                                        <?php if ($isCheckIn): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $punchTypeClass; ?>">
                                                                <?php echo $punchType; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                            <?php echo $terminalAlias; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php 
                                                    else:
                                            ?>
                                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                                    <div class="text-sm text-gray-400 dark:text-gray-500">-</div>
                                                </td>
                                            <?php 
                                                    endif;
                                                else:
                                            ?>
                                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                                    <div class="text-sm text-gray-400 dark:text-gray-500">-</div>
                                                </td>
                                            <?php 
                                                endif;
                                            endfor; 
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif (!empty($startDate) && !empty($endDate)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-400">No office time records found for the selected date range.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-alt text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-400">Please select a date range to view office time records.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>

