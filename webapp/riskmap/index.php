<?php
session_start();
require_once '../../config/config.php';

if (!empty($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'VIEW_RISKMAP', 'Viewed risk map');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Risk Map — EcoProtean</title>
  <link rel="stylesheet" href="../../assets/css/riskmap.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>

    /* ── Notification Banner ── */
    .eco-notification {
      position: fixed;
      top: 80px; left: 50%;
      transform: translateX(-50%);
      z-index: 9999;
      padding: 14px 24px;
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.88rem; font-weight: 500;
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
      display: none; max-width: 480px; text-align: center;
    }
    .eco-notification.success { background:#e8f8f5; color:#1e8449; border:1px solid #a9dfbf; }
    .eco-notification.error   { background:#fdecea; color:#c0392b; border:1px solid #f5b7b1; }

    /* ── My Requests Button ── */
    .my-requests-btn-wrap {
      position: fixed; bottom: 30px; left: 30px;
      z-index: 1000;
      display: <?= !empty($_SESSION['user_id']) ? 'block' : 'none' ?>;
    }
    .my-requests-btn {
      background: linear-gradient(135deg, #2c5f5d, #1b9e9b);
      color: #fff; border: none;
      padding: 12px 20px; border-radius: 30px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.88rem; font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(0,0,0,0.2);
      display: flex; align-items: center; gap: 8px;
      transition: opacity 0.2s;
    }
    .my-requests-btn:hover { opacity: 0.88; }
    .my-requests-badge {
      background: #e74c3c; color: #fff;
      border-radius: 50%; width: 20px; height: 20px;
      font-size: 0.72rem; font-weight: 700;
      display: none; align-items: center; justify-content: center;
    }

    /* ── My Requests Panel ── */
    .my-requests-panel {
      position: fixed; bottom: 90px; left: 30px;
      width: 340px; max-height: 480px;
      background: #fff; border-radius: 14px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18);
      z-index: 1000; display: flex; flex-direction: column;
      font-family: 'Poppins', sans-serif;
      transform: translateY(20px); opacity: 0;
      pointer-events: none;
      transition: transform 0.25s ease, opacity 0.25s ease;
    }
    .my-requests-panel.open { transform: translateY(0); opacity: 1; pointer-events: all; }
    .panel-header {
      padding: 16px 20px; border-bottom: 1px solid #f0f4f3;
      display: flex; justify-content: space-between; align-items: center;
    }
    .panel-header h3 { font-size: 1rem; color: #2c5f5d; font-weight: 600; margin: 0; }
    .panel-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #aaa; padding: 0 4px; }
    .panel-close:hover { color: #333; }
    .panel-body { overflow-y: auto; flex: 1; padding: 12px 16px; }

    /* ── Request Items ── */
    .req-item { background: #f9fafb; border-radius: 10px; padding: 14px; margin-bottom: 10px; border-left: 4px solid #ccc; }
    .req-item.req-pending  { border-left-color: #e67e22; }
    .req-item.req-approved { border-left-color: #27ae60; }
    .req-item.req-rejected { border-left-color: #e74c3c; }
    .req-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .req-location { font-weight: 600; font-size: 0.88rem; color: #2c5f5d; }
    .req-status-badge { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
    .req-badge-pending  { background: #fef9e7; color: #d35400; }
    .req-badge-approved { background: #e8f8f5; color: #1e8449; }
    .req-badge-rejected { background: #fdecea; color: #c0392b; }
    .req-reason  { font-size: 0.78rem; color: #666; margin-bottom: 4px; font-style: italic; }
    .req-date    { font-size: 0.72rem; color: #aaa; }
    .req-reviewed{ font-size: 0.75rem; color: #888; margin-top: 4px; }
    .req-prefs   { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0 4px; }
    .req-prefs span { font-size: 0.72rem; background: #f0f4f3; color: #2c5f5d; padding: 2px 8px; border-radius: 6px; font-weight: 500; }
    .req-remarks { font-size: 0.78rem; color: #c0392b; background: #fdecea; border-radius: 6px; padding: 6px 10px; margin-top: 6px; }
    .req-view-btn {
      margin-top: 10px;
      background: linear-gradient(135deg, #2c5f5d, #1b9e9b);
      color: #fff; border: none; padding: 7px 14px;
      border-radius: 8px; font-family: 'Poppins', sans-serif;
      font-size: 0.78rem; font-weight: 600;
      cursor: pointer; width: 100%; transition: opacity 0.2s;
    }
    .req-view-btn:hover { opacity: 0.88; }
    .req-loading, .req-empty, .req-error { text-align: center; padding: 24px; color: #aaa; font-size: 0.85rem; }

    /* ── Modal Base ── */
    .eco-modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.55);
      z-index: 9999; display: none;
      justify-content: center; align-items: center;
      padding: 16px;
    }
    .eco-modal {
      background: #fff; border-radius: 16px;
      padding: 28px; width: 100%; max-width: 480px;
      max-height: 90vh; overflow-y: auto;
      position: relative;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
      font-family: 'Poppins', sans-serif;
    }
    .eco-modal-close { position: absolute; top: 16px; right: 20px; background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #aaa; line-height: 1; }
    .eco-modal-close:hover { color: #333; }
    .eco-modal-icon  { font-size: 2rem; text-align: center; margin-bottom: 10px; }
    .eco-modal h2    { text-align: center; font-size: 1.1rem; color: #2c5f5d; margin-bottom: 6px; }
    .eco-modal p     { text-align: center; font-size: 0.83rem; color: #888; margin-bottom: 16px; }
    .eco-modal-location { text-align: center; font-weight: 600; color: #1b9e9b; font-size: 0.92rem; margin-bottom: 16px; }
    .eco-form-group  { margin-bottom: 14px; }
    .eco-form-group label { display: block; font-size: 0.83rem; font-weight: 500; color: #555; margin-bottom: 6px; }
    .eco-form-group textarea {
      width: 100%; padding: 10px 12px;
      border: 2px solid #e0e0e0; border-radius: 8px;
      font-family: 'Poppins', sans-serif; font-size: 0.88rem;
      resize: vertical; min-height: 72px; outline: none;
      transition: border-color 0.2s; box-sizing: border-box;
    }
    .eco-form-group textarea:focus { border-color: #1b9e9b; }
    .eco-modal-error { background: #fdecea; color: #c0392b; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; margin-bottom: 14px; }
    .eco-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
    .eco-modal-btn { padding: 10px 20px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; transition: opacity 0.2s; text-decoration: none; display: inline-block; }
    .eco-modal-btn.primary  { background: linear-gradient(135deg, #2c5f5d, #1b9e9b); color: #fff; }
    .eco-modal-btn.secondary{ background: #f0f4f3; color: #555; }
    .eco-modal-btn:hover    { opacity: 0.88; }
    .eco-modal-btn:disabled { opacity: 0.6; cursor: not-allowed; }

    /* ── Step indicator ── */
    .eco-steps { display: flex; align-items: center; justify-content: center; margin-bottom: 18px; }
    .eco-step-item { display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .eco-step-dot { width: 28px; height: 28px; border-radius: 50%; font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .eco-step-dot.active  { background: #1b9e9b; color: #fff; }
    .eco-step-dot.done    { background: #27ae60; color: #fff; }
    .eco-step-dot.inactive{ background: #e0e0e0; color: #aaa; }
    .eco-step-label { font-size: 0.72rem; color: #888; }
    .eco-step-line  { flex: 1; height: 2px; background: #e0e0e0; margin: 0 10px 20px; min-width: 40px; }

    /* ── Form controls ── */
    .eco-select, .eco-input {
      width: 100%; padding: 10px 12px;
      border: 2px solid #e0e0e0; border-radius: 8px;
      font-family: 'Poppins', sans-serif; font-size: 0.88rem;
      outline: none; transition: border-color 0.2s;
      box-sizing: border-box; background: #fff;
    }
    .eco-select:focus, .eco-input:focus { border-color: #1b9e9b; }

    /* ── Checkbox group ── */
    .eco-checkbox-group { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .eco-checkbox-item {
      display: flex; align-items: center; gap: 8px;
      background: #f7faf9; border-radius: 8px; padding: 8px 12px;
      cursor: pointer; font-size: 0.83rem; color: #444;
      border: 2px solid transparent; transition: border-color 0.15s;
    }
    .eco-checkbox-item:has(input:checked) { border-color: #1b9e9b; background: #e8f4f3; }
    .eco-checkbox-item input { accent-color: #1b9e9b; }

    /* ── History Modal ── */
    .hist-modal-inner { max-width: 620px; }
    .hist-loading, .hist-empty, .hist-error { text-align: center; padding: 32px; color: #aaa; font-size: 0.88rem; }
    .hist-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
    .hist-range-info { font-size: 0.83rem; color: #666; }
    .hist-csv-btn {
      background: linear-gradient(135deg, #2c5f5d, #1b9e9b);
      color: #fff; border: none; padding: 8px 16px;
      border-radius: 8px; font-family: 'Poppins', sans-serif;
      font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s;
    }
    .hist-csv-btn:hover { opacity: 0.88; }
    .hist-latest { background: #f7faf9; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; }
    .hist-latest-label { font-size: 0.75rem; font-weight: 600; color: #999; text-transform: uppercase; margin-bottom: 10px; }
    .hist-gauge { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .hist-gauge-bar { flex: 1; height: 14px; background: #e8f0ef; border-radius: 8px; overflow: hidden; }
    .hist-gauge-fill { height: 100%; border-radius: 8px; transition: width 0.4s ease; }
    .hist-gauge-val { font-size: 1rem; font-weight: 700; min-width: 52px; text-align: right; }
    .hist-latest-risk { display: flex; align-items: center; gap: 10px; }
    .hist-latest-time { font-size: 0.75rem; color: #aaa; }
    .hist-chart-wrap { margin-bottom: 20px; }
    .hist-table-title { font-size: 0.8rem; font-weight: 600; color: #999; text-transform: uppercase; margin-bottom: 10px; }
    .hist-interval-badge { background: #e8f4f3; color: #2c5f5d; font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 6px; margin-left: 8px; }
    .hist-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .hist-table th { background: #f7faf9; color: #2c5f5d; font-weight: 600; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e8f0ef; }
    .hist-table td { padding: 8px 10px; border-bottom: 1px solid #f0f4f3; vertical-align: middle; }
    .hist-table tr:last-child td { border-bottom: none; }
    .hist-num { color: #ccc; font-size: 0.75rem; }
    .hist-bar-wrap { display: flex; align-items: center; gap: 8px; }
    .hist-bar-bg { flex: 1; height: 8px; background: #e8f0ef; border-radius: 4px; overflow: hidden; min-width: 80px; }
    .hist-bar-fill { height: 100%; border-radius: 4px; }
    .hist-bar-val { font-weight: 600; font-size: 0.82rem; min-width: 28px; }
    .hist-time { color: #aaa; font-size: 0.75rem; }
    .cause-chip { font-size: 0.75rem; background: #f0f4f3; color: #555; padding: 2px 8px; border-radius: 6px; }

    /* ── Risk badges ── */
    .eco-risk-badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
    .eco-risk-badge.high   { background: #fdecea; color: #c0392b; }
    .eco-risk-badge.medium { background: #fef9e7; color: #d35400; }
    .eco-risk-badge.low    { background: #e8f8f1; color: #1e8449; }

  </style>
</head>
<body>

  <!-- ── Header ── -->
  <header>
    <nav>
      <div class="logo">
        <img src="../../assets/images/logo.png" alt="EcoProtean Logo">
        <div class="logo-content">
          <span class="logo-text">EcoProtean</span>
          <span class="tagline">Guarding the Land, Growing the Future</span>
        </div>
      </div>
      <ul>
        <li><a class="active" href="/ecoprotean/webapp/riskmap/index.php">Risk Map</a></li>
        <li><a href="/ecoprotean/webapp/about/index.php">About</a></li>
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin','manager'])): ?>
          <li><a href="/ecoprotean/admin/index.php">Admin</a></li>
        <?php endif; ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <li><a href="/ecoprotean/auth/logout.php">Logout</a></li>
        <?php else: ?>
          <li><a href="/ecoprotean/auth/login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <!-- ── Notification Banner ── -->
  <div id="notificationBanner" class="eco-notification"></div>

  <!-- ── Map status bar ── -->
  <div class="map-statusbar">
    <div class="statusbar-left">
      <span class="live-dot"></span>
      <span id="mapStatus">Loading sensor data...</span>
    </div>
    <div class="statusbar-right">
      <span class="stat-pill" id="statTotal">— sensors</span>
      <span class="stat-pill warning" id="statWarning">— warning</span>
      <span class="stat-pill danger"  id="statCritical">— critical</span>
    </div>
  </div>

  <!-- ── Map ── -->
  <div class="map-container">
    <div id="map"></div>
  </div>

  <!-- ── My Requests Button (logged-in users only) ── -->
  <?php if (!empty($_SESSION['user_id'])): ?>
  <div class="my-requests-btn-wrap">
    <button class="my-requests-btn" id="myRequestsBtn">
      📋 My Requests
      <span class="my-requests-badge" id="myRequestsBadge"></span>
    </button>
  </div>

  <!-- ── My Requests Panel ── -->
  <div class="my-requests-panel" id="myRequestsPanel">
    <div class="panel-header">
      <h3>📋 My Sensor Requests</h3>
      <button class="panel-close" id="myRequestsClose">&times;</button>
    </div>
    <div class="panel-body">
      <div id="myRequestsList">
        <div class="req-empty">Click to load your requests.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Login Modal (guests) ── -->
  <div id="loginModal" class="eco-modal-overlay" style="display:none;">
    <div class="eco-modal">
      <button class="eco-modal-close" id="modalClose">&times;</button>
      <div class="eco-modal-icon">🔒</div>
      <h2>You are not logged in</h2>
      <p>Login to request sensor data and view sensor history.</p>
      <div class="eco-modal-actions">
        <a href="/ecoprotean/auth/login.php" class="eco-modal-btn primary">Yes, Login</a>
        <button class="eco-modal-btn secondary" id="modalCancel">Cancel</button>
      </div>
    </div>
  </div>

  <?php if (!empty($_SESSION['user_id'])): ?>

  <!-- ── Request Modal (two-step) ── -->
  <div id="requestModal" class="eco-modal-overlay" style="display:none;">
    <div class="eco-modal" style="max-width:480px;">
      <button class="eco-modal-close" id="requestModalClose">&times;</button>
      <div class="eco-modal-icon">🔬</div>
      <h2>Request Sensor Data</h2>
      <p>A manager will review your request before you can access the data.</p>
      <div class="eco-modal-location">📍 <span id="requestLocationName"></span></div>

      <!-- Step indicator -->
      <div class="eco-steps">
        <div class="eco-step-item">
          <div class="eco-step-dot active" id="stepDot1">1</div>
          <span class="eco-step-label" id="stepLabel1">Your Reason</span>
        </div>
        <div class="eco-step-line"></div>
        <div class="eco-step-item">
          <div class="eco-step-dot inactive" id="stepDot2">2</div>
          <span class="eco-step-label" id="stepLabel2">Data Preferences</span>
        </div>
      </div>

      <div id="requestError" class="eco-modal-error" style="display:none;"></div>

      <form id="requestForm">
        <input type="hidden" id="requestLocationId" name="location_id">

        <!-- Step 1 -->
        <div id="requestStep1">
          <div class="eco-form-group">
            <label for="requestReason">Why are you requesting this data?</label>
            <textarea id="requestReason" name="reason"
              placeholder="e.g. I am a researcher studying landslide risk patterns..."></textarea>
          </div>
          <div class="eco-form-group">
            <label for="requestIntendedUse">How will you use this data?</label>
            <textarea id="requestIntendedUse" name="intended_use"
              placeholder="e.g. The data will be used to prepare a community risk report..."></textarea>
          </div>
          <div class="eco-modal-actions">
            <button type="button" class="eco-modal-btn secondary" id="requestModalCancel">Cancel</button>
            <button type="button" class="eco-modal-btn primary"   id="btnNextStep">Next →</button>
          </div>
        </div>

        <!-- Step 2 -->
        <div id="requestStep2" style="display:none;">
          <div class="eco-form-group">
            <label>Date Range</label>
            <select id="reqDateRange" name="date_range" class="eco-select">
              <option value="last_7_days">Last 7 days</option>
              <option value="last_30_days" selected>Last 30 days</option>
              <option value="last_90_days">Last 90 days</option>
              <option value="custom">Custom range</option>
            </select>
          </div>

          <div id="customDateWrap" style="display:none;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div class="eco-form-group">
                <label>From</label>
                <input type="date" id="reqCustomFrom" name="custom_from" class="eco-input">
              </div>
              <div class="eco-form-group">
                <label>To</label>
                <input type="date" id="reqCustomTo" name="custom_to" class="eco-input">
              </div>
            </div>
          </div>

          <div class="eco-form-group">
            <label>Data Fields <span style="font-size:0.75rem;color:#aaa;">(select at least one)</span></label>
            <div class="eco-checkbox-group">
              <label class="eco-checkbox-item">
                <input type="checkbox" name="fields" value="movement" checked>
                <span>Movement Level</span>
              </label>
              <label class="eco-checkbox-item">
                <input type="checkbox" name="fields" value="risk" checked>
                <span>Risk Classification</span>
              </label>
              <label class="eco-checkbox-item">
                <input type="checkbox" name="fields" value="cause" checked>
                <span>Cause Label</span>
              </label>
              <label class="eco-checkbox-item">
                <input type="checkbox" name="fields" value="timestamp" checked>
                <span>Timestamps</span>
              </label>
            </div>
          </div>

          <div class="eco-form-group">
            <label>Reading Interval</label>
            <select id="reqInterval" name="interval_type" class="eco-select">
              <option value="raw">Every reading (~every 5 min)</option>
              <option value="hourly">Hourly average</option>
              <option value="daily">Daily summary</option>
            </select>
          </div>

          <div class="eco-form-group">
            <label>Preferred Format</label>
            <select id="reqFormat" name="format_pref" class="eco-select">
              <option value="both" selected>View in browser + Download CSV</option>
              <option value="view">View in browser only</option>
              <option value="download">Download CSV only</option>
            </select>
          </div>

          <div class="eco-modal-actions">
            <button type="button" class="eco-modal-btn secondary" id="btnPrevStep">← Back</button>
            <button type="submit" class="eco-modal-btn primary"   id="requestSubmitBtn">Submit Request</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Sensor History Modal ── -->
  <div id="historyModal" class="eco-modal-overlay" style="display:none;">
    <div class="eco-modal hist-modal-inner">
      <button class="eco-modal-close" id="historyModalClose">&times;</button>
      <h2 id="historyTitle">Sensor History</h2>
      <div id="historyContent">
        <div class="hist-loading">Loading...</div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <!-- ── Footer ── -->
  <footer>
    <p>&copy; 2025 EcoProtean | Environmental Monitoring System</p>
    <p><a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
  </footer>

  <script>
    window.ecoUser      = { loggedIn: <?= !empty($_SESSION['user_id']) ? 'true' : 'false' ?> };
    window.ecoMapConfig = { apiBase: '/ecoprotean/api' };
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../../assets/js/riskmap.js"></script>
</body>
</html>