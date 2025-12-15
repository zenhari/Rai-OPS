<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /dashboard/');
    exit();
}

// Check maintenance mode
$maintenanceActive = false;
$endDateTime = null;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT is_active, end_datetime FROM maintenance_mode ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $maintenanceActive = (bool)$result['is_active'];
        $endDateTime = $result['end_datetime'];
    }
} catch (PDOException $e) {
    // Ignore database errors for maintenance check
}

$error_message = '';
$success_message = '';

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
                header('Location: /dashboard/');
                exit();
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
    <title>Login - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-blue-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-plane text-white text-2xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-gray-900 dark:text-white">
                    Welcome to <?php echo PROJECT_NAME; ?>
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <?php echo COMPANY_NAME; ?> Fleet Management System
                </p>
            </div>

            <!-- Maintenance Message -->
            

            <!-- Login Form -->
            <form class="mt-8 space-y-6" method="POST" action="">
                <div class="rounded-md shadow-sm bg-white dark:bg-gray-800 p-6 border border-gray-200 dark:border-gray-700">
                    <!-- Error Message -->
                    <?php if (!empty($error_message)): ?>
                        <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                            <div class="flex">
                                <i class="fas fa-exclamation-circle mt-0.5 mr-2"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Success Message -->
                    <?php if (!empty($success_message)): ?>
                        <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                            <div class="flex">
                                <i class="fas fa-check-circle mt-0.5 mr-2"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($success_message); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-4">
                        <!-- Username Field -->
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-user mr-2"></i>Username
                            </label>
                            <input id="username" name="username" type="text" required 
                                   class="appearance-none rounded-md relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm transition-colors duration-200" 
                                   placeholder="Enter your username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required 
                                       class="appearance-none rounded-md relative block w-full px-3 py-3 pr-10 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm transition-colors duration-200" 
                                       placeholder="Enter your password">
                                <button type="button" onclick="togglePassword()" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="password-icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between mt-4">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Remember me
                            </label>
                        </div>
                        <div class="text-sm">
                            <a href="#" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-6">
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                            </span>
                            Sign in
                        </button>
                    </div>
                    
                    <!-- Crew Plan Button -->
                    <div class="mt-4">
                        <a href="/crewplan/" 
                           class="group relative w-full flex justify-center py-3 px-4 border border-green-300 dark:border-green-600 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-calendar-alt text-green-200 group-hover:text-green-100"></i>
                            </span>
                            Crew Plan
                        </a>
                    </div>
                    
                    <!-- Transport Data Button -->
                    <div class="mt-3">
                        <a href="/transport/login.php" 
                           class="group relative w-full flex justify-center py-3 px-4 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-users text-gray-500 group-hover:text-gray-600 dark:text-gray-400 dark:group-hover:text-gray-300"></i>
                            </span>
                            Transport Data
                        </a>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div class="text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    © <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?>. All rights reserved.
                </p>
            </div>
        </div>
    </div>


    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Dark mode functionality
        function toggleDarkMode() {
            const html = document.documentElement;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                if (darkModeIcon) {
                darkModeIcon.className = 'fas fa-moon';
                }
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                if (darkModeIcon) {
                darkModeIcon.className = 'fas fa-sun';
                }
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
                if (darkModeIcon) {
                darkModeIcon.className = 'fas fa-sun';
                }
            } else {
                document.documentElement.classList.remove('dark');
                if (darkModeIcon) {
                darkModeIcon.className = 'fas fa-moon';
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('darkMode') === null) {
                const darkModeIcon = document.getElementById('dark-mode-icon');
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                    if (darkModeIcon) {
                        darkModeIcon.className = 'fas fa-sun';
                    }
                } else {
                    document.documentElement.classList.remove('dark');
                    if (darkModeIcon) {
                        darkModeIcon.className = 'fas fa-moon';
                    }
                }
            }
        });
    </script>
</body>
</html>
