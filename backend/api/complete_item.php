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
$itemId = isset($input['itemId']) ? (int)$input['itemId'] : 0;

if ($token === '' || $itemId <= 0) {
    respond([
        'ok' => false,
        'error' => 'Missing token or itemId'
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

/*
|--------------------------------------------------------------------------
| Verify item belongs to this device
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM cyd_checklist_items
    WHERE id = ?
      AND device_id = ?
      AND is_active = 1
    LIMIT 1
");

$stmt->bind_param("ii", $itemId, $deviceId);
$stmt->execute();

$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    respond([
        'ok' => false,
        'error' => 'Checklist item not found'
    ], 404);
}

/*
|--------------------------------------------------------------------------
| Mark completed for today
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO cyd_item_completions
        (item_id, device_id, completed_date, completed_at)
    VALUES
        (?, ?, CURDATE(), NOW())
    ON DUPLICATE KEY UPDATE
        completed_at = NOW()
");

$stmt->bind_param("ii", $itemId, $deviceId);
$stmt->execute();
$stmt->close();

$conn->close();

respond([
    'ok' => true,
    'itemId' => $itemId,
    'completedToday' => true
]);