<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/settings/last_location/index.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Get last location for each mobile user
$mobileUsers = [];
try {
    $pdo = getDBConnection();
    
    // Get the most recent location for each user where device_type is mobile
    // Using subquery to get only the latest location per user
    $query = "SELECT 
                ul.*,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.username,
                u.picture,
                u.position
              FROM user_locations ul
              INNER JOIN users u ON u.id = ul.user_id
              INNER JOIN (
                  SELECT user_id, MAX(created_at) as max_created_at
                  FROM user_locations
                  WHERE device_type = 'mobile'
                  GROUP BY user_id
              ) latest ON ul.user_id = latest.user_id AND ul.created_at = latest.max_created_at
              WHERE ul.device_type = 'mobile'
              ORDER BY ul.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $mobileUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure we only have one location per user (in case of exact timestamp matches)
    $userMap = [];
    foreach ($mobileUsers as $user) {
        $userId = $user['user_id'];
        if (!isset($userMap[$userId])) {
            $userMap[$userId] = $user;
        }
    }
    $mobileUsers = array_values($userMap);
    
} catch (Exception $e) {
    error_log("Error fetching mobile user locations: " . $e->getMessage());
    $message = 'Error loading locations';
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Last Location - <?php echo PROJECT_NAME; ?></title>
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
            height: 700px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 1;
        }
        
        .user-marker {
            cursor: pointer;
        }
        
        .custom-popup {
            min-width: 200px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3b82f6;
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
                                <i class="fas fa-map-marker-alt mr-2"></i>Last Location
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Last known location of mobile users
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium">
                                <i class="fas fa-mobile-alt mr-1"></i><?php echo count($mobileUsers); ?> Users
                            </span>
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
                
                <!-- Map -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-map mr-2"></i>Location Map
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-400 ml-2">
                                (<?php echo count($mobileUsers); ?> mobile users)
                            </span>
                        </h3>
                    </div>
                    <div class="p-6">
                        <div id="map"></div>
                    </div>
                </div>
                
                <!-- User List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-users mr-2"></i>Mobile Users
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($mobileUsers)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-mobile-alt text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500 dark:text-gray-400">No mobile user locations found</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($mobileUsers as $user): ?>
                                    <div class="user-card bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600 hover:shadow-lg transition-all cursor-pointer"
                                         data-lat="<?php echo htmlspecialchars($user['latitude']); ?>" 
                                         data-lng="<?php echo htmlspecialchars($user['longitude']); ?>"
                                         data-user-id="<?php echo $user['user_id']; ?>">
                                        <div class="flex items-center gap-4">
                                            <div class="flex-shrink-0">
                                                <?php 
                                                $profileImageUrl = getProfileImageUrl($user['picture'] ?? '');
                                                ?>
                                                <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" 
                                                     alt="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                     class="user-avatar"
                                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Ccircle cx=%2750%27 cy=%2750%27 r=%2740%27 fill=%27%23e5e7eb%27/%3E%3Ctext x=%2750%27 y=%2750%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2740%27 fill=%27%239ca3af%27%3E%3C/text%3E%3C/svg%3E';">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </h4>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    <?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?>
                                                </p>
                                                <?php if (!empty($user['position'])): ?>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-1">
                                                        <i class="fas fa-briefcase mr-1"></i><?php echo htmlspecialchars($user['position']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                                    <i class="fas fa-map-marker-alt mr-1 text-blue-600 dark:text-blue-400"></i>
                                                    <?php echo number_format($user['latitude'], 6); ?>, <?php echo number_format($user['longitude'], 6); ?>
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>
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
        // Initialize map
        const mobileUsers = <?php echo json_encode($mobileUsers); ?>;
        let map = null;
        let markers = [];
        
        // Create custom icon with user avatar
        function createUserIcon(imageUrl, userName) {
            return L.divIcon({
                className: 'user-marker',
                html: `
                    <div style="position: relative;">
                        <img src="${escapeHtml(imageUrl)}" 
                             alt="${escapeHtml(userName)}"
                             style="width: 40px; height: 40px; border-radius: 50%; border: 3px solid #3b82f6; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Ccircle cx=%2750%27 cy=%2750%27 r=%2740%27 fill=%27%23e5e7eb%27/%3E%3Ctext x=%2750%27 y=%2750%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2740%27 fill=%27%239ca3af%27%3E%3C/text%3E%3C/svg%3E';">
                        <div style="position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 8px solid #3b82f6;"></div>
                    </div>
                `,
                iconSize: [40, 48],
                iconAnchor: [20, 48],
                popupAnchor: [0, -48]
            });
        }
        
        // Initialize map
        function initMap() {
            if (mobileUsers.length === 0) {
                // Default center (Tehran)
                map = L.map('map').setView([35.6892, 51.3890], 6);
            } else {
                // Calculate center from locations
                const lats = mobileUsers.map(user => parseFloat(user.latitude));
                const lngs = mobileUsers.map(user => parseFloat(user.longitude));
                const centerLat = (Math.min(...lats) + Math.max(...lats)) / 2;
                const centerLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;
                
                map = L.map('map').setView([centerLat, centerLng], 10);
            }
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add markers
            mobileUsers.forEach(user => {
                const lat = parseFloat(user.latitude);
                const lng = parseFloat(user.longitude);
                const userName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username || 'Unknown';
                const dateTime = new Date(user.created_at).toLocaleString();
                // Get profile image URL - use PHP function result
                let profileImageUrl = '/assets/images/default-avatar.svg';
                if (user.picture) {
                    profileImageUrl = user.picture.startsWith('/') ? user.picture : '/' + user.picture;
                }
                
                // Use default avatar if no picture
                const imageUrl = profileImageUrl || 'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Ccircle cx=%2750%27 cy=%2750%27 r=%2740%27 fill=%27%23e5e7eb%27/%3E%3Ctext x=%2750%27 y=%2750%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2740%27 fill=%27%239ca3af%27%3E%3C/text%3E%3C/svg%3E';
                
                const marker = L.marker([lat, lng], { 
                    icon: createUserIcon(imageUrl, userName)
                }).addTo(map);
                
                const popupContent = `
                    <div class="custom-popup">
                        <div style="text-align: center; margin-bottom: 10px;">
                            <img src="${escapeHtml(imageUrl)}" 
                                 alt="${escapeHtml(userName)}"
                                 style="width: 60px; height: 60px; border-radius: 50%; border: 3px solid #3b82f6; object-fit: cover; margin-bottom: 8px;"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Ccircle cx=%2750%27 cy=%2750%27 r=%2740%27 fill=%27%23e5e7eb%27/%3E%3Ctext x=%2750%27 y=%2750%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2740%27 fill=%27%239ca3af%27%3E%3C/text%3E%3C/svg%3E';">
                            <div style="font-weight: bold; font-size: 14px; margin-bottom: 4px;">${escapeHtml(userName)}</div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">${escapeHtml(user.username || 'N/A')}</div>
                        </div>
                        <hr style="margin: 8px 0; border-color: #e5e7eb;">
                        <div style="font-size: 12px;">
                            <div style="margin-bottom: 4px;"><strong>Position:</strong> ${escapeHtml(user.position || 'N/A')}</div>
                            <div style="margin-bottom: 4px;"><strong>Coordinates:</strong><br>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</div>
                            <div style="margin-bottom: 4px;"><strong>Accuracy:</strong> ${user.accuracy ? parseFloat(user.accuracy).toFixed(2) + ' m' : 'N/A'}</div>
                            <div style="margin-bottom: 4px;"><strong>Last Update:</strong> ${escapeHtml(dateTime)}</div>
                            <div><strong>IP:</strong> ${escapeHtml(user.ip_address || 'N/A')}</div>
                        </div>
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
        
        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
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
            
            // Add click handler to user cards
            document.querySelectorAll('.user-card').forEach(card => {
                card.addEventListener('click', function() {
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

