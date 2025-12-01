/**
 * IndexedDB Wrapper for Offline Data Storage
 * Stores ticket prices and fuel costs when offline
 */

class StatisticsDB {
    constructor() {
        this.dbName = 'RaiOpsStatistics';
        this.dbVersion = 1;
        this.db = null;
        this.storeName = 'pendingUpdates';
    }

    /**
     * Initialize IndexedDB
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => {
                console.error('IndexedDB error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                console.log('IndexedDB initialized successfully');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create object store if it doesn't exist
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const objectStore = db.createObjectStore(this.storeName, {
                        keyPath: 'id',
                        autoIncrement: true
                    });

                    // Create indexes for efficient querying
                    objectStore.createIndex('action', 'action', { unique: false });
                    objectStore.createIndex('timestamp', 'timestamp', { unique: false });
                    objectStore.createIndex('synced', 'synced', { unique: false });
                }
            };
        });
    }

    /**
     * Add a pending update to IndexedDB
     * @param {string} action - 'update_ticket_price' or 'update_fuel_cost'
     * @param {object} data - Data to be synced
     */
    async addPendingUpdate(action, data) {
        if (!this.db) {
            await this.init();
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);

            const updateRecord = {
                action: action,
                data: data,
                timestamp: new Date().toISOString(),
                synced: false
            };

            const request = store.add(updateRecord);

            request.onsuccess = () => {
                console.log('Pending update added to IndexedDB:', updateRecord);
                // Store offline data in localStorage for UI display
                this.storeOfflineData(action, data);
                resolve(request.result);
            };

            request.onerror = () => {
                console.error('Error adding pending update:', request.error);
                reject(request.error);
            };
        });
    }

    /**
     * Store offline data in localStorage for UI display
     * @param {string} action - Action type
     * @param {object} data - Data to store
     */
    storeOfflineData(action, data) {
        try {
            const offlineData = JSON.parse(localStorage.getItem('offlineStatisticsData') || '{}');
            
            if (action === 'update_ticket_price') {
                if (!offlineData.ticketPrices) {
                    offlineData.ticketPrices = {};
                }
                offlineData.ticketPrices[data.rego] = parseFloat(data.ticket_price);
            } else if (action === 'update_fuel_cost') {
                offlineData.fuelCostPerLiter = parseFloat(data.fuel_cost);
            }
            
            localStorage.setItem('offlineStatisticsData', JSON.stringify(offlineData));
        } catch (error) {
            console.error('Error storing offline data:', error);
        }
    }

    /**
     * Get offline data from localStorage
     */
    getOfflineData() {
        try {
            return JSON.parse(localStorage.getItem('offlineStatisticsData') || '{}');
        } catch (error) {
            console.error('Error getting offline data:', error);
            return {};
        }
    }

    /**
     * Clear offline data from localStorage (after successful sync)
     * @param {string} action - Action type to clear
     * @param {object} data - Data that was synced
     */
    clearOfflineData(action, data) {
        try {
            const offlineData = this.getOfflineData();
            
            if (action === 'update_ticket_price' && data.rego) {
                if (offlineData.ticketPrices && offlineData.ticketPrices[data.rego]) {
                    delete offlineData.ticketPrices[data.rego];
                    // If no more ticket prices, remove the object
                    if (Object.keys(offlineData.ticketPrices).length === 0) {
                        delete offlineData.ticketPrices;
                    }
                }
            } else if (action === 'update_fuel_cost') {
                delete offlineData.fuelCostPerLiter;
            }
            
            localStorage.setItem('offlineStatisticsData', JSON.stringify(offlineData));
        } catch (error) {
            console.error('Error clearing offline data:', error);
        }
    }

    /**
     * Get all pending updates
     */
    async getPendingUpdates() {
        if (!this.db) {
            await this.init();
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.openCursor();

            const results = [];

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    // Only include records where synced is false
                    if (cursor.value.synced === false) {
                        results.push(cursor.value);
                    }
                    cursor.continue();
                } else {
                    resolve(results);
                }
            };

            request.onerror = () => {
                console.error('Error getting pending updates:', request.error);
                reject(request.error);
            };
        });
    }

    /**
     * Mark an update as synced
     * @param {number} id - ID of the update record
     */
    async markAsSynced(id) {
        if (!this.db) {
            await this.init();
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const getRequest = store.get(id);

            getRequest.onsuccess = () => {
                const record = getRequest.result;
                if (record) {
                    record.synced = true;
                    record.syncedAt = new Date().toISOString();
                    const updateRequest = store.put(record);

                    updateRequest.onsuccess = () => {
                        console.log('Update marked as synced:', id);
                        // Clear offline data from localStorage after successful sync
                        this.clearOfflineData(record.action, record.data);
                        resolve();
                    };

                    updateRequest.onerror = () => {
                        console.error('Error marking as synced:', updateRequest.error);
                        reject(updateRequest.error);
                    };
                } else {
                    resolve();
                }
            };

            getRequest.onerror = () => {
                console.error('Error getting record:', getRequest.error);
                reject(getRequest.error);
            };
        });
    }

    /**
     * Delete a synced update (cleanup)
     * @param {number} id - ID of the update record
     */
    async deleteSyncedUpdate(id) {
        if (!this.db) {
            await this.init();
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.delete(id);

            request.onsuccess = () => {
                console.log('Synced update deleted:', id);
                resolve();
            };

            request.onerror = () => {
                console.error('Error deleting synced update:', request.error);
                reject(request.error);
            };
        });
    }

    /**
     * Get count of pending updates
     */
    async getPendingCount() {
        const updates = await this.getPendingUpdates();
        return updates.length;
    }

    /**
     * Clear all synced records (cleanup)
     */
    async clearSyncedRecords() {
        if (!this.db) {
            await this.init();
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.openCursor();

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    // Only delete records where synced is true
                    if (cursor.value.synced === true) {
                        cursor.delete();
                    }
                    cursor.continue();
                } else {
                    console.log('All synced records cleared');
                    resolve();
                }
            };

            request.onerror = () => {
                console.error('Error clearing synced records:', request.error);
                reject(request.error);
            };
        });
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StatisticsDB;
}

