<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/metar_tafor.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get all stations with ICAO codes from the database
$stations = getWeatherStations();
$selectedStation = '';
$weatherData = null;

// Handle station selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['station_code'])) {
    $selectedStation = trim($_POST['station_code']);
    
    if (!empty($selectedStation)) {
        // Fetch weather data from Aviation Weather API
        $weatherData = fetchWeatherData($selectedStation);
        
        if (!$weatherData) {
            $error = 'Failed to fetch weather data for station ' . $selectedStation;
        }
    }
}

// Function to fetch weather data from Aviation Weather API
function fetchWeatherData($stationCode) {
    $url = "https://aviationweather.gov/api/data/metar?ids={$stationCode}&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, '1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    return $data;
}

// Function to format temperature
function formatTemperature($temp) {
    if ($temp === null) return 'N/A';
    return $temp > 0 ? "+{$temp}°C" : "{$temp}°C";
}

// Function to format wind direction and speed
function formatWind($wdir, $wspd) {
    if ($wdir === null || $wspd === null) return 'N/A';
    
    $direction = '';
    if ($wdir >= 337.5 || $wdir < 22.5) $direction = 'N';
    elseif ($wdir >= 22.5 && $wdir < 67.5) $direction = 'NE';
    elseif ($wdir >= 67.5 && $wdir < 112.5) $direction = 'E';
    elseif ($wdir >= 112.5 && $wdir < 157.5) $direction = 'SE';
    elseif ($wdir >= 157.5 && $wdir < 202.5) $direction = 'S';
    elseif ($wdir >= 202.5 && $wdir < 247.5) $direction = 'SW';
    elseif ($wdir >= 247.5 && $wdir < 292.5) $direction = 'W';
    elseif ($wdir >= 292.5 && $wdir < 337.5) $direction = 'NW';
    
    return "{$direction} {$wspd}KT";
}

// Function to format visibility
function formatVisibility($visib) {
    if (empty($visib)) return 'N/A';
    return $visib === '6+' ? '10KM+' : $visib . 'KM';
}

// Function to format pressure
function formatPressure($altim) {
    if ($altim === null) return 'N/A';
    return $altim . ' hPa';
}

// Function to format flight category
function formatFlightCategory($fltCat) {
    if (empty($fltCat)) return 'N/A';
    
    $colors = [
        'VFR' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'MVFR' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'IFR' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'LIFR' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    ];
    
    $colorClass = $colors[$fltCat] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colorClass}\">{$fltCat}</span>";
}

// Function to format cloud cover
function formatCloudCover($cover) {
    if (empty($cover)) return 'N/A';
    
    $covers = [
        'CAVOK' => 'Clear and Visibility OK',
        'CLR' => 'Clear',
        'FEW' => 'Few',
        'SCT' => 'Scattered',
        'BKN' => 'Broken',
        'OVC' => 'Overcast'
    ];
    
    return $covers[$cover] ?? $cover;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>METAR/TAFOR - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">METAR/TAFOR</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Aviation Weather Information</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-cloud-sun text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Real-time Weather Data</span>
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

                <!-- Station Selection -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Station</h2>
                    <form method="POST" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label for="station_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                ICAO Station Code
                            </label>
                            <select id="station_code" name="station_code" required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select Station --</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo htmlspecialchars($station['icao_code']); ?>" 
                                            <?php echo $selectedStation === $station['icao_code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station['icao_code'] . ' - ' . $station['station_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>
                            Get Weather Data
                        </button>
                    </form>
                </div>

                <!-- Weather Data Display -->
                <?php if ($weatherData && !empty($weatherData)): ?>
                    <?php foreach ($weatherData as $data): ?>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                            <!-- Header -->
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xl font-bold text-white">
                                            <?php echo htmlspecialchars($data['icaoId']); ?>
                                        </h3>
                                        <p class="text-blue-100">
                                            <?php echo htmlspecialchars($data['name']); ?>
                                        </p>
                                    </div>
                                    <div class="text-right text-white">
                                        <div class="text-sm text-blue-100">Last Updated</div>
                                        <div class="font-medium">
                                            <?php echo date('H:i UTC', $data['obsTime']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Weather Information -->
                            <div class="p-6">
                                <!-- Current Conditions -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                                    <!-- Temperature -->
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-thermometer-half text-2xl text-red-500 mr-3"></i>
                                            <div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Temperature</div>
                                                <div class="text-xl font-bold text-gray-900 dark:text-white">
                                                    <?php echo formatTemperature($data['temp']); ?>
                                                </div>
                                                <?php if ($data['dewp'] !== null): ?>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        Dew Point: <?php echo formatTemperature($data['dewp']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Wind -->
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-wind text-2xl text-blue-500 mr-3"></i>
                                            <div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Wind</div>
                                                <div class="text-xl font-bold text-gray-900 dark:text-white">
                                                    <?php echo formatWind($data['wdir'], $data['wspd']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Visibility -->
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-eye text-2xl text-green-500 mr-3"></i>
                                            <div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Visibility</div>
                                                <div class="text-xl font-bold text-gray-900 dark:text-white">
                                                    <?php echo formatVisibility($data['visib']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pressure -->
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-tachometer-alt text-2xl text-purple-500 mr-3"></i>
                                            <div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Pressure</div>
                                                <div class="text-xl font-bold text-gray-900 dark:text-white">
                                                    <?php echo formatPressure($data['altim']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Flight Category and Cloud Cover -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Flight Category</h4>
                                        <div class="text-2xl">
                                            <?php echo formatFlightCategory($data['fltCat']); ?>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Cloud Cover</h4>
                                        <div class="text-lg text-gray-700 dark:text-gray-300">
                                            <?php echo formatCloudCover($data['cover']); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Raw METAR -->
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Raw METAR</h4>
                                    <div class="bg-gray-900 text-green-400 p-4 rounded-md font-mono text-sm overflow-x-auto">
                                        <?php echo htmlspecialchars($data['rawOb']); ?>
                                    </div>
                                </div>

                                <!-- Station Information -->
                                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Latitude</div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            <?php echo number_format($data['lat'], 4); ?>°N
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Longitude</div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            <?php echo number_format($data['lon'], 4); ?>°E
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Elevation</div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            <?php echo number_format($data['elev']); ?> ft
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($selectedStation): ?>
                    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    No weather data available for station <?php echo htmlspecialchars($selectedStation); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
