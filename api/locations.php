<?php
// ─────────────────────────────────────────────
//  API: GET /api/locations.php
//  Returns all locations as JSON for the map.
// ─────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
require_once dirname(__DIR__) . '/config/config.php';

/* ── Public Access ──
  We removed the session_id check here so the public 
  can see the pins on the Risk Map without logging in.
*/

// ── Auto-simulate logic ──
$lastResult = $conn->query("SELECT TIMESTAMPDIFF(SECOND, MAX(timestamp), NOW()) AS seconds_ago FROM simulation_data");
$last = $lastResult->fetch_assoc()['seconds_ago'];

// Only allow the database to update if a MANAGER is logged in 
// This prevents random public visitors from spamming your database
if (!empty($_SESSION['role']) && $_SESSION['role'] === 'manager') {
    if ($last === null || $last >= 5) {
        $sensors = $conn->query("SELECT sensor_id FROM sensors")->fetch_all(MYSQLI_ASSOC);
        if (!empty($sensors)) {
            $stmt = $conn->prepare("INSERT INTO simulation_data (sensor_id, movement_level) VALUES (?, ?)");
            foreach ($sensors as $sensor) {
                $movement = rand(0, 100);
                $stmt->bind_param('ii', $sensor['sensor_id'], $movement);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

// ── Fetch latest data per sensor (PUBLIC DATA) ─────────────
$result = $conn->query(
    "SELECT
        l.location_id,
        l.location_name,
        l.latitude,
        l.longitude,
        l.description,
        s.sensor_id,
        sd.movement_level,
        CASE
            WHEN sd.movement_level IS NULL  THEN 'low'
            WHEN sd.movement_level < 30     THEN 'low'
            WHEN sd.movement_level < 60     THEN 'medium'
            ELSE                                 'high'
        END AS risk_level
     FROM locations l
     LEFT JOIN sensors s ON s.location_id = l.location_id
     LEFT JOIN simulation_data sd
        ON sd.sensor_id = s.sensor_id
        AND sd.sim_id = (
            SELECT MAX(sim_id)
            FROM simulation_data
            WHERE sensor_id = s.sensor_id
        )
     ORDER BY l.location_id ASC"
);

if (!$result) {
    echo json_encode(['error' => true, 'message' => $conn->error]);
    exit;
}

$locations = [];
while ($row = $result->fetch_assoc()) {
    $locations[] = [
        'id'             => (int)$row['location_id'],
        'sensor_id'      => (int)$row['sensor_id'],
        'name'           => $row['location_name'],
        'coords'         => [(float)$row['latitude'], (float)$row['longitude']],
        'risk'           => $row['risk_level'],
        'movement_level' => $row['movement_level'] !== null ? (int)$row['movement_level'] : null,
        'description'    => $row['description'],
    ];
}

echo json_encode($locations);
$conn->close();
?>