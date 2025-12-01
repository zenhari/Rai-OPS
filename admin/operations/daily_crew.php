<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/daily_crew.php');

$user = getCurrentUser();

// Get date parameter - default to today
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$dateFormatted = date('M j, Y', strtotime($selectedDate));

// Get all flights for the selected date with crew members
$db = getDBConnection();
$stmt = $db->prepare("
    SELECT 
        f.id,
        f.FltDate,
        f.Route,
        f.TaskName,
        f.Rego,
        f.TaskStart,
        f.TaskEnd,
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
    ORDER BY f.Route ASC, f.TaskStart ASC
");
$stmt->execute([$selectedDate]);
$flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Allowed roles
$allowedRoles = ['PIC', 'TRI', 'TRE', 'SCC', 'NC'];

// Build crew list data grouped by flight
$flightGroups = [];
$crewNames = []; // Cache for crew names

foreach ($flights as $flight) {
    $route = $flight['Route'] ?? 'N/A';
    $taskName = $flight['TaskName'] ?? 'N/A';
    $rego = $flight['Rego'] ?? 'N/A';
    $date = date('Y-m-d', strtotime($flight['FltDate']));
    
    // Create flight key for grouping
    $flightKey = $date . '|' . $route . '|' . $taskName;
    
    // Initialize flight group if not exists
    if (!isset($flightGroups[$flightKey])) {
        $flightGroups[$flightKey] = [
            'date' => $date,
            'route' => $route,
            'task_name' => $taskName,
            'rego' => $rego,
            'task_start' => $flight['TaskStart'] ?? null,
            'task_end' => $flight['TaskEnd'] ?? null,
            'roles' => [] // Will store crew names by role
        ];
    }
    
    // Get all crew members for this flight
    for ($i = 1; $i <= 10; $i++) {
        $crewField = "Crew{$i}";
        $roleField = "Crew{$i}_role";
        
        if (!empty($flight[$crewField])) {
            $crewId = $flight[$crewField];
            $crewRole = trim($flight[$roleField] ?? '');
            
            // Filter by allowed roles only
            if (!in_array($crewRole, $allowedRoles)) {
                continue;
            }
            
            // Get crew name if not cached
            if (!isset($crewNames[$crewId])) {
                $user = getUserById($crewId);
                if ($user) {
                    $crewNames[$crewId] = $user['first_name'] . ' ' . $user['last_name'];
                } else {
                    $crewNames[$crewId] = "ID: {$crewId}";
                }
            }
            
            // Add crew to role in this flight group
            if (!isset($flightGroups[$flightKey]['roles'][$crewRole])) {
                $flightGroups[$flightKey]['roles'][$crewRole] = [];
            }
            
            // Avoid duplicates
            if (!in_array($crewNames[$crewId], $flightGroups[$flightKey]['roles'][$crewRole])) {
                $flightGroups[$flightKey]['roles'][$crewRole][] = $crewNames[$crewId];
            }
        }
    }
}

// Convert grouped data to list for display
$crewList = array_values($flightGroups);

// Find which roles are actually used (have at least one crew member)
$usedRoles = [];
foreach ($crewList as $flight) {
    if (isset($flight['roles']) && is_array($flight['roles'])) {
        foreach ($flight['roles'] as $role => $crewMembers) {
            if (!empty($crewMembers)) {
                $usedRoles[$role] = true;
            }
        }
    }
}

// Filter allowed roles to only show used ones
$displayRoles = array_filter($allowedRoles, function($role) use ($usedRoles) {
    return isset($usedRoles[$role]);
});
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Crew - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .print-section table {
                border-collapse: collapse;
                width: 100%;
            }
            .print-section th,
            .print-section td {
                border: 1px solid #000;
                padding: 8px;
            }
            .print-section img {
                max-height: 80px;
            }
        }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Daily Crew</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Crew members assigned to flights</p>
                        </div>
                        <div class="flex items-center space-x-3 no-print">
                            <div class="flex items-center space-x-2">
                                <label for="date-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">Date:</label>
                                <input type="date" id="date-select" value="<?php echo $selectedDate; ?>" 
                                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                       onchange="changeDate()">
                            </div>
                            <button onclick="window.print()" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-print mr-2"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../includes/permission_banner.php'; ?>
                
                <!-- Daily Crew Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden print-section">
                    <!-- Logo at the top -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-center">
                        <img src="/assets/raimon.png" alt="Raimon Airways" 
                             class="h-20 w-auto mx-auto dark:hidden" 
                             onerror="this.style.display='none';">
                        <img src="/assets/raimon-dark.png" alt="Raimon Airways" 
                             class="h-20 w-auto mx-auto hidden dark:block" 
                             onerror="this.style.display='none';">
                    </div>
                    
                    <!-- Header with Title -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="text-center">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                Raimon Airways Daily Crew List
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 no-print">
                                Crew List for <?php echo $dateFormatted; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Flight No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Aircraft Register</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Aircraft Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Departure</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Arrival</th>
                                    <?php foreach ($displayRoles as $role): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars($role); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($crewList)): ?>
                                    <tr>
                                        <td colspan="<?php echo 7 + count($displayRoles); ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-600">
                                            No crew members found for <?php echo $dateFormatted; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($crewList as $item): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                <?php echo date('M j, Y', strtotime($item['date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                <?php echo htmlspecialchars($item['route']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                <?php echo htmlspecialchars($item['task_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                <?php echo htmlspecialchars($item['rego']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                ERJ-145
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                <?php 
                                                if (!empty($item['task_start'])) {
                                                    try {
                                                        $taskStart = new DateTime($item['task_start']);
                                                        echo $taskStart->format('H:i');
                                                    } catch (Exception $e) {
                                                        echo htmlspecialchars($item['task_start']);
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                <?php 
                                                if (!empty($item['task_end'])) {
                                                    try {
                                                        $taskEnd = new DateTime($item['task_end']);
                                                        echo $taskEnd->format('H:i');
                                                    } catch (Exception $e) {
                                                        echo htmlspecialchars($item['task_end']);
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <?php foreach ($displayRoles as $role): ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                                    <?php 
                                                    if (isset($item['roles'][$role]) && !empty($item['roles'][$role])) {
                                                        echo htmlspecialchars(implode(', ', $item['roles'][$role]));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Contact Information Footer -->
                    <div class="px-6 py-6 border-t border-gray-200 dark:border-gray-700 mt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-700 dark:text-gray-300">
                            <div>
                                <p class="font-semibold mb-2">Support Mobile1:</p>
                                <p>Mr. Bedakhanian +989900726233</p>
                            </div>
                            <div>
                                <p class="font-semibold mb-2">Support Mobile2:</p>
                                <p>Ms. Gholami +989122896594</p>
                            </div>
                            <div>
                                <p class="font-semibold mb-2">Support Email:</p>
                                <p>scheduling@raimonairways.net</p>
                            </div>
                            <div>
                                <p class="font-semibold mb-2">Address:</p>
                                <p>Tehransar, Golha Boulevard, Corner of 16th Alley, No. 7</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changeDate() {
            const dateInput = document.getElementById('date-select');
            const selectedDate = dateInput.value;
            if (selectedDate) {
                window.location.href = '?date=' + selectedDate;
            }
        }
        
        // Initialize dark mode from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const html = document.documentElement;
            
            if (savedDarkMode === 'true') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        });
    </script>
</body>
</html>

