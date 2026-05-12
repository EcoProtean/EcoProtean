<?php
// ─────────────────────────────────────────────
//  API: /api/sensor_requests.php
//
//  POST action=submit_request    → User submits a new sensor data request
//  POST action=review_request    → Manager approves or rejects a request
//  GET  action=get_my_requests   → User fetches all their own requests
//  GET  action=get_all_requests  → Manager fetches all requests
//  GET  action=get_sensor_history → User fetches approved sensor history
// ─────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
require_once dirname(__DIR__) . '/config/config.php';

// ── Auth check — must be logged in ──
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'user';
$method  = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helpers ───────────────────────────────────

/**
 * Resolve a date_range value + custom dates into
 * a [from, to] pair of MySQL-formatted datetime strings.
 */
function resolveDateRange(string $range, ?string $custom_from, ?string $custom_to): array {
    $to   = date('Y-m-d 23:59:59');
    $from = match($range) {
        'last_7_days'  => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'last_30_days' => date('Y-m-d 00:00:00', strtotime('-30 days')),
        'last_90_days' => date('Y-m-d 00:00:00', strtotime('-90 days')),
        'custom'       => ($custom_from ? date('Y-m-d 00:00:00', strtotime($custom_from)) : date('Y-m-d 00:00:00', strtotime('-30 days'))),
        default        => date('Y-m-d 00:00:00', strtotime('-30 days')),
    };
    if ($range === 'custom' && $custom_to) {
        $to = date('Y-m-d 23:59:59', strtotime($custom_to));
    }
    return [$from, $to];
}

/**
 * Aggregate raw rows by hourly or daily interval.
 * Returns averaged movement_level per bucket.
 */
function aggregateRows(array $rows, string $interval): array {
    if ($interval === 'raw' || empty($rows)) return $rows;

    $buckets = [];
    foreach ($rows as $row) {
        $ts  = strtotime($row['timestamp']);
        $key = $interval === 'hourly'
            ? date('Y-m-d H:00:00', $ts)
            : date('Y-m-d 00:00:00', $ts);

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'values'        => [],
                'timestamp'     => $key,
                'sensor_type'   => $row['sensor_type'] ?? 'Motion',
                'location_name' => $row['location_name'] ?? 'Unknown Location',
            ];
        }
        $buckets[$key]['values'][] = (int)$row['movement_level'];
    }

    $result = [];
    foreach ($buckets as $key => $bucket) {
        $avg = (int)round(array_sum($bucket['values']) / count($bucket['values']));
        $risk = $avg < 30 ? 'low' : ($avg < 60 ? 'medium' : 'high');
        $cause = $avg < 30 ? 'Wind' : ($avg < 60 ? 'Rain / Soil Softening' : 'Ground Instability');
        $result[] = [
            'movement_level' => $avg,
            'timestamp'      => $bucket['timestamp'],
            'sensor_type'    => $bucket['sensor_type'],
            'location_name'  => $bucket['location_name'],
            'risk'           => $risk,
            'cause'          => $cause,
            'aggregated'     => true,
            'sample_count'   => count($bucket['values']),
        ];
    }

    // Keep descending order
    usort($result, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
    return $result;
}

/**
 * Filter row fields based on comma-separated fields string.
 * Always keeps timestamp as it's needed for the chart/table.
 */
function filterFields(array $rows, string $fields_str): array {
    $allowed = array_map('trim', explode(',', $fields_str));
    if (!in_array('timestamp', $allowed)) $allowed[] = 'timestamp';

    $map = [
        'movement'  => 'movement_level',
        'risk'      => 'risk',
        'cause'     => 'cause',
        'timestamp' => 'timestamp',
    ];

    return array_map(function($row) use ($allowed, $map) {
        $filtered = [];
        foreach ($allowed as $field) {
            $col = $map[$field] ?? null;
            if ($col && array_key_exists($col, $row)) {
                $filtered[$col] = $row[$col];
            }
        }
        $filtered['timestamp']     = $row['timestamp']     ?? null;
        $filtered['sensor_type']   = $row['sensor_type']   ?? null;
        $filtered['location_name'] = $row['location_name'] ?? null;
        if (isset($row['aggregated']))   $filtered['aggregated']   = $row['aggregated'];
        if (isset($row['sample_count'])) $filtered['sample_count'] = $row['sample_count'];
        return $filtered;
    }, $rows);
}

// ══════════════════════════════════════════════
//  GET handlers
// ══════════════════════════════════════════════
if ($method === 'GET') {

    // ── GET: User fetches their own requests ──
    if ($action === 'get_my_requests') {
        $stmt = $conn->prepare("
            SELECT
                sr.*,
                l.location_name,
                CONCAT(m.first_name, ' ', m.last_name) AS reviewed_by_name
            FROM sensor_requests sr
            JOIN locations l ON sr.location_id = l.location_id
            LEFT JOIN users m ON sr.reviewed_by = m.user_id
            WHERE sr.user_id = ?
            ORDER BY sr.requested_at DESC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode($result);
        exit;
    }

    // ── GET: Manager fetches all requests ──
    if ($action === 'get_all_requests') {
        if (!in_array($role, ['manager', 'admin'])) {
            http_response_code(403);
            echo json_encode(['error' => true, 'message' => 'Forbidden']);
            exit;
        }

        $status_filter = $_GET['status'] ?? 'all';
        $allowed_statuses = ['pending', 'approved', 'rejected', 'all'];
        if (!in_array($status_filter, $allowed_statuses)) {
            $status_filter = 'all';
        }

        $where = $status_filter !== 'all' ? 'WHERE sr.status = ?' : '';

        $query = "
            SELECT
                sr.*,
                l.location_name,
                CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
                u.email                                AS requester_email,
                CONCAT(m.first_name, ' ', m.last_name) AS reviewed_by_name
            FROM sensor_requests sr
            JOIN locations l ON sr.location_id = l.location_id
            JOIN users u     ON sr.user_id     = u.user_id
            LEFT JOIN users m ON sr.reviewed_by = m.user_id
            $where
            ORDER BY
                CASE sr.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
                sr.requested_at DESC
        ";

        if ($status_filter !== 'all') {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $status_filter);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $result = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
        }

        echo json_encode($result);
        exit;
    }

    // ── GET: Fetch sensor history for an approved request ──
    if ($action === 'get_sensor_history') {
        $request_id  = isset($_GET['request_id'])  ? (int)$_GET['request_id']  : 0;
        $location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

        if (!$request_id || !$location_id) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'request_id and location_id are required']);
            exit;
        }

        $check = $conn->prepare("
            SELECT request_id, status, date_range, custom_from, custom_to, fields, interval_type, format_pref
            FROM sensor_requests
            WHERE request_id = ? AND user_id = ? AND status = 'approved'
        ");
        $check->bind_param('ii', $request_id, $user_id);
        $check->execute();
        $approved = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$approved) {
            http_response_code(403);
            echo json_encode(['error' => true, 'message' => 'Request not approved or not found']);
            exit;
        }

        [$date_from, $date_to] = resolveDateRange($approved['date_range'], $approved['custom_from'], $approved['custom_to']);

        $stmt = $conn->prepare("
            SELECT
                sd.sim_id, sd.movement_level, sd.timestamp, s.sensor_type, l.location_name,
                CASE WHEN sd.movement_level < 30 THEN 'low' WHEN sd.movement_level < 60 THEN 'medium' ELSE 'high' END AS risk,
                CASE WHEN sd.movement_level < 30 THEN 'Wind' WHEN sd.movement_level < 60 THEN 'Rain / Soil Softening' ELSE 'Ground Instability' END AS cause
            FROM simulation_data sd
            JOIN sensors   s ON sd.sensor_id  = s.sensor_id
            JOIN locations l ON s.location_id = l.location_id
            WHERE l.location_id = ? AND sd.timestamp >= ? AND sd.timestamp <= ?
            ORDER BY sd.timestamp DESC
        ");
        $stmt->bind_param('iss', $location_id, $date_from, $date_to);
        $stmt->execute();
        $raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $history = aggregateRows($raw, $approved['interval_type']);
        $history = filterFields($history, $approved['fields']);

        echo json_encode([
            'success'       => true,
            'history'       => $history,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'date_range'    => $approved['date_range'],
            'interval_type' => $approved['interval_type'],
            'format_pref'   => $approved['format_pref'],
            'fields'        => $approved['fields'],
            'total'         => count($history),
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Unknown GET action']);
    exit;
}

// ══════════════════════════════════════════════
//  POST handlers
// ══════════════════════════════════════════════
if ($method === 'POST') {

    // ── POST: User submits a new sensor request ──
    if ($action === 'submit_request') {
        $location_id  = isset($_POST['location_id'])  ? (int)$_POST['location_id']   : 0;
        $reason       = isset($_POST['reason'])        ? trim($_POST['reason'])        : '';
        $intended_use = isset($_POST['intended_use'])  ? trim($_POST['intended_use'])  : '';

        $date_range    = isset($_POST['date_range'])    ? trim($_POST['date_range'])    : 'last_30_days';
        $custom_from   = isset($_POST['custom_from'])   ? trim($_POST['custom_from'])   : null;
        $custom_to     = isset($_POST['custom_to'])     ? trim($_POST['custom_to'])     : null;
        $interval_type = isset($_POST['interval_type']) ? trim($_POST['interval_type']) : 'raw';
        $format_pref   = isset($_POST['format_pref'])   ? trim($_POST['format_pref'])   : 'both';

        if (isset($_POST['fields']) && is_array($_POST['fields'])) {
            $fields = implode(',', array_map('trim', $_POST['fields']));
        } else {
            $fields = isset($_POST['fields']) ? trim($_POST['fields']) : 'movement,risk,cause,timestamp';
        }

        if (!$location_id || empty($reason) || empty($intended_use)) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'location_id, reason, and intended_use are required']);
            exit;
        }

        $valid_ranges    = ['last_7_days', 'last_30_days', 'last_90_days', 'custom'];
        $valid_intervals = ['raw', 'hourly', 'daily'];
        $valid_formats   = ['view', 'download', 'both'];

        if (!in_array($date_range,    $valid_ranges))    $date_range    = 'last_30_days';
        if (!in_array($interval_type, $valid_intervals)) $interval_type = 'raw';
        if (!in_array($format_pref,   $valid_formats))   $format_pref   = 'both';

        if ($date_range === 'custom') {
            if (empty($custom_from) || empty($custom_to)) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'custom_from and custom_to are required for custom date range']);
                exit;
            }
            if (strtotime($custom_from) > strtotime($custom_to)) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'custom_from must be before custom_to']);
                exit;
            }
        } else {
            $custom_from = null;
            $custom_to   = null;
        }

        $valid_fields   = ['movement', 'risk', 'cause', 'timestamp'];
        $requested_fields = array_filter(
            array_map('trim', explode(',', $fields)),
            fn($f) => in_array($f, $valid_fields)
        );
        if (empty($requested_fields)) $requested_fields = $valid_fields;
        $fields = implode(',', $requested_fields);

        $check = $conn->prepare("SELECT request_id FROM sensor_requests WHERE user_id = ? AND location_id = ? AND status = 'pending' LIMIT 1");
        $check->bind_param('ii', $user_id, $location_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            http_response_code(409);
            echo json_encode(['error' => true, 'message' => 'You already have a pending request for this location.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO sensor_requests (user_id, location_id, reason, intended_use, date_range, custom_from, custom_to, fields, interval_type, format_pref) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissssssss', $user_id, $location_id, $reason, $intended_use, $date_range, $custom_from, $custom_to, $fields, $interval_type, $format_pref);
        $stmt->execute();
        $request_id = $conn->insert_id;
        $stmt->close();

        logActivity($conn, $user_id, 'REQUEST_SENSOR_DATA', "Requested sensor data for location ID: $location_id");

        echo json_encode([
            'success'    => true,
            'request_id' => $request_id,
            'message'    => 'Request submitted successfully.'
        ]);
        exit;
    }

    // ── POST: Manager approves or rejects a request ──
    if ($action === 'review_request') {
        if (!in_array($role, ['manager', 'admin'])) {
            http_response_code(403);
            echo json_encode(['error' => true, 'message' => 'Forbidden']);
            exit;
        }

        $request_id        = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        
        // FIX: Read both 'review_action' (from custom scripts) and fallback to 'review' 
        $review_action     = $_POST['review_action'] ?? $_POST['review'] ?? '';
        $review_action     = trim($review_action);
        
        $rejection_remarks = isset($_POST['rejection_remarks']) ? trim($_POST['rejection_remarks']) : '';

        if (!$request_id || !in_array($review_action, ['approve', 'reject'])) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'request_id and review criteria are required']);
            exit;
        }

        if ($review_action === 'reject' && empty($rejection_remarks)) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'Rejection remarks are required when rejecting a request']);
            exit;
        }

        $new_status = $review_action === 'approve' ? 'approved' : 'rejected';

        $check = $conn->prepare("SELECT request_id FROM sensor_requests WHERE request_id = ? AND status = 'pending'");
        $check->bind_param('i', $request_id);
        $check->execute();
        $request = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'Request not found or already reviewed']);
            exit;
        }

        $remarks_val = $review_action === 'approve' ? null : $rejection_remarks;
        $stmt = $conn->prepare("
            UPDATE sensor_requests
            SET status            = ?,
                rejection_remarks  = ?,
                reviewed_at        = NOW(),
                reviewed_by        = ?
            WHERE request_id = ?
        ");
        $stmt->bind_param('ssii', $new_status, $remarks_val, $user_id, $request_id);
        $stmt->execute();
        $stmt->close();

        logActivity($conn, $user_id, $new_status === 'approved' ? 'APPROVE_SENSOR_REQUEST' : 'REJECT_SENSOR_REQUEST', "Request #$request_id $new_status");

        echo json_encode([
            'success'    => true,
            'status'     => $new_status,
            'request_id' => $request_id,
            'message'    => "Request $new_status successfully."
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Unknown POST action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => true, 'message' => 'Method not allowed']);