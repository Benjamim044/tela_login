<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['email'])) {
    write_log('logout_success', ['email' => $_SESSION['email'], 'user_id' => $_SESSION['user_id'] ?? null]);
} else {
    write_log('logout_without_session');
}

session_unset();
session_destroy();

if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

header('Location: login.php');
exit;
