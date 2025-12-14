<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/settings/call_center/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// API Base URL
$apiBaseUrl = 'http://192.168.201.23:5001';

// Get parameters
$startTime = isset($_GET['start_time']) ? trim($_GET['start_time']) : '';
$caller = isset($_GET['caller']) ? trim($_GET['caller']) : '';
$callee = isset($_GET['callee']) ? trim($_GET['callee']) : '';

if (empty($startTime) || empty($caller) || empty($callee)) {
    header('Location: index.php');
    exit();
}

// Fetch call details from API
$call = null;
$apiError = '';

try {
    // Try to fetch the specific call by searching with the parameters
    $apiUrl = $apiBaseUrl . '/api/calls';
    $queryParams = [
        'caller' => $caller,
        'callee' => $callee,
        'limit' => 100
    ];
    
    if (!empty($queryParams)) {
        $apiUrl .= '?' . http_build_query($queryParams);
    }
    
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
            // Find the matching call by start time
            foreach ($data['calls'] as $c) {
                if (isset($c['StartTime']) && $c['StartTime'] === $startTime &&
                    isset($c['Caller']) && $c['Caller'] === $caller &&
                    isset($c['Callee']) && $c['Callee'] === $callee) {
                    $call = $c;
                    break;
                }
            }
            
            if (!$call) {
                $apiError = 'Call record not found.';
            }
        } else {
            $apiError = 'Invalid API response format.';
        }
    } else {
        $apiError = "API Error: HTTP $httpCode" . ($curlError ? " - $curlError" : '');
    }
} catch (Exception $e) {
    $apiError = "Error fetching call details: " . $e->getMessage();
}

if (!$call && !empty($apiError)) {
    $error = $apiError;
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
    if (strpos($callType, 'Internal') !== false) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    } elseif (strpos($callType, 'External') !== false) {
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
    <title>Call Details - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>

    <div class="lg:pl-64">
        <main class="py-6">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <!-- Page Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-phone-alt mr-2"></i>Call Details
                            </h1>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Detailed information about the call record
                            </p>
                        </div>
                        <a href="index.php" class="px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                Error
                            </h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <p><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif ($call): ?>
                <!-- Call Details Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-info-circle mr-2"></i>Call Information
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div class="space-y-4">
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                                    <i class="fas fa-phone mr-2"></i>Basic Information
                                </h3>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Start Time</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <?php echo formatDateTime($call['StartTime'] ?? ''); ?>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Call Type</label>
                                    <p class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getCallTypeBadgeColor($call['CallType'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($call['CallType'] ?? 'N/A'); ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                                    <p class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadgeColor($call['Status'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($call['Status'] ?? 'N/A'); ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Duration</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <?php echo formatDuration($call['Duration'] ?? 0); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Caller/Callee Information -->
                            <div class="space-y-4">
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                                    <i class="fas fa-users mr-2"></i>Call Participants
                                </h3>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Caller</label>
                                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['Caller'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Caller ID Name</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['caller_id_name'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Callee</label>
                                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['Callee'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Channel</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($call['Channel'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <?php if (isset($call) && count(array_filter($call, function($key) use ($call) { 
                            return !in_array($key, ['StartTime', 'CallType', 'Status', 'Duration', 'Caller', 'caller_id_name', 'Callee', 'Channel']);
                        }, ARRAY_FILTER_USE_KEY)) > 0): ?>
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-list mr-2"></i>Additional Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($call as $key => $value): ?>
                                    <?php if (!in_array($key, ['StartTime', 'CallType', 'Status', 'Duration', 'Caller', 'caller_id_name', 'Callee', 'Channel']) && !empty($value)): ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>
                                        </label>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">Call record not found.</p>
                    <a href="index.php" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

