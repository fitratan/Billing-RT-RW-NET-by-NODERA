<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified by NODERA for Instant Theme Switching
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
error_reporting(0);

$gettheme = $_GET['set-theme'] ?? '';
$mtheme = array(
    "dark",
    "light",
    "blue",
    "green",
    "pink",
);
$theme_color = array(
    "#3a4149",
    "#008BC9",
    "#008BC9",
    "#4dbd74",
    "#e83e8c",
);

$themenum = array_search($gettheme, $mtheme);
$getthemecolor = ($themenum !== false) ? $theme_color[$themenum] : "#3a4149";

if (!empty($gettheme)) {
    if (in_array($gettheme, $mtheme)) {
        $gen = '<?php $theme="' . $gettheme . '"; $themecolor="'.$getthemecolor.'";?>';
        $stheme = __DIR__ . '/../include/theme.php';
        @file_put_contents($stheme, $gen);
        $_SESSION['theme'] = $gettheme;
        $_SESSION['themecolor'] = $getthemecolor;
        @setcookie('mikhmon_theme', $gettheme, time() + 31536000, '/');
        @setcookie('mikhmon_themecolor', $getthemecolor, time() + 31536000, '/');
        $theme = $gettheme;
        $themecolor = $getthemecolor;
    }

    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        session_write_close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'theme' => $gettheme]);
        exit;
    }

    $reqUri = $url ?? $_SERVER['REQUEST_URI'] ?? ('./?session=' . ($session ?? 'default'));
    $parsed = parse_url($reqUri);
    $path = $parsed['path'] ?? './';
    $query = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    unset($query['set-theme'], $query['_t'], $query['_nc']);
    $qs = http_build_query($query);
    $cleanThemeUrl = ($path ?: './') . ($qs ? '?' . $qs : '');
    $sep = (strpos($cleanThemeUrl, '?') !== false) ? '&' : '?';
    $redirectUrl = $cleanThemeUrl . $sep . '_t=' . time();

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