<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/settings/call_center/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// API Base URL
$apiBaseUrl = 'http://192.168.201.39:5000';

// Get filter parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$caller = isset($_GET['caller']) ? trim($_GET['caller']) : '';
$callee = isset($_GET['callee']) ? trim($_GET['callee']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build API URL
$apiUrl = $apiBaseUrl . '/api/calls';
$queryParams = [];
if ($limit > 0) $queryParams['limit'] = $limit;
if (!empty($type)) $queryParams['type'] = $type;
if (!empty($status)) $queryParams['status'] = $status;
if (!empty($caller)) $queryParams['caller'] = $caller;
if (!empty($callee)) $queryParams['callee'] = $callee;
if (!empty($search)) $queryParams['search'] = $search;

if (!empty($queryParams)) {
    $apiUrl .= '?' . http_build_query($queryParams);
}

// Fetch calls from API
$calls = [];
$callsCount = 0;
$apiError = '';

try {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] && isset($data['calls'])) {
            $calls = $data['calls'];
            $callsCount = $data['count'] ?? count($calls);
        } else {
            $apiError = 'Invalid API response format.';
        }
    } else {
        $apiError = "API Error: HTTP $httpCode" . ($curlError ? " - $curlError" : '');
    }
} catch (Exception $e) {
    $apiError = "Error fetching calls: " . $e->getMessage();
}

// Get statistics
$stats = null;
try {
    $statsUrl = $apiBaseUrl . '/api/stats';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $statsUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]
    ]);
    
    $statsResponse = curl_exec($ch);
    $statsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statsResponse !== false && $statsHttpCode === 200) {
        $statsData = json_decode($statsResponse, true);
        if (isset($statsData['success']) && $statsData['success']) {
            $stats = $statsData;
        }
    }
} catch (Exception $e) {
    // Stats error is not critical, continue without it
}

// Helper function to format duration
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . 'm ' . $secs . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return $hours . 'h ' . $minutes . 'm ' . $secs . 's';
    }
}

// Helper function to format date/time
function formatDateTime($dateTimeString) {
    if (empty($dateTimeString)) return 'N/A';
    try {
        $dt = new DateTime($dateTimeString);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $dateTimeString;
    }
}

// Get status badge color
function getStatusBadgeColor($status) {
    switch (strtolower($status)) {
        case 'answer':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'ringing':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'not answered':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'busy':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
}

// Get call type badge color
function getCallTypeBadgeColor($callType) {
    if (strpos($callType, 'Internal') !== false && strpos($callType, 'Internal') !== false) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    } elseif (strpos($callType, 'External') !== false && strpos($callType, 'External') !== false) {
        return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
    } else {
        return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Center - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/header.php'; ?>
    <?php include '../../../includes/sidebar.php'; ?>

    <div class="lg:pl-64">
        <main class="py-6">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <!-- Page Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        <i class="fas fa-phone-alt mr-2"></i>Call Center
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Monitor and view all call records from Asterisk system
                    </p>
                </div>

                <!-- Statistics Cards -->
                <?php if ($stats): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <i class="fas fa-phone text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Calls</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo number_format($stats['total_calls'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <i class="fas fa-check-circle text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Connected Calls</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo number_format($stats['connected_calls'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Duration</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo formatDuration($stats['total_duration_seconds'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                                <i class="fas fa-stopwatch text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Connected Duration</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo formatDuration($stats['connected_duration_seconds'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-filter mr-2"></i>Filters
                    </h2>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Limit
                            </label>
                            <input type="number" id="limit" name="limit" value="<?php echo htmlspecialchars($limit); ?>" 
                                   min="1" max="1000" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Call Type
                            </label>
                            <select id="type" name="type" 
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Types</option>
                                <option value="Internal to Internal" <?php echo $type === 'Internal to Internal' ? 'selected' : ''; ?>>Internal to Internal</option>
                                <option value="Internal to External" <?php echo $type === 'Internal to External' ? 'selected' : ''; ?>>Internal to External</option>
                                <option value="External to Internal" <?php echo $type === 'External to Internal' ? 'selected' : ''; ?>>External to Internal</option>
                                <option value="External to External" <?php echo $type === 'External to External' ? 'selected' : ''; ?>>External to External</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Status
                            </label>
                            <select id="status" name="status" 
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Statuses</option>
                                <option value="Answer" <?php echo $status === 'Answer' ? 'selected' : ''; ?>>Answer</option>
                                <option value="Ringing" <?php echo $status === 'Ringing' ? 'selected' : ''; ?>>Ringing</option>
                                <option value="Not Answered" <?php echo $status === 'Not Answered' ? 'selected' : ''; ?>>Not Answered</option>
                                <option value="Busy" <?php echo $status === 'Busy' ? 'selected' : ''; ?>>Busy</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Search (Caller/Callee)
                            </label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search number..."
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div class="md:col-span-2 lg:col-span-4 flex justify-end space-x-3">
                            <a href="index.php" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-redo mr-2"></i>Reset
                            </a>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Calls Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-list mr-2"></i>Call Records
                            </h2>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo number_format($callsCount); ?> call(s) found
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($apiError)): ?>
                    <div class="p-6">
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        API Error
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                        <p><?php echo htmlspecialchars($apiError); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif (empty($calls)): ?>
                    <div class="p-6 text-center">
                        <i class="fas fa-phone-slash text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No calls found.</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Call Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Caller</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Caller ID Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Callee</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Channel</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($calls as $call): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo formatDateTime($call['StartTime'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getCallTypeBadgeColor($call['CallType'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($call['CallType'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['Caller'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['caller_id_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['Callee'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadgeColor($call['Status'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($call['Status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo formatDuration($call['Duration'] ?? 0); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($call['Channel'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

