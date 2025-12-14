<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/settings/call_center/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// API Base URL
$apiBaseUrl = 'http://192.168.201.23:5001';

// Get filter parameters
$limit = 100; // Fixed to 100 records per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$caller = isset($_GET['caller']) ? trim($_GET['caller']) : '';
$callee = isset($_GET['callee']) ? trim($_GET['callee']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build API URL - First get total count with filters (without limit for filtering)
$apiUrl = $apiBaseUrl . '/api/calls';
$queryParams = [];
// Don't send limit to API - we'll get all filtered data and paginate in PHP
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
            $allCalls = $data['calls'];
            $callsCount = $data['count'] ?? count($allCalls);
            
            // Apply pagination in PHP
            $offset = ($page - 1) * $limit;
            $calls = array_slice($allCalls, $offset, $limit);
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

// Extract Iranian mobile numbers from call data
function extractIranianMobileNumbers($call) {
    $numbers = [];
    $mobilePatterns = [
        '/^\+98(9\d{9})$/',
        '/^98(9\d{9})$/',
        '/^0098(9\d{9})$/',
        '/^0?(9\d{9})$/'
    ];
    
    // Check all fields in the call array
    foreach ($call as $key => $value) {
        if (is_string($value) && !empty($value) && $value !== '<unknown>') {
            // Remove spaces and special characters
            $cleanValue = preg_replace('/[^\d+]/', '', $value);
            
            foreach ($mobilePatterns as $pattern) {
                if (preg_match($pattern, $cleanValue, $matches)) {
                    // Normalize to 09xxxxxxxxx format
                    $normalized = '0' . $matches[1];
                    if (!in_array($normalized, $numbers)) {
                        $numbers[] = $normalized;
                    }
                    break;
                }
            }
        }
    }
    
    return $numbers;
}

// Get passenger tickets by phone number
function getPassengerTicketsByPhone($phoneNumber) {
    try {
        $db = getDBConnection();
        
        // Normalize phone number - extract the 9-digit mobile part
        $cleanPhone = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // Extract mobile number (9 digits starting with 9)
        $mobileNumber = null;
        if (preg_match('/(?:^|\+?98|0098|0?)(9\d{9})/', $cleanPhone, $matches)) {
            $mobileNumber = '0' . $matches[1]; // Normalize to 09xxxxxxxxx
        } else {
            // If no match, try to use the number as is
            $mobileNumber = $cleanPhone;
        }
        
        if (empty($mobileNumber)) {
            return [];
        }
        
        // Search with various formats - using LIKE for partial matches
        $searchPatterns = [
            $mobileNumber,
            '+' . $mobileNumber,
            '98' . substr($mobileNumber, 1),
            '+98' . substr($mobileNumber, 1),
            '0098' . substr($mobileNumber, 1),
            substr($mobileNumber, 1), // Without leading 0
            '%' . $mobileNumber . '%', // Partial match
            '%' . substr($mobileNumber, 1) . '%' // Partial match without 0
        ];
        
        // Build WHERE clause with LIKE for flexible matching
        $whereConditions = [];
        $params = [];
        foreach ($searchPatterns as $pattern) {
            $whereConditions[] = "passenger_contact LIKE ?";
            $params[] = $pattern;
        }
        
        $whereClause = '(' . implode(' OR ', $whereConditions) . ')';
        
        $sql = "SELECT 
                    id, ticket_code, office_code, office_name, origin, destination,
                    passenger_full_name, docs, flight_no, passenger_contact, pnr,
                    departure_date, sales_date_gmt, coupon_status, flight_class_code,
                    adult_count, child_count, infant_count, total_pax,
                    fare_amount, tax_amount, total_amount, currency,
                    booking_reference, seat_number, baggage_allowance, meal_preference,
                    special_requests, created_at, updated_at, status
                FROM tickets 
                WHERE $whereClause
                ORDER BY departure_date DESC, created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting passenger tickets: " . $e->getMessage());
        return [];
    }
}

// Handle AJAX request for passenger tickets
if (isset($_GET['action']) && $_GET['action'] === 'get_passenger_tickets') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }
    
    $phoneNumber = isset($_GET['phone']) ? trim($_GET['phone']) : '';
    
    if (empty($phoneNumber)) {
        echo json_encode(['success' => false, 'message' => 'Phone number required']);
        exit();
    }
    
    $tickets = getPassengerTicketsByPhone($phoneNumber);
    
    echo json_encode([
        'success' => true,
        'tickets' => $tickets,
        'count' => count($tickets)
    ]);
    exit();
}

// Handle CSV export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    if (!isLoggedIn()) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
    
    // Get filter parameters (same as main page)
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $caller = isset($_GET['caller']) ? trim($_GET['caller']) : '';
    $callee = isset($_GET['callee']) ? trim($_GET['callee']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build API URL with filters (no limit - get all filtered data)
    $apiUrl = $apiBaseUrl . '/api/calls';
    $queryParams = [];
    if (!empty($type)) $queryParams['type'] = $type;
    if (!empty($status)) $queryParams['status'] = $status;
    if (!empty($caller)) $queryParams['caller'] = $caller;
    if (!empty($callee)) $queryParams['callee'] = $callee;
    if (!empty($search)) $queryParams['search'] = $search;
    
    if (!empty($queryParams)) {
        $apiUrl .= '?' . http_build_query($queryParams);
    }
    
    // Fetch all calls from API
    $allCalls = [];
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] && isset($data['calls'])) {
                $allCalls = $data['calls'];
            }
        }
    } catch (Exception $e) {
        // Error handling - continue with empty array
    }
    
    // Generate CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="call_center_export_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Collect all unique keys from all calls to create comprehensive headers
    $allKeys = [];
    foreach ($allCalls as $call) {
        foreach (array_keys($call) as $key) {
            if (!in_array($key, $allKeys)) {
                $allKeys[] = $key;
            }
        }
    }
    
    // Sort keys to have common fields first
    $commonFields = ['StartTime', 'CallType', 'Caller', 'caller_id_name', 'Callee', 'Status', 'Duration', 'Channel'];
    $orderedKeys = [];
    foreach ($commonFields as $field) {
        if (in_array($field, $allKeys)) {
            $orderedKeys[] = $field;
            $allKeys = array_diff($allKeys, [$field]);
        }
    }
    // Add remaining keys
    $orderedKeys = array_merge($orderedKeys, $allKeys);
    
    // Create CSV Headers with formatted names
    $headers = [];
    foreach ($orderedKeys as $key) {
        // Format key name: convert snake_case to Title Case
        $headerName = str_replace('_', ' ', $key);
        $headerName = ucwords($headerName);
        $headers[] = $headerName;
    }
    // Add formatted duration column
    $headers[] = 'Duration (formatted)';
    fputcsv($output, $headers);
    
    // CSV Data - include all fields from each call
    foreach ($allCalls as $call) {
        $row = [];
        foreach ($orderedKeys as $key) {
            $value = $call[$key] ?? '';
            
            // Format specific fields
            if ($key === 'StartTime' && !empty($value)) {
                $value = formatDateTime($value);
            } elseif ($key === 'Duration' && is_numeric($value)) {
                // Keep numeric value for Duration
                $value = $value;
            } elseif (is_array($value) || is_object($value)) {
                // Convert arrays/objects to JSON string
                $value = json_encode($value);
            } elseif ($value === null) {
                $value = '';
            }
            
            $row[] = $value;
        }
        // Add formatted duration
        $row[] = formatDuration($call['Duration'] ?? 0);
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
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
                            <div class="flex items-center space-x-4">
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php 
                                    $startRecord = ($page - 1) * $limit + 1;
                                    $endRecord = min($page * $limit, $callsCount);
                                    echo number_format($startRecord) . '-' . number_format($endRecord) . ' of ' . number_format($callsCount) . ' call(s)';
                                    ?>
                                </span>
                                <?php if (!empty($calls) || $callsCount > 0): ?>
                                <?php
                                // Build query string for CSV export (preserve filters)
                                $csvQueryParams = [];
                                $csvQueryParams['action'] = 'export_csv';
                                if (!empty($type)) $csvQueryParams['type'] = $type;
                                if (!empty($status)) $csvQueryParams['status'] = $status;
                                if (!empty($caller)) $csvQueryParams['caller'] = $caller;
                                if (!empty($callee)) $csvQueryParams['callee'] = $callee;
                                if (!empty($search)) $csvQueryParams['search'] = $search;
                                $csvQueryString = '?' . http_build_query($csvQueryParams);
                                ?>
                                <a href="<?php echo htmlspecialchars($csvQueryString); ?>" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                    <i class="fas fa-download mr-2"></i>Download as CSV
                                </a>
                                <?php endif; ?>
                            </div>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="showCallDetail(this)" 
                                                data-call='<?php echo htmlspecialchars(json_encode($call), ENT_QUOTES, 'UTF-8'); ?>'
                                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-eye mr-1"></i>Detail
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if ($callsCount > $limit): ?>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    Page <?php echo $page; ?> of <?php echo ceil($callsCount / $limit); ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php
                                $totalPages = ceil($callsCount / $limit);
                                $currentPage = $page;
                                
                                // Build query string for pagination (preserve filters)
                                $queryParams = [];
                                if (!empty($type)) $queryParams['type'] = $type;
                                if (!empty($status)) $queryParams['status'] = $status;
                                if (!empty($caller)) $queryParams['caller'] = $caller;
                                if (!empty($callee)) $queryParams['callee'] = $callee;
                                if (!empty($search)) $queryParams['search'] = $search;
                                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                
                                // Previous button
                                if ($currentPage > 1):
                                    $prevPage = $currentPage - 1;
                                ?>
                                <a href="?page=<?php echo $prevPage; ?><?php echo $queryString; ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-left mr-1"></i>Previous
                                </a>
                                <?php else: ?>
                                <span class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                    <i class="fas fa-chevron-left mr-1"></i>Previous
                                </span>
                                <?php endif; ?>
                                
                                <!-- Page numbers -->
                                <?php
                                $maxPagesToShow = 7;
                                $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                
                                if ($startPage > 1):
                                ?>
                                <a href="?page=1<?php echo $queryString; ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    1
                                </a>
                                <?php if ($startPage > 2): ?>
                                <span class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">...</span>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                    <span class="px-3 py-2 border border-blue-500 text-sm font-medium rounded-md text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20">
                                        <?php echo $i; ?>
                                    </span>
                                    <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>" 
                                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                <span class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <?php echo $totalPages; ?>
                                </a>
                                <?php endif; ?>
                                
                                <!-- Next button -->
                                <?php if ($currentPage < $totalPages):
                                    $nextPage = $currentPage + 1;
                                ?>
                                <a href="?page=<?php echo $nextPage; ?><?php echo $queryString; ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    Next<i class="fas fa-chevron-right ml-1"></i>
                                </a>
                                <?php else: ?>
                                <span class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                    Next<i class="fas fa-chevron-right ml-1"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Call Detail Modal -->
    <div id="callDetailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay with smooth transition -->
            <div id="modalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-75 transition-opacity duration-300 ease-out opacity-0" aria-hidden="true" onclick="closeCallDetailModal()"></div>

            <!-- Center modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel with animation -->
            <div id="modalPanel" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all duration-300 ease-out translate-y-4 opacity-0 sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <!-- Header with gradient accent -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 bg-white bg-opacity-20 rounded-lg p-3">
                                <i class="fas fa-phone-alt text-white text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white" id="modal-title">
                                    Call Details
                                </h3>
                                <p class="text-sm text-blue-100 mt-0.5">Complete call information</p>
                            </div>
                        </div>
                        <button onclick="closeCallDetailModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Content area with smooth scroll -->
                <div class="bg-white dark:bg-gray-800 max-h-[calc(100vh-200px)] overflow-y-auto">
                    <div id="callDetailContent" class="p-6">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex justify-end">
                        <button type="button" onclick="closeCallDetailModal()" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatDuration(seconds) {
            if (!seconds) return '0s';
            if (seconds < 60) {
                return seconds + 's';
            } else if (seconds < 3600) {
                const minutes = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return minutes + 'm ' + secs + 's';
            } else {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                return hours + 'h ' + minutes + 'm ' + secs + 's';
            }
        }

        function formatDateTime(dateTimeString) {
            if (!dateTimeString) return 'N/A';
            try {
                const dt = new Date(dateTimeString);
                return dt.toLocaleString('en-US', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch (e) {
                return dateTimeString;
            }
        }

        function getStatusBadgeColor(status) {
            const s = (status || '').toLowerCase();
            if (s === 'answer') {
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            } else if (s === 'ringing') {
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
            } else if (s === 'not answered') {
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            } else if (s === 'busy') {
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
            } else {
                return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
            }
        }

        function getCallTypeBadgeColor(callType) {
            if (!callType) return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
            if (callType.includes('Internal')) {
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            } else if (callType.includes('External')) {
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
            } else {
                return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
            }
        }

        // Extract Iranian mobile numbers from call data
        function extractIranianMobileNumbers(call) {
            const numbers = [];
            const mobilePatterns = [
                /^\+98(9\d{9})$/,
                /^98(9\d{9})$/,
                /^0098(9\d{9})$/,
                /^0?(9\d{9})$/
            ];
            
            // Check all fields in the call object
            for (const key in call) {
                const value = call[key];
                if (typeof value === 'string' && value && value !== '<unknown>') {
                    // Remove spaces and special characters
                    const cleanValue = value.replace(/[^\d+]/g, '');
                    
                    for (const pattern of mobilePatterns) {
                        const match = cleanValue.match(pattern);
                        if (match) {
                            // Normalize to 09xxxxxxxxx format
                            const normalized = '0' + match[1];
                            if (!numbers.includes(normalized)) {
                                numbers.push(normalized);
                            }
                            break;
                        }
                    }
                }
            }
            
            return numbers;
        }

        function showCallDetail(button) {
            const callData = button.getAttribute('data-call');
            const call = JSON.parse(callData);
            const modal = document.getElementById('callDetailModal');
            const content = document.getElementById('callDetailContent');
            const overlay = document.getElementById('modalOverlay');
            const panel = document.getElementById('modalPanel');
            
            // Build HTML content with professional design
            let html = '<div class="space-y-6">';
            
            // Basic Information Card
            html += '<div class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-700/50 dark:to-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-600 shadow-sm">';
            html += '<div class="flex items-center space-x-3 mb-5">';
            html += '<div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/30 rounded-lg p-3">';
            html += '<i class="fas fa-info-circle text-blue-600 dark:text-blue-400 text-xl"></i>';
            html += '</div>';
            html += '<h3 class="text-lg font-bold text-gray-900 dark:text-white">Basic Information</h3>';
            html += '</div>';
            
            html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Start Time</label>';
            html += '<div class="mt-1 flex items-center space-x-2">';
            html += '<i class="fas fa-clock text-gray-400 text-sm"></i>';
            html += '<p class="text-sm font-medium text-gray-900 dark:text-white">' + formatDateTime(call.StartTime) + '</p>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Call Type</label>';
            html += '<div class="mt-1">';
            html += '<span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold ' + getCallTypeBadgeColor(call.CallType) + ' shadow-sm">';
            html += '<i class="fas fa-phone mr-1.5"></i>';
            html += (call.CallType || 'N/A');
            html += '</span>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</label>';
            html += '<div class="mt-1">';
            html += '<span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold ' + getStatusBadgeColor(call.Status) + ' shadow-sm">';
            html += '<i class="fas fa-circle text-[8px] mr-1.5"></i>';
            html += (call.Status || 'N/A');
            html += '</span>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Duration</label>';
            html += '<div class="mt-1 flex items-center space-x-2">';
            html += '<i class="fas fa-hourglass-half text-gray-400 text-sm"></i>';
            html += '<p class="text-sm font-semibold text-gray-900 dark:text-white">' + formatDuration(call.Duration || 0) + '</p>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            
            // Call Participants Card
            html += '<div class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-700/50 dark:to-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-600 shadow-sm">';
            html += '<div class="flex items-center space-x-3 mb-5">';
            html += '<div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900/30 rounded-lg p-3">';
            html += '<i class="fas fa-users text-purple-600 dark:text-purple-400 text-xl"></i>';
            html += '</div>';
            html += '<h3 class="text-lg font-bold text-gray-900 dark:text-white">Call Participants</h3>';
            html += '</div>';
            
            html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Caller</label>';
            html += '<div class="mt-1 flex items-center space-x-2">';
            html += '<i class="fas fa-user-outgoing text-blue-500 text-sm"></i>';
            html += '<p class="text-sm font-semibold text-gray-900 dark:text-white">' + (call.Caller || 'N/A') + '</p>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Caller ID Name</label>';
            html += '<div class="mt-1 flex items-center space-x-2">';
            html += '<i class="fas fa-id-card text-gray-400 text-sm"></i>';
            html += '<p class="text-sm text-gray-900 dark:text-white">' + (call.caller_id_name || 'N/A') + '</p>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Callee</label>';
            html += '<div class="mt-1 flex items-center space-x-2">';
            html += '<i class="fas fa-user-incoming text-green-500 text-sm"></i>';
            html += '<p class="text-sm font-semibold text-gray-900 dark:text-white">' + (call.Callee || 'N/A') + '</p>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="space-y-1">';
            html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Channel</label>';
            html += '<div class="mt-1 flex items-center space-x-2">';
            html += '<i class="fas fa-broadcast-tower text-gray-400 text-sm"></i>';
            html += '<p class="text-sm font-mono text-gray-900 dark:text-white">' + (call.Channel || 'N/A') + '</p>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            
            // Extract Iranian mobile numbers and check for passenger
            const mobileNumbers = extractIranianMobileNumbers(call);
            
            // Passenger Information Card (will be populated via AJAX)
            if (mobileNumbers.length > 0) {
                html += '<div id="passengerInfoCard" class="bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20 rounded-xl p-6 border border-amber-200 dark:border-amber-700 shadow-sm">';
                html += '<div class="flex items-center space-x-3 mb-5">';
                html += '<div class="flex-shrink-0 bg-amber-100 dark:bg-amber-900/30 rounded-lg p-3">';
                html += '<i class="fas fa-user-tie text-amber-600 dark:text-amber-400 text-xl"></i>';
                html += '</div>';
                html += '<div class="flex-1">';
                html += '<h3 class="text-lg font-bold text-gray-900 dark:text-white">Passenger Information</h3>';
                html += '<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Checking tickets for: ' + mobileNumbers.join(', ') + '</p>';
                html += '</div>';
                html += '<div id="passengerLoading" class="flex-shrink-0">';
                html += '<i class="fas fa-spinner fa-spin text-amber-600 dark:text-amber-400"></i>';
                html += '</div>';
                html += '</div>';
                html += '<div id="passengerContent">';
                html += '<p class="text-sm text-gray-600 dark:text-gray-400">Loading passenger data...</p>';
                html += '</div>';
                html += '</div>';
                
                // Load passenger data via AJAX
                loadPassengerData(mobileNumbers);
            }
            
            // Additional Information
            const excludedKeys = ['StartTime', 'CallType', 'Status', 'Duration', 'Caller', 'caller_id_name', 'Callee', 'Channel'];
            const additionalInfo = [];
            for (const key in call) {
                if (!excludedKeys.includes(key) && call[key] !== null && call[key] !== '' && call[key] !== undefined) {
                    additionalInfo.push({ key: key, value: call[key] });
                }
            }
            
            if (additionalInfo.length > 0) {
                html += '<div class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-700/50 dark:to-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-600 shadow-sm">';
                html += '<div class="flex items-center space-x-3 mb-5">';
                html += '<div class="flex-shrink-0 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg p-3">';
                html += '<i class="fas fa-list-ul text-indigo-600 dark:text-indigo-400 text-xl"></i>';
                html += '</div>';
                html += '<h3 class="text-lg font-bold text-gray-900 dark:text-white">Additional Information</h3>';
                html += '</div>';
                html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">';
                additionalInfo.forEach(function(item) {
                    const label = item.key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    const value = typeof item.value === 'object' ? JSON.stringify(item.value) : item.value;
                    html += '<div class="space-y-1">';
                    html += '<label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">' + label + '</label>';
                    html += '<p class="mt-1 text-sm text-gray-900 dark:text-white break-words">' + value + '</p>';
                    html += '</div>';
                });
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            
            content.innerHTML = html;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Trigger animation
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                overlay.classList.add('opacity-100');
                panel.classList.remove('translate-y-4', 'opacity-0');
                panel.classList.add('translate-y-0', 'opacity-100');
            }, 10);
        }

        function closeCallDetailModal() {
            const modal = document.getElementById('callDetailModal');
            const overlay = document.getElementById('modalOverlay');
            const panel = document.getElementById('modalPanel');
            
            // Animate out
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
            panel.classList.remove('translate-y-0', 'opacity-100');
            panel.classList.add('translate-y-4', 'opacity-0');
            
            // Hide modal after animation
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }, 300);
        }

        // Load passenger data via AJAX
        function loadPassengerData(mobileNumbers) {
            const passengerContent = document.getElementById('passengerContent');
            const passengerLoading = document.getElementById('passengerLoading');
            
            if (!passengerContent || mobileNumbers.length === 0) return;
            
            // Try each mobile number
            let requestsCompleted = 0;
            let allTickets = [];
            const totalRequests = mobileNumbers.length;
            
            mobileNumbers.forEach(phone => {
                fetch('index.php?action=get_passenger_tickets&phone=' + encodeURIComponent(phone))
                    .then(response => response.json())
                    .then(data => {
                        requestsCompleted++;
                        
                        if (data.success && data.tickets && data.tickets.length > 0) {
                            allTickets = allTickets.concat(data.tickets);
                        }
                        
                        // When all requests are done
                        if (requestsCompleted === totalRequests) {
                            displayPassengerInfo(allTickets, mobileNumbers);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading passenger data:', error);
                        requestsCompleted++;
                        
                        if (requestsCompleted === totalRequests) {
                            displayPassengerInfo(allTickets, mobileNumbers);
                        }
                    });
            });
        }
        
        function displayPassengerInfo(tickets, mobileNumbers) {
            const passengerContent = document.getElementById('passengerContent');
            const passengerLoading = document.getElementById('passengerLoading');
            
            if (!passengerContent) return;
            
            if (passengerLoading) {
                passengerLoading.style.display = 'none';
            }
            
            if (tickets.length === 0) {
                passengerContent.innerHTML = '<div class="text-center py-4">';
                passengerContent.innerHTML += '<i class="fas fa-user-slash text-gray-400 text-3xl mb-2"></i>';
                passengerContent.innerHTML += '<p class="text-sm font-medium text-gray-600 dark:text-gray-400">This person is not a passenger</p>';
                passengerContent.innerHTML += '<p class="text-xs text-gray-500 dark:text-gray-500 mt-1">No tickets found for: ' + mobileNumbers.join(', ') + '</p>';
                passengerContent.innerHTML += '</div>';
                return;
            }
            
            // Group tickets by passenger name
            const ticketsByPassenger = {};
            tickets.forEach(ticket => {
                const name = ticket.passenger_full_name || 'Unknown';
                if (!ticketsByPassenger[name]) {
                    ticketsByPassenger[name] = [];
                }
                ticketsByPassenger[name].push(ticket);
            });
            
            let html = '<div class="space-y-4">';
            
            // Summary
            html += '<div class="bg-white dark:bg-gray-700 rounded-lg p-4 border border-amber-200 dark:border-amber-700">';
            html += '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
            html += '<div>';
            html += '<p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Total Tickets</p>';
            html += '<p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">' + tickets.length + '</p>';
            html += '</div>';
            html += '<div>';
            html += '<p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Passenger Name</p>';
            html += '<p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">' + Object.keys(ticketsByPassenger)[0] + '</p>';
            html += '</div>';
            html += '<div>';
            html += '<p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Flight Dates</p>';
            const uniqueDates = [...new Set(tickets.map(t => t.departure_date).filter(d => d))];
            html += '<p class="text-sm font-medium text-gray-900 dark:text-white mt-1">' + uniqueDates.length + ' unique date(s)</p>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Tickets Table
            html += '<div class="overflow-x-auto">';
            html += '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
            html += '<thead class="bg-gray-100 dark:bg-gray-700">';
            html += '<tr>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Ticket Code</th>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Flight No</th>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Route</th>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Departure Date</th>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Passenger</th>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">PNR</th>';
            html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Status</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
            
            tickets.forEach(ticket => {
                html += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">';
                html += '<td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">' + (ticket.ticket_code || 'N/A') + '</td>';
                html += '<td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">' + (ticket.flight_no || 'N/A') + '</td>';
                html += '<td class="px-4 py-3 text-sm text-gray-900 dark:text-white">';
                html += '<span class="inline-flex items-center">';
                html += '<span class="font-semibold">' + (ticket.origin || 'N/A') + '</span>';
                html += '<i class="fas fa-arrow-right mx-2 text-gray-400 text-xs"></i>';
                html += '<span class="font-semibold">' + (ticket.destination || 'N/A') + '</span>';
                html += '</span>';
                html += '</td>';
                html += '<td class="px-4 py-3 text-sm text-gray-900 dark:text-white">' + (ticket.departure_date || 'N/A') + '</td>';
                html += '<td class="px-4 py-3 text-sm text-gray-900 dark:text-white">' + (ticket.passenger_full_name || 'N/A') + '</td>';
                html += '<td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-400">' + (ticket.pnr || 'N/A') + '</td>';
                html += '<td class="px-4 py-3 text-sm">';
                const statusColor = ticket.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                  ticket.status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                  ticket.status === 'used' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                  'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                html += '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ' + statusColor + '">';
                html += (ticket.status || 'N/A');
                html += '</span>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            html += '</div>';
            
            passengerContent.innerHTML = html;
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCallDetailModal();
            }
        });
    </script>
</body>
</html>

