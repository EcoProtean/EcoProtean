<?php
// ─────────────────────────────────────────────
//  API: GET /api/locations.php
//  Returns all locations as JSON for the map
// ─────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Go up two levels to reach config.php from api/
require_once dirname(__DIR__) . '/config.php';

$result = $conn->query(
    "SELECT location_id, location_name, latitude, longitude, risk_level, description
     FROM locations
     ORDER BY location_id ASC"
);

if (!$result) {
    echo json_encode(['error' => true, 'message' => $conn->error]);
    exit;
}

$locations = [];
while ($row = $result->fetch_assoc()) {
    $locations[] = [
        'id'          => (int)$row['location_id'],
        'name'        => $row['location_name'],
        'coords'      => [(float)$row['latitude'], (float)$row['longitude']],
        'risk'        => strtolower($row['risk_level']), // normalize to lowercase for JS
        'description' => $row['description'],
    ];
}

echo json_encode($locations);
$conn->close();
?>
