<?php
session_start();

// 1. Clear all session variables
$_SESSION = array();

// 2. Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to the login page using a RELATIVE path
// This is safer because it doesn't care if your folder is 'Ecoprotean' or 'ecoprotean'
header("Location: login.php");
exit;
?>