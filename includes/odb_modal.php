<?php
// Check if user is logged in
if (!isLoggedIn()) {
    return;
}

$current_user = getCurrentUser();
$unread_notifications = [];

// Get active notifications for current user that haven't been acknowledged
$all_notifications = getActiveODBNotificationsForUser($current_user['id']);
foreach ($all_notifications as $notification) {
    if (!hasUserAcknowledgedNotification($notification['id'], $current_user['id'])) {
        $unread_notifications[] = $notification;
    }
}

// Debug: Log the notifications
error_log('ODB Modal - Total notifications: ' . count($all_notifications));
error_log('ODB Modal - Unread notifications: ' . count($unread_notifications));

// Only show modal if there are unread notifications
if (!empty($unread_notifications)):
?>

<!-- ODB Notifications Modal -->
<div id="odbModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeODBModal()"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="w-full">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                <i class="fas fa-bell mr-2 text-blue-600"></i>
                                ODB Notifications (<?php echo count($unread_notifications); ?>)
                            </h3>
                            <button onclick="closeODBModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                <i class="fas fa-info-circle mr-2"></i>
                                You have <?php echo count($unread_notifications); ?> unread ODB notification(s). Please read and acknowledge them.
                            </p>
                        </div>
                        
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($unread_notifications as $notification): ?>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h4>
                                        <div class="flex items-center space-x-3 mb-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getODBPriorityColor($notification['priority']); ?>">
                                                <i class="<?php echo getODBPriorityIcon($notification['priority']); ?> mr-1"></i>
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-user mr-1"></i>
                                                Created by <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                            </span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
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
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
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
                                
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php if ($notification['expires_at']): ?>
                                            <i class="fas fa-calendar-times mr-1"></i>
                                            Expires <?php echo date('M j, Y g:i A', strtotime($notification['expires_at'])); ?>
                                        <?php else: ?>
                                            <i class="fas fa-infinity mr-1"></i>
                                            No expiration
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button onclick="acknowledgeNotification(<?php echo $notification['id']; ?>)" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                        <i class="fas fa-check mr-2"></i>
                                        Acknowledge
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                You can view all ODB notifications anytime from the sidebar menu.
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="acknowledgeAllNotifications()" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-check-double mr-2"></i>
                                    Acknowledge All
                                </button>
                                <button onclick="closeODBModal()" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-times mr-2"></i>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ODB Modal Management
let unreadNotificationIds = [<?php echo implode(',', array_column($unread_notifications, 'id')); ?>];

function showODBModal() {
    document.getElementById('odbModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeODBModal() {
    document.getElementById('odbModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function acknowledgeNotification(notificationId) {
    console.log('Acknowledging notification:', notificationId);
    
    // Disable button to prevent double-click
    const button = document.querySelector(`[onclick="acknowledgeNotification(${notificationId})"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Acknowledging...';
    }
    
    fetch('/admin/api/acknowledge_odb.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'acknowledge',
            notification_id: notificationId
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Check if it was already acknowledged
            if (data.message.includes('already acknowledged')) {
                console.log('Notification was already acknowledged, removing from modal');
                // Remove the entire notification element from modal
                const notificationElement = document.querySelector(`[onclick="acknowledgeNotification(${notificationId})"]`)?.closest('.border');
                if (notificationElement) {
                    notificationElement.remove();
                }
            } else {
                // Find the notification element and update it
                const notificationElement = document.querySelector(`[onclick="acknowledgeNotification(${notificationId})"]`)?.closest('.border');
                
                if (notificationElement) {
                    // Update the notification element
                    notificationElement.style.opacity = '0.5';
                    const buttonElement = notificationElement.querySelector('button');
                    if (buttonElement) {
                        buttonElement.innerHTML = '<i class="fas fa-check mr-2"></i>Acknowledged';
                        buttonElement.disabled = true;
                        buttonElement.classList.remove('bg-green-600', 'hover:bg-green-700');
                        buttonElement.classList.add('bg-gray-400', 'cursor-not-allowed');
                    }
                }
            }
            
            // Remove from unread list
            unreadNotificationIds = unreadNotificationIds.filter(id => id !== notificationId);
            
            // Update modal title
            const title = document.querySelector('#odbModal h3');
            if (title) {
                const count = unreadNotificationIds.length;
                title.innerHTML = `<i class="fas fa-bell mr-2 text-blue-600"></i>ODB Notifications (${count})`;
                
                // If no more unread notifications, close modal
                if (count === 0) {
                    setTimeout(() => {
                        closeODBModal();
                    }, 1000);
                }
            }
        } else {
            // Re-enable button on error
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check mr-2"></i>Acknowledge';
            }
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error acknowledging notification:', error);
        // Re-enable button on error
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-check mr-2"></i>Acknowledge';
        }
        alert('Error acknowledging notification. Please check console for details and try again.');
    });
}

function acknowledgeAllNotifications() {
    if (confirm('Are you sure you want to acknowledge all notifications?')) {
        // Disable button to prevent double-click
        const button = document.querySelector('[onclick="acknowledgeAllNotifications()"]');
        let originalText = '';
        if (button) {
            button.disabled = true;
            originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Acknowledging...';
        }
        
        const promises = unreadNotificationIds.map(id => {
            return fetch('/admin/api/acknowledge_odb.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'acknowledge',
                    notification_id: id
                })
            })
            .then(response => {
                // Try to parse JSON response
                return response.json().then(data => {
                    // Consider both success:true and "already acknowledged" as success
                    const isSuccess = data.success === true || 
                                     (data.message && data.message.includes('already acknowledged'));
                    return {
                        success: isSuccess,
                        message: data.message || 'Unknown response',
                        notification_id: id,
                        httpStatus: response.status
                    };
                }).catch(() => {
                    // If JSON parsing fails, check HTTP status
                    return {
                        success: response.ok,
                        message: `HTTP ${response.status}: ${response.statusText}`,
                        notification_id: id,
                        httpStatus: response.status
                    };
                });
            })
            .catch(error => {
                console.error(`Error acknowledging notification ${id}:`, error);
                return {
                    success: false,
                    message: error.message || 'Network error',
                    notification_id: id,
                    httpStatus: 0
                };
            });
        });
        
        Promise.all(promises)
        .then(results => {
            console.log('Acknowledge all results:', results);
            
            // Count successful acknowledgments (including already acknowledged)
            const successful = results.filter(r => r.success === true);
            const failed = results.filter(r => r.success === false);
            
            // If all were successful (including already acknowledged), close modal
            if (failed.length === 0) {
                closeODBModal();
                // Reload the page to update the sidebar
                window.location.reload();
            } else {
                // Re-enable button on error
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
                
                // Show detailed error message
                const errorMessages = failed.map(r => `Notification ${r.notification_id}: ${r.message || 'Unknown error'}`).join('\n');
                console.error('Failed to acknowledge notifications:', errorMessages);
                alert(`Some notifications could not be acknowledged:\n\n${errorMessages}\n\n${successful.length} notification(s) were successfully acknowledged.`);
            }
        })
        .catch(error => {
            console.error('Error acknowledging all notifications:', error);
            // Re-enable button on error
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
            alert('Error acknowledging notifications. Please check console for details and try again.');
        });
    }
}

// Show modal on page load if there are unread notifications
document.addEventListener('DOMContentLoaded', function() {
    if (unreadNotificationIds.length > 0) {
        // Small delay to ensure page is fully loaded
        setTimeout(() => {
            showODBModal();
        }, 500);
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('odbModal');
    if (event.target === modal) {
        closeODBModal();
    }
}
</script>

<?php endif; ?>
