<?php
require_once '../../config.php';

// Check access for this page
checkPageAccessWithRedirect('admin/operations/fdp_calculation.php');

// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Function to send SSE data
function sendSSEData($type, $data) {
    echo "data: " . json_encode(['type' => $type, ...$data]) . "\n\n";
    ob_flush();
    flush();
}

try {
    // Get all crew members (returns array of IDs)
    $crewMemberIds = getAllCrewMembersForFDP();
    $totalCrew = count($crewMemberIds);
    $processedCrew = 0;
    
    sendSSEData('progress', [
        'percentage' => 0,
        'message' => "Starting FDP calculations for {$totalCrew} crew members...",
        'current_crew' => null
    ]);
    
    foreach ($crewMemberIds as $crewMemberId) {
        $processedCrew++;
        $percentage = round(($processedCrew / $totalCrew) * 100);
        
        // Get user info for display
        $user = getUserById($crewMemberId);
        $crewMemberName = $user ? ($user['first_name'] . ' ' . $user['last_name']) : "ID: {$crewMemberId}";
        
        sendSSEData('progress', [
            'percentage' => $percentage,
            'message' => "Calculating FDP for crew member {$processedCrew} of {$totalCrew}",
            'current_crew' => $crewMemberName
        ]);
        
        // Get FDP data for this crew member (using ID)
        $crewFDPData = getCrewMemberFDPData($crewMemberId);
        
        if (!empty($crewFDPData)) {
            // Calculate summary for this crew member
            $totalDutyDays = count($crewFDPData);
            $totalSectors = array_sum(array_column($crewFDPData, 'sectors'));
            $totalFlightHours = array_sum(array_column($crewFDPData, 'flight_hours'));
            $totalFDPHours = array_sum(array_column($crewFDPData, 'fdp_hours'));
            $totalDutyHours = array_sum(array_column($crewFDPData, 'duty_hours'));
            
            // Collect violations
            $fdpViolations = [];
            $dutyViolations = [];
            $flightViolations = [];
            $fdpViolationsCount = 0;
            
            foreach ($crewFDPData as $data) {
                // Calculate Max FDP based on FDP start time and sectors
                // For now, assume crew is acclimatised (use Table-1)
                // TODO: Add logic to determine acclimatisation state
                $maxAllowed = calculateMaxFDP($data['fdp_start'] ?? null, $data['sectors'] ?? 1, true);
                
                // FDP violations (when FDP hours exceed max allowed)
                // If max_allowed is null, fallback to 14 hours check
                $isViolation = false;
                if ($maxAllowed !== null) {
                    $isViolation = $data['fdp_hours'] > $maxAllowed;
                } else {
                    $isViolation = $data['fdp_hours'] > 14;
                    $maxAllowed = 14.0; // Fallback value
                }
                
                if ($isViolation) {
                    $fdpViolationsCount++;
                    $fdpViolations[] = [
                        'crew_member' => $crewMemberName,
                        'crew_id' => $crewMemberId,
                        'date' => $data['date'],
                        'position' => $data['position'] ?? 'N/A',
                        'sectors' => $data['sectors'],
                        'fdp_hours' => $data['fdp_hours'],
                        'fdp_start' => $data['fdp_start'] ?? null,
                        'fdp_end' => $data['fdp_end'] ?? null,
                        'max_allowed' => $maxAllowed,
                        'flight_hours' => $data['flight_hours'] ?? 0,
                        'routes' => $data['routes'] ?? 'N/A',
                        'aircraft' => $data['aircraft'] ?? 'N/A'
                    ];
                }
            }
            
            // Get duty limit violations from v_duty_rolling_crew view
            $dutyLimits = getCrewMemberDutyLimits($crewMemberName);
            foreach ($dutyLimits as $dutyLimit) {
                // Check if any limit is exceeded
                if ($dutyLimit['DutyH_7d'] > 60 || $dutyLimit['DutyH_14d'] > 110 || $dutyLimit['DutyH_28d'] > 190) {
                    // Get position for this date from FDP data
                    $position = 'N/A';
                    $base = 'N/A';
                    $totalFlights = 0;
                    $lastDutyDate = null;
                    
                    foreach ($crewFDPData as $data) {
                        if ($data['date'] == $dutyLimit['DutyDate']) {
                            $position = $data['position'] ?? 'N/A';
                            break;
                        }
                    }
                    
                    // Get base from user
                    $user = getUserById($crewMemberId);
                    if ($user && !empty($user['base'])) {
                        $base = $user['base'];
                    }
                    
                    // Count total flights for this crew member
                    $totalFlights = count($crewFDPData);
                    
                    // Get last duty date
                    if (!empty($crewFDPData)) {
                        $lastDutyDate = $crewFDPData[0]['date'];
                    }
                    
                    $dutyViolations[] = [
                        'crew_member' => $crewMemberName,
                        'crew_id' => $crewMemberId,
                        'date' => $dutyLimit['DutyDate'],
                        'position' => $position,
                        'duty_7d' => floatval($dutyLimit['DutyH_7d'] ?? 0),
                        'duty_14d' => floatval($dutyLimit['DutyH_14d'] ?? 0),
                        'duty_28d' => floatval($dutyLimit['DutyH_28d'] ?? 0),
                        'base' => $base,
                        'total_flights' => $totalFlights,
                        'last_duty_date' => $lastDutyDate
                    ];
                }
            }
            
            // Get flight time limit violations from v_flight_rolling_crew view
            $flightLimits = getCrewMemberFlightLimits($crewMemberName);
            foreach ($flightLimits as $flightLimit) {
                // Check if any limit is exceeded
                if ($flightLimit['FltH_28d'] > 100 || $flightLimit['FltH_CalendarYear'] > 900 || $flightLimit['FltH_12mo'] > 1000) {
                    // Get position for this date from FDP data
                    $position = 'N/A';
                    $base = 'N/A';
                    $totalFlights = 0;
                    $lastFlightDate = null;
                    
                    foreach ($crewFDPData as $data) {
                        if ($data['date'] == $flightLimit['DutyDate']) {
                            $position = $data['position'] ?? 'N/A';
                            break;
                        }
                    }
                    
                    // Get base from user
                    $user = getUserById($crewMemberId);
                    if ($user && !empty($user['base'])) {
                        $base = $user['base'];
                    }
                    
                    // Count total flights for this crew member
                    $totalFlights = count($crewFDPData);
                    
                    // Get last flight date
                    if (!empty($crewFDPData)) {
                        $lastFlightDate = $crewFDPData[0]['date'];
                    }
                    
                    $flightViolations[] = [
                        'crew_member' => $crewMemberName,
                        'crew_id' => $crewMemberId,
                        'date' => $flightLimit['DutyDate'],
                        'position' => $position,
                        'flight_28d' => floatval($flightLimit['FltH_28d'] ?? 0),
                        'flight_calendar' => floatval($flightLimit['FltH_CalendarYear'] ?? 0),
                        'flight_12mo' => floatval($flightLimit['FltH_12mo'] ?? 0),
                        'base' => $base,
                        'total_flights' => $totalFlights,
                        'last_flight_date' => $lastFlightDate
                    ];
                }
            }
            
            $lastDutyDate = $crewFDPData[0]['date'] ?? null;
            
            $summary = [
                'crew_member' => $crewMemberName,
                'crew_id' => $crewMemberId,
                'total_duty_days' => $totalDutyDays,
                'total_sectors' => $totalSectors,
                'total_flight_hours' => round($totalFlightHours, 1),
                'total_fdp_hours' => round($totalFDPHours, 1),
                'total_duty_hours' => round($totalDutyHours, 1),
                'fdp_violations' => $fdpViolationsCount,
                'last_duty_date' => $lastDutyDate
            ];
            
            // Send crew completed data with violations
            sendSSEData('crew_completed', [
                'summary' => $summary,
                'crew_member' => $crewMemberName,
                'crew_id' => $crewMemberId,
                'violations' => [
                    'fdp_violations' => $fdpViolations,
                    'duty_violations' => $dutyViolations,
                    'flight_violations' => $flightViolations,
                    'filtered_data' => $crewFDPData
                ]
            ]);
        }
        
        // Small delay to show progress
        usleep(100000); // 0.1 second
    }
    
    // Send completion
    sendSSEData('completed', [
        'message' => "FDP calculations completed successfully!",
        'total_crew' => $totalCrew,
        'processed_crew' => $processedCrew
    ]);
    
} catch (Exception $e) {
    sendSSEData('error', [
        'message' => "Error during FDP calculation: " . $e->getMessage()
    ]);
}
?>