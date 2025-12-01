/**
 * Notification System
 * Polls for new notifications and displays them as browser notifications
 */

(function() {
    'use strict';
    
    // Check if notifications are supported
    if (!('Notification' in window)) {
        console.log('This browser does not support notifications');
        return;
    }
    
    // Request permission for notifications
    let notificationPermission = Notification.permission;
    if (notificationPermission === 'default') {
        Notification.requestPermission().then(function(permission) {
            notificationPermission = permission;
        });
    }
    
    // Store last notification IDs to avoid duplicates
    let lastNotificationIds = new Set();
    let pollInterval = null;
    const POLL_INTERVAL = 30000; // Poll every 30 seconds
    
    /**
     * Show browser notification
     */
    function showBrowserNotification(notification) {
        if (notificationPermission !== 'granted') {
            return;
        }
        
        // Check if we've already shown this notification
        if (lastNotificationIds.has(notification.id)) {
            return;
        }
        
        // Use icon-512x512.png if available, otherwise fallback to favicon
        const iconPath = '/crewplan/icons/icon-512x512.png';
        
        // Build notification options
        const options = {
            body: notification.message,
            icon: iconPath,
            badge: iconPath,
            tag: 'notification-' + notification.id,
            requireInteraction: notification.priority === 'urgent' || notification.priority === 'high',
            timestamp: new Date(notification.created_at).getTime()
        };
        
        // Add image for mobile (if supported)
        if ('image' in Notification.prototype) {
            options.image = iconPath;
        }
        
        // Add vibrate pattern for mobile (if supported and allowed)
        if ('vibrate' in navigator) {
            if (notification.priority === 'urgent') {
                options.vibrate = [200, 100, 200, 100, 200];
            } else if (notification.priority === 'high') {
                options.vibrate = [200, 100, 200];
            } else if (notification.priority === 'normal') {
                options.vibrate = [200];
            }
        }
        
        // Set silent for low priority
        if (notification.priority === 'low') {
            options.silent = true;
        }
        
        // Add data for click handling
        options.data = {
            notificationId: notification.id,
            url: window.location.href
        };
        
        // Add different icons/colors based on type
        const typeIcons = {
            'info': 'ℹ️',
            'warning': '⚠️',
            'success': '✅',
            'error': '❌'
        };
        
        try {
            const browserNotification = new Notification(notification.title, options);
            
            // Mark as read when clicked
            browserNotification.onclick = function() {
                markNotificationAsRead(notification.id);
                window.focus();
                browserNotification.close();
            };
            
            // Auto close after 5 seconds (unless urgent)
            if (notification.priority !== 'urgent') {
                setTimeout(() => {
                    browserNotification.close();
                }, 5000);
            }
            
            // Add to seen notifications
            lastNotificationIds.add(notification.id);
            
            // Keep only last 100 IDs in memory
            if (lastNotificationIds.size > 100) {
                const firstId = lastNotificationIds.values().next().value;
                lastNotificationIds.delete(firstId);
            }
            
        } catch (error) {
            console.error('Error showing notification:', error);
        }
    }
    
    /**
     * Mark notification as read
     */
    function markNotificationAsRead(notificationId) {
        fetch('/admin/api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        }).catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    /**
     * Fetch new notifications
     */
    function fetchNotifications() {
        fetch('/admin/api/get_notifications.php?unread_only=1')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications) {
                    // Show notifications that haven't been seen
                    data.notifications.forEach(notification => {
                        if (!lastNotificationIds.has(notification.id)) {
                            showBrowserNotification(notification);
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
            });
    }
    
    /**
     * Start polling for notifications
     */
    function startPolling() {
        // Initial fetch
        fetchNotifications();
        
        // Set up interval
        pollInterval = setInterval(fetchNotifications, POLL_INTERVAL);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // Start polling when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }
    
    // Stop polling when page is hidden (to save resources)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });
    
    // Stop polling when page is unloaded
    window.addEventListener('beforeunload', stopPolling);
    
})();

