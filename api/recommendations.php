<?php
// ─────────────────────────────────────────────
//  API: GET /api/recommendations.php?location_id=1
//  Returns tree recommendations for a location
// ─────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/config/config.php';

$location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

if (!$location_id) {
    echo json_encode(['error' => true, 'message' => 'location_id is required']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT 
        tr.tree_name, 
        tr.reason, 
        CONCAT(u.first_name, ' ', u.last_name) AS recommended_by, 
        tr.created_at
     FROM tree_recommendations tr
     JOIN users u ON tr.recommended_by = u.user_id
     WHERE tr.location_id = ?
     ORDER BY tr.created_at DESC"
);

$stmt->bind_param('i', $location_id);
$stmt->execute();
$result = $stmt->get_result();

$recommendations = [];
while ($row = $result->fetch_assoc()) {
    $recommendations[] = $row;
}

echo json_encode($recommendations);
$stmt->close();
$conn->close();
?>
