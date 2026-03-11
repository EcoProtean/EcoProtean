<?php
session_start();
require_once 'config.php';

// Log page view if logged in
if (!empty($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'VIEW_HOME', 'Viewed home page');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title>EcoProtean</title>
</head>
<body>
  <header>
    <nav>
      <div class="logo">
        <img src="Photo logo/EcoProteous logo.jpg" alt="EcoProtean Logo">
        <div class="logo-content">
          <span class="logo-text">EcoProtean</span>
          <span class="tagline">Guarding the Land, Growing the Future</span>
        </div>
      </div>
      <ul>
        <li><a class="active" href="/EcoProtean/WebApp/RiskMap/index.php">Risk Map</a></li>
        <li><a href="/EcoProtean/WebApp/About/index.php">About</a></li>
        <?php if (isLoggedIn()): ?>
          <li><a href="/EcoProtean/auth/logout.php" class="icon-link">
            <img src="Photo logo/exit.png" alt="Logout" class="nav-icon">
          </a></li>
        <?php else: ?>
          <li><a href="/EcoProtean/auth/login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-content">
      <h1>EcoProtean</h1>
      <p>Helping communities identify landslide risks and choose the right trees.</p>
    </div>
  </section>

  <footer>
    <p>&copy; 2024 EcoProtean Proteus | All Rights Reserved</p>
    <p><a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
  </footer>
</body>
</html>