<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/settings/notification.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Ensure tables exist
$tablesCreated = ensureNotificationsTablesExist();
if (!$tablesCreated) {
    $error = 'Failed to create notification tables. Please check database connection.';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_notification':
            $title = trim($_POST['title'] ?? '');
            $messageText = trim($_POST['message'] ?? '');
            $targetRole = !empty($_POST['target_role']) ? $_POST['target_role'] : null;
            $targetUserId = !empty($_POST['target_user_id']) ? intval($_POST['target_user_id']) : null;
            $priority = $_POST['priority'] ?? 'normal';
            $type = $_POST['type'] ?? 'info';
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            
            if (empty($title) || empty($messageText)) {
                $error = 'Title and message are required.';
            } else {
                $result = createNotification($title, $messageText, $targetRole, $targetUserId, $priority, $type, $expiresAt, $current_user['id']);
                if ($result['success']) {
                    $message = 'Notification created successfully.';
                } else {
                    $error = $result['message'] ?? 'Failed to create notification.';
                }
            }
            break;
            
        case 'update_notification':
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $messageText = trim($_POST['message'] ?? '');
            $targetRole = !empty($_POST['target_role']) ? $_POST['target_role'] : null;
            $targetUserId = !empty($_POST['target_user_id']) ? intval($_POST['target_user_id']) : null;
            $priority = $_POST['priority'] ?? 'normal';
            $type = $_POST['type'] ?? 'info';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            
            if (empty($title) || empty($messageText)) {
                $error = 'Title and message are required.';
            } else {
                if (updateNotification($id, $title, $messageText, $targetRole, $targetUserId, $priority, $type, $isActive, $expiresAt)) {
                    $message = 'Notification updated successfully.';
                } else {
                    $error = 'Failed to update notification.';
                }
            }
            break;
            
        case 'delete_notification':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                if (deleteNotification($id)) {
                    $message = 'Notification deleted successfully.';
                } else {
                    $error = 'Failed to delete notification.';
                }
            }
            break;
    }
}

// Get all notifications (including expired ones for admin view)
try {
    $notifications = getAllNotifications(1000, true);
    
    // Debug: Check count directly from database
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
    $dbCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // If database has notifications but getAllNotifications returns empty, log error
    if ($dbCount > 0 && empty($notifications)) {
        error_log("Warning: Database has {$dbCount} notifications but getAllNotifications returned empty array");
        // Try direct query
        $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 1000");
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error getting notifications: " . $e->getMessage());
    $notifications = [];
    $error = 'Error loading notifications: ' . $e->getMessage();
}

$available_roles = getAllRolesFromTable();
$all_users = getAllUsers(1000); // Get all users for dropdown

// Get stats for each notification
$notificationStats = [];
foreach ($notifications as $notification) {
    $notificationStats[$notification['id']] = getNotificationStats($notification['id']);
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'roboto': ['Roboto', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage system notifications for users</p>
                        </div>
                        <button onclick="openCreateNotificationModal()" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-plus mr-2"></i>
                            Create Notification
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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

                <!-- Notifications Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">All Notifications</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total: <?php echo count($notifications); ?> notification(s)</p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Target</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Read Count</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($notifications)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-8 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-bell-slash text-gray-400 dark:text-gray-500 text-4xl mb-4"></i>
                                                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium mb-2">No notifications found</p>
                                                <p class="text-gray-400 dark:text-gray-500 text-sm mb-4">Create your first notification to get started</p>
                                                <button onclick="openCreateNotificationModal()" 
                                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                                    <i class="fas fa-plus mr-2"></i>
                                                    Create First Notification
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    <?php echo htmlspecialchars(substr($notification['message'], 0, 50)) . (strlen($notification['message']) > 50 ? '...' : ''); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($notification['target_user_id']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                        User ID: <?php echo $notification['target_user_id']; ?>
                                                    </span>
                                                <?php elseif ($notification['target_role']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        Role: <?php echo htmlspecialchars($notification['target_role']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                        All Users
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $priorityColors = [
                                                    'low' => 'gray',
                                                    'normal' => 'blue',
                                                    'high' => 'orange',
                                                    'urgent' => 'red'
                                                ];
                                                $color = $priorityColors[$notification['priority']] ?? 'gray';
                                                ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 dark:bg-<?php echo $color; ?>-900 dark:text-<?php echo $color; ?>-200">
                                                    <?php echo ucfirst($notification['priority']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $typeColors = [
                                                    'info' => 'blue',
                                                    'warning' => 'yellow',
                                                    'success' => 'green',
                                                    'error' => 'red'
                                                ];
                                                $color = $typeColors[$notification['type']] ?? 'blue';
                                                ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 dark:bg-<?php echo $color; ?>-900 dark:text-<?php echo $color; ?>-200">
                                                    <?php echo ucfirst($notification['type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($notification['is_active']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                        Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $stats = $notificationStats[$notification['id']] ?? null;
                                                if ($stats):
                                                    $readCount = $stats['read_count'] ?? 0;
                                                    $targetCount = $stats['target_count'] ?? 0;
                                                    $unreadCount = $stats['unread_count'] ?? 0;
                                                ?>
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <span class="font-medium text-green-600 dark:text-green-400"><?php echo $readCount; ?></span>
                                                        <span class="text-gray-500 dark:text-gray-400">/</span>
                                                        <span class="text-gray-600 dark:text-gray-300"><?php echo $targetCount; ?></span>
                                                    </div>
                                                    <?php if ($unreadCount > 0): ?>
                                                        <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                                                            <?php echo $unreadCount; ?> unread
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="viewNotificationReaders(<?php echo $notification['id']; ?>)" 
                                                        class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300 mr-3" 
                                                        title="View readers">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="openEditNotificationModal(<?php echo htmlspecialchars(json_encode($notification)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteNotificationConfirm(<?php echo $notification['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Notification Modal -->
    <div id="createNotificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Create Notification</h3>
                    <button onclick="closeCreateNotificationModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_notification">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Message <span class="text-red-500">*</span>
                        </label>
                        <textarea name="message" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Enter notification message..."></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Target Role
                            </label>
                            <select name="target_role"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Users</option>
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['name']); ?>">
                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Target User
                            </label>
                            <select name="target_user_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Users</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Priority
                            </label>
                            <select name="priority"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Type
                            </label>
                            <select name="type"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="info" selected>Info</option>
                                <option value="warning">Warning</option>
                                <option value="success">Success</option>
                                <option value="error">Error</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Expires At (Optional)
                        </label>
                        <input type="datetime-local" name="expires_at"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for no expiration</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCreateNotificationModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Create Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Notification Modal -->
    <div id="editNotificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Notification</h3>
                    <button onclick="closeEditNotificationModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_notification">
                    <input type="hidden" id="edit_notification_id" name="id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Message <span class="text-red-500">*</span>
                        </label>
                        <textarea id="edit_message" name="message" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Target Role
                            </label>
                            <select id="edit_target_role" name="target_role"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Users</option>
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['name']); ?>">
                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Target User
                            </label>
                            <select id="edit_target_user_id" name="target_user_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Users</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Priority
                            </label>
                            <select id="edit_priority" name="priority"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Type
                            </label>
                            <select id="edit_type" name="type"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="success">Success</option>
                                <option value="error">Error</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Expires At (Optional)
                        </label>
                        <input type="datetime-local" id="edit_expires_at" name="expires_at"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="edit_is_active" name="is_active" checked
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                        </label>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditNotificationModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateNotificationModal() {
            document.getElementById('createNotificationModal').classList.remove('hidden');
        }

        function closeCreateNotificationModal() {
            document.getElementById('createNotificationModal').classList.add('hidden');
        }

        function openEditNotificationModal(notification) {
            document.getElementById('edit_notification_id').value = notification.id;
            document.getElementById('edit_title').value = notification.title;
            document.getElementById('edit_message').value = notification.message;
            document.getElementById('edit_target_role').value = notification.target_role || '';
            document.getElementById('edit_target_user_id').value = notification.target_user_id || '';
            document.getElementById('edit_priority').value = notification.priority;
            document.getElementById('edit_type').value = notification.type;
            document.getElementById('edit_is_active').checked = notification.is_active == 1;
            
            if (notification.expires_at) {
                const expiresDate = new Date(notification.expires_at);
                const localDateTime = new Date(expiresDate.getTime() - expiresDate.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                document.getElementById('edit_expires_at').value = localDateTime;
            } else {
                document.getElementById('edit_expires_at').value = '';
            }
            
            document.getElementById('editNotificationModal').classList.remove('hidden');
        }

        function closeEditNotificationModal() {
            document.getElementById('editNotificationModal').classList.add('hidden');
        }

        function deleteNotificationConfirm(id) {
            if (confirm('Are you sure you want to delete this notification?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_notification">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // View notification readers
        function viewNotificationReaders(notificationId) {
            fetch(`<?php echo getAbsolutePath('admin/api/get_notification_readers.php'); ?>?notification_id=${notificationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showReadersModal(data.readers, data.notification);
                    } else {
                        alert('Error loading readers: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading readers');
                });
        }

        function showReadersModal(readers, notification) {
            const modal = document.getElementById('readersModal');
            const title = document.getElementById('readersModalTitle');
            const body = document.getElementById('readersModalBody');
            
            title.textContent = `Readers for: ${notification.title}`;
            
            if (readers.length === 0) {
                body.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">No readers yet.</p>';
            } else {
                body.innerHTML = `
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        ${readers.map(reader => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            ${reader.first_name} ${reader.last_name}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            ${reader.position || 'N/A'} • ${reader.role || 'N/A'}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    ${new Date(reader.read_at).toLocaleString()}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            modal.classList.remove('hidden');
        }

        function closeReadersModal() {
            document.getElementById('readersModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createNotificationModal');
            const editModal = document.getElementById('editNotificationModal');
            const readersModal = document.getElementById('readersModal');
            
            if (event.target === createModal) {
                closeCreateNotificationModal();
            } else if (event.target === editModal) {
                closeEditNotificationModal();
            } else if (event.target === readersModal) {
                closeReadersModal();
            }
        }

        // Dark mode functionality - detect browser preference
        function initDarkMode() {
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const savedDarkMode = localStorage.getItem('darkMode');
            
            // Use saved preference if exists, otherwise use system preference
            if (savedDarkMode !== null) {
                if (savedDarkMode === 'true') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } else {
                // Use system preference
                if (systemPrefersDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        }

        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            const savedDarkMode = localStorage.getItem('darkMode');
            // Only update if user hasn't manually set a preference
            if (savedDarkMode === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    </script>

    <!-- Readers Modal -->
    <div id="readersModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="readersModalTitle" class="text-lg font-medium text-gray-900 dark:text-white">Notification Readers</h3>
                    <button onclick="closeReadersModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="readersModalBody" class="mb-4">
                    <!-- Readers will be loaded here -->
                </div>
                <div class="flex justify-end">
                    <button onclick="closeReadersModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

