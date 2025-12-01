<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/roster/roster_management.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_assignments':
            $assignments = json_decode($_POST['assignments'] ?? '[]', true);
            $deletions = json_decode($_POST['deletions'] ?? '[]', true);
            if (bulkSaveRosterAssignments($assignments, $deletions, $current_user['id'])) {
                $message = 'Roster assignments saved successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to save roster assignments.';
                $message_type = 'error';
            }
            break;
    }
}

// Get date range from GET or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Validate dates
if (!strtotime($startDate) || !strtotime($endDate)) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

// Ensure start date is before end date
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// Helper function to get file URL
function getFileUrl($path) {
    if (empty($path)) return null;
    // If path already starts with /uploads/profile/, use as is
    if (strpos($path, '/uploads/profile/') === 0) {
        return $path;
    }
    // If path already starts with uploads/profile/, prepend /
    if (strpos($path, 'uploads/profile/') === 0) {
        return '/' . $path;
    }
    // Otherwise, assume it's in uploads/profile folder
    return '/uploads/profile/' . $path;
}

// Get crew users
$crewUsers = getCrewUsers();

// Group users by position
$groupedUsers = [];
foreach ($crewUsers as $user) {
    $position = $user['position'] ?? 'Other';
    if (!isset($groupedUsers[$position])) {
        $groupedUsers[$position] = [];
    }
    $groupedUsers[$position][] = $user;
}

// Sort groups alphabetically
ksort($groupedUsers);

// Get existing roster assignments
$rosterAssignments = getRosterAssignments($startDate, $endDate);

// Get all shift codes
$shiftCodes = getAllShiftCodes();

// Generate date range
$dates = [];
$currentDate = strtotime($startDate);
$endTimestamp = strtotime($endDate);
while ($currentDate <= $endTimestamp) {
    $dates[] = date('Y-m-d', $currentDate);
    $currentDate = strtotime('+1 day', $currentDate);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roster Management - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Inter (Modern & Clean) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="/assets/js/tailwind.js"></script>
    
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Dark mode detection from browser */
        @media (prefers-color-scheme: dark) {
            html:not(.light) {
                color-scheme: dark;
            }
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Roster Box Styles */
        .roster-box {
            width: 45px;
            height: 45px;
            border: 1px solid;
            border-color: rgb(209, 213, 219);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 4px;
            background: white;
            aspect-ratio: 1;
        }
        
        .dark .roster-box {
            border-color: rgb(75, 85, 99);
            background: rgb(31, 41, 55);
        }
        
        .roster-box:hover {
            border-color: rgb(59, 130, 246);
        }
        
        .roster-box.selected {
            border-color: rgb(59, 130, 246);
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .roster-box.filled {
            border-width: 2px;
            font-weight: 700;
        }
        
        /* Sticky elements */
        .sticky-user {
            position: sticky;
            left: 0;
            z-index: 10;
            background: white;
            border-right: 1px solid rgb(229, 231, 235);
        }
        
        .dark .sticky-user {
            background: rgb(31, 41, 55);
            border-right-color: rgb(55, 65, 81);
        }
        
        .sticky-date-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgb(249, 250, 251);
            border-bottom: 1px solid rgb(229, 231, 235);
        }
        
        .dark .sticky-date-header {
            background: rgb(17, 24, 39);
            border-bottom-color: rgb(55, 65, 81);
        }
        
        /* Date header */
        .date-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            min-width: 50px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            color: rgb(107, 114, 128);
        }
        
        .dark .date-header {
            color: rgb(156, 163, 175);
        }
        
        /* Smooth scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgb(243, 244, 246);
            border-radius: 4px;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-track {
            background: rgb(31, 41, 55);
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgb(209, 213, 219);
            border-radius: 4px;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgb(75, 85, 99);
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgb(156, 163, 175);
        }
        
        /* Button styles */
        .btn-primary {
            background: rgb(59, 130, 246);
        }
        
        .btn-primary:hover {
            background: rgb(37, 99, 235);
        }
        
        /* Card styles */
        .card {
            background: white;
            border-radius: 6px;
            border: 1px solid rgb(229, 231, 235);
        }
        
        .dark .card {
            background: rgb(31, 41, 55);
            border-color: rgb(55, 65, 81);
        }
        
        /* Input styles */
        .input-modern {
            border-radius: 4px;
            border: 1px solid rgb(209, 213, 219);
        }
        
        .dark .input-modern {
            border-color: rgb(75, 85, 99);
            background: rgb(17, 24, 39);
        }
        
        .input-modern:focus {
            border-color: rgb(59, 130, 246);
            outline: none;
        }
        
        /* Mode indicator */
        .mode-indicator {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: white;
            border: 1px solid rgb(229, 231, 235);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 50;
        }
        
        .dark .mode-indicator {
            background: rgb(31, 41, 55);
            border-color: rgb(55, 65, 81);
        }
        
        /* Shift Code Badge Styles */
        .shift-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 4px;
            border: 2px solid;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            position: relative;
            min-width: 80px;
            text-align: center;
        }
        
        .shift-badge:hover {
            border-color: rgb(59, 130, 246);
        }
        
        .shift-badge.selected {
            border-width: 3px;
            border-color: rgb(59, 130, 246);
        }
        
        .shift-badge.selected::after {
            content: '✓';
            position: absolute;
            top: -8px;
            right: -8px;
            width: 18px;
            height: 18px;
            background: rgb(34, 197, 94);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            border: 2px solid white;
        }
        
        .dark .shift-badge.selected::after {
            border-color: rgb(31, 41, 55);
        }
        
        /* Tree structure styles */
        .role-group-header {
            cursor: pointer;
            user-select: none;
        }
        
        .role-group-header:hover {
            background-color: rgb(249, 250, 251);
        }
        
        .dark .role-group-header:hover {
            background-color: rgb(31, 41, 55);
        }
        
        .role-group-arrow {
            transition: transform 0.15s ease;
        }
        
        .role-group-arrow.expanded {
            transform: rotate(90deg);
        }
        
        .role-group-user-row {
            display: none;
        }
        
        .role-group-user-row.expanded {
            display: table-row;
        }
        
        .role-group-row {
            background-color: rgb(249, 250, 251);
        }
        
        .dark .role-group-row {
            background-color: rgb(17, 24, 39);
        }
        
        .user-row {
            background-color: white;
        }
        
        .dark .user-row {
            background-color: rgb(31, 41, 55);
        }
        
        /* Active button state */
        .btn-active {
            position: relative;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        
        .btn-active::after {
            content: '✓';
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: rgb(34, 197, 94);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: 2px solid white;
        }
        
        .dark .btn-active::after {
            border-color: rgb(31, 41, 55);
        }
        
        /* Range selection styles */
        .roster-box.range-start {
            border-color: rgb(59, 130, 246) !important;
            border-width: 3px !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        .roster-box.range-end {
            border-color: rgb(59, 130, 246) !important;
            border-width: 3px !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        .roster-box.range-selected {
            background-color: rgba(59, 130, 246, 0.1) !important;
            border-color: rgb(59, 130, 246) !important;
        }
        
        /* From flights box styles - cannot be cleared */
        .roster-box.from-flights {
            position: relative;
        }
        
        .roster-box.from-flights::after {
            content: '';
            position: absolute;
            top: 2px;
            right: 2px;
            width: 6px;
            height: 6px;
            background-color: rgb(34, 197, 94);
            border-radius: 50%;
            border: 1px solid white;
        }
        
        .dark .roster-box.from-flights::after {
            border-color: rgb(31, 41, 55);
        }
        
        .roster-box.from-flights.clear-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .roster-box.from-flights.clear-disabled:hover {
            border-color: rgb(239, 68, 68) !important;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-30">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                Roster Management
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage shift code assignments for crew members</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6 space-y-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="card p-4 border-l-4 <?php echo $message_type == 'success' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-red-500 bg-red-50 dark:bg-red-900/20'; ?> animate-slide-in">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> text-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-800 dark:text-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-200">
                                    <?php echo htmlspecialchars($message); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Date Range Selection Card -->
                <div class="card p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Date Range</h2>
                    </div>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" 
                                   class="input-modern w-full px-3 py-2 text-sm focus:outline-none dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" 
                                   class="input-modern w-full px-3 py-2 text-sm focus:outline-none dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary w-full px-4 py-2 text-white rounded font-medium text-sm">
                                <i class="fas fa-search mr-2"></i>Load
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Action Panel Card -->
                <div class="card p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Actions</h2>
                    </div>
                    
                    <!-- Shift Code Selection -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Select Shift Code</label>
                        <div class="flex flex-wrap gap-2" id="shiftCodeBadges">
                            <?php foreach ($shiftCodes as $shift): ?>
                                <?php if ($shift['enabled']): ?>
                                    <?php 
                                    $bgColor = $shift['background_color'] ?? '#ffffff';
                                    $textColor = $shift['text_color'] ?? '#000000';
                                    ?>
                                    <div class="shift-badge" 
                                         data-shift-id="<?php echo $shift['id']; ?>"
                                         data-code="<?php echo htmlspecialchars($shift['code']); ?>"
                                         data-bg="<?php echo htmlspecialchars($bgColor); ?>"
                                         data-text="<?php echo htmlspecialchars($textColor); ?>"
                                         style="background-color: <?php echo htmlspecialchars($bgColor); ?>; color: <?php echo htmlspecialchars($textColor); ?>; border-color: <?php echo htmlspecialchars($bgColor); ?>;">
                                        <span class="font-semibold"><?php echo htmlspecialchars(strtoupper($shift['code'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fill Mode</label>
                            <button type="button" id="fillBtn" 
                                    class="w-full px-4 py-2 bg-green-600 text-white rounded font-medium text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed" 
                                    disabled>
                                <i class="fas fa-fill-drip mr-2"></i>Fill
                            </button>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Clear Mode</label>
                            <button type="button" id="clearBtn" 
                                    class="w-full px-4 py-2 bg-red-600 text-white rounded font-medium text-sm hover:bg-red-700">
                                <i class="fas fa-eraser mr-2"></i>Clear
                            </button>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Range Mode</label>
                            <button type="button" id="rangeBtn" 
                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded font-medium text-sm hover:bg-blue-700">
                                <i class="fas fa-vector-square mr-2"></i>Range
                            </button>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Save Changes</label>
                            <button type="button" id="saveBtn" 
                                    class="btn-primary w-full px-4 py-2 text-white rounded font-semibold text-sm">
                                <i class="fas fa-save mr-2"></i>Save All
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Roster Calendar -->
                <?php if (!empty($dates) && !empty($crewUsers)): ?>
                <div class="card overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Roster Calendar</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <?php echo count($crewUsers); ?> crew members • <?php echo count($dates); ?> days • <?php echo count($groupedUsers); ?> roles
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="sticky-date-header">
                                <tr>
                                    <th class="sticky-user px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        <div class="flex items-center">
                                            <i class="fas fa-user mr-2"></i>Crew Member
                                        </div>
                                    </th>
                                    <?php foreach ($dates as $date): ?>
                                        <th class="px-2 py-4 text-center border-r border-gray-300 dark:border-gray-600" style="vertical-align: middle;">
                                            <div class="text-xs font-bold text-gray-700 dark:text-gray-300 mx-auto" style="writing-mode: vertical-rl; text-orientation: mixed; height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; width: fit-content;">
                                                <div><?php echo date('j/n/Y', strtotime($date)); ?></div>
                                                <div class="text-xs mt-1 opacity-75 font-normal"><?php echo date('D', strtotime($date)); ?></div>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700/50">
                                <?php 
                                $groupIndex = 0;
                                foreach ($groupedUsers as $position => $users): 
                                    $groupId = 'group-' . $groupIndex;
                                    $groupIndex++;
                                ?>
                                    <!-- Role Group Header -->
                                    <tr class="role-group-row role-group-header" data-group-id="<?php echo $groupId; ?>">
                                        <td class="sticky-user px-6 py-3" colspan="<?php echo count($dates) + 1; ?>">
                                            <div class="flex items-center">
                                                <i class="fas fa-chevron-right role-group-arrow mr-3 text-gray-400 text-xs"></i>
                                                <div class="w-7 h-7 rounded bg-indigo-600 flex items-center justify-center text-white text-xs mr-3">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($position); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                        <?php echo count($users); ?> member<?php echo count($users) > 1 ? 's' : ''; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Users in this group -->
                                    <?php foreach ($users as $user): ?>
                                        <tr class="user-row role-group-user-row expanded" data-group-id="<?php echo $groupId; ?>" data-user-id="<?php echo $user['id']; ?>">
                                            <td class="sticky-user px-6 py-4 whitespace-nowrap pl-12">
                                                <div class="flex items-center">
                                                    <?php 
                                                    // Get user image - priority: picture > personnel_image > avatar
                                                    $userImage = null;
                                                    if (!empty($user['picture'])) {
                                                        $userImage = getFileUrl($user['picture']);
                                                    } elseif (!empty($user['personnel_image'])) {
                                                        $userImage = getFileUrl($user['personnel_image']);
                                                    }
                                                    $userInitials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                                                    ?>
                                                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-xs mr-3 overflow-hidden flex-shrink-0" style="<?php echo $userImage ? 'background-color: transparent;' : ''; ?>">
                                                        <?php if ($userImage): ?>
                                                            <img src="<?php echo htmlspecialchars($userImage); ?>" 
                                                                 alt="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                                 class="w-full h-full object-cover"
                                                                 onerror="this.style.display='none'; this.parentElement.style.backgroundColor='rgb(37, 99, 235)'; this.parentElement.innerHTML='<?php echo htmlspecialchars($userInitials); ?>';">
                                                        <?php else: ?>
                                                            <?php echo $userInitials; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                            <?php echo htmlspecialchars($user['position']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php foreach ($dates as $date): ?>
                                                <?php 
                                                $assignment = $rosterAssignments[$user['id']][$date] ?? null;
                                                $shiftCodeId = $assignment['shift_code_id'] ?? null;
                                                $shiftCode = $assignment['shift_code'] ?? '';
                                                $bgColor = $assignment['background_color'] ?? '#ffffff';
                                                $textColor = $assignment['text_color'] ?? '#000000';
                                                $fromFlights = isset($assignment['from_flights']) && $assignment['from_flights'] ? 'true' : 'false';
                                                ?>
                                                <td class="px-2 py-3">
                                                    <div class="roster-box <?php echo $shiftCodeId ? 'filled' : ''; ?> <?php echo $fromFlights === 'true' ? 'from-flights' : ''; ?>" 
                                                         data-user-id="<?php echo $user['id']; ?>"
                                                         data-date="<?php echo $date; ?>"
                                                         data-shift-code-id="<?php echo $shiftCodeId ?? ''; ?>"
                                                         data-from-flights="<?php echo $fromFlights; ?>"
                                                         style="<?php if ($shiftCodeId): ?>background-color: <?php echo htmlspecialchars($bgColor); ?>; color: <?php echo htmlspecialchars($textColor); ?>; border-color: <?php echo htmlspecialchars($bgColor); ?>;<?php endif; ?>">
                                                        <?php if ($shiftCode): ?>
                                                            <?php echo htmlspecialchars(strtoupper($shiftCode)); ?>
                                                        <?php else: ?>
                                                            <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif (empty($crewUsers)): ?>
                    <div class="card p-12 text-center">
                        <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users-slash text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">No crew members found.</p>
                    </div>
                <?php elseif (empty($dates)): ?>
                    <div class="card p-12 text-center">
                        <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-calendar-times text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Please select a date range.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle (Optional - can be removed if using browser preference only) -->
    <div class="mode-indicator" id="modeToggle" title="Toggle Dark Mode">
        <i class="fas fa-moon text-gray-600 dark:text-yellow-400 text-xl"></i>
    </div>

    <script>
        // Dark mode detection and toggle
        (function() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const html = document.documentElement;
            
            // Check localStorage first, then browser preference
            const savedMode = localStorage.getItem('darkMode');
            if (savedMode === 'dark' || (savedMode === null && prefersDark)) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            
            // Toggle dark mode
            document.getElementById('modeToggle')?.addEventListener('click', function() {
                html.classList.toggle('dark');
                localStorage.setItem('darkMode', html.classList.contains('dark') ? 'dark' : 'light');
            });
            
            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem('darkMode')) {
                    if (e.matches) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                }
            });
        })();

        let selectedShiftCode = null;
        let isFillMode = false;
        let isClearMode = false;
        let isRangeMode = false;
        let rangeStartBox = null;
        let rangeEndBox = null;
        
        // Function to reset all modes
        function resetModes() {
            isFillMode = false;
            isClearMode = false;
            isRangeMode = false;
            rangeStartBox = null;
            rangeEndBox = null;
            
            // Remove active states from buttons
            document.getElementById('fillBtn').classList.remove('btn-active', 'ring-2', 'ring-green-500', 'ring-offset-1');
            document.getElementById('clearBtn').classList.remove('btn-active', 'ring-2', 'ring-red-500', 'ring-offset-1');
            document.getElementById('rangeBtn').classList.remove('btn-active', 'ring-2', 'ring-blue-500', 'ring-offset-1');
            
            // Remove range selection classes
            document.querySelectorAll('.roster-box').forEach(box => {
                box.classList.remove('range-start', 'range-end', 'range-selected', 'clear-disabled');
            });
            
            document.body.style.cursor = '';
        }

        // Shift code badge selection
        document.querySelectorAll('.shift-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                // Remove selected class from all badges
                document.querySelectorAll('.shift-badge').forEach(b => b.classList.remove('selected'));
                
                // Add selected class to clicked badge
                this.classList.add('selected');
                
                // Set selected shift code
                selectedShiftCode = {
                    id: this.dataset.shiftId,
                    code: this.dataset.code,
                    bg: this.dataset.bg,
                    text: this.dataset.text
                };
                
                // Enable fill button
                document.getElementById('fillBtn').disabled = false;
            });
        });

        // Fill mode
        document.getElementById('fillBtn').addEventListener('click', function() {
            if (!selectedShiftCode) {
                alert('Please select a shift code first.');
                return;
            }
            resetModes();
            isFillMode = true;
            this.classList.add('btn-active', 'ring-2', 'ring-green-500', 'ring-offset-1');
            document.body.style.cursor = 'crosshair';
        });

        // Clear mode
        document.getElementById('clearBtn').addEventListener('click', function() {
            resetModes();
            isClearMode = true;
            this.classList.add('btn-active', 'ring-2', 'ring-red-500', 'ring-offset-1');
            document.body.style.cursor = 'crosshair';
            
            // Mark boxes from flights as disabled for clearing
            document.querySelectorAll('.roster-box.from-flights').forEach(box => {
                box.classList.add('clear-disabled');
            });
        });
        
        // Range mode - can work with Fill or Clear mode
        document.getElementById('rangeBtn').addEventListener('click', function() {
            // Don't reset Fill/Clear modes, just activate Range
            isRangeMode = true;
            this.classList.add('btn-active', 'ring-2', 'ring-blue-500', 'ring-offset-1');
            document.body.style.cursor = 'crosshair';
        });

        // Function to get box coordinates (row index, column index)
        function getBoxCoordinates(box) {
            const row = box.closest('tr');
            const tbody = row.closest('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr.user-row'));
            const rowIndex = rows.indexOf(row);
            
            const cells = Array.from(row.querySelectorAll('td'));
            const cellIndex = cells.indexOf(box.closest('td'));
            
            return { rowIndex, cellIndex };
        }
        
        // Function to get all boxes in range
        function getBoxesInRange(startBox, endBox) {
            const startCoords = getBoxCoordinates(startBox);
            const endCoords = getBoxCoordinates(endBox);
            
            const minRow = Math.min(startCoords.rowIndex, endCoords.rowIndex);
            const maxRow = Math.max(startCoords.rowIndex, endCoords.rowIndex);
            const minCell = Math.min(startCoords.cellIndex, endCoords.cellIndex);
            const maxCell = Math.max(startCoords.cellIndex, endCoords.cellIndex);
            
            const tbody = startBox.closest('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr.user-row'));
            const boxes = [];
            
            for (let r = minRow; r <= maxRow; r++) {
                const row = rows[r];
                if (row) {
                    const cells = Array.from(row.querySelectorAll('td'));
                    for (let c = minCell; c <= maxCell; c++) {
                        const cell = cells[c];
                        if (cell) {
                            const box = cell.querySelector('.roster-box');
                            if (box) {
                                boxes.push(box);
                            }
                        }
                    }
                }
            }
            
            return boxes;
        }
        
        // Function to apply fill to boxes
        function applyFillToBoxes(boxes) {
            if (!selectedShiftCode) return;
            
            boxes.forEach(box => {
                // When user manually assigns, remove from-flights flag
                if (box.dataset.fromFlights === 'true') {
                    box.dataset.fromFlights = 'false';
                    box.classList.remove('from-flights', 'clear-disabled');
                }
                box.dataset.shiftCodeId = selectedShiftCode.id;
                box.style.backgroundColor = selectedShiftCode.bg;
                box.style.color = selectedShiftCode.text;
                box.style.borderColor = selectedShiftCode.bg;
                box.classList.add('filled');
                box.innerHTML = selectedShiftCode.code.toUpperCase();
            });
        }
        
        // Function to clear boxes (skip boxes from flights)
        function clearBoxes(boxes) {
            boxes.forEach(box => {
                // Skip boxes that come from flights table
                if (box.dataset.fromFlights === 'true') {
                    return;
                }
                box.dataset.shiftCodeId = '';
                box.style.backgroundColor = '';
                box.style.color = '';
                box.style.borderColor = '';
                box.classList.remove('filled');
                box.innerHTML = '<span class="text-gray-300 dark:text-gray-600 text-xs">—</span>';
            });
        }

        // Box click handlers
        document.querySelectorAll('.roster-box').forEach(box => {
            box.addEventListener('click', function() {
                if (isRangeMode) {
                    // Range selection mode
                    if (!rangeStartBox) {
                        // First click - set start
                        rangeStartBox = this;
                        this.classList.add('range-start');
                        document.querySelectorAll('.roster-box').forEach(b => {
                            b.classList.remove('range-end', 'range-selected');
                        });
                    } else if (rangeStartBox === this) {
                        // Clicked same box - reset
                        rangeStartBox = null;
                        this.classList.remove('range-start');
                    } else {
                        // Second click - set end and apply action
                        rangeEndBox = this;
                        const boxesInRange = getBoxesInRange(rangeStartBox, rangeEndBox);
                        
                        // Remove previous range markers
                        document.querySelectorAll('.roster-box').forEach(b => {
                            b.classList.remove('range-start', 'range-end', 'range-selected');
                        });
                        
                        // Mark range
                        rangeStartBox.classList.add('range-start');
                        rangeEndBox.classList.add('range-end');
                        boxesInRange.forEach(b => {
                            if (b !== rangeStartBox && b !== rangeEndBox) {
                                b.classList.add('range-selected');
                            }
                        });
                        
                        // Apply fill or clear based on active mode
                        if (isFillMode && selectedShiftCode) {
                            // When filling, remove from-flights flag if user manually assigns
                            boxesInRange.forEach(box => {
                                if (box.dataset.fromFlights === 'true') {
                                    box.dataset.fromFlights = 'false';
                                    box.classList.remove('from-flights', 'clear-disabled');
                                }
                            });
                            applyFillToBoxes(boxesInRange);
                        } else if (isClearMode) {
                            clearBoxes(boxesInRange);
                        }
                        
                        // Reset range selection after a short delay
                        setTimeout(() => {
                            document.querySelectorAll('.roster-box').forEach(b => {
                                b.classList.remove('range-start', 'range-end', 'range-selected');
                            });
                            rangeStartBox = null;
                            rangeEndBox = null;
                        }, 1000);
                    }
                } else if (isFillMode && selectedShiftCode) {
                    // Fill the box
                    // When user manually assigns, remove from-flights flag
                    if (this.dataset.fromFlights === 'true') {
                        this.dataset.fromFlights = 'false';
                        this.classList.remove('from-flights', 'clear-disabled');
                    }
                    this.dataset.shiftCodeId = selectedShiftCode.id;
                    this.style.backgroundColor = selectedShiftCode.bg;
                    this.style.color = selectedShiftCode.text;
                    this.style.borderColor = selectedShiftCode.bg;
                    this.classList.add('filled');
                    this.innerHTML = selectedShiftCode.code.toUpperCase();
                    this.classList.add('selected');
                    setTimeout(() => this.classList.remove('selected'), 300);
                } else if (isClearMode) {
                    // Clear the box (skip if from flights)
                    if (this.dataset.fromFlights === 'true') {
                        // Show a brief visual feedback that this box cannot be cleared
                        this.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 200);
                        return;
                    }
                    this.dataset.shiftCodeId = '';
                    this.style.backgroundColor = '';
                    this.style.color = '';
                    this.style.borderColor = '';
                    this.classList.remove('filled');
                    this.innerHTML = '<span class="text-gray-300 dark:text-gray-600 text-xs">—</span>';
                    this.classList.add('selected');
                    setTimeout(() => this.classList.remove('selected'), 300);
                }
            });

            box.addEventListener('mouseenter', function() {
                if (isFillMode || isClearMode) {
                    this.style.opacity = '0.8';
                } else if (isRangeMode && rangeStartBox) {
                    // Show preview of range
                    const boxesInRange = getBoxesInRange(rangeStartBox, this);
                    document.querySelectorAll('.roster-box').forEach(b => {
                        b.classList.remove('range-selected');
                    });
                    boxesInRange.forEach(b => {
                        if (b !== rangeStartBox && b !== this) {
                            b.classList.add('range-selected');
                        }
                    });
                    this.classList.add('range-end');
                }
            });

            box.addEventListener('mouseleave', function() {
                if (isFillMode || isClearMode) {
                    this.style.opacity = '1';
                } else if (isRangeMode && rangeStartBox && this !== rangeStartBox) {
                    this.classList.remove('range-end');
                    if (!rangeEndBox) {
                        document.querySelectorAll('.roster-box').forEach(b => {
                            if (b !== rangeStartBox) {
                                b.classList.remove('range-selected');
                            }
                        });
                    }
                }
            });
        });

        // Save button
        document.getElementById('saveBtn').addEventListener('click', function() {
            const btn = this;
            const originalHTML = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            
            // Reset modes after save
            resetModes();
            
            const assignments = [];
            const deletions = [];
            document.querySelectorAll('.roster-box').forEach(box => {
                const shiftCodeId = box.dataset.shiftCodeId;
                const fromFlights = box.dataset.fromFlights === 'true';
                
                // If box is from flights and still has shift_code_id, don't save it
                // (it will be auto-generated on next load)
                if (fromFlights && shiftCodeId) {
                    return; // Skip saving boxes that come from flights
                }
                
                // If box was cleared (no shift_code_id) and it's not from flights, mark for deletion
                if (!shiftCodeId && !fromFlights) {
                    // Check if there was an original assignment (we need to track this)
                    // For now, we'll include it in assignments with null shift_code_id
                    // The backend will handle deletion
                    deletions.push({
                        user_id: box.dataset.userId,
                        date: box.dataset.date
                    });
                } else if (shiftCodeId) {
                    // Normal assignment
                    assignments.push({
                        user_id: box.dataset.userId,
                        date: box.dataset.date,
                        shift_code_id: shiftCodeId
                    });
                }
            });

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="save_assignments">
                <input type="hidden" name="assignments" value='${JSON.stringify(assignments)}'>
                <input type="hidden" name="deletions" value='${JSON.stringify(deletions)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        });
        
        // Role group toggle functionality
        document.querySelectorAll('.role-group-header').forEach(header => {
            header.addEventListener('click', function() {
                const groupId = this.dataset.groupId;
                const userRows = document.querySelectorAll(`.role-group-user-row[data-group-id="${groupId}"]`);
                const arrow = this.querySelector('.role-group-arrow');
                
                if (userRows.length > 0) {
                    const isExpanded = userRows[0].classList.contains('expanded');
                    
                    userRows.forEach(row => {
                        if (isExpanded) {
                            row.classList.remove('expanded');
                        } else {
                            row.classList.add('expanded');
                        }
                    });
                    
                    if (isExpanded) {
                        arrow.classList.remove('expanded');
                    } else {
                        arrow.classList.add('expanded');
                    }
                }
            });
        });
        
        // Auto-expand all groups on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.role-group-user-row').forEach(row => {
                row.classList.add('expanded');
            });
            document.querySelectorAll('.role-group-arrow').forEach(arrow => {
                arrow.classList.add('expanded');
            });
        });
    </script>
</body>
</html>
