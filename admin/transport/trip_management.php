<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/transport/trip_management.php');

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

$current_user = getCurrentUser();
$message = '';
$error = '';

// Ensure trip driver assignments table exists
ensureTripDriverAssignmentsTableExists();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'assign_driver':
            $flightId = intval($_POST['flight_id'] ?? 0);
            $crewUserId = intval($_POST['crew_user_id'] ?? 0);
            $crewPosition = trim($_POST['crew_position'] ?? '');
            $driverId = intval($_POST['driver_id'] ?? 0);
            $assignmentDate = trim($_POST['assignment_date'] ?? '');
            $pickupTime = !empty($_POST['pickup_time']) ? trim($_POST['pickup_time']) : null;
            $dropoffTime = !empty($_POST['dropoff_time']) ? trim($_POST['dropoff_time']) : null;
            $pickupLocation = !empty($_POST['pickup_location']) ? trim($_POST['pickup_location']) : null;
            $dropoffLocation = !empty($_POST['dropoff_location']) ? trim($_POST['dropoff_location']) : null;
            $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
            
            if (empty($flightId) || empty($crewUserId) || empty($crewPosition) || empty($driverId) || empty($assignmentDate)) {
                $error = 'All required fields must be filled.';
            } else {
                // Log input values for debugging
                error_log("Assign driver attempt - FlightID: $flightId, CrewUserID: $crewUserId, CrewPosition: $crewPosition, DriverID: $driverId, Date: $assignmentDate");
                
                if (assignDriverToCrew($flightId, $crewUserId, $crewPosition, $driverId, $assignmentDate, $pickupTime, $dropoffTime, $pickupLocation, $dropoffLocation, $notes, $current_user['id'])) {
                    $message = 'Driver assigned successfully.';
                    // Redirect to avoid form resubmission
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?date=' . urlencode($assignmentDate) . '&message=success');
                    exit();
                } else {
                    $error = 'Failed to assign driver. Please check error logs for details.';
                }
            }
            break;
            
        case 'update_driver':
            $flightId = intval($_POST['flight_id'] ?? 0);
            $crewUserId = intval($_POST['crew_user_id'] ?? 0);
            $crewPosition = trim($_POST['crew_position'] ?? '');
            $driverId = intval($_POST['driver_id'] ?? 0);
            $assignmentDate = trim($_POST['assignment_date'] ?? '');
            $pickupTime = !empty($_POST['pickup_time']) ? trim($_POST['pickup_time']) : null;
            $dropoffTime = !empty($_POST['dropoff_time']) ? trim($_POST['dropoff_time']) : null;
            $pickupLocation = !empty($_POST['pickup_location']) ? trim($_POST['pickup_location']) : null;
            $dropoffLocation = !empty($_POST['dropoff_location']) ? trim($_POST['dropoff_location']) : null;
            $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
            
            if (empty($flightId) || empty($crewUserId) || empty($crewPosition) || empty($driverId) || empty($assignmentDate)) {
                $error = 'All required fields must be filled.';
            } else {
                if (assignDriverToCrew($flightId, $crewUserId, $crewPosition, $driverId, $assignmentDate, $pickupTime, $dropoffTime, $pickupLocation, $dropoffLocation, $notes, $current_user['id'])) {
                    $message = 'Assignment updated successfully.';
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?date=' . urlencode($assignmentDate) . '&message=updated');
                    exit();
                } else {
                    $error = 'Failed to update assignment. Please check error logs for details.';
                }
            }
            break;
            
        case 'unassign_driver':
            $flightId = intval($_POST['flight_id'] ?? 0);
            $crewUserId = intval($_POST['crew_user_id'] ?? 0);
            $driverId = intval($_POST['driver_id'] ?? 0);
            $assignmentDate = trim($_POST['assignment_date'] ?? '');
            
            if (empty($flightId) || empty($crewUserId) || empty($driverId) || empty($assignmentDate)) {
                $error = 'All required fields must be filled.';
            } else {
                if (unassignDriverFromCrew($flightId, $crewUserId, $driverId, $assignmentDate)) {
                    $message = 'Driver unassigned successfully.';
                } else {
                    $error = 'Failed to unassign driver.';
                }
            }
            break;
    }
}

// Get selected date (default to today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Check for success message from redirect
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'success') {
        $message = 'Driver assigned successfully.';
    } elseif ($_GET['message'] === 'updated') {
        $message = 'Assignment updated successfully.';
    }
}

// Get transport users (drivers)
$transportUsers = getTransportUsers();

// Helper function to parse route and get origin/destination
function parseRoute($route) {
    if (empty($route)) {
        return ['origin' => '', 'destination' => ''];
    }
    
    $parts = explode('-', $route);
    return [
        'origin' => trim($parts[0] ?? ''),
        'destination' => trim($parts[1] ?? '')
    ];
}

// Helper function to check if flight needs driver assignment
function needsDriverAssignment($route) {
    $routeInfo = parseRoute($route);
    $origin = $routeInfo['origin'];
    $destination = $routeInfo['destination'];
    
    // Check if origin or destination is RAS or THR
    $relevantStations = ['RAS', 'THR'];
    return in_array($origin, $relevantStations) || in_array($destination, $relevantStations);
}

// Helper function to get all flights for a crew member on a specific date
function getCrewMemberFlightsForDate($crewUserId, $date) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                f.FlightID,
                f.Route,
                f.TaskStart,
                f.TaskEnd
            FROM flights f
            WHERE DATE(f.FltDate) = ?
            AND (
                f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR 
                f.Crew4 = ? OR f.Crew5 = ? OR f.Crew6 = ? OR 
                f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR 
                f.Crew10 = ?
            )
            AND f.TaskStart IS NOT NULL
            ORDER BY f.TaskStart ASC
        ");
        $stmt->execute([$date, $crewUserId, $crewUserId, $crewUserId, $crewUserId, $crewUserId, 
                       $crewUserId, $crewUserId, $crewUserId, $crewUserId, $crewUserId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting crew member flights: " . $e->getMessage());
        return [];
    }
}

// Helper function to check if crew member should be shown for this flight
function shouldShowCrewMember($crewUserId, $flightId, $date) {
    $crewFlights = getCrewMemberFlightsForDate($crewUserId, $date);
    
    if (empty($crewFlights)) {
        return false;
    }
    
    // If only one flight, show it
    if (count($crewFlights) === 1) {
        return $crewFlights[0]['FlightID'] == $flightId;
    }
    
    // Find first and last flight
    $firstFlight = $crewFlights[0];
    $lastFlight = $crewFlights[count($crewFlights) - 1];
    
    // Show if this is the first or last flight
    return $firstFlight['FlightID'] == $flightId || $lastFlight['FlightID'] == $flightId;
}

// Get flights for the selected date
$allFlights = getFlightsWithCrewByDate($selectedDate);

// Separate flights into those that need driver assignment and those that don't
$flights = [];
$flightsWithoutDriver = [];
foreach ($allFlights as $flight) {
    if (needsDriverAssignment($flight['Route'] ?? '')) {
        $flights[] = $flight;
    } else {
        $flightsWithoutDriver[] = $flight;
    }
}

// Get driver assignments for the selected date
$driverAssignments = getDriverAssignmentsByDate($selectedDate);

// Create a map of assignments for quick lookup
$assignmentsMap = [];
foreach ($driverAssignments as $assignment) {
    $key = $assignment['flight_id'] . '_' . $assignment['crew_user_id'] . '_' . $assignment['driver_id'];
    $assignmentsMap[$key] = $assignment;
}

// Get all stations for dropdown
$stations = getAllStations();

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Management - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Trip Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage driver assignments for crew members</p>
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

                <!-- Date Selector -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 mb-6">
                    <form method="GET" class="flex items-center space-x-4">
                        <label for="date" class="text-sm font-medium text-gray-700 dark:text-gray-300">Select Date:</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" 
                               class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>
                            Load Flights
                        </button>
                    </form>
                </div>

                <!-- Transport Users List -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-truck mr-2"></i>Available Drivers (Transport)
                    </h2>
                    <?php if (empty($transportUsers)): ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No transport users found.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($transportUsers as $driver): ?>
                                <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="h-10 w-10 bg-blue-600 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            <?php echo htmlspecialchars($driver['position'] ?? 'Driver'); ?>
                                        </p>
                                        <?php if ($driver['mobile'] || $driver['phone']): ?>
                                            <p class="text-xs text-blue-600 dark:text-blue-400 truncate">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($driver['mobile'] ?? $driver['phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Flights List -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            <i class="fas fa-plane mr-2"></i>Flights for <?php echo date('F j, Y', strtotime($selectedDate)); ?>
                        </h2>
                    </div>
                    
                    <?php if (empty($flights)): ?>
                        <div class="px-6 py-8 text-center">
                            <i class="fas fa-plane-slash text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-400">No flights found for this date.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($flights as $flight): ?>
                                <?php
                                $crewMembers = getFlightCrewMembers($flight['FlightID']);
                                $flightAssignments = getDriverAssignmentsForFlight($flight['FlightID'], $selectedDate);
                                $assignmentsByCrew = [];
                                foreach ($flightAssignments as $assignment) {
                                    $assignmentsByCrew[$assignment['crew_user_id']][] = $assignment;
                                }
                                ?>
                                <div class="flight-card p-6 transition-colors duration-200" data-flight-id="<?php echo $flight['FlightID']; ?>">
                                    <!-- Flight Header -->
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-base font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($flight['FlightNo'] ?? $flight['TaskName'] ?? 'N/A'); ?>
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                <?php if ($flight['TaskName']): ?>
                                                    <i class="fas fa-plane mr-1"></i><?php echo htmlspecialchars($flight['TaskName']); ?>
                                                    <span class="mx-2">•</span>
                                                <?php endif; ?>
                                                <i class="fas fa-route mr-1"></i><?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php 
                                                if ($flight['TaskStart']) {
                                                    echo date('H:i', strtotime($flight['TaskStart']));
                                                }
                                                if ($flight['TaskEnd']) {
                                                    echo ' - ' . date('H:i', strtotime($flight['TaskEnd']));
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="fas fa-plane mr-1"></i><?php echo htmlspecialchars($flight['Rego'] ?? 'N/A'); ?>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($flight['ACType'] ?? ''); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Crew Members -->
                                    <?php if (empty($crewMembers)): ?>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No crew members assigned to this flight.</p>
                                    <?php else: ?>
                                        <div class="space-y-3">
                                            <?php 
                                            // Filter crew members: only show those in their first or last flight of the day
                                            $filteredCrewMembers = [];
                                            foreach ($crewMembers as $crew) {
                                                if (shouldShowCrewMember($crew['id'], $flight['FlightID'], $selectedDate)) {
                                                    $filteredCrewMembers[] = $crew;
                                                }
                                            }
                                            ?>
                                            <?php if (empty($filteredCrewMembers)): ?>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    This flight is in the middle route and does not require Driver Assignment
                                                </p>
                                            <?php else: ?>
                                                <?php foreach ($filteredCrewMembers as $crew): ?>
                                                <div class="crew-member-card flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg transition-colors duration-200">
                                                    <div class="flex items-center flex-1">
                                                        <?php
                                                        // Get crew user picture
                                                        $crewPicture = $crew['picture'] ?? null;
                                                        $crewPicturePath = $crewPicture ? '../../uploads/profile/' . basename($crewPicture) : null;
                                                        ?>
                                                        <div class="h-10 w-10 rounded-full mr-3 flex-shrink-0 overflow-hidden border-2 border-green-600 bg-green-600 flex items-center justify-center">
                                                            <?php if ($crewPicturePath): ?>
                                                                <img src="<?php echo htmlspecialchars($crewPicturePath); ?>" 
                                                                     alt="<?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?>"
                                                                     class="h-full w-full object-cover"
                                                                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'40\' height=\'40\'%3E%3Ccircle cx=\'20\' cy=\'20\' r=\'20\' fill=\'%2316a34a\'/%3E%3Cpath d=\'M20 10c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 13c-3.31 0-6 1.34-6 3v1h12v-1c0-1.66-2.69-3-6-3z\' fill=\'white\'/%3E%3C/svg%3E';">
                                                            <?php else: ?>
                                                                <i class="fas fa-user text-white text-sm"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                                <?php echo htmlspecialchars($crew['first_name'] . ' ' . $crew['last_name']); ?>
                                                            </p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                <?php echo htmlspecialchars($crew['position'] ?? ''); ?>
                                                                <?php if ($crew['role']): ?>
                                                                    <span class="ml-2">(<?php echo htmlspecialchars($crew['role']); ?>)</span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <?php if ($crew['mobile'] || $crew['phone']): ?>
                                                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                                    <i class="fas fa-phone mr-1"></i>
                                                                    <a href="tel:<?php echo htmlspecialchars($crew['mobile'] ?? $crew['phone']); ?>">
                                                                        <?php echo htmlspecialchars($crew['mobile'] ?? $crew['phone']); ?>
                                                                    </a>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Assigned Drivers -->
                                                    <div class="flex items-center space-x-2 ml-4 flex-wrap">
                                                        <?php if (isset($assignmentsByCrew[$crew['id']])): ?>
                                                            <?php foreach ($assignmentsByCrew[$crew['id']] as $assignment): ?>
                                                                <?php
                                                                // Get crew user coordinates (pickup location)
                                                                $crewUser = getUserById($crew['id']);
                                                                $pickupLat = $crewUser['latitude'] ?? null;
                                                                $pickupLng = $crewUser['longitude'] ?? null;
                                                                
                                                                // Get station coordinates (dropoff location)
                                                                $dropoffLat = null;
                                                                $dropoffLng = null;
                                                                if ($assignment['dropoff_location']) {
                                                                    // Try to get station by IATA code
                                                                    $dropoffStation = getStationByIATACode($assignment['dropoff_location']);
                                                                    if ($dropoffStation) {
                                                                        $dropoffLat = $dropoffStation['latitude'] ?? null;
                                                                        $dropoffLng = $dropoffStation['longitude'] ?? null;
                                                                    }
                                                                }
                                                                
                                                                // Calculate trip duration if both coordinates are available
                                                                $tripDuration = null;
                                                                if ($pickupLat && $pickupLng && $dropoffLat && $dropoffLng) {
                                                                    $tripDuration = calculateTripDuration($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, 'car', true);
                                                                }
                                                                ?>
                                                                <div class="flex items-center px-3 py-1.5 bg-blue-100 dark:bg-blue-900 rounded-lg mb-2">
                                                                    <i class="fas fa-truck mr-2 text-blue-600 dark:text-blue-400"></i>
                                                                    <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                                                        <?php echo htmlspecialchars($assignment['driver_first_name'] . ' ' . $assignment['driver_last_name']); ?>
                                                                    </span>
                                                                    <?php if ($assignment['pickup_time']): ?>
                                                                        <span class="text-xs text-blue-600 dark:text-blue-400 ml-2">
                                                                            <i class="fas fa-clock mr-1"></i><?php echo date('H:i', strtotime($assignment['pickup_time'])); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <?php if ($tripDuration && isset($tripDuration['duration'])): ?>
                                                                        <span class="text-xs text-green-600 dark:text-green-400 ml-2 font-semibold" title="Estimated trip duration">
                                                                            <i class="fas fa-route mr-1"></i><?php echo htmlspecialchars(formatDurationEnglish($tripDuration['duration'])); ?>
                                                                        </span>
                                                                        <?php if (isset($tripDuration['distance'])): ?>
                                                                            <span class="text-xs text-purple-600 dark:text-purple-400 ml-2 font-semibold" title="Estimated trip distance">
                                                                                <i class="fas fa-arrows-alt-h mr-1"></i><?php echo htmlspecialchars(formatDistanceEnglish($tripDuration['distance'])); ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                    <button onclick="openEditAssignmentModal(<?php echo $flight['FlightID']; ?>, <?php echo $crew['id']; ?>, <?php echo $assignment['driver_id']; ?>, '<?php echo htmlspecialchars($selectedDate); ?>', '<?php echo htmlspecialchars($assignment['pickup_time'] ?? ''); ?>', '<?php echo htmlspecialchars($assignment['dropoff_time'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($assignment['pickup_location'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($assignment['dropoff_location'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($assignment['notes'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($crew['address'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($assignment['crew_position'] ?? $crew['crew_position'] ?? 'Crew1')); ?>')" 
                                                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 ml-2" title="Edit assignment">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <form method="POST" class="inline ml-2" onsubmit="return confirm('Are you sure you want to unassign this driver?');">
                                                                        <input type="hidden" name="action" value="unassign_driver">
                                                                        <input type="hidden" name="flight_id" value="<?php echo $flight['FlightID']; ?>">
                                                                        <input type="hidden" name="crew_user_id" value="<?php echo $crew['id']; ?>">
                                                                        <input type="hidden" name="driver_id" value="<?php echo $assignment['driver_id']; ?>">
                                                                        <input type="hidden" name="assignment_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                                                                        <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 ml-2" title="Unassign driver">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Assign Driver Button -->
                                                        <?php
                                                        // Get crew user coordinates
                                                        $crewUser = getUserById($crew['id']);
                                                        $crewLat = $crewUser['latitude'] ?? null;
                                                        $crewLng = $crewUser['longitude'] ?? null;
                                                        
                                                        // Get flight details for calculation
                                                        $flightRoute = $flight['Route'] ?? '';
                                                        $taskStart = $flight['TaskStart'] ?? '';
                                                        $minutes1 = $flight['minutes_1'] ?? 0;
                                                        $minutes2 = $flight['minutes_2'] ?? 0;
                                                        $minutes3 = $flight['minutes_3'] ?? 0;
                                                        $minutes4 = $flight['minutes_4'] ?? 0;
                                                        $minutes5 = $flight['minutes_5'] ?? 0;
                                                        ?>
                                                        <?php
                                                        $taskEnd = $flight['TaskEnd'] ?? '';
                                                        ?>
                                                        <button onclick="openAssignDriverModal(<?php echo $flight['FlightID']; ?>, <?php echo $crew['id']; ?>, '<?php echo htmlspecialchars($crew['crew_position']); ?>', '<?php echo htmlspecialchars($selectedDate); ?>', '<?php echo htmlspecialchars(addslashes($crew['address'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($flightRoute)); ?>', '<?php echo htmlspecialchars($taskStart); ?>', '<?php echo htmlspecialchars($taskEnd); ?>', <?php echo intval($minutes1); ?>, <?php echo intval($minutes2); ?>, <?php echo intval($minutes3); ?>, <?php echo intval($minutes4); ?>, <?php echo intval($minutes5); ?>, <?php echo $crewLat ? $crewLat : 'null'; ?>, <?php echo $crewLng ? $crewLng : 'null'; ?>)" 
                                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                                            <i class="fas fa-plus mr-1"></i>
                                                            Assign Driver
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Flights that don't need driver assignment -->
                            <?php if (!empty($flightsWithoutDriver)): ?>
                                <?php foreach ($flightsWithoutDriver as $flight): ?>
                                    <div class="flight-card p-6 transition-colors duration-200 opacity-60" data-flight-id="<?php echo $flight['FlightID']; ?>">
                                        <!-- Flight Header -->
                                        <div class="flex items-start justify-between mb-4">
                                            <div>
                                                <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($flight['FlightNo'] ?? $flight['TaskName'] ?? 'N/A'); ?>
                                                </h3>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                    <?php if ($flight['TaskName']): ?>
                                                        <i class="fas fa-plane mr-1"></i><?php echo htmlspecialchars($flight['TaskName']); ?>
                                                        <span class="mx-2">•</span>
                                                    <?php endif; ?>
                                                    <i class="fas fa-route mr-1"></i><?php echo htmlspecialchars($flight['Route'] ?? 'N/A'); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php 
                                                    if ($flight['TaskStart']) {
                                                        echo date('H:i', strtotime($flight['TaskStart']));
                                                    }
                                                    if ($flight['TaskEnd']) {
                                                        echo ' - ' . date('H:i', strtotime($flight['TaskEnd']));
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <i class="fas fa-plane mr-1"></i><?php echo htmlspecialchars($flight['Rego'] ?? 'N/A'); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($flight['ACType'] ?? ''); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Message -->
                                        <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-info-circle text-yellow-600 dark:text-yellow-400 mr-2"></i>
                                                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                                    This flight does not require Driver Assignment
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Driver Modal -->
    <div id="assignDriverModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-6 border w-full max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-truck mr-2"></i>Assign Driver
                    </h3>
                    <button onclick="closeAssignDriverModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column: Assignment Details (Legend) -->
                    <div class="lg:col-span-1">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-5 border border-blue-200 dark:border-blue-700 sticky top-5">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-blue-600 dark:text-blue-400"></i>
                                Assignment Details
                            </h4>
                            
                            <!-- To Airport Section -->
                            <div id="legend_to_airport" class="mb-6">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-blue-300 dark:border-blue-600 shadow-sm">
                                    <h5 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                        <i class="fas fa-plane-departure mr-2 text-blue-600"></i>
                                        Take Crew to Airport
                                    </h5>
                                    
                                    <div class="space-y-3 text-sm">
                                        <div>
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                <i class="fas fa-map-marker-alt mr-1 text-green-600"></i>Pickup Location:
                                            </p>
                                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 ml-4 space-y-1">
                                                <li>If origin is <strong>RAS</strong>: Ahoo Building</li>
                                                <li>If origin is <strong>THR</strong>: Crew's Home Address</li>
                                            </ul>
                                        </div>
                                        
                                        <div>
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                <i class="fas fa-map-marker-alt mr-1 text-red-600"></i>Dropoff Location:
                                            </p>
                                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 ml-4 space-y-1">
                                                <li>Origin Airport (RAS or THR)</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded p-3 border border-blue-200 dark:border-blue-700">
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="fas fa-calculator mr-1 text-blue-600"></i>Pickup Time Calculation:
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                                (TaskStart + Delay Minutes) - ((Trip Duration + 60 min) + (Trip Duration + 60 min) × 30%)
                                            </p>
                                        </div>
                                        
                                        <div class="bg-green-50 dark:bg-green-900/30 rounded p-3 border border-green-200 dark:border-green-700">
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="fas fa-clock mr-1 text-green-600"></i>Dropoff Time Calculation:
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                                Pickup Time + Trip Duration + (Trip Duration × 30% Risk)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- To Home Section -->
                            <div id="legend_to_home" class="hidden">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-purple-300 dark:border-purple-600 shadow-sm">
                                    <h5 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                        <i class="fas fa-plane-arrival mr-2 text-purple-600"></i>
                                        Take Crew Home
                                    </h5>
                                    
                                    <div class="space-y-3 text-sm">
                                        <div>
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                <i class="fas fa-map-marker-alt mr-1 text-green-600"></i>Pickup Location:
                                            </p>
                                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 ml-4 space-y-1">
                                                <li>Destination Airport (Second airport in route)</li>
                                                <li>Example: Route <strong>RAS-THR</strong> → Pickup from <strong>THR</strong></li>
                                            </ul>
                                        </div>
                                        
                                        <div>
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                <i class="fas fa-map-marker-alt mr-1 text-red-600"></i>Dropoff Location:
                                            </p>
                                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 ml-4 space-y-1">
                                                <li>If destination is <strong>RAS</strong>: Ahoo Building</li>
                                                <li>If destination is <strong>THR</strong>: Crew's Home Address</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="bg-purple-50 dark:bg-purple-900/30 rounded p-3 border border-purple-200 dark:border-purple-700">
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="fas fa-calculator mr-1 text-purple-600"></i>Pickup Time Calculation:
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                                TaskEnd + 45 minutes
                                            </p>
                                        </div>
                                        
                                        <div class="bg-green-50 dark:bg-green-900/30 rounded p-3 border border-green-200 dark:border-green-700">
                                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="fas fa-clock mr-1 text-green-600"></i>Dropoff Time Calculation:
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                                Pickup Time + Trip Duration + (Trip Duration × 30% Risk)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Form -->
                    <div class="lg:col-span-2">
                        <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="assign_driver">
                    <input type="hidden" id="assign_flight_id" name="flight_id">
                    <input type="hidden" id="assign_crew_user_id" name="crew_user_id">
                    <input type="hidden" id="assign_crew_position" name="crew_position">
                    <input type="hidden" id="assign_assignment_date" name="assignment_date">
                    <input type="hidden" id="assign_flight_route" name="flight_route">
                    <input type="hidden" id="assign_task_start" name="task_start">
                    <input type="hidden" id="assign_task_end" name="task_end">
                    <input type="hidden" id="assign_minutes_1" name="minutes_1">
                    <input type="hidden" id="assign_minutes_2" name="minutes_2">
                    <input type="hidden" id="assign_minutes_3" name="minutes_3">
                    <input type="hidden" id="assign_minutes_4" name="minutes_4">
                    <input type="hidden" id="assign_minutes_5" name="minutes_5">
                    <input type="hidden" id="assign_crew_latitude" name="crew_latitude">
                    <input type="hidden" id="assign_crew_longitude" name="crew_longitude">
                    
                    <!-- Assignment Type Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Assignment Type <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <label id="label_to_airport" class="flex items-center">
                                <input type="radio" name="assignment_type" value="to_airport" id="assignment_type_to_airport"
                                       class="mr-2 text-blue-600 focus:ring-blue-500" onchange="updateLocationsBasedOnType()">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Take crew to airport for flight</span>
                            </label>
                            <label id="label_to_home" class="flex items-center">
                                <input type="radio" name="assignment_type" value="to_home" id="assignment_type_to_home"
                                       class="mr-2 text-blue-600 focus:ring-blue-500" onchange="updateLocationsBasedOnType()">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Take crew home from airport after flight</span>
                            </label>
                        </div>
                        <p id="assignment_type_info" class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="assignment_type_info_text">Select assignment type based on route</span>
                        </p>
                    </div>
                    
                    <div>
                        <label for="assign_driver_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Select Driver <span class="text-red-500">*</span>
                        </label>
                        <select id="assign_driver_id" name="driver_id" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Driver --</option>
                            <?php foreach ($transportUsers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>">
                                    <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name'] . ' (' . ($driver['position'] ?? 'Driver') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="assign_pickup_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Pickup Time
                        </label>
                        <input type="time" id="assign_pickup_time" name="pickup_time"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <p id="pickup_time_calculation_info" class="text-xs text-gray-500 dark:text-gray-400 mt-1 hidden">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="pickup_time_formula_text"></span>
                        </p>
                    </div>
                    
                    <div>
                        <label for="assign_dropoff_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Dropoff Time
                        </label>
                        <input type="time" id="assign_dropoff_time" name="dropoff_time"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="assign_pickup_location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-green-600"></i>Pickup Location
                        </label>
                        <input type="text" id="assign_pickup_location" name="pickup_location"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Enter pickup location">
                        <p id="pickup_location_info" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="pickup_location_info_text">Location will be set automatically based on assignment type</span>
                        </p>
                    </div>
                    
                    <div>
                        <label for="assign_dropoff_location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-red-600"></i>Dropoff Location
                        </label>
                        <input type="text" id="assign_dropoff_location" name="dropoff_location" list="stations_list"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Select station or enter address manually">
                        <datalist id="stations_list">
                            <?php foreach ($stations as $station): ?>
                                <?php if (!empty($station['iata_code'])): ?>
                                    <?php
                                    // Build display text: station_name (iata_code) - address
                                    $displayText = htmlspecialchars($station['station_name']);
                                    if (!empty($station['iata_code'])) {
                                        $displayText .= ' (' . htmlspecialchars($station['iata_code']) . ')';
                                    }
                                    if (!empty($station['address_line1'])) {
                                        $displayText .= ' - ' . htmlspecialchars($station['address_line1']);
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($station['iata_code']); ?>" label="<?php echo htmlspecialchars($station['station_name']); ?>">
                                        <?php echo $displayText; ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </datalist>
                        <p id="dropoff_location_info" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="dropoff_location_info_text">Location will be set automatically based on assignment type</span>
                        </p>
                    </div>
                    
                    <!-- Trip Duration Estimate -->
                    <div id="trip_duration_section" class="hidden p-4 bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-900 dark:to-blue-900 rounded-lg border border-green-200 dark:border-green-700">
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
                                <p id="trip_duration_text" class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    --
                                </p>
                                <p id="trip_distance_text" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <i class="fas fa-arrows-alt-h mr-1"></i>
                                    --
                                </p>
                            </div>
                        </div>
                        <div id="trip_duration_loading" class="hidden mt-2 text-xs text-gray-600 dark:text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-1"></i>
                            Calculating...
                        </div>
                        <div id="trip_duration_error" class="hidden mt-2 text-xs text-red-600 dark:text-red-400">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span id="trip_duration_error_text"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="assign_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes
                        </label>
                        <textarea id="assign_notes" name="notes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    
                        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" onclick="closeAssignDriverModal()"
                                    class="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                <i class="fas fa-check mr-2"></i>Assign Driver
                            </button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div id="editAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Assignment</h3>
                    <button onclick="closeEditAssignmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_driver">
                    <input type="hidden" id="edit_flight_id" name="flight_id">
                    <input type="hidden" id="edit_crew_user_id" name="crew_user_id">
                    <input type="hidden" id="edit_crew_position" name="crew_position">
                    <input type="hidden" id="edit_driver_id" name="driver_id">
                    <input type="hidden" id="edit_assignment_date" name="assignment_date">
                    
                    <div>
                        <label for="edit_pickup_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Pickup Time
                        </label>
                        <input type="time" id="edit_pickup_time" name="pickup_time"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_dropoff_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Dropoff Time
                        </label>
                        <input type="time" id="edit_dropoff_time" name="dropoff_time"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_pickup_location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Pickup Location
                        </label>
                        <input type="text" id="edit_pickup_location" name="pickup_location"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Enter pickup location">
                    </div>
                    
                    <div>
                        <label for="edit_dropoff_location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Dropoff Location
                        </label>
                        <input type="text" id="edit_dropoff_location" name="dropoff_location" list="stations_list_edit"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Select station or enter address manually">
                        <datalist id="stations_list_edit">
                            <?php foreach ($stations as $station): ?>
                                <?php if (!empty($station['iata_code'])): ?>
                                    <?php
                                    // Build display text: station_name (iata_code) - address
                                    $displayText = htmlspecialchars($station['station_name']);
                                    if (!empty($station['iata_code'])) {
                                        $displayText .= ' (' . htmlspecialchars($station['iata_code']) . ')';
                                    }
                                    if (!empty($station['address_line1'])) {
                                        $displayText .= ' - ' . htmlspecialchars($station['address_line1']);
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($station['iata_code']); ?>" label="<?php echo htmlspecialchars($station['station_name']); ?>">
                                        <?php echo $displayText; ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </datalist>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select from list or type custom address
                        </p>
                    </div>
                    
                    <!-- Trip Duration Estimate for Edit Modal -->
                    <div id="edit_trip_duration_section" class="hidden p-4 bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-900 dark:to-blue-900 rounded-lg border border-green-200 dark:border-green-700">
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
                                <p id="edit_trip_duration_text" class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    --
                                </p>
                                <p id="edit_trip_distance_text" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <i class="fas fa-arrows-alt-h mr-1"></i>
                                    --
                                </p>
                            </div>
                        </div>
                        <div id="edit_trip_duration_loading" class="hidden mt-2 text-xs text-gray-600 dark:text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-1"></i>
                            Calculating...
                        </div>
                        <div id="edit_trip_duration_error" class="hidden mt-2 text-xs text-red-600 dark:text-red-400">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span id="edit_trip_duration_error_text"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes
                        </label>
                        <textarea id="edit_notes" name="notes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditAssignmentModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize dark mode
        function initDarkMode() {
            const isDarkMode = localStorage.getItem('darkMode') === 'true' || 
                             (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDarkMode) {
                document.documentElement.classList.add('dark');
            }
        }
        initDarkMode();

        function openAssignDriverModal(flightId, crewUserId, crewPosition, assignmentDate, crewAddress) {
            document.getElementById('assign_flight_id').value = flightId;
            document.getElementById('assign_crew_user_id').value = crewUserId;
            document.getElementById('assign_crew_position').value = crewPosition;
            document.getElementById('assign_assignment_date').value = assignmentDate;
            
            // Set pickup location from crew member's address
            const pickupLocationInput = document.getElementById('assign_pickup_location');
            if (crewAddress && crewAddress.trim() !== '') {
                pickupLocationInput.value = crewAddress.trim();
            } else {
                pickupLocationInput.value = '';
            }
            
            document.getElementById('assignDriverModal').classList.remove('hidden');
        }

        function closeAssignDriverModal() {
            document.getElementById('assignDriverModal').classList.add('hidden');
            document.getElementById('assign_driver_id').value = '';
            document.getElementById('assign_pickup_time').value = '';
            document.getElementById('assign_dropoff_time').value = '';
            document.getElementById('assign_pickup_location').value = '';
            document.getElementById('assign_dropoff_location').value = '';
            document.getElementById('assign_notes').value = '';
            // Reset trip duration section
            document.getElementById('trip_duration_section').classList.add('hidden');
            document.getElementById('trip_duration_text').textContent = '--';
            document.getElementById('trip_distance_text').innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
            document.getElementById('trip_duration_loading').classList.add('hidden');
            document.getElementById('trip_duration_error').classList.add('hidden');
        }

        // Calculate trip duration when dropoff location changes
        let calculateDurationTimeout;
        function calculateTripDuration() {
            const crewUserId = document.getElementById('assign_crew_user_id').value;
            const dropoffLocation = document.getElementById('assign_dropoff_location').value.trim();
            
            const durationSection = document.getElementById('trip_duration_section');
            const durationText = document.getElementById('trip_duration_text');
            const distanceText = document.getElementById('trip_distance_text');
            const loadingDiv = document.getElementById('trip_duration_loading');
            const errorDiv = document.getElementById('trip_duration_error');
            const errorText = document.getElementById('trip_duration_error_text');
            
            // Hide section initially
            durationSection.classList.add('hidden');
            loadingDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');
            
            // Check if both fields are filled
            if (!crewUserId || !dropoffLocation) {
                return;
            }
            
            // Show loading
            durationSection.classList.remove('hidden');
            loadingDiv.classList.remove('hidden');
            durationText.textContent = '--';
            distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
            
            // Clear previous timeout
            if (calculateDurationTimeout) {
                clearTimeout(calculateDurationTimeout);
            }
            
            // Debounce API call (wait 500ms after user stops typing)
            calculateDurationTimeout = setTimeout(() => {
                // Get pickup location
                const pickupLocation = document.getElementById('assign_pickup_location').value || '';
                
                // Prepare form data
                const formData = new FormData();
                formData.append('crew_user_id', crewUserId);
                if (pickupLocation) {
                    formData.append('pickup_location', pickupLocation);
                }
                formData.append('dropoff_location', dropoffLocation);
                
                // Call API
                fetch('api/calculate_trip_duration.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loadingDiv.classList.add('hidden');
                    
                    if (data.success) {
                        errorDiv.classList.add('hidden');
                        durationText.textContent = data.duration_text;
                        distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>' + data.distance_text;
                        
                        // Calculate dropoff time after trip duration is calculated
                        setTimeout(() => {
                            calculateDropoffTime();
                        }, 100);
                    } else {
                        errorDiv.classList.remove('hidden');
                        errorText.textContent = data.error || 'Failed to calculate trip duration';
                        durationText.textContent = '--';
                        distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
                    }
                })
                .catch(error => {
                    loadingDiv.classList.add('hidden');
                    errorDiv.classList.remove('hidden');
                    errorText.textContent = 'Error calculating trip duration: ' + error.message;
                    durationText.textContent = '--';
                    distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
                });
            }, 500);
        }

        // Constants
        const AHOO_BUILDING_LAT = 37.300192413916534;
        const AHOO_BUILDING_LNG = 49.60160649999986;
        
        // Station coordinates cache
        let stationCoordinatesCache = {};
        
        // Function to get station coordinates
        async function getStationCoordinates(iataCode) {
            if (stationCoordinatesCache[iataCode]) {
                return stationCoordinatesCache[iataCode];
            }
            
            try {
                const response = await fetch('api/get_station_by_iata.php?iata=' + encodeURIComponent(iataCode));
                const data = await response.json();
                if (data.success && data.station) {
                    const coords = {
                        lat: parseFloat(data.station.latitude),
                        lng: parseFloat(data.station.longitude)
                    };
                    stationCoordinatesCache[iataCode] = coords;
                    return coords;
                }
            } catch (error) {
                console.error('Error fetching station coordinates:', error);
            }
            return null;
        }
        
        // Function to parse route
        function parseRoute(route) {
            if (!route) return { origin: '', destination: '' };
            const parts = route.split('-');
            return {
                origin: parts[0] ? parts[0].trim() : '',
                destination: parts[1] ? parts[1].trim() : ''
            };
        }
        
        // Function to update locations based on assignment type
        async function updateLocationsBasedOnType() {
            const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value;
            const route = document.getElementById('assign_flight_route').value;
            const crewLat = parseFloat(document.getElementById('assign_crew_latitude').value) || null;
            const crewLng = parseFloat(document.getElementById('assign_crew_longitude').value) || null;
            
            if (!assignmentType || !route) return;
            
            const routeInfo = parseRoute(route);
            const origin = routeInfo.origin;
            const destination = routeInfo.destination;
            
            const pickupLocationInput = document.getElementById('assign_pickup_location');
            const dropoffLocationInput = document.getElementById('assign_dropoff_location');
            const pickupLocationInfo = document.getElementById('pickup_location_info_text');
            const dropoffLocationInfo = document.getElementById('dropoff_location_info_text');
            
            // Update legend visibility
            if (assignmentType === 'to_airport') {
                document.getElementById('legend_to_airport').classList.remove('hidden');
                document.getElementById('legend_to_home').classList.add('hidden');
            } else if (assignmentType === 'to_home') {
                document.getElementById('legend_to_airport').classList.add('hidden');
                document.getElementById('legend_to_home').classList.remove('hidden');
            }
            
            if (assignmentType === 'to_airport') {
                // Taking crew to airport for flight
                if (origin === 'RAS') {
                    // Pickup from Ahoo building
                    pickupLocationInput.value = AHOO_BUILDING_LAT + ',' + AHOO_BUILDING_LNG;
                    pickupLocationInfo.textContent = 'Pickup from Ahoo Building (Rasht)';
                    // Dropoff at RAS station
                    dropoffLocationInput.value = 'RAS';
                    dropoffLocationInfo.textContent = 'Dropoff at Rasht Airport (RAS)';
                } else if (origin === 'THR') {
                    // Pickup from crew's home (user coordinates)
                    if (crewLat && crewLng) {
                        pickupLocationInput.value = crewLat + ',' + crewLng;
                        pickupLocationInfo.textContent = 'Pickup from Crew\'s Home Address';
                    } else {
                        pickupLocationInput.value = '';
                        pickupLocationInfo.textContent = 'Crew home address not available';
                    }
                    // Dropoff at THR station
                    dropoffLocationInput.value = 'THR';
                    dropoffLocationInfo.textContent = 'Dropoff at Tehran Airport (THR)';
                } else {
                    pickupLocationInput.value = '';
                    dropoffLocationInput.value = '';
                    pickupLocationInfo.textContent = 'Origin airport not recognized (RAS/THR)';
                    dropoffLocationInfo.textContent = 'Origin airport not recognized (RAS/THR)';
                }
            } else if (assignmentType === 'to_home') {
                // Taking crew home from airport after flight
                // Pickup Location = destination airport (second airport in route)
                if (destination) {
                    pickupLocationInput.value = destination;
                    pickupLocationInfo.textContent = `Pickup from ${destination} Airport (Destination airport)`;
                } else {
                    pickupLocationInput.value = '';
                    pickupLocationInfo.textContent = 'Destination airport not found in route';
                }
                
                // Dropoff location based on destination
                if (destination === 'RAS') {
                    // Dropoff at Ahoo building
                    dropoffLocationInput.value = AHOO_BUILDING_LAT + ',' + AHOO_BUILDING_LNG;
                    dropoffLocationInfo.textContent = 'Dropoff at Ahoo Building (Rasht)';
                } else if (destination === 'THR') {
                    // Dropoff at crew's home (user coordinates)
                    if (crewLat && crewLng) {
                        dropoffLocationInput.value = crewLat + ',' + crewLng;
                        dropoffLocationInfo.textContent = 'Dropoff at Crew\'s Home Address';
                    } else {
                        dropoffLocationInput.value = '';
                        dropoffLocationInfo.textContent = 'Crew home address not available';
                    }
                } else {
                    // For other destinations, dropoff at crew's home
                    if (crewLat && crewLng) {
                        dropoffLocationInput.value = crewLat + ',' + crewLng;
                        dropoffLocationInfo.textContent = 'Dropoff at Crew\'s Home Address';
                    } else {
                        dropoffLocationInput.value = '';
                        dropoffLocationInfo.textContent = 'Crew home address not available';
                    }
                }
            }
            
            // Calculate trip duration if locations are set
            if (pickupLocationInput.value && dropoffLocationInput.value) {
                setTimeout(() => {
                    calculateTripDuration();
                    // Pickup time and dropoff time will be calculated after trip duration is ready
                    setTimeout(() => {
                        calculatePickupTime();
                    }, 700);
                }, 100);
            }
        }
        
        // Function to calculate pickup time
        async function calculatePickupTime() {
            const taskStart = document.getElementById('assign_task_start').value;
            const taskEnd = document.getElementById('assign_task_end').value;
            const minutes1 = parseInt(document.getElementById('assign_minutes_1').value) || 0;
            const minutes2 = parseInt(document.getElementById('assign_minutes_2').value) || 0;
            const minutes3 = parseInt(document.getElementById('assign_minutes_3').value) || 0;
            const minutes4 = parseInt(document.getElementById('assign_minutes_4').value) || 0;
            const minutes5 = parseInt(document.getElementById('assign_minutes_5').value) || 0;
            const route = document.getElementById('assign_flight_route').value;
            const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value;
            
            const routeInfo = parseRoute(route);
            const origin = routeInfo.origin;
            const destination = routeInfo.destination;
            
            // Handle "to_home" assignment type
            if (assignmentType === 'to_home') {
                if (!taskEnd) {
                    document.getElementById('pickup_time_calculation_info').classList.add('hidden');
                    return;
                }
                
                // Pickup Time = TaskEnd + 45 minutes
                const taskEndDate = new Date(taskEnd);
                if (isNaN(taskEndDate.getTime())) {
                    document.getElementById('pickup_time_calculation_info').classList.add('hidden');
                    return;
                }
                
                const pickupTime = new Date(taskEndDate);
                pickupTime.setMinutes(pickupTime.getMinutes() + 45);
                
                // Format as HH:mm
                const hours = String(pickupTime.getHours()).padStart(2, '0');
                const minutes = String(pickupTime.getMinutes()).padStart(2, '0');
                const pickupTimeString = hours + ':' + minutes;
                
                // Set pickup time
                document.getElementById('assign_pickup_time').value = pickupTimeString;
                
                // Show calculation info
                const formulaText = `Formula: TaskEnd + 45 min = ${pickupTimeString}`;
                document.getElementById('pickup_time_formula_text').textContent = formulaText;
                document.getElementById('pickup_time_calculation_info').classList.remove('hidden');
                
                // Calculate dropoff time for "to_home" assignment type
                // Wait a bit to ensure trip duration is calculated first
                setTimeout(() => {
                    calculateDropoffTime();
                }, 200);
                return;
            }
            
            // Handle "to_airport" assignment type
            // Only calculate if flight starts from RAS or THR and assignment type is "to_airport"
            if (!taskStart || assignmentType !== 'to_airport' || (origin !== 'RAS' && origin !== 'THR')) {
                document.getElementById('pickup_time_calculation_info').classList.add('hidden');
                return;
            }
            
            // Get trip duration from the trip duration section
            const tripDurationText = document.getElementById('trip_duration_text').textContent.trim();
            if (tripDurationText === '--' || !tripDurationText) {
                // Try to get trip duration from API
                const pickupLocation = document.getElementById('assign_pickup_location').value;
                const dropoffLocation = document.getElementById('assign_dropoff_location').value;
                
                if (pickupLocation && dropoffLocation) {
                    // Wait for trip duration calculation
                    setTimeout(calculatePickupTime, 500);
                    return;
                }
            }
            
            // Parse trip duration from text (e.g., "1h 30m" or "45m")
            let tripDurationMinutes = 0;
            if (tripDurationText && tripDurationText !== '--') {
                const hoursMatch = tripDurationText.match(/(\d+)h/);
                const minutesMatch = tripDurationText.match(/(\d+)m/);
                if (hoursMatch) tripDurationMinutes += parseInt(hoursMatch[1]) * 60;
                if (minutesMatch) tripDurationMinutes += parseInt(minutesMatch[1]);
            }
            
            // Calculate: (TaskStart + sum(minutes_1 to minutes_5)) - ((Estimated Trip Duration + 60) + (Estimated Trip Duration + 60) × 30%)
            const totalDelayMinutes = minutes1 + minutes2 + minutes3 + minutes4 + minutes5;
            const baseDuration = tripDurationMinutes + 60;
            const adjustedTripDuration = baseDuration + (baseDuration * 0.3);
            const deductionMinutes = Math.round(adjustedTripDuration);
            
            // Parse TaskStart
            const taskStartDate = new Date(taskStart);
            if (isNaN(taskStartDate.getTime())) {
                document.getElementById('pickup_time_calculation_info').classList.add('hidden');
                return;
            }
            
            // Add delay minutes
            const taskStartWithDelay = new Date(taskStartDate);
            taskStartWithDelay.setMinutes(taskStartWithDelay.getMinutes() + totalDelayMinutes);
            
            // Subtract adjusted trip duration
            const pickupTime = new Date(taskStartWithDelay);
            pickupTime.setMinutes(pickupTime.getMinutes() - deductionMinutes);
            
            // Format as HH:mm
            const hours = String(pickupTime.getHours()).padStart(2, '0');
            const minutes = String(pickupTime.getMinutes()).padStart(2, '0');
            const pickupTimeString = hours + ':' + minutes;
            
            // Set pickup time
            document.getElementById('assign_pickup_time').value = pickupTimeString;
            
            // Show calculation info
            const formulaText = `Formula: (TaskStart + ${totalDelayMinutes} min delay) - ((${tripDurationMinutes} min + 60 min) + (${tripDurationMinutes} min + 60 min) × 30%) = ${pickupTimeString}`;
            document.getElementById('pickup_time_formula_text').textContent = formulaText;
            document.getElementById('pickup_time_calculation_info').classList.remove('hidden');
            
            // Calculate dropoff time for "to_airport" assignment type
            calculateDropoffTime();
        }
        
        // Function to calculate dropoff time
        function calculateDropoffTime() {
            const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value;
            
            // Calculate for both "to_airport" and "to_home" assignment types
            if (assignmentType !== 'to_airport' && assignmentType !== 'to_home') {
                return;
            }
            
            // Get pickup time
            const pickupTimeValue = document.getElementById('assign_pickup_time').value;
            if (!pickupTimeValue) {
                return;
            }
            
            // Get trip duration from the trip duration section
            const tripDurationText = document.getElementById('trip_duration_text').textContent.trim();
            if (tripDurationText === '--' || !tripDurationText) {
                return;
            }
            
            // Parse trip duration from text (e.g., "1h 30m" or "45m" or "12m")
            let tripDurationMinutes = 0;
            if (tripDurationText && tripDurationText !== '--') {
                const hoursMatch = tripDurationText.match(/(\d+)h/);
                const minutesMatch = tripDurationText.match(/(\d+)m/);
                if (hoursMatch) tripDurationMinutes += parseInt(hoursMatch[1]) * 60;
                if (minutesMatch) tripDurationMinutes += parseInt(minutesMatch[1]);
            }
            
            if (tripDurationMinutes === 0) {
                return;
            }
            
            // Calculate: Dropoff Time = Pickup Time + Estimated Trip Duration + (Estimated Trip Duration × 30%)
            const riskMinutes = Math.round(tripDurationMinutes * 0.3);
            const totalDurationMinutes = tripDurationMinutes + riskMinutes;
            
            // Parse pickup time (format: HH:mm)
            const [pickupHours, pickupMinutes] = pickupTimeValue.split(':').map(Number);
            
            // Validate pickup time
            if (isNaN(pickupHours) || isNaN(pickupMinutes) || pickupHours < 0 || pickupHours > 23 || pickupMinutes < 0 || pickupMinutes > 59) {
                return;
            }
            
            // Calculate dropoff time in minutes from midnight
            const pickupTotalMinutes = pickupHours * 60 + pickupMinutes;
            const dropoffTotalMinutes = pickupTotalMinutes + totalDurationMinutes;
            
            // Handle next day (if dropoff time exceeds 24 hours)
            const dropoffHours = Math.floor(dropoffTotalMinutes / 60) % 24;
            const dropoffMinutes = dropoffTotalMinutes % 60;
            
            // Format as HH:mm
            const dropoffTimeString = String(dropoffHours).padStart(2, '0') + ':' + String(dropoffMinutes).padStart(2, '0');
            
            // Set dropoff time
            document.getElementById('assign_dropoff_time').value = dropoffTimeString;
            
            // Debug log (can be removed later)
            console.log('Dropoff Time Calculation:', {
                pickupTime: pickupTimeValue,
                tripDurationText: tripDurationText,
                tripDurationMinutes: tripDurationMinutes,
                riskMinutes: riskMinutes,
                totalDurationMinutes: totalDurationMinutes,
                pickupTotalMinutes: pickupTotalMinutes,
                dropoffTotalMinutes: dropoffTotalMinutes,
                dropoffTime: dropoffTimeString
            });
        }
        
        // Function to update assignment type visibility based on route
        function updateAssignmentTypeVisibility() {
            const route = document.getElementById('assign_flight_route').value;
            const routeInfo = parseRoute(route);
            const origin = routeInfo.origin;
            const destination = routeInfo.destination;
            
            const toAirportLabel = document.getElementById('label_to_airport');
            const toHomeLabel = document.getElementById('label_to_home');
            const toAirportRadio = document.getElementById('assignment_type_to_airport');
            const toHomeRadio = document.getElementById('assignment_type_to_home');
            const assignmentTypeInfo = document.getElementById('assignment_type_info_text');
            
            // Check if route starts from RAS or THR
            const canGoToAirport = (origin === 'RAS' || origin === 'THR');
            // Check if route ends at RAS or THR
            const canGoHome = (destination === 'RAS' || destination === 'THR');
            
            // Show/hide "to_airport" option
            if (canGoToAirport) {
                toAirportLabel.classList.remove('hidden');
            } else {
                toAirportLabel.classList.add('hidden');
                if (toAirportRadio.checked) {
                    toAirportRadio.checked = false;
                }
            }
            
            // Show/hide "to_home" option
            if (canGoHome) {
                toHomeLabel.classList.remove('hidden');
            } else {
                toHomeLabel.classList.add('hidden');
                if (toHomeRadio.checked) {
                    toHomeRadio.checked = false;
                }
            }
            
            // Set default selection
            if (canGoToAirport && !canGoHome) {
                toAirportRadio.checked = true;
                assignmentTypeInfo.textContent = 'Route starts from ' + origin + ' - Can take crew to airport';
            } else if (!canGoToAirport && canGoHome) {
                toHomeRadio.checked = true;
                assignmentTypeInfo.textContent = 'Route ends at ' + destination + ' - Can take crew home';
            } else if (canGoToAirport && canGoHome) {
                toAirportRadio.checked = true;
                assignmentTypeInfo.textContent = 'Route: ' + origin + ' → ' + destination + ' - Both options available';
            } else {
                assignmentTypeInfo.textContent = 'Route does not start or end at RAS/THR - No assignment available';
            }
            
            // Update locations and legend based on selected type
            updateLocationsBasedOnType();
        }
        
        // Add event listeners when modal opens
        function openAssignDriverModal(flightId, crewUserId, crewPosition, assignmentDate, crewAddress, flightRoute, taskStart, taskEnd, minutes1, minutes2, minutes3, minutes4, minutes5, crewLat, crewLng) {
            document.getElementById('assign_flight_id').value = flightId;
            document.getElementById('assign_crew_user_id').value = crewUserId;
            document.getElementById('assign_crew_position').value = crewPosition;
            document.getElementById('assign_assignment_date').value = assignmentDate;
            document.getElementById('assign_flight_route').value = flightRoute || '';
            document.getElementById('assign_task_start').value = taskStart || '';
            document.getElementById('assign_task_end').value = taskEnd || '';
            document.getElementById('assign_minutes_1').value = minutes1 || 0;
            document.getElementById('assign_minutes_2').value = minutes2 || 0;
            document.getElementById('assign_minutes_3').value = minutes3 || 0;
            document.getElementById('assign_minutes_4').value = minutes4 || 0;
            document.getElementById('assign_minutes_5').value = minutes5 || 0;
            document.getElementById('assign_crew_latitude').value = crewLat || '';
            document.getElementById('assign_crew_longitude').value = crewLng || '';
            
            // Update assignment type visibility based on route
            updateAssignmentTypeVisibility();
            
            // Reset pickup time and calculation info
            document.getElementById('assign_pickup_time').value = '';
            document.getElementById('pickup_time_calculation_info').classList.add('hidden');
            
            // Reset location info texts
            document.getElementById('pickup_location_info_text').textContent = 'Location will be set automatically based on assignment type';
            document.getElementById('dropoff_location_info_text').textContent = 'Location will be set automatically based on assignment type';
            
            // Reset trip duration section
            document.getElementById('trip_duration_section').classList.add('hidden');
            document.getElementById('trip_duration_text').textContent = '--';
            document.getElementById('trip_distance_text').innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
            document.getElementById('trip_duration_loading').classList.add('hidden');
            document.getElementById('trip_duration_error').classList.add('hidden');
            
            // Remove existing event listeners and add new ones
            const dropoffLocationInput = document.getElementById('assign_dropoff_location');
            const newDropoffInput = dropoffLocationInput.cloneNode(true);
            dropoffLocationInput.parentNode.replaceChild(newDropoffInput, dropoffLocationInput);
            
            // Add event listeners for dropoff location change
            document.getElementById('assign_dropoff_location').addEventListener('input', function() {
                calculateTripDuration();
                setTimeout(() => {
                    calculatePickupTime();
                    setTimeout(calculateDropoffTime, 100);
                }, 600);
            });
            document.getElementById('assign_dropoff_location').addEventListener('change', function() {
                calculateTripDuration();
                setTimeout(() => {
                    calculatePickupTime();
                    setTimeout(calculateDropoffTime, 100);
                }, 600);
            });
            
            // Add event listener for assignment type change
            document.querySelectorAll('input[name="assignment_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'to_airport' || this.value === 'to_home') {
                        setTimeout(() => {
                            calculatePickupTime();
                            setTimeout(calculateDropoffTime, 200);
                        }, 100);
                    } else {
                        // Clear times for other types
                        document.getElementById('assign_pickup_time').value = '';
                        document.getElementById('assign_dropoff_time').value = '';
                    }
                });
            });
            
            // Add event listener for pickup time change (manual input)
            const pickupTimeInput = document.getElementById('assign_pickup_time');
            const newPickupTimeInput = pickupTimeInput.cloneNode(true);
            pickupTimeInput.parentNode.replaceChild(newPickupTimeInput, pickupTimeInput);
            document.getElementById('assign_pickup_time').addEventListener('change', function() {
                const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value;
                if (assignmentType === 'to_airport' || assignmentType === 'to_home') {
                    calculateDropoffTime();
                }
            });
            document.getElementById('assign_pickup_time').addEventListener('input', function() {
                const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value;
                if (assignmentType === 'to_airport' || assignmentType === 'to_home') {
                    calculateDropoffTime();
                }
            });
            
            document.getElementById('assignDriverModal').classList.remove('hidden');
        }

        // Edit Assignment Modal Functions
        function openEditAssignmentModal(flightId, crewUserId, driverId, assignmentDate, pickupTime, dropoffTime, pickupLocation, dropoffLocation, notes, crewAddress, crewPosition) {
            document.getElementById('edit_flight_id').value = flightId;
            document.getElementById('edit_crew_user_id').value = crewUserId;
            document.getElementById('edit_driver_id').value = driverId;
            document.getElementById('edit_assignment_date').value = assignmentDate;
            document.getElementById('edit_crew_position').value = crewPosition || 'Crew1';
            
            // Set form values
            document.getElementById('edit_pickup_time').value = pickupTime || '';
            document.getElementById('edit_dropoff_time').value = dropoffTime || '';
            document.getElementById('edit_pickup_location').value = pickupLocation || crewAddress || '';
            document.getElementById('edit_dropoff_location').value = dropoffLocation || '';
            document.getElementById('edit_notes').value = notes || '';
            
            // Reset trip duration section
            document.getElementById('edit_trip_duration_section').classList.add('hidden');
            document.getElementById('edit_trip_duration_text').textContent = '--';
            document.getElementById('edit_trip_distance_text').innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
            document.getElementById('edit_trip_duration_loading').classList.add('hidden');
            document.getElementById('edit_trip_duration_error').classList.add('hidden');
            
            // Remove existing event listeners and add new ones
            const dropoffLocationInput = document.getElementById('edit_dropoff_location');
            const newDropoffInput = dropoffLocationInput.cloneNode(true);
            dropoffLocationInput.parentNode.replaceChild(newDropoffInput, dropoffLocationInput);
            
            // Add event listeners for dropoff location change
            document.getElementById('edit_dropoff_location').addEventListener('input', calculateEditTripDuration);
            document.getElementById('edit_dropoff_location').addEventListener('change', calculateEditTripDuration);
            
            // If dropoff location already has a value, calculate immediately
            if (document.getElementById('edit_dropoff_location').value.trim()) {
                calculateEditTripDuration();
            }
            
            document.getElementById('editAssignmentModal').classList.remove('hidden');
        }

        function closeEditAssignmentModal() {
            document.getElementById('editAssignmentModal').classList.add('hidden');
            document.getElementById('edit_pickup_time').value = '';
            document.getElementById('edit_dropoff_time').value = '';
            document.getElementById('edit_pickup_location').value = '';
            document.getElementById('edit_dropoff_location').value = '';
            document.getElementById('edit_notes').value = '';
            // Reset trip duration section
            document.getElementById('edit_trip_duration_section').classList.add('hidden');
            document.getElementById('edit_trip_duration_text').textContent = '--';
            document.getElementById('edit_trip_distance_text').innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
            document.getElementById('edit_trip_duration_loading').classList.add('hidden');
            document.getElementById('edit_trip_duration_error').classList.add('hidden');
        }

        // Calculate trip duration for edit modal
        let calculateEditDurationTimeout;
        function calculateEditTripDuration() {
            const crewUserId = document.getElementById('edit_crew_user_id').value;
            const dropoffLocation = document.getElementById('edit_dropoff_location').value.trim();
            
            const durationSection = document.getElementById('edit_trip_duration_section');
            const durationText = document.getElementById('edit_trip_duration_text');
            const distanceText = document.getElementById('edit_trip_distance_text');
            const loadingDiv = document.getElementById('edit_trip_duration_loading');
            const errorDiv = document.getElementById('edit_trip_duration_error');
            const errorText = document.getElementById('edit_trip_duration_error_text');
            
            // Hide section initially
            durationSection.classList.add('hidden');
            loadingDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');
            
            // Check if both fields are filled
            if (!crewUserId || !dropoffLocation) {
                return;
            }
            
            // Show loading
            durationSection.classList.remove('hidden');
            loadingDiv.classList.remove('hidden');
            durationText.textContent = '--';
            distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
            
            // Clear previous timeout
            if (calculateEditDurationTimeout) {
                clearTimeout(calculateEditDurationTimeout);
            }
            
            // Debounce API call (wait 500ms after user stops typing)
            calculateEditDurationTimeout = setTimeout(() => {
                // Prepare form data
                const formData = new FormData();
                formData.append('crew_user_id', crewUserId);
                formData.append('dropoff_location', dropoffLocation);
                
                // Call API
                fetch('api/calculate_trip_duration.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loadingDiv.classList.add('hidden');
                    
                    if (data.success) {
                        errorDiv.classList.add('hidden');
                        durationText.textContent = data.duration_text;
                        distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>' + data.distance_text;
                    } else {
                        errorDiv.classList.remove('hidden');
                        errorText.textContent = data.error || 'Failed to calculate trip duration';
                        durationText.textContent = '--';
                        distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
                    }
                })
                .catch(error => {
                    loadingDiv.classList.add('hidden');
                    errorDiv.classList.remove('hidden');
                    errorText.textContent = 'Error calculating trip duration: ' + error.message;
                    durationText.textContent = '--';
                    distanceText.innerHTML = '<i class="fas fa-arrows-alt-h mr-1"></i>--';
                });
            }, 500);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const assignModal = document.getElementById('assignDriverModal');
            const editModal = document.getElementById('editAssignmentModal');
            if (event.target === assignModal) {
                closeAssignDriverModal();
            }
            if (event.target === editModal) {
                closeEditAssignmentModal();
            }
        }
        
        // Hover effect for flight cards - highlight all crew member cards
        document.addEventListener('DOMContentLoaded', function() {
            const flightCards = document.querySelectorAll('.flight-card');
            
            flightCards.forEach(function(flightCard) {
                flightCard.addEventListener('mouseenter', function() {
                    // Find all crew member cards within this flight card
                    const crewCards = flightCard.querySelectorAll('.crew-member-card');
                    crewCards.forEach(function(crewCard) {
                        crewCard.classList.add('bg-gray-100', 'dark:bg-gray-600');
                        crewCard.classList.remove('bg-gray-50', 'dark:bg-gray-700');
                    });
                    // Also highlight the flight card itself
                    flightCard.classList.add('bg-gray-50', 'dark:bg-gray-700');
                });
                
                flightCard.addEventListener('mouseleave', function() {
                    // Find all crew member cards within this flight card
                    const crewCards = flightCard.querySelectorAll('.crew-member-card');
                    crewCards.forEach(function(crewCard) {
                        crewCard.classList.remove('bg-gray-100', 'dark:bg-gray-600');
                        crewCard.classList.add('bg-gray-50', 'dark:bg-gray-700');
                    });
                    // Remove highlight from flight card
                    flightCard.classList.remove('bg-gray-50', 'dark:bg-gray-700');
                });
            });
        });
    </script>
</body>
</html>

