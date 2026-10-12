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
        $theme = $gettheme;
        $themecolor = $getthemecolor;
    }

    $reqUri = $url ?? $_SERVER['REQUEST_URI'] ?? ('./?session=' . ($session ?? 'default'));
    $cleanThemeUrl = preg_replace('/([?&])set-theme=[^&]*(&|$)/', '$1', $reqUri);
    $cleanThemeUrl = rtrim($cleanThemeUrl, '?&');
    if (empty($cleanThemeUrl) || $cleanThemeUrl === '?' || $cleanThemeUrl === '&') {
        $cleanThemeUrl = './?session=' . ($session ?? 'default');
    }

    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: " . $cleanThemeUrl);
        exit;
    }
    echo "<script>window.location.replace('" . addslashes($cleanThemeUrl) . "');</script>";
    exit;
}
?>