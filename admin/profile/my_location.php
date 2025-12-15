<?php
require_once '../../config.php';

// Check access
checkPageAccessWithRedirect('admin/profile/my_location.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Get user's location history
$userLocations = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM user_locations WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$current_user['id']]);
    $userLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user locations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get My Location - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../../assets/js/tailwind.js"></script>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        #map {
            height: 500px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 1;
        }
        
        .location-card {
            transition: all 0.2s ease;
        }
        
        .location-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
                                <i class="fas fa-map-marker-alt mr-2"></i>Get My Location
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Share your current location
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../includes/permission_banner.php'; ?>
                
                <!-- Message -->
                <?php if (!empty($message)): ?>
                <div class="mb-4 bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-50 dark:bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900/20 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 dark:border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-800 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 dark:text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-400 px-4 py-3 rounded-md">
                    <div class="flex">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mt-0.5 mr-2"></i>
                        <span class="text-sm"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Get Location Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-crosshairs mr-2"></i>Current Location
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <button id="getLocationBtn" 
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                Get My Location
                            </button>
                        </div>
                        
                        <div id="locationStatus" class="mb-4 hidden">
                            <div class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-3"></i>
                                <span class="text-sm text-blue-800 dark:text-blue-200" id="statusText"></span>
                            </div>
                        </div>
                        
                        <div id="locationInfo" class="hidden mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Latitude</label>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" id="latitude">-</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Longitude</label>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" id="longitude">-</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Accuracy</label>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" id="accuracy">-</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Device Type</label>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" id="deviceType">-</p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="map" class="mt-4"></div>
                    </div>
                </div>
                
                <!-- Location History -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-history mr-2"></i>Location History
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($userLocations)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-map-marker-alt text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500 dark:text-gray-400">No location history found</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($userLocations as $location): ?>
                                    <div class="location-card bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-blue-600 dark:text-blue-400 mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($location['latitude']); ?>, <?php echo htmlspecialchars($location['longitude']); ?>
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                    <div>
                                                        <span class="font-medium">Device:</span> <?php echo htmlspecialchars(ucfirst($location['device_type'])); ?>
                                                    </div>
                                                    <div>
                                                        <span class="font-medium">Accuracy:</span> <?php echo $location['accuracy'] ? number_format($location['accuracy'], 2) . ' m' : 'N/A'; ?>
                                                    </div>
                                                    <div>
                                                        <span class="font-medium">Date:</span> <?php echo date('Y-m-d H:i', strtotime($location['created_at'])); ?>
                                                    </div>
                                                    <div>
                                                        <span class="font-medium">IP:</span> <?php echo htmlspecialchars($location['ip_address'] ?? 'N/A'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        let map = null;
        let marker = null;
        
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
        
        // Initialize map
        function initMap(lat, lng) {
            if (map) {
                map.remove();
            }
            
            map = L.map('map').setView([lat, lng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            if (marker) {
                map.removeLayer(marker);
            }
            
            marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup(`<strong>Your Location</strong><br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        }
        
        // Get location
        document.getElementById('getLocationBtn').addEventListener('click', function() {
            const btn = this;
            const statusDiv = document.getElementById('locationStatus');
            const statusText = document.getElementById('statusText');
            const locationInfo = document.getElementById('locationInfo');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Getting Location...';
            
            statusDiv.classList.remove('hidden');
            statusText.textContent = 'Requesting location access...';
            
            if (!navigator.geolocation) {
                statusText.textContent = 'Geolocation is not supported by your browser.';
                statusDiv.className = 'mb-4 flex items-center p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-map-marker-alt mr-2"></i>Get My Location';
                return;
            }
            
            const options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
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
                    
                    // Display location info
                    document.getElementById('latitude').textContent = lat.toFixed(8);
                    document.getElementById('longitude').textContent = lng.toFixed(8);
                    document.getElementById('accuracy').textContent = accuracy ? accuracy.toFixed(2) + ' m' : 'N/A';
                    document.getElementById('deviceType').textContent = detectDeviceType();
                    
                    locationInfo.classList.remove('hidden');
                    
                    // Initialize map
                    initMap(lat, lng);
                    
                    // Save location to server
                    saveLocation(lat, lng, accuracy, altitude, altitudeAccuracy, heading, speed);
                    
                    statusText.textContent = 'Location retrieved successfully!';
                    statusDiv.className = 'mb-4 flex items-center p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg';
                    
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-map-marker-alt mr-2"></i>Get My Location';
                },
                function(error) {
                    let errorMsg = 'Error getting location: ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg += 'Permission denied. Please allow location access.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg += 'Location information unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMsg += 'Location request timed out.';
                            break;
                        default:
                            errorMsg += 'Unknown error occurred.';
                            break;
                    }
                    
                    statusText.textContent = errorMsg;
                    statusDiv.className = 'mb-4 flex items-center p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg';
                    
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-map-marker-alt mr-2"></i>Get My Location';
                },
                options
            );
        });
        
        // Save location to server
        function saveLocation(lat, lng, accuracy, altitude, altitudeAccuracy, heading, speed) {
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
                    // Reload page after 1 second to show new location in history
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    console.error('Error saving location:', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>

