<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/settings/sms.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get all users
$all_users = getAllUsers(1000);

// Get flight crew members for selected date
$flight_date = $_GET['flight_date'] ?? date('Y-m-d');
$flight_crew_users = [];

if (!empty($flight_date)) {
    try {
        $db = getDBConnection();
        // Get all flights for the selected date
        $stmt = $db->prepare("
            SELECT 
                f.id,
                f.FltDate,
                f.Route,
                f.TaskStart,
                f.TaskEnd,
                f.FlightNo,
                f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10
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
        $stmt->execute([$flight_date]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all unique crew IDs from flights
        $crewIds = [];
        foreach ($flights as $flight) {
            for ($i = 1; $i <= 10; $i++) {
                $crewField = "Crew{$i}";
                if (!empty($flight[$crewField])) {
                    $crewIds[$flight[$crewField]] = true;
                }
            }
        }
        
        // Get user information for crew IDs
        if (!empty($crewIds)) {
            $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
            $stmt = $db->prepare("
                SELECT u.*, r.name as role_name, r.display_name as role_display_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id IN ($placeholders)
                ORDER BY u.first_name, u.last_name
            ");
            $stmt->execute(array_keys($crewIds));
            $flight_crew_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add role field for backward compatibility
            foreach ($flight_crew_users as &$user) {
                $user['role'] = $user['role_name'] ?? 'employee';
            }
        }
    } catch (Exception $e) {
        error_log("Error getting flight crew users: " . $e->getMessage());
        $flight_crew_users = [];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_sms':
            $selectedUsers = $_POST['selected_users'] ?? [];
            $smsText = trim($_POST['sms_text'] ?? '');
            
            if (empty($selectedUsers)) {
                $error = 'Please select at least one user.';
            } elseif (empty($smsText)) {
                $error = 'SMS text is required.';
            } else {
                $successCount = 0;
                $failCount = 0;
                $results = [];
                
                foreach ($selectedUsers as $userId) {
                    $user = getUserById($userId);
                    // Try mobile first, then phone, then alternative_mobile
                    $mobile = $user['mobile'] ?? $user['phone'] ?? $user['alternative_mobile'] ?? '';
                    
                    if ($user && !empty($mobile)) {
                        // Remove any non-numeric characters except + at the start
                        $mobile = preg_replace('/[^0-9+]/', '', $mobile);
                        // If starts with 0, replace with country code
                        if (substr($mobile, 0, 1) === '0') {
                            $mobile = '98' . substr($mobile, 1);
                        }
                        // If doesn't start with + or country code, add country code
                        if (substr($mobile, 0, 1) !== '+' && substr($mobile, 0, 2) !== '98') {
                            $mobile = '98' . $mobile;
                        }
                        
                        $result = sendSMS($mobile, $smsText);
                        
                        if ($result['success']) {
                            $successCount++;
                            $results[] = [
                                'user' => $user['first_name'] . ' ' . $user['last_name'],
                                'mobile' => $mobile,
                                'status' => 'success',
                                'message' => 'SMS sent successfully'
                            ];
                        } else {
                            $failCount++;
                            $results[] = [
                                'user' => $user['first_name'] . ' ' . $user['last_name'],
                                'mobile' => $mobile,
                                'status' => 'failed',
                                'message' => 'Failed to send SMS: ' . ($result['response'] ?? 'Unknown error')
                            ];
                        }
                    } else {
                        $failCount++;
                        $results[] = [
                            'user' => $user ? ($user['first_name'] . ' ' . $user['last_name']) : 'User ID: ' . $userId,
                            'mobile' => 'N/A',
                            'status' => 'failed',
                            'message' => 'User has no phone number'
                        ];
                    }
                }
                
                if ($successCount > 0) {
                    $message = "SMS sent successfully to {$successCount} user(s).";
                    if ($failCount > 0) {
                        $message .= " {$failCount} failed.";
                    }
                } else {
                    $error = "Failed to send SMS to all selected users.";
                }
                
                // Store results in session for display
                $_SESSION['sms_results'] = $results;
            }
            break;
            
        case 'send_flight_sms':
            $selectedUsers = $_POST['selected_users'] ?? [];
            $smsText = trim($_POST['sms_text'] ?? '');
            $flightDate = trim($_POST['flight_date'] ?? '');
            
            if (empty($selectedUsers)) {
                $error = 'Please select at least one user.';
            } elseif (empty($smsText)) {
                $error = 'SMS text is required.';
            } elseif (empty($flightDate)) {
                $error = 'Flight date is required.';
            } else {
                $successCount = 0;
                $failCount = 0;
                $results = [];
                
                foreach ($selectedUsers as $userId) {
                    $user = getUserById($userId);
                    // Try mobile first, then phone, then alternative_mobile
                    $mobile = $user['mobile'] ?? $user['phone'] ?? $user['alternative_mobile'] ?? '';
                    
                    if ($user && !empty($mobile)) {
                        // Remove any non-numeric characters except + at the start
                        $mobile = preg_replace('/[^0-9+]/', '', $mobile);
                        // If starts with 0, replace with country code
                        if (substr($mobile, 0, 1) === '0') {
                            $mobile = '98' . substr($mobile, 1);
                        }
                        // If doesn't start with + or country code, add country code
                        if (substr($mobile, 0, 1) !== '+' && substr($mobile, 0, 2) !== '98') {
                            $mobile = '98' . $mobile;
                        }
                        
                        $result = sendSMS($mobile, $smsText);
                        
                        if ($result['success']) {
                            $successCount++;
                            $results[] = [
                                'user' => $user['first_name'] . ' ' . $user['last_name'],
                                'mobile' => $mobile,
                                'status' => 'success',
                                'message' => 'SMS sent successfully'
                            ];
                        } else {
                            $failCount++;
                            $results[] = [
                                'user' => $user['first_name'] . ' ' . $user['last_name'],
                                'mobile' => $mobile,
                                'status' => 'failed',
                                'message' => 'Failed to send SMS: ' . ($result['response'] ?? 'Unknown error')
                            ];
                        }
                    } else {
                        $failCount++;
                        $results[] = [
                            'user' => $user ? ($user['first_name'] . ' ' . $user['last_name']) : 'User ID: ' . $userId,
                            'mobile' => 'N/A',
                            'status' => 'failed',
                            'message' => 'User has no phone number'
                        ];
                    }
                }
                
                if ($successCount > 0) {
                    $message = "SMS sent successfully to {$successCount} user(s).";
                    if ($failCount > 0) {
                        $message .= " {$failCount} failed.";
                    }
                } else {
                    $error = "Failed to send SMS to all selected users.";
                }
                
                // Store results in session for display
                $_SESSION['sms_results'] = $results;
            }
            break;
    }
}

$sms_results = $_SESSION['sms_results'] ?? [];
unset($_SESSION['sms_results']);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
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

        // Initialize dark mode based on browser preference
        function initDarkMode() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // If user has saved preference, use it; otherwise use system preference
            if (savedDarkMode === 'true' || (savedDarkMode === null && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            const savedDarkMode = localStorage.getItem('darkMode');
            // Only auto-switch if user hasn't manually set a preference
            if (savedDarkMode === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    </script>
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        /* Placeholder styles for dark mode */
        textarea::placeholder {
            color: #9ca3af;
        }
        
        .dark textarea::placeholder {
            color: #6b7280;
        }
        
        input::placeholder {
            color: #9ca3af;
        }
        
        .dark input::placeholder {
            color: #6b7280;
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">SMS Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Send SMS messages to users</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SMS Results -->
                <?php if (!empty($sms_results)): ?>
                    <div class="mb-6 bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">SMS Results</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mobile</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($sms_results as $result): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($result['user']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($result['mobile']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($result['status'] === 'success'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        Success
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                        Failed
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($result['message']); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SMS Form with Tabs -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <!-- Tabs -->
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px">
                            <button type="button" onclick="switchTab('all-users')" 
                                    id="tab-all-users"
                                    class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400">
                                <i class="fas fa-users mr-2"></i>
                                All Users
                            </button>
                            <button type="button" onclick="switchTab('flight-sms')" 
                                    id="tab-flight-sms"
                                    class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                                <i class="fas fa-plane mr-2"></i>
                                Flight SMS
                            </button>
                        </nav>
                    </div>
                    
                    <!-- All Users Tab -->
                    <div id="tab-content-all-users" class="tab-content">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Send SMS to All Users</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Select users and compose your message</p>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="send_sms">
                        
                        <!-- User Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select Users <span class="text-red-500">*</span>
                            </label>
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <button type="button" onclick="selectAllUsers()" 
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        <i class="fas fa-check-square mr-1"></i>Select All
                                    </button>
                                    <button type="button" onclick="deselectAllUsers()" 
                                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                                        <i class="fas fa-square mr-1"></i>Deselect All
                                    </button>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <span id="selected-count">0</span> user(s) selected
                                </div>
                            </div>
                            
                            <!-- Search -->
                            <div class="mb-4">
                                <input type="text" id="user-search" placeholder="Search users..." 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       onkeyup="filterUsers()">
                            </div>
                            
                            <!-- Users List -->
                            <div class="border border-gray-300 dark:border-gray-600 rounded-md max-h-96 overflow-y-auto bg-gray-50 dark:bg-gray-700">
                                <div class="p-4 space-y-2">
                                    <?php foreach ($all_users as $user): ?>
                                        <label class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-600 rounded user-item" 
                                               data-name="<?php 
                                               $phone = $user['mobile'] ?? $user['phone'] ?? $user['alternative_mobile'] ?? '';
                                               echo htmlspecialchars(strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . ($user['position'] ?? '') . ' ' . $phone)); 
                                               ?>">
                                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                                   class="user-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-600 dark:border-gray-500"
                                                   onchange="updateSelectedCount()">
                                            <div class="ml-3 flex-1">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?> - 
                                                    <?php 
                                                    $phone = $user['mobile'] ?? $user['phone'] ?? $user['alternative_mobile'] ?? 'No phone';
                                                    echo htmlspecialchars($phone); 
                                                    ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SMS Text -->
                        <div class="mb-6">
                            <label for="sms_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                SMS Text <span class="text-red-500">*</span>
                            </label>
                            <textarea id="sms_text" name="sms_text" rows="6" required
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                      placeholder="Enter your SMS message here..."></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span id="char-count">0</span> characters
                            </p>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="confirmSendSMS()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Send SMS
                            </button>
                        </div>
                    </form>
                    </div>
                    
                    <!-- Flight SMS Tab -->
                    <div id="tab-content-flight-sms" class="tab-content hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Send SMS to Flight Crew</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Select date and crew members from flights</p>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="send_flight_sms">
                            
                            <!-- Date Selection -->
                            <div class="mb-6">
                                <label for="flight_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Flight Date <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center space-x-3">
                                    <input type="date" id="flight_date" name="flight_date" value="<?php echo htmlspecialchars($flight_date); ?>" required
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           onchange="loadFlightCrew()">
                                    <button type="button" onclick="loadFlightCrew()" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-search mr-2"></i>
                                        Load Crew
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Select a date to load crew members assigned to flights on that date
                                </p>
                            </div>
                            
                            <!-- Flight Crew Selection -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Select Crew Members <span class="text-red-500">*</span>
                                </label>
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <button type="button" onclick="selectAllFlightCrew()" 
                                                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            <i class="fas fa-check-square mr-1"></i>Select All
                                        </button>
                                        <button type="button" onclick="deselectAllFlightCrew()" 
                                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                                            <i class="fas fa-square mr-1"></i>Deselect All
                                        </button>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <span id="flight-selected-count">0</span> crew member(s) selected
                                    </div>
                                </div>
                                
                                <!-- Search -->
                                <div class="mb-4">
                                    <input type="text" id="flight-user-search" placeholder="Search crew members..." 
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           onkeyup="filterFlightCrew()">
                                </div>
                                
                                <!-- Flight Crew List -->
                                <div class="border border-gray-300 dark:border-gray-600 rounded-md max-h-96 overflow-y-auto bg-gray-50 dark:bg-gray-700">
                                    <div class="p-4 space-y-2">
                                        <?php if (empty($flight_crew_users)): ?>
                                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-plane text-4xl mb-4"></i>
                                                <p class="text-sm">No crew members found for selected date.</p>
                                                <p class="text-xs mt-2">Please select a date and click "Load Crew" to load crew members.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($flight_crew_users as $user): ?>
                                                <label class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-600 rounded flight-crew-item" 
                                                       data-name="<?php 
                                                       $phone = $user['mobile'] ?? $user['phone'] ?? $user['alternative_mobile'] ?? '';
                                                       echo htmlspecialchars(strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . ($user['position'] ?? '') . ' ' . $phone)); 
                                                       ?>">
                                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                                           class="flight-crew-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-600 dark:border-gray-500"
                                                           onchange="updateFlightSelectedCount()">
                                                    <div class="ml-3 flex-1">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?> - 
                                                            <?php 
                                                            $phone = $user['mobile'] ?? $user['phone'] ?? $user['alternative_mobile'] ?? 'No phone';
                                                            echo htmlspecialchars($phone); 
                                                            ?>
                                                        </div>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SMS Text -->
                            <div class="mb-6">
                                <label for="flight_sms_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    SMS Text <span class="text-red-500">*</span>
                                </label>
                                <textarea id="flight_sms_text" name="sms_text" rows="6" required
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Enter your SMS message here..."></textarea>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span id="flight-char-count">0</span> characters
                                </p>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="confirmSendFlightSMS()" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Send SMS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Send SMS Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Confirm Send SMS</h3>
                    <button onclick="closeConfirmModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Are you sure you want to send SMS to <span id="confirm-count" class="font-medium">0</span> selected user(s)?
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" id="flight-confirm-info" style="display: none;">
                        Flight Date: <span id="flight-confirm-date" class="font-medium"></span><br>
                        Crew Members: <span id="flight-confirm-count" class="font-medium">0</span>
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeConfirmModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button" onclick="submitSMSForm()"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                        Confirm & Send
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update character count
        document.getElementById('sms_text').addEventListener('input', function() {
            document.getElementById('char-count').textContent = this.value.length;
        });

        // Update selected count
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkboxes.length;
            const selectedCountEl = document.getElementById('selected-count');
            const confirmCountEl = document.getElementById('confirm-count');
            
            if (selectedCountEl) {
                selectedCountEl.textContent = count;
            }
            if (confirmCountEl) {
                confirmCountEl.textContent = count;
            }
        }

        // Select all users
        function selectAllUsers() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                if (checkbox.closest('.user-item').style.display !== 'none') {
                    checkbox.checked = true;
                }
            });
            updateSelectedCount();
        }

        // Deselect all users
        function deselectAllUsers() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        // Filter users
        function filterUsers() {
            const searchTerm = document.getElementById('user-search').value.toLowerCase();
            document.querySelectorAll('.user-item').forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Confirm send SMS
        function confirmSendSMS() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one user.');
                return;
            }
            
            const smsText = document.getElementById('sms_text').value.trim();
            if (!smsText) {
                alert('Please enter SMS text.');
                return;
            }
            
            updateSelectedCount();
            // Hide flight date info for regular SMS
            document.getElementById('flight-confirm-info').style.display = 'none';
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        // Close confirm modal
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        // Submit SMS form
        function submitSMSForm() {
            // Find the active form (either all-users or flight-sms)
            const allUsersForm = document.querySelector('#tab-content-all-users form');
            const flightSMSForm = document.querySelector('#tab-content-flight-sms form');
            
            // Check which tab is active
            if (!document.getElementById('tab-content-all-users').classList.contains('hidden')) {
                if (allUsersForm) {
                    allUsersForm.submit();
                }
            } else if (!document.getElementById('tab-content-flight-sms').classList.contains('hidden')) {
                if (flightSMSForm) {
                    flightSMSForm.submit();
                }
            }
        }

        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            });
            
            // Show selected tab content
            document.getElementById('tab-content-' + tabName).classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            activeTab.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        }

        // Load flight crew
        function loadFlightCrew() {
            const date = document.getElementById('flight_date').value;
            if (!date) {
                alert('Please select a date.');
                return;
            }
            window.location.href = '?flight_date=' + date;
        }

        // Update flight selected count
        function updateFlightSelectedCount() {
            const checkboxes = document.querySelectorAll('.flight-crew-checkbox:checked');
            const count = checkboxes.length;
            const selectedCountEl = document.getElementById('flight-selected-count');
            const confirmCountEl = document.getElementById('flight-confirm-count');
            
            if (selectedCountEl) {
                selectedCountEl.textContent = count;
            }
            if (confirmCountEl) {
                confirmCountEl.textContent = count;
            }
        }

        // Select all flight crew
        function selectAllFlightCrew() {
            document.querySelectorAll('.flight-crew-checkbox').forEach(checkbox => {
                if (checkbox.closest('.flight-crew-item').style.display !== 'none') {
                    checkbox.checked = true;
                }
            });
            updateFlightSelectedCount();
        }

        // Deselect all flight crew
        function deselectAllFlightCrew() {
            document.querySelectorAll('.flight-crew-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateFlightSelectedCount();
        }

        // Filter flight crew
        function filterFlightCrew() {
            const searchTerm = document.getElementById('flight-user-search').value.toLowerCase();
            document.querySelectorAll('.flight-crew-item').forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Confirm send flight SMS
        function confirmSendFlightSMS() {
            const checkboxes = document.querySelectorAll('.flight-crew-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one crew member.');
                return;
            }
            
            const smsText = document.getElementById('flight_sms_text').value.trim();
            if (!smsText) {
                alert('Please enter SMS text.');
                return;
            }
            
            const flightDate = document.getElementById('flight_date').value;
            if (!flightDate) {
                alert('Please select a flight date.');
                return;
            }
            
            updateFlightSelectedCount();
            // Show flight date in modal
            document.getElementById('flight-confirm-date').textContent = flightDate;
            document.getElementById('flight-confirm-info').style.display = 'block';
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        // Update flight character count
        document.getElementById('flight_sms_text')?.addEventListener('input', function() {
            document.getElementById('flight-char-count').textContent = this.value.length;
        });

        // Initialize
        updateSelectedCount();
        
        // Only update flight selected count if flight SMS tab is active or elements exist
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('flight_date')) {
            switchTab('flight-sms');
            // Wait a bit for DOM to be ready
            setTimeout(() => {
                updateFlightSelectedCount();
            }, 100);
        } else {
            // Initialize flight selected count if elements exist (check safely)
            setTimeout(() => {
                const selectedCountEl = document.getElementById('flight-selected-count');
                if (selectedCountEl) {
                    updateFlightSelectedCount();
                }
            }, 100);
        }
    </script>
</body>
</html>

