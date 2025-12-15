<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/caa/city_per_international.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get current year and month for default values
$currentYear = date('Y');
$currentMonth = date('n');

// Handle AJAX request for flight data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_flight_data') {
    // Clear any previous output and disable error display
    // Check if output buffering is active before cleaning
    if (ob_get_level() > 0) {
        ob_clean();
    }
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    try {
        // Get months as array from POST data
        $selectedMonths = $_POST['months'] ?? [];
        
        // Validate input
        if (empty($selectedMonths) || !is_array($selectedMonths)) {
            throw new Exception('No months selected');
        }
        
        $year = intval($_POST['year'] ?? $currentYear);
        if ($year < 2020 || $year > 2030) {
            throw new Exception('Invalid year');
        }
        
        $airlineName = trim($_POST['airline_name'] ?? '');
        $airlineCode = trim($_POST['airline_code'] ?? '');
    
        $db = getDBConnection();
        
        // Build the query
        $whereConditions = [];
        $params = [];
        
        // Add year filter
        $whereConditions[] = "YEAR(FltDate) = ?";
        $params[] = $year;
        
        // Add month filter
        if (!empty($selectedMonths) && is_array($selectedMonths)) {
            $monthPlaceholders = str_repeat('?,', count($selectedMonths) - 1) . '?';
            $whereConditions[] = "MONTH(FltDate) IN ($monthPlaceholders)";
            $params = array_merge($params, $selectedMonths);
        }
        
        // Airline filters removed - no corresponding columns in database
        
        // Exclude cancelled flights (handle NULL values properly)
        $whereConditions[] = "(f.ScheduledTaskStatus IS NULL OR f.ScheduledTaskStatus NOT LIKE 'Cancelled')";
        
        // Filter for International flights only
        // A flight is International if at least one of From or To is International
        // Handle NULL values from LEFT JOIN - if station doesn't exist, exclude from international filter
        $whereConditions[] = "(s_from.location_type = 'International' OR s_to.location_type = 'International')";
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Extract From and To from Route (format: "FROM-TO")
        // Join with stations table twice to check both origin and destination
        // Use LEFT JOIN to include flights even if stations don't exist in stations table
        $query = "SELECT 
                    f.Route,
                    SUBSTRING_INDEX(f.Route, '-', 1) as from_code,
                    SUBSTRING_INDEX(f.Route, '-', -1) as to_code,
                    SUM(CAST(f.adult AS UNSIGNED)) as total_adult,
                    SUM(CAST(f.child AS UNSIGNED)) as total_child,
                    SUM(CAST(f.infant AS UNSIGNED)) as total_infant,
                    SUM(CAST(f.total_pax AS UNSIGNED)) as total_passengers,
                    COUNT(*) as flight_count,
                    s_from.location_type as from_location_type,
                    s_to.location_type as to_location_type
                  FROM flights f
                  LEFT JOIN stations s_from ON s_from.iata_code = SUBSTRING_INDEX(f.Route, '-', 1)
                  LEFT JOIN stations s_to ON s_to.iata_code = SUBSTRING_INDEX(f.Route, '-', -1)
                  $whereClause
                  GROUP BY f.Route, from_code, to_code, s_from.location_type, s_to.location_type
                  ORDER BY total_passengers DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data for the report
        $reportData = [];
        $totalTransit = 0;
        
        foreach ($flights as $index => $flight) {
            $from = $flight['from_code'] ?? '';
            $to = $flight['to_code'] ?? '';
            
            // Skip rows where From or To is empty
            if (empty($from) || empty($to)) {
                continue;
            }
            
            $reportData[] = [
                'no' => count($reportData) + 1, // Use count instead of index for proper numbering
                'sns' => '', // S/NS column - empty for now
                'from' => $from,
                'to' => $to,
                'adu' => intval($flight['total_adult']),
                'chd' => intval($flight['total_child']),
                'inf' => intval($flight['total_infant']),
                'total' => intval($flight['total_passengers']),
                'freight' => 0, // Always 0 as per requirements
                'mail' => 0, // Always 0 as per requirements
                'flights' => intval($flight['flight_count']),
                'isSameCity' => ($from === $to) // Flag for same city pairs
            ];
            
            $totalTransit += intval($flight['total_passengers']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'totalTransit' => $totalTransit
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage(),
            'debug' => [
                'months' => $selectedMonths ?? 'not set',
                'year' => $year ?? 'not set',
                'query' => $query ?? 'not set',
                'params' => $params ?? 'not set'
            ]
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>City-Pairs International - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">City-Pairs International</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Monthly City-Pairs and Traffic Report (International Only)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Report Container -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <!-- Header Section -->
                    <div class="bg-blue-600 dark:bg-blue-900/20 text-white p-6 text-center">
                        <div class="text-2xl font-bold mb-2">CAOIRI</div>
                        <div class="text-lg mb-1">Civil Aviation Organization of IRAN</div>
                        <div class="text-base mb-2">Center for Statistics and Computing</div>
                        <div class="text-xl font-semibold">Monthly City-Pairs and Traffic Report</div>
                    </div>

                    <!-- Form Controls -->
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col lg:flex-row lg:items-end gap-6">
                            <!-- Left Section -->
                            <div class="flex-1">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Airline Name:
                                        </label>
                                        <input type="text" id="airlineName" value="Raimon Airways" placeholder="Enter airline name"
                                               class="w-full h-10 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-colors duration-200">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Airline Code:
                                        </label>
                                        <input type="text" id="airlineCode" value="RAI" placeholder="Enter airline code"
                                               class="w-full h-10 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-colors duration-200">
                                    </div>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Month:
                                    </label>
                                    <div class="grid grid-cols-6 gap-2">
                                        <?php
                                        $months = [
                                            ['id' => 'jan', 'value' => '01', 'label' => 'Jan'],
                                            ['id' => 'feb', 'value' => '02', 'label' => 'Feb'],
                                            ['id' => 'mar', 'value' => '03', 'label' => 'Mar'],
                                            ['id' => 'apr', 'value' => '04', 'label' => 'Apr'],
                                            ['id' => 'may', 'value' => '05', 'label' => 'May'],
                                            ['id' => 'jun', 'value' => '06', 'label' => 'Jun'],
                                            ['id' => 'jul', 'value' => '07', 'label' => 'Jul'],
                                            ['id' => 'aug', 'value' => '08', 'label' => 'Aug'],
                                            ['id' => 'sep', 'value' => '09', 'label' => 'Sep'],
                                            ['id' => 'oct', 'value' => '10', 'label' => 'Oct'],
                                            ['id' => 'nov', 'value' => '11', 'label' => 'Nov'],
                                            ['id' => 'dec', 'value' => '12', 'label' => 'Dec']
                                        ];
                                        
                                        foreach ($months as $month) {
                                            $checked = ($month['value'] == sprintf('%02d', $currentMonth)) ? 'checked' : '';
                                            echo '<div class="flex items-center h-10">';
                                            echo '<input type="checkbox" id="' . $month['id'] . '" value="' . $month['value'] . '" ' . $checked . ' class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">';
                                            echo '<label for="' . $month['id'] . '" class="ml-2 text-sm text-gray-700 dark:text-gray-300">' . $month['label'] . '</label>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Year:
                                    </label>
                                    <input type="text" id="year" value="<?php echo $currentYear; ?>" placeholder="Enter year"
                                           class="w-full h-10 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-colors duration-200">
                                </div>
                            </div>

                            <!-- Right Section -->
                            <div class="flex flex-col lg:items-end">
                                <div class="flex flex-wrap gap-3 lg:flex-nowrap">
                                    <button onclick="loadFlightData()" 
                                            class="inline-flex items-center justify-center h-10 px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-sm hover:shadow-md">
                                        <i class="fas fa-download mr-2"></i>
                                        Load Flight Data
                                    </button>
                                    <button onclick="downloadExcel()" id="downloadBtn" disabled
                                            class="inline-flex items-center justify-center h-10 px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-600">
                                        <i class="fas fa-file-excel mr-2"></i>
                                        Download Excel
                                    </button>
                                    <button onclick="downloadWord()" id="downloadWordBtn" disabled
                                            class="inline-flex items-center justify-center h-10 px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-600">
                                        <i class="fas fa-file-word mr-2"></i>
                                        Download Word
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="loading" class="hidden p-6 text-center">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-spinner fa-spin text-2xl text-blue-600 mr-3"></i>
                            <span class="text-lg text-gray-600 dark:text-gray-400">Loading flight data...</span>
                        </div>
                    </div>

                    <!-- Error State -->
                    <div id="error" class="hidden p-6">
                        <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200" id="errorMessage"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Data State -->
                    <div id="noData" class="hidden p-6 text-center">
                        <div class="text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p class="text-lg">No flight data available for the selected period</p>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div id="tableContainer" class="hidden overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">NO.</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">S/NS</th>
                                    <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">City - Pair</th>
                                    <th colspan="4" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">Passenger</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">Freight (Kg)</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">Mail (Kg)</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Number of Flights</th>
                                </tr>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">From</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">To</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">ADU</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">CHD</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">INF</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Data will be populated here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-6 border-t border-gray-200 dark:border-gray-600">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-48">Total Transit Passengers:</span>
                                        <span class="text-sm text-gray-900 dark:text-white font-semibold" id="totalTransit">0</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-48">Total Flights:</span>
                                        <span class="text-sm text-gray-900 dark:text-white font-semibold" id="totalFlights">0</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-48">Contact Person:</span>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Name:</span>
                                        <span class="text-sm text-gray-900 dark:text-white font-semibold ml-2">Somayeh Kakavand</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-48"></span>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Tel:</span>
                                        <span class="text-sm text-gray-900 dark:text-white font-semibold ml-2">09121471778</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-48"></span>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Email:</span>
                                        <span class="text-sm text-gray-900 dark:text-white font-semibold ml-2">kakavand.s@raimonairways.net</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentData = [];

        function loadFlightData() {
            const selectedMonths = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
                .map(cb => cb.value);
            const year = document.getElementById('year').value;
            const airlineName = document.getElementById('airlineName').value;
            const airlineCode = document.getElementById('airlineCode').value;

            if (selectedMonths.length === 0) {
                alert('Please select at least one month.');
                return;
            }

            if (!year) {
                alert('Please enter a year.');
                return;
            }

            // Show loading state
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('error').classList.add('hidden');
            document.getElementById('noData').classList.add('hidden');
            document.getElementById('tableContainer').classList.add('hidden');

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'get_flight_data');
            // Send months as individual parameters
            selectedMonths.forEach(month => {
                formData.append('months[]', month);
            });
            formData.append('year', year);
            formData.append('airline_name', airlineName);
            formData.append('airline_code', airlineCode);

            // Make AJAX request
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Get as text first to debug
            })
            .then(text => {
                console.log('Raw response:', text); // Debug log
                try {
                    const data = JSON.parse(text);
                    document.getElementById('loading').classList.add('hidden');
                    
                    if (data.success) {
                        currentData = data.data;
                        displayData(data.data);
                        document.getElementById('totalTransit').textContent = data.totalTransit;
                        // Calculate total flights
                        const totalFlights = data.data.reduce((sum, row) => sum + row.flights, 0);
                        document.getElementById('totalFlights').textContent = totalFlights;
                        document.getElementById('downloadBtn').disabled = false;
                        document.getElementById('downloadWordBtn').disabled = false;
                    } else {
                        showError(data.error || 'Failed to load flight data');
                        if (data.debug) {
                            console.log('Debug info:', data.debug);
                        }
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', text);
                    document.getElementById('loading').classList.add('hidden');
                    showError('Invalid response format: ' + text.substring(0, 100));
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.add('hidden');
                showError('Network error: ' + error.message);
            });
        }

        function displayData(data) {
            if (data.length === 0) {
                document.getElementById('noData').classList.remove('hidden');
                return;
            }

            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';

            data.forEach(row => {
                const tr = document.createElement('tr');
                
                // Apply red background for same city pairs
                if (row.isSameCity) {
                    tr.className = 'bg-red-100 dark:bg-red-900 hover:bg-red-200 dark:hover:bg-red-800';
                } else {
                    tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                }
                
                tr.innerHTML = `
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.no}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.sns}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.from}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.to}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.adu}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.chd}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.inf}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.total}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.freight}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.mail}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${row.flights}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('tableContainer').classList.remove('hidden');
        }

        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('error').classList.remove('hidden');
        }

        function downloadExcel() {
            if (currentData.length === 0) {
                alert('No data to download. Please load flight data first.');
                return;
            }
            
            // Create CSV content
            const headers = [
                'NO.',
                'S/NS',
                'From',
                'To',
                'ADU',
                'CHD',
                'INF',
                'TOTAL',
                'Freight (Kg)',
                'Mail (Kg)',
                'Number of Flights'
            ];
            
            const csvContent = [
                headers.join(','),
                ...currentData.map(row => [
                    row.no,
                    `"${row.sns}"`,
                    `"${row.from}"`,
                    `"${row.to}"`,
                    row.adu,
                    row.chd,
                    row.inf,
                    row.total,
                    row.freight,
                    row.mail,
                    row.flights
                ].join(','))
            ].join('\n');
            
            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `city_pairs_international_report_${document.getElementById('year').value}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadWord() {
            if (currentData.length === 0) {
                alert('No data to download. Please load flight data first.');
                return;
            }
            
            // Create HTML content for Word document
            const year = document.getElementById('year').value;
            const totalTransit = document.getElementById('totalTransit').textContent;
            const totalFlights = document.getElementById('totalFlights').textContent;
            
            const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monthly City-Pairs and Traffic Report (International)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .subtitle { font-size: 18px; margin-bottom: 5px; }
        .org { font-size: 16px; margin-bottom: 5px; }
        .center { font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .city-pair-header { text-align: center; }
        .passenger-header { text-align: center; }
        .summary { margin-top: 30px; }
        .summary h3 { font-size: 18px; margin-bottom: 15px; }
        .summary table { width: 50%; }
        .footer { margin-top: 40px; border-top: 1px solid #000; padding-top: 20px; }
        .contact-info { display: flex; justify-content: space-between; }
        .contact-left, .contact-right { width: 45%; }
        .contact-row { margin-bottom: 10px; }
        .signature-line { border-bottom: 1px solid #000; width: 200px; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">CAOIRI</div>
        <div class="subtitle">Civil Aviation Organization of IRAN</div>
        <div class="center">Center for Statistics and Computing</div>
        <div class="title">Monthly City-Pairs and Traffic Report (International)</div>
        <div>Year: ${year}</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th rowspan="2">NO.</th>
                <th rowspan="2">S/NS</th>
                <th colspan="2" class="city-pair-header">City - Pair</th>
                <th colspan="4" class="passenger-header">Passenger</th>
                <th rowspan="2">Freight (Kg)</th>
                <th rowspan="2">Mail (Kg)</th>
                <th rowspan="2">Number of Flights</th>
            </tr>
            <tr>
                <th>From</th>
                <th>To</th>
                <th>ADU</th>
                <th>CHD</th>
                <th>INF</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            ${currentData.map(row => `
                <tr>
                    <td>${row.no}</td>
                    <td>${row.sns}</td>
                    <td>${row.from}</td>
                    <td>${row.to}</td>
                    <td>${row.adu}</td>
                    <td>${row.chd}</td>
                    <td>${row.inf}</td>
                    <td>${row.total}</td>
                    <td>${row.freight}</td>
                    <td>${row.mail}</td>
                    <td>${row.flights}</td>
                </tr>
            `).join('')}
        </tbody>
    </table>
    
    <div class="summary">
        <h3>Report Summary</h3>
        <table>
            <tr><td>Total Transit Passengers</td><td>${totalTransit}</td></tr>
            <tr><td>Total Flights</td><td>${totalFlights}</td></tr>
            <tr><td>Total Routes</td><td>${currentData.length}</td></tr>
            <tr><td>Report Generated</td><td>${new Date().toLocaleDateString()}</td></tr>
        </table>
    </div>
    
    <div class="footer">
        <div class="contact-info">
            <div class="contact-left">
                <div class="contact-row">
                    <strong>Total Transit Passengers:</strong> ${totalTransit}
                </div>
                <div class="contact-row">
                    <strong>Total Flights:</strong> ${totalFlights}
                </div>
                <div class="contact-row">
                    <strong>Contact Person:</strong>Somayeh Kakavand
                </div>
                <div class="contact-row">
                    <strong>Tel:</strong> 09121471778
                </div>
                <div class="contact-row">
                    <strong>Email:</strong> kakavand.s@raimonairways.net
                </div>
            </div>
        </div>
    </div>
</body>
</html>`;
            
            // Create and download file
            const blob = new Blob([htmlContent], { type: 'application/msword' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `city_pairs_international_report_${year}.doc`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Load data on page load with current month selected
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select current month
            const currentMonthCheckbox = document.getElementById('<?php echo strtolower(date("M")); ?>');
            if (currentMonthCheckbox) {
                currentMonthCheckbox.checked = true;
            }
        });
    </script>
</body>
</html>

