<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/fleet/routes/fix_time.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_fix_time':
            $routeId = intval($_POST['route_id'] ?? 0);
            $fixTime = intval($_POST['fix_time'] ?? 0);
            
            if ($routeId <= 0) {
                $error = 'Invalid route selected.';
            } elseif ($fixTime < 0) {
                $error = 'Fix time cannot be negative.';
            } else {
                if (updateRouteFixTime($routeId, $fixTime)) {
                    $message = 'Fix time updated successfully.';
                } else {
                    $error = 'Failed to update fix time.';
                }
            }
            break;
            
        case 'bulk_update_fix_time':
            $fixTime = intval($_POST['bulk_fix_time'] ?? 0);
            
            if ($fixTime < 0) {
                $error = 'Fix time cannot be negative.';
            } else {
                $updated = bulkUpdateFixTime($fixTime);
                if ($updated > 0) {
                    $message = "Successfully updated fix time for {$updated} route(s).";
                } else {
                    $error = 'No routes were updated.';
                }
            }
            break;
    }
}

// Get all routes with their fix times
$routes = getAllRoutesWithFixTime();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Fix Time Management - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Route Fix Time Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage fix times for all flight routes</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-clock text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Time Management</span>
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

                <!-- Bulk Update Section -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Bulk Update Fix Time</h2>
                    <form method="POST" class="flex items-end space-x-4">
                        <input type="hidden" name="action" value="bulk_update_fix_time">
                        <div class="flex-1">
                            <label for="bulk_fix_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Fix Time (minutes)
                            </label>
                            <input type="number" id="bulk_fix_time" name="bulk_fix_time" min="0" step="1"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="Enter fix time in minutes">
                        </div>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200"
                                onclick="return confirm('Are you sure you want to update fix time for ALL routes?')">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Update All Routes
                        </button>
                    </form>
                </div>

                <!-- Routes Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Routes Fix Time</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage individual route fix times</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Origin</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Destination</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fix Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($routes)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No routes found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($routes as $route): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($route['route_code']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($route['route_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($route['origin_station']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($route['destination_station']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo $route['flight_time_minutes'] ? $route['flight_time_minutes'] . ' min' : 'N/A'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo $route['fix_time'] ? $route['fix_time'] . ' min' : 'Not set'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                                    echo $route['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                         ($route['status'] === 'inactive' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'); 
                                                ?>">
                                                    <?php echo ucfirst($route['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openEditFixTimeModal(<?php echo $route['id']; ?>, '<?php echo htmlspecialchars($route['route_code']); ?>', <?php echo $route['fix_time'] ?? 0; ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    <i class="fas fa-edit"></i>
                                                </button>
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

    <!-- Edit Fix Time Modal -->
    <div id="editFixTimeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Fix Time</h3>
                    <button onclick="closeEditFixTimeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_fix_time">
                    <input type="hidden" id="edit_route_id" name="route_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Route Code
                        </label>
                        <input type="text" id="edit_route_code" readonly
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    </div>
                    
                    <div>
                        <label for="edit_fix_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Fix Time (minutes) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="edit_fix_time" name="fix_time" min="0" step="1" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Enter fix time in minutes (0 to clear)</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditFixTimeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Fix Time
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditFixTimeModal(routeId, routeCode, currentFixTime) {
            document.getElementById('edit_route_id').value = routeId;
            document.getElementById('edit_route_code').value = routeCode;
            document.getElementById('edit_fix_time').value = currentFixTime || 0;
            document.getElementById('editFixTimeModal').classList.remove('hidden');
        }

        function closeEditFixTimeModal() {
            document.getElementById('editFixTimeModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editFixTimeModal');
            if (event.target === modal) {
                closeEditFixTimeModal();
            }
        }
    </script>
</body>
</html>
