<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/metar_tafor_history.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$weatherHistory = null;
$selectedStation = '';
$selectedDate = '';

// Get all stations with ICAO codes
$stations = getWeatherStations();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selectedStation = trim($_POST['station_id'] ?? '');
    $selectedDate = trim($_POST['date'] ?? date('Y-m-d'));
    
    if (empty($selectedStation)) {
        $error = 'Please select a station.';
    } elseif (empty($selectedDate)) {
        $error = 'Please select a date.';
    } else {
        // Fetch METAR/TAFOR history from API
        $weatherHistory = fetchMETARHistory($selectedStation, $selectedDate);
        
        if (!$weatherHistory || !isset($weatherHistory['ok']) || !$weatherHistory['ok']) {
            $error = 'Failed to fetch METAR/TAFOR history. ' . ($weatherHistory['message'] ?? 'Please check the station code and date.');
        }
    }
}

// Function to fetch METAR/TAFOR history from API
function fetchMETARHistory($stationId, $date) {
    $url = 'http://192.168.201.23/metar/metar_api.php';
    
    $data = [
        'station_id' => $stationId,
        'date' => $date
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, '1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response || !empty($curlError)) {
        return [
            'ok' => false,
            'message' => $curlError ?: 'API request failed'
        ];
    }
    
    $result = json_decode($response, true);
    return $result ?: ['ok' => false, 'message' => 'Invalid JSON response'];
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>METAR/TAFOR History - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">METAR/TAFOR History</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View historical METAR/TAFOR data for stations</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-history text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Historical Weather Data</span>
                        </div>
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

                <!-- Search Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-search mr-2"></i>
                        Search Historical Data
                    </h2>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="station_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Station (ICAO Code) <span class="text-red-500">*</span>
                            </label>
                            <select id="station_id" name="station_id" required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select a station...</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo htmlspecialchars($station['icao_code']); ?>" 
                                            <?php echo ($selectedStation === $station['icao_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station['icao_code']); ?> - <?php echo htmlspecialchars($station['station_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selectedDate ?: date('Y-m-d')); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit"
                                    class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-search mr-2"></i>
                                Search History
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results -->
                <?php if ($weatherHistory && isset($weatherHistory['ok']) && $weatherHistory['ok']): ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <i class="fas fa-cloud-sun mr-2"></i>
                                        METAR/TAFOR History
                                    </h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Station: <span class="font-medium"><?php echo htmlspecialchars($weatherHistory['station_id']); ?></span> | 
                                        Date: <span class="font-medium"><?php echo htmlspecialchars($weatherHistory['date']); ?></span> | 
                                        Records: <span class="font-medium"><?php echo htmlspecialchars($weatherHistory['count'] ?? 0); ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($weatherHistory['data']) && !empty($weatherHistory['data'])): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Raw METAR Text</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($weatherHistory['data'] as $record): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php 
                                                        $dateTime = new DateTime($record['date_time']);
                                                        echo htmlspecialchars($dateTime->format('Y-m-d H:i:s')); 
                                                        ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($dateTime->format('D, M j, Y')); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-mono text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 px-3 py-2 rounded border border-gray-200 dark:border-gray-600">
                                                        <?php echo htmlspecialchars($record['raw_text']); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="px-6 py-8 text-center">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500 dark:text-gray-400">No METAR/TAFOR data found for this station and date.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-center">
                        <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No data available. Please try a different station or date.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

