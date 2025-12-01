<?php
require_once '../config.php';

// Handle logout
if (isset($_GET['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: /transport/login.php');
    exit();
}

// Check if user is logged in and has transport role (role_id == 18)
if (!isLoggedIn()) {
    header('Location: /transport/login.php');
    exit();
}

$current_user = getCurrentUser();
if (!isset($current_user['role_id']) || $current_user['role_id'] != 18) {
    header('Location: /transport/login.php');
    exit();
}

// Ensure trip driver assignments table exists
ensureTripDriverAssignmentsTableExists();

// Get search date parameter
$searchDate = $_GET['date'] ?? '';

// Get assigned trips for current user (driver)
$assignedTrips = getDriverAssignmentsByDriverId($current_user['id'], $searchDate);

// Get flights with crew information (filtered by date if provided)
$db = getDBConnection();
$sql = "
    SELECT 
        f.FlightID,
        f.FlightNo,
        f.TaskName,
        f.Route,
        f.FltDate,
        f.TaskStart,
        f.TaskEnd,
        f.adult,
        f.child,
        f.infant,
        f.minutes_1,
        f.minutes_2,
        f.minutes_3,
        f.minutes_4,
        f.minutes_5,
        f.delay_diversion_codes,
        f.delay_diversion_codes_2,
        f.delay_diversion_codes_3,
        f.delay_diversion_codes_4,
        f.delay_diversion_codes_5,
        f.Crew1,
        f.Crew2,
        f.Crew3,
        f.Crew4,
        f.Crew5,
        f.Crew6,
        f.Crew7,
        f.Crew8,
        f.Crew9,
        f.Crew10,
        f.Crew1_role,
        f.Crew2_role,
        f.Crew3_role,
        f.Crew4_role,
        f.Crew5_role,
        f.Crew6_role,
        f.Crew7_role,
        f.Crew8_role,
        f.Crew9_role,
        f.Crew10_role
    FROM flights f 
";

// Add date filter if provided
if (!empty($searchDate)) {
    $sql .= " WHERE DATE(f.FltDate) = :searchDate";
}

$sql .= " ORDER BY f.FltDate DESC, f.TaskStart DESC";

$stmt = $db->prepare($sql);

if (!empty($searchDate)) {
    $stmt->bindParam(':searchDate', $searchDate);
}

$stmt->execute();
$allFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter flights to show only delayed flights (flights with delays)
$flights = [];
foreach ($allFlights as $flight) {
    $hasDelay = false;
    $totalDelayMinutes = 0;
    
    // Check if flight has any delay (minutes_1 to minutes_5)
    for ($i = 1; $i <= 5; $i++) {
        $minutes = $flight["minutes_$i"];
        if (!empty($minutes) && is_numeric($minutes) && intval($minutes) > 0) {
            $totalDelayMinutes += intval($minutes);
            $hasDelay = true;
        }
    }
    
    // Only include flights with delays
    if ($hasDelay && $totalDelayMinutes > 0) {
        $flights[] = $flight;
    }
}

// Function to get user details by ID
function getUserDetails($userId) {
    if (empty($userId) || !is_numeric($userId)) {
        return null;
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, first_name, last_name, mobile, phone, address_line_1, address_line_2, latitude, longitude, picture FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to format duration in seconds to English format (hours and minutes)
function formatDurationEnglish($seconds) {
    if (!is_numeric($seconds) || $seconds < 0) {
        return '0 min';
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($minutes > 0 || $hours == 0) {
        $parts[] = $minutes . 'm';
    }
    
    return implode(' ', $parts);
}

// Function to format distance in meters to English format (kilometers)
function formatDistanceEnglish($meters) {
    if (!is_numeric($meters) || $meters < 0) {
        return '0 km';
    }
    
    $kilometers = $meters / 1000;
    
    // If less than 1 km, show in meters
    if ($kilometers < 1) {
        return round($meters) . ' m';
    }
    
    // Show kilometers with appropriate decimal places
    if ($kilometers < 10) {
        return number_format($kilometers, 1) . ' km';
    } else {
        return round($kilometers) . ' km';
    }
}

// Function to get delay description from delay.json
function getDelayDescription($code) {
    if (empty($code)) return '';
    
    $delayFile = '../admin/flights/delay.json';
    if (file_exists($delayFile)) {
        $delays = json_decode(file_get_contents($delayFile), true);
        foreach ($delays as $delay) {
            if ($delay['code'] === $code) {
                return $delay['description'];
            }
        }
    }
    return $code;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Data - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com/3.4.0"></script>
    
    <!-- Leaflet for Maps (using CDN since Neshan Maps SDK requires special setup) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'roboto': ['Roboto', 'sans-serif'],
                    }
                }
            }
        }
        
        // Suppress Tailwind CDN warning
        console.clear = function() {};
    </script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <i class="fas fa-users mr-2"></i>Transport Data
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Flight delays and crew information
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="showAddresses()" 
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        Addresses
                    </button>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                    </span>
                    <a href="?logout=1" 
                       class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- My Assigned Trips Section -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        <i class="fas fa-route mr-2"></i>My Assigned Trips
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Trips assigned to you as a driver
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="date" id="tripSearchDate" value="<?php echo htmlspecialchars($searchDate); ?>" 
                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                           onchange="searchTrips()">
        <?php if (!empty($searchDate)): ?>
                        <button onclick="clearTripSearch()" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i>
                            Clear Filter
                        </button>
                    <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
            
    <!-- Assigned Trips Content -->
    <div class="p-6">
        <?php if (empty($assignedTrips)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center">
                        <i class="fas fa-route text-6xl text-gray-400 mb-6"></i>
                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">
                            No Assigned Trips
                        </h3>
                        <p class="text-lg text-gray-600 dark:text-gray-400 mb-6 max-w-md">
                            <?php if (!empty($searchDate)): ?>
                                You have no trips assigned for <?php echo date('M j, Y', strtotime($searchDate)); ?>.
                            <?php else: ?>
                                You currently have no trips assigned. Trips will appear here once they are assigned to you.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-8">
                <?php foreach ($assignedTrips as $trip): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-8">
                        <!-- Header Section - Minimal -->
                        <div class="mb-6 pb-6 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">
                                        <?php echo htmlspecialchars($trip['FlightNo'] ?? $trip['TaskName'] ?? 'N/A'); ?>
                                    </h3>
                                    <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        <span><?php echo htmlspecialchars($trip['Route'] ?? 'N/A'); ?></span>
                                        <span>•</span>
                                        <span><?php echo $trip['FltDate'] ? date('M j, Y', strtotime($trip['FltDate'])) : date('M j, Y', strtotime($trip['assignment_date'])); ?></span>
                                    </div>
                                </div>
                                <!-- Pickup & Dropoff Times - Prominent Design -->
                                <div class="flex flex-col gap-3">
                                    <?php if ($trip['pickup_time']): ?>
                                        <div class="inline-flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/30 rounded-xl border-2 border-green-200 dark:border-green-700 shadow-sm">
                                            <div class="h-10 w-10 bg-green-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-arrow-up text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-green-700 dark:text-green-300 uppercase tracking-wide">Pickup</p>
                                                <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                                    <?php echo date('H:i', strtotime($trip['pickup_time'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($trip['dropoff_time']): ?>
                                        <div class="inline-flex items-center gap-3 px-4 py-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl border-2 border-blue-200 dark:border-blue-700 shadow-sm">
                                            <div class="h-10 w-10 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-arrow-down text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-blue-700 dark:text-blue-300 uppercase tracking-wide">Dropoff</p>
                                                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                                    <?php echo date('H:i', strtotime($trip['dropoff_time'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Flight Times - Minimal -->
                        <?php if ($trip['TaskStart'] || $trip['TaskEnd']): ?>
                            <div class="mb-6 inline-flex gap-6 text-sm">
                                <?php if ($trip['TaskStart']): ?>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Start</span>
                                        <p class="font-medium text-gray-900 dark:text-white mt-0.5">
                                            <?php echo date('H:i', strtotime($trip['TaskStart'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($trip['TaskEnd']): ?>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">End</span>
                                        <p class="font-medium text-gray-900 dark:text-white mt-0.5">
                                            <?php echo date('H:i', strtotime($trip['TaskEnd'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Crew Member Info - Minimal 3 Cards -->
                        <?php 
                        // Get crew user details including latitude/longitude
                        $crewUser = getUserDetails($trip['crew_user_id'] ?? null);
                        $crewLat = $crewUser['latitude'] ?? null;
                        $crewLng = $crewUser['longitude'] ?? null;
                        $hasCrewCoordinates = $crewLat && $crewLng && is_numeric($crewLat) && is_numeric($crewLng) && 
                                               (float)$crewLat >= -90 && (float)$crewLat <= 90 && 
                                               (float)$crewLng >= -180 && (float)$crewLng <= 180;
                        
                        // Get crew user picture
                        $crewPicture = $crewUser['picture'] ?? null;
                        $crewPicturePath = $crewPicture ? '../uploads/profile/' . basename($crewPicture) : null;
                        ?>
                        <div class="mb-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Card 1: Crew Member -->
                            <div class="p-5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="h-14 w-14 rounded-full overflow-hidden flex-shrink-0 ring-2 ring-gray-200 dark:ring-gray-600">
                                        <?php if ($crewPicturePath): ?>
                                            <img src="<?php echo htmlspecialchars($crewPicturePath); ?>" 
                                                 alt="<?php echo htmlspecialchars(($trip['crew_first_name'] ?? '') . ' ' . ($trip['crew_last_name'] ?? '')); ?>"
                                                 class="h-full w-full object-cover"
                                                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'56\' height=\'56\'%3E%3Ccircle cx=\'28\' cy=\'28\' r=\'28\' fill=\'%239ca3af\'/%3E%3Cpath d=\'M28 14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 16c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z\' fill=\'white\'/%3E%3C/svg%3E';">
                                        <?php else: ?>
                                            <div class="h-full w-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-500 dark:text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">
                                            <?php echo htmlspecialchars(($trip['crew_first_name'] ?? '') . ' ' . ($trip['crew_last_name'] ?? '')); ?>
                                        </p>
                                        <?php if ($trip['crew_position']): ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                                <?php echo htmlspecialchars($trip['crew_position']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card 2: Contact -->
                            <div class="p-5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Contact</p>
                                <?php if ($trip['crew_mobile'] || $trip['crew_phone']): ?>
                                    <a href="tel:<?php echo htmlspecialchars($trip['crew_mobile'] ?? $trip['crew_phone']); ?>" 
                                       class="text-base font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <?php echo htmlspecialchars($trip['crew_mobile'] ?? $trip['crew_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">—</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Card 3: Location -->
                            <div class="p-5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Location</p>
                                <?php if ($hasCrewCoordinates): ?>
                                    <p class="text-sm font-mono text-gray-700 dark:text-gray-300">
                                        <?php echo number_format((float)$crewLat, 6); ?>, <?php echo number_format((float)$crewLng, 6); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">—</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Pickup & Dropoff Locations - Minimal -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <?php if ($trip['pickup_location']): ?>
                                <?php 
                                // Get crew user coordinates for pickup location
                                $pickupCrewUser = getUserDetails($trip['crew_user_id'] ?? null);
                                $pickupLat = $pickupCrewUser['latitude'] ?? null;
                                $pickupLng = $pickupCrewUser['longitude'] ?? null;
                                $hasPickupCoordinates = $pickupLat && $pickupLng && is_numeric($pickupLat) && is_numeric($pickupLng) && 
                                                       (float)$pickupLat >= -90 && (float)$pickupLat <= 90 && 
                                                       (float)$pickupLng >= -180 && (float)$pickupLng <= 180;
                                
                                // Get dropoff coordinates for trip duration calculation
                                $dropoffStationForDuration = null;
                                $dropoffLatForDuration = null;
                                $dropoffLngForDuration = null;
                                if (!empty($trip['dropoff_location'])) {
                                    $dropoffStationForDuration = getStationByIATACode(trim($trip['dropoff_location']));
                                    if ($dropoffStationForDuration) {
                                        $dropoffLatForDuration = $dropoffStationForDuration['latitude'] ?? null;
                                        $dropoffLngForDuration = $dropoffStationForDuration['longitude'] ?? null;
                                    }
                                }
                                
                                // Calculate trip duration if both coordinates are available
                                $tripDuration = null;
                                if ($hasPickupCoordinates && $dropoffLatForDuration && $dropoffLngForDuration && 
                                    is_numeric($dropoffLatForDuration) && is_numeric($dropoffLngForDuration) &&
                                    (float)$dropoffLatForDuration >= -90 && (float)$dropoffLatForDuration <= 90 &&
                                    (float)$dropoffLngForDuration >= -180 && (float)$dropoffLngForDuration <= 180) {
                                    $tripDuration = calculateTripDuration($pickupLat, $pickupLng, $dropoffLatForDuration, $dropoffLngForDuration, 'car', true);
                                }
                                ?>
                                <div class="p-5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white mb-3">Pickup</p>
                                    
                                    <?php if ($hasPickupCoordinates): ?>
                                        <div class="mb-4 rounded-lg overflow-hidden">
                                            <iframe 
                                                src="https://www.google.com/maps?q=<?php echo htmlspecialchars($pickupLat); ?>,<?php echo htmlspecialchars($pickupLng); ?>&output=embed&z=15" 
                                                width="100%" 
                                                height="180" 
                                                style="border:0;" 
                                                allowfullscreen="" 
                                                loading="lazy" 
                                                referrerpolicy="no-referrer-when-downgrade">
                                            </iframe>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                                        <?php echo htmlspecialchars($trip['pickup_location']); ?>
                                    </p>
                                    
                                    <?php if ($hasPickupCoordinates): ?>
                                        <div class="flex gap-2">
                                            <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($pickupLat); ?>,<?php echo htmlspecialchars($pickupLng); ?>" 
                                               target="_blank"
                                               class="flex-1 text-center px-3 py-2 text-xs font-medium rounded-lg text-white bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 transition-colors">
                                                Google Maps
                                            </a>
                                            <a href="https://neshan.org/maps/@<?php echo htmlspecialchars($pickupLat); ?>,<?php echo htmlspecialchars($pickupLng); ?>,15" 
                                               target="_blank"
                                               class="flex-1 text-center px-3 py-2 text-xs font-medium rounded-lg text-white bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 transition-colors">
                                                Neshan
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($trip['dropoff_location']): ?>
                                <?php 
                                // Try to get station by dropoff_location (might be iata_code)
                                $dropoffStation = null;
                                $dropoffLat = null;
                                $dropoffLng = null;
                                
                                if (!empty($trip['dropoff_location'])) {
                                    // Check if dropoff_location is an IATA code
                                    $dropoffStation = getStationByIATACode(trim($trip['dropoff_location']));
                                    if ($dropoffStation) {
                                        $dropoffLat = $dropoffStation['latitude'] ?? null;
                                        $dropoffLng = $dropoffStation['longitude'] ?? null;
                                    }
                                }
                                
                                $hasDropoffCoordinates = $dropoffLat && $dropoffLng && is_numeric($dropoffLat) && is_numeric($dropoffLng) && 
                                                         (float)$dropoffLat >= -90 && (float)$dropoffLat <= 90 && 
                                                         (float)$dropoffLng >= -180 && (float)$dropoffLng <= 180;
                                ?>
                                <div class="p-5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white mb-3">Dropoff</p>
                                    
                                    <?php if ($hasDropoffCoordinates): ?>
                                        <div class="mb-4 rounded-lg overflow-hidden">
                                            <iframe 
                                                src="https://www.google.com/maps?q=<?php echo htmlspecialchars($dropoffLat); ?>,<?php echo htmlspecialchars($dropoffLng); ?>&output=embed&z=15" 
                                                width="100%" 
                                                height="180" 
                                                style="border:0;" 
                                                allowfullscreen="" 
                                                loading="lazy" 
                                                referrerpolicy="no-referrer-when-downgrade">
                                            </iframe>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-1">
                                        <?php echo htmlspecialchars($trip['dropoff_location']); ?>
                                    </p>
                                    <?php if ($dropoffStation): ?>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            <?php echo htmlspecialchars($dropoffStation['station_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($hasDropoffCoordinates): ?>
                                        <div class="flex gap-2">
                                            <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($dropoffLat); ?>,<?php echo htmlspecialchars($dropoffLng); ?>" 
                                               target="_blank"
                                               class="flex-1 text-center px-3 py-2 text-xs font-medium rounded-lg text-white bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 transition-colors">
                                                Google Maps
                                            </a>
                                            <a href="https://neshan.org/maps/@<?php echo htmlspecialchars($dropoffLat); ?>,<?php echo htmlspecialchars($dropoffLng); ?>,15" 
                                               target="_blank"
                                               class="flex-1 text-center px-3 py-2 text-xs font-medium rounded-lg text-white bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 transition-colors">
                                                Neshan
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Trip Duration Estimate -->
                        <?php if (isset($tripDuration) && $tripDuration && $tripDuration['duration_text']): ?>
                            <div class="mb-6 p-5 bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-900 dark:to-blue-900 rounded-xl border border-green-200 dark:border-green-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-green-600 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                            <i class="fas fa-route text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">
                                                Estimated Trip Duration
                                            </h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                Calculated with real-time traffic data
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                            <?php 
                                            // Format duration in English
                                            $durationSeconds = $tripDuration['duration'] ?? 0;
                                            echo htmlspecialchars(formatDurationEnglish($durationSeconds)); 
                                            ?>
                                        </p>
                                        <?php if (isset($tripDuration['distance'])): ?>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <i class="fas fa-arrows-alt-h mr-1"></i>
                                                <?php 
                                                // Format distance in English
                                                $distanceMeters = $tripDuration['distance'] ?? 0;
                                                echo htmlspecialchars(formatDistanceEnglish($distanceMeters)); 
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Notes - Minimal -->
                        <?php if ($trip['notes']): ?>
                            <div class="mb-6 p-5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                <p class="text-sm font-medium text-gray-900 dark:text-white mb-2">Notes</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($trip['notes'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Aircraft Info - Minimal -->
                        <?php if ($trip['Rego'] || $trip['ACType']): ?>
                            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <?php if ($trip['Rego']): ?>
                                        <span><?php echo htmlspecialchars($trip['Rego']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($trip['ACType']): ?>
                                        <span><?php echo htmlspecialchars($trip['ACType']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                </div>
                <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>


    <!-- Flight Details Modal -->
    <div id="flightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Flight Details</h3>
                    <button onclick="closeFlightModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="flightDetails" class="space-y-4">
                    <!-- Flight details will be populated here -->
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeFlightModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Addresses Modal -->
    <div id="addressesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fas fa-map-marker-alt mr-2"></i>Crew Addresses
                    </h3>
                    <button onclick="closeAddressesModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="addressesContent" class="space-y-4">
                    <!-- Addresses will be populated here -->
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeAddressesModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fas fa-map mr-2"></i><span id="mapModalTitle">Map</span>
                    </h3>
                    <button onclick="closeMapModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="mapContainer" style="height: 500px; width: 100%; border-radius: 8px; overflow: hidden;">
                    <!-- Map will be rendered here -->
                </div>
                
                <div class="mt-4 flex justify-between items-center">
                    <div id="mapAddress" class="text-sm text-gray-600 dark:text-gray-400"></div>
                    <button onclick="closeMapModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Initialize dark mode based on system preference
        function initDarkMode() {
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (systemPrefersDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (e.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Search Functions
        function searchFlights() {
            const searchDate = document.getElementById('searchDate').value;
            if (searchDate) {
                window.location.href = `?date=${searchDate}`;
            } else {
                window.location.href = window.location.pathname;
            }
        }

        function searchFlightsFromPrompt() {
            const searchDate = document.getElementById('searchDatePrompt').value;
            if (searchDate) {
                window.location.href = `?date=${searchDate}`;
            } else {
                alert('Please select a date to search for flights.');
            }
        }

        function clearSearch() {
            window.location.href = window.location.pathname;
        }

        function searchTrips() {
            const tripSearchDate = document.getElementById('tripSearchDate').value;
            if (tripSearchDate) {
                window.location.href = `?date=${tripSearchDate}`;
            } else {
                window.location.href = window.location.pathname;
            }
        }

        function clearTripSearch() {
            window.location.href = window.location.pathname;
        }

        // Flight Details Modal Functions
        function showFlightDetails(flightId) {
            const modal = document.getElementById('flightModal');
            const detailsContainer = document.getElementById('flightDetails');
            
            // Find flight data from the table
            const button = document.getElementById(`btn-${flightId}`) || 
                          document.querySelector(`button[data-flight-id="${flightId}"]`) ||
                          document.querySelector(`button[onclick*="showFlightDetails(${flightId})"]`);
            
            if (!button) {
                console.error('Button not found for flight ID:', flightId);
                return;
            }
            
            const flightRow = button.closest('tr');
            if (!flightRow) {
                console.error('Flight row not found for flight ID:', flightId);
                return;
            }
            
            const cells = flightRow.querySelectorAll('td');
            if (cells.length < 5) {
                console.error('Not enough cells found in flight row');
                return;
            }
            
            // Extract data from table cells with error handling
            const flightNo = cells[0]?.querySelector('.text-sm.font-medium')?.textContent || 'N/A';
            const taskName = cells[0]?.querySelector('.text-sm.text-gray-500')?.textContent || 'N/A';
            const date = cells[1]?.querySelector('.text-sm.text-gray-900')?.textContent || 'N/A';
            const time = cells[1]?.querySelector('.text-sm.text-gray-500')?.textContent || 'N/A';
            const route = cells[2]?.querySelector('.text-sm.text-gray-900')?.textContent || 'N/A';
            const delays = cells[3]?.innerHTML || 'No delay information';
            const crew = cells[4]?.querySelector('.text-sm.text-gray-900')?.textContent || 'No crew information';
            
            // Extract delay minutes from the delay cell
            const delaySpan = cells[3]?.querySelector('span');
            const delayText = delaySpan?.textContent || '';
            const delayMinutes = delayText.match(/(\d+)\s*min/)?.[1] || '0';
            
            detailsContainer.innerHTML = `
                <div class="space-y-6">
                    <!-- Flight Basic Info -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Flight Information</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Flight Number:</label>
                                <p class="text-sm text-gray-900 dark:text-white">${flightNo}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Task Name:</label>
                                <p class="text-sm text-gray-900 dark:text-white">${taskName}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Date:</label>
                                <p class="text-sm text-gray-900 dark:text-white">${date}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Time:</label>
                                <p class="text-sm text-gray-900 dark:text-white">${time}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Route:</label>
                                <p class="text-sm text-gray-900 dark:text-white">${route}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delays Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Delay Information</h4>
                        <div class="text-center">
                            ${delayMinutes > 0 ? 
                                `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <i class="fas fa-clock mr-2"></i>
                                    Total Delay: ${delayMinutes} minutes
                                </span>` :
                                `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <i class="fas fa-check mr-2"></i>
                                    On Time - No Delays
                                </span>`
                            }
                        </div>
                    </div>
                    
                    <!-- Crew Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Crew Information</h4>
                        <div class="text-sm text-gray-900 dark:text-white">
                            ${crew}
                        </div>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeFlightModal() {
            document.getElementById('flightModal').classList.add('hidden');
        }

        // Addresses Modal Functions
        function showAddresses() {
            const modal = document.getElementById('addressesModal');
            const contentContainer = document.getElementById('addressesContent');
            
            <?php
            // Get unique crew members from assigned trips only
            $crewDetailsMap = [];
            
            // Get unique crew member IDs from assigned trips
            foreach ($assignedTrips as $trip) {
                $crewUserId = $trip['crew_user_id'];
                if (!empty($crewUserId) && !isset($crewDetailsMap[$crewUserId])) {
                    // Get full user details for address
                    $user = getUserDetails($crewUserId);
                    
                    $crewDetailsMap[$crewUserId] = [
                        'id' => $crewUserId,
                        'first_name' => $trip['crew_first_name'] ?? ($user['first_name'] ?? ''),
                        'last_name' => $trip['crew_last_name'] ?? ($user['last_name'] ?? ''),
                        'mobile' => $trip['crew_mobile'] ?? ($user['mobile'] ?? null),
                        'phone' => $trip['crew_phone'] ?? ($user['phone'] ?? null),
                        'address_line_1' => $trip['crew_address_line_1'] ?? ($user['address_line_1'] ?? null),
                        'address_line_2' => $trip['crew_address_line_2'] ?? ($user['address_line_2'] ?? null),
                        'latitude' => $user['latitude'] ?? null,
                        'longitude' => $user['longitude'] ?? null,
                        'picture' => $user['picture'] ?? null,
                    ];
                }
            }
            
            // Convert map to array
            $crewDetails = array_values($crewDetailsMap);
            ?>
            
            const crewData = <?php echo json_encode($crewDetails); ?>;
            
            if (crewData.length === 0) {
                contentContainer.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">No crew members found</p>
                    </div>
                `;
            } else {
                let addressesHTML = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
                
                crewData.forEach((member, index) => {
                    const fullName = `${member.first_name || ''} ${member.last_name || ''}`.trim();
                    const address1 = member.address_line_1 || 'Not provided';
                    const address2 = member.address_line_2 || '';
                    const mobile = member.mobile || 'Not provided';
                    const phone = member.phone || 'Not provided';
                    const latitude = member.latitude || null;
                    const longitude = member.longitude || null;
                    const hasCoordinates = latitude && longitude && !isNaN(parseFloat(latitude)) && !isNaN(parseFloat(longitude));
                    const picture = member.picture || null;
                    const picturePath = picture ? '../uploads/profile/' + picture.split('/').pop() : null;
                    
                    addressesHTML += `
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center mb-3">
                                <div class="h-12 w-12 rounded-full mr-3 flex-shrink-0 overflow-hidden border-2 border-blue-600 bg-blue-600 flex items-center justify-center">
                                    ${picturePath ? `
                                        <img src="${escapeHtml(picturePath)}" 
                                             alt="${escapeHtml(fullName)}"
                                             class="h-full w-full object-cover"
                                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'48\' height=\'48\'%3E%3Ccircle cx=\'24\' cy=\'24\' r=\'24\' fill=\'%232563eb\'/%3E%3Cpath d=\'M24 12c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 16c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z\' fill=\'white\'/%3E%3C/svg%3E';">
                                    ` : `
                                        <i class="fas fa-user text-white"></i>
                                    `}
                                </div>
                                <h4 class="ml-3 text-lg font-semibold text-gray-900 dark:text-white">${escapeHtml(fullName)}</h4>
                            </div>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Address Line 1:</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${escapeHtml(address1)}</p>
                                </div>
                                
                                ${address2 ? `
                                <div>
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Address Line 2:</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${escapeHtml(address2)}</p>
                                </div>
                                ` : ''}
                                
                                ${hasCoordinates ? `
                                <div>
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 block">Location:</label>
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-600 mb-3">
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            Lat: ${parseFloat(latitude).toFixed(6)}, Lng: ${parseFloat(longitude).toFixed(6)}
                                        </div>
                                        <div class="mb-3">
                                            <iframe 
                                                src="https://www.google.com/maps?q=${latitude},${longitude}&output=embed&z=15" 
                                                width="100%" 
                                                height="200" 
                                                style="border:0;" 
                                                allowfullscreen="" 
                                                loading="lazy" 
                                                referrerpolicy="no-referrer-when-downgrade"
                                                class="rounded-lg">
                                            </iframe>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="https://www.google.com/maps?q=${latitude},${longitude}" 
                                               target="_blank"
                                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                                <i class="fab fa-google mr-1"></i>
                                                Google Map
                                            </a>
                                            <a href="https://neshan.org/maps/@${latitude},${longitude},15" 
                                               target="_blank"
                                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                                <i class="fas fa-map-marked-alt mr-1"></i>
                                                Neshan Map
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${!hasCoordinates && address1 !== 'Not provided' ? `
                                <div>
                                    <button data-address="${escapeHtml(address1)}" data-person="${escapeHtml(fullName)}" 
                                            class="show-map-btn mt-2 inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        Show on Map
                                    </button>
                                </div>
                                ` : ''}
                                
                                <div>
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Mobile:</label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        <i class="fas fa-mobile-alt mr-1"></i>${escapeHtml(mobile)}
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Phone:</label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        <i class="fas fa-phone mr-1"></i>${escapeHtml(phone)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                addressesHTML += '</div>';
                contentContainer.innerHTML = addressesHTML;
                
                // Add event listeners for map buttons
                const mapButtons = contentContainer.querySelectorAll('.show-map-btn');
                mapButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const address = this.getAttribute('data-address');
                        const person = this.getAttribute('data-person');
                        showAddressOnMap(address, person);
                    });
                });
            }
            
            modal.classList.remove('hidden');
        }

        function closeAddressesModal() {
            document.getElementById('addressesModal').classList.add('hidden');
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Map Modal Functions
        const NESHAN_API_KEY = 'service.55356840079247609ba2a270fe45054c';
        let map = null;
        let marker = null;

        async function showAddressOnMap(address, personName) {
            const mapModal = document.getElementById('mapModal');
            const mapContainer = document.getElementById('mapContainer');
            const mapModalTitle = document.getElementById('mapModalTitle');
            const mapAddress = document.getElementById('mapAddress');
            
            mapModalTitle.textContent = personName || 'Map';
            mapAddress.textContent = address;
            
            // Show modal
            mapModal.classList.remove('hidden');
            
            // Show loading
            mapContainer.innerHTML = '<div class="flex items-center justify-center h-full"><i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i></div>';
            
            try {
                // Call Neshan Geocoding API
                const geocodeUrl = `https://api.neshan.org/v6/geocoding?address=${encodeURIComponent(address)}`;
                
                const response = await fetch(geocodeUrl, {
                    method: 'GET',
                    headers: {
                        'Api-Key': NESHAN_API_KEY,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.status === 'OK' && data.location) {
                    const lng = data.location.x;
                    const lat = data.location.y;
                    
                    // Initialize map
                    mapContainer.innerHTML = '';
                    
                    // Create map using Leaflet with Neshan tile layer
                    map = L.map('mapContainer', {
                        center: [lat, lng],
                        zoom: 16,
                        scrollWheelZoom: true
                    });
                    
                    // Add OpenStreetMap tile layer (using OpenStreetMap as base, since Neshan Maps requires special SDK)
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 18
                    }).addTo(map);
                    
                    // Add marker
                    marker = L.marker([lat, lng]).addTo(map);
                    marker.bindPopup(`<strong>${escapeHtml(personName || 'Location')}</strong><br>${escapeHtml(address)}`).openPopup();
                    
                } else {
                    // Error handling
                    let errorMessage = 'Unable to find location for this address.';
                    let errorDetails = '';
                    
                    if (data.code === 470) {
                        errorMessage = 'Invalid address format.';
                        errorDetails = 'Please check the address format and try again.';
                    } else if (data.code === 480) {
                        errorMessage = 'API Key not found.';
                        errorDetails = 'The API key is missing or invalid. Please check your API key configuration.';
                    } else if (data.code === 481) {
                        errorMessage = 'API limit exceeded.';
                        errorDetails = 'You have exceeded your API usage limit. Please try again later.';
                    } else if (data.code === 482) {
                        errorMessage = 'Rate limit exceeded.';
                        errorDetails = 'Too many requests. Please wait a moment and try again.';
                    } else if (data.code === 483) {
                        errorMessage = 'API Key Type Error.';
                        errorDetails = 'The API key you are using is not configured for Geocoding service. Please create a new API key specifically for "Address to Location" (Geocoding) service in your Neshan panel.';
                    } else if (data.code === 484) {
                        errorMessage = 'API Whitelist Error.';
                        errorDetails = 'Your IP address is not whitelisted. Please add your server IP to the allowed list in your Neshan API key settings.';
                    } else if (data.code === 485) {
                        errorMessage = 'API Service List Error.';
                        errorDetails = 'The API key does not have access to Geocoding service. Please enable "Address to Location" service for this API key.';
                    } else if (data.code === 500) {
                        errorMessage = 'Generic error occurred.';
                        errorDetails = 'An unexpected error occurred. Please try again later.';
                    } else if (data.msg) {
                        errorMessage = data.msg;
                        errorDetails = `Error code: ${data.code || 'Unknown'}`;
                    }
                    
                    mapContainer.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-full bg-red-50 dark:bg-red-900 rounded-lg p-6">
                            <i class="fas fa-exclamation-triangle text-4xl text-red-600 dark:text-red-400 mb-4"></i>
                            <p class="text-red-800 dark:text-red-200 font-medium text-lg mb-2">${errorMessage}</p>
                            ${errorDetails ? `<p class="text-sm text-red-600 dark:text-red-400 mb-4 text-center max-w-md">${errorDetails}</p>` : ''}
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mt-4 max-w-md w-full">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Address:</p>
                                <p class="text-sm text-gray-900 dark:text-white break-words">${escapeHtml(address)}</p>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Geocoding error:', error);
                mapContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-full bg-red-50 dark:bg-red-900 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-4xl text-red-600 dark:text-red-400 mb-4"></i>
                        <p class="text-red-800 dark:text-red-200 font-medium">Error loading map: ${error.message}</p>
                    </div>
                `;
            }
        }

        function closeMapModal() {
            const mapModal = document.getElementById('mapModal');
            mapModal.classList.add('hidden');
            
            // Clean up map
            if (map) {
                map.remove();
                map = null;
                marker = null;
            }
        }

        // Close modals when clicking outside
        document.getElementById('flightModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFlightModal();
            }
        });

        document.getElementById('addressesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddressesModal();
            }
        });

        document.getElementById('mapModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMapModal();
            }
        });
    </script>
</body>
</html>

