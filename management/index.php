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

    $stmt = $conn->prepare("INSERT INTO locations (location_name, latitude, longitude, risk_level, description) VALUES (?,?,?,'Low',?)");
    $stmt->bind_param('sdds', $loc_name, $lat, $lng, $desc);
    $stmt->execute();
    $new_location_id = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO sensors (location_id, sensor_type) VALUES (?,?)");
    $stmt->bind_param('is', $new_location_id, $sensor_type);
    $stmt->execute();
    $stmt->close();

    logActivity($conn, $_SESSION['user_id'], 'ADD_SENSOR', "Added sensor ($sensor_type) at $loc_name");
    $success     = "Sensor and location '$loc_name' added successfully.";
    $return_view = 'sensors';
  }

  // Delete sensor + its location
  if ($action === 'delete_sensor') {
    $sensor_id = (int)$_POST['sensor_id'];
    $loc_id    = (int)$_POST['location_id'];

    $stmt = $conn->prepare("DELETE FROM sensors WHERE sensor_id=?");
    $stmt->bind_param('i', $sensor_id); $stmt->execute(); $stmt->close();

    $check = $conn->prepare("SELECT COUNT(*) AS c FROM sensors WHERE location_id=?");
    $check->bind_param('i', $loc_id); $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['c'];
    $check->close();

    if ($cnt === 0) {
      $stmt = $conn->prepare("DELETE FROM locations WHERE location_id=?");
      $stmt->bind_param('i', $loc_id); $stmt->execute(); $stmt->close();
    }

    logActivity($conn, $_SESSION['user_id'], 'DELETE_SENSOR', "Deleted sensor ID: $sensor_id");
    $success     = "Sensor deleted.";
    $return_view = 'sensors';
  }
}

// ── Fetch data ─────────────────────────────────

$sensor_requests = $conn->query("
  SELECT
    sr.request_id, sr.location_id, sr.reason, sr.intended_use,
    sr.date_range, sr.custom_from, sr.custom_to,
    sr.fields, sr.interval_type, sr.format_pref,
    sr.status, sr.rejection_remarks,
    sr.requested_at, sr.reviewed_at,
    l.location_name,
    CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
    u.email AS requester_email,
    CONCAT(rv.first_name, ' ', rv.last_name) AS reviewed_by_name
  FROM sensor_requests sr
  JOIN locations l  ON sr.location_id = l.location_id
  JOIN users u      ON sr.user_id     = u.user_id
  LEFT JOIN users rv ON sr.reviewed_by = rv.user_id
  ORDER BY
    CASE sr.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
    sr.requested_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pending_count = count(array_filter($sensor_requests, fn($r) => $r['status'] === 'pending'));

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

$alerts = $conn->query("
  SELECT sd.movement_level, sd.timestamp, l.location_name, s.sensor_id
  FROM simulation_data sd
  JOIN sensors s   ON sd.sensor_id  = s.sensor_id
  JOIN locations l ON s.location_id = l.location_id
  WHERE sd.movement_level >= 60
  ORDER BY sd.timestamp DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

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

$all_locations_js = $conn->query("
  SELECT location_id, location_name, latitude, longitude
  FROM locations
")->fetch_all(MYSQLI_ASSOC);

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
    <a href="#" id="nav-dashboard" onclick="showView('dashboard')" class="active">📊 Dashboard</a>
    <a href="#" id="nav-sensors"   onclick="showView('sensors')">📡 Sensors</a>
    <a href="#" id="nav-requests"  onclick="showView('requests')">
      📬 Sensor Requests
      <?php if ($pending_count > 0): ?>
        <span class="nav-badge" id="navRequestsBadge"><?= $pending_count ?></span>
      <?php endif; ?>
    </a>
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
      <div class="page-sub"   id="pageSub">Sensor Monitoring — Live Data</div>
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

  <!-- VIEW: DASHBOARD -->
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

  <!-- VIEW: SENSORS -->
  <div id="view-sensors" style="display:none;">
    <div class="two-col">
      <div class="section-box">
        <div class="section-header">
          <h2>➕ Add New Sensor</h2>
        </div>
        <p class="map-hint">📍 Click anywhere on the map to set the sensor location.</p>
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

  <!-- VIEW: SENSOR REQUESTS -->
  <div id="view-requests" style="display:none;">
    <div class="section-box">
      <div class="section-header">
        <h2>📬 Sensor Data Requests</h2>
        <span class="muted-text">
          <?= $pending_count ?> pending · <?= count($sensor_requests) ?> total
        </span>
      </div>

      <?php if (empty($sensor_requests)): ?>
        <div style="text-align:center;padding:40px;color:#aaa;font-size:0.9rem;">
          No requests yet.
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Requester</th>
                <th>Location</th>
                <th>Reason</th>
                <th>Data Preferences</th>
                <th>Requested</th>
                <th class="req-status-cell">Status</th>
                <th class="req-action-cell">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sensor_requests as $r):
                $isPending  = $r['status'] === 'pending';
                $badgeClass = match($r['status']) {
                  'approved' => 'risk-low',
                  'rejected' => 'risk-high',
                  default    => 'risk-medium'
                };
                $badgeIcon = match($r['status']) {
                  'approved' => '✅',
                  'rejected' => '❌',
                  default    => '⏳'
                };
                // Encode full row for JS modal
                $rowJson = htmlspecialchars(json_encode($r), ENT_QUOTES);
              ?>
                <tr data-request-id="<?= $r['request_id'] ?>">
                  <td><span class="sensor-chip">#<?= $r['request_id'] ?></span></td>
                  <td>
                    <strong><?= htmlspecialchars($r['requester_name']) ?></strong>
                    <div class="muted-text" style="font-size:0.7rem;">
                      <?= htmlspecialchars($r['requester_email']) ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($r['location_name']) ?></td>
                  <td>
                    <div style="max-width:160px;font-size:0.8rem;color:#555;">
                      <?= htmlspecialchars($r['reason']) ?>
                    </div>
                  </td>
                  <td style="font-size:0.78rem;color:#555;">
                    <?php
                      $rangeMap = [
                        'last_7_days'  => 'Last 7 days',
                        'last_30_days' => 'Last 30 days',
                        'last_90_days' => 'Last 90 days',
                        'custom'       => ($r['custom_from'] ?? '?') . ' → ' . ($r['custom_to'] ?? '?'),
                      ];
                      $intMap = ['raw'=>'Every reading','hourly'=>'Hourly avg','daily'=>'Daily summary'];
                    ?>
                    <div>📅 <?= $rangeMap[$r['date_range']] ?? $r['date_range'] ?></div>
                    <div>🕐 <?= $intMap[$r['interval_type']] ?? $r['interval_type'] ?></div>
                  </td>
                  <td class="muted-text" style="font-size:0.78rem;white-space:nowrap;">
                    <?= date('M d, Y H:i', strtotime($r['requested_at'])) ?>
                  </td>
                  <td class="req-status-cell">
                    <span class="risk-badge <?= $badgeClass ?>">
                      <?= $badgeIcon ?> <?= ucfirst($r['status']) ?>
                    </span>
                    <?php if (!$isPending && $r['reviewed_by_name']): ?>
                      <div class="muted-text" style="font-size:0.68rem;margin-top:3px;">
                        by <?= htmlspecialchars($r['reviewed_by_name']) ?><br>
                        <?= date('M d H:i', strtotime($r['reviewed_at'])) ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'rejected' && !empty($r['rejection_remarks'])): ?>
                      <div style="font-size:0.7rem;color:#c0392b;margin-top:3px;font-style:italic;">
                        "<?= htmlspecialchars($r['rejection_remarks']) ?>"
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="req-action-cell">
                    <?php if ($isPending): ?>
                      <button class="btn btn-sm"
                        onclick="openReviewModal(<?= $rowJson ?>)">
                        🔍 Review
                      </button>
                    <?php else: ?>
                      <span class="muted-text" style="font-size:0.78rem;">Reviewed</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div><!-- end #view-requests -->

</main>

<script>
const allLocations = <?= json_encode(array_map(fn($l) => [
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