<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/journey_log_list.php');

$current_user = getCurrentUser();

// Get all journey log entries
$journeyLogs = getAllJourneyLogEntries();

// Function to get all journey log entries with related data
function getAllJourneyLogEntries() {
    try {
        $db = getDBConnection();
        
        // Check if journey_log table exists
        $stmt = $db->query("SHOW TABLES LIKE 'journey_log'");
        $journeyLogTableExists = $stmt->rowCount() > 0;
        
        if ($journeyLogTableExists) {
            // Use new journey_log table
            // Try to use JSON_LENGTH, fallback to PHP counting if not available
            try {
                $stmt = $db->prepare("
                    SELECT 
                        jl.*,
                        CASE 
                            WHEN jl.flights_data IS NOT NULL AND jl.flights_data != '' 
                            THEN JSON_LENGTH(jl.flights_data) 
                            ELSE 0 
                        END as flight_count,
                        CASE 
                            WHEN jl.crew_data IS NOT NULL AND jl.crew_data != '' 
                            THEN JSON_LENGTH(jl.crew_data) 
                            ELSE 0 
                        END as crew_count
                    FROM journey_log jl
                    ORDER BY jl.selected_date DESC, jl.created_at DESC
                ");
                
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Fallback: Get all data and count in PHP
                $stmt = $db->prepare("
                    SELECT jl.*
                    FROM journey_log jl
                    ORDER BY jl.selected_date DESC, jl.created_at DESC
                ");
                
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Convert JSON counts to integers and ensure counts are set
            foreach ($results as &$result) {
                if (!isset($result['flight_count']) || $result['flight_count'] === null) {
                    $flightsData = json_decode($result['flights_data'] ?? '[]', true);
                    $result['flight_count'] = is_array($flightsData) ? count($flightsData) : 0;
                } else {
                    $result['flight_count'] = (int)$result['flight_count'];
                }
                
                if (!isset($result['crew_count']) || $result['crew_count'] === null) {
                    $crewData = json_decode($result['crew_data'] ?? '[]', true);
                    $result['crew_count'] = is_array($crewData) ? count($crewData) : 0;
                } else {
                    $result['crew_count'] = (int)$result['crew_count'];
                }
            }
            
            return $results;
        } else {
            // Fallback to old tables if they exist
            $stmt = $db->query("SHOW TABLES LIKE 'journey_log_entries'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("
                    SELECT 
                        jle.*,
                        COUNT(DISTINCT jlf.id) as flight_count,
                        COUNT(DISTINCT jlc.id) as crew_count
                    FROM journey_log_entries jle
                    LEFT JOIN journey_log_flights jlf ON jle.id = jlf.journey_log_id
                    LEFT JOIN journey_log_crew jlc ON jle.id = jlc.journey_log_id
                    GROUP BY jle.id
                    ORDER BY jle.selected_date DESC, jle.created_at DESC
                ");
                
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return [];
        }
        
    } catch (Exception $e) {
        error_log("Error getting journey log entries: " . $e->getMessage());
        return [];
    }
}

// Function to get journey log details
function getJourneyLogDetails($logId) {
    try {
        $db = getDBConnection();
        
        // Check if journey_log table exists
        $stmt = $db->query("SHOW TABLES LIKE 'journey_log'");
        $journeyLogTableExists = $stmt->rowCount() > 0;
        
        if ($journeyLogTableExists) {
            // Use new journey_log table
            $stmt = $db->prepare("SELECT * FROM journey_log WHERE id = ?");
            $stmt->execute([$logId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$entry) {
                return null;
            }
            
            // Decode flights data from JSON
            $flights = [];
            if (!empty($entry['flights_data'])) {
                $flightsData = json_decode($entry['flights_data'], true);
                if (is_array($flightsData)) {
                    $flights = $flightsData;
                }
            }
            
            // Decode crew data from JSON
            $crew = [];
            if (!empty($entry['crew_data'])) {
                $crewData = json_decode($entry['crew_data'], true);
                if (is_array($crewData)) {
                    $crew = $crewData;
                }
            }
            
            return [
                'entry' => $entry,
                'flights' => $flights,
                'crew' => $crew
            ];
        } else {
            // Fallback to old tables
            $stmt = $db->prepare("SELECT * FROM journey_log_entries WHERE id = ?");
            $stmt->execute([$logId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$entry) {
                return null;
            }
            
            // Get flights
            $stmt = $db->query("SHOW TABLES LIKE 'journey_log_flights'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("SELECT * FROM journey_log_flights WHERE journey_log_id = ? ORDER BY flight_number");
                $stmt->execute([$logId]);
                $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $flights = [];
            }
            
            // Get crew
            $stmt = $db->query("SHOW TABLES LIKE 'journey_log_crew'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("SELECT * FROM journey_log_crew WHERE journey_log_id = ? ORDER BY crew_number");
                $stmt->execute([$logId]);
                $crew = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $crew = [];
            }
            
            return [
                'entry' => $entry,
                'flights' => $flights,
                'crew' => $crew
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error getting journey log details: " . $e->getMessage());
        return null;
    }
}

// Handle AJAX request for details
if ($_GET['action'] ?? '' === 'get_details') {
    $logId = intval($_GET['log_id'] ?? 0);
    $details = getJourneyLogDetails($logId);
    
    header('Content-Type: application/json');
    echo json_encode($details);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journey Log List - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Journey Log List</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and manage all journey logs</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <a href="journey_log.php" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Create New Log
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Journey Logs Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">All Journey Logs</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total: <?php echo count($journeyLogs); ?> logs</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pilot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flights</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($journeyLogs)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No journey logs found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($journeyLogs as $log): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($log['pilot_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php 
                                                    $date = $log['selected_date'] ?? $log['log_date'] ?? '';
                                                    echo $date ? date('M j, Y', strtotime($date)) : 'N/A'; 
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($log['sector_aircraft_type'] ?? $log['aircraft_type'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($log['sector_aircraft_reg'] ?? $log['aircraft_registration'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <?php echo $log['flight_count']; ?> flights
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    <?php echo $log['crew_count']; ?> crew
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="viewDetails(<?php echo $log['id']; ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                <a href="journey_log.php?step=3&selected_date=<?php echo urlencode($log['selected_date'] ?? $log['log_date'] ?? ''); ?>&selected_pilot=<?php echo urlencode($log['pilot_name']); ?>" 
                                                   class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
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

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-[90%] max-w-6xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Journey Log Details</h3>
                    <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="detailsContent" class="space-y-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewDetails(logId) {
            // Show loading state
            document.getElementById('detailsContent').innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i>
                    <span class="ml-2 text-gray-600">Loading details...</span>
                </div>
            `;
            
            document.getElementById('detailsModal').classList.remove('hidden');
            
            // Fetch details
            fetch(`?action=get_details&log_id=${logId}`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        displayDetails(data);
                    } else {
                        document.getElementById('detailsContent').innerHTML = `
                            <div class="text-center py-8 text-red-600">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Error loading details</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('detailsContent').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <p>Error loading details</p>
                        </div>
                    `;
                });
        }

        function displayDetails(data) {
            const entry = data.entry;
            const flights = data.flights || [];
            const crew = data.crew || [];
            
            const logoPath = '/assets/raimon.png';
            
            let content = `
                <!-- Journey Log Header -->
                <div class="mb-4">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                        <tr>
                            <td colspan="19" style="text-align:center; vertical-align:middle; padding:10px; border:1px solid #000; background-color: #fff;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                    <tr>
                                        <td style="width:20%; text-align:center; vertical-align:middle;">
                                            <img src="${logoPath}" alt="RAIMON AIRWAYS" style="max-width: 150px; max-height: 80px; height: auto; width: auto; object-fit: contain;" onerror="this.style.display='none';">
                                        </td>
                                        <td style="width:60%; text-align:center; vertical-align:middle;">
                                            <div style="font-size: 28px; font-weight: bold; font-style: italic; color: #000; letter-spacing: 1px;">RAIMON AIRWAYS JOURNEY LOG</div>
                                        </td>
                                        <td style="width:20%; text-align:center; vertical-align:middle;">
                                            &nbsp;
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="19" style="text-align:center; padding:5px; background-color: #000; color: #fff; font-size: 11px; font-weight: normal;">
                                ALL TIMES ARE IN UTC, EXCEPT FLIGHT DUTY PERIOD TIMES IN LOCAL
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Flight Information Table (19 columns) -->
                <div class="mb-6">
                    <table border="1" cellpadding="4" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid #000; font-size: 11px;">
                        <!-- Header Row -->
                        <tr>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; vertical-align: top; border-bottom: 0; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">*CVR
                            </td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; border-bottom: 1px solid #000; font-size: 11px;">
                                Aircraft Type
                            </td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; border-bottom: 1px solid #000; font-size: 11px;">
                                Aircraft
                            </td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; border-bottom: 1px solid #000; font-size: 11px;">
                                Flight Date
                            </td>
                            <td colspan="6" style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 1px solid #000; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                    <tr>
                                        <td style="text-align: center; padding: 2px; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">Flown BY
                                        </td>
                                        <td style="text-align: center; padding: 2px; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">INST
                                        </td>
                                        <td style="text-align: center; padding: 2px; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">CAPT
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="6" style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 1px solid #000; border-right: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                    <tr>
                                        <td style="text-align: center; padding: 2px; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">Flown BY
                                        </td>
                                        <td style="text-align: center; padding: 2px; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">INST
                                        </td>
                                        <td style="text-align: center; padding: 2px; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">COP
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <!-- Data Row -->
                        <tr>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; vertical-align: top; border-top: 0; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">**ASR
                            </td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-size: 11px;">
                                ${entry.sector_aircraft_type || entry.aircraft_type || 'N/A'}
                            </td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-size: 11px;">
                                ${entry.sector_aircraft_reg || entry.aircraft_registration || 'N/A'}
                            </td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-size: 11px;">
                                ${entry.selected_date ? new Date(entry.selected_date).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }) : (entry.log_date ? new Date(entry.log_date).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'N/A')}
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 1px solid #000; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">1
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">2
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">3
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">4
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">5
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">6
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 1px solid #000; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">1
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">2
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">3
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">4
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 0; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">5
                            </td>
                            <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: 0; border-right: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">
                                <input type="checkbox" style="margin-right: 4px;">6
                            </td>
                        </tr>
                    </table>
                </div>
            `;
            
            // Main Flight Data Table
            content += `
                <div class="mb-4">
                    <table border="1" cellpadding="3" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid #000; font-size: 11px;">
                        <!-- Header Row -->
                        <tr>
                            <td rowspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">NO</td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Flight Data</td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Sectors</td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Scheduled Time</td>
                            <td colspan="6" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Flight Time</td>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Delay</td>
                            <td colspan="4" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Fuel LTR/KG S/LBS</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Tech Log NO</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Flight No</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">From</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">To</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">STD</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">STA</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Off Block</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">T/O Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Land Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">On Block</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Flight Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Block Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Code</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Uplift LTR</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Ramp Fuel</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">ARR Fuel</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Total Used</td>
                        </tr>
            `;
            
            // Flight Data Rows - Support both old and new data structures
            const maxFlights = Math.max(flights.length, 20);
            for (let i = 0; i < maxFlights && i < 20; i++) {
                const flight = flights[i] || {};
                // Support both old structure (from_airport, to_airport) and new structure (from, to)
                const fromAirport = flight.from_airport || flight.from || '';
                const toAirport = flight.to_airport || flight.to || '';
                const flightNo = flight.flight_no || flight.flight_no || '';
                const offBlock = flight.off_block || '';
                const takeoff = flight.takeoff_time || flight.takeoff || '';
                const landing = flight.land_time || flight.landing || '';
                const onBlock = flight.on_block || '';
                const flightTime = flight.flight_time || '';
                const tripTime = flight.trip_time || flight.block_time || '';
                const upliftLtr = flight.uplift_ltr || '';
                const rampFuel = flight.ramp_fuel || '';
                const arrFuel = flight.arr_fuel || '';
                const totalFuel = flight.total_used || flight.total_fuel || '';
                
                // Format time from HHMM to HH:MM if needed
                const formatTime = (timeStr) => {
                    if (!timeStr || timeStr === ':') return ':';
                    if (timeStr.length === 4 && /^\d{4}$/.test(timeStr)) {
                        return timeStr.substring(0, 2) + ':' + timeStr.substring(2, 4);
                    }
                    return timeStr;
                };
                
                content += `
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${i + 1}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${flight.atl_no || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${flightNo || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${fromAirport || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${toAirport || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${flight.std || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${flight.sta || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${formatTime(offBlock) || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${formatTime(takeoff) || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${formatTime(landing) || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${formatTime(onBlock) || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${formatTime(flightTime) || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${formatTime(tripTime) || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${flight.delay_time || flight.night_time || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${flight.delay_code || flight.fuel_page_no || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${upliftLtr || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${rampFuel || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${arrFuel || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${totalFuel || ':'}</td>
                        </tr>
                `;
            }
            
            // Total Row
            content += `
                        <tr>
                            <td colspan="13" style="border: 1px solid #000; padding: 4px; text-align: right; font-weight: bold;">Total:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td colspan="4" style="border: 1px solid #000; padding: 4px;"></td>
                        </tr>
                    </table>
                </div>
            `;
            
            // Crew Section with Commander Comments
            const totalRows = Math.max(crew.length, 7);
            content += `
                <div class="mb-4">
                    <table border="1" cellpadding="3" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid #000; font-size: 11px;">
                        <tr>
                            <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Crew</td>
                            <td colspan="5" style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Flight Crew Records</td>
                            <td rowspan="${totalRows + 1}" style="border: 1px solid #000; padding: 4px; vertical-align: top; width: 35%;">
                                <div style="font-weight: bold; margin-bottom: 4px;">Commander Comments</div>
                                <div style="min-height: 120px; border: 1px solid #ccc; padding: 4px; margin-bottom: 8px; background-color: #fff; white-space: pre-wrap; word-wrap: break-word;">${entry.commander_comments || ''}</div>
                                ${entry.commander_signature ? `<div style="margin-top: 8px; font-size: 10px; color: #666;">Signature: ${entry.commander_signature}</div>` : ''}
                                <table border="1" cellpadding="4" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid #000; font-size: 11px; margin-top: 8px;">
                                    <tr>
                                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">Schedule
                                            <span style="border-left: 1px solid #000; margin: 0 8px; display: inline-block; height: 14px; vertical-align: middle;"></span>
                                            <input type="checkbox" style="margin-right: 4px;">Non Schedule
                                        </td>
                                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">
                                            <input type="checkbox" style="margin-right: 4px;">Observation
                                            <span style="border-left: 1px solid #000; margin: 0 8px; display: inline-block; height: 14px; vertical-align: middle;"></span>
                                            <input type="checkbox" style="margin-right: 4px;">Incidence
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px; min-height: 60px; vertical-align: top;">
                                            <div style="margin-bottom: 4px;">Commander Signature</div>
                                            <div style="min-height: 50px; border: 1px solid #ccc; margin-top: 4px;"></div>
                                        </td>
                                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px; min-height: 60px; vertical-align: top;">
                                            <div style="margin-bottom: 4px;">CAP</div>
                                            <div style="min-height: 50px; border: 1px solid #ccc; margin-top: 4px;"></div>
                                        </td>
                                    </tr>
                                </table>
                                <div style="margin-top: 20px; font-size: 10px; color: #666;">
                                    <div>*CVR: COMMENDER VOYAGE REPORT FILED</div>
                                    <div>**ASR: AIR SAFETY REPORT</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Position</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Name</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">ID NO</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">RPT Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">ENG Shut</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">FDP Time</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center; background-color: #f0f0f0; font-weight: bold;">Sector(s)</td>
                        </tr>
            `;
            
            // Crew Data Rows
            crew.forEach(member => {
                const reportingHr = String(member.reporting_hr || '00').padStart(2, '0');
                const reportingMin = String(member.reporting_min || '00').padStart(2, '0');
                const engShutdownHr = String(member.eng_shutdown_hr || '00').padStart(2, '0');
                const engShutdownMin = String(member.eng_shutdown_min || '00').padStart(2, '0');
                
                content += `
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${member.crew_rank || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: left;">${member.crew_name || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${member.crew_national_id || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${reportingHr}:${reportingMin}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${engShutdownHr}:${engShutdownMin}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">${member.fdp_time || ':'}</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                        </tr>
                `;
            });
            
            // Add empty rows if needed
            for (let i = crew.length; i < 7; i++) {
                content += `
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: left;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;">:</td>
                        </tr>
                `;
            }
            
            content += `
                    </table>
                </div>
            `;
            
            document.getElementById('detailsContent').innerHTML = content;
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target === modal) {
                closeDetailsModal();
            }
        }
    </script>
</body>
</html>
