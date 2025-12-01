<?php
require_once 'config.php';

// Get current user info
$current_user = getCurrentUser();
$requested_page = $_GET['page'] ?? 'Unknown Page';
$user_role = $current_user['role'] ?? 'employee';
$user_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Roboto Font - Local -->
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-ban mr-2"></i>Access Denied
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                You don't have permission to access this page
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Dark Mode Toggle -->
                            <button id="dark-mode-icon" onclick="toggleDarkMode()" 
                                    class="p-2 rounded-md text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-moon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 flex items-center justify-center p-6">
                <div class="max-w-lg w-full">
                    <!-- Access Denied Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Header -->
                        <div class="bg-red-50 dark:bg-red-900/20 px-6 py-6 border-b border-red-200 dark:border-red-800">
                            <div class="text-center">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                                    <i class="fas fa-lock text-red-600 dark:text-red-400 text-2xl"></i>
                                </div>
                                <h2 class="text-xl font-semibold text-red-800 dark:text-red-200 mb-2">
                                    Access Denied
                                </h2>
                                <p class="text-sm text-red-600 dark:text-red-300">
                                    You don't have permission to access this resource
                                </p>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="px-6 py-6">
                            <!-- Requested Page Info -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-file mr-2"></i>
                                    <span class="font-medium">Requested Page:</span>
                                    <span class="ml-2 font-mono bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded text-xs">
                                        <?php echo htmlspecialchars($requested_page); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                            Current User
                                        </h4>
                                        <div class="mt-1 text-sm text-blue-600 dark:text-blue-300">
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($user_name); ?></p>
                                            <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($user_role)); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="space-y-3">
                                <a href="/dashboard/" 
                                   class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                    <i class="fas fa-tachometer-alt mr-2"></i>
                                    Go to Dashboard
                                </a>
                                
                                <div class="flex space-x-3">
                                    <a href="/admin/profile/" 
                                       class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-user-circle mr-2"></i>
                                        Profile
                                    </a>
                                    
                                    <button onclick="history.back()" 
                                            class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Go Back
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help Text -->
                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Contact your administrator if you believe this is an error.
                        </p>
                    </div>
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
