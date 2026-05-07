<?php
// ─────────────────────────────────────────────
//  API: POST /api/simulate.php
//  Inserts a random movement reading for every
//  sensor — mimics what a real gyro sensor
//  would POST when it detects movement.
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

// ── Only accept POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    exit;
}

// ── Get all sensors ──
$sensors = $conn->query("
    SELECT sensor_id FROM sensors
")->fetch_all(MYSQLI_ASSOC);

if (empty($sensors)) {
    echo json_encode(['error' => true, 'message' => 'No sensors found']);
    exit;
}

$stmt     = $conn->prepare("
    INSERT INTO simulation_data (sensor_id, movement_level) VALUES (?, ?)
");
$inserted = [];

foreach ($sensors as $sensor) {
    $movement = generateMovement(); // ← uses shared function
    $stmt->bind_param('ii', $sensor['sensor_id'], $movement);
    $stmt->execute();

    $inserted[] = [
        'sensor_id'      => $sensor['sensor_id'],
        'movement_level' => $movement
    ];
}

$stmt->close();

echo json_encode([
    'success'  => true,
    'inserted' => $inserted,
    'count'    => count($inserted),
    'time'     => date('H:i:s')
]);

$conn->close();
?>