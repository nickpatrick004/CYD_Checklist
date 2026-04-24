<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$token = $input['token'] ?? '';
$itemId = isset($input['itemId']) ? (int)$input['itemId'] : 0;

if ($token === '' || $itemId <= 0) {
    respond(['ok' => false, 'error' => 'Missing token or itemId'], 400);
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    respond(['ok' => false, 'error' => 'Database connection failed'], 500);
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
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) {
    respond(['ok' => false, 'error' => 'Invalid device token'], 401);
}

$deviceId = (int)$device['id'];

$stmt = $conn->prepare("
    DELETE FROM cyd_item_completions
    WHERE item_id = ?
      AND device_id = ?
      AND completed_date = CURDATE()
");

$stmt->bind_param("ii", $itemId, $deviceId);
$stmt->execute();
$stmt->close();

$conn->close();

respond([
    'ok' => true,
    'itemId' => $itemId,
    'completedToday' => false
]);