<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/settings/maintenance_mode.php');

$current_user = getCurrentUser();
$isSuperAdmin = ($current_user['role_name'] ?? '') === 'super_admin';
$message = '';
$error = '';

// Ensure maintenance_mode table exists
try {
    $pdo = getDBConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `maintenance_mode` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `is_active` TINYINT(1) DEFAULT 0 COMMENT '1 = Active, 0 = Inactive',
        `end_datetime` DATETIME DEFAULT NULL COMMENT 'End date and time for maintenance',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maintenance mode settings'");
    
    // Check if record exists, if not create one
    $stmt = $pdo->query("SELECT id FROM maintenance_mode ORDER BY id DESC LIMIT 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO maintenance_mode (is_active, end_datetime) VALUES (0, NULL)");
    }
} catch (PDOException $e) {
    $error = 'Failed to initialize maintenance mode table: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_maintenance_mode') {
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $endDatetime = !empty($_POST['end_datetime']) ? $_POST['end_datetime'] : null;
        
        if ($isActive && empty($endDatetime)) {
            $error = 'End date and time is required when activating maintenance mode.';
        } else {
            if (updateMaintenanceMode($isActive, $endDatetime)) {
                $message = 'Maintenance mode settings updated successfully.';
                
                // Log activity
                logActivity('update', 'admin/settings/maintenance_mode.php', [
                    'page_name' => 'Maintenance Mode',
                    'section' => 'Settings',
                    'action' => $isActive ? 'Activated' : 'Deactivated',
                    'end_datetime' => $endDatetime
                ]);
            } else {
                $error = 'Failed to update maintenance mode settings.';
            }
        }
    }
}

// Get current maintenance mode settings
$maintenanceMode = getMaintenanceMode();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'roboto': ['Roboto', 'sans-serif'],
                    }
                }
            }
        }
        
        // Apply dark mode immediately to prevent flash
        (function() {
            const html = document.documentElement;
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const savedTheme = localStorage.getItem('theme');
            
            if (savedTheme === 'dark' || savedTheme === 'light') {
                if (savedTheme === 'dark') {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            } else {
                if (systemPrefersDark) {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            }
        })();
    </script>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Maintenance Mode</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Configure system maintenance window and countdown</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-md">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-md">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Maintenance Mode Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <form method="POST" action="" id="maintenanceForm">
                        <input type="hidden" name="action" value="update_maintenance_mode">
                        
                        <!-- Active/Inactive Toggle -->
                        <div class="mb-6">
                            <label class="flex items-center cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" 
                                           name="is_active" 
                                           id="is_active" 
                                           value="1"
                                           <?php echo ($maintenanceMode['is_active'] ?? 0) ? 'checked' : ''; ?>
                                           class="sr-only peer"
                                           onchange="toggleMaintenanceMode()">
                                    <div class="block bg-gray-300 dark:bg-gray-600 w-14 h-8 rounded-full transition-colors duration-200 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-500"></div>
                                    <div class="dot absolute left-1 top-1 bg-white dark:bg-gray-200 w-6 h-6 rounded-full transition-transform duration-200 peer-checked:translate-x-6"></div>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        Maintenance Mode
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400" id="statusText">
                                        <?php echo ($maintenanceMode['is_active'] ?? 0) ? 'Currently Active' : 'Currently Inactive'; ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                        
                        <!-- End Date and Time -->
                        <div class="mb-6" id="datetimeSection">
                            <label for="end_datetime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                End Date and Time <span class="text-red-500">*</span>
                            </label>
                            <input type="datetime-local" 
                                   id="end_datetime" 
                                   name="end_datetime" 
                                   value="<?php echo $maintenanceMode['end_datetime'] ? date('Y-m-d\TH:i', strtotime($maintenanceMode['end_datetime'])) : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   required>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Select the date and time when maintenance will end. Users will see a countdown timer.
                            </p>
                        </div>
                        
                        <!-- Preview -->
                        <?php if ($maintenanceMode['is_active'] ?? 0): ?>
                        <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-1 mr-2"></i>
                                <div>
                                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Maintenance mode is currently active
                                    </p>
                                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                        All non-super_admin users will be redirected to the maintenance page.
                                    </p>
                                    <?php if ($maintenanceMode['end_datetime']): ?>
                                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                        End time: <?php echo date('Y-m-d H:i:s', strtotime($maintenanceMode['end_datetime'])); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Submit Button -->
                        <div class="flex items-center justify-end space-x-3">
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Information Card -->
                <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-200 mb-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        How Maintenance Mode Works
                    </h3>
                    <ul class="space-y-2 text-sm text-blue-800 dark:text-blue-300">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1"></i>
                            <span>When active, all users except <strong>super_admin</strong> will be redirected to a maintenance page.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1"></i>
                            <span><strong>super_admin</strong> users can access all pages normally and see maintenance status.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1"></i>
                            <span>The maintenance page displays a countdown timer showing time remaining until maintenance ends.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1"></i>
                            <span>Maintenance mode automatically deactivates when the end date/time is reached.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1"></i>
                            <span>Contact information for Mehdi Zenhari (Developer and Designer) is displayed on the maintenance page.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Dark Mode Detection and Application
        function initDarkMode() {
            const html = document.documentElement;
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Check if user has a saved preference
            const savedTheme = localStorage.getItem('theme');
            
            if (savedTheme === 'dark' || savedTheme === 'light') {
                // Use saved preference
                if (savedTheme === 'dark') {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            } else {
                // Use system preference
                if (systemPrefersDark) {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            }
        }
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            const savedTheme = localStorage.getItem('theme');
            // Only apply system preference if user hasn't manually set a theme
            if (!savedTheme) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
        
        // Initialize dark mode on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            toggleMaintenanceMode();
        });
        
        function toggleMaintenanceMode() {
            const isActive = document.getElementById('is_active').checked;
            const datetimeSection = document.getElementById('datetimeSection');
            const endDatetimeInput = document.getElementById('end_datetime');
            const statusText = document.getElementById('statusText');
            
            if (isActive) {
                datetimeSection.style.display = 'block';
                endDatetimeInput.required = true;
                if (statusText) {
                    statusText.textContent = 'Currently Active';
                }
            } else {
                datetimeSection.style.display = 'block'; // Keep visible but not required
                endDatetimeInput.required = false;
                if (statusText) {
                    statusText.textContent = 'Currently Inactive';
                }
            }
        }
        
        // Form validation
        document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
            const isActive = document.getElementById('is_active').checked;
            const endDatetime = document.getElementById('end_datetime').value;
            
            if (isActive && !endDatetime) {
                e.preventDefault();
                alert('Please select an end date and time when activating maintenance mode.');
                document.getElementById('end_datetime').focus();
                return false;
            }
        });
    </script>
</body>
</html>

