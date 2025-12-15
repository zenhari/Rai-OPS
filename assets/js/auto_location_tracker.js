/**
 * Auto Location Tracker
 * Automatically gets user location every 30 minutes and on page load
 * Only works for mobile, tablet, and laptop devices
 */

(function() {
    'use strict';
    
    // Check if user is logged in (sidebar exists means user is logged in)
    const isLoggedIn = document.getElementById('sidebar') !== null;
    
    if (!isLoggedIn) {
        return; // Exit if user is not logged in
    }
    
    // Detect device type
    function detectDeviceType() {
        const ua = navigator.userAgent.toLowerCase();
        if (/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i.test(ua)) {
            return 'mobile';
        } else if (/tablet|ipad|playbook|silk/i.test(ua)) {
            return 'tablet';
        } else if (/laptop/i.test(ua) || (window.screen.width >= 1024 && window.screen.height >= 768)) {
            return 'laptop';
        } else {
            return 'desktop';
        }
    }
    
    // Only track location for mobile, tablet, or laptop
    const deviceType = detectDeviceType();
    if (deviceType === 'desktop') {
        return; // Exit for desktop
    }
    
    // Check if geolocation is supported
    if (!navigator.geolocation) {
        console.log('Geolocation is not supported by your browser.');
        return;
    }
    
    // Get and save location
    function getAndSaveLocation(silent = false) {
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000 // Accept cached location up to 1 minute old
        };
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                const altitude = position.coords.altitude;
                const altitudeAccuracy = position.coords.altitudeAccuracy;
                const heading = position.coords.heading;
                const speed = position.coords.speed;
                
                // Save location to server
                saveLocation(lat, lng, accuracy, altitude, altitudeAccuracy, heading, speed, silent);
            },
            function(error) {
                if (!silent) {
                    console.log('Error getting location:', error.message);
                }
            },
            options
        );
    }
    
    // Save location to server
    function saveLocation(lat, lng, accuracy, altitude, altitudeAccuracy, heading, speed, silent = false) {
        const deviceType = detectDeviceType();
        const userAgent = navigator.userAgent;
        
        fetch('/admin/api/save_location.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                latitude: lat,
                longitude: lng,
                accuracy: accuracy,
                altitude: altitude,
                altitude_accuracy: altitudeAccuracy,
                heading: heading,
                speed: speed,
                device_type: deviceType,
                user_agent: userAgent
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (!silent) {
                    console.log('Location saved successfully');
                }
                // Update last location time in localStorage
                localStorage.setItem('lastLocationTime', Date.now().toString());
            } else {
                if (!silent) {
                    console.error('Error saving location:', data.error);
                }
            }
        })
        .catch(error => {
            if (!silent) {
                console.error('Error:', error);
            }
        });
    }
    
    // Check if location permission was granted before
    function checkLocationPermission() {
        // Check localStorage for permission status
        const permissionStatus = localStorage.getItem('locationPermission');
        
        if (permissionStatus === 'granted') {
            // Permission was granted before, start tracking immediately
            getAndSaveLocation(true); // Silent mode
            
            // Set interval for every 30 minutes (1800000 milliseconds)
            setInterval(function() {
                getAndSaveLocation(true); // Silent mode
            }, 30 * 60 * 1000); // 30 minutes
            
            return true;
        } else if (permissionStatus === 'denied') {
            // Permission was denied, don't track
            return false;
        }
        
        // Permission not set yet, will be set when user grants/denies
        return null;
    }
    
    // Request location permission on first load
    function requestLocationPermission() {
        const permissionStatus = localStorage.getItem('locationPermission');
        
        // If permission was already granted, start tracking
        if (permissionStatus === 'granted') {
            checkLocationPermission();
            return;
        }
        
        // If permission was denied, don't show modal again
        if (permissionStatus === 'denied') {
            return;
        }
        
        // Show modal after 2 seconds
        setTimeout(() => {
            const modal = document.createElement('div');
            modal.id = 'locationRequestModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-map-marker-alt text-blue-600 dark:text-blue-400 text-2xl mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Auto Location Tracking</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Allow automatic location tracking? Your location will be updated every 30 minutes to help us provide better services.
                    </p>
                    <div class="flex gap-3">
                        <button id="allowLocationBtn" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors">
                            <i class="fas fa-check mr-2"></i>Allow
                        </button>
                        <button id="denyLocationBtn" class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-medium rounded-md transition-colors">
                            <i class="fas fa-times mr-2"></i>Deny
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Handle allow button
            document.getElementById('allowLocationBtn').addEventListener('click', function() {
                modal.remove();
                localStorage.setItem('locationPermission', 'granted');
                
                // Get location immediately
                getAndSaveLocation(false);
                
                // Set interval for every 30 minutes
                setInterval(function() {
                    getAndSaveLocation(true); // Silent mode after first time
                }, 30 * 60 * 1000); // 30 minutes
            });
            
            // Handle deny button
            document.getElementById('denyLocationBtn').addEventListener('click', function() {
                modal.remove();
                localStorage.setItem('locationPermission', 'denied');
            });
        }, 2000);
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check if location was already granted
        const permissionGranted = checkLocationPermission();
        
        if (permissionGranted === null) {
            // Permission not set, request it
            requestLocationPermission();
        }
        
        // Also get location when page becomes visible (user returns to tab/app)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && localStorage.getItem('locationPermission') === 'granted') {
                // Page became visible and permission is granted, get location
                const lastLocationTime = parseInt(localStorage.getItem('lastLocationTime') || '0');
                const now = Date.now();
                const timeSinceLastLocation = now - lastLocationTime;
                
                // Only get location if it's been more than 5 minutes since last update
                if (timeSinceLastLocation > 5 * 60 * 1000) {
                    getAndSaveLocation(true); // Silent mode
                }
            }
        });
        
        // Get location when window gains focus (user switches back to window)
        window.addEventListener('focus', function() {
            if (localStorage.getItem('locationPermission') === 'granted') {
                const lastLocationTime = parseInt(localStorage.getItem('lastLocationTime') || '0');
                const now = Date.now();
                const timeSinceLastLocation = now - lastLocationTime;
                
                // Only get location if it's been more than 5 minutes since last update
                if (timeSinceLastLocation > 5 * 60 * 1000) {
                    getAndSaveLocation(true); // Silent mode
                }
            }
        });
    });
})();

