<?php
// Temporary helper for creating a parent password hash.
// Upload/run this once, copy the hash into the database, then DELETE this file.

$password = 'CHANGE_THIS_PASSWORD';

echo password_hash($password, PASSWORD_DEFAULT);
