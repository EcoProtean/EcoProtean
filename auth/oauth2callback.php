<?php
session_start();
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use Dotenv\Dotenv; // Add this line!

// Use dirname(__DIR__) so it finds the .env in the root Ecoprotean folder
$dotenv = Dotenv::createImmutable(dirname(__DIR__)); 
$dotenv->load();

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URL']);

if (isset($_GET['code'])) {
    // 1. Exchange the code for a token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    // 2. Get user profile info from Google
    $google_oauth = new Google\Service\Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email = $google_account_info->email;

    // 3. Check your MySQL database
    $stmt = $conn->prepare("SELECT user_id, first_name, role FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

   if ($user) {
    // 1. Set the sessions (same as your manual login)
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['first_name'];

    // 2. Log the activity
    logActivity($conn, $user['user_id'], 'login_google', 'Admin logged in via Google');

    // 3. Redirect based on the ROLE in your database
    if ($user['role'] === 'admin') {
        header('Location: ../admin/index.php');
    } elseif ($user['role'] === 'manager') {
        header('Location: ../management/index.php');
    } else {
        header('Location: ../webapp/riskmap/index.php');
    }
    exit;
   }
}