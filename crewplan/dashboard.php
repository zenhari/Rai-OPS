<?php
require_once '../config.php';

// Check if user is logged in and has flight_crew access (flight_crew == 1)
if (!isLoggedIn()) {
    header('Location: /crewplan/');
    exit();
}

$current_user = getCurrentUser();
if (!$current_user || !isset($current_user['flight_crew']) || $current_user['flight_crew'] != 1) {
    header('Location: /crewplan/');
    exit();
}

// Get pilot's flights
$pilotFlights = getPilotFlights($current_user['id']);

// Get roster assignments for current user (current month)
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$rosterAssignments = getUserRosterAssignments($current_user['id'], $startDate, $endDate);

// Calculate statistics
$totalFlights = count($pilotFlights);
$totalHours = 0;
$upcomingFlights = 0;
$completedFlights = 0;

foreach ($pilotFlights as $flight) {
    if ($flight['FlightHours']) {
        $totalHours += floatval($flight['FlightHours']);
    }
    
    $flightDate = $flight['TaskStart'] ?: $flight['FltDate'];
    if ($flightDate) {
        $flightDateTime = new DateTime($flightDate);
        $now = new DateTime();
        
        if ($flightDateTime > $now) {
            $upcomingFlights++;
        } else {
            $completedFlights++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#16a34a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Crew Plan">
    <meta name="description" content="Pilot Flight Schedule Portal for Raimon Airways">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#16a34a">
    <meta name="msapplication-config" content="browserconfig.xml">
    
    <title>Crew Plan Dashboard - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="72x72" href="icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="icons/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="icons/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="icons/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="icons/icon-512x512.png">
    
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
        
        /* PWA Install Prompt Styles */
        .pwa-install-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #16a34a;
            color: white;
            padding: 1rem;
            display: none;
            z-index: 9999;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .pwa-install-banner.show {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .pwa-install-banner button {
            background: white;
            color: #16a34a;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .pwa-install-banner button:hover {
            background: #f3f4f6;
        }
        
        .pwa-install-banner button.secondary {
            background: transparent;
            color: white;
            border: 1px solid white;
        }
        
        .pwa-install-banner button.secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        @media (max-width: 640px) {
            .pwa-install-banner {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center">
                        <?php 
                        $userPicture = isset($current_user['picture']) && !empty($current_user['picture']) ? $current_user['picture'] : null;
                        if ($userPicture) {
                            // Remove crewplan/ from path if exists and ensure path starts with ../uploads/ or /uploads/
                            $picturePath = $userPicture;
                            // Remove crewplan/ if it exists in the path
                            $picturePath = str_replace('crewplan/', '', $picturePath);
                            $picturePath = str_replace('crewplan\\', '', $picturePath);
                            // If path doesn't start with uploads/, add it
                            if (strpos($picturePath, 'uploads/') !== 0) {
                                $picturePath = 'uploads/profile/' . basename($picturePath);
                            }
                            // Use relative path from crewplan directory
                            $picturePath = '../' . $picturePath;
                        ?>
                        <div class="h-10 w-10 rounded-full overflow-hidden mr-4 flex-shrink-0 border-2 border-green-600 dark:border-green-500">
                            <img src="<?php echo htmlspecialchars($picturePath); ?>" 
                                 alt="<?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>" 
                                 class="h-full w-full object-cover"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%2310b981\'%3E%3Cpath d=\'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z\'/%3E%3C/svg%3E'; this.parentElement.classList.add('bg-green-600'); this.parentElement.innerHTML='<i class=\"fas fa-user text-white\"></i>';">
                        </div>
                        <?php } else { ?>
                        <div class="h-10 w-10 bg-green-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <?php } ?>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Crew Plan Dashboard</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="logout.php" 
                           class="inline-flex items-center p-2 text-gray-600 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 focus:outline-none transition-colors duration-200"
                           title="Logout">
                            <i class="fas fa-sign-out-alt text-lg"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6 mb-4 sm:mb-6 lg:mb-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-3 sm:p-4 lg:p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-plane text-blue-500 text-lg sm:text-xl lg:text-2xl"></i>
                            </div>
                            <div class="ml-2 sm:ml-3 lg:ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Flights</dt>
                                    <dd class="text-sm sm:text-base lg:text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($totalFlights); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-3 sm:p-4 lg:p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-green-500 text-lg sm:text-xl lg:text-2xl"></i>
                            </div>
                            <div class="ml-2 sm:ml-3 lg:ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Hours</dt>
                                    <dd class="text-sm sm:text-base lg:text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($totalHours * 60); ?> min</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-3 sm:p-4 lg:p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-calendar-plus text-orange-500 text-lg sm:text-xl lg:text-2xl"></i>
                            </div>
                            <div class="ml-2 sm:ml-3 lg:ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Upcoming</dt>
                                    <dd class="text-sm sm:text-base lg:text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($upcomingFlights); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-3 sm:p-4 lg:p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-purple-500 text-lg sm:text-xl lg:text-2xl"></i>
                            </div>
                            <div class="ml-2 sm:ml-3 lg:ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Completed</dt>
                                    <dd class="text-sm sm:text-base lg:text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($completedFlights); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-3 sm:px-6 py-3 sm:py-4 border-b border-gray-200 dark:border-gray-700">
                    <!-- Desktop/Tablet Layout -->
                    <div class="hidden sm:flex items-center justify-between">
                        <div>
                    <h2 class="text-base sm:text-lg font-medium text-gray-900 dark:text-white">Flight Calendar</h2>
                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mt-1">Your flight schedule by date</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- View Switcher -->
                            <div class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                                <button onclick="switchView('day')" id="viewDayBtn"
                                        class="inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 view-btn">
                                    <i class="fas fa-calendar-day mr-1.5"></i>
                                    <span>Day</span>
                                </button>
                                <button onclick="switchView('week')" id="viewWeekBtn"
                                        class="inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 view-btn">
                                    <i class="fas fa-calendar-week mr-1.5"></i>
                                    <span>Week</span>
                                </button>
                                <button onclick="switchView('month')" id="viewMonthBtn"
                                        class="inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 view-btn active">
                                    <i class="fas fa-calendar-alt mr-1.5"></i>
                                    <span>Month</span>
                                </button>
                            </div>
                            <!-- Navigation -->
                            <div class="flex items-center space-x-2">
                                <button onclick="previousPeriod()" 
                                        class="inline-flex items-center justify-center w-9 h-9 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-chevron-left text-xs"></i>
                                </button>
                                <span id="currentPeriodDisplay" class="text-sm font-medium text-gray-900 dark:text-white min-w-[140px] text-center px-2">
                                    <!-- Period will be populated by JavaScript -->
                                </span>
                                <button onclick="nextPeriod()" 
                                        class="inline-flex items-center justify-center w-9 h-9 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile Layout -->
                    <div class="sm:hidden space-y-3">
                        <!-- Title -->
                        <div>
                            <h2 class="text-base font-medium text-gray-900 dark:text-white">Flight Calendar</h2>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">Your flight schedule by date</p>
                        </div>
                        
                        <!-- View Switcher - Mobile -->
                        <div class="flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button onclick="switchView('day')" id="viewDayBtnMobile"
                                    class="flex-1 inline-flex items-center justify-center px-2 py-2 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 view-btn-mobile">
                                <i class="fas fa-calendar-day mr-1.5"></i>
                                <span>Day</span>
                            </button>
                            <button onclick="switchView('week')" id="viewWeekBtnMobile"
                                    class="flex-1 inline-flex items-center justify-center px-2 py-2 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 view-btn-mobile">
                                <i class="fas fa-calendar-week mr-1.5"></i>
                                <span>Week</span>
                            </button>
                            <button onclick="switchView('month')" id="viewMonthBtnMobile"
                                    class="flex-1 inline-flex items-center justify-center px-2 py-2 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 view-btn-mobile active">
                                <i class="fas fa-calendar-alt mr-1.5"></i>
                                <span>Month</span>
                            </button>
                        </div>
                        
                        <!-- Navigation - Mobile -->
                        <div class="flex items-center justify-between">
                            <button onclick="previousPeriod()" 
                                    class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 active:bg-gray-100 dark:active:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span id="currentPeriodDisplayMobile" class="text-sm font-semibold text-gray-900 dark:text-white text-center flex-1 px-2">
                                <!-- Period will be populated by JavaScript -->
                            </span>
                            <button onclick="nextPeriod()" 
                                    class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 active:bg-gray-100 dark:active:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 sm:p-6">
                    <div class="calendar-wrapper overflow-x-auto">
                    <style>
                        /* View switcher styles */
                        .view-btn.active,
                        .view-btn-mobile.active {
                            background-color: rgb(59, 130, 246); /* bg-blue-500 */
                            color: white;
                            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
                        }
                        
                        .view-btn.active:hover,
                        .view-btn-mobile.active:hover {
                            background-color: rgb(37, 99, 235); /* bg-blue-600 */
                        }
                        
                        /* Mobile view switcher container */
                        .view-btn-mobile {
                            min-width: 0;
                            flex: 1;
                        }
                        
                        /* Mobile optimizations */
                        @media (max-width: 640px) {
                            .view-btn-mobile {
                                font-size: 11px;
                                padding: 8px 4px;
                            }
                            
                            .view-btn-mobile i {
                                font-size: 12px;
                            }
                        }
                        
                        /* Week view styles */
                        .week-view {
                            display: grid;
                            grid-template-columns: repeat(7, 1fr);
                            gap: 1px;
                            background-color: #e5e7eb;
                            border: 1px solid #e5e7eb;
                            border-radius: 8px;
                            overflow: hidden;
                        }
                        
                        .dark .week-view {
                            background-color: #374151;
                            border-color: #374151;
                        }
                        
                        /* Day view styles */
                        .day-view {
                            display: flex;
                            flex-direction: column;
                            background-color: white;
                            border: 1px solid #e5e7eb;
                            border-radius: 8px;
                            overflow: hidden;
                        }
                        
                        .dark .day-view {
                            background-color: #1f2937;
                            border-color: #374151;
                        }
                        
                        .day-view-header {
                            background-color: #f3f4f6;
                            padding: 16px;
                            text-align: center;
                            font-weight: 600;
                            font-size: 16px;
                            color: #374151;
                            border-bottom: 1px solid #d1d5db;
                        }
                        
                        .dark .day-view-header {
                            background-color: #4b5563;
                            color: #d1d5db;
                            border-bottom-color: #6b7280;
                        }
                        
                        .day-view-content {
                            padding: 16px;
                            min-height: 400px;
                        }
                        
                        .day-view-time-slot {
                            border-bottom: 1px solid #e5e7eb;
                            padding: 12px 0;
                        }
                        
                        .dark .day-view-time-slot {
                            border-bottom-color: #374151;
                        }
                        
                        .day-view-time-label {
                            font-weight: 600;
                            font-size: 14px;
                            color: #374151;
                            margin-bottom: 8px;
                        }
                        
                        .dark .day-view-time-label {
                            color: #d1d5db;
                        }
                        
                        @media (max-width: 768px) {
                            .day-view-content {
                                padding: 12px;
                                min-height: 300px;
                            }
                            
                            .day-view-time-slot {
                                padding: 8px 0;
                            }
                        }
                        
                        .calendar {
                            display: grid;
                            grid-template-columns: repeat(7, 1fr);
                            gap: 1px;
                            background-color: #e5e7eb;
                            border: 1px solid #e5e7eb;
                            border-radius: 8px;
                            overflow: hidden;
                        }
                        
                        .dark .calendar {
                            background-color: #374151;
                            border-color: #374151;
                        }
                        
                        .calendar-weekdays {
                            display: contents;
                        }
                        
                        .weekday {
                            background-color: #f3f4f6;
                            padding: 12px 8px;
                            text-align: center;
                            font-weight: 600;
                            font-size: 14px;
                            color: #374151;
                            border-bottom: 1px solid #d1d5db;
                        }
                        
                        .dark .weekday {
                            background-color: #4b5563;
                            color: #d1d5db;
                            border-bottom-color: #6b7280;
                        }
                        
                        .calendar-days {
                            display: contents;
                        }
                        
                        .calendar-day {
                            background-color: white;
                            min-height: 120px;
                            padding: 8px;
                            border-right: 1px solid #e5e7eb;
                            border-bottom: 1px solid #e5e7eb;
                            position: relative;
                            cursor: pointer;
                            transition: background-color 0.2s;
                            overflow: hidden;
                            word-wrap: break-word;
                        }
                        
                        .dark .calendar-day {
                            background-color: #1f2937;
                            border-right-color: #374151;
                            border-bottom-color: #374151;
                        }
                        
                        .calendar-day:hover {
                            background-color: #f9fafb;
                        }
                        
                        .dark .calendar-day:hover {
                            background-color: #374151;
                        }
                        
                        .calendar-day.other-month {
                            background-color: #f9fafb;
                            color: #9ca3af;
                        }
                        
                        .dark .calendar-day.other-month {
                            background-color: #111827;
                            color: #6b7280;
                        }
                        
                        .calendar-day.today {
                            background-color: #dbeafe;
                            border: 2px solid #3b82f6;
                        }
                        
                        .dark .calendar-day.today {
                            background-color: #1e3a8a;
                            border-color: #60a5fa;
                        }
                        
                        .calendar-day.today .day-number {
                            background-color: #3b82f6;
                            color: white;
                            border-radius: 50%;
                            width: 24px;
                            height: 24px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-weight: 600;
                        }
                        
                        .dark .calendar-day.today .day-number {
                            background-color: #60a5fa;
                        }
                        
                        .day-number {
                            font-weight: 600;
                            font-size: 14px;
                            margin-bottom: 4px;
                            color: #111827;
                        }
                        
                        .dark .day-number {
                            color: #f9fafb;
                        }
                        
                        .classes-container {
                            display: flex;
                            flex-direction: column;
                            gap: 2px;
                        }
                        
                        .class-item {
                            /* Tailwind: bg-blue-500 text-white py-1 px-1.5 rounded text-[11px] font-medium text-center cursor-pointer transition-colors */
                            background-color: rgb(59, 130, 246); /* bg-blue-500 */
                            color: rgb(255, 255, 255); /* text-white */
                            padding: 4px 6px; /* py-1 px-1.5 */
                            border-radius: 4px; /* rounded */
                            font-size: 11px; /* text-[11px] */
                            font-weight: 500; /* font-medium */
                            text-align: center;
                            cursor: pointer;
                            transition: background-color 0.2s;
                            line-height: 1.2;
                        }
                        
                        .class-item:hover {
                            background-color: rgb(37, 99, 235); /* bg-blue-600 */
                        }
                        
                        .class-item.class-a {
                            background-color: rgb(59, 130, 246); /* bg-blue-500 */
                        }
                        
                        .class-item.class-a:hover {
                            background-color: rgb(37, 99, 235); /* bg-blue-600 */
                        }
                        
                        .class-item.class-b {
                            background-color: rgb(16, 185, 129); /* bg-emerald-500 */
                        }
                        
                        .class-item.class-b:hover {
                            background-color: rgb(5, 150, 105); /* bg-emerald-600 */
                        }
                        
                        .class-item.class-c {
                            background-color: rgb(245, 158, 11); /* bg-amber-500 */
                        }
                        
                        .class-item.class-c:hover {
                            background-color: rgb(217, 119, 6); /* bg-amber-600 */
                        }
                        
                        .class-item.class-d {
                            background-color: rgb(239, 68, 68); /* bg-red-500 */
                        }
                        
                        .class-item.class-d:hover {
                            background-color: rgb(220, 38, 38); /* bg-red-600 */
                        }
                        
                        .class-item.class-e {
                            background-color: rgb(139, 92, 246); /* bg-violet-500 */
                        }
                        
                        .class-item.class-e:hover {
                            background-color: rgb(124, 58, 237); /* bg-violet-600 */
                        }
                        
                        .class-item.class-on-block {
                            background-color: rgb(107, 114, 128); /* bg-gray-500 */
                        }
                        
                        .class-item.class-on-block:hover {
                            background-color: rgb(75, 85, 99); /* bg-gray-600 */
                        }
                        
                        @media (max-width: 768px) {
                            .calendar-day {
                                min-height: 80px;
                                padding: 4px;
                            }
                            
                            .weekday {
                                padding: 8px 4px;
                                font-size: 12px;
                            }
                            
                            .day-number {
                                font-size: 12px;
                            }
                            
                            .class-item {
                                font-size: 10px;
                                padding: 1px 4px;
                            }
                        }
                        
                        /* Mobile-specific styles */
                        @media (max-width: 640px) {
                            .calendar {
                                border-radius: 4px;
                                gap: 0.5px;
                                width: 100%;
                                overflow-x: auto;
                                -webkit-overflow-scrolling: touch;
                            }
                            
                            /* Ensure calendar doesn't overflow on mobile */
                            .calendar-wrapper {
                                overflow-x: auto;
                                -webkit-overflow-scrolling: touch;
                            }
                            
                            .calendar-day {
                                min-height: 70px;
                                min-width: 40px;
                                padding: 2px;
                                font-size: 10px;
                            }
                            
                            .calendar {
                                grid-template-columns: repeat(7, minmax(40px, 1fr));
                            }
                            
                            .weekday {
                                padding: 6px 2px;
                                font-size: 10px;
                                font-weight: 500;
                            }
                            
                            /* Shorten weekday names on mobile */
                            .weekday {
                                font-size: 0;
                                position: relative;
                            }
                            
                            .weekday::before {
                                content: attr(data-short);
                                font-size: 10px;
                                font-weight: 600;
                                display: block;
                            }
                            
                            .day-number {
                                font-size: 11px;
                                margin-bottom: 2px;
                                line-height: 1.2;
                            }
                            
                            .classes-container {
                                gap: 1px;
                                max-height: 60px;
                                overflow-y: auto;
                            }
                            
                            .class-item {
                                font-size: 9px;
                                padding: 2px 3px;
                                line-height: 1.2;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                                max-width: 100%;
                            }
                            
                            .calendar-day.today {
                                border-width: 1px;
                            }
                            
                            .calendar-day.today .day-number {
                                width: 20px;
                                height: 20px;
                                font-size: 10px;
                            }
                        }
                        
                        /* Extra small mobile devices */
                        @media (max-width: 375px) {
                            .calendar-day {
                                min-height: 60px;
                                padding: 1px;
                            }
                            
                            .weekday {
                                padding: 4px 1px;
                                font-size: 9px;
                            }
                            
                            .day-number {
                                font-size: 10px;
                            }
                            
                            .class-item {
                                font-size: 8px;
                                padding: 1px 2px;
                            }
                            
                            .classes-container {
                                max-height: 50px;
                            }
                        }
                    </style>
                    
                    <div class="calendar" id="calendarContainer">
                        <div class="calendar-weekdays" id="calendarWeekdays">
                            <div class="weekday" data-short="Sun">Sunday</div>
                            <div class="weekday" data-short="Mon">Monday</div>
                            <div class="weekday" data-short="Tue">Tuesday</div>
                            <div class="weekday" data-short="Wed">Wednesday</div>
                            <div class="weekday" data-short="Thu">Thursday</div>
                            <div class="weekday" data-short="Fri">Friday</div>
                            <div class="weekday" data-short="Sat">Saturday</div>
                                                    </div>
                        <div class="calendar-days" id="calendarDays">
                            <!-- Calendar days will be generated by JavaScript -->
                                                </div>
                    </div>
                    
                    <!-- Roster Assignments Table -->
                    <?php if (!empty($rosterAssignments)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mt-4 sm:mt-6 lg:mt-8">
                        <div class="px-3 sm:px-6 py-3 sm:py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900 dark:text-white">Roster Assignments</h2>
                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mt-1">Your shift code assignments for this month</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Day</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Shift Code</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($rosterAssignments as $assignment): ?>
                                        <?php 
                                        $date = new DateTime($assignment['date']);
                                        $dayName = $date->format('l');
                                        $dayNumber = $date->format('j');
                                        $monthName = $date->format('F');
                                        $year = $date->format('Y');
                                        $isFromFlights = isset($assignment['from_flights']) && $assignment['from_flights'];
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($dayNumber . ' ' . $monthName . ' ' . $year); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($dayName); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <?php if (!empty($assignment['shift_code'])): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" 
                                                          style="background-color: <?php echo htmlspecialchars($assignment['background_color'] ?? '#ffffff'); ?>; color: <?php echo htmlspecialchars($assignment['text_color'] ?? '#000000'); ?>;">
                                                        <?php echo htmlspecialchars(strtoupper($assignment['shift_code'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php if ($isFromFlights): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <i class="fas fa-plane mr-1"></i>From Flights
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        <i class="fas fa-calendar-check mr-1"></i>Manual
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                                            </div>
                                    </div>
                                </div>
            </div>
        </div>
    </div>

    <!-- Flight Details Modal -->
    <div id="flightDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Flight Details</h3>
                    <button onclick="closeFlightDetailsModal()" 
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="flightDetailsContent" class="space-y-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeFlightDetailsModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Flight data from PHP
        const pilotFlights = <?php echo json_encode($pilotFlights); ?>;
        
        // Calendar state
        let currentView = 'month'; // 'day', 'week', or 'month'
        let currentDisplayMonth = new Date().getMonth();
        let currentDisplayYear = new Date().getFullYear();
        let currentDisplayDate = new Date(); // For day and week views
        
        // Initialize calendar when page loads
        document.addEventListener('DOMContentLoaded', function() {
            switchView('month');
        });
        
        // Switch view type
        function switchView(view) {
            currentView = view;
            
            // Update active button (desktop)
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const desktopBtn = document.getElementById('view' + view.charAt(0).toUpperCase() + view.slice(1) + 'Btn');
            if (desktopBtn) {
                desktopBtn.classList.add('active');
            }
            
            // Update active button (mobile)
            document.querySelectorAll('.view-btn-mobile').forEach(btn => {
                btn.classList.remove('active');
            });
            const mobileBtn = document.getElementById('view' + view.charAt(0).toUpperCase() + view.slice(1) + 'BtnMobile');
            if (mobileBtn) {
                mobileBtn.classList.add('active');
            }
            
            // Update calendar container
            const calendarContainer = document.getElementById('calendarContainer');
            const calendarWeekdays = document.getElementById('calendarWeekdays');
        
            // Show/hide weekday headers based on view
            if (view === 'day') {
                calendarWeekdays.style.display = 'none';
            } else {
                calendarWeekdays.style.display = 'contents';
            }
            
            // Remove existing calendar classes
            calendarContainer.className = '';
            
            // Generate view
            if (view === 'month') {
                generateMonthView();
            } else if (view === 'week') {
                generateWeekView();
            } else if (view === 'day') {
                generateDayView();
            }
            
            updatePeriodDisplay();
        }
        
        // Generate month view
        function generateMonthView() {
            const calendarDays = document.getElementById('calendarDays');
            const calendarContainer = document.getElementById('calendarContainer');
            calendarContainer.className = 'calendar';
            const today = new Date();
            const currentMonth = currentDisplayMonth;
            const currentYear = currentDisplayYear;
            
            // Get first day of current month and calculate starting date
            const firstDay = new Date(currentYear, currentMonth, 1);
            const startDate = new Date(currentYear, currentMonth, 1 - firstDay.getDay());
            
            // Generate 42 days (6 weeks)
            let calendarHTML = '';
            for (let i = 0; i < 42; i++) {
                const date = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + i);
                
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const dateString = `${year}-${month}-${day}`;
                
                const dayNumber = date.getDate();
                const isCurrentMonth = date.getMonth() === currentMonth;
                const isToday = date.toDateString() === today.toDateString();
                
                        const dayFlights = getFlightsForDate(dateString);
                        // Sort flights by TaskStart (earliest to latest)
                        dayFlights.sort((a, b) => {
                            const dateA = new Date(a.TaskStart || a.FltDate || 0);
                            const dateB = new Date(b.TaskStart || b.FltDate || 0);
                            return dateA - dateB;
                        });
                        let classesHTML = '';
                        dayFlights.forEach((flight, index) => {
                            const flightClass = getFlightClass(flight, index);
                            const route = flight.Route || flight.ScheduledRoute || '';
                            const taskStart = flight.TaskStart || flight.FltDate;
                            let timeDisplay = '';
                            
                            if (taskStart) {
                                const taskDate = new Date(taskStart);
                                const hours = taskDate.getHours();
                                const minutes = taskDate.getMinutes();
                                const displayHours = hours.toString().padStart(2, '0');
                                const displayMinutes = minutes.toString().padStart(2, '0');
                                timeDisplay = `${displayHours}:${displayMinutes}`;
                            }
                            
                            let displayText = '';
                            if (route) {
                                displayText = route;
                            }
                            if (timeDisplay) {
                                if (displayText) {
                                    displayText += ` - ${timeDisplay}`;
                                } else {
                                    displayText = timeDisplay;
                                }
                            }
                            
                            classesHTML += `
                                <div class="class-item ${flightClass}" onclick="showFlightDetails(${JSON.stringify(flight).replace(/"/g, '&quot;')})" title="${displayText}">
                                    ${displayText}
                                </div>
                            `;
                        });
                
                const dayClass = `calendar-day ${!isCurrentMonth ? 'other-month' : ''} ${isToday ? 'today' : ''}`;
                
                calendarHTML += `
                    <div class="${dayClass}" data-date="${dateString}">
                        <div class="day-number">${dayNumber}</div>
                        <div class="classes-container">${classesHTML}</div>
                    </div>
                `;
            }
            
            calendarDays.innerHTML = calendarHTML;
        }
        
        // Generate week view
        function generateWeekView() {
            const calendarDays = document.getElementById('calendarDays');
            const calendarContainer = document.getElementById('calendarContainer');
            calendarContainer.className = 'week-view';
            const today = new Date();
            
            // Get start of week (Sunday)
            const weekStart = new Date(currentDisplayDate);
            weekStart.setDate(currentDisplayDate.getDate() - currentDisplayDate.getDay());
            weekStart.setHours(0, 0, 0, 0);
            
            let calendarHTML = '';
            for (let i = 0; i < 7; i++) {
                const date = new Date(weekStart);
                date.setDate(weekStart.getDate() + i);
                
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const dateString = `${year}-${month}-${day}`;
                
                const dayNumber = date.getDate();
                const isToday = date.toDateString() === today.toDateString();
                
                const dayFlights = getFlightsForDate(dateString);
                // Sort flights by TaskStart (earliest to latest)
                dayFlights.sort((a, b) => {
                    const dateA = new Date(a.TaskStart || a.FltDate || 0);
                    const dateB = new Date(b.TaskStart || b.FltDate || 0);
                    return dateA - dateB;
                });
                let classesHTML = '';
                dayFlights.forEach((flight, index) => {
                    const flightClass = getFlightClass(flight, index);
                    const route = flight.Route || flight.ScheduledRoute || '';
                    const taskStart = flight.TaskStart || flight.FltDate;
                    let timeDisplay = '';
                    
                    if (taskStart) {
                        const taskDate = new Date(taskStart);
                        const hours = taskDate.getHours();
                        const minutes = taskDate.getMinutes();
                        const displayHours = hours.toString().padStart(2, '0');
                        const displayMinutes = minutes.toString().padStart(2, '0');
                        timeDisplay = `${displayHours}:${displayMinutes}`;
                    }
                    
                    let displayText = '';
                    if (route) {
                        displayText = route;
                    }
                    if (timeDisplay) {
                        if (displayText) {
                            displayText += ` - ${timeDisplay}`;
                        } else {
                            displayText = timeDisplay;
                        }
                    }
                    
                    classesHTML += `
                        <div class="class-item ${flightClass}" onclick="showFlightDetails(${JSON.stringify(flight).replace(/"/g, '&quot;')})" title="${displayText}">
                            ${displayText}
                        </div>
                    `;
                });
                
                const dayClass = `calendar-day ${isToday ? 'today' : ''}`;
                const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                
                calendarHTML += `
                    <div class="${dayClass}" data-date="${dateString}">
                        <div class="day-number">
                            ${weekdayNames[i]} ${dayNumber}
                        </div>
                        <div class="classes-container">${classesHTML}</div>
                    </div>
                `;
            }
            
            calendarDays.innerHTML = calendarHTML;
        }
        
        // Generate day view
        function generateDayView() {
            const calendarDays = document.getElementById('calendarDays');
            const calendarContainer = document.getElementById('calendarContainer');
            calendarContainer.className = 'day-view';
            const today = new Date();
            
            const year = currentDisplayDate.getFullYear();
            const month = String(currentDisplayDate.getMonth() + 1).padStart(2, '0');
            const day = String(currentDisplayDate.getDate()).padStart(2, '0');
            const dateString = `${year}-${month}-${day}`;
            
            const isToday = currentDisplayDate.toDateString() === today.toDateString();
            const dayFlights = getFlightsForDate(dateString);
            
            // Sort flights by time
            dayFlights.sort((a, b) => {
                const dateA = new Date(a.TaskStart || a.FltDate || 0);
                const dateB = new Date(b.TaskStart || b.FltDate || 0);
                return dateA - dateB;
            });
            
            // Group flights by hour
            const flightsByHour = {};
            dayFlights.forEach(flight => {
                const taskStart = flight.TaskStart || flight.FltDate;
                if (taskStart) {
                    const taskDate = new Date(taskStart);
                    const hour = taskDate.getHours();
                    if (!flightsByHour[hour]) {
                        flightsByHour[hour] = [];
                    }
                    flightsByHour[hour].push(flight);
                } else {
                    if (!flightsByHour['all']) {
                        flightsByHour['all'] = [];
                    }
                    flightsByHour['all'].push(flight);
                }
            });
            
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            const weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            
            let calendarHTML = `
                <div class="day-view-header">
                    ${weekdayNames[currentDisplayDate.getDay()]}, ${monthNames[currentDisplayDate.getMonth()]} ${currentDisplayDate.getDate()}, ${currentDisplayDate.getFullYear()}
                    ${isToday ? '<span class="ml-2 text-xs text-blue-600 dark:text-blue-400">(Today)</span>' : ''}
                </div>
                <div class="day-view-content">
            `;
            
            // Generate time slots (24 hours)
            for (let hour = 0; hour < 24; hour++) {
                const hourFlights = flightsByHour[hour] || [];
                if (hourFlights.length === 0 && Object.keys(flightsByHour).length > 0) {
                    continue; // Skip empty hours
                }
                
                const timeLabel = `${hour.toString().padStart(2, '0')}:00`;
                let flightsHTML = '';
                
                hourFlights.forEach((flight, index) => {
                    const flightClass = getFlightClass(flight, index);
                    const route = flight.Route || flight.ScheduledRoute || '';
                    const taskStart = flight.TaskStart || flight.FltDate;
                    let timeDisplay = '';
                    
                    if (taskStart) {
                        const taskDate = new Date(taskStart);
                        const hours = taskDate.getHours();
                        const minutes = taskDate.getMinutes();
                        const displayHours = hours.toString().padStart(2, '0');
                        const displayMinutes = minutes.toString().padStart(2, '0');
                        timeDisplay = `${displayHours}:${displayMinutes}`;
                    }
                    
                    let displayText = '';
                    if (route) {
                        displayText = route;
                    }
                    if (timeDisplay) {
                        if (displayText) {
                            displayText += ` - ${timeDisplay}`;
                        } else {
                            displayText = timeDisplay;
                        }
                    }
                    
                    flightsHTML += `
                        <div class="class-item ${flightClass} mb-2" onclick="showFlightDetails(${JSON.stringify(flight).replace(/"/g, '&quot;')})" title="${displayText}">
                            ${displayText}
                        </div>
                    `;
                });
                
                if (hourFlights.length > 0 || Object.keys(flightsByHour).length === 0) {
                    calendarHTML += `
                        <div class="day-view-time-slot">
                            <div class="day-view-time-label">${timeLabel}</div>
                            ${flightsHTML}
                        </div>
                    `;
                }
            }
            
            // Add flights without time
            if (flightsByHour['all']) {
                calendarHTML += `
                    <div class="day-view-time-slot">
                        <div class="day-view-time-label">All Day</div>
                `;
                flightsByHour['all'].forEach((flight, index) => {
                    const flightClass = getFlightClass(flight, index);
                    const route = flight.Route || flight.ScheduledRoute || '';
                    let displayText = '';
                    if (route) {
                        displayText = route;
                    }
                    calendarHTML += `
                        <div class="class-item ${flightClass} mb-2" onclick="showFlightDetails(${JSON.stringify(flight).replace(/"/g, '&quot;')})" title="${displayText}">
                            ${displayText}
                        </div>
                    `;
                });
                calendarHTML += `</div>`;
            }
            
            if (dayFlights.length === 0) {
                calendarHTML += `
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-calendar-times text-4xl mb-4"></i>
                        <p>No flights scheduled for this day</p>
                    </div>
                `;
            }
            
            calendarHTML += `</div>`;
            calendarDays.innerHTML = calendarHTML;
        }
        
        // Get flights for a specific date
        function getFlightsForDate(dateString) {
            return pilotFlights.filter(flight => {
                const flightDate = flight.TaskStart || flight.FltDate;
                if (!flightDate) return false;
                
                try {
                    // Parse the date string and create a date object
                    const flightDateObj = new Date(flightDate);
                    
                    // Check if date is valid
                    if (isNaN(flightDateObj.getTime())) {
                        return false;
                    }
                    
                    // Get the date components in local timezone
                    const year = flightDateObj.getFullYear();
                    const month = String(flightDateObj.getMonth() + 1).padStart(2, '0');
                    const day = String(flightDateObj.getDate()).padStart(2, '0');
                    const flightDateString = `${year}-${month}-${day}`;
                    
                    return flightDateString === dateString;
                } catch (error) {
                    console.error('Error parsing flight date:', flightDate, error);
                    return false;
                }
            });
        }
        
        // Get CSS class for flight based on type/status
        function getFlightClass(flight, index) {
            const classes = ['class-a', 'class-b', 'class-c', 'class-d', 'class-e'];
            
            // Check if flight is "On Block" status
            if (flight.ScheduledTaskStatus === 'On Block') {
                return 'class-on-block'; // On Block flights - gray
            }
            
            // Check flight status
            const flightDate = flight.TaskStart || flight.FltDate;
            if (flightDate) {
                const flightDateTime = new Date(flightDate);
                const now = new Date();
                
                if (flightDateTime > now) {
                    return 'class-a'; // Upcoming flights - blue
                } else {
                    return 'class-b'; // Completed flights - green
                }
            }
            
            return classes[index % classes.length];
        }
        
        // Show flight details modal
        function showFlightDetails(flight) {
            const modal = document.getElementById('flightDetailsModal');
            const content = document.getElementById('flightDetailsContent');
            
            
            // Show loading state
            content.innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Loading crew details...</span>
                </div>
            `;
            
            modal.classList.remove('hidden');
            
            // Get crew members (Crew1-Crew10)
            const crewMembers = [];
            const crewRoles = [
                'Crew 1 (Left Seat Pilot)', 'Crew 2 (Right Seat Pilot)', 'Crew 3 (Co-Pilot)',
                'Crew 4 (Deadhead)', 'Crew 5 (Senior Cabin Crew)', 'Crew 6 (Cabin Crew)',
                'Crew 7', 'Crew 8', 'Crew 9', 'Crew 10'
            ];
            
            // Check Crew1-Crew10
            for (let i = 1; i <= 10; i++) {
                const crewField = `Crew${i}`;
                const crewRoleField = `${crewField}_role`;
                if (flight[crewField]) {
                    // Get role detail (e.g., PIC, FO, etc.)
                    const roleDetail = flight[crewRoleField] || flight[crewRoleField.toLowerCase()] || '';
                crewMembers.push({
                        id: flight[crewField],
                        role: crewRoles[i - 1] || `Crew ${i}`,
                        roleDetail: roleDetail
                    });
                }
            }
            
            // Fetch user details for all crew members
            const userIds = crewMembers.map(member => member.id);
            fetchCrewDetails(userIds, crewMembers, flight);
        }
        
        // Fetch crew details from server
        function fetchCrewDetails(userIds, crewMembers, flight) {
            // Fetch both crew details and driver assignments in parallel
            Promise.all([
            fetch('api/get_crew_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_ids: userIds })
                }).then(response => response.json()),
                fetch('api/get_driver_assignments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        flight_id: flight.FlightID,
                        assignment_date: flight.FltDate ? flight.FltDate.split(' ')[0] : null
                    })
                }).then(response => response.json())
            ])
            .then(([crewData, driverData]) => {
                const crewDetails = crewData.success ? crewData.users : {};
                const driverAssignments = driverData.success ? driverData.assignments : [];
                displayFlightDetails(flight, crewMembers, crewDetails, driverAssignments);
            })
            .catch(error => {
                console.error('Error fetching details:', error);
                displayFlightDetails(flight, crewMembers, {}, []);
            });
        }
        
        // Display flight details with crew information
        function displayFlightDetails(flight, crewMembers, userDetails, driverAssignments = []) {
            const content = document.getElementById('flightDetailsContent');
            
            // Calculate duration from TaskStart and TaskEnd
            let duration = '';
            if (flight.TaskStart && flight.TaskEnd) {
                const start = new Date(flight.TaskStart);
                const end = new Date(flight.TaskEnd);
                const diffMs = end - start;
                const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
                duration = `${diffHours} hours ${diffMinutes} minutes`;
            } else if (flight.block_time_min) {
                // Use block_time_min if available
                const totalMinutes = parseInt(flight.block_time_min);
                const hours = Math.floor(totalMinutes / 60);
                const minutes = totalMinutes % 60;
                duration = `${hours} hours ${minutes} minutes`;
            }
            
            // Format dates
            const formatDate = (dateString) => {
                if (!dateString) return 'N/A';
                
                // Handle different date formats
                let date;
                if (dateString.includes('T')) {
                    // ISO format with time
                    date = new Date(dateString);
                } else if (dateString.includes(' ')) {
                    // Date with time but no T separator
                    date = new Date(dateString);
                } else {
                    // Date only format
                    date = new Date(dateString + 'T00:00:00');
                }
                
                // Check if date is valid
                if (isNaN(date.getTime())) {
                    return 'Invalid Date';
                }
                
                return date.toLocaleString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
            };
            
            content.innerHTML = `
                <!-- Flight Information -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-plane mr-2"></i>Flight Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flight Number</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${flight.TaskName || flight.FlightNo || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Route</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${flight.Route || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aircraft Registration</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${flight.Rego || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${duration || 'N/A'}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Flight Times -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-clock mr-2"></i>Flight Times
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Task Start</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${formatDate(flight.TaskStart)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Task End</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${formatDate(flight.TaskEnd)}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Crew Information -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-user-tie mr-2"></i>Crew Information
                    </h4>
                    <div class="space-y-3">
                        ${crewMembers.map(member => {
                            const user = userDetails[member.id];
                            const userName = user ? `${user.first_name} ${user.last_name}` : 'Unknown User';
                            const userPosition = user ? user.position : '';
                            const userPicture = user && user.picture ? user.picture : null;
                            const userMobile = user && user.mobile ? user.mobile : null;
                            const userPhone = user && user.phone ? user.phone : null;
                            const phoneNumber = userMobile || userPhone || null;
                            
                            // Process picture path - remove crewplan/ if exists and use relative path
                            let picturePath = null;
                            if (userPicture) {
                                let path = userPicture;
                                // Remove crewplan/ if it exists in the path
                                path = path.replace('crewplan/', '').replace('crewplan\\', '');
                                // If path doesn't start with uploads/, add it
                                if (path.indexOf('uploads/') !== 0) {
                                    path = 'uploads/profile/' + path.split('/').pop();
                                }
                                // Use relative path from crewplan directory
                                picturePath = '../' + path;
                            }
                            
                            return `
                                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                                    ${picturePath ? `
                                    <div class="h-12 w-12 rounded-full overflow-hidden mr-3 flex-shrink-0 border-2 border-green-600 dark:border-green-500">
                                        <img src="${picturePath}" 
                                             alt="${userName}" 
                                             class="h-full w-full object-cover"
                                             onerror="this.onerror=null; this.parentElement.classList.add('bg-green-600'); this.parentElement.innerHTML='<i class=\\'fas fa-user text-white text-lg\\'></i>';">
                                    </div>
                                    ` : `
                                    <div class="h-12 w-12 bg-green-600 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-user text-white text-lg"></i>
                                    </div>
                                    `}
                                    <div class="flex-1">
                                        ${member.roleDetail ? `<p class="text-sm font-medium text-gray-900 dark:text-white mb-1">${member.roleDetail}</p>` : ''}
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">${userName}</p>
                                        ${userPosition ? `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${userPosition}</p>` : ''}
                                        ${phoneNumber ? `
                                        <a href="tel:${phoneNumber.replace(/[^0-9+]/g, '')}" 
                                           class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 mt-1 inline-flex items-center">
                                            <i class="fas fa-phone mr-1"></i>
                                            ${phoneNumber}
                                        </a>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
                
                <!-- Driver Assignments Information -->
                ${driverAssignments.length > 0 ? `
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-car mr-2"></i>Driver Assignments
                    </h4>
                    <div class="space-y-3">
                        ${driverAssignments.map(assignment => {
                            const driverName = (assignment.driver_first_name || '') + ' ' + (assignment.driver_last_name || '');
                            const driverMobile = assignment.driver_mobile || null;
                            const driverPhone = assignment.driver_phone || null;
                            const phoneNumber = driverMobile || driverPhone || null;
                            const driverPicture = assignment.driver_picture || null;
                            const crewName = (assignment.crew_first_name || '') + ' ' + (assignment.crew_last_name || '');
                            const crewPosition = assignment.crew_position || '';
                            
                            return `
                                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                                    ${driverPicture ? `
                                    <div class="h-12 w-12 rounded-full overflow-hidden mr-3 flex-shrink-0 border-2 border-blue-600 dark:border-blue-500">
                                        <img src="${driverPicture}" 
                                             alt="${driverName.trim()}" 
                                             class="h-full w-full object-cover"
                                             onerror="this.onerror=null; this.parentElement.classList.add('bg-blue-600'); this.parentElement.innerHTML='<i class=\\'fas fa-user text-white text-lg\\'></i>';">
                                    </div>
                                    ` : `
                                    <div class="h-12 w-12 bg-blue-600 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-user text-white text-lg"></i>
                                    </div>
                                    `}
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">${driverName.trim() || 'Unknown Driver'}</p>
                                        ${crewName.trim() ? `
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <i class="fas fa-user-tie mr-1"></i>
                                            Assigned to: ${crewName.trim()}${crewPosition ? ' (' + crewPosition + ')' : ''}
                                        </p>
                                        ` : ''}
                                        ${phoneNumber ? `
                                        <a href="tel:${phoneNumber.replace(/[^0-9+]/g, '')}" 
                                           class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 mt-1 inline-flex items-center">
                                            <i class="fas fa-phone mr-1"></i>
                                            ${phoneNumber}
                                        </a>
                                        ` : ''}
                                        ${assignment.pickup_location ? `
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            Pickup: ${assignment.pickup_location}
                                        </p>
                                        ` : ''}
                                        ${assignment.dropoff_location ? `
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            Dropoff: ${assignment.dropoff_location}
                                        </p>
                                        ` : ''}
                    </div>
                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
                ` : ''}
            `;
        }
        
        // Close flight details modal
        function closeFlightDetailsModal() {
            document.getElementById('flightDetailsModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('flightDetailsModal');
            if (event.target === modal) {
                closeFlightDetailsModal();
            }
        }
        
        // Navigation functions
        function previousPeriod() {
            if (currentView === 'month') {
            currentDisplayMonth--;
            if (currentDisplayMonth < 0) {
                currentDisplayMonth = 11;
                currentDisplayYear--;
            }
                generateMonthView();
            } else if (currentView === 'week') {
                currentDisplayDate.setDate(currentDisplayDate.getDate() - 7);
                generateWeekView();
            } else if (currentView === 'day') {
                currentDisplayDate.setDate(currentDisplayDate.getDate() - 1);
                generateDayView();
            }
            updatePeriodDisplay();
        }
        
        function nextPeriod() {
            if (currentView === 'month') {
            currentDisplayMonth++;
            if (currentDisplayMonth > 11) {
                currentDisplayMonth = 0;
                currentDisplayYear++;
            }
                generateMonthView();
            } else if (currentView === 'week') {
                currentDisplayDate.setDate(currentDisplayDate.getDate() + 7);
                generateWeekView();
            } else if (currentView === 'day') {
                currentDisplayDate.setDate(currentDisplayDate.getDate() + 1);
                generateDayView();
            }
            updatePeriodDisplay();
        }
        
        // Update period display based on current view
        function updatePeriodDisplay() {
            const periodElement = document.getElementById('currentPeriodDisplay');
            const periodElementMobile = document.getElementById('currentPeriodDisplayMobile');
            
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            let periodText = '';
            
            if (currentView === 'month') {
                periodText = `${monthNames[currentDisplayMonth]} ${currentDisplayYear}`;
            } else if (currentView === 'week') {
                // Get start and end of week
                const weekStart = new Date(currentDisplayDate);
                weekStart.setDate(currentDisplayDate.getDate() - currentDisplayDate.getDay());
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                
                // Format week range
                const startMonth = monthNames[weekStart.getMonth()];
                const endMonth = monthNames[weekEnd.getMonth()];
                
                if (weekStart.getFullYear() === weekEnd.getFullYear() && weekStart.getMonth() === weekEnd.getMonth()) {
                    periodText = `${startMonth} ${weekStart.getDate()} - ${weekEnd.getDate()}, ${weekStart.getFullYear()}`;
                } else if (weekStart.getFullYear() === weekEnd.getFullYear()) {
                    periodText = `${startMonth} ${weekStart.getDate()} - ${endMonth} ${weekEnd.getDate()}, ${weekStart.getFullYear()}`;
                } else {
                    periodText = `${startMonth} ${weekStart.getDate()}, ${weekStart.getFullYear()} - ${endMonth} ${weekEnd.getDate()}, ${weekEnd.getFullYear()}`;
                }
            } else if (currentView === 'day') {
                const weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const monthName = monthNames[currentDisplayDate.getMonth()];
                // Mobile: shorter format
                periodText = `${weekdayNames[currentDisplayDate.getDay()]}, ${monthName} ${currentDisplayDate.getDate()}, ${currentDisplayDate.getFullYear()}`;
            }
            
            // Update desktop display
            if (periodElement) {
                periodElement.textContent = periodText;
            }
            
            // Update mobile display (shorter format for mobile)
            if (periodElementMobile) {
                if (currentView === 'month') {
                    const monthShort = monthNames[currentDisplayMonth].substring(0, 3);
                    periodElementMobile.textContent = `${monthShort} ${currentDisplayYear}`;
                } else if (currentView === 'week') {
                    const weekStart = new Date(currentDisplayDate);
                    weekStart.setDate(currentDisplayDate.getDate() - currentDisplayDate.getDay());
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekStart.getDate() + 6);
                    const startMonthShort = monthNames[weekStart.getMonth()].substring(0, 3);
                    const endMonthShort = monthNames[weekEnd.getMonth()].substring(0, 3);
                    if (weekStart.getMonth() === weekEnd.getMonth()) {
                        periodElementMobile.textContent = `${startMonthShort} ${weekStart.getDate()}-${weekEnd.getDate()}`;
                    } else {
                        periodElementMobile.textContent = `${startMonthShort} ${weekStart.getDate()} - ${endMonthShort} ${weekEnd.getDate()}`;
                    }
                } else if (currentView === 'day') {
                    const weekdayShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    const monthShort = monthNames[currentDisplayDate.getMonth()].substring(0, 3);
                    periodElementMobile.textContent = `${weekdayShort[currentDisplayDate.getDay()]}, ${monthShort} ${currentDisplayDate.getDate()}`;
                }
            }
        }

        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js', { scope: './' })
                    .then((registration) => {
                        console.log('Service Worker registered successfully:', registration.scope);
                        console.log('Service Worker active:', registration.active);
                        console.log('Service Worker installing:', registration.installing);
                        console.log('Service Worker waiting:', registration.waiting);
                        
                        // Check for updates periodically
                        setInterval(() => {
                            registration.update();
                        }, 60000); // Check every minute
                    })
                    .catch((error) => {
                        console.error('Service Worker registration failed:', error);
                        console.error('Error details:', error.message, error.stack);
                    });
            });
        } else {
            console.warn('Service Worker not supported in this browser');
        }

        // PWA Install Prompt
        let deferredPrompt;
        const installBanner = document.getElementById('pwaInstallBanner');
        
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallprompt event fired');
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later
            deferredPrompt = e;
            console.log('deferredPrompt stored:', deferredPrompt);
            
            // Show our custom install banner
            if (installBanner && shouldShowInstallBanner()) {
                installBanner.classList.add('show');
            }
            
            // Also show install button in header if available
            if (!isStandalone()) {
                setTimeout(() => {
                    addInstallButtonToHeader();
                }, 500);
            }
            
        });
        
        // Function to add install button to header
        function addInstallButtonToHeader() {
            if (document.getElementById('headerInstallBtn')) {
                return; // Already exists
            }
            
            // Try multiple selectors to find header actions area
            // First try the exact structure from the HTML
            let headerActions = document.querySelector('.max-w-7xl .flex.justify-between .flex.items-center.space-x-4');
            
            // If not found, try other selectors
            if (!headerActions) {
                headerActions = document.querySelector('.max-w-7xl .flex.items-center.space-x-4');
            }
            if (!headerActions) {
                // Try to find by searching for logout button and then its parent
                const logoutBtn = document.querySelector('a[href="logout.php"]');
                if (logoutBtn && logoutBtn.parentElement) {
                    headerActions = logoutBtn.parentElement;
                }
            }
            if (!headerActions) {
                headerActions = document.querySelector('.flex.items-center.justify-between .flex.items-center.space-x-4');
            }
            
            if (headerActions && !document.getElementById('headerInstallBtn')) {
                const installBtn = document.createElement('button');
                installBtn.id = 'headerInstallBtn';
                installBtn.className = 'inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200';
                installBtn.innerHTML = '<i class="fas fa-download mr-2"></i>Install App';
                installBtn.onclick = installPWA;
                
                try {
                    // Insert before logout button if it exists
                    const logoutBtn = headerActions.querySelector('a[href="logout.php"]');
                    if (logoutBtn) {
                        headerActions.insertBefore(installBtn, logoutBtn);
                    } else if (headerActions.firstChild) {
                        headerActions.insertBefore(installBtn, headerActions.firstChild);
                    } else {
                        headerActions.appendChild(installBtn);
                    }
                    console.log('Install button added to header successfully');
                } catch (e) {
                    console.error('Error inserting install button:', e);
                    headerActions.appendChild(installBtn);
                }
            } else {
                if (!headerActions) {
                    console.log('Header actions area not found, retrying...');
                    // Retry after a bit more time (max 3 retries)
                    if (!window.installButtonRetryCount) {
                        window.installButtonRetryCount = 0;
                    }
                    if (window.installButtonRetryCount < 3) {
                        window.installButtonRetryCount++;
                        setTimeout(() => {
                            addInstallButtonToHeader();
                        }, 1000);
                    } else {
                        console.warn('Failed to add install button after 3 retries');
                    }
                }
            }
        }
        
        // Log if beforeinstallprompt doesn't fire (for debugging)
        window.addEventListener('load', () => {
            setTimeout(() => {
                if (!deferredPrompt) {
                    console.log('beforeinstallprompt event did not fire. Possible reasons:');
                    console.log('- App already installed');
                    console.log('- Browser does not support PWA installation');
                    console.log('- HTTPS required (or localhost)');
                    console.log('- Service Worker not registered');
                    console.log('- Manifest not valid');
                }
            }, 3000);
        });

        // Handle install button click
        function installPWA() {
            console.log('Install button clicked, deferredPrompt:', deferredPrompt);
            
            // Check if we're on iOS
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            
            if (isIOS) {
                // iOS doesn't support beforeinstallprompt, show manual instructions
                alert('To install this app on iOS:\n\n1. Tap the Share button in Safari\n2. Select "Add to Home Screen"\n3. Tap "Add" to confirm');
                return;
            }
            
            if (deferredPrompt) {
                // Show the install prompt
                deferredPrompt.prompt();
                
                // Wait for the user to respond to the prompt
                deferredPrompt.userChoice.then((choiceResult) => {
                    console.log('User choice:', choiceResult.outcome);
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                        if (installBanner) {
                            installBanner.classList.remove('show');
                        }
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    // Clear the deferredPrompt so it can only be used once
                    deferredPrompt = null;
                }).catch((error) => {
                    console.error('Error showing install prompt:', error);
                    alert('Error installing app. Please try again or use browser menu to install.');
                });
            } else {
                // deferredPrompt is not available, show manual instructions
                const isAndroid = /Android/.test(navigator.userAgent);
                if (isAndroid) {
                    alert('To install this app:\n\n1. Tap the browser menu (three dots)\n2. Select "Install app" or "Add to Home screen"');
                } else {
                    alert('To install this app:\n\n1. Click the install icon in the address bar\n2. Or select "Install" from the browser menu');
                }
            }
        }

        // Dismiss install banner
        function dismissInstallBanner() {
            if (installBanner) {
                installBanner.classList.remove('show');
            }
            // Store dismissal in localStorage to not show again for 24 hours
            localStorage.setItem('pwaInstallDismissed', Date.now());
        }

        // Check if banner was dismissed recently
        function shouldShowInstallBanner() {
            const dismissed = localStorage.getItem('pwaInstallDismissed');
            if (!dismissed) return true;
            const dismissedTime = parseInt(dismissed);
            const oneDay = 24 * 60 * 60 * 1000;
            return (Date.now() - dismissedTime) > oneDay;
        }

        // Hide banner when app is installed
        window.addEventListener('appinstalled', () => {
            console.log('PWA installed');
            if (installBanner) {
                installBanner.classList.remove('show');
            }
            deferredPrompt = null;
        });

        // Check if running as standalone PWA
        function isStandalone() {
            return (window.matchMedia('(display-mode: standalone)').matches) || 
                   (window.navigator.standalone) || 
                   document.referrer.includes('android-app://');
        }

        // Add install button to header if not installed (always show on mobile)
        if (!isStandalone() && shouldShowInstallBanner()) {
            // Show install button in header after page load (even if beforeinstallprompt hasn't fired)
            setTimeout(() => {
                addInstallButtonToHeader();
            }, 1500);
        }
    </script>
    
    <!-- PWA Install Banner -->
    <div id="pwaInstallBanner" class="pwa-install-banner">
        <div>
            <i class="fas fa-mobile-alt mr-2"></i>
            <span>Install Crew Plan App for offline access and better experience</span>
        </div>
        <div class="flex gap-2">
            <button onclick="dismissInstallBanner()" class="secondary">Later</button>
            <button onclick="installPWA()">Install</button>
        </div>
    </div>
</body>
</html>
