<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
if (!isLoggedIn() || !checkPageAccessEnhanced('admin/odb/index.php')) {
    header('Location: /login.php');
    exit();
}

$current_user = getCurrentUser();
$notification_id = $_GET['id'] ?? '';

if (empty($notification_id)) {
    header('Location: index.php');
    exit();
}

$stats = getODBNotificationStats($notification_id);
if (!$stats) {
    header('Location: index.php');
    exit();
}

$notification = $stats['notification'];
$available_roles = getAllRoles();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODB Notification Details - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        /* Custom Scrollbar */
        .overflow-y-auto {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        
        .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 4px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
        
        /* Dark mode scrollbar */
        .dark .overflow-y-auto {
            scrollbar-color: #4a5568 #2d3748;
        }
        
        .dark .overflow-y-auto::-webkit-scrollbar-track {
            background: #2d3748;
        }
        
        .dark .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #4a5568;
        }
        
        .dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="flex h-full">
        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-64">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-bell mr-3"></i>ODB Notification Details
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                View notification details and acknowledgment status
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                                <i class="fas fa-users text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Target Users</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_target_users']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Acknowledged</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $stats['acknowledged_count']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                                <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Not Acknowledged</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $stats['not_acknowledged_count']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                                <i class="fas fa-percentage text-orange-600 dark:text-orange-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Completion Rate</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo $stats['total_target_users'] > 0 ? round(($stats['acknowledged_count'] / $stats['total_target_users']) * 100, 1) : 0; ?>%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Details -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-info-circle mr-2"></i>Notification Details
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h4>
                                <div class="flex items-center space-x-4 mb-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getODBPriorityColor($notification['priority']); ?>">
                                        <i class="<?php echo getODBPriorityIcon($notification['priority']); ?> mr-1"></i>
                                        <?php echo ucfirst($notification['priority']); ?>
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        Created <?php echo getDaysSinceCreated($notification['created_at']); ?> days ago
                                    </span>
                                </div>
                                <div class="prose dark:prose-invert max-w-none">
                                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($notification['message']); ?></p>
                                </div>
                                
                                <!-- File Attachment -->
                                <?php if (!empty($notification['file_path'])): ?>
                                <div class="mt-4">
                                    <?php 
                                    $fileUrl = getODBFileUrl($notification['file_path']);
                                    $fileExtension = strtolower(pathinfo($notification['file_path'], PATHINFO_EXTENSION));
                                    ?>
                                    <?php if ($fileUrl): ?>
                                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" 
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])): ?>
                                                <i class="fas fa-image mr-2"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file-pdf mr-2"></i>
                                            <?php endif; ?>
                                            View Attachment
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800">
                                            <i class="fas fa-file mr-2"></i>
                                            File not found
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Target Roles</label>
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                            $targetRoles = json_decode($notification['target_roles'], true);
                                            foreach ($targetRoles as $role): 
                                            ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    <?php echo ucfirst($role); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Created By</label>
                                        <p class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Created At</label>
                                        <p class="text-gray-900 dark:text-white"><?php echo $notification['created_at'] ? date('M j, Y g:i A', strtotime($notification['created_at'])) : 'N/A'; ?></p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Expires At</label>
                                        <p class="text-gray-900 dark:text-white"><?php echo $notification['expires_at'] ? date('M j, Y g:i A', strtotime($notification['expires_at'])) : 'Never'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acknowledgment Status -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Acknowledged Users -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>Acknowledged Users
                                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(<?php echo $stats['acknowledged_count']; ?>)</span>
                            </h3>
                        </div>
                        <div class="p-6 max-h-96 overflow-y-auto">
                            <?php if (!empty($stats['acknowledgments'])): ?>
                                <div class="space-y-3">
                                    <?php foreach ($stats['acknowledgments'] as $ack): ?>
                                    <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($ack['first_name'] . ' ' . $ack['last_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo ucfirst($ack['role']); ?> • <?php echo $ack['acknowledged_at'] ? date('M j, Y g:i A', strtotime($ack['acknowledged_at'])) : 'N/A'; ?>
                                            </p>
                                        </div>
                                        <i class="fas fa-check-circle text-green-600"></i>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No acknowledgments yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Not Acknowledged Users -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-times-circle text-red-600 mr-2"></i>Not Acknowledged Users
                                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(<?php echo $stats['not_acknowledged_count']; ?>)</span>
                            </h3>
                        </div>
                        <div class="p-6 max-h-96 overflow-y-auto">
                            <?php if (!empty($stats['not_acknowledged_users'])): ?>
                                <div class="space-y-3">
                                    <?php foreach ($stats['not_acknowledged_users'] as $user): ?>
                                    <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo ucfirst($user['role']); ?> • <?php echo getDaysSinceCreated($notification['created_at']); ?> days pending
                                            </p>
                                        </div>
                                        <i class="fas fa-times-circle text-red-600"></i>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 dark:text-gray-400 text-center py-4">All users have acknowledged</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

