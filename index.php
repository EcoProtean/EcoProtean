<?php
session_start();
require_once 'config.php';

// Log page view if logged in
if (!empty($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'VIEW_HOME', 'Viewed home page');
}

// Redirect to Risk Map as the default page
header("Location: /EcoProtean/WebApp/RiskMap/index.php");
exit();
?>