<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/routes/stations.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_station':
            $stationName = trim($_POST['station_name'] ?? '');
            $iataCode = trim($_POST['iata_code'] ?? '');
            $icaoCode = trim($_POST['icao_code'] ?? '');
            $isBase = isset($_POST['is_base']) ? 1 : 0;
            $shortName = trim($_POST['short_name'] ?? '');
            $timezone = $_POST['timezone'] ?? '';
            $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
            $magneticVariation = trim($_POST['magnetic_variation'] ?? '');
            $addressLine1 = trim($_POST['address_line1'] ?? '');
            $addressLine2 = trim($_POST['address_line2'] ?? '');
            $citySuburb = trim($_POST['city_suburb'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postcode = trim($_POST['postcode'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $ownedByBase = trim($_POST['owned_by_base'] ?? '');
            $slotCoordination = trim($_POST['slot_coordination'] ?? '');
            $locationType = trim($_POST['location_type'] ?? 'Domestic');
            
            // Site properties checkboxes
            $isAla = isset($_POST['is_ala']) ? 1 : 0;
            $isFuelDepot = isset($_POST['is_fuel_depot']) ? 1 : 0;
            $isBaseOffice = isset($_POST['is_base_office']) ? 1 : 0;
            $isCustomsImmigration = isset($_POST['is_customs_immigration']) ? 1 : 0;
            $isFixedBaseOperators = isset($_POST['is_fixed_base_operators']) ? 1 : 0;
            $isHls = isset($_POST['is_hls']) ? 1 : 0;
            $isMaintenanceEngineering = isset($_POST['is_maintenance_engineering']) ? 1 : 0;
            
            if (empty($stationName) || empty($iataCode)) {
                $error = 'Station name and IATA code are required.';
            } else {
                $stationData = [
                    'station_name' => $stationName,
                    'iata_code' => $iataCode,
                    'icao_code' => $icaoCode,
                    'is_base' => $isBase,
                    'short_name' => $shortName,
                    'timezone' => $timezone,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'magnetic_variation' => $magneticVariation,
                    'address_line1' => $addressLine1,
                    'address_line2' => $addressLine2,
                    'city_suburb' => $citySuburb,
                    'state' => $state,
                    'postcode' => $postcode,
                    'country' => $country,
                    'location_type' => $locationType,
                    'owned_by_base' => $ownedByBase,
                    'slot_coordination' => $slotCoordination,
                    'is_ala' => $isAla,
                    'is_fuel_depot' => $isFuelDepot,
                    'is_base_office' => $isBaseOffice,
                    'is_customs_immigration' => $isCustomsImmigration,
                    'is_fixed_base_operators' => $isFixedBaseOperators,
                    'is_hls' => $isHls,
                    'is_maintenance_engineering' => $isMaintenanceEngineering
                ];
                
                if (createStation($stationData)) {
                    $message = 'Station added successfully.';
                } else {
                    $error = 'Failed to add station.';
                }
            }
            break;
            
        case 'update_station':
            $id = intval($_POST['id'] ?? 0);
            $stationName = trim($_POST['station_name'] ?? '');
            $iataCode = trim($_POST['iata_code'] ?? '');
            $icaoCode = trim($_POST['icao_code'] ?? '');
            $isBase = isset($_POST['is_base']) ? 1 : 0;
            $shortName = trim($_POST['short_name'] ?? '');
            $timezone = $_POST['timezone'] ?? '';
            $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
            $magneticVariation = trim($_POST['magnetic_variation'] ?? '');
            $addressLine1 = trim($_POST['address_line1'] ?? '');
            $addressLine2 = trim($_POST['address_line2'] ?? '');
            $citySuburb = trim($_POST['city_suburb'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postcode = trim($_POST['postcode'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $ownedByBase = trim($_POST['owned_by_base'] ?? '');
            $slotCoordination = trim($_POST['slot_coordination'] ?? '');
            $locationType = trim($_POST['location_type'] ?? 'Domestic');
            
            // Site properties checkboxes
            $isAla = isset($_POST['is_ala']) ? 1 : 0;
            $isFuelDepot = isset($_POST['is_fuel_depot']) ? 1 : 0;
            $isBaseOffice = isset($_POST['is_base_office']) ? 1 : 0;
            $isCustomsImmigration = isset($_POST['is_customs_immigration']) ? 1 : 0;
            $isFixedBaseOperators = isset($_POST['is_fixed_base_operators']) ? 1 : 0;
            $isHls = isset($_POST['is_hls']) ? 1 : 0;
            $isMaintenanceEngineering = isset($_POST['is_maintenance_engineering']) ? 1 : 0;
            
            if ($id > 0 && !empty($stationName) && !empty($iataCode)) {
                $stationData = [
                    'station_name' => $stationName,
                    'iata_code' => $iataCode,
                    'icao_code' => $icaoCode,
                    'is_base' => $isBase,
                    'short_name' => $shortName,
                    'timezone' => $timezone,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'magnetic_variation' => $magneticVariation,
                    'address_line1' => $addressLine1,
                    'address_line2' => $addressLine2,
                    'city_suburb' => $citySuburb,
                    'state' => $state,
                    'postcode' => $postcode,
                    'country' => $country,
                    'location_type' => $locationType,
                    'owned_by_base' => $ownedByBase,
                    'slot_coordination' => $slotCoordination,
                    'is_ala' => $isAla,
                    'is_fuel_depot' => $isFuelDepot,
                    'is_base_office' => $isBaseOffice,
                    'is_customs_immigration' => $isCustomsImmigration,
                    'is_fixed_base_operators' => $isFixedBaseOperators,
                    'is_hls' => $isHls,
                    'is_maintenance_engineering' => $isMaintenanceEngineering
                ];
                
                if (updateStation($id, $stationData)) {
                    $message = 'Station updated successfully.';
                } else {
                    $error = 'Failed to update station.';
                }
            } else {
                $error = 'Invalid station data.';
            }
            break;
            
        case 'delete_station':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0 && deleteStation($id)) {
                $message = 'Station deleted successfully.';
            } else {
                $error = 'Failed to delete station.';
            }
            break;
    }
}

// Handle search parameters
$searchName = trim($_GET['search_name'] ?? '');
$searchIata = trim($_GET['search_iata'] ?? '');
$searchIcao = trim($_GET['search_icao'] ?? '');
$searchBase = $_GET['search_base'] ?? '';

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Get stations data with search filters
    $stations = getAllStationsWithSearch($searchName, $searchIata, $searchIcao, $searchBase);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stations_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    fputcsv($output, [
        'Station Name',
        'IATA Code', 
        'ICAO Code',
        'Short Name',
        'Base',
        'Location Type',
        'Country',
        'City/Suburb',
        'Created At',
        'Updated At'
    ]);
    
    // CSV data
    foreach ($stations as $station) {
        fputcsv($output, [
            $station['station_name'],
            $station['iata_code'],
            $station['icao_code'] ?? '',
            $station['short_name'] ?? '',
            $station['is_base'] ? 'Yes' : 'No',
            $station['location_type'] ?? 'Domestic',
            $station['country'] ?? '',
            $station['city_suburb'] ?? '',
            $station['created_at'] ?? '',
            $station['updated_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Get stations data with search filters
$stations = getAllStationsWithSearch($searchName, $searchIata, $searchIcao, $searchBase);
$stationsCount = getStationsCount();
$baseStationsCount = getStationsCount('base');

// Get home bases for dropdown (only active ones)
$homeBases = getAllHomeBases(null, 0, ['published' => 1]);

// Get all countries for dropdown
$countries = getAllCountries();

// Get all timezones
$allTimezones = [];
try {
    $timezoneIdentifiers = DateTimeZone::listIdentifiers();
    foreach ($timezoneIdentifiers as $timezone) {
        try {
            $tz = new DateTimeZone($timezone);
            $now = new DateTime('now', $tz);
            $offset = $tz->getOffset($now);
            $hours = intval($offset / 3600);
            $minutes = abs(intval(($offset % 3600) / 60));
            $offsetString = sprintf('%s%02d:%02d', $offset >= 0 ? '+' : '-', abs($hours), $minutes);
            $displayName = str_replace('_', ' ', $timezone);
            $allTimezones[] = [
                'identifier' => $timezone,
                'offset' => $offsetString,
                'display' => "({$offsetString}) {$displayName}"
            ];
        } catch (Exception $e) {
            // Skip invalid timezones
            continue;
        }
    }
    // Sort by offset, then by name
    usort($allTimezones, function($a, $b) {
        if ($a['offset'] === $b['offset']) {
            return strcmp($a['display'], $b['display']);
        }
        return strcmp($a['offset'], $b['offset']);
    });
    
    // Add custom timezone entries for better city names
    $customTimezones = [
        'Asia/Aqtau' => 'Aktau, Kazakhstan',
        'Asia/Samarkand' => 'Samarkand, Uzbekistan',
        'Asia/Tashkent' => 'Tashkent, Uzbekistan'
    ];
    
    // Update display names for existing timezones
    foreach ($allTimezones as &$tz) {
        if (isset($customTimezones[$tz['identifier']])) {
            $tz['display'] = "({$tz['offset']}) {$customTimezones[$tz['identifier']]}";
        }
    }
    
    // Add missing timezones manually
    foreach ($customTimezones as $identifier => $displayName) {
        $found = false;
        foreach ($allTimezones as $tz) {
            if ($tz['identifier'] === $identifier) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            try {
                $tz = new DateTimeZone($identifier);
                $now = new DateTime('now', $tz);
                $offset = $tz->getOffset($now);
                $hours = intval($offset / 3600);
                $minutes = abs(intval(($offset % 3600) / 60));
                $offsetString = sprintf('%s%02d:%02d', $offset >= 0 ? '+' : '-', abs($hours), $minutes);
                $allTimezones[] = [
                    'identifier' => $identifier,
                    'offset' => $offsetString,
                    'display' => "({$offsetString}) {$displayName}"
                ];
            } catch (Exception $e) {
                // Add with known offsets if timezone doesn't exist
                $knownOffsets = [
                    'Asia/Aqtau' => '+05:00',
                    'Asia/Samarkand' => '+05:00',
                    'Asia/Tashkent' => '+05:00'
                ];
                if (isset($knownOffsets[$identifier])) {
                    $allTimezones[] = [
                        'identifier' => $identifier,
                        'offset' => $knownOffsets[$identifier],
                        'display' => "(UTC{$knownOffsets[$identifier]}) {$displayName}"
                    ];
                }
            }
        }
    }
    
    // Re-sort after adding custom timezones
    usort($allTimezones, function($a, $b) {
        if ($a['offset'] === $b['offset']) {
            return strcmp($a['display'], $b['display']);
        }
        return strcmp($a['offset'], $b['offset']);
    });
} catch (Exception $e) {
    // Fallback to common timezones if listIdentifiers fails
    $allTimezones = [
        ['identifier' => 'Asia/Tehran', 'offset' => '+03:30', 'display' => '(UTC+03:30) Tehran'],
        ['identifier' => 'Asia/Aqtau', 'offset' => '+05:00', 'display' => '(UTC+05:00) Aktau, Kazakhstan'],
        ['identifier' => 'Asia/Samarkand', 'offset' => '+05:00', 'display' => '(UTC+05:00) Samarkand, Uzbekistan'],
        ['identifier' => 'Asia/Tashkent', 'offset' => '+05:00', 'display' => '(UTC+05:00) Tashkent, Uzbekistan'],
        ['identifier' => 'UTC', 'offset' => '+00:00', 'display' => '(UTC+00:00) UTC'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Management - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Station Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage airports and stations</p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="openAddStationModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Station
                            </button>
                            <button onclick="exportStations()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                <i class="fas fa-download mr-2"></i>
                                Export CSV
                            </button>
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-route mr-2"></i>
                                Back to Routes
                            </a>
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

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                                <i class="fas fa-map-marker-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Stations</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $stationsCount; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                                <i class="fas fa-home text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Base Stations</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $baseStationsCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Filter -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Search & Filter</h2>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <label for="search_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Station Name
                            </label>
                            <input type="text" id="search_name" name="search_name" value="<?php echo htmlspecialchars($_GET['search_name'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="Search by name">
                        </div>
                        
                        <div>
                            <label for="search_iata" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                IATA Code
                            </label>
                            <input type="text" id="search_iata" name="search_iata" value="<?php echo htmlspecialchars($_GET['search_iata'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., IKA">
                        </div>
                        
                        <div>
                            <label for="search_icao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                ICAO Code
                            </label>
                            <input type="text" id="search_icao" name="search_icao" value="<?php echo htmlspecialchars($_GET['search_icao'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., OIIE">
                        </div>
                        
                        <div>
                            <label for="search_base" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Base
                            </label>
                            <select id="search_base" name="search_base"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Stations</option>
                                <option value="1" <?php echo ($_GET['search_base'] ?? '') === '1' ? 'selected' : ''; ?>>Base Only</option>
                                <option value="0" <?php echo ($_GET['search_base'] ?? '') === '0' ? 'selected' : ''; ?>>Non-Base Only</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Search
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($_GET['search_name']) || !empty($_GET['search_iata']) || !empty($_GET['search_icao']) || !empty($_GET['search_base'])): ?>
                    <div class="mt-4 flex justify-between items-center">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Filtered results
                        </div>
                        <a href="stations.php" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            <i class="fas fa-times mr-1"></i>
                            Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Stations Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Airports & Stations</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Station Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IATA Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ICAO Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Short Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Latitude</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Longitude</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Base</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($stations)): ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No stations found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($stations as $station): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($station['station_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($station['iata_code']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($station['icao_code'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($station['short_name'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo isset($station['latitude']) && $station['latitude'] !== null ? number_format((float)$station['latitude'], 7, '.', '') : 'N/A'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo isset($station['longitude']) && $station['longitude'] !== null ? number_format((float)$station['longitude'], 7, '.', '') : 'N/A'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($station['is_base']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        <i class="fas fa-home mr-1"></i>
                                                        Base
                                                </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $locationType = $station['location_type'] ?? 'Domestic';
                                                $locationTypeClass = $locationType === 'International' 
                                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' 
                                                    : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $locationTypeClass; ?>">
                                                    <i class="fas fa-globe mr-1"></i>
                                                    <?php echo htmlspecialchars($locationType); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="edit_station.php?id=<?php echo $station['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="deleteStation(<?php echo $station['id']; ?>, '<?php echo htmlspecialchars($station['station_name']); ?>')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
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
            </div>
        </div>
    </div>

    <!-- Add Station Modal -->
    <div id="addStationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Station</h3>
                    <button onclick="closeAddStationModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4 max-h-96 overflow-y-auto">
                    <input type="hidden" name="action" value="add_station">
                    
                    <!-- Basic Information -->
                    <div class="border-b border-gray-200 dark:border-gray-600 pb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Basic Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="station_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Station Name *
                        </label>
                        <input type="text" id="station_name" name="station_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., Tehran Imam Khomeini International">
                    </div>
                    
                            <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="iata_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            IATA Code *
                        </label>
                        <input type="text" id="iata_code" name="iata_code" required maxlength="3"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., IKA">
                    </div>
                    
                    <div>
                        <label for="icao_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ICAO Code
                        </label>
                        <input type="text" id="icao_code" name="icao_code" maxlength="4"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., OIIE">
                                </div>
                            </div>
                            
                            <div>
                                <label for="short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Short Name
                                </label>
                                <input type="text" id="short_name" name="short_name" maxlength="10"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="e.g., IKA">
                            </div>
                            
                            <div>
                                <label for="location_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Location Type *
                                </label>
                                <select id="location_type" name="location_type" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="Domestic" selected>Domestic</option>
                                    <option value="International">International</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" id="is_base" name="is_base" value="1"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Base Station
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location Information -->
                    <div class="border-b border-gray-200 dark:border-gray-600 pb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Location Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Timezone
                                </label>
                                <input type="text" id="timezone" name="timezone" list="timezones_list"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Type to search timezone..." autocomplete="off">
                                <datalist id="timezones_list">
                                    <?php foreach ($allTimezones as $tz): ?>
                                        <option value="<?php echo htmlspecialchars($tz['display']); ?>" data-identifier="<?php echo htmlspecialchars($tz['identifier']); ?>">
                                            <?php echo htmlspecialchars($tz['display']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="latitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Latitude
                                    </label>
                                    <input type="number" id="latitude" name="latitude" step="0.0000001"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 35.6892">
                                </div>
                                
                                <div>
                                    <label for="longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Longitude
                                    </label>
                                    <input type="number" id="longitude" name="longitude" step="0.0000001"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 51.3890">
                                </div>
                            </div>
                            
                            <div>
                                <label for="magnetic_variation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Magnetic Variation
                                </label>
                                <input type="text" id="magnetic_variation" name="magnetic_variation"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="e.g., 3°E">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="border-b border-gray-200 dark:border-gray-600 pb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Address Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="address_line1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Address Line 1
                                </label>
                                <input type="text" id="address_line1" name="address_line1" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Street address">
                            </div>
                            
                            <div>
                                <label for="address_line2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Address Line 2
                                </label>
                                <input type="text" id="address_line2" name="address_line2" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Apartment, suite, etc.">
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label for="city_suburb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        City/Suburb
                                    </label>
                                    <input type="text" id="city_suburb" name="city_suburb" maxlength="100"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="City">
                                </div>
                                
                                <div>
                                    <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        State
                                    </label>
                                    <input type="text" id="state" name="state" maxlength="3"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="State">
                                </div>
                                
                                <div>
                                    <label for="postcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Postcode
                                    </label>
                                    <input type="text" id="postcode" name="postcode" maxlength="10"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="12345">
                                </div>
                            </div>
                            
                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Country
                                </label>
                                <input type="text" id="country" name="country" maxlength="100" list="countries_list"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Type to search country..." autocomplete="off">
                                <datalist id="countries_list">
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo htmlspecialchars($country['name']); ?>">
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Additional Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="owned_by_base" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Owned by Base
                                </label>
                                <select id="owned_by_base" name="owned_by_base"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">- Select -</option>
                                    <?php foreach ($homeBases as $homeBase): ?>
                                        <option value="<?php echo htmlspecialchars($homeBase['location_name']); ?>">
                                            <?php echo htmlspecialchars($homeBase['location_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="slot_coordination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Slot Coordination
                                </label>
                                <input type="text" id="slot_coordination" name="slot_coordination" maxlength="50"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Slot coordination details">
                            </div>
                            
                            <!-- Site Properties -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Site Properties
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_ala" name="is_ala" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">ALA</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_fuel_depot" name="is_fuel_depot" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fuel Depot</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_base_office" name="is_base_office" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Base/Office</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_customs_immigration" name="is_customs_immigration" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Customs & Immigration</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_fixed_base_operators" name="is_fixed_base_operators" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fixed Base Operators</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_hls" name="is_hls" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">HLS</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_maintenance_engineering" name="is_maintenance_engineering" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Maintenance/Engineering</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddStationModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Station
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Station Modal -->
    <div id="editStationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Station</h3>
                    <button onclick="closeEditStationModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4 max-h-96 overflow-y-auto">
                    <input type="hidden" name="action" value="update_station">
                    <input type="hidden" id="edit_station_id" name="id">
                    
                    <!-- Basic Information -->
                    <div class="border-b border-gray-200 dark:border-gray-600 pb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Basic Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="edit_station_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Station Name *
                        </label>
                        <input type="text" id="edit_station_name" name="station_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                            <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_iata_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            IATA Code *
                        </label>
                        <input type="text" id="edit_iata_code" name="iata_code" required maxlength="3"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_icao_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ICAO Code
                        </label>
                        <input type="text" id="edit_icao_code" name="icao_code" maxlength="4"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            
                            <div>
                                <label for="edit_short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Short Name
                                </label>
                                <input type="text" id="edit_short_name" name="short_name" maxlength="10"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="edit_location_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Location Type *
                                </label>
                                <select id="edit_location_type" name="location_type" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="Domestic">Domestic</option>
                                    <option value="International">International</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" id="edit_is_base" name="is_base" value="1"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Base Station
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location Information -->
                    <div class="border-b border-gray-200 dark:border-gray-600 pb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Location Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="edit_timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Timezone
                                </label>
                                <input type="text" id="edit_timezone" name="timezone" list="edit_timezones_list"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Type to search timezone..." autocomplete="off">
                                <datalist id="edit_timezones_list">
                                    <?php foreach ($allTimezones as $tz): ?>
                                        <option value="<?php echo htmlspecialchars($tz['display']); ?>" data-identifier="<?php echo htmlspecialchars($tz['identifier']); ?>">
                                            <?php echo htmlspecialchars($tz['display']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="edit_latitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Latitude
                                    </label>
                                    <input type="number" id="edit_latitude" name="latitude" step="0.0000001"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label for="edit_longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Longitude
                                    </label>
                                    <input type="number" id="edit_longitude" name="longitude" step="0.0000001"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            
                            <div>
                                <label for="edit_magnetic_variation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Magnetic Variation
                                </label>
                                <input type="text" id="edit_magnetic_variation" name="magnetic_variation"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="border-b border-gray-200 dark:border-gray-600 pb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Address Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="edit_address_line1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Address Line 1
                                </label>
                                <input type="text" id="edit_address_line1" name="address_line1" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="edit_address_line2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Address Line 2
                                </label>
                                <input type="text" id="edit_address_line2" name="address_line2" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label for="edit_city_suburb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        City/Suburb
                                    </label>
                                    <input type="text" id="edit_city_suburb" name="city_suburb" maxlength="100"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label for="edit_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        State
                                    </label>
                                    <input type="text" id="edit_state" name="state" maxlength="3"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label for="edit_postcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Postcode
                                    </label>
                                    <input type="text" id="edit_postcode" name="postcode" maxlength="10"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            
                                <div>
                                    <label for="edit_country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Country
                                    </label>
                                    <input type="text" id="edit_country" name="country" maxlength="100" list="edit_countries_list"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Type to search country..." autocomplete="off">
                                    <datalist id="edit_countries_list">
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo htmlspecialchars($country['name']); ?>">
                                                <?php echo htmlspecialchars($country['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Additional Information</h4>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="edit_owned_by_base" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Owned by Base
                                </label>
                                <select id="edit_owned_by_base" name="owned_by_base"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">- Select -</option>
                                    <?php foreach ($homeBases as $homeBase): ?>
                                        <option value="<?php echo htmlspecialchars($homeBase['location_name']); ?>">
                                            <?php echo htmlspecialchars($homeBase['location_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="edit_slot_coordination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Slot Coordination
                                </label>
                                <input type="text" id="edit_slot_coordination" name="slot_coordination" maxlength="50"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <!-- Site Properties -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Site Properties
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_ala" name="is_ala" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">ALA</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_fuel_depot" name="is_fuel_depot" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fuel Depot</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_base_office" name="is_base_office" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Base/Office</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_customs_immigration" name="is_customs_immigration" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Customs & Immigration</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_fixed_base_operators" name="is_fixed_base_operators" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fixed Base Operators</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_hls" name="is_hls" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">HLS</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="edit_is_maintenance_engineering" name="is_maintenance_engineering" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Maintenance/Engineering</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditStationModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Station
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400"></i>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Station</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to delete station <span id="deleteStationName" class="font-medium"></span>? This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex justify-center space-x-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <form id="deleteForm" method="POST" class="inline">
                        <input type="hidden" name="action" value="delete_station">
                        <input type="hidden" name="id" id="deleteStationId">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAddStationModal() {
            document.getElementById('addStationModal').classList.remove('hidden');
        }

        function closeAddStationModal() {
            document.getElementById('addStationModal').classList.add('hidden');
        }

        function openEditStationModal(id, name, iataCode, icaoCode, shortName, isBase, timezone, latitude, longitude, magneticVariation, addressLine1, addressLine2, citySuburb, state, postcode, country, ownedByBase, slotCoordination, locationType, isAla, isFuelDepot, isBaseOffice, isCustomsImmigration, isFixedBaseOperators, isHls, isMaintenanceEngineering) {
            document.getElementById('edit_station_id').value = id;
            document.getElementById('edit_station_name').value = name;
            document.getElementById('edit_iata_code').value = iataCode;
            document.getElementById('edit_icao_code').value = icaoCode;
            document.getElementById('edit_short_name').value = shortName;
            document.getElementById('edit_is_base').checked = isBase;
            document.getElementById('edit_timezone').value = timezone || '';
            document.getElementById('edit_latitude').value = latitude || '';
            document.getElementById('edit_longitude').value = longitude || '';
            document.getElementById('edit_magnetic_variation').value = magneticVariation || '';
            document.getElementById('edit_address_line1').value = addressLine1 || '';
            document.getElementById('edit_address_line2').value = addressLine2 || '';
            document.getElementById('edit_city_suburb').value = citySuburb || '';
            document.getElementById('edit_state').value = state || '';
            document.getElementById('edit_postcode').value = postcode || '';
            document.getElementById('edit_country').value = country || '';
            document.getElementById('edit_owned_by_base').value = ownedByBase || '';
            document.getElementById('edit_slot_coordination').value = slotCoordination || '';
            document.getElementById('edit_location_type').value = locationType || 'Domestic';
            document.getElementById('edit_is_ala').checked = isAla;
            document.getElementById('edit_is_fuel_depot').checked = isFuelDepot;
            document.getElementById('edit_is_base_office').checked = isBaseOffice;
            document.getElementById('edit_is_customs_immigration').checked = isCustomsImmigration;
            document.getElementById('edit_is_fixed_base_operators').checked = isFixedBaseOperators;
            document.getElementById('edit_is_hls').checked = isHls;
            document.getElementById('edit_is_maintenance_engineering').checked = isMaintenanceEngineering;
            document.getElementById('editStationModal').classList.remove('hidden');
        }

        function closeEditStationModal() {
            document.getElementById('editStationModal').classList.add('hidden');
        }

        function deleteStation(id, name) {
            document.getElementById('deleteStationId').value = id;
            document.getElementById('deleteStationName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function exportStations() {
            // Get current search parameters
            const searchParams = new URLSearchParams(window.location.search);
            
            // Create export URL with current filters
            const exportUrl = 'stations.php?' + searchParams.toString() + '&export=csv';
            
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = 'stations_export.csv';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addStationModal');
            const editModal = document.getElementById('editStationModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === addModal) {
                closeAddStationModal();
            } else if (event.target === editModal) {
                closeEditStationModal();
            } else if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>

