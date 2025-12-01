<?php
require_once '../config.php';

// Logout the user
logoutUser();

// Redirect to crew plan login
header('Location: /crewplan/');
exit();
?>
