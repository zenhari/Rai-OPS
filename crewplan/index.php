<?php
require_once '../config.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            $pdo = getDBConnection();
            if (loginUser($username, $password)) {
                // Check if user has flight_crew access (flight_crew == 1)
                $current_user = getCurrentUser();
                if ($current_user && isset($current_user['flight_crew']) && $current_user['flight_crew'] == 1) {
                    header('Location: /crewplan/dashboard.php');
                    exit();
                } else {
                    $error_message = 'Access denied. Only flight crew members can access crew plan.';
                    // Logout the user
                    logoutUser();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#16a34a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Crew Plan">
    <meta name="description" content="Pilot Flight Schedule Portal for Raimon Airways">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#16a34a">
    <meta name="msapplication-config" content="browserconfig.xml">
    
    <title>Crew Plan Login - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="72x72" href="icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="icons/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="icons/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="icons/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="icons/icon-512x512.png">
    
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
        
        /* PWA Install Prompt Styles */
        .pwa-install-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #16a34a;
            color: white;
            padding: 1rem;
            display: none;
            z-index: 9999;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .pwa-install-banner.show {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .pwa-install-banner button {
            background: white;
            color: #16a34a;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .pwa-install-banner button:hover {
            background: #f3f4f6;
        }
        
        @media (max-width: 640px) {
            .pwa-install-banner {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-green-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-white text-2xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-gray-900 dark:text-white">
                    Crew Plan Access
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Pilot Flight Schedule Portal
                </p>
            </div>

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
                                   class="appearance-none rounded-md relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm transition-colors duration-200" 
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
                                       class="appearance-none rounded-md relative block w-full px-3 py-3 pr-10 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm transition-colors duration-200" 
                                       placeholder="Enter your password">
                                <button type="button" onclick="togglePassword()" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="password-icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-6">
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-green-500 group-hover:text-green-400"></i>
                            </span>
                            Access Crew Plan
                        </button>
                    </div>
                </div>
            </form>

            <!-- Back to Main Login -->
            <div class="text-center">
                <a href="/login.php" 
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Back to Main Login
                </a>
            </div>

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
                darkModeIcon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun';
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
                if (darkModeIcon) darkModeIcon.className = 'fas fa-sun';
            } else {
                document.documentElement.classList.remove('dark');
                if (darkModeIcon) darkModeIcon.className = 'fas fa-moon';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('darkMode') === null) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                    const darkModeIcon = document.getElementById('dark-mode-icon');
                    if (darkModeIcon) darkModeIcon.className = 'fas fa-sun';
                } else {
                    document.documentElement.classList.remove('dark');
                    const darkModeIcon = document.getElementById('dark-mode-icon');
                    if (darkModeIcon) darkModeIcon.className = 'fas fa-moon';
                }
            }
        });

        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then((registration) => {
                        console.log('Service Worker registered:', registration.scope);
                        
                        // Check for updates periodically
                        setInterval(() => {
                            registration.update();
                        }, 60000); // Check every minute
                    })
                    .catch((error) => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }

        // PWA Install Prompt
        let deferredPrompt;
        const installBanner = document.getElementById('pwaInstallBanner');
        
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later
            deferredPrompt = e;
            // Show our custom install banner
            if (installBanner) {
                installBanner.classList.add('show');
            }
        });

        // Handle install button click
        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                    if (installBanner) {
                        installBanner.classList.remove('show');
                    }
                });
            }
        }

        // Hide banner when app is installed
        window.addEventListener('appinstalled', () => {
            console.log('PWA installed');
            if (installBanner) {
                installBanner.classList.remove('show');
            }
            deferredPrompt = null;
        });
    </script>
    
    <!-- PWA Install Banner -->
    <div id="pwaInstallBanner" class="pwa-install-banner">
        <div>
            <i class="fas fa-mobile-alt mr-2"></i>
            <span>Install Crew Plan App for better experience</span>
        </div>
        <button onclick="installPWA()">Install</button>
    </div>
</body>
</html>
