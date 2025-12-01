<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/aircraft/index.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $aircraft_id = intval($_POST['aircraft_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'toggle_status':
                $new_status = $_POST['new_status'] ?? 'active';
                if (updateAircraftStatus($aircraft_id, $new_status)) {
                    $message = 'Aircraft status updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update aircraft status.';
                    $message_type = 'error';
                }
                break;
                
            case 'delete':
                if (deleteAircraft($aircraft_id)) {
                    $message = 'Aircraft deleted successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete aircraft.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

$aircraft_list = getAllAircraft();
$aircraft_count = getAircraftCount();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aircraft Management - <?php echo PROJECT_NAME; ?></title>
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
                                <i class="fas fa-plane mr-2"></i>Aircraft Management
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage fleet aircraft and their specifications
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Add Aircraft Button -->
                            <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Aircraft
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

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-plane text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Aircraft</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $aircraft_count; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($aircraft_list, function($a) { return $a['status'] === 'active'; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-tools text-yellow-600 dark:text-yellow-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Maintenance</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($aircraft_list, function($a) { return $a['status'] === 'maintenance'; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-times-circle text-red-600 dark:text-red-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Inactive</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($aircraft_list, function($a) { return $a['status'] === 'inactive'; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aircraft List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-list mr-2"></i>Aircraft List
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Registration
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Aircraft Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Manufacturer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Base Location
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Capabilities
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($aircraft_list)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-plane-slash text-4xl mb-2"></i>
                                        <p>No aircraft found. <a href="add.php" class="text-blue-600 hover:text-blue-500">Add your first aircraft</a></p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($aircraft_list as $aircraft): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-plane text-blue-600 dark:text-blue-400"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($aircraft['registration']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($aircraft['serial_number'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($aircraft['aircraft_type'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($aircraft['aircraft_category'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($aircraft['manufacturer'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($aircraft['base_location'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_colors = [
                                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'inactive' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'maintenance' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            'retired' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                        ];
                                        $status_color = $status_colors[$aircraft['status']] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                            <?php echo ucfirst($aircraft['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex space-x-1">
                                            <?php if ($aircraft['nvfr']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    NVFR
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($aircraft['ifr']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    IFR
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($aircraft['spifr']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    SPIFR
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <!-- View Button -->
                                            <button onclick="viewAircraft(<?php echo $aircraft['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Edit Button -->
                                            <a href="edit.php?id=<?php echo $aircraft['id']; ?>" 
                                               class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <!-- Toggle Status Button -->
                                            <button onclick="toggleAircraftStatus(<?php echo $aircraft['id']; ?>, '<?php echo $aircraft['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                                                    class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300">
                                                <i class="fas fa-toggle-<?php echo $aircraft['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                            </button>
                                            
                                            <!-- Delete Button -->
                                            <button onclick="deleteAircraft(<?php echo $aircraft['id']; ?>)" 
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
                </div>
            </main>
        </div>
    </div>

    <!-- View Aircraft Modal -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-plane mr-2"></i>Aircraft Details
                    </h3>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div id="aircraftDetails" class="text-sm text-gray-600 dark:text-gray-400">
                    <!-- Aircraft details will be loaded here -->
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end mt-6">
                    <button onclick="closeViewModal()"
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>

                <!-- Modal Body -->
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Delete Aircraft
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Are you sure you want to delete this aircraft? This action cannot be undone.
                    </p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-center space-x-3">
                    <button onclick="closeDeleteModal()"
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-200">
                        Cancel
                    </button>
                    <form id="deleteForm" method="POST" class="inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="aircraft_id" id="deleteAircraftId">
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors duration-200">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toggle Status Form -->
    <form id="toggleStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="aircraft_id" id="toggleAircraftId">
        <input type="hidden" name="new_status" id="toggleNewStatus">
    </form>

    <script>
        // View Aircraft Modal
        function viewAircraft(id) {
            // Show loading state
            document.getElementById('aircraftDetails').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">Loading aircraft details...</p>
                </div>
            `;
            document.getElementById('viewModal').classList.remove('hidden');
            
            // Fetch aircraft details via AJAX
            fetch('get_aircraft_details.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const aircraft = data.aircraft;
                        document.getElementById('aircraftDetails').innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>Basic Information
                                    </h4>
                                    <div class="space-y-2">
                                        <p><strong>Registration:</strong> <span class="text-gray-900 dark:text-white">${aircraft.registration || 'N/A'}</span></p>
                                        <p><strong>Serial Number:</strong> <span class="text-gray-900 dark:text-white">${aircraft.serial_number || 'N/A'}</span></p>
                                        <p><strong>Aircraft Type:</strong> <span class="text-gray-900 dark:text-white">${aircraft.aircraft_type || 'N/A'}</span></p>
                                        <p><strong>Manufacturer:</strong> <span class="text-gray-900 dark:text-white">${aircraft.manufacturer || 'N/A'}</span></p>
                                        <p><strong>Engine Model:</strong> <span class="text-gray-900 dark:text-white">${aircraft.engine_model || 'N/A'}</span></p>
                                        <p><strong>Date of Manufacture:</strong> <span class="text-gray-900 dark:text-white">${aircraft.date_of_manufacture || 'N/A'}</span></p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                        <i class="fas fa-cogs mr-2 text-green-500"></i>Status & Capabilities
                                    </h4>
                                    <div class="space-y-2">
                                        <p><strong>Status:</strong> 
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(aircraft.status)}">
                                                ${aircraft.status ? aircraft.status.charAt(0).toUpperCase() + aircraft.status.slice(1) : 'N/A'}
                                            </span>
                                        </p>
                                        <p><strong>Base Location:</strong> <span class="text-gray-900 dark:text-white">${aircraft.base_location || 'N/A'}</span></p>
                                        <p><strong>Capabilities:</strong> 
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                ${aircraft.nvfr ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">NVFR</span>' : ''}
                                                ${aircraft.ifr ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">IFR</span>' : ''}
                                                ${aircraft.spifr ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">SPIFR</span>' : ''}
                                                ${!aircraft.nvfr && !aircraft.ifr && !aircraft.spifr ? '<span class="text-gray-500 dark:text-gray-400">None specified</span>' : ''}
                                            </div>
                                        </p>
                                        <p><strong>Engine Type:</strong> <span class="text-gray-900 dark:text-white">${aircraft.engine_type || 'N/A'}</span></p>
                                        <p><strong>Number of Engines:</strong> <span class="text-gray-900 dark:text-white">${aircraft.number_of_engines || 'N/A'}</span></p>
                                    </div>
                                </div>
                            </div>
                            ${aircraft.notes ? `
                                <div class="mt-6 bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center">
                                        <i class="fas fa-sticky-note mr-2 text-yellow-500"></i>Notes
                                    </h4>
                                    <p class="text-gray-700 dark:text-gray-300">${aircraft.notes}</p>
                                </div>
                            ` : ''}
                        `;
                    } else {
                        document.getElementById('aircraftDetails').innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-triangle text-2xl text-red-400 mb-4"></i>
                                <p class="text-red-600 dark:text-red-400">Error loading aircraft details: ${data.error || 'Unknown error'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('aircraftDetails').innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-triangle text-2xl text-red-400 mb-4"></i>
                            <p class="text-red-600 dark:text-red-400">Failed to load aircraft details. Please try again.</p>
                        </div>
                    `;
                });
        }
        
        function getStatusColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'inactive': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'maintenance': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'retired': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
            };
            return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Delete Aircraft Modal
        function deleteAircraft(id) {
            document.getElementById('deleteAircraftId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Toggle Aircraft Status
        function toggleAircraftStatus(id, newStatus) {
            document.getElementById('toggleAircraftId').value = id;
            document.getElementById('toggleNewStatus').value = newStatus;
            document.getElementById('toggleStatusForm').submit();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === viewModal) {
                closeViewModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeViewModal();
                closeDeleteModal();
            }
        });
    </script>
        </div>
    </div>
</body>
</html>
