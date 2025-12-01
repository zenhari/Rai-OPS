<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/payload_calculator.php');

$current_user = getCurrentUser();

// Get today's date
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;

// Get flights for the selected date
$flights = getFlightsForMonitoring($selectedDate);

// Get METAR temperature and payload data for each flight
foreach ($flights as &$flight) {
    $flight['origin_icao'] = null;
    $flight['metar_temp'] = null;
    $flight['route_code_icao'] = null;
    $flight['aircraft_id'] = null;
    $flight['payload_weight'] = null;
    
    if (!empty($flight['Route'])) {
        // Get origin ICAO code from route
        $originIcao = getOriginICAOFromRoute($flight['Route']);
        
        if ($originIcao) {
            $flight['origin_icao'] = $originIcao;
            // Fetch METAR temperature
            $flight['metar_temp'] = fetchMETARTemperature($originIcao);
        }
        
        // Get ICAO-based route code
        $routeCodeIcao = getRouteCodeICAOFromFlightRoute($flight['Route']);
        if ($routeCodeIcao) {
            $flight['route_code_icao'] = $routeCodeIcao;
        }
    }
    
    // Get aircraft ID from registration
    if (!empty($flight['Rego'])) {
        $aircraftId = getAircraftIdByRegistration($flight['Rego']);
        if ($aircraftId) {
            $flight['aircraft_id'] = $aircraftId;
            
            // Get payload data for this route and aircraft
            if ($flight['route_code_icao'] && $flight['metar_temp'] !== null) {
                $payloadData = getPayloadDataByRouteCodeAndAircraft($flight['route_code_icao'], $aircraftId);
                
                if ($payloadData) {
                    // Get payload weight based on temperature
                    $flight['payload_weight'] = getPayloadWeightByTemperature($payloadData, $flight['metar_temp']);
                }
            }
        }
    }
}
unset($flight); // Unset reference
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payload Calculator - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payload Calculator</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Calculate payload weight for today's flights</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-calculator text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Weight Calculator</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Date Selection -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <form method="GET" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select Date
                            </label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   onchange="this.form.submit()">
                        </div>
                        <button type="button" onclick="window.location.href='?date=<?php echo date('Y-m-d'); ?>'"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Today
                        </button>
                    </form>
                </div>

                <!-- Flights List -->
                <div class="space-y-4">
                    <?php if (empty($flights)): ?>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-center">
                            <i class="fas fa-plane-slash text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-400">No flights found for <?php echo date('F j, Y', strtotime($selectedDate)); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($flights as $flight): ?>
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                                <!-- Flight Header -->
                                <button onclick="toggleFlight(<?php echo $flight['id']; ?>)" 
                                        class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-plane text-blue-500 text-xl"></i>
                                        </div>
                                        <div class="text-left">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($flight['TaskName'] ?? 'N/A'); ?>
                                            </h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                                <?php if ($flight['TaskStart']): ?>
                                                    | <?php echo date('H:i', strtotime($flight['TaskStart'])); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white" id="total-payload-<?php echo $flight['id']; ?>">
                                                0.00 lbs
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Total Payload</p>
                                        </div>
                                        <i class="fas fa-chevron-down text-gray-400 transition-transform duration-200" id="arrow-<?php echo $flight['id']; ?>"></i>
                                    </div>
                                </button>

                                <!-- Flight Details (Collapsible) -->
                                <div id="flight-<?php echo $flight['id']; ?>" class="hidden border-t border-gray-200 dark:border-gray-700">
                                    <div class="px-6 py-4">
                                        <!-- Flight Information -->
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Aircraft</p>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['Rego'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Route</p>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Task Start</p>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo $flight['TaskStart'] ? date('H:i', strtotime($flight['TaskStart'])) : 'N/A'; ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Origin Temperature</p>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php if ($flight['metar_temp'] !== null): ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-md bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                            <i class="fas fa-thermometer-half mr-1"></i>
                                                            <?php echo $flight['metar_temp']; ?>°C
                                                        </span>
                                                        <?php if ($flight['origin_icao']): ?>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(<?php echo htmlspecialchars($flight['origin_icao']); ?>)</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 dark:text-gray-500">
                                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                                            N/A
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Payload Data from Database -->
                                        <?php if ($flight['payload_weight'] !== null): ?>
                                            <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6" 
                                                 id="reference-payload-<?php echo $flight['id']; ?>" 
                                                 data-payload-weight="<?php echo $flight['payload_weight']; ?>">
                                                <div class="flex items-center justify-between mb-4">
                                                    <div>
                                                        <h4 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-1">
                                                            <i class="fas fa-database mr-2"></i>
                                                            Maximum Payload Capacity (from Payload Data)
                                                        </h4>
                                                        <p class="text-xs text-blue-700 dark:text-blue-300">
                                                            Based on temperature: <?php echo $flight['metar_temp']; ?>°C
                                                            <?php if ($flight['route_code_icao']): ?>
                                                                | Route: <?php echo htmlspecialchars($flight['route_code_icao']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-200">
                                                            <?php echo number_format($flight['payload_weight'], 2); ?> lbs
                                                        </p>
                                                        <p class="text-xs text-blue-700 dark:text-blue-300">
                                                            <?php echo number_format($flight['payload_weight'] / 2.20462, 2); ?> kg
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <!-- Difference and Available Passengers -->
                                                <div id="payload-difference-<?php echo $flight['id']; ?>" class="hidden border-t border-blue-200 dark:border-blue-700 pt-4 mt-4">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <p class="text-xs text-blue-700 dark:text-blue-300 mb-1">Remaining Capacity (Max - Current)</p>
                                                            <p class="text-lg font-bold text-green-600 dark:text-green-400" id="payload-diff-<?php echo $flight['id']; ?>">
                                                                0.00 lbs
                                                            </p>
                                                            <p class="text-xs text-blue-700 dark:text-blue-300 mt-1" id="payload-diff-kg-<?php echo $flight['id']; ?>">
                                                                0.00 kg
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <p class="text-xs text-blue-700 dark:text-blue-300 mb-1">Available Passengers (÷ 231 lbs per person)</p>
                                                            <p class="text-lg font-bold text-green-600 dark:text-green-400" id="available-passengers-<?php echo $flight['id']; ?>">
                                                                0
                                                            </p>
                                                            <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                                                Additional passengers
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif ($flight['metar_temp'] !== null && $flight['route_code_icao'] && $flight['aircraft_id']): ?>
                                            <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 mb-6">
                                                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    No payload data found for this route (<?php echo htmlspecialchars($flight['route_code_icao']); ?>) and aircraft combination.
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Payload Calculation Form -->
                                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">
                                                <i class="fas fa-weight mr-2"></i>
                                                Payload Calculation
                                            </h4>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                                <!-- Adult Count -->
                                                <div>
                                                    <label for="adult-<?php echo $flight['id']; ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Adult Count
                                                    </label>
                                                    <input type="number" id="adult-<?php echo $flight['id']; ?>" min="0" step="1" 
                                                           oninput="calculatePayload(<?php echo $flight['id']; ?>)"
                                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white"
                                                           placeholder="0">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">× 85 kg = <span id="adult-weight-<?php echo $flight['id']; ?>">0</span> kg</p>
                                                </div>

                                                <!-- Child Count -->
                                                <div>
                                                    <label for="child-<?php echo $flight['id']; ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Child Count
                                                    </label>
                                                    <input type="number" id="child-<?php echo $flight['id']; ?>" min="0" step="1"
                                                           oninput="calculatePayload(<?php echo $flight['id']; ?>)"
                                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white"
                                                           placeholder="0">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">× 20 kg = <span id="child-weight-<?php echo $flight['id']; ?>">0</span> kg</p>
                                                </div>

                                                <!-- Infant Count -->
                                                <div>
                                                    <label for="infant-<?php echo $flight['id']; ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Infant Count
                                                    </label>
                                                    <input type="number" id="infant-<?php echo $flight['id']; ?>" min="0" step="1"
                                                           oninput="calculatePayload(<?php echo $flight['id']; ?>)"
                                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white"
                                                           placeholder="0">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">× 0 kg = <span id="infant-weight-<?php echo $flight['id']; ?>">0</span> kg</p>
                                                </div>

                                                <!-- Accompanying Load -->
                                                <div>
                                                    <label for="accompanying-<?php echo $flight['id']; ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Weight of the Accompanying Load (kg)
                                                    </label>
                                                    <input type="number" id="accompanying-<?php echo $flight['id']; ?>" min="0" step="0.01"
                                                           oninput="calculatePayload(<?php echo $flight['id']; ?>)"
                                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white"
                                                           placeholder="0.00">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">in kilograms</p>
                                                </div>
                                            </div>

                                            <!-- Calculation Summary -->
                                            <div class="border-t border-gray-200 dark:border-gray-600 pt-4 mt-4">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Calculation Summary</h5>
                                                        <div class="space-y-1 text-sm">
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-600 dark:text-gray-400">Adult Weight:</span>
                                                                <span class="font-medium text-gray-900 dark:text-white" id="summary-adult-<?php echo $flight['id']; ?>">0.00 kg</span>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-600 dark:text-gray-400">Child Weight:</span>
                                                                <span class="font-medium text-gray-900 dark:text-white" id="summary-child-<?php echo $flight['id']; ?>">0.00 kg</span>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-600 dark:text-gray-400">Infant Weight:</span>
                                                                <span class="font-medium text-gray-900 dark:text-white" id="summary-infant-<?php echo $flight['id']; ?>">0.00 kg</span>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-600 dark:text-gray-400">Accompanying Load:</span>
                                                                <span class="font-medium text-gray-900 dark:text-white" id="summary-accompanying-<?php echo $flight['id']; ?>">0.00 kg</span>
                                                            </div>
                                                            <div class="flex justify-between border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                                                                <span class="font-medium text-gray-900 dark:text-white">Total (kg):</span>
                                                                <span class="font-bold text-gray-900 dark:text-white" id="total-kg-<?php echo $flight['id']; ?>">0.00 kg</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center justify-center">
                                                        <div class="text-center bg-blue-50 dark:bg-blue-900 rounded-lg p-6 w-full">
                                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Total Payload</p>
                                                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400" id="total-payload-display-<?php echo $flight['id']; ?>">
                                                                0.00 lbs
                                                            </p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" id="total-kg-display-<?php echo $flight['id']; ?>">
                                                                0.00 kg
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle flight details
        function toggleFlight(flightId) {
            const flightDetails = document.getElementById('flight-' + flightId);
            const arrow = document.getElementById('arrow-' + flightId);
            
            if (flightDetails.classList.contains('hidden')) {
                flightDetails.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                flightDetails.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }

        // Calculate payload for a flight
        function calculatePayload(flightId) {
            // Get input values
            const adultCount = parseFloat(document.getElementById('adult-' + flightId).value) || 0;
            const childCount = parseFloat(document.getElementById('child-' + flightId).value) || 0;
            const infantCount = parseFloat(document.getElementById('infant-' + flightId).value) || 0;
            const accompanyingLoad = parseFloat(document.getElementById('accompanying-' + flightId).value) || 0;

            // Calculate weights in kilograms
            const adultWeight = adultCount * 85; // 85 kg per adult
            const childWeight = childCount * 20; // 20 kg per child
            const infantWeight = infantCount * 0; // 0 kg per infant

            // Total weight in kilograms
            const totalKg = adultWeight + childWeight + infantWeight + accompanyingLoad;

            // Convert to pounds (1 kg = 2.20462 lbs)
            const totalLbs = totalKg * 2.20462;

            // Update displays
            document.getElementById('adult-weight-' + flightId).textContent = adultWeight.toFixed(2);
            document.getElementById('child-weight-' + flightId).textContent = childWeight.toFixed(2);
            document.getElementById('infant-weight-' + flightId).textContent = infantWeight.toFixed(2);

            document.getElementById('summary-adult-' + flightId).textContent = adultWeight.toFixed(2) + ' kg';
            document.getElementById('summary-child-' + flightId).textContent = childWeight.toFixed(2) + ' kg';
            document.getElementById('summary-infant-' + flightId).textContent = infantWeight.toFixed(2) + ' kg';
            document.getElementById('summary-accompanying-' + flightId).textContent = accompanyingLoad.toFixed(2) + ' kg';

            document.getElementById('total-kg-' + flightId).textContent = totalKg.toFixed(2) + ' kg';
            document.getElementById('total-payload-' + flightId).textContent = totalLbs.toFixed(2) + ' lbs';
            document.getElementById('total-payload-display-' + flightId).textContent = totalLbs.toFixed(2) + ' lbs';
            document.getElementById('total-kg-display-' + flightId).textContent = totalKg.toFixed(2) + ' kg';

            // Calculate difference with reference payload from database
            // Reference Payload = Maximum capacity (from database)
            // Total Payload = Current passenger weight
            // Difference = Reference Payload - Total Payload (remaining capacity)
            const referencePayloadEl = document.getElementById('reference-payload-' + flightId);
            if (referencePayloadEl) {
                const referencePayloadWeight = parseFloat(referencePayloadEl.getAttribute('data-payload-weight')) || 0;
                
                // Calculate difference: Reference Payload - Total Payload (remaining capacity)
                const difference = referencePayloadWeight - totalLbs;
                
                // Calculate available passengers: difference / 231
                const availablePassengers = Math.floor(difference / 231);
                
                // Show difference section
                const diffSection = document.getElementById('payload-difference-' + flightId);
                if (diffSection) {
                    diffSection.classList.remove('hidden');
                    
                    // Update difference display
                    const diffEl = document.getElementById('payload-diff-' + flightId);
                    const diffKgEl = document.getElementById('payload-diff-kg-' + flightId);
                    
                    if (difference >= 0) {
                        // Positive difference = remaining capacity
                        diffEl.textContent = difference.toFixed(2) + ' lbs';
                        diffEl.classList.remove('text-red-600', 'dark:text-red-400');
                        diffEl.classList.add('text-green-600', 'dark:text-green-400');
                        diffKgEl.textContent = (difference / 2.20462).toFixed(2) + ' kg (remaining)';
                    } else {
                        // Negative difference = overload
                        diffEl.textContent = Math.abs(difference).toFixed(2) + ' lbs';
                        diffEl.classList.remove('text-green-600', 'dark:text-green-400');
                        diffEl.classList.add('text-red-600', 'dark:text-red-400');
                        diffKgEl.textContent = (Math.abs(difference) / 2.20462).toFixed(2) + ' kg (overload!)';
                    }
                    
                    // Update available passengers
                    const availablePassengersEl = document.getElementById('available-passengers-' + flightId);
                    if (availablePassengers >= 0) {
                        availablePassengersEl.textContent = availablePassengers;
                        availablePassengersEl.classList.remove('text-red-600', 'dark:text-red-400');
                        availablePassengersEl.classList.add('text-green-600', 'dark:text-green-400');
                    } else {
                        availablePassengersEl.textContent = Math.abs(availablePassengers) + ' (overload!)';
                        availablePassengersEl.classList.remove('text-green-600', 'dark:text-green-400');
                        availablePassengersEl.classList.add('text-red-600', 'dark:text-red-400');
                    }
                    
                    // Show warning if overloaded
                    let warningEl = document.getElementById('payload-warning-' + flightId);
                    if (difference < 0) {
                        if (!warningEl) {
                            warningEl = document.createElement('div');
                            warningEl.id = 'payload-warning-' + flightId;
                            warningEl.className = 'mt-3 p-3 bg-red-100 dark:bg-red-900 border border-red-300 dark:border-red-700 rounded-md';
                            warningEl.innerHTML = '<p class="text-sm text-red-800 dark:text-red-200"><i class="fas fa-exclamation-triangle mr-2"></i><strong>Warning:</strong> Current payload exceeds maximum capacity!</p>';
                            diffSection.appendChild(warningEl);
                        }
                        warningEl.classList.remove('hidden');
                    } else if (warningEl) {
                        warningEl.classList.add('hidden');
                    }
                }
            }
        }
    </script>
</body>
</html>
