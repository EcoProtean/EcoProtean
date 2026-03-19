<?php
session_start();
require_once '../config/config.php';

if (!empty($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'LOGOUT', ($_SESSION['full_name'] ?? 'User') . ' logged out');
}

session_destroy();
header('Location: /ecoprotean/auth/login.php');
exit;
?>
