<?php
require_once '../../../config.php';

// Check access to delete page
checkPageAccessWithRedirect('admin/training/class/delete');

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

// Get class data
$stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$classId]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    header('Location: index.php');
    exit();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $db->beginTransaction();
        
        // Delete class schedules
        $stmt = $db->prepare("DELETE FROM class_schedules WHERE class_id = ?");
        $stmt->execute([$classId]);
        
        // Delete class assignments
        $stmt = $db->prepare("DELETE FROM class_assignments WHERE class_id = ?");
        $stmt->execute([$classId]);
        
        // Delete material file if exists
        if (!empty($class['material_file']) && file_exists(__DIR__ . '/../' . $class['material_file'])) {
            @unlink(__DIR__ . '/../' . $class['material_file']);
        }
        
        // Delete class
        $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$classId]);
        
        $db->commit();
        
        // Log activity
        logActivity('delete', __FILE__, [
            'page_name' => 'Delete Class',
            'section' => 'Training',
            'class_id' => $classId,
            'class_name' => $class['name']
        ]);
        
        header('Location: index.php?message=' . urlencode('Class deleted successfully.'));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to delete class: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Class - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Delete Class</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Permanently delete a training class and all associated data</p>
                        </div>
                        <div>
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
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

                <div class="max-w-2xl mx-auto">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-2xl mr-3"></i>
                                <div>
                                    <h2 class="text-lg font-medium text-red-900 dark:text-red-200">Warning: This action cannot be undone</h2>
                                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">Deleting this class will permanently remove all associated data</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-6 py-6">
                            <div class="mb-6">
                                <h3 class="text-base font-medium text-gray-900 dark:text-white mb-4">Class Information</h3>
                                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Class Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['name']); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['duration'] ?? 'N/A'); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Location</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($class['location'] ?? 'N/A'); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo ucfirst($class['status']); ?></dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="mb-6 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-md p-4">
                                <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    The following data will be permanently deleted:
                                </h4>
                                <ul class="list-disc list-inside text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                                    <li>Class record</li>
                                    <li>All class schedules</li>
                                    <li>All class assignments (users and roles)</li>
                                    <?php if (!empty($class['material_file'])): ?>
                                        <li>Material file (<?php echo htmlspecialchars(basename($class['material_file'])); ?>)</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete this class? This action cannot be undone.');">
                                <input type="hidden" name="confirm_delete" value="1">
                                <div class="flex justify-end space-x-3">
                                    <a href="index.php"
                                       class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        Cancel
                                    </a>
                                    <button type="submit"
                                            class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-trash-alt mr-2"></i>
                                        Delete Class
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

