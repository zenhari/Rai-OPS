<?php
/**
 * CAO API Integration - Main Interface
 * 
 * This page is the main interface for managing and testing CAO API message sending scripts.
 */

require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

// Check page access
if (!checkPageAccessEnhanced('admin/cao_api/index.php')) {
    $encodedPage = urlencode('admin/cao_api/index.php');
    header("Location: /access_denied.php?page=$encodedPage");
    exit();
}

$user = getCurrentUser();

// Get today's flights only
$today = date('Y-m-d');
$db = getDBConnection();
$stmt = $db->prepare("
    SELECT FlightID, TaskName, FlightNo, FltDate, Route, Rego
    FROM flights 
    WHERE DATE(FltDate) = ?
    ORDER BY TaskStart DESC
");
$stmt->execute([$today]);
$recentFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAO API Integration - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../../assets/js/tailwind.js"></script>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-paper-plane mr-2"></i>CAO API Integration
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Send flight messages to CAO API
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>
                
                <!-- API Scripts -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <!-- MVT-DEP -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-plane-departure text-blue-500 mr-2"></i>MVT-DEP
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Send Departure message
                        </p>
                        <select id="flight-dep" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-3">
                            <option value="">Select flight...</option>
                            <?php foreach ($recentFlights as $flight): ?>
                                <option value="<?php echo htmlspecialchars($flight['FlightID']); ?>">
                                    <?php echo htmlspecialchars($flight['TaskName'] ?? $flight['FlightNo']); ?> - 
                                    <?php echo htmlspecialchars($flight['Route'] ?? ''); ?> - 
                                    <?php echo htmlspecialchars($flight['FltDate'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="sendMessage('dep', 'send_mvt_dep.php')" 
                                class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <div id="result-dep" class="mt-3 text-sm"></div>
                    </div>

                    <!-- MVT-ARR -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-plane-arrival text-green-500 mr-2"></i>MVT-ARR
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Send Arrival message
                        </p>
                        <select id="flight-arr" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-3">
                            <option value="">Select flight...</option>
                            <?php foreach ($recentFlights as $flight): ?>
                                <option value="<?php echo htmlspecialchars($flight['FlightID']); ?>">
                                    <?php echo htmlspecialchars($flight['TaskName'] ?? $flight['FlightNo']); ?> - 
                                    <?php echo htmlspecialchars($flight['Route'] ?? ''); ?> - 
                                    <?php echo htmlspecialchars($flight['FltDate'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="sendMessage('arr', 'send_mvt_arr.php')" 
                                class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <div id="result-arr" class="mt-3 text-sm"></div>
                    </div>

                    <!-- MVT-DLY -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-clock text-orange-500 mr-2"></i>MVT-DLY
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Send Delay message
                        </p>
                        <select id="flight-dly" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-3">
                            <option value="">Select flight...</option>
                            <?php foreach ($recentFlights as $flight): ?>
                                <option value="<?php echo htmlspecialchars($flight['FlightID']); ?>">
                                    <?php echo htmlspecialchars($flight['TaskName'] ?? $flight['FlightNo']); ?> - 
                                    <?php echo htmlspecialchars($flight['Route'] ?? ''); ?> - 
                                    <?php echo htmlspecialchars($flight['FltDate'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="sendMessage('dly', 'send_mvt_dly.php')" 
                                class="w-full px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-md transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <div id="result-dly" class="mt-3 text-sm"></div>
                    </div>

                    <!-- LDM -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-weight text-purple-500 mr-2"></i>LDM
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Send Load Distribution message
                        </p>
                        <select id="flight-ldm" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-3">
                            <option value="">Select flight...</option>
                            <?php foreach ($recentFlights as $flight): ?>
                                <option value="<?php echo htmlspecialchars($flight['FlightID']); ?>">
                                    <?php echo htmlspecialchars($flight['TaskName'] ?? $flight['FlightNo']); ?> - 
                                    <?php echo htmlspecialchars($flight['Route'] ?? ''); ?> - 
                                    <?php echo htmlspecialchars($flight['FltDate'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="sendMessage('ldm', 'send_ldm.php')" 
                                class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <div id="result-ldm" class="mt-3 text-sm"></div>
                    </div>

                    <!-- CPM -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-box text-teal-500 mr-2"></i>CPM
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Send Container/Pallet message
                        </p>
                        <select id="flight-cpm" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-3">
                            <option value="">Select flight...</option>
                            <?php foreach ($recentFlights as $flight): ?>
                                <option value="<?php echo htmlspecialchars($flight['FlightID']); ?>">
                                    <?php echo htmlspecialchars($flight['TaskName'] ?? $flight['FlightNo']); ?> - 
                                    <?php echo htmlspecialchars($flight['Route'] ?? ''); ?> - 
                                    <?php echo htmlspecialchars($flight['FltDate'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="sendMessage('cpm', 'send_cpm.php')" 
                                class="w-full px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-md transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <div id="result-cpm" class="mt-3 text-sm"></div>
                    </div>
                </div>

                <!-- Documentation -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-book mr-2"></i>Documentation
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        For more information, please read the <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">README.md</code> file.
                    </p>
                    <div class="space-y-2 text-sm">
                        <div><strong>API URL:</strong> <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">https://caadc.cao.ir/api/flight/messages</code></div>
                        <div><strong>Token:</strong> <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">3aea9ada385ce8dca95f125a0fc1c793</code></div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function sendMessage(type, script) {
            const selectId = `flight-${type}`;
            const resultId = `result-${type}`;
            const flightId = document.getElementById(selectId).value;
            const resultDiv = document.getElementById(resultId);
            
            if (!flightId) {
                resultDiv.innerHTML = '<span class="text-red-600 dark:text-red-400">Please select a flight</span>';
                return;
            }
            
            resultDiv.innerHTML = '<span class="text-blue-600 dark:text-blue-400"><i class="fas fa-spinner fa-spin mr-2"></i>Sending...</span>';
            
            fetch(`${script}?flight_id=${flightId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="text-green-600 dark:text-green-400">
                                <i class="fas fa-check-circle mr-2"></i>Message sent successfully!
                            </div>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400">View details</summary>
                                <pre class="mt-2 p-2 bg-gray-100 dark:bg-gray-700 rounded text-xs overflow-auto">${JSON.stringify(data, null, 2)}</pre>
                            </details>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div class="text-red-600 dark:text-red-400">
                                <i class="fas fa-times-circle mr-2"></i>Error: ${data.error || 'Unknown error'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                        <div class="text-red-600 dark:text-red-400">
                            <i class="fas fa-times-circle mr-2"></i>Connection error: ${error.message}
                        </div>
                    `;
                });
        }
    </script>
</body>
</html>

