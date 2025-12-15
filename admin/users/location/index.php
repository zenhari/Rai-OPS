<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/users/location/index.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Get filter parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$deviceType = isset($_GET['device_type']) ? trim($_GET['device_type']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build query
$whereConditions = [];
$params = [];

if ($userId > 0) {
    $whereConditions[] = "ul.user_id = ?";
    $params[] = $userId;
}

if (!empty($deviceType)) {
    $whereConditions[] = "ul.device_type = ?";
    $params[] = $deviceType;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(ul.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(ul.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get all locations
$locations = [];
try {
    $pdo = getDBConnection();
    $query = "SELECT ul.*, u.first_name, u.last_name, u.username 
              FROM user_locations ul
              LEFT JOIN users u ON u.id = ul.user_id
              $whereClause
              ORDER BY ul.created_at DESC
              LIMIT 1000";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching locations: " . $e->getMessage());
    $message = 'Error loading locations';
    $message_type = 'error';
}

// Get all users for filter
$allUsers = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, username FROM users WHERE status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Location - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../../../assets/js/tailwind.js"></script>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        #map {
            height: 600px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 1;
        }
        
        .location-marker {
            cursor: pointer;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-map-marked-alt mr-2"></i>User Location
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                View user locations on map
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Message -->
                <?php if (!empty($message)): ?>
                <div class="mb-4 bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-50 dark:bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900/20 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 dark:border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-800 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 dark:text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-400 px-4 py-3 rounded-md">
                    <div class="flex">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mt-0.5 mr-2"></i>
                        <span class="text-sm"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-filter mr-2"></i>Filters
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User</label>
                                <select name="user_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                    <option value="">All Users</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Device Type</label>
                                <select name="device_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                    <option value="">All Devices</option>
                                    <option value="mobile" <?php echo $deviceType === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                                    <option value="tablet" <?php echo $deviceType === 'tablet' ? 'selected' : ''; ?>>Tablet</option>
                                    <option value="laptop" <?php echo $deviceType === 'laptop' ? 'selected' : ''; ?>>Laptop</option>
                                    <option value="desktop" <?php echo $deviceType === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date From</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            </div>
                            
                            <div class="md:col-span-4 flex gap-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md">
                                    <i class="fas fa-search mr-2"></i>Filter
                                </button>
                                <a href="?" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md">
                                    <i class="fas fa-redo mr-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Map -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-map mr-2"></i>Location Map
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-400 ml-2">
                                (<?php echo count($locations); ?> locations)
                            </span>
                        </h3>
                    </div>
                    <div class="p-6">
                        <div id="map"></div>
                    </div>
                </div>
                
                <!-- Location List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-list mr-2"></i>Location List
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Coordinates</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Device</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Accuracy</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date & Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($locations)): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            No locations found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($locations as $location): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 location-row" 
                                            data-lat="<?php echo htmlspecialchars($location['latitude']); ?>" 
                                            data-lng="<?php echo htmlspecialchars($location['longitude']); ?>">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars(($location['first_name'] ?? '') . ' ' . ($location['last_name'] ?? '') . ' (' . ($location['username'] ?? 'N/A') . ')'); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($location['latitude']); ?>, <?php echo htmlspecialchars($location['longitude']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <?php echo htmlspecialchars(ucfirst($location['device_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo $location['accuracy'] ? number_format($location['accuracy'], 2) . ' m' : 'N/A'; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo date('Y-m-d H:i:s', strtotime($location['created_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($location['ip_address'] ?? 'N/A'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Initialize map
        const locations = <?php echo json_encode($locations); ?>;
        let map = null;
        let markers = [];
        
        // Color mapping for device types
        const deviceColors = {
            'mobile': '#3b82f6',    // Blue
            'tablet': '#10b981',    // Green
            'laptop': '#f59e0b',    // Amber
            'desktop': '#ef4444',   // Red
            'unknown': '#6b7280'    // Gray
        };
        
        // Create custom icon
        function createIcon(deviceType) {
            const color = deviceColors[deviceType] || deviceColors['unknown'];
            return L.divIcon({
                className: 'location-marker',
                html: `<div style="background-color: ${color}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                iconSize: [12, 12],
                iconAnchor: [6, 6]
            });
        }
        
        // Initialize map
        function initMap() {
            if (locations.length === 0) {
                // Default center (Tehran)
                map = L.map('map').setView([35.6892, 51.3890], 6);
            } else {
                // Calculate center from locations
                const lats = locations.map(loc => parseFloat(loc.latitude));
                const lngs = locations.map(loc => parseFloat(loc.longitude));
                const centerLat = (Math.min(...lats) + Math.max(...lats)) / 2;
                const centerLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;
                
                map = L.map('map').setView([centerLat, centerLng], 10);
            }
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add markers
            locations.forEach(location => {
                const lat = parseFloat(location.latitude);
                const lng = parseFloat(location.longitude);
                const deviceType = location.device_type || 'unknown';
                const userName = `${location.first_name || ''} ${location.last_name || ''}`.trim() || location.username || 'Unknown';
                const dateTime = new Date(location.created_at).toLocaleString();
                
                const marker = L.marker([lat, lng], { icon: createIcon(deviceType) }).addTo(map);
                
                const popupContent = `
                    <div style="min-width: 200px;">
                        <strong>${escapeHtml(userName)}</strong><br>
                        <small>${escapeHtml(location.username || 'N/A')}</small><br>
                        <hr style="margin: 5px 0;">
                        <strong>Coordinates:</strong><br>
                        Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}<br>
                        <strong>Device:</strong> ${escapeHtml(ucfirst(deviceType))}<br>
                        <strong>Accuracy:</strong> ${location.accuracy ? parseFloat(location.accuracy).toFixed(2) + ' m' : 'N/A'}<br>
                        <strong>Date:</strong> ${escapeHtml(dateTime)}<br>
                        <strong>IP:</strong> ${escapeHtml(location.ip_address || 'N/A')}
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markers.push(marker);
            });
            
            // Fit map to show all markers
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }
        
        // Helper function to capitalize first letter
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Add click handler to table rows
            document.querySelectorAll('.location-row').forEach(row => {
                row.addEventListener('click', function() {
                    const lat = parseFloat(this.dataset.lat);
                    const lng = parseFloat(this.dataset.lng);
                    
                    map.setView([lat, lng], 15);
                    
                    // Find and open corresponding marker popup
                    markers.forEach(marker => {
                        const markerLat = marker.getLatLng().lat;
                        const markerLng = marker.getLatLng().lng;
                        if (Math.abs(markerLat - lat) < 0.0001 && Math.abs(markerLng - lng) < 0.0001) {
                            marker.openPopup();
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

