<?php
session_start();
require_once '../config.php';

// ── Role Protection: only manager and admin ──
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

logActivity($conn, $_SESSION['user_id'], 'VIEW_DASHBOARD', 'Viewed manager dashboard');

// ── Fetch sensors + their locations from DB ──
$result = $conn->query(
    "SELECT s.sensor_id, s.sensor_type, s.location_id,
            l.location_name, l.latitude, l.longitude, l.risk_level,
            (SELECT sd.movement_level
             FROM simulation_data sd
             WHERE sd.sensor_id = s.sensor_id
             ORDER BY sd.timestamp DESC LIMIT 1) AS last_movement
     FROM sensors s
     JOIN locations l ON s.location_id = l.location_id
     ORDER BY s.sensor_id ASC"
);

$sensors = [];
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}

// ── Fetch recent alerts (High risk simulation entries) ──
$alerts = $conn->query(
    "SELECT sd.movement_level, sd.timestamp, l.location_name
     FROM simulation_data sd
     JOIN sensors s ON sd.sensor_id = s.sensor_id
     JOIN locations l ON s.location_id = l.location_id
     WHERE sd.movement_level >= 60
     ORDER BY sd.timestamp DESC
     LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// ── KPI counts ──
$total  = count($sensors);
$atRisk   = 0;
$critical = 0;
foreach ($sensors as $s) {
    $m = (float)($s['last_movement'] ?? 0);
    if ($m >= 30) $atRisk++;
    if ($m >= 60) $critical++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Dashboard - EcoProtean</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

  <!-- ── Sidebar Nav ── -->
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-name">EcoProtean</span>
      <span class="brand-role">Manager Panel</span>
    </div>
    <nav class="sidenav">
      <a href="index.php" class="active">📊 Dashboard</a>
      <a href="../WebApp/RiskMap/index.php">🗺️ Risk Map</a>
      <a href="../WebApp/About/index.php">ℹ️ About</a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></span>
        <span class="user-role"><?= ucfirst($_SESSION['role']) ?></span>
      </div>
      <a href="../logout.php" class="logout-btn">🚪 Logout</a>
    </div>
  </aside>

  <!-- ── Main Content ── -->
  <main class="main-content">

    <div class="page-header">
      <h1>Tree Monitoring Dashboard</h1>
      <span id="lastUpdate" class="last-update">Loading...</span>
    </div>

    <!-- KPI Cards -->
    <section class="kpi">
      <div class="card">
        <div class="card-label">Total Sensors</div>
        <div class="card-value" id="totalTrees"><?= $total ?></div>
      </div>
      <div class="card card-warning">
        <div class="card-label">At Risk</div>
        <div class="card-value" id="atRisk"><?= $atRisk ?></div>
      </div>
      <div class="card card-danger">
        <div class="card-label">Critical</div>
        <div class="card-value" id="critical"><?= $critical ?></div>
      </div>
    </section>

    <!-- Map -->
    <section class="section-box">
      <h2>Sensor Location Map</h2>
      <div id="map"></div>
    </section>

    <!-- Table -->
    <section class="section-box">
      <h2>Tree Monitoring Data</h2>
      <table>
        <thead>
          <tr>
            <th>Sensor ID</th>
            <th>Location</th>
            <th>Movement</th>
            <th>Cause</th>
            <th>Risk Level</th>
          </tr>
        </thead>
        <tbody id="treeTable">
          <!-- Populated by JS -->
        </tbody>
      </table>
    </section>

    <!-- Alerts -->
    <section class="section-box">
      <h2>🚨 Alerts</h2>
      <ul id="alerts">
        <?php foreach ($alerts as $a): ?>
          <li class="alert-item">
            <strong><?= htmlspecialchars($a['location_name']) ?></strong>
            — Movement: <?= $a['movement_level'] ?>/100
            <span class="alert-time"><?= $a['timestamp'] ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (empty($alerts)): ?>
          <li class="no-alerts">No critical alerts yet.</li>
        <?php endif; ?>
      </ul>
    </section>

  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="script.js"></script>
</body>
</html>