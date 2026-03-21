<?php
session_start();
require_once '../config/config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
  header('Location: ../index.php');
  exit;
}

logActivity($conn, $_SESSION['user_id'], 'VIEW_DASHBOARD', 'Viewed manager dashboard');

// ── POST handlers ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Add location + sensor together
  if ($action === 'add_sensor_location') {
    $loc_name    = trim($_POST['location_name']);
    $lat         = (float)$_POST['latitude'];
    $lng         = (float)$_POST['longitude'];
    $desc        = trim($_POST['description']);
    $sensor_type = trim($_POST['sensor_type']);

    // Insert location first
    $stmt = $conn->prepare("INSERT INTO locations (location_name, latitude, longitude, risk_level, description) VALUES (?,?,?,'Low',?)");
    $stmt->bind_param('sdds', $loc_name, $lat, $lng, $desc);
    $stmt->execute();
    $new_location_id = $conn->insert_id;
    $stmt->close();

    // Insert sensor linked to new location
    $stmt = $conn->prepare("INSERT INTO sensors (location_id, sensor_type) VALUES (?,?)");
    $stmt->bind_param('is', $new_location_id, $sensor_type);
    $stmt->execute();
    $stmt->close();

    logActivity($conn, $_SESSION['user_id'], 'ADD_SENSOR', "Added sensor ($sensor_type) at $loc_name");
    $success = "Sensor and location '$loc_name' added successfully. It will start simulating on the next tick.";
    $return_view = 'sensors';
  }

  // Delete sensor + its location
  if ($action === 'delete_sensor') {
    $sensor_id = (int)$_POST['sensor_id'];
    $loc_id    = (int)$_POST['location_id'];

    $stmt = $conn->prepare("DELETE FROM sensors WHERE sensor_id=?");
    $stmt->bind_param('i', $sensor_id); $stmt->execute(); $stmt->close();

    // Only delete location if no other sensors use it
    $check = $conn->prepare("SELECT COUNT(*) AS c FROM sensors WHERE location_id=?");
    $check->bind_param('i', $loc_id); $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['c'];
    $check->close();

    if ($cnt === 0) {
      $stmt = $conn->prepare("DELETE FROM locations WHERE location_id=?");
      $stmt->bind_param('i', $loc_id); $stmt->execute(); $stmt->close();
    }

    logActivity($conn, $_SESSION['user_id'], 'DELETE_SENSOR', "Deleted sensor ID: $sensor_id");
    $success = "Sensor deleted.";
    $return_view = 'sensors';
  }

  // Add recommendation
  if ($action === 'add_recommendation') {
    $loc_id = (int)$_POST['location_id'];
    $tree   = trim($_POST['tree_name']);
    $reason = trim($_POST['reason']);
    $rec_by = $_SESSION['user_id'];
    $stmt   = $conn->prepare("INSERT INTO tree_recommendations (location_id, tree_name, reason, recommended_by) VALUES (?,?,?,?)");
    $stmt->bind_param('issi', $loc_id, $tree, $reason, $rec_by);
    $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'ADD_RECOMMENDATION', "Added recommendation: $tree");
    $success = "Tree recommendation added.";
    $return_view = 'recommendations';
  }

  // Delete recommendation
  if ($action === 'delete_recommendation') {
    $id = (int)$_POST['recommendation_id'];
    $stmt = $conn->prepare("DELETE FROM tree_recommendations WHERE recommendation_id=?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'DELETE_RECOMMENDATION', "Deleted recommendation ID: $id");
    $success = "Recommendation deleted.";
    $return_view = 'recommendations';
  }
}

// ── Fetch data ─────────────────────────────────

// Dashboard sensors
$sensors_dash = $conn->query("
  SELECT s.sensor_id, s.sensor_type, l.location_name, l.latitude, l.longitude,
         (SELECT sd.movement_level FROM simulation_data sd
          WHERE sd.sensor_id = s.sensor_id
          ORDER BY sd.timestamp DESC LIMIT 1) AS last_movement
  FROM sensors s
  JOIN locations l ON s.location_id = l.location_id
  ORDER BY s.sensor_id ASC
")->fetch_all(MYSQLI_ASSOC);

$total = count($sensors_dash);
$atRisk = 0; $critical = 0;
foreach ($sensors_dash as $s) {
  $m = (float)($s['last_movement'] ?? 0);
  if ($m >= 30) $atRisk++;
  if ($m >= 60) $critical++;
}

// Recent alerts
$alerts = $conn->query("
  SELECT sd.movement_level, sd.timestamp, l.location_name, s.sensor_id
  FROM simulation_data sd
  JOIN sensors s   ON sd.sensor_id   = s.sensor_id
  JOIN locations l ON s.location_id  = l.location_id
  WHERE sd.movement_level >= 60
  ORDER BY sd.timestamp DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Sensors list with location info
$sensors_list = $conn->query("
  SELECT s.sensor_id, s.sensor_type, s.created_at,
         l.location_id, l.location_name, l.latitude, l.longitude, l.description,
         (SELECT sd.movement_level FROM simulation_data sd
          WHERE sd.sensor_id = s.sensor_id
          ORDER BY sd.timestamp DESC LIMIT 1) AS last_movement
  FROM sensors s
  JOIN locations l ON s.location_id = l.location_id
  ORDER BY s.sensor_id ASC
")->fetch_all(MYSQLI_ASSOC);

// Recommendations
$recommendations = $conn->query("
  SELECT tr.*, l.location_name, l.location_id,
         CONCAT(u.first_name,' ',u.last_name) AS rec_by_name
  FROM tree_recommendations tr
  JOIN locations l ON tr.location_id = l.location_id
  JOIN users u     ON tr.recommended_by = u.user_id
  ORDER BY tr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Locations dropdown for recommendations
$locations_dropdown = $conn->query("
  SELECT l.location_id, l.location_name, s.sensor_id, s.sensor_type
  FROM locations l
  LEFT JOIN sensors s ON s.location_id = l.location_id
  ORDER BY l.location_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Group sensors by location for recommendation auto-fill
$sensors_by_location = [];
foreach ($locations_dropdown as $row) {
  if ($row['sensor_id']) {
    $sensors_by_location[$row['location_id']][] = [
      'sensor_id'   => $row['sensor_id'],
      'sensor_type' => $row['sensor_type'],
    ];
  }
}
$unique_locations = [];
$seen = [];
foreach ($locations_dropdown as $row) {
  if (!in_array($row['location_id'], $seen)) {
    $unique_locations[] = ['location_id' => $row['location_id'], 'location_name' => $row['location_name']];
    $seen[] = $row['location_id'];
  }
}

// All locations with coords for JS
$all_locations_js = $conn->query("SELECT location_id, location_name, latitude, longitude FROM locations")->fetch_all(MYSQLI_ASSOC);

// ── Helpers ────────────────────────────────────
function riskFromLevel(?float $lvl): string {
  if ($lvl === null || $lvl < 30) return 'low';
  if ($lvl < 60) return 'medium';
  return 'high';
}
function riskLabel(string $r): string {
  return match($r) { 'high'=>'Critical', 'medium'=>'Warning', default=>'Normal' };
}
function levelColor(?float $lvl): string {
  if ($lvl === null || $lvl < 30) return '#27ae60';
  if ($lvl < 60) return '#e67e22';
  return '#e74c3c';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Dashboard — EcoProtean</title>
  <link rel="stylesheet" href="../assets/css/management.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<button class="menu-toggle" id="menuToggle">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar">
  <div class="brand">
    <span class="brand-name">EcoProtean</span>
    <span class="brand-role">Manager Panel</span>
  </div>
  <div class="nav-section">Menu</div>
  <nav class="sidenav">
    <a href="#" id="nav-dashboard"       onclick="showView('dashboard')"       class="active">📊 Dashboard</a>
    <a href="#" id="nav-sensors"         onclick="showView('sensors')">📡 Sensors</a>
    <a href="#" id="nav-recommendations" onclick="showView('recommendations')">🌳 Recommendations</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></span>
      <span class="user-role"><?= ucfirst($_SESSION['role']) ?></span>
    </div>
    <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</aside>

<main class="main-content">

  <div class="topbar">
    <div>
      <div class="page-title" id="pageTitle">Dashboard</div>
      <div class="page-sub"   id="pageSub">Tree Monitoring — Live Data</div>
    </div>
    <div class="live-indicator">
      <span class="live-dot"></span>
      Live · updates every 5s
    </div>
  </div>

  <?php if (!empty($success)): ?>
    <div class="alert-msg success" id="successMessage"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert-msg error" id="errorMessage"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ════════════════════════════════════ -->
  <!-- VIEW: DASHBOARD                     -->
  <!-- ════════════════════════════════════ -->
  <div id="view-dashboard">

    <div id="alertBanner" class="<?= $critical > 0 ? 'critical-banner' : 'all-clear' ?>">
      <?php if ($critical > 0): ?>
        <div class="pulse"></div>
        <strong><?= $critical ?> sensor<?= $critical > 1 ? 's' : '' ?> critical</strong>
        — movement ≥ 60 detected.
      <?php else: ?>
        ✅ All clear — no critical sensor readings at this time.
      <?php endif; ?>
    </div>

    <div class="kpi">
      <div class="card">
        <div class="card-label">Total Sensors</div>
        <div class="card-value" id="totalSensors"><?= $total ?></div>
      </div>
      <div class="card card-warning">
        <div class="card-label">At Risk (≥ 30)</div>
        <div class="card-value" id="atRisk"><?= $atRisk ?></div>
      </div>
      <div class="card card-danger">
        <div class="card-label">Critical (≥ 60)</div>
        <div class="card-value" id="critical"><?= $critical ?></div>
      </div>
    </div>

    <div class="two-col">
      <div class="section-box">
        <div class="section-header">
          <h2>🗺 Live Sensor Map</h2>
          <span class="muted-text" id="lastUpdate">Connecting...</span>
        </div>
        <div id="map"></div>
        <div class="map-legend">
          <span class="map-legend-item"><span class="map-legend-dot" style="background:#27ae60"></span>Normal (&lt;30)</span>
          <span class="map-legend-item"><span class="map-legend-dot" style="background:#e67e22"></span>Warning (30–59)</span>
          <span class="map-legend-item"><span class="map-legend-dot" style="background:#e74c3c"></span>Critical (≥60)</span>
        </div>
      </div>
      <div class="section-box" style="display:flex;flex-direction:column;">
        <div class="section-header">
          <h2>🚨 Recent Alerts</h2>
          <span class="muted-text">movement ≥ 60</span>
        </div>
        <ul id="alerts" style="flex:1;">
          <?php foreach ($alerts as $a): ?>
            <li class="alert-item">
              <div>
                <strong><?= htmlspecialchars($a['location_name']) ?></strong>
                <span class="sensor-chip" style="margin-left:5px;">S<?= str_pad($a['sensor_id'],2,'0',STR_PAD_LEFT) ?></span>
                <div style="margin-top:3px;font-size:0.77rem;color:#888;">
                  Movement: <strong style="color:#c0392b;"><?= $a['movement_level'] ?>/100</strong>
                </div>
              </div>
              <span class="alert-time"><?= date('M d H:i', strtotime($a['timestamp'])) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (empty($alerts)): ?>
            <li class="no-alerts">No critical alerts yet.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="section-box">
      <div class="section-header">
        <h2>📡 Sensor Monitoring</h2>
        <span class="muted-text">auto-refreshes every 5s</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Sensor</th><th>Location</th><th>Type</th>
              <th style="width:200px;">Movement Level</th>
              <th>Cause</th><th>Status</th>
            </tr>
          </thead>
          <tbody id="sensorTable">
            <?php foreach ($sensors_dash as $s):
              $lvl   = (int)($s['last_movement'] ?? 0);
              $risk  = riskFromLevel($lvl);
              $color = levelColor($lvl);
              $cause = $lvl < 30 ? 'Wind' : ($lvl < 60 ? 'Rain / Soil Softening' : 'Ground Instability');
            ?>
              <tr>
                <td><span class="sensor-chip">S<?= str_pad($s['sensor_id'],2,'0',STR_PAD_LEFT) ?></span></td>
                <td><?= htmlspecialchars($s['location_name']) ?></td>
                <td><?= htmlspecialchars($s['sensor_type']) ?></td>
                <td>
                  <div class="movement-wrap">
                    <div class="movement-bar-bg">
                      <div class="movement-bar-fill" style="width:<?= $lvl ?>%;background:<?= $color ?>;"></div>
                    </div>
                    <span class="movement-val" style="color:<?= $color ?>;"><?= $lvl ?></span>
                  </div>
                </td>
                <td><span class="cause-tag"><?= $cause ?></span></td>
                <td><span class="risk-badge risk-<?= $risk ?>"><?= riskLabel($risk) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- end #view-dashboard -->

  <!-- ════════════════════════════════════ -->
  <!-- VIEW: SENSORS                       -->
  <!-- ════════════════════════════════════ -->
  <div id="view-sensors" style="display:none;">

    <div class="two-col">

      <!-- Left: Add sensor + map -->
      <div class="section-box">
        <div class="section-header">
          <h2>➕ Add New Sensor</h2>
        </div>
        <p class="map-hint">📍 Click anywhere on the map to set the sensor location. The name will auto-fill — you can edit it.</p>

        <div id="sensorPickerMap"></div>

        <form method="POST" style="margin-top:16px;">
          <input type="hidden" name="action" value="add_sensor_location">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="form-group">
              <label>Latitude</label>
              <input type="text" name="latitude" id="sensorLat" readonly required placeholder="Click map">
            </div>
            <div class="form-group">
              <label>Longitude</label>
              <input type="text" name="longitude" id="sensorLng" readonly required placeholder="Click map">
            </div>
          </div>

          <div class="form-group">
            <label>Location Name <span class="muted-text">(auto-filled, editable)</span></label>
            <input type="text" name="location_name" id="sensorLocName" required placeholder="Click the map first...">
          </div>

          <div class="form-group">
            <label>Sensor Type</label>
            <input type="text" name="sensor_type" required placeholder="e.g. Motion, Gyroscope, Vibration">
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" required placeholder="Describe this monitoring site..."></textarea>
          </div>

          <div id="geocodeStatus" class="geocode-status" style="display:none;"></div>

          <div style="margin-top:14px;">
            <button type="submit" class="btn" id="addSensorBtn" disabled>Add Sensor</button>
            <span class="muted-text" style="margin-left:10px;font-size:0.75rem;" id="mapClickHint">Click the map to enable</span>
          </div>
        </form>
      </div>

      <!-- Right: Sensors list -->
      <div class="section-box">
        <div class="section-header">
          <h2>📡 All Sensors</h2>
          <span class="muted-text"><?= count($sensors_list) ?> total</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>ID</th><th>Type</th><th>Location</th><th>Last Level</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($sensors_list as $s):
                $lvl   = (int)($s['last_movement'] ?? 0);
                $color = levelColor($lvl);
                $risk  = riskFromLevel($lvl);
              ?>
                <tr>
                  <td><span class="sensor-chip">S<?= str_pad($s['sensor_id'],2,'0',STR_PAD_LEFT) ?></span></td>
                  <td><?= htmlspecialchars($s['sensor_type']) ?></td>
                  <td>
                    <?= htmlspecialchars($s['location_name']) ?>
                    <div class="muted-text" style="font-size:0.68rem;"><?= number_format($s['latitude'],4) ?>, <?= number_format($s['longitude'],4) ?></div>
                  </td>
                  <td>
                    <span style="font-weight:600;color:<?= $color ?>;"><?= $s['last_movement'] ?? '—' ?></span>
                    <span class="risk-badge risk-<?= $risk ?>" style="margin-left:4px;"><?= riskLabel($risk) ?></span>
                  </td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Delete this sensor and its location?')">
                      <input type="hidden" name="action" value="delete_sensor">
                      <input type="hidden" name="sensor_id" value="<?= $s['sensor_id'] ?>">
                      <input type="hidden" name="location_id" value="<?= $s['location_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div><!-- end #view-sensors -->

  <!-- ════════════════════════════════════ -->
  <!-- VIEW: RECOMMENDATIONS              -->
  <!-- ════════════════════════════════════ -->
  <div id="view-recommendations" style="display:none;">

    <div class="two-col">

      <div class="section-box">
        <div class="section-header"><h2>➕ Add Recommendation</h2></div>
        <form method="POST">
          <input type="hidden" name="action" value="add_recommendation">
          <div class="form-group">
            <label>Location</label>
            <select name="location_id" required onchange="loadSensorsForLocation(this.value)">
              <option value="">— Select location —</option>
              <?php foreach ($unique_locations as $loc): ?>
                <option value="<?= $loc['location_id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" id="sensorsForLocGroup" style="display:none;">
            <label>Sensors at this location</label>
            <div id="sensorsForLocList" class="sensor-list-preview"></div>
          </div>

          <div class="form-group">
            <label>Tree Name</label>
            <input type="text" name="tree_name" required placeholder="e.g. Narra, Bamboo, Acacia">
          </div>
          <div class="form-group">
            <label>Reason</label>
            <textarea name="reason" rows="4" required placeholder="Why is this tree recommended for this location?"></textarea>
          </div>
          <div style="margin-top:14px;">
            <button type="submit" class="btn">Add Recommendation</button>
          </div>
        </form>
      </div>

      <div class="section-box">
        <div class="section-header">
          <h2>🌳 All Recommendations</h2>
          <span class="muted-text"><?= count($recommendations) ?> total</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Tree</th><th>Location</th><th>By</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recommendations as $rec): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($rec['tree_name']) ?></strong>
                    <div class="muted-text" style="font-size:0.72rem;"><?= htmlspecialchars(substr($rec['reason'],0,50)) ?>…</div>
                  </td>
                  <td><?= htmlspecialchars($rec['location_name']) ?></td>
                  <td><?= htmlspecialchars($rec['rec_by_name']) ?></td>
                  <td class="muted-text"><?= date('M d, Y', strtotime($rec['created_at'])) ?></td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Delete this recommendation?')">
                      <input type="hidden" name="action" value="delete_recommendation">
                      <input type="hidden" name="recommendation_id" value="<?= $rec['recommendation_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div><!-- end #view-recommendations -->

</main>

<script>
const sensorsByLocation = <?= json_encode($sensors_by_location) ?>;
const allLocations      = <?= json_encode(array_map(fn($l) => [
  'id'   => (int)$l['location_id'],
  'name' => $l['location_name'],
  'lat'  => (float)$l['latitude'],
  'lng'  => (float)$l['longitude'],
], $all_locations_js)) ?>;
const returnView = '<?= htmlspecialchars($return_view ?? 'dashboard') ?>';
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/management.js"></script>
</body>
</html>