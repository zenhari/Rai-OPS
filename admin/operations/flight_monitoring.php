<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/flight_monitoring.php');

$user = getCurrentUser();

// Get date parameter (default to today)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_user = isset($_GET['user']) ? intval($_GET['user']) : null;
$days_count = isset($_GET['days']) ? max(1, min(30, intval($_GET['days']))) : 1; // Default 1 day, max 30 days

// Calculate date range
$start_date = $selected_date;
$end_date = date('Y-m-d', strtotime($selected_date . ' +' . ($days_count - 1) . ' days'));

// Get flights for all days in range
$all_flights = [];
$all_aircraft = [];
for ($i = 0; $i < $days_count; $i++) {
    $current_date = date('Y-m-d', strtotime($selected_date . ' +' . $i . ' days'));
    $day_flights = getFlightsForMonitoring($current_date, $selected_user);
    $day_aircraft = getAircraftForTimeline($current_date);
    
    // Add date to each flight for grouping
    foreach ($day_flights as &$flight) {
        $flight['display_date'] = $current_date;
    }
    unset($flight);
    
    $all_flights = array_merge($all_flights, $day_flights);
    $all_aircraft = array_merge($all_aircraft, $day_aircraft);
}

// Get unique aircraft list
$aircraft_list = [];
$aircraft_seen = [];
foreach ($all_aircraft as $aircraft) {
    $rego = $aircraft['registration'] ?? 'Unknown';
    if (!isset($aircraft_seen[$rego])) {
        $aircraft_list[] = $aircraft;
        $aircraft_seen[$rego] = true;
    }
}

// Use flights for stats (only selected date)
$flights = getFlightsForMonitoring($selected_date, $selected_user);
$stats = getFlightMonitoringStats($selected_date);

// Get all users for filter dropdown
$all_users = getAllUsers();

// Group flights by aircraft and date
$flights_by_aircraft = [];
foreach ($all_flights as $flight) {
    $aircraft_rego = $flight['aircraft_rego'] ?? 'Unknown';
    $flight_date = $flight['display_date'] ?? $selected_date;
    if (!isset($flights_by_aircraft[$aircraft_rego])) {
        $flights_by_aircraft[$aircraft_rego] = [];
    }
    if (!isset($flights_by_aircraft[$aircraft_rego][$flight_date])) {
        $flights_by_aircraft[$aircraft_rego][$flight_date] = [];
    }
    $flights_by_aircraft[$aircraft_rego][$flight_date][] = $flight;
}

// Calculate timeline range based on flights (like dashboard.php)
$timelineStart = null;
$timelineEnd = null;
$timelineHours = [];

// Find earliest start and latest end times (considering delays)
foreach ($all_flights as $flight) {
    if (!empty($flight['TaskStart'])) {
        try {
            $taskStartDate = new DateTime($flight['TaskStart']);
            $taskStartDateOnly = $taskStartDate->format('Y-m-d');
            
            if ($taskStartDateOnly === $selected_date) {
                $startTime = $taskStartDate;
            } else {
                $timeOnly = $taskStartDate->format('H:i:s');
                $startTime = new DateTime($selected_date . ' ' . $timeOnly, $taskStartDate->getTimezone());
            }
            
            // Use TaskStart (before delay) for timeline start
            if (!$timelineStart || $startTime < $timelineStart) {
                $timelineStart = clone $startTime;
            }
        } catch (Exception $e) {
            // Skip invalid dates
        }
    }
    
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
    
    if (!empty($flight['TaskEnd'])) {
        try {
            $taskEndDate = new DateTime($flight['TaskEnd']);
            $taskEndDateOnly = $taskEndDate->format('Y-m-d');
            
            if ($taskEndDateOnly === $selected_date) {
                $endTime = $taskEndDate;
            } else {
                $timeOnly = $taskEndDate->format('H:i:s');
                $endTime = new DateTime($selected_date . ' ' . $timeOnly, $taskEndDate->getTimezone());
            }
            
            // Use TaskEnd + delay for timeline end
            if ($delay_minutes > 0) {
                $endTime->modify('+' . $delay_minutes . ' minutes');
            }
            
            if (!$timelineEnd || $endTime > $timelineEnd) {
                $timelineEnd = clone $endTime;
            }
        } catch (Exception $e) {
            // Skip invalid dates
        }
    }
}

// Generate timeline hours for full day (00:00 to 23:00) - 24 hours
// Timeline starts from first day 00:00:00
$timelineStart = new DateTime($start_date . ' 00:00:00');
// Timeline ends at last day 23:59:59
$timelineEnd = new DateTime($end_date . ' 23:59:59');

// Generate timeline for multiple days
$timelineDays = [];
for ($i = 0; $i < $days_count; $i++) {
    $day_date = date('Y-m-d', strtotime($selected_date . ' +' . $i . ' days'));
    $timelineDays[] = [
        'date' => $day_date,
        'display' => date('M j, Y', strtotime($day_date)),
        'short' => date('M j', strtotime($day_date))
    ];
}

$timelineHours = [];
// Generate hours for full day (24 hours)
for ($i = 0; $i < 24; $i++) {
    $timelineHours[] = sprintf('%02d:00', $i);
}

$totalHours = 24 * $days_count; // Total hours across all days

// Calculate maximum flights per day for grid lines
// We need to find the maximum number of flights in a single day across all aircraft and dates
$maxFlightsPerDay = 0;
foreach ($flights_by_aircraft as $aircraft_rego => $aircraft_flights_by_date) {
    foreach ($aircraft_flights_by_date as $date => $date_flights) {
        $flightCount = count($date_flights);
        if ($flightCount > $maxFlightsPerDay) {
            $maxFlightsPerDay = $flightCount;
        }
    }
}

// Ensure minimum of 1 line even if no flights
if ($maxFlightsPerDay < 1) {
    $maxFlightsPerDay = 1;
}

// Calculate current time position for timeline indicator
$currentTimePosition = null;
$currentTimeDisplay = null;
try {
    // Get current time in Tehran timezone
    $tehranTimezone = new DateTimeZone('Asia/Tehran');
    $currentTime = new DateTime('now', $tehranTimezone);
    $currentTimeDisplay = $currentTime->format('H:i');
    
    // Check if current date is within the selected date range
    $currentDate = $currentTime->format('Y-m-d');
    $currentDateObj = new DateTime($currentDate);
    $startDateObj = new DateTime($start_date);
    $endDateObj = new DateTime($end_date);
    
    if ($currentDateObj >= $startDateObj && $currentDateObj <= $endDateObj && $timelineStart && $timelineEnd) {
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

// Sort flights within each aircraft group by date and TaskStart (not TaskStart + delay)
// This ensures bars are positioned correctly based on scheduled start time
foreach ($flights_by_aircraft as $aircraft_rego => $aircraft_flights_by_date) {
    foreach ($aircraft_flights_by_date as $date => $date_flights) {
        usort($flights_by_aircraft[$aircraft_rego][$date], function($a, $b) use ($date) {
            $a_start = 0;
            $b_start = 0;
            
            if (!empty($a['TaskStart'])) {
                try {
                    $taskStartDate = new DateTime($a['TaskStart']);
                    $timeOnly = $taskStartDate->format('H:i:s');
                    $dayStartDate = new DateTime($date . ' ' . $timeOnly);
                    $a_start = $dayStartDate->getTimestamp();
                } catch (Exception $e) {
                    // Keep 0
                }
            }
            
            if (!empty($b['TaskStart'])) {
                try {
                    $taskStartDate = new DateTime($b['TaskStart']);
                    $timeOnly = $taskStartDate->format('H:i:s');
                    $dayStartDate = new DateTime($date . ' ' . $timeOnly);
                    $b_start = $dayStartDate->getTimestamp();
                } catch (Exception $e) {
                    // Keep 0
                }
            }
            
            return $a_start <=> $b_start;
        });
    }
}

// Timeline hours are now calculated dynamically above
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Monitoring - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
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
        
        .timeline-row {
            position: relative;
            overflow: hidden;
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
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .flight-bar:hover {
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
        
        .flight-edit-form-section {
            margin-bottom: 24px;
            padding: 0;
            background: transparent;
            border-radius: 0;
            border: none;
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
        
        .dark .flight-edit-form-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-form-input {
                background: #0f172a;
                border-color: #334155;
                color: #f1f5f9;
            }
            
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
        
        .dark .flight-edit-form-select:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-form-select {
                background: #0f172a;
                border-color: #334155;
                color: #f1f5f9;
            }
            
            .flight-edit-form-select:focus {
                border-color: #60a5fa;
                box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.15);
            }
        }
        
        .calculated-field {
            background: #d1fae5 !important;
            color: #000000 !important;
        }
        
        .dark .calculated-field {
            background: #065f46 !important;
            color: #d1fae5 !important;
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
        
        .dark .flight-edit-btn-cancel:hover {
            background: #334155;
            border-color: #475569;
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
        
        .flight-edit-btn-save {
            background: #3b82f6;
            color: white;
        }
        
        .flight-edit-btn-save:hover {
            background: #2563eb;
        }
        
        .flight-edit-btn-save:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }
        
        .dark .flight-edit-btn-save:disabled {
            background: #475569;
        }
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-btn-save:disabled {
                background: #475569;
            }
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
        
        @media (prefers-color-scheme: dark) {
            .flight-edit-delay-row.delay-row-enabled {
                border-left-color: #60a5fa;
            }
        }
        
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
        
        @media (prefers-color-scheme: dark) {
            .delay-row-header {
                border-bottom-color: #374151;
            }
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
        
        @media (prefers-color-scheme: dark) {
            .delay-row-number {
                background: #60a5fa;
            }
        }
        
        .delay-row-title {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        
        .dark .delay-row-title {
            color: #d1d5db;
        }
        
        @media (prefers-color-scheme: dark) {
            .delay-row-title {
                color: #d1d5db;
            }
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
                                Flight Monitoring
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Real-time flight operations timeline
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Date Picker -->
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date:</label>
                                <input type="date" id="datePicker" value="<?php echo $selected_date; ?>" 
                                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <!-- Days Count -->
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Days:</label>
                                <input type="number" id="daysCount" value="<?php echo $days_count; ?>" min="1" max="30" 
                                       class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <!-- User Filter -->
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">User:</label>
                                <select id="userFilter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Users</option>
                                    <?php foreach ($all_users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo ($selected_user == $u['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Refresh Button -->
                            <button onclick="refreshData()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-sync-alt mr-2"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>

                <!-- Statistics Panel -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-plane text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Flights</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_flights']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-plane-departure text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Departed</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['departed']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-plane-arrival text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Arrived</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['arrived']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Canceled</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['canceled']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock text-orange-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Delay</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_delay_hours']; ?>h <?php echo $stats['total_delay_minutes']; ?>m</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users text-purple-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Passengers</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_pax']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-clock mr-2"></i>Flight Timeline
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <?php 
                            if ($days_count == 1) {
                                echo date('l, F j, Y', strtotime($selected_date));
                            } else {
                                echo date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)) . ' (' . $days_count . ' days)';
                            }
                            ?>
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
                                        <?php foreach ($timelineDays as $dayIndex => $day): ?>
                                            <div class="flex flex-col" style="width: <?php echo 24 * 120; ?>px;">
                                                <!-- Day Header -->
                                                <div class="text-center text-sm font-semibold text-gray-700 dark:text-gray-300 border-b-2 border-gray-300 dark:border-gray-600 pb-1 mb-1">
                                                    <?php echo htmlspecialchars($day['short']); ?>
                                                </div>
                                                <!-- Hours for this day -->
                                                <div class="flex">
                                                    <?php foreach ($timelineHours as $hour): ?>
                                                        <div class="timeline-hour text-center text-xs text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-600 relative">
                                                            <?php echo $hour; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Current Time Indicator (Yellow Line) - only for today -->
                                                    <?php if ($currentTimePosition !== null && $day['date'] === date('Y-m-d')): ?>
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
                                        <?php endforeach; ?>
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
                                
                                foreach ($aircraft_list as $aircraft): ?>
                                    <div class="flex mb-2" style="min-width: <?php echo ($totalHours * 120) + 128; ?>px;">
                                        <!-- Aircraft Label -->
                                        <div class="w-32 flex-shrink-0 flex items-center">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($aircraft['registration']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Timeline Row -->
                                        <div class="timeline-row flex-1 relative bg-gray-50 dark:bg-gray-700 rounded" style="min-height: <?php echo max(60, ($maxFlightsPerDay > 0 ? (6 + (32 + 2 + 14 + 2) + (($maxFlightsPerDay - 1) * 50) + 30 + 8) : 60)); ?>px; width: <?php echo $totalHours * 120; ?>px;">
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
                                            
                                            <!-- Current Time Indicator (Yellow Line) - for each aircraft row -->
                                            <?php if ($currentTimePosition !== null): ?>
                                                <div class="current-time-indicator" style="left: <?php echo number_format($currentTimePosition, 2); ?>%;">
                                                    <div class="current-time-line"></div>
                                                </div>
                                            <?php endif; ?>
                                        
                                        <?php if (isset($flights_by_aircraft[$aircraft['registration']])): ?>
                                            <?php 
                                            // Group flights by date and sort each day's flights by time
                                            // This ensures each day starts from the top (index 0)
                                            $flights_by_date_sorted = [];
                                            foreach ($flights_by_aircraft[$aircraft['registration']] as $date => $date_flights) {
                                                // Sort flights within each date by TaskStart time
                                                usort($date_flights, function($a, $b) {
                                                    $a_start = 0;
                                                    $b_start = 0;
                                                    if (!empty($a['TaskStart'])) {
                                                        try {
                                                            $taskStartDate = new DateTime($a['TaskStart']);
                                                            $a_start = $taskStartDate->getTimestamp();
                                                        } catch (Exception $e) {}
                                                    }
                                                    if (!empty($b['TaskStart'])) {
                                                        try {
                                                            $taskStartDate = new DateTime($b['TaskStart']);
                                                            $b_start = $taskStartDate->getTimestamp();
                                                        } catch (Exception $e) {}
                                                    }
                                                    return $a_start <=> $b_start;
                                                });
                                                $flights_by_date_sorted[$date] = $date_flights;
                                            }
                                            
                                            // Sort dates chronologically
                                            ksort($flights_by_date_sorted);
                                            ?>
                                            
                                            <?php 
                                            // Loop through each date, then through flights in that date
                                            // This ensures each day's flights start from index 0 (top)
                                            foreach ($flights_by_date_sorted as $flight_date => $date_flights): ?>
                                                <?php foreach ($date_flights as $index => $flight): ?>
                                                    <?php
                                                    // $index is now the index within this specific date (0, 1, 2, ...)
                                                    // This ensures each day starts from the top grid line
                                                    ?>
                                                <?php
                                                // Calculate timeline positions
                                                // Use dynamic timeline range (timelineStart to timelineEnd)
                                                $timeline_duration = ($timelineEnd->getTimestamp() - $timelineStart->getTimestamp()); // Total duration in seconds
                                                
                                                // Parse TaskStart and TaskEnd
                                                // TaskStart and TaskEnd contain full datetime (e.g., 2025-11-08 06:57:00 or 2025-11-07 15:40:00)
                                                // We need to use the date from TaskStart/TaskEnd if it matches selected_date, 
                                                // or use selected_date if the date in TaskStart/TaskEnd is different
                                                $task_start_time = '';
                                                $task_end_time = '';
                                                $start_timestamp = 0;
                                                $end_timestamp = 0;
                                                
                                                if (!empty($flight['TaskStart'])) {
                                                    try {
                                                        // Parse TaskStart as datetime (contains full date and time)
                                                        $taskStartDate = new DateTime($flight['TaskStart']);
                                                        $task_start_time = $taskStartDate->format('H:i');
                                                        
                                                        // Get the date from TaskStart
                                                        $taskStartDateOnly = $taskStartDate->format('Y-m-d');
                                                        
                                                        // If TaskStart date matches selected_date, use TaskStart as-is
                                                        // Otherwise, extract time and combine with selected_date
                                                        if ($taskStartDateOnly === $selected_date) {
                                                            // Use TaskStart timestamp directly
                                                        $start_timestamp = $taskStartDate->getTimestamp();
                                                        } else {
                                                            // Extract time component and combine with selected_date
                                                            $timeOnly = $taskStartDate->format('H:i:s');
                                                            $dayStartDate = new DateTime($selected_date . ' ' . $timeOnly, $taskStartDate->getTimezone());
                                                            $start_timestamp = $dayStartDate->getTimestamp();
                                                        }
                                                    } catch (Exception $e) {
                                                        $task_start_time = '';
                                                        error_log("Error parsing TaskStart: " . $e->getMessage() . " - Value: " . $flight['TaskStart']);
                                                    }
                                                }
                                                
                                                
                                                if (!empty($flight['TaskEnd'])) {
                                                    try {
                                                        // Parse TaskEnd as datetime (contains full date and time)
                                                        $taskEndDate = new DateTime($flight['TaskEnd']);
                                                        // Format time in 24-hour format (HH:MM)
                                                        $task_end_time = $taskEndDate->format('H:i');
                                                        
                                                        // Get the date from TaskEnd
                                                        $taskEndDateOnly = $taskEndDate->format('Y-m-d');
                                                        
                                                        // If TaskEnd date matches selected_date, use TaskEnd as-is
                                                        // Otherwise, extract time and combine with selected_date
                                                        if ($taskEndDateOnly === $selected_date) {
                                                            // Use TaskEnd timestamp directly
                                                        $end_timestamp = $taskEndDate->getTimestamp();
                                                        } else {
                                                            // Extract time component and combine with selected_date
                                                            $timeOnly = $taskEndDate->format('H:i:s');
                                                            $dayEndDate = new DateTime($selected_date . ' ' . $timeOnly, $taskEndDate->getTimezone());
                                                            $end_timestamp = $dayEndDate->getTimestamp();
                                                        }
                                                    } catch (Exception $e) {
                                                        $task_end_time = '';
                                                        error_log("Error parsing TaskEnd: " . $e->getMessage() . " - Value: " . $flight['TaskEnd']);
                                                    }
                                                }
                                                
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
                                                
                                                // Get flight date (already set from outer loop: $flight_date)
                                                // $flight_date is from the foreach loop above
                                                // Find which day index this flight belongs to
                                                $day_index = 0;
                                                foreach ($timelineDays as $idx => $day) {
                                                    if ($day['date'] === $flight_date) {
                                                        $day_index = $idx;
                                                        break;
                                                    }
                                                }
                                                
                                                // Calculate positions in pixels (100px per hour)
                                                if ($start_timestamp > 0 && $end_timestamp > 0) {
                                                    // Get the start of the flight date for timeline
                                                    // Use the same timezone as TaskStart/TaskEnd to avoid timezone issues
                                                    try {
                                                        // Get timezone from TaskStart if available
                                                        $timezone = null;
                                                        if (!empty($flight['TaskStart'])) {
                                                            try {
                                                                $taskStartDate = new DateTime($flight['TaskStart']);
                                                                $timezone = $taskStartDate->getTimezone();
                                                            } catch (Exception $e) {
                                                                // Use default timezone
                                                            }
                                                        }
                                                        
                                                        if ($timezone) {
                                                            $dayStartDate = new DateTime($flight_date . ' 00:00:00', $timezone);
                                                        } else {
                                                            $dayStartDate = new DateTime($flight_date . ' 00:00:00');
                                                        }
                                                        $day_start = $dayStartDate->getTimestamp();
                                                    } catch (Exception $e) {
                                                        // Fallback to strtotime
                                                    $day_start = strtotime($flight_date . ' 00:00:00');
                                                    }
                                                    
                                                    // Calculate actual start (TaskStart) and actual end (TaskEnd)
                                                    // Since we already combined TaskStart/TaskEnd with flight_date, 
                                                    // the offset should be positive (time of day in seconds)
                                                    $actual_start_offset = $start_timestamp - $day_start;
                                                    $actual_end_offset = $end_timestamp - $day_start;
                                                    
                                                    // Calculate delay in seconds
                                                    $delay_seconds = $delay_minutes * 60;
                                                    
                                                    // Calculate positions relative to timelineStart (not day_start)
                                                    $timeline_start_timestamp = $timelineStart->getTimestamp();
                                                    
                                                    // Calculate offsets relative to timelineStart
                                                    // Add day offset: each day is 24 hours = 86400 seconds
                                                    $day_offset_seconds = $day_index * 86400;
                                                    
                                                    $delay_start_offset_from_timeline = ($day_start + $actual_start_offset + $day_offset_seconds) - $timeline_start_timestamp;
                                                    $delay_end_offset_from_timeline = ($day_start + $actual_start_offset + $delay_seconds + $day_offset_seconds) - $timeline_start_timestamp;
                                                    $flight_start_offset_from_timeline = ($day_start + $actual_start_offset + $delay_seconds + $day_offset_seconds) - $timeline_start_timestamp;
                                                    // Flight end is TaskEnd + delay (not just TaskEnd)
                                                    $flight_end_offset_from_timeline = ($day_start + $actual_end_offset + $delay_seconds + $day_offset_seconds) - $timeline_start_timestamp;
                                                    
                                                    // Convert to percentages (0-100%)
                                                    $delay_start_percent = ($delay_start_offset_from_timeline / $timeline_duration) * 100;
                                                    $delay_end_percent = ($delay_end_offset_from_timeline / $timeline_duration) * 100;
                                                    $delay_width_percent = (($delay_end_offset_from_timeline - $delay_start_offset_from_timeline) / $timeline_duration) * 100;
                                                    
                                                    $flight_start_percent = ($flight_start_offset_from_timeline / $timeline_duration) * 100;
                                                    $flight_end_percent = ($flight_end_offset_from_timeline / $timeline_duration) * 100;
                                                    
                                                    // Calculate flight duration (from TaskStart + delay to TaskEnd + delay)
                                                    // Ensure flight_end_offset is not less than flight_start_offset
                                                    if ($flight_end_offset_from_timeline >= $flight_start_offset_from_timeline) {
                                                        $flight_duration_percent = (($flight_end_offset_from_timeline - $flight_start_offset_from_timeline) / $timeline_duration) * 100;
                                                } else {
                                                        // If TaskEnd is before TaskStart + delay, set minimum duration
                                                        $flight_duration_percent = 0.5; // Minimum 0.5% width
                                                }
                                                
                                                    // Ensure minimum values and bounds (0-100%)
                                                    $delay_start_percent = max(0, min(100, $delay_start_percent));
                                                    $delay_end_percent = max(0, min(100, $delay_end_percent));
                                                    $delay_width_percent = max(0.5, min(100 - $delay_start_percent, $delay_width_percent));
                                                    
                                                    $flight_start_percent = max(0, min(100, $flight_start_percent));
                                                    $flight_end_percent = max(0, min(100, $flight_end_percent));
                                                    $flight_duration_percent = max(0.5, min(100 - $flight_start_percent, $flight_duration_percent));
                                                    
                                                    // Ensure delay bar ends exactly where flight bar starts (no gap, no overlap)
                                                    if ($delay_minutes > 0 && $delay_width_percent > 0) {
                                                        if ($flight_start_percent > $delay_start_percent) {
                                                            // Adjust delay to end exactly where flight starts
                                                            $delay_width_percent = $flight_start_percent - $delay_start_percent;
                                                            $delay_end_percent = $flight_start_percent;
                                                        } else {
                                                            // If flight starts before delay ends, adjust delay
                                                            $delay_width_percent = max(0.5, min(100 - $delay_start_percent, $delay_width_percent));
                                                            $delay_end_percent = $delay_start_percent + $delay_width_percent;
                                                            // Update flight_start_percent to match delay_end_percent
                                                            $flight_start_percent = $delay_end_percent;
                                                        }
                                                    } else {
                                                        $delay_width_percent = 0;
                                                        $delay_start_percent = 0;
                                                        $delay_end_percent = 0;
                                                    }
                                                } else {
                                                    // Fallback if timeline duration is invalid
                                                    $flight_start_percent = 0;
                                                    $flight_duration_percent = 0.5;
                                                    $delay_width_percent = 0;
                                                    $delay_start_percent = 0;
                                                }
                                                
                                                // Calculate vertical position for each flight (stacked vertically)
                                                $top_position = 6 + ($index * 50); // 50px spacing between flights (32px bar + 2px gap + 14px time + 2px bottom)
                                                
                                                // Calculate time labels position (below the flight bar)
                                                $time_labels_top = $top_position + 32 + 2; // Below the 32px height bar + 2px spacing
                                                
                                                // Get status color
                                                $flight_status = $flight['ScheduledTaskStatus'] ?? '';
                                                $status_color_hex = getStatusColor($flight_status);
                                                
                                                // Format display text: TaskName - Route - Time
                                                $flight_display_name = !empty($flight['TaskName']) ? $flight['TaskName'] : ($flight['FlightNo'] ?? '');
                                                $route_display = $flight['Route'] ?? '';
                                                
                                                // If status is Diverted and divert_station exists, append it to route
                                                if (strtoupper($flight_status) === 'DIVERTED' && !empty($flight['divert_station'])) {
                                                    if ($route_display) {
                                                        $route_display .= '-' . $flight['divert_station'];
                                                    } else {
                                                        $route_display = $flight['divert_station'];
                                                    }
                                                }
                                                
                                                // Build display text: TaskName - Route - Time
                                                $display_text = '';
                                                if ($flight_display_name) {
                                                    $display_text .= htmlspecialchars($flight_display_name);
                                                }
                                                if ($route_display) {
                                                    if ($display_text) $display_text .= ' - ';
                                                    $display_text .= htmlspecialchars($route_display);
                                                }
                                                if ($task_start_time) {
                                                    if ($display_text) $display_text .= ' - ';
                                                    $display_text .= $task_start_time;
                                                }
                                                ?>
                                                
                                                <?php if ($delay_minutes > 0 && $delay_width_percent > 0): ?>
                                                <!-- Delay Bar (Red) - From TaskStart to TaskStart + delay -->
                                                <div class="flight-bar bg-red-500 text-white text-xs px-2 py-1 cursor-pointer"
                                                     style="left: <?php echo number_format($delay_start_percent, 2); ?>%; width: <?php echo number_format($delay_width_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 5; border-radius: 8px 0 0 8px; height: 32px; display: flex; align-items: center;"
                                                     onclick="showFlightDetails(<?php echo $flight['id']; ?>)"
                                                     title="<?php echo $delay_minutes; ?> min delay">
                                                    <div class="truncate text-xs font-medium">
                                                        <?php echo $delay_minutes; ?>m
                                                    </div>
                                                </div>
                                                
                                                <!-- Flight Bar - From TaskStart + delay to TaskEnd -->
                                                <!-- Ensure flight bar starts exactly where delay bar ends -->
                                                <div class="flight-bar text-white text-xs px-2 py-1 cursor-pointer flight-tooltip-trigger flight-edit-trigger"
                                                     data-flight-id="<?php echo $flight['id']; ?>"
                                                     style="left: <?php echo number_format($flight_start_percent, 2); ?>%; width: <?php echo number_format($flight_duration_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 10; border-radius: 0 8px 8px 0; height: 32px; background-color: <?php echo htmlspecialchars($status_color_hex); ?>; display: flex; align-items: center;"
                                                     onclick="showFlightDetails(<?php echo $flight['id']; ?>)"
                                                     title="<?php echo htmlspecialchars($display_text); ?>">
                                                    <div class="truncate">
                                                        <?php echo $display_text; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Time Labels Below Flight Bar (with delay) -->
                                                <?php if ($task_start_time || $task_end_time): ?>
                                                <div class="flight-time-labels"
                                                     style="left: <?php echo number_format($flight_start_percent, 2); ?>%; width: <?php echo number_format($flight_duration_percent, 2); ?>%; top: <?php echo $time_labels_top; ?>px;">
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
                                                <!-- Flight Bar - No delay, full radius, from TaskStart to TaskEnd -->
                                                <div class="flight-bar text-white text-xs px-2 py-1 cursor-pointer flight-tooltip-trigger flight-edit-trigger"
                                                     data-flight-id="<?php echo $flight['id']; ?>"
                                                     style="left: <?php echo number_format($flight_start_percent, 2); ?>%; width: <?php echo number_format($flight_duration_percent, 2); ?>%; top: <?php echo $top_position; ?>px; z-index: 10; border-radius: 8px; height: 32px; background-color: <?php echo htmlspecialchars($status_color_hex); ?>; display: flex; align-items: center;"
                                                     onclick="showFlightDetails(<?php echo $flight['id']; ?>)"
                                                     title="<?php echo htmlspecialchars($display_text); ?>">
                                                    <div class="truncate">
                                                        <?php echo $display_text; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Time Labels Below Flight Bar (no delay) -->
                                                <?php if ($task_start_time || $task_end_time): ?>
                                                <div class="flight-time-labels"
                                                     style="left: <?php echo number_format($flight_start_percent, 2); ?>%; width: <?php echo number_format($flight_duration_percent, 2); ?>%; top: <?php echo $time_labels_top; ?>px;">
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
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Flight Details Modal -->
    <div id="flightModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeFlightModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modalTitle">
                                    Flight Details
                                </h3>
                                <button onclick="closeFlightModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                            
                            <div id="flightDetails" class="space-y-4">
                                <!-- Flight details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

    <script>
        // Date picker change handler
        document.getElementById('datePicker').addEventListener('change', function() {
            updateUrl();
        });

        // Days count change handler
        document.getElementById('daysCount').addEventListener('change', function() {
            updateUrl();
        });

        // User filter change handler
        document.getElementById('userFilter').addEventListener('change', function() {
            updateUrl();
        });

        function updateUrl() {
            const date = document.getElementById('datePicker').value;
            const days = document.getElementById('daysCount').value;
            const user = document.getElementById('userFilter').value;
            
            let url = 'flight_monitoring.php?date=' + date;
            if (days && days != '1') {
                url += '&days=' + days;
            }
            if (user) {
                url += '&user=' + user;
            }
            
            window.location.href = url;
        }

        function refreshData() {
            window.location.reload();
        }

        function showFlightDetails(flightId) {
            // Show loading state
            document.getElementById('flightDetails').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-gray-600 dark:text-gray-400">Loading flight details...</p></div>';
            document.getElementById('flightModal').classList.remove('hidden');

            // Fetch flight details
            fetch('flight_details.php?id=' + flightId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('flightDetails').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('flightDetails').innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Error loading flight details</p></div>';
                });
        }

        function closeFlightModal() {
            document.getElementById('flightModal').classList.add('hidden');
        }
        
        // Flight Edit Modal Functions
        let currentEditFlightId = null;
        let timesInUTC = false;
        let globalDelayCodes = [];
        
        function openFlightEditModal(flightId) {
            if (!flightId) return;
            
            currentEditFlightId = flightId;
            timesInUTC = false;
            
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
            
            modal.classList.add('show');
            modalBody.innerHTML = '<div class="flight-edit-loading">Loading flight data...</div>';
            
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
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function renderFlightEditForm(container, data) {
            globalDelayCodes = data.delay_codes || [];
            
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
                    
                    let shouldShow = i === 1;
                    if (i > 1) {
                        if (selectedCode || minutes || dv93) {
                            shouldShow = true;
                        } else {
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
                                <div>
                                    <label class="flight-edit-form-label">Code</label>
                                    <select name="${codeField}" id="${codeField}" onchange="updateDV93Description(${i}, this.value)" class="flight-edit-form-select" ${!isEnabled && i > 1 ? 'disabled' : ''}>
                                        <option value="">-- Select Code --</option>
                                        ${delayCodes.map(code => {
                                            const isSelected = code.code === selectedCode ? 'selected' : '';
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
                                    <select name="${subCodeField}" id="${subCodeField}" class="flight-edit-form-select" data-selected-value="${escapeHtml(selectedSubCode)}">
                                        <option value="">-- Select Sub Code --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="flight-edit-form-label">Minutes</label>
                                    <input type="number" name="${minutesField}" id="${minutesField}" value="${escapeHtml(minutes)}" class="flight-edit-form-input" min="0" ${!isEnabled && i > 1 ? 'disabled' : ''}>
                                </div>
                                <div>
                                    <label class="flight-edit-form-label">DV93 Type</label>
                                    <input type="text" name="${dv93Field}" id="dv93_${i}" value="${escapeHtml(dv93)}" class="flight-edit-form-input" readonly>
                                </div>
                                <div style="display: ${selectedCode === '99 (MX)' ? 'block' : 'none'};" id="remark_${i}_container">
                                    <label class="flight-edit-form-label">Remark (Code 99)</label>
                                    <textarea name="${remarkField}" id="${remarkField}" class="flight-edit-form-input" rows="3" ${!isEnabled && i > 1 ? 'disabled' : ''}>${escapeHtml(remark)}</textarea>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                return html;
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
            initializeDateTimeHandlers();
        }
        
        function combineDateTime(dateFieldId, timeFieldId, hiddenFieldId) {
            const dateField = document.getElementById(dateFieldId);
            const timeField = document.getElementById(timeFieldId);
            const hiddenField = document.getElementById(hiddenFieldId);
            
            if (!dateField || !timeField || !hiddenField) return;
            
            const dateValue = dateField.value;
            const timeValue = timeField.value.trim();
            
            if (dateValue && timeValue && timeValue.length === 4) {
                const hours = timeValue.substring(0, 2);
                const minutes = timeValue.substring(2, 4);
                
                const hoursInt = parseInt(hours);
                const minutesInt = parseInt(minutes);
                
                if (hoursInt >= 0 && hoursInt <= 23 && minutesInt >= 0 && minutesInt <= 59) {
                    const datetime = dateValue + ' ' + hours + ':' + minutes + ':00';
                    hiddenField.value = datetime;
                } else {
                    hiddenField.value = '';
                }
            } else {
                hiddenField.value = '';
            }
        }
        
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
                    dateField.addEventListener('change', () => combineDateTime(field.date, field.time, field.hidden));
                    timeField.addEventListener('input', () => combineDateTime(field.date, field.time, field.hidden));
                    timeField.addEventListener('blur', () => combineDateTime(field.date, field.time, field.hidden));
                    combineDateTime(field.date, field.time, field.hidden);
                }
            });
        }
        
        function calculateUpliftLbs(input) {
            const form = input.closest('form');
            if (!form) return;
            
            const upliftFuelInput = input;
            const upliftLbsInput = form.querySelector('input[name="uplft_lbs"]');
            
            if (upliftFuelInput && upliftLbsInput) {
                const fuelLiters = parseFloat(upliftFuelInput.value) || 0;
                const fuelLbs = Math.round(fuelLiters * 0.76 * 2.20462);
                upliftLbsInput.value = fuelLbs;
            }
        }
        
        function updateDV93Description(rowNumber, selectedValue) {
            const codeSelect = document.getElementById(rowNumber === 1 ? 'delay_diversion_codes' : `delay_diversion_codes_${rowNumber}`);
            const dv93Input = document.getElementById(`dv93_${rowNumber}`);
            const descriptionDiv = document.getElementById(`code_description_${rowNumber}`);
            const subCodeContainer = document.getElementById(`sub_code_${rowNumber}_container`);
            const remarkContainer = document.getElementById(`remark_${rowNumber}_container`);
            const delayRowTitle = document.querySelector(`#delay_row_${rowNumber} .delay-row-title`);
            
            if (!codeSelect || !dv93Input) return;
            
            if (selectedValue) {
                const selectedOption = codeSelect.options[codeSelect.selectedIndex];
                const description = selectedOption.getAttribute('data-description');
                
                if (descriptionDiv) {
                    descriptionDiv.textContent = description || '';
                    descriptionDiv.style.display = description ? 'block' : 'none';
                }
                
                if (description) {
                    dv93Input.value = description;
                }
                
                if (delayRowTitle) {
                    delayRowTitle.textContent = `Delay/Diversion Code ${rowNumber}: ${selectedValue}`;
                }
                
                if (selectedValue === '93 (RA)') {
                    const codeData = globalDelayCodes.find(code => code.code === selectedValue);
                    if (codeData && codeData.sub_codes && subCodeContainer) {
                        subCodeContainer.style.display = 'block';
                        const subCodeSelect = document.getElementById(`delay_diversion_sub_codes_${rowNumber}`);
                        if (subCodeSelect) {
                            subCodeSelect.innerHTML = '<option value="">-- Select Sub Code --</option>';
                            codeData.sub_codes.forEach(subCode => {
                                const option = document.createElement('option');
                                option.value = subCode.code;
                                option.textContent = subCode.code + ' - ' + subCode.description;
                                option.setAttribute('data-description', subCode.description);
                                subCodeSelect.appendChild(option);
                            });
                        }
                    }
                } else {
                    if (subCodeContainer) subCodeContainer.style.display = 'none';
                }
                
                if (selectedValue === '99 (MX)') {
                    if (remarkContainer) remarkContainer.style.display = 'block';
                } else {
                    if (remarkContainer) remarkContainer.style.display = 'none';
                }
            } else {
                if (descriptionDiv) {
                    descriptionDiv.textContent = '';
                    descriptionDiv.style.display = 'none';
                }
                dv93Input.value = '';
                if (subCodeContainer) subCodeContainer.style.display = 'none';
                if (remarkContainer) remarkContainer.style.display = 'none';
                if (delayRowTitle) {
                    delayRowTitle.textContent = `Delay/Diversion Code ${rowNumber}`;
                }
            }
        }
        
        function saveFlightData(event) {
            event.preventDefault();
            
            if (!currentEditFlightId) return;
            
            const form = document.getElementById('flightEditForm');
            const messagesDiv = document.getElementById('flightEditMessages');
            const saveBtn = document.getElementById('saveFlightBtn');
            
            if (!form || !messagesDiv || !saveBtn) return;
            
            combineDateTime('TaskStart_date', 'TaskStart_time', 'TaskStart');
            combineDateTime('TaskEnd_date', 'TaskEnd_time', 'TaskEnd');
            combineDateTime('actual_out_utc_date', 'actual_out_utc_time', 'actual_out_utc');
            combineDateTime('actual_in_utc_date', 'actual_in_utc_time', 'actual_in_utc');
            
            const formData = new FormData(form);
            const data = {
                flight_id: currentEditFlightId
            };
            
            formData.forEach((value, key) => {
                if (key.endsWith('_date') || key.endsWith('_time')) {
                    return;
                }
                if (data.hasOwnProperty(key)) {
                    return;
                }
                if (value !== '' && value !== null) {
                    data[key] = value;
                }
            });
            
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            messagesDiv.innerHTML = '';
            
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
        
        function toggleTimeZone() {
            // Placeholder for timezone conversion - can be implemented later if needed
            alert('Timezone conversion feature will be implemented');
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            // Only refresh if no modal is open
            if (document.getElementById('flightModal').classList.contains('hidden')) {
                refreshData();
            }
        }, 300000); // 5 minutes
        
        // Auto-scroll to current time indicator (yellow line)
        document.addEventListener('DOMContentLoaded', function() {
            scrollToCurrentTime();
        });
        
        // Function to scroll to current time indicator
        function scrollToCurrentTime() {
            // Find the scrollable container (timeline-container has overflow-x: auto)
            const timelineContainer = document.querySelector('.timeline-container');
            if (!timelineContainer) return;
            
            // Find the first current time indicator (yellow line) in the header
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
    </script>
</body>
</html>
