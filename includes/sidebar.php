<?php
// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

$current_user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Function to generate absolute paths based on current location
function getAbsolutePath($path) {
    // Always use absolute path from project root
    return '/' . ltrim($path, '/');
}
?>

<style>
/* Custom Scrollbar for Sidebar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f7fafc;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
    transition: background-color 0.2s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* Dark mode scrollbar */
.dark .custom-scrollbar {
    scrollbar-color: #4a5568 #2d3748;
}

.dark .custom-scrollbar::-webkit-scrollbar-track {
    background: #2d3748;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #4a5568;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #718096;
}

/* Smooth scrolling */
.custom-scrollbar {
    scroll-behavior: smooth;
}
</style>

<!-- Sidebar -->
<div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between h-16 px-6 bg-blue-600 dark:bg-blue-700">
        <div class="flex items-center">
            <div class="h-8 w-8 bg-white rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-plane text-blue-600 text-sm"></i>
            </div>
            <h1 class="text-white font-semibold text-lg"><?php echo PROJECT_NAME; ?></h1>
        </div>
        <!-- Close button for mobile -->
        <button id="sidebar-close" class="lg:hidden text-white hover:text-gray-200">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <!-- User Profile Section -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <div class="h-12 w-12 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center mr-4">
                <?php if (!empty($current_user['picture']) && file_exists(__DIR__ . '/../' . $current_user['picture'])): ?>
                    <img src="<?php echo getProfileImageUrl($current_user['picture']); ?>" 
                         alt="Profile" class="h-12 w-12 rounded-full object-cover">
                <?php else: ?>
                    <i class="fas fa-user text-gray-600 dark:text-gray-300 text-xl"></i>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                    <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    <?php echo htmlspecialchars($current_user['position']); ?>
                </p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mt-1">
                     <?php echo ucfirst($current_user['role'] ?? 'employee'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto px-3 py-6 custom-scrollbar">
        <div class="space-y-1">
            <!-- Dashboard -->
            <a href="<?php echo getAbsolutePath('dashboard/'); ?>" 
               class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'dashboard') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
                Dashboard
            </a>

            <!-- NOTAM -->
            <?php if (checkPageAccessEnhanced('admin/notam.php')): ?>
            <a href="<?php echo getAbsolutePath('admin/notam.php'); ?>" 
               class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'notam') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                <i class="fas fa-exclamation-triangle mr-3 text-lg"></i>
                NOTAM
            </a>
            <?php endif; ?>

            <!-- Fleet Management -->
            <div class="space-y-1">
                <button id="fleet-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-plane mr-3 text-lg"></i>
                        Fleet Management
                    </div>
                    <i id="fleet-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="fleet-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/fleet/aircraft/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'aircraft') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-plane-departure mr-3 text-sm"></i>
                        Aircraft
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/fleet/routes/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'routes' && $current_page != 'stations') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-route mr-3 text-sm"></i>
                        Routes
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/fleet/routes/stations.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'stations') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-map-marker-alt mr-3 text-sm"></i>
                        Stations
                    </a>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/routes/fix_time.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/routes/fix_time.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'fix_time') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clock mr-3 text-sm"></i>
                        Fix Time
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/delay_codes/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/delay_codes/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'delay_codes' && $current_page != 'raimon_delay_code') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clock mr-3 text-sm"></i>
                        Delay Codes
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/delay_codes/raimon_delay_code.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/delay_codes/raimon_delay_code.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'raimon_delay_code') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-code mr-3 text-sm"></i>
                        Raimon Delay Code
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/etl_report/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/etl_report/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'etl_report') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-chart-line mr-3 text-sm"></i>
                        ETL Report
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/airsar_report/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/airsar_report/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'airsar_report') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-file-chart-line mr-3 text-sm"></i>
                        Airsar Report (ETL)
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/handover/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/handover/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'handover') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-exchange-alt mr-3 text-sm"></i>
                        HandOver
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/mel_items/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/mel_items/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'mel_items') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clipboard-list mr-3 text-sm"></i>
                        MEL Items
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/camo_report/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/camo_report/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'camo_report') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-file-alt mr-3 text-sm"></i>
                        Camo Report
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/fleet/toolbox/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/fleet/toolbox/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'toolbox') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-toolbox mr-3 text-sm"></i>
                        Toolbox
                    </a>
                    <?php endif; ?>
                    
                </div>
            </div>

            <!-- Dispatch -->
            <div class="space-y-1">
                <button id="dispatch-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-paper-plane mr-3 text-lg"></i>
                        Dispatch
                    </div>
                    <i id="dispatch-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="dispatch-menu" class="hidden pl-6 space-y-1">
                    <?php if (checkPageAccessEnhanced('admin/dispatch/webform/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/dispatch/webform/index.php'); ?>"
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'webform' || ($current_dir == 'dispatch' && $current_page == 'index')) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clipboard-check mr-3 text-sm"></i>
                        Dispatch Handover
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RLSS -->
            <div class="space-y-1">
                <button id="rlss-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-search mr-3 text-lg"></i>
                        RLSS
                    </div>
                    <i id="rlss-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="rlss-menu" class="hidden pl-6 space-y-1">
                    <?php if (checkPageAccessEnhanced('admin/rlss/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/rlss/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'rlss' && strpos($_SERVER['REQUEST_URI'], '/admin/rlss/part_search/') === false) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-home mr-3 text-sm"></i>
                        RLSS Home
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/rlss/part_search/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/rlss/part_search/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/rlss/part_search/') !== false ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-search mr-3 text-sm"></i>
                        Part Search
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/rlss/search_mro/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/rlss/search_mro/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/rlss/search_mro/') !== false ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-wrench mr-3 text-sm"></i>
                        Search MRO
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Flight Management -->
            <div class="space-y-1">
                <button id="flight-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-plane-departure mr-3 text-lg"></i>
                        Flight Management
                    </div>
                    <i id="flight-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="flight-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/flights/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'flights' && strpos($_SERVER['REQUEST_URI'], '/admin/flights/asr/') === false) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-plane mr-3 text-sm"></i>
                        Flight Manager
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/flights/asr/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/flights/asr/') !== false ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-shield-alt mr-3 text-sm"></i>
                        Air Safety Report
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/crew/scheduling.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'crew') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-users-cog mr-3 text-sm"></i>
                        Crew Scheduling
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/crew/location.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'location' && $current_dir == 'crew') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-map-marker-alt mr-3 text-sm"></i>
                        Crew Location
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/flight_monitoring.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'flight_monitoring') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-chart-line mr-3 text-sm"></i>
                        Flight Monitoring
                    </a>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/ofp.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/ofp.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'ofp') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-file-alt mr-3 text-sm"></i>
                        OFP
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/flight_time.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'flight_time') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clock mr-3 text-sm"></i>
                        Flight Time
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/fdp_calculation.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'fdp_calculation') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-calculator mr-3 text-sm"></i>
                        FDP Calculation
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/crew_list.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'crew_list') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-users mr-3 text-sm"></i>
                        Crew List
                    </a>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/gd_list.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/gd_list.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'gd_list') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-file-alt mr-3 text-sm"></i>
                        GD List
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/daily_crew.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/daily_crew.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'daily_crew') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-calendar-day mr-3 text-sm"></i>
                        Daily Crew
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/journey_log.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'journey_log') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clipboard-list mr-3 text-sm"></i>
                        Journey Log
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/journey_log_list.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'journey_log_list') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-list-alt mr-3 text-sm"></i>
                        Journey Log List
                    </a>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/flight_roles.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'flight_roles') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user-tag mr-3 text-sm"></i>
                        Flight Roles
                    </a>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/roster/index.php') || checkPageAccessEnhanced('admin/operations/roster/roster_management.php')): ?>
                    <!-- Roster Submenu -->
                    <div class="space-y-1">
                        <button id="roster-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt mr-3 text-sm"></i>
                                Roster
                            </div>
                            <i id="roster-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                        </button>
                        <div id="roster-menu" class="hidden pl-6 space-y-1">
                            <?php if (checkPageAccessEnhanced('admin/operations/roster/index.php')): ?>
                            <a href="<?php echo getAbsolutePath('admin/operations/roster/index.php'); ?>" 
                               class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'roster' && $current_page == 'index') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                                <i class="fas fa-code mr-3 text-sm"></i>
                                Shift Code
                            </a>
                            <?php endif; ?>
                            <?php if (checkPageAccessEnhanced('admin/operations/roster/roster_management.php')): ?>
                            <a href="<?php echo getAbsolutePath('admin/operations/roster/roster_management.php'); ?>" 
                               class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'roster' && $current_page == 'roster_management') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                                <i class="fas fa-calendar-check mr-3 text-sm"></i>
                                Roster Management
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <a href="<?php echo getAbsolutePath('admin/operations/metar_tafor.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'metar_tafor') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-cloud-sun mr-3 text-sm"></i>
                        Metar Taf
                    </a>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/metar_tafor_history.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/metar_tafor_history.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'metar_tafor_history') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-history mr-3 text-sm"></i>
                        Metar Taf History
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/payload_data.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/payload_data.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'payload_data') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-weight mr-3 text-sm"></i>
                        Payload Data
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/payload_calculator.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/payload_calculator.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'payload_calculator') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-calculator mr-3 text-sm"></i>
                        Payload Calculator
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/operations/passenger_by_aircraft.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/operations/passenger_by_aircraft.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'passenger_by_aircraft') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-users mr-3 text-sm"></i>
                        Passenger Capacity
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Management Section (Only for admin/manager roles or individual access) -->
            <?php if (hasAnyRole(['admin', 'manager']) || checkPageAccessEnhanced('admin/users/index.php') || checkPageAccessEnhanced('admin/users/add.php') || checkPageAccessEnhanced('admin/roles/index.php') || checkPageAccessEnhanced('admin/users/office_time.php')): ?>
            <div class="space-y-1">
                <button id="users-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-users-cog mr-3 text-lg"></i>
                        User Management
                    </div>
                    <i id="users-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="users-menu" class="hidden pl-6 space-y-1">
                    <?php if (checkPageAccessEnhanced('admin/users/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'index' && $current_dir == 'users') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-list mr-3 text-sm"></i>
                        All Users
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/users/add.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/add.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'add' && $current_dir == 'users') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user-plus mr-3 text-sm"></i>
                        Add User
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/roles/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/roles/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'index' && $current_dir == 'roles') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user-tag mr-3 text-sm"></i>
                        Role Manager
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/users/office_time.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/office_time.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'office_time' && $current_dir == 'users') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-clock mr-3 text-sm"></i>
                        Office Time
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/users/contacts.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/contacts.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'contacts' && $current_dir == 'users') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-address-book mr-3 text-sm"></i>
                        Contacts
                    </a>
                    <?php endif; ?>
                    
                </div>
            </div>
            <?php endif; ?>

            <!-- Recency -->
            <?php if (checkPageAccessEnhanced('admin/users/personnel_recency/index.php') || checkPageAccessEnhanced('admin/users/certificate/index.php') || checkPageAccessEnhanced('admin/recency_management/index.php')): ?>
            <div class="space-y-1">
                <button id="recency-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-user-clock mr-3 text-lg"></i>
                        Recency
                    </div>
                    <i id="recency-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="recency-menu" class="hidden pl-6 space-y-1">
                    <?php if (checkPageAccessEnhanced('admin/recency_management/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/recency_management/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'recency_management' && $current_page == 'index') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-cog mr-3 text-sm"></i>
                        Recency Management
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/recency_management/set_recency.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/recency_management/set_recency.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'recency_management' && $current_page == 'set_recency') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-list-check mr-3 text-sm"></i>
                        Set Recency's
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/users/personnel_recency/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/personnel_recency/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'index' && $current_dir == 'personnel_recency') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user-clock mr-3 text-sm"></i>
                        Personnel Recency
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/users/certificate/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/certificate/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'index' && $current_dir == 'certificate') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-certificate mr-3 text-sm"></i>
                        Certificate
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Settings -->
            <div class="space-y-1">
                <button id="settings-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-cogs mr-3 text-lg"></i>
                        Settings
                    </div>
                    <i id="settings-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="settings-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/settings/home_base/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'home_base') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-home mr-3 text-sm"></i>
                        Home Base
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/settings/safety_reports/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'safety_reports') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-shield-alt mr-3 text-sm"></i>
                        Safety Report
                    </a>
                    <?php if (checkPageAccessEnhanced('admin/settings/backup_db.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/settings/backup_db.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'backup_db') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-database mr-3 text-sm"></i>
                        Backup Database
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo getAbsolutePath('admin/odb/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'odb' && $current_page == 'index') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-bell mr-3 text-sm"></i>
                        ODB Management
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/role_permission.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'role_permission') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-shield-alt mr-3 text-sm"></i>
                        Page Permissions
                    </a>
                    <?php if (checkPageAccessEnhanced('admin/settings/notification.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/settings/notification.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'notification') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-bell mr-3 text-sm"></i>
                        Notifications
                    </a>
                    <?php endif; ?>
                    
                    <?php if (checkPageAccessEnhanced('admin/settings/sms.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/settings/sms.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'sms') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-sms mr-3 text-sm"></i>
                        SMS
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Flight Load -->
            <div class="space-y-1">
                <button id="flight-load-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-users mr-3 text-lg"></i>
                        Flight Load
                    </div>
                    <i id="flight-load-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="flight-load-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/flight_load/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'flight_load') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-ticket-alt mr-3 text-sm"></i>
                        All Tickets
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <?php if (checkPageAccessEnhanced('admin/statistics/flight_statistics.php')): ?>
            <div class="space-y-1">
                <button id="statistics-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-chart-line mr-3 text-lg"></i>
                        Statistics
                    </div>
                    <i id="statistics-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="statistics-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/statistics/flight_statistics.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'statistics' && $current_page == 'flight_statistics') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-plane mr-3 text-sm"></i>
                        Flight Statistics
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- CAA -->
            <?php if (checkPageAccessEnhanced('admin/caa/city_per.php')): ?>
            <div class="space-y-1">
                <button id="caa-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-chart-bar mr-3 text-lg"></i>
                        CAA
                    </div>
                    <i id="caa-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="caa-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/caa/city_per.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'caa' && $current_page == 'city_per') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-route mr-3 text-sm"></i>
                        City-Pairs Report
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/caa/revenue.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'caa' && $current_page == 'revenue') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-dollar-sign mr-3 text-sm"></i>
                        Revenue Flights
                    </a>
                    <?php if (checkPageAccessEnhanced('admin/caa/daily_report.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/caa/daily_report.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'caa' && $current_page == 'daily_report') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-file-excel mr-3 text-sm"></i>
                        Daily Report
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- E-Lib -->
            <?php if (checkPageAccessEnhanced('admin/elib/index.php')): ?>
            <div class="space-y-1">
                <a href="<?php echo getAbsolutePath('admin/elib/index.php'); ?>" 
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'elib') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                    <i class="fas fa-book-open mr-3 text-lg"></i>
                    E-Lib
                </a>
            </div>
            <?php endif; ?>

            <!-- EFB -->
            <?php if (checkPageAccessEnhanced('admin/efb/index.php')): ?>
            <div class="space-y-1">
                <a href="<?php echo getAbsolutePath('admin/efb/index.php'); ?>" 
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'efb') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                    <i class="fas fa-briefcase mr-3 text-lg"></i>
                    EFB
                </a>
            </div>
            <?php endif; ?>

            <!-- Price -->
            <?php if (checkPageAccessEnhanced('admin/pricing/routes/index.php')): ?>
            <div class="space-y-1">
                <button id="price-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-dollar-sign mr-3 text-lg"></i>
                        Price
                    </div>
                    <i id="price-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="price-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/pricing/routes/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'routes' && strpos($_SERVER['REQUEST_URI'], '/admin/pricing/') !== false) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-route mr-3 text-sm"></i>
                        Ground Price
                    </a>
                    <?php if (checkPageAccessEnhanced('admin/pricing/catering/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/pricing/catering/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'catering' && strpos($_SERVER['REQUEST_URI'], '/admin/pricing/') !== false) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-utensils mr-3 text-sm"></i>
                        Catering
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/pricing/ifso_costs/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/pricing/ifso_costs/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'ifso_costs' && strpos($_SERVER['REQUEST_URI'], '/admin/pricing/') !== false) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-dollar-sign mr-3 text-sm"></i>
                        IFSO Costs
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ODB -->
            <div class="space-y-1">
                <button id="odb-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-database mr-3 text-lg"></i>
                        ODB
                    </div>
                    <i id="odb-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="odb-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/odb/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'odb' && $current_page == 'index') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-cog mr-3 text-sm"></i>
                        ODB Management
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/odb/list.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'odb' && $current_page == 'list') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user-circle mr-3 text-sm"></i>
                        My ODB
                    </a>
                </div>
            </div>

            <!-- My RIOPS -->
            <div class="space-y-1">
                <button id="my-riops-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-user-circle mr-3 text-lg"></i>
                        My RIOPS
                    </div>
                    <i id="my-riops-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="my-riops-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/profile/'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'profile' && $current_page != 'my_recency' && $current_page != 'my_certificate') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user mr-3 text-sm"></i>
                        My Profile
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/profile/my_recency.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'my_recency') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 448 512">
                            <path d="M32 416c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64C46.3 64 32 78.3 32 96l0 320zM0 96C0 60.7 28.7 32 64 32l320 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zm96 80c0-8.8 7.2-16 16-16l48 0 0-24c0-8.8 7.2-16 16-16s16 7.2 16 16l0 24 144 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-144 0 0 24c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-24-48 0c-8.8 0-16-7.2-16-16zm0 160c0-8.8 7.2-16 16-16l144 0 0-24c0-8.8 7.2-16 16-16s16 7.2 16 16l0 24 48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-48 0 0 24c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-24-144 0c-8.8 0-16-7.2-16-16z"/>
                        </svg>
                        My Recency
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/profile/my_certificate.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'my_certificate') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-certificate mr-3 text-sm"></i>
                        My Certificate
                    </a>
                    <a href="<?php echo getAbsolutePath('admin/profile/my_quiz.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'my_quiz') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-question-circle mr-3 text-sm"></i>
                        My Quiz
                    </a>
                    <?php if (checkPageAccessEnhanced('admin/profile/my_class.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/profile/my_class.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'my_class') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-chalkboard-teacher mr-3 text-sm"></i>
                        My Class
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Training -->
            <?php if (checkPageAccessEnhanced('admin/training/quiz/index.php') || checkPageAccessEnhanced('admin/training/quiz/create_set.php') || checkPageAccessEnhanced('admin/training/quiz/assign_quiz.php') || checkPageAccessEnhanced('admin/training/quiz/results.php') || checkPageAccessEnhanced('admin/training/certificate/issue_certificate.php') || checkPageAccessEnhanced('admin/users/certificate/index.php') || checkPageAccessEnhanced('admin/training/class/index.php')): ?>
            <div class="space-y-1">
                <button id="training-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-graduation-cap mr-3 text-lg"></i>
                        Training
                    </div>
                    <i id="training-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="training-menu" class="hidden pl-6 space-y-1">
                    <?php if (checkPageAccessEnhanced('admin/training/quiz/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/training/quiz/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'index' && $current_dir == 'quiz') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-list mr-3 text-sm"></i>
                        Quiz Set List
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/training/quiz/create_set.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/training/quiz/create_set.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'create_set' && $current_dir == 'quiz') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-plus-circle mr-3 text-sm"></i>
                        Create Quiz Set
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/training/quiz/assign_quiz.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/training/quiz/assign_quiz.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'assign_quiz' && $current_dir == 'quiz') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-user-check mr-3 text-sm"></i>
                        Assign Quiz
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/training/quiz/results.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/training/quiz/results.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'results' && $current_dir == 'quiz') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-chart-bar mr-3 text-sm"></i>
                        Quiz Results
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/training/certificate/issue_certificate.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/training/certificate/issue_certificate.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'issue_certificate' && $current_dir == 'certificate') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-certificate mr-3 text-sm"></i>
                        Issue Certificate
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/users/certificate/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/users/certificate/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_page == 'index' && $current_dir == 'certificate') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-list mr-3 text-sm"></i>
                        Certificate List
                    </a>
                    <?php endif; ?>
                    <?php if (checkPageAccessEnhanced('admin/training/class/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/training/class/index.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'class') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-chalkboard-teacher mr-3 text-sm"></i>
                        Class Management
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transport -->
            <?php if (checkPageAccessEnhanced('admin/transport/trip_management.php')): ?>
            <div class="space-y-1">
                <button id="transport-toggle" class="group flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-truck mr-3 text-lg"></i>
                        Transport
                    </div>
                    <i id="transport-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                </button>
                <div id="transport-menu" class="hidden pl-6 space-y-1">
                    <a href="<?php echo getAbsolutePath('admin/transport/trip_management.php'); ?>" 
                       class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'transport') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <i class="fas fa-route mr-3 text-sm"></i>
                        Manage Trip
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Messages -->
            <a href="<?php echo getAbsolutePath('admin/messages/index.php'); ?>" 
               class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'messages') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                <i class="fas fa-comments mr-3 text-lg"></i>
                Message
                <?php 
                $unreadCount = getUnreadMessageCount($current_user['id'] ?? 0);
                if ($unreadCount > 0): 
                ?>
                    <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        <?php echo $unreadCount; ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Full Log -->
            <?php if (checkPageAccessEnhanced('admin/full_log/activity_log.php')): ?>
            <div class="space-y-1">
                <a href="<?php echo getAbsolutePath('admin/full_log/activity_log.php'); ?>" 
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 <?php echo ($current_dir == 'full_log' && $current_page == 'activity_log') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                    <i class="fas fa-history mr-3 text-lg"></i>
                    Activity Log
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="flex-shrink-0 p-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Last login: <?php echo $current_user['last_login'] ? date('M j, g:i A', strtotime($current_user['last_login'])) : 'First time'; ?>
            </div>
            <a href="<?php echo getAbsolutePath('admin/logout.php'); ?>" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 text-sm">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <!-- Design Credit with Logo -->
        <div class="flex items-center justify-center space-x-2 pt-3 border-t border-gray-200 dark:border-gray-600">
            <img src="<?php echo getAbsolutePath('assets/logo.png'); ?>" alt="MMZ Logo" class="w-6 h-6 rounded-full">
            <div class="text-xs text-gray-400 dark:text-gray-500">
            Developed by <a href="<?php echo getAbsolutePath('about.php'); ?>" class="font-medium text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">MMZ</a> Copyright ©2025
            </div>
        </div>
        
    </div>
</div>

<!-- Mobile Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 hidden lg:hidden"></div>

<!-- Mobile Menu Button -->
<button id="sidebar-toggle" class="fixed top-4 left-4 z-50 p-2 bg-white dark:bg-gray-800 rounded-md shadow-lg lg:hidden">
    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    // Toggle sidebar on mobile
    function toggleSidebar() {
        sidebar.classList.toggle('-translate-x-full');
        sidebarOverlay.classList.toggle('hidden');
    }

    // Close sidebar
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    }

    // Event listeners
    sidebarToggle.addEventListener('click', toggleSidebar);
    sidebarClose.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Collapsible menu functionality
    const fleetToggle = document.getElementById('fleet-toggle');
    const fleetMenu = document.getElementById('fleet-menu');
    const fleetArrow = document.getElementById('fleet-arrow');

    const flightToggle = document.getElementById('flight-toggle');
    const flightMenu = document.getElementById('flight-menu');
    const flightArrow = document.getElementById('flight-arrow');

    const usersToggle = document.getElementById('users-toggle');
    const usersMenu = document.getElementById('users-menu');
    const usersArrow = document.getElementById('users-arrow');

    const settingsToggle = document.getElementById('settings-toggle');
    const settingsMenu = document.getElementById('settings-menu');
    const settingsArrow = document.getElementById('settings-arrow');

    const flightLoadToggle = document.getElementById('flight-load-toggle');
    const flightLoadMenu = document.getElementById('flight-load-menu');
    const flightLoadArrow = document.getElementById('flight-load-arrow');

    const recencyToggle = document.getElementById('recency-toggle');
    const recencyMenu = document.getElementById('recency-menu');
    const recencyArrow = document.getElementById('recency-arrow');

    const statisticsToggle = document.getElementById('statistics-toggle');
    const statisticsMenu = document.getElementById('statistics-menu');
    const statisticsArrow = document.getElementById('statistics-arrow');

    const caaToggle = document.getElementById('caa-toggle');
    const caaMenu = document.getElementById('caa-menu');
    const caaArrow = document.getElementById('caa-arrow');

    const transportToggle = document.getElementById('transport-toggle');
    const transportMenu = document.getElementById('transport-menu');
    const transportArrow = document.getElementById('transport-arrow');

    const myRiOPSToggle = document.getElementById('my-riops-toggle');
    const myRiOPSMenu = document.getElementById('my-riops-menu');
    const myRiOPSArrow = document.getElementById('my-riops-arrow');

    const priceToggle = document.getElementById('price-toggle');
    const priceMenu = document.getElementById('price-menu');
    const priceArrow = document.getElementById('price-arrow');

    const odbToggle = document.getElementById('odb-toggle');
    const odbMenu = document.getElementById('odb-menu');
    const odbArrow = document.getElementById('odb-arrow');

    const rosterToggle = document.getElementById('roster-toggle');
    const rosterMenu = document.getElementById('roster-menu');
    const rosterArrow = document.getElementById('roster-arrow');

    const trainingToggle = document.getElementById('training-toggle');
    const trainingMenu = document.getElementById('training-menu');
    const trainingArrow = document.getElementById('training-arrow');

    const dispatchToggle = document.getElementById('dispatch-toggle');
    const dispatchMenu = document.getElementById('dispatch-menu');
    const dispatchArrow = document.getElementById('dispatch-arrow');

    const rlssToggle = document.getElementById('rlss-toggle');
    const rlssMenu = document.getElementById('rlss-menu');
    const rlssArrow = document.getElementById('rlss-arrow');

    function toggleMenu(menu, arrow) {
        // Toggle the clicked menu (open if closed, close if open)
        // Don't close other menus
        if (menu && arrow) {
            const isHidden = menu.classList.contains('hidden');
            if (isHidden) {
                // Open the menu
                menu.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                // Close the menu
                menu.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }
    }

    function toggleSubMenu(menu, arrow, parentMenu, parentArrow) {
        // Toggle the submenu without closing parent menu
        // First ensure parent menu is open
        if (parentMenu && parentArrow) {
            parentMenu.classList.remove('hidden');
            parentArrow.classList.add('rotate-180');
        }
        
        // Then toggle the submenu
        if (menu && arrow) {
            const isHidden = menu.classList.contains('hidden');
            if (isHidden) {
                menu.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                menu.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }
    }

    if (fleetToggle) {
        fleetToggle.addEventListener('click', () => toggleMenu(fleetMenu, fleetArrow));
    }

    if (flightToggle) {
        flightToggle.addEventListener('click', () => toggleMenu(flightMenu, flightArrow));
    }

    if (usersToggle) {
        usersToggle.addEventListener('click', () => toggleMenu(usersMenu, usersArrow));
    }

    if (settingsToggle) {
        settingsToggle.addEventListener('click', () => toggleMenu(settingsMenu, settingsArrow));
    }

    if (flightLoadToggle) {
        flightLoadToggle.addEventListener('click', () => toggleMenu(flightLoadMenu, flightLoadArrow));
    }

    if (recencyToggle) {
        recencyToggle.addEventListener('click', () => toggleMenu(recencyMenu, recencyArrow));
    }

    if (statisticsToggle) {
        statisticsToggle.addEventListener('click', () => toggleMenu(statisticsMenu, statisticsArrow));
    }

    if (caaToggle) {
        caaToggle.addEventListener('click', () => toggleMenu(caaMenu, caaArrow));
    }

    if (transportToggle) {
        transportToggle.addEventListener('click', () => toggleMenu(transportMenu, transportArrow));
    }

    if (myRiOPSToggle) {
        myRiOPSToggle.addEventListener('click', () => toggleMenu(myRiOPSMenu, myRiOPSArrow));
    }

    if (priceToggle) {
        priceToggle.addEventListener('click', () => toggleMenu(priceMenu, priceArrow));
    }

    if (odbToggle) {
        odbToggle.addEventListener('click', () => toggleMenu(odbMenu, odbArrow));
    }

    if (rosterToggle) {
        rosterToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSubMenu(rosterMenu, rosterArrow, flightMenu, flightArrow);
        });
    }

    if (trainingToggle) {
        trainingToggle.addEventListener('click', () => toggleMenu(trainingMenu, trainingArrow));
    }

    if (dispatchToggle) {
        dispatchToggle.addEventListener('click', () => toggleMenu(dispatchMenu, dispatchArrow));
    }

    if (rlssToggle) {
        rlssToggle.addEventListener('click', () => toggleMenu(rlssMenu, rlssArrow));
    }

    // Function to close all menus
    function closeAllMenus() {
        const allMenus = [fleetMenu, flightMenu, usersMenu, settingsMenu, flightLoadMenu, recencyMenu, statisticsMenu, caaMenu, transportMenu, myRiOPSMenu, priceMenu, odbMenu, rosterMenu, trainingMenu, dispatchMenu, rlssMenu];
        const allArrows = [fleetArrow, flightArrow, usersArrow, settingsArrow, flightLoadArrow, recencyArrow, statisticsArrow, caaArrow, transportArrow, myRiOPSArrow, priceArrow, odbArrow, rosterArrow, trainingArrow, dispatchArrow, rlssArrow];
        
        allMenus.forEach(menu => {
            if (menu) menu.classList.add('hidden');
        });
        
        allArrows.forEach(arrow => {
            if (arrow) arrow.classList.remove('rotate-180');
        });
    }
    
    // Function to open specific menu
    function openMenu(menu, arrow) {
        if (menu && arrow) {
            menu.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        }
    }
    
    // Auto-open menus based on current page
    const currentPage = '<?php echo $current_page; ?>';
    const currentDir = '<?php echo $current_dir; ?>';
    
    // Close all menus first
    closeAllMenus();
    
    // Auto-open fleet menu if on fleet management pages
    if (currentDir === 'fleet' || currentDir === 'aircraft' || currentDir === 'routes' || currentDir === 'etl_report' || currentDir === 'airsar_report' || currentDir === 'handover' || currentDir === 'delay_codes' || currentDir === 'mel_items' || currentDir === 'camo_report' || currentDir === 'toolbox' || currentPage === 'stations' || currentPage === 'fix_time') {
        openMenu(fleetMenu, fleetArrow);
    }
    
    // Auto-open flight menu if on flight management pages
    else if (currentDir === 'flights' || currentDir === 'crew' || currentDir === 'operations' || currentDir === 'roster' || currentPage === 'flight_monitoring' || currentPage === 'flight_time' || currentPage === 'fdp_calculation' || currentPage === 'crew_list' || currentPage === 'daily_crew' || currentPage === 'journey_log' || currentPage === 'journey_log_list' || currentPage === 'flight_roles' || currentPage === 'metar_tafor' || currentPage === 'metar_tafor_history' || currentPage === 'payload_data' || currentPage === 'payload_calculator' || currentPage === 'passenger_by_aircraft' || currentPage === 'ofp') {
        openMenu(flightMenu, flightArrow);
        
        // Auto-open roster menu if on roster pages
        if (currentDir === 'roster') {
            openMenu(rosterMenu, rosterArrow);
        }
    }
    
    // Auto-open users menu if on user management pages
    else if (currentDir === 'users' || currentDir === 'roles' || currentPage === 'add' || currentPage === 'edit') {
        openMenu(usersMenu, usersArrow);
    }
    
    // Auto-open recency menu if on recency pages
    else if (currentDir === 'personnel_recency' || currentDir === 'certificate' || currentDir === 'recency_management') {
        openMenu(recencyMenu, recencyArrow);
    }
    
    // Auto-open statistics menu if on statistics pages
    else if (currentDir === 'statistics') {
        openMenu(statisticsMenu, statisticsArrow);
    }
    
    // Auto-open flight load menu if on flight load pages
    else if (currentDir === 'flight_load') {
        openMenu(flightLoadMenu, flightLoadArrow);
    }
    
    // Auto-open caa menu if on caa pages
    else if (currentDir === 'caa') {
        openMenu(caaMenu, caaArrow);
    }
    
    // Auto-open settings menu if on settings pages
    else if (currentDir === 'home_base' || currentDir === 'safety_reports' || currentDir === 'settings' || currentPage === 'role_permission' || currentPage === 'backup_db' || currentPage === 'notification') {
        openMenu(settingsMenu, settingsArrow);
    }
    
    // Auto-open ODB menu if on ODB pages
    else if (currentDir === 'odb') {
        openMenu(odbMenu, odbArrow);
    }
    
    // Auto-open My RIOPS menu if on profile pages
    else if (currentDir === 'profile' || currentPage === 'my_recency' || currentPage === 'my_certificate' || currentPage === 'my_quiz' || currentPage === 'my_class') {
        openMenu(myRiOPSMenu, myRiOPSArrow);
    }
    
    // Auto-open Price menu if on pricing pages
    else if (window.location.pathname.indexOf('/admin/pricing/') !== -1) {
        openMenu(priceMenu, priceArrow);
    }
    
    // Auto-open Full Log menu if on full log pages (removed - no longer a menu, just a link)
    
    // Auto-open Training menu if on training pages
    else if (currentDir === 'training' || currentDir === 'quiz' || currentDir === 'certificate' || currentDir === 'class') {
        openMenu(trainingMenu, trainingArrow);
    }
    
    // Auto-open Dispatch menu if on dispatch pages
    else if (currentDir === 'dispatch' || currentDir === 'webform') {
        openMenu(dispatchMenu, dispatchArrow);
    }
    
    // Auto-open RLSS menu if on RLSS pages
    if (currentDir === 'rlss' || window.location.pathname.indexOf('/admin/rlss/') !== -1) {
        if (rlssMenu && rlssArrow) {
            openMenu(rlssMenu, rlssArrow);
        }
    }
    
    // Auto-open My RIOPS menu if on profile pages or my_quiz
    else if (currentDir === 'profile' || currentPage === 'my_recency' || currentPage === 'my_certificate' || currentPage === 'my_quiz' || currentPage === 'my_class') {
        openMenu(myRiOPSMenu, myRiOPSArrow);
    }

    // Close sidebar when clicking on a link (mobile)
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                closeSidebar();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        }
    });
});
</script>

<!-- Notification System -->
<script src="<?php echo getAbsolutePath('assets/js/notifications.js'); ?>"></script>
