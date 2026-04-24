<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$token = trim($input['token'] ?? '');
$message = trim($input['message'] ?? '');
$sender = trim($input['sender'] ?? 'kid');

if ($token === '' || $message === '') {
    respond([
        'ok' => false,
        'error' => 'Missing token or message'
    ], 400);
}

if (!in_array($sender, ['kid', 'parent'], true)) {
    respond([
        'ok' => false,
        'error' => 'Invalid sender'
    ], 400);
}

if (mb_strlen($message) > 1000) {
    respond([
        'ok' => false,
        'error' => 'Message too long'
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

$stmt = $conn->prepare("
    SELECT id
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

$stmt = $conn->prepare("
    INSERT INTO cyd_messages
        (device_id, sender, message, is_read, created_at)
    VALUES
        (?, ?, ?, 0, NOW())
");

$stmt->bind_param("iss", $deviceId, $sender, $message);
$stmt->execute();

$messageId = $stmt->insert_id;

$stmt->close();
$conn->close();

respond([
    'ok' => true,
    'messageId' => $messageId,
    'sender' => $sender,
    'message' => $message
]);