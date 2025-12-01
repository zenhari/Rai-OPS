<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/pricing/catering/add.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $customName = ($name === 'Custom') ? trim($_POST['custom_name'] ?? '') : null;
    $passengerFood = isset($_POST['passenger_food']) && $_POST['passenger_food'] !== '' ? floatval($_POST['passenger_food']) : null;
    $equipment = isset($_POST['equipment']) && $_POST['equipment'] !== '' ? floatval($_POST['equipment']) : null;
    $transportation = isset($_POST['transportation']) && $_POST['transportation'] !== '' ? floatval($_POST['transportation']) : null;
    $storage = isset($_POST['storage']) && $_POST['storage'] !== '' ? floatval($_POST['storage']) : null;
    $waste = isset($_POST['waste']) && $_POST['waste'] !== '' ? floatval($_POST['waste']) : null;
    $qualityInspection = isset($_POST['quality_inspection']) && $_POST['quality_inspection'] !== '' ? floatval($_POST['quality_inspection']) : null;
    $packaging = isset($_POST['packaging']) && $_POST['packaging'] !== '' ? floatval($_POST['packaging']) : null;
    $specialServices = isset($_POST['special_services']) && $_POST['special_services'] !== '' ? floatval($_POST['special_services']) : null;
    
    // Validation
    if (empty($name)) {
        $error = 'Catering Name is required.';
    } elseif ($name === 'Custom' && empty($customName)) {
        $error = 'Custom Name is required when Custom is selected.';
    } else {
        if (addCatering($name, $customName, $passengerFood, $equipment, $transportation, $storage, $waste, $qualityInspection, $packaging, $specialServices)) {
            $message = 'Catering added successfully.';
            // Redirect after 1 second
            header('Refresh: 1; url=index.php');
        } else {
            $error = 'Failed to add catering.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Catering - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add New Catering</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create a new catering cost configuration</p>
                        </div>
                        <a href="index.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to List
                        </a>
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

                <!-- Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <form method="POST" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Catering Name -->
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Catering Name <span class="text-red-500">*</span>
                                </label>
                                <select id="name" name="name" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                        onchange="toggleCustomName()">
                                    <option value="">Select catering type</option>
                                    <option value="Economy">Economy</option>
                                    <option value="VIP">VIP</option>
                                    <option value="CIP">CIP</option>
                                    <option value="Custom">Custom</option>
                                </select>
                            </div>

                            <!-- Custom Name (only when Custom is selected) -->
                            <div class="md:col-span-2" id="customNameContainer" style="display: none;">
                                <label for="custom_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Custom Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="custom_name" name="custom_name"
                                       placeholder="Enter custom catering name"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Passenger Food and Beverages -->
                            <div>
                                <label for="passenger_food" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Passenger Food and Beverages
                                </label>
                                <input type="number" id="passenger_food" name="passenger_food" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Equipment -->
                            <div>
                                <label for="equipment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Catering Equipment
                                </label>
                                <input type="number" id="equipment" name="equipment" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Transportation -->
                            <div>
                                <label for="transportation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Catering Transportation
                                </label>
                                <input type="number" id="transportation" name="transportation" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Storage -->
                            <div>
                                <label for="storage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Catering Storage
                                </label>
                                <input type="number" id="storage" name="storage" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Waste -->
                            <div>
                                <label for="waste" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Catering Waste
                                </label>
                                <input type="number" id="waste" name="waste" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Quality Inspection -->
                            <div>
                                <label for="quality_inspection" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Quality Inspection
                                </label>
                                <input type="number" id="quality_inspection" name="quality_inspection" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Packaging -->
                            <div>
                                <label for="packaging" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Packaging
                                </label>
                                <input type="number" id="packaging" name="packaging" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Special Services -->
                            <div>
                                <label for="special_services" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Special Services
                                </label>
                                <input type="number" id="special_services" name="special_services" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <a href="index.php"
                               class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Add Catering
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomName() {
            const nameSelect = document.getElementById('name');
            const customNameContainer = document.getElementById('customNameContainer');
            const customNameInput = document.getElementById('custom_name');
            
            if (nameSelect.value === 'Custom') {
                customNameContainer.style.display = 'block';
                customNameInput.required = true;
            } else {
                customNameContainer.style.display = 'none';
                customNameInput.required = false;
                customNameInput.value = '';
            }
        }
    </script>
</body>
</html>

