<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/users/add.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Get available roles from database
$available_roles = getAllRolesFromTable();

// Helper function to safely output values
function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

// Helper function to handle date fields properly
function handleDateField($value) {
    return !empty($value) ? $value : null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'position' => trim($_POST['position'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'asic_number' => trim($_POST['asic_number'] ?? ''),
        'national_id' => trim($_POST['national_id'] ?? ''),
        'date_of_birth' => handleDateField($_POST['date_of_birth'] ?? ''),
        'employment_history' => trim($_POST['employment_history'] ?? ''),
        'probationary_date' => handleDateField($_POST['probationary_date'] ?? ''),
        'standalone_period_wp' => trim($_POST['standalone_period_wp'] ?? ''),
        'performance_review' => trim($_POST['performance_review'] ?? ''),
        'address_line_1' => trim($_POST['address_line_1'] ?? ''),
        'address_line_2' => trim($_POST['address_line_2'] ?? ''),
        'latitude' => trim($_POST['latitude'] ?? '0'),
        'longitude' => trim($_POST['longitude'] ?? '0'),
        'suburb_city' => trim($_POST['suburb_city'] ?? ''),
        'postcode' => trim($_POST['postcode'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'mobile' => trim($_POST['mobile'] ?? ''),
        'alternative_mobile' => trim($_POST['alternative_mobile'] ?? ''),
        'fax' => trim($_POST['fax'] ?? ''),
        'alternate_email' => trim($_POST['alternate_email'] ?? ''),
        'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_number' => trim($_POST['emergency_contact_number'] ?? ''),
        'emergency_contact_email' => trim($_POST['emergency_contact_email'] ?? ''),
        'emergency_contact_alternate_email' => trim($_POST['emergency_contact_alternate_email'] ?? ''),
        'passport_number' => trim($_POST['passport_number'] ?? ''),
        'passport_nationality' => trim($_POST['passport_nationality'] ?? ''),
        'passport_expiry_date' => handleDateField($_POST['passport_expiry_date'] ?? ''),
        'driver_licence_number' => trim($_POST['driver_licence_number'] ?? ''),
        'frequent_flyer_number' => trim($_POST['frequent_flyer_number'] ?? ''),
        'other_award_scheme_name' => trim($_POST['other_award_scheme_name'] ?? ''),
        'other_award_scheme_number' => trim($_POST['other_award_scheme_number'] ?? ''),
        'individual_leave_entitlements' => trim($_POST['individual_leave_entitlements'] ?? ''),
        'using_standalone_annual_leave' => isset($_POST['using_standalone_annual_leave']) ? 1 : 0,
        'leave_days' => (int)($_POST['leave_days'] ?? 0),
        'roles_groups' => trim($_POST['roles_groups'] ?? ''),
        'selected_roles_groups' => trim($_POST['selected_roles_groups'] ?? ''),
        'receive_scheduled_emails' => isset($_POST['receive_scheduled_emails']) ? 1 : 0,
        'role_id' => intval($_POST['role_id'] ?? 2), // Default to employee role
        'status' => $_POST['status'] ?? 'active',
        'flight_crew' => isset($_POST['flight_crew']) ? 1 : 0
    ];
    
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = uploadProfileImage($_FILES['profile_image']);
        if ($uploadResult['success']) {
            $user_data['picture'] = $uploadResult['path'];
        } else {
            $message = 'Image upload failed: ' . $uploadResult['message'];
            $message_type = 'error';
        }
    }
    
    // Validation
    if (empty($user_data['first_name']) || empty($user_data['last_name']) || 
        empty($user_data['position']) || empty($user_data['username']) || 
        empty($user_data['password']) || empty($user_data['email'])) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (strlen($user_data['password']) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'error';
    } else {
        $errorMessage = '';
        if (createUser($user_data, $errorMessage)) {
            $message = 'User created successfully!';
            $message_type = 'success';
            // Clear form data after successful creation
            $user_data = [];
        } else {
            $message = $errorMessage ?: 'Failed to create user. Please check all required fields and try again.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - <?php echo PROJECT_NAME; ?></title>
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
                                <i class="fas fa-user-plus mr-2"></i>Add New User
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Create a new user account in the system
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Users
                            </a>
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
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
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
                                           value="<?php echo safeOutput($user_data['first_name'] ?? ''); ?>">
                                </div>

                                <!-- Last Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Last Name *
                                    </label>
                                    <input type="text" name="last_name" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['last_name'] ?? ''); ?>">
                                </div>

                                <!-- Profile Picture -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Profile Picture
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <!-- Placeholder Image -->
                                        <div class="flex-shrink-0">
                                            <div class="h-20 w-20 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center border-2 border-gray-300 dark:border-gray-600">
                                                <i class="fas fa-user text-gray-500 dark:text-gray-400 text-2xl"></i>
                                            </div>
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
                                                JPEG, PNG, or GIF. Max size: 5MB
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
                                           value="<?php echo safeOutput($user_data['position'] ?? ''); ?>">
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Username *
                                    </label>
                                    <input type="text" name="username" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['username'] ?? ''); ?>">
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Password *
                                    </label>
                                    <input type="password" name="password" required minlength="6"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Email *
                                    </label>
                                    <input type="email" name="email" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['email'] ?? ''); ?>">
                                </div>

                                <!-- ASIC Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ASIC Number
                                    </label>
                                    <input type="text" name="asic_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['asic_number'] ?? ''); ?>">
                                </div>

                                <!-- National ID -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        National ID
                                    </label>
                                    <input type="text" name="national_id"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['national_id'] ?? ''); ?>"
                                           placeholder="Enter National ID">
                                </div>

                                <!-- Date of Birth -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Date of Birth
                                    </label>
                                    <input type="date" name="date_of_birth"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $user_data['date_of_birth'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Professional Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-briefcase mr-2"></i>Professional Information
                                </h3>
                            </div>
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Employment History -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Employment History
                                    </label>
                                    <textarea name="employment_history" rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user_data['employment_history'] ?? ''); ?></textarea>
                                </div>

                                <!-- Probationary Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Probationary Date
                                    </label>
                                    <input type="date" name="probationary_date"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $user_data['probationary_date'] ?? ''; ?>">
                                </div>

                                <!-- Standalone Period WP -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Standalone Period WP
                                    </label>
                                    <input type="text" name="standalone_period_wp"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['standalone_period_wp'] ?? ''); ?>">
                                </div>

                                <!-- Performance Review -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Performance Review
                                    </label>
                                    <textarea name="performance_review" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user_data['performance_review'] ?? ''); ?></textarea>
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
                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Phone
                                    </label>
                                    <input type="tel" name="phone"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['phone'] ?? ''); ?>">
                                </div>

                                <!-- Mobile -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Mobile
                                    </label>
                                    <input type="tel" name="mobile"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['mobile'] ?? ''); ?>">
                                </div>

                                <!-- Alternative Mobile -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Alternative Mobile
                                    </label>
                                    <input type="tel" name="alternative_mobile"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['alternative_mobile'] ?? ''); ?>">
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Phone
                                    </label>
                                    <input type="tel" name="phone"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['phone'] ?? ''); ?>">
                                </div>

                                <!-- Fax -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Fax
                                    </label>
                                    <input type="tel" name="fax"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['fax'] ?? ''); ?>">
                                </div>

                                <!-- Alternate Email -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Alternate Email
                                    </label>
                                    <input type="email" name="alternate_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['alternate_email'] ?? ''); ?>">
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
                                           value="<?php echo safeOutput($user_data['address_line_1'] ?? ''); ?>">
                                </div>

                                <!-- Address Line 2 -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 2
                                    </label>
                                    <input type="text" name="address_line_2"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['address_line_2'] ?? ''); ?>">
                                </div>

                                <!-- Suburb/City -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Suburb / City
                                    </label>
                                    <input type="text" name="suburb_city"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['suburb_city'] ?? ''); ?>">
                                </div>

                                <!-- Postcode -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Postcode
                                    </label>
                                    <input type="text" name="postcode"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['postcode'] ?? ''); ?>">
                                </div>

                                <!-- State -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        State
                                    </label>
                                    <input type="text" name="state"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['state'] ?? ''); ?>">
                                </div>

                                <!-- Country -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Country
                                    </label>
                                    <input type="text" name="country"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['country'] ?? ''); ?>">
                                </div>

                                <!-- Latitude -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Latitude (Lat)
                                    </label>
                                    <input type="text" name="latitude" id="latitude"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 35.7476667"
                                           value="<?php echo safeOutput($user_data['latitude'] ?? '0'); ?>">
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
                                           value="<?php echo safeOutput($user_data['longitude'] ?? '0'); ?>">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Enter longitude coordinate (e.g., 51.2539722)
                                    </p>
                                </div>
                            </div>
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
                                           value="<?php echo safeOutput($user_data['emergency_contact_name'] ?? ''); ?>">
                                </div>

                                <!-- Emergency Contact Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Number
                                    </label>
                                    <input type="tel" name="emergency_contact_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['emergency_contact_number'] ?? ''); ?>">
                                </div>

                                <!-- Emergency Contact Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Email
                                    </label>
                                    <input type="email" name="emergency_contact_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['emergency_contact_email'] ?? ''); ?>">
                                </div>

                                <!-- Emergency Contact Alternate Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Alternate Email
                                    </label>
                                    <input type="email" name="emergency_contact_alternate_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['emergency_contact_alternate_email'] ?? ''); ?>">
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
                                           value="<?php echo safeOutput($user_data['passport_number'] ?? ''); ?>">
                                </div>

                                <!-- Passport Nationality -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Nationality
                                    </label>
                                    <input type="text" name="passport_nationality"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['passport_nationality'] ?? ''); ?>">
                                </div>

                                <!-- Passport Expiry Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Expiry Date
                                    </label>
                                    <input type="date" name="passport_expiry_date"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $user_data['passport_expiry_date'] ?? ''; ?>">
                                </div>

                                <!-- Driver Licence Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Driver Licence Number
                                    </label>
                                    <input type="text" name="driver_licence_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['driver_licence_number'] ?? ''); ?>">
                                </div>

                                <!-- Frequent Flyer Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Frequent Flyer Number
                                    </label>
                                    <input type="text" name="frequent_flyer_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['frequent_flyer_number'] ?? ''); ?>">
                                </div>

                                <!-- Other Award Scheme Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Award Scheme Name
                                    </label>
                                    <input type="text" name="other_award_scheme_name"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['other_award_scheme_name'] ?? ''); ?>">
                                </div>

                                <!-- Other Award Scheme Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Award Scheme Number
                                    </label>
                                    <input type="text" name="other_award_scheme_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user_data['other_award_scheme_number'] ?? ''); ?>">
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
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user_data['individual_leave_entitlements'] ?? ''); ?></textarea>
                                </div>

                                <!-- Using Standalone Annual Leave -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="using_standalone_annual_leave" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo isset($user_data['using_standalone_annual_leave']) && $user_data['using_standalone_annual_leave'] ? 'checked' : ''; ?>>
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
                                           value="<?php echo intval($user_data['leave_days'] ?? 0); ?>">
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
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user_data['roles_groups'] ?? ''); ?></textarea>
                                </div>

                                <!-- Selected Roles/Groups -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Selected Roles/Groups
                                    </label>
                                    <textarea name="selected_roles_groups" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user_data['selected_roles_groups'] ?? ''); ?></textarea>
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
                            
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Role -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Role *
                                    </label>
                                    <select name="role_id" required
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <?php foreach ($available_roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo ($user_data['role_id'] ?? 2) == $role['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status *
                                    </label>
                                    <select name="status" required
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="active" <?php echo ($user_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($user_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <!-- Flight Crew -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="flight_crew" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo isset($user_data['flight_crew']) && $user_data['flight_crew'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Flight Crew Member
                                        </span>
                                    </label>
                                </div>

                                <!-- Receive Scheduled Emails -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="receive_scheduled_emails" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600"
                                               <?php echo isset($user_data['receive_scheduled_emails']) && $user_data['receive_scheduled_emails'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Receive Scheduled Emails
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex justify-end space-x-4">
                            <a href="index.php" 
                               class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-user-plus mr-2"></i>
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
        </div>
    </div>
</body>
</html>
