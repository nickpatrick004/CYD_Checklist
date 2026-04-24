<?php
header('Content-Type: application/json');

date_default_timezone_set('America/Chicago');

require_once __DIR__ . '/../includes/config.php';

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$token = $_GET['token'] ?? '';

if ($token === '') {
    respond([
        'ok' => false,
        'error' => 'Missing device token'
    ], 400);
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    respond([
        'ok' => false,
        'error' => 'Database connection failed'
    ], 500);
}

$conn->set_charset('utf8mb4');

/*
|--------------------------------------------------------------------------
| Validate device
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id, device_name 
    FROM cyd_devices 
    WHERE device_token = ?
    LIMIT 1
");

$stmt->bind_param("s", $token);
$stmt->execute();

$result = $stmt->get_result();
$device = $result->fetch_assoc();

$stmt->close();

if (!$device) {
    respond([
        'ok' => false,
        'error' => 'Invalid device token'
    ], 401);
}

$deviceId = (int)$device['id'];

/*
|--------------------------------------------------------------------------
| Update last seen
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE cyd_devices 
    SET last_seen_at = NOW() 
    WHERE id = ?
");

$stmt->bind_param("i", $deviceId);
$stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Get today's checklist with completion status
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT 
        i.id,
        i.title,
        i.due_time,
        i.repeat_days,
        i.alert_enabled,
        CASE 
            WHEN c.id IS NULL THEN 0 
            ELSE 1 
        END AS completed_today
    FROM cyd_checklist_items i
    LEFT JOIN cyd_item_completions c
        ON c.item_id = i.id
        AND c.device_id = i.device_id
        AND c.completed_date = CURDATE()
    WHERE i.device_id = ?
      AND i.is_active = 1
    ORDER BY i.due_time IS NULL, i.due_time ASC, i.id ASC
");

$stmt->bind_param("i", $deviceId);
$stmt->execute();

$result = $stmt->get_result();

$checklist = [];

while ($row = $result->fetch_assoc()) {
    $checklist[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'dueTime' => $row['due_time'],
        'repeatDays' => $row['repeat_days'],
        'alertEnabled' => (bool)$row['alert_enabled'],
        'completedToday' => (bool)$row['completed_today']
    ];
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Get recent messages
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT 
        id,
        sender,
        message,
        is_read,
        created_at
    FROM cyd_messages
    WHERE device_id = ?
    ORDER BY created_at DESC
    LIMIT 25
");

$stmt->bind_param("i", $deviceId);
$stmt->execute();

$result = $stmt->get_result();

$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'sender' => $row['sender'],
        'message' => $row['message'],
        'isRead' => (bool)$row['is_read'],
        'createdAt' => $row['created_at']
    ];
}

$stmt->close();
$conn->close();

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond([
    'ok' => true,
    'serverTime' => date('Y-m-d H:i:s'),
    'deviceName' => $device['device_name'],
    'checklist' => $checklist,
    'messages' => $messages
]);