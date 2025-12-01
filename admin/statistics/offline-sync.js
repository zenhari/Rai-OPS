/**
 * Offline Sync Manager
 * Handles synchronization of offline data with server
 */

class OfflineSyncManager {
    constructor() {
        this.db = new StatisticsDB();
        this.syncInProgress = false;
        this.syncInterval = null;
        this.apiEndpoint = 'flight_statistics.php';
        this.init();
    }

    /**
     * Initialize the sync manager
     */
    async init() {
        // Initialize IndexedDB
        try {
            await this.db.init();
            console.log('OfflineSyncManager initialized');
        } catch (error) {
            console.error('Failed to initialize IndexedDB:', error);
        }

        // Listen for online/offline events
        window.addEventListener('online', () => {
            console.log('Connection restored - attempting sync');
            this.syncPendingUpdates();
        });

        window.addEventListener('offline', () => {
            console.log('Connection lost - working offline');
            this.showOfflineNotification();
        });

        // Check connection status on load
        if (navigator.onLine) {
            // Try to sync immediately if online
            setTimeout(() => this.syncPendingUpdates(), 1000);
        } else {
            this.showOfflineNotification();
        }

        // Periodic sync check (every 30 seconds when online)
        this.syncInterval = setInterval(() => {
            if (navigator.onLine && !this.syncInProgress) {
                this.syncPendingUpdates();
            }
        }, 30000);
    }

    /**
     * Check if device is online
     */
    isOnline() {
        return navigator.onLine;
    }

    /**
     * Show offline notification
     */
    showOfflineNotification() {
        const existingNotification = document.getElementById('offline-notification');
        if (existingNotification) {
            return;
        }

        const notification = document.createElement('div');
        notification.id = 'offline-notification';
        notification.className = 'fixed top-4 right-4 bg-yellow-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
        notification.innerHTML = `
            <i class="fas fa-wifi-slash"></i>
            <span>Working offline - Data will be saved in browser</span>
        `;
        document.body.appendChild(notification);

        // Auto-hide after 5 seconds if online
        if (navigator.onLine) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    }

    /**
     * Hide offline notification
     */
    hideOfflineNotification() {
        const notification = document.getElementById('offline-notification');
        if (notification) {
            notification.remove();
        }
    }

    /**
     * Show sync notification
     */
    showSyncNotification(message, type = 'info') {
        const existingNotification = document.getElementById('sync-notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
        const notification = document.createElement('div');
        notification.id = 'sync-notification';
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-sync'}"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transition = 'opacity 0.5s';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }
        }, 3000);
    }

    /**
     * Save data offline or online
     * @param {string} action - Action type
     * @param {object} data - Data to save
     */
    async saveData(action, data) {
        if (this.isOnline()) {
            // Try to save online first
            try {
                const response = await fetch(this.apiEndpoint + '?api=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: action,
                        ...data
                    })
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        // Clear offline data if it exists
                        this.db.clearOfflineData(action, data);
                        this.showSyncNotification('Data saved successfully', 'success');
                        return { success: true, online: true };
                    } else {
                        throw new Error(result.message || 'Error saving data');
                    }
                } else {
                    throw new Error('Server connection error');
                }
            } catch (error) {
                console.error('Online save failed, saving offline:', error);
                // Fall through to offline save
            }
        }

        // Save offline
        try {
            await this.db.addPendingUpdate(action, data);
            this.showOfflineNotification();
            this.showSyncNotification('Data saved offline and will be synced when connection is restored', 'info');
            return { success: true, online: false };
        } catch (error) {
            console.error('Offline save failed:', error);
            this.showSyncNotification('Error saving data', 'error');
            return { success: false, error: error.message };
        }
    }

    /**
     * Sync all pending updates
     */
    async syncPendingUpdates() {
        if (this.syncInProgress || !this.isOnline()) {
            return;
        }

        this.syncInProgress = true;
        this.hideOfflineNotification();

        try {
            const pendingUpdates = await this.db.getPendingUpdates();

            if (pendingUpdates.length === 0) {
                this.syncInProgress = false;
                return;
            }

            console.log(`Syncing ${pendingUpdates.length} pending updates...`);
            this.showSyncNotification(`Syncing ${pendingUpdates.length} item(s)...`, 'info');

            let successCount = 0;
            let errorCount = 0;

            for (const update of pendingUpdates) {
                try {
                    const response = await fetch(this.apiEndpoint + '?api=1', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            action: update.action,
                            ...update.data
                        })
                    });

                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            await this.db.markAsSynced(update.id);
                            // Clear offline data from localStorage
                            this.db.clearOfflineData(update.action, update.data);
                            successCount++;
                        } else {
                            errorCount++;
                            console.error('Sync failed for update:', update, result.message);
                        }
                    } else {
                        errorCount++;
                        console.error('Sync failed for update:', update, response.statusText);
                    }
                } catch (error) {
                    errorCount++;
                    console.error('Error syncing update:', update, error);
                }
            }

            // Clean up synced records
            for (const update of pendingUpdates) {
                if (update.synced) {
                    await this.db.deleteSyncedUpdate(update.id);
                }
            }

            if (successCount > 0) {
                this.showSyncNotification(`${successCount} item(s) synced successfully`, 'success');
                // Reload page to show updated data after successful sync
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }

            if (errorCount > 0) {
                this.showSyncNotification(`${errorCount} item(s) failed to sync`, 'error');
            }

        } catch (error) {
            console.error('Error during sync:', error);
            this.showSyncNotification('Error during synchronization', 'error');
        } finally {
            this.syncInProgress = false;
        }
    }

    /**
     * Get pending updates count
     */
    async getPendingCount() {
        return await this.db.getPendingCount();
    }

    /**
     * Manual sync trigger
     */
    async manualSync() {
        if (!this.isOnline()) {
            this.showSyncNotification('Please connect to the internet first', 'error');
            return;
        }

        await this.syncPendingUpdates();
    }
}

// Initialize global sync manager
const offlineSyncManager = new OfflineSyncManager();

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineSyncManager;
}

