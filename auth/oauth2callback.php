<?php
session_start();
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__)); 
$dotenv->load();

// Initialize Google Client
$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URL']);

if (isset($_GET['code'])) {
    // 1. Exchange the code for a token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    // Check for errors in the token exchange
    if (isset($token['error'])) {
        die("Auth Error: " . $token['error_description']);
    }
    
    $client->setAccessToken($token);

    // 2. Get user profile info from Google
    $google_oauth = new Google\Service\Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email = $google_account_info->email;

    // 3. Check your MySQL database for existing user
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, role FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // --- CASE 1: EXISTING USER ---
        // Set the sessions
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

        // Log activity
        logActivity($conn, $user['user_id'], 'login_google', 'User logged in via Google');

        // Redirect based on the ROLE
        if ($user['role'] === 'admin') {
            header('Location: ../admin/index.php');
        } elseif ($user['role'] === 'manager') {
            header('Location: ../management/index.php');
        } else {
            header('Location: ../webapp/riskmap/index.php');
        }
        exit;

    } else {
        // --- CASE 2: NEW USER (The fix for the blank page) ---
        $firstName = $google_account_info->givenName;
        $lastName = $google_account_info->familyName;
        $role = 'user'; // Default role for new signups

        // Insert new user into database
        $insert = $conn->prepare("INSERT INTO users (first_name, last_name, email, role, password) VALUES (?, ?, ?, ?, 'google_auth')");
        $insert->bind_param('ssss', $firstName, $lastName, $email, $role);
        
        if ($insert->execute()) {
            $new_id = $conn->insert_id;

            // Set sessions for the new user
            $_SESSION['user_id'] = $new_id;
            $_SESSION['first_name'] = $firstName;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $firstName . ' ' . $lastName;

            // Log the new signup
            logActivity($conn, $new_id, 'signup_google', 'New account created via Google auto-registration');

            // Redirect new users to the Risk Map
            header('Location: ../webapp/riskmap/index.php');
            exit;
        } else {
            // Handle database errors
            die("Database Error: Could not create account. " . $conn->error);
        }
    }
} else {
    // If someone tries to access this file directly without a code
    header('Location: login.php');
    exit;
}
?>