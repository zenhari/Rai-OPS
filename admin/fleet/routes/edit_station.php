<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/routes/stations.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get station ID from URL
$stationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($stationId <= 0) {
    header('Location: stations.php');
    exit;
}

// Get station data
$station = getStationById($stationId);

if (!$station) {
    header('Location: stations.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_station') {
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
    
    // Site properties checkboxes
    $isAla = isset($_POST['is_ala']) ? 1 : 0;
    $isFuelDepot = isset($_POST['is_fuel_depot']) ? 1 : 0;
    $isBaseOffice = isset($_POST['is_base_office']) ? 1 : 0;
    $isCustomsImmigration = isset($_POST['is_customs_immigration']) ? 1 : 0;
    $isFixedBaseOperators = isset($_POST['is_fixed_base_operators']) ? 1 : 0;
    $isHls = isset($_POST['is_hls']) ? 1 : 0;
    $isMaintenanceEngineering = isset($_POST['is_maintenance_engineering']) ? 1 : 0;
    
    if ($stationId > 0 && !empty($stationName) && !empty($iataCode)) {
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
        
        if (updateStation($stationId, $stationData)) {
            // Update station_info if ICAO code exists and station_info fields are provided
            if (!empty($icaoCode)) {
                $stationInfoData = [];
                
                // Collect station_info fields from POST
                if (isset($_POST['ala_call_frequency'])) {
                    $stationInfoData['ala_call_frequency'] = trim($_POST['ala_call_frequency'] ?? '');
                }
                if (isset($_POST['ala_call_sign'])) {
                    $stationInfoData['ala_call_sign'] = trim($_POST['ala_call_sign'] ?? '');
                }
                if (isset($_POST['ala_call_type'])) {
                    $stationInfoData['ala_call_type'] = trim($_POST['ala_call_type'] ?? '');
                }
                if (isset($_POST['ala_elevation'])) {
                    $stationInfoData['ala_elevation'] = trim($_POST['ala_elevation'] ?? '');
                }
                if (isset($_POST['ala_fuel_notes'])) {
                    $stationInfoData['ala_fuel_notes'] = trim($_POST['ala_fuel_notes'] ?? '');
                }
                if (isset($_POST['ala_navaids'])) {
                    $stationInfoData['ala_navaids'] = trim($_POST['ala_navaids'] ?? '');
                }
                if (isset($_POST['ala_operating_hours'])) {
                    $stationInfoData['ala_operating_hours'] = trim($_POST['ala_operating_hours'] ?? '');
                }
                if (isset($_POST['ala_night_operations'])) {
                    $stationInfoData['ala_night_operations'] = isset($_POST['ala_night_operations']) ? 1 : 0;
                }
                if (isset($_POST['ala_remarks_restrictions'])) {
                    $stationInfoData['ala_remarks_restrictions'] = trim($_POST['ala_remarks_restrictions'] ?? '');
                }
                if (isset($_POST['fuel_all_type'])) {
                    $stationInfoData['fuel_all_type'] = trim($_POST['fuel_all_type'] ?? '');
                }
                if (isset($_POST['fuel_measurement'])) {
                    $stationInfoData['fuel_measurement'] = trim($_POST['fuel_measurement'] ?? '');
                }
                
                // Update station_info if there's any data
                if (!empty($stationInfoData)) {
                    updateStationInfoByALAIdentifier($icaoCode, $stationInfoData);
                }
            }
            
            $message = 'Station updated successfully.';
            // Refresh station data
            $station = getStationById($stationId);
            // Refresh station info
            if (!empty($station['icao_code'])) {
                $stationInfo = getStationInfoByALAIdentifier($station['icao_code']);
            }
        } else {
            $error = 'Failed to update station.';
        }
    } else {
        $error = 'Invalid station data.';
    }
}

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

// If ICAO code is provided in URL, use it to search station_info
if (isset($_GET['icao']) && !empty($_GET['icao'])) {
    $icaoFromUrl = trim($_GET['icao']);
    if (!empty($icaoFromUrl)) {
        $station['icao_code'] = $icaoFromUrl;
    }
}

// Get station info from station_info table if ICAO code exists
$stationInfo = null;
if (!empty($station['icao_code'])) {
    $stationInfo = getStationInfoByALAIdentifier($station['icao_code']);
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Station - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Station</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1"><?php echo htmlspecialchars($station['station_name']); ?></p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="stations.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Stations
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

                <!-- Edit Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_station">
                        <input type="hidden" name="id" value="<?php echo $stationId; ?>">
                        
                        <!-- Basic Information -->
                        <div class="border-b border-gray-200 dark:border-gray-600 pb-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h4>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="station_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Station Name *
                                    </label>
                                    <input type="text" id="station_name" name="station_name" required
                                           value="<?php echo htmlspecialchars($station['station_name']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., Tehran Imam Khomeini International">
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="iata_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            IATA Code *
                                        </label>
                                        <input type="text" id="iata_code" name="iata_code" required maxlength="3"
                                               value="<?php echo htmlspecialchars($station['iata_code']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="e.g., IKA">
                                    </div>
                                    
                                    <div>
                                        <label for="icao_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ICAO Code
                                            <button type="button" id="search_station_info_btn" onclick="searchStationInfo()" 
                                                    class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded-md text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                                                <i class="fas fa-search mr-1"></i>
                                                Search Station Info
                                            </button>
                                        </label>
                                        <input type="text" id="icao_code" name="icao_code" maxlength="4"
                                               value="<?php echo htmlspecialchars($station['icao_code'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="e.g., OIIE"
                                               onchange="searchStationInfo()">
                                        <div id="station_info_loading" class="hidden mt-2 text-sm text-blue-600 dark:text-blue-400">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>
                                            Searching...
                                        </div>
                                        <div id="station_info_found" class="hidden mt-2 text-sm text-green-600 dark:text-green-400">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Station info found! Data loaded below.
                                        </div>
                                        <div id="station_info_not_found" class="hidden mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            No station info found for this ICAO code.
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Short Name
                                    </label>
                                    <input type="text" id="short_name" name="short_name" maxlength="10"
                                           value="<?php echo htmlspecialchars($station['short_name'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., IKA">
                                </div>
                                
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is_base" name="is_base" value="1"
                                               <?php echo $station['is_base'] ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Base Station
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Location Information -->
                        <div class="border-b border-gray-200 dark:border-gray-600 pb-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Location Information</h4>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Timezone
                                    </label>
                                    <input type="text" id="timezone" name="timezone" list="timezones_list"
                                           value="<?php echo htmlspecialchars($station['timezone'] ?? ''); ?>"
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
                                        <input type="text" id="latitude" name="latitude" pattern="-?[0-9]+(\.[0-9]+)?"
                                               value="<?php echo $station['latitude'] !== null ? htmlspecialchars($station['latitude']) : ''; ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="e.g., 35.6892 or -35.6892"
                                               oninput="validateCoordinate(this)">
                                    </div>
                                    
                                    <div>
                                        <label for="longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Longitude
                                        </label>
                                        <input type="text" id="longitude" name="longitude" pattern="-?[0-9]+(\.[0-9]+)?"
                                               value="<?php echo $station['longitude'] !== null ? htmlspecialchars($station['longitude']) : ''; ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="e.g., 51.3890 or -51.3890"
                                               oninput="validateCoordinate(this)">
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="magnetic_variation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Magnetic Variation
                                    </label>
                                    <input type="text" id="magnetic_variation" name="magnetic_variation"
                                           value="<?php echo htmlspecialchars($station['magnetic_variation'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 3°E">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address Information -->
                        <div class="border-b border-gray-200 dark:border-gray-600 pb-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Address Information</h4>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="address_line1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 1
                                    </label>
                                    <input type="text" id="address_line1" name="address_line1" maxlength="100"
                                           value="<?php echo htmlspecialchars($station['address_line1'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Street address">
                                </div>
                                
                                <div>
                                    <label for="address_line2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 2
                                    </label>
                                    <input type="text" id="address_line2" name="address_line2" maxlength="100"
                                           value="<?php echo htmlspecialchars($station['address_line2'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Apartment, suite, etc.">
                                </div>
                                
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label for="city_suburb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            City/Suburb
                                        </label>
                                        <input type="text" id="city_suburb" name="city_suburb" maxlength="100"
                                               value="<?php echo htmlspecialchars($station['city_suburb'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="City">
                                    </div>
                                    
                                    <div>
                                        <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            State
                                        </label>
                                        <input type="text" id="state" name="state" maxlength="3"
                                               value="<?php echo htmlspecialchars($station['state'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="State">
                                    </div>
                                    
                                    <div>
                                        <label for="postcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Postcode
                                        </label>
                                        <input type="text" id="postcode" name="postcode" maxlength="10"
                                               value="<?php echo htmlspecialchars($station['postcode'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="12345">
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Country
                                    </label>
                                    <input type="text" id="country" name="country" maxlength="100" list="countries_list"
                                           value="<?php echo htmlspecialchars($station['country'] ?? ''); ?>"
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
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Additional Information</h4>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="owned_by_base" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Owned by Base
                                    </label>
                                    <select id="owned_by_base" name="owned_by_base"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">- Select -</option>
                                        <?php foreach ($homeBases as $homeBase): ?>
                                            <option value="<?php echo htmlspecialchars($homeBase['location_name']); ?>"
                                                    <?php echo ($station['owned_by_base'] ?? '') === $homeBase['location_name'] ? 'selected' : ''; ?>>
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
                                           value="<?php echo htmlspecialchars($station['slot_coordination'] ?? ''); ?>"
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
                                                   <?php echo $station['is_ala'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">ALA</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="is_fuel_depot" name="is_fuel_depot" value="1"
                                                   <?php echo $station['is_fuel_depot'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fuel Depot</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="is_base_office" name="is_base_office" value="1"
                                                   <?php echo $station['is_base_office'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Base/Office</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="is_customs_immigration" name="is_customs_immigration" value="1"
                                                   <?php echo $station['is_customs_immigration'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Customs & Immigration</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="is_fixed_base_operators" name="is_fixed_base_operators" value="1"
                                                   <?php echo $station['is_fixed_base_operators'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fixed Base Operators</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="is_hls" name="is_hls" value="1"
                                                   <?php echo $station['is_hls'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">HLS</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="is_maintenance_engineering" name="is_maintenance_engineering" value="1"
                                                   <?php echo $station['is_maintenance_engineering'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Maintenance/Engineering</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Station Info Data (from station_info table) -->
                        <?php if ($stationInfo): ?>
                        <div class="border-t border-gray-200 dark:border-gray-600 pt-6 mt-6" id="station_info_section">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-info-circle mr-2 text-blue-600 dark:text-blue-400"></i>
                                Station Info (from station_info table)
                            </h4>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                                <p class="text-sm text-blue-800 dark:text-blue-200">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    The following data was found in the station_info table for ICAO Code: <strong><?php echo htmlspecialchars($station['icao_code'] ?? ''); ?></strong>
                                </p>
                            </div>
                            
                            <!-- ALA Information -->
                            <div class="border-b border-gray-200 dark:border-gray-600 pb-6 mb-6">
                                <h5 class="text-md font-medium text-gray-900 dark:text-white mb-4">ALA (Airport Landing Area) Information</h5>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Call Frequency
                                        </label>
                                        <input type="text" name="ala_call_frequency"
                                               value="<?php echo htmlspecialchars($stationInfo['ala_call_frequency'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Call Sign
                                        </label>
                                        <input type="text" name="ala_call_sign"
                                               value="<?php echo htmlspecialchars($stationInfo['ala_call_sign'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Call Type
                                        </label>
                                        <input type="text" name="ala_call_type"
                                               value="<?php echo htmlspecialchars($stationInfo['ala_call_type'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Elevation
                                        </label>
                                        <input type="text" name="ala_elevation"
                                               value="<?php echo htmlspecialchars($stationInfo['ala_elevation'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Fuel Notes
                                        </label>
                                        <textarea name="ala_fuel_notes" rows="3"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($stationInfo['ala_fuel_notes'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Navaids
                                        </label>
                                        <textarea name="ala_navaids" rows="2"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($stationInfo['ala_navaids'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Operating Hours
                                        </label>
                                        <input type="text" name="ala_operating_hours"
                                               value="<?php echo htmlspecialchars($stationInfo['ala_operating_hours'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ala_night_operations" value="1"
                                                   <?php echo ($stationInfo['ala_night_operations'] ?? 0) ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                ALA Night Operations
                                            </span>
                                        </label>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Remarks/Restrictions
                                        </label>
                                        <textarea name="ala_remarks_restrictions" rows="2"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($stationInfo['ala_remarks_restrictions'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fuel Information -->
                            <?php if (!empty($stationInfo['ala_fuel_notes']) || !empty($stationInfo['fuel_all_type'])): ?>
                            <div class="border-b border-gray-200 dark:border-gray-600 pb-6 mb-6">
                                <h5 class="text-md font-medium text-gray-900 dark:text-white mb-4">Fuel Information</h5>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Fuel Type
                                        </label>
                                        <input type="text" name="fuel_all_type"
                                               value="<?php echo htmlspecialchars($stationInfo['fuel_all_type'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Fuel Measurement
                                        </label>
                                        <input type="text" name="fuel_measurement"
                                               value="<?php echo htmlspecialchars($stationInfo['fuel_measurement'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-600">
                            <a href="stations.php"
                               class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Update Station
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function searchStationInfo() {
            const icaoCode = document.getElementById('icao_code').value.trim();
            const loadingDiv = document.getElementById('station_info_loading');
            const foundDiv = document.getElementById('station_info_found');
            const notFoundDiv = document.getElementById('station_info_not_found');
            
            // Hide all messages
            loadingDiv.classList.add('hidden');
            foundDiv.classList.add('hidden');
            notFoundDiv.classList.add('hidden');
            
            if (!icaoCode) {
                return;
            }
            
            // Show loading
            loadingDiv.classList.remove('hidden');
            
            // Make AJAX request
            fetch('api/get_station_info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ icao_code: icaoCode })
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.classList.add('hidden');
                
                if (data.success && data.data) {
                    foundDiv.classList.remove('hidden');
                    // Populate form fields with station info data
                    populateStationInfoFields(data.data);
                    // Show station info section
                    showStationInfoSection(data.data);
                } else {
                    notFoundDiv.classList.remove('hidden');
                    // Hide station info section if it exists
                    const stationInfoSection = document.getElementById('station_info_section');
                    if (stationInfoSection) {
                        stationInfoSection.classList.add('hidden');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingDiv.classList.add('hidden');
                notFoundDiv.classList.remove('hidden');
            });
        }
        
        function populateStationInfoFields(data) {
            // Populate ALA fields
            if (data.ala_call_frequency !== undefined) {
                const field = document.querySelector('input[name="ala_call_frequency"]');
                if (field) field.value = data.ala_call_frequency || '';
            }
            if (data.ala_call_sign !== undefined) {
                const field = document.querySelector('input[name="ala_call_sign"]');
                if (field) field.value = data.ala_call_sign || '';
            }
            if (data.ala_call_type !== undefined) {
                const field = document.querySelector('input[name="ala_call_type"]');
                if (field) field.value = data.ala_call_type || '';
            }
            if (data.ala_elevation !== undefined) {
                const field = document.querySelector('input[name="ala_elevation"]');
                if (field) field.value = data.ala_elevation || '';
            }
            if (data.ala_fuel_notes !== undefined) {
                const field = document.querySelector('textarea[name="ala_fuel_notes"]');
                if (field) field.value = data.ala_fuel_notes || '';
            }
            if (data.ala_navaids !== undefined) {
                const field = document.querySelector('textarea[name="ala_navaids"]');
                if (field) field.value = data.ala_navaids || '';
            }
            if (data.ala_operating_hours !== undefined) {
                const field = document.querySelector('input[name="ala_operating_hours"]');
                if (field) field.value = data.ala_operating_hours || '';
            }
            if (data.ala_night_operations !== undefined) {
                const field = document.querySelector('input[name="ala_night_operations"]');
                if (field) field.checked = data.ala_night_operations == 1;
            }
            if (data.ala_remarks_restrictions !== undefined) {
                const field = document.querySelector('textarea[name="ala_remarks_restrictions"]');
                if (field) field.value = data.ala_remarks_restrictions || '';
            }
            
            // Populate Fuel fields
            if (data.fuel_all_type !== undefined) {
                const field = document.querySelector('input[name="fuel_all_type"]');
                if (field) field.value = data.fuel_all_type || '';
            }
            if (data.fuel_measurement !== undefined) {
                const field = document.querySelector('input[name="fuel_measurement"]');
                if (field) field.value = data.fuel_measurement || '';
            }
        }
        
        function showStationInfoSection(data) {
            let stationInfoSection = document.getElementById('station_info_section');
            
            // If section doesn't exist, create it dynamically
            if (!stationInfoSection) {
                // Find the form and insert before submit buttons
                const form = document.querySelector('form');
                const submitButtons = form.querySelector('.flex.justify-end.space-x-3.pt-6');
                
                if (submitButtons) {
                    // Create station info section HTML
                    const sectionHTML = `
                        <div class="border-t border-gray-200 dark:border-gray-600 pt-6 mt-6" id="station_info_section">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-info-circle mr-2 text-blue-600 dark:text-blue-400"></i>
                                Station Info (from station_info table)
                            </h4>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                                <p class="text-sm text-blue-800 dark:text-blue-200">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    The following data was found in the station_info table for ICAO Code: <strong id="station_info_icao_display"></strong>
                                </p>
                            </div>
                            
                            <!-- ALA Information -->
                            <div class="border-b border-gray-200 dark:border-gray-600 pb-6 mb-6">
                                <h5 class="text-md font-medium text-gray-900 dark:text-white mb-4">ALA (Airport Landing Area) Information</h5>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Call Frequency
                                        </label>
                                        <input type="text" name="ala_call_frequency"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Call Sign
                                        </label>
                                        <input type="text" name="ala_call_sign"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Call Type
                                        </label>
                                        <input type="text" name="ala_call_type"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Elevation
                                        </label>
                                        <input type="text" name="ala_elevation"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Fuel Notes
                                        </label>
                                        <textarea name="ala_fuel_notes" rows="3"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Navaids
                                        </label>
                                        <textarea name="ala_navaids" rows="2"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Operating Hours
                                        </label>
                                        <input type="text" name="ala_operating_hours"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ala_night_operations" value="1"
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                ALA Night Operations
                                            </span>
                                        </label>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            ALA Remarks/Restrictions
                                        </label>
                                        <textarea name="ala_remarks_restrictions" rows="2"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fuel Information -->
                            <div class="border-b border-gray-200 dark:border-gray-600 pb-6 mb-6">
                                <h5 class="text-md font-medium text-gray-900 dark:text-white mb-4">Fuel Information</h5>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Fuel Type
                                        </label>
                                        <input type="text" name="fuel_all_type"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Fuel Measurement
                                        </label>
                                        <input type="text" name="fuel_measurement"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Insert before submit buttons
                    submitButtons.insertAdjacentHTML('beforebegin', sectionHTML);
                    stationInfoSection = document.getElementById('station_info_section');
                }
            }
            
            // Show the section
            if (stationInfoSection) {
                stationInfoSection.classList.remove('hidden');
                // Update ICAO code display
                const icaoDisplay = document.getElementById('station_info_icao_display');
                if (icaoDisplay) {
                    icaoDisplay.textContent = document.getElementById('icao_code').value;
                }
            }
        }
        
        // Auto-search when page loads if ICAO code is present in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('icao')) {
                const icaoCode = urlParams.get('icao');
                document.getElementById('icao_code').value = icaoCode;
                // Auto-search
                setTimeout(() => {
                    searchStationInfo();
                }, 500);
            }
        });
        
        // Validate coordinate input (latitude/longitude)
        function validateCoordinate(input) {
            // Remove any non-numeric characters except minus sign and decimal point
            let value = input.value.replace(/[^0-9.\-]/g, '');
            
            // Ensure only one decimal point
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Ensure minus sign is only at the beginning
            if (value.includes('-') && value.indexOf('-') !== 0) {
                value = value.replace(/-/g, '');
                if (value && parseFloat(value) < 0) {
                    value = '-' + value.replace(/-/g, '');
                }
            }
            
            // Update input value
            if (input.value !== value) {
                input.value = value;
            }
        }
        
        // Handle timezone selection from datalist
        document.getElementById('timezone').addEventListener('input', function(e) {
            const input = e.target;
            const datalist = document.getElementById('timezones_list');
            const options = datalist.querySelectorAll('option');
            
            // Find matching option
            for (let option of options) {
                if (option.value === input.value) {
                    // Option found, value is valid
                    input.setCustomValidity('');
                    return;
                }
            }
            
            // If value doesn't match any option, still allow it (user might type custom value)
            input.setCustomValidity('');
        });
    </script>
</body>
</html>

