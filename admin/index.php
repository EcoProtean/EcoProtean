<?php
session_start();
require_once '../config/config.php';
requireLogin();

// Only admin and manager can access
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
  header('Location: ../index.php');
  exit;
}

logActivity($conn, $_SESSION['user_id'], 'VIEW_DASHBOARD', 'Viewed admin dashboard');

// ── Handle POST actions ────────────────────────────────

// Add location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_location') {
  $name      = trim($_POST['location_name']);
  $lat       = (float)$_POST['latitude'];
  $lng       = (float)$_POST['longitude'];
  $risk      = $_POST['risk_level'];
  $desc      = trim($_POST['description']);

  $stmt = $conn->prepare(
    "INSERT INTO locations (location_name, latitude, longitude, risk_level, description) VALUES (?,?,?,?,?)"
  );
  $stmt->bind_param('sddss', $name, $lat, $lng, $risk, $desc);
  $stmt->execute();
  $stmt->close();
  logActivity($conn, $_SESSION['user_id'], 'ADD_LOCATION', "Added location: $name");
  $success = "Location '$name' added successfully.";
}

// Delete location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_location') {
  $id = (int)$_POST['location_id'];
  $stmt = $conn->prepare("DELETE FROM locations WHERE location_id = ?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->close();
  logActivity($conn, $_SESSION['user_id'], 'DELETE_LOCATION', "Deleted location ID: $id");
  $success = "Location deleted.";
}

// Add tree recommendation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_recommendation') {
  $loc_id    = (int)$_POST['location_id'];
  $tree      = trim($_POST['tree_name']);
  $reason    = trim($_POST['reason']);
  $rec_by    = $_SESSION['user_id'];

  $stmt = $conn->prepare(
    "INSERT INTO tree_recommendations (location_id, tree_name, reason, recommended_by) VALUES (?,?,?,?)"
  );
  $stmt->bind_param('issi', $loc_id, $tree, $reason, $rec_by);
  $stmt->execute();
  $stmt->close();
  logActivity($conn, $_SESSION['user_id'], 'ADD_RECOMMENDATION', "Added recommendation: $tree");
  $success = "Tree recommendation added.";
}

// ── Handle User Management POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Delete user
  if ($action === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    if ($user_id === $_SESSION['user_id']) {
      $error = "You cannot delete your own account!";
    } else {
      $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
      logActivity($conn, $_SESSION['user_id'], 'DELETE_USER', "Deleted user ID: $user_id");
      $success = "User deleted successfully.";
    }
  }

  // Edit user (role + optional password reset)
  if ($action === 'edit_user') {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'] ?? 'user';
    $new_password = trim($_POST['password'] ?? '');

    if ($new_password !== '') {
      $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
      $stmt = $conn->prepare("UPDATE users SET role = ?, password = ? WHERE user_id = ?");
      $stmt->bind_param("ssi", $new_role, $hashed_password, $user_id);
    } else {
      $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
      $stmt->bind_param("si", $new_role, $user_id);
    }

    $stmt->execute();
    $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'EDIT_USER', "Updated user ID $user_id (role/password)");
    $success = "User updated successfully.";
  }

  if ($action === 'add_user') {

    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $role  = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("
        INSERT INTO users (first_name,last_name,email,password,role)
        VALUES (?,?,?,?,?)
    ");

    $stmt->bind_param("sssss", $first, $last, $email, $password, $role);
    $stmt->execute();
    $stmt->close();

    $success = "User added successfully.";
  }
}
// ── Fetch data for display ─────────────────────────────

$locations = $conn->query(
  "SELECT * FROM locations ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$recommendations = $conn->query(
  "SELECT tr.*, l.location_name, CONCAT(u.first_name, ' ', u.last_name) AS recommended_by_name
     FROM tree_recommendations tr
     JOIN locations l ON tr.location_id = l.location_id
     JOIN users u ON tr.recommended_by = u.user_id
     ORDER BY tr.created_at DESC LIMIT 20"
)->fetch_all(MYSQLI_ASSOC);

$logs = $conn->query(
  "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.role
     FROM activity_logs al
     JOIN users u ON al.user_id = u.user_id
     ORDER BY al.created_at DESC LIMIT 30"
)->fetch_all(MYSQLI_ASSOC);

// Fetch users for user management
$users = $conn->query(
  "SELECT user_id, first_name, last_name, email, role, last_login 
     FROM users ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$users = $conn->query("
    SELECT user_id, first_name, last_name, email, role, last_login, created_at
    FROM users 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title>Admin - EcoProtean</title>

</head>

<body>

  <button class="menu-toggle" id="menuToggle">☰</button>
  <div class="sidebar-overlay" id="sidebarOverlay"></div> 

  <div class="sidebar">
    <div class="brand">
      EcoProtean
      <span>Admin Panel</span>
    </div>
    <a href="../admin/index.php" class="active">📊 Dashboard</a>
    <a href=" ">User Management</a>
    <div class="logout">
      <a href="../auth/logout.php">🚪 Logout (<?= htmlspecialchars($_SESSION['full_name']) ?>)</a>
    </div>
  </div>

  <div class="main">
    <div class="page-title">Admin Dashboard</div>
    <div class="welcome">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?> · Role: <?= ucfirst($_SESSION['role']) ?></div>

    <?php if (!empty($success)): ?>
      <div class="alert success" id="successMessage"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert error" id="errorMessage"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats">
      <div class="stat-card">
        <div class="num"><?= count($locations) ?></div>
        <div class="label">Total Locations</div>
      </div>
      <div class="stat-card">
        <?php $total_recs = $conn->query("SELECT COUNT(*) AS c FROM tree_recommendations")->fetch_assoc()['c']; ?>
        <div class="num"><?= $total_recs ?></div>
        <div class="label">Tree Recommendations</div>
      </div>
      <div class="stat-card">
        <?php $total_logs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs")->fetch_assoc()['c']; ?>
        <div class="num"><?= $total_logs ?></div>
        <div class="label">Activity Logs</div>
      </div>
    </div>

    <!-- Locations Table -->
    <div class="section">
      <h2>📍 Sensor Locations</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Risk</th>
            <th>Coordinates</th>
            <th>Description</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locations as $loc): ?>
            <tr>
              <td><?= $loc['location_id'] ?></td>
              <td><?= htmlspecialchars($loc['location_name']) ?></td>
              <td><span class="badge <?= strtolower($loc['risk_level']) ?>"><?= $loc['risk_level'] ?></span></td>
              <td><?= $loc['latitude'] ?>, <?= $loc['longitude'] ?></td>
              <td><?= htmlspecialchars(substr($loc['description'], 0, 60)) ?>…</td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this location?')">
                  <input type="hidden" name="action" value="delete_location">
                  <input type="hidden" name="location_id" value="<?= $loc['location_id'] ?>">
                  <button type="submit" class="btn btn-danger" style="padding:5px 12px;font-size:0.8rem;">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Recommendations -->
    <div class="section">
      <h2>🌳 Recent Tree Recommendations</h2>
      <table>
        <thead>
          <tr>
            <th>Tree</th>
            <th>Location</th>
            <th>Reason</th>
            <th>Recommended By</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recommendations as $rec): ?>
            <tr>
              <td><strong><?= htmlspecialchars($rec['tree_name']) ?></strong></td>
              <td><?= htmlspecialchars($rec['location_name']) ?></td>
              <td><?= htmlspecialchars(substr($rec['reason'], 0, 80)) ?></td>
              <td><?= htmlspecialchars($rec['recommended_by_name']) ?></td>
              <td style="color:#aaa;font-size:0.82rem;"><?= $rec['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Activity Logs -->
    <div class="section">
      <h2>📋 Recent Activity Logs</h2>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Description</th>
              <th>IP</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td><?= htmlspecialchars($log['full_name']) ?></td>
                <td><?= ucfirst($log['role']) ?></td>
                <td><code style="font-size:0.8rem;background:#f0f4f3;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($log['action']) ?></code></td>
                <td><?= htmlspecialchars($log['description']) ?></td>
                <td style="color:#aaa;font-size:0.82rem;"><?= $log['ip_address'] ?></td>
                <td style="color:#aaa;font-size:0.82rem;"><?= $log['created_at'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section">
      <h2>👥 User Management</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Created At</th>
            <th>Last Login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><?= $user['user_id'] ?></td>
              <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
              <td><?= htmlspecialchars($user['email']) ?></td>
              <td><?= ucfirst($user['role']) ?></td>
              <td><?= $user['created_at'] ?></td>
              <td><?= $user['last_login'] ?? 'Never' ?></td>
              <td>
                <button class="btn btn-sm"
                  onclick='openViewModal({
        user_id: <?= $user["user_id"] ?>,
        name: "<?= htmlspecialchars($user["first_name"] . ' ' . $user["last_name"]) ?>",
        email: "<?= htmlspecialchars($user["email"]) ?>",
        role: "<?= $user["role"] ?>",
        created_at: "<?= $user["created_at"] ?>",
      })'>View</button>

                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?')">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <br>

      <button class="btn" onclick="openAddUserModal()">➕ Add User</button>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:9999;">

      <div style="background:#fff;padding:25px;border-radius:12px;width:450px;box-shadow:0 4px 12px rgba(0,0,0,0.3);">

        <h3 style="margin-bottom:20px;">Add New User</h3>

        <form method="POST">
          <input type="hidden" name="action" value="add_user">

          <label>First Name</label>
          <input type="text" name="first_name" required style="width:100%;padding:8px;margin:5px 0 10px;border-radius:6px;border:1px solid #ccc;">

          <label>Last Name</label>
          <input type="text" name="last_name" required style="width:100%;padding:8px;margin:5px 0 10px;border-radius:6px;border:1px solid #ccc;">

          <label>Email</label>
          <input type="email" name="email" required style="width:100%;padding:8px;margin:5px 0 10px;border-radius:6px;border:1px solid #ccc;">

          <label>Password</label>
          <input type="password" name="password" required style="width:100%;padding:8px;margin:5px 0 10px;border-radius:6px;border:1px solid #ccc;">

          <label>Role</label>
          <select name="role" style="width:100%;padding:8px;margin:5px 0 15px;border-radius:6px;border:1px solid #ccc;">
            <option value="user">User</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>

          <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" class="btn btn-danger" onclick="closeAddUserModal()">Cancel</button>
            <button type="submit" class="btn">Add User</button>
          </div>

        </form>
      </div>
    </div>

    <!-- View User Modal -->
    <div id="viewUserModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:9999;">
      <div style="background:#fff;padding:25px;border-radius:12px;width:450px;position:relative;box-shadow:0 4px 12px rgba(0,0,0,0.3);font-family:'Poppins',sans-serif;">
        <h3 style="margin-bottom:20px;">User Details</h3>

        <p><strong>ID:</strong> <span id="viewUserId"></span></p>
        <p><strong>Name:</strong> <span id="viewName"></span></p>
        <p><strong>Email:</strong> <span id="viewEmail"></span></p>
        <p><strong>Role:</strong> <span id="viewRole"></span></p>
        <p><strong>Created At:</strong> <span id="viewCreated"></span></p>

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
          <button class="btn" onclick="openEditFromView()">Edit</button>
          <button class="btn btn-danger" onclick="closeViewModal()">Close</button>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:9999;">
      <div style="background:#fff;padding:25px;border-radius:12px;width:450px;position:relative;box-shadow:0 4px 12px rgba(0,0,0,0.3);font-family:'Poppins',sans-serif;">
        <h3 style="margin-bottom:20px;">Edit User</h3>

        <form method="POST" id="editUserForm">
          <input type="hidden" name="action" value="edit_user">
          <input type="hidden" name="user_id" id="editUserId">

          <label>Email:</label>
          <input type="email" name="email" id="editEmail" readonly style="width:100%;padding:8px;margin:5px 0 10px;border-radius:6px;border:1px solid #ccc;">

          <label>Password (leave empty to keep current):</label>
          <input type="password" name="password" placeholder="New password if resetting" style="width:100%;padding:8px;margin:5px 0 10px;border-radius:6px;border:1px solid #ccc;">

          <label>Role:</label>
          <select name="role" id="editRole" style="width:100%;padding:8px;margin:5px 0 15px;border-radius:6px;border:1px solid #ccc;">
            <option value="user">User</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>

          <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
            <button type="submit" class="btn">Update User</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="../assets/js/admin.js"></script>
</body>

</html>