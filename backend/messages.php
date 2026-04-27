<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$deviceId = 1;
$showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

function json_response(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function current_url_for_redirect(bool $showArchived): string {
    return '/cyd/messages.php' . ($showArchived ? '?archived=1' : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send';

    if ($action === 'send') {
        $summary = trim($_POST['summary'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $detail = trim($_POST['detail'] ?? '');
        if ($summary === '' && $message !== '') $summary = mb_substr($message, 0, 180);
        if ($message === '' && $summary !== '') $message = $summary;
        if ($detail === '') $detail = null;

        if ($message !== '') {
            $stmt = $conn->prepare("\n                INSERT INTO cyd_messages (device_id, sender, summary, message, detail_text, is_read, is_archived, created_at)\n                VALUES (?, 'parent', ?, ?, ?, 0, 0, NOW())\n            ");
            $stmt->bind_param("isss", $deviceId, $summary, $message, $detail);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'archive' || $action === 'restore' || $action === 'delete' || $action === 'mark_read') {
        $messageId = (int)($_POST['message_id'] ?? 0);

        if ($messageId > 0 && $action === 'archive') {
            $stmt = $conn->prepare("UPDATE cyd_messages SET is_archived = 1, archived_at = NOW() WHERE id = ? AND device_id = ?");
            $stmt->bind_param("ii", $messageId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }

        if ($messageId > 0 && $action === 'restore') {
            $stmt = $conn->prepare("UPDATE cyd_messages SET is_archived = 0, archived_at = NULL WHERE id = ? AND device_id = ?");
            $stmt->bind_param("ii", $messageId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }

        if ($messageId > 0 && $action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM cyd_messages WHERE id = ? AND device_id = ?");
            $stmt->bind_param("ii", $messageId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }

        if ($messageId > 0 && $action === 'mark_read') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("\n                    INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)\n                    SELECT id, ?, NOW()\n                    FROM cyd_messages\n                    WHERE id = ? AND device_id = ? AND sender = 'parent'\n                ");
                $stmt->bind_param("iii", $deviceId, $messageId, $deviceId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE cyd_messages SET is_read = 1 WHERE id = ? AND device_id = ? AND sender = 'parent'");
                $stmt->bind_param("ii", $messageId, $deviceId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
            }
        }
    }

    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("\n            INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)\n            SELECT id, ?, NOW()\n            FROM cyd_messages\n            WHERE device_id = ?\n              AND sender = 'parent'\n              AND COALESCE(is_archived, 0) = 0\n        ");
        $stmt->bind_param("ii", $deviceId, $deviceId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE cyd_messages SET is_read = 1 WHERE device_id = ? AND sender = 'parent' AND COALESCE(is_archived, 0) = 0");
        $stmt->bind_param("i", $deviceId);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
        json_response(['ok' => true]);
    }

    header('Location: ' . current_url_for_redirect($showArchived));
    exit;
}

function load_messages(mysqli $conn, int $deviceId, int $archivedFilter, int $page, int $perPage, int $offset): array {
    $countStmt = $conn->prepare("\n        SELECT COUNT(*) AS total\n        FROM cyd_messages\n        WHERE device_id = ?\n          AND COALESCE(is_archived, 0) = ?\n    ");
    $countStmt->bind_param("ii", $deviceId, $archivedFilter);
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $messages = [];
    $stmt = $conn->prepare("\n        SELECT m.id, m.sender, m.summary, m.message, m.detail_text, m.created_at, m.is_archived, m.archived_at,\n               CASE WHEN m.sender <> 'parent' THEN 1 WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read\n        FROM cyd_messages m\n        LEFT JOIN cyd_message_reads r\n          ON r.message_id = m.id\n         AND r.reader_device_id = ?\n        WHERE m.device_id = ?\n          AND COALESCE(m.is_archived, 0) = ?\n        ORDER BY m.created_at DESC, m.id DESC\n        LIMIT ? OFFSET ?\n    ");
    $stmt->bind_param("iiiii", $deviceId, $deviceId, $archivedFilter, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $messages[] = $row;
    $stmt->close();

    return [
        'messages' => $messages,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ],
    ];
}

$archivedFilter = $showArchived ? 1 : 0;
$data = load_messages($conn, $deviceId, $archivedFilter, $page, $perPage, $offset);

if ($isAjax) {
    json_response(['ok' => true] + $data);
}

$conn->close();
$messages = $data['messages'];
$pagination = $data['pagination'];
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>CYD Messages</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; max-width: 960px; margin: 30px auto; padding: 20px; background: #f6f7f9; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 12px; }
        .card { background: white; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        input, textarea, button, select { width: 100%; padding: 12px; font-size: 16px; box-sizing: border-box; margin: 6px 0 12px; }
        textarea { min-height: 90px; }
        button { cursor: pointer; }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid transparent; }
        .parent { background: #e8f0ff; }
        .kid { background: #e9f8ec; }
        .unread { border-color: #d67a00; box-shadow: inset 4px 0 0 #d67a00; }
        .meta { font-size: 13px; color: #555; margin-bottom: 4px; }
        .detail { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,.1); color: #333; }
        .row-actions, .toolbar, .pager { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .row-actions form, .toolbar form { margin: 0; }
        .row-actions button, .toolbar button, .pager button, .toolbar select { width: auto; padding: 8px 10px; margin: 0; font-size: 14px; }
        .danger { background: #8b0000; color: white; border: 0; border-radius: 5px; }
        .secondary { background: #eee; border: 1px solid #bbb; border-radius: 5px; }
        .toolbar h2 { margin-right: auto; }
        .muted { color: #666; font-size: 14px; }
        .live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#2a8f2a; margin-right:6px; }
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
    <form method="post" class="ajax-form" data-reset="1">
        <input type="hidden" name="action" value="send">
        <label>Short summary</label>
        <input name="summary" maxlength="255" placeholder="Dinner in 10 minutes">
        <label>Message</label>
        <textarea name="message" placeholder="Type the main message..." required></textarea>
        <label>Optional detail/body text</label>
        <textarea name="detail" placeholder="Extra instructions shown on the CYD detail screen."></textarea>
        <button type="submit">Send</button>
    </form>
</div>

<div class="card">
    <div class="toolbar">
        <h2><?= $showArchived ? 'Archived Messages' : 'Active Messages' ?></h2>
        <span class="muted"><span class="live-dot"></span>Auto-refresh on</span>
        <label class="muted">Per page
            <select id="perPageSelect">
                <?php foreach ([10,20,50,100] as $n): ?>
                    <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!$showArchived): ?>
            <form method="post" class="ajax-form">
                <input type="hidden" name="action" value="mark_all_read">
                <button class="secondary" type="submit">Mark all active read</button>
            </form>
            <a href="/cyd/messages.php?archived=1">View archived</a>
        <?php else: ?>
            <a href="/cyd/messages.php">View active</a>
        <?php endif; ?>
    </div>

    <div id="messageList"></div>
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
    archived: <?= $showArchived ? '1' : '0' ?>,
    totalPages: <?= (int)$pagination['total_pages'] ?>,
    loading: false
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
}

function nl2br(value) {
    return escapeHtml(value).replace(/\n/g, '<br>');
}

function actionForm(action, id, label, cls = 'secondary', confirmText = '') {
    const confirmAttr = confirmText ? ` data-confirm="${escapeHtml(confirmText)}"` : '';
    return `<form method="post" class="ajax-form"${confirmAttr}>
        <input type="hidden" name="action" value="${escapeHtml(action)}">
        <input type="hidden" name="message_id" value="${Number(id)}">
        <button class="${escapeHtml(cls)}" type="submit">${escapeHtml(label)}</button>
    </form>`;
}

function renderMessages(messages, pagination) {
    const list = document.getElementById('messageList');
    if (!messages.length) {
        list.innerHTML = '<p>No messages in this view.</p>';
    } else {
        list.innerHTML = messages.map(msg => {
            const readText = Number(msg.is_read) ? 'Read on CYD' : 'Unread on CYD';
            const archivedText = Number(msg.is_archived) ? ` · Archived ${escapeHtml(msg.archived_at || '')}` : '';
            const title = msg.summary || msg.message || '';
            const detail = msg.detail_text ? `<div class="detail">${nl2br(msg.detail_text)}</div>` : '';
            const readButton = (!Number(msg.is_read) && msg.sender === 'parent') ? actionForm('mark_read', msg.id, 'Mark read') : '';
            const archiveButton = state.archived ? actionForm('restore', msg.id, 'Restore') : actionForm('archive', msg.id, 'Archive');
            const deleteButton = actionForm('delete', msg.id, 'Delete', 'danger', 'Permanently delete this message?');
            return `<div class="message ${escapeHtml(msg.sender)} ${Number(msg.is_read) ? '' : 'unread'}">
                <div class="meta">${escapeHtml(String(msg.sender).toUpperCase())} · ${escapeHtml(msg.created_at)} · ${readText}${archivedText}</div>
                <strong>${escapeHtml(title)}</strong>
                <div>${nl2br(msg.message)}</div>
                ${detail}
                <div class="row-actions">${readButton}${archiveButton}${deleteButton}</div>
            </div>`;
        }).join('');
    }

    state.totalPages = pagination.total_pages;
    document.getElementById('pageInfo').textContent = `Page ${pagination.page} of ${pagination.total_pages} · ${pagination.total} total`;
    document.getElementById('prevPage').disabled = pagination.page <= 1;
    document.getElementById('nextPage').disabled = pagination.page >= pagination.total_pages;
}

async function loadMessages() {
    if (state.loading) return;
    state.loading = true;
    try {
        const url = `/cyd/messages.php?ajax=1&archived=${state.archived}&page=${state.page}&per_page=${state.perPage}`;
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();
        if (data.ok) renderMessages(data.messages, data.pagination);
    } catch (err) {
        console.error(err);
    } finally {
        state.loading = false;
    }
}

async function submitAjaxForm(form) {
    const confirmText = form.dataset.confirm;
    if (confirmText && !confirm(confirmText)) return;
    const url = `/cyd/messages.php?ajax=1&archived=${state.archived}`;
    const response = await fetch(url, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
    const data = await response.json();
    if (data.ok) {
        if (form.dataset.reset === '1') form.reset();
        await loadMessages();
    }
}

document.addEventListener('submit', event => {
    const form = event.target.closest('.ajax-form');
    if (!form) return;
    event.preventDefault();
    submitAjaxForm(form);
});

document.getElementById('prevPage').addEventListener('click', () => { if (state.page > 1) { state.page--; loadMessages(); } });
document.getElementById('nextPage').addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadMessages(); } });
document.getElementById('refreshNow').addEventListener('click', loadMessages);
document.getElementById('perPageSelect').addEventListener('change', event => { state.perPage = Number(event.target.value); state.page = 1; loadMessages(); });

loadMessages();
setInterval(loadMessages, 5000);
</script>

</body>
</html>
