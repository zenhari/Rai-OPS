<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
if (!isLoggedIn() || !checkPageAccessEnhanced('admin/odb/index.php')) {
    header('Location: /login.php');
    exit();
}

$current_user = getCurrentUser();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_notification':
            $title = trim($_POST['title'] ?? '');
            $message_text = trim($_POST['message'] ?? '');
            $priority = $_POST['priority'] ?? 'normal';
            $targetRoles = $_POST['target_roles'] ?? [];
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $filePath = null;
            
            // Handle file upload if provided
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = handleODBFileUpload($_FILES['attachment']);
                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'];
                    break;
                }
                $filePath = $uploadResult['file_path'];
            }
            
            if (empty($title) || empty($message_text) || empty($targetRoles)) {
                $error = 'Title, message, and target roles are required.';
            } else {
                if (createODBNotification($title, $message_text, $priority, $targetRoles, $current_user['id'], $expiresAt, $filePath)) {
                    $message = 'ODB notification created successfully.';
                } else {
                    $error = 'Failed to create ODB notification.';
                }
            }
            break;
            
        case 'delete_notification':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                if (deleteODBNotification($id)) {
                    $message = 'ODB notification deleted successfully.';
                } else {
                    $error = 'Failed to delete ODB notification.';
                }
            }
            break;
    }
}

$notifications = getAllODBNotifications();
$notifications_count = getODBNotificationsCount();
$available_roles = getAllRolesFromTable();
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
                                Manage operational daily briefings and notifications
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Create Notification Button -->
                            <button onclick="openCreateModal()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Create Notification
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>
                
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
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $notifications_count; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                                <i class="fas fa-exclamation-circle text-orange-600 dark:text-orange-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Urgent Notifications</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($notifications, function($n) { return $n['priority'] == 'urgent'; })); ?>
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
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical Notifications</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($notifications, function($n) { return $n['priority'] == 'critical'; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-list mr-2"></i>All Notifications
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Target Roles</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Attachment</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created At</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expires At</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($notifications as $notification): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white" title="<?php echo htmlspecialchars($notification['title']); ?>">
                                            <?php 
                                            $title = $notification['title'];
                                            echo htmlspecialchars(strlen($title) > 70 ? substr($title, 0, 70) . '...' : $title);
                                            ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                            <?php echo htmlspecialchars(substr($notification['message'], 0, 100)) . (strlen($notification['message']) > 100 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getODBPriorityColor($notification['priority']); ?>">
                                            <i class="<?php echo getODBPriorityIcon($notification['priority']); ?> mr-1"></i>
                                            <?php echo ucfirst($notification['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1">
                                            <?php 
                                            $targetRoles = json_decode($notification['target_roles'], true);
                                            foreach ($targetRoles as $role): 
                                            ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    <?php echo ucfirst($role); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php if (!empty($notification['file_path'])): ?>
                                            <?php 
                                            $fileUrl = getODBFileUrl($notification['file_path']);
                                            $fileExtension = strtolower(pathinfo($notification['file_path'], PATHINFO_EXTENSION));
                                            ?>
                                            <?php if ($fileUrl): ?>
                                                <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" 
                                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])): ?>
                                                        <i class="fas fa-image mr-1"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file-pdf mr-1"></i>
                                                    <?php endif; ?>
                                                    View File
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 dark:text-gray-500">
                                                    <i class="fas fa-file mr-1"></i>File not found
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500">No attachment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $notification['expires_at'] ? date('M j, Y g:i A', strtotime($notification['expires_at'])) : 'Never'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="view.php?id=<?php echo $notification['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Notification Modal -->
    <div id="createModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCreateModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_notification">
                    
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                    <i class="fas fa-plus mr-2"></i>Create ODB Notification
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- Title -->
                                    <div>
                                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Title *
                                        </label>
                                        <input type="text" id="title" name="title" required
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="Enter notification title">
                                    </div>
                                    
                                    <!-- Message -->
                                    <div>
                                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Message *
                                        </label>
                                        <textarea id="message" name="message" rows="4" required
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                  placeholder="Enter notification message"></textarea>
                                    </div>
                                    
                                    <!-- Priority -->
                                    <div>
                                        <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Priority *
                                        </label>
                                        <select id="priority" name="priority" required
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                            <option value="normal">Normal</option>
                                            <option value="urgent">Urgent</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Target Roles -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Target Roles *
                                        </label>
                                        <div class="space-y-2">
                                            <?php foreach ($available_roles as $role): ?>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="target_roles[]" value="<?php echo htmlspecialchars($role['name']); ?>"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <?php echo htmlspecialchars($role['display_name']); ?>
                                                    <?php if (!empty($role['description'])): ?>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">- <?php echo htmlspecialchars($role['description']); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Expires At -->
                                    <div>
                                        <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Expires At (Optional)
                                        </label>
                                        <input type="datetime-local" id="expires_at" name="expires_at"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <!-- File Attachment -->
                                    <div>
                                        <label for="attachment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Attachment (Optional)
                                        </label>
                                        <input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Allowed formats: PDF, JPG, PNG (Max size: 5MB)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Create Notification
                        </button>
                        <button type="button" onclick="closeCreateModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function deleteNotification(id) {
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createModal');
            if (event.target === modal) {
                closeCreateModal();
            }
        }
    </script>
</body>
</html>

