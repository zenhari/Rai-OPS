<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/odb/list.php');

$current_user = getCurrentUser();

// Handle acknowledgment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'acknowledge') {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    if ($notification_id > 0) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        if (acknowledgeODBNotification($notification_id, $current_user['id'], $ip_address, $user_agent)) {
            $message = 'Notification acknowledged successfully.';
        } else {
            $error = 'Failed to acknowledge notification.';
        }
    }
}

// Get active notifications for current user
$notifications = getActiveODBNotificationsForUser($current_user['id']);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ODB Notifications - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="flex h-full">
        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col lg:ml-64">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-bell mr-3"></i>ODB Notifications
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Operational Daily Briefings and important notifications
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Message Display -->
                <?php if (!empty($message)): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                                <i class="fas fa-bell text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Notifications</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo count($notifications); ?></p>
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
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($notifications, function($n) { return hasUserAcknowledgedNotification($n['id'], $GLOBALS['current_user']['id']); })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($notifications, function($n) { return !hasUserAcknowledgedNotification($n['id'], $GLOBALS['current_user']['id']); })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <?php if (!empty($notifications)): ?>
                <div class="space-y-6">
                    <?php foreach ($notifications as $notification): ?>
                    <?php $isAcknowledged = hasUserAcknowledgedNotification($notification['id'], $current_user['id']); ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 <?php echo $isAcknowledged ? 'opacity-75' : ''; ?>">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h3>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getODBPriorityColor($notification['priority']); ?>">
                                            <i class="<?php echo getODBPriorityIcon($notification['priority']); ?> mr-1"></i>
                                            <?php echo ucfirst($notification['priority']); ?>
                                        </span>
                                        <?php if ($isAcknowledged): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Acknowledged
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="prose dark:prose-invert max-w-none mb-4">
                                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    </div>
                                    
                                    <!-- File Attachment -->
                                    <?php if (!empty($notification['file_path'])): ?>
                                    <div class="mb-4">
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
                                    
                                    <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                        <span>
                                            <i class="fas fa-user mr-1"></i>
                                            Created by <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </span>
                                        <?php if ($notification['expires_at']): ?>
                                        <span>
                                            <i class="fas fa-calendar-times mr-1"></i>
                                            Expires <?php echo date('M j, Y g:i A', strtotime($notification['expires_at'])); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!$isAcknowledged): ?>
                                <div class="ml-6">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="acknowledge">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" 
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                                onclick="return confirm('Are you sure you have read and understood this notification?')">
                                            <i class="fas fa-check mr-2"></i>
                                            Acknowledge
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-bell-slash text-gray-400 dark:text-gray-500 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No ODB Notifications</h3>
                    <p class="text-gray-500 dark:text-gray-400">You don't have any active ODB notifications at the moment.</p>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>

