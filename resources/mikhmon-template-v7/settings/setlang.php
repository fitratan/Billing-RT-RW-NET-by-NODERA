<?php
/*
 *  MIKHMON Language Switcher Handler
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
error_reporting(0);

include_once __DIR__ . '/../lang/isocodelang.php';

$getlang = $_GET['setlang'] ?? '';

if (!empty($getlang) && !empty($isocodelang[$getlang])) {
    $gen = '<?php $langid="' . addslashes($getlang) . '";?>';
    $slang = __DIR__ . '/../include/lang.php';
    @file_put_contents($slang, $gen);
    
    $_SESSION['lang'] = $getlang;
    $_SESSION['m_lang'] = $getlang;
    @setcookie('mikhmon_lang', $getlang, time() + 31536000, '/');
    $langid = $getlang;
    
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        session_write_close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'lang' => $getlang]);
        exit;
    }
    
    // Clean return URL
    $targetUrl = $_SERVER['REQUEST_URI'] ?? './admin.php?id=sessions';
    $parsed = parse_url($targetUrl);
    $path = $parsed['path'] ?? './admin.php';
    $query = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    unset($query['setlang'], $query['_t'], $query['_nc']);
    $qs = http_build_query($query);
    $cleanLangUrl = ($path ?: './admin.php') . ($qs ? '?' . $qs : '');
    $sep = (strpos($cleanLangUrl, '?') !== false) ? '&' : '?';
    $redirectUrl = $cleanLangUrl . $sep . '_t=' . time();
    
    session_write_close();

    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: " . $redirectUrl);
        exit;
    }
    echo "<script>window.location.replace('" . addslashes($redirectUrl) . "');</script>";
    exit;
}
?>