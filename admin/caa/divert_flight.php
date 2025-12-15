<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/caa/divert_flight.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50; // Records per page
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// If no dates provided, default to current month
// But ensure fromDate is not before 2025-07-12
if (empty($fromDate) && empty($toDate)) {
    $minDate = '2025-07-12';
    $currentMonthStart = date('Y-m-01');
    $fromDate = $currentMonthStart < $minDate ? $minDate : $currentMonthStart;
    $toDate = date('Y-m-t'); // Last day of current month
}

$db = getDBConnection();

// Build query for divert flights
// A divert flight has Route like "MHD-AZD-MHD" (3 parts, first and third are same)
$whereConditions = [];
$params = [];

// Minimum date filter - don't show flights before 2025-07-12
$whereConditions[] = "DATE(f.FltDate) >= ?";
$params[] = '2025-07-12';

// Date filter
if (!empty($fromDate)) {
    // Ensure fromDate is not before minimum date
    if ($fromDate < '2025-07-12') {
        $fromDate = '2025-07-12';
    }
    $whereConditions[] = "DATE(f.FltDate) >= ?";
    $params[] = $fromDate;
}

if (!empty($toDate)) {
    $whereConditions[] = "DATE(f.FltDate) <= ?";
    $params[] = $toDate;
}

// Exclude cancelled flights
$whereConditions[] = "f.ScheduledTaskStatus NOT LIKE 'Cancelled'";

// Divert condition: 
// 1. Route with 3 parts where first equals third (MHD-AZD-MHD)
// 2. Route with same origin and destination (RAS-RAS)
$whereConditions[] = "(
    (
        SUBSTRING_INDEX(f.Route, '-', 1) = SUBSTRING_INDEX(f.Route, '-', -1)
        AND LENGTH(f.Route) - LENGTH(REPLACE(f.Route, '-', '')) = 2
    )
    OR
    (
        SUBSTRING_INDEX(f.Route, '-', 1) = SUBSTRING_INDEX(f.Route, '-', -1)
        AND LENGTH(f.Route) - LENGTH(REPLACE(f.Route, '-', '')) = 1
    )
)";

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM flights f $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate pagination
$totalPages = ceil($totalRecords / $limit);
$offset = ($page - 1) * $limit;

// Get flights with pagination
$query = "SELECT 
            f.id,
            f.FltDate,
            f.TaskName,
            f.FlightNo,
            f.Route,
            f.ACType,
            f.Rego,
            f.Crew1,
            f.adult,
            f.child,
            f.infant,
            f.total_pax,
            f.off_block,
            f.takeoff,
            f.landed,
            f.on_block,
            f.air_time_min,
            f.ScheduledTaskStatus,
            SUBSTRING_INDEX(f.Route, '-', 1) as origin,
            CASE 
                WHEN LENGTH(f.Route) - LENGTH(REPLACE(f.Route, '-', '')) = 1 THEN NULL
                ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(f.Route, '-', 2), '-', -1)
            END as diverted_to,
            SUBSTRING_INDEX(f.Route, '-', -1) as final_destination,
            u.first_name,
            u.last_name
          FROM flights f
          LEFT JOIN users u ON u.id = f.Crew1
          $whereClause
          ORDER BY f.FltDate DESC, f.TaskStart DESC
          LIMIT " . intval($limit) . " OFFSET " . intval($offset);

$stmt = $db->prepare($query);
$stmt->execute($params);
$divertFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format duration
function formatDuration($minutes) {
    if (empty($minutes) || $minutes == 0) return 'N/A';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours > 0) {
        return $hours . 'h ' . $mins . 'm';
    }
    return $mins . 'm';
}

// Helper function to format time
function formatTime($timeStr) {
    if (empty($timeStr)) return 'N/A';
    // If time is in format like 1931, convert to 19:31
    if (strlen($timeStr) == 4 && is_numeric($timeStr)) {
        return substr($timeStr, 0, 2) . ':' . substr($timeStr, 2, 2);
    }
    return $timeStr;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divert Flight - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-exclamation-triangle mr-2 text-orange-500"></i>
                                Divert Flight Report
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Flights that diverted and returned to origin
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg mb-6 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-filter mr-2"></i>Filters
                    </h2>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="from_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                From Date
                            </label>
                            <input type="date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="to_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                To Date
                            </label>
                            <input type="date" id="to_date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div class="flex items-end space-x-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="divert_flight.php" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-redo mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                                <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Divert Flights</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo number_format($totalRecords); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <i class="fas fa-users text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Passengers</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php 
                                    $totalPax = array_sum(array_column($divertFlights, 'total_pax'));
                                    echo number_format($totalPax);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flights Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-list mr-2"></i>Divert Flights
                            </h2>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                <?php 
                                $startRecord = ($page - 1) * $limit + 1;
                                $endRecord = min($page * $limit, $totalRecords);
                                echo number_format($startRecord) . '-' . number_format($endRecord) . ' of ' . number_format($totalRecords) . ' flight(s)';
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (empty($divertFlights)): ?>
                    <div class="p-6 text-center">
                        <i class="fas fa-plane-slash text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No divert flights found for the selected date range.</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pilot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Passengers</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($divertFlights as $flight): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo date('Y-m-d', strtotime($flight['FltDate'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($flight['TaskName'] ?: 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php if (!empty($flight['diverted_to'])): ?>
                                        <!-- Diverted flight: Origin -> Diverted To <- Final Destination -->
                                        <div class="flex items-center space-x-2">
                                            <span class="font-semibold text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($flight['origin']); ?></span>
                                            <i class="fas fa-arrow-right text-gray-400"></i>
                                            <span class="font-semibold text-orange-600 dark:text-orange-400"><?php echo htmlspecialchars($flight['diverted_to']); ?></span>
                                            <i class="fas fa-arrow-left text-gray-400"></i>
                                            <span class="font-semibold text-red-600 dark:text-red-400"><?php echo htmlspecialchars($flight['final_destination']); ?></span>
                                        </div>
                                        <?php else: ?>
                                        <!-- Same origin and destination: Origin = Destination -->
                                        <div class="flex items-center space-x-2">
                                            <span class="font-semibold text-orange-600 dark:text-orange-400"><?php echo htmlspecialchars($flight['origin']); ?></span>
                                            <i class="fas fa-exchange-alt text-gray-400"></i>
                                            <span class="font-semibold text-orange-600 dark:text-orange-400"><?php echo htmlspecialchars($flight['final_destination']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Route: <?php echo htmlspecialchars($flight['Route']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div><?php echo htmlspecialchars($flight['ACType'] ?: 'N/A'); ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($flight['Rego'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars(trim(($flight['first_name'] ?? '') . ' ' . ($flight['last_name'] ?? '')) ?: 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo number_format($flight['total_pax'] ?? 0); ?>
                                        <?php if ($flight['total_pax'] > 0): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            A:<?php echo $flight['adult'] ?? 0; ?> 
                                            C:<?php echo $flight['child'] ?? 0; ?> 
                                            I:<?php echo $flight['infant'] ?? 0; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Diverted
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php
                                // Build query string for pagination (preserve filters)
                                $queryParams = [];
                                if (!empty($fromDate)) $queryParams['from_date'] = $fromDate;
                                if (!empty($toDate)) $queryParams['to_date'] = $toDate;
                                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                
                                // Previous button
                                if ($page > 1):
                                    $prevPage = $page - 1;
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
                                $startPage = max(1, $page - floor($maxPagesToShow / 2));
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
                                    <?php if ($i == $page): ?>
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
                                <?php if ($page < $totalPages):
                                    $nextPage = $page + 1;
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

