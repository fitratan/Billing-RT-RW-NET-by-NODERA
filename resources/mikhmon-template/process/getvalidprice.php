<?php
/*
 *  Copyright (C) 2019 Laksamadi Guko.
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
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// load session MikroTik
  $session = $_GET['session'];

// load config
  include('../include/config.php');
  $iphost = explode('!', $data[$session][1])[1];
  $userhost = explode('@|@', $data[$session][2])[1];
  $passwdhost = explode('#|#', $data[$session][3])[1];
  $curency = explode('&', $data[$session][6])[1];

// lang
// lang
include_once __DIR__ . '/../lang/isocodelang.php';
$langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
if (empty($langid) || empty($isocodelang[$langid])) {
    if (file_exists(__DIR__ . '/../include/lang.php')) {
        include __DIR__ . '/../include/lang.php';
    }
    if (empty($langid) || empty($isocodelang[$langid])) {
        $langid = 'id';
    }
}
$_SESSION['lang'] = $langid;
if (!file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    $langid = 'id';
}
include __DIR__ . '/../lang/' . $langid . '.php';

  include_once('../lib/routeros_api.class.php');

  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  $uprofname = $_GET['name'] ?? '';
  if ($uprofname != "") {
    $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$uprofname"));
    $ponlogin = isset($getprofile[0]['on-login']) ? (string)$getprofile[0]['on-login'] : '';
    $parts = explode(",", $ponlogin);
    $validityPart = isset($parts[3]) ? trim($parts[3]) : '';
    $priceRaw = isset($parts[2]) ? trim($parts[2]) : '';
    $spriceRaw = isset($parts[4]) ? trim($parts[4]) : '';
    $lockPart = isset($parts[6]) ? trim($parts[6]) : '';

    $getvalid = $validityPart !== '' ? $_validity . " : " . $validityPart : '';
    $getlock = $lockPart !== '' ? "| " . $_lock_user . " : " . $lockPart : '';

    $price = "";
    if (is_numeric($priceRaw) && (float)$priceRaw > 0) {
      if ($curency == "Rp" || $curency == "rp" || $curency == "IDR" || $curency == "idr") {
        $price = "| " . $_price . " : " . $curency . " " . number_format((float)$priceRaw, 0, ",", ".");
      } else {
        $price = "| " . $_price . " : " . $curency . " " . number_format((float)$priceRaw);
      }
    }
    $sprice = "";
    if (is_numeric($spriceRaw) && (float)$spriceRaw > 0) {
      if ($curency == "Rp" || $curency == "rp" || $curency == "IDR" || $curency == "idr") {
        $sprice = "| " . $_selling_price . " : " . $curency . " " . number_format((float)$spriceRaw, 0, ",", ".");
      } else {
        $sprice = "| " . $_selling_price . " : " . $curency . " " . number_format((float)$spriceRaw);
      }
    }
    echo '<b id="getdata">' . trim($getvalid . ' ' . $price . ' ' . $sprice . ' ' . $getlock) . '</b>';
    echo '<span id="validity">' . $validityPart . '</span> ';
  }
}
?>
