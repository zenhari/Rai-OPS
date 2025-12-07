<?php
require_once '../../config.php';

// Check access - but don't fail if page not in permissions yet
if (!checkPageAccessEnhanced('admin/profile/my_class.php')) {
    // If page doesn't exist in permissions, show a helpful message
    $permissionCheck = true;
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM page_permissions WHERE page_path = ?");
        $stmt->execute(['admin/profile/my_class.php']);
        $permissionCheck = $stmt->fetch();
    } catch (Exception $e) {
        // Ignore
    }
    
    if (!$permissionCheck) {
        // Page not in permissions - show message instead of redirecting
        $showPermissionMessage = true;
    } else {
        // User doesn't have access
        checkPageAccessWithRedirect('admin/profile/my_class.php');
    }
}

// Log activity
logActivity('view', __FILE__, ['page_name' => 'My Class', 'section' => 'My RIOPS']);

$current_user = getCurrentUser();
$db = getDBConnection();
$myClasses = [];
$error = '';

try {
    // Check if tables exist
    $tablesCheck = $db->query("SHOW TABLES LIKE 'classes'")->fetch();
    if (!$tablesCheck) {
        $error = 'Class Management tables not found. Please run the installation script: <a href="/database/install_class_management.php" class="text-blue-600 hover:underline">Install Class Management</a>';
    } else {
        // Get user's role_id
        $userRoleId = $current_user['role_id'] ?? null;

        // Get classes assigned to current user (directly or through role)
        $stmt = $db->prepare("SELECT DISTINCT c.*,
                        CONCAT(u.first_name, ' ', u.last_name) as instructor_name
                        FROM classes c
                        LEFT JOIN users u ON c.instructor_id = u.id
                        INNER JOIN class_assignments ca ON c.id = ca.class_id
                        WHERE c.status = 'active'
                        AND (
                            ca.user_id = :user_id
                            OR (ca.role_id = :role_id AND ca.role_id IS NOT NULL)
                        )
                        ORDER BY c.created_at DESC");
        $stmt->execute([
            'user_id' => $current_user['id'],
            'role_id' => $userRoleId
        ]);
        $myClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get detailed schedules for each class
        foreach ($myClasses as &$class) {
            $stmt = $db->prepare("SELECT * FROM class_schedules WHERE class_id = ? ORDER BY 
                FIELD(day_of_week, 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday')");
            $stmt->execute([$class['id']]);
            $class['schedules'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($class);
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage() . '. Please ensure Class Management tables are installed.';
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Class - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Class</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View your assigned training classes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php if (isset($showPermissionMessage)): ?>
                    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200 px-4 py-3 rounded mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Page Permission Required:</strong> This page needs to be added to the permission system. 
                        Please go to <a href="/admin/role_permission.php" class="underline font-semibold">Role Permission</a> 
                        and use Quick Add → "My Class" to add this page.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($myClasses) && !$error): ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center">
                        <i class="fas fa-chalkboard-teacher text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Classes Assigned</h3>
                        <p class="text-gray-500 dark:text-gray-400">You don't have any assigned classes at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($myClasses as $class): ?>
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900 dark:to-blue-800">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($class['name']); ?>
                                            </h3>
                                            <?php if ($class['duration']): ?>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                    <i class="fas fa-clock mr-1"></i><?php echo htmlspecialchars($class['duration']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($class['material_file']): ?>
                                            <a href="/<?php echo htmlspecialchars($class['material_file']); ?>" target="_blank"
                                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                <i class="fas fa-file-download mr-2"></i>Download Material
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="px-6 py-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Class Information -->
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Class Information</h4>
                                            <dl class="space-y-2">
                                                <?php if ($class['instructor_name']): ?>
                                                    <div class="flex items-center">
                                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 w-24">Instructor:</dt>
                                                        <dd class="text-sm text-gray-900 dark:text-white">
                                                            <i class="fas fa-user-tie mr-1"></i><?php echo htmlspecialchars($class['instructor_name']); ?>
                                                        </dd>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($class['location']): ?>
                                                    <div class="flex items-center">
                                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 w-24">Location:</dt>
                                                        <dd class="text-sm text-gray-900 dark:text-white">
                                                            <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($class['location']); ?>
                                                        </dd>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($class['description']): ?>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Description:</dt>
                                                        <dd class="text-sm text-gray-900 dark:text-white"><?php echo nl2br(htmlspecialchars($class['description'])); ?></dd>
                                                    </div>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                        
                                        <!-- Schedule -->
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Schedule</h4>
                                            <?php if (!empty($class['schedules'])): ?>
                                                <div class="space-y-2">
                                                    <?php 
                                                    $dayLabels = [
                                                        'saturday' => 'Saturday',
                                                        'sunday' => 'Sunday',
                                                        'monday' => 'Monday',
                                                        'tuesday' => 'Tuesday',
                                                        'wednesday' => 'Wednesday',
                                                        'thursday' => 'Thursday',
                                                        'friday' => 'Friday'
                                                    ];
                                                    foreach ($class['schedules'] as $schedule): 
                                                        $startTime = date('g:i A', strtotime($schedule['start_time']));
                                                        $endTime = date('g:i A', strtotime($schedule['end_time']));
                                                    ?>
                                                        <div class="flex items-center p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                                            <i class="fas fa-calendar-day text-blue-600 dark:text-blue-400 mr-2"></i>
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                                <?php echo $dayLabels[$schedule['day_of_week']]; ?>
                                                            </span>
                                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                                <?php echo $startTime; ?> - <?php echo $endTime; ?>
                                                            </span>
                                                            <?php if ($schedule['start_date'] || $schedule['end_date']): ?>
                                                                <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                                                                    <?php if ($schedule['start_date']): ?>
                                                                        From: <?php echo date('M j, Y', strtotime($schedule['start_date'])); ?>
                                                                    <?php endif; ?>
                                                                    <?php if ($schedule['end_date']): ?>
                                                                        To: <?php echo date('M j, Y', strtotime($schedule['end_date'])); ?>
                                                                    <?php endif; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">No schedule information available.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

