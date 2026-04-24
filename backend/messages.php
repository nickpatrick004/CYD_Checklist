<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$deviceId = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message !== '') {
        $stmt = $conn->prepare("
            INSERT INTO cyd_messages
                (device_id, sender, message, is_read, created_at)
            VALUES
                (?, 'parent', ?, 0, NOW())
        ");
        $stmt->bind_param("is", $deviceId, $message);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: /cyd/messages.php');
    exit;
}

$messages = [];

$stmt = $conn->prepare("
    SELECT id, sender, message, is_read, created_at
    FROM cyd_messages
    WHERE device_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $deviceId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();
$conn->close();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>CYD Messages</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 850px;
            margin: 30px auto;
            padding: 20px;
            background: #f6f7f9;
        }
        nav {
            margin-bottom: 20px;
        }
        nav a {
            margin-right: 12px;
        }
        .card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }
        textarea, button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            box-sizing: border-box;
        }
        textarea {
            min-height: 90px;
        }
        button {
            margin-top: 8px;
            cursor: pointer;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .parent {
            background: #e8f0ff;
        }
        .kid {
            background: #e9f8ec;
        }
        .meta {
            font-size: 13px;
            color: #555;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

<h1>Messages</h1>

<nav>
    <a href="/cyd/checklist.php">Checklist</a>
    <a href="/cyd/messages.php">Messages</a>
    <a href="/cyd/status.php">Status</a>
    <a href="/cyd/logout.php">Log out</a>
</nav>

<div class="card">
    <h2>Send Message to CYD</h2>

    <form method="post">
        <textarea name="message" placeholder="Type a message..." required></textarea>
        <button type="submit">Send</button>
    </form>
</div>

<div class="card">
    <h2>Recent Messages</h2>

    <?php if (empty($messages)): ?>
        <p>No messages yet.</p>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message <?= htmlspecialchars($msg['sender']) ?>">
                <div class="meta">
                    <?= htmlspecialchars(strtoupper($msg['sender'])) ?>
                    ·
                    <?= htmlspecialchars($msg['created_at']) ?>
                    ·
                    <?= $msg['is_read'] ? 'Read' : 'Unread' ?>
                </div>
                <div>
                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>