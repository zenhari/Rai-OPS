<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/crew_list.php');

$user = getCurrentUser();

// Get date parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get crew routes data
$crewRoutes = getCrewRoutesByDate($startDate, $endDate);
$crewStats = getCrewStatistics($startDate, $endDate);

// Format dates for display
$startDateFormatted = date('M j, Y', strtotime($startDate));
$endDateFormatted = date('M j, Y', strtotime($endDate));
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crew List - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
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
    </script>
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .crew-route { 
            font-family: 'Courier New', monospace; 
            background: linear-gradient(90deg, #61207f, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="flex flex-col min-h-screen">
        <!-- Include Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                Crew List
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Daily crew routes and flight patterns
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <label for="start-date" class="text-sm font-medium text-gray-700 dark:text-gray-300">From:</label>
                                <input type="date" id="start-date" value="<?php echo $startDate; ?>" 
                                       class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                       onchange="changeDateRange()">
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="end-date" class="text-sm font-medium text-gray-700 dark:text-gray-300">To:</label>
                                <input type="date" id="end-date" value="<?php echo $endDate; ?>" 
                                       class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                       onchange="changeDateRange()">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar-day text-blue-600 dark:text-blue-400 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Days Covered
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            <?php echo $crewStats['total_days']; ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users text-green-600 dark:text-green-400 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Total Crew Members
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            <?php echo $crewStats['total_crew_members']; ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-route text-purple-600 dark:text-purple-400 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Unique Routes
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            <?php echo count($crewStats['unique_routes']); ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-layer-group text-orange-600 dark:text-orange-400 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Crew Groups
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            <?php echo $crewStats['total_crew_groups']; ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crew Routes by Date -->
                <?php 
                // Helper functions for crew processing
                if (!function_exists('getFlightCrew')) {
                    function getFlightCrew($flight, $crewNames) {
                        $crew = [];
                        for ($i = 1; $i <= 10; $i++) {
                            $crewField = "Crew{$i}";
                            $roleField = "Crew{$i}_role";
                            if (!empty($flight[$crewField])) {
                                $crewId = $flight[$crewField];
                                $crewName = $crewNames[$crewId] ?? "ID: {$crewId}";
                                $crew[] = [
                                    'id' => $crewId,
                                    'name' => $crewName,
                                    'role' => $flight[$roleField] ?? "Crew{$i}"
                                ];
                            }
                        }
                        // Sort by ID to ensure consistent comparison
                        usort($crew, function($a, $b) {
                            return $a['id'] - $b['id'];
                        });
                        return $crew;
                    }
                }
                
                if (!function_exists('sameCrew')) {
                    function sameCrew($crew1, $crew2) {
                        if (count($crew1) !== count($crew2)) {
                            return false;
                        }
                        $ids1 = array_column($crew1, 'id');
                        $ids2 = array_column($crew2, 'id');
                        sort($ids1);
                        sort($ids2);
                        return $ids1 === $ids2;
                    }
                }
                ?>
                
                <?php if (!empty($crewRoutes)): ?>
                    <?php foreach ($crewRoutes as $date => $crewMembers): ?>
                        <?php 
                        $dateFormatted = date('l, M j, Y', strtotime($date));
                        $dateStats = $crewStats['crew_by_date'][$date] ?? [];
                        ?>
                        
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <?php echo $dateFormatted; ?>
                                    </h3>
                                    <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                        <span><i class="fas fa-users mr-1"></i><?php echo $dateStats['crew_count'] ?? 0; ?> crew members</span>
                                        <span><i class="fas fa-route mr-1"></i><?php echo count($dateStats['unique_routes'] ?? []); ?> routes</span>
                                        <span><i class="fas fa-layer-group mr-1"></i><?php echo $dateStats['crew_groups'] ?? 0; ?> groups</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <!-- Group flights by continuous routes with same crew members -->
                                <?php 
                                // Get all flights for this date with crew members
                                $db = getDBConnection();
                                $stmt = $db->prepare("
                                    SELECT 
                                        f.id,
                                        f.FltDate,
                                        f.Route,
                                        f.TaskStart,
                                        f.TaskEnd,
                                        f.TaskName,
                                        f.FlightNo,
                                        f.Rego,
                                        f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                                        f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                                        f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                                        f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
                                    FROM flights f
                                    WHERE DATE(f.FltDate) = ?
                                    AND (
                                        f.Crew1 IS NOT NULL OR f.Crew2 IS NOT NULL OR f.Crew3 IS NOT NULL OR 
                                        f.Crew4 IS NOT NULL OR f.Crew5 IS NOT NULL OR f.Crew6 IS NOT NULL OR 
                                        f.Crew7 IS NOT NULL OR f.Crew8 IS NOT NULL OR f.Crew9 IS NOT NULL OR 
                                        f.Crew10 IS NOT NULL
                                    )
                                    ORDER BY f.TaskStart ASC
                                ");
                                $stmt->execute([$date]);
                                $allFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Get crew names
                                $crewIds = [];
                                foreach ($allFlights as $flight) {
                                    for ($i = 1; $i <= 10; $i++) {
                                        $crewField = "Crew{$i}";
                                        if (!empty($flight[$crewField])) {
                                            $crewIds[$flight[$crewField]] = true;
                                        }
                                    }
                                }
                                
                                $crewNames = [];
                                $crewNationalIds = [];
                                $crewPictures = [];
                                if (!empty($crewIds)) {
                                    $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
                                    $stmt = $db->prepare("
                                        SELECT id, CONCAT(first_name, ' ', last_name) as name, national_id, picture
                                        FROM users
                                        WHERE id IN ($placeholders)
                                    ");
                                    $stmt->execute(array_keys($crewIds));
                                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($users as $user) {
                                        $crewNames[$user['id']] = $user['name'];
                                        $crewNationalIds[$user['id']] = $user['national_id'] ?? '';
                                        $crewPictures[$user['id']] = $user['picture'] ?? '';
                                    }
                                }
                                
                                // Group flights by same crew members (regardless of route continuity)
                                $manifestGroups = [];
                                
                                // First, group all flights by their crew composition
                                $crewGroups = [];
                                foreach ($allFlights as $flight) {
                                    $flightCrew = getFlightCrew($flight, $crewNames);
                                    
                                    if (empty($flightCrew)) {
                                        continue;
                                    }
                                    
                                    // Create a unique key for this crew composition
                                    $crewIds = array_column($flightCrew, 'id');
                                    sort($crewIds);
                                    $crewKey = implode('-', $crewIds);
                                    
                                    if (!isset($crewGroups[$crewKey])) {
                                        $crewGroups[$crewKey] = [
                                            'crew' => $flightCrew,
                                            'flights' => [],
                                            'routes' => []
                                        ];
                                    }
                                    
                                    $crewGroups[$crewKey]['flights'][] = $flight;
                                    $currentRoute = $flight['Route'] ?? '';
                                    if (!empty($currentRoute) && !in_array($currentRoute, $crewGroups[$crewKey]['routes'])) {
                                        $crewGroups[$crewKey]['routes'][] = $currentRoute;
                                    }
                                    }
                                    
                                // Convert crew groups to manifest groups
                                foreach ($crewGroups as $crewKey => $crewGroup) {
                                    if (empty($crewGroup['flights'])) {
                                        continue;
                                    }
                                    
                                    // Sort flights by TaskStart
                                    usort($crewGroup['flights'], function($a, $b) {
                                        $timeA = strtotime($a['TaskStart'] ?? '');
                                        $timeB = strtotime($b['TaskStart'] ?? '');
                                        return $timeA - $timeB;
                                    });
                                    
                                    // Get first and last task times
                                    $firstFlight = $crewGroup['flights'][0];
                                    $lastFlight = end($crewGroup['flights']);
                                    $firstTaskStart = $firstFlight['TaskStart'] ?? '';
                                    $lastTaskEnd = $lastFlight['TaskEnd'] ?? '';
                                    
                                    // Create continuous route from all routes
                                    $continuousRoute = createContinuousRoute($crewGroup['routes']);
                                    if (empty($continuousRoute)) {
                                        $continuousRoute = implode(', ', $crewGroup['routes']);
                                    }
                                    
                                    // Create unique manifest key
                                    $manifestKey = $continuousRoute . '_' . $crewKey;
                                    
                                    $manifestGroups[$manifestKey] = [
                                        'route' => $continuousRoute,
                                        'crew_members' => array_map(function($c) use ($crewNationalIds, $crewPictures) {
                                            return [
                                                'id' => $c['id'],
                                                'name' => $c['name'],
                                                'role' => $c['role'] ?? '',
                                                'national_id' => $crewNationalIds[$c['id']] ?? '',
                                                'picture' => $crewPictures[$c['id']] ?? '',
                                                'data' => [
                                                    'flights' => [],
                                                    'routes' => []
                                                ]
                                            ];
                                        }, $crewGroup['crew']),
                                        'flights' => $crewGroup['flights'],
                                        'first_task_start' => $firstTaskStart,
                                        'last_task_end' => $lastTaskEnd
                                    ];
                                }
                                
                                // Update crew_members data with their flights
                                foreach ($manifestGroups as $manifestRoute => &$manifestGroup) {
                                    foreach ($manifestGroup['crew_members'] as &$member) {
                                        $member['data']['flights'] = array_filter($manifestGroup['flights'], function($f) use ($member, $crewNames) {
                                            $flightCrew = getFlightCrew($f, $crewNames);
                                            $crewIds = array_column($flightCrew, 'id');
                                            return in_array($member['id'], $crewIds);
                                        });
                                        $member['data']['flights'] = array_values($member['data']['flights']);
                                        $member['data']['routes'] = array_unique(array_column($member['data']['flights'], 'Route'));
                                    }
                                    unset($member);
                                }
                                unset($manifestGroup);
                                ?>
                                
                                <?php foreach ($manifestGroups as $manifestRoute => $manifestGroup): ?>
                                    <?php 
                                    // Get TaskStart from first flight and TaskEnd from last flight
                                    $firstFlight = !empty($manifestGroup['flights']) ? $manifestGroup['flights'][0] : null;
                                    $lastFlight = !empty($manifestGroup['flights']) ? end($manifestGroup['flights']) : null;
                                    $taskStart = $firstFlight ? ($firstFlight['TaskStart'] ?? '') : '';
                                    $taskEnd = $lastFlight ? ($lastFlight['TaskEnd'] ?? '') : '';
                                    ?>
                                    <?php 
                                    // Get aircraft registration (Rego) from first flight
                                    $aircraftRego = 'N/A';
                                    if (!empty($manifestGroup['flights']) && !empty($manifestGroup['flights'][0]['Rego'])) {
                                        $aircraftRego = $manifestGroup['flights'][0]['Rego'];
                                    }
                                    ?>
                                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg" 
                                         data-route="<?php echo htmlspecialchars($manifestGroup['route']); ?>"
                                         data-task-start="<?php echo htmlspecialchars($taskStart); ?>"
                                         data-task-end="<?php echo htmlspecialchars($taskEnd); ?>"
                                         data-aircraft-rego="<?php echo htmlspecialchars($aircraftRego); ?>">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-md font-semibold text-gray-900 dark:text-white">
                                                <i class="fas fa-route mr-2 text-blue-600 dark:text-blue-400"></i>
                                                Manifest Route: <span class="crew-route font-mono"><?php echo htmlspecialchars($manifestGroup['route']); ?></span>
                                            </h4>
                                            <div class="flex items-center space-x-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <?php echo count($manifestGroup['crew_members']); ?> crew members
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    <?php echo count($manifestGroup['flights']); ?> flights
                                                </span>
                                                <button onclick="showManifestDetails('<?php echo htmlspecialchars($manifestGroup['route']); ?>', '<?php echo $date; ?>')" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                                    <i class="fas fa-file-alt mr-1"></i>
                                                    Generate Manifest
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Show flight numbers for this manifest -->
                                        <?php 
                                        // Get unique flight numbers from TaskName field
                                        $flightNumbers = [];
                                        foreach ($manifestGroup['flights'] as $flight) {
                                            $taskName = !empty($flight['TaskName']) && $flight['TaskName'] !== 'N/A' ? trim($flight['TaskName']) : null;
                                            if ($taskName && !in_array($taskName, $flightNumbers)) {
                                                $flightNumbers[] = $taskName;
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($flightNumbers)): ?>
                                            <div class="mb-4 p-3 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-600" data-flight-numbers>
                                                <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                    <i class="fas fa-hashtag mr-1"></i>Flight Numbers:
                                                </h5>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($flightNumbers as $flightNo): ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold font-mono bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800">
                                                            RAI<?php echo htmlspecialchars($flightNo); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Show all flights for this manifest -->
                                        <div class="mb-4 p-3 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-600" data-flights>
                                            <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="fas fa-plane mr-1"></i>Flights in this manifest:
                                            </h5>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($manifestGroup['flights'] as $flight): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                        <?php 
                                                        $flightNo = !empty($flight['FlightNo']) && $flight['FlightNo'] !== 'N/A' ? $flight['FlightNo'] : '';
                                                        $route = htmlspecialchars($flight['Route'] ?: 'N/A');
                                                        $taskStart = date('H:i', strtotime($flight['TaskStart']));
                                                        ?>
                                                        <?php if ($flightNo): ?>
                                                            <span class="font-semibold"><?php echo htmlspecialchars($flightNo); ?></span>
                                                            <span class="mx-1">-</span>
                                                        <?php endif; ?>
                                                        <?php echo $route; ?>
                                                        <span class="ml-1 text-gray-500">(<?php echo $taskStart; ?>)</span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                            <?php 
                                            // Filter out crew members with role == 'TD'
                                            $filteredCrewMembers = array_filter($manifestGroup['crew_members'], function($member) {
                                                $role = strtoupper(trim($member['role'] ?? ''));
                                                return $role !== 'TD';
                                            });
                                            
                                            // Sort crew members by role priority: TRE,TRI,NC,PIC,DIC,FO,SP,OBS,CCE,CCI,SCC,CC
                                            $rolePriority = ['TRE' => 1, 'TRI' => 2, 'NC' => 3, 'PIC' => 4, 'DIC' => 5, 'FO' => 6, 'SP' => 7, 'OBS' => 8, 'CCE' => 9, 'CCI' => 10, 'SCC' => 11, 'CC' => 12];
                                            
                                            $sortedCrewMembers = array_values($filteredCrewMembers);
                                            usort($sortedCrewMembers, function($a, $b) use ($rolePriority) {
                                                $roleA = strtoupper(trim($a['role'] ?? ''));
                                                $roleB = strtoupper(trim($b['role'] ?? ''));
                                                
                                                $priorityA = isset($rolePriority[$roleA]) ? $rolePriority[$roleA] : 999;
                                                $priorityB = isset($rolePriority[$roleB]) ? $rolePriority[$roleB] : 999;
                                                
                                                if ($priorityA === $priorityB) {
                                                    return strcmp($a['name'] ?? '', $b['name'] ?? '');
                                                }
                                                return $priorityA - $priorityB;
                                            });
                                            
                                            foreach ($sortedCrewMembers as $member): ?>
                                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600 shadow-sm hover:shadow-md transition-shadow" 
                                                     data-route="<?php echo htmlspecialchars($manifestGroup['route']); ?>" 
                                                     data-date="<?php echo $date; ?>"
                                                     data-crew-id="<?php echo htmlspecialchars($member['id']); ?>"
                                                     data-crew-role="<?php echo htmlspecialchars($member['role'] ?? ''); ?>"
                                                     data-crew-national-id="<?php echo htmlspecialchars($member['national_id'] ?? ''); ?>">
                                                    <div class="flex items-center space-x-4">
                                                        <!-- Profile Picture -->
                                                        <div class="flex-shrink-0">
                                                            <?php if (!empty($member['picture']) && file_exists(__DIR__ . '/../../' . $member['picture'])): ?>
                                                                <img src="<?php echo getProfileImageUrl($member['picture']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                                                     class="h-16 w-16 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600">
                                                            <?php else: ?>
                                                                <div class="h-16 w-16 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center border-2 border-gray-200 dark:border-gray-600">
                                                                    <i class="fas fa-user text-gray-600 dark:text-gray-300 text-2xl"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Name and Role -->
                                                        <div class="flex-1 min-w-0">
                                                            <h5 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                                <?php echo htmlspecialchars($member['name']); ?>
                                                            </h5>
                                                            <?php if (!empty($member['role'])): ?>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                        <?php echo htmlspecialchars(strtoupper($member['role'])); ?>
                                                                    </span>
                                                                </p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($member['national_id'])): ?>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    <i class="fas fa-id-card mr-1"></i><?php echo htmlspecialchars($member['national_id']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" data-flights-count>
                                                                <i class="fas fa-plane mr-1"></i><?php echo count($member['data']['flights']); ?> flights
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                        <div class="p-8 text-center">
                            <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Crew Data Found</h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                No crew members found for the selected date range (<?php echo $startDateFormatted; ?> - <?php echo $endDateFormatted; ?>).
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Crew Manifest Modal -->
    <div id="crewManifestModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 backdrop-blur-sm transition-opacity duration-300">
        <div class="relative top-4 sm:top-10 md:top-20 mx-auto p-4 sm:p-6 md:p-8 w-11/12 max-w-6xl shadow-2xl rounded-lg bg-white border border-gray-200 transform transition-all duration-300">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-600 flex items-center justify-center">
                        <i class="fas fa-file-alt text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-semibold text-gray-900">
                            Crew Manifest
                        </h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Flight crew documentation
                        </p>
                    </div>
                </div>
                <button onclick="closeCrewManifest()" class="flex-shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div id="manifestContent" class="bg-gray-50 p-4 sm:p-6 rounded-lg overflow-x-auto max-h-[70vh] overflow-y-auto">
                <!-- Manifest content will be loaded here -->
            </div>
            
            <!-- Modal Footer -->
            <div class="flex flex-col sm:flex-row justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                <button onclick="printManifest()" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 shadow-sm">
                    <i class="fas fa-print mr-2"></i>
                    Print Manifest
                </button>
                <button onclick="closeCrewManifest()" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Get current user name and dispatch license (defined at page load)
        const currentUserData = <?php 
            $userName = '';
            $dispatchLicense = '';
            // Always use session user_id to ensure we get the logged-in user, not crew
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            if ($userId) {
                try {
                    $db = getDBConnection();
                    // Get first_name, last_name, and dispatch_license from users table for the logged-in user
                    $stmt = $db->prepare("SELECT first_name, last_name, username, dispatch_license FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($userData) {
                        $firstName = trim($userData['first_name'] ?? '');
                        $lastName = trim($userData['last_name'] ?? '');
                        $userName = trim($firstName . ' ' . $lastName);
                        $dispatchLicense = trim($userData['dispatch_license'] ?? '');
                        
                        // If still empty, try to get from username as last resort
                        if (empty($userName) && !empty($userData['username'])) {
                            $userName = $userData['username'];
                        }
                    }
                } catch (Exception $e) {
                    // If database query fails, try to use session data
                    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
                        $firstName = trim($_SESSION['first_name'] ?? '');
                        $lastName = trim($_SESSION['last_name'] ?? '');
                        $userName = trim($firstName . ' ' . $lastName);
                    } elseif (isset($_SESSION['username'])) {
                        $userName = $_SESSION['username'];
                    }
                }
            } elseif (isset($user) && !empty($user)) {
                // Fallback: use $user array if session is not available
                $firstName = $user['first_name'] ?? '';
                $lastName = $user['last_name'] ?? '';
                $userName = trim($firstName . ' ' . $lastName);
                $dispatchLicense = trim($user['dispatch_license'] ?? '');
                
                if (empty($userName) && !empty($user['username'])) {
                    $userName = $user['username'];
                }
            }
            // Return both user name and dispatch license
            echo json_encode([
                'name' => $userName ?: '',
                'dispatch_license' => $dispatchLicense ?: ''
            ]);
        ?>;
        
        // Extract user name and dispatch license for backward compatibility
        const currentUserName = currentUserData.name || '';
        const currentUserDispatchLicense = currentUserData.dispatch_license || '';
        
        // Detect if dark mode is enabled
        function isDarkMode() {
            return document.documentElement.classList.contains('dark') || 
                   window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        
        // Initialize dark mode based on browser/system preference
        function initDarkMode() {
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const savedDarkMode = localStorage.getItem('darkMode');
            
            if (savedDarkMode !== null) {
                if (savedDarkMode === 'true') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } else {
                if (systemPrefersDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        }
        
        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (localStorage.getItem('darkMode') === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
        
        function changeDateRange() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            if (startDate && endDate) {
                window.location.href = `crew_list.php?start_date=${startDate}&end_date=${endDate}`;
            }
        }

        function showManifestDetails(route, date) {
            // Find the manifest group element
            const manifestGroupElement = document.querySelector(`[data-route="${route.replace(/"/g, '&quot;')}"][data-task-start]`);
            if (!manifestGroupElement) {
                alert('Manifest group not found');
                return;
            }
            
            // Get flight numbers (remove "RAI" prefix if present)
            const flightNumbersSection = manifestGroupElement.querySelector('[data-flight-numbers]');
            const flightNumbers = flightNumbersSection ? Array.from(flightNumbersSection.querySelectorAll('span')).map(span => {
                let text = span.textContent.trim();
                // Remove "RAI" prefix if present
                if (text.startsWith('RAI')) {
                    text = text.substring(3);
                }
                return text;
            }).filter(text => text) : [];
            
            // Get flights
            const flightsSection = manifestGroupElement.querySelector('[data-flights]');
            const flights = flightsSection ? Array.from(flightsSection.querySelectorAll('span')).map(span => {
                const text = span.textContent.trim();
                const match = text.match(/(.+?)\s*\((\d{2}:\d{2})\)/);
                if (match) {
                    return { route: match[1].trim(), time: match[2] };
                }
                return { route: text, time: '' };
            }) : [];
            
            // Get crew members
            const crewCards = manifestGroupElement.querySelectorAll('[data-crew-id]');
            let crewMembers = Array.from(crewCards).map(card => {
                const name = card.querySelector('h5')?.textContent.trim() || '';
                const role = card.getAttribute('data-crew-role') || '';
                const nationalId = card.getAttribute('data-crew-national-id') || '';
                const flightsCount = card.querySelector('[data-flights-count]')?.textContent.trim() || '0';
                const picture = card.querySelector('img')?.src || '';
                
                return { name, role, nationalId, flightsCount, picture };
            });
            
            // Filter out crew members with role == 'TD'
            crewMembers = crewMembers.filter(member => {
                const role = (member.role || '').toUpperCase().trim();
                return role !== 'TD';
            });
            
            // Sort crew members by role priority: TRE,TRI,NC,PIC,DIC,FO,SP,OBS,CCE,CCI,SCC,CC
            const rolePriority = { 'TRE': 1, 'TRI': 2, 'NC': 3, 'PIC': 4, 'DIC': 5, 'FO': 6, 'SP': 7, 'OBS': 8, 'CCE': 9, 'CCI': 10, 'SCC': 11, 'CC': 12 };
            crewMembers.sort((a, b) => {
                const roleA = (a.role || '').toUpperCase().trim();
                const roleB = (b.role || '').toUpperCase().trim();
                
                const priorityA = rolePriority[roleA] || 999;
                const priorityB = rolePriority[roleB] || 999;
                
                if (priorityA === priorityB) {
                    return (a.name || '').localeCompare(b.name || '');
                }
                return priorityA - priorityB;
            });
            
            // Get task times
            const taskStart = manifestGroupElement.getAttribute('data-task-start') || '';
            const taskEnd = manifestGroupElement.getAttribute('data-task-end') || '';
            
            // Get aircraft registration (Rego) from manifest group element
            const aircraftRego = manifestGroupElement.getAttribute('data-aircraft-rego') || 'N/A';
            
            // Generate table content
            generateManifestTable(route, date, flightNumbers, flights, crewMembers, taskStart, taskEnd, aircraftRego);
            
            // Show modal
            document.getElementById('crewManifestModal').classList.remove('hidden');
        }
        
        function generateCrewManifest(route, date) {
            // Show loading state
            document.getElementById('manifestContent').innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-purple-600 mr-3"></i>
                    <span class="text-gray-600 dark:text-gray-400">Generating crew manifest...</span>
                </div>
            `;
            
            // Show modal
            document.getElementById('crewManifestModal').classList.remove('hidden');
            
            // Generate manifest content
            setTimeout(() => {
                generateManifestContent(route, date);
            }, 500);
        }
        
        function generateManifestTable(route, date, flightNumbers, flights, crewMembers, taskStart, taskEnd, aircraftRego) {
            const dateFormatted = new Date(date + 'T00:00:00').toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            
            const routeParts = route.split('-');
            const departure = routeParts[0] || '';
            const arrival = routeParts[routeParts.length - 1] || '';
            
            const formatTime = (dateTimeString) => {
                if (!dateTimeString) return '';
                try {
                    const date = new Date(dateTimeString);
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return hours + ':' + minutes;
                } catch (e) {
                    return '';
                }
            };
            
            // Get route sectors
            const routeSectors = route.split('-');
            
            // Get flight number (combine all with -)
            const flightNo = flightNumbers.length > 0 ? 'RAI' + flightNumbers.join('-') : 'N/A';
            
            // Force light mode for manifest (always use light mode colors)
            const darkMode = false;
            
            // Color scheme - always light mode
            const colors = {
                bgHeader: '#f9fafb',
                bgCell: '#ffffff',
                textPrimary: '#1f2937',
                textSecondary: '#6b7280',
                border: '#000000',
                purple: '#61207f',
                purpleLight: '#61207f'
            };
            
            let tableHTML = '<div class="overflow-x-auto" style="font-family: Arial, sans-serif;">';
            tableHTML += `<table border="1" cellpadding="4" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid ${colors.border}; background-color: ${colors.bgCell};">`;
            
            // HEADER - LOGO & COMPANY & CREW LIST TITLE
            const logoPath = '/assets/raimon.png';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="2" style="width:20%; text-align:center; border: 1px solid ${colors.border}; vertical-align: middle; padding: 12px; background-color: ${colors.bgHeader};">`;
            tableHTML += `<div style="display: flex; align-items: center; justify-content: center; height: 100%;"><img src="${logoPath}" alt="RAIMON AIRWAYS" style="max-width: 100%; max-height: 120px; height: auto; width: auto; object-fit: contain; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"><div style="display: none; font-size: 11px; color: ${colors.textSecondary}; margin-bottom: 4px; font-weight: 500;">AIRWAYS</div><div style="display: none; font-size: 18px; font-weight: bold; color: ${colors.textPrimary};">RAIMON</div></div>`;
            tableHTML += '</td>';
            tableHTML += `<td colspan="4" style="text-align:center; border: 1px solid ${colors.border}; vertical-align: middle; padding: 12px; background-color: ${colors.bgHeader};">`;
            tableHTML += `<div style="font-size: 28px; font-weight: bold; color: ${colors.purple}; letter-spacing: 1px;">CREW LIST</div>`;
            tableHTML += '</td>';
            tableHTML += `<td colspan="2" style="text-align:center; border: 1px solid ${colors.border}; vertical-align: middle; padding: 12px; background-color: ${colors.bgHeader};">`;
            tableHTML += `<div style="font-size: 16px; font-weight: bold; color: ${colors.purple};">RAIMON AIRWAYS</div>`;
            tableHTML += '</td>';
            tableHTML += '</tr>';
            
            // Purple line separator
            tableHTML += '<tr>';
            tableHTML += `<td colspan="8" style="height: 2px; background-color: ${colors.purple}; border: 1px solid ${colors.border}; padding: 0;"></td>`;
            tableHTML += '</tr>';
            
            // DEPARTURE / ARRIVAL TITLES
            tableHTML += '<tr>';
            tableHTML += `<td colspan="4" style="text-align:center; border: 1px solid ${colors.border}; font-weight: bold; padding: 8px; color: #000000; background-color: #d9d9d9;">DEPARTURE</td>`;
            tableHTML += `<td colspan="4" style="text-align:center; border: 1px solid ${colors.border}; font-weight: bold; padding: 8px; color: #000000; background-color: #d9d9d9;">ARRIVAL</td>`;
            tableHTML += '</tr>';
            
            // DATES
            tableHTML += '<tr>';
            tableHTML += `<td colspan="4" style="border: 1px solid ${colors.border}; padding: 4px; color: ${colors.textPrimary}; background-color: ${colors.bgHeader};">`;
            tableHTML += 'DATE OF DEP: ' + dateFormatted;
            tableHTML += '</td>';
            tableHTML += `<td colspan="4" style="border: 1px solid ${colors.border}; padding: 4px; color: ${colors.textPrimary}; background-color: ${colors.bgHeader};">`;
            tableHTML += 'DATE OF ARR: ' + dateFormatted;
            tableHTML += '</td>';
            tableHTML += '</tr>';
            
            // SCHEDULE TIMES
            tableHTML += '<tr>';
            tableHTML += `<td colspan="4" style="border: 1px solid ${colors.border}; padding: 4px; color: ${colors.textPrimary}; background-color: ${colors.bgHeader};">`;
            tableHTML += 'SCHEDULE DEP TIME: ' + formatTime(taskStart);
            tableHTML += '</td>';
            tableHTML += `<td colspan="4" style="border: 1px solid ${colors.border}; padding: 4px; color: ${colors.textPrimary}; background-color: ${colors.bgHeader};">`;
            tableHTML += 'SCHEDULE ARR TIME: ' + formatTime(taskEnd);
            tableHTML += '</td>';
            tableHTML += '</tr>';
            
            // ROUTE & FLIGHT / REG
            tableHTML += '<tr>';
            tableHTML += `<td style="width:5%; border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += `<td style="width:15%; border: 0; padding: 4px; font-weight: bold; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">ROUTE</td>`;
            tableHTML += `<td colspan="3" style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += `<td colspan="2" style="border: 0; padding: 4px; text-align: right; color: ${colors.textPrimary}; background-color: ${colors.bgCell};"><strong>FLIGHT NO:</strong> ` + flightNo + '</td>';
            tableHTML += `<td style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += '</tr>';
            
            // A/C REG row (above ROUTE SECTORS)
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += `<td style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += `<td colspan="3" style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += `<td colspan="2" style="border: 0; padding: 4px; text-align: right; color: ${colors.textPrimary}; background-color: ${colors.bgCell};"><strong>A/C REG:</strong> ` + (aircraftRego || 'N/A') + '</td>';
            tableHTML += `<td style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            tableHTML += '</tr>';
            
            // ROUTE SECTORS - Display as boxes
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            // Generate route sectors (e.g., RAS-MHD, MHD-THR, THR-AZD)
            const sectors = [];
            for (let i = 0; i < routeSectors.length - 1; i++) {
                sectors.push(`${routeSectors[i]}-${routeSectors[i + 1]}`);
            }
            // Add sectors as individual cells (up to 3 sectors shown as boxes)
            sectors.slice(0, 3).forEach(sector => {
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">${sector}</td>`;
            });
            // Fill remaining cells (no border for empty cells)
            const remainingCells = 6 - sectors.slice(0, 3).length;
            for (let i = 0; i < remainingCells; i++) {
                tableHTML += `<td style="border: 0; padding: 4px; background-color: ${colors.bgCell};">&nbsp;</td>`;
            }
            tableHTML += '</tr>';
            
            // Empty row for spacing
            tableHTML += '<tr>';
            tableHTML += `<td colspan="8" style="border: 0; padding: 4px; background-color: ${colors.bgCell}; height: 8px;">&nbsp;</td>`;
            tableHTML += '</tr>';
            
            // CREW TITLE - Gray banner
            tableHTML += '<tr>';
            tableHTML += `<td colspan="8" style="text-align:center; border: 1px solid ${colors.border}; background-color: #d9d9d9; color: #000000; font-weight: bold; padding: 10px; font-size: 18px; letter-spacing: 0.5px;">CREW</td>`;
            tableHTML += '</tr>';
            
            // CREW HEADER
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">NO</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">POSITION</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">ID NO</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.purple}; color: white;">NAME OF CREW</td>`;
            tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">PASS NO</td>`;
            tableHTML += '</tr>';
            
            // CREW ROWS
            const positions = ['PIC', 'FO', 'SCC', 'CC'];
            for (let i = 0; i < 9; i++) {
                const member = crewMembers[i] || null;
                const position = positions[i] || '';
                tableHTML += '<tr>';
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">${i + 1}</td>`;
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">${member ? (member.role.toUpperCase() || position) : position}</td>`;
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; background-color: ${colors.bgCell};"></td>`;
                tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">${member ? member.name.toUpperCase() : ''}</td>`;
                tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">${member ? (member.nationalId || '') : ''}</td>`;
                tableHTML += '</tr>';
            }
            
            // FM & D/H SECTION - Purple banner (title row removed)
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">NO</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">POSITION</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">ID NO</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.purple}; color: white;">FM &amp; D/H</td>`;
            tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 6px; text-align: center; font-weight: bold; background-color: ${colors.bgHeader}; color: ${colors.textPrimary};">PASS NO</td>`;
            tableHTML += '</tr>';
            
            // FM & D/H ROWS (empty for now)
            for (let i = 0; i < 3; i++) {
                tableHTML += '<tr>';
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; text-align: center; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">${i + 1}</td>`;
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; background-color: ${colors.bgCell};"></td>`;
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 4px; background-color: ${colors.bgCell};"></td>`;
                tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 4px; background-color: ${colors.bgCell};"></td>`;
                tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 4px; background-color: ${colors.bgCell};"></td>`;
                tableHTML += '</tr>';
            }
            
            // DISPATCH & STAMP
            tableHTML += '<tr>';
            tableHTML += `<td colspan="5" valign="top" style="border: 1px solid ${colors.border}; padding: 4px; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">`;
            // Display user name or N/A if empty
            const dispatchName = currentUserName && currentUserName.trim() !== '' ? currentUserName.toUpperCase() : 'N/A';
            // Display dispatch license or empty if not available
            const dispatchLicense = currentUserDispatchLicense && currentUserDispatchLicense.trim() !== '' ? currentUserDispatchLicense : '';
            tableHTML += 'RAIMON AIRWAYS DISPATCH: ' + dispatchName + '<br>';
            tableHTML += 'LICENCE NUMBER: ' + dispatchLicense + '<br><br>';
            tableHTML += 'SIGNATURE:<br><br><br>';
            tableHTML += '</td>';
            tableHTML += `<td colspan="3" valign="top" style="text-align:center; border: 1px solid ${colors.border}; padding: 4px; color: ${colors.textPrimary}; background-color: ${colors.bgCell};">`;
            tableHTML += 'STAMP:<br><br><br>';
            tableHTML += '</td>';
            tableHTML += '</tr>';
            
            tableHTML += '</table>';
            tableHTML += '</div>';
            
            document.getElementById('manifestContent').innerHTML = tableHTML;
        }

        function generateManifestContent(route, date) {
            try {
                if (!route || !date) {
                    throw new Error('Route and date are required');
                }
                
                const dateFormatted = new Date(date + 'T00:00:00').toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                const routeParts = route.split('-');
                const departure = routeParts[0] || '';
                const arrival = routeParts[routeParts.length - 1] || '';
                
                // Get crew data for this route and date
                const crewData = getCrewDataForRoute(route, date);
                
                // Generate flight number based on route
                const flightNumber = generateFlightNumber(route);
                
                // Get TaskStart and TaskEnd from manifest group data attributes
                const manifestGroupElement = document.querySelector('[data-route="' + route.replace(/"/g, '&quot;') + '"][data-task-start]');
                const taskStart = manifestGroupElement ? manifestGroupElement.getAttribute('data-task-start') : null;
                const taskEnd = manifestGroupElement ? manifestGroupElement.getAttribute('data-task-end') : null;
                
                // Format times
                const firstTaskStart = taskStart ? formatTime(taskStart) : null;
                const lastTaskEnd = taskEnd ? formatTime(taskEnd) : null;
                
                const userName = currentUserName || 'N/A';
                
                const manifestHTML = '<div class="bg-white dark:bg-gray-800 p-6 rounded-lg border-2 border-purple-200 dark:border-purple-800">' +
                    '<!-- Header -->' +
                    '<div class="text-center mb-6">' +
                        '<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">RAIMON AIRWAYS</h2>' +
                        '<h3 class="text-lg font-semibold text-purple-600 dark:text-purple-400">CREW MANIFEST</h3>' +
                    '</div>' +
                    '<!-- Flight Information -->' +
                    '<div class="grid grid-cols-2 gap-6 mb-6">' +
                        '<div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">' +
                            '<h4 class="font-semibold text-gray-900 dark:text-white mb-2">DEPARTURE</h4>' +
                            '<div class="space-y-2 text-sm text-gray-900 dark:text-white">' +
                                '<div><span class="font-medium">Date:</span> ' + dateFormatted + '</div>' +
                                '<div><span class="font-medium">Schedule Time:</span> ' + (firstTaskStart || '07:15') + '</div>' +
                                '<div><span class="font-medium">Station:</span> ' + departure + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">' +
                            '<h4 class="font-semibold text-gray-900 dark:text-white mb-2">ARRIVAL</h4>' +
                            '<div class="space-y-2 text-sm text-gray-900 dark:text-white">' +
                                '<div><span class="font-medium">Date:</span> ' + dateFormatted + '</div>' +
                                '<div><span class="font-medium">Schedule Time:</span> ' + (lastTaskEnd || '11:25') + '</div>' +
                                '<div><span class="font-medium">Station:</span> ' + arrival + '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<!-- Route and Flight Details -->' +
                    '<div class="mb-6">' +
                        '<div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">' +
                            '<div class="grid grid-cols-2 gap-4 text-gray-900 dark:text-white">' +
                                '<div><span class="font-medium">Route:</span> ' + route + '</div>' +
                                '<div><span class="font-medium">Flight No:</span> ' + flightNumber + '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<!-- Crew List -->' +
                    '<div class="mb-6">' +
                        '<h4 class="font-semibold mb-3" style="color: #000000; background-color: #d9d9d9; padding: 8px;">CREW</h4>' +
                        '<div class="overflow-x-auto">' +
                            '<table class="min-w-full border border-gray-300 dark:border-gray-600">' +
                                '<thead class="bg-gray-100 dark:bg-gray-700">' +
                                    '<tr>' +
                                        '<th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">NO</th>' +
                                        '<th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">POSITION</th>' +
                                        '<th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID NO</th>' +
                                        '<th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: white; background-color: #61207f;">NAME OF CREW</th>' +
                                        '<th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">PASS NO</th>' +
                                    '</tr>' +
                                '</thead>' +
                                '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">' +
                                    generateCrewTableRows(crewData) +
                                '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>' +
                    '<!-- Footer -->' +
                    '<div class="grid grid-cols-2 gap-6">' +
                        '<div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">' +
                            '<h5 class="font-semibold text-gray-900 dark:text-white mb-2">RAIMON AIRWAYS DISPATCH</h5>' +
                            '<div class="text-sm space-y-1">' +
                                '<div class="text-gray-900 dark:text-white text-sm font-bold">' + userName + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="bg-gray-50 dark:bg-gray-700 p-4 rounded text-center">' +
                            '<div class="text-sm">' +
                                '<div class="border border-gray-400 h-20 flex items-center justify-center">' +
                                    '<span class="text-gray-500">STAMP:</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
                
                document.getElementById('manifestContent').innerHTML = manifestHTML;
            } catch (error) {
                console.error('Error generating manifest:', error);
                document.getElementById('manifestContent').innerHTML = 
                    '<div class="flex items-center justify-center py-8">' +
                        '<div class="text-center">' +
                            '<i class="fas fa-exclamation-triangle text-2xl text-red-600 mb-3"></i>' +
                            '<p class="text-gray-600 dark:text-gray-400">Error generating manifest: ' + error.message + '</p>' +
                        '</div>' +
                    '</div>';
            }
        }
        

        function generateCrewTableRows(crewData) {
            let rows = '';
            
            if (!crewData || !Array.isArray(crewData)) {
                crewData = [];
            }
            
            crewData.forEach((member, index) => {
                // Use actual role from database (Crew1_role to Crew10_role)
                const position = (member && member.role) ? member.role.toUpperCase() : '';
                const memberName = (member && member.name) ? member.name.toUpperCase() : '';
                const nationalId = (member && member.national_id) ? member.national_id : '';
                rows += `
                    <tr>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white">${index + 1}</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white">${position}</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white"></td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white">${memberName}</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white">${nationalId}</td>
                    </tr>
                `;
            });
            
            // Add empty rows to fill up to 7 crew members
            for (let i = crewData.length; i < 7; i++) {
                rows += `
                    <tr>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white">${i + 1}</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white"></td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white"></td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white"></td>
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-900 dark:text-white"></td>
                    </tr>
                `;
            }
            
            return rows;
        }

        function getCrewDataForRoute(route, date) {
            const crewMembers = [];
            
            // Find crew members for this specific route and date
            // Use attribute selector with proper escaping
            const routeElements = document.querySelectorAll('[data-route][data-date]');
            routeElements.forEach(element => {
                const elementRoute = element.getAttribute('data-route');
                const elementDate = element.getAttribute('data-date');
                if (elementRoute === route && elementDate === date) {
                    const nameElement = element.querySelector('h5');
                    const crewId = element.getAttribute('data-crew-id');
                    const crewRole = element.getAttribute('data-crew-role') || '';
                    const nationalId = element.getAttribute('data-crew-national-id') || '';
                    
                    if (nameElement) {
                        const name = nameElement.textContent.trim();
                        if (name && !crewMembers.some(m => m.id === crewId)) {
                            crewMembers.push({ 
                                id: crewId,
                                name: name,
                                role: crewRole,
                                national_id: nationalId
                            });
                        }
                    }
                }
            });
            
            // Filter out crew members with role == 'TD'
            crewMembers = crewMembers.filter(member => {
                const role = (member.role || '').toUpperCase().trim();
                return role !== 'TD';
            });
            
            // Sort crew members by role priority: TRE,TRI,NC,PIC,DIC,FO,SP,OBS,CCE,CCI,SCC,CC
            const rolePriority = { 'TRE': 1, 'TRI': 2, 'NC': 3, 'PIC': 4, 'DIC': 5, 'FO': 6, 'SP': 7, 'OBS': 8, 'CCE': 9, 'CCI': 10, 'SCC': 11, 'CC': 12 };
            crewMembers.sort((a, b) => {
                const roleA = (a.role || '').toUpperCase().trim();
                const roleB = (b.role || '').toUpperCase().trim();
                
                const priorityA = rolePriority[roleA] || 999;
                const priorityB = rolePriority[roleB] || 999;
                
                if (priorityA === priorityB) {
                    return (a.name || '').localeCompare(b.name || '');
                }
                return priorityA - priorityB;
            });
            
            return crewMembers;
        }
        
        function formatTime(dateTimeString) {
            if (!dateTimeString) return null;
            try {
                const date = new Date(dateTimeString);
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return hours + ':' + minutes;
            } catch (e) {
                return null;
            }
        }

        function generateFlightNumber(route) {
            // Generate flight number based on route
            // This is a simple mapping - you can customize this logic
            const routeMap = {
                'RAS-MHD': 'RAI7520',
                'MHD-THR': 'RAI7523', 
                'THR-AZD': 'RAI7526',
                'RAS-MHD-THR': 'RAI7520-7523',
                'MHD-THR-AZD': 'RAI7523-7526',
                'RAS-MHD-THR-AZD': 'RAI7520-7523-7526'
            };
            
            return routeMap[route] || 'RAI7520-7523';
        }

        function closeCrewManifest() {
            document.getElementById('crewManifestModal').classList.add('hidden');
        }

        function printManifest() {
            const manifestContent = document.getElementById('manifestContent').innerHTML;
            const printWindow = window.open('', '_blank');
            
            // Extract the table content while preserving all inline styles
            let tableContent = manifestContent;
            
            // Try to get the table from the wrapper div
            const wrapperMatch = manifestContent.match(/<div[^>]*class="overflow-x-auto"[^>]*>([\s\S]*)<\/div>/);
            if (wrapperMatch) {
                tableContent = wrapperMatch[1];
            } else {
                // Fallback: get table directly
            const tableMatch = manifestContent.match(/<table[^>]*>[\s\S]*<\/table>/);
                if (tableMatch) {
                    tableContent = tableMatch[0];
                }
            }
            
            printWindow.document.write('<!DOCTYPE html><html><head><title>Crew Manifest</title><style>' +
                '@media print { ' +
                '  @page { margin: 0.5cm; size: A4; }' +
                '  body { margin: 0; padding: 0; }' +
                '  * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; print-color-adjust: exact !important; }' +
                '}' +
                'body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white !important; }' +
                'table { border-collapse: collapse; width: 100%; border: 1px solid #000; }' +
                'td { border: 1px solid #000; padding: 4px; }' +
                'strong { font-weight: bold; }' +
                'img { max-width: 100%; height: auto; }' +
                '</style></head><body>' + tableContent + '</body></html>');
            printWindow.document.close();
            
            // Wait for content to load before printing
            printWindow.onload = function() {
                setTimeout(function() {
            printWindow.print();
                }, 250);
            };
        }

        // Close modal when clicking outside
        document.getElementById('crewManifestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCrewManifest();
            }
        });
    </script>
</body>
</html>
