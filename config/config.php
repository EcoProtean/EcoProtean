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
        header('Location: /ecoprotean/auth/login.php');
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

// ─────────────────────────────────────────────
//  Log an action to activity_logs
// ─────────────────────────────────────────────
function logActivity($conn, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isss', $user_id, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────
//  Generate a weighted random movement level
//  50% chance low    (0–29)   → Normal
//  30% chance medium (30–59)  → Warning
//  20% chance high   (60–100) → Critical
//  Used by both api/locations.php and 
//  api/simulate.php to ensure consistency
// ─────────────────────────────────────────────
function generateMovement() {
    $rand = rand(1, 100);
    if ($rand <= 50) {
        return rand(0, 29);    // Normal
    } elseif ($rand <= 80) {
        return rand(30, 59);   // Warning
    } else {
        return rand(60, 100);  // Critical
    }
}
?>