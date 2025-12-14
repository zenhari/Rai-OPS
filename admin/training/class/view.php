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
// Get ALL schedules, even if dates are NULL (we'll handle them separately)
$stmt = $db->prepare("SELECT day_of_week, start_date, end_date 
                     FROM class_schedules 
                     WHERE class_id = ?
                     ORDER BY day_of_week, start_date");
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
        $targetDay = getDayNumber($schedule['day_of_week']);
        
        // If dates are not set, skip this schedule (it won't contribute to class dates)
        if (empty($schedule['start_date']) || empty($schedule['end_date'])) {
            continue;
        }
        
        try {
            $start = new DateTime($schedule['start_date']);
            $end = new DateTime($schedule['end_date']);
            
            $startDateStr = $start->format('Y-m-d');
            $endDateStr = $end->format('Y-m-d');
            
            // For single-day schedules (start_date == end_date), just add the date
            // if it matches the day_of_week (or add it anyway to show all scheduled dates)
            if ($startDateStr === $endDateStr) {
                $startDay = (int)$start->format('w');
                // Add the date regardless of day match (data might be set incorrectly)
                // but log a warning if it doesn't match
                if ($startDay != $targetDay) {
                    error_log("Warning: Date {$startDateStr} (day {$startDay}) doesn't match day_of_week {$targetDay} for class_id {$classId}, but adding it anyway");
                }
                $classDates[] = $startDateStr;
                continue;
            }
            
            // For date ranges, add both start_date and end_date if they match the target day
            // Also add any dates in between that match the target day
            
            // Add start_date if it matches the target day
            $startDay = (int)$start->format('w');
            if ($startDay == $targetDay) {
                $classDates[] = $startDateStr;
            }
            
            // Add end_date if it's different and matches the target day
            $endDay = (int)$end->format('w');
            if ($endDateStr !== $startDateStr && $endDay == $targetDay) {
                $classDates[] = $endDateStr;
            }
            
            // Generate dates in the range that match the target day
            // Determine which date is earlier and which is later
            $earlier = $start < $end ? clone $start : clone $end;
            $later = $start < $end ? clone $end : clone $start;
            
            $current = clone $earlier;
            $currentDay = (int)$current->format('w');
            
            // Move to the target day if not already there
            if ($currentDay != $targetDay) {
                $daysToAdd = ($targetDay - $currentDay + 7) % 7;
                if ($daysToAdd > 0) {
                    $current->modify("+{$daysToAdd} days");
                }
            }
            
            // If we went past the later date, skip the range generation
            if ($current > $later) {
                // But we've already added start_date and/or end_date above
                continue;
            }
            
            // Generate all dates in the range that match the target day
            while ($current <= $later) {
                $dateStr = $current->format('Y-m-d');
                // Only add if not already added
                if (!in_array($dateStr, $classDates)) {
                    $dateDay = (int)$current->format('w');
                    if ($dateDay == $targetDay) {
                        $classDates[] = $dateStr;
                    }
                }
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

// NOTE: We ONLY use dates from class_schedules table
// We do NOT generate additional dates based on duration
// If schedules are defined, we use only those dates
// Duration is just informational and doesn't affect the actual class dates

// Debug: Log schedules and class dates for troubleshooting
error_log("=== Class ID: {$classId} Debug ===");
error_log("Total schedules found: " . count($schedules));
foreach ($schedules as $idx => $schedule) {
    error_log("Schedule {$idx}: day_of_week=" . ($schedule['day_of_week'] ?? 'NULL') . 
              ", start_date=" . ($schedule['start_date'] ?? 'NULL') . 
              ", end_date=" . ($schedule['end_date'] ?? 'NULL'));
}
error_log("Total class dates calculated: " . count($classDates));
if (!empty($classDates)) {
    error_log("Class dates: " . implode(', ', $classDates));
} else {
    error_log("No class dates found from schedules.");
}
error_log("=== End Debug ===");

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
            @page {
                size: portrait;
                margin: 1.5cm 0.5cm 3cm 0.5cm;
                @top-left {
                    content: element(course-header);
                    vertical-align: top;
                }
                @bottom-center {
                    content: "Page " counter(page) " of " counter(pages);
                    font-size: 11pt;
                }
            }
            /* Combined table structure for header repeat */
            .print-combined-table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .print-combined-table thead {
                display: table-header-group !important;
            }
            .print-combined-table tbody {
                display: table-row-group !important;
            }
            .print-combined-table tfoot {
                display: table-footer-group !important;
            }
            .print-combined-table thead tr {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                display: table-row !important;
            }
            .print-combined-table thead td {
                display: table-cell !important;
            }
            /* Footer - Repeat on every page - CRITICAL */
            .print-combined-table tfoot,
            .print-page-footer {
                display: table-footer-group !important;
                width: 100% !important;
            }
            .print-combined-table tfoot tr,
            .print-page-footer tr {
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                display: table-row !important;
            }
            .print-combined-table tfoot td,
            .print-page-footer td {
                padding: 0 !important;
                border: 0 !important;
                display: table-cell !important;
            }
            .print-page-footer div {
                display: block !important;
                visibility: visible !important;
            }
            /* Ensure footer content is visible */
            .print-page-footer * {
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Force footer to repeat on every page */
            tfoot {
                display: table-footer-group !important;
            }
            .print-combined-table tfoot {
                display: table-footer-group !important;
                position: relative !important;
            }
            /* Ensure footer is visible and repeats */
            .print-combined-table tfoot,
            .print-combined-table tfoot.print-page-footer,
            tfoot.print-page-footer {
                display: table-footer-group !important;
                visibility: visible !important;
            }
            .print-combined-table tfoot tr,
            .print-combined-table tfoot.print-page-footer tr,
            tfoot.print-page-footer tr {
                display: table-row !important;
                visibility: visible !important;
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
            }
            .print-combined-table tfoot td,
            .print-combined-table tfoot.print-page-footer td,
            tfoot.print-page-footer td {
                display: table-cell !important;
                visibility: visible !important;
            }
            /* Ensure footer content is properly displayed */
            .print-page-footer .flex {
                display: flex !important;
            }
            .print-page-footer div {
                display: block !important;
                visibility: visible !important;
            }
            /* Final override to ensure footer repeats on every page */
            table.print-combined-table tfoot {
                display: table-footer-group !important;
            }
            table.print-combined-table tfoot.print-page-footer {
                display: table-footer-group !important;
            }
            /* Ensure footer doesn't break */
            .print-combined-table tfoot tr {
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }
            /* Ensure header section displays correctly and repeats on every page */
            .print-page-header {
                display: table-header-group !important;
                width: 100% !important;
            }
            .print-page-header tr {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }
            .print-page-header td {
                padding: 0 !important;
                border: 0 !important;
                display: table-cell !important;
            }
            /* Logo styling in print */
            .print-logo {
                display: block !important;
                visibility: visible !important;
                max-height: 60px !important;
                width: auto !important;
                margin: 0 auto !important;
            }
            .print-page-header td div {
                display: block !important;
                visibility: visible !important;
            }
            .print-page-header td img {
                display: block !important;
                visibility: visible !important;
            }
            /* Ensure nested table displays correctly */
            .print-page-header table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .print-page-header table thead {
                display: table-header-group !important;
            }
            .print-page-header table thead tr {
                display: table-row !important;
            }
            .print-page-header table thead td {
                display: table-cell !important;
            }
            /* Ensure course details header table displays correctly */
            .course-details-header table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .course-details-header table thead {
                display: table-header-group !important;
            }
            .course-details-header table thead tr {
                display: table-row !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }
            .course-details-header table thead td {
                display: table-cell !important;
            }
            /* Ensure course details header appears at top of each page */
            .course-details-header {
                page-break-inside: avoid !important;
            }
            /* Reset html and body */
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: auto !important;
                background: #fff !important;
            }
            /* Hide sidebar and navigation */
            aside, nav, .no-print, #certificateModal {
                display: none !important;
            }
            /* Hide hamburger menu and all menu buttons */
            button[aria-label*="menu"],
            button[aria-label*="Menu"],
            [class*="hamburger"],
            [class*="menu-button"],
            [class*="sidebar-toggle"],
            [id*="menu"],
            [id*="Menu"],
            [id*="sidebar"],
            [id*="Sidebar"],
            aside *,
            nav *,
            [role="button"][aria-expanded],
            [data-drawer],
            [data-menu] {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            /* Hide fixed positioned elements in top corners (likely menu buttons) */
            [style*="position: fixed"][style*="top"],
            [style*="position:fixed"][style*="top"],
            [style*="position: absolute"][style*="top"]:not(.print-content):not(.print-content *) {
                display: none !important;
                visibility: hidden !important;
            }
            /* Hide any button or icon in top-left/top-right corners */
            body > button:first-of-type,
            body > div:first-of-type > button,
            [class*="fixed"][class*="top"][class*="left"],
            [class*="fixed"][class*="top"][class*="right"] {
                display: none !important;
                visibility: hidden !important;
            }
            /* Hide permission banner */
            [class*="permission"], [id*="permission"], [class*="banner"] {
                display: none !important;
            }
            /* Hide all elements outside print-content that might be UI elements */
            body > *:not(.print-content):not(script):not(style) {
                position: static !important;
            }
            /* Specifically hide any floating buttons or icons */
            button[class*="fixed"],
            div[class*="fixed"]:not(.print-content):not(.print-content *),
            [class*="floating"],
            [class*="fab"],
            i[class*="fa-bars"],
            svg[class*="menu"] {
                display: none !important;
                visibility: hidden !important;
            }
            /* Show all parent containers - CRITICAL */
            body > div,
            body > div.lg\:ml-64,
            body > div > div,
            body > div > div.flex-col,
            body > div > div > div.flex-1,
            body > div > div > div.p-6 {
                display: block !important;
                visibility: visible !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                position: static !important;
                height: auto !important;
                overflow: visible !important;
            }
            /* Remove sidebar margin */
            .lg\:ml-64 {
                margin-left: 0 !important;
            }
            /* Show print-content - CRITICAL */
            .print-content {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                position: static !important;
                height: auto !important;
                background: #fff !important;
            }
            /* Show all children of print-content - CRITICAL */
            .print-content,
            .print-content *:not(.no-print) {
                visibility: visible !important;
                opacity: 1 !important;
                color: #000 !important;
            }
            /* Ensure divs are displayed */
            .print-content > div,
            .print-content div {
                display: block !important;
                visibility: visible !important;
                height: auto !important;
            }
            /* Ensure tables are displayed */
            .print-content table,
            table {
                display: table !important;
                visibility: visible !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .print-content table thead,
            table thead {
                display: table-header-group !important;
                visibility: visible !important;
            }
            .print-content table tbody,
            table tbody {
                display: table-row-group !important;
                visibility: visible !important;
            }
            .print-content table tr,
            table tr {
                display: table-row !important;
                visibility: visible !important;
            }
            .print-content table td:not(.no-print),
            .print-content table th:not(.no-print),
            table td:not(.no-print),
            table th:not(.no-print) {
                display: table-cell !important;
                visibility: visible !important;
                border: 1px solid #000 !important;
            }
            /* Ensure text elements are visible */
            .print-content h1,
            .print-content h2,
            .print-content h3,
            .print-content h4,
            .print-content h5,
            .print-content h6,
            .print-content p,
            .print-content span {
                display: block !important;
                visibility: visible !important;
                color: #000 !important;
            }
            .print-content span {
                display: inline !important;
            }
            /* Ensure overflow containers don't hide content */
            .overflow-x-auto {
                overflow: visible !important;
                display: block !important;
                width: 100% !important;
                visibility: visible !important;
            }
            .overflow-x-auto table {
                display: table !important;
                width: 100% !important;
                visibility: visible !important;
            }
            /* Ensure all divs inside print-content are visible */
            .print-content > div,
            .print-content > div > div,
            .print-content div {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                height: auto !important;
                min-height: auto !important;
            }
            /* Ensure overflow containers show content */
            .print-content .overflow-x-auto {
                overflow: visible !important;
                display: block !important;
                width: 100% !important;
                visibility: visible !important;
            }
            /* Ensure all text and spans are visible */
            .print-content span {
                display: inline !important;
                visibility: visible !important;
                opacity: 1 !important;
                color: #000 !important;
            }
            .print-content p,
            .print-content h1,
            .print-content h2,
            .print-content h3,
            .print-content h4,
            .print-content h5,
            .print-content h6 {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                color: #000 !important;
            }
            /* Hide all elements with no-print class */
            .no-print,
            .no-print * {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                overflow: hidden !important;
                font-size: 0 !important;
                line-height: 0 !important;
            }
            /* Hide "Date and roll call" header column in print */
            thead tr:first-child th:nth-child(4),
            thead tr:first-child th[colspan],
            thead tr:first-child th.no-print-header {
                display: none !important;
                visibility: hidden !important;
            }
            /* Hide Actions column header and cells - CRITICAL */
            th.no-print,
            td.no-print,
            thead th.no-print,
            tbody td.no-print {
                display: none !important;
                visibility: hidden !important;
                width: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                overflow: hidden !important;
                font-size: 0 !important;
                line-height: 0 !important;
            }
            /* Hide Actions column using nth-child as backup (last column) */
            thead tr:first-child th:last-child,
            thead tr:last-child th:last-child,
            tbody tr td:last-child {
                display: none !important;
                visibility: hidden !important;
            }
            /* Additional rule to hide Actions column in all tables */
            table th.no-print,
            table td.no-print,
            .print-content table th.no-print,
            .print-content table td.no-print,
            #attendanceTable th.no-print,
            #attendanceTable td.no-print {
                display: none !important;
                visibility: hidden !important;
                width: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                overflow: hidden !important;
                font-size: 0 !important;
                line-height: 0 !important;
            }
            /* Table styling */
            table {
                width: 100% !important;
                font-size: 13.2px !important; /* 10% increase from 12px */
                table-layout: fixed;
                border-collapse: collapse !important;
                display: table !important;
                visibility: visible !important;
            }
            /* Ensure all table elements are visible with correct display types */
            table {
                display: table !important;
                visibility: visible !important;
                width: 100% !important;
            }
            table thead {
                display: table-header-group !important;
                visibility: visible !important;
            }
            table tbody {
                display: table-row-group !important;
                visibility: visible !important;
            }
            table tfoot {
                display: table-footer-group !important;
                visibility: visible !important;
            }
            table tr {
                display: table-row !important;
                visibility: visible !important;
            }
            table th:not(.no-print),
            table td:not(.no-print) {
                display: table-cell !important;
                visibility: visible !important;
            }
            /* Row column - wider width for print */
            th:first-child,
            td:first-child {
                width: 4% !important;
                min-width: 30px !important;
                max-width: 50px !important;
                padding: 4px 5px !important;
                text-align: center !important;
            }
            /* Specifically for attendance table */
            #attendanceTable th:first-child,
            #attendanceTable td:first-child {
                width: 4% !important;
                min-width: 30px !important;
                max-width: 50px !important;
                padding: 4px 5px !important;
                text-align: center !important;
            }
            /* Keep header on each page - repeat on every page */
            thead {
                display: table-header-group !important;
            }
            thead tr {
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            /* Ensure attendance table header repeats on every page */
            #attendanceTable thead {
                display: table-header-group !important;
                visibility: visible !important;
            }
            /* Ensure both header rows repeat together */
            #attendanceTable thead tr {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                display: table-row !important;
                visibility: visible !important;
            }
            /* Keep both header rows together - don't break between them */
            #attendanceTable thead tr:first-child {
                page-break-after: avoid !important;
            }
            #attendanceTable thead tr:last-child {
                page-break-before: avoid !important;
            }
            #attendanceTable thead th {
                display: table-cell !important;
                visibility: visible !important;
            }
            /* Course Details Header - Repeat on every page */
            .course-details-header table thead {
                display: table-header-group !important;
            }
            .course-details-header table thead tr {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                display: table-row !important;
            }
            .course-details-header table thead td {
                display: table-cell !important;
            }
            /* Force header to repeat on each page */
            table {
                border-collapse: collapse;
            }
            /* No page breaks - all rows on one page in portrait */
            tbody tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            /* Remove all forced page breaks */
            tbody tr:nth-child(n) {
                page-break-after: auto !important;
                page-break-before: auto !important;
            }
            /* Ensure table header repeats */
            thead {
                display: table-header-group;
            }
            tbody {
                display: table-row-group;
            }
            /* Keep rows together */
            tbody tr {
                page-break-inside: avoid;
                height: auto;
            }
            /* Adjust cell padding for print - 10% font increase */
            th, td {
                padding: 4px 5px !important;
                font-size: 13.2px !important; /* 10% increase from 12px */
                line-height: 1.3 !important;
            }
            /* Header font size - 10% increase */
            thead th {
                padding: 4px 5px !important;
                font-size: 13.2px !important; /* 10% increase from 12px */
                font-weight: bold;
            }
            /* Body text - 10% increase */
            tbody td {
                font-size: 13.2px !important; /* 10% increase from 12px */
            }
            /* Compact attendance cells */
            .attendance-cell {
                min-height: 30px !important;
                padding: 3px !important;
            }
            .attendance-checkbox {
                width: 14px !important;
                height: 14px !important;
                margin: 0 auto;
                display: block !important;
                visibility: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            /* Ensure attendance cells are visible */
            .attendance-cell {
                display: flex !important;
                visibility: visible !important;
                align-items: center !important;
                justify-content: center !important;
            }
            /* Ensure header repeats on each page */
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
            /* Prevent page break in header section */
            .print-content > div:first-child,
            .print-content > div:nth-child(2),
            .print-content > div:nth-child(3) {
                page-break-after: avoid;
                page-break-inside: avoid;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Ensure course details section is visible */
            .print-content > div:nth-child(3),
            .print-content > div:nth-child(4) {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Force all content inside print-content to be visible */
            .print-content *:not(.no-print):not(.no-print *) {
                visibility: visible !important;
                opacity: 1 !important;
            }
            .print-content > div:nth-child(3) table {
                display: table !important;
                visibility: visible !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .print-content > div:nth-child(3) table tr {
                display: table-row !important;
                visibility: visible !important;
            }
            .print-content > div:nth-child(3) table td {
                display: table-cell !important;
                visibility: visible !important;
                border: 1px solid #000 !important;
                padding: 8px !important;
                color: #000 !important;
            }
            /* Make sure second header row doesn't break */
            thead tr:last-child {
                page-break-inside: avoid;
            }
            /* Adjust all text sizes by 10% */
            .print-content {
                font-size: 110% !important;
            }
            .print-content h2 {
                font-size: 132% !important; /* 10% increase from 120% */
            }
            .print-content table {
                font-size: 110% !important;
            }
            /* Ensure all text in print is 10% larger */
            .print-content * {
                font-size: 110% !important;
            }
            /* All rows on one page - no page breaks */
            tbody tr {
                height: auto !important;
                page-break-after: auto !important;
                page-break-before: auto !important;
            }
            /* Ensure course details table is visible and properly styled */
            .print-content table {
                border-collapse: collapse !important;
                width: 100% !important;
            }
            .print-content table td:not(.no-print),
            .print-content table th:not(.no-print) {
                border: 1px solid #000 !important;
                padding: 8px !important;
                font-size: 13.2px !important; /* 10% increase from 12px */
                color: #000 !important;
                background: #fff !important;
            }
            /* Course details table styling */
            .print-content > div > div:first-child,
            .print-content > div > div:nth-child(2),
            .print-content > div > div:nth-child(3) {
                page-break-inside: avoid !important;
                background: #fff !important;
                color: #000 !important;
            }
            /* Ensure all text is black in print */
            .print-content * {
                color: #000 !important;
            }
            .print-content h2 {
                color: #000 !important;
            }
            /* Main title - show only on first page in content, but in header on all pages */
            .main-title-header {
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                margin-bottom: 0 !important;
                padding-bottom: 8px !important;
            }
            .main-title-header h2 {
                margin: 0 !important;
                padding: 0 !important;
                font-size: 18pt !important;
            }
            /* Course title header */
            .course-title-header {
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
                padding: 6px 8px !important;
            }
            /* Course details header - compact for print */
            .course-details-header {
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
            }
            /* Hide separate course title header in print */
            .course-title-header {
                display: none !important;
            }
            /* Style course details table for print */
            .course-details-table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 1px solid #000 !important;
                margin: 0 !important;
            }
            .course-details-table thead tr {
                border: 1px solid #000 !important;
            }
            .course-details-table thead td {
                border: 1px solid #000 !important;
                padding: 0.15in 0.075in !important;
                font-size: 12pt !important;
                vertical-align: top !important;
            }
            /* First row - purple background with course title */
            .course-details-table .course-title-row {
                background: #61207F !important;
            }
            .course-details-table .course-title-row td {
                background: #61207F !important;
                color: white !important;
                font-weight: bold !important;
                padding: 0.15in 0.075in !important;
                border: 1px solid #000 !important;
            }
            .course-details-table .course-title-row td span {
                color: white !important;
                font-weight: bold !important;
            }
            /* Other rows - normal styling with bold labels */
            .course-details-table .course-details-row-1 td,
            .course-details-table .course-details-row-2 td,
            .course-details-table .course-details-row-3 td {
                background: #fff !important;
                color: #000 !important;
            }
            .course-details-table .course-details-row-1 td span:first-child,
            .course-details-table .course-details-row-2 td span:first-child,
            .course-details-table .course-details-row-3 td span:first-child {
                color: #000 !important;
                font-weight: bold !important;
            }
            .course-details-table .course-details-row-1 td .course-details-value,
            .course-details-table .course-details-row-2 td .course-details-value,
            .course-details-table .course-details-row-3 td .course-details-value {
                color: #000 !important;
                font-weight: normal !important;
            }
            /* Hide flex containers in print, show as inline */
            .course-details-table td .flex {
                display: block !important;
            }
            .course-details-table td span {
                display: inline !important;
            }
            /* Hide print-only-row in screen view */
            @media screen {
                .print-only-row {
                    display: none !important;
                }
            }
            /* Improve spacing between sections */
            .print-content > div {
                margin-bottom: 0 !important;
            }
            /* Remove extra padding from print-content */
            .print-content {
                padding: 0 !important;
                margin: 0 !important;
            }
            /* Ensure proper spacing before attendance table */
            #attendanceTable {
                margin-top: 8px !important;
            }
            /* Ensure table text is visible */
            .print-content table th:not(.no-print),
            .print-content table td:not(.no-print) {
                color: #000 !important;
                background: #fff !important;
            }
            .print-content table thead th:not(.no-print) {
                color: #000 !important;
                background: #f3f4f6 !important;
            }
            .print-content table tbody td:not(.no-print) {
                color: #000 !important;
                background: #fff !important;
            }
            /* Course title header */
            .print-content > div > div:nth-child(2) {
                background: #f3f4f6 !important;
                border-bottom: 1px solid #000 !important;
            }
            .print-content > div > div:nth-child(2) span {
                color: #000 !important;
            }
            /* Course details table rows */
            .print-content > div > div:nth-child(3) table tr {
                background: #fff !important;
            }
            .print-content > div > div:nth-child(3) table tr.bg-gray-50,
            .print-content > div > div:nth-child(3) table tr:nth-child(odd) {
                background: #f9fafb !important;
            }
            .print-content > div > div:nth-child(3) table td {
                color: #000 !important;
                background: inherit !important;
            }
            /* Ensure borders are visible */
            .print-content table,
            .print-content table td,
            .print-content table th {
                border-color: #000 !important;
            }
            /* Ensure empty state message is visible */
            .print-content tbody td[colspan] {
                display: table-cell !important;
                visibility: visible !important;
                text-align: center !important;
            }
            /* Print-specific styling for course info */
            .print-content {
                background: #fff !important;
            }
            /* Ensure proper display for all elements */
            .print-content {
                display: block !important;
                position: static !important;
            }
            .print-content > div {
                display: block !important;
                position: static !important;
                visibility: visible !important;
            }
            .print-content table {
                display: table !important;
                width: 100% !important;
                visibility: visible !important;
                position: static !important;
            }
            .print-content table thead {
                display: table-header-group !important;
                visibility: visible !important;
            }
            .print-content table tbody {
                display: table-row-group !important;
                visibility: visible !important;
            }
            .print-content table tr {
                display: table-row !important;
                visibility: visible !important;
            }
            .print-content table td:not(.no-print),
            .print-content table th:not(.no-print) {
                display: table-cell !important;
                visibility: visible !important;
            }
            /* Specifically ensure attendance table is visible */
            #attendanceTable {
                display: table !important;
                width: 100% !important;
                visibility: visible !important;
            }
            #attendanceTable thead {
                display: table-header-group !important;
                visibility: visible !important;
            }
            /* Ensure both header rows repeat on every page */
            #attendanceTable thead:first-of-type,
            #attendanceTable thead:last-of-type {
                display: table-header-group !important;
                visibility: visible !important;
            }
            #attendanceTable thead tr {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                display: table-row !important;
                visibility: visible !important;
            }
            #attendanceTable thead th {
                display: table-cell !important;
                visibility: visible !important;
            }
            #attendanceTable tbody {
                display: table-row-group !important;
                visibility: visible !important;
            }
            #attendanceTable tr {
                display: table-row !important;
                visibility: visible !important;
                page-break-inside: avoid;
            }
            #attendanceTable th:not(.no-print),
            #attendanceTable td:not(.no-print) {
                display: table-cell !important;
                visibility: visible !important;
                border: 1px solid #000 !important;
            }
            /* Final override to ensure all print-content is visible */
            .print-content,
            .print-content *:not(.no-print) {
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Ensure print-content and its parent containers are visible */
            .print-content {
                display: block !important;
            }
            .print-content > * {
                display: block !important;
            }
            .print-content table {
                display: table !important;
            }
            .print-content table > * {
                display: table-row-group !important;
            }
            .print-content table thead {
                display: table-header-group !important;
            }
            .print-content table tbody {
                display: table-row-group !important;
            }
            .print-content table tr {
                display: table-row !important;
            }
            .print-content table td:not(.no-print),
            .print-content table th:not(.no-print) {
                display: table-cell !important;
            }
            /* Ensure flex containers display as block in print */
            .print-content .flex {
                display: block !important;
            }
            .print-content .flex > * {
                display: inline-block !important;
            }
            /* Ensure permission banner is hidden */
            .permission-banner,
            [class*="permission-banner"],
            [id*="permission-banner"] {
                display: none !important;
                visibility: hidden !important;
            }
            /* Ensure all divs in the main content area are visible */
            body > div.lg\:ml-64 > div > div.flex-1 {
                display: block !important;
                visibility: visible !important;
            }
            /* Make sure overflow containers don't clip content */
            .overflow-x-auto,
            .overflow-hidden {
                overflow: visible !important;
            }
            /* Force all text elements to be visible */
            .print-content p,
            .print-content span,
            .print-content div,
            .print-content h1,
            .print-content h2,
            .print-content h3,
            .print-content h4,
            .print-content h5,
            .print-content h6 {
                color: #000 !important;
                background: transparent !important;
            }
            /* Ensure no element is hidden by height or display */
            .print-content * {
                max-height: none !important;
                min-height: auto !important;
            }
            /* Remove any transforms or positioning that might hide content */
            .print-content,
            .print-content * {
                transform: none !important;
            }
            .print-content table {
                position: static !important;
            }
            /* FINAL OVERRIDE: Make absolutely sure print-content is visible */
            body .print-content {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                z-index: 1 !important;
            }
            /* Make sure all content inside is visible */
            body .print-content *:not(.no-print) {
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Specific display types for different elements */
            body .print-content div {
                display: block !important;
            }
            body .print-content table {
                display: table !important;
            }
            body .print-content table thead {
                display: table-header-group !important;
            }
            body .print-content table tbody {
                display: table-row-group !important;
            }
            body .print-content table tr {
                display: table-row !important;
            }
            body .print-content table td:not(.no-print),
            body .print-content table th:not(.no-print) {
                display: table-cell !important;
            }
            /* ABSOLUTE FINAL OVERRIDE - Show everything */
            body * {
                max-height: none !important;
            }
            body .print-content *:not(.no-print) {
                visibility: visible !important;
                opacity: 1 !important;
            }
            body .print-content div {
                display: block !important;
            }
            body .print-content table {
                display: table !important;
            }
            body .print-content table thead {
                display: table-header-group !important;
            }
            body .print-content table tbody {
                display: table-row-group !important;
            }
            body .print-content table tr {
                display: table-row !important;
            }
            body .print-content table td:not(.no-print),
            body .print-content table th:not(.no-print) {
                display: table-cell !important;
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
            /* Hide print header in screen view */
            .print-page-header {
                display: none !important;
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
                            <button onclick="printAttendanceTable()" 
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
                    <!-- Main Title - Only on first page -->
                    <div class="text-center py-3 border-b-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 main-title-header">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Training Course Attendance List</h2>
                    </div>
                    
                    <!-- Course Title Header -->
                    <div class="bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 px-4 py-2 course-title-header">
                        <div class="flex items-center">
                            <span class="font-semibold text-sm mr-2 text-gray-700 dark:text-gray-200">Training course title:</span>
                            <span class="flex-1 font-medium text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['name']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Combined Table with Header and Attendance -->
                    <table class="w-full border-collapse print-combined-table">
                        <!-- Header Section - Repeat on every page -->
                        <thead class="print-page-header">
                            <!-- Logo Row - Repeat on every page -->
                            <tr>
                                <td colspan="100%" class="p-0 border-0 text-center">
                                    <div class="py-2">
                                        <img src="/assets/raimon.png" alt="Raimon Logo" class="h-16 mx-auto print-logo">
                                    </div>
                                </td>
                            </tr>
                            <!-- Course Details Table as header rows -->
                            <tr>
                                <td colspan="100%" class="p-0 border-0">
                                    <table class="w-full border-collapse course-details-table">
                                        <thead>
                                            <!-- First row: Course title (purple background in print) -->
                                            <tr class="course-title-row print-only-row">
                                                <td colspan="2" class="border border-gray-300 dark:border-gray-600 p-2 course-title-cell">
                                                    <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">Training course title:</span>
                                                    <span class="font-medium text-sm text-gray-900 dark:text-white ml-2"><?php echo htmlspecialchars($class['name']); ?></span>
                                                </td>
                                            </tr>
                                            <!-- Second row: Start date / Ending date -->
                                            <tr class="bg-gray-50 dark:bg-gray-700/50 course-details-row-1">
                                                <td class="border border-gray-300 dark:border-gray-600 p-2 w-1/2 course-details-cell-start">
                                                    <div class="flex items-center">
                                                        <span class="font-medium text-xs mr-2 text-gray-700 dark:text-gray-300">Start date:</span>
                                                        <span class="text-sm text-gray-900 dark:text-white course-details-value"><?php echo htmlspecialchars($formattedStartDate); ?></span>
                                                    </div>
                                                </td>
                                                <td class="border border-gray-300 dark:border-gray-600 p-2 w-1/2 course-details-cell-end">
                                                    <div class="flex items-center">
                                                        <span class="font-medium text-xs mr-2 text-gray-700 dark:text-gray-300">Ending date:</span>
                                                        <span class="text-sm text-gray-900 dark:text-white course-details-value"><?php echo htmlspecialchars($formattedEndDate); ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Third row: Duration / Instructor -->
                                            <tr class="course-details-row-2">
                                                <td class="border border-gray-300 dark:border-gray-600 p-2 bg-white dark:bg-gray-800 course-details-cell-start">
                                                    <div class="flex items-center">
                                                        <span class="font-medium text-xs mr-2 text-gray-700 dark:text-gray-300">Training course duration:</span>
                                                        <span class="text-sm text-gray-900 dark:text-white course-details-value"><?php echo htmlspecialchars($duration)." Hrs."; ?></span>
                                                    </div>
                                                </td>
                                                <td class="border border-gray-300 dark:border-gray-600 p-2 bg-white dark:bg-gray-800 course-details-cell-end">
                                                    <div class="flex items-center">
                                                        <span class="font-medium text-xs mr-2 text-gray-700 dark:text-gray-300">Instructor:</span>
                                                        <span class="text-sm text-gray-900 dark:text-white course-details-value"><?php echo htmlspecialchars($class['instructor_name'] ?? 'Not assigned'); ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Fourth row: Held on / Event place -->
                                            <tr class="bg-gray-50 dark:bg-gray-700/50 course-details-row-3">
                                                <td class="border border-gray-300 dark:border-gray-600 p-2 course-details-cell-start">
                                                    <div class="flex items-center">
                                                        <span class="font-medium text-xs mr-2 text-gray-700 dark:text-gray-300">Held on:</span>
                                                        <span class="text-sm text-gray-900 dark:text-white course-details-value">RMAW</span>
                                                    </div>
                                                </td>
                                                <td class="border border-gray-300 dark:border-gray-600 p-2 course-details-cell-end">
                                                    <div class="flex items-center">
                                                        <span class="font-medium text-xs mr-2 text-gray-700 dark:text-gray-300">Event place:</span>
                                                        <span class="text-sm text-gray-900 dark:text-white course-details-value"><?php echo htmlspecialchars(isset($class['location']) && $class['location'] !== null && $class['location'] !== '' ? $class['location'] : 'Not specified'); ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </thead>
                                    </table>
                                </td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="100%" class="p-0 border-0">
                                    <!-- Main Attendance Table -->
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="attendanceTable">
                            <!-- Header Rows - Combined in single thead for print repeat -->
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <!-- First Header Row -->
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600 text-center">Row</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Surname</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">Name</th>
                                    <?php if (!empty($classDates)): ?>
                                        <th colspan="<?php echo count($classDates); ?>" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600 no-print-header">
                                            Date and roll call
                                        </th>
                                    <?php else: ?>
                                        <th colspan="3" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600 no-print-header">Date and roll call</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600 no-print">Actions</th>
                                </tr>
                                <!-- Second Header Row (under Date and roll call) -->
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 no-print">
                                            <button onclick="issueCertificate(<?php echo $student['id']; ?>, <?php echo $classId; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200"
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
                                </td>
                            </tr>
                        </tbody>
                        <!-- Footer - Repeat on every page -->
                        <tfoot class="print-page-footer">
                            <tr>
                                <td colspan="100%" class="p-0 border-0">
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
                                </td>
                            </tr>
                        </tfoot>
                    </table>
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
        
        function printAttendanceTable() {
            // Use window.print() directly - the CSS @media print styles will handle the formatting
            // This is simpler and more reliable than opening a new window
            window.print();
        }
        
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

