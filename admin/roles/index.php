<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
if (!isLoggedIn() || !checkPageAccessEnhanced('admin/roles/index.php')) {
    header('Location: /login.php');
    exit();
}

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_role':
            $roleName = trim($_POST['role_name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#3B82F6');
            
            if (empty($roleName)) {
                $error = 'Role name is required.';
            } else {
                $roleData = [
                    'name' => $roleName,
                    'display_name' => $displayName ?: $roleName,
                    'description' => $description,
                    'color' => $color,
                    'can_manage_users' => isset($_POST['can_manage_users']) ? 1 : 0,
                    'can_manage_aircraft' => isset($_POST['can_manage_aircraft']) ? 1 : 0,
                    'can_manage_personnel' => isset($_POST['can_manage_personnel']) ? 1 : 0,
                    'can_manage_fleet' => isset($_POST['can_manage_fleet']) ? 1 : 0,
                    'can_view_reports' => isset($_POST['can_view_reports']) ? 1 : 0,
                    'can_manage_system' => isset($_POST['can_manage_system']) ? 1 : 0,
                    'can_manage_roles' => isset($_POST['can_manage_roles']) ? 1 : 0,
                    'is_system_role' => isset($_POST['is_system_role']) ? 1 : 0
                ];
                
                $result = createRole($roleData);
                if ($result['success']) {
                    $message = 'Role "' . htmlspecialchars($roleName) . '" added successfully.';
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'update_role':
            $roleId = $_POST['role_id'] ?? '';
            $roleName = trim($_POST['role_name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#3B82F6');
            
            if (empty($roleId) || empty($roleName)) {
                $error = 'Role ID and name are required.';
            } else {
                $roleData = [
                    'name' => $roleName,
                    'display_name' => $displayName ?: $roleName,
                    'description' => $description,
                    'color' => $color,
                    'can_manage_users' => isset($_POST['can_manage_users']) ? 1 : 0,
                    'can_manage_aircraft' => isset($_POST['can_manage_aircraft']) ? 1 : 0,
                    'can_manage_personnel' => isset($_POST['can_manage_personnel']) ? 1 : 0,
                    'can_manage_fleet' => isset($_POST['can_manage_fleet']) ? 1 : 0,
                    'can_view_reports' => isset($_POST['can_view_reports']) ? 1 : 0,
                    'can_manage_system' => isset($_POST['can_manage_system']) ? 1 : 0,
                    'can_manage_roles' => isset($_POST['can_manage_roles']) ? 1 : 0,
                    'is_system_role' => isset($_POST['is_system_role']) ? 1 : 0
                ];
                
                $result = updateRoleInTable($roleId, $roleData);
                if ($result['success']) {
                    $message = 'Role updated successfully.';
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'delete_role':
            $roleId = $_POST['role_id'] ?? '';
            if (empty($roleId)) {
                $error = 'Role ID is required.';
            } else {
                $result = deleteRoleFromTable($roleId);
                if ($result['success']) {
                    $message = 'Role deleted successfully.';
                } else {
                    $error = $result['message'];
                }
            }
            break;
    }
}

$roles = getAllRolesFromTable();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Manager - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Role Manager</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage user roles and permissions</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="openAddRoleModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                            <i class="fas fa-plus mr-2"></i>
                            Add Role
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-6">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Roles Table -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Available Roles</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage role definitions for the system</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Permissions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Users</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($roles)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No roles found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($roles as $role): ?>
                                    <?php $usageCount = getRoleUsageCountFromTable($role['id']); ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-3 h-3 rounded-full mr-3" style="background-color: <?php echo htmlspecialchars($role['color']); ?>"></div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($role['name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($role['description'] ?: 'No description'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                <?php if ($role['can_manage_users']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Users</span>
                                                <?php endif; ?>
                                                <?php if ($role['can_manage_aircraft']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Aircraft</span>
                                                <?php endif; ?>
                                                <?php if ($role['can_manage_personnel']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Personnel</span>
                                                <?php endif; ?>
                                                <?php if ($role['can_manage_fleet']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Fleet</span>
                                                <?php endif; ?>
                                                <?php if ($role['can_view_reports']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">Reports</span>
                                                <?php endif; ?>
                                                <?php if ($role['can_manage_system']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">System</span>
                                                <?php endif; ?>
                                                <?php if ($role['can_manage_roles']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">Roles</span>
                                                <?php endif; ?>
                                                <?php if ($role['is_system_role']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">System</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <?php echo $usageCount; ?> user<?php echo $usageCount !== 1 ? 's' : ''; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="openEditRoleModal(this)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                        data-role-id="<?php echo $role['id']; ?>"
                                                        data-role-name="<?php echo htmlspecialchars($role['name'], ENT_QUOTES); ?>"
                                                        data-display-name="<?php echo htmlspecialchars($role['display_name'], ENT_QUOTES); ?>"
                                                        data-description="<?php echo htmlspecialchars($role['description'], ENT_QUOTES); ?>"
                                                        data-color="<?php echo htmlspecialchars($role['color'], ENT_QUOTES); ?>"
                                                        data-role-data="<?php echo htmlspecialchars(json_encode($role), ENT_QUOTES); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($usageCount == 0 && !$role['is_system_role']): ?>
                                                    <button onclick="openDeleteRoleModal(this)" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                            data-role-id="<?php echo $role['id']; ?>"
                                                            data-role-name="<?php echo htmlspecialchars($role['display_name'], ENT_QUOTES); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500" title="<?php echo $role['is_system_role'] ? 'Cannot delete system role' : 'Cannot delete role in use'; ?>">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Role Modal -->
    <div id="addRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Role</h3>
                    <button onclick="closeAddRoleModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="add_role">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="role_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Role Name *
                            </label>
                            <input type="text" id="role_name" name="role_name" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., manager">
                        </div>
                        
                        <div>
                            <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Display Name
                            </label>
                            <input type="text" id="display_name" name="display_name"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., Manager">
                        </div>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Describe the role's responsibilities"></textarea>
                    </div>
                    
                    <div>
                        <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Color
                        </label>
                        <input type="color" id="color" name="color" value="#3B82F6"
                               class="w-20 h-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            Permissions
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="can_manage_users" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Users</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="can_manage_aircraft" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Aircraft</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="can_manage_personnel" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Personnel</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="can_manage_fleet" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Fleet</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="can_view_reports" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Reports</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="can_manage_system" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage System</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="can_manage_roles" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Roles</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_system_role" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">System Role</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddRoleModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div id="editRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Role</h3>
                    <button onclick="closeEditRoleModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" id="edit_role_id" name="role_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_role_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Role Name *
                            </label>
                            <input type="text" id="edit_role_name" name="role_name" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., manager">
                        </div>
                        
                        <div>
                            <label for="edit_display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Display Name
                            </label>
                            <input type="text" id="edit_display_name" name="display_name"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., Manager">
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="edit_description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Describe the role's responsibilities"></textarea>
                    </div>
                    
                    <div>
                        <label for="edit_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Color
                        </label>
                        <input type="color" id="edit_color" name="color" value="#3B82F6"
                               class="w-20 h-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            Permissions
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_manage_users" name="can_manage_users" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Users</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_manage_aircraft" name="can_manage_aircraft" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Aircraft</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_manage_personnel" name="can_manage_personnel" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Personnel</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_manage_fleet" name="can_manage_fleet" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Fleet</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_view_reports" name="can_view_reports" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Reports</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_manage_system" name="can_manage_system" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage System</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_can_manage_roles" name="can_manage_roles" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Roles</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit_is_system_role" name="is_system_role" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">System Role</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditRoleModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Role Modal -->
    <div id="deleteRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Role</h3>
                    <button onclick="closeDeleteRoleModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                        Are you sure you want to delete the role "<span id="delete_role_name" class="font-medium text-gray-900 dark:text-white"></span>"?
                        This action cannot be undone.
                    </p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" id="delete_role_id" name="role_id">
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteRoleModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                            Delete Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddRoleModal() {
            document.getElementById('addRoleModal').classList.remove('hidden');
        }

        function closeAddRoleModal() {
            document.getElementById('addRoleModal').classList.add('hidden');
            document.getElementById('role_name').value = '';
        }

        function openEditRoleModal(button) {
            try {
                const roleId = button.getAttribute('data-role-id');
                const roleName = button.getAttribute('data-role-name');
                const displayName = button.getAttribute('data-display-name');
                const description = button.getAttribute('data-description');
                const color = button.getAttribute('data-color');
                const roleDataStr = button.getAttribute('data-role-data');
                
                // Parse roleData
                const roleData = JSON.parse(roleDataStr);
                
                document.getElementById('edit_role_id').value = roleId;
                document.getElementById('edit_role_name').value = roleName || '';
                document.getElementById('edit_display_name').value = displayName || '';
                document.getElementById('edit_description').value = description || '';
                document.getElementById('edit_color').value = color || '#3B82F6';
                
                // Set checkboxes based on role data
                document.getElementById('edit_can_manage_users').checked = roleData.can_manage_users == 1;
                document.getElementById('edit_can_manage_aircraft').checked = roleData.can_manage_aircraft == 1;
                document.getElementById('edit_can_manage_personnel').checked = roleData.can_manage_personnel == 1;
                document.getElementById('edit_can_manage_fleet').checked = roleData.can_manage_fleet == 1;
                document.getElementById('edit_can_view_reports').checked = roleData.can_view_reports == 1;
                document.getElementById('edit_can_manage_system').checked = roleData.can_manage_system == 1;
                document.getElementById('edit_can_manage_roles').checked = roleData.can_manage_roles == 1;
                document.getElementById('edit_is_system_role').checked = roleData.is_system_role == 1;
                
                document.getElementById('editRoleModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error opening edit modal:', error);
                alert('Error opening edit modal. Please try again.');
            }
        }

        function closeEditRoleModal() {
            document.getElementById('editRoleModal').classList.add('hidden');
        }

        function openDeleteRoleModal(button) {
            try {
                const roleId = button.getAttribute('data-role-id');
                const roleName = button.getAttribute('data-role-name');
                
                document.getElementById('delete_role_name').textContent = roleName || 'Unknown Role';
                document.getElementById('delete_role_id').value = roleId;
                document.getElementById('deleteRoleModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error opening delete modal:', error);
                alert('Error opening delete modal. Please try again.');
            }
        }

        function closeDeleteRoleModal() {
            document.getElementById('deleteRoleModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addRoleModal');
            const editModal = document.getElementById('editRoleModal');
            const deleteModal = document.getElementById('deleteRoleModal');
            
            if (event.target === addModal) {
                closeAddRoleModal();
            }
            if (event.target === editModal) {
                closeEditRoleModal();
            }
            if (event.target === deleteModal) {
                closeDeleteRoleModal();
            }
        }
    </script>
</body>
</html>
