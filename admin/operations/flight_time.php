<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/flight_time.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Get flight time summary
$flightTimeSummary = getFlightTimeSummary();

// Handle AJAX request for crew member details
if (isset($_GET['action']) && $_GET['action'] === 'get_crew_details') {
    $crewId = isset($_GET['crew_id']) ? intval($_GET['crew_id']) : 0;
    if ($crewId > 0) {
        $crewData = getCrewMemberFlightHoursWithPeriods($crewId);
        // Get user info for display
        $user = getUserById($crewId);
        if ($user) {
            $crewData['name'] = $user['first_name'] . ' ' . $user['last_name'];
        }
        header('Content-Type: application/json');
        echo json_encode($crewData);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Time - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../../assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-clock mr-2"></i>Flight Time
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Crew flight hours calculation and tracking
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <button onclick="refreshData()" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-sync-alt mr-2"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Crew Members</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo count($flightTimeSummary); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-clock text-green-600 dark:text-green-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Flight Hours</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php 
                                    $totalHours = array_sum(array_column($flightTimeSummary, 'total_hours'));
                                    echo number_format($totalHours, 2);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-plane text-purple-600 dark:text-purple-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Flights</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php 
                                    $totalFlights = array_sum(array_column($flightTimeSummary, 'flight_count'));
                                    echo number_format($totalFlights);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flight Time Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-list mr-2"></i>Crew Flight Hours
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Crew Member
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Total Hours
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="24 Hours Before">
                                        24H
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="7 Days Before">
                                        7D
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="14 Days Before">
                                        14D
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="28 Days Before">
                                        28D
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="12 Months Before">
                                        12M
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="1 Calendar Year">
                                        1CY
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" title="168 Hours (7 Days) Before">
                                        168H
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($flightTimeSummary)): ?>
                                <tr>
                                    <td colspan="10" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        No flight data found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($flightTimeSummary as $crew): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                                        <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($crew['name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['total_hours'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['24h'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['7d'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['14d'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['28d'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['12m'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['1cy'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo number_format($crew['periods']['168h'], 2); ?> hrs
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="viewCrewDetails(<?php echo intval($crew['id']); ?>, '<?php echo htmlspecialchars($crew['name']); ?>')"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Crew Details Modal -->
    <div id="crewDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modalTitle">
                        <i class="fas fa-user mr-2"></i>Crew Member Details
                    </h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="modalContent" class="max-h-96 overflow-y-auto">
                    <!-- Content will be loaded here -->
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button onclick="closeModal()" 
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function refreshData() {
            location.reload();
        }

        function viewCrewDetails(crewId, crewName) {
            const modal = document.getElementById('crewDetailsModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalContent = document.getElementById('modalContent');
            
            modalTitle.innerHTML = `<i class="fas fa-user mr-2"></i>${crewName} - Flight Details`;
            modalContent.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i><p class="mt-2">Loading...</p></div>';
            
            modal.classList.remove('hidden');
            
            // Fetch crew details
            fetch(`?action=get_crew_details&crew_id=${crewId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Hours</p>
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${parseFloat(data.total_hours).toFixed(2)} hrs</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Flights</p>
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${data.flight_count}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">24 Hours</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['24h']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">7 Days</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['7d']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">14 Days</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['14d']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">28 Days</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['28d']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">12 Months</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['12m']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">1 Calendar Year</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['1cy']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">168 Hours</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">${data.periods ? parseFloat(data.periods['168h']).toFixed(2) : '0.00'} hrs</p>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    if (data.flights && data.flights.length > 0) {
                        html += `
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Route</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Aircraft</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Start</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">End</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        `;
                        
                        data.flights.forEach(flight => {
                            const date = new Date(flight.date).toLocaleDateString();
                            const startTime = new Date(flight.task_start).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                            const endTime = new Date(flight.task_end).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                            
                            html += `
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${date}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${flight.route || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${flight.rego || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${startTime}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${endTime}</td>
                                    <td class="px-4 py-2 text-sm font-semibold text-blue-600 dark:text-blue-400">${parseFloat(flight.hours).toFixed(2)} hrs</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        html += '<p class="text-center text-gray-500 dark:text-gray-400 py-4">No flight details available</p>';
                    }
                    
                    modalContent.innerHTML = html;
                })
                .catch(error => {
                    modalContent.innerHTML = '<p class="text-center text-red-500 py-4">Error loading details</p>';
                });
        }

        function closeModal() {
            document.getElementById('crewDetailsModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('crewDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
