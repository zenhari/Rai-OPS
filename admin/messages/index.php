<?php
require_once '../../config.php';

// Check if user is logged in
checkPageAccessWithRedirect('admin/messages/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Ensure tables exist
ensureMessagesTablesExist();

// Get messages
$inboxMessages = getInboxMessages($current_user['id'], 100);
$sentMessages = getSentMessages($current_user['id'], 100);
$unreadCount = getUnreadMessageCount($current_user['id']);

// Get all users for compose
$pdo = getDBConnection();
$sql = "SELECT id, first_name, last_name, position, email, picture FROM users WHERE status = 'active' AND id != ? ORDER BY first_name, last_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$current_user['id']]);
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current message if viewing
$viewMessage = null;
$viewMessageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($viewMessageId > 0) {
    $viewMessage = getMessageById($viewMessageId, $current_user['id']);
    if ($viewMessage && $viewMessage['receiver_id'] == $current_user['id']) {
        markMessageAsRead($viewMessageId, $current_user['id']);
    }
}

$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo PROJECT_NAME; ?></title>
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <script src="/assets/js/tailwind.js"></script>
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-comments mr-2"></i>Messages
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Send and receive messages with your team
                            </p>
                        </div>
                        <button onclick="openComposeModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                            <i class="fas fa-paper-plane mr-2"></i>Send Message
                        </button>
                    </div>
                </div>
            </header>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mx-4 mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mx-4 mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <!-- Tabs -->
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px">
                            <a href="?tab=inbox" class="<?php echo $currentTab == 'inbox' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                                <i class="fas fa-inbox mr-2"></i>Inbox
                                <?php if ($unreadCount > 0): ?>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <?php echo $unreadCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a href="?tab=sent" class="<?php echo $currentTab == 'sent' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                                <i class="fas fa-paper-plane mr-2"></i>Sent
                            </a>
                            <a href="?tab=users" class="<?php echo $currentTab == 'users' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                                <i class="fas fa-users mr-2"></i>Users
                            </a>
                        </nav>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <?php if ($currentTab == 'inbox'): ?>
                            <!-- Inbox Messages -->
                            <?php if (empty($inboxMessages)): ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-inbox text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                    <p class="text-gray-500 dark:text-gray-400">No messages in inbox</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($inboxMessages as $msg): ?>
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 cursor-pointer <?php echo !$msg['is_read'] ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700' : ''; ?>" onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <div class="h-10 w-10 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                                <?php if (!empty($msg['sender_picture']) && file_exists('../../' . $msg['sender_picture'])): ?>
                                                                    <img src="../../<?php echo htmlspecialchars($msg['sender_picture']); ?>" alt="" class="h-10 w-10 rounded-full object-cover">
                                                                <?php else: ?>
                                                                    <i class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                                <?php echo htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']); ?>
                                                                <?php if ($msg['sender_position']): ?>
                                                                    <span class="text-gray-500 dark:text-gray-400"> - <?php echo htmlspecialchars($msg['sender_position']); ?></span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                                <?php echo htmlspecialchars($msg['subject'] ?: '(No Subject)'); ?>
                                                            </p>
                                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                                <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <?php if ($msg['attachment_count'] > 0): ?>
                                                        <i class="fas fa-paperclip text-gray-400"></i>
                                                    <?php endif; ?>
                                                    <?php if (!$msg['is_read']): ?>
                                                        <span class="h-2 w-2 bg-blue-600 rounded-full"></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($currentTab == 'sent'): ?>
                            <!-- Sent Messages -->
                            <?php if (empty($sentMessages)): ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-paper-plane text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                    <p class="text-gray-500 dark:text-gray-400">No sent messages</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($sentMessages as $msg): ?>
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 cursor-pointer" onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <div class="h-10 w-10 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                                <?php if (!empty($msg['receiver_picture']) && file_exists('../../' . $msg['receiver_picture'])): ?>
                                                                    <img src="../../<?php echo htmlspecialchars($msg['receiver_picture']); ?>" alt="" class="h-10 w-10 rounded-full object-cover">
                                                                <?php else: ?>
                                                                    <i class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                                To: <?php echo htmlspecialchars($msg['receiver_first_name'] . ' ' . $msg['receiver_last_name']); ?>
                                                                <?php if ($msg['receiver_position']): ?>
                                                                    <span class="text-gray-500 dark:text-gray-400"> - <?php echo htmlspecialchars($msg['receiver_position']); ?></span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                                <?php echo htmlspecialchars($msg['subject'] ?: '(No Subject)'); ?>
                                                            </p>
                                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                                <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <?php if ($msg['attachment_count'] > 0): ?>
                                                        <i class="fas fa-paperclip text-gray-400"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($currentTab == 'users'): ?>
                            <!-- Users List -->
                            <div class="mb-4">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                    <input type="text" id="userSearchBox" placeholder="Search users by name, email, or position..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div id="usersGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($allUsers as $user): ?>
                                    <div class="user-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 cursor-pointer" 
                                         data-name="<?php echo htmlspecialchars(strtolower($user['first_name'] . ' ' . $user['last_name'])); ?>"
                                         data-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>"
                                         data-position="<?php echo htmlspecialchars(strtolower($user['position'] ?: '')); ?>"
                                         onclick="composeToUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="h-12 w-12 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                    <?php if (!empty($user['picture']) && file_exists('../../' . $user['picture'])): ?>
                                                        <img src="../../<?php echo htmlspecialchars($user['picture']); ?>" alt="" class="h-12 w-12 rounded-full object-cover">
                                                    <?php else: ?>
                                                        <i class="fas fa-user text-gray-600 dark:text-gray-300 text-xl"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    <?php echo htmlspecialchars($user['position'] ?: 'No position'); ?>
                                                </p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <i class="fas fa-chevron-right text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="noUsersFound" class="hidden text-center py-12">
                                <i class="fas fa-user-slash text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                <p class="text-gray-500 dark:text-gray-400">No users found matching your search</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Compose Modal -->
    <div id="composeModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeComposeModal()"></div>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form id="composeForm" method="POST" action="api/send_message.php" enctype="multipart/form-data">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Compose Message</h3>
                            <button type="button" onclick="closeComposeModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                                <select name="receiver_id" id="receiver_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select user...</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' - ' . $user['position']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                                <input type="text" name="subject" id="subject" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                                <textarea name="message" id="message" rows="6" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Attachments (Images, DOCX, XLSX)</label>
                                <input type="file" name="attachments[]" id="attachments" multiple accept="image/*,.docx,.xlsx" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">You can select multiple files (images, .docx, .xlsx)</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <button type="button" onclick="closeComposeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Message Modal -->
    <div id="viewMessageModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeViewMessageModal()"></div>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div id="viewMessageContent" class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all max-w-md w-full mx-4">
            <div class="px-6 py-8 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 mb-4">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Success!</h3>
                <p id="successMessage" class="text-sm text-gray-500 dark:text-gray-400">Message sent successfully!</p>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all max-w-md w-full mx-4">
            <div class="px-6 py-8 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 dark:bg-red-900 mb-4">
                    <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Error!</h3>
                <p id="errorMessage" class="text-sm text-gray-500 dark:text-gray-400 mb-4">An error occurred while sending the message.</p>
                <button onclick="closeErrorModal()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors duration-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openComposeModal() {
            document.getElementById('composeModal').classList.remove('hidden');
        }

        function closeComposeModal() {
            document.getElementById('composeModal').classList.add('hidden');
            document.getElementById('composeForm').reset();
        }

        function composeToUser(userId, userName) {
            document.getElementById('receiver_id').value = userId;
            openComposeModal();
        }

        function viewMessage(messageId) {
            fetch('api/get_message.php?id=' + messageId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessage(data.message);
                        document.getElementById('viewMessageModal').classList.remove('hidden');
                    } else {
                        alert('Error loading message');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading message');
                });
        }

        function displayMessage(msg) {
            let html = `
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">${msg.subject || '(No Subject)'}</h3>
                    <button type="button" onclick="closeViewMessageModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="h-12 w-12 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">From: ${msg.sender_first_name} ${msg.sender_last_name}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">To: ${msg.receiver_first_name} ${msg.receiver_last_name}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">${new Date(msg.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    <div class="prose dark:prose-invert max-w-none">
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${msg.message}</p>
                    </div>
            `;
            
            if (msg.attachments && msg.attachments.length > 0) {
                html += '<div class="mt-4"><p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Attachments:</p><div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                msg.attachments.forEach(att => {
                    if (att.file_category === 'image') {
                        html += `<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-2"><img src="../../${att.file_path}" alt="${att.file_name}" class="w-full h-auto rounded cursor-pointer" onclick="window.open('../../${att.file_path}', '_blank')"></div>`;
                    } else {
                        const fileSize = att.file_size ? ` (${formatFileSize(att.file_size)})` : '';
                        html += `<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 flex items-center"><a href="../../${att.file_path}" download="${att.file_name}" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center"><i class="fas fa-file mr-2"></i>${att.file_name}${fileSize}</a></div>`;
                    }
                });
                html += '</div></div>';
            }
            
            html += `
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button onclick="replyToMessage(${msg.id})" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-reply mr-2"></i>Reply
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('viewMessageContent').innerHTML = html;
        }

        function closeViewMessageModal() {
            document.getElementById('viewMessageModal').classList.add('hidden');
        }

        function replyToMessage(messageId) {
            fetch('api/get_message.php?id=' + messageId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const msg = data.message;
                        document.getElementById('receiver_id').value = msg.sender_id;
                        document.getElementById('subject').value = 'Re: ' + (msg.subject || '');
                        document.getElementById('message').value = '\n\n--- Original Message ---\n' + msg.message;
                        closeViewMessageModal();
                        openComposeModal();
                    }
                });
        }

        // Show success modal
        function showSuccessModal(message) {
            const modal = document.getElementById('successModal');
            const messageElement = document.getElementById('successMessage');
            if (messageElement) {
                messageElement.textContent = message;
            }
            modal.classList.remove('hidden');
            
            // Auto-hide after 2 seconds
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 2000);
        }

        // Show error modal
        function showErrorModal(message) {
            const modal = document.getElementById('errorModal');
            const messageElement = document.getElementById('errorMessage');
            if (messageElement) {
                messageElement.textContent = message;
            }
            modal.classList.remove('hidden');
        }

        // Close error modal
        function closeErrorModal() {
            document.getElementById('errorModal').classList.add('hidden');
        }

        // Handle form submission
        document.getElementById('composeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Disable submit button
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            
            fetch('api/send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is actually JSON
                const contentType = response.headers.get("content-type");
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('HTTP Error Response:', text);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                if (!contentType || !contentType.includes("application/json")) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response. Check console for details.');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeComposeModal();
                    showSuccessModal('Message sent successfully!');
                    // Reload page after modal is shown
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    showErrorModal('Error: ' + (data.message || 'Failed to send message'));
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                showErrorModal('Error sending message: ' + error.message + '. Please check the console for more details.');
            });
        });

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // User search functionality
        const userSearchBox = document.getElementById('userSearchBox');
        if (userSearchBox) {
            userSearchBox.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                const userCards = document.querySelectorAll('.user-card');
                const noUsersFound = document.getElementById('noUsersFound');
                const usersGrid = document.getElementById('usersGrid');
                let visibleCount = 0;
                
                userCards.forEach(card => {
                    const name = card.getAttribute('data-name') || '';
                    const email = card.getAttribute('data-email') || '';
                    const position = card.getAttribute('data-position') || '';
                    
                    const matches = name.includes(searchTerm) || 
                                  email.includes(searchTerm) || 
                                  position.includes(searchTerm);
                    
                    if (matches || searchTerm === '') {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Show/hide no results message
                if (visibleCount === 0 && searchTerm !== '') {
                    noUsersFound.classList.remove('hidden');
                    usersGrid.classList.add('hidden');
                } else {
                    noUsersFound.classList.add('hidden');
                    usersGrid.classList.remove('hidden');
                }
            });
        }

        // Initialize dark mode
        function initDarkMode() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const shouldBeDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
            
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        
        initDarkMode();
    </script>
</body>
</html>

