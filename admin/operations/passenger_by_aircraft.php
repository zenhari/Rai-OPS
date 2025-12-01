<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/passenger_by_aircraft.php');

$current_user = getCurrentUser();

// Aircraft capacity mapping (seat capacity for each aircraft registration)
$aircraftCapacities = [
    'EP-NEB' => 47,
    'EP-NEA' => 30,
    'EP-NEC' => 47,
];

// Get passenger statistics by aircraft
function getPassengerStatsByAircraft() {
    $db = getDBConnection();
    
    $sql = "SELECT 
                Rego,
                COUNT(*) as total_flights,
                SUM(CAST(total_pax AS UNSIGNED)) as total_passengers,
                AVG(CAST(total_pax AS UNSIGNED)) as avg_passengers_per_flight
            FROM flights
            WHERE Rego IS NOT NULL 
            AND Rego != ''
            AND total_pax IS NOT NULL 
            AND total_pax != ''
            AND CAST(total_pax AS UNSIGNED) > 0
            GROUP BY Rego
            ORDER BY Rego ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add capacity and utilization calculations
    global $aircraftCapacities;
    foreach ($results as &$result) {
        $rego = $result['Rego'];
        $capacity = $aircraftCapacities[$rego] ?? null;
        
        $result['capacity'] = $capacity;
        $result['total_capacity'] = $capacity ? $capacity * $result['total_flights'] : null;
        $result['utilization_percent'] = ($result['total_capacity'] && $result['total_capacity'] > 0) 
            ? ($result['total_passengers'] / $result['total_capacity']) * 100 
            : null;
        $result['avg_utilization_percent'] = ($capacity && $capacity > 0) 
            ? ($result['avg_passengers_per_flight'] / $capacity) * 100 
            : null;
    }
    
    return $results;
}

$passengerStats = getPassengerStatsByAircraft();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger By Aircraft - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Passenger By Aircraft</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Passenger capacity utilization analysis by aircraft</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-users text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Capacity Analysis</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-md p-3">
                                <i class="fas fa-plane text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Aircraft</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo count($passengerStats); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-md p-3">
                                <i class="fas fa-users text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Passengers</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    <?php 
                                    $totalPassengers = array_sum(array_column($passengerStats, 'total_passengers'));
                                    echo number_format($totalPassengers); 
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-md p-3">
                                <i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Flights</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    <?php 
                                    $totalFlights = array_sum(array_column($passengerStats, 'total_flights'));
                                    echo number_format($totalFlights); 
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aircraft Statistics Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            <i class="fas fa-table mr-2"></i>
                            Passenger Statistics by Aircraft
                        </h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft Registration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Flights</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Passengers</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Potential Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Passengers/ Flight</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Utilization</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($passengerStats)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                            <p>No passenger data found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($passengerStats as $stat): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <i class="fas fa-plane text-blue-500 mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($stat['Rego']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($stat['capacity']): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-md bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm font-medium">
                                                        <i class="fas fa-chair mr-2"></i>
                                                        <?php echo htmlspecialchars($stat['capacity']); ?> seats
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                                        N/A
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900 dark:text-white font-medium">
                                                    <?php echo number_format($stat['total_flights']); ?>
                                                </span>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo number_format($stat['total_passengers']); ?>
                                                </span>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($stat['total_capacity']): ?>
                                                    <span class="text-sm text-gray-900 dark:text-white">
                                                        <?php echo number_format($stat['total_capacity']); ?>
                                                    </span>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        (<?php echo number_format($stat['capacity']); ?> × <?php echo number_format($stat['total_flights']); ?> flights)
                                                    </p>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo number_format($stat['avg_passengers_per_flight'], 1); ?>
                                                </span>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($stat['utilization_percent'] !== null): ?>
                                                    <div class="flex items-center">
                                                        <div class="flex-1 mr-2">
                                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                                <div class="h-2 rounded-full <?php 
                                                                    $util = $stat['utilization_percent'];
                                                                    echo $util >= 80 ? 'bg-green-600' : ($util >= 60 ? 'bg-yellow-500' : 'bg-red-500');
                                                                ?>" style="width: <?php echo min(100, $stat['utilization_percent']); ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <span class="text-sm font-medium <?php 
                                                            $util = $stat['utilization_percent'];
                                                            echo $util >= 80 ? 'text-green-600 dark:text-green-400' : ($util >= 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                                        ?>">
                                                            <?php echo number_format($stat['utilization_percent'], 1); ?>%
                                                        </span>
                                                    </div>
                                                    <?php if ($stat['avg_utilization_percent'] !== null): ?>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Avg per flight: <?php echo number_format($stat['avg_utilization_percent'], 1); ?>%
                                                        </p>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary Cards for Each Aircraft -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($passengerStats as $stat): ?>
                        <?php if ($stat['capacity']): ?>
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <i class="fas fa-plane mr-2 text-blue-500"></i>
                                        <?php echo htmlspecialchars($stat['Rego']); ?>
                                    </h3>
                                    <span class="inline-flex items-center px-3 py-1 rounded-md bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm font-medium">
                                        <?php echo htmlspecialchars($stat['capacity']); ?> seats
                                    </span>
                                </div>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Flights:</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo number_format($stat['total_flights']); ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Passengers:</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo number_format($stat['total_passengers']); ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Potential Capacity:</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo number_format($stat['total_capacity']); ?></span>
                                    </div>
                                    
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                            <?php echo htmlspecialchars($stat['Rego']); ?> has carried 
                                            <strong><?php echo number_format($stat['total_passengers']); ?></strong> passengers 
                                            in <strong><?php echo number_format($stat['total_flights']); ?></strong> flights.
                                            With a capacity of <strong><?php echo htmlspecialchars($stat['capacity']); ?></strong> seats per flight,
                                            it could have carried <strong><?php echo number_format($stat['total_capacity']); ?></strong> passengers total.
                                        </p>
                                        <?php if ($stat['utilization_percent'] !== null): ?>
                                            <div class="mt-2">
                                                <div class="flex justify-between text-xs mb-1">
                                                    <span class="text-gray-600 dark:text-gray-400">Utilization:</span>
                                                    <span class="font-medium <?php 
                                                        $util = $stat['utilization_percent'];
                                                        echo $util >= 80 ? 'text-green-600 dark:text-green-400' : ($util >= 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                                    ?>">
                                                        <?php echo number_format($stat['utilization_percent'], 1); ?>%
                                                    </span>
                                                </div>
                                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                    <div class="h-2 rounded-full <?php 
                                                        $util = $stat['utilization_percent'];
                                                        echo $util >= 80 ? 'bg-green-600' : ($util >= 60 ? 'bg-yellow-500' : 'bg-red-500');
                                                    ?>" style="width: <?php echo min(100, $stat['utilization_percent']); ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

