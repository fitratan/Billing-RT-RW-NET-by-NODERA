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
session_start();

error_reporting(0);

ob_start("ob_gzhandler");

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  
  date_default_timezone_set($_SESSION['timezone']);
  
// load session MikroTik
  $session = $_GET['session'];

// load config
  include_once('../include/config.php');
  include_once('../include/readcfg.php');

  include_once('../lib/formatbytesbites.php');

  $id = $_GET['id'];
  $qr = $_GET['qr'];
  $small = $_GET['small'];
  $userp = $_GET['user'];

  require_once('../lib/routeros_api.class.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  

  if ($userp != "") {
    $usermode = explode('-', $userp)[0];
    $pulluser = explode('-', $userp);
    $iuser = count($pulluser);
    $prefix = explode('-', $userp)[$iuser - 2];
    $user = explode('-', $userp)[$iuser - 1];
    if ($iuser == 3) {
      $user = $prefix . "-" . $user;
    } else {
      $user = $user;
    }
    $voucher_props = ".id,name,password,profile,limit-uptime,limit-bytes-total,comment";
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?name" => "$user",
      ".proplist" => "$voucher_props"
    ));
    $getuser = is_array($getuser) ? $getuser : array();
    $TotalReg = count($getuser);
  } elseif ($id != "") {
    $usermode = explode('-', $id)[0];
    $voucher_props = ".id,name,password,profile,limit-uptime,limit-bytes-total,comment";
    $getuser = $API->comm('/ip/hotspot/user/print', array(
      "?comment" => "$id",
      ".proplist" => "$voucher_props"
    ));
    $getuser = is_array($getuser) ? $getuser : array();
    $TotalReg = count($getuser);
  }
  $getuprofile = isset($getuser[0]['profile']) ? $getuser[0]['profile'] : '';


  $getprofile = $API->comm("/ip/hotspot/user/profile/print", array(
    "?name" => "$getuprofile",
    ".proplist" => "shared-users,on-login"
  ));
  $getsharedu = $getprofile[0]['shared-users'];
  $ponlogin = $getprofile[0]['on-login'];
  $validity = explode(",", $ponlogin)[3];
  $getprice = explode(",", $ponlogin)[2];
  $getsprice = explode(",", $ponlogin)[4];

 
  
    if($getsprice == "0" && $getprice != "0"){
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float)$getprice, 2);
      }
    }else if($getsprice != "0"){
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float)$getsprice, 2);
      }
    }else if ($getsprice == "0") {
      $price = "";
    }

    
  

  $imgDir = "../img/";
  $cleanSession = trim((string)$session);
  $candidates = [
    $imgDir . "logo-{$cleanSession}.png",
    $imgDir . "logo-{$cleanSession}.jpg",
    $imgDir . "logo-{$cleanSession}.jpeg",
    $imgDir . "logo-{$cleanSession}.webp",
    $imgDir . "logo-" . strtolower($cleanSession) . ".png",
    $imgDir . "logo-" . strtoupper($cleanSession) . ".png",
  ];

  $foundLogo = "";
  foreach ($candidates as $cand) {
    if (file_exists($cand)) {
      $foundLogo = $cand;
      break;
    }
  }

  if (empty($foundLogo) && is_dir($imgDir)) {
    $targetLower = strtolower("logo-{$cleanSession}");
    if ($dh = @opendir($imgDir)) {
      while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..') continue;
        $info = pathinfo($f);
        if (strtolower($info['filename'] ?? '') === $targetLower && in_array(strtolower($info['extension'] ?? ''), ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
          $foundLogo = $imgDir . $f;
          break;
        }
      }
      closedir($dh);
    }
  }

  // Check logo configuration preference
  $logoCfgFile = '../include/logo_config.php';
  $logoCfgData = [];
  if (file_exists($logoCfgFile)) {
    @include($logoCfgFile);
  }
  $useLogoInVoucher = !isset($logoCfgData[$cleanSession]['use_in_voucher']) || $logoCfgData[$cleanSession]['use_in_voucher'] !== 'no';

  if ($useLogoInVoucher) {
    if (!empty($foundLogo)) {
      $logo = $foundLogo . "?t=" . filemtime($foundLogo);
    } elseif (file_exists($imgDir . "logo.png")) {
      $logo = $imgDir . "logo.png?t=" . filemtime($imgDir . "logo.png");
    } else {
      $logo = $imgDir . "logo.png";
    }
  } else {
    $logo = "";
  }


}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Voucher-<?= $hotspotname . "-" . $getuprofile . "-" . $id; ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta http-equiv="pragma" content="no-cache" />
		<link rel="icon" href="../img/favicon.png" />
		<script src="../js/qrious.min.js"></script>
		<style>
body {
  color: #000000;
  background-color: #FFFFFF;
  font-size: 13px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  margin: 0px;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.voucher, table.voucher {
  display: inline-block;
  vertical-align: top;
  margin: 3px;
  page-break-inside: avoid;
}
@page {
  size: auto;
  margin-left: 5mm;
  margin-right: 5mm;
  margin-top: 5mm;
  margin-bottom: 5mm;
}
@media print {
  body { background: #fff !important; }
  table, tr, td, .voucher { page-break-inside: avoid !important; }
  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }
}
#num {
  float: right;
  display: inline-block;
}
		</style>
	</head>
	<body onload="window.print()">

<?php for ($i = 0; $i < $TotalReg; $i++) {;
  $regtable = $getuser[$i];
  $uid = str_replace("=","",base64_encode($regtable['.id']));
  $idqr = str_replace("=","",base64_encode(($regtable['.id']."qr")));
  $username = $regtable['name'];
  $password = $regtable['password'];
  $profile = $regtable['profile'];
  $timelimit = $regtable['limit-uptime'];
  $getdatalimit = $regtable['limit-bytes-total'];
  $comment = $regtable['comment'];
  if ($getdatalimit == 0) {
    $datalimit = "";
  } else {
    $datalimit = formatBytes($getdatalimit, 2);
  }
  
  $urilogin = "http://$dnsname/login?username=$username&password=$password";
  $qrcode = "
	<canvas class='qrcode' id='".$uid."'></canvas>
    <script>
      (function() {
        var ".$uid." = new QRious({
          element: document.getElementById('".$uid."'),
          value: '".$urilogin."',
          size:'256'
        });

      })();
    </script>
	";
 
  $num = $i + 1;
  ?>
<?php
$thermal = $_GET['thermal'] ?? ($_GET['t'] ?? ($_GET['template'] ?? ''));
if ($userp != "" || $thermal === "yes" || $thermal === "thermal") {
  include('./template-thermal.php');
} else {
  if ($small == "yes") {
    include('./template-small.php');
  } else {
    include('./template.php');
  }
}
?>
<?php 
} ?>

	
</body>
</html>
