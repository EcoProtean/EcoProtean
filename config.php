<?php
// ─────────────────────────────────────────────
//  EcoProtean - Database Configuration
//  Edit the values below to match your XAMPP setup
// ─────────────────────────────────────────────

define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // default XAMPP username
define('DB_PASS', '');       // default XAMPP password (empty)
define('DB_NAME', 'ecoprotean');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// ─────────────────────────────────────────────
//  Session helper - call at top of any page
//  that needs login protection
// ─────────────────────────────────────────────
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['user_id']);
}

function currentUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION ?? [];
}

// Log an action to activity_logs
function logActivity($conn, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isss', $user_id, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
