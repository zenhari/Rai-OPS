<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/flights/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_flight':
            $id = intval($_POST['id'] ?? 0);
            if (deleteFlight($id)) {
                $message = 'Flight deleted successfully.';
            } else {
                $error = 'Failed to delete flight.';
            }
            break;
    }
}

// Pagination settings
$perPage = 100;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Get filter date if provided
$filterDate = isset($_GET['filterTaskStart']) ? trim($_GET['filterTaskStart']) : '';

// Get total flights count (with filter if applied)
$db = getDBConnection();
if ($filterDate && $filterDate !== '') {
    // Filter by TaskStart date, or FltDate if TaskStart is NULL
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM flights 
        WHERE (
            (TaskStart IS NOT NULL AND TaskStart != '' AND DATE(TaskStart) = ?) 
            OR 
            (TaskStart IS NULL OR TaskStart = '' AND FltDate IS NOT NULL AND FltDate != '' AND DATE(FltDate) = ?)
        )
    ");
    $stmt->execute([$filterDate, $filterDate]);
    $totalFlights = $stmt->fetchColumn();
} else {
    $totalFlights = getFlightsCount();
}
$totalPages = ceil($totalFlights / $perPage);

// Get flights data with pagination and filter
if ($filterDate && $filterDate !== '') {
    // Filter by TaskStart date, or FltDate if TaskStart is NULL
    $stmt = $db->prepare("
        SELECT * 
        FROM flights 
        WHERE (
            (TaskStart IS NOT NULL AND TaskStart != '' AND DATE(TaskStart) = ?) 
            OR 
            (TaskStart IS NULL OR TaskStart = '' AND FltDate IS NOT NULL AND FltDate != '' AND DATE(FltDate) = ?)
        )
        ORDER BY COALESCE(TaskStart, FltDate) DESC 
        LIMIT " . intval($perPage) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute([$filterDate, $filterDate]);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $flights = getAllFlights($perPage, $offset);
}
$stats = getFlightStats();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Management - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage flight operations and schedules</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Flight
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../includes/permission_banner.php'; ?>
                
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

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-plane text-blue-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Flights</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($stats['total_flights']); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock text-green-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Hours</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($stats['total_hours'], 1); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar text-purple-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">This Month</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($stats['flights_this_month']); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-helicopter text-orange-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Aircraft</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($stats['active_aircraft']); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flights Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Flight List</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">All flight operations and schedules</p>
                            </div>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <label for="filterTaskStart" class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center whitespace-nowrap">
                                    <i class="fas fa-filter mr-2"></i>
                                    <span class="hidden sm:inline">Filter by Task Start Date:</span>
                                    <span class="sm:hidden">Filter by Date:</span>
                                </label>
                                <div class="flex items-center gap-2 w-full sm:w-auto">
                                    <input type="date" 
                                           id="filterTaskStart" 
                                           value="<?php echo isset($_GET['filterTaskStart']) ? htmlspecialchars($_GET['filterTaskStart']) : ''; ?>"
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm flex-1 sm:flex-none"
                                           onchange="filterFlightsByDate(this.value)">
                                    <button onclick="clearDateFilter()" 
                                            class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200 whitespace-nowrap">
                                        <i class="fas fa-times mr-1"></i>
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight No.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pilot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Minutes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($flights)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No flights found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($flights as $flight): 
                                        // Extract date from TaskStart for filtering
                                        $taskStartDate = '';
                                        if (!empty($flight['TaskStart'])) {
                                            $taskStartDate = date('Y-m-d', strtotime($flight['TaskStart']));
                                        } elseif (!empty($flight['FltDate'])) {
                                            $taskStartDate = date('Y-m-d', strtotime($flight['FltDate']));
                                        }
                                    ?>
                                        <tr class="flight-row hover:bg-gray-50 dark:hover:bg-gray-700" data-task-start-date="<?php echo htmlspecialchars($taskStartDate); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['TaskName'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php 
                                                    if (!empty($flight['TaskStart'])) {
                                                        echo date('M d, Y H:i', strtotime($flight['TaskStart']));
                                                    } elseif (!empty($flight['FltDate'])) {
                                                        echo date('M d, Y H:i', strtotime($flight['FltDate']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['Rego'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($flight['ACType'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php
                                                    // Check if Crew1 field has a value (user ID) - formerly LSP
                                                    if (!empty($flight['Crew1']) && is_numeric($flight['Crew1'])) {
                                                        // Look up user information from users table
                                                        $lspUser = getUserById($flight['Crew1']);
                                                        if ($lspUser) {
                                                            echo htmlspecialchars(($lspUser['first_name'] ?? '') . ' ' . ($lspUser['last_name'] ?? ''));
                                                        } else {
                                                            // Fallback to flights table if user not found
                                                            echo htmlspecialchars(($flight['FirstName'] ?? '') . ' ' . ($flight['LastName'] ?? ''));
                                                        }
                                                    } else {
                                                        // Fallback to flights table FirstName/LastName
                                                        echo htmlspecialchars(($flight['FirstName'] ?? '') . ' ' . ($flight['LastName'] ?? ''));
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php 
                                                    if (!empty($flight['FlightHours'])) {
                                                        $minutes = round($flight['FlightHours'] * 60);
                                                        echo number_format($minutes, 0) . ' min';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($flight['FlightLocked']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <i class="fas fa-lock mr-1"></i>
                                                        Locked
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                        <i class="fas fa-edit mr-1"></i>
                                                        Editable
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="edit.php?id=<?php echo $flight['id']; ?>" 
                                                       class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="deleteFlight(<?php echo $flight['id']; ?>, '<?php echo htmlspecialchars($flight['FlightID'] ?? 'Flight'); ?>')" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($offset + $perPage, $totalFlights); ?></span> of 
                                    <span class="font-medium"><?php echo number_format($totalFlights); ?></span> flights
                                </div>
                                <div class="flex items-center gap-2">
                                    <!-- Previous Button -->
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?page=<?php echo $currentPage - 1; ?><?php echo isset($_GET['filterTaskStart']) ? '&filterTaskStart=' . htmlspecialchars($_GET['filterTaskStart']) : ''; ?>" 
                                           class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                                            <i class="fas fa-chevron-left mr-1"></i>
                                            Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md cursor-not-allowed">
                                            <i class="fas fa-chevron-left mr-1"></i>
                                            Previous
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Page Info -->
                                    <div class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Page <span class="font-semibold"><?php echo $currentPage; ?></span> of 
                                        <span class="font-semibold"><?php echo $totalPages; ?></span>
                                    </div>
                                    
                                    <!-- Next Button -->
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?page=<?php echo $currentPage + 1; ?><?php echo isset($_GET['filterTaskStart']) ? '&filterTaskStart=' . htmlspecialchars($_GET['filterTaskStart']) : ''; ?>" 
                                           class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                                            Next
                                            <i class="fas fa-chevron-right ml-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md cursor-not-allowed">
                                            Next
                                            <i class="fas fa-chevron-right ml-1"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Flight</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to delete flight <span id="flightId" class="font-medium"></span>? 
                            This action cannot be undone.
                        </p>
                    </div>
                    <div class="flex justify-center space-x-3 mt-4">
                        <button onclick="closeDeleteModal()" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <form id="deleteForm" method="POST" class="inline">
                            <input type="hidden" name="action" value="delete_flight">
                            <input type="hidden" name="id" id="deleteFlightId">
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Filter flights by TaskStart date
        function filterFlightsByDate(selectedDate) {
            // Redirect to page 1 with filter parameter
            const url = new URL(window.location.href);
            if (selectedDate && selectedDate !== '') {
                url.searchParams.set('filterTaskStart', selectedDate);
            } else {
                url.searchParams.delete('filterTaskStart');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        // Format date for display (YYYY-MM-DD to readable format)
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString + 'T00:00:00');
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
        
        // Clear date filter
        function clearDateFilter() {
            const url = new URL(window.location.href);
            url.searchParams.delete('filterTaskStart');
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        function deleteFlight(id, flightId) {
            document.getElementById('deleteFlightId').value = id;
            document.getElementById('flightId').textContent = flightId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
