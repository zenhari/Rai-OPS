<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/camo_report/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get filter parameters
$filterStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$filterEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get all flights with crew information
$db = getDBConnection();

// Build query to get all crew fields
$crewFields = [];
$crewRoleFields = [];
for ($i = 1; $i <= 10; $i++) {
    $crewFields[] = "f.Crew{$i}";
    $crewRoleFields[] = "f.Crew{$i}_role";
}
$crewFieldsStr = implode(', ', $crewFields);
$crewRoleFieldsStr = implode(', ', $crewRoleFields);

// Build query with date range filter
$sql = "
    SELECT 
        f.id,
        f.TaskName,
        f.ScheduledTaskStatus as Status,
        f.TaskStart,
        f.TaskEnd,
        f.Route,
        f.Rego,
        f.FltDate,
        $crewFieldsStr,
        $crewRoleFieldsStr
    FROM flights f
    WHERE DATE(f.FltDate) BETWEEN ? AND ?
    ORDER BY f.TaskStart ASC, f.TaskName ASC
";

$stmt = $db->prepare($sql);
$stmt->execute([$filterStartDate, $filterEndDate]);
$flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for crew name lookup
$usersStmt = $db->query("SELECT id, first_name, last_name FROM users WHERE status = 'active'");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
$usersById = [];
foreach ($users as $user) {
    $usersById[$user['id']] = $user;
}

// Helper function to convert datetime to Excel serial date
function datetimeToExcelSerial($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '';
    }
    try {
        $date = new DateTime($datetime);
        $excelEpoch = new DateTime('1899-12-30');
        $diff = $date->diff($excelEpoch);
        $days = $diff->days;
        $hours = $date->format('H');
        $minutes = $date->format('i');
        $seconds = $date->format('s');
        $fraction = ($hours * 3600 + $minutes * 60 + $seconds) / 86400;
        return $days + $fraction;
    } catch (Exception $e) {
        return '';
    }
}

// Helper function to format crew member
function formatCrewMember($crewId, $crewRole, $usersById) {
    if (empty($crewId) || !isset($usersById[$crewId])) {
        return '';
    }
    $user = $usersById[$crewId];
    $name = trim(($user['last_name'] ?? '') . ', ' . ($user['first_name'] ?? ''));
    $role = $crewRole ?? '';
    return $role . ': ' . $name;
}

// Helper function to parse route
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

// Check which crew columns have data
$crewHasData = [];
for ($i = 1; $i <= 10; $i++) {
    $crewHasData[$i] = false;
    foreach ($flights as $flight) {
        $crewId = $flight["Crew{$i}"] ?? null;
        if (!empty($crewId)) {
            $crewHasData[$i] = true;
            break;
        }
    }
}

// Excel Export
if ($action === 'excel') {
    $filename = 'Camo_Report_' . $filterStartDate;
    if ($filterStartDate != $filterEndDate) {
        $filename .= '_to_' . $filterEndDate;
    }
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";
    
    // Start table
    echo '<table border="1">';
    
    // Header row
    echo '<tr>';
    echo '<th>TaskName</th>';
    echo '<th>Status</th>';
    echo '<th>Task Start Date</th>';
    echo '<th>Task Start Time</th>';
    echo '<th>Task End Date</th>';
    echo '<th>Task End Time</th>';
    echo '<th>Route</th>';
    echo '<th>Resources Text</th>';
    for ($i = 1; $i <= 10; $i++) {
        if ($crewHasData[$i]) {
            echo '<th>Crew' . $i . '</th>';
        }
    }
    echo '</tr>';
    
    // Data rows
    foreach ($flights as $flight) {
        // Parse TaskStart and TaskEnd
        $taskStartDate = '';
        $taskStartTime = '';
        $taskEndDate = '';
        $taskEndTime = '';
        
        if (!empty($flight['TaskStart']) && $flight['TaskStart'] != '0000-00-00 00:00:00') {
            try {
                $taskStart = new DateTime($flight['TaskStart']);
                $taskStartDate = $taskStart->format('Y-m-d');
                $taskStartTime = $taskStart->format('H:i:s');
            } catch (Exception $e) {
                // Keep empty
            }
        }
        
        if (!empty($flight['TaskEnd']) && $flight['TaskEnd'] != '0000-00-00 00:00:00') {
            try {
                $taskEnd = new DateTime($flight['TaskEnd']);
                $taskEndDate = $taskEnd->format('Y-m-d');
                $taskEndTime = $taskEnd->format('H:i:s');
            } catch (Exception $e) {
                // Keep empty
            }
        }
        
        // Parse Route
        $routeParts = parseRoute($flight['Route']);
        $route = $routeParts['origin'] . "\t" . $routeParts['destination'];
        
        // Resources Text (only Rego)
        $resourcesText = !empty($flight['Rego']) ? $flight['Rego'] : '';
        
        // Format crew members
        $crewMembers = [];
        for ($i = 1; $i <= 10; $i++) {
            $crewId = $flight["Crew{$i}"] ?? null;
            $crewRole = $flight["Crew{$i}_role"] ?? null;
            $crewMembers[$i] = formatCrewMember($crewId, $crewRole, $usersById);
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($flight['TaskName'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($flight['Status'] ?? '') . '</td>';
        echo '<td>' . $taskStartDate . '</td>';
        echo '<td>' . $taskStartTime . '</td>';
        echo '<td>' . $taskEndDate . '</td>';
        echo '<td>' . $taskEndTime . '</td>';
        echo '<td>' . $route . '</td>';
        echo '<td>' . htmlspecialchars($resourcesText) . '</td>';
        for ($i = 1; $i <= 10; $i++) {
            if ($crewHasData[$i]) {
                echo '<td>' . htmlspecialchars($crewMembers[$i]) . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camo Report - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        @media print {
            body * {
                visibility: hidden;
            }
            .print-content, .print-content * {
                visibility: visible;
            }
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            table {
                font-size: 10px;
            }
            th, td {
                padding: 4px 6px;
            }
        }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Camo Report</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Flight operations report with crew assignments</p>
                        </div>
                        <div class="flex space-x-3 no-print">
                            <a href="?start_date=<?php echo urlencode($filterStartDate); ?>&end_date=<?php echo urlencode($filterEndDate); ?>&action=excel" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-file-excel mr-2"></i>
                                Download Excel
                            </a>
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
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Report Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden print-content">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 no-print">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Camo Report</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Flight operations report with crew assignments</p>
                            </div>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center whitespace-nowrap">
                                    <i class="fas fa-filter mr-2"></i>
                                    <span class="hidden sm:inline">Filter by Date Range:</span>
                                    <span class="sm:hidden">Date Range:</span>
                                </label>
                                <form method="GET" action="" class="flex items-center gap-2 w-full sm:w-auto">
                                    <input type="date" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="<?php echo htmlspecialchars($filterStartDate); ?>"
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm flex-1 sm:flex-none"
                                           onchange="if (this.value > document.getElementById('end_date').value) { document.getElementById('end_date').value = this.value; } this.form.submit();">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">to</span>
                                    <input type="date" 
                                           id="end_date" 
                                           name="end_date" 
                                           value="<?php echo htmlspecialchars($filterEndDate); ?>"
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm flex-1 sm:flex-none"
                                           onchange="if (this.value < document.getElementById('start_date').value) { this.value = document.getElementById('start_date').value; } this.form.submit();">
                                    <button type="button" onclick="clearDateFilter()" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200 whitespace-nowrap">
                                        <i class="fas fa-times mr-1"></i>
                                        Clear
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">TaskName</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task End Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task End Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resources Text</th>
                                    <?php 
                                    $crewColumnsCount = 0;
                                    for ($i = 1; $i <= 10; $i++): 
                                        if ($crewHasData[$i]): 
                                            $crewColumnsCount++;
                                    ?>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew<?php echo $i; ?></th>
                                    <?php 
                                        endif;
                                    endfor; 
                                    ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php 
                                $colspan = 8 + $crewColumnsCount;
                                if (empty($flights)): ?>
                                    <tr>
                                        <td colspan="<?php echo $colspan; ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            <?php if ($filterStartDate == $filterEndDate): ?>
                                                No flights found for <?php echo date('Y-m-d', strtotime($filterStartDate)); ?>
                                            <?php else: ?>
                                                No flights found for the date range <?php echo date('Y-m-d', strtotime($filterStartDate)); ?> to <?php echo date('Y-m-d', strtotime($filterEndDate)); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($flights as $flight): ?>
                                        <?php
                                        // Parse TaskStart and TaskEnd
                                        $taskStartDate = '';
                                        $taskStartTime = '';
                                        $taskEndDate = '';
                                        $taskEndTime = '';
                                        $taskStartSerial = '';
                                        $taskEndSerial = '';
                                        
                                        if (!empty($flight['TaskStart']) && $flight['TaskStart'] != '0000-00-00 00:00:00') {
                                            try {
                                                $taskStart = new DateTime($flight['TaskStart']);
                                                $taskStartDate = $taskStart->format('Y-m-d');
                                                $taskStartTime = $taskStart->format('H:i:s');
                                                $taskStartSerial = datetimeToExcelSerial($flight['TaskStart']);
                                            } catch (Exception $e) {
                                                $taskStartSerial = '';
                                            }
                                        }
                                        
                                        if (!empty($flight['TaskEnd']) && $flight['TaskEnd'] != '0000-00-00 00:00:00') {
                                            try {
                                                $taskEnd = new DateTime($flight['TaskEnd']);
                                                $taskEndDate = $taskEnd->format('Y-m-d');
                                                $taskEndTime = $taskEnd->format('H:i:s');
                                                $taskEndSerial = datetimeToExcelSerial($flight['TaskEnd']);
                                            } catch (Exception $e) {
                                                $taskEndSerial = '';
                                            }
                                        }
                                        
                                        // Parse Route
                                        $routeParts = parseRoute($flight['Route']);
                                        
                                        // Format crew members
                                        $crewMembers = [];
                                        for ($i = 1; $i <= 10; $i++) {
                                            $crewId = $flight["Crew{$i}"] ?? null;
                                            $crewRole = $flight["Crew{$i}_role"] ?? null;
                                            $crewMembers[$i] = formatCrewMember($crewId, $crewRole, $usersById);
                                        }
                                        
                                        // Build Resources Text (only Rego)
                                        $resourcesText = !empty($flight['Rego']) ? htmlspecialchars($flight['Rego']) : '';
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['TaskName'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($flight['Status'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $taskStartDate; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $taskStartTime; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $taskEndDate; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $taskEndTime; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($routeParts['origin']); ?>	<?php echo htmlspecialchars($routeParts['destination']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $resourcesText; ?>
                                                </div>
                                            </td>
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <?php if ($crewHasData[$i]): ?>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 dark:text-white">
                                                            <?php echo htmlspecialchars($crewMembers[$i]); ?>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                            <?php endfor; ?>
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

    <script>
        function clearDateFilter() {
            window.location.href = 'index.php';
        }
    </script>
</body>
</html>

