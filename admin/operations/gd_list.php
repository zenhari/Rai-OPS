<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/gd_list.php');

$user = getCurrentUser();

// Get date parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get crew routes data (same as crew_list.php)
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
    <title>GD List - <?php echo PROJECT_NAME; ?></title>
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
        .gd-route { 
            font-family: 'Courier New', monospace; 
            background: linear-gradient(90deg, #61207f, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
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
                                General Declaration (GD) List
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Generate General Declaration forms for flights
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
                                            Flight Groups
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

                <!-- GD Routes by Date -->
                <?php 
                // Helper functions for crew processing (same as crew_list.php)
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
                                
                                // Group flights by continuous routes with same crew
                                $manifestGroups = [];
                                $currentManifest = null;
                                
                                foreach ($allFlights as $flight) {
                                    $flightCrew = getFlightCrew($flight, $crewNames);
                                    
                                    if (empty($flightCrew)) {
                                        continue;
                                    }
                                    
                                    if ($currentManifest === null) {
                                        $currentManifest = [
                                            'flights' => [$flight],
                                            'crew' => $flightCrew,
                                            'routes' => [$flight['Route']],
                                            'first_task_start' => $flight['TaskStart'],
                                            'last_task_end' => $flight['TaskEnd']
                                        ];
                                        continue;
                                    }
                                    
                                    $lastFlight = end($currentManifest['flights']);
                                    $lastRoute = $lastFlight['Route'] ?? '';
                                    $currentRoute = $flight['Route'] ?? '';
                                    
                                    $lastRouteParts = explode('-', $lastRoute);
                                    $currentRouteParts = explode('-', $currentRoute);
                                    $isContinuous = false;
                                    
                                    if (!empty($lastRouteParts) && !empty($currentRouteParts)) {
                                        $lastDestination = end($lastRouteParts);
                                        $currentOrigin = $currentRouteParts[0];
                                        $isContinuous = ($lastDestination === $currentOrigin);
                                    }
                                    
                                    $crewSame = sameCrew($currentManifest['crew'], $flightCrew);
                                    
                                    if ($isContinuous && $crewSame) {
                                        $currentManifest['flights'][] = $flight;
                                        if (!in_array($currentRoute, $currentManifest['routes'])) {
                                            $currentManifest['routes'][] = $currentRoute;
                                        }
                                        if ($flight['TaskEnd'] > $currentManifest['last_task_end']) {
                                            $currentManifest['last_task_end'] = $flight['TaskEnd'];
                                        }
                                    } else {
                                        if (!empty($currentManifest['flights'])) {
                                            $continuousRoute = createContinuousRoute($currentManifest['routes']);
                                            $crewIds = array_column($currentManifest['crew'], 'id');
                                            sort($crewIds);
                                            $manifestKey = $continuousRoute . '_' . implode('-', $crewIds);
                                            
                                            $manifestGroups[$manifestKey] = [
                                                'route' => $continuousRoute,
                                                'crew_members' => array_map(function($c) use ($crewNationalIds, $crewPictures) {
                                                    return [
                                                        'id' => $c['id'],
                                                        'name' => $c['name'],
                                                        'role' => $c['role'] ?? '',
                                                        'national_id' => $crewNationalIds[$c['id']] ?? '',
                                                        'picture' => $crewPictures[$c['id']] ?? '',
                                                    ];
                                                }, $currentManifest['crew']),
                                                'flights' => $currentManifest['flights'],
                                                'first_task_start' => $currentManifest['first_task_start'],
                                                'last_task_end' => $currentManifest['last_task_end']
                                            ];
                                        }
                                        
                                        $currentManifest = [
                                            'flights' => [$flight],
                                            'crew' => $flightCrew,
                                            'routes' => [$currentRoute],
                                            'first_task_start' => $flight['TaskStart'],
                                            'last_task_end' => $flight['TaskEnd']
                                        ];
                                    }
                                }
                                
                                if ($currentManifest !== null && !empty($currentManifest['flights'])) {
                                    $continuousRoute = createContinuousRoute($currentManifest['routes']);
                                    $crewIds = array_column($currentManifest['crew'], 'id');
                                    sort($crewIds);
                                    $manifestKey = $continuousRoute . '_' . implode('-', $crewIds);
                                    
                                    $manifestGroups[$manifestKey] = [
                                        'route' => $continuousRoute,
                                        'crew_members' => array_map(function($c) use ($crewNationalIds, $crewPictures) {
                                            return [
                                                'id' => $c['id'],
                                                'name' => $c['name'],
                                                'role' => $c['role'] ?? '',
                                                'national_id' => $crewNationalIds[$c['id']] ?? '',
                                                'picture' => $crewPictures[$c['id']] ?? '',
                                            ];
                                        }, $currentManifest['crew']),
                                        'flights' => $currentManifest['flights'],
                                        'first_task_start' => $currentManifest['first_task_start'],
                                        'last_task_end' => $currentManifest['last_task_end']
                                    ];
                                }
                                ?>
                                
                                <?php foreach ($manifestGroups as $manifestRoute => $manifestGroup): ?>
                                    <?php 
                                    $firstFlight = !empty($manifestGroup['flights']) ? $manifestGroup['flights'][0] : null;
                                    $lastFlight = !empty($manifestGroup['flights']) ? end($manifestGroup['flights']) : null;
                                    $taskStart = $firstFlight ? ($firstFlight['TaskStart'] ?? '') : '';
                                    $taskEnd = $lastFlight ? ($lastFlight['TaskEnd'] ?? '') : '';
                                    
                                    $aircraftRego = 'N/A';
                                    if (!empty($manifestGroup['flights']) && !empty($manifestGroup['flights'][0]['Rego'])) {
                                        $aircraftRego = $manifestGroup['flights'][0]['Rego'];
                                    }
                                    
                                    // Get flight numbers
                                    $flightNumbers = [];
                                    foreach ($manifestGroup['flights'] as $flight) {
                                        $taskName = !empty($flight['TaskName']) && $flight['TaskName'] !== 'N/A' ? trim($flight['TaskName']) : null;
                                        if ($taskName && !in_array($taskName, $flightNumbers)) {
                                            $flightNumbers[] = $taskName;
                                        }
                                    }
                                    ?>
                                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg" 
                                         data-route="<?php echo htmlspecialchars($manifestGroup['route']); ?>"
                                         data-task-start="<?php echo htmlspecialchars($taskStart); ?>"
                                         data-task-end="<?php echo htmlspecialchars($taskEnd); ?>"
                                         data-aircraft-rego="<?php echo htmlspecialchars($aircraftRego); ?>"
                                         data-date="<?php echo $date; ?>">
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
                                                <button onclick="showGDDetails('<?php echo htmlspecialchars($manifestGroup['route']); ?>', '<?php echo $date; ?>')" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                                    <i class="fas fa-file-alt mr-1"></i>
                                                    Generate GD
                                                </button>
                                            </div>
                                        </div>
                                        
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
                                            $filteredCrewMembers = array_filter($manifestGroup['crew_members'], function($member) {
                                                $role = strtoupper(trim($member['role'] ?? ''));
                                                return $role !== 'TD';
                                            });
                                            
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
                                                     data-crew-id="<?php echo htmlspecialchars($member['id']); ?>"
                                                     data-crew-role="<?php echo htmlspecialchars($member['role'] ?? ''); ?>"
                                                     data-crew-national-id="<?php echo htmlspecialchars($member['national_id'] ?? ''); ?>">
                                                    <div class="flex items-center space-x-4">
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
                            <i class="fas fa-file-alt text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No GD Data Found</h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                No flight data found for the selected date range (<?php echo $startDateFormatted; ?> - <?php echo $endDateFormatted; ?>).
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- GD Modal - Always Light Mode -->
    <div id="gdModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 backdrop-blur-sm transition-opacity duration-300">
        <div class="relative top-4 sm:top-10 md:top-20 mx-auto p-4 sm:p-6 md:p-8 w-11/12 max-w-6xl shadow-2xl rounded-lg bg-white border border-gray-200 transform transition-all duration-300">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-600 flex items-center justify-center">
                        <i class="fas fa-file-alt text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-semibold text-gray-900">
                            General Declaration
                        </h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            OUTWARD/INWARD
                        </p>
                    </div>
                </div>
                <button onclick="closeGDModal()" class="flex-shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div id="gdContent" class="bg-gray-50 p-4 sm:p-6 rounded-lg overflow-x-auto max-h-[70vh] overflow-y-auto">
                <!-- GD content will be loaded here -->
            </div>
            
            <!-- Modal Footer -->
            <div class="flex flex-col sm:flex-row justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                <button onclick="printGD()" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 shadow-sm">
                    <i class="fas fa-print mr-2"></i>
                    Print GD
                </button>
                <button onclick="closeGDModal()" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
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
                window.location.href = `gd_list.php?start_date=${startDate}&end_date=${endDate}`;
            }
        }

        function showGDDetails(route, date) {
            // Find the manifest group element
            const manifestGroupElement = document.querySelector(`[data-route="${route.replace(/"/g, '&quot;')}"][data-date="${date}"]`);
            if (!manifestGroupElement) {
                alert('Manifest group not found');
                return;
            }
            
            // Get flight numbers
            const flightNumbersSection = manifestGroupElement.querySelector('[data-flight-numbers]');
            const flightNumbers = flightNumbersSection ? Array.from(flightNumbersSection.querySelectorAll('span')).map(span => {
                let text = span.textContent.trim();
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
                
                return { name, role, nationalId };
            });
            
            // Filter out crew members with role == 'TD'
            crewMembers = crewMembers.filter(member => {
                const role = (member.role || '').toUpperCase().trim();
                return role !== 'TD';
            });
            
            // Sort crew members by role priority
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
            
            // Get aircraft registration
            const aircraftRego = manifestGroupElement.getAttribute('data-aircraft-rego') || 'N/A';
            
            // Generate GD table
            generateGDTable(route, date, flightNumbers, flights, crewMembers, taskStart, taskEnd, aircraftRego);
            
            // Show modal
            document.getElementById('gdModal').classList.remove('hidden');
        }
        
        function generateGDTable(route, date, flightNumbers, flights, crewMembers, taskStart, taskEnd, aircraftRego) {
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
            
            // Get flight number (combine all with -)
            const flightNo = flightNumbers.length > 0 ? flightNumbers.join('-') : 'N/A';
            
            // Always use light mode colors for GD table
            const colors = {
                bgHeader: '#f9fafb',
                bgCell: '#ffffff',
                textPrimary: '#1f2937',
                textSecondary: '#6b7280',
                border: '#000000',
                purple: '#61207f',
                gray: '#d9d9d9'
            };
            
            const logoPath = '/assets/raimon.png';
            
            let tableHTML = '<div class="overflow-x-auto" style="font-family: Arial, sans-serif;">';
            tableHTML += `<table border="1" cellpadding="4" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid ${colors.border}; background-color: ${colors.bgCell};">`;
            
            // HEADER - LOGO & GENERAL DECLARATION TITLE (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="2" style="width:20%; text-align:center; border: 1px solid ${colors.border}; vertical-align: middle; padding: 12px; background-color: ${colors.bgHeader};">`;
            tableHTML += `<div style="display: flex; align-items: center; justify-content: center; height: 100%;"><img src="${logoPath}" alt="RAIMON AIRWAYS" style="max-width: 100%; max-height: 120px; height: auto; width: auto; object-fit: contain; display: block;" onerror="this.style.display='none';"></div>`;
            tableHTML += '</td>';
            tableHTML += `<td colspan="2" style="text-align:center; border: 1px solid ${colors.border}; vertical-align: middle; padding: 12px; background-color: ${colors.bgHeader};">`;
            tableHTML += `<div style="font-size: 28px; font-weight: bold; color: ${colors.purple}; letter-spacing: 1px;">GENERAL DECLARATION</div>`;
            tableHTML += `<div style="font-size: 20px; font-weight: bold; color: ${colors.purple}; letter-spacing: 0.5px; margin-top: 4px;">OUTWARD/INWARD</div>`;
            tableHTML += '</td>';
            tableHTML += `<td colspan="2" style="text-align:center; border: 1px solid ${colors.border}; vertical-align: middle; padding: 12px; background-color: ${colors.bgHeader};">`;
            tableHTML += `<div style="font-size: 11px; color: ${colors.textSecondary}; line-height: 1.6;">PAGE: 1 of 1</div>`;
            tableHTML += `<div style="font-size: 11px; color: ${colors.textSecondary}; line-height: 1.6;">CHAP:</div>`;
            tableHTML += `<div style="font-size: 11px; color: ${colors.textSecondary}; line-height: 1.6;">ISSUE:</div>`;
            tableHTML += `<div style="font-size: 11px; color: ${colors.textSecondary}; line-height: 1.6;">REV:</div>`;
            tableHTML += `<div style="font-size: 11px; color: ${colors.textSecondary}; line-height: 1.6;">DATE:</div>`;
            tableHTML += '</td>';
            tableHTML += '</tr>';
            
            // FLIGHT INFORMATION ROW (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; white-space: nowrap; width: auto;">OPERATOR:</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; white-space: nowrap; width: auto;">RAIMON AIRWAYS</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; white-space: nowrap; width: auto;">DATE:</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; white-space: nowrap; width: auto;">${dateFormatted}</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; white-space: nowrap; width: auto;">DEP:</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; white-space: nowrap; width: auto;">${departure}</td>`;
            tableHTML += '</tr>';
            
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; white-space: nowrap; width: auto;">MARKS OF NATIONALITY AND REGISTRATION:</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; white-space: nowrap; width: auto;">${aircraftRego}</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; white-space: nowrap; width: auto;">FLIGHT NO:</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; white-space: nowrap; width: auto;">${flightNo}</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; white-space: nowrap; width: auto;">DES:</td>`;
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; white-space: nowrap; width: auto;">${arrival}</td>`;
            tableHTML += '</tr>';
            
            // FLIGHT ROUTING SECTION (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">FLIGHT ROUTING</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 4px; font-size: 10px; background-color: ${colors.bgCell}; text-align: center; font-style: italic;">"PLACE" COLUMN ALWAYS TO LIST ORIGIN, EVERY EN_ROUTE STOP AND DESTINATION</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">PLACE</td>`;
            // Add route places (up to 5 places to fill remaining columns)
            const routePlaces = route.split('-');
            routePlaces.slice(0, 5).forEach((place) => {
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; text-align: center;">${place}</td>`;
            });
            // Fill remaining cells to make 6 columns total
            for (let i = routePlaces.length; i < 5; i++) {
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px;"></td>`;
            }
            tableHTML += '</tr>';
            
            // CREW LIST SECTION - Single table with two columns (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">NAMES OF CREW</td>`;
            tableHTML += '</tr>';
            
            // Split crew members into two groups (left column: first 8, right column: rest)
            const leftCrew = [];
            const rightCrew = [];
            crewMembers.forEach((crew, index) => {
                if (index < 8) {
                    leftCrew.push(crew);
                } else {
                    rightCrew.push(crew);
                }
            });
            
            // Determine max rows for both columns (should be equal)
            const maxRows = Math.max(8, Math.max(leftCrew.length, rightCrew.length));
            
            // Fill both columns to the same number of rows
            while (leftCrew.length < maxRows) {
                leftCrew.push(null);
            }
            
            while (rightCrew.length < maxRows) {
                rightCrew.push(null);
            }
            
            // Format name as LASTNAME,FIRSTNAME
            const formatCrewName = (crew) => {
                if (!crew || !crew.name) return '';
                const parts = crew.name.trim().split(' ');
                if (parts.length >= 2) {
                    const lastName = parts[parts.length - 1];
                    const firstName = parts.slice(0, -1).join(' ');
                    return `${lastName.toUpperCase()}, ${firstName.toUpperCase()}`;
                }
                return crew.name.toUpperCase();
            };
            
            // Header row for two columns
            tableHTML += '<tr>';
            // Left column header
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">TITILE</td>`;
            tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">NAMES OF CREW</td>`;
            // Right column header
            tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">TITILE</td>`;
            tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 6px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">NAMES OF CREW</td>`;
            tableHTML += '</tr>';
            
            // CREW ROWS - Two columns side by side
            for (let i = 0; i < maxRows; i++) {
                const leftCrewMember = leftCrew[i] || null;
                const rightCrewMember = rightCrew[i] || null;
                
                tableHTML += '<tr>';
                // Left column
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 8px; text-align: center; min-height: 35px; height: 35px; vertical-align: middle; background-color: ${leftCrewMember ? colors.bgCell : '#f9fafb'};">${leftCrewMember ? (leftCrewMember.role.toUpperCase() || '') : ''}</td>`;
                tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 8px; text-align: left; min-height: 35px; height: 35px; vertical-align: middle; background-color: ${leftCrewMember ? colors.bgCell : '#f9fafb'};">${formatCrewName(leftCrewMember)}</td>`;
                // Right column
                tableHTML += `<td style="border: 1px solid ${colors.border}; padding: 8px; text-align: center; min-height: 35px; height: 35px; vertical-align: middle; background-color: ${rightCrewMember ? colors.bgCell : '#f9fafb'};">${rightCrewMember ? (rightCrewMember.role.toUpperCase() || '') : ''}</td>`;
                tableHTML += `<td colspan="2" style="border: 1px solid ${colors.border}; padding: 8px; text-align: left; min-height: 35px; height: 35px; vertical-align: middle; background-color: ${rightCrewMember ? colors.bgCell : '#f9fafb'};">${formatCrewName(rightCrewMember)}</td>`;
                tableHTML += '</tr>';
            }
            
            
            // CARGO SECTION (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 8px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">CARGO</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 8px; background-color: ${colors.bgCell};">CARGO MANIFASTS ATTACHED</td>`;
            tableHTML += '</tr>';
            
            // NUMBER OF PASSENGER SECTION (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">NUMBER OF PASSENGER ON THIS STAGE</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">DEPARTURE PLACE:</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">ARRIVAL PLACE:</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">EMBARKING:........................</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">DISEMBARKING:...................</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">THROUGH ON SAME FLIGHT</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">THROUGH ON SAME FLIGHT</td>`;
            tableHTML += '</tr>';
            
            // DECLARATION OF HEALTH SECTION (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">DECLARATION OF HEALTH</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-size: 10px; line-height: 1.5; text-align: justify;">`;
            tableHTML += 'The following conditions must be reported: illnesses other than airsickness or accident effects; communicable diseases (fever 38°C/100°F or greater, with symptoms like appearing unwell, persistent coughing, impaired breathing, persistent diarrhea, persistent vomiting, skin rash, bruising/bleeding without injury, or recent confusion); cases of illness disembarked at a previous stop; details of disinsecting or sanitary treatment during the flight (place, date, time, method). If no disinsecting, details of the most recent one.';
            tableHTML += '</td>';
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">SIGNED IF REQUIRED</td>`;
            tableHTML += `<td colspan="3" style="border: 1px solid ${colors.border}; padding: 6px;">CREW MEMBER CONCERD</td>`;
            tableHTML += '</tr>';
            
            // DECLARATION STATEMENT (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-size: 10px; line-height: 1.5; text-align: justify; font-weight: bold;">`;
            tableHTML += 'I DECLARE THAT ALL STATEMENTS AND PARTICULARS CONTAINED IN THIS GENERAL DECLARATION, AND IN ANY SUPPLEMENATRY FROMS REQUIRED TO BE PRESENTED WITH GENERAL DECLARATION ARE COMPLETED, EXAT AND TRUE TO THE BEST OF MY KNOWLEDGE AND THAT ALL THROUGH PASSENGERS WILL CONTINUE / HAVE CONTINUED THE FLIGHT.';
            tableHTML += '</td>';
            tableHTML += '</tr>';
            
            // FOR OFFICIAL USE ONLY (6 columns)
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 8px; font-weight: bold; background-color: ${colors.gray}; text-align: center;">FOR OFFICIAL USE ONLY</td>`;
            tableHTML += '</tr>';
            tableHTML += '<tr>';
            tableHTML += `<td colspan="6" style="border: 1px solid ${colors.border}; padding: 40px; min-height: 80px; background-color: ${colors.bgCell};"></td>`;
            tableHTML += '</tr>';
            
            tableHTML += '</table>';
            tableHTML += '</div>';
            
            document.getElementById('gdContent').innerHTML = tableHTML;
        }

        function closeGDModal() {
            document.getElementById('gdModal').classList.add('hidden');
        }

        function printGD() {
            const gdContent = document.getElementById('gdContent').innerHTML;
            const printWindow = window.open('', '_blank');
            
            let printContent = gdContent;
            const wrapperMatch = gdContent.match(/<div[^>]*class="overflow-x-auto"[^>]*>([\s\S]*)<\/div>/);
            if (wrapperMatch) {
                printContent = wrapperMatch[1];
            } else {
                const tableMatch = gdContent.match(/<table[^>]*>[\s\S]*<\/table>/);
                if (tableMatch) {
                    printContent = tableMatch[0];
                }
            }
            
            // GD table is always in light mode, so no color conversion needed
            
            printWindow.document.write('<!DOCTYPE html><html><head><title>General Declaration</title><style>' +
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
                '</style></head><body>' + printContent + '</body></html>');
            printWindow.document.close();
            
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            };
        }

        // Close modal when clicking outside
        document.getElementById('gdModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeGDModal();
            }
        });
    </script>
</body>
</html>

