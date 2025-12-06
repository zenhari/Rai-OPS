<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/users/edit.php');

$user_id = $_GET['id'] ?? '';
if (empty($user_id)) {
    header('Location: /admin/users/index.php');
    exit();
}

$user = getUserById($user_id);
if (!$user) {
    header('Location: /admin/users/index.php');
    exit();
}

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Log page view
logActivity('view', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Management',
    'record_id' => $user_id,
    'record_type' => 'user'
]);

// Handle success messages from redirects
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'file_uploaded') {
        $message = 'File uploaded successfully.';
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'file_deleted') {
        $message = 'File deleted successfully.';
        $message_type = 'success';
    }
}

// Get available roles from database
$available_roles = getAllRolesFromTable();

// Get user's accessible pages
$user_role = null;
if (!empty($user['role_id'])) {
    foreach ($available_roles as $role) {
        if ($role['id'] == $user['role_id']) {
            $user_role = $role['name'];
            break;
        }
    }
}

$accessible_pages = [];
if ($user_role) {
    $all_permissions = getAllPagePermissions();
    foreach ($all_permissions as $permission) {
        $required_roles = json_decode($permission['required_roles'] ?? '[]', true) ?: [];
        
        // Check role-based access
        $has_role_access = in_array($user_role, $required_roles);
        
        // Check individual access
        $has_individual_access = hasIndividualAccess($permission['page_path'], $user_id);
        
        if ($has_role_access || $has_individual_access) {
            $accessible_pages[] = [
                'page_name' => $permission['page_name'],
                'page_path' => $permission['page_path'],
                'access_type' => $has_individual_access ? 'individual' : 'role'
            ];
        }
    }
}

// Helper function to safely output values
function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

// Helper function to handle date fields properly
function handleDateField($value) {
    return !empty($value) ? $value : null;
}

// Helper function to get file URL
function getFileUrl($path) {
    if (empty($path)) return null;
    // If path already starts with assets/, use as is
    if (strpos($path, 'assets/') === 0) {
        return '/' . $path;
    }
    // If path starts with personnel_docs/, prepend assets/
    if (strpos($path, 'personnel_docs/') === 0) {
        return '/assets/' . $path;
    }
    // Otherwise, assume it's in personnel_docs folder
    return '/assets/personnel_docs/' . $path;
}

// Helper function to get file icon
function getFileIcon($path) {
    if (empty($path)) return 'fa-file';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fa-file-word';
        case 'jpg':
        case 'jpeg':
        case 'png':
            return 'fa-file-image';
        default:
            return 'fa-file';
    }
}

// Get personnel documents data based on national_id
$personnel_docs = null;
if (!empty($user['national_id'])) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM personnel_docs WHERE national_id = ?");
    $stmt->execute([$user['national_id']]);
    $personnel_docs = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Endorsement helper functions (if not defined in config.php)
if (!function_exists('getUserEndorsements')) {
    function getUserEndorsements($userId) {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM user_endorsement WHERE user_id = ? ORDER BY aircraft_type, role_code");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user endorsements: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('saveUserEndorsements')) {
    function saveUserEndorsements($userId, $endorsements) {
        try {
            $db = getDBConnection();
            $db->beginTransaction();
            
            // Delete existing endorsements for this user
            $stmt = $db->prepare("DELETE FROM user_endorsement WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Insert new endorsements
            if (!empty($endorsements)) {
                $stmt = $db->prepare("INSERT INTO user_endorsement (user_id, aircraft_type, role_code, role_type) VALUES (?, ?, ?, ?)");
                foreach ($endorsements as $endorsement) {
                    $stmt->execute([
                        $userId,
                        $endorsement['aircraft_type'],
                        $endorsement['role_code'],
                        $endorsement['role_type'] // 'cockpit' or 'cabin'
                    ]);
                }
            }
            
            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error saving user endorsements: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getAllCabinRoles')) {
    function getAllCabinRoles() {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM cabin_roles ORDER BY sort_order, label");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting cabin roles: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getAllCockpitRoles')) {
    function getAllCockpitRoles() {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM cockpit_roles ORDER BY sort_order, label");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting cockpit roles: " . $e->getMessage());
            return [];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_user') {
        $user_data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'position' => trim($_POST['position'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'asic_number' => trim($_POST['asic_number'] ?? ''),
            'national_id' => trim($_POST['national_id'] ?? ''),
            'date_of_birth' => handleDateField($_POST['date_of_birth'] ?? ''),
            'employment_history' => trim($_POST['employment_history'] ?? ''),
            'probationary_date' => handleDateField($_POST['probationary_date'] ?? ''),
            'standalone_period_wp' => trim($_POST['standalone_period_wp'] ?? ''),
            'performance_review' => trim($_POST['performance_review'] ?? ''),
            'address_line_1' => trim($_POST['address_line_1'] ?? ''),
            'address_line_2' => trim($_POST['address_line_2'] ?? ''),
            'latitude' => trim($_POST['latitude'] ?? ''),
            'longitude' => trim($_POST['longitude'] ?? ''),
            'suburb_city' => trim($_POST['suburb_city'] ?? ''),
            'postcode' => trim($_POST['postcode'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'mobile' => trim($_POST['mobile'] ?? ''),
            'alternative_mobile' => trim($_POST['alternative_mobile'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
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
            'role_id' => intval($_POST['role_id'] ?? $user['role_id']),
            'status' => $_POST['status'] ?? 'active',
            'flight_crew' => isset($_POST['flight_crew']) ? 1 : 0
        ];
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadProfileImage($_FILES['profile_image'], $user_id);
            if ($uploadResult['success']) {
                // Delete old image if exists
                if (!empty($user['picture'])) {
                    deleteProfileImage($user['picture']);
                }
                $user_data['picture'] = $uploadResult['path'];
            } else {
                $message = 'Image upload failed: ' . $uploadResult['message'];
                $message_type = 'error';
            }
        }
        
        // Validate required fields
        if (empty($user_data['first_name']) || empty($user_data['last_name']) || 
            empty($user_data['position']) || empty($user_data['username'])) {
            $message = 'Please fill in all required fields.';
            $message_type = 'error';
        } else {
            if (updateUser($user_id, $user_data)) {
                $message = 'User updated successfully.';
                $message_type = 'success';
                $user = getUserById($user_id); // Refresh user data
            } else {
                // Get detailed error information
                $latestError = getLatestUserUpdateError();
                $errorDetails = '';
                
                if ($latestError) {
                    $errorData = json_decode($latestError, true);
                    if ($errorData) {
                        $errorDetails = '<div class="mt-2 text-xs">';
                        $errorDetails .= '<strong>Error Details:</strong><br>';
                        
                        if (isset($errorData['error'])) {
                            $errorDetails .= 'Error: ' . htmlspecialchars($errorData['error']) . '<br>';
                        }
                        
                        if (isset($errorData['exception_message'])) {
                            $errorDetails .= 'Exception: ' . htmlspecialchars($errorData['exception_message']) . '<br>';
                        }
                        
                        if (isset($errorData['pdo_error']) && is_array($errorData['pdo_error'])) {
                            $errorDetails .= 'PDO Error: ' . htmlspecialchars(implode(' - ', $errorData['pdo_error'])) . '<br>';
                        }
                        
                        if (isset($errorData['sql_query'])) {
                            $errorDetails .= 'SQL Query: ' . htmlspecialchars($errorData['sql_query']) . '<br>';
                        }
                        
                        if (isset($errorData['affected_rows'])) {
                            $errorDetails .= 'Affected Rows: ' . $errorData['affected_rows'] . '<br>';
                        }
                        
                        if (isset($errorData['server_info'])) {
                            $errorDetails .= 'Server: ' . htmlspecialchars($errorData['server_info']['server_software'] ?? 'Unknown') . '<br>';
                            $errorDetails .= 'PHP Version: ' . htmlspecialchars($errorData['server_info']['php_version'] ?? 'Unknown') . '<br>';
                        }
                        
                        $errorDetails .= '</div>';
                    }
                }
                
                $message = 'Failed to update user.' . $errorDetails;
                $message_type = 'error';
            }
        }
    } elseif ($action === 'change_password') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate password fields
        if (empty($new_password) || empty($confirm_password)) {
            $message = 'Please fill in all password fields.';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New password and confirm password do not match.';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters long.';
            $message_type = 'error';
        } else {
            if (changePassword($user_id, null, $new_password)) {
                $message = 'Password changed successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to change password.';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'delete_file') {
        $field = $_POST['field'] ?? '';
        $national_id = trim($user['national_id'] ?? '');
        
        if (!empty($national_id) && in_array($field, ['passport_path', 'idcard_path', 'degree_path', 'resume_path'])) {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT id, {$field} FROM personnel_docs WHERE national_id = ?");
            $stmt->execute([$national_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && !empty($row[$field])) {
                $filePath = $row[$field];
                // Handle both relative paths (personnel_docs/...) and full paths
                if (strpos($filePath, 'personnel_docs/') === 0) {
                    $fullPath = dirname(__DIR__, 2) . '/assets/' . $filePath;
                } else {
                    $fullPath = dirname(__DIR__, 2) . '/assets/personnel_docs/' . basename($filePath);
                }
                
                // Delete file from filesystem
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                
                // Update database
                $updateStmt = $db->prepare("UPDATE personnel_docs SET {$field} = NULL WHERE id = ?");
                if ($updateStmt->execute([$row['id']])) {
                    header('Location: edit.php?id=' . $user_id . '&msg=file_deleted');
                    exit();
                } else {
                    $message = 'Failed to update database.';
                    $message_type = 'error';
                }
            }
        }
    } elseif ($action === 'upload_file') {
        $field = $_POST['field'] ?? '';
        $national_id = trim($user['national_id'] ?? '');
        
        if (!empty($national_id) && in_array($field, ['passport_path', 'idcard_path', 'degree_path', 'resume_path']) && isset($_FILES['file'])) {
            $file = $_FILES['file'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowedExtensions)) {
                    $uploadDir = dirname(__DIR__, 2) . '/assets/personnel_docs/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = uniqid() . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $db = getDBConnection();
                        
                        // Check if record exists
                        $checkStmt = $db->prepare("SELECT id FROM personnel_docs WHERE national_id = ?");
                        $checkStmt->execute([$national_id]);
                        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $relativePath = 'personnel_docs/' . $fileName;
                        
                        if ($existing) {
                            // Update existing record
                            $updateStmt = $db->prepare("UPDATE personnel_docs SET {$field} = ? WHERE id = ?");
                            if ($updateStmt->execute([$relativePath, $existing['id']])) {
                                header('Location: edit.php?id=' . $user_id . '&msg=file_uploaded');
                                exit();
                            } else {
                                @unlink($targetPath);
                                $message = 'Failed to update database.';
                                $message_type = 'error';
                            }
                        } else {
                            // Create new record
                            $insertStmt = $db->prepare("INSERT INTO personnel_docs (national_id, {$field}) VALUES (?, ?)");
                            if ($insertStmt->execute([$national_id, $relativePath])) {
                                header('Location: edit.php?id=' . $user_id . '&msg=file_uploaded');
                                exit();
                            } else {
                                @unlink($targetPath);
                                $message = 'Failed to insert into database.';
                                $message_type = 'error';
                            }
                        }
                    } else {
                        $message = 'Failed to move uploaded file.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions);
                    $message_type = 'error';
                }
            } else {
                $message = 'File upload error: ' . $file['error'];
                $message_type = 'error';
            }
        }
    } elseif ($action === 'save_endorsements') {
        // Handle endorsement saving
        if ($user['flight_crew'] == 1) {
            $aircraft_type = trim($_POST['endorsement_aircraft_type'] ?? '');
            $selected_roles = $_POST['endorsement_roles'] ?? [];
            
            if (!empty($aircraft_type)) {
                // Get existing endorsements
                $existing_endorsements = getUserEndorsements($user_id);
                
                // Filter out endorsements for the selected aircraft type
                $other_endorsements = array_filter($existing_endorsements, function($e) use ($aircraft_type) {
                    return $e['aircraft_type'] != $aircraft_type;
                });
                
                // Build new endorsements array
                $endorsements = [];
                
                // Add endorsements from other aircraft types
                foreach ($other_endorsements as $endorsement) {
                    $endorsements[] = [
                        'aircraft_type' => $endorsement['aircraft_type'],
                        'role_code' => $endorsement['role_code'],
                        'role_type' => $endorsement['role_type']
                    ];
                }
                
                // Add new endorsements for selected aircraft type
                foreach ($selected_roles as $role_data) {
                    // Format: "type:code" (e.g., "cockpit:CPT" or "cabin:FA")
                    if (strpos($role_data, ':') !== false) {
                        list($role_type, $role_code) = explode(':', $role_data, 2);
                        if (in_array($role_type, ['cockpit', 'cabin']) && !empty($role_code)) {
                            $endorsements[] = [
                                'aircraft_type' => $aircraft_type,
                                'role_code' => $role_code,
                                'role_type' => $role_type
                            ];
                        }
                    }
                }
                
                if (saveUserEndorsements($user_id, $endorsements)) {
                    $message = 'Endorsements saved successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to save endorsements.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Please select an aircraft type.';
                $message_type = 'error';
            }
        }
    }
}

// Load endorsement data if user is flight crew
$aircraft_types = [];
$cabin_roles = [];
$cockpit_roles = [];
$existing_endorsements = [];
$endorsements_by_aircraft = [];

if ($user['flight_crew'] == 1) {
    $aircraft_types = getAircraftTypes();
    $cabin_roles = getAllCabinRoles();
    $cockpit_roles = getAllCockpitRoles();
    $existing_endorsements = getUserEndorsements($user_id);
    
    // Group existing endorsements by aircraft_type
    foreach ($existing_endorsements as $endorsement) {
        $aircraft = $endorsement['aircraft_type'];
        if (!isset($endorsements_by_aircraft[$aircraft])) {
            $endorsements_by_aircraft[$aircraft] = [];
        }
        $endorsements_by_aircraft[$aircraft][] = $endorsement['role_type'] . ':' . $endorsement['role_code'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo PROJECT_NAME; ?></title>
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }
        .dark .modal-content {
            background-color: #1f2937;
            border-color: #4b5563;
        }
        .close-button {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
        }
        .dark .close-button {
            color: #ccc;
        }
        .dark .close-button:hover,
        .dark .close-button:focus {
            color: white;
        }
        .file-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: contain;
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
                                <i class="fas fa-user-edit mr-2"></i>Edit User
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Edit user information for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Back Button -->
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Users
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Message Display -->
                <?php if (!empty($message)): ?>
                    <div class="mb-6 <?php echo $message_type == 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400'; ?> px-4 py-3 rounded-md">
                        <div class="flex">
                            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5 mr-2"></i>
                            <div class="text-sm">
                                <?php if ($message_type == 'error' && strpos($message, '<div') !== false): ?>
                                    <?php echo $message; ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($message); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit User Form -->
                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <input type="hidden" name="action" value="update_user">
                    <!-- Personal Information -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-user mr-2"></i>Personal Information
                                </h3>
                                <?php if (!empty($accessible_pages)): ?>
                                    <div class="flex flex-wrap gap-2 items-center">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Accessible Pages:</span>
                                        <?php foreach ($accessible_pages as $page): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $page['access_type'] == 'individual' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'; ?>" title="<?php echo htmlspecialchars($page['page_path']); ?>">
                                                <?php if ($page['access_type'] == 'individual'): ?>
                                                    <i class="fas fa-user-check mr-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-shield-alt mr-1"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($page['page_name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- First Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        First Name *
                                    </label>
                                    <input type="text" name="first_name" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['first_name']); ?>">
                                </div>

                                <!-- Last Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Last Name *
                                    </label>
                                    <input type="text" name="last_name" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['last_name']); ?>">
                                </div>

                                <!-- Profile Picture -->
                                <div class="md:col-span-2 lg:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Profile Picture
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <!-- Current Image -->
                                        <div class="flex-shrink-0">
                                            <?php if (!empty($user['picture']) && file_exists(__DIR__ . '/../../' . $user['picture'])): ?>
                                                <img src="<?php echo getProfileImageUrl($user['picture']); ?>" 
                                                     alt="Profile Picture" 
                                                     class="h-20 w-20 rounded-full object-cover border-2 border-gray-300 dark:border-gray-600">
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
                                           value="<?php echo safeOutput($user['position']); ?>">
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Username *
                                    </label>
                                    <input type="text" name="username" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['username']); ?>">
                                </div>

                                <!-- ASIC Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ASIC Number
                                    </label>
                                    <input type="text" name="asic_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['asic_number']); ?>">
                                </div>

                                <!-- National ID -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        National ID
                                    </label>
                                    <input type="text" name="national_id"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['national_id'] ?? ''); ?>"
                                           placeholder="Enter National ID">
                                </div>

                                <!-- Date of Birth -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Date of Birth
                                    </label>
                                    <input type="date" name="date_of_birth"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $user['date_of_birth']; ?>">
                                </div>
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
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Employment History -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Employment History
                                    </label>
                                    <textarea name="employment_history" rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user['employment_history']); ?></textarea>
                                </div>

                                <!-- Probationary Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Probationary Date
                                    </label>
                                    <input type="date" name="probationary_date"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $user['probationary_date']; ?>">
                                </div>

                                <!-- Standalone Period WP -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Standalone Period WP
                                    </label>
                                    <input type="text" name="standalone_period_wp"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['standalone_period_wp']); ?>">
                                </div>

                                <!-- Performance Review -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Performance Review
                                    </label>
                                    <textarea name="performance_review" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user['performance_review']); ?></textarea>
                                </div>
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
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Address Line 1 -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 1
                                    </label>
                                    <input type="text" name="address_line_1"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['address_line_1']); ?>">
                                </div>

                                <!-- Address Line 2 -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Address Line 2
                                    </label>
                                    <input type="text" name="address_line_2"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['address_line_2']); ?>">
                                </div>

                                <!-- Suburb/City -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Suburb / City
                                    </label>
                                    <input type="text" name="suburb_city"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['suburb_city']); ?>">
                                </div>

                                <!-- Postcode -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Postcode
                                    </label>
                                    <input type="text" name="postcode"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['postcode']); ?>">
                                </div>

                                <!-- State -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        State
                                    </label>
                                    <input type="text" name="state"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['state']); ?>">
                                </div>

                                <!-- Country -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Country
                                    </label>
                                    <input type="text" name="country"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['country']); ?>">
                                </div>

                                <!-- Latitude -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Latitude (Lat)
                                    </label>
                                    <input type="text" name="latitude" id="latitude"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 35.7476667"
                                           value="<?php echo safeOutput($user['latitude'] ?? ''); ?>">
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
                                           value="<?php echo safeOutput($user['longitude'] ?? ''); ?>">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Enter longitude coordinate (e.g., 51.2539722)
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Map Display -->
                            <?php if (!empty($user['latitude']) && !empty($user['longitude'])): 
                                $lat = floatval($user['latitude']);
                                $lng = floatval($user['longitude']);
                                
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
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-phone mr-2"></i>Contact Information
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Phone
                                    </label>
                                    <input type="tel" name="phone"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['phone']); ?>">
                                </div>

                                <!-- Mobile -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Mobile
                                    </label>
                                    <input type="tel" name="mobile"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['mobile']); ?>">
                                </div>

                                <!-- Alternative Mobile -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Alternative Mobile
                                    </label>
                                    <input type="tel" name="alternative_mobile"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['alternative_mobile']); ?>">
                                </div>

                                <!-- Fax -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Fax
                                    </label>
                                    <input type="tel" name="fax"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['fax']); ?>">
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Email
                                    </label>
                                    <input type="email" name="email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['email']); ?>">
                                </div>

                                <!-- Alternate Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Alternate Email
                                    </label>
                                    <input type="email" name="alternate_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['alternate_email']); ?>">
                                </div>
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
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Emergency Contact Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Name
                                    </label>
                                    <input type="text" name="emergency_contact_name"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['emergency_contact_name']); ?>">
                                </div>

                                <!-- Emergency Contact Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Number
                                    </label>
                                    <input type="tel" name="emergency_contact_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['emergency_contact_number']); ?>">
                                </div>

                                <!-- Emergency Contact Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Email
                                    </label>
                                    <input type="email" name="emergency_contact_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['emergency_contact_email']); ?>">
                                </div>

                                <!-- Emergency Contact Alternate Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Emergency Contact Alternate Email
                                    </label>
                                    <input type="email" name="emergency_contact_alternate_email"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['emergency_contact_alternate_email']); ?>">
                                </div>
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
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Passport Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Number
                                    </label>
                                    <input type="text" name="passport_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['passport_number']); ?>">
                                </div>

                                <!-- Passport Nationality -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Nationality
                                    </label>
                                    <input type="text" name="passport_nationality"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['passport_nationality']); ?>">
                                </div>

                                <!-- Passport Expiry Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passport Expiry Date
                                    </label>
                                    <input type="date" name="passport_expiry_date"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo $user['passport_expiry_date']; ?>">
                                </div>

                                <!-- Driver Licence Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Driver Licence Number
                                    </label>
                                    <input type="text" name="driver_licence_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['driver_licence_number']); ?>">
                                </div>

                                <!-- Frequent Flyer Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Frequent Flyer Number
                                    </label>
                                    <input type="text" name="frequent_flyer_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['frequent_flyer_number']); ?>">
                                </div>

                                <!-- Other Award Scheme Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Award Scheme Name
                                    </label>
                                    <input type="text" name="other_award_scheme_name"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['other_award_scheme_name']); ?>">
                                </div>

                                <!-- Other Award Scheme Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Award Scheme Number
                                    </label>
                                    <input type="text" name="other_award_scheme_number"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($user['other_award_scheme_number']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personnel Documents -->
                    <?php if (!empty($user['national_id'])): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-file-alt mr-2"></i>Personnel Documents
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage documents for National ID: <?php echo htmlspecialchars($user['national_id']); ?>
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <?php
                                $fileFields = [
                                    'passport_path' => 'Passport',
                                    'idcard_path' => 'ID Card',
                                    'degree_path' => 'Degree',
                                    'resume_path' => 'Resume'
                                ];
                                
                                foreach ($fileFields as $field => $label):
                                    $filePath = $personnel_docs[$field] ?? null;
                                    $fileUrl = $filePath ? getFileUrl($filePath) : null;
                                    $fileIcon = getFileIcon($filePath);
                                    $isImage = $filePath && in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']);
                                ?>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                                            <?php echo htmlspecialchars($label); ?>
                                        </h4>
                                        
                                        <?php if ($fileUrl): ?>
                                            <div class="space-y-2">
                                                <?php if ($isImage): ?>
                                                    <img src="<?php echo htmlspecialchars($fileUrl); ?>" 
                                                         alt="<?php echo htmlspecialchars($label); ?>" 
                                                         class="w-full h-32 object-cover rounded cursor-pointer"
                                                         onclick="openImageModal('<?php echo htmlspecialchars($fileUrl); ?>')">
                                                <?php else: ?>
                                                    <div class="w-full h-32 bg-gray-100 dark:bg-gray-700 rounded flex items-center justify-center">
                                                        <i class="fas <?php echo htmlspecialchars($fileIcon); ?> text-4xl text-gray-400 dark:text-gray-500"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="flex flex-col gap-2">
                                                    <a href="<?php echo htmlspecialchars($fileUrl); ?>" 
                                                       target="_blank" 
                                                       class="text-center px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                                        <i class="fas fa-eye mr-1"></i>View
                                                    </a>
                                                    <button onclick="confirmDeleteFile('<?php echo htmlspecialchars($field); ?>')" 
                                                            class="px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                                                        <i class="fas fa-trash mr-1"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-file-upload text-3xl text-gray-400 dark:text-gray-500 mb-2"></i>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">No file uploaded</p>
                                                <button onclick="openUploadModal('<?php echo htmlspecialchars($field); ?>', '<?php echo htmlspecialchars($label); ?>')" 
                                                        class="px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                                    <i class="fas fa-upload mr-1"></i>Upload
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <p class="text-sm text-yellow-700 dark:text-yellow-400">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Please set a National ID to manage personnel documents.
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Leave and Entitlements -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-calendar-alt mr-2"></i>Leave and Entitlements
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Individual Leave Entitlements -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Individual Leave Entitlements
                                    </label>
                                    <textarea name="individual_leave_entitlements" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user['individual_leave_entitlements']); ?></textarea>
                                </div>

                                <!-- Using Standalone Annual Leave -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="using_standalone_annual_leave" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                               <?php echo $user['using_standalone_annual_leave'] ? 'checked' : ''; ?>>
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
                                           value="<?php echo $user['leave_days']; ?>">
                                </div>
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
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Roles/Groups -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Roles/Groups
                                    </label>
                                    <textarea name="roles_groups" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user['roles_groups']); ?></textarea>
                                </div>

                                <!-- Selected Roles/Groups -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Selected Roles/Groups
                                    </label>
                                    <textarea name="selected_roles_groups" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo safeOutput($user['selected_roles_groups']); ?></textarea>
                                </div>
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
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Role -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Role *
                                    </label>
                                    <select name="role_id" required
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <?php foreach ($available_roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo ($user['role_id'] ?? 2) == $role['id'] ? 'selected' : ''; ?>>
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
                                        <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>

                                <!-- Flight Crew -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="flight_crew" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                               <?php echo $user['flight_crew'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Flight Crew Member
                                        </span>
                                    </label>
                                </div>

                                <!-- Receive Scheduled Emails -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="receive_scheduled_emails" value="1"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                               <?php echo $user['receive_scheduled_emails'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Receive Scheduled Emails
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Endorsement Section (Only for Flight Crew) -->
                    <?php if ($user['flight_crew'] == 1): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-certificate mr-2"></i>Endorsement
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage aircraft type endorsements and role assignments
                            </p>
                        </div>
                        <div class="p-6">
                            <form method="POST" id="endorsementForm" class="space-y-6">
                                <input type="hidden" name="action" value="save_endorsements">
                                
                                <!-- Aircraft Type Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Type *
                                    </label>
                                    <select name="endorsement_aircraft_type" id="endorsement_aircraft_type" required
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">-- Select Aircraft Type --</option>
                                        <?php 
                                        $selected_aircraft = !empty($existing_endorsements) ? $existing_endorsements[0]['aircraft_type'] : '';
                                        foreach ($aircraft_types as $aircraft_type): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($aircraft_type); ?>" 
                                                    <?php echo $selected_aircraft == $aircraft_type ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($aircraft_type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Role Selection (shown after aircraft type is selected) -->
                                <div id="role_selection_container" style="<?php echo empty($selected_aircraft) ? 'display: none;' : ''; ?>">
                                    <!-- Cockpit Roles -->
                                    <div class="mb-6">
                                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                                            <i class="fas fa-plane mr-2"></i>Cockpit Roles
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <?php 
                                            foreach ($cockpit_roles as $role): 
                                                $role_key = 'cockpit:' . $role['code'];
                                                $is_checked = false;
                                                if (!empty($selected_aircraft) && isset($endorsements_by_aircraft[$selected_aircraft])) {
                                                    $is_checked = in_array($role_key, $endorsements_by_aircraft[$selected_aircraft]);
                                                }
                                            ?>
                                                <label class="flex items-center p-2 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                    <input type="checkbox" 
                                                           name="endorsement_roles[]" 
                                                           value="<?php echo htmlspecialchars($role_key); ?>"
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                        <strong><?php echo htmlspecialchars($role['code']); ?></strong>
                                                        <?php if (!empty($role['label'])): ?>
                                                            - <?php echo htmlspecialchars($role['label']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Cabin Roles -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                                            <i class="fas fa-users mr-2"></i>Cabin Roles
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <?php 
                                            foreach ($cabin_roles as $role): 
                                                $role_key = 'cabin:' . $role['code'];
                                                $is_checked = false;
                                                if (!empty($selected_aircraft) && isset($endorsements_by_aircraft[$selected_aircraft])) {
                                                    $is_checked = in_array($role_key, $endorsements_by_aircraft[$selected_aircraft]);
                                                }
                                            ?>
                                                <label class="flex items-center p-2 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                    <input type="checkbox" 
                                                           name="endorsement_roles[]" 
                                                           value="<?php echo htmlspecialchars($role_key); ?>"
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                        <strong><?php echo htmlspecialchars($role['code']); ?></strong>
                                                        <?php if (!empty($role['label'])): ?>
                                                            - <?php echo htmlspecialchars($role['label']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Save Button for Endorsements -->
                                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <button type="submit"
                                                class="px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                            <i class="fas fa-save mr-2"></i>Save Endorsements
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-4">
                        <a href="index.php" 
                           class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>Update User
                        </button>
                    </div>
                </form>

                <!-- Password Change Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Change the password for this user
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- New Password -->
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        New Password *
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="new_password" name="new_password" required
                                               class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="Enter new password" minlength="6">
                                        <button type="button" onclick="togglePassword('new_password', 'new_password_icon')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i id="new_password_icon" class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Confirm Password *
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="confirm_password" name="confirm_password" required
                                               class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="Confirm new password" minlength="6">
                                        <button type="button" onclick="togglePassword('confirm_password', 'confirm_password_icon')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i id="confirm_password_icon" class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                    <i class="fas fa-key mr-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeDeleteModal()">&times;</span>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Confirm File Deletion</h3>
            <p class="text-gray-700 dark:text-gray-300 mb-6">Are you sure you want to delete this file? This action cannot be undone.</p>
            <form id="deleteForm" method="POST" action="">
                <input type="hidden" name="action" value="delete_file">
                <input type="hidden" name="field" id="deleteField">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 rounded-md transition-colors duration-200">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeUploadModal()">&times;</span>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Upload File</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4" id="uploadLabel">Upload file</p>
            <form id="uploadForm" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="field" id="uploadField">
                <div class="mb-4">
                    <label for="fileInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select File</label>
                    <input type="file" name="file" id="fileInput" required
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Allowed types: JPG, PNG, PDF, DOC, DOCX</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-md transition-colors duration-200">
                        <i class="fas fa-upload mr-2"></i>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="imagePreviewModal" class="modal">
        <div class="modal-content max-w-4xl">
            <span class="close-button" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="" alt="Document Image" class="max-w-full h-auto mx-auto">
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const passwordIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswordMatch() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('input', validatePasswordMatch);
            confirmPassword.addEventListener('input', validatePasswordMatch);
        });

        // Personnel Documents Modals
        function confirmDeleteFile(field) {
            document.getElementById('deleteField').value = field;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            document.getElementById('deleteField').value = '';
        }

        function openUploadModal(field, label) {
            document.getElementById('uploadField').value = field;
            document.getElementById('uploadLabel').textContent = 'Upload ' + label;
            document.getElementById('fileInput').value = '';
            document.getElementById('uploadModal').classList.add('show');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('show');
            document.getElementById('uploadField').value = '';
            document.getElementById('fileInput').value = '';
        }

        function openImageModal(imageUrl) {
            document.getElementById('modalImage').src = imageUrl;
            document.getElementById('imagePreviewModal').classList.add('show');
        }

        function closeImageModal() {
            document.getElementById('imagePreviewModal').classList.remove('show');
            document.getElementById('modalImage').src = '';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const uploadModal = document.getElementById('uploadModal');
            const imageModal = document.getElementById('imagePreviewModal');
            
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
            if (event.target == uploadModal) {
                closeUploadModal();
            }
            if (event.target == imageModal) {
                closeImageModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
                closeUploadModal();
                closeImageModal();
            }
        });

        // Endorsement functionality
        <?php if ($user['flight_crew'] == 1): ?>
        const endorsementAircraftType = document.getElementById('endorsement_aircraft_type');
        const roleSelectionContainer = document.getElementById('role_selection_container');
        
        // Endorsements data from PHP
        const endorsementsByAircraft = <?php echo json_encode($endorsements_by_aircraft ?? []); ?>;
        
        if (endorsementAircraftType && roleSelectionContainer) {
            // Show/hide role selection based on aircraft type selection
            function updateRoleSelection() {
                const selectedAircraft = endorsementAircraftType.value;
                
                if (selectedAircraft) {
                    roleSelectionContainer.style.display = 'block';
                    
                    // Get existing endorsements for this aircraft type
                    const existingRoles = endorsementsByAircraft[selectedAircraft] || [];
                    
                    // Update all checkboxes
                    const checkboxes = roleSelectionContainer.querySelectorAll('input[type="checkbox"][name="endorsement_roles[]"]');
                    checkboxes.forEach(function(checkbox) {
                        const roleValue = checkbox.value;
                        checkbox.checked = existingRoles.includes(roleValue);
                    });
                } else {
                    roleSelectionContainer.style.display = 'none';
                    
                    // Uncheck all checkboxes
                    const checkboxes = roleSelectionContainer.querySelectorAll('input[type="checkbox"][name="endorsement_roles[]"]');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = false;
                    });
                }
            }
            
            // Listen for aircraft type changes
            endorsementAircraftType.addEventListener('change', updateRoleSelection);
            
            // Initialize on page load
            updateRoleSelection();
        }
        <?php endif; ?>
    </script>
</body>
</html>
