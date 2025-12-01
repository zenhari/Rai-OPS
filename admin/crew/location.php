<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/crew/location.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get date parameters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDateFormatted = date('l, M j, Y', strtotime($selectedDate));

// Get crew location data for the selected date
$crewLocations = getCrewLocationsForDate($selectedDate);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crew Location - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Crew Location</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Track crew member locations at end of day</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <label for="date-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">Date:</label>
                                <input type="date" id="date-select" value="<?php echo $selectedDate; ?>" 
                                       class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                       onchange="changeDate(this.value)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Date Header -->
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
                    <h2 class="text-xl font-semibold text-blue-900 dark:text-blue-100">
                        <i class="fas fa-calendar-day mr-2"></i>
                        Crew Locations for <?php echo $selectedDateFormatted; ?>
                    </h2>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        Showing where each crew member ends up at the end of the day
                    </p>
                </div>

                <?php if (empty($crewLocations)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8 text-center">
                        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-gray-100 dark:bg-gray-700 rounded-full">
                            <i class="fas fa-users text-gray-400 dark:text-gray-500 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Crew Activity Found</h3>
                        <p class="text-gray-500 dark:text-gray-400">No crew members were assigned to flights on <?php echo $selectedDateFormatted; ?>.</p>
                    </div>
                <?php else: ?>
                    <!-- Crew Locations Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($crewLocations as $crewMember): ?>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                            <!-- Crew Member Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 flex-shrink-0">
                                        <?php if (!empty($crewMember['picture']) && file_exists(__DIR__ . '/../../' . $crewMember['picture'])): ?>
                                            <img class="w-10 h-10 rounded-full object-cover border-2 border-blue-200 dark:border-blue-700" 
                                                 src="<?php echo getProfileImageUrl($crewMember['picture']); ?>" 
                                                 alt="<?php echo htmlspecialchars($crewMember['first_name'] . ' ' . $crewMember['last_name']); ?>">
                                        <?php else: ?>
                                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center border-2 border-blue-200 dark:border-blue-700">
                                                <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($crewMember['first_name'] . ' ' . $crewMember['last_name']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($crewMember['position'] ?? 'N/A'); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo count($crewMember['flights']); ?> flight(s)
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Timeline -->
                            <div class="space-y-3 mb-4">
                                <?php foreach ($crewMember['flights'] as $index => $flight): ?>
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                            <span class="text-xs font-medium text-blue-600 dark:text-blue-400"><?php echo $index + 1; ?></span>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($flight['flight_no']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($flight['role']); ?>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-300">
                                            <i class="fas fa-plane text-xs mr-1"></i>
                                            <?php echo htmlspecialchars($flight['origin']); ?> → <?php echo htmlspecialchars($flight['destination']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Final Location -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-map-marker-alt text-green-500"></i>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Final Location:</span>
                                    </div>
                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">
                                        <?php echo htmlspecialchars($crewMember['final_location']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Summary -->
                    <div class="mt-8 bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    <?php echo count($crewLocations); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Active Crew Members</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    <?php echo array_sum(array_map(function($crew) { return count($crew['flights']); }, $crewLocations)); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Total Flights</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                    <?php echo count(array_unique(array_column($crewLocations, 'final_location'))); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Different Locations</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function changeDate(date) {
            window.location.href = '?date=' + date;
        }
    </script>
</body>
</html>
