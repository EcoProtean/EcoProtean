<?php
session_start();
require_once '../config/config.php';
requireLogin();

if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
  header('Location: ../index.php');
  exit;
}

logActivity($conn, $_SESSION['user_id'], 'VIEW_DASHBOARD', 'Viewed admin dashboard');

// ── POST handlers ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    if ($user_id === $_SESSION['user_id']) {
      $error = "You cannot delete your own account!";
    } else {
      $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
      $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->close();
      logActivity($conn, $_SESSION['user_id'], 'DELETE_USER', "Deleted user ID: $user_id");
      $success = "User deleted successfully.";
    }
  }

  if ($action === 'edit_user') {
    $user_id      = (int)$_POST['user_id'];
    $new_role     = $_POST['role'] ?? 'user';
    $new_password = trim($_POST['password'] ?? '');
    if ($new_password !== '') {
      $hashed = password_hash($new_password, PASSWORD_BCRYPT);
      $stmt = $conn->prepare("UPDATE users SET role=?, password=? WHERE user_id=?");
      $stmt->bind_param("ssi", $new_role, $hashed, $user_id);
    } else {
      $stmt = $conn->prepare("UPDATE users SET role=? WHERE user_id=?");
      $stmt->bind_param("si", $new_role, $user_id);
    }
    $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'EDIT_USER', "Updated user ID $user_id");
    $success = "User updated successfully.";
  }

  if ($action === 'add_user') {
    $first    = trim($_POST['first_name']);
    $last     = trim($_POST['last_name']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (first_name,last_name,email,password,role) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $first, $last, $email, $password, $role);
    $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'ADD_USER', "Added user: $first $last");
    $success = "User added successfully.";
  }

  if ($action === 'add_location') {
    $name = trim($_POST['location_name']);
    $lat  = (float)$_POST['latitude'];
    $lng  = (float)$_POST['longitude'];
    $risk = $_POST['risk_level'];
    $desc = trim($_POST['description']);
    $stmt = $conn->prepare("INSERT INTO locations (location_name,latitude,longitude,risk_level,description) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sddss', $name, $lat, $lng, $risk, $desc);
    $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'ADD_LOCATION', "Added location: $name");
    $success = "Location '$name' added successfully.";
  }

  if ($action === 'delete_location') {
    $id = (int)$_POST['location_id'];
    $stmt = $conn->prepare("DELETE FROM locations WHERE location_id=?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'DELETE_LOCATION', "Deleted location ID: $id");
    $success = "Location deleted.";
  }

  if ($action === 'add_recommendation') {
    $loc_id = (int)$_POST['location_id'];
    $tree   = trim($_POST['tree_name']);
    $reason = trim($_POST['reason']);
    $rec_by = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO tree_recommendations (location_id,tree_name,reason,recommended_by) VALUES (?,?,?,?)");
    $stmt->bind_param('issi', $loc_id, $tree, $reason, $rec_by);
    $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'ADD_RECOMMENDATION', "Added recommendation: $tree");
    $success = "Tree recommendation added.";
  }
}

// ── Fetch stat counts ──────────────────────────────────
$total_locations = $conn->query("SELECT COUNT(*) AS c FROM locations")->fetch_assoc()['c'];
$total_sensors   = $conn->query("SELECT COUNT(*) AS c FROM sensors")->fetch_assoc()['c'];
$total_users     = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_recs      = $conn->query("SELECT COUNT(*) AS c FROM tree_recommendations")->fetch_assoc()['c'];

// ── Critical alerts ──
$critical = $conn->query("
  SELECT sd.movement_level, sd.timestamp, l.location_name
  FROM simulation_data sd
  JOIN sensors s   ON sd.sensor_id   = s.sensor_id
  JOIN locations l ON s.location_id  = l.location_id
  WHERE sd.movement_level >= 60
    AND sd.timestamp >= NOW() - INTERVAL 1 HOUR
  ORDER BY sd.movement_level DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Chart 1: movement last 24h ──
$movement_raw = $conn->query("
  SELECT s.sensor_id, l.location_name,
         DATE_FORMAT(sd.timestamp,'%H:00') AS hour,
         ROUND(AVG(sd.movement_level),1)   AS avg_level
  FROM simulation_data sd
  JOIN sensors s   ON sd.sensor_id   = s.sensor_id
  JOIN locations l ON s.location_id  = l.location_id
  WHERE sd.timestamp >= NOW() - INTERVAL 24 HOUR
  GROUP BY s.sensor_id, l.location_name, hour
  ORDER BY hour
")->fetch_all(MYSQLI_ASSOC);

$hours_set   = [];
$sensor_data = [];
foreach ($movement_raw as $row) {
  $hours_set[$row['hour']] = true;
  $key = $row['sensor_id'].'|'.$row['location_name'];
  $sensor_data[$key][$row['hour']] = $row['avg_level'];
}
$chart_hours = array_keys($hours_set);

// ── Chart 2: risk distribution ──
$risk_raw    = $conn->query("SELECT risk_level, COUNT(*) AS cnt FROM locations GROUP BY risk_level")->fetch_all(MYSQLI_ASSOC);
$risk_labels = array_column($risk_raw, 'risk_level');
$risk_counts = array_column($risk_raw, 'cnt');

// ── Chart 3: top trees ──
$tree_raw    = $conn->query("SELECT tree_name, COUNT(*) AS cnt FROM tree_recommendations GROUP BY tree_name ORDER BY cnt DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
$tree_labels = array_column($tree_raw, 'tree_name');
$tree_counts = array_column($tree_raw, 'cnt');

// ── Sensor status with latest reading + coordinates ──
$sensors_status = $conn->query("
  SELECT s.sensor_id, l.location_name, l.risk_level,
         l.latitude, l.longitude, l.description,
         (SELECT sd2.movement_level FROM simulation_data sd2
          WHERE sd2.sensor_id = s.sensor_id
          ORDER BY sd2.timestamp DESC LIMIT 1) AS latest_level
  FROM sensors s
  JOIN locations l ON s.location_id = l.location_id
")->fetch_all(MYSQLI_ASSOC);

// ── Recent recommendations ──
$recommendations = $conn->query("
  SELECT tr.tree_name, l.location_name, tr.created_at,
         CONCAT(u.first_name,' ',u.last_name) AS recommended_by_name
  FROM tree_recommendations tr
  JOIN locations l ON tr.location_id = l.location_id
  JOIN users u     ON tr.recommended_by = u.user_id
  ORDER BY tr.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ── Recent logs ──
$logs = $conn->query("
  SELECT al.action, al.description, al.created_at,
         CONCAT(u.first_name,' ',u.last_name) AS full_name
  FROM activity_logs al
  JOIN users u ON al.user_id = u.user_id
  ORDER BY al.created_at DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// ── Helpers ──
function actionClass(string $a): string {
  $a = strtolower($a);
  if (str_contains($a,'login'))  return 'login';
  if (str_contains($a,'logout')) return 'logout';
  if (str_contains($a,'delete')) return 'delete';
  if (str_contains($a,'add'))    return 'add';
  if (str_contains($a,'edit') || str_contains($a,'update')) return 'edit';
  if (str_contains($a,'view'))   return 'view';
  return 'default';
}
function levelClass(int $lvl): string {
  if ($lvl >= 60) return 'lvl-high';
  if ($lvl >= 30) return 'lvl-medium';
  return 'lvl-low';
}
function riskColor(string $risk): string {
  return match(strtolower($risk)) {
    'high'   => '#e74c3c',
    'medium' => '#e67e22',
    default  => '#27ae60',
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — EcoProtean</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <style>
    /* ── Map styles ── */
    #sensorMap {
      width: 100%;
      height: 100%;
      min-height: 300px;
      border-radius: 8px;
      z-index: 1;
    }
    .map-wrap {
      flex: 1;
      min-height: 300px;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
    }
    /* custom leaflet marker popup */
    .leaflet-popup-content-wrapper {
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.82rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    .popup-title {
      font-weight: 600;
      color: #2c5f5d;
      margin-bottom: 4px;
      font-size: 0.88rem;
    }
    .popup-row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-top: 3px;
      font-size: 0.78rem;
      color: #555;
    }
    .popup-badge {
      display: inline-block;
      padding: 1px 8px;
      border-radius: 10px;
      font-size: 0.72rem;
      font-weight: 600;
    }
    .popup-badge.high   { background:#fdecea; color:#c0392b; }
    .popup-badge.medium { background:#fef9e7; color:#d35400; }
    .popup-badge.low    { background:#eafaf1; color:#1e8449; }
  </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar">
  <div class="brand">EcoProtean <span>Admin Panel</span></div>
  <div class="nav-section">Menu</div>
  <a href="#" class="active" id="nav-dashboard" onclick="showView('dashboard')">📊 Dashboard</a>
  <a href="#" id="nav-usermanagement" onclick="showView('usermanagement')">👥 User Management</a>
  <div class="logout">
    <a href="../auth/logout.php">🚪 Logout (<?= htmlspecialchars($_SESSION['full_name']) ?>)</a>
  </div>
</div>

<div class="main">

  <!-- Top bar -->
  <div class="topbar">
    <div>
      <div class="page-title" id="pageTitle">Dashboard</div>
      <div class="welcome" id="pageWelcome">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?> · <?= ucfirst($_SESSION['role']) ?></div>
    </div>
    <div class="live-clock"><span id="clockTime">—</span></div>
  </div>

  <?php if (!empty($success)): ?>
    <div class="alert success" id="successMessage"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert error" id="errorMessage"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════ -->
  <!-- VIEW: DASHBOARD                         -->
  <!-- ═══════════════════════════════════════ -->
  <div id="view-dashboard">

  <!-- Critical alerts -->
  <?php if (!empty($critical)): ?>
    <div class="critical-banner">
      <div class="pulse"></div>
      <strong>Critical Alert</strong>
      <div class="critical-chips">
        <?php foreach ($critical as $c): ?>
          <span class="critical-chip"><?= htmlspecialchars($c['location_name']) ?> · <?= $c['movement_level'] ?> · <?= date('H:i', strtotime($c['timestamp'])) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="all-clear">✅ All clear — no critical sensor alerts in the last hour.</div>
  <?php endif; ?>

  <!-- Stat cards -->
  <div class="stats">
    <div class="stat-card"><div class="num"><?= $total_locations ?></div><div class="label">📍 Locations</div></div>
    <div class="stat-card"><div class="num"><?= $total_sensors ?></div><div class="label">📡 Active Sensors</div></div>
    <div class="stat-card"><div class="num"><?= $total_users ?></div><div class="label">👥 Total Users</div></div>
    <div class="stat-card"><div class="num"><?= $total_recs ?></div><div class="label">🌳 Recommendations</div></div>
  </div>

  <!-- ── ROW: Sensor Status + Map ── -->
  <div class="dash-row col-2">

    <!-- Sensor status table -->
    <div class="section">
      <div class="section-header">
        <h2>📡 Sensor Status</h2>
      </div>
      <table class="mini-table">
        <thead>
          <tr>
            <th style="width:8%">#</th>
            <th style="width:32%">Location</th>
            <th style="width:20%">Level</th>
            <th style="width:20%">Risk</th>
            <th style="width:20%">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sensors_status as $s):
            $lvl = (int)($s['latest_level'] ?? 0);
            $status = $lvl >= 60 ? 'Critical' : ($lvl >= 30 ? 'Warning' : 'Normal');
            $statusClass = $lvl >= 60 ? 'sensor-critical' : ($lvl >= 30 ? 'sensor-warning' : 'sensor-normal');
          ?>
            <tr>
              <td><?= $s['sensor_id'] ?></td>
              <td><?= htmlspecialchars($s['location_name']) ?></td>
              <td class="<?= levelClass($lvl) ?>"><?= $lvl ?></td>
              <td><span class="badge <?= strtolower($s['risk_level']) ?>"><?= $s['risk_level'] ?></span></td>
              <td><span class="sensor-status <?= $statusClass ?>"><?= $status ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Legend -->
      <div style="margin-top:16px;padding-top:14px;border-top:1px solid #f0f4f3;">
        <div style="font-size:0.7rem;color:#999;margin-bottom:8px;font-weight:500;">MOVEMENT LEVEL GUIDE</div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
          <span style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:#888;">
            <span style="width:10px;height:10px;border-radius:50%;background:#27ae60;display:inline-block;"></span> 0–29 Normal
          </span>
          <span style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:#888;">
            <span style="width:10px;height:10px;border-radius:50%;background:#e67e22;display:inline-block;"></span> 30–59 Warning
          </span>
          <span style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:#888;">
            <span style="width:10px;height:10px;border-radius:50%;background:#e74c3c;display:inline-block;"></span> 60+ Critical
          </span>
        </div>
      </div>
    </div>

    <!-- Map -->
    <div class="section" style="display:flex;flex-direction:column;padding:18px 20px;">
      <div class="section-header">
        <h2>🗺 Sensor Locations</h2>
        <span style="font-size:0.72rem;color:#999;"><?= count($sensors_status) ?> sensors active</span>
      </div>
      <div class="map-wrap">
        <div id="sensorMap"></div>
      </div>
    </div>

  </div>

  <!-- ── ROW: Line chart + Doughnut ── -->
  <div class="dash-row col-2">
    <div class="section">
      <div class="section-header">
        <h2>📈 Movement Levels — Last 24 Hours</h2>
      </div>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="lineChart"></canvas>
      </div>
      <div class="chart-legend" id="lineLegend" style="margin-top:12px;justify-content:center;"></div>
    </div>
    <div class="section" style="display:flex;flex-direction:column;">
      <div class="section-header"><h2>📊 Risk Distribution</h2></div>
      <div class="chart-wrap" style="flex:1;min-height:220px;position:relative;">
        <canvas id="doughnutChart"></canvas>
      </div>
      <div class="chart-legend" id="riskLegend" style="margin-top:12px;justify-content:center;"></div>
    </div>
  </div>

  <!-- ── ROW: Bar chart ── -->
  <div class="section">
    <div class="section-header">
      <h2>🌳 Top Recommended Trees</h2>
    </div>
    <div class="chart-legend" id="barLegend"></div>
    <div class="chart-wrap" style="height:140px;">
      <canvas id="barChart"></canvas>
    </div>
  </div>

  <!-- ── ROW: Recommendations + Activity ── -->
  <div class="dash-row col-2">
    <div class="section">
      <div class="section-header">
        <h2>🌿 Recent Recommendations</h2>
      </div>
      <table class="mini-table">
        <thead><tr><th style="width:26%">Tree</th><th style="width:30%">Location</th><th style="width:26%">By</th><th style="width:18%">Date</th></tr></thead>
        <tbody>
          <?php foreach ($recommendations as $rec): ?>
            <tr>
              <td><strong><?= htmlspecialchars($rec['tree_name']) ?></strong></td>
              <td><?= htmlspecialchars($rec['location_name']) ?></td>
              <td><?= htmlspecialchars($rec['recommended_by_name']) ?></td>
              <td class="ts"><?= date('M d', strtotime($rec['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="section">
      <div class="section-header">
        <h2>📋 Recent Activity</h2>
      </div>
      <table class="mini-table">
        <thead><tr><th style="width:30%">User</th><th style="width:44%">Action</th><th style="width:26%">Time</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= htmlspecialchars($log['full_name']) ?></td>
              <td><span class="action-tag <?= actionClass($log['action']) ?>"><?= htmlspecialchars($log['action']) ?></span></td>
              <td class="ts"><?= date('M d H:i', strtotime($log['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  </div><!-- end #view-dashboard -->

  <!-- ═══════════════════════════════════════ -->
  <!-- VIEW: USER MANAGEMENT                   -->
  <!-- ═══════════════════════════════════════ -->
  <div id="view-usermanagement" style="display:none;">

    <!-- Role summary cards -->
    <div class="stats" style="grid-template-columns:repeat(3,minmax(0,1fr));">
      <?php
        $rc = ['admin'=>0,'manager'=>0,'user'=>0];
        $role_counts = $conn->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role")->fetch_all(MYSQLI_ASSOC);
        foreach ($role_counts as $r) $rc[$r['role']] = $r['cnt'];
      ?>
      <div class="stat-card"><div class="num"><?= $rc['admin'] ?></div><div class="label">Admins</div></div>
      <div class="stat-card"><div class="num"><?= $rc['manager'] ?></div><div class="label">Managers</div></div>
      <div class="stat-card"><div class="num"><?= $rc['user'] ?></div><div class="label">Users</div></div>
    </div>

    <!-- Users table -->
    <div class="section">
      <div class="section-header">
        <h2>👥 All Users</h2>
        <button class="btn btn-sm" onclick="openAddUserModal()">+ Add User</button>
      </div>
      <?php
        $users = $conn->query("
          SELECT user_id, first_name, last_name, email, role, last_login, created_at
          FROM users ORDER BY created_at DESC
        ")->fetch_all(MYSQLI_ASSOC);
      ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Last Login</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= $user['user_id'] ?></td>
                <td><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><span class="role-badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span></td>
                <td class="ts"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                <td class="ts"><?= $user['last_login'] ? date('M d H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                  <button class="btn btn-sm" onclick='openViewModal({
                    user_id: <?= $user["user_id"] ?>,
                    name: "<?= htmlspecialchars($user["first_name"]." ".$user["last_name"]) ?>",
                    email: "<?= htmlspecialchars($user["email"]) ?>",
                    role: "<?= $user["role"] ?>",
                    created_at: "<?= $user["created_at"] ?>"
                  })'>View</button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?')">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- end #view-usermanagement -->

</div><!-- end .main -->

<!-- ── Map init ── -->
<script>
const sensorLocations = <?= json_encode(array_map(fn($s) => [
  'id'        => $s['sensor_id'],
  'name'      => $s['location_name'],
  'lat'       => (float)$s['latitude'],
  'lng'       => (float)$s['longitude'],
  'risk'      => strtolower($s['risk_level']),
  'level'     => (int)($s['latest_level'] ?? 0),
  'desc'      => $s['description'],
], $sensors_status)) ?>;

document.addEventListener('DOMContentLoaded', function () {
  if (!sensorLocations.length) return;

  const avgLat = sensorLocations.reduce((s, l) => s + l.lat, 0) / sensorLocations.length;
  const avgLng = sensorLocations.reduce((s, l) => s + l.lng, 0) / sensorLocations.length;

  const map = L.map('sensorMap', { zoomControl: true, scrollWheelZoom: false }).setView([avgLat, avgLng], 13);
  window._leafletMap = map;

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 12
  }).addTo(map);

  const pinColors = { high: '#e74c3c', medium: '#e67e22', low: '#27ae60' };

  sensorLocations.forEach(loc => {
    const color  = pinColors[loc.risk] || '#1b9e9b';
    const status = loc.level >= 60 ? 'Critical' : loc.level >= 30 ? 'Warning' : 'Normal';
    const statusColor = loc.level >= 60 ? '#e74c3c' : loc.level >= 30 ? '#e67e22' : '#27ae60';

    const icon = L.divIcon({
      className: '',
      html: `<div style="
        width:34px;height:34px;border-radius:50% 50% 50% 0;
        background:${color};border:3px solid #fff;
        transform:rotate(-45deg);
        box-shadow:0 2px 8px rgba(0,0,0,0.25);
      "></div>`,
      iconSize: [34, 34],
      iconAnchor: [17, 34],
      popupAnchor: [0, -36]
    });

    const popup = `
      <div style="min-width:180px;">
        <div class="popup-title">${loc.name}</div>
        <div class="popup-row">
          <span>Sensor #${loc.id}</span>
          <span class="popup-badge ${loc.risk}">${loc.risk.charAt(0).toUpperCase()+loc.risk.slice(1)} Risk</span>
        </div>
        <div class="popup-row">
          <span>Movement</span>
          <strong style="color:${statusColor}">${loc.level} — ${status}</strong>
        </div>
        <div class="popup-row" style="margin-top:5px;color:#888;font-size:0.72rem;">${loc.desc}</div>
      </div>`;

    L.marker([loc.lat, loc.lng], { icon }).addTo(map).bindPopup(popup);

    // Pulse circle for critical sensors
    if (loc.level >= 60) {
      L.circle([loc.lat, loc.lng], {
        color: '#e74c3c', fillColor: '#e74c3c',
        fillOpacity: 0.12, radius: 120, weight: 1.5
      }).addTo(map);
    }
  });
});
</script>

<!-- ── Charts ── -->
<script>
Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = '#999';

// ── Line chart ──────────────────────────────
const lineColors = ['#e74c3c','#e67e22','#27ae60','#3498db','#9b59b6'];
const chartHours = <?= json_encode($chart_hours) ?>;
const sensorRaw  = <?= json_encode($sensor_data) ?>;

const datasets = Object.entries(sensorRaw).map(([key, hourMap], i) => {
  const [, name] = key.split('|');
  // legend goes BELOW the chart now — appended after chart renders
  return {
    label: name,
    data: chartHours.map(h => hourMap[h] ?? null),
    borderColor: lineColors[i % lineColors.length],
    backgroundColor: 'transparent',
    tension: 0.4, pointRadius: 3, pointHoverRadius: 6,
    borderWidth: 2, spanGaps: true
  };
});

new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: { labels: chartHours.length ? chartHours : ['No data'], datasets },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
    scales: {
      y: { min:0, max:100, grid:{ color:'#f4f7f6' }, ticks:{ font:{size:10}, maxTicksLimit:5 } },
      x: { grid:{ display:false }, ticks:{ font:{size:10}, maxTicksLimit:7, autoSkip:true } }
    }
  }
});

// Build line legend BELOW the chart
const lineLeg = document.getElementById('lineLegend');
datasets.forEach((ds, i) => {
  lineLeg.innerHTML += `<span class="legend-item">
    <span class="legend-sq" style="background:${lineColors[i % lineColors.length]}"></span>${ds.label}
  </span>`;
});

// ── Doughnut chart ──────────────────────────
// Color map keyed by risk label to ensure correct color regardless of DB order
const riskColorMap = { 'Low':'#27ae60', 'Medium':'#e67e22', 'High':'#e74c3c' };
const riskLabels = <?= json_encode($risk_labels) ?>;
const riskCounts = <?= json_encode($risk_counts) ?>;
const riskColors = riskLabels.map(l => riskColorMap[l] ?? '#ccc');

new Chart(document.getElementById('doughnutChart'), {
  type: 'doughnut',
  data: {
    labels: riskLabels,
    datasets:[{ data: riskCounts, backgroundColor: riskColors, borderWidth: 2, borderColor:'#fff', hoverOffset: 8 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '62%',
    plugins: { legend: { display: false } }
  }
});

// Build doughnut legend BELOW the chart
const riskLeg = document.getElementById('riskLegend');
riskLabels.forEach((l, i) => {
  riskLeg.innerHTML += `<span class="legend-item">
    <span class="legend-sq" style="background:${riskColors[i]}"></span>${l} (${riskCounts[i]})
  </span>`;
});

// ── Bar chart ───────────────────────────────
const treeLabels = <?= json_encode($tree_labels) ?>;
const treeCounts = <?= json_encode($tree_counts) ?>;
const treeColors = ['rgba(27,158,155,.85)','rgba(44,95,93,.85)','rgba(39,174,96,.85)','rgba(52,152,219,.85)','rgba(230,126,34,.85)','rgba(155,89,182,.85)'];

const barLeg = document.getElementById('barLegend');
treeLabels.forEach((n, i) => {
  barLeg.innerHTML += `<span class="legend-item">
    <span class="legend-sq" style="background:${treeColors[i % treeColors.length]}"></span>${n} (${treeCounts[i]})
  </span>`;
});

new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: { labels: treeLabels, datasets:[{ data: treeCounts, backgroundColor: treeColors, borderRadius: 6, borderSkipped: false }] },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero:true, grid:{ color:'#f4f7f6' }, ticks:{ stepSize:1, font:{size:10} } },
      x: { grid:{ display:false }, ticks:{ font:{size:10} } }
    }
  }
});

function updateClock() {
  const d = new Date();
  const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  document.getElementById('clockTime').textContent =
    days[d.getDay()] + ' · ' + d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);
</script>

<!-- ── Add sensor-status badge styles ── -->
<style>
.sensor-status {
  display: inline-block;
  padding: 2px 9px;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
}
.sensor-critical { background: #fdecea; color: #c0392b; }
.sensor-warning  { background: #fef9e7; color: #d35400; }
.sensor-normal   { background: #eafaf1; color: #1e8449; }
</style>

<!-- ── Add User Modal ── -->
<div id="addUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);justify-content:center;align-items:center;z-index:9999;padding:16px;">
  <div class="modal-box">
    <h3>Add New User</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="form-grid">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" required>
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" required>
        </div>
        <div class="form-group full">
          <label>Email</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group full">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <div class="form-group full">
          <label>Role</label>
          <select name="role">
            <option value="user">User</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-danger" onclick="closeAddUserModal()">Cancel</button>
        <button type="submit" class="btn">Add User</button>
      </div>
    </form>
  </div>
</div>

<!-- ── View User Modal ── -->
<div id="viewUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);justify-content:center;align-items:center;z-index:9999;padding:16px;">
  <div class="modal-box">
    <h3>User Details</h3>
    <div class="detail-row"><span class="detail-label">ID</span><span class="detail-value" id="viewUserId"></span></div>
    <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value" id="viewName"></span></div>
    <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value" id="viewEmail"></span></div>
    <div class="detail-row"><span class="detail-label">Role</span><span class="detail-value" id="viewRole"></span></div>
    <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value" id="viewCreated"></span></div>
    <div class="modal-actions">
      <button class="btn btn-danger" onclick="closeViewModal()">Close</button>
      <button class="btn" onclick="openEditFromView()">Edit</button>
    </div>
  </div>
</div>

<!-- ── Edit User Modal ── -->
<div id="editUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);justify-content:center;align-items:center;z-index:9999;padding:16px;">
  <div class="modal-box">
    <h3>Edit User</h3>
    <form method="POST" id="editUserForm">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="editUserId">
      <label>Email</label>
      <input type="email" name="email" id="editEmail" readonly style="background:#f8f8f8;color:#999;">
      <label>New Password <span style="font-size:0.75rem;color:#aaa;">(leave blank to keep current)</span></label>
      <input type="password" name="password" placeholder="Enter new password to reset">
      <label>Role</label>
      <select name="role" id="editRole">
        <option value="user">User</option>
        <option value="manager">Manager</option>
        <option value="admin">Admin</option>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn">Update User</button>
      </div>
    </form>
  </div>
</div>

<!-- ── View switching ── -->
<script>
// On page load — if a POST just happened for user management, stay on that view
const lastAction = '<?= htmlspecialchars($_POST['action'] ?? '') ?>';
const userActions = ['add_user','edit_user','delete_user'];
if (userActions.includes(lastAction)) {
  showView('usermanagement');
} 

function showView(view) {
  // toggle views
  document.getElementById('view-dashboard').style.display      = view === 'dashboard'      ? '' : 'none';
  document.getElementById('view-usermanagement').style.display = view === 'usermanagement' ? '' : 'none';

  // toggle active nav
  document.getElementById('nav-dashboard').classList.toggle('active',      view === 'dashboard');
  document.getElementById('nav-usermanagement').classList.toggle('active', view === 'usermanagement');

  // update page title
  document.getElementById('pageTitle').textContent =
    view === 'dashboard' ? 'Dashboard' : 'User Management';
  document.getElementById('pageWelcome').textContent =
    view === 'dashboard'
      ? 'Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?> · <?= ucfirst($_SESSION['role']) ?>'
      : 'Manage system accounts and roles';

  // invalidate Leaflet map size when switching back to dashboard
  if (view === 'dashboard' && window._leafletMap) {
    setTimeout(() => window._leafletMap.invalidateSize(), 100);
  }
}
</script>

<script src="../assets/js/admin.js"></script>
</body>
</html>