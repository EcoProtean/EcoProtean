<?php
// ─────────────────────────────────────────────
//  API: GET /api/locations.php
//  Shared Data: All UIs see the same latest data.
//  Self-Simulating: Any visitor triggers the update.
// ─────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
require_once dirname(__DIR__) . '/config/config.php';

// ── 1. INDEPENDENT AUTO-SIMULATE ──
// We check the time since the last sensor update.
$timerQuery = $conn->query("SELECT TIMESTAMPDIFF(SECOND, MAX(timestamp), NOW()) AS seconds_ago FROM simulation_data");
$timerRow = $timerQuery->fetch_assoc();
$secondsSinceLast = $timerRow['seconds_ago'];

// REMOVED THE ROLE CHECK:
// Now, the "EcoProtean" system simulates itself regardless of who is watching.
// If 5 seconds passed, the FIRST person to load the map (Guest or Admin) 
// triggers the new data for EVERYONE.
if ($secondsSinceLast === null || $secondsSinceLast >= 5) {
    $sensors = $conn->query("SELECT sensor_id FROM sensors")->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($sensors)) {
        $stmt = $conn->prepare("INSERT INTO simulation_data (sensor_id, movement_level) VALUES (?, ?)");
        foreach ($sensors as $sensor) {
            // Shared random values generated once every 5 seconds
            $movement = rand(0, 100); 
            $stmt->bind_param('ii', $sensor['sensor_id'], $movement);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// ── 2. SHARED DATA FETCH ──
// Everyone (Public, Admin, Manager) runs this same query to see the same "Shared Reality"
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