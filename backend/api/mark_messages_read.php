<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$token = trim($input['token'] ?? '');
$messageIds = $input['messageIds'] ?? [];

if ($token === '') {
    respond(['ok' => false, 'error' => 'Missing token'], 400);
}

if (!is_array($messageIds)) {
    respond(['ok' => false, 'error' => 'messageIds must be an array'], 400);
}

$cleanIds = [];
foreach ($messageIds as $id) {
    $id = (int)$id;
    if ($id > 0) $cleanIds[$id] = $id;
}

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

$marked = 0;
if (!empty($cleanIds)) {
    $conn->begin_transaction();
    try {
        $insert = $conn->prepare("
            INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)
            SELECT id, ?, NOW()
            FROM cyd_messages
            WHERE id = ?
              AND device_id = ?
              AND sender = 'parent'
        ");
        $legacy = $conn->prepare("
            UPDATE cyd_messages
            SET is_read = 1
            WHERE id = ?
              AND device_id = ?
              AND sender = 'parent'
        ");

        foreach ($cleanIds as $messageId) {
            $insert->bind_param("iii", $deviceId, $messageId, $deviceId);
            $insert->execute();
            $marked += $insert->affected_rows;

            $legacy->bind_param("ii", $messageId, $deviceId);
            $legacy->execute();
        }

        $insert->close();
        $legacy->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond(['ok' => false, 'error' => 'Could not mark messages read'], 500);
    }
}

$conn->close();
respond(['ok' => true, 'markedReadCount' => $marked]);
