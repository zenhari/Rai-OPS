<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

// Redirect to logout confirmation page
header('Location: /admin/logout.php');
exit();
?>
