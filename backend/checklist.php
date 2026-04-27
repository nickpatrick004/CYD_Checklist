<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$deviceId = 1;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

function json_response(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

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
            $stmt = $conn->prepare("\n                INSERT INTO cyd_checklist_items\n                    (device_id, title, detail_text, due_time, repeat_days, alert_enabled)\n                VALUES\n                    (?, ?, ?, ?, ?, ?)\n            ");
            $stmt->bind_param("issssi", $deviceId, $title, $detail, $dueTime, $repeatDays, $alertEnabled);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $conn->prepare("\n                UPDATE cyd_checklist_items\n                SET is_active = 0\n                WHERE id = ?\n                  AND device_id = ?\n            ");
            $stmt->bind_param("ii", $itemId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($isAjax) {
        json_response(['ok' => true]);
    }

    header('Location: /cyd/checklist.php');
    exit;
}

function load_items(mysqli $conn, int $deviceId, int $page, int $perPage, int $offset): array {
    $countStmt = $conn->prepare("\n        SELECT COUNT(*) AS total\n        FROM cyd_checklist_items\n        WHERE device_id = ?\n          AND is_active = 1\n    ");
    $countStmt->bind_param("i", $deviceId);
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $items = [];
    $stmt = $conn->prepare("\n        SELECT\n            i.id,\n            i.title,\n            i.detail_text,\n            i.due_time,\n            i.repeat_days,\n            i.alert_enabled,\n            CASE WHEN c.id IS NULL THEN 0 ELSE 1 END AS completed_today\n        FROM cyd_checklist_items i\n        LEFT JOIN cyd_item_completions c\n            ON c.item_id = i.id\n            AND c.device_id = i.device_id\n            AND c.completed_date = CURDATE()\n        WHERE i.device_id = ?\n          AND i.is_active = 1\n        ORDER BY i.due_time IS NULL, i.due_time ASC, i.id ASC\n        LIMIT ? OFFSET ?\n    ");
    $stmt->bind_param("iii", $deviceId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $stmt->close();

    return [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ],
    ];
}

$data = load_items($conn, $deviceId, $page, $perPage, $offset);

if ($isAjax) {
    json_response(['ok' => true] + $data);
}

$conn->close();
$pagination = $data['pagination'];
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>CYD Checklist</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; max-width: 960px; margin: 30px auto; padding: 20px; background: #f6f7f9; }
        h1 { margin-bottom: 5px; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 12px; }
        .card { background: white; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        input, select, button, textarea { padding: 10px; margin: 5px 0; font-size: 15px; }
        input[type="text"], input[type="time"], textarea, select { width: 100%; box-sizing: border-box; }
        button { cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        .done { color: green; font-weight: bold; }
        .not-done { color: #b00020; font-weight: bold; }
        .delete { background: #b00020; color: white; border: none; border-radius: 5px; }
        .secondary { background: #eee; border: 1px solid #bbb; border-radius: 5px; }
        .toolbar, .pager { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
        .toolbar h2 { margin-right: auto; }
        .toolbar button, .toolbar select, .pager button { width: auto; padding: 8px 10px; margin: 0; font-size: 14px; }
        .muted { color: #666; font-size: 14px; }
        .live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#2a8f2a; margin-right:6px; }
        .detail-cell { max-width: 330px; white-space: pre-wrap; }
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

    <form method="post" class="ajax-form" data-reset="1">
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
    <div class="toolbar">
        <h2>Current Checklist</h2>
        <span class="muted"><span class="live-dot"></span>Auto-refresh on</span>
        <label class="muted">Per page
            <select id="perPageSelect">
                <?php foreach ([10,20,50,100] as $n): ?>
                    <option value="<?= $n ?>" <?= ((int)$pagination['per_page']) === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div id="checklistTable"></div>
    <div class="pager">
        <button class="secondary" id="prevPage" type="button">Previous</button>
        <span id="pageInfo" class="muted"></span>
        <button class="secondary" id="nextPage" type="button">Next</button>
        <button class="secondary" id="refreshNow" type="button">Refresh now</button>
    </div>
</div>

<script>
const state = {
    page: <?= (int)$pagination['page'] ?>,
    perPage: <?= (int)$pagination['per_page'] ?>,
    totalPages: <?= (int)$pagination['total_pages'] ?>,
    loading: false
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
}

function renderItems(items, pagination) {
    const mount = document.getElementById('checklistTable');
    if (!items.length) {
        mount.innerHTML = '<p>No checklist items yet.</p>';
    } else {
        const rows = items.map(item => {
            const done = Number(item.completed_today)
                ? '<span class="done">Done</span>'
                : '<span class="not-done">Not done</span>';
            return `<tr>
                <td>${escapeHtml(item.title)}</td>
                <td class="detail-cell">${escapeHtml(item.detail_text || '')}</td>
                <td>${escapeHtml(item.due_time || '')}</td>
                <td>${escapeHtml(item.repeat_days || '')}</td>
                <td>${Number(item.alert_enabled) ? 'Yes' : 'No'}</td>
                <td>${done}</td>
                <td>
                    <form method="post" class="ajax-form" data-confirm="Remove this item?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="${Number(item.id)}">
                        <button class="delete" type="submit">Remove</button>
                    </form>
                </td>
            </tr>`;
        }).join('');

        mount.innerHTML = `<table>
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
            <tbody>${rows}</tbody>
        </table>`;
    }

    state.totalPages = pagination.total_pages;
    document.getElementById('pageInfo').textContent = `Page ${pagination.page} of ${pagination.total_pages} · ${pagination.total} total`;
    document.getElementById('prevPage').disabled = pagination.page <= 1;
    document.getElementById('nextPage').disabled = pagination.page >= pagination.total_pages;
}

async function loadChecklist() {
    if (state.loading) return;
    state.loading = true;
    try {
        const url = `/cyd/checklist.php?ajax=1&page=${state.page}&per_page=${state.perPage}`;
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();
        if (data.ok) renderItems(data.items, data.pagination);
    } catch (err) {
        console.error(err);
    } finally {
        state.loading = false;
    }
}

async function submitAjaxForm(form) {
    const confirmText = form.dataset.confirm;
    if (confirmText && !confirm(confirmText)) return;
    const response = await fetch('/cyd/checklist.php?ajax=1', { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
    const data = await response.json();
    if (data.ok) {
        if (form.dataset.reset === '1') form.reset();
        await loadChecklist();
    }
}

document.addEventListener('submit', event => {
    const form = event.target.closest('.ajax-form');
    if (!form) return;
    event.preventDefault();
    submitAjaxForm(form);
});

document.getElementById('prevPage').addEventListener('click', () => { if (state.page > 1) { state.page--; loadChecklist(); } });
document.getElementById('nextPage').addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadChecklist(); } });
document.getElementById('refreshNow').addEventListener('click', loadChecklist);
document.getElementById('perPageSelect').addEventListener('change', event => { state.perPage = Number(event.target.value); state.page = 1; loadChecklist(); });

loadChecklist();
setInterval(loadChecklist, 5000);
</script>

</body>
</html>
