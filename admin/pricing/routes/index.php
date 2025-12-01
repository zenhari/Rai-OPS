<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/pricing/routes/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_price':
            $id = intval($_POST['id'] ?? 0);
            if (deleteRoutePrice($id)) {
                $message = 'Route price deleted successfully.';
            } else {
                $error = 'Failed to delete route price.';
            }
            break;
    }
}

// Get filter parameters
$filterOrigin = isset($_GET['origin']) ? intval($_GET['origin']) : 0;
$filterDestination = isset($_GET['destination']) ? intval($_GET['destination']) : 0;

// Get all stations for filter dropdowns
$db = getDBConnection();
$stationsStmt = $db->query("SELECT id, station_name, iata_code FROM stations ORDER BY station_name ASC");
$allStations = $stationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Build query with filters
$sql = "
    SELECT 
        r.id,
        r.route_code,
        r.route_name,
        r.origin_station_id,
        r.destination_station_id,
        r.distance_nm,
        r.flight_time_minutes,
        r.status,
        o.station_name as origin_station,
        o.id as origin_id,
        d.station_name as destination_station,
        d.id as destination_id,
        rp.id as price_id,
        rp.total_cost,
        rp.final_price,
        rp.updated_at as price_updated_at
    FROM routes r
    LEFT JOIN stations o ON r.origin_station_id = o.id
    LEFT JOIN stations d ON r.destination_station_id = d.id
    LEFT JOIN route_prices rp ON r.id = rp.route_id
    WHERE r.status = 'active'
";

$params = [];

if ($filterOrigin > 0) {
    $sql .= " AND r.origin_station_id = ?";
    $params[] = $filterOrigin;
}

if ($filterDestination > 0) {
    $sql .= " AND r.destination_station_id = ?";
    $params[] = $filterDestination;
}

$sql .= " ORDER BY r.route_code ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Price Management - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Route Price Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage pricing for all active routes</p>
                        </div>
                        <div class="flex space-x-3">
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

                <!-- Filter Section -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Filter Routes</h2>
                    </div>
                    <div class="px-6 py-4">
                        <form method="GET" action="" class="flex flex-col sm:flex-row gap-4 items-end">
                            <div class="flex-1 w-full sm:w-auto">
                                <label for="filter_origin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Origin Station
                                </label>
                                <select id="filter_origin" 
                                        name="origin" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm">
                                    <option value="">All Origins</option>
                                    <?php foreach ($allStations as $station): ?>
                                        <option value="<?php echo $station['id']; ?>" <?php echo $filterOrigin == $station['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['station_name']); ?>
                                            <?php if (!empty($station['iata_code'])): ?>
                                                (<?php echo htmlspecialchars($station['iata_code']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex-1 w-full sm:w-auto">
                                <label for="filter_destination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Destination Station
                                </label>
                                <select id="filter_destination" 
                                        name="destination" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm">
                                    <option value="">All Destinations</option>
                                    <?php foreach ($allStations as $station): ?>
                                        <option value="<?php echo $station['id']; ?>" <?php echo $filterDestination == $station['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['station_name']); ?>
                                            <?php if (!empty($station['iata_code'])): ?>
                                                (<?php echo htmlspecialchars($station['iata_code']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex gap-2 w-full sm:w-auto">
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-md transition-colors duration-200">
                                    <i class="fas fa-filter mr-2"></i>
                                    Filter
                                </button>
                                <?php if ($filterOrigin > 0 || $filterDestination > 0): ?>
                                    <a href="index.php" 
                                       class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200">
                                        <i class="fas fa-times mr-2"></i>
                                        Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Routes Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Active Routes</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <?php if ($filterOrigin > 0 || $filterDestination > 0): ?>
                                        Showing filtered results
                                        <?php if ($filterOrigin > 0): ?>
                                            <?php 
                                            $originStation = array_filter($allStations, function($s) use ($filterOrigin) { return $s['id'] == $filterOrigin; });
                                            $originStation = reset($originStation);
                                            ?>
                                            - Origin: <?php echo htmlspecialchars($originStation['station_name'] ?? ''); ?>
                                        <?php endif; ?>
                                        <?php if ($filterDestination > 0): ?>
                                            <?php 
                                            $destStation = array_filter($allStations, function($s) use ($filterDestination) { return $s['id'] == $filterDestination; });
                                            $destStation = reset($destStation);
                                            ?>
                                            - Destination: <?php echo htmlspecialchars($destStation['station_name'] ?? ''); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Click on a route to manage its pricing
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Total: <?php echo count($routes); ?> route(s)
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Origin → Destination</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Cost (Toman)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($routes)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No active routes found
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
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($route['origin_station'] ?? 'N/A'); ?> → 
                                                    <?php echo htmlspecialchars($route['destination_station'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php if ($route['total_cost']): ?>
                                                        <?php echo number_format($route['total_cost'], 2); ?> Toman
                                                    <?php else: ?>
                                                        <span class="text-gray-400">Not set</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="edit.php?id=<?php echo $route['id']; ?>" 
                                                       class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($route['price_id']): ?>
                                                        <button onclick="deletePrice(<?php echo $route['price_id']; ?>, '<?php echo htmlspecialchars($route['route_code']); ?>')" 
                                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Route Price</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to delete price for route <span id="routeCode" class="font-medium"></span>? 
                            This action cannot be undone.
                        </p>
                    </div>
                    <div class="flex justify-center space-x-3 mt-4">
                        <button onclick="closeDeleteModal()" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <form id="deleteForm" method="POST" class="inline">
                            <input type="hidden" name="action" value="delete_price">
                            <input type="hidden" name="id" id="deletePriceId">
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
        function deletePrice(id, routeCode) {
            document.getElementById('deletePriceId').value = id;
            document.getElementById('routeCode').textContent = routeCode;
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

