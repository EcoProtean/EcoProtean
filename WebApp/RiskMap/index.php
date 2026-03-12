<?php
session_start();
require_once '../../config.php';

if (!empty($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'VIEW_RISKMAP', 'Viewed risk map');
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
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <title>Risk Map - EcoProtean</title>
</head>
<body>
  <header>
    <nav>
      <div class="logo">
        <img src="../Photo logo/EcoProteous logo.png" alt="EcoProtean Logo">
        <div class="logo-content">
          <span class="logo-text">EcoProtean</span>
          <span class="tagline">Guarding the Land, Growing the Future</span>
        </div>
      </div>
      <ul>
        <li><a class="active" href="../RiskMap/index.php">Risk Map</a></li>
        <li><a href="../About/index.php">About</a></li>
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin','manager'])): ?>
          <li><a href="../../admin/index.php">Admin</a></li>
        <?php endif; ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <li><a href="/EcoProtean/auth/logout.php" class="icon-link">
            <img src="../Photo logo/exit.png" alt="Logout" class="nav-icon">
          </a></li>
        <?php else: ?>
          <li><a href="../../login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <div class="map-container">
    <div id="map"></div>
  </div>

  <footer>
    <p>&copy; 2024 EcoProtean Proteus | All Rights Reserved</p>
    <p><a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
  </footer>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="services.js"></script>
</body>
</html>
