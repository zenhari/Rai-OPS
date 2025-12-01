<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/flight_roles.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_cockpit_role':
            $code = trim($_POST['code'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 100);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($code) || empty($label)) {
                $error = 'Code and label are required.';
            } else {
                if (addCockpitRole($code, $label, $sort_order, $is_active)) {
                    $message = 'Cockpit role added successfully.';
                } else {
                    $error = 'Failed to add cockpit role.';
                }
            }
            break;
            
        case 'add_cabin_role':
            $code = trim($_POST['code'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 100);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($code) || empty($label)) {
                $error = 'Code and label are required.';
            } else {
                if (addCabinRole($code, $label, $sort_order, $is_active)) {
                    $message = 'Cabin role added successfully.';
                } else {
                    $error = 'Failed to add cabin role.';
                }
            }
            break;
            
        case 'update_cockpit_role':
            $id = intval($_POST['id'] ?? 0);
            $code = trim($_POST['code'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 100);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($code) || empty($label)) {
                $error = 'Code and label are required.';
            } else {
                if (updateCockpitRole($id, $code, $label, $sort_order, $is_active)) {
                    $message = 'Cockpit role updated successfully.';
                } else {
                    $error = 'Failed to update cockpit role.';
                }
            }
            break;
            
        case 'update_cabin_role':
            $id = intval($_POST['id'] ?? 0);
            $code = trim($_POST['code'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 100);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($code) || empty($label)) {
                $error = 'Code and label are required.';
            } else {
                if (updateCabinRole($id, $code, $label, $sort_order, $is_active)) {
                    $message = 'Cabin role updated successfully.';
                } else {
                    $error = 'Failed to update cabin role.';
                }
            }
            break;
            
        case 'delete_cockpit_role':
            $id = intval($_POST['id'] ?? 0);
            if (deleteCockpitRole($id)) {
                $message = 'Cockpit role deleted successfully.';
            } else {
                $error = 'Failed to delete cockpit role.';
            }
            break;
            
        case 'delete_cabin_role':
            $id = intval($_POST['id'] ?? 0);
            if (deleteCabinRole($id)) {
                $message = 'Cabin role deleted successfully.';
            } else {
                $error = 'Failed to delete cabin role.';
            }
            break;
    }
}

// Get all roles
$cockpit_roles = getAllCockpitRoles();
$cabin_roles = getAllCabinRoles();

// Functions for cockpit roles
function addCockpitRole($code, $label, $sort_order, $is_active) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO cockpit_roles (code, label, sort_order, is_active) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$code, $label, $sort_order, $is_active]);
    } catch (Exception $e) {
        error_log("Error adding cockpit role: " . $e->getMessage());
        return false;
    }
}

function updateCockpitRole($id, $code, $label, $sort_order, $is_active) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE cockpit_roles SET code = ?, label = ?, sort_order = ?, is_active = ? WHERE id = ?");
        return $stmt->execute([$code, $label, $sort_order, $is_active, $id]);
    } catch (Exception $e) {
        error_log("Error updating cockpit role: " . $e->getMessage());
        return false;
    }
}

function deleteCockpitRole($id) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM cockpit_roles WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log("Error deleting cockpit role: " . $e->getMessage());
        return false;
    }
}

// Functions for cabin roles
function addCabinRole($code, $label, $sort_order, $is_active) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO cabin_roles (code, label, sort_order, is_active) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$code, $label, $sort_order, $is_active]);
    } catch (Exception $e) {
        error_log("Error adding cabin role: " . $e->getMessage());
        return false;
    }
}

function updateCabinRole($id, $code, $label, $sort_order, $is_active) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE cabin_roles SET code = ?, label = ?, sort_order = ?, is_active = ? WHERE id = ?");
        return $stmt->execute([$code, $label, $sort_order, $is_active, $id]);
    } catch (Exception $e) {
        error_log("Error updating cabin role: " . $e->getMessage());
        return false;
    }
}

function deleteCabinRole($id) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM cabin_roles WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log("Error deleting cabin role: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Roles - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Roles</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage cockpit and cabin roles</p>
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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Cockpit Roles -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Cockpit Roles</h2>
                                <button onclick="openAddCockpitModal()" 
                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Role
                                </button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Label</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($cockpit_roles)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No cockpit roles found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cockpit_roles as $role): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($role['code']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($role['label']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    <?php echo $role['sort_order']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $role['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                                        <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openEditCockpitModal(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['code']); ?>', '<?php echo htmlspecialchars($role['label']); ?>', <?php echo $role['sort_order']; ?>, <?php echo $role['is_active']; ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteCockpitRole(<?php echo $role['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Cabin Roles -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Cabin Roles</h2>
                                <button onclick="openAddCabinModal()" 
                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Role
                                </button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Label</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($cabin_roles)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No cabin roles found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cabin_roles as $role): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($role['code']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($role['label']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    <?php echo $role['sort_order']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $role['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                                        <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openEditCabinModal(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['code']); ?>', '<?php echo htmlspecialchars($role['label']); ?>', <?php echo $role['sort_order']; ?>, <?php echo $role['is_active']; ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteCabinRole(<?php echo $role['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
        </div>
    </div>

    <!-- Add Cockpit Role Modal -->
    <div id="addCockpitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Cockpit Role</h3>
                    <button onclick="closeAddCockpitModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_cockpit_role">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="code" required maxlength="10"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="label" required maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Sort Order
                        </label>
                        <input type="number" name="sort_order" value="100" min="0" max="999"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" checked
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Active
                        </label>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddCockpitModal()"
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

    <!-- Edit Cockpit Role Modal -->
    <div id="editCockpitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Cockpit Role</h3>
                    <button onclick="closeEditCockpitModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_cockpit_role">
                    <input type="hidden" id="edit_cockpit_id" name="id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_cockpit_code" name="code" required maxlength="10"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_cockpit_label" name="label" required maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Sort Order
                        </label>
                        <input type="number" id="edit_cockpit_sort_order" name="sort_order" min="0" max="999"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_cockpit_is_active" name="is_active"
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Active
                        </label>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditCockpitModal()"
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

    <!-- Add Cabin Role Modal -->
    <div id="addCabinModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Cabin Role</h3>
                    <button onclick="closeAddCabinModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_cabin_role">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="code" required maxlength="10"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="label" required maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Sort Order
                        </label>
                        <input type="number" name="sort_order" value="100" min="0" max="999"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Active
                        </label>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddCabinModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            Add Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Cabin Role Modal -->
    <div id="editCabinModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Cabin Role</h3>
                    <button onclick="closeEditCabinModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_cabin_role">
                    <input type="hidden" id="edit_cabin_id" name="id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_cabin_code" name="code" required maxlength="10"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_cabin_label" name="label" required maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Sort Order
                        </label>
                        <input type="number" id="edit_cabin_sort_order" name="sort_order" min="0" max="999"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_cabin_is_active" name="is_active"
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Active
                        </label>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditCabinModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Cockpit Role Functions
        function openAddCockpitModal() {
            document.getElementById('addCockpitModal').classList.remove('hidden');
        }

        function closeAddCockpitModal() {
            document.getElementById('addCockpitModal').classList.add('hidden');
        }

        function openEditCockpitModal(id, code, label, sortOrder, isActive) {
            document.getElementById('edit_cockpit_id').value = id;
            document.getElementById('edit_cockpit_code').value = code;
            document.getElementById('edit_cockpit_label').value = label;
            document.getElementById('edit_cockpit_sort_order').value = sortOrder;
            document.getElementById('edit_cockpit_is_active').checked = isActive == 1;
            document.getElementById('editCockpitModal').classList.remove('hidden');
        }

        function closeEditCockpitModal() {
            document.getElementById('editCockpitModal').classList.add('hidden');
        }

        function deleteCockpitRole(id) {
            if (confirm('Are you sure you want to delete this cockpit role?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_cockpit_role">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Cabin Role Functions
        function openAddCabinModal() {
            document.getElementById('addCabinModal').classList.remove('hidden');
        }

        function closeAddCabinModal() {
            document.getElementById('addCabinModal').classList.add('hidden');
        }

        function openEditCabinModal(id, code, label, sortOrder, isActive) {
            document.getElementById('edit_cabin_id').value = id;
            document.getElementById('edit_cabin_code').value = code;
            document.getElementById('edit_cabin_label').value = label;
            document.getElementById('edit_cabin_sort_order').value = sortOrder;
            document.getElementById('edit_cabin_is_active').checked = isActive == 1;
            document.getElementById('editCabinModal').classList.remove('hidden');
        }

        function closeEditCabinModal() {
            document.getElementById('editCabinModal').classList.add('hidden');
        }

        function deleteCabinRole(id) {
            if (confirm('Are you sure you want to delete this cabin role?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_cabin_role">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addCockpitModal = document.getElementById('addCockpitModal');
            const editCockpitModal = document.getElementById('editCockpitModal');
            const addCabinModal = document.getElementById('addCabinModal');
            const editCabinModal = document.getElementById('editCabinModal');
            
            if (event.target === addCockpitModal) {
                closeAddCockpitModal();
            } else if (event.target === editCockpitModal) {
                closeEditCockpitModal();
            } else if (event.target === addCabinModal) {
                closeAddCabinModal();
            } else if (event.target === editCabinModal) {
                closeEditCabinModal();
            }
        }
    </script>
</body>
</html>
