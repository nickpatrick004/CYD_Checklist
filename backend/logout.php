<?php
session_start();
session_destroy();
header('Location: /cyd/index.php');
exit;
?>