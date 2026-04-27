<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$deviceId = 1;
$showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';

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
            $stmt = $conn->prepare("
                INSERT INTO cyd_messages (device_id, sender, summary, message, detail_text, is_read, is_archived, created_at)
                VALUES (?, 'parent', ?, ?, ?, 0, 0, NOW())
            ");
            $stmt->bind_param("isss", $deviceId, $summary, $message, $detail);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'archive') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $stmt = $conn->prepare("UPDATE cyd_messages SET is_archived = 1, archived_at = NOW() WHERE id = ? AND device_id = ?");
            $stmt->bind_param("ii", $messageId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'restore') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $stmt = $conn->prepare("UPDATE cyd_messages SET is_archived = 0, archived_at = NULL WHERE id = ? AND device_id = ?");
            $stmt->bind_param("ii", $messageId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $stmt = $conn->prepare("DELETE FROM cyd_messages WHERE id = ? AND device_id = ?");
            $stmt->bind_param("ii", $messageId, $deviceId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'mark_read') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)
                    SELECT id, ?, NOW()
                    FROM cyd_messages
                    WHERE id = ? AND device_id = ? AND sender = 'parent'
                ");
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
        $stmt = $conn->prepare("
            INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)
            SELECT id, ?, NOW()
            FROM cyd_messages
            WHERE device_id = ?
              AND sender = 'parent'
              AND COALESCE(is_archived, 0) = 0
        ");
        $stmt->bind_param("ii", $deviceId, $deviceId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE cyd_messages SET is_read = 1 WHERE device_id = ? AND sender = 'parent' AND COALESCE(is_archived, 0) = 0");
        $stmt->bind_param("i", $deviceId);
        $stmt->execute();
        $stmt->close();
    }

    $redirect = '/cyd/messages.php' . ($showArchived ? '?archived=1' : '');
    header('Location: ' . $redirect);
    exit;
}

$messages = [];
$archivedFilter = $showArchived ? 1 : 0;
$stmt = $conn->prepare("
    SELECT m.id, m.sender, m.summary, m.message, m.detail_text, m.created_at, m.is_archived, m.archived_at,
           CASE WHEN m.sender <> 'parent' THEN 1 WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read
    FROM cyd_messages m
    LEFT JOIN cyd_message_reads r
      ON r.message_id = m.id
     AND r.reader_device_id = ?
    WHERE m.device_id = ?
      AND COALESCE(m.is_archived, 0) = ?
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT 100
");
$stmt->bind_param("iii", $deviceId, $deviceId, $archivedFilter);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $messages[] = $row;
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
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f6f7f9; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 12px; }
        .card { background: white; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        input, textarea, button { width: 100%; padding: 12px; font-size: 16px; box-sizing: border-box; margin: 6px 0 12px; }
        textarea { min-height: 90px; }
        button { cursor: pointer; }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid transparent; }
        .parent { background: #e8f0ff; }
        .kid { background: #e9f8ec; }
        .unread { border-color: #d67a00; }
        .meta { font-size: 13px; color: #555; margin-bottom: 4px; }
        .detail { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,.1); color: #333; }
        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .row-actions form { margin: 0; }
        .row-actions button { width: auto; padding: 8px 10px; margin: 0; font-size: 14px; }
        .danger { background: #8b0000; color: white; border: 0; border-radius: 5px; }
        .secondary { background: #eee; border: 1px solid #bbb; border-radius: 5px; }
        .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .toolbar a, .toolbar button { width: auto; }
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
        <h2 style="margin-right:auto;"><?= $showArchived ? 'Archived Messages' : 'Active Messages' ?></h2>
        <?php if (!$showArchived): ?>
            <form method="post" style="margin:0;">
                <input type="hidden" name="action" value="mark_all_read">
                <button class="secondary" type="submit">Mark all active read</button>
            </form>
            <a href="/cyd/messages.php?archived=1">View archived</a>
        <?php else: ?>
            <a href="/cyd/messages.php">View active</a>
        <?php endif; ?>
    </div>

    <?php if (empty($messages)): ?>
        <p>No messages in this view.</p>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message <?= htmlspecialchars($msg['sender']) ?> <?= !$msg['is_read'] ? 'unread' : '' ?>">
                <div class="meta">
                    <?= htmlspecialchars(strtoupper($msg['sender'])) ?>
                    · <?= htmlspecialchars($msg['created_at']) ?>
                    · <?= $msg['is_read'] ? 'Read on CYD' : 'Unread on CYD' ?>
                    <?php if ($msg['is_archived']): ?>
                        · Archived <?= htmlspecialchars($msg['archived_at'] ?? '') ?>
                    <?php endif; ?>
                </div>
                <strong><?= htmlspecialchars($msg['summary'] ?: $msg['message']) ?></strong>
                <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                <?php if (!empty($msg['detail_text'])): ?>
                    <div class="detail"><?= nl2br(htmlspecialchars($msg['detail_text'])) ?></div>
                <?php endif; ?>
                <div class="row-actions">
                    <?php if (!$msg['is_read'] && $msg['sender'] === 'parent'): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                            <button class="secondary" type="submit">Mark read</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$showArchived): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                            <button class="secondary" type="submit">Archive</button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                            <button class="secondary" type="submit">Restore</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Permanently delete this message?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                        <button class="danger" type="submit">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
