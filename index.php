<?php
// Robust, secure session and cookie cleanup, then redirect to login

// Ensure no output before headers
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

// Tighten session configuration for cookie-only sessions
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Disable caching (avoid back button showing authenticated pages)
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Clear all session variables
$_SESSION = [];

// Delete the session cookie using its exact params
$sessionName = session_name();
$params = session_get_cookie_params();
if (isset($_COOKIE[$sessionName])) {
    // PHP 7.3+ array options syntax for correct flag alignment
    setcookie($sessionName, '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => (isset($params['samesite']) ? $params['samesite'] : 'Lax'),
    ]);
}

// Destroy the session and its data on the server
session_destroy();

// Best-effort deletion for all other cookies (cannot know original flags for each)
// Attempt deletion with common path/domain combinations
foreach ($_COOKIE as $cookieName => $cookieValue) {
    // Skip if already removed session cookie above
    if ($cookieName === $sessionName) {
        continue;
    }

    // Try with default path '/'
    setcookie($cookieName, '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Also try current directory path
    $currentPath = dirname(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/') ?: '/';
    setcookie($cookieName, '', [
        'expires' => time() - 42000,
        'path' => $currentPath,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Regenerate a fresh session ID to prevent fixation on next request
// (Start a new session context purely to rotate the ID, then close it.)
session_start();
session_regenerate_id(true);
session_write_close();

// Final redirect to login
header('Location: login.php', true, 303);
exit;
?>