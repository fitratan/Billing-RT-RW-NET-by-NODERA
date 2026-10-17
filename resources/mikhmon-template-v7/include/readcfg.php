<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
// hide all error
error_reporting(0);
if (substr($_SERVER["REQUEST_URI"], -11) == "readcfg.php") {
    header("Location:./");
};
// read config

$iphost = explode('!', $data[$session][1])[1];
$userhost = explode('@|@', $data[$session][2])[1];
$passwdhost = explode('#|#', $data[$session][3])[1];
$hotspotname = explode('%', $data[$session][4])[1];
$dnsname = explode('^', $data[$session][5])[1];
$currency = explode('&', $data[$session][6])[1];
$areload = !empty($data[$session][7]) ? (explode('*', $data[$session][7])[1] ?? '10') : '10';
if (empty($areload) || intval($areload) < 5) {
    $areload = '10';
}
$iface = explode('(', $data[$session][8])[1];
$infolp = explode(')', $data[$session][9])[1];
$idleto = explode('=', $data[$session][10])[1];
$sesname = explode('+', $data[$session][10])[1];
$useradm = explode('<|<', $data['mikhmon'][1])[1];
$passadm = explode('>|>', $data['mikhmon'][2])[1];
$livereport = 'enable';

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);

if (!function_exists('mikhmon_decrypt')) {
    function mikhmon_decrypt($string, $key=128) {
        $result = '';
        $string = base64_decode($string);
        for($i=0, $k=strlen($string); $i< $k ; $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key))-1, 1);
            $char = chr(ord($char)-ord($keychar));
            $result .= $char;
        }
        return $result;
    }
}

if (!function_exists('mikhmon_encrypt')) {
    function mikhmon_encrypt($string, $key=128) {
        $result = '';
        for($i=0, $k= strlen($string); $i<$k; $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key))-1, 1);
            $char = chr(ord($char)+ord($keychar));
            $result .= $char;
        }
        return base64_encode($result);
    }
}


