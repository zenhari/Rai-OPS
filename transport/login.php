<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $current_user = getCurrentUser();
    if (isset($current_user['role_id']) && $current_user['role_id'] == 18) {
        header('Location: /transport/index.php');
        exit();
    } else {
        header('Location: /dashboard/');
        exit();
    }
}

$error_message = '';
$success_message = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /transport/login.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
            // Test database connection first
            try {
                $pdo = getDBConnection();
                if (loginUser($username, $password)) {
                    $current_user = getCurrentUser();
                    if (isset($current_user['role_id']) && $current_user['role_id'] == 18) {
                        header('Location: /transport/index.php');
                        exit();
                    } else {
                        $error_message = 'Access denied. This area is restricted to transport personnel only.';
                        // Logout the user since they don't have access
                        session_destroy();
                    }
                } else {
                    $error_message = 'Invalid username or password.';
                }
        } catch (Exception $e) {
            $error_message = 'Database connection error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Login - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="/assets/js/tailwind.js"></script>
    
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
    </script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <i class="fas fa-users text-6xl text-gray-600 dark:text-gray-400 mb-4"></i>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Transport Team</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Access transport and flight delay information
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-gray-200 dark:border-gray-700">
                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                    Error
                                </h3>
                                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                                    Success
                                </h3>
                                <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                    <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Username
                        </label>
                        <div class="mt-1">
                            <input id="username" name="username" type="text" required 
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md placeholder-gray-400 dark:placeholder-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Enter your username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <div class="mt-1">
                            <input id="password" name="password" type="password" required 
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md placeholder-gray-400 dark:placeholder-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Enter your password">
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-gray-300 group-hover:text-gray-200"></i>
                            </span>
                            Sign in to Transport Data
                        </button>
                    </div>
                    
                    <!-- Back to Main Login -->
                    <div class="mt-4">
                        <a href="/login.php" 
                           class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-home text-gray-500 group-hover:text-gray-600 dark:text-gray-400 dark:group-hover:text-gray-300"></i>
                            </span>
                            Back to Main Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle -->
    <button onclick="toggleDarkMode()" 
            class="fixed top-4 right-4 p-3 rounded-full bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 z-50">
        <i id="dark-mode-icon" class="fas fa-moon text-gray-600 dark:text-gray-400"></i>
    </button>

    <script>
        function toggleDarkMode() {
            const html = document.documentElement;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            const isDark = html.classList.contains('dark');
            
            if (isDark) {
                html.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon text-gray-600 dark:text-gray-400';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun text-gray-600 dark:text-gray-400';
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Initialize dark mode based on system preference or saved preference
        function initDarkMode() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (savedDarkMode === 'true' || (savedDarkMode === null && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun text-gray-600 dark:text-gray-400';
            } else {
                document.documentElement.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon text-gray-600 dark:text-gray-400';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('darkMode') === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                    document.getElementById('dark-mode-icon').className = 'fas fa-sun text-gray-600 dark:text-gray-400';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.getElementById('dark-mode-icon').className = 'fas fa-moon text-gray-600 dark:text-gray-400';
                }
            }
        });
    </script>
</body>
</html>
