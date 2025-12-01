<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/profile/index.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Helper function to safely output values
function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $profile_data = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'position' => trim($_POST['position'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'mobile' => trim($_POST['mobile'] ?? ''),
                'alternative_mobile' => trim($_POST['alternative_mobile'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'fax' => trim($_POST['fax'] ?? ''),
                'alternate_email' => trim($_POST['alternate_email'] ?? ''),
                'address_line_1' => trim($_POST['address_line_1'] ?? ''),
                'address_line_2' => trim($_POST['address_line_2'] ?? ''),
                'suburb_city' => trim($_POST['suburb_city'] ?? ''),
                'postcode' => trim($_POST['postcode'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
                'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null,
                'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
                'emergency_contact_number' => trim($_POST['emergency_contact_number'] ?? ''),
                'emergency_contact_email' => trim($_POST['emergency_contact_email'] ?? ''),
                'emergency_contact_alternate_email' => trim($_POST['emergency_contact_alternate_email'] ?? ''),
                'passport_number' => trim($_POST['passport_number'] ?? ''),
                'passport_nationality' => trim($_POST['passport_nationality'] ?? ''),
                'passport_expiry_date' => !empty($_POST['passport_expiry_date']) ? $_POST['passport_expiry_date'] : null,
                'driver_licence_number' => trim($_POST['driver_licence_number'] ?? ''),
                'frequent_flyer_number' => trim($_POST['frequent_flyer_number'] ?? ''),
                'other_award_scheme_name' => trim($_POST['other_award_scheme_name'] ?? ''),
                'other_award_scheme_number' => trim($_POST['other_award_scheme_number'] ?? ''),
                'individual_leave_entitlements' => trim($_POST['individual_leave_entitlements'] ?? ''),
                'using_standalone_annual_leave' => isset($_POST['using_standalone_annual_leave']) ? 1 : 0,
                'leave_days' => intval($_POST['leave_days'] ?? 0),
                'roles_groups' => trim($_POST['roles_groups'] ?? ''),
                'selected_roles_groups' => trim($_POST['selected_roles_groups'] ?? ''),
                'receive_scheduled_emails' => isset($_POST['receive_scheduled_emails']) ? 1 : 0
            ];
            
            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = uploadProfileImage($_FILES['profile_image'], $current_user['id']);
                if ($uploadResult['success']) {
                    $profile_data['picture'] = $uploadResult['path'];
                } else {
                    $message = 'Image upload failed: ' . $uploadResult['message'];
                    $message_type = 'error';
                }
            }
            
            if (empty($message)) { // Only proceed if no upload error
                if (updateUserProfile($current_user['id'], $profile_data)) {
                    $message = 'Profile updated successfully!';
                    $message_type = 'success';
                    // Refresh user data
                    $current_user = getCurrentUser();
                } else {
                    $message = 'Failed to update profile. Please try again.';
                    $message_type = 'error';
                }
            }
        } elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $message_type = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = 'New password must be at least 6 characters long.';
                $message_type = 'error';
            } elseif (changePassword($current_user['id'], $current_password, $new_password)) {
                $message = 'Password changed successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to change password. Please check your current password.';
                $message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../../assets/js/tailwind.js"></script>
    
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
                                <i class="fas fa-user-circle mr-2"></i>My Profile
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage your personal information and account settings
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Success/Error Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'; ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400'; ?>"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium <?php echo $message_type === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div>
                    <!-- Profile Header Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-user mr-2"></i>Profile Information
                            </h3>
                        </div>
                        
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="h-20 w-20 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center mr-6">
                                    <?php if (!empty($current_user['picture']) && file_exists(__DIR__ . '/../../' . $current_user['picture'])): ?>
                                        <img src="<?php echo getProfileImageUrl($current_user['picture']); ?>" 
                                             alt="Profile" class="h-20 w-20 rounded-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-user text-gray-600 dark:text-gray-300 text-3xl"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo safeOutput($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                                    </h4>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        <?php echo safeOutput($current_user['position']); ?>
                                    </p>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mt-2">
                                        <?php echo ucfirst($current_user['role'] ?? 'employee'); ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Member since
                                    </p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo date('M Y', strtotime($current_user['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <!-- Personal Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-user mr-2"></i>Personal Information
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- First Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        First Name *
                                    </label>
                                    <input type="text" name="first_name" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['first_name']); ?>">
                                </div>

                                <!-- Last Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Last Name *
                                    </label>
                                    <input type="text" name="last_name" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['last_name']); ?>">
                                </div>

                                <!-- Profile Picture -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Profile Picture
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <!-- Current Image -->
                                        <div class="flex-shrink-0">
                                            <?php 
                                            $profileImageUrl = getProfileImageUrl($current_user['picture'] ?? '');
                                            $hasImage = !empty($current_user['picture']) && file_exists(__DIR__ . '/../../' . $current_user['picture']);
                                            ?>
                                            <?php if ($hasImage): ?>
                                                <img src="<?php echo $profileImageUrl; ?>" 
                                                     alt="Current Profile" class="h-20 w-20 rounded-full object-cover border-2 border-gray-300 dark:border-gray-600">
                                            <?php else: ?>
                                                <div class="h-20 w-20 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center border-2 border-gray-300 dark:border-gray-600">
                                                    <i class="fas fa-user text-gray-500 dark:text-gray-400 text-2xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Upload Input -->
                                        <div class="flex-1">
                                            <input type="file" name="profile_image" id="profile_image" 
                                                   accept="image/jpeg,image/jpg,image/png,image/gif"
                                                   class="block w-full text-sm text-gray-500 dark:text-gray-400
                                                          file:mr-4 file:py-2 file:px-4
                                                          file:rounded-md file:border-0
                                                          file:text-sm file:font-medium
                                                          file:bg-blue-50 file:text-blue-700
                                                          hover:file:bg-blue-100
                                                          dark:file:bg-blue-900 dark:file:text-blue-200
                                                          dark:hover:file:bg-blue-800">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                JPEG, PNG, or GIF. Max size: 5MB. Leave empty to keep current image.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Position -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Position *
                                    </label>
                                    <input type="text" name="position" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['position']); ?>">
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Email *
                                    </label>
                                    <input type="email" name="email" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['email']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-phone mr-2"></i>Contact Information
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Mobile -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Mobile
                                    </label>
                                    <input type="tel" name="mobile"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['mobile']); ?>">
                                </div>

                                <!-- Alternative Mobile -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Alternative Mobile
                                    </label>
                                    <input type="tel" name="alternative_mobile"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['alternative_mobile']); ?>">
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Phone
                                    </label>
                                    <input type="tel" name="phone"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['phone']); ?>">
                                </div>

                                <!-- Fax -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Fax
                                    </label>
                                    <input type="tel" name="fax"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['fax']); ?>">
                                </div>

                                <!-- Alternate Email -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Alternate Email
                                    </label>
                                    <input type="email" name="alternate_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['alternate_email']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-map-marker-alt mr-2"></i>Address Information
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Address Line 1 -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 1
                                    </label>
                                    <input type="text" name="address_line_1"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['address_line_1']); ?>">
                                </div>

                                <!-- Address Line 2 -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 2
                                    </label>
                                    <input type="text" name="address_line_2"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['address_line_2']); ?>">
                                </div>

                                <!-- Suburb/City -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Suburb / City
                                    </label>
                                    <input type="text" name="suburb_city"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['suburb_city']); ?>">
                                </div>

                                <!-- Postcode -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Postcode
                                    </label>
                                    <input type="text" name="postcode"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['postcode']); ?>">
                                </div>

                                <!-- State -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        State
                                    </label>
                                    <input type="text" name="state"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['state']); ?>">
                                </div>

                                <!-- Country -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Country
                                    </label>
                                    <input type="text" name="country"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['country']); ?>">
                                </div>

                                <!-- Latitude -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Latitude (Lat)
                                    </label>
                                    <input type="text" name="latitude" id="latitude"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 35.7476667"
                                           value="<?php echo safeOutput($current_user['latitude'] ?? ''); ?>">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Enter latitude coordinate (e.g., 35.7476667)
                                    </p>
                                </div>

                                <!-- Longitude -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Longitude (Lng)
                                    </label>
                                    <input type="text" name="longitude" id="longitude"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 51.2539722"
                                           value="<?php echo safeOutput($current_user['longitude'] ?? ''); ?>">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Enter longitude coordinate (e.g., 51.2539722)
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Map Display -->
                            <?php if (!empty($current_user['latitude']) && !empty($current_user['longitude'])): 
                                $lat = floatval($current_user['latitude']);
                                $lng = floatval($current_user['longitude']);
                                
                                // Validate coordinates
                                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180):
                                    // Convert to degrees, minutes, seconds format for Google Maps URL
                                    $latDeg = floor(abs($lat));
                                    $latMin = floor((abs($lat) - $latDeg) * 60);
                                    $latSec = round(((abs($lat) - $latDeg) * 60 - $latMin) * 60, 1);
                                    $latDir = $lat >= 0 ? 'N' : 'S';
                                    
                                    $lngDeg = floor(abs($lng));
                                    $lngMin = floor((abs($lng) - $lngDeg) * 60);
                                    $lngSec = round(((abs($lng) - $lngDeg) * 60 - $lngMin) * 60, 1);
                                    $lngDir = $lng >= 0 ? 'E' : 'W';
                                    
                                    // Google Maps embed URL (simple format)
                                    $mapsEmbedUrl = "https://www.google.com/maps?q={$lat},{$lng}&output=embed&z=16";
                                    // Google Maps link URL (format: lat,lng)
                                    $mapsLinkUrl = "https://www.google.de/maps/place/{$latDeg}%C2%B0{$latMin}'{$latSec}%22{$latDir}+{$lngDeg}%C2%B0{$lngMin}'{$lngSec}%22{$lngDir}/@{$lat},{$lng},16z/data=!4m4!3m3!8m2!3d{$lat}!4d{$lng}?entry=ttu";
                            ?>
                                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                                        <i class="fas fa-map mr-2"></i>Location Map
                                    </h4>
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
                                        <!-- Google Maps Iframe -->
                                        <div class="mb-4">
                                            <iframe 
                                                src="<?php echo htmlspecialchars($mapsEmbedUrl); ?>" 
                                                width="100%" 
                                                height="400" 
                                                style="border:0;" 
                                                allowfullscreen="" 
                                                loading="lazy" 
                                                referrerpolicy="no-referrer-when-downgrade"
                                                class="rounded-lg">
                                            </iframe>
                                        </div>
                                        
                                        <!-- Google Maps Link -->
                                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                                            <div class="flex items-center">
                                                <i class="fas fa-external-link-alt text-blue-600 dark:text-blue-400 mr-2"></i>
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Open in Google Maps</span>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($mapsLinkUrl); ?>" 
                                               target="_blank"
                                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                                <i class="fas fa-map-marker-alt mr-2"></i>
                                                View Location
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                else:
                                    // Invalid coordinates
                                    echo '<div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                            <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                Invalid coordinates. Latitude must be between -90 and 90, and longitude must be between -180 and 180.
                                            </p>
                                        </div>
                                    </div>';
                                endif;
                            endif; ?>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Emergency Contact
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Emergency Contact Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Name
                                    </label>
                                    <input type="text" name="emergency_contact_name"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['emergency_contact_name']); ?>">
                                </div>

                                <!-- Emergency Contact Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Number
                                    </label>
                                    <input type="tel" name="emergency_contact_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['emergency_contact_number']); ?>">
                                </div>

                                <!-- Emergency Contact Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Email
                                    </label>
                                    <input type="email" name="emergency_contact_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['emergency_contact_email']); ?>">
                                </div>

                                <!-- Emergency Contact Alternate Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Alternate Email
                                    </label>
                                    <input type="email" name="emergency_contact_alternate_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['emergency_contact_alternate_email']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Travel Documents -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-passport mr-2"></i>Travel Documents
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Passport Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Number
                                    </label>
                                    <input type="text" name="passport_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['passport_number']); ?>">
                                </div>

                                <!-- Passport Nationality -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Nationality
                                    </label>
                                    <input type="text" name="passport_nationality"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['passport_nationality']); ?>">
                                </div>

                                <!-- Passport Expiry Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Expiry Date
                                    </label>
                                    <input type="date" name="passport_expiry_date"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $current_user['passport_expiry_date'] ? date('Y-m-d', strtotime($current_user['passport_expiry_date'])) : ''; ?>">
                                </div>

                                <!-- Driver Licence Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Driver Licence Number
                                    </label>
                                    <input type="text" name="driver_licence_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['driver_licence_number']); ?>">
                                </div>

                                <!-- Frequent Flyer Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Frequent Flyer Number
                                    </label>
                                    <input type="text" name="frequent_flyer_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['frequent_flyer_number']); ?>">
                                </div>

                                <!-- Other Award Scheme Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Award Scheme Name
                                    </label>
                                    <input type="text" name="other_award_scheme_name"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['other_award_scheme_name']); ?>">
                                </div>

                                <!-- Other Award Scheme Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Award Scheme Number
                                    </label>
                                    <input type="text" name="other_award_scheme_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($current_user['other_award_scheme_number']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Leave and Entitlements -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-calendar-alt mr-2"></i>Leave and Entitlements
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Individual Leave Entitlements -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Individual Leave Entitlements
                                    </label>
                                    <textarea name="individual_leave_entitlements" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($current_user['individual_leave_entitlements']); ?></textarea>
                                </div>

                                <!-- Using Standalone Annual Leave -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="using_standalone_annual_leave" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo $current_user['using_standalone_annual_leave'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Using Standalone Annual Leave
                                        </span>
                                    </label>
                                </div>

                                <!-- Leave Days -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Leave Days
                                    </label>
                                    <input type="number" name="leave_days" min="0"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo intval($current_user['leave_days']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Roles and Groups -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-users-cog mr-2"></i>Roles and Groups
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Roles/Groups -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Roles/Groups
                                    </label>
                                    <textarea name="roles_groups" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($current_user['roles_groups']); ?></textarea>
                                </div>

                                <!-- Selected Roles/Groups -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Selected Roles/Groups
                                    </label>
                                    <textarea name="selected_roles_groups" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($current_user['selected_roles_groups']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- System Settings -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-cog mr-2"></i>System Settings
                                </h3>
                            </div>
                            
                            <div class="p-6">
                                <!-- Receive Scheduled Emails -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="receive_scheduled_emails" value="1"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                           <?php echo $current_user['receive_scheduled_emails'] ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Receive Scheduled Emails
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Update Profile
                            </button>
                        </div>
                    </form>

                    <!-- Change Password Section -->
                    <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-lock mr-2"></i>Change Password
                            </h3>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Current Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Current Password *
                                    </label>
                                    <input type="password" name="current_password" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>

                                <!-- New Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        New Password *
                                    </label>
                                    <input type="password" name="new_password" required minlength="6"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Confirm Password *
                                    </label>
                                    <input type="password" name="confirm_password" required minlength="6"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                    <i class="fas fa-key mr-2"></i>
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
        </div>
    </div>
</body>
</html>
