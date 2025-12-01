<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/crew/shift_code.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get shift code ID if editing
$shift_code_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit_mode = $shift_code_id > 0;

// Handle form submission (placeholder for future implementation)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_shift_code') {
        // TODO: Implement save logic when database table is ready
        $message = 'Shift code saved successfully.';
    }
}

// Get shift code data if editing (placeholder for future implementation)
$shift_code_data = null;
if ($is_edit_mode) {
    // TODO: Fetch shift code data from database when table is ready
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit_mode ? 'Edit Shift Code' : 'Add Shift Code'; ?> - Flight Management</title>
    <?php include '../../includes/head.php'; ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="p-4 sm:ml-64">
        <div class="p-4 mt-14">
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-code mr-2"></i>
                            <?php echo $is_edit_mode ? 'Edit Shift Code' : 'Add Shift Code'; ?>
                        </h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Manage shift codes for crew scheduling
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="<?php echo getAbsolutePath('admin/crew/shift_code.php'); ?>" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            <i class="fas fa-plus mr-2"></i>New Shift Code
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Shift Code Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                <form method="POST" action="" id="shiftCodeForm" class="p-6 space-y-6">
                    <input type="hidden" name="action" value="save_shift_code">
                    <?php if ($is_edit_mode): ?>
                        <input type="hidden" name="shift_code_id" value="<?php echo htmlspecialchars($shift_code_id); ?>">
                    <?php endif; ?>

                    <!-- Form Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Shift Code -->
                        <div>
                            <label for="shift_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Shift Code <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="shift_code" 
                                   name="shift_code" 
                                   value="<?php echo htmlspecialchars($shift_code_data['shift_code'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   required>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Description
                            </label>
                            <input type="text" 
                                   id="description" 
                                   name="description" 
                                   value="<?php echo htmlspecialchars($shift_code_data['description'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <!-- Start Time -->
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Start Time
                            </label>
                            <input type="time" 
                                   id="start_time" 
                                   name="start_time" 
                                   value="<?php echo htmlspecialchars($shift_code_data['start_time'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <!-- End Time -->
                        <div>
                            <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                End Time
                            </label>
                            <input type="time" 
                                   id="end_time" 
                                   name="end_time" 
                                   value="<?php echo htmlspecialchars($shift_code_data['end_time'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Status
                            </label>
                            <select id="status" 
                                    name="status" 
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="active" <?php echo ($shift_code_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($shift_code_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <!-- Color Code (Optional) -->
                        <div>
                            <label for="color_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Color Code
                            </label>
                            <input type="color" 
                                   id="color_code" 
                                   name="color_code" 
                                   value="<?php echo htmlspecialchars($shift_code_data['color_code'] ?? '#3B82F6'); ?>"
                                   class="w-full h-10 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes
                        </label>
                        <textarea id="notes" 
                                  name="notes" 
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($shift_code_data['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="<?php echo getAbsolutePath('admin/crew/shift_code.php'); ?>" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo $is_edit_mode ? 'Update Shift Code' : 'Save Shift Code'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>

