<?php
require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check access for API endpoint
if (!checkPageAccessEnhanced('admin/operations/fdp_calculation.php')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access to FDP calculation page']);
    exit();
}

$crewMember = isset($_GET['crew_member']) ? trim($_GET['crew_member']) : '';
$crewMemberId = isset($_GET['crew_id']) ? intval($_GET['crew_id']) : 0;

// Support both crew_id (preferred) and crew_member (backward compatibility)
if (empty($crewMember) && $crewMemberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Crew member ID or name is required']);
    exit();
}

try {
    // Use ID if provided, otherwise use name (backward compatibility)
    $crewIdentifier = $crewMemberId > 0 ? $crewMemberId : $crewMember;
    
    // Get user info for name lookup
    $user = null;
    if ($crewMemberId > 0) {
        $user = getUserById($crewMemberId);
    } else {
        // Try to find user by name
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
        $stmt->execute([$crewMember]);
        $userResult = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userResult) {
            $user = getUserById($userResult['id']);
        }
    }
    
    $crewMemberName = $user ? ($user['first_name'] . ' ' . $user['last_name']) : $crewMember;
    
    // Get FDP data using new calculation method
    $fdpData = getCrewMemberFDPData($crewIdentifier);
    
    // Get duty limits data
    $dutyData = getCrewMemberDutyLimits($crewMemberName);
    
    // Get flight limits data
    $flightData = getCrewMemberFlightLimits($crewMemberName);
    
    // Generate HTML content
    $html = '<div class="space-y-6">';
    
    // FDP Compliance Section
    $html .= '<div>';
    $html .= '<h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">FDP Compliance History</h4>';
    $html .= '<div class="overflow-x-auto">';
    $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
    $html .= '<thead class="bg-gray-50 dark:bg-gray-700">';
    $html .= '<tr>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sectors</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Hours</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Time</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Time</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Split Duty</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Routes</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
    
    if (empty($fdpData)) {
        $html .= '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No FDP data found</td></tr>';
    } else {
        foreach ($fdpData as $data) {
            // Check if split duty exists
            $hasSplitDuty = isset($data['has_split_duty']) && $data['has_split_duty'] === true;
            
            // Calculate Max FDP for this entry
            $maxAllowed = null;
            if ($hasSplitDuty && isset($data['max_fdp_with_split']) && $data['max_fdp_with_split'] !== null) {
                // Use Max FDP with split duty extension
                $maxAllowed = $data['max_fdp_with_split'];
            } else {
                // Use normal Max FDP
                $maxAllowed = calculateMaxFDP($data['fdp_start'] ?? null, $data['sectors'] ?? 1, true);
            }
            
            // Check if this is a violation
            $isViolation = false;
            if ($maxAllowed !== null) {
                $isViolation = $data['fdp_hours'] > $maxAllowed;
            } else {
                $isViolation = $data['fdp_hours'] > 14;
            }
            
            // Determine row color based on violation
            $rowClass = $isViolation ? 'bg-red-50 dark:bg-red-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700';
            
            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . date('M j, Y', strtotime($data['date'])) . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . $data['sectors'] . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm ' . ($isViolation ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400') . '">' . number_format($data['fdp_hours'], 1) . 'h' . ($maxAllowed !== null ? ' / ' . number_format($maxAllowed, 1) . 'h' : '') . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['flight_hours'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . date('H:i', strtotime($data['fdp_start'])) . ' - ' . date('H:i', strtotime($data['fdp_end'])) . '</td>';
            
            // Split Duty column
            if ($hasSplitDuty) {
                $breakNet = isset($data['split_duty_break_net']) ? $data['split_duty_break_net'] : null;
                $breakDisplay = $breakNet !== null ? number_format($breakNet, 1) . 'h' : 'N/A';
                $html .= '<td class="px-6 py-4 whitespace-nowrap">';
                $html .= '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200" title="Split Duty Break (Net): ' . $breakDisplay . '">';
                $html .= '<i class="fas fa-pause-circle mr-1"></i>YES (' . $breakDisplay . ')';
                $html .= '</span>';
                $html .= '</td>';
            } else {
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">-</td>';
            }
            
            // Status badge - red for violations, green for compliant
            if ($isViolation) {
                $html .= '<td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">VIOLATION</span></td>';
            } else {
                $html .= '<td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">COMPLIANT</span></td>';
            }
            
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . htmlspecialchars($data['routes']) . '</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Duty Limits Section
    $html .= '<div>';
    $html .= '<h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Duty Time Limits (Last 10 Days)</h4>';
    $html .= '<div class="overflow-x-auto">';
    $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
    $html .= '<thead class="bg-gray-50 dark:bg-gray-700">';
    $html .= '<tr>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Daily Duty</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">7-Day Total</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">14-Day Total</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">28-Day Total</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
    
    $dutyDataLimited = array_slice($dutyData, 0, 10);
    if (empty($dutyDataLimited)) {
        $html .= '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No duty data found</td></tr>';
    } else {
        foreach ($dutyDataLimited as $data) {
            $html .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . date('M j, Y', strtotime($data['DutyDate'])) . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['DayDutyHours'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['DutyH_7d'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['DutyH_14d'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['DutyH_28d'], 1) . 'h</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Flight Time Limits Section
    $html .= '<div>';
    $html .= '<h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flight Time Limits (Last 10 Days)</h4>';
    $html .= '<div class="overflow-x-auto">';
    $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
    $html .= '<thead class="bg-gray-50 dark:bg-gray-700">';
    $html .= '<tr>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Daily Flight</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">28-Day Total</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Calendar Year</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">12-Month Total</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
    
    $flightDataLimited = array_slice($flightData, 0, 10);
    if (empty($flightDataLimited)) {
        $html .= '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No flight data found</td></tr>';
    } else {
        foreach ($flightDataLimited as $data) {
            $html .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . date('M j, Y', strtotime($data['DutyDate'])) . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['DayFlightHours'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['FltH_28d'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['FltH_CalendarYear'], 1) . 'h</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' . number_format($data['FltH_12mo'], 1) . 'h</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    error_log("FDP API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading crew details: ' . $e->getMessage()]);
}
?>
