<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/flight_load/index.php');

$user = getCurrentUser();

// Get filter parameters
$ticket_code = isset($_GET['ticket_code']) ? trim($_GET['ticket_code']) : '';
$docs = isset($_GET['docs']) ? trim($_GET['docs']) : '';
$passenger_contact = isset($_GET['passenger_contact']) ? trim($_GET['passenger_contact']) : '';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 100;
$offset = ($page - 1) * $limit;

// Initialize variables
$tickets_data = [];
$loading = false;
$error_message = '';
$success_message = '';
$total_count = 0;
$total_pages = 0;

// Handle form submission or load all tickets
$has_search_params = !empty($ticket_code) || !empty($docs) || !empty($passenger_contact);
$load_all = isset($_GET['load_all']) && $_GET['load_all'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $has_search_params || $load_all) {
    $loading = true;
    
    // Prepare API request filters
    $filters = [];
    
    if (!$load_all) {
        if (!empty($ticket_code)) {
            $filters['ticket_code'] = $ticket_code;
        }
        if (!empty($docs)) {
            $filters['docs'] = $docs;
        }
        if (!empty($passenger_contact)) {
            $filters['passenger_contact'] = $passenger_contact;
        }
    }
    
    // Call API
    $api_response = fetchTicketsFromAPI($filters);
    
    if ($api_response['success']) {
        $api_data = $api_response['data'] ?? [];
        
        // Save to database
        if (!empty($api_data)) {
            $save_result = saveTicketsToDatabase($api_data, $user['id']);
            if ($save_result) {
                if ($load_all) {
                    $success_message = "Loaded " . count($api_data) . " tickets from API and saved to database";
                } else {
                    $success_message = "Found " . count($api_data) . " tickets and saved to database";
                }
            } else {
                $error_message = "Failed to save tickets to database";
            }
        } else {
            $success_message = "No tickets found with the specified criteria";
        }
    } else {
        $error_message = $api_response['error'] ?? 'Failed to fetch data from API';
    }
    
    $loading = false;
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Increase memory limit and execution time for large exports
    ini_set('memory_limit', '1024M');
    ini_set('max_execution_time', 600);
    
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Set headers for Excel download first
    $filename = 'flight_load_tickets_' . date('Y-m-d_H-i-s') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";
    
    // Start table
    echo '<table border="1">';
    
    // Header row
    echo '<tr>';
    echo '<th>Passenger Name</th>';
    echo '<th>Document Number</th>';
    echo '<th>Contact</th>';
    echo '<th>Flight Number</th>';
    echo '<th>PNR</th>';
    echo '<th>Origin</th>';
    echo '<th>Destination</th>';
    echo '<th>Departure Date</th>';
    echo '<th>Sales Date (GMT)</th>';
    echo '<th>Ticket Code</th>';
    echo '<th>Flight Class</th>';
    echo '<th>Status</th>';
    echo '<th>Created At</th>';
    echo '</tr>';
    
    // Flush output buffer to start streaming
    if (ob_get_level()) {
        ob_end_flush();
    }
    
    // Get all tickets from database and stream output (no array storage)
    try {
        $db = getDBConnection();
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE 
                ticket_code LIKE ? OR 
                docs LIKE ? OR 
                passenger_contact LIKE ? OR 
                passenger_full_name LIKE ? OR 
                flight_no LIKE ? OR 
                pnr LIKE ?";
            $searchParam = "%$search%";
            $params = array_fill(0, 6, $searchParam);
        }
        
        // Get all records without LIMIT - stream results
        $sql = "SELECT * FROM tickets $whereClause ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Process rows one by one and output immediately (streaming)
        $rowCount = 0;
        while ($ticket = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($ticket['passenger_full_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['docs'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['passenger_contact'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['flight_no'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['pnr'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['origin'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['destination'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['departure_date'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['sales_date_gmt'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['ticket_code'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['flight_class_code'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['coupon_status'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($ticket['created_at'] ?? 'N/A') . '</td>';
            echo '</tr>';
            
            $rowCount++;
            
            // Flush output every 100 rows to prevent memory issues
            if ($rowCount % 100 == 0) {
                flush();
                if (ob_get_level() > 0) {
                    ob_flush();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting tickets for export: " . $e->getMessage());
    }
    
    echo '</table>';
    exit;
}

// Get tickets from database
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tickets_data = getAllTickets($page, $limit, $search);
$total_count = getTicketsCount($search);
$total_pages = ceil($total_count / $limit);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Load - All Tickets - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
                                Flight Load - All Tickets
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Search and view passenger ticket information
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>

                <!-- Search Form -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Search Filters
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Ticket Code
                                </label>
                                <input type="text" name="ticket_code" value="<?php echo htmlspecialchars($ticket_code); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Enter ticket code">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Document Number
                                </label>
                                <input type="text" name="docs" value="<?php echo htmlspecialchars($docs); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Enter document number">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Passenger Contact
                                </label>
                                <input type="text" name="passenger_contact" value="<?php echo htmlspecialchars($passenger_contact); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Enter contact number">
                            </div>
                            
                            <div class="md:col-span-3 flex justify-end space-x-3">
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-search mr-2"></i>
                                    Search
                                </button>
                                
                                <button type="button" onclick="loadAllTickets()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                    <i class="fas fa-download mr-2"></i>
                                    Load All Tickets
                                </button>
                                
                                <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-times mr-2"></i>
                                    Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success_message): ?>
                <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                <?php echo htmlspecialchars($success_message); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                <?php echo htmlspecialchars($error_message); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Loading State -->
                <?php if ($loading): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="p-8 text-center">
                        <div class="loading-spinner inline-block w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full"></div>
                        <p class="mt-4 text-gray-600 dark:text-gray-400">Loading passenger data...</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Database Search -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Search in Database
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="flex space-x-4">
                            <input type="hidden" name="ticket_code" value="<?php echo htmlspecialchars($ticket_code); ?>">
                            <input type="hidden" name="docs" value="<?php echo htmlspecialchars($docs); ?>">
                            <input type="hidden" name="passenger_contact" value="<?php echo htmlspecialchars($passenger_contact); ?>">
                            
                            <div class="flex-1">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Search in database (ticket code, docs, contact, name, flight, PNR)">
                            </div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Search DB
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Results Table -->
                <?php if (!empty($tickets_data)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Tickets in Database (<?php echo count($tickets_data); ?> results)
                            </h3>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                <i class="fas fa-file-excel mr-2"></i>
                                Download Excel
                            </a>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Passenger</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ticket</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($tickets_data as $ticket): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($ticket['passenger_full_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            Docs: <?php echo htmlspecialchars($ticket['docs'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($ticket['flight_no'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            PNR: <?php echo htmlspecialchars($ticket['pnr'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars(($ticket['origin'] ?? 'N/A') . ' → ' . ($ticket['destination'] ?? 'N/A')); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($ticket['departure_date'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($ticket['sales_date_gmt'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($ticket['ticket_code'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            Class: <?php echo htmlspecialchars($ticket['flight_class_code'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status = $ticket['coupon_status'] ?? 'N/A';
                                        $status_color = match($status) {
                                            'O' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'C' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'R' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                        };
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($ticket['passenger_contact'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Previous
                            </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Next
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $total_count); ?></span> of <span class="font-medium"><?php echo $total_count; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-blue-50 dark:bg-blue-900 border-blue-500 text-blue-600 dark:text-blue-200' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif (!$loading && empty($tickets_data) && ($ticket_code || $docs || $passenger_contact || $search)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="p-8 text-center">
                        <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Results Found</h3>
                        <p class="text-gray-600 dark:text-gray-400">Try adjusting your search criteria or load tickets from API first.</p>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function loadAllTickets() {
            // Show loading state
            const loadingElement = document.createElement('div');
            loadingElement.className = 'bg-white dark:bg-gray-800 shadow rounded-lg mb-6';
            loadingElement.innerHTML = `
                <div class="p-8 text-center">
                    <div class="loading-spinner inline-block w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full"></div>
                    <p class="mt-4 text-gray-600 dark:text-gray-400">Loading all passenger data from API...</p>
                </div>
            `;
            
            // Insert loading element after search form
            const searchForm = document.querySelector('.bg-white.dark\\:bg-gray-800.shadow.rounded-lg');
            searchForm.parentNode.insertBefore(loadingElement, searchForm.nextSibling);
            
            // Redirect to load all data
            setTimeout(() => {
                window.location.href = 'index.php?load_all=1';
            }, 1000);
        }
    </script>
</body>
</html>