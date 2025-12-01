<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/delay_codes/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Path to delay.json file
$delayJsonPath = '../../../admin/flights/delay.json';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_codes') {
        $codes = [];
        
        // Process submitted codes
        if (isset($_POST['codes']) && is_array($_POST['codes'])) {
            foreach ($_POST['codes'] as $index => $codeData) {
                if (!empty($codeData['code']) && !empty($codeData['description'])) {
                    $codes[] = [
                        'code' => trim($codeData['code']),
                        'description' => trim($codeData['description'])
                    ];
                }
            }
        }
        
        // Save to JSON file
        if (file_put_contents($delayJsonPath, json_encode($codes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $message = 'Delay codes updated successfully.';
        } else {
            $error = 'Failed to update delay codes.';
        }
    }
    
    if ($action === 'add_code') {
        $newCode = trim($_POST['new_code'] ?? '');
        $newDescription = trim($_POST['new_description'] ?? '');
        
        if (!empty($newCode) && !empty($newDescription)) {
            // Load existing codes
            $existingCodes = [];
            if (file_exists($delayJsonPath)) {
                $existingCodes = json_decode(file_get_contents($delayJsonPath), true) ?: [];
            }
            
            // Check if code already exists
            $codeExists = false;
            foreach ($existingCodes as $code) {
                if ($code['code'] === $newCode) {
                    $codeExists = true;
                    break;
                }
            }
            
            if (!$codeExists) {
                $existingCodes[] = [
                    'code' => $newCode,
                    'description' => $newDescription
                ];
                
                if (file_put_contents($delayJsonPath, json_encode($existingCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $message = 'New delay code added successfully.';
                } else {
                    $error = 'Failed to add new delay code.';
                }
            } else {
                $error = 'Delay code already exists.';
            }
        } else {
            $error = 'Both code and description are required.';
        }
    }
    
    if ($action === 'edit_code') {
        $originalCode = $_POST['original_code'] ?? '';
        $newCode = trim($_POST['edit_code'] ?? '');
        $newDescription = trim($_POST['edit_description'] ?? '');
        
        if (!empty($originalCode) && !empty($newCode) && !empty($newDescription)) {
            // Load existing codes
            $existingCodes = [];
            if (file_exists($delayJsonPath)) {
                $existingCodes = json_decode(file_get_contents($delayJsonPath), true) ?: [];
            }
            
            // Check if new code already exists (and it's not the same as original)
            $codeExists = false;
            if ($newCode !== $originalCode) {
                foreach ($existingCodes as $code) {
                    if ($code['code'] === $newCode) {
                        $codeExists = true;
                        break;
                    }
                }
            }
            
            if (!$codeExists) {
                // Update the code
                foreach ($existingCodes as &$code) {
                    if ($code['code'] === $originalCode) {
                        $code['code'] = $newCode;
                        $code['description'] = $newDescription;
                        break;
                    }
                }
                
                if (file_put_contents($delayJsonPath, json_encode($existingCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $message = 'Delay code updated successfully.';
                } else {
                    $error = 'Failed to update delay code.';
                }
            } else {
                $error = 'Delay code already exists.';
            }
        } else {
            $error = 'All fields are required.';
        }
    }
    
    if ($action === 'delete_code') {
        $codeToDelete = $_POST['code_to_delete'] ?? '';
        
        if (!empty($codeToDelete)) {
            // Load existing codes
            $existingCodes = [];
            if (file_exists($delayJsonPath)) {
                $existingCodes = json_decode(file_get_contents($delayJsonPath), true) ?: [];
            }
            
            // Remove the code
            $existingCodes = array_filter($existingCodes, function($code) use ($codeToDelete) {
                return $code['code'] !== $codeToDelete;
            });
            
            // Re-index array
            $existingCodes = array_values($existingCodes);
            
            if (file_put_contents($delayJsonPath, json_encode($existingCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $message = 'Delay code deleted successfully.';
            } else {
                $error = 'Failed to delete delay code.';
            }
        }
    }
}

// Load existing delay codes
$delayCodes = [];
if (file_exists($delayJsonPath)) {
    $delayCodes = json_decode(file_get_contents($delayJsonPath), true) ?: [];
}

function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delay Codes Management - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Delay Codes Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage delay and diversion codes for flight operations</p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="openAddCodeModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add New Code
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
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

                <!-- Delay Codes Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Delay & Diversion Codes</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total codes: <?php echo count($delayCodes); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($delayCodes)): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No delay codes found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($delayCodes as $index => $code): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo safeOutput($code['code']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo safeOutput($code['description']); ?>
                                                    <?php if (isset($code['sub_codes']) && !empty($code['sub_codes'])): ?>
                                                        <div class="mt-2">
                                                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sub-codes:</div>
                                                            <div class="space-y-1">
                                                                <?php foreach ($code['sub_codes'] as $subCode): ?>
                                                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                                                        <span class="font-medium"><?php echo safeOutput($subCode['code']); ?>:</span>
                                                                        <?php echo safeOutput($subCode['description']); ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button onclick="editCode('<?php echo safeOutput($code['code']); ?>', '<?php echo safeOutput($code['description']); ?>')" 
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteCode('<?php echo safeOutput($code['code']); ?>')" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
    </div>

    <!-- Add Code Modal -->
    <div id="addCodeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Delay Code</h3>
                    <button onclick="closeAddCodeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_code">
                    
                    <div>
                        <label for="new_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Code *
                        </label>
                        <input type="text" id="new_code" name="new_code" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., 00-05">
                    </div>
                    
                    <div>
                        <label for="new_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description *
                        </label>
                        <textarea id="new_description" name="new_description" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Enter description for this delay code"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddCodeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Code
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Code Modal -->
    <div id="editCodeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Delay Code</h3>
                    <button onclick="closeEditCodeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit_code">
                    <input type="hidden" id="original_code" name="original_code">
                    
                    <div>
                        <label for="edit_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Code *
                        </label>
                        <input type="text" id="edit_code" name="edit_code" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., 00-05">
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description *
                        </label>
                        <textarea id="edit_description" name="edit_description" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Enter description for this delay code"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditCodeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Code
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Confirm Delete</h3>
                    <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Are you sure you want to delete this delay code? This action cannot be undone.
                    </p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="delete_code">
                    <input type="hidden" id="code_to_delete" name="code_to_delete">
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                            Delete Code
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddCodeModal() {
            document.getElementById('addCodeModal').classList.remove('hidden');
        }

        function closeAddCodeModal() {
            document.getElementById('addCodeModal').classList.add('hidden');
        }

        function editCode(code, description) {
            document.getElementById('original_code').value = code;
            document.getElementById('edit_code').value = code;
            document.getElementById('edit_description').value = description;
            document.getElementById('editCodeModal').classList.remove('hidden');
        }

        function closeEditCodeModal() {
            document.getElementById('editCodeModal').classList.add('hidden');
        }

        function deleteCode(code) {
            document.getElementById('code_to_delete').value = code;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addCodeModal');
            const editModal = document.getElementById('editCodeModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === addModal) {
                closeAddCodeModal();
            } else if (event.target === editModal) {
                closeEditCodeModal();
            } else if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
