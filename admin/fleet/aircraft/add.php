<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/aircraft/add.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_aircraft') {
        // Prepare data for insertion
        $data = [];
        
        // Basic Information
        if (!empty($_POST['registration'])) $data['registration'] = $_POST['registration'];
        if (!empty($_POST['serial_number'])) $data['serial_number'] = $_POST['serial_number'];
        if (!empty($_POST['aircraft_category'])) $data['aircraft_category'] = $_POST['aircraft_category'];
        if (!empty($_POST['manufacturer'])) $data['manufacturer'] = $_POST['manufacturer'];
        if (!empty($_POST['base_location'])) $data['base_location'] = $_POST['base_location'];
        if (!empty($_POST['responsible_personnel'])) $data['responsible_personnel'] = $_POST['responsible_personnel'];
        if (!empty($_POST['aircraft_owner'])) $data['aircraft_owner'] = $_POST['aircraft_owner'];
        if (!empty($_POST['aircraft_operator'])) $data['aircraft_operator'] = $_POST['aircraft_operator'];
        if (!empty($_POST['date_of_manufacture'])) $data['date_of_manufacture'] = $_POST['date_of_manufacture'];
        if (!empty($_POST['aircraft_type'])) $data['aircraft_type'] = $_POST['aircraft_type'];
        
        // Flight Capabilities
        $data['nvfr'] = isset($_POST['nvfr']) ? 1 : 0;
        $data['ifr'] = isset($_POST['ifr']) ? 1 : 0;
        $data['spifr'] = isset($_POST['spifr']) ? 1 : 0;
        
        // Engine Information
        if (!empty($_POST['engine_type'])) $data['engine_type'] = $_POST['engine_type'];
        if (!empty($_POST['number_of_engines'])) $data['number_of_engines'] = $_POST['number_of_engines'];
        if (!empty($_POST['engine_model'])) $data['engine_model'] = $_POST['engine_model'];
        if (!empty($_POST['engine_serial_number'])) $data['engine_serial_number'] = $_POST['engine_serial_number'];
        
        // Avionics
        if (!empty($_POST['avionics'])) $data['avionics'] = $_POST['avionics'];
        if (!empty($_POST['other_avionics_info'])) $data['other_avionics_information'] = $_POST['other_avionics_info'];
        
        // Configuration
        if (!empty($_POST['internal_configuration'])) $data['internal_configuration'] = $_POST['internal_configuration'];
        if (!empty($_POST['external_configuration'])) $data['external_configuration'] = $_POST['external_configuration'];
        
        // Airframe Type
        if (!empty($_POST['airframe_type'])) $data['airframe_type'] = $_POST['airframe_type'];
        
        // Status
        $data['enabled'] = isset($_POST['enabled']) ? 1 : 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Debug: Log the data being sent
        error_log("Aircraft data being sent: " . print_r($data, true));
        
        $result = createAircraft($data);
        if ($result) {
            $message = 'Aircraft added successfully.';
            // Redirect to aircraft list after successful creation
            header('Location: /admin/fleet/aircraft/index.php');
            exit();
        } else {
            $error = 'Failed to add aircraft. Check error logs for details.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Aircraft - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add New Aircraft</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create a new aircraft record</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Aircraft
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

                <!-- Aircraft Add Form -->
                <form method="POST" class="space-y-8">
                    <input type="hidden" name="action" value="add_aircraft">
                    
                    <!-- Basic Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="registration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Registration *</label>
                                <input type="text" id="registration" name="registration" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="serial_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Serial Number</label>
                                <input type="text" id="serial_number" name="serial_number"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="aircraft_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Category</label>
                                <select id="aircraft_category" name="aircraft_category"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Category</option>
                                    <option value="Airplane">Airplane</option>
                                    <option value="Helicopter">Helicopter</option>
                                    <option value="Glider">Glider</option>
                                    <option value="Balloon">Balloon</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="manufacturer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Manufacturer</label>
                                <input type="text" id="manufacturer" name="manufacturer"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="aircraft_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Type</label>
                                <input type="text" id="aircraft_type" name="aircraft_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="date_of_manufacture" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date of Manufacture</label>
                                <input type="date" id="date_of_manufacture" name="date_of_manufacture"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="base_location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Base Location</label>
                                <input type="text" id="base_location" name="base_location"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="responsible_personnel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Responsible Personnel</label>
                                <input type="text" id="responsible_personnel" name="responsible_personnel"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="aircraft_owner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Owner</label>
                                <input type="text" id="aircraft_owner" name="aircraft_owner"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="aircraft_operator" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aircraft Operator</label>
                                <input type="text" id="aircraft_operator" name="aircraft_operator"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Flight Capabilities -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Capabilities</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex items-center">
                                <input type="checkbox" id="nvfr" name="nvfr" value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="nvfr" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                    NVFR (Night VFR)
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="ifr" name="ifr" value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="ifr" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                    IFR (Instrument Flight Rules)
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="spifr" name="spifr" value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="spifr" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                    SPIFR (Single Pilot IFR)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Engine Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Engine Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="engine_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Engine Type</label>
                                <select id="engine_type" name="engine_type"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Engine Type</option>
                                    <option value="Piston">Piston</option>
                                    <option value="Turboprop">Turboprop</option>
                                    <option value="Turbojet">Turbojet</option>
                                    <option value="Turbofan">Turbofan</option>
                                    <option value="Turboshaft">Turboshaft</option>
                                    <option value="Electric">Electric</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="number_of_engines" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Number of Engines</label>
                                <input type="number" id="number_of_engines" name="number_of_engines" min="1" max="10"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="engine_model" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Engine Model</label>
                                <input type="text" id="engine_model" name="engine_model"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="engine_serial_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Engine Serial Number</label>
                                <input type="text" id="engine_serial_number" name="engine_serial_number"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Avionics -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Avionics</h2>
                        <div class="space-y-6">
                            <div>
                                <label for="avionics" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Avionics</label>
                                <textarea id="avionics" name="avionics" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Describe the avionics equipment..."></textarea>
                            </div>
                            <div>
                                <label for="other_avionics_info" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Other Avionics Information</label>
                                <textarea id="other_avionics_info" name="other_avionics_info" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Additional avionics information..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Configuration</h2>
                        <div class="space-y-6">
                            <div>
                                <label for="internal_configuration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Internal Configuration</label>
                                <textarea id="internal_configuration" name="internal_configuration" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Describe the internal configuration..."></textarea>
                            </div>
                            <div>
                                <label for="external_configuration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">External Configuration</label>
                                <textarea id="external_configuration" name="external_configuration" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Describe the external configuration..."></textarea>
                            </div>
                            <div>
                                <label for="airframe_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Airframe Type</label>
                                <input type="text" id="airframe_type" name="airframe_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Aircraft Status</h2>
                        <div class="flex items-center">
                            <input type="checkbox" id="enabled" name="enabled" value="1" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="enabled" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                Aircraft Enabled (Active)
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-3">
                        <a href="index.php" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Aircraft
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
