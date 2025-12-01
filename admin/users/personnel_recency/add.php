<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/users/personnel_recency/add.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'RecencyPersonnelItemID' => $_POST['recency_personnel_item_id'] ?? null,
        'RecencyItemID' => $_POST['recency_item_id'] ?? null,
        'PersonnelID' => $_POST['personnel_id'] ?? null,
        'TypeCode' => $_POST['type_code'] ?? null,
        'LastUpdated' => $_POST['last_updated'] ?? null,
        'Expires' => $_POST['expires'] ?? null,
        'Value' => $_POST['value'] ?? null,
        'ModifiedBy' => $current_user['id'],
        'ModifiedAt' => date('Y-m-d H:i:s'),
        'CFMaster' => isset($_POST['cf_master']) ? 1 : 0,
        'DocID' => $_POST['doc_id'] ?? null,
        'DocName' => $_POST['doc_name'] ?? null,
        'Name' => $_POST['name'] ?? null,
        'LastName' => $_POST['last_name'] ?? null,
        'FirstName' => $_POST['first_name'] ?? null,
        'HomeBaseID' => $_POST['home_base_id'] ?? null,
        'HomeDepartmentID' => $_POST['home_department_id'] ?? null,
        'PrimaryDepartmentName' => $_POST['primary_department_name'] ?? null,
        'BaseName' => $_POST['base_name'] ?? null,
        'BaseShortName' => $_POST['base_short_name'] ?? null,
        'IntegrationReferenceCode' => $_POST['integration_reference_code'] ?? null
    ];
    
    if (createPersonnelRecency($data)) {
        $message = 'Personnel recency record created successfully.';
        header('Location: index.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create personnel recency record.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Personnel Recency - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="/assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
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
                                <i class="fas fa-plus mr-2"></i>Add Personnel Recency
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Create a new personnel recency record
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Back Button -->
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Message Display -->
                <?php if (!empty($error)): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle mt-0.5 mr-2"></i>
                            <span class="text-sm"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-user-clock mr-2"></i>Personnel Recency Information
                        </h3>
                    </div>
                    
                    <form method="POST" class="p-6 space-y-6">
                        <!-- Personnel Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    First Name
                                </label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Last Name
                                </label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Name
                                </label>
                                <input type="text" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Recency Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="recency_personnel_item_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Recency Personnel Item ID
                                </label>
                                <input type="number" step="0.1" id="recency_personnel_item_id" name="recency_personnel_item_id" 
                                       value="<?php echo htmlspecialchars($_POST['recency_personnel_item_id'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="recency_item_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Recency Item ID
                                </label>
                                <input type="number" step="0.1" id="recency_item_id" name="recency_item_id" 
                                       value="<?php echo htmlspecialchars($_POST['recency_item_id'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="personnel_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Personnel ID
                                </label>
                                <input type="number" step="0.1" id="personnel_id" name="personnel_id" 
                                       value="<?php echo htmlspecialchars($_POST['personnel_id'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Type and Dates -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="type_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Type Code
                                </label>
                                <input type="text" id="type_code" name="type_code" 
                                       value="<?php echo htmlspecialchars($_POST['type_code'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="last_updated" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Last Updated
                                </label>
                                <input type="datetime-local" id="last_updated" name="last_updated" 
                                       value="<?php echo htmlspecialchars($_POST['last_updated'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="expires" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Expires
                                </label>
                                <input type="datetime-local" id="expires" name="expires" 
                                       value="<?php echo htmlspecialchars($_POST['expires'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Value and Document Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Value
                                </label>
                                <input type="number" step="0.01" id="value" name="value" 
                                       value="<?php echo htmlspecialchars($_POST['value'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="doc_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Document ID
                                </label>
                                <input type="number" step="0.1" id="doc_id" name="doc_id" 
                                       value="<?php echo htmlspecialchars($_POST['doc_id'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="doc_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Document Name
                                </label>
                                <input type="text" id="doc_name" name="doc_name" 
                                       value="<?php echo htmlspecialchars($_POST['doc_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Base and Department Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="home_base_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Home Base ID
                                </label>
                                <input type="number" step="0.1" id="home_base_id" name="home_base_id" 
                                       value="<?php echo htmlspecialchars($_POST['home_base_id'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="home_department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Home Department ID
                                </label>
                                <input type="number" step="0.1" id="home_department_id" name="home_department_id" 
                                       value="<?php echo htmlspecialchars($_POST['home_department_id'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="primary_department_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Primary Department Name
                                </label>
                                <input type="text" id="primary_department_name" name="primary_department_name" 
                                       value="<?php echo htmlspecialchars($_POST['primary_department_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Base Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="base_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Base Name
                                </label>
                                <input type="text" id="base_name" name="base_name" 
                                       value="<?php echo htmlspecialchars($_POST['base_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="base_short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Base Short Name
                                </label>
                                <input type="text" id="base_short_name" name="base_short_name" 
                                       value="<?php echo htmlspecialchars($_POST['base_short_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="integration_reference_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Integration Reference Code
                                </label>
                                <input type="text" id="integration_reference_code" name="integration_reference_code" 
                                       value="<?php echo htmlspecialchars($_POST['integration_reference_code'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- CF Master Checkbox -->
                        <div class="flex items-center">
                            <input type="checkbox" id="cf_master" name="cf_master" 
                                   <?php echo isset($_POST['cf_master']) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600">
                            <label for="cf_master" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                CF Master
                            </label>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Create Record
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Dark mode functionality
        function toggleDarkMode() {
            const html = document.documentElement;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun';
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Initialize dark mode
        function initDarkMode() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (savedDarkMode === 'true' || (savedDarkMode === null && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun';
            } else {
                document.documentElement.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);
    </script>
</body>
</html>
