<?php
/**
 * MIKHMON — Admin Login Entrypoint (/login or login.php)
 * by NODERA (nodera.id)
 */
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}
$qs = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header("Location: ./admin.php?id=login" . $qs);
exit;
