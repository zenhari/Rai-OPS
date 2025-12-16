<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/class/index.php');

// Log activity
logActivity('view', __FILE__, ['page_name' => 'Defined Class', 'section' => 'Training']);

$current_user = getCurrentUser();
$db = getDBConnection();

// Check if user has access to delete class
$canDelete = checkPageAccessEnhanced('admin/training/class/delete');

// Get all classes with instructor and schedule info
$stmt = $db->query("SELECT c.*, 
                    CONCAT(u1.first_name, ' ', u1.last_name) as instructor_name,
                    CONCAT(u2.first_name, ' ', u2.last_name) as created_by_name,
                    (SELECT COUNT(*) FROM class_schedules WHERE class_id = c.id) as schedule_count,
                    (SELECT COUNT(*) FROM class_assignments WHERE class_id = c.id) as assignment_count
                    FROM classes c
                    LEFT JOIN users u1 ON c.instructor_id = u1.id
                    LEFT JOIN users u2 ON c.created_by = u2.id
                    ORDER BY c.created_at DESC");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defined Class - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Defined Class</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and manage all training classes</p>
                        </div>
                        <div>
                            <a href="create.php"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i>
                                Create New Class
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php if (empty($classes)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center">
                        <i class="fas fa-chalkboard-teacher text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Classes Found</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Get started by creating your first class.</p>
                        <a href="create.php"
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            Create Class
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-md p-3">
                                    <i class="fas fa-chalkboard-teacher text-blue-600 dark:text-blue-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Classes</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo count($classes); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-md p-3">
                                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Classes</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo count(array_filter($classes, function($c) { return $c['status'] == 'active'; })); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-md p-3">
                                    <i class="fas fa-calendar-alt text-purple-600 dark:text-purple-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Schedules</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo array_sum(array_column($classes, 'schedule_count')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-orange-100 dark:bg-orange-900 rounded-md p-3">
                                    <i class="fas fa-user-check text-orange-600 dark:text-orange-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Assignments</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo array_sum(array_column($classes, 'assignment_count')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Classes Table -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">All Classes</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Class Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Instructor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Schedules</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Assignments</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($classes as $class): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($class['name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($class['duration'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($class['instructor_name'] ?? 'Not assigned'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($class['location'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    <?php echo $class['schedule_count']; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-user-check mr-1"></i>
                                                    <?php echo $class['assignment_count']; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $statusColors = [
                                                    'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                    'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                ];
                                                $statusColor = $statusColors[$class['status']] ?? $statusColors['inactive'];
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusColor; ?>">
                                                    <?php echo ucfirst($class['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($class['created_by_name'] ?? 'Unknown'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    <a href="edit.php?id=<?php echo $class['id']; ?>"
                                                       class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view.php?id=<?php echo $class['id']; ?>"
                                                       class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300"
                                                       title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($canDelete): ?>
                                                        <a href="delete.php?id=<?php echo $class['id']; ?>"
                                                           class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                           title="Delete Class"
                                                           onclick="return confirm('Are you sure you want to delete this class? This action cannot be undone.');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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
</body>
</html>

