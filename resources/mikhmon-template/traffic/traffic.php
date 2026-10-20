<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
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
include_once(__DIR__ . '/../include/init_session.php');
}
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

$session = $_GET['session'] ?? '';
$setIface = $_GET['set_iface'] ?? null;
if (!empty($setIface) && !empty($session)) {
    $_SESSION[$session . '_iface_name'] = $setIface;
    session_write_close();
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'ok', 'iface' => $setIface));
    exit;
}
$interface = $_GET['iface'] ?? ($_SESSION[$session . '_iface_name'] ?? '');

// Release session lock immediately
session_write_close();

include('../include/config.php');
include('../include/readcfg.php');
include_once('../lib/routeros_api.class.php');
include_once('../lib/formatbytesbites.php');

$API = new RouterosAPI();
$API->debug = false;

header('Content-Type: application/json');

if (!empty($interface) && $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost))) {
    $getinterfacetraffic = $API->comm("/interface/monitor-traffic", array(
        "interface" => "$interface",
        "once" => "",
        ".proplist" => "rx-bits-per-second,tx-bits-per-second"
    ));
    $ftx = isset($getinterfacetraffic[0]['tx-bits-per-second']) ? (float)$getinterfacetraffic[0]['tx-bits-per-second'] : 0;
    $frx = isset($getinterfacetraffic[0]['rx-bits-per-second']) ? (float)$getinterfacetraffic[0]['rx-bits-per-second'] : 0;

    $rows = array('name' => 'Tx', 'data' => array($ftx));
    $rows2 = array('name' => 'Rx', 'data' => array($frx));
    echo json_encode(array($rows, $rows2));
} else {
    echo json_encode(array(
        array('name' => 'Tx', 'data' => array(0)),
        array('name' => 'Rx', 'data' => array(0))
    ));
}
exit;
