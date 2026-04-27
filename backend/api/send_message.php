<?php
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
$message = trim($input['message'] ?? '');
$summary = trim($input['summary'] ?? '');
$detail = trim($input['detail'] ?? '');
$sender = trim($input['sender'] ?? 'kid');

if ($token === '' || $message === '') respond(['ok' => false, 'error' => 'Missing token or message'], 400);
if (!in_array($sender, ['kid', 'parent'], true)) respond(['ok' => false, 'error' => 'Invalid sender'], 400);
if ($summary === '') $summary = mb_substr($message, 0, 180);
if ($detail === '') $detail = null;
if (mb_strlen($summary) > 255) respond(['ok' => false, 'error' => 'Summary too long'], 400);
if (mb_strlen($message) > 1000) respond(['ok' => false, 'error' => 'Message too long'], 400);
if ($detail !== null && mb_strlen($detail) > 4000) respond(['ok' => false, 'error' => 'Detail too long'], 400);

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
$isRead = $sender === 'kid' ? 1 : 0;

$stmt = $conn->prepare("
    INSERT INTO cyd_messages (device_id, sender, summary, message, detail_text, is_read, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("issssi", $deviceId, $sender, $summary, $message, $detail, $isRead);
$stmt->execute();
$messageId = $stmt->insert_id;
$stmt->close();

if ($sender === 'kid') {
    $stmt = $conn->prepare("INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $messageId, $deviceId);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
respond(['ok' => true, 'messageId' => $messageId, 'sender' => $sender, 'summary' => $summary, 'message' => $message]);
