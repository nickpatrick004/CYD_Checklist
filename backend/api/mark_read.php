<?php
// Backward-compatible wrapper for older clients that mark one message read.
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) respond(['ok' => false, 'error' => 'Invalid JSON'], 400);

$token = trim($input['token'] ?? '');
$messageId = isset($input['messageId']) ? (int)$input['messageId'] : 0;
if ($token === '' || $messageId <= 0) respond(['ok' => false, 'error' => 'Missing token or messageId'], 400);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) respond(['ok' => false, 'error' => 'Database connection failed'], 500);
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("SELECT id FROM cyd_devices WHERE device_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$device) respond(['ok' => false, 'error' => 'Invalid device token'], 401);
$deviceId = (int)$device['id'];

$stmt = $conn->prepare("
    INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)
    SELECT id, ?, NOW()
    FROM cyd_messages
    WHERE id = ? AND device_id = ? AND sender = 'parent'
");
$stmt->bind_param("iii", $deviceId, $messageId, $deviceId);
$stmt->execute();
$marked = $stmt->affected_rows > 0;
$stmt->close();

$stmt = $conn->prepare("UPDATE cyd_messages SET is_read = 1 WHERE id = ? AND device_id = ? AND sender = 'parent'");
$stmt->bind_param("ii", $messageId, $deviceId);
$stmt->execute();
$stmt->close();
$conn->close();

respond(['ok' => true, 'messageId' => $messageId, 'markedRead' => $marked]);
