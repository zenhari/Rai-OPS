<?php
require_once '../../config.php';

// Check access
checkPageAccessWithRedirect('admin/rlss/index.php');

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLSS - <?php echo PROJECT_NAME; ?></title>
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
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            html {
                color-scheme: dark;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900" onload="applyDarkMode()">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">RLSS</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">RLSS Management System</p>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Part Search Card -->
                    <?php if (checkPageAccessEnhanced('admin/rlss/part_search/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/rlss/part_search/index.php'); ?>" 
                       class="bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 p-6 border border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-500">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                <i class="fas fa-search text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Part Search</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Search for aircraft parts using Locatory API. Find parts by part number, condition, and quantity.
                        </p>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Search MRO Card -->
                    <?php if (checkPageAccessEnhanced('admin/rlss/search_mro/index.php')): ?>
                    <a href="<?php echo getAbsolutePath('admin/rlss/search_mro/index.php'); ?>" 
                       class="bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 p-6 border border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-500">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                <i class="fas fa-wrench text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Search MRO</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Search for MRO capabilities using Locatory API. Find maintenance, repair, and overhaul services by part number or description.
                        </p>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Placeholder for future submenus -->
                    <!-- Add more cards here as new submenus are created -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark mode detection from browser preference
        function applyDarkMode() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const html = document.documentElement;
            
            if (prefersDark) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            
            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (e.matches) {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            });
        }
    </script>
</body>
</html>
