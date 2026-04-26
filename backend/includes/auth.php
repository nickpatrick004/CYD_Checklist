<?php
session_start();

function require_login() {
    if (empty($_SESSION['parent_user_id'])) {
        header('Location: /cyd/index.php');
        exit;
    }
}

function is_logged_in() {
    return !empty($_SESSION['parent_user_id']);
}
?>