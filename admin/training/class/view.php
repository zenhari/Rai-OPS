<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/class/view.php');

$current_user = getCurrentUser();
$db = getDBConnection();
$message = '';
$error = '';

// Get class ID
$classId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($classId <= 0) {
    header('Location: index.php');
    exit();
}

// Get class data with instructor info
$stmt = $db->prepare("SELECT c.id, c.name, c.duration, c.instructor_id, c.location, c.material_file, c.description, c.status, c.created_by, c.created_at, c.updated_at,
                     CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                     u.first_name as instructor_first_name,
                     u.last_name as instructor_last_name
                     FROM classes c
                     LEFT JOIN users u ON c.instructor_id = u.id
                     WHERE c.id = ?");
$stmt->execute([$classId]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Check if location is being fetched
if (!$class) {
    header('Location: index.php');
    exit();
}

if (!$class) {
    header('Location: index.php');
    exit();
}

// Get class schedules to determine start and end dates
$stmt = $db->prepare("SELECT MIN(start_date) as start_date, MAX(end_date) as end_date 
                     FROM class_schedules 
                     WHERE class_id = ?");
$stmt->execute([$classId]);
$scheduleInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$startDate = $scheduleInfo['start_date'] ?? $class['created_at'] ?? '';
$endDate = $scheduleInfo['end_date'] ?? '';

// Get all schedules with day_of_week and dates
$stmt = $db->prepare("SELECT day_of_week, start_date, end_date 
                     FROM class_schedules 
                     WHERE class_id = ? AND start_date IS NOT NULL AND end_date IS NOT NULL
                     ORDER BY start_date");
$stmt->execute([$classId]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get day number (0=Sunday, 1=Monday, ..., 6=Saturday)
function getDayNumber($dayOfWeek) {
    $days = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6
    ];
    return $days[strtolower($dayOfWeek)] ?? 0;
}

// Calculate all class dates
$classDates = [];
if (!empty($schedules)) {
    foreach ($schedules as $schedule) {
        // Skip if dates are not set
        if (empty($schedule['start_date']) || empty($schedule['end_date'])) {
            continue;
        }
        
        try {
            $start = new DateTime($schedule['start_date']);
            $end = new DateTime($schedule['end_date']);
            $targetDay = getDayNumber($schedule['day_of_week']);
            
            // Fix invalid dates (if start > end, swap them)
            if ($start > $end) {
                $temp = clone $start;
                $start = clone $end;
                $end = clone $temp;
            }
            
            // If start_date and end_date are the same, just add that date
            // (This handles single-day classes)
            if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                $dateStr = $start->format('Y-m-d');
                // Always add the date (array_unique will remove duplicates later)
                $classDates[] = $dateStr;
                continue; // Move to next schedule
            }
            
            // For date ranges, find all occurrences of the target day
            $current = clone $start;
            $currentDay = (int)$current->format('w'); // 0=Sunday, 6=Saturday
            
            // Calculate days to add to reach target day
            // If current day is the target day, use it; otherwise find next occurrence
            if ($currentDay != $targetDay) {
                $daysToAdd = ($targetDay - $currentDay + 7) % 7;
                if ($daysToAdd > 0) {
                    $current->modify("+{$daysToAdd} days");
                }
            }
            
            // If the calculated date is before start_date, move to next week
            if ($current < $start) {
                $current->modify('+7 days');
            }
            
            // Generate all dates for this schedule
            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                // Always add the date (array_unique will remove duplicates later)
                $classDates[] = $dateStr;
                $current->modify('+7 days'); // Next week same day
            }
        } catch (Exception $e) {
            // Skip invalid dates
            error_log("Error processing schedule date: " . $e->getMessage());
            continue;
        }
    }
    
    // Sort dates chronologically
    sort($classDates);
    
    // Remove duplicates (in case of overlapping schedules)
    $classDates = array_unique($classDates);
    $classDates = array_values($classDates); // Re-index array
}

// Count total class days
$totalClassDays = count($classDates);

// Debug: Log class dates for troubleshooting
if (empty($classDates)) {
    error_log("No class dates found for class_id: {$classId}. Schedules count: " . count($schedules));
}

// Get assigned students (users assigned to this class)
$stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.first_name as name_first, u.last_name as name_last
                     FROM class_assignments ca
                     JOIN users u ON ca.user_id = u.id
                     WHERE ca.class_id = ? AND ca.user_id IS NOT NULL
                     ORDER BY u.last_name, u.first_name");
$stmt->execute([$classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no direct user assignments, check role assignments and get users with those roles
if (empty($students)) {
    $stmt = $db->prepare("SELECT DISTINCT u.id, u.first_name, u.last_name, u.first_name as name_first, u.last_name as name_last
                         FROM class_assignments ca
                         JOIN roles r ON ca.role_id = r.id
                         JOIN users u ON u.role_id = r.id
                         WHERE ca.class_id = ? AND ca.role_id IS NOT NULL AND u.status = 'active'
                         ORDER BY u.last_name, u.first_name");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate course duration
$duration = $class['duration'] ?? '';
if (empty($duration) && $startDate && $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $diff = $start->diff($end);
    $days = $diff->days + 1;
    $duration = $days . ' days';
}

// Format dates
$formattedStartDate = $startDate ? date('Y-m-d', strtotime($startDate)) : '';
$formattedEndDate = $endDate ? date('Y-m-d', strtotime($endDate)) : '';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Attendance List - <?php echo htmlspecialchars($class['name']); ?> - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        @media print {
            body * {
                visibility: hidden;
            }
            .print-content, .print-content * {
                visibility: visible;
            }
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        .date-roll-call-cell {
            text-align: center;
        }
        .y-md-d-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .y-md-d-cell span {
            font-size: 10px;
            padding: 2px;
        }
        .date-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            font-size: 9px;
            padding: 4px 2px;
            line-height: 1.2;
        }
        .date-vertical span {
            display: block;
            writing-mode: vertical-rl;
            text-orientation: upright;
        }
        .date-in-header {
            display: block;
        }
        .attendance-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
        }
        .attendance-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #6b7280;
            border-radius: 3px;
            cursor: pointer;
            display: block;
            position: relative;
            background: white;
            -webkit-appearance: checkbox;
            appearance: checkbox;
        }
        .attendance-checkbox:hover {
            border-color: #374151;
        }
        @media print {
            .attendance-checkbox {
                width: 18px;
                height: 18px;
                border: 2px solid #000;
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                -webkit-appearance: checkbox;
                appearance: checkbox;
            }
            .attendance-cell {
                min-height: 40px;
            }
        }
        @media screen {
            .dark .attendance-checkbox {
                border-color: #9ca3af;
                background: #1f2937;
            }
            .dark .attendance-checkbox:hover {
                border-color: #d1d5db;
            }
            .dark .attendance-labels {
                color: #9ca3af;
            }
        }
        .no-print {
            @media print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 no-print">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-clipboard-list mr-2"></i>
                                Class Attendance List
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <?php echo htmlspecialchars($class['name']); ?>
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="window.print()" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-print mr-2"></i>
                                Print
                            </button>
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Attendance List Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden print-content">
                    <!-- Main Title -->
                    <div class="text-center py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Training Course Attendance List</h2>
                    </div>
                    
                    <!-- Course Title Header -->
                    <div class="bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 px-6 py-3">
                        <div class="flex items-center">
                            <span class="font-semibold mr-2 text-gray-700 dark:text-gray-200">Training course title:</span>
                            <span class="flex-1 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['name']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Course Details Table -->
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <table class="w-full border-collapse">
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <td class="border border-gray-300 dark:border-gray-600 p-3 w-1/2">
                                    <div class="flex">
                                        <span class="font-medium mr-2 text-gray-700 dark:text-gray-300">Start date:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($formattedStartDate); ?></span>
                                    </div>
                                </td>
                                <td class="border border-gray-300 dark:border-gray-600 p-3 w-1/2">
                                    <div class="flex">
                                        <span class="font-medium mr-2 text-gray-700 dark:text-gray-300">Ending date:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($formattedEndDate); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 dark:border-gray-600 p-3 bg-white dark:bg-gray-800">
                                    <div class="flex">
                                        <span class="font-medium mr-2 text-gray-700 dark:text-gray-300">Training course duration:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($duration); ?></span>
                                    </div>
                                </td>
                                <td class="border border-gray-300 dark:border-gray-600 p-3 bg-white dark:bg-gray-800">
                                    <div class="flex">
                                        <span class="font-medium mr-2 text-gray-700 dark:text-gray-300">Instructor:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['instructor_name'] ?? 'Not assigned'); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <td class="border border-gray-300 dark:border-gray-600 p-3">
                                    <div class="flex">
                                        <span class="font-medium mr-2 text-gray-700 dark:text-gray-300">Held on:</span>
                                        <span class="text-gray-900 dark:text-white">RMAW</span>
                                    </div>
                                </td>
                                <td class="border border-gray-300 dark:border-gray-600 p-3">
                                    <div class="flex">
                                        <span class="font-medium mr-2 text-gray-700 dark:text-gray-300">Event place:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars(isset($class['location']) && $class['location'] !== null && $class['location'] !== '' ? $class['location'] : 'Not specified'); ?></span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Main Attendance Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- First Header Row -->
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600 text-center">Row</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Surname</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Name</th>
                                    <?php if (!empty($classDates)): ?>
                                        <th colspan="<?php echo count($classDates); ?>" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                            Date and roll call
                                        </th>
                                    <?php else: ?>
                                        <th colspan="3" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Date and roll call</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600 no-print">Actions</th>
                                </tr>
                            </thead>
                            <!-- Second Header Row (under Date and roll call) -->
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"></th>
                                    <th class="px-6 py-3 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"></th>
                                    <th class="px-6 py-3 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"></th>
                                    <?php if (!empty($classDates)): ?>
                                        <?php foreach ($classDates as $date): ?>
                                        <?php 
                                        $dateObj = new DateTime($date);
                                        $dayOfWeekNumber = (int)$dateObj->format('w'); // 0=Sunday, 6=Saturday
                                        $dayNames = [
                                            0 => 'Sunday',
                                            1 => 'Monday',
                                            2 => 'Tuesday',
                                            3 => 'Wednesday',
                                            4 => 'Thursday',
                                            5 => 'Friday',
                                            6 => 'Saturday'
                                        ];
                                        $dayName = $dayNames[$dayOfWeekNumber] ?? 'Unknown';
                                        ?>
                                        <th class="px-2 py-3 text-center border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700" style="min-width: 35px; max-width: 45px;">
                                            <div class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                                <?php echo $dateObj->format('Y-m-d'); ?><br>
                                                <?php echo $dayName; ?>
                                            </div>
                                        </th>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <th class="px-6 py-3 text-center border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                                            <div class="y-md-d-cell">
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-200">Y</span>
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-200">M</span>
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-200">D</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-center border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"></th>
                                        <th class="px-6 py-3 text-center border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"></th>
                                    <?php endif; ?>
                                    <th class="px-6 py-3 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 no-print"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php 
                                $totalCols = 3 + (empty($classDates) ? 3 : count($classDates)) + 1; // Row + Surname + Name + Date columns + Actions
                                if (empty($students)): ?>
                                    <tr>
                                        <td colspan="<?php echo $totalCols; ?>" class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                            No students assigned to this class.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $index => $student): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center bg-white dark:bg-gray-800">
                                            <?php echo $index + 1; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                            <?php echo htmlspecialchars($student['last_name'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                            <?php echo htmlspecialchars($student['first_name'] ?? ''); ?>
                                        </td>
                                        <?php if (!empty($classDates)): ?>
                                            <?php foreach ($classDates as $date): ?>
                                            <td class="px-2 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center bg-white dark:bg-gray-800">
                                                <div class="attendance-cell">
                                                    <input type="checkbox" 
                                                           class="attendance-checkbox" 
                                                           id="attendance_<?php echo $student['id']; ?>_<?php echo str_replace('-', '_', $date); ?>"
                                                           name="attendance[<?php echo $student['id']; ?>][<?php echo $date; ?>]"
                                                           title="Mark attendance for <?php echo date('Y-m-d', strtotime($date)); ?>">
                                                </div>
                                            </td>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <td class="px-2 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center bg-white dark:bg-gray-800">
                                                <div class="attendance-cell">
                                                    <input type="checkbox" 
                                                           class="attendance-checkbox" 
                                                           id="attendance_<?php echo $student['id']; ?>_date1"
                                                           name="attendance[<?php echo $student['id']; ?>][date1]">
                                                </div>
                                            </td>
                                            <td class="px-2 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center bg-white dark:bg-gray-800">
                                                <div class="attendance-cell">
                                                    <input type="checkbox" 
                                                           class="attendance-checkbox" 
                                                           id="attendance_<?php echo $student['id']; ?>_date2"
                                                           name="attendance[<?php echo $student['id']; ?>][date2]">
                                                </div>
                                            </td>
                                            <td class="px-2 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center bg-white dark:bg-gray-800">
                                                <div class="attendance-cell">
                                                    <input type="checkbox" 
                                                           class="attendance-checkbox" 
                                                           id="attendance_<?php echo $student['id']; ?>_date3"
                                                           name="attendance[<?php echo $student['id']; ?>][date3]">
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                            <button onclick="issueCertificate(<?php echo $student['id']; ?>, <?php echo $classId; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200 no-print"
                                                    title="Issue Certificate">
                                                <i class="fas fa-certificate mr-1"></i>
                                                Issue Certificate
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Footer -->
                    <div class="mt-6 px-6 pb-6 flex justify-between items-end border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex-1">
                            <div class="font-medium mb-2 text-gray-700 dark:text-gray-300">Instructors Signature:</div>
                            <div class="border-b-2 border-gray-400 dark:border-gray-500 pb-1 min-h-[40px]"></div>
                        </div>
                        <div class="flex-1 ml-8">
                            <div class="font-medium mb-2 text-gray-700 dark:text-gray-300">Date:</div>
                            <div class="border-b-2 border-gray-400 dark:border-gray-500 pb-1 min-h-[40px]"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificate Issue Modal -->
    <div id="certificateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Issue Certificate</h3>
                    <button onclick="closeCertificateModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Issue certificate for: <strong id="modalStudentName" class="text-gray-900 dark:text-white"></strong>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        Course: <strong class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['name']); ?></strong>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Start Date: <strong class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($formattedStartDate); ?></strong>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        End Date: <strong class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($formattedEndDate); ?></strong>
                    </p>
                </div>
                <form id="certificateForm" class="space-y-4">
                    <input type="hidden" id="modalUserId" name="user_id">
                    <input type="hidden" id="modalClassId" name="class_id" value="<?php echo $classId; ?>">
                    
                    <div>
                        <label for="modalCertificateno" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Certificate Number <span class="text-red-500">*</span>
                            <span class="text-xs text-gray-500">(RMAW- prefix will be added automatically)</span>
                        </label>
                        <input type="text" id="modalCertificateno" name="certificateno" required
                               placeholder="Enter certificate number"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="modalIssueDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Issue Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="modalIssueDate" name="issue_date" required
                               value="<?php echo htmlspecialchars($formattedEndDate ?: date('Y-m-d')); ?>"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="modalExpireDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Expire Date
                        </label>
                        <input type="date" id="modalExpireDate" name="expire_date"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div id="certificateError" class="hidden bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md text-sm"></div>
                    <div id="certificateSuccess" class="hidden bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md text-sm"></div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCertificateModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            <i class="fas fa-certificate mr-2"></i>
                            Issue Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = null;
        let currentClassId = <?php echo $classId; ?>;
        
        function issueCertificate(userId, classId, studentName) {
            currentUserId = userId;
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalClassId').value = classId;
            document.getElementById('modalStudentName').textContent = studentName;
            // Generate default certificate number: YYYYMMDD + user ID (4 digits)
            const today = new Date();
            const dateStr = today.getFullYear() + 
                           String(today.getMonth() + 1).padStart(2, '0') + 
                           String(today.getDate()).padStart(2, '0');
            const defaultCertNo = dateStr + String(userId).padStart(4, '0');
            document.getElementById('modalCertificateno').value = defaultCertNo;
            document.getElementById('certificateError').classList.add('hidden');
            document.getElementById('certificateSuccess').classList.add('hidden');
            document.getElementById('certificateModal').classList.remove('hidden');
        }
        
        function closeCertificateModal() {
            document.getElementById('certificateModal').classList.add('hidden');
            document.getElementById('certificateError').classList.add('hidden');
            document.getElementById('certificateSuccess').classList.add('hidden');
        }
        
        document.getElementById('certificateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const errorDiv = document.getElementById('certificateError');
            const successDiv = document.getElementById('certificateSuccess');
            const submitBtn = e.target.querySelector('button[type="submit"]');
            
            // Hide previous messages
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Issuing...';
            
            // Get form data
            const formData = {
                user_id: document.getElementById('modalUserId').value,
                class_id: document.getElementById('modalClassId').value,
                certificateno: document.getElementById('modalCertificateno').value.trim(),
                issue_date: document.getElementById('modalIssueDate').value,
                expire_date: document.getElementById('modalExpireDate').value || null
            };
            
            // Send request
            fetch('api/issue_certificate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    // Try to get error message from response
                    return response.text().then(text => {
                        try {
                            const json = JSON.parse(text);
                            throw new Error(json.error || 'Server error: ' + response.status);
                        } catch (e) {
                            if (e instanceof Error && e.message) {
                                throw e;
                            }
                            throw new Error('Server error: ' + response.status + ' - ' + text.substring(0, 100));
                        }
                    });
                }
                return response.text().then(text => {
                    // Try to parse as JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response from server. Response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    successDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message + '<br><a href="' + data.certificate_url + '" target="_blank" class="text-green-600 dark:text-green-400 underline mt-2 inline-block">View Certificate</a>';
                    successDiv.classList.remove('hidden');
                    
                    // Close modal after 3 seconds
                    setTimeout(() => {
                        closeCertificateModal();
                        // Optionally reload the page or show a notification
                        showNotification('Certificate issued successfully!', 'success');
                    }, 3000);
                } else {
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + (data.error || 'Failed to issue certificate');
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>Error: ' + (error.message || 'Failed to issue certificate. Please check console for details.');
                errorDiv.classList.remove('hidden');
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-certificate mr-2"></i>Issue Certificate';
            });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('certificateModal');
            if (event.target === modal) {
                closeCertificateModal();
            }
        };
        
        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-md shadow-lg ${
                type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' :
                type === 'error' ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400' :
                'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transition = 'opacity 0.3s';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
