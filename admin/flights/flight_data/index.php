<?php
require_once '../../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

// Check page access - use enhanced check to avoid 500 error if permission doesn't exist yet
if (!checkPageAccessEnhanced('admin/flights/flight_data/index.php')) {
    $encodedPage = urlencode('admin/flights/flight_data/index.php');
    header("Location: /access_denied.php?page=$encodedPage");
    exit();
}

$user = getCurrentUser();

// Get flights for today only
$today = date('Y-m-d');

// Get flights for today
try {
    $todayFlights = getFlightsForMonitoring($today);
    if (!is_array($todayFlights)) {
        $todayFlights = [];
    }
} catch (Exception $e) {
    error_log("Error getting flights for monitoring: " . $e->getMessage());
    $todayFlights = [];
}

// Group flights by aircraft
$flightsByAircraft = [];

// Find earliest start and latest end times (considering delays)
$earliestStart = null;
$latestEnd = null;

foreach ($todayFlights as $flight) {
    $aircraft_rego = $flight['aircraft_rego'] ?? 'Unknown';
    
    // Group by aircraft
    if (!isset($flightsByAircraft[$aircraft_rego])) {
        $flightsByAircraft[$aircraft_rego] = [];
    }
    $flightsByAircraft[$aircraft_rego][] = $flight;
    
    // Track earliest start and latest end - use actual_out_utc and actual_in_utc (fallback to TaskStart/TaskEnd)
    $actualStart = !empty($flight['actual_out_utc']) ? $flight['actual_out_utc'] : ($flight['TaskStart'] ?? null);
    $actualEnd = !empty($flight['actual_in_utc']) ? $flight['actual_in_utc'] : ($flight['TaskEnd'] ?? null);
    
    // Calculate delay for this flight
    $delay_minutes = 0;
    for ($i = 1; $i <= 5; $i++) {
        $minutesField = $i === 1 ? 'minutes_1' : "minutes_$i";
        if (!empty($flight[$minutesField])) {
            $minutes_value = intval($flight[$minutesField]);
            if ($minutes_value > 0) {
                $delay_minutes += $minutes_value;
            }
        }
    }
    
    // Use TaskStart (before delay) for timeline start
    if ($actualStart) {
        try {
            $taskStart = new DateTime($actualStart);
            if ($earliestStart === null || $taskStart < $earliestStart) {
                $earliestStart = clone $taskStart;
            }
        } catch (Exception $e) {
            // Skip if invalid date
        }
    }
    
    // Use TaskEnd + delay for timeline end
    if ($actualEnd) {
        try {
            $taskEnd = new DateTime($actualEnd);
            // Add delay to TaskEnd
            if ($delay_minutes > 0) {
                $taskEnd->modify('+' . $delay_minutes . ' minutes');
            }
            if ($latestEnd === null || $taskEnd > $latestEnd) {
                $latestEnd = clone $taskEnd;
            }
        } catch (Exception $e) {
            // Skip if invalid date
        }
    }
}

// Sort aircraft by Rego (A to Z) - ascending order
ksort($flightsByAircraft, SORT_STRING | SORT_FLAG_CASE);

// Calculate maximum flights per day PER AIRCRAFT for grid lines
$maxFlightsPerDay = 0;
$maxFlightsPerAircraftPerDay = [];

// Count flights per aircraft
foreach ($flightsByAircraft as $aircraft_rego => $flights) {
    $flightCount = count($flights);
    
    // Track maximum flights per day for each aircraft
    if (!isset($maxFlightsPerAircraftPerDay[$aircraft_rego])) {
        $maxFlightsPerAircraftPerDay[$aircraft_rego] = 0;
    }
    if ($flightCount > $maxFlightsPerAircraftPerDay[$aircraft_rego]) {
        $maxFlightsPerAircraftPerDay[$aircraft_rego] = $flightCount;
    }
}

// Find the overall maximum flights per day across all aircraft
foreach ($maxFlightsPerAircraftPerDay as $aircraft_rego => $maxFlights) {
    if ($maxFlights > $maxFlightsPerDay) {
        $maxFlightsPerDay = $maxFlights;
    }
}

// Ensure minimum of 1 line even if no flights
if ($maxFlightsPerDay < 1) {
    $maxFlightsPerDay = 1;
}

// Calculate timeline for today (00:00 to 23:59)
$timelineStart = new DateTime($today . ' 00:00:00');
$timelineEnd = new DateTime($today . ' 23:59:59');

$timelineHours = [];
// Generate hours for today (24 hours)
$current = clone $timelineStart;

while ($current <= $timelineEnd) {
    $hour = $current->format('H:i');
    
    $timelineHours[] = [
        'hour' => $hour,
        'date' => $today,
        'dateLabel' => '',
        'timestamp' => $current->getTimestamp()
    ];
    
    $current->modify('+1 hour');
}

$totalHours = count($timelineHours);

// Calculate current time position for timeline indicator
$currentTimePosition = null;
$currentTimeDisplay = null;
try {
    // Get current time in Tehran timezone
    $tehranTimezone = new DateTimeZone('Asia/Tehran');
    $currentTime = new DateTime('now', $tehranTimezone);
    $currentTimeDisplay = $currentTime->format('H:i');
    
    // Check if current date is today
    $currentDate = $currentTime->format('Y-m-d');
    if ($currentDate === $today && $timelineStart && $timelineEnd) {
        // Calculate position of current time in timeline (as percentage)
        $currentTimestamp = $currentTime->getTimestamp();
        $timelineStartTimestamp = $timelineStart->getTimestamp();
        $timelineEndTimestamp = $timelineEnd->getTimestamp();
        $timelineDuration = $timelineEndTimestamp - $timelineStartTimestamp;
        
        if ($currentTimestamp >= $timelineStartTimestamp && $currentTimestamp <= $timelineEndTimestamp) {
            $currentTimeOffset = $currentTimestamp - $timelineStartTimestamp;
            $currentTimePosition = ($currentTimeOffset / $timelineDuration) * 100;
        }
    }
} catch (Exception $e) {
    // If timezone not available, skip current time indicator
    error_log("Error calculating current time position: " . $e->getMessage());
}

// Function to get status color based on status name
function getStatusColor($status) {
    $statusColors = [
        'Boarding' => '#ADD8E6',
        'Cancelled' => '#FF0000',
        'Complete' => '#32CD32',
        'Confirmed' => '#242526',
        'Delayed' => '#FFA500',
        'Diverted' => '#8B0000',
        'Gate Closed' => '#FFD700',
        'Landed' => '#1E90FF',
        'Off Block' => '#A0522D',
        'On Block' => '#9370DB',
        'Pending' => '#808080',
        'Ready' => '#00FF00',
        'Return to Ramp' => '#FF8C00',
        'Start' => '#4682B4',
        'Takeoff' => '#228B22',
        'Taxi' => '#2F4F4F'
    ];
    return $statusColors[$status] ?? '#3b82f6';
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Data - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../../../assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        .timeline-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        
        .timeline-wrapper {
            min-width: 100%;
            width: max-content;
        }
        
        .timeline-hour {
            min-width: 120px;
            width: 120px;
            flex-shrink: 0;
            position: relative;
        }
        
        .timeline-row {
            overflow: hidden;
            position: relative;
        }
        
        .timeline-row-grid {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 1;
        }
        
        .horizontal-grid-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 1px;
            background-color: rgba(156, 163, 175, 0.2);
            pointer-events: none;
            z-index: 1;
        }
        
        .dark .horizontal-grid-line {
            background-color: rgba(75, 85, 99, 0.3);
        }
        
        .flight-bar {
            position: absolute;
            height: 32px;
            border-radius: 4px;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Flight Time Labels - Below flight bars */
        .flight-time-labels {
            position: absolute;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2px 4px;
            font-size: 11px;
            pointer-events: none;
            z-index: 8;
        }
        
        .flight-time-start {
            font-weight: 600;
            color: #111827;
            font-size: 11px;
        }
        
        .flight-time-end {
            font-weight: 600;
            color: #111827;
            font-size: 11px;
        }
        
        .dark .flight-time-start {
            color: #ffffff;
        }
        
        .dark .flight-time-end {
            color: #ffffff;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-time-start {
                color: #ffffff;
            }
            
            .flight-time-end {
                color: #ffffff;
            }
        }
        
        /* Current Time Indicator (Yellow Line) */
        .current-time-indicator {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            z-index: 20;
            pointer-events: none;
        }
        
        .current-time-line {
            width: 100%;
            height: 100%;
            background-color: #fbbf24;
            box-shadow: 0 0 4px rgba(251, 191, 36, 0.6);
        }
        
        .current-time-label {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #fbbf24;
            color: #78350f;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .current-time-label i {
            font-size: 9px;
        }
        
        .dark .current-time-label {
            background-color: #fbbf24;
            color: #78350f;
        }
        
        /* Flight Details Modal Styles */
        .flight-details-modal {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            animation: fadeIn 0.2s ease-out;
        }
        
        .flight-details-modal-content {
            animation: slideUp 0.3s ease-out;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        .flight-details-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .dark .flight-details-header {
            background: linear-gradient(135deg, #4c51bf 0%, #553c9a 100%);
        }
        
        .flight-details-body {
            max-height: calc(90vh - 180px);
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        .flight-details-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .flight-details-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        .flight-details-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .flight-details-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .dark .flight-details-body::-webkit-scrollbar-track {
            background: #1e293b;
        }
        
        .dark .flight-details-body::-webkit-scrollbar-thumb {
            background: #475569;
        }
        
        .flight-details-body::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        
        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .info-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .dark .info-card {
            background: #1e293b;
            border-color: #334155;
        }
        
        .info-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dark .info-card-title {
            color: #cbd5e1;
            border-bottom-color: #334155;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .dark .info-row {
            border-bottom-color: #334155;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .dark .info-label {
            color: #94a3b8;
        }
        
        .info-value {
            font-size: 0.875rem;
            color: #1e293b;
            font-weight: 600;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        .dark .info-value {
            color: #f1f5f9;
        }
        
        .delay-card {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #fca5a5;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .dark .delay-card {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            border-color: #dc2626;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                Flight Data
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Today's Flight Timeline - <?php echo date('l, F j, Y'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Today's Flight Timeline -->
                <?php if (!empty($flightsByAircraft)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-clock mr-2"></i>Today's Flight Timeline
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="w-full">
                            <div class="timeline-container">
                                <div class="timeline-wrapper py-6">
                                <!-- Timeline Header -->
                                <div class="flex mb-4 relative" style="min-width: <?php echo $totalHours * 120; ?>px;">
                                    <div class="w-32 flex-shrink-0"></div>
                                    <div class="flex relative" style="width: <?php echo $totalHours * 120; ?>px;">
                                        <?php 
                                        foreach ($timelineHours as $hourData): 
                                            $hour = is_array($hourData) ? $hourData['hour'] : $hourData;
                                        ?>
                                            <div class="timeline-hour text-center text-xs text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-600 relative">
                                                <?php echo $hour; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <!-- Current Time Indicator (Yellow Line) -->
                                        <?php if ($currentTimePosition !== null): ?>
                                        <div class="current-time-indicator" style="left: <?php echo number_format($currentTimePosition, 2); ?>%;">
                                            <div class="current-time-line"></div>
                                            <div class="current-time-label">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo $currentTimeDisplay; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Aircraft Rows -->
                                <?php 
                                foreach ($flightsByAircraft as $aircraft_rego => $flights): 
                                    // Get max flights per day for this specific aircraft
                                    $aircraftMaxFlightsPerDay = isset($maxFlightsPerAircraftPerDay[$aircraft_rego]) 
                                        ? $maxFlightsPerAircraftPerDay[$aircraft_rego] 
                                        : $maxFlightsPerDay;
                                    
                                    // Ensure minimum of 1
                                    if ($aircraftMaxFlightsPerDay < 1) {
                                        $aircraftMaxFlightsPerDay = 1;
                                    }
                                    
                                    // Calculate height based on max flights per day for this aircraft
                                    $min_row_height = 6 + (32 + 2 + 14 + 2) + (($aircraftMaxFlightsPerDay - 1) * 50) + 30 + 8;
                                ?>
                                    <div class="flex mb-2" style="min-width: <?php echo ($totalHours * 120) + 128; ?>px;">
                                        <!-- Aircraft Label -->
                                        <div class="w-32 flex-shrink-0 flex items-center">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($aircraft_rego); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Timeline Row -->
                                        <div class="timeline-row flex-1 relative bg-gray-50 dark:bg-gray-700 rounded" style="min-height: <?php echo $min_row_height; ?>px; width: <?php echo $totalHours * 120; ?>px;">
                                            <!-- Horizontal Grid Lines -->
                                            <div class="timeline-row-grid">
                                                <?php 
                                                for ($i = 0; $i < $aircraftMaxFlightsPerDay; $i++): 
                                                    $lineTop = 6 + ($i * 50);
                                                ?>
                                                    <div class="horizontal-grid-line" style="top: <?php echo $lineTop; ?>px;"></div>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Current Time Indicator (Yellow Line) -->
                                            <?php if ($currentTimePosition !== null): ?>
                                            <div class="current-time-indicator" style="left: <?php echo number_format($currentTimePosition, 2); ?>%;">
                                                <div class="current-time-line"></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            foreach ($flights as $index => $flight): 
                                                // Get TaskStart and TaskEnd (scheduled times)
                                                $taskStart = $flight['TaskStart'] ?? null;
                                                $taskEnd = $flight['TaskEnd'] ?? null;
                                                
                                                // Use actual_out_utc if available and not null, otherwise use TaskStart
                                                // Use actual_in_utc if available and not null, otherwise use TaskEnd
                                                $actualStart = (!empty($flight['actual_out_utc']) && $flight['actual_out_utc'] !== null) 
                                                    ? $flight['actual_out_utc'] 
                                                    : $taskStart;
                                                $actualEnd = (!empty($flight['actual_in_utc']) && $flight['actual_in_utc'] !== null) 
                                                    ? $flight['actual_in_utc'] 
                                                    : $taskEnd;
                                            
                                            if ($timelineStart && $timelineEnd && $actualStart && $actualEnd):
                                            // Calculate positions and durations relative to timeline range
                                            try {
                                                $task_start_dt = $taskStart ? new DateTime($taskStart) : null;
                                                $task_end_dt = $taskEnd ? new DateTime($taskEnd) : null;
                                                $actual_start_dt = new DateTime($actualStart);
                                                $actual_end_dt = new DateTime($actualEnd);
                                            } catch (Exception $e) {
                                                continue;
                                            }
                                            
                                            // Calculate total timeline duration in seconds
                                            $timelineDuration = $timelineEnd->getTimestamp() - $timelineStart->getTimestamp();
                                            
                                            if ($timelineDuration > 0) {
                                                $timeline_start_ts = $timelineStart->getTimestamp();
                                                $timeline_end_ts = $timelineEnd->getTimestamp();
                                                
                                                // Calculate delay from minutes_1 to minutes_5
                                                $delay_minutes = 0;
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $minutesField = $i === 1 ? 'minutes_1' : "minutes_$i";
                                                    if (!empty($flight[$minutesField])) {
                                                        $minutes_value = intval($flight[$minutesField]);
                                                        if ($minutes_value > 0) {
                                                            $delay_minutes += $minutes_value;
                                                        }
                                                    }
                                                }
                                                
                                                // Determine flight bar start and end
                                                $flight_start_timestamp = $actual_start_dt->getTimestamp();
                                                $flight_end_timestamp = $actual_end_dt->getTimestamp();
                                                
                                                // Determine delay bar start and end
                                                $delay_start_timestamp = 0;
                                                $delay_end_timestamp = 0;
                                                $delay_seconds = 0;
                                                $estimated_delay_minutes = 0;
                                                $is_estimated_delay = false;
                                                
                                                $calculated_delay_minutes = $delay_minutes;
                                                
                                                if ($task_start_dt) {
                                                    if ($calculated_delay_minutes > 0) {
                                                        $delay_seconds = $calculated_delay_minutes * 60;
                                                        $is_estimated_delay = false;
                                                        $delay_minutes = $calculated_delay_minutes;
                                                        
                                                        $delay_start_timestamp = $task_start_dt->getTimestamp();
                                                        $delay_end_timestamp = $task_start_dt->getTimestamp() + $delay_seconds;
                                                    } elseif ($calculated_delay_minutes == 0 && !empty($flight['actual_out_utc']) && $flight['actual_out_utc'] !== null) {
                                                        $is_estimated_delay = true;
                                                        $delay_start_timestamp = $task_start_dt->getTimestamp();
                                                        $delay_end_timestamp = $flight_start_timestamp;
                                                        
                                                        $estimated_delay_seconds = $delay_end_timestamp - $delay_start_timestamp;
                                                        if ($estimated_delay_seconds > 0) {
                                                            $delay_seconds = $estimated_delay_seconds;
                                                            $estimated_delay_minutes = round($estimated_delay_seconds / 60);
                                                            $delay_minutes = $estimated_delay_minutes;
                                                        }
                                                    }
                                                }
                                                
                                                // Calculate positions relative to timelineStart
                                                $flight_start_offset_from_timeline = $flight_start_timestamp - $timeline_start_ts;
                                                $flight_end_offset_from_timeline = $flight_end_timestamp - $timeline_start_ts;
                                                
                                                // Check if flight is within timeline bounds
                                                if ($flight_end_timestamp < $timeline_start_ts || $flight_start_timestamp > $timeline_end_ts) {
                                                    continue;
                                                }
                                                
                                                // Calculate flight bar positions as percentages
                                                $start_position_percent = ($flight_start_offset_from_timeline / $timelineDuration) * 100;
                                                $end_position_percent = ($flight_end_offset_from_timeline / $timelineDuration) * 100;
                                                
                                                // Calculate flight duration
                                                if ($flight_end_offset_from_timeline >= $flight_start_offset_from_timeline) {
                                                    $duration_percent = (($flight_end_offset_from_timeline - $flight_start_offset_from_timeline) / $timelineDuration) * 100;
                                                } else {
                                                    $duration_percent = 0.5;
                                                }
                                                
                                                // Clamp flight bar positions to timeline bounds
                                                $clamped_start = max(0, min(100, $start_position_percent));
                                                $clamped_end = max(0, min(100, $end_position_percent));
                                                
                                                // Adjust duration if clamped
                                                if ($clamped_end > $clamped_start) {
                                                    $duration_percent = $clamped_end - $clamped_start;
                                                } else {
                                                    $duration_percent = max(0.5, $duration_percent);
                                                }
                                                
                                                // Ensure minimum width
                                                if ($duration_percent < 0.5) {
                                                    $duration_percent = 0.5;
                                                }
                                                
                                                // Ensure bar doesn't exceed 100% width
                                                if ($clamped_start + $duration_percent > 100) {
                                                    $duration_percent = max(0.5, 100 - $clamped_start);
                                                }
                                                
                                                $start_position_percent = $clamped_start;
                                                
                                                // Calculate delay bar positions (if delay exists)
                                                $show_delay_bar = false;
                                                
                                                if (($calculated_delay_minutes > 0 || $is_estimated_delay) && $delay_start_timestamp > 0 && $delay_end_timestamp > 0 && $delay_end_timestamp > $delay_start_timestamp) {
                                                    $show_delay_bar = true;
                                                    $delay_start_offset_from_timeline = $delay_start_timestamp - $timeline_start_ts;
                                                    $delay_end_offset_from_timeline = $delay_end_timestamp - $timeline_start_ts;
                                                    
                                                    $delay_start_position_percent = ($delay_start_offset_from_timeline / $timelineDuration) * 100;
                                                    $delay_end_position_percent = ($delay_end_offset_from_timeline / $timelineDuration) * 100;
                                                    
                                                    $delay_duration_percent = (($delay_end_offset_from_timeline - $delay_start_offset_from_timeline) / $timelineDuration) * 100;
                                                    
                                                    $delay_start_position_percent = max(0, min(100, $delay_start_position_percent));
                                                    $delay_end_position_percent = max(0, min(100, $delay_end_position_percent));
                                                    
                                                    if ($delay_end_position_percent > $start_position_percent) {
                                                        $delay_end_position_percent = $start_position_percent;
                                                        $delay_duration_percent = max(0.5, $delay_end_position_percent - $delay_start_position_percent);
                                                    } else if ($delay_end_position_percent < $start_position_percent) {
                                                        $delay_end_position_percent = $start_position_percent;
                                                        $delay_duration_percent = max(0.5, $delay_end_position_percent - $delay_start_position_percent);
                                                    }
                                                    
                                                    if ($delay_duration_percent < 0.5) {
                                                        $delay_duration_percent = 0.5;
                                                        if ($delay_start_position_percent + $delay_duration_percent <= $start_position_percent) {
                                                            $delay_end_position_percent = $delay_start_position_percent + $delay_duration_percent;
                                                        } else {
                                                            $delay_end_position_percent = $start_position_percent;
                                                            $delay_duration_percent = max(0.5, $delay_end_position_percent - $delay_start_position_percent);
                                                        }
                                                    }
                                                    
                                                    if ($delay_start_position_percent + $delay_duration_percent > 100) {
                                                        $delay_duration_percent = max(0.5, 100 - $delay_start_position_percent);
                                                        $delay_end_position_percent = $delay_start_position_percent + $delay_duration_percent;
                                                    }
                                                } else {
                                                    $delay_duration_percent = 0;
                                                    $delay_start_position_percent = 0;
                                                    $delay_end_position_percent = 0;
                                                    $delay_minutes = 0;
                                                }
                                                
                                                $top_position = 6 + ($index * 50);
                                                
                                                // Get status color
                                                $flight_status = $flight['ScheduledTaskStatus'] ?? '';
                                                $status_color_hex = getStatusColor($flight_status);
                                            } else {
                                                $start_position_percent = 0;
                                                $duration_percent = 2;
                                                
                                                $delay_minutes = 0;
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $minutesField = $i === 1 ? 'minutes_1' : "minutes_$i";
                                                    if (!empty($flight[$minutesField])) {
                                                        $minutes_value = intval($flight[$minutesField]);
                                                        if ($minutes_value > 0) {
                                                            $delay_minutes += $minutes_value;
                                                        }
                                                    }
                                                }
                                                
                                                $delay_duration_percent = 0;
                                                $delay_start_position_percent = 0;
                                                $top_position = 4 + ($index * 25);
                                                
                                                $flight_status = $flight['ScheduledTaskStatus'] ?? '';
                                                $status_color_hex = getStatusColor($flight_status);
                                            }
                                            
                                            // Format start and end times
                                            $task_start_time = '';
                                            $task_end_time = '';
                                            
                                            $startTimeField = !empty($flight['actual_out_utc']) && $flight['actual_out_utc'] !== null 
                                                ? $flight['actual_out_utc'] 
                                                : ($flight['TaskStart'] ?? null);
                                            
                                            if (!empty($startTimeField)) {
                                                try {
                                                    $taskStartDate = new DateTime($startTimeField);
                                                    $task_start_time = $taskStartDate->format('H:i');
                                                } catch (Exception $e) {
                                                    $task_start_time = '';
                                                }
                                            }
                                            
                                            $endTimeField = !empty($flight['actual_in_utc']) && $flight['actual_in_utc'] !== null 
                                                ? $flight['actual_in_utc'] 
                                                : ($flight['TaskEnd'] ?? null);
                                            
                                            if (!empty($endTimeField)) {
                                                try {
                                                    $taskEndDate = new DateTime($endTimeField);
                                                    $task_end_time = $taskEndDate->format('H:i');
                                                } catch (Exception $e) {
                                                    $task_end_time = '';
                                                }
                                            }
                                            
                                            // Get TaskName or FlightNo
                                            $flight_display_name = !empty($flight['TaskName']) ? $flight['TaskName'] : ($flight['FlightNo'] ?? '');
                                            $route_display = $flight['Route'] ?? '';
                                            
                                            // Build display text
                                            $display_text = '';
                                            if ($flight_display_name) {
                                                $display_text .= htmlspecialchars($flight_display_name);
                                            }
                                            if ($route_display) {
                                                if ($display_text) $display_text .= ' - ';
                                                $display_text .= htmlspecialchars($route_display);
                                            }
                                            
                                            // Calculate time labels position
                                            $time_labels_top = $top_position + 32 + 2;
                                            ?>
                                            
                                            <?php if ($delay_minutes > 0 && $delay_duration_percent > 0): ?>
                                            <!-- Delay Bar (Red) -->
                                            <div class="flight-bar bg-red-500 text-white text-xs px-2 py-1"
                                                 style="left: <?php echo number_format($delay_start_position_percent, 2); ?>%; width: <?php echo number_format($delay_duration_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 5; border-radius: 8px 0 0 8px; height: 32px; display: flex; align-items: center;"
                                                 title="<?php echo $delay_minutes; ?> min delay">
                                                <div class="truncate text-xs font-medium">
                                                    <?php echo $delay_minutes; ?>m
                                                </div>
                                            </div>
                                            
                                            <!-- Flight Bar -->
                                            <div class="flight-bar text-white text-xs px-2 py-1"
                                                 style="left: <?php echo number_format($start_position_percent, 2); ?>%; width: <?php echo number_format($duration_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 10; border-radius: 0 8px 8px 0; height: 32px; background-color: <?php echo htmlspecialchars($status_color_hex); ?>; display: flex; align-items: center;">
                                                <div class="truncate">
                                                    <?php echo $display_text; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Time Labels Below Flight Bar (with delay) -->
                                            <?php if ($task_start_time || $task_end_time): ?>
                                            <div class="flight-time-labels"
                                                 style="left: <?php echo number_format($start_position_percent, 2); ?>%; width: <?php echo number_format($duration_percent, 2); ?>%; top: <?php echo $time_labels_top; ?>px;">
                                                <?php if ($task_start_time): ?>
                                                    <span class="flight-time-start"><?php echo htmlspecialchars($task_start_time); ?></span>
                                                <?php else: ?>
                                                    <span></span>
                                                <?php endif; ?>
                                                <?php if ($task_end_time): ?>
                                                    <span class="flight-time-end"><?php echo htmlspecialchars($task_end_time); ?></span>
                                                <?php else: ?>
                                                    <span></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php else: ?>
                                            <!-- Flight Bar - No delay -->
                                            <div class="flight-bar text-white text-xs px-2 py-1"
                                                 style="left: <?php echo number_format($start_position_percent, 2); ?>%; width: <?php echo number_format($duration_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 10; border-radius: 8px; height: 32px; background-color: <?php echo htmlspecialchars($status_color_hex); ?>; display: flex; align-items: center;">
                                                <div class="truncate">
                                                    <?php echo $display_text; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Time Labels Below Flight Bar (no delay) -->
                                            <?php if ($task_start_time || $task_end_time): ?>
                                            <div class="flight-time-labels"
                                                 style="left: <?php echo number_format($start_position_percent, 2); ?>%; width: <?php echo number_format($duration_percent, 2); ?>%; top: <?php echo $time_labels_top; ?>px;">
                                                <?php if ($task_start_time): ?>
                                                    <span class="flight-time-start"><?php echo htmlspecialchars($task_start_time); ?></span>
                                                <?php else: ?>
                                                    <span></span>
                                                <?php endif; ?>
                                                <?php if ($task_end_time): ?>
                                                    <span class="flight-time-end"><?php echo htmlspecialchars($task_end_time); ?></span>
                                                <?php else: ?>
                                                    <span></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php endif; ?>
                                            <?php endif; // Close timeline check ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                    <div class="text-center py-8">
                        <i class="fas fa-plane text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No flights found for today</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Flights Data Table -->
                <?php if (!empty($todayFlights)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-table mr-2"></i>Flights Data Table
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            All flights for <?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pilot</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Start</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Delay (min)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Passengers</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($todayFlights as $flight): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($flight['FlightID'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($flight['TaskName'] ?? ($flight['FlightNo'] ?? '-')); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($flight['Route'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($flight['Rego'] ?? '-'); ?>
                                        <?php if (!empty($flight['ACType'])): ?>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">(<?php echo htmlspecialchars($flight['ACType']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php 
                                        $pilotName = '';
                                        if (!empty($flight['FirstName'])) {
                                            $pilotName .= htmlspecialchars($flight['FirstName']);
                                        }
                                        if (!empty($flight['LastName'])) {
                                            if ($pilotName) $pilotName .= ' ';
                                            $pilotName .= htmlspecialchars($flight['LastName']);
                                        }
                                        echo $pilotName ?: '-';
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php 
                                        if (!empty($flight['TaskStart'])) {
                                            try {
                                                $dt = new DateTime($flight['TaskStart']);
                                                echo $dt->format('Y-m-d H:i');
                                            } catch (Exception $e) {
                                                echo htmlspecialchars($flight['TaskStart']);
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php 
                                        if (!empty($flight['TaskEnd'])) {
                                            try {
                                                $dt = new DateTime($flight['TaskEnd']);
                                                echo $dt->format('Y-m-d H:i');
                                            } catch (Exception $e) {
                                                echo htmlspecialchars($flight['TaskEnd']);
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php 
                                        $status = $flight['ScheduledTaskStatus'] ?? '';
                                        $statusColor = getStatusColor($status);
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: <?php echo $statusColor; ?>; color: white;">
                                            <?php echo htmlspecialchars($status ?: 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php 
                                        $delayMinutes = calculateFlightDelay($flight);
                                        if ($delayMinutes > 0) {
                                            echo '<span class="text-red-600 dark:text-red-400 font-medium">' . $delayMinutes . '</span>';
                                        } else {
                                            echo '<span class="text-gray-400">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php 
                                        $totalPax = $flight['total_pax'] ?? 0;
                                        $adult = $flight['adult'] ?? 0;
                                        $child = $flight['child'] ?? 0;
                                        $infant = $flight['infant'] ?? 0;
                                        if ($totalPax > 0 || $adult > 0 || $child > 0 || $infant > 0) {
                                            echo htmlspecialchars($totalPax);
                                            if ($adult > 0 || $child > 0 || $infant > 0) {
                                                echo ' <span class="text-xs text-gray-500">(';
                                                $parts = [];
                                                if ($adult > 0) $parts[] = $adult . 'A';
                                                if ($child > 0) $parts[] = $child . 'C';
                                                if ($infant > 0) $parts[] = $infant . 'I';
                                                echo implode(', ', $parts);
                                                echo ')</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                        <button onclick="showFlightDetails(<?php echo htmlspecialchars(json_encode($flight, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" 
                                                class="px-3 py-1 text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                            <i class="fas fa-eye mr-1"></i>Detail
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Flight Details Modal -->
    <div id="flightDetailsModal" class="flight-details-modal fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center p-4">
        <div class="flight-details-modal-content relative w-full max-w-6xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700">
            <!-- Modal Header -->
            <div class="flight-details-header flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plane text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Flight Details</h3>
                        <p class="text-sm text-white text-opacity-90 mt-0.5" id="flightDetailsSubtitle">Complete flight information</p>
                    </div>
                </div>
                <button onclick="closeFlightDetailsModal()" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white bg-opacity-20 hover:bg-opacity-30 transition-all duration-200 text-white hover:scale-110">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="flight-details-body" id="flightDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Initialize dark mode from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const html = document.documentElement;
            
            if (savedDarkMode === 'true') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            
            // Auto-scroll to current time indicator
            scrollToCurrentTime();
        });
        
        // Function to scroll to current time indicator
        function scrollToCurrentTime() {
            const timelineContainer = document.querySelector('.timeline-container');
            if (!timelineContainer) return;
            
            const timelineWrapper = timelineContainer.querySelector('.timeline-wrapper');
            if (!timelineWrapper) return;
            
            const timelineHeader = timelineWrapper.querySelector('div.flex.mb-4.relative');
            if (!timelineHeader) return;
            
            const hoursContainer = timelineHeader.querySelector('div.flex.relative');
            if (!hoursContainer) return;
            
            const currentTimeIndicator = hoursContainer.querySelector('.current-time-indicator');
            if (!currentTimeIndicator) return;
            
            setTimeout(function() {
                const indicatorLeft = currentTimeIndicator.offsetLeft;
                const indicatorWidth = currentTimeIndicator.offsetWidth || 2;
                
                const scrollLeft = indicatorLeft - (timelineContainer.clientWidth / 2) + (indicatorWidth / 2);
                
                timelineContainer.scrollTo({
                    left: Math.max(0, scrollLeft),
                    behavior: 'smooth'
                });
            }, 200);
        }
        
        // Flight Details Modal Functions
        function showFlightDetails(flight) {
            const modal = document.getElementById('flightDetailsModal');
            const content = document.getElementById('flightDetailsContent');
            
            if (!modal || !content) return;
            
            // Format date/time values
            function formatDateTime(value) {
                if (!value || value === 'null' || value === '') return '-';
                try {
                    const dt = new Date(value);
                    if (isNaN(dt.getTime())) return value;
                    return dt.toLocaleString('en-US', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                } catch (e) {
                    return value;
                }
            }
            
            function formatValue(value) {
                if (value === null || value === undefined || value === '') return '-';
                return value;
            }
            
            // Update subtitle
            const subtitle = document.getElementById('flightDetailsSubtitle');
            if (subtitle) {
                const flightName = escapeHtml(formatValue(flight.TaskName || flight.FlightNo || 'N/A'));
                const route = escapeHtml(formatValue(flight.Route || ''));
                subtitle.textContent = flightName + (route ? ' - ' + route : '');
            }
            
            // Build HTML content with modern design
            let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
            
            // Basic Information Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-info-circle text-blue-500"></i>Basic Information</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-hashtag mr-1 text-xs"></i>Flight ID</span><span class="info-value">' + escapeHtml(formatValue(flight.FlightID)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-tag mr-1 text-xs"></i>Task Name</span><span class="info-value">' + escapeHtml(formatValue(flight.TaskName)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-plane mr-1 text-xs"></i>Flight No</span><span class="info-value">' + escapeHtml(formatValue(flight.FlightNo)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-route mr-1 text-xs"></i>Route</span><span class="info-value">' + escapeHtml(formatValue(flight.Route)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-map-marked-alt mr-1 text-xs"></i>Scheduled Route</span><span class="info-value">' + escapeHtml(formatValue(flight.ScheduledRoute)) + '</span></div>';
            const statusColor = getStatusColor(flight.ScheduledTaskStatus || '');
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-flag mr-1 text-xs"></i>Status</span><span class="status-badge" style="background-color: ' + statusColor + '; color: white;">' + escapeHtml(formatValue(flight.ScheduledTaskStatus)) + '</span></div>';
            html += '</div></div>';
            
            // Aircraft Information Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-plane text-indigo-500"></i>Aircraft Information</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-id-card mr-1 text-xs"></i>Registration</span><span class="info-value">' + escapeHtml(formatValue(flight.Rego)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-cog mr-1 text-xs"></i>Aircraft Type</span><span class="info-value">' + escapeHtml(formatValue(flight.ACType)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-fingerprint mr-1 text-xs"></i>Aircraft ID</span><span class="info-value">' + escapeHtml(formatValue(flight.AircraftID)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-home mr-1 text-xs"></i>Home Base</span><span class="info-value">' + escapeHtml(formatValue(flight.HomeBases)) + '</span></div>';
            html += '</div></div>';
            
            // Pilot Information Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-user-tie text-purple-500"></i>Pilot Information</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-user mr-1 text-xs"></i>Pilot Name</span><span class="info-value">' + escapeHtml(formatValue((flight.FirstName || '') + ' ' + (flight.LastName || ''))) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-id-badge mr-1 text-xs"></i>Pilot ID</span><span class="info-value">' + escapeHtml(formatValue(flight.CmdPilotID)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-clock mr-1 text-xs"></i>Flight Hours</span><span class="info-value">' + escapeHtml(formatValue(flight.FlightHours)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-star mr-1 text-xs"></i>Command Hours</span><span class="info-value">' + escapeHtml(formatValue(flight.CommandHours)) + '</span></div>';
            html += '</div></div>';
            
            // Schedule Information Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-calendar-alt text-green-500"></i>Schedule Information</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-calendar mr-1 text-xs"></i>Flight Date</span><span class="info-value">' + formatDateTime(flight.FltDate) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-play-circle mr-1 text-xs"></i>Task Start</span><span class="info-value">' + formatDateTime(flight.TaskStart) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-stop-circle mr-1 text-xs"></i>Task End</span><span class="info-value">' + formatDateTime(flight.TaskEnd) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-list-ol mr-1 text-xs"></i>Scheduled Task ID</span><span class="info-value">' + escapeHtml(formatValue(flight.ScheduledTaskID)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-tasks mr-1 text-xs"></i>Task Type</span><span class="info-value">' + escapeHtml(formatValue(flight.ScheduledTaskType)) + '</span></div>';
            html += '</div></div>';
            
            // Actual Times Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-clock text-orange-500"></i>Actual Times (UTC)</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-door-open mr-1 text-xs"></i>Actual Out</span><span class="info-value">' + formatDateTime(flight.actual_out_utc) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-plane-departure mr-1 text-xs"></i>Actual Off</span><span class="info-value">' + formatDateTime(flight.actual_off_utc) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-plane-arrival mr-1 text-xs"></i>Actual On</span><span class="info-value">' + formatDateTime(flight.actual_on_utc) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-door-closed mr-1 text-xs"></i>Actual In</span><span class="info-value">' + formatDateTime(flight.actual_in_utc) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-hourglass-half mr-1 text-xs"></i>Block Time</span><span class="info-value">' + escapeHtml(formatValue(flight.block_time_min)) + ' min</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-wind mr-1 text-xs"></i>Air Time</span><span class="info-value">' + escapeHtml(formatValue(flight.air_time_min)) + ' min</span></div>';
            html += '</div></div>';
            
            // Flight Times (HHMM) Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-stopwatch text-teal-500"></i>Flight Times (HHMM)</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-walking mr-1 text-xs"></i>Boarding</span><span class="info-value">' + escapeHtml(formatValue(flight.boarding)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-lock mr-1 text-xs"></i>Gate Closed</span><span class="info-value">' + escapeHtml(formatValue(flight.gate_closed)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-check-circle mr-1 text-xs"></i>Ready</span><span class="info-value">' + escapeHtml(formatValue(flight.ready)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-play mr-1 text-xs"></i>Start</span><span class="info-value">' + escapeHtml(formatValue(flight.start)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-arrow-right mr-1 text-xs"></i>Off Block</span><span class="info-value">' + escapeHtml(formatValue(flight.off_block)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-road mr-1 text-xs"></i>Taxi</span><span class="info-value">' + escapeHtml(formatValue(flight.taxi)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-rocket mr-1 text-xs"></i>Takeoff</span><span class="info-value">' + escapeHtml(formatValue(flight.takeoff)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-parachute-box mr-1 text-xs"></i>Landed</span><span class="info-value">' + escapeHtml(formatValue(flight.landed)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-arrow-left mr-1 text-xs"></i>On Block</span><span class="info-value">' + escapeHtml(formatValue(flight.on_block)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-undo mr-1 text-xs"></i>Return to Ramp</span><span class="info-value">' + escapeHtml(formatValue(flight.return_to_ramp)) + '</span></div>';
            html += '</div></div>';
            
            // Passenger & Weight Card
            html += '<div class="info-card">';
            html += '<div class="info-card-title"><i class="fas fa-users text-pink-500"></i>Passenger & Weight</div>';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-user mr-1 text-xs"></i>Adults</span><span class="info-value">' + escapeHtml(formatValue(flight.adult)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-child mr-1 text-xs"></i>Children</span><span class="info-value">' + escapeHtml(formatValue(flight.child)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-baby mr-1 text-xs"></i>Infants</span><span class="info-value">' + escapeHtml(formatValue(flight.infant)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-users-cog mr-1 text-xs"></i>Total Passengers</span><span class="info-value font-bold text-blue-600 dark:text-blue-400">' + escapeHtml(formatValue(flight.total_pax)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-box mr-1 text-xs"></i>PCS</span><span class="info-value">' + escapeHtml(formatValue(flight.pcs)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-weight mr-1 text-xs"></i>Weight (kg)</span><span class="info-value">' + escapeHtml(formatValue(flight.weight)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-gas-pump mr-1 text-xs"></i>Uplift Fuel (L)</span><span class="info-value">' + escapeHtml(formatValue(flight.uplift_fuel)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-weight-hanging mr-1 text-xs"></i>Uplift (lbs)</span><span class="info-value">' + escapeHtml(formatValue(flight.uplft_lbs)) + '</span></div>';
            html += '</div></div>';
            
            // Delay/Diversion Codes
            let hasDelays = false;
            for (let i = 1; i <= 5; i++) {
                const codeField = i === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${i}`;
                const subCodeField = `delay_diversion_sub_codes_${i}`;
                const minutesField = `minutes_${i}`;
                const dv93Field = `dv93_${i}`;
                const remarkField = `remark_${i}`;
                
                if (flight[codeField] || flight[minutesField] || flight[dv93Field] || flight[remarkField]) {
                    hasDelays = true;
                    break;
                }
            }
            
            // Delay/Diversion Codes Card
            if (hasDelays) {
                html += '<div class="info-card md:col-span-2 lg:col-span-3">';
                html += '<div class="info-card-title"><i class="fas fa-exclamation-triangle text-red-500"></i>Delay/Diversion Codes</div>';
                html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
                
                for (let i = 1; i <= 5; i++) {
                    const codeField = i === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${i}`;
                    const subCodeField = `delay_diversion_sub_codes_${i}`;
                    const minutesField = `minutes_${i}`;
                    const dv93Field = `dv93_${i}`;
                    const remarkField = `remark_${i}`;
                    
                    const code = flight[codeField] || '';
                    const subCode = flight[subCodeField] || '';
                    const minutes = flight[minutesField] || '';
                    const dv93 = flight[dv93Field] || '';
                    const remark = flight[remarkField] || '';
                    
                    if (code || minutes || dv93 || remark) {
                        html += '<div class="delay-card">';
                        html += '<div class="flex items-center justify-between mb-2">';
                        html += '<span class="text-sm font-bold text-red-800 dark:text-red-200"><i class="fas fa-clock mr-1"></i>Delay Code ' + i + '</span>';
                        if (minutes) {
                            html += '<span class="text-sm font-bold text-red-900 dark:text-red-100 bg-white bg-opacity-30 px-2 py-1 rounded">' + escapeHtml(minutes) + ' min</span>';
                        }
                        html += '</div>';
                        html += '<div class="space-y-2 text-sm">';
                        
                        if (code) {
                            html += '<div class="flex items-center gap-2"><span class="text-red-700 dark:text-red-300 font-medium">Code:</span><span class="text-red-900 dark:text-red-100 font-bold">' + escapeHtml(code) + '</span></div>';
                        }
                        if (subCode) {
                            html += '<div class="flex items-center gap-2"><span class="text-red-700 dark:text-red-300 font-medium">Sub Code:</span><span class="text-red-900 dark:text-red-100">' + escapeHtml(subCode) + '</span></div>';
                        }
                        if (dv93) {
                            html += '<div class="mt-2 pt-2 border-t border-red-300 dark:border-red-700"><span class="text-red-700 dark:text-red-300 font-medium block mb-1">DV93 Description:</span><span class="text-red-900 dark:text-red-100">' + escapeHtml(dv93) + '</span></div>';
                        }
                        if (remark) {
                            html += '<div class="mt-2 pt-2 border-t border-red-300 dark:border-red-700"><span class="text-red-700 dark:text-red-300 font-medium block mb-1">Remark:</span><span class="text-red-900 dark:text-red-100">' + escapeHtml(remark) + '</span></div>';
                        }
                        
                        html += '</div></div>';
                    }
                }
                
                html += '</div></div>';
            }
            
            // Additional Information Card
            html += '<div class="info-card md:col-span-2 lg:col-span-3">';
            html += '<div class="info-card-title"><i class="fas fa-info-circle text-gray-500"></i>Additional Information</div>';
            html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
            html += '<div class="space-y-2">';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-clock mr-1 text-xs"></i>Flight Hours</span><span class="info-value">' + escapeHtml(formatValue(flight.FltHours)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-map-marker-alt mr-1 text-xs"></i>Divert Station</span><span class="info-value">' + escapeHtml(formatValue(flight.divert_station)) + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-lock mr-1 text-xs"></i>Flight Locked</span><span class="info-value">' + (flight.FlightLocked == 1 ? '<span class="text-red-600 dark:text-red-400">Yes</span>' : '<span class="text-green-600 dark:text-green-400">No</span>') + '</span></div>';
            html += '<div class="info-row"><span class="info-label"><i class="fas fa-sync-alt mr-1 text-xs"></i>Last Updated</span><span class="info-value text-xs">' + formatDateTime(flight.LastUpdated) + '</span></div>';
            html += '</div>';
            if (flight.OtherCrew) {
                html += '<div class="md:col-span-1 lg:col-span-2"><div class="info-row"><span class="info-label block mb-1"><i class="fas fa-users mr-1 text-xs"></i>Other Crew</span><span class="info-value block text-left">' + escapeHtml(flight.OtherCrew) + '</span></div></div>';
            }
            if (flight.AllCrew) {
                html += '<div class="md:col-span-1 lg:col-span-2"><div class="info-row"><span class="info-label block mb-1"><i class="fas fa-user-friends mr-1 text-xs"></i>All Crew</span><span class="info-value block text-left">' + escapeHtml(flight.AllCrew) + '</span></div></div>';
            }
            if (flight.TaskDescriptionHTML) {
                html += '<div class="md:col-span-2 lg:col-span-3"><div class="info-row"><span class="info-label block mb-1"><i class="fas fa-file-alt mr-1 text-xs"></i>Task Description</span><div class="info-value block text-left mt-1 p-2 bg-gray-50 dark:bg-gray-700 rounded">' + flight.TaskDescriptionHTML + '</div></div></div>';
            }
            html += '</div></div>';
            
            html += '</div>';
            
            content.innerHTML = html;
            modal.classList.remove('hidden');
        }
        
        function closeFlightDetailsModal() {
            const modal = document.getElementById('flightDetailsModal');
            if (modal) {
                modal.style.animation = 'fadeOut 0.2s ease-out';
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.style.animation = '';
                }, 200);
            }
        }
        
        // Helper function to get status color (matching PHP function)
        function getStatusColor(status) {
            const statusColors = {
                'Boarding': '#ADD8E6',
                'Cancelled': '#FF0000',
                'Complete': '#32CD32',
                'Confirmed': '#242526',
                'Delayed': '#FFA500',
                'Diverted': '#8B0000',
                'Gate Closed': '#FFD700',
                'Landed': '#1E90FF',
                'Off Block': '#A0522D',
                'On Block': '#9370DB',
                'Pending': '#808080',
                'Ready': '#00FF00',
                'Return to Ramp': '#FF8C00',
                'Start': '#4682B4',
                'Takeoff': '#228B22',
                'Taxi': '#2F4F4F'
            };
            return statusColors[status] || '#3b82f6';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('flightDetailsModal');
            if (modal && e.target === modal) {
                closeFlightDetailsModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('flightDetailsModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeFlightDetailsModal();
                }
            }
        });
    </script>
</body>
</html>

