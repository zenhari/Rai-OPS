<?php
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

$current_user = getCurrentUser();

// Handle logout confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_logout'])) {
    logoutUser();
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
        
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="../assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Confirm your logout from the system
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <div class="max-w-md mx-auto">
                    <!-- Logout Confirmation Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>Confirm Logout
                            </h3>
                        </div>
                        
                        <div class="p-6">
                            <div class="text-center">
                                <!-- User Info -->
                                <div class="flex items-center justify-center mb-6">
                                    <div class="h-16 w-16 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center mr-4">
                                        <?php if (!empty($current_user['picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($current_user['picture']); ?>" 
                                                 alt="Profile" class="h-16 w-16 rounded-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-gray-600 dark:text-gray-300 text-2xl"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-left">
                                        <p class="text-lg font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($current_user['position']); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Warning Message -->
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-4 mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                                Are you sure you want to logout?
                                            </h3>
                                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                                <p>You will be redirected to the login page and will need to sign in again to access the system.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex space-x-4">
                                    <a href="javascript:history.back()" 
                                       class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Cancel
                                    </a>
                                    
                                    <button onclick="showLogoutModal()" 
                                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-sign-out-alt mr-2"></i>
                                        Logout
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full mb-4">
                    <i class="fas fa-sign-out-alt text-red-600 dark:text-red-400 text-xl"></i>
                </div>

                <!-- Modal Body -->
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Confirm Logout
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Are you absolutely sure you want to logout from the system? You will need to sign in again to access your account.
                    </p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-center space-x-3">
                    <button onclick="hideLogoutModal()"
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-200">
                        Cancel
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="confirm_logout" value="1">
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors duration-200">
                            <i class="fas fa-sign-out-alt mr-2"></i>Yes, Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('logoutModal');
            if (event.target === modal) {
                hideLogoutModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideLogoutModal();
            }
        });
    </script>
        </div>
    </div>
</body>
</html>
