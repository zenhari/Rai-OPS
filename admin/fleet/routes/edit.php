<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/routes/edit.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get route ID from URL
$route_id = intval($_GET['id'] ?? 0);
if (!$route_id) {
    header('Location: /admin/fleet/routes/index.php');
    exit();
}

// Get route data
$route = getRouteById($route_id);
if (!$route) {
    header('Location: /admin/fleet/routes/index.php');
    exit();
}

// Get stations data for dropdowns
$stations = getAllStations('active');

// Get aircraft types data
$aircraft_types = getAircraftTypesWithManufacturer();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_route') {
        // Validate required fields
        $required_fields = ['route_code', 'route_name', 'origin_station_id', 'destination_station_id'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $error = 'Please fill in all required fields: ' . implode(', ', $missing_fields);
        } else {
            // Prepare data for update
            $data = [
                'route_code' => trim($_POST['route_code']),
                'route_name' => trim($_POST['route_name']),
                'origin_station_id' => intval($_POST['origin_station_id']),
                'destination_station_id' => intval($_POST['destination_station_id']),
                'distance_nm' => !empty($_POST['distance_nm']) ? floatval($_POST['distance_nm']) : null,
                'flight_time_minutes' => !empty($_POST['flight_time_minutes']) ? intval($_POST['flight_time_minutes']) : null,
                'aircraft_types' => !empty($_POST['aircraft_types']) ? $_POST['aircraft_types'] : null,
                'frequency' => !empty($_POST['frequency']) ? trim($_POST['frequency']) : null,
                'status' => $_POST['status'] ?? 'active',
                'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null
            ];
            
            // Validate that origin and destination are different
            if ($data['origin_station_id'] == $data['destination_station_id']) {
                $error = 'Origin and destination stations must be different.';
            } else {
                if (updateRoute($route_id, $data)) {
                    $message = 'Route updated successfully.';
                    // Refresh route data
                    $route = getRouteById($route_id);
                } else {
                    $error = 'Failed to update route. Please check if route code already exists.';
                }
            }
        }
    }
}

function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Route - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Route</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Update route information for Route ID: <?php echo safeOutput($route['id']); ?></p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="/admin/fleet/routes/index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Routes
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
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

                <!-- Route Edit Form -->
                <form method="POST" class="space-y-8">
                    <input type="hidden" name="action" value="update_route">
                    
                    <!-- Basic Route Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Route Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="route_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Route Code *</label>
                                <input type="text" id="route_code" name="route_code" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="e.g., THR-ISF" value="<?php echo safeOutput($route['route_code']); ?>">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Unique identifier for the route (e.g., THR-ISF)</p>
                            </div>
                            <div>
                                <label for="route_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Route Name *</label>
                                <input type="text" id="route_name" name="route_name" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="e.g., Tehran to Isfahan" value="<?php echo safeOutput($route['route_name']); ?>">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Descriptive name for the route</p>
                            </div>
                        </div>
                    </div>

                    <!-- Station Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Station Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="origin_station_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Origin Station *</label>
                                <select id="origin_station_id" name="origin_station_id" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select origin station...</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo $station['id']; ?>" 
                                                <?php echo ($route['origin_station_id'] == $station['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['station_name'] . ' (' . $station['iata_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="destination_station_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destination Station *</label>
                                <select id="destination_station_id" name="destination_station_id" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select destination station...</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo $station['id']; ?>" 
                                                <?php echo ($route['destination_station_id'] == $station['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['station_name'] . ' (' . $station['iata_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Flight Details -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="distance_nm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Distance (NM)</label>
                                <input type="number" step="0.01" id="distance_nm" name="distance_nm"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="180.50" value="<?php echo safeOutput($route['distance_nm']); ?>">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Distance in nautical miles</p>
                            </div>
                            <div>
                                <label for="flight_time_minutes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flight Time (Minutes)</label>
                                <input type="number" id="flight_time_minutes" name="flight_time_minutes"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="60" value="<?php echo safeOutput($route['flight_time_minutes']); ?>">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Estimated flight time in minutes</p>
                            </div>
                            <div>
                                <label for="frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Frequency</label>
                                <input type="text" id="frequency" name="frequency"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Daily, Mon-Fri, Weekly" value="<?php echo safeOutput($route['frequency']); ?>">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Flight frequency (e.g., Daily, Mon-Fri)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Aircraft and Status -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Aircraft and Status</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="aircraft_types" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Types</label>
                                <div class="space-y-2">
                                    <div class="flex flex-wrap gap-2" id="selected-aircraft-types">
                                        <!-- Selected aircraft types will appear here -->
                                    </div>
                                    <select id="aircraft_type_select" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">Select Aircraft Type</option>
                                        <?php 
                                        $grouped_types = [];
                                        foreach ($aircraft_types as $type) {
                                            $grouped_types[$type['manufacturer']][] = $type['aircraft_type'];
                                        }
                                        foreach ($grouped_types as $manufacturer => $types): 
                                        ?>
                                            <optgroup label="<?php echo htmlspecialchars($manufacturer); ?>">
                                                <?php foreach ($types as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type); ?>">
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" id="aircraft_types" name="aircraft_types" value="<?php echo safeOutput($route['aircraft_types']); ?>">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Select aircraft types from available fleet</p>
                                </div>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                                <select id="status" name="status"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="active" <?php echo ($route['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($route['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($route['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Additional Information</h2>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                            <textarea id="notes" name="notes" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                      placeholder="Additional notes about this route..."><?php echo safeOutput($route['notes']); ?></textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3">
                        <a href="/admin/fleet/routes/index.php" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Update Route
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Aircraft types management
        let selectedAircraftTypes = [];
        
        // Form validation and initialization
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const originSelect = document.getElementById('origin_station_id');
            const destinationSelect = document.getElementById('destination_station_id');
            
            // Initialize aircraft types from form data
            const aircraftTypesInput = document.getElementById('aircraft_types');
            if (aircraftTypesInput.value) {
                try {
                    selectedAircraftTypes = JSON.parse(aircraftTypesInput.value);
                    updateAircraftTypesDisplay();
                } catch (e) {
                    console.error('Error parsing aircraft types:', e);
                }
            }
            
            // Add event listener for aircraft type selection
            const aircraftTypeSelect = document.getElementById('aircraft_type_select');
            aircraftTypeSelect.addEventListener('change', function() {
                if (this.value && !selectedAircraftTypes.includes(this.value)) {
                    selectedAircraftTypes.push(this.value);
                    updateAircraftTypesDisplay();
                    this.value = ''; // Reset selection
                }
            });
            
            form.addEventListener('submit', function(e) {
                // Check if origin and destination are the same
                if (originSelect.value && destinationSelect.value && originSelect.value === destinationSelect.value) {
                    e.preventDefault();
                    alert('Origin and destination stations must be different.');
                    return false;
                }
            });
            
            // Auto-generate route code when stations are selected
            function generateRouteCode() {
                const originOption = originSelect.options[originSelect.selectedIndex];
                const destinationOption = destinationSelect.options[destinationSelect.selectedIndex];
                
                if (originOption.value && destinationOption.value) {
                    const originCode = originOption.text.match(/\(([^)]+)\)/)?.[1];
                    const destinationCode = destinationOption.text.match(/\(([^)]+)\)/)?.[1];
                    
                    if (originCode && destinationCode) {
                        const routeCode = originCode + '-' + destinationCode;
                        document.getElementById('route_code').value = routeCode;
                    }
                }
            }
            
            originSelect.addEventListener('change', generateRouteCode);
            destinationSelect.addEventListener('change', generateRouteCode);
        });
        
        function updateAircraftTypesDisplay() {
            const container = document.getElementById('selected-aircraft-types');
            const hiddenInput = document.getElementById('aircraft_types');
            
            container.innerHTML = '';
            
            selectedAircraftTypes.forEach(function(type, index) {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                badge.innerHTML = `
                    ${type}
                    <button type="button" onclick="removeAircraftType(${index})" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(badge);
            });
            
            // Update hidden input
            hiddenInput.value = JSON.stringify(selectedAircraftTypes);
        }
        
        function removeAircraftType(index) {
            selectedAircraftTypes.splice(index, 1);
            updateAircraftTypesDisplay();
        }
    </script>
</body>
</html>
