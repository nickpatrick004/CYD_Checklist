<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$devices = [];

$result = $conn->query("
    SELECT id, device_name, created_at, last_seen_at
    FROM cyd_devices
    ORDER BY id ASC
");

while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

$conn->close();

function status_text($lastSeen) {
    if (!$lastSeen) {
        return 'Never connected';
    }

    $last = strtotime($lastSeen);
    $now = time();
    $diff = $now - $last;

    if ($diff < 30) {
        return 'Online';
    }

    if ($diff < 300) {
        return 'Recently online';
    }

    return 'Offline';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>CYD Status</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .online {
            color: green;
            font-weight: bold;
        }
        .offline {
            color: #b00020;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h1>Device Status</h1>

<nav>
    <a href="/cyd/checklist.php">Checklist</a>
    <a href="/cyd/messages.php">Messages</a>
    <a href="/cyd/status.php">Status</a>
    <a href="/cyd/logout.php">Log out</a>
</nav>

<div class="card">
    <h2>CYD Devices</h2>

    <?php if (empty($devices)): ?>
        <p>No devices found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Device</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $device): ?>
                    <?php
                        $status = status_text($device['last_seen_at']);
                        $class = $status === 'Online' ? 'online' : 'offline';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($device['device_name']) ?></td>
                        <td class="<?= $class ?>"><?= htmlspecialchars($status) ?></td>
                        <td><?= htmlspecialchars($device['last_seen_at'] ?? 'Never') ?></td>
                        <td><?= htmlspecialchars($device['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>