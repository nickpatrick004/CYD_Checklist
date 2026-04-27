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
if ($token === '') respond(['ok' => false, 'error' => 'Missing device token'], 400);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) respond(['ok' => false, 'error' => 'Database connection failed'], 500);
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("SELECT id, device_name FROM cyd_devices WHERE device_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) respond(['ok' => false, 'error' => 'Invalid device token'], 401);
$deviceId = (int)$device['id'];

$stmt = $conn->prepare("UPDATE cyd_devices SET last_seen_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $deviceId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    SELECT i.id, i.title, i.detail_text, i.due_time, i.repeat_days, i.alert_enabled,
           CASE WHEN c.id IS NULL THEN 0 ELSE 1 END AS completed_today
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
        'detail' => $row['detail_text'] ?? '',
        'dueTime' => $row['due_time'],
        'repeatDays' => $row['repeat_days'],
        'alertEnabled' => (bool)$row['alert_enabled'],
        'completedToday' => (bool)$row['completed_today']
    ];
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM cyd_messages m
    LEFT JOIN cyd_message_reads r
      ON r.message_id = m.id
     AND r.reader_device_id = ?
    WHERE m.device_id = ?
      AND m.sender = 'parent'
      AND COALESCE(m.is_archived, 0) = 0
      AND r.id IS NULL
");
$stmt->bind_param("ii", $deviceId, $deviceId);
$stmt->execute();
$unreadRow = $stmt->get_result()->fetch_assoc();
$unreadMessageCount = (int)($unreadRow['unread_count'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
    SELECT * FROM (
        SELECT m.id, m.sender, m.summary, m.message, m.detail_text, m.created_at,
               CASE WHEN m.sender <> 'parent' THEN 1 WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read_for_device,
               CASE WHEN m.sender = 'parent' AND r.id IS NULL THEN 0 ELSE 1 END AS read_sort
        FROM cyd_messages m
        LEFT JOIN cyd_message_reads r
          ON r.message_id = m.id
         AND r.reader_device_id = ?
        WHERE m.device_id = ?
          AND COALESCE(m.is_archived, 0) = 0
          AND m.sender = 'parent'
          AND r.id IS NULL

        UNION

        SELECT m.id, m.sender, m.summary, m.message, m.detail_text, m.created_at,
               CASE WHEN m.sender <> 'parent' THEN 1 WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read_for_device,
               1 AS read_sort
        FROM cyd_messages m
        LEFT JOIN cyd_message_reads r
          ON r.message_id = m.id
         AND r.reader_device_id = ?
        WHERE m.device_id = ?
          AND COALESCE(m.is_archived, 0) = 0
        ORDER BY created_at DESC, id DESC
        LIMIT 25
    ) visible_messages
    ORDER BY read_sort ASC, created_at DESC, id DESC
");
$stmt->bind_param("iiii", $deviceId, $deviceId, $deviceId, $deviceId);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $summary = $row['summary'] ?: $row['message'];
    $messages[] = [
        'id' => (int)$row['id'],
        'sender' => $row['sender'],
        'summary' => $summary,
        'message' => $row['message'],
        'detail' => $row['detail_text'] ?? '',
        'isRead' => (bool)$row['is_read_for_device'],
        'createdAt' => $row['created_at']
    ];
}
$stmt->close();
$conn->close();

respond([
    'ok' => true,
    'serverTime' => date('Y-m-d H:i:s'),
    'deviceName' => $device['device_name'],
    'unreadMessageCount' => $unreadMessageCount,
    'checklist' => $checklist,
    'messages' => $messages
]);
