<?php
/*
 *  MIKHMON Language Switcher Handler
 */
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
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
    
    // Clean return URL
    $targetUrl = $_SERVER['REQUEST_URI'] ?? './admin.php?id=sessions';
    $targetUrl = preg_replace('/([?&])(setlang|_t|_nc)=[^&]*(&|$)/', '$1', $targetUrl);
    $targetUrl = rtrim($targetUrl, '?&');
    if (empty($targetUrl) || $targetUrl === '?' || $targetUrl === '&') {
        $targetUrl = './admin.php?id=sessions';
    }
    $sep = (strpos($targetUrl, '?') !== false) ? '&' : '?';
    $redirectUrl = $targetUrl . $sep . '_t=' . time();
    
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