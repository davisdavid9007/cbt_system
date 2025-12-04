<?php
// File: logout.php - Update existing file
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to appropriate login page
if (strpos($_SERVER['HTTP_REFERER'], '/staff/') !== false) {
    header("Location: staff/login.php");
} else {
    header("Location: admin/login.php");
}
exit();
?>