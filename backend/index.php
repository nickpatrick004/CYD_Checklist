<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: /cyd/checklist.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("
        SELECT id, password_hash
        FROM cyd_parent_users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();
    $conn->close();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['parent_user_id'] = (int)$user['id'];
        $_SESSION['parent_username'] = $username;
        header('Location: /cyd/checklist.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>CYD Parent Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 420px;
            margin: 60px auto;
            padding: 20px;
        }
        input, button {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            font-size: 16px;
        }
        button {
            cursor: pointer;
        }
        .error {
            color: #b00020;
        }
    </style>
</head>
<body>
    <h1>CYD Parent Login</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input name="username" placeholder="Username" autocomplete="username" required>
        <input name="password" type="password" placeholder="Password" autocomplete="current-password" required>
        <button type="submit">Log In</button>
    </form>
</body>
</html>
