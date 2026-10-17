<?php
/**
 * MIKHMON — Admin Login Entrypoint (/login or login.php)
 * by NODERA (nodera.id)
 */
if (session_status() === PHP_SESSION_NONE) {
    $dir = __DIR__;
    $isRoot = (file_exists($dir . '/buy.php') || file_exists($dir . '/admin.php') || file_exists($dir . '/include/config.php'));
    $mikhRoot = $isRoot ? $dir : (file_exists(dirname($dir) . '/buy.php') || file_exists(dirname($dir) . '/admin.php') ? dirname($dir) : $dir);
    $instanceHash = substr(md5($mikhRoot), 0, 10);
    @session_name('MIKH_' . $instanceHash);
    $cookiePath = $isRoot 
        ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') 
        : rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    @ini_set('session.cookie_path', !empty($cookiePath) ? $cookiePath : '/');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (PHP_VERSION_ID >= 70300) {
        @ini_set('session.cookie_samesite', 'Lax');
    }
    @ini_set('session.cookie_httponly', '1');
    if ($isSecure) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}
$qs = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header("Location: ./admin.php?id=login" . $qs);
exit;
