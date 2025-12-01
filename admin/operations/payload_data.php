<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/payload_data.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get selected aircraft from GET or POST
$selectedAircraftId = null;
if (isset($_GET['aircraft_id']) && !empty($_GET['aircraft_id'])) {
    $selectedAircraftId = intval($_GET['aircraft_id']);
} elseif (isset($_POST['aircraft_id']) && !empty($_POST['aircraft_id'])) {
    $selectedAircraftId = intval($_POST['aircraft_id']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_payload':
            $routeCode = trim($_POST['route_code'] ?? '');
            $aircraftId = !empty($_POST['aircraft_id']) ? intval($_POST['aircraft_id']) : null;
            $temperature20 = !empty($_POST['temperature_20']) ? floatval($_POST['temperature_20']) : null;
            $temperature25 = !empty($_POST['temperature_25']) ? floatval($_POST['temperature_25']) : null;
            $temperature35 = !empty($_POST['temperature_35']) ? floatval($_POST['temperature_35']) : null;
            $temperature40 = !empty($_POST['temperature_40']) ? floatval($_POST['temperature_40']) : null;
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($routeCode)) {
                $error = 'Route code is required.';
            } elseif (empty($aircraftId)) {
                $error = 'Aircraft selection is required.';
            } else {
                $data = [
                    'temperature_20' => $temperature20,
                    'temperature_25' => $temperature25,
                    'temperature_35' => $temperature35,
                    'temperature_40' => $temperature40,
                    'notes' => $notes
                ];
                
                if (savePayloadData($routeCode, $aircraftId, $data, $current_user['id'])) {
                    $message = 'Payload data saved successfully.';
                    $selectedAircraftId = $aircraftId; // Keep the selected aircraft
                } else {
                    $error = 'Failed to save payload data.';
                }
            }
            break;
            
        case 'delete_payload':
            $routeCode = trim($_POST['route_code'] ?? '');
            $aircraftId = !empty($_POST['aircraft_id']) ? intval($_POST['aircraft_id']) : null;
            
            if (empty($routeCode) || empty($aircraftId)) {
                $error = 'Route code and aircraft are required.';
            } else {
                if (deletePayloadData($routeCode, $aircraftId)) {
                    $message = 'Payload data deleted successfully.';
                } else {
                    $error = 'Failed to delete payload data.';
                }
            }
            break;
    }
}

// Get all aircraft for dropdown
$aircraft = getAllAircraftForPayload();

// Get all routes for dropdown (all routes with ICAO codes)
$allRoutesForDropdown = getAllRoutesForPayloadDropdown();

// Get all routes with payload data (filtered by aircraft if selected)
$routes = getAllRoutesWithPayloadData($selectedAircraftId);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payload Data - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payload Data</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage payload weights for routes at different temperatures</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-weight text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Weight Management</span>
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

                <!-- Aircraft Filter -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Aircraft</h2>
                    <form method="GET" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label for="aircraft_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Aircraft
                            </label>
                            <select id="aircraft_id" name="aircraft_id" 
                                    onchange="this.form.submit()"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- All Aircraft --</option>
                                <?php foreach ($aircraft as $ac): ?>
                                    <option value="<?php echo $ac['id']; ?>" 
                                            <?php echo $selectedAircraftId == $ac['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ac['registration'] . ' - ' . ($ac['aircraft_type'] ?? 'N/A')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($selectedAircraftId): ?>
                        <button type="button" onclick="window.location.href='?aircraft_id='"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Clear Filter
                        </button>
                        <?php endif; ?>
                    </form>
                    <?php if ($selectedAircraftId): ?>
                        <?php 
                        $selectedAircraft = array_filter($aircraft, function($ac) use ($selectedAircraftId) {
                            return $ac['id'] == $selectedAircraftId;
                        });
                        $selectedAircraft = reset($selectedAircraft);
                        ?>
                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <i class="fas fa-plane mr-2"></i>
                                Showing payload data for: <strong><?php echo htmlspecialchars($selectedAircraft['registration'] ?? 'Unknown'); ?></strong>
                                <?php if ($selectedAircraft['aircraft_type'] ?? null): ?>
                                    (<?php echo htmlspecialchars($selectedAircraft['aircraft_type']); ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Routes Payload Data Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Route Payload Data</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Payload weights in pounds at different temperatures
                                    <?php if ($selectedAircraftId): ?>
                                        for selected aircraft
                                    <?php else: ?>
                                        (select an aircraft to filter)
                                    <?php endif; ?>
                                </p>
                            </div>
                            <button onclick="openAddPayloadModal()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Payload Data
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Origin</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Destination</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">20°C (lbs)</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">25°C (lbs)</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">35°C (lbs)</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">40°C (lbs)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($routes)): ?>
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
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
                                                <?php if ($route['aircraft_reg'] && $route['aircraft_type']): ?>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($route['aircraft_reg']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($route['aircraft_type']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo $route['temperature_20'] ? number_format($route['temperature_20'], 2) . ' lbs' : '-'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo $route['temperature_25'] ? number_format($route['temperature_25'], 2) . ' lbs' : '-'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo $route['temperature_35'] ? number_format($route['temperature_35'], 2) . ' lbs' : '-'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo $route['temperature_40'] ? number_format($route['temperature_40'], 2) . ' lbs' : '-'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openEditPayloadModal('<?php echo htmlspecialchars($route['route_code']); ?>', '<?php echo htmlspecialchars($route['route_name']); ?>', <?php echo $route['aircraft_id'] ?? 'null'; ?>, <?php echo htmlspecialchars(json_encode([
                                                    'temperature_20' => $route['temperature_20'],
                                                    'temperature_25' => $route['temperature_25'],
                                                    'temperature_35' => $route['temperature_35'],
                                                    'temperature_40' => $route['temperature_40'],
                                                    'notes' => $route['payload_notes'] ?? ''
                                                ])); ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($route['payload_id']): ?>
                                                <button onclick="deletePayloadData('<?php echo htmlspecialchars($route['route_code']); ?>', <?php echo $route['aircraft_id'] ?? 'null'; ?>, '<?php echo htmlspecialchars($route['route_name']); ?>')" 
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
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

    <!-- Add Payload Data Modal -->
    <div id="addPayloadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Payload Data</h3>
                    <button onclick="closeAddPayloadModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="addPayloadForm" class="space-y-4">
                    <input type="hidden" name="action" value="save_payload">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Aircraft <span class="text-red-500">*</span>
                        </label>
                        <select id="add_aircraft_select" name="aircraft_id" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Aircraft --</option>
                            <?php foreach ($aircraft as $ac): ?>
                                <option value="<?php echo $ac['id']; ?>" 
                                        <?php echo ($selectedAircraftId == $ac['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ac['registration'] . ' - ' . ($ac['aircraft_type'] ?? 'N/A')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select the aircraft for this payload data</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Route <span class="text-red-500">*</span>
                        </label>
                        <select id="add_route_select" name="route_code" required
                                onchange="updateRouteDisplay()"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Route --</option>
                            <?php foreach ($allRoutesForDropdown as $route): ?>
                                <option value="<?php echo htmlspecialchars($route['route_code']); ?>" 
                                        data-route-name="<?php echo htmlspecialchars($route['route_name']); ?>">
                                    <?php echo htmlspecialchars($route['route_code'] . ' - ' . $route['route_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="add_route_info"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="add_temperature_20" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 20°C (lbs)
                            </label>
                            <input type="number" id="add_temperature_20" name="temperature_20" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                        <div>
                            <label for="add_temperature_25" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 25°C (lbs)
                            </label>
                            <input type="number" id="add_temperature_25" name="temperature_25" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                        <div>
                            <label for="add_temperature_35" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 35°C (lbs)
                            </label>
                            <input type="number" id="add_temperature_35" name="temperature_35" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                        <div>
                            <label for="add_temperature_40" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 40°C (lbs)
                            </label>
                            <input type="number" id="add_temperature_40" name="temperature_40" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                    </div>
                    
                    <div>
                        <label for="add_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes
                        </label>
                        <textarea id="add_notes" name="notes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Additional notes about this payload data"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddPayloadModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Payload Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Payload Data Modal -->
    <div id="editPayloadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Payload Data</h3>
                    <button onclick="closeEditPayloadModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="payloadForm" class="space-y-4">
                    <input type="hidden" name="action" value="save_payload">
                    <input type="hidden" id="edit_route_code" name="route_code">
                    <input type="hidden" name="aircraft_id" id="edit_aircraft_id" value="<?php echo $selectedAircraftId ?? ''; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Aircraft <span class="text-red-500">*</span>
                        </label>
                        <select id="edit_aircraft_select" required
                                onchange="document.getElementById('edit_aircraft_id').value = this.value"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Aircraft --</option>
                            <?php foreach ($aircraft as $ac): ?>
                                <option value="<?php echo $ac['id']; ?>" 
                                        <?php echo ($selectedAircraftId == $ac['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ac['registration'] . ' - ' . ($ac['aircraft_type'] ?? 'N/A')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select the aircraft for this payload data</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Route Code
                        </label>
                        <input type="text" id="edit_route_code_display" readonly
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="edit_route_name_display"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_temperature_20" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 20°C (lbs)
                            </label>
                            <input type="number" id="edit_temperature_20" name="temperature_20" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                        <div>
                            <label for="edit_temperature_25" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 25°C (lbs)
                            </label>
                            <input type="number" id="edit_temperature_25" name="temperature_25" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                        <div>
                            <label for="edit_temperature_35" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 35°C (lbs)
                            </label>
                            <input type="number" id="edit_temperature_35" name="temperature_35" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                        <div>
                            <label for="edit_temperature_40" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payload at 40°C (lbs)
                            </label>
                            <input type="number" id="edit_temperature_40" name="temperature_40" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., 9308">
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes
                        </label>
                        <textarea id="edit_notes" name="notes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Additional notes about this payload data"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditPayloadModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Save Payload Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deletePayloadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Payload Data</h3>
                    <button onclick="closeDeletePayloadModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Are you sure you want to delete payload data for route <span id="delete_route_code" class="font-medium"></span>?
                </p>
                
                <form method="POST" id="deletePayloadForm">
                    <input type="hidden" name="action" value="delete_payload">
                    <input type="hidden" id="delete_route_code_input" name="route_code">
                    <input type="hidden" id="delete_aircraft_id_input" name="aircraft_id">
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeletePayloadModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddPayloadModal() {
            // Clear all fields
            document.getElementById('add_aircraft_select').value = '<?php echo $selectedAircraftId ?? ''; ?>';
            document.getElementById('add_route_select').value = '';
            document.getElementById('add_temperature_20').value = '';
            document.getElementById('add_temperature_25').value = '';
            document.getElementById('add_temperature_35').value = '';
            document.getElementById('add_temperature_40').value = '';
            document.getElementById('add_notes').value = '';
            document.getElementById('add_route_info').textContent = '';
            
            document.getElementById('addPayloadModal').classList.remove('hidden');
        }

        function closeAddPayloadModal() {
            document.getElementById('addPayloadModal').classList.add('hidden');
        }

        function updateRouteDisplay() {
            const routeSelect = document.getElementById('add_route_select');
            const selectedOption = routeSelect.options[routeSelect.selectedIndex];
            const routeInfo = document.getElementById('add_route_info');
            
            if (selectedOption.value) {
                const routeName = selectedOption.getAttribute('data-route-name');
                routeInfo.textContent = 'Route: ' + (routeName || '');
            } else {
                routeInfo.textContent = '';
            }
        }

        function openEditPayloadModal(routeCode, routeName, aircraftId, payloadData) {
            document.getElementById('edit_route_code').value = routeCode;
            document.getElementById('edit_route_code_display').value = routeCode;
            document.getElementById('edit_route_name_display').textContent = routeName;
            
            // Set aircraft selection
            const aircraftSelect = document.getElementById('edit_aircraft_select');
            const aircraftIdInput = document.getElementById('edit_aircraft_id');
            if (aircraftId) {
                aircraftSelect.value = aircraftId;
                aircraftIdInput.value = aircraftId;
            } else if (<?php echo $selectedAircraftId ?? 'null'; ?>) {
                aircraftSelect.value = <?php echo $selectedAircraftId ?? 'null'; ?>;
                aircraftIdInput.value = <?php echo $selectedAircraftId ?? 'null'; ?>;
            }
            
            // Set temperature values
            document.getElementById('edit_temperature_20').value = payloadData.temperature_20 || '';
            document.getElementById('edit_temperature_25').value = payloadData.temperature_25 || '';
            document.getElementById('edit_temperature_35').value = payloadData.temperature_35 || '';
            document.getElementById('edit_temperature_40').value = payloadData.temperature_40 || '';
            document.getElementById('edit_notes').value = payloadData.notes || '';
            
            document.getElementById('editPayloadModal').classList.remove('hidden');
        }

        function closeEditPayloadModal() {
            document.getElementById('editPayloadModal').classList.add('hidden');
        }

        function deletePayloadData(routeCode, aircraftId, routeName) {
            document.getElementById('delete_route_code').textContent = routeCode + ' - ' + routeName;
            document.getElementById('delete_route_code_input').value = routeCode;
            document.getElementById('delete_aircraft_id_input').value = aircraftId || '';
            document.getElementById('deletePayloadModal').classList.remove('hidden');
        }

        function closeDeletePayloadModal() {
            document.getElementById('deletePayloadModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addPayloadModal');
            const editModal = document.getElementById('editPayloadModal');
            const deleteModal = document.getElementById('deletePayloadModal');
            
            if (event.target === addModal) {
                closeAddPayloadModal();
            } else if (event.target === editModal) {
                closeEditPayloadModal();
            } else if (event.target === deleteModal) {
                closeDeletePayloadModal();
            }
        }
    </script>
</body>
</html>
