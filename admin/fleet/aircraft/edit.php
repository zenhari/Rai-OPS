<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/aircraft/edit.php');

$aircraft_id = intval($_GET['id'] ?? 0);
$aircraft = getAircraftById($aircraft_id);

if (!$aircraft) {
    header('Location: /admin/fleet/aircraft/index.php');
    exit();
}

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Helper function to safely output values
function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aircraft_data = [
        'registration' => trim($_POST['registration'] ?? ''),
        'serial_number' => trim($_POST['serial_number'] ?? ''),
        'aircraft_category' => trim($_POST['aircraft_category'] ?? ''),
        'manufacturer' => trim($_POST['manufacturer'] ?? ''),
        'base_location' => trim($_POST['base_location'] ?? ''),
        'responsible_personnel' => trim($_POST['responsible_personnel'] ?? ''),
        'aircraft_owner' => trim($_POST['aircraft_owner'] ?? ''),
        'aircraft_operator' => trim($_POST['aircraft_operator'] ?? ''),
        'date_of_manufacture' => !empty($_POST['date_of_manufacture']) ? $_POST['date_of_manufacture'] : null,
        'aircraft_type' => trim($_POST['aircraft_type'] ?? ''),
        'nvfr' => isset($_POST['nvfr']) ? 1 : 0,
        'ifr' => isset($_POST['ifr']) ? 1 : 0,
        'spifr' => isset($_POST['spifr']) ? 1 : 0,
        'engine_type' => trim($_POST['engine_type'] ?? ''),
        'number_of_engines' => intval($_POST['number_of_engines'] ?? 1),
        'engine_model' => trim($_POST['engine_model'] ?? ''),
        'engine_serial_number' => trim($_POST['engine_serial_number'] ?? ''),
        'avionics' => trim($_POST['avionics'] ?? ''),
        'other_avionics_information' => trim($_POST['other_avionics_information'] ?? ''),
        'internal_configuration' => trim($_POST['internal_configuration'] ?? ''),
        'external_configuration' => trim($_POST['external_configuration'] ?? ''),
        'airframe_type' => trim($_POST['airframe_type'] ?? ''),
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'status' => $_POST['status'] ?? 'active'
    ];
    
    // Validation
    if (empty($aircraft_data['registration'])) {
        $message = 'Registration is required.';
        $message_type = 'error';
    } else {
        if (updateAircraft($aircraft_id, $aircraft_data)) {
            $message = 'Aircraft updated successfully!';
            $message_type = 'success';
            // Refresh aircraft data
            $aircraft = getAircraftById($aircraft_id);
        } else {
            $message = 'Failed to update aircraft. Registration might already exist.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Aircraft - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="/assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-edit mr-2"></i>Edit Aircraft
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Edit aircraft information for <?php echo safeOutput($aircraft['registration']); ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Aircraft
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Success/Error Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'; ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400'; ?>"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium <?php echo $message_type === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="max-w-4xl mx-auto">
                    <form method="POST" class="space-y-6">
                        <!-- Basic Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-plane mr-2"></i>Basic Information
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Registration -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Registration *
                                    </label>
                                    <input type="text" name="registration" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['registration']); ?>">
                                </div>

                                <!-- Serial Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Serial Number
                                    </label>
                                    <input type="text" name="serial_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['serial_number']); ?>">
                                </div>

                                <!-- Aircraft Category -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Category
                                    </label>
                                    <select name="aircraft_category"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">Select Category</option>
                                        <option value="Commercial" <?php echo $aircraft['aircraft_category'] === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                        <option value="Private" <?php echo $aircraft['aircraft_category'] === 'Private' ? 'selected' : ''; ?>>Private</option>
                                        <option value="Cargo" <?php echo $aircraft['aircraft_category'] === 'Cargo' ? 'selected' : ''; ?>>Cargo</option>
                                        <option value="Military" <?php echo $aircraft['aircraft_category'] === 'Military' ? 'selected' : ''; ?>>Military</option>
                                        <option value="Training" <?php echo $aircraft['aircraft_category'] === 'Training' ? 'selected' : ''; ?>>Training</option>
                                    </select>
                                </div>

                                <!-- Manufacturer -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Manufacturer
                                    </label>
                                    <input type="text" name="manufacturer"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['manufacturer']); ?>">
                                </div>

                                <!-- Base Location -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Base Location
                                    </label>
                                    <input type="text" name="base_location"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['base_location']); ?>">
                                </div>

                                <!-- Responsible Personnel -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Responsible Personnel
                                    </label>
                                    <input type="text" name="responsible_personnel"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['responsible_personnel']); ?>">
                                </div>

                                <!-- Aircraft Owner -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Owner
                                    </label>
                                    <input type="text" name="aircraft_owner"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['aircraft_owner']); ?>">
                                </div>

                                <!-- Aircraft Operator -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Operator
                                    </label>
                                    <input type="text" name="aircraft_operator"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['aircraft_operator']); ?>">
                                </div>

                                <!-- Date of Manufacture -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Date of Manufacture
                                    </label>
                                    <input type="date" name="date_of_manufacture"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $aircraft['date_of_manufacture'] ? date('Y-m-d', strtotime($aircraft['date_of_manufacture'])) : ''; ?>">
                                </div>

                                <!-- Aircraft Type -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Type
                                    </label>
                                    <input type="text" name="aircraft_type"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['aircraft_type']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Flight Capabilities -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-cloud mr-2"></i>Flight Capabilities
                                </h3>
                            </div>
                            
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- NVFR -->
                                    <div class="flex items-center">
                                        <input type="checkbox" name="nvfr" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo $aircraft['nvfr'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            NVFR (Night VFR)
                                        </span>
                                    </div>

                                    <!-- IFR -->
                                    <div class="flex items-center">
                                        <input type="checkbox" name="ifr" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo $aircraft['ifr'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            IFR (Instrument Flight Rules)
                                        </span>
                                    </div>

                                    <!-- SPIFR -->
                                    <div class="flex items-center">
                                        <input type="checkbox" name="spifr" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo $aircraft['spifr'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            SPIFR (Single Pilot IFR)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Engine Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-cog mr-2"></i>Engine Information
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Engine Type -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Engine Type
                                    </label>
                                    <select name="engine_type"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">Select Engine Type</option>
                                        <option value="Turbofan" <?php echo $aircraft['engine_type'] === 'Turbofan' ? 'selected' : ''; ?>>Turbofan</option>
                                        <option value="Turboprop" <?php echo $aircraft['engine_type'] === 'Turboprop' ? 'selected' : ''; ?>>Turboprop</option>
                                        <option value="Piston" <?php echo $aircraft['engine_type'] === 'Piston' ? 'selected' : ''; ?>>Piston</option>
                                        <option value="Turboshaft" <?php echo $aircraft['engine_type'] === 'Turboshaft' ? 'selected' : ''; ?>>Turboshaft</option>
                                        <option value="Electric" <?php echo $aircraft['engine_type'] === 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                    </select>
                                </div>

                                <!-- Number of Engines -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Number of Engines
                                    </label>
                                    <input type="number" name="number_of_engines" min="1" max="8"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo intval($aircraft['number_of_engines']); ?>">
                                </div>

                                <!-- Engine Model -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Engine Model
                                    </label>
                                    <input type="text" name="engine_model"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['engine_model']); ?>">
                                </div>

                                <!-- Engine Serial Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Engine Serial Number
                                    </label>
                                    <input type="text" name="engine_serial_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($aircraft['engine_serial_number']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Avionics -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-microchip mr-2"></i>Avionics
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 gap-6">
                                <!-- Avionics -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Avionics
                                    </label>
                                    <textarea name="avionics" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($aircraft['avionics']); ?></textarea>
                                </div>

                                <!-- Other Avionics Information -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Avionics Information
                                    </label>
                                    <textarea name="other_avionics_information" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($aircraft['other_avionics_information']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Configuration -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-cogs mr-2"></i>Configuration
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 gap-6">
                                <!-- Internal Configuration -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Internal Configuration
                                    </label>
                                    <textarea name="internal_configuration" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($aircraft['internal_configuration']); ?></textarea>
                                </div>

                                <!-- External Configuration -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        External Configuration
                                    </label>
                                    <textarea name="external_configuration" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($aircraft['external_configuration']); ?></textarea>
                                </div>

                                <!-- Airframe Type -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Airframe Type
                                    </label>
                                    <select name="airframe_type"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">Select Airframe Type</option>
                                        <option value="Narrow-body" <?php echo $aircraft['airframe_type'] === 'Narrow-body' ? 'selected' : ''; ?>>Narrow-body</option>
                                        <option value="Wide-body" <?php echo $aircraft['airframe_type'] === 'Wide-body' ? 'selected' : ''; ?>>Wide-body</option>
                                        <option value="Regional Jet" <?php echo $aircraft['airframe_type'] === 'Regional Jet' ? 'selected' : ''; ?>>Regional Jet</option>
                                        <option value="Business Jet" <?php echo $aircraft['airframe_type'] === 'Business Jet' ? 'selected' : ''; ?>>Business Jet</option>
                                        <option value="Turboprop" <?php echo $aircraft['airframe_type'] === 'Turboprop' ? 'selected' : ''; ?>>Turboprop</option>
                                        <option value="Helicopter" <?php echo $aircraft['airframe_type'] === 'Helicopter' ? 'selected' : ''; ?>>Helicopter</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- System Settings -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-cog mr-2"></i>System Settings
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status *
                                    </label>
                                    <select name="status" required
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="active" <?php echo $aircraft['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $aircraft['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="maintenance" <?php echo $aircraft['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="retired" <?php echo $aircraft['status'] === 'retired' ? 'selected' : ''; ?>>Retired</option>
                                    </select>
                                </div>

                                <!-- Enabled -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="enabled" value="1"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                           <?php echo $aircraft['enabled'] ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Enabled
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex justify-end space-x-4">
                            <a href="index.php" 
                               class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Update Aircraft
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
        </div>
    </div>
</body>
</html>
