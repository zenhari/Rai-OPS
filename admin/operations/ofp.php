<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/ofp.php');

$current_user = getCurrentUser();

// Get filter parameters
$selected_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : null;
$selected_route = isset($_GET['route']) ? trim($_GET['route']) : '';
$selected_hour = isset($_GET['hour']) && $_GET['hour'] !== '' ? intval($_GET['hour']) : null;

// Pagination parameters
$page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$per_page = isset($_GET['per_page']) && $_GET['per_page'] > 0 ? intval($_GET['per_page']) : 50; // Default 50 records per page
$offset = ($page - 1) * $per_page;

// Log directory path
$log_dir = __DIR__ . '/../../skyputer/logs/';

/**
 * Get all OFP log files
 */
function getOFPLogFiles($log_dir) {
    $files = [];
    if (!is_dir($log_dir)) {
        return $files;
    }
    
    $pattern = $log_dir . 'skyputer_ofp-*.json';
    $found_files = glob($pattern);
    
    foreach ($found_files as $file) {
        $files[] = [
            'path' => $file,
            'name' => basename($file),
            'modified' => filemtime($file)
        ];
    }
    
    // Sort by modified time (newest first)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $files;
}

/**
 * Load and parse OFP data from JSON files with pagination support
 * Returns array with 'data' (paginated records) and 'total' (total count)
 */
function loadOFPData($log_dir, $filter_date = null, $filter_route = null, $filter_hour = null, $offset = 0, $limit = null) {
    $all_data = [];
    $files = getOFPLogFiles($log_dir);
    
    foreach ($files as $file_info) {
        $file_path = $file_info['path'];
        
        // Extract date from filename (skyputer_ofp-YYYY-MM-DD-HH.json)
        $filename = $file_info['name'];
        if (preg_match('/skyputer_ofp-(\d{4})-(\d{2})-(\d{2})-(\d{2})\.json/', $filename, $matches)) {
            $file_date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            $file_hour = intval($matches[4]);
            
            // Filter by date if specified (based on file date)
            if ($filter_date && $file_date !== $filter_date) {
                continue;
            }
            
            // Filter by hour if specified
            if ($filter_hour !== null && $file_hour !== $filter_hour) {
                continue;
            }
        } else {
            // If filename doesn't match pattern, skip file if date filter is set
            if ($filter_date) {
                continue;
            }
        }
        
        $content = @file_get_contents($file_path);
        if ($content === false) {
            continue;
        }
        
        $json_data = @json_decode($content, true);
        if (!is_array($json_data)) {
            continue;
        }
        
        // Process each record in the file
        foreach ($json_data as $record) {
            if (!is_array($record)) {
                continue;
            }
            
            // Extract route from parsed_data or flight_info
            $route = '';
            if (isset($record['parsed_data']['binfo']['RTS'])) {
                $route = $record['parsed_data']['binfo']['RTS'];
            } elseif (isset($record['parsed_data']['binfo']['|RTS'])) {
                $route = $record['parsed_data']['binfo']['|RTS'];
            } elseif (isset($record['flight_info']['route'])) {
                $route = $record['flight_info']['route'];
            }
            
            // Filter by route if specified
            if ($filter_route && stripos($route, $filter_route) === false) {
                continue;
            }
            
            // Extract date from flight_info or parsed_data (for display only, not filtering)
            $record_date = '';
            if (isset($record['flight_info']['date'])) {
                $record_date = $record['flight_info']['date'];
            } elseif (isset($record['parsed_data']['binfo']['DTE'])) {
                $record_date = $record['parsed_data']['binfo']['DTE'];
            } elseif (isset($record['parsed_data']['binfo']['|DTE'])) {
                $record_date = $record['parsed_data']['binfo']['|DTE'];
            }
            
            // Convert date format if needed (APR 02 2025 -> 2025-04-02) - for display only
            if ($record_date && preg_match('/(\w+)\s+(\d+)\s+(\d+)/', $record_date, $date_matches)) {
                $month_map = [
                    'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                    'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                    'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
                ];
                $month = strtoupper(substr($date_matches[1], 0, 3));
                if (isset($month_map[$month])) {
                    $record_date = $date_matches[3] . '-' . $month_map[$month] . '-' . str_pad($date_matches[2], 2, '0', STR_PAD_LEFT);
                }
            }
            
            $all_data[] = [
                'file' => $file_info['name'],
                'timestamp_utc' => $record['timestamp_utc'] ?? '',
                'timestamp_local' => $record['timestamp_local'] ?? '',
                'request_id' => $record['request_id'] ?? '',
                'client_ip' => $record['client_ip'] ?? '',
                'format' => $record['format'] ?? '',
                'flight_number' => $record['flight_info']['flight_number'] ?? ($record['parsed_data']['binfo']['FLN'] ?? ''),
                'date' => $record_date,
                'route' => $route,
                'aircraft_reg' => $record['flight_info']['aircraft_reg'] ?? ($record['parsed_data']['binfo']['REG'] ?? ''),
                'etd' => $record['flight_info']['etd'] ?? ($record['parsed_data']['binfo']['ETD'] ?? ''),
                'eta' => $record['flight_info']['eta'] ?? ($record['parsed_data']['binfo']['ETA'] ?? ''),
                'operator' => $record['flight_info']['operator'] ?? ($record['parsed_data']['binfo']['OPT'] ?? ''),
                'parsed_data' => $record['parsed_data'] ?? [],
                'flight_info' => $record['flight_info'] ?? [],
                'raw_data' => $record['raw_data'] ?? ''
            ];
        }
    }
    
    // Sort by timestamp (newest first)
    usort($all_data, function($a, $b) {
        $time_a = strtotime($a['timestamp_utc'] ?? $a['timestamp_local'] ?? '');
        $time_b = strtotime($b['timestamp_utc'] ?? $b['timestamp_local'] ?? '');
        return $time_b - $time_a;
    });
    
    $total = count($all_data);
    
    // Apply pagination if limit is specified
    if ($limit !== null && $limit > 0) {
        $all_data = array_slice($all_data, $offset, $limit);
    }
    
    return [
        'data' => $all_data,
        'total' => $total
    ];
}

/**
 * Get unique routes without loading all data (memory efficient)
 */
function getUniqueRoutes($log_dir, $max_files = 100) {
    $unique_routes = [];
    $files = getOFPLogFiles($log_dir);
    $processed = 0;
    
    foreach ($files as $file_info) {
        if ($max_files > 0 && $processed >= $max_files) {
            break;
        }
        
        $file_path = $file_info['path'];
        $content = @file_get_contents($file_path);
        if ($content === false) {
            continue;
        }
        
        $json_data = @json_decode($content, true);
        if (!is_array($json_data)) {
            continue;
        }
        
        foreach ($json_data as $record) {
            if (!is_array($record)) {
                continue;
            }
            
            $route = '';
            if (isset($record['parsed_data']['binfo']['RTS'])) {
                $route = $record['parsed_data']['binfo']['RTS'];
            } elseif (isset($record['parsed_data']['binfo']['|RTS'])) {
                $route = $record['parsed_data']['binfo']['|RTS'];
            } elseif (isset($record['flight_info']['route'])) {
                $route = $record['flight_info']['route'];
            }
            
            if (!empty($route) && !in_array($route, $unique_routes)) {
                $unique_routes[] = $route;
            }
        }
        
        $processed++;
    }
    
    sort($unique_routes);
    return $unique_routes;
}

/**
 * Get unique dates without loading all data (memory efficient)
 */
function getUniqueDates($log_dir, $max_files = 100) {
    $unique_dates = [];
    $files = getOFPLogFiles($log_dir);
    $processed = 0;
    
    foreach ($files as $file_info) {
        if ($max_files > 0 && $processed >= $max_files) {
            break;
        }
        
        $file_path = $file_info['path'];
        $content = @file_get_contents($file_path);
        if ($content === false) {
            continue;
        }
        
        $json_data = @json_decode($content, true);
        if (!is_array($json_data)) {
            continue;
        }
        
        foreach ($json_data as $record) {
            if (!is_array($record)) {
                continue;
            }
            
            $record_date = '';
            if (isset($record['flight_info']['date'])) {
                $record_date = $record['flight_info']['date'];
            } elseif (isset($record['parsed_data']['binfo']['DTE'])) {
                $record_date = $record['parsed_data']['binfo']['DTE'];
            } elseif (isset($record['parsed_data']['binfo']['|DTE'])) {
                $record_date = $record['parsed_data']['binfo']['|DTE'];
            }
            
            // Convert date format if needed
            if ($record_date && preg_match('/(\w+)\s+(\d+)\s+(\d+)/', $record_date, $date_matches)) {
                $month_map = [
                    'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                    'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                    'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
                ];
                $month = strtoupper(substr($date_matches[1], 0, 3));
                if (isset($month_map[$month])) {
                    $record_date = $date_matches[3] . '-' . $month_map[$month] . '-' . str_pad($date_matches[2], 2, '0', STR_PAD_LEFT);
                }
            }
            
            if (!empty($record_date) && !in_array($record_date, $unique_dates)) {
                $unique_dates[] = $record_date;
            }
        }
        
        $processed++;
    }
    
    rsort($unique_dates);
    return $unique_dates;
}

// OFP Field Labels Mapping
$field_labels = [
    // binfo fields
    'OPT' => 'Operator',
    '|OPT' => 'Operator',
    'UNT' => 'Unit',
    'FPF' => 'Fuel Performance Factor',
    'FLN' => 'Flight Number',
    'DTE' => 'Date',
    'ETD' => 'Estimated Time of Departure',
    'ETA' => 'Estimated Time of Arrival',
    'REG' => 'Aircraft Registration',
    'THM' => 'Thrust Mode',
    'MCI' => 'Mach',
    'FLL' => 'Flight Level',
    'NGM' => 'Nautical Ground Mile (Total Distance)',
    'NAM' => 'Nautical Air Mile',
    'DOW' => 'Dry Operating Weight',
    'PLD' => 'Payload',
    'CRW' => 'Crew Version',
    'RTM' => 'Main Route',
    'RTA' => '1st Alternate Route',
    'RTB' => '2nd Alternate Route',
    'RTS' => 'Route',
    '|RTS' => 'Route',
    'RTT' => 'Takeoff Alternate Route',
    'STT' => 'Take-off Alternate Summary',
    'DID' => 'Document ID',
    'VDT' => 'OFP Generated Date and Time',
    // futbl fields
    'PRM' => 'Parameter',
    'TIM' => 'Time of PRM',
    'VAL' => 'Value of PRM',
    // mpln/apln/bpln/tpln fields
    'WAP' => 'Waypoint',
    'GEO' => 'Coordinates',
    'FRQ' => 'Frequency',
    'VIA' => 'Airway',
    'ALT' => 'Flight Phase (CLB / FL / DES)',
    'MEA' => 'Minimum En Route Altitude',
    'GMR' => 'Grid Mora',
    'DIS' => 'Distance',
    'TDS' => 'Total Distance',
    'WID' => 'Wind Information',
    'TRK' => 'Track / Heading',
    'TMP' => 'Temperature / ISA',
    'TME' => 'Time',
    'TTM' => 'Total Time',
    'FRE' => 'Fuel Remaining',
    'FUS' => 'Fuel Used',
    'TAS' => 'True Airspeed',
    'GSP' => 'Ground Speed',
    'LAT' => 'Latitude',
    'LON' => 'Longitude',
    // cstbl fields
    'ETN' => 'Entry Number',
    'APT' => 'Airport',
    'ETP' => 'Entry Type',
    'ATI' => 'Altitude',
    'RWY' => 'Runway',
    'FUR' => 'Fuel Remaining',
    'FUQ' => 'Fuel Quantity',
    'FUD' => 'Fuel Used',
    'TIM' => 'Time',
];

// Table descriptions
$table_descriptions = [
    'binfo' => 'Basic Information of the OFP',
    'futbl' => 'Fuel Table Sheet',
    'mpln' => 'Primary Point-to-Point',
    'apln' => '1st Alternate Point-to-Point',
    'bpln' => '2nd Alternate Point-to-Point',
    'tpln' => 'Take-off Alternate Point-to-Point',
    'cstbl' => 'Critical Fuel Scenario',
    'aldrf' => 'Altitude Drift',
    'wtdrf' => 'Weight Drift',
    'wdtmp' => 'Wind & Temperature Aloft',
    'wdclb' => 'Wind Climb',
    'wddes' => 'Wind Descent',
    'icatc' => 'ICAO ATC Format',
];

/**
 * Get label for a field
 */
function getFieldLabel($field, $labels) {
    return $labels[$field] ?? $field;
}

/**
 * Format time value
 */
function formatTime($time) {
    if (empty($time)) return '';
    // Handle formats like "01:08:00.00000" or "00:05:00.00000"
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2})/', $time, $matches)) {
        $hours = intval($matches[1]);
        $minutes = intval($matches[2]);
        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }
        return sprintf('%dm', $minutes);
    }
    return $time;
}

/**
 * Format coordinates
 */
function formatCoordinates($geo) {
    if (empty($geo)) return '';
    // Format: "N3614.1 E05938.7"
    return $geo;
}

// Load OFP data with filters and pagination
$ofp_result = loadOFPData($log_dir, $selected_date, $selected_route, $selected_hour, $offset, $per_page);
$ofp_data = $ofp_result['data'];
$total_records = $ofp_result['total'];
$total_pages = ceil($total_records / $per_page);

// Get unique routes for filter dropdown (memory efficient - only process first 100 files)
$unique_routes = getUniqueRoutes($log_dir, 100);

// Get available dates for filter (memory efficient - only process first 100 files)
$available_dates = getUniqueDates($log_dir, 100);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OFP Viewer - <?php echo PROJECT_NAME; ?></title>
    <script src="../../assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="flex flex-col min-h-screen">
        <!-- Include Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-file-alt mr-2"></i>OFP Viewer
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                View Operational Flight Plan data from Skyputer API
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 px-4 sm:px-6 lg:px-8 py-4">
                <form method="GET" action="" class="flex flex-wrap items-end gap-4" onsubmit="document.querySelector('[name=page]').value = '1';">
                    <!-- Date Filter -->
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-calendar mr-1"></i>Date (Optional)
                        </label>
                        <input type="date" name="date" value="<?php echo $selected_date ? htmlspecialchars($selected_date) : ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <!-- Route Filter -->
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-route mr-1"></i>Route (RTS)
                        </label>
                        <input type="text" name="route" value="<?php echo htmlspecialchars($selected_route); ?>" 
                               placeholder="e.g., OIMM - OIII" list="route-list"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <datalist id="route-list">
                            <?php foreach ($unique_routes as $route): ?>
                                <option value="<?php echo htmlspecialchars($route); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <!-- Hour Filter (optional) -->
                    <div class="w-32">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-clock mr-1"></i>Hour
                        </label>
                        <select name="hour" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">All Hours</option>
                            <?php for ($h = 0; $h < 24; $h++): ?>
                                <option value="<?php echo $h; ?>" <?php echo ($selected_hour === $h) ? 'selected' : ''; ?>>
                                    <?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-search mr-1"></i>Filter
                        </button>
                        <a href="?" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200">
                            <i class="fas fa-redo mr-1"></i>Reset
                        </a>
                    </div>
                </form>
                
                <!-- Results Count and Pagination Info -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        Showing <strong><?php echo count($ofp_data); ?></strong> of <strong><?php echo $total_records; ?></strong> OFP record(s)
                        <?php if ($total_pages > 1): ?>
                            (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700 dark:text-gray-300">Records per page:</label>
                        <select name="per_page" onchange="document.querySelector('[name=page]').value = '1'; this.form.submit();" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm">
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $per_page == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <main class="px-4 sm:px-6 lg:px-8 py-6">
                <?php if (empty($ofp_data)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">No OFP data found for the selected filters.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-plane mr-1"></i>Flight No.
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-calendar mr-1"></i>Date
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-route mr-1"></i>Route
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-tag mr-1"></i>Aircraft
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-clock mr-1"></i>ETD
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-clock mr-1"></i>ETA
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-building mr-1"></i>Operator
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-clock mr-1"></i>Timestamp
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <i class="fas fa-cog mr-1"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($ofp_data as $index => $record): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['flight_number'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['date'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['route'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['aircraft_reg'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['etd'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['eta'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($record['operator'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($record['timestamp_local'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <a href="ofp_detail.php?request_id=<?php echo htmlspecialchars($record['request_id']); ?>&file=<?php echo urlencode($record['file']); ?>" 
                                                   class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-600 sm:px-6">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-800 cursor-not-allowed">
                                            Previous
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            Next
                                        </a>
                                    <?php else: ?>
                                        <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-800 cursor-not-allowed">
                                            Next
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                            <span class="font-medium"><?php echo min($offset + $per_page, $total_records); ?></span> of 
                                            <span class="font-medium"><?php echo $total_records; ?></span> results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <?php if ($page > 1): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <span class="sr-only">Previous</span>
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                                    <span class="sr-only">Previous</span>
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php
                                            // Show page numbers (max 7 pages around current page)
                                            $start_page = max(1, $page - 3);
                                            $end_page = min($total_pages, $page + 3);
                                            
                                            if ($start_page > 1): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    1
                                                </a>
                                                <?php if ($start_page > 2): ?>
                                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        ...
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <?php if ($i == $page): ?>
                                                    <span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-sm font-medium text-blue-600 dark:text-blue-400">
                                                        <?php echo $i; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <?php echo $i; ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            
                                            <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        ...
                                                    </span>
                                                <?php endif; ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <?php echo $total_pages; ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <span class="sr-only">Next</span>
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                                    <span class="sr-only">Next</span>
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                            <?php endif; ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

</body>
</html>

