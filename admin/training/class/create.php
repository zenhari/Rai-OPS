<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/class/create.php');

$current_user = getCurrentUser();
$db = getDBConnection();
$message = '';
$error = '';

// Get all users for instructor selection
$stmt = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE status = 'active' ORDER BY first_name, last_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles
$stmt = $db->query("SELECT id, display_name FROM roles WHERE is_active = 1 ORDER BY display_name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Days of week
$daysOfWeek = [
    'saturday' => 'Saturday',
    'sunday' => 'Sunday',
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $instructorId = !empty($_POST['instructor_id']) ? intval($_POST['instructor_id']) : null;
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $schedules = $_POST['schedules'] ?? [];
    $assignedUsers = $_POST['assigned_users'] ?? [];
    $assignedRoles = $_POST['assigned_roles'] ?? [];
    
    // Validate
    if (empty($name)) {
        $error = 'Class name is required.';
    } elseif (empty($schedules)) {
        $error = 'Please add at least one schedule (day and time).';
    } else {
        // Validate schedules
        foreach ($schedules as $schedule) {
            if (empty($schedule['day_of_week']) || empty($schedule['start_time']) || empty($schedule['end_time'])) {
                $error = 'All schedule fields (day, start time, end time) are required.';
                break;
            }
        }
    }
    
    if (empty($error)) {
        try {
            $db->beginTransaction();
            
            // Handle file upload
            $materialFile = null;
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] == UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/materials/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                
                $file = $_FILES['material_file'];
                $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'];
                $allowedExtensions = ['pdf', 'docx', 'doc'];
                
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (in_array($file['type'], $allowedTypes) || in_array($fileExtension, $allowedExtensions)) {
                    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        $materialFile = 'admin/training/class/materials/' . $fileName;
                    } else {
                        throw new Exception('Failed to upload material file.');
                    }
                } else {
                    throw new Exception('Invalid file type. Only PDF and DOCX files are allowed.');
                }
            }
            
            // Insert class
            $stmt = $db->prepare("INSERT INTO classes (name, duration, instructor_id, location, material_file, description, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $duration, $instructorId, $location, $materialFile, $description, $current_user['id']]);
            $classId = $db->lastInsertId();
            
            // Insert schedules
            $stmt = $db->prepare("INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($schedules as $schedule) {
                $startDate = !empty($schedule['start_date']) ? $schedule['start_date'] : null;
                $endDate = !empty($schedule['end_date']) ? $schedule['end_date'] : null;
                $stmt->execute([
                    $classId,
                    $schedule['day_of_week'],
                    $schedule['start_time'],
                    $schedule['end_time'],
                    $startDate,
                    $endDate
                ]);
            }
            
            // Insert assignments (users)
            if (!empty($assignedUsers)) {
                $stmt = $db->prepare("INSERT INTO class_assignments (class_id, user_id, assigned_by) VALUES (?, ?, ?)");
                foreach ($assignedUsers as $userId) {
                    $stmt->execute([$classId, intval($userId), $current_user['id']]);
                }
            }
            
            // Insert assignments (roles)
            if (!empty($assignedRoles)) {
                $stmt = $db->prepare("INSERT INTO class_assignments (class_id, role_id, assigned_by) VALUES (?, ?, ?)");
                foreach ($assignedRoles as $roleId) {
                    $stmt->execute([$classId, intval($roleId), $current_user['id']]);
                }
            }
            
            // Log activity
            logActivity('create', __FILE__, [
                'page_name' => 'Create Class',
                'section' => 'Training',
                'record_id' => $classId,
                'record_type' => 'class',
                'changes' => [['field' => 'name', 'old' => '', 'new' => $name]]
            ]);
            
            $db->commit();
            $message = "Class created successfully!";
            
            // Redirect after 2 seconds
            header('Refresh: 2; url=index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to create class: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Class - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Class</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Define a new training class</p>
                        </div>
                        <div>
                            <a href="index.php"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php if ($message): ?>
                    <div class="mb-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 text-green-800 dark:text-green-200 px-4 py-3 rounded">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 px-4 py-3 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <!-- Basic Information -->
                    <div class="mb-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Class Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Duration
                                </label>
                                <input type="text" name="duration" placeholder="e.g., 40 hours, 2 weeks"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Instructor
                                </label>
                                <select name="instructor_id"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Instructor</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['instructor_id']) && $_POST['instructor_id'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Location
                                </label>
                                <input type="text" name="location"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <textarea name="description" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Material File (PDF, DOCX)
                                </label>
                                <input type="file" name="material_file" accept=".pdf,.doc,.docx"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload class material (PDF or DOCX format)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Schedule</h2>
                            <button type="button" onclick="addSchedule()"
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Add Schedule
                            </button>
                        </div>
                        <div id="schedules-container">
                            <div class="schedule-item mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded-md">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Day of Week</label>
                                        <select name="schedules[0][day_of_week]" required
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                            <option value="">Select Day</option>
                                            <?php foreach ($daysOfWeek as $key => $label): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Time</label>
                                        <input type="time" name="schedules[0][start_time]" required
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Time</label>
                                        <input type="time" name="schedules[0][end_time]" required
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                                        <input type="date" name="schedules[0][start_date]"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                                        <input type="date" name="schedules[0][end_date]"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments -->
                    <div class="mb-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Assignments</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Users Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Assign to Users
                                </label>
                                <!-- Selected Users Badges -->
                                <div id="selected-users-badges" class="mb-3 flex flex-wrap gap-2 min-h-[40px] p-2 bg-gray-50 dark:bg-gray-700 rounded-md border border-gray-200 dark:border-gray-600">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 self-center">No users selected</span>
                                </div>
                                <!-- Search Box -->
                                <div class="mb-2">
                                    <input type="text" id="user-search" placeholder="Search users..." 
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <!-- Checkbox List -->
                                <div class="border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 max-h-64 overflow-y-auto p-2">
                                    <div class="mb-2 flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-600">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Select users:</span>
                                        <div class="flex gap-2">
                                            <button type="button" onclick="selectAllUsers()" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                                Select All
                                            </button>
                                            <button type="button" onclick="clearAllUsers()" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">
                                                Clear All
                                            </button>
                                        </div>
                                    </div>
                                    <div id="users-checkbox-list" class="space-y-1">
                                        <?php foreach ($users as $user): ?>
                                            <label class="user-checkbox-item flex items-center px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-600 rounded cursor-pointer">
                                                <input type="checkbox" name="assigned_users[]" value="<?php echo $user['id']; ?>" 
                                                       class="user-checkbox mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                       data-search="<?php echo strtolower(htmlspecialchars($user['name'])); ?>"
                                                       data-name="<?php echo htmlspecialchars($user['name']); ?>">
                                                <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Roles Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Assign to Roles
                                </label>
                                <!-- Selected Roles Badges -->
                                <div id="selected-roles-badges" class="mb-3 flex flex-wrap gap-2 min-h-[40px] p-2 bg-gray-50 dark:bg-gray-700 rounded-md border border-gray-200 dark:border-gray-600">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 self-center">No roles selected</span>
                                </div>
                                <!-- Search Box -->
                                <div class="mb-2">
                                    <input type="text" id="role-search" placeholder="Search roles..." 
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <!-- Checkbox List -->
                                <div class="border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 max-h-64 overflow-y-auto p-2">
                                    <div class="mb-2 flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-600">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Select roles:</span>
                                        <div class="flex gap-2">
                                            <button type="button" onclick="selectAllRoles()" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                                Select All
                                            </button>
                                            <button type="button" onclick="clearAllRoles()" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">
                                                Clear All
                                            </button>
                                        </div>
                                    </div>
                                    <div id="roles-checkbox-list" class="space-y-1">
                                        <?php foreach ($roles as $role): ?>
                                            <label class="role-checkbox-item flex items-center px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-600 rounded cursor-pointer">
                                                <input type="checkbox" name="assigned_roles[]" value="<?php echo $role['id']; ?>" 
                                                       class="role-checkbox mr-2 rounded border-gray-300 text-green-600 focus:ring-green-500 dark:border-gray-600 dark:bg-gray-700"
                                                       data-search="<?php echo strtolower(htmlspecialchars($role['display_name'])); ?>"
                                                       data-name="<?php echo htmlspecialchars($role['display_name']); ?>">
                                                <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($role['display_name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center justify-end space-x-3">
                        <a href="index.php"
                           class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>Create Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let scheduleIndex = 1;
        
        // User Search Functionality
        document.getElementById('user-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const checkboxes = document.querySelectorAll('.user-checkbox-item');
            
            checkboxes.forEach(item => {
                const checkbox = item.querySelector('.user-checkbox');
                const searchText = checkbox.getAttribute('data-search') || '';
                if (searchText.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Role Search Functionality
        document.getElementById('role-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const checkboxes = document.querySelectorAll('.role-checkbox-item');
            
            checkboxes.forEach(item => {
                const checkbox = item.querySelector('.role-checkbox');
                const searchText = checkbox.getAttribute('data-search') || '';
                if (searchText.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Update selected badges
        function updateSelectedBadges() {
            const userCheckboxes = document.querySelectorAll('.user-checkbox:checked');
            const roleCheckboxes = document.querySelectorAll('.role-checkbox:checked');
            const usersBadgesContainer = document.getElementById('selected-users-badges');
            const rolesBadgesContainer = document.getElementById('selected-roles-badges');
            
            // Update Users Badges
            usersBadgesContainer.innerHTML = '';
            
            if (userCheckboxes.length === 0) {
                usersBadgesContainer.innerHTML = '<span class="text-xs text-gray-500 dark:text-gray-400 self-center">No users selected</span>';
            } else {
                userCheckboxes.forEach(checkbox => {
                    const userId = checkbox.value;
                    const userName = checkbox.getAttribute('data-name');
                    const badge = document.createElement('span');
                    badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                    badge.innerHTML = `
                        ${userName}
                        <button type="button" onclick="removeUserBadge(${userId})" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    usersBadgesContainer.appendChild(badge);
                });
            }
            
            // Update Roles Badges
            rolesBadgesContainer.innerHTML = '';
            
            if (roleCheckboxes.length === 0) {
                rolesBadgesContainer.innerHTML = '<span class="text-xs text-gray-500 dark:text-gray-400 self-center">No roles selected</span>';
            } else {
                roleCheckboxes.forEach(checkbox => {
                    const roleId = checkbox.value;
                    const roleName = checkbox.getAttribute('data-name');
                    const badge = document.createElement('span');
                    badge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                    badge.innerHTML = `
                        ${roleName}
                        <button type="button" onclick="removeRoleBadge(${roleId})" class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    rolesBadgesContainer.appendChild(badge);
                });
            }
        }
        
        // Remove user badge
        function removeUserBadge(userId) {
            const checkbox = document.querySelector(`.user-checkbox[value="${userId}"]`);
            if (checkbox) {
                checkbox.checked = false;
                updateSelectedBadges();
            }
        }
        
        // Remove role badge
        function removeRoleBadge(roleId) {
            const checkbox = document.querySelector(`.role-checkbox[value="${roleId}"]`);
            if (checkbox) {
                checkbox.checked = false;
                updateSelectedBadges();
            }
        }
        
        // Select All Users
        function selectAllUsers() {
            const visibleCheckboxes = Array.from(document.querySelectorAll('.user-checkbox-item'))
                .filter(item => item.style.display !== 'none')
                .map(item => item.querySelector('.user-checkbox'));
            visibleCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedBadges();
        }
        
        // Clear All Users
        function clearAllUsers() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedBadges();
        }
        
        // Select All Roles
        function selectAllRoles() {
            const visibleCheckboxes = Array.from(document.querySelectorAll('.role-checkbox-item'))
                .filter(item => item.style.display !== 'none')
                .map(item => item.querySelector('.role-checkbox'));
            visibleCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedBadges();
        }
        
        // Clear All Roles
        function clearAllRoles() {
            document.querySelectorAll('.role-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedBadges();
        }
        
        // Add event listeners for checkbox changes
        document.addEventListener('DOMContentLoaded', function() {
            // User checkboxes
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedBadges);
            });
            
            // Role checkboxes
            document.querySelectorAll('.role-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedBadges);
            });
            
            // Initialize badges
            updateSelectedBadges();
        });
        
        function addSchedule() {
            const container = document.getElementById('schedules-container');
            const daysOfWeek = <?php echo json_encode($daysOfWeek); ?>;
            
            let optionsHtml = '<option value="">Select Day</option>';
            for (const [key, label] of Object.entries(daysOfWeek)) {
                optionsHtml += `<option value="${key}">${label}</option>`;
            }
            
            const scheduleHtml = `
                <div class="schedule-item mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded-md">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Schedule ${scheduleIndex + 1}</span>
                        <button type="button" onclick="removeSchedule(this)" class="text-red-600 hover:text-red-800 dark:text-red-400">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Day of Week</label>
                            <select name="schedules[${scheduleIndex}][day_of_week]" required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                ${optionsHtml}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Time</label>
                            <input type="time" name="schedules[${scheduleIndex}][start_time]" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Time</label>
                            <input type="time" name="schedules[${scheduleIndex}][end_time]" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                            <input type="date" name="schedules[${scheduleIndex}][start_date]"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                            <input type="date" name="schedules[${scheduleIndex}][end_date]"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', scheduleHtml);
            scheduleIndex++;
        }
        
        function removeSchedule(button) {
            button.closest('.schedule-item').remove();
        }
    </script>
</body>
</html>

