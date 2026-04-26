<?php
require_once __DIR__ . '/config.php';

function get_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => 'Database connection failed'
        ]);
        exit;
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}
?>