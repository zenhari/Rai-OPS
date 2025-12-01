<?php
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

$user = getCurrentUser();

// Get aircraft data for dashboard
$aircraft = getAllAircraft();
$aircraftCount = count($aircraft);
$activeAircraft = array_filter($aircraft, function($a) { return $a['status'] === 'active'; });
$activeAircraftCount = count($activeAircraft);
$maintenanceAircraft = array_filter($aircraft, function($a) { return $a['status'] === 'maintenance'; });
$maintenanceCount = count($maintenanceAircraft);

// Get recent aircraft (last 3)
$recentAircraft = array_slice($aircraft, 0, 3);

// Get unread ODB count for current user
$unreadOdbCount = getUnreadODBCount();

// Get flights for 3 days (yesterday, today, tomorrow) for timeline
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Get flights for all 3 days
$yesterdayFlights = getFlightsForMonitoring($yesterday);
$todayFlights = getFlightsForMonitoring($today);
$tomorrowFlights = getFlightsForMonitoring($tomorrow);

// Combine all flights
$allFlights = array_merge($yesterdayFlights, $todayFlights, $tomorrowFlights);

// Group flights by date and aircraft
$flightsByDateAndAircraft = [];
$flightsByAircraft = [];

// Find earliest start and latest end times (considering delays)
$earliestStart = null;
$latestEnd = null;

foreach ($allFlights as $flight) {
    $aircraft_rego = $flight['aircraft_rego'] ?? 'Unknown';
    
    // Determine which date this flight belongs to
    $flightDate = null;
    if (!empty($flight['TaskStart'])) {
        try {
            $taskStartDate = new DateTime($flight['TaskStart']);
            $flightDate = $taskStartDate->format('Y-m-d');
        } catch (Exception $e) {
            // Skip if invalid date
        }
    }
    if (!$flightDate && !empty($flight['FltDate'])) {
        try {
            $fltDate = new DateTime($flight['FltDate']);
            $flightDate = $fltDate->format('Y-m-d');
        } catch (Exception $e) {
            // Skip if invalid date
        }
    }
    
    // Default to today if date cannot be determined
    if (!$flightDate) {
        $flightDate = $today;
    }
    
    // Group by date and aircraft
    if (!isset($flightsByDateAndAircraft[$flightDate])) {
        $flightsByDateAndAircraft[$flightDate] = [];
    }
    if (!isset($flightsByDateAndAircraft[$flightDate][$aircraft_rego])) {
        $flightsByDateAndAircraft[$flightDate][$aircraft_rego] = [];
    }
    $flightsByDateAndAircraft[$flightDate][$aircraft_rego][] = $flight;
    
    // Also group by aircraft for backward compatibility
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

// Also sort flightsByDateAndAircraft by aircraft_rego for each date
foreach ($flightsByDateAndAircraft as $date => &$aircraftFlights) {
    ksort($aircraftFlights, SORT_STRING | SORT_FLAG_CASE);
}
unset($aircraftFlights); // Unset reference

// Calculate maximum flights per day for grid lines
// We need to find the maximum number of flights in a single day across all aircraft
$maxFlightsPerDay = 0;
$flightsCountByDate = [];

// Count flights per date
foreach ($flightsByDateAndAircraft as $date => $aircraftFlights) {
    $totalFlightsForDate = 0;
    foreach ($aircraftFlights as $aircraft_rego => $flights) {
        $totalFlightsForDate += count($flights);
    }
    $flightsCountByDate[$date] = $totalFlightsForDate;
    if ($totalFlightsForDate > $maxFlightsPerDay) {
        $maxFlightsPerDay = $totalFlightsForDate;
    }
}

// Ensure minimum of 1 line even if no flights
if ($maxFlightsPerDay < 1) {
    $maxFlightsPerDay = 1;
}

// Calculate timeline for 3 days (yesterday, today, tomorrow)
// Each day will have 24 hours (00:00 to 23:00)
$timelineStart = new DateTime($yesterday . ' 00:00:00');
$timelineEnd = new DateTime($tomorrow . ' 23:59:59');

$timelineHours = [];
// Generate hours for all 3 days (72 hours total)
$current = clone $timelineStart;
$currentDay = $yesterday;

while ($current <= $timelineEnd) {
    $currentDayFormatted = $current->format('Y-m-d');
    
    // Check if we've moved to a new day
    if ($currentDayFormatted !== $currentDay) {
        $currentDay = $currentDayFormatted;
    }
    
    $hour = $current->format('H:i');
    $dateLabel = '';
    
    // Add date label for first hour of each day
    if ($current->format('H') == '00') {
        if ($currentDayFormatted === $today) {
            $dateLabel = 'Today';
        } elseif ($currentDayFormatted === $yesterday) {
            $dateLabel = 'Yesterday';
        } elseif ($currentDayFormatted === $tomorrow) {
            $dateLabel = 'Tomorrow';
        } else {
            $dateLabel = date('M j', strtotime($currentDayFormatted));
        }
    }
    
    $timelineHours[] = [
        'hour' => $hour,
        'date' => $currentDayFormatted,
        'dateLabel' => $dateLabel,
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
    
    // Check if current date is within the 3-day range (yesterday, today, tomorrow)
    $currentDate = $currentTime->format('Y-m-d');
    if (($currentDate === $yesterday || $currentDate === $today || $currentDate === $tomorrow) && $timelineStart && $timelineEnd) {
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
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../assets/js/tailwind.js"></script>
    
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
            min-width: 120px; /* 2x of 60px for better data display */
            width: 120px; /* 2x of 60px for better data display */
            flex-shrink: 0;
            position: relative;
        }
        
        /* Vertical grid line at day boundaries (00:00) */
        .timeline-hour.day-boundary::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 2px;
            background-color: #3b82f6; /* blue-500 */
            opacity: 0.4;
            z-index: 15;
        }
        
        .dark .timeline-hour.day-boundary::after {
            background-color: #60a5fa; /* blue-400 */
            opacity: 0.5;
        }
        
        .timeline-row {
            overflow: hidden;
            position: relative;
        }
        
        /* Horizontal grid lines - dynamically calculated based on max flights per day */
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
            cursor: pointer;
            transition: all 0.2s ease;
            max-width: 100%;
            box-sizing: border-box;
        }
        .flight-bar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 10;
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
            color: #111827; /* Black for light mode */
            font-size: 11px;
        }
        
        .flight-time-end {
            font-weight: 600;
            color: #111827; /* Black for light mode */
            font-size: 11px;
        }
        
        .dark .flight-time-start {
            color: #ffffff; /* White for dark mode */
        }
        
        .dark .flight-time-end {
            color: #ffffff; /* White for dark mode */
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-time-start {
                color: #ffffff; /* White for dark mode */
            }
            
            .flight-time-end {
                color: #ffffff; /* White for dark mode */
            }
        }
        
        /* Day Boundary Line (Vertical) */
        .day-boundary-line {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            z-index: 15;
            pointer-events: none;
        }
        
        .day-boundary-line-inner {
            width: 100%;
            height: 100%;
            background-color: #3b82f6; /* blue-500 */
            opacity: 0.4;
            box-shadow: 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        .dark .day-boundary-line-inner {
            background-color: #60a5fa; /* blue-400 */
            opacity: 0.5;
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
            background-color: #fbbf24; /* yellow-400 */
            box-shadow: 0 0 4px rgba(251, 191, 36, 0.6);
        }
        
        .current-time-label {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #fbbf24; /* yellow-400 */
            color: #78350f; /* yellow-900 */
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
        
        /* Dark mode support for current time indicator */
        .dark .current-time-label {
            background-color: #fbbf24;
            color: #78350f;
        }
        
        /* Flight Tooltip Styles */
        .flight-tooltip {
            position: fixed;
            z-index: 9999;
            max-width: 450px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            display: none;
            pointer-events: none;
            overflow: hidden;
            max-height: 90vh;
        }
        
        .dark .flight-tooltip {
            background: #1f2937;
            color: #f9fafb;
        }
        
        .flight-tooltip.show {
            display: block;
        }
        
        .flight-tooltip-content {
            padding: 12px;
            overflow: hidden;
        }
        
        .flight-tooltip-header {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .dark .flight-tooltip-header {
            border-bottom-color: #4b5563;
        }
        
        .flight-tooltip-section {
            margin-bottom: 10px;
        }
        
        .flight-tooltip-section-title {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #4b5563;
        }
        
        .dark .flight-tooltip-section-title {
            color: #9ca3af;
        }
        
        .flight-tooltip-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 11px;
            line-height: 1.4;
        }
        
        .flight-tooltip-label {
            color: #6b7280;
            font-weight: 500;
            font-size: 10px;
        }
        
        .dark .flight-tooltip-label {
            color: #9ca3af;
        }
        
        .flight-tooltip-value {
            color: #111827;
            font-weight: 400;
            text-align: right;
            font-size: 10px;
        }
        
        .dark .flight-tooltip-value {
            color: #f9fafb;
        }
        
        /* Status value styling - Bold, larger, red */
        .flight-tooltip-status-value {
            color: #dc2626 !important; /* red-600 */
            font-weight: 700 !important; /* Bold */
            font-size: 12px !important; /* Larger than default 10px */
        }
        
        .dark .flight-tooltip-status-value {
            color: #f87171 !important; /* red-400 for dark mode */
        }
        
        .flight-tooltip-actions {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 6px;
        }
        
        .dark .flight-tooltip-actions {
            border-top-color: #4b5563;
        }
        
        .flight-tooltip-btn {
            flex: 1;
            padding: 6px 10px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .flight-tooltip-btn:hover {
            background: #2563eb;
        }
        
        .flight-tooltip-delays {
            margin-top: 6px;
        }
        
        .flight-tooltip-delay-item {
            padding: 4px;
            margin-bottom: 3px;
            background: #fef2f2;
            border-left: 3px solid #dc2626;
            border-radius: 3px;
            font-size: 10px;
            line-height: 1.3;
        }
        
        .dark .flight-tooltip-delay-item {
            background: #7f1d1d;
            border-left-color: #ef4444;
        }
        
        /* Flight Edit Modal Styles - Minimal Design */
        .flight-edit-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 10000;
            overflow-y: auto;
            padding: 20px;
            backdrop-filter: blur(4px);
        }
        
        .flight-edit-modal.show {
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }
        
        .flight-edit-modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 1400px;
            margin: 20px auto;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .dark .flight-edit-modal-content {
            background: #1e293b;
            color: #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-modal-content {
                background: #1e293b;
                color: #f1f5f9;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
            }
        }
        
        .flight-edit-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dark .flight-edit-modal-header {
            border-bottom-color: #334155;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-modal-header {
                border-bottom-color: #334155;
            }
        }
        
        .flight-edit-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.01em;
        }
        
        .dark .flight-edit-modal-title {
            color: #f1f5f9;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-modal-title {
                color: #f1f5f9;
            }
        }
        
        .flight-edit-modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #64748b;
            padding: 0;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.15s ease;
        }
        
        .flight-edit-modal-close:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }
        
        .dark .flight-edit-modal-close {
            color: #94a3b8;
        }
        
        .dark .flight-edit-modal-close:hover {
            background-color: #334155;
            color: #f1f5f9;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-modal-close {
                color: #94a3b8;
            }
            
            .flight-edit-modal-close:hover {
                background-color: #334155;
                color: #f1f5f9;
            }
        }
        
         .flight-edit-modal-body {
             padding: 20px 24px;
         }
         
         @media (min-width: 768px) {
             .flight-edit-modal-body {
                 padding: 24px;
             }
         }
        
         .flight-edit-form-section {
             margin-bottom: 24px;
             padding: 0;
             background: transparent;
             border-radius: 0;
             border: none;
         }
         
         .dark .flight-edit-form-section {
             background: transparent;
             border: none;
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-form-section {
                 background: transparent;
                 border: none;
             }
         }
         
         .flight-edit-form-section-title {
             font-size: 13px;
             font-weight: 600;
             color: #475569;
             margin-bottom: 16px;
             padding-bottom: 8px;
             border-bottom: 1px solid #e2e8f0;
             display: flex;
             align-items: center;
             gap: 8px;
             text-transform: uppercase;
             letter-spacing: 0.05em;
         }
         
         .dark .flight-edit-form-section-title {
             color: #94a3b8;
             border-bottom-color: #334155;
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-form-section-title {
                 color: #94a3b8;
                 border-bottom-color: #334155;
             }
         }
         
         .flight-edit-form-section-title::before {
             content: '';
             width: 3px;
             height: 14px;
             background: #3b82f6;
             border-radius: 2px;
         }
         
         .dark .flight-edit-form-section-title::before {
             background: #60a5fa;
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-form-section-title::before {
                 background: #60a5fa;
             }
         }
         
         .flight-edit-form-grid {
             display: grid;
             grid-template-columns: repeat(1, 1fr);
             gap: 12px;
         }
         
         @media (min-width: 640px) {
             .flight-edit-form-grid {
                 grid-template-columns: repeat(2, 1fr);
             }
         }
         
         @media (min-width: 1024px) {
             .flight-edit-form-grid.grid-3 {
                 grid-template-columns: repeat(3, 1fr);
             }
             
             .flight-edit-form-grid.grid-4 {
                 grid-template-columns: repeat(4, 1fr);
             }
         }
        
        .flight-edit-form-group {
            display: flex;
            flex-direction: column;
        }
        
         .flight-edit-form-label {
             font-size: 12px;
             font-weight: 500;
             color: #64748b;
             margin-bottom: 6px;
         }
         
         .dark .flight-edit-form-label {
             color: #94a3b8;
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-form-label {
                 color: #94a3b8;
             }
         }
        
        .flight-edit-form-input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            color: #0f172a;
            transition: all 0.15s ease;
        }
        
        .flight-edit-form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .dark .flight-edit-form-input {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-form-input {
                background: #0f172a;
                border-color: #334155;
                color: #f1f5f9;
            }
        }
        
        .dark .flight-edit-form-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-form-input:focus {
                border-color: #60a5fa;
                box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
            }
        }
        
        .flight-edit-form-select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            color: #0f172a;
            transition: all 0.15s ease;
        }
        
        .flight-edit-form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .dark .flight-edit-form-select {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-form-select {
                background: #0f172a;
                border-color: #334155;
                color: #f1f5f9;
            }
        }
        
        .dark .flight-edit-form-select:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-form-select:focus {
                border-color: #60a5fa;
                box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
            }
        }
        
        /* Calculated fields (readonly fields with calculated values) */
        .calculated-field {
            background: #d1fae5 !important;
            color: #000000 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            .calculated-field {
                background: #065f46 !important;
                color: #d1fae5 !important;
            }
        }
        
        .flight-edit-modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 8px;
        }
        
        .dark .flight-edit-modal-footer {
            border-top-color: #334155;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-modal-footer {
                border-top-color: #334155;
            }
        }
        
        .flight-edit-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
        }
        
        .flight-edit-btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }
        
        .flight-edit-btn-cancel:hover {
            background: #e2e8f0;
        }
        
        .dark .flight-edit-btn-cancel {
            background: #1e293b;
            color: #94a3b8;
            border: 1px solid #334155;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-btn-cancel {
                background: #1e293b;
                color: #94a3b8;
                border: 1px solid #334155;
            }
            
            .flight-edit-btn-cancel:hover {
                background: #334155;
                border-color: #475569;
            }
        }
        
        .dark .flight-edit-btn-cancel:hover {
            background: #334155;
            border-color: #475569;
        }
        
        .flight-edit-btn-save {
            background: #3b82f6;
            color: white;
        }
        
        .flight-edit-btn-save:hover {
            background: #2563eb;
        }
        
        .dark .flight-edit-btn-save {
            background: #3b82f6;
        }
        
        .dark .flight-edit-btn-save:hover {
            background: #2563eb;
        }
        
        .flight-edit-btn-save:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }
        
        .dark .flight-edit-btn-save:disabled {
            background: #475569;
        }
        
        .flight-edit-loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        
        .dark .flight-edit-loading {
            color: #9ca3af;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-loading {
                color: #9ca3af;
            }
        }
        
        .flight-edit-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .dark .flight-edit-error {
            background: #7f1d1d;
            border-color: #991b1b;
            color: #fca5a5;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-error {
                background: #7f1d1d;
                border-color: #991b1b;
                color: #fca5a5;
            }
        }
        
        .flight-edit-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .dark .flight-edit-success {
            background: #14532d;
            border-color: #166534;
            color: #86efac;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-success {
                background: #14532d;
                border-color: #166534;
                color: #86efac;
            }
        }
        
         .flight-edit-delay-row {
             margin-bottom: 12px;
             padding: 14px;
             background: white;
             border-radius: 8px;
             border: 1px solid #e5e7eb;
             transition: all 0.2s;
         }
         
         .dark .flight-edit-delay-row {
             background: #1f2937;
             border-color: #374151;
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-delay-row {
                 background: #1f2937;
                 border-color: #374151;
             }
         }
         
         .flight-edit-delay-row.delay-row-enabled {
             border-left: 3px solid #3b82f6;
         }
         
         .dark .flight-edit-delay-row.delay-row-enabled {
             border-left-color: #60a5fa;
         }
         
         /* Delay Code Description Display */
         .delay-code-description {
             margin-top: 6px;
             font-size: 12px;
             color: #64748b;
             min-height: 18px;
             line-height: 1.5;
             padding: 8px 12px;
             background: #f8fafc;
             border-radius: 4px;
             border-left: 2px solid #3b82f6;
         }
         
         .dark .delay-code-description {
             color: #94a3b8;
             background: #0f172a;
             border-left-color: #60a5fa;
         }
         
         @media (prefers-color-scheme: dark) {
             .delay-code-description {
                 color: #94a3b8;
                 background: #0f172a;
                 border-left-color: #60a5fa;
             }
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-delay-row.delay-row-enabled {
                 border-left-color: #60a5fa;
             }
         }
         
         .flight-edit-delay-row.delay-row-disabled {
             opacity: 0.5;
             background: #f9fafb;
             border-left: 3px solid #d1d5db;
         }
         
         .dark .flight-edit-delay-row.delay-row-disabled {
             background: #111827;
             border-left-color: #4b5563;
         }
         
         @media (prefers-color-scheme: dark) {
             .flight-edit-delay-row.delay-row-disabled {
                 background: #111827;
                 border-left-color: #4b5563;
             }
         }
         
         .flight-edit-delay-row.delay-row-disabled .flight-edit-form-input,
         .flight-edit-delay-row.delay-row-disabled .flight-edit-form-select {
             cursor: not-allowed;
         }
         
         .flight-edit-delay-row:not(.delay-row-disabled):hover {
             box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
         }
         
         .dark .flight-edit-delay-row:not(.delay-row-disabled):hover {
             box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
         }
         
         .delay-row-header {
             display: flex;
             align-items: center;
             gap: 8px;
             margin-bottom: 10px;
             padding-bottom: 8px;
             border-bottom: 1px solid #e5e7eb;
         }
         
         .dark .delay-row-header {
             border-bottom-color: #374151;
         }
         
         .delay-row-number {
             display: flex;
             align-items: center;
             justify-content: center;
             width: 24px;
             height: 24px;
             border-radius: 50%;
             background: #3b82f6;
             color: white;
             font-size: 12px;
             font-weight: 600;
         }
         
         .dark .delay-row-number {
             background: #60a5fa;
         }
         
         .flight-edit-delay-row.delay-row-disabled .delay-row-number {
             background: #9ca3af;
         }
         
         .delay-row-title {
             font-size: 13px;
             font-weight: 600;
             color: #374151;
         }
         
         .dark .delay-row-title {
             color: #d1d5db;
         }
         
         .dark .flight-edit-delay-row input[readonly],
         .dark .flight-edit-delay-row textarea[readonly] {
             background: #374151 !important;
             color: #9ca3af !important;
         }
         
         @media (max-width: 768px) {
             .flight-edit-delay-row {
                 padding: 12px;
             }
             
             .delay-row-header {
                 margin-bottom: 8px;
                 padding-bottom: 6px;
             }
             
             .delay-row-number {
                 width: 20px;
                 height: 20px;
                 font-size: 11px;
             }
             
             .delay-row-title {
                 font-size: 12px;
             }
         }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Flight Tooltip Container -->
    <div id="flightTooltip" class="flight-tooltip">
        <div class="flight-tooltip-content" id="flightTooltipContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
    
    <!-- Flight Edit Modal -->
    <div id="flightEditModal" class="flight-edit-modal">
        <div class="flight-edit-modal-content">
            <div class="flight-edit-modal-header">
                <div class="flex items-center justify-between w-full">
                    <h2 class="flight-edit-modal-title">Edit Flight</h2>
                    <div class="flex items-center space-x-2">
                        <button type="button" id="convertToUTCBtn" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200" onclick="toggleTimeZone()" title="Toggle between Tehran/IRAN and UTC timezone">
                            <i class="fas fa-clock mr-1"></i>
                            <span id="convertToUTCBtnText">Convert To UTC</span>
                        </button>
                        <button type="button" class="flight-edit-modal-close" onclick="closeFlightEditModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="flight-edit-modal-body" id="flightEditModalBody">
                <div class="flight-edit-loading">Loading...</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                Dashboard
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Notifications -->
                            <a href="/admin/odb/list.php" class="relative p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200" title="ODB Notifications">
                                <i class="fas fa-bell"></i>
                                <?php if ($unreadOdbCount > 0): ?>
                                    <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-medium">
                                        <?php echo $unreadOdbCount > 99 ? '99+' : $unreadOdbCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../includes/permission_banner.php'; ?>
                
                <!-- Dashboard Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Aircraft -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                                <i class="fas fa-plane text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Aircraft</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $aircraftCount; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Active Aircraft -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                                <i class="fas fa-plane-departure text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Aircraft</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $activeAircraftCount; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Personnel -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                                <i class="fas fa-users text-purple-600 dark:text-purple-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Personnel</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo getUsersCount(); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Due -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                                <i class="fas fa-tools text-orange-600 dark:text-orange-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">In Maintenance</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $maintenanceCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Safety Report Alert -->
                <div class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 border border-orange-200 dark:border-orange-700 rounded-lg p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-orange-600 dark:text-orange-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-semibold text-orange-800 dark:text-orange-200 mb-2">
                                Safety Report Required
                            </h3>
                            <p class="text-orange-700 dark:text-orange-300 mb-4">
                                Do you have a safety report to submit? Please ensure all safety incidents and observations are properly documented.
                            </p>
                            <div class="flex space-x-3">
                                <a href="<?php echo getAbsolutePath('admin/settings/safety_reports/add.php'); ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                                    <i class="fas fa-plus mr-2"></i>
                                    Submit Safety Report
                                </a>
                                <button onclick="dismissAlert()" 
                                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-orange-300 dark:border-orange-600 text-orange-700 dark:text-orange-300 text-sm font-medium rounded-md hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                                    <i class="fas fa-times mr-2"></i>
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Flight Timeline -->
                <?php if (!empty($flightsByAircraft) && checkPageAccessEnhanced('dashboard/flight_monitoring.php')): ?>
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
                                        $lastDate = '';
                                        foreach ($timelineHours as $hourData): 
                                            $hour = is_array($hourData) ? $hourData['hour'] : $hourData;
                                            $dateLabel = is_array($hourData) ? $hourData['dateLabel'] : '';
                                            $currentDate = is_array($hourData) ? $hourData['date'] : '';
                                            
                                            // Show date label if it's a new day
                                            $showDateLabel = ($dateLabel && $currentDate !== $lastDate);
                                            $lastDate = $currentDate;
                                        ?>
                                            <div class="timeline-hour text-center text-xs text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-600 relative <?php echo ($showDateLabel) ? 'day-boundary' : ''; ?>">
                                                <?php if ($showDateLabel): ?>
                                                    <div class="absolute -top-5 left-0 right-0 text-[10px] font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                        <?php echo htmlspecialchars($dateLabel); ?>
                                                    </div>
                                                <?php endif; ?>
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
                                // Function to get status color based on status name
                                function getStatusColor($status) {
                                    $statusColors = [
                                        'Boarding' => '#ADD8E6',          // Light Blue
                                        'Cancelled' => '#FF0000',         // Red
                                        'Complete' => '#32CD32',          // Green
                                        'Confirmed' => '#006400',         // Dark Green
                                        'Delayed' => '#FFA500',           // Orange
                                        'Diverted' => '#8B0000',          // Dark Red
                                        'Gate Closed' => '#FFD700',        // Gold
                                        'Landed' => '#1E90FF',            // Dodger Blue
                                        'Off Block' => '#A0522D',         // Brown
                                        'On Block' => '#9370DB',          // Purple
                                        'Pending' => '#808080',           // Gray
                                        'Ready' => '#00FF00',             // Bright Green
                                        'Return to Ramp' => '#FF8C00',    // Dark Orange
                                        'Start' => '#4682B4',             // Steel Blue
                                        'Takeoff' => '#228B22',           // Forest Green
                                        'Taxi' => '#2F4F4F'               // Dark Slate Gray
                                    ];
                                    return $statusColors[$status] ?? '#3b82f6'; // Default blue
                                }
                                
                                foreach ($flightsByAircraft as $aircraft_rego => $flights): 
                                    // Calculate height based on max flights per day (not total flights)
                                    // 32px bar height + 2px spacing + 14px time labels + 2px bottom spacing = 50px per flight
                                    if ($maxFlightsPerDay > 0) {
                                        $min_row_height = 6 + (32 + 2 + 14 + 2) + (($maxFlightsPerDay - 1) * 50) + 30 + 8;
                                    } else {
                                        $min_row_height = 100;
                                    }
                                    
                                    // Group flights by date for vertical positioning
                                    // Each day's flights start from row 1 (index 0)
                                    $flightsByDateForAircraft = [];
                                    foreach ($flights as $flight) {
                                        // Determine which date this flight belongs to
                                        $flightDate = null;
                                        if (!empty($flight['TaskStart'])) {
                                            try {
                                                $taskStartDate = new DateTime($flight['TaskStart']);
                                                $flightDate = $taskStartDate->format('Y-m-d');
                                            } catch (Exception $e) {
                                                // Skip if invalid date
                                            }
                                        }
                                        if (!$flightDate && !empty($flight['FltDate'])) {
                                            try {
                                                $fltDate = new DateTime($flight['FltDate']);
                                                $flightDate = $fltDate->format('Y-m-d');
                                            } catch (Exception $e) {
                                                // Skip if invalid date
                                            }
                                        }
                                        
                                        // Default to today if date cannot be determined
                                        if (!$flightDate) {
                                            $flightDate = $today;
                                        }
                                        
                                        if (!isset($flightsByDateForAircraft[$flightDate])) {
                                            $flightsByDateForAircraft[$flightDate] = [];
                                        }
                                        $flightsByDateForAircraft[$flightDate][] = $flight;
                                    }
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
                                            <!-- Horizontal Grid Lines - Based on max flights per day -->
                                            <div class="timeline-row-grid">
                                                <?php 
                                                // Generate horizontal grid lines based on max flights per day
                                                // Each line is at 50px intervals (6px top + 32px bar + 2px gap + 14px time + 2px bottom)
                                                for ($i = 0; $i < $maxFlightsPerDay; $i++): 
                                                    $lineTop = 6 + ($i * 50); // 6px top offset + 50px per flight
                                                ?>
                                                    <div class="horizontal-grid-line" style="top: <?php echo $lineTop; ?>px;"></div>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Day Boundary Lines (Vertical) - Separates different days -->
                                            <?php 
                                            $lastDateForBoundary = '';
                                            foreach ($timelineHours as $hourData): 
                                                $currentDate = is_array($hourData) ? $hourData['date'] : '';
                                                $hour = is_array($hourData) ? $hourData['hour'] : $hourData;
                                                
                                                // Show vertical line at day boundaries (00:00) except for first day
                                                if ($hour === '00:00' && $currentDate !== $lastDateForBoundary && $currentDate !== $yesterday): 
                                                    // Calculate position of this hour in timeline
                                                    $hourTimestamp = is_array($hourData) ? $hourData['timestamp'] : 0;
                                                    $timelineStartTimestamp = $timelineStart->getTimestamp();
                                                    $timelineDuration = $timelineEnd->getTimestamp() - $timelineStartTimestamp;
                                                    $hourPosition = (($hourTimestamp - $timelineStartTimestamp) / $timelineDuration) * 100;
                                            ?>
                                                <div class="day-boundary-line" style="left: <?php echo number_format($hourPosition, 2); ?>%;">
                                                    <div class="day-boundary-line-inner"></div>
                                                </div>
                                            <?php 
                                                endif;
                                                if ($currentDate !== $lastDateForBoundary) {
                                                    $lastDateForBoundary = $currentDate;
                                                }
                                            endforeach; 
                                            ?>
                                            
                                            <!-- Current Time Indicator (Yellow Line) -->
                                            <?php if ($currentTimePosition !== null): ?>
                                            <div class="current-time-indicator" style="left: <?php echo number_format($currentTimePosition, 2); ?>%;">
                                                <div class="current-time-line"></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            // Iterate through flights grouped by date
                                            // Each day's flights start from row 1 (index 0)
                                            foreach ($flightsByDateForAircraft as $flightDate => $dateFlights): 
                                                foreach ($dateFlights as $index => $flight): 
                                                    // Only calculate if timeline is properly initialized
                                                    // Use actual_out_utc and actual_in_utc (fallback to TaskStart/TaskEnd)
                                                    $actualStart = !empty($flight['actual_out_utc']) ? $flight['actual_out_utc'] : ($flight['TaskStart'] ?? null);
                                                    $actualEnd = !empty($flight['actual_in_utc']) ? $flight['actual_in_utc'] : ($flight['TaskEnd'] ?? null);
                                                
                                                if ($timelineStart && $timelineEnd && $actualStart && $actualEnd):
                                                // Calculate positions and durations relative to timeline range
                                                try {
                                                    $task_start = new DateTime($actualStart);
                                                    $task_end = new DateTime($actualEnd);
                                                } catch (Exception $e) {
                                                    // Skip this flight if invalid date
                                                    continue;
                                                }
                                                
                                                // Calculate total timeline duration in seconds
                                                $timelineDuration = $timelineEnd->getTimestamp() - $timelineStart->getTimestamp();
                                                
                                                if ($timelineDuration > 0) {
                                                    // Calculate relative positions as percentages
                                                    $start_timestamp = $task_start->getTimestamp();
                                                    $end_timestamp = $task_end->getTimestamp();
                                                    $timeline_start_ts = $timelineStart->getTimestamp();
                                                    $timeline_end_ts = $timelineEnd->getTimestamp();
                                                    
                                                    // Calculate raw positions
                                                    $start_position_percent = (($start_timestamp - $timeline_start_ts) / $timelineDuration) * 100;
                                                    $end_position_percent = (($end_timestamp - $timeline_start_ts) / $timelineDuration) * 100;
                                                    
                                                    // Clamp positions to timeline bounds (0% to 100%)
                                                    $clamped_start = max(0, min(100, $start_position_percent));
                                                    $clamped_end = max(0, min(100, $end_position_percent));
                                                    
                                                    // If flight is completely outside timeline, skip it
                                                    if ($end_timestamp < $timeline_start_ts || $start_timestamp > $timeline_end_ts) {
                                                        continue;
                                                    }
                                                    
                                                    // Use clamped positions
                                                    $start_position_percent = $clamped_start;
                                                    
                                                    // Calculate duration within visible bounds
                                                    // Ensure end position doesn't exceed 100%
                                                    if ($clamped_end > $clamped_start) {
                                                        $duration_percent = $clamped_end - $clamped_start;
                                                    } else {
                                                        // If end is before start (shouldn't happen), use minimum width
                                                        $duration_percent = 2;
                                                        $start_position_percent = max(0, min(98, $clamped_start));
                                                    }
                                                    
                                                    // Ensure minimum width
                                                    if ($duration_percent < 2) {
                                                        $duration_percent = 2;
                                                        // Adjust start position to keep bar visible and within bounds
                                                        if ($start_position_percent + $duration_percent > 100) {
                                                            $start_position_percent = max(0, 100 - $duration_percent);
                                                        }
                                                    }
                                                    
                                                    // Ensure bar doesn't exceed 100% width
                                                    if ($start_position_percent + $duration_percent > 100) {
                                                        $duration_percent = 100 - $start_position_percent;
                                                        if ($duration_percent < 1) {
                                                            $duration_percent = 1;
                                                            $start_position_percent = 99;
                                                        }
                                                    }
                                                    
                                                    // Ensure start position is within bounds
                                                    $start_position_percent = max(0, min(100 - $duration_percent, $start_position_percent));
                                                    
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
                                                    
                                                    // Calculate delay in seconds
                                                    $delay_seconds = $delay_minutes * 60;
                                                    
                                                    // Delay bar: from TaskStart to TaskStart + delay
                                                    $delay_start_timestamp = $start_timestamp;
                                                    $delay_end_timestamp = $start_timestamp + $delay_seconds;
                                                    
                                                    // Flight bar: from TaskStart + delay to TaskEnd + delay
                                                    $flight_start_timestamp = $start_timestamp + $delay_seconds;
                                                    $flight_end_timestamp = $end_timestamp + $delay_seconds; // Add delay to TaskEnd
                                                    
                                                    // Calculate positions relative to timelineStart
                                                    $delay_start_offset_from_timeline = $delay_start_timestamp - $timeline_start_ts;
                                                    $delay_end_offset_from_timeline = $delay_end_timestamp - $timeline_start_ts;
                                                    $flight_start_offset_from_timeline = $flight_start_timestamp - $timeline_start_ts;
                                                    $flight_end_offset_from_timeline = $flight_end_timestamp - $timeline_start_ts;
                                                    
                                                    // Convert to percentages (0-100%)
                                                    $delay_start_position_percent = ($delay_start_offset_from_timeline / $timelineDuration) * 100;
                                                    $delay_end_position_percent = ($delay_end_offset_from_timeline / $timelineDuration) * 100;
                                                    $delay_duration_percent = (($delay_end_offset_from_timeline - $delay_start_offset_from_timeline) / $timelineDuration) * 100;
                                                    
                                                    $start_position_percent = ($flight_start_offset_from_timeline / $timelineDuration) * 100;
                                                    $end_position_percent = ($flight_end_offset_from_timeline / $timelineDuration) * 100;
                                                    
                                                    // Calculate flight duration (from TaskStart + delay to TaskEnd + delay)
                                                    if ($flight_end_offset_from_timeline >= $flight_start_offset_from_timeline) {
                                                        $duration_percent = (($flight_end_offset_from_timeline - $flight_start_offset_from_timeline) / $timelineDuration) * 100;
                                                    } else {
                                                        // If TaskEnd + delay is before TaskStart + delay, set minimum duration
                                                        $duration_percent = 0.5; // Minimum 0.5% width
                                                    }
                                                    
                                                    // Ensure minimum values and bounds (0-100%)
                                                    $delay_start_position_percent = max(0, min(100, $delay_start_position_percent));
                                                    $delay_end_position_percent = max(0, min(100, $delay_end_position_percent));
                                                    $delay_duration_percent = max(0.5, min(100 - $delay_start_position_percent, $delay_duration_percent));
                                                    
                                                    $start_position_percent = max(0, min(100, $start_position_percent));
                                                    $end_position_percent = max(0, min(100, $end_position_percent));
                                                    $duration_percent = max(0.5, min(100 - $start_position_percent, $duration_percent));
                                                    
                                                    // Ensure delay bar ends exactly where flight bar starts (no gap, no overlap)
                                                    if ($delay_minutes > 0 && $delay_duration_percent > 0) {
                                                        if ($start_position_percent > $delay_start_position_percent) {
                                                            // Adjust delay to end exactly where flight starts
                                                            $delay_duration_percent = $start_position_percent - $delay_start_position_percent;
                                                            $delay_end_position_percent = $start_position_percent;
                                                        } else {
                                                            // If flight starts before delay ends, adjust delay
                                                            $delay_duration_percent = max(0.5, min(100 - $delay_start_position_percent, $delay_duration_percent));
                                                            $delay_end_position_percent = $delay_start_position_percent + $delay_duration_percent;
                                                            // Update flight_start_percent to match delay_end_percent
                                                            $start_position_percent = $delay_end_position_percent;
                                                        }
                                                    } else {
                                                        $delay_duration_percent = 0;
                                                        $delay_start_position_percent = 0;
                                                        $delay_end_position_percent = 0;
                                                    }
                                                    
                                                    $top_position = 6 + ($index * 50); // 50px spacing between flights (32px bar + 2px gap + 14px time + 2px bottom)
                                                    
                                                    // Get status color
                                                    $flight_status = $flight['ScheduledTaskStatus'] ?? '';
                                                    $status_color_hex = getStatusColor($flight_status);
                                                } else {
                                                    // Fallback if timeline duration is invalid
                                                    $start_position_percent = 0;
                                                    $duration_percent = 2;
                                                    
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
                                                    
                                                    $delay_duration_percent = 0;
                                                    $delay_start_position_percent = 0;
                                                    $top_position = 4 + ($index * 25);
                                                    
                                                    // Get status color
                                                    $flight_status = $flight['ScheduledTaskStatus'] ?? '';
                                                    $status_color_hex = getStatusColor($flight_status);
                                                }
                                                
                                                // Format TaskStart and TaskEnd times
                                                $task_start_time = '';
                                                $task_end_time = '';
                                                if (!empty($flight['TaskStart'])) {
                                                    try {
                                                        $taskStartDate = new DateTime($flight['TaskStart']);
                                                        $task_start_time = $taskStartDate->format('H:i');
                                                    } catch (Exception $e) {
                                                        $task_start_time = '';
                                                    }
                                                }
                                                if (!empty($flight['TaskEnd'])) {
                                                    try {
                                                        $taskEndDate = new DateTime($flight['TaskEnd']);
                                                        $task_end_time = $taskEndDate->format('H:i');
                                                    } catch (Exception $e) {
                                                        $task_end_time = '';
                                                    }
                                                }
                                                
                                                // Get TaskName or FlightNo
                                                $flight_display_name = !empty($flight['TaskName']) ? $flight['TaskName'] : ($flight['FlightNo'] ?? '');
                                                $route_display = $flight['Route'] ?? '';
                                                
                                                // Build display text: TaskName - Route (without time)
                                                $display_text = '';
                                                if ($flight_display_name) {
                                                    $display_text .= htmlspecialchars($flight_display_name);
                                                }
                                                if ($route_display) {
                                                    if ($display_text) $display_text .= ' - ';
                                                    $display_text .= htmlspecialchars($route_display);
                                                }
                                                
                                                // Calculate time labels position (below the flight bar)
                                                $time_labels_top = $top_position + 32 + 2; // Below the 32px height bar + 2px spacing
                                                ?>
                                                
                                                <?php if ($delay_minutes > 0 && $delay_duration_percent > 0): ?>
                                                <!-- Delay Bar (Red) -->
                                                <div class="flight-bar bg-red-500 text-white text-xs px-2 py-1 cursor-pointer"
                                                     style="left: <?php echo number_format($delay_start_position_percent, 2); ?>%; width: <?php echo number_format($delay_duration_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 5; border-radius: 8px 0 0 8px; height: 32px; display: flex; align-items: center;"
                                                     title="<?php echo $delay_minutes; ?> min delay">
                                                    <div class="truncate text-xs font-medium">
                                                        <?php echo $delay_minutes; ?>m
                                                    </div>
                                                </div>
                                                
                                                <!-- Flight Bar -->
                                                <div class="flight-bar text-white text-xs px-2 py-1 cursor-pointer flight-tooltip-trigger flight-edit-trigger"
                                                     data-flight-id="<?php echo htmlspecialchars($flight['id'] ?? ''); ?>"
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
                                                <div class="flight-bar text-white text-xs px-2 py-1 cursor-pointer flight-tooltip-trigger flight-edit-trigger"
                                                     data-flight-id="<?php echo htmlspecialchars($flight['id'] ?? ''); ?>"
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
                                                <?php endforeach; // End dateFlights loop ?>
                                            <?php endforeach; // End flightsByDateForAircraft loop ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Aircraft -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-plane mr-2"></i>Recent Aircraft
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php if (empty($recentAircraft)): ?>
                                    <div class="text-center py-8">
                                        <i class="fas fa-plane text-gray-400 text-4xl mb-4"></i>
                                        <p class="text-gray-500 dark:text-gray-400">No aircraft found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentAircraft as $aircraft_item): ?>
                                        <?php
                                        // Determine status color and text
                                        $statusConfig = [
                                            'active' => ['class' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'text' => 'Active'],
                                            'inactive' => ['class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200', 'text' => 'Inactive'],
                                            'maintenance' => ['class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', 'text' => 'Maintenance'],
                                            'retired' => ['class' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', 'text' => 'Retired']
                                        ];
                                        $status = $statusConfig[$aircraft_item['status']] ?? $statusConfig['inactive'];
                                        ?>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($aircraft_item['registration']); ?></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($aircraft_item['manufacturer'] . ' ' . $aircraft_item['aircraft_type']); ?>
                                                    <?php if ($aircraft_item['base_location']): ?>
                                                        • <?php echo htmlspecialchars($aircraft_item['base_location']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status['class']; ?>">
                                                <?php echo $status['text']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-bolt mr-2"></i>Quick Actions
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <a href="/admin/fleet/aircraft/" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-plane text-blue-600 dark:text-blue-400 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Aircraft</span>
                                </a>
                                <a href="/admin/users/" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-users text-green-600 dark:text-green-400 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Users</span>
                                </a>
                                <a href="/admin/flights/" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-route text-purple-600 dark:text-purple-400 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Flights</span>
                                </a>
                                <a href="/admin/role_permission.php" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-shield-alt text-orange-600 dark:text-orange-400 text-2xl mb-2"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Permissions</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize dark mode from localStorage (if available)
        document.addEventListener('DOMContentLoaded', function() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const html = document.documentElement;
            
            if (savedDarkMode === 'true') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            
            // Auto-scroll to current time indicator (yellow line)
            scrollToCurrentTime();
        });
        
        // Function to scroll to current time indicator
        function scrollToCurrentTime() {
            // Find the scrollable container (timeline-container has overflow-x: auto)
            const timelineContainer = document.querySelector('.timeline-container');
            if (!timelineContainer) return;
            
            // Find the first current time indicator (yellow line) in the header
            // It's in the timeline header, not in the aircraft rows
            // Look for the indicator in the header div that contains the timeline hours
            const timelineWrapper = timelineContainer.querySelector('.timeline-wrapper');
            if (!timelineWrapper) return;
            
            // Find the header div (first flex div with mb-4)
            const timelineHeader = timelineWrapper.querySelector('div.flex.mb-4.relative');
            if (!timelineHeader) return;
            
            // Find the inner flex div that contains the hours and indicator
            const hoursContainer = timelineHeader.querySelector('div.flex.relative');
            if (!hoursContainer) return;
            
            const currentTimeIndicator = hoursContainer.querySelector('.current-time-indicator');
            if (!currentTimeIndicator) return;
            
            // Wait for layout to be calculated and images to load
            setTimeout(function() {
                // Get the position of the current time indicator relative to its parent (hoursContainer)
                const indicatorLeft = currentTimeIndicator.offsetLeft;
                const indicatorWidth = currentTimeIndicator.offsetWidth || 2; // Default to 2px if not set
                
                // Calculate scroll position to center the indicator
                // Scroll to position indicator in the middle of the visible area
                const scrollLeft = indicatorLeft - (timelineContainer.clientWidth / 2) + (indicatorWidth / 2);
                
                // Smooth scroll to the current time indicator
                timelineContainer.scrollTo({
                    left: Math.max(0, scrollLeft),
                    behavior: 'smooth'
                });
            }, 200); // Increased timeout to ensure layout is ready
        }
        
        // Dismiss safety alert
        function dismissAlert() {
            const alertElement = document.querySelector('.bg-gradient-to-r.from-orange-50');
            if (alertElement) {
                alertElement.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                alertElement.style.opacity = '0';
                alertElement.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    alertElement.remove();
                }, 300);
            }
        }
        
        // Flight Tooltip Handler
        let tooltipTimeout = null;
        let currentTooltipFlightId = null;
        let tooltipData = null;
        
        function showFlightTooltip(event, flightId) {
            if (!flightId) return;
            
            const tooltip = document.getElementById('flightTooltip');
            const tooltipContent = document.getElementById('flightTooltipContent');
            
            if (!tooltip || !tooltipContent) return;
            
            // Clear any existing timeout
            if (tooltipTimeout) {
                clearTimeout(tooltipTimeout);
            }
            
            // If we already have data for this flight, show immediately
            if (currentTooltipFlightId === flightId && tooltipData) {
                renderTooltip(tooltipContent, tooltipData);
                positionTooltip(tooltip, event);
                tooltip.classList.add('show');
                return;
            }
            
            // Show loading state
            tooltipContent.innerHTML = '<div style="padding: 20px; text-align: center;">Loading...</div>';
            positionTooltip(tooltip, event);
            tooltip.classList.add('show');
            
            // Fetch flight data
            fetch(`api/get_flight_tooltip.php?id=${flightId}`)
                .then(response => response.json())
                .then(data => {
                    tooltipData = data;
                    currentTooltipFlightId = flightId;
                    renderTooltip(tooltipContent, data);
                    positionTooltip(tooltip, event);
                })
                .catch(error => {
                    console.error('Error fetching flight tooltip:', error);
                    tooltipContent.innerHTML = '<div style="padding: 20px; text-align: center; color: red;">Error loading flight details</div>';
                });
        }
        
        function hideFlightTooltip() {
            const tooltip = document.getElementById('flightTooltip');
            if (tooltip) {
                tooltipTimeout = setTimeout(() => {
                    tooltip.classList.remove('show');
                }, 200);
            }
        }
        
        function renderTooltip(container, data) {
            let html = `
                <div class="flight-tooltip-header">Flight Details</div>
                
                <div class="flight-tooltip-section">
                    <div class="flight-tooltip-section-title">Flight Information</div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Flight Number:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.flight_number || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Route:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.route || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Aircraft:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.aircraft || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Aircraft Type:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.aircraft_type || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Status:</span>
                        <span class="flight-tooltip-value flight-tooltip-status-value">${escapeHtml(data.status || '-')}</span>
                    </div>
                </div>
                
                <div class="flight-tooltip-section">
                    <div class="flight-tooltip-section-title">Scheduled Information</div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Date:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.date || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Task Start:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.scheduled_task_start || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Task End:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.scheduled_task_end || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Duration:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.duration || '-')}</span>
                    </div>
                </div>
                
                <div class="flight-tooltip-section">
                    <div class="flight-tooltip-section-title">Actual Information</div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Actual Start:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.task_start || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Actual End:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.task_end || '-')}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Actual Duration:</span>
                        <span class="flight-tooltip-value">${escapeHtml(data.actual_duration || '-')}</span>
                    </div>
                </div>
                
                <div class="flight-tooltip-section">
                    <div class="flight-tooltip-section-title">Crew Information</div>
                    ${data.crew_list && data.crew_list.length > 0 ? `
                    <div style="margin-bottom: 8px;">
                        <div class="flight-tooltip-label" style="font-weight: bold; margin-bottom: 6px;">Crew Members:</div>
                        <div style="padding-left: 0;">
                            ${data.crew_list.map(crew => `
                                <div style="margin-bottom: 4px; text-align: left;">
                                    ${crew.role ? `<span style="color:rgb(0, 0, 0); font-weight: 200;">${escapeHtml(crew.role)}:</span> ` : ''}
                                    <span style="font-weight: 200;">${escapeHtml(crew.name || ':')}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <div class="flight-tooltip-section">
                    <div class="flight-tooltip-section-title">Passenger Information</div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Total Passengers:</span>
                        <span class="flight-tooltip-value">${data.total_passengers || '0'}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Adults:</span>
                        <span class="flight-tooltip-value">${data.adults || '0.00'}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Children:</span>
                        <span class="flight-tooltip-value">${data.children || '0.00'}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Infants:</span>
                        <span class="flight-tooltip-value">${data.infants || '0.00'}</span>
                    </div>
                </div>
                
                <div class="flight-tooltip-section">
                    <div class="flight-tooltip-section-title">Additional Information</div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">PCS:</span>
                        <span class="flight-tooltip-value">${data.pcs || '0.00'}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Weight (KG):</span>
                        <span class="flight-tooltip-value">${data.weight || '0.00'}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Uplift Fuel (Liter):</span>
                        <span class="flight-tooltip-value">${data.uplift_fuel || '0.00'}</span>
                    </div>
                    <div class="flight-tooltip-row">
                        <span class="flight-tooltip-label">Uplift in LBS:</span>
                        <span class="flight-tooltip-value">${data.uplift_lbs || '0'}</span>
                    </div>
                </div>
            `;
            
            // Add delay information if available
            if (data.delays && data.delays.length > 0) {
                html += `
                    <div class="flight-tooltip-section">
                        <div class="flight-tooltip-section-title">Delay/Diversion Information</div>
                        <div class="flight-tooltip-delays">
                `;
                data.delays.forEach(delay => {
                    html += `
                        <div class="flight-tooltip-delay-item">
                            <strong>${escapeHtml(delay.code || '-')}</strong> - ${escapeHtml(delay.minutes || '0')} minutes<br>
                            ${delay.description ? `<small>${escapeHtml(delay.description)}</small>` : ''}
                            ${delay.remark ? `<br><em>Remark: ${escapeHtml(delay.remark)}</em>` : ''}
                        </div>
                    `;
                });
                html += `
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        function positionTooltip(tooltip, event) {
            if (!tooltip || !event) return;
            
            const mouseX = event.clientX;
            const mouseY = event.clientY;
            const tooltipWidth = tooltip.offsetWidth || 500;
            const tooltipHeight = tooltip.offsetHeight || 400;
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            const offset = 10;
            
            let left = mouseX + offset;
            let top = mouseY + offset;
            
            // Adjust if tooltip goes off screen horizontally
            if (left + tooltipWidth > windowWidth) {
                left = mouseX - tooltipWidth - offset;
            }
            
            // Adjust if tooltip goes off screen vertically
            if (top + tooltipHeight > windowHeight) {
                top = mouseY - tooltipHeight - offset;
            }
            
            // Ensure tooltip stays within viewport
            left = Math.max(offset, Math.min(left, windowWidth - tooltipWidth - offset));
            top = Math.max(offset, Math.min(top, windowHeight - tooltipHeight - offset));
            
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
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
        
        // Initialize tooltip handlers
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('mouseenter', function(e) {
                const target = e.target;
                if (!target || typeof target.closest !== 'function') return;
                
                const trigger = target.closest('.flight-tooltip-trigger');
                if (trigger) {
                    const flightId = trigger.getAttribute('data-flight-id');
                    if (flightId) {
                        if (tooltipTimeout) {
                            clearTimeout(tooltipTimeout);
                            tooltipTimeout = null;
                        }
                        showFlightTooltip(e, flightId);
                    }
                }
            }, true);
            
            document.addEventListener('mouseleave', function(e) {
                const target = e.target;
                if (!target || typeof target.closest !== 'function') return;
                
                const trigger = target.closest('.flight-tooltip-trigger');
                if (trigger) {
                    hideFlightTooltip();
                }
            }, true);
            
            // Hide tooltip when mouse leaves tooltip itself
            const tooltip = document.getElementById('flightTooltip');
            if (tooltip) {
                tooltip.addEventListener('mouseenter', function() {
                    if (tooltipTimeout) {
                        clearTimeout(tooltipTimeout);
                        tooltipTimeout = null;
                    }
                });
                
                tooltip.addEventListener('mouseleave', function() {
                    hideFlightTooltip();
                });
            }
            
            // Flight Edit Modal - Click handlers
            document.addEventListener('click', function(e) {
                const target = e.target;
                if (!target || typeof target.closest !== 'function') return;
                
                const trigger = target.closest('.flight-edit-trigger');
                if (trigger) {
                    const flightId = trigger.getAttribute('data-flight-id');
                    if (flightId && e.type === 'click') {
                        e.stopPropagation();
                        e.preventDefault();
                        openFlightEditModal(flightId);
                    }
                }
            });
        });
        
        // Flight Edit Modal Functions
        let currentEditFlightId = null;
        
        function openFlightEditModal(flightId) {
            if (!flightId) return;
            
            currentEditFlightId = flightId;
            // Reset timezone flag when opening modal (default is Tehran)
            timesInUTC = false;
            
            // Reset button text when opening modal
            setTimeout(() => {
                const btn = document.getElementById('convertToUTCBtn');
                const btnText = document.getElementById('convertToUTCBtnText');
                if (btn && btnText) {
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    btnText.textContent = 'Convert To UTC';
                }
            }, 100);
            
            const modal = document.getElementById('flightEditModal');
            const modalBody = document.getElementById('flightEditModalBody');
            
            if (!modal || !modalBody) return;
            
            // Show modal
            modal.classList.add('show');
            modalBody.innerHTML = '<div class="flight-edit-loading">Loading flight data...</div>';
            
            // Load flight data
            fetch(`api/get_flight_edit.php?id=${flightId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<div class="flight-edit-error">${escapeHtml(data.error)}</div>`;
                        return;
                    }
                    renderFlightEditForm(modalBody, data);
                })
                .catch(error => {
                    console.error('Error loading flight data:', error);
                    modalBody.innerHTML = '<div class="flight-edit-error">Error loading flight data. Please try again.</div>';
                });
        }
        
        // Global variable to store delay codes
        let globalDelayCodes = [];
        
        function renderFlightEditForm(container, data) {
            // Store delay codes globally for use in updateDV93Description
            globalDelayCodes = data.delay_codes || [];
            
            // Format date for input fields
            function formatDate(dt) {
                if (!dt) return '';
                try {
                    const date = new Date(dt);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                } catch (e) {
                    return '';
                }
            }
            
            // Format time for input fields (HHMM format)
            function formatTime(dt) {
                if (!dt) return '';
                try {
                    const date = new Date(dt);
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${hours}${minutes}`;
                } catch (e) {
                    return '';
                }
            }
            
            const html = `
                <form id="flightEditForm" onsubmit="saveFlightData(event)">
                    <div id="flightEditMessages"></div>
                    
                    <!-- Basic Information -->
                    <div class="flight-edit-form-section">
                        <h3 class="flight-edit-form-section-title">Basic Information</h3>
                        <div class="flight-edit-form-grid grid-3">
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Task Name (Flight Number)</label>
                                <input type="text" name="TaskName" value="${escapeHtml(data.TaskName || '')}" class="flight-edit-form-input" required>
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Route</label>
                                <input type="text" name="Route" value="${escapeHtml(data.Route || '')}" class="flight-edit-form-input" required>
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Status</label>
                                <select name="ScheduledTaskStatus" class="flight-edit-form-select">
                                    <option value="">--Select--</option>
                                    <option value="Boarding" ${data.ScheduledTaskStatus === 'Boarding' ? 'selected' : ''}>Boarding</option>
                                    <option value="Cancelled" ${data.ScheduledTaskStatus === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                                    <option value="Complete" ${data.ScheduledTaskStatus === 'Complete' ? 'selected' : ''}>Complete</option>
                                    <option value="Confirmed" ${data.ScheduledTaskStatus === 'Confirmed' ? 'selected' : ''}>Confirmed</option>
                                    <option value="Delayed" ${data.ScheduledTaskStatus === 'Delayed' ? 'selected' : ''}>Delayed</option>
                                    <option value="Diverted" ${data.ScheduledTaskStatus === 'Diverted' ? 'selected' : ''}>Diverted</option>
                                    <option value="Gate Closed" ${data.ScheduledTaskStatus === 'Gate Closed' ? 'selected' : ''}>Gate Closed</option>
                                    <option value="Landed" ${data.ScheduledTaskStatus === 'Landed' ? 'selected' : ''}>Landed</option>
                                    <option value="Off Block" ${data.ScheduledTaskStatus === 'Off Block' ? 'selected' : ''}>Off Block</option>
                                    <option value="On Block" ${data.ScheduledTaskStatus === 'On Block' ? 'selected' : ''}>On Block</option>
                                    <option value="Pending" ${data.ScheduledTaskStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Ready" ${data.ScheduledTaskStatus === 'Ready' ? 'selected' : ''}>Ready</option>
                                    <option value="Return to Ramp" ${data.ScheduledTaskStatus === 'Return to Ramp' ? 'selected' : ''}>Return to Ramp</option>
                                    <option value="Start" ${data.ScheduledTaskStatus === 'Start' ? 'selected' : ''}>Start</option>
                                    <option value="Takeoff" ${data.ScheduledTaskStatus === 'Takeoff' ? 'selected' : ''}>Takeoff</option>
                                    <option value="Taxi" ${data.ScheduledTaskStatus === 'Taxi' ? 'selected' : ''}>Taxi</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Times Section -->
                    <div class="flight-edit-form-section">
                        <h3 class="flight-edit-form-section-title">Times</h3>
                        <div class="flight-edit-form-grid grid-4">
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Task Start Date</label>
                                <input type="date" name="TaskStart_date" id="TaskStart_date" value="${formatDate(data.TaskStart)}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Task Start Time (HHMM)</label>
                                <input type="text" name="TaskStart_time" id="TaskStart_time" value="${formatTime(data.TaskStart)}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4" placeholder="1125">
                                <input type="hidden" name="TaskStart" id="TaskStart" value="${data.TaskStart || ''}">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Task End Date</label>
                                <input type="date" name="TaskEnd_date" id="TaskEnd_date" value="${formatDate(data.TaskEnd)}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Task End Time (HHMM)</label>
                                <input type="text" name="TaskEnd_time" id="TaskEnd_time" value="${formatTime(data.TaskEnd)}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4" placeholder="1200">
                                <input type="hidden" name="TaskEnd" id="TaskEnd" value="${data.TaskEnd || ''}">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Actual Out (UTC) Date</label>
                                <input type="date" name="actual_out_utc_date" id="actual_out_utc_date" value="${formatDate(data.actual_out_utc)}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Actual Out (UTC) Time (HHMM)</label>
                                <input type="text" name="actual_out_utc_time" id="actual_out_utc_time" value="${formatTime(data.actual_out_utc)}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4" placeholder="1119">
                                <input type="hidden" name="actual_out_utc" id="actual_out_utc" value="${data.actual_out_utc || ''}">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Actual In (UTC) Date</label>
                                <input type="date" name="actual_in_utc_date" id="actual_in_utc_date" value="${formatDate(data.actual_in_utc)}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Actual In (UTC) Time (HHMM)</label>
                                <input type="text" name="actual_in_utc_time" id="actual_in_utc_time" value="${formatTime(data.actual_in_utc)}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4" placeholder="0017">
                                <input type="hidden" name="actual_in_utc" id="actual_in_utc" value="${data.actual_in_utc || ''}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Flight Times -->
                    <div class="flight-edit-form-section">
                        <h3 class="flight-edit-form-section-title">Flight Times (HHMM)</h3>
                        <div class="flight-edit-form-grid grid-3">
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Boarding</label>
                                <input type="text" name="boarding" value="${escapeHtml(data.boarding || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Gate Closed</label>
                                <input type="text" name="gate_closed" value="${escapeHtml(data.gate_closed || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Ready</label>
                                <input type="text" name="ready" value="${escapeHtml(data.ready || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Start</label>
                                <input type="text" name="start" value="${escapeHtml(data.start || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Off Block</label>
                                <input type="text" name="off_block" value="${escapeHtml(data.off_block || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Taxi</label>
                                <input type="text" name="taxi" value="${escapeHtml(data.taxi || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Return to Ramp</label>
                                <input type="text" name="return_to_ramp" value="${escapeHtml(data.return_to_ramp || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Takeoff</label>
                                <input type="text" name="takeoff" value="${escapeHtml(data.takeoff || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Landed</label>
                                <input type="text" name="landed" value="${escapeHtml(data.landed || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">On Block</label>
                                <input type="text" name="on_block" value="${escapeHtml(data.on_block || '')}" class="flight-edit-form-input" pattern="[0-9]{4}" maxlength="4">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Passengers & Weight -->
                    <div class="flight-edit-form-section">
                        <h3 class="flight-edit-form-section-title">Passengers & Weight</h3>
                        <div class="flight-edit-form-grid grid-4">
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Adults</label>
                                <input type="number" step="0.01" name="adult" value="${data.adult || 0}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Children</label>
                                <input type="number" step="0.01" name="child" value="${data.child || 0}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Infants</label>
                                <input type="number" step="0.01" name="infant" value="${data.infant || 0}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Total Passengers</label>
                                <input type="number" step="0.01" name="total_pax" value="${data.total_pax || 0}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">PCS</label>
                                <input type="number" step="1" min="0" name="pcs" value="${data.pcs || 0}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Weight (kg)</label>
                                <input type="number" step="1" min="0" name="weight" value="${data.weight || 0}" class="flight-edit-form-input">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Uplift Fuel (L)</label>
                                <input type="number" step="0.01" name="uplift_fuel" value="${data.uplift_fuel || 0}" class="flight-edit-form-input" oninput="calculateUpliftLbs(this)">
                            </div>
                            <div class="flight-edit-form-group">
                                <label class="flight-edit-form-label">Uplift (lbs)</label>
                                <input type="text" name="uplft_lbs" value="${data.uplft_lbs || 0}" class="flight-edit-form-input calculated-field" style="background: #d1fae5; color: #000000;" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delay and Diversion Codes -->
                    <div class="flight-edit-form-section">
                        <h3 class="flight-edit-form-section-title">Delay and Diversion Codes</h3>
                        ${generateDelayCodeRows(data)}
                    </div>
                    
                    <div class="flight-edit-modal-footer">
                        <button type="button" class="flight-edit-btn flight-edit-btn-cancel" onclick="closeFlightEditModal()">Cancel</button>
                        <button type="submit" class="flight-edit-btn flight-edit-btn-save" id="saveFlightBtn">Save Changes</button>
                    </div>
                </form>
            `;
            
            container.innerHTML = html;
            
            // Initialize datetime handlers
            initializeDateTimeHandlers();
            
            // Initialize delay code handlers after form is rendered
            initializeDelayCodeHandlers();
            
            // Initialize existing delay codes if any
            for (let i = 1; i <= 5; i++) {
                const codeField = i === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${i}`;
                const codeSelect = document.getElementById(codeField);
                if (codeSelect && codeSelect.value) {
                    updateDV93Description(i, codeSelect.value);
                }
            }
        }
        
        function generateDelayCodeRows(data) {
            const delayCodes = data.delay_codes || [];
            let html = '';
            
            for (let i = 1; i <= 5; i++) {
                const codeField = i === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${i}`;
                const subCodeField = `delay_diversion_sub_codes_${i}`;
                const minutesField = `minutes_${i}`;
                const dv93Field = `dv93_${i}`;
                const remarkField = `remark_${i}`;
                
                const selectedCode = data[codeField] || '';
                const selectedSubCode = data[subCodeField] || '';
                const minutes = data[minutesField] || '';
                const dv93 = data[dv93Field] || '';
                const remark = data[remarkField] || '';
                
                const isEnabled = i === 1 || selectedCode || minutes || dv93;
                const rowClass = isEnabled ? 'delay-row-enabled' : 'delay-row-disabled';
                
                // Determine if this row should be shown initially
                // Row 1 always shown, other rows shown if they have data or if previous rows are complete
                let shouldShow = i === 1;
                
                if (i > 1) {
                    // Show if this row has data
                    if (selectedCode || minutes || dv93) {
                        shouldShow = true;
                    } else {
                        // Check if previous row(s) are complete
                        let allPreviousComplete = true;
                        for (let j = 1; j < i; j++) {
                            const prevCodeField = j === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${j}`;
                            const prevMinutesField = `minutes_${j}`;
                            const prevCode = data[prevCodeField] || '';
                            const prevMinutes = data[prevMinutesField] || '';
                            
                            if (!prevCode || !prevMinutes) {
                                allPreviousComplete = false;
                                break;
                            }
                        }
                        shouldShow = allPreviousComplete;
                    }
                }
                
                const displayStyle = shouldShow ? '' : 'style="display: none;"';
                
                html += `
                    <div class="flight-edit-delay-row ${rowClass}" id="delay_row_${i}" ${displayStyle}>
                        <div class="delay-row-header">
                            <div class="delay-row-number">${i}</div>
                            <div class="delay-row-title">Delay/Diversion Code ${i}${selectedCode ? ': ' + escapeHtml(selectedCode) : ''}</div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(1, 1fr); gap: 12px;">
                            <div style="display: grid; grid-template-columns: repeat(1, 1fr); gap: 12px;" class="delay-code-grid">
                                <div>
                                    <label class="flight-edit-form-label">Code</label>
                                    <select name="${codeField}" id="${codeField}" onchange="updateDV93Description(${i}, this.value)" class="flight-edit-form-select" ${!isEnabled && i > 1 ? 'disabled' : ''}>
                                        <option value="">-- Select Code --</option>
                                        ${delayCodes.map(code => {
                                            const isSelected = code.code === selectedCode ? 'selected' : '';
                                            // Show only code in option, description will be shown below
                                            return `<option value="${escapeHtml(code.code)}" data-description="${escapeHtml(code.description)}" ${isSelected}>${escapeHtml(code.code)}</option>`;
                                        }).join('')}
                                    </select>
                                    <div id="code_description_${i}" class="delay-code-description" style="display: ${selectedCode ? 'block' : 'none'};">
                                        ${selectedCode ? (() => {
                                            const selectedCodeObj = delayCodes.find(c => c.code === selectedCode);
                                            return selectedCodeObj ? escapeHtml(selectedCodeObj.description) : '';
                                        })() : ''}
                                    </div>
                                </div>
                                <div style="display: ${selectedCode === '93 (RA)' ? 'block' : 'none'};" id="sub_code_${i}_container">
                                    <label class="flight-edit-form-label">Sub Code</label>
                                    <select name="${subCodeField}" id="${subCodeField}" onchange="updateSubCodeDescription(${i}, this.value)" class="flight-edit-form-select" data-selected-value="${escapeHtml(selectedSubCode)}">
                                        <option value="">-- Select Sub Code --</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(1, 1fr); gap: 12px;" class="delay-details-grid">
                                <div>
                                    <label class="flight-edit-form-label">Minutes</label>
                                    <input type="text" name="${minutesField}" id="${minutesField}" value="${escapeHtml(minutes)}" maxlength="5" class="flight-edit-form-input" ${!isEnabled && i > 1 ? 'disabled' : ''} placeholder="0">
                                </div>
                                <div>
                                    <label class="flight-edit-form-label">DV93 Description</label>
                                    <input type="text" name="${dv93Field}" id="${dv93Field}" value="${escapeHtml(dv93)}" class="flight-edit-form-input calculated-field" style="background: #d1fae5; color: #000000;" readonly placeholder="Select code to see description">
                                </div>
                            </div>
                            <div style="display: ${selectedCode === '99 (MX)' ? 'block' : 'none'}; margin-top: 4px;" id="remark_${i}_container">
                                <label class="flight-edit-form-label">Remark (for code 99)</label>
                                <textarea name="${remarkField}" id="${remarkField}" rows="2" class="flight-edit-form-input" placeholder="Enter remark...">${escapeHtml(remark)}</textarea>
                            </div>
                        </div>
                    </div>
                    <style>
                        @media (min-width: 768px) {
                            .delay-code-grid {
                                grid-template-columns: repeat(2, 1fr) !important;
                            }
                            .delay-details-grid {
                                grid-template-columns: repeat(2, 1fr) !important;
                            }
                        }
                    </style>
                `;
            }
            
            return html;
        }
        
        // Delay Code Management Functions
        function initializeDelayCodeHandlers() {
            // Enable next row when current row is filled (Code and Minutes both filled)
            for (let i = 1; i <= 4; i++) {
                const codeSelect = document.getElementById(i === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${i}`);
                const minutesInput = document.getElementById(`minutes_${i}`);
                
                if (codeSelect) {
                    codeSelect.addEventListener('change', function() {
                        checkAndShowNextRow(i);
                    });
                }
                
                if (minutesInput) {
                    minutesInput.addEventListener('input', function() {
                        checkAndShowNextRow(i);
                    });
                }
            }
        }
        
        function checkAndShowNextRow(currentRowNumber) {
            const codeSelect = document.getElementById(currentRowNumber === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${currentRowNumber}`);
            const minutesInput = document.getElementById(`minutes_${currentRowNumber}`);
            
            if (codeSelect && minutesInput && codeSelect.value && minutesInput.value) {
                // Current row is complete, show next row
                enableNextRow(currentRowNumber + 1);
            }
        }
        
        function enableNextRow(rowNumber) {
            if (rowNumber > 5) return;
            
            const nextRow = document.getElementById(`delay_row_${rowNumber}`);
            const codeSelect = document.getElementById(rowNumber === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${rowNumber}`);
            const minutesInput = document.getElementById(`minutes_${rowNumber}`);
            
            // Show the next row
            if (nextRow) {
                nextRow.style.display = 'block';
                nextRow.classList.remove('delay-row-disabled');
                nextRow.classList.add('delay-row-enabled');
            }
            
            // Enable inputs
            if (codeSelect) codeSelect.disabled = false;
            if (minutesInput) minutesInput.disabled = false;
        }
        
        function updateDV93Description(rowNumber, selectedValue) {
            const codeSelect = document.getElementById(rowNumber === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${rowNumber}`);
            const dv93Input = document.getElementById(`dv93_${rowNumber}`);
            const subCodeContainer = document.getElementById(`sub_code_${rowNumber}_container`);
            const subCodeSelect = document.getElementById(`delay_diversion_sub_codes_${rowNumber}`);
            const remarkContainer = document.getElementById(`remark_${rowNumber}_container`);
            const delayRow = codeSelect ? codeSelect.closest('.flight-edit-delay-row') : null;
            const delayRowTitle = delayRow ? delayRow.querySelector('.delay-row-title') : null;
            const descriptionDiv = document.getElementById(`code_description_${rowNumber}`);
            
            if (!codeSelect || !dv93Input) return;
            
            // Update description display
            if (descriptionDiv) {
                if (selectedValue) {
                    const selectedOption = codeSelect.options[codeSelect.selectedIndex];
                    const description = selectedOption ? selectedOption.getAttribute('data-description') : '';
                    descriptionDiv.textContent = description || '';
                    descriptionDiv.style.display = description ? 'block' : 'none';
                } else {
                    descriptionDiv.textContent = '';
                    descriptionDiv.style.display = 'none';
                }
            }
            
            if (selectedValue) {
                const selectedOption = codeSelect.options[codeSelect.selectedIndex];
                const description = selectedOption.getAttribute('data-description');
                
                // Update title in header
                if (delayRowTitle) {
                    delayRowTitle.textContent = `Delay/Diversion Code ${rowNumber}: ${selectedValue}`;
                }
                
                if (description) {
                    dv93Input.value = description;
                }
                
                // Handle sub-codes for code 93 (RA) - get from globalDelayCodes
                if (selectedValue === '93 (RA)') {
                    // Find the code in globalDelayCodes
                    const codeData = globalDelayCodes.find(code => code.code === selectedValue);
                    if (codeData && codeData.sub_codes) {
                        const subCodes = codeData.sub_codes;
                        if (subCodeContainer) {
                            subCodeContainer.style.display = 'block';
                            // Adjust grid layout for sub code
                            const parentGrid = subCodeContainer.closest('[style*="grid-template-columns"]');
                            if (parentGrid && parentGrid.style.gridTemplateColumns.includes('repeat(2')) {
                                // Already in 2-column layout, no change needed
                            }
                        }
                        if (subCodeSelect) {
                            subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                            subCodes.forEach(subCode => {
                                const option = document.createElement('option');
                                option.value = subCode.code;
                                option.textContent = subCode.code + ' - ' + subCode.description;
                                option.setAttribute('data-description', subCode.description);
                                // Check if this sub code was previously selected
                                const currentValue = subCodeSelect.getAttribute('data-selected-value') || '';
                                if (subCode.code === currentValue) {
                                    option.selected = true;
                                }
                                subCodeSelect.appendChild(option);
                            });
                        }
                    } else {
                        if (subCodeContainer) {
                            subCodeContainer.style.display = 'none';
                        }
                        if (subCodeSelect) {
                            subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                        }
                    }
                } else {
                    if (subCodeContainer) {
                        subCodeContainer.style.display = 'none';
                    }
                    if (subCodeSelect) {
                        subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                    }
                }
                
                // Handle remark for code 99 (MX)
                if (selectedValue === '99 (MX)') {
                    if (remarkContainer) {
                        remarkContainer.style.display = 'block';
                    }
                } else {
                    if (remarkContainer) {
                        remarkContainer.style.display = 'none';
                    }
                }
                
                // Check if row is complete and show next row
                checkAndShowNextRow(rowNumber);
            } else {
                // Update title in header
                if (delayRowTitle) {
                    delayRowTitle.textContent = `Delay/Diversion Code ${rowNumber}`;
                }
                
                dv93Input.value = '';
                if (subCodeContainer) {
                    subCodeContainer.style.display = 'none';
                }
                if (subCodeSelect) {
                    subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                }
                if (remarkContainer) {
                    remarkContainer.style.display = 'none';
                }
            }
        }
        
        function updateSubCodeDescription(rowNumber, selectedValue) {
            const subCodeSelect = document.getElementById(`delay_diversion_sub_codes_${rowNumber}`);
            const dv93Input = document.getElementById(`dv93_${rowNumber}`);
            
            if (!subCodeSelect || !dv93Input) return;
            
            if (selectedValue) {
                const selectedOption = subCodeSelect.options[subCodeSelect.selectedIndex];
                const description = selectedOption.getAttribute('data-description');
                
                if (description) {
                    dv93Input.value = description;
                }
            }
        }
        
        function calculateUpliftLbs(input) {
            const form = input.closest('form');
            if (!form) return;
            
            const upliftFuelInput = input;
            const upliftLbsInput = form.querySelector('input[name="uplft_lbs"]');
            
            if (upliftFuelInput && upliftLbsInput) {
                const fuelLiters = parseFloat(upliftFuelInput.value) || 0;
                // Convert liters to pounds: 1 L jet fuel ≈ 0.8 kg, 1 kg = 2.20462 lbs
                // So: 1 L = 0.8 × 2.20462 = 1.763696 lbs
                const fuelLbs = Math.round(fuelLiters * 0.76 * 2.20462);
                upliftLbsInput.value = fuelLbs;
            }
        }
        
        // Function to combine date and time (HHMM) into datetime string
        function combineDateTime(dateFieldId, timeFieldId, hiddenFieldId) {
            const dateField = document.getElementById(dateFieldId);
            const timeField = document.getElementById(timeFieldId);
            const hiddenField = document.getElementById(hiddenFieldId);
            
            if (!dateField || !timeField || !hiddenField) return;
            
            const dateValue = dateField.value;
            const timeValue = timeField.value.trim();
            
            if (dateValue && timeValue && timeValue.length === 4) {
                // Extract hours and minutes from HHMM format
                const hours = timeValue.substring(0, 2);
                const minutes = timeValue.substring(2, 4);
                
                // Validate hours (00-23) and minutes (00-59)
                const hoursInt = parseInt(hours);
                const minutesInt = parseInt(minutes);
                
                if (hoursInt >= 0 && hoursInt <= 23 && minutesInt >= 0 && minutesInt <= 59) {
                    // Combine date and time into datetime format: YYYY-MM-DD HH:MM:SS
                    const datetime = dateValue + ' ' + hours + ':' + minutes + ':00';
                    hiddenField.value = datetime;
                } else {
                    hiddenField.value = '';
                }
            } else {
                hiddenField.value = '';
            }
        }
        
        // Initialize datetime field handlers
        function initializeDateTimeHandlers() {
            const datetimeFields = [
                { date: 'TaskStart_date', time: 'TaskStart_time', hidden: 'TaskStart' },
                { date: 'TaskEnd_date', time: 'TaskEnd_time', hidden: 'TaskEnd' },
                { date: 'actual_out_utc_date', time: 'actual_out_utc_time', hidden: 'actual_out_utc' },
                { date: 'actual_in_utc_date', time: 'actual_in_utc_time', hidden: 'actual_in_utc' }
            ];
            
            datetimeFields.forEach(field => {
                const dateField = document.getElementById(field.date);
                const timeField = document.getElementById(field.time);
                
                if (dateField && timeField) {
                    // Update hidden field when date or time changes
                    dateField.addEventListener('change', () => combineDateTime(field.date, field.time, field.hidden));
                    timeField.addEventListener('input', () => combineDateTime(field.date, field.time, field.hidden));
                    timeField.addEventListener('blur', () => combineDateTime(field.date, field.time, field.hidden));
                    
                    // Initialize on page load
                    combineDateTime(field.date, field.time, field.hidden);
                }
            });
        }
        
        function saveFlightData(event) {
            event.preventDefault();
            
            if (!currentEditFlightId) return;
            
            const form = document.getElementById('flightEditForm');
            const messagesDiv = document.getElementById('flightEditMessages');
            const saveBtn = document.getElementById('saveFlightBtn');
            
            if (!form || !messagesDiv || !saveBtn) return;
            
            // Update all datetime fields before submission
            combineDateTime('TaskStart_date', 'TaskStart_time', 'TaskStart');
            combineDateTime('TaskEnd_date', 'TaskEnd_time', 'TaskEnd');
            combineDateTime('actual_out_utc_date', 'actual_out_utc_time', 'actual_out_utc');
            combineDateTime('actual_in_utc_date', 'actual_in_utc_time', 'actual_in_utc');
            
            // Convert UTC times back to Tehran timezone before saving
            // (since database stores times in Local Tehran/IRAN timezone)
            // Tehran timezone offset: UTC+3:30
            // Example: 09:15 UTC = 12:45 Tehran (09:15 + 3:30 = 12:45)
            const TEHRAN_OFFSET_HOURS = 3;
            const TEHRAN_OFFSET_MINUTES = 30;
            
            function convertUTCToTehran(dateTimeStr) {
                if (!dateTimeStr) return dateTimeStr;
                try {
                    // Parse datetime string (format: YYYY-MM-DD HH:MM:SS)
                    const [datePart, timePart] = dateTimeStr.split(' ');
                    if (!datePart || !timePart) return dateTimeStr;
                    
                    const [year, month, day] = datePart.split('-').map(Number);
                    const [hours, minutes, seconds = 0] = timePart.split(':').map(Number);
                    
                    // Add Tehran offset
                    let tehranHours = hours + TEHRAN_OFFSET_HOURS;
                    let tehranMinutes = minutes + TEHRAN_OFFSET_MINUTES;
                    let tehranDay = day;
                    let tehranMonth = month;
                    let tehranYear = year;
                    
                    // Handle minute overflow
                    if (tehranMinutes >= 60) {
                        tehranMinutes -= 60;
                        tehranHours += 1;
                    }
                    
                    // Handle hour overflow (crossing midnight)
                    if (tehranHours >= 24) {
                        tehranHours -= 24;
                        tehranDay += 1;
                        
                        // Handle day overflow
                        const daysInMonth = new Date(tehranYear, tehranMonth, 0).getDate();
                        if (tehranDay > daysInMonth) {
                            tehranDay = 1;
                            tehranMonth += 1;
                            if (tehranMonth > 12) {
                                tehranMonth = 1;
                                tehranYear += 1;
                            }
                        }
                    }
                    
                    return `${tehranYear}-${String(tehranMonth).padStart(2, '0')}-${String(tehranDay).padStart(2, '0')} ${String(tehranHours).padStart(2, '0')}:${String(tehranMinutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                } catch (e) {
                    return dateTimeStr;
                }
            }
            
            function convertUTCTimeToTehran(timeStr, dateStr) {
                if (!timeStr || timeStr.length !== 4) return timeStr;
                if (!dateStr) return timeStr;
                
                try {
                    const hours = parseInt(timeStr.substring(0, 2));
                    const minutes = parseInt(timeStr.substring(2, 4));
                    
                    // Add Tehran offset
                    let tehranHours = hours + TEHRAN_OFFSET_HOURS;
                    let tehranMinutes = minutes + TEHRAN_OFFSET_MINUTES;
                    
                    // Handle minute overflow
                    if (tehranMinutes >= 60) {
                        tehranMinutes -= 60;
                        tehranHours += 1;
                    }
                    
                    // Handle hour overflow (crossing midnight)
                    if (tehranHours >= 24) {
                        tehranHours -= 24;
                    }
                    
                    return `${String(tehranHours).padStart(2, '0')}${String(tehranMinutes).padStart(2, '0')}`;
                } catch (e) {
                    return timeStr;
                }
            }
            
            // Get form data
            const formData = new FormData(form);
            const data = {
                flight_id: currentEditFlightId
            };
            
            // If times are in UTC, convert them back to Tehran before saving
            if (timesInUTC) {
                const taskStartDate = document.getElementById('TaskStart_date')?.value;
                const taskStartTime = document.getElementById('TaskStart_time')?.value;
                const taskStartHidden = document.getElementById('TaskStart')?.value;
                
                // Convert datetime fields back to Tehran
                if (taskStartHidden) {
                    const tehranDateTime = convertUTCToTehran(taskStartHidden);
                    data.TaskStart = tehranDateTime;
                }
                
                const taskEndHidden = document.getElementById('TaskEnd')?.value;
                if (taskEndHidden) {
                    const tehranDateTime = convertUTCToTehran(taskEndHidden);
                    data.TaskEnd = tehranDateTime;
                }
                
                // Convert flight times (HHMM format) back to Tehran
                const referenceDate = taskStartDate || new Date().toISOString().split('T')[0];
                const flightTimeFields = [
                    'boarding', 'gate_closed', 'ready', 'start', 'off_block', 
                    'taxi', 'return_to_ramp', 'takeoff', 'landed', 'on_block'
                ];
                
                flightTimeFields.forEach(fieldName => {
                    const field = document.querySelector(`input[name="${fieldName}"]`);
                    if (field && field.value && field.value.length === 4) {
                        const tehranTime = convertUTCTimeToTehran(field.value, referenceDate);
                        data[fieldName] = tehranTime;
                    }
                });
                
                // Reset flag after conversion
                timesInUTC = false;
            }
            
            // Collect all form fields (skip date and time fields, use hidden datetime fields)
            formData.forEach((value, key) => {
                // Skip date and time fields, they are combined into hidden datetime fields
                if (key.endsWith('_date') || key.endsWith('_time')) {
                    return;
                }
                // Skip fields we've already processed
                if (data.hasOwnProperty(key)) {
                    return;
                }
                if (value !== '' && value !== null) {
                    data[key] = value;
                }
            });
            
            // Disable save button
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            messagesDiv.innerHTML = '';
            
            // Send update request
            fetch('api/update_flight.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    messagesDiv.innerHTML = '<div class="flight-edit-success">Flight updated successfully!</div>';
                    // Close modal after 1.5 seconds and reload page
                    setTimeout(() => {
                        closeFlightEditModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    messagesDiv.innerHTML = `<div class="flight-edit-error">${escapeHtml(result.error || 'Failed to update flight')}</div>`;
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                }
            })
            .catch(error => {
                console.error('Error saving flight:', error);
                messagesDiv.innerHTML = '<div class="flight-edit-error">Error saving flight data. Please try again.</div>';
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            });
        }
        
        function closeFlightEditModal() {
            const modal = document.getElementById('flightEditModal');
            if (modal) {
                modal.classList.remove('show');
            }
            currentEditFlightId = null;
        }
        
        // Global flag to track current timezone state: false = Tehran, true = UTC
        let timesInUTC = false;
        
        // Toggle between Tehran and UTC timezone
        function toggleTimeZone() {
            if (timesInUTC) {
                // Currently in UTC, convert back to Tehran
                convertTimesToTehran();
            } else {
                // Currently in Tehran, convert to UTC
                convertTimesToUTC();
            }
        }
        
        // Convert times from Tehran/IRAN timezone to UTC
        function convertTimesToUTC() {
            // Tehran timezone offset: UTC+3:30 (IRST)
            // Example: 12:45 Tehran = 09:15 UTC (12:45 - 3:30 = 09:15)
            const TEHRAN_OFFSET_HOURS = 3;
            const TEHRAN_OFFSET_MINUTES = 30;
            const TEHRAN_OFFSET_TOTAL_MINUTES = TEHRAN_OFFSET_HOURS * 60 + TEHRAN_OFFSET_MINUTES; // 210 minutes
            
            // Set flag to indicate times are now in UTC
            timesInUTC = true;
            
            // Update button text
            const btn = document.getElementById('convertToUTCBtn');
            const btnText = document.getElementById('convertToUTCBtnText');
            if (btn) btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            if (btn) btn.classList.add('bg-green-600', 'hover:bg-green-700');
            if (btnText) btnText.textContent = 'Tehran Local';
            
            // Helper function to convert HHMM time string to minutes since midnight
            function timeToMinutes(timeStr) {
                if (!timeStr || timeStr.length !== 4) return null;
                const hours = parseInt(timeStr.substring(0, 2));
                const minutes = parseInt(timeStr.substring(2, 4));
                if (isNaN(hours) || isNaN(minutes)) return null;
                return hours * 60 + minutes;
            }
            
            // Helper function to convert date and time from Tehran to UTC
            function convertDateTimeToUTC(dateStr, timeStr) {
                if (!dateStr || !timeStr || timeStr.length !== 4) return { date: dateStr, time: timeStr };
                
                const timeMinutes = timeToMinutes(timeStr);
                if (timeMinutes === null) return { date: dateStr, time: timeStr };
                
                // Parse date components
                const [year, month, day] = dateStr.split('-').map(Number);
                const hours = Math.floor(timeMinutes / 60);
                const minutes = timeMinutes % 60;
                
                // Create a date string in ISO format (this will be treated as local time)
                // We need to manually subtract the offset
                let utcHours = hours - TEHRAN_OFFSET_HOURS;
                let utcMinutes = minutes - TEHRAN_OFFSET_MINUTES;
                let utcDay = day;
                let utcMonth = month;
                let utcYear = year;
                
                // Handle minute underflow
                if (utcMinutes < 0) {
                    utcMinutes += 60;
                    utcHours -= 1;
                }
                
                // Handle hour underflow (crossing midnight)
                if (utcHours < 0) {
                    utcHours += 24;
                    utcDay -= 1;
                    
                    // Handle day underflow
                    if (utcDay < 1) {
                        utcMonth -= 1;
                        if (utcMonth < 1) {
                            utcMonth = 12;
                            utcYear -= 1;
                        }
                        // Get days in previous month
                        const daysInMonth = new Date(utcYear, utcMonth, 0).getDate();
                        utcDay = daysInMonth;
                    }
                }
                
                // Handle hour overflow (shouldn't happen, but just in case)
                if (utcHours >= 24) {
                    utcHours -= 24;
                    utcDay += 1;
                    
                    // Handle day overflow
                    const daysInMonth = new Date(utcYear, utcMonth, 0).getDate();
                    if (utcDay > daysInMonth) {
                        utcDay = 1;
                        utcMonth += 1;
                        if (utcMonth > 12) {
                            utcMonth = 1;
                            utcYear += 1;
                        }
                    }
                }
                
                const utcDateStr = `${utcYear}-${String(utcMonth).padStart(2, '0')}-${String(utcDay).padStart(2, '0')}`;
                const utcTimeStr = `${String(utcHours).padStart(2, '0')}${String(utcMinutes).padStart(2, '0')}`;
                
                return { date: utcDateStr, time: utcTimeStr };
            }
            
            // Helper function to convert HHMM time only (for flight times)
            function convertTimeToUTC(timeStr, dateStr) {
                if (!timeStr || timeStr.length !== 4) return timeStr;
                
                const timeMinutes = timeToMinutes(timeStr);
                if (timeMinutes === null) return timeStr;
                
                const hours = Math.floor(timeMinutes / 60);
                const minutes = timeMinutes % 60;
                
                // Subtract Tehran offset
                let utcHours = hours - TEHRAN_OFFSET_HOURS;
                let utcMinutes = minutes - TEHRAN_OFFSET_MINUTES;
                
                // Handle minute underflow
                if (utcMinutes < 0) {
                    utcMinutes += 60;
                    utcHours -= 1;
                }
                
                // Handle hour underflow (crossing midnight)
                if (utcHours < 0) {
                    utcHours += 24;
                }
                
                // Handle hour overflow
                if (utcHours >= 24) {
                    utcHours -= 24;
                }
                
                return `${String(utcHours).padStart(2, '0')}${String(utcMinutes).padStart(2, '0')}`;
            }
            
            // Convert TaskStart
            const taskStartDate = document.getElementById('TaskStart_date')?.value;
            const taskStartTime = document.getElementById('TaskStart_time')?.value;
            if (taskStartDate && taskStartTime) {
                const utc = convertDateTimeToUTC(taskStartDate, taskStartTime);
                const dateField = document.getElementById('TaskStart_date');
                const timeField = document.getElementById('TaskStart_time');
                if (dateField) dateField.value = utc.date;
                if (timeField) timeField.value = utc.time;
                // Update hidden field
                if (typeof combineDateTime === 'function') {
                    combineDateTime('TaskStart_date', 'TaskStart_time', 'TaskStart');
                }
            }
            
            // Convert TaskEnd
            const taskEndDate = document.getElementById('TaskEnd_date')?.value;
            const taskEndTime = document.getElementById('TaskEnd_time')?.value;
            if (taskEndDate && taskEndTime) {
                const utc = convertDateTimeToUTC(taskEndDate, taskEndTime);
                const dateField = document.getElementById('TaskEnd_date');
                const timeField = document.getElementById('TaskEnd_time');
                if (dateField) dateField.value = utc.date;
                if (timeField) timeField.value = utc.time;
                // Update hidden field
                if (typeof combineDateTime === 'function') {
                    combineDateTime('TaskEnd_date', 'TaskEnd_time', 'TaskEnd');
                }
            }
            
            // Convert Flight Times (HHMM format)
            // Use TaskStart date as reference date for flight times
            const referenceDate = taskStartDate || new Date().toISOString().split('T')[0];
            
            const flightTimeFields = [
                'boarding', 'gate_closed', 'ready', 'start', 'off_block', 
                'taxi', 'return_to_ramp', 'takeoff', 'landed', 'on_block'
            ];
            
            flightTimeFields.forEach(fieldName => {
                const field = document.querySelector(`input[name="${fieldName}"]`);
                if (field && field.value && field.value.length === 4) {
                    const utcTime = convertTimeToUTC(field.value, referenceDate);
                    field.value = utcTime;
                }
            });
            
        }
        
        // Convert times from UTC timezone back to Tehran/IRAN
        function convertTimesToTehran() {
            // Tehran timezone offset: UTC+3:30 (IRST)
            // Example: 09:15 UTC = 12:45 Tehran (09:15 + 3:30 = 12:45)
            const TEHRAN_OFFSET_HOURS = 3;
            const TEHRAN_OFFSET_MINUTES = 30;
            
            // Set flag to indicate times are now in Tehran
            timesInUTC = false;
            
            // Helper function to convert HHMM time string to minutes since midnight
            function timeToMinutes(timeStr) {
                if (!timeStr || timeStr.length !== 4) return null;
                const hours = parseInt(timeStr.substring(0, 2));
                const minutes = parseInt(timeStr.substring(2, 4));
                if (isNaN(hours) || isNaN(minutes)) return null;
                return hours * 60 + minutes;
            }
            
            // Helper function to convert date and time from UTC to Tehran
            function convertDateTimeToTehran(dateStr, timeStr) {
                if (!dateStr || !timeStr || timeStr.length !== 4) return { date: dateStr, time: timeStr };
                
                const timeMinutes = timeToMinutes(timeStr);
                if (timeMinutes === null) return { date: dateStr, time: timeStr };
                
                // Parse date components
                const [year, month, day] = dateStr.split('-').map(Number);
                const hours = Math.floor(timeMinutes / 60);
                const minutes = timeMinutes % 60;
                
                // Add Tehran offset
                let tehranHours = hours + TEHRAN_OFFSET_HOURS;
                let tehranMinutes = minutes + TEHRAN_OFFSET_MINUTES;
                let tehranDay = day;
                let tehranMonth = month;
                let tehranYear = year;
                
                // Handle minute overflow
                if (tehranMinutes >= 60) {
                    tehranMinutes -= 60;
                    tehranHours += 1;
                }
                
                // Handle hour overflow (crossing midnight)
                if (tehranHours >= 24) {
                    tehranHours -= 24;
                    tehranDay += 1;
                    
                    // Handle day overflow
                    const daysInMonth = new Date(tehranYear, tehranMonth, 0).getDate();
                    if (tehranDay > daysInMonth) {
                        tehranDay = 1;
                        tehranMonth += 1;
                        if (tehranMonth > 12) {
                            tehranMonth = 1;
                            tehranYear += 1;
                        }
                    }
                }
                
                const tehranDateStr = `${tehranYear}-${String(tehranMonth).padStart(2, '0')}-${String(tehranDay).padStart(2, '0')}`;
                const tehranTimeStr = `${String(tehranHours).padStart(2, '0')}${String(tehranMinutes).padStart(2, '0')}`;
                
                return { date: tehranDateStr, time: tehranTimeStr };
            }
            
            // Helper function to convert HHMM time only (for flight times)
            function convertTimeToTehran(timeStr, dateStr) {
                if (!timeStr || timeStr.length !== 4) return timeStr;
                
                const timeMinutes = timeToMinutes(timeStr);
                if (timeMinutes === null) return timeStr;
                
                const hours = Math.floor(timeMinutes / 60);
                const minutes = timeMinutes % 60;
                
                // Add Tehran offset
                let tehranHours = hours + TEHRAN_OFFSET_HOURS;
                let tehranMinutes = minutes + TEHRAN_OFFSET_MINUTES;
                
                // Handle minute overflow
                if (tehranMinutes >= 60) {
                    tehranMinutes -= 60;
                    tehranHours += 1;
                }
                
                // Handle hour overflow (crossing midnight)
                if (tehranHours >= 24) {
                    tehranHours -= 24;
                }
                
                return `${String(tehranHours).padStart(2, '0')}${String(tehranMinutes).padStart(2, '0')}`;
            }
            
            // Convert TaskStart
            const taskStartDate = document.getElementById('TaskStart_date')?.value;
            const taskStartTime = document.getElementById('TaskStart_time')?.value;
            if (taskStartDate && taskStartTime) {
                const tehran = convertDateTimeToTehran(taskStartDate, taskStartTime);
                const dateField = document.getElementById('TaskStart_date');
                const timeField = document.getElementById('TaskStart_time');
                if (dateField) dateField.value = tehran.date;
                if (timeField) timeField.value = tehran.time;
                // Update hidden field
                if (typeof combineDateTime === 'function') {
                    combineDateTime('TaskStart_date', 'TaskStart_time', 'TaskStart');
                }
            }
            
            // Convert TaskEnd
            const taskEndDate = document.getElementById('TaskEnd_date')?.value;
            const taskEndTime = document.getElementById('TaskEnd_time')?.value;
            if (taskEndDate && taskEndTime) {
                const tehran = convertDateTimeToTehran(taskEndDate, taskEndTime);
                const dateField = document.getElementById('TaskEnd_date');
                const timeField = document.getElementById('TaskEnd_time');
                if (dateField) dateField.value = tehran.date;
                if (timeField) timeField.value = tehran.time;
                // Update hidden field
                if (typeof combineDateTime === 'function') {
                    combineDateTime('TaskEnd_date', 'TaskEnd_time', 'TaskEnd');
                }
            }
            
            // Convert Flight Times (HHMM format)
            // Use TaskStart date as reference date for flight times
            const referenceDate = taskStartDate || new Date().toISOString().split('T')[0];
            
            const flightTimeFields = [
                'boarding', 'gate_closed', 'ready', 'start', 'off_block', 
                'taxi', 'return_to_ramp', 'takeoff', 'landed', 'on_block'
            ];
            
            flightTimeFields.forEach(fieldName => {
                const field = document.querySelector(`input[name="${fieldName}"]`);
                if (field && field.value && field.value.length === 4) {
                    const tehranTime = convertTimeToTehran(field.value, referenceDate);
                    field.value = tehranTime;
                }
            });
            
            // Update button text
            const btn = document.getElementById('convertToUTCBtn');
            const btnText = document.getElementById('convertToUTCBtnText');
            if (btn) btn.classList.remove('bg-green-600', 'hover:bg-green-700');
            if (btn) btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            if (btnText) btnText.textContent = 'Convert To UTC';
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('flightEditModal');
            if (modal && e.target === modal) {
                closeFlightEditModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('flightEditModal');
                if (modal && modal.classList.contains('show')) {
                    closeFlightEditModal();
                }
            }
        });
    </script>
    
    <!-- ODB Notifications Modal -->
    <?php include '../includes/odb_modal.php'; ?>
        </div>
    </div>
</body>
</html>

