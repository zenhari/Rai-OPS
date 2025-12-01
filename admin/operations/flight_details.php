<?php
require_once '../../config.php';

// Check access for API endpoint
if (!checkPageAccessEnhanced('admin/operations/flight_details.php')) {
    http_response_code(401);
    exit('Unauthorized');
}

$flight_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$flight_id) {
    http_response_code(400);
    exit('Invalid flight ID');
}

$flight = getFlightDetailsForModal($flight_id);
if (!$flight) {
    http_response_code(404);
    exit('Flight not found');
}

// Calculate durations
$scheduled_duration = calculateFlightDuration($flight['TaskStart'], $flight['TaskEnd']);
$actual_duration = calculateFlightDuration($flight['TaskStart'], $flight['TaskEnd']);

// Format times
function formatDateTime($datetime) {
    if (!$datetime) return 'N/A';
    return date('H:i', strtotime($datetime)) . ' ' . date('d/m/Y', strtotime($datetime));
}

function formatTime($time) {
    if (!$time) return 'N/A';
    return date('H:i', strtotime($time));
}

// Format HHMM time (4 digits) to HH:MM
function formatHHMM($hhmm) {
    if (empty($hhmm) || strlen($hhmm) < 4) return 'N/A';
    $hhmm = str_pad($hhmm, 4, '0', STR_PAD_LEFT);
    $hours = substr($hhmm, 0, 2);
    $minutes = substr($hhmm, 2, 2);
    return $hours . ':' . $minutes;
}
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Flight Information -->
    <div class="space-y-4">
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Flight Information</h4>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Flight Number:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['FlightNo']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Route:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['Route']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Aircraft:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['aircraft_rego']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Aircraft Type:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['aircraft_type']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getFlightStatusColor($flight['status'] ?? 'scheduled', $flight['delay_minutes'] ?? 0); ?> text-white">
                        <?php echo ucfirst($flight['status'] ?? 'scheduled'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Scheduled Information -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Scheduled Information</h4>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Date:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo date('D d/m/Y', strtotime($flight['FltDate'])); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Task Start:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatTime($flight['TaskStart']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Task End:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatTime($flight['TaskEnd']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Duration:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $scheduled_duration; ?> hours</span>
                </div>
            </div>
        </div>

        <!-- Actual Information -->
        <?php if ($flight['TaskStart'] || $flight['TaskEnd']): ?>
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Actual Information</h4>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Actual Start:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatTime($flight['TaskStart']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Actual End:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatTime($flight['TaskEnd']); ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Actual Duration:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $actual_duration; ?> hours</span>
                </div>
                
                <?php if (isset($flight['delay_minutes']) && $flight['delay_minutes'] > 0): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Delay:</span>
                    <span class="text-sm font-medium text-red-600"><?php echo $flight['delay_minutes']; ?> minutes</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Flight Times -->
        <?php 
        $hasFlightTimes = false;
        $flightTimeFields = ['ready', 'start', 'gate_closed', 'off_block', 'taxi', 'return_to_ramp', 'takeoff', 'landed', 'on_block'];
        foreach ($flightTimeFields as $field) {
            if (!empty($flight[$field])) {
                $hasFlightTimes = true;
                break;
            }
        }
        ?>
        <?php if ($hasFlightTimes): ?>
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Flight Times</h4>
            
            <div class="space-y-2">
                <?php if (!empty($flight['ready'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Ready:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['ready']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['start'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Start:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['start']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['gate_closed'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Gate Closed:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['gate_closed']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['off_block'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Off Block:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['off_block']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['taxi'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Taxi:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['taxi']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['return_to_ramp'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Return to Ramp:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['return_to_ramp']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['takeoff'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Takeoff:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['takeoff']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['landed'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Landed:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['landed']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['on_block'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">On Block:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo formatHHMM($flight['on_block']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Crew Information -->
    <div class="space-y-4">
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Crew Information</h4>
            
            <div class="space-y-3">
                <?php
                // Get all crew members (Crew1 to Crew10) with their roles
                $crewMembers = [];
                for ($i = 1; $i <= 10; $i++) {
                    $crewField = 'Crew' . $i;
                    $roleField = 'Crew' . $i . '_role';
                    
                    if (!empty($flight[$crewField])) {
                        $userId = $flight[$crewField];
                        $user = getUserById($userId);
                        $role = $flight[$roleField] ?? '';
                        
                        if ($user) {
                            $crewMembers[] = [
                                'user_id' => $userId,
                                'name' => htmlspecialchars(($user['last_name'] ?? '') . ', ' . ($user['first_name'] ?? '')),
                                'role' => htmlspecialchars($role)
                            ];
                        } else {
                            // If user not found, still show the ID
                            $crewMembers[] = [
                                'user_id' => $userId,
                                'name' => "User ID: $userId",
                                'role' => htmlspecialchars($role)
                            ];
                        }
                    }
                }
                ?>
                
                <?php if (!empty($crewMembers)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Role</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                <?php foreach ($crewMembers as $member): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white"><?php echo $member['name']; ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                                        <?php if ($member['role']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <?php echo $member['role']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-users text-2xl mb-2"></i>
                        <p class="text-sm">No crew members assigned</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Passenger Information -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Passenger Information</h4>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Passengers:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $flight['total_pax'] ?? 0; ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Adults:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $flight['adult'] ?? 0; ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Children:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $flight['child'] ?? 0; ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Infants:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $flight['infant'] ?? 0; ?></span>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <?php if (!empty($flight['TaskDescriptionHTML']) || !empty($flight['pcs']) || !empty($flight['weight']) || !empty($flight['uplift_fuel'])): ?>
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Additional Information</h4>
            
            <div class="space-y-2">
                <?php if (!empty($flight['pcs'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">PCS:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['pcs']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['weight'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Weight (KG):</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['weight']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['uplift_fuel'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Uplift Fuel (Liter):</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['uplift_fuel']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($flight['uplft_lbs'])): ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Uplift in LBS:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($flight['uplft_lbs']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Actions</h4>
            
            <div class="flex space-x-3">
                <button onclick="openFlightEditModal(<?php echo $flight['id']; ?>)" 
                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-edit mr-2"></i>
                    Edit Flight
                </button>
                
                <a href="../flights/index.php" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-list mr-2"></i>
                    View All Flights
                </a>
            </div>
        </div>
        
        <!-- Delay/Diversion Information -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Delay/Diversion Information</h4>
            
            <div class="space-y-3">
                <?php
                // Display delay/diversion codes and minutes for all 5 entries
                for ($i = 1; $i <= 5; $i++):
                    $delayCode = $flight['delay_diversion_codes' . ($i > 1 ? '_' . $i : '')] ?? '';
                    $minutes = $flight['minutes_' . $i] ?? '';
                    $dv93 = $flight['dv93_' . $i] ?? '';
                    $remark = $flight['remark_' . $i] ?? '';
                    
                    // Only show if at least one field has data
                    if (!empty($delayCode) || !empty($minutes) || !empty($dv93) || !empty($remark)):
                ?>
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delay Entry #<?php echo $i; ?></div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <?php if (!empty($delayCode)): ?>
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Delay Code:</span>
                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($delayCode); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($minutes)): ?>
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Minutes:</span>
                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($minutes); ?> min</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($dv93)): ?>
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">DV93 Type:</span>
                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($dv93); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($remark) && $delayCode === '99 (MX)'): ?>
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Remark (Code 99):</span>
                        <div class="text-sm text-gray-900 dark:text-white mt-1 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border-l-4 border-yellow-400">
                            <?php echo nl2br(htmlspecialchars($remark)); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php 
                    endif;
                endfor;
                
                // Show message if no delay/diversion data
                $hasDelayData = false;
                for ($i = 1; $i <= 5; $i++) {
                    if (!empty($flight['delay_diversion_codes' . ($i > 1 ? '_' . $i : '')]) || 
                        !empty($flight['minutes_' . $i]) || 
                        !empty($flight['dv93_' . $i]) ||
                        !empty($flight['remark_' . $i])) {
                        $hasDelayData = true;
                        break;
                    }
                }
                
                if (!$hasDelayData):
                ?>
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-clock text-2xl mb-2"></i>
                    <p class="text-sm">No delay/diversion information recorded</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
