<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$deviceId = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $dueTime = $_POST['due_time'] ?: null;
        $detail = trim($_POST['detail'] ?? '');
        if ($detail === '') $detail = null;
        $repeatDays = trim($_POST['repeat_days'] ?? 'daily');
        $alertEnabled = isset($_POST['alert_enabled']) ? 1 : 0;

        if ($title !== '') {
            $stmt = $conn->prepare("
                INSERT INTO cyd_checklist_items
                    (device_id, title, detail_text, due_time, repeat_days, alert_enabled)
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssi", $deviceId, $title, $detail, $dueTime, $repeatDays, $alertEnabled);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $itemId = (int)($_POST['item_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE cyd_checklist_items
            SET is_active = 0
            WHERE id = ?
              AND device_id = ?
        ");
        $stmt->bind_param("ii", $itemId, $deviceId);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: /cyd/checklist.php');
    exit;
}

$items = [];

$stmt = $conn->prepare("
    SELECT
        i.id,
        i.title,
        i.detail_text,
        i.due_time,
        i.repeat_days,
        i.alert_enabled,
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

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();
$conn->close();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>CYD Checklist</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 850px;
            margin: 30px auto;
            padding: 20px;
            background: #f6f7f9;
        }
        h1 {
            margin-bottom: 5px;
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
        input, select, button {
            padding: 10px;
            margin: 5px 0;
            font-size: 15px;
        }
        input[type="text"], input[type="time"], textarea, select {
            width: 100%;
            box-sizing: border-box;
        }
        button {
            cursor: pointer;
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
        .done {
            color: green;
            font-weight: bold;
        }
        .not-done {
            color: #b00020;
            font-weight: bold;
        }
        .delete {
            background: #b00020;
            color: white;
            border: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<h1>Checklist Dashboard</h1>

<nav>
    <a href="/cyd/checklist.php">Checklist</a>
    <a href="/cyd/messages.php">Messages</a>
    <a href="/cyd/status.php">Status</a>
    <a href="/cyd/logout.php">Log out</a>
</nav>

<div class="card">
    <h2>Add Checklist Item</h2>

    <form method="post">
        <input type="hidden" name="action" value="add">

        <label>Title</label>
        <input type="text" name="title" placeholder="Brush teeth" required>

        <label>Detail / instruction text</label>
        <textarea name="detail" placeholder="Optional details shown when the item or alert is opened."></textarea>

        <label>Due Time</label>
        <input type="time" name="due_time">

        <label>Repeat</label>
        <select name="repeat_days">
            <option value="daily">Daily</option>
            <option value="mon,tue,wed,thu,fri">School nights / weekdays</option>
            <option value="sat,sun">Weekends</option>
            <option value="mon">Monday</option>
            <option value="tue">Tuesday</option>
            <option value="wed">Wednesday</option>
            <option value="thu">Thursday</option>
            <option value="fri">Friday</option>
            <option value="sat">Saturday</option>
            <option value="sun">Sunday</option>
        </select>

        <label>
            <input type="checkbox" name="alert_enabled" checked>
            Alert enabled
        </label>

        <br>
        <button type="submit">Add Item</button>
    </form>
</div>

<div class="card">
    <h2>Current Checklist</h2>

    <?php if (empty($items)): ?>
        <p>No checklist items yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Detail</th>
                    <th>Due</th>
                    <th>Repeat</th>
                    <th>Alert</th>
                    <th>Today</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['title']) ?></td>
                        <td><?= htmlspecialchars($item['detail_text'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['due_time'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['repeat_days'] ?? '') ?></td>
                        <td><?= $item['alert_enabled'] ? 'Yes' : 'No' ?></td>
                        <td>
                            <?php if ($item['completed_today']): ?>
                                <span class="done">Done</span>
                            <?php else: ?>
                                <span class="not-done">Not done</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('Remove this item?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <button class="delete" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>