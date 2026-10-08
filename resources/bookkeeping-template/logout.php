<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['bk_logged_in']);
unset($_SESSION['bk_username']);
session_destroy();
setcookie('bk_remember_token', '', time() - 3600, '/');
header('Location: login.php');
exit;
