<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/statistics/flight_statistics.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

$db = getDBConnection();

// Check if this is an API request (for offline sync)
$isApiRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$isApiRequest = $isApiRequest || (isset($_GET['api']) && $_GET['api'] === '1');

// Handle ticket price and fuel cost updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    if ($action === 'update_ticket_price') {
        $rego = trim($_POST['rego'] ?? '');
        $ticketPrice = floatval($_POST['ticket_price'] ?? 0);
        
        if (!empty($rego) && $ticketPrice > 0) {
            try {
                // Check if record exists for this rego
                $stmt = $db->prepare("SELECT id FROM statistics WHERE rego = ?");
                $stmt->execute([$rego]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Update existing record
                    $stmt = $db->prepare("UPDATE statistics SET ticket_price = ?, updated_at = CURRENT_TIMESTAMP WHERE rego = ?");
                    $stmt->execute([$ticketPrice, $rego]);
                } else {
                    // Insert new record
                    $stmt = $db->prepare("INSERT INTO statistics (rego, ticket_price) VALUES (?, ?)");
                    $stmt->execute([$rego, $ticketPrice]);
                }
                
                $response['success'] = true;
                $response['message'] = 'Ticket price updated successfully for ' . htmlspecialchars($rego);
                $message = $response['message'];
            } catch (PDOException $e) {
                $response['message'] = 'Database error: ' . $e->getMessage();
                $error = $response['message'];
            }
        } else {
            $response['message'] = 'Invalid ticket price or Rego.';
            $error = $response['message'];
        }
        
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    } elseif ($action === 'update_fuel_cost') {
        $fuelCost = floatval($_POST['fuel_cost'] ?? 0);
        
        if ($fuelCost > 0) {
            try {
                // Check if global fuel cost record exists (rego is NULL)
                $stmt = $db->prepare("SELECT id FROM statistics WHERE rego IS NULL");
                $stmt->execute();
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Update existing record
                    $stmt = $db->prepare("UPDATE statistics SET fuel_cost_per_liter = ?, updated_at = CURRENT_TIMESTAMP WHERE rego IS NULL");
                    $stmt->execute([$fuelCost]);
                } else {
                    // Insert new record
                    $stmt = $db->prepare("INSERT INTO statistics (rego, fuel_cost_per_liter) VALUES (NULL, ?)");
                    $stmt->execute([$fuelCost]);
                }
                
                $response['success'] = true;
                $response['message'] = 'Fuel cost per liter updated successfully.';
                $message = $response['message'];
            } catch (PDOException $e) {
                $response['message'] = 'Database error: ' . $e->getMessage();
                $error = $response['message'];
            }
        } else {
            $response['message'] = 'Invalid fuel cost.';
            $error = $response['message'];
        }
        
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

// Get ticket prices and fuel cost from database
$ticketPrices = [];
$fuelCostPerLiter = 1; // Default to 1 if not set

try {
    // Get all ticket prices (where rego is not NULL)
    $stmt = $db->query("SELECT rego, ticket_price FROM statistics WHERE rego IS NOT NULL AND ticket_price IS NOT NULL");
    $ticketPriceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ticketPriceRecords as $record) {
        $ticketPrices[$record['rego']] = floatval($record['ticket_price']);
    }
    
    // Get global fuel cost (where rego is NULL)
    $stmt = $db->prepare("SELECT fuel_cost_per_liter FROM statistics WHERE rego IS NULL AND fuel_cost_per_liter IS NOT NULL LIMIT 1");
    $stmt->execute();
    $fuelCostRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fuelCostRecord) {
        $fuelCostPerLiter = floatval($fuelCostRecord['fuel_cost_per_liter']);
    }
} catch (PDOException $e) {
    error_log("Error loading statistics from database: " . $e->getMessage());
    // Fallback to session if database fails
    $ticketPrices = $_SESSION['ticket_prices'] ?? [];
    $fuelCostPerLiter = $_SESSION['fuel_cost_per_liter'] ?? 1;
}

// Aircraft seat configuration
$aircraftSeats = [
    'EP-NEA' => 30,
    'EP-NEB' => 47,
    'EP-NEC' => 47
];

// Get statistics grouped by Rego
$statsQuery = "
    SELECT 
        Rego,
        AVG(uplift_fuel) as avg_uplift_fuel,
        COUNT(FlightID) as flight_count,
        SUM(total_pax) as total_passengers,
        COUNT(DISTINCT Route) as route_count
    FROM flights
    WHERE Rego IS NOT NULL 
        AND Rego != ''
        AND Rego IN ('EP-NEA', 'EP-NEB', 'EP-NEC')
    GROUP BY Rego
    ORDER BY Rego ASC
";

$statsStmt = $db->query($statsQuery);
$aircraftStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get route-based statistics for profit analysis
$routeStatsQuery = "
    SELECT 
        Rego,
        Route,
        AVG(uplift_fuel) as avg_uplift_fuel,
        COUNT(FlightID) as flight_count,
        SUM(total_pax) as total_passengers
    FROM flights
    WHERE Rego IS NOT NULL 
        AND Rego != ''
        AND Rego IN ('EP-NEA', 'EP-NEB', 'EP-NEC')
        AND Route IS NOT NULL
        AND Route != ''
    GROUP BY Rego, Route
    ORDER BY Rego ASC, Route ASC
";

$routeStatsStmt = $db->query($routeStatsQuery);
$routeStats = $routeStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate profits for each route
$routeProfits = [];
foreach ($routeStats as $route) {
    $rego = $route['Rego'];
    $ticketPrice = $ticketPrices[$rego] ?? 0;
    $totalRevenue = $route['total_passengers'] * $ticketPrice;
    // Total fuel consumed = average fuel per flight × number of flights
    $totalFuelLiters = $route['avg_uplift_fuel'] * $route['flight_count'];
    // Total fuel cost = total fuel liters × cost per liter
    $totalFuelCost = $totalFuelLiters * $fuelCostPerLiter;
    $profit = $totalRevenue - $totalFuelCost;
    
    $routeProfits[] = [
        'rego' => $rego,
        'route' => $route['Route'],
        'avg_uplift_fuel' => $route['avg_uplift_fuel'],
        'flight_count' => $route['flight_count'],
        'total_passengers' => $route['total_passengers'],
        'ticket_price' => $ticketPrice,
        'total_revenue' => $totalRevenue,
        'total_fuel_liters' => $totalFuelLiters,
        'total_fuel_cost' => $totalFuelCost,
        'profit' => $profit,
        'seats' => $aircraftSeats[$rego] ?? 0
    ];
}

// Sort route profits by profit descending
usort($routeProfits, function($a, $b) {
    return $b['profit'] <=> $a['profit'];
});
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Statistics - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
    <!-- Offline Support Scripts -->
    <script src="indexeddb.js"></script>
    <script src="offline-sync.js"></script>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Statistics</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Aircraft performance and profitability analysis</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Fuel Cost Setting -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Fuel Cost Setting</h2>
                    </div>
                    <div class="px-6 py-4">
                        <form method="POST" id="fuelCostForm" class="flex items-center space-x-4">
                            <input type="hidden" name="action" value="update_fuel_cost">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Fuel Cost per Liter (Toman):
                            </label>
                            <input type="number" name="fuel_cost" id="fuel_cost_input" step="0.01" min="0" value="<?php echo $fuelCostPerLiter; ?>" required
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white w-32">
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>Save
                            </button>
                        </form>
                    </div>
                </div>

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

                <!-- Aircraft Statistics Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Aircraft Statistics</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft (Rego)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Seats</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Uplift Fuel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Count</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Passengers</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Routes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ticket Price (Toman)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($aircraftStats)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No flight data found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($aircraftStats as $stat): 
                                        $rego = $stat['Rego'];
                                        $seats = $aircraftSeats[$rego] ?? 0;
                                        $ticketPrice = $ticketPrices[$rego] ?? 0;
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($rego); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo $seats; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo number_format($stat['avg_uplift_fuel'] ?? 0, 2); ?> L
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo $stat['flight_count']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo $stat['total_passengers']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo $stat['route_count']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <span id="ticket_price_display_<?php echo htmlspecialchars($rego); ?>">
                                                        <?php echo $ticketPrice > 0 ? number_format($ticketPrice, 0) : '-'; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openTicketPriceModal('<?php echo htmlspecialchars($rego); ?>', <?php echo $ticketPrice; ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    <i class="fas fa-edit"></i> Set Price
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Route Profitability Analysis -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Route Profitability Analysis</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Profit = (Total Passengers × Ticket Price) - (Avg Fuel × Flight Count)</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flights</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Passengers</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Fuel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ticket Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revenue</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fuel Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Profit</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($routeProfits)): ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No route data found. Please set ticket prices to calculate profits.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($routeProfits as $profit): ?>
                                        <tr class="<?php echo $profit['profit'] > 0 ? 'bg-green-50 dark:bg-green-900/20' : ($profit['profit'] < 0 ? 'bg-red-50 dark:bg-red-900/20' : ''); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($profit['rego']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($profit['route']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo $profit['flight_count']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo $profit['total_passengers']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white"><?php echo number_format($profit['avg_uplift_fuel'] ?? 0, 2); ?> L</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $profit['ticket_price'] > 0 ? number_format($profit['ticket_price'], 0) : '<span class="text-gray-400">Not set</span>'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo number_format($profit['total_revenue'] ?? 0, 0); ?> Toman
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo number_format($profit['total_fuel_liters'] ?? 0, 2); ?> L
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        (<?php echo number_format($profit['total_fuel_cost'] ?? 0, 0); ?> Toman)
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-semibold <?php echo $profit['profit'] > 0 ? 'text-green-600 dark:text-green-400' : ($profit['profit'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'); ?>">
                                                    <?php echo number_format($profit['profit'] ?? 0, 0); ?> Toman
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

    <!-- Ticket Price Modal -->
    <div id="ticketPriceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Set Ticket Price</h3>
                    <button onclick="closeTicketPriceModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="ticketPriceForm" class="space-y-4">
                    <input type="hidden" name="action" value="update_ticket_price">
                    <input type="hidden" id="modal_rego" name="rego">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Aircraft: <span id="modal_rego_display" class="font-semibold"></span>
                        </label>
                    </div>
                    
                    <div>
                        <label for="ticket_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Ticket Price (Toman) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="ticket_price" name="ticket_price" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeTicketPriceModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" id="ticketPriceSubmitBtn"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openTicketPriceModal(rego, currentPrice) {
            document.getElementById('modal_rego').value = rego;
            document.getElementById('modal_rego_display').textContent = rego;
            
            // Check for offline data first
            const offlineData = offlineSyncManager.db.getOfflineData();
            let displayPrice = currentPrice || '';
            
            if (offlineData.ticketPrices && offlineData.ticketPrices[rego]) {
                displayPrice = offlineData.ticketPrices[rego];
            }
            
            document.getElementById('ticket_price').value = displayPrice;
            document.getElementById('ticketPriceModal').classList.remove('hidden');
        }

        function closeTicketPriceModal() {
            document.getElementById('ticketPriceModal').classList.add('hidden');
            document.getElementById('ticketPriceForm').reset();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('ticketPriceModal');
            if (event.target === modal) {
                closeTicketPriceModal();
            }
        }

        // Load and display offline data
        function loadOfflineData() {
            try {
                const offlineData = offlineSyncManager.db.getOfflineData();
                
                // Load fuel cost
                if (offlineData.fuelCostPerLiter) {
                    const fuelInput = document.getElementById('fuel_cost_input');
                    if (fuelInput) {
                        fuelInput.value = offlineData.fuelCostPerLiter;
                    }
                }
                
                // Load ticket prices
                if (offlineData.ticketPrices) {
                    Object.keys(offlineData.ticketPrices).forEach(rego => {
                        const price = offlineData.ticketPrices[rego];
                        const displayElement = document.getElementById('ticket_price_display_' + rego);
                        if (displayElement) {
                            displayElement.innerHTML = parseFloat(price).toLocaleString('en-US') + ' <span class="text-xs text-yellow-600 dark:text-yellow-400">(offline)</span>';
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading offline data:', error);
            }
        }

        // Handle form submissions with offline support
        document.addEventListener('DOMContentLoaded', function() {
            // Load offline data on page load
            loadOfflineData();
            
            // Fuel cost form
            const fuelCostForm = document.getElementById('fuelCostForm');
            if (fuelCostForm) {
                fuelCostForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const data = {
                        fuel_cost: formData.get('fuel_cost')
                    };
                    
                    const result = await offlineSyncManager.saveData('update_fuel_cost', data);
                    
                    if (result.success) {
                        if (result.online) {
                            // Reload page to show updated data
                            window.location.reload();
                        } else {
                            // Update UI to show saved offline
                            const input = document.getElementById('fuel_cost_input');
                            if (input) {
                                input.value = data.fuel_cost;
                            }
                        }
                    }
                });
            }

            // Ticket price form
            const ticketPriceForm = document.getElementById('ticketPriceForm');
            if (ticketPriceForm) {
                ticketPriceForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const rego = formData.get('rego');
                    const ticketPrice = formData.get('ticket_price');
                    
                    const data = {
                        rego: rego,
                        ticket_price: ticketPrice
                    };
                    
                    const result = await offlineSyncManager.saveData('update_ticket_price', data);
                    
                    if (result.success) {
                        if (result.online) {
                            // Update display immediately
                            const displayElement = document.getElementById('ticket_price_display_' + rego);
                            if (displayElement) {
                                displayElement.textContent = parseFloat(ticketPrice).toLocaleString('en-US');
                            }
                            closeTicketPriceModal();
                            
                            // Reload page after a short delay to show updated calculations
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Update display for offline save
                            const displayElement = document.getElementById('ticket_price_display_' + rego);
                            if (displayElement) {
                                displayElement.innerHTML = parseFloat(ticketPrice).toLocaleString('en-US') + ' <span class="text-xs text-yellow-600 dark:text-yellow-400">(offline)</span>';
                            }
                            closeTicketPriceModal();
                        }
                    }
                });
            }

            // Update pending count display periodically
            setInterval(async () => {
                const count = await offlineSyncManager.getPendingCount();
                updatePendingCountBadge(count);
            }, 5000);

            // Initial pending count
            offlineSyncManager.getPendingCount().then(count => {
                updatePendingCountBadge(count);
            });
        });

        function updatePendingCountBadge(count) {
            let badge = document.getElementById('pending-sync-badge');
            let badgeButton = document.getElementById('pending-sync-button');
            
            if (count > 0) {
                if (!badgeButton) {
                    // Create badge in header
                    const header = document.querySelector('.px-6.py-4 .flex.items-center.justify-between');
                    if (header) {
                        const badgeContainer = document.createElement('div');
                        badgeContainer.className = 'flex items-center space-x-2';
                        badgeContainer.innerHTML = `
                            <button id="pending-sync-button" onclick="offlineSyncManager.manualSync()" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 rounded-md transition-colors duration-200 flex items-center space-x-2">
                                <i class="fas fa-sync"></i>
                                <span id="pending-sync-badge">${count} item(s) pending sync</span>
                            </button>
                        `;
                        header.appendChild(badgeContainer);
                    }
                } else {
                    if (badge) {
                        badge.textContent = count + ' item(s) pending sync';
                    }
                }
            } else {
                if (badgeButton) {
                    badgeButton.remove();
                }
            }
        }
    </script>
</body>
</html>

