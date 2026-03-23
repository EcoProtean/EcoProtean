<?php
// ─────────────────────────────────────────────
//  API: GET /api/locations.php
//  Returns all locations as JSON for the map.
//
//  Only the manager triggers simulation.
//  Admin and user just read the latest data.
// ─────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
require_once dirname(__DIR__) . '/config/config.php';

// ── Auth check ──
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Unauthorized']);
    exit;
}

// ── Auto-simulate if 5s have passed since last insert ──
// Any logged-in user can trigger this — whoever calls first
// after 5s inserts new data. Everyone else reads the same row.
$last = $conn->query("
    SELECT TIMESTAMPDIFF(SECOND, MAX(timestamp), NOW()) AS seconds_ago
    FROM simulation_data
")->fetch_assoc()['seconds_ago'];

if ($last === null || $last >= 5) {
    $sensors = $conn->query("SELECT sensor_id FROM sensors")->fetch_all(MYSQLI_ASSOC);
    if (!empty($sensors)) {
        $stmt = $conn->prepare(
            "INSERT INTO simulation_data (sensor_id, movement_level) VALUES (?, ?)"
        );
        foreach ($sensors as $sensor) {
            $movement = rand(0, 100);
            $stmt->bind_param('ii', $sensor['sensor_id'], $movement);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// ── Fetch latest data per sensor ─────────────
// Everyone reads the same latest row from DB
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