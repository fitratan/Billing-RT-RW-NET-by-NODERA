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
// hide all error
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

// time zone
date_default_timezone_set($_SESSION['timezone']);
	
// load session MikroTik
$session = $_GET['session'];

$quickprint = $_GET['quickprint'];
$qty = 1;
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
// quick bt
include('../include/quickbt.php');
// load config
include('../include/config.php');
include('../include/readcfg.php');

// routeros api
include_once('../lib/routeros_api.class.php');
include_once('../lib/formatbytesbites.php');
$API = new RouterosAPI();
$API->debug = false;
$API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));
	// get quick print
$getquickprint = $API->comm("/system/script/print", array("?name" => "$quickprint"));

  $quickprintdetails = $getquickprint[0] ?? array();
  $qpid = $quickprintdetails['.id'] ?? '';
  $quickprintsource = explode("#", $quickprintdetails['source'] ?? '');
  $package = $quickprintsource[1] ?? '';
  $server = $quickprintsource[2] ?? '';
  $usermode = ($quickprintsource[3] ?? 'vc') === 'up' ? 'up' : 'vc';
  $userl = !empty($quickprintsource[4]) ? intval($quickprintsource[4]) : 6;
  if ($userl < 3 || $userl > 8) {
	  $userl = 6;
  }
  $qty = !empty($qty) ? min(500, max(1, intval($qty))) : 1;
  $prefix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $quickprintsource[5] ?? '');
  $char = $quickprintsource[6] ?? 'mix1';
  $profile = $quickprintsource[7] ?? '';
  $timelimit = $quickprintsource[8] ?? '0';
  $datalimit = $quickprintsource[9] ?? '0';
  $comment = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $quickprintsource[10] ?? '');
  $getvalid = $quickprintsource[11] ?? '';
  $priceParts = explode("_", $quickprintsource[12] ?? '');
  $getprice = $priceParts[0] ?? '';
  $getsprice = $priceParts[1] ?? '';
  $userlock = $quickprintsource[13] ?? 'Disable';

  if($getsprice == "" && $getprice != ""){
	  $price = $getprice;
  }else if($getsprice != ""){
	  $price = $getsprice;
  }else if ($getsprice == "") {
	$price = "";
  }

		$commt = $usermode . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $comment;

		$a = array("1" => "", "", 1, 2, 2, 3, 3, 4);

		$u = [];
		$p = [];
		$used = [];
		$shuf = max(1, $userl - ($a[$userl] ?? 2));
		$attempts = 0;
		$maxAttempts = $qty * 50;
		$i = 1;

		while ($i <= $qty && $attempts < $maxAttempts) {
			$attempts++;
			if ($usermode == "up") {
				if ($char == "lower") {
					$candU = randLC($userl);
				} elseif ($char == "upper") {
					$candU = randUC($userl);
				} elseif ($char == "upplow") {
					$candU = randULC($userl);
				} elseif ($char == "mix") {
					$candU = randNLC($userl);
				} elseif ($char == "mix1") {
					$candU = randNUC($userl);
				} elseif ($char == "mix2") {
					$candU = randNULC($userl);
				} elseif ($char == "num") {
					$candU = randN($userl);
				} else {
					$candU = randNUC($userl);
				}
				$candP = randN($userl);
				$candName = $prefix . $candU;
				$candPass = $candP;
			} else { // $usermode == "vc"
				if ($char == "num") {
					$candU = randN($userl);
				} elseif ($char == "mix") {
					$candU = randNLC($userl);
				} elseif ($char == "mix1") {
					$candU = randNUC($userl);
				} elseif ($char == "mix2") {
					$candU = randNULC($userl);
				} elseif ($char == "lower") {
					$candU = randLC($shuf) . randN($userl - $shuf);
				} elseif ($char == "upper") {
					$candU = randUC($shuf) . randN($userl - $shuf);
				} elseif ($char == "upplow") {
					$candU = randULC($shuf) . randN($userl - $shuf);
				} else {
					$candU = randNUC($userl);
				}
				$candName = $prefix . $candU;
				$candPass = $candName;
			}

			if (!isset($used[$candName])) {
				$used[$candName] = true;
				$u[$i] = $candName;
				$p[$i] = $candPass;
				$i++;
			}
		}

		$actualQty = count($u);

		session_write_close();
		@set_time_limit(300);
		if (is_object($API)) {
			$API->timeout = 15;
			if (is_resource($API->socket)) {
				@socket_set_timeout($API->socket, 15);
			}
		}

		for ($i = 1; $i <= $actualQty; $i++) {
			$usrPass = ($usermode == "up") ? $p[$i] : $u[$i];
			$API->comm("/ip/hotspot/user/add", array(
				"server" => "$server",
				"name" => "$u[$i]",
				"password" => "$usrPass",
				"profile" => "$profile",
				"limit-uptime" => "$timelimit",
				"limit-bytes-total" => "$datalimit",
				"comment" => "$commt",
			));
		}

        $getuser = $API->comm("/ip/hotspot/user/print", array(
            "?name" => "$u[1]",
          ));
          $userdetails = $getuser[0];
          $uid = $userdetails['.id'];
          $uname = $userdetails['name'];
          $upass = $userdetails['password'];
          $uprofile = $userdetails['profile'];
					$uuptime = formatDTM($userdetails['uptime']);
					$utimelimit = $userdetails['limit-uptime'];
          $udatalimit = $userdetails['limit-bytes-total'];
          $ucomment = $userdetails['comment'];
        
          if (substr(formatBytes2($udatalimit, 2), -2) == "MB") {
            $udatalimit = $udatalimit / 1048576;
            $MG = "MB";
          } elseif (substr(formatBytes2($udatalimit, 2), -2) == "GB") {
            $udatalimit = $udatalimit / 1073741824;
            $MG = "GB";
          } elseif ($udatalimit == "") {
            $udatalimit = "";
            $MG = "MB";
          }
          $_SESSION['sss'] = $uname;
         
// Print BT
  $chl = urlencode("http://$dnsname/login?username=$uname&password=$upass");
	$qrcode = 'https://chart.googleapis.com/chart?cht=qr&chs=100x100&chld=L|0&chl=' . $chl . '&choe=utf-8';
	//$qrcode = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.$chl;

if (isset($cekindo['indo']) && in_array($currency, $cekindo['indo'])) {
  $pricebt = $currency . " " . number_format((float)$price, 0, ",", ".");
  if (substr($getvalid, -1) == "d") {
    $validity = substr($getvalid, 0, -1) . ($_days_short ?? "Hari");
  } else if (substr($getvalid, -1) == "h") {
    $validity = substr($getvalid, 0, -1) . ($_hours_short ?? "Jam");
  }
  if (substr($utimelimit, -1) == "d" & strlen($utimelimit) > 3) {
    $timelimit = ((substr($utimelimit, 0, -1) * 7) + substr($utimelimit, 2, 1)) . ($_days_short ?? "Hari");
  } else if (substr($utimelimit, -1) == "d") {
    $timelimit = substr($utimelimit, 0, -1) . ($_days_short ?? "Hari");
  } else if (substr($utimelimit, -1) == "h") {
    $timelimit = substr($utimelimit, 0, -1) . ($_hours_short ?? "Jam");
  } else if (substr($utimelimit, -1) == "w") {
    $timelimit = (substr($utimelimit, 0, -1) * 7) . ($_days_short ?? "Hari");
  }

  } else {
    $pricebt = $currency . " " . number_format((float)$price);
    $timelimit = $utimelimit;
    $validity = $getvalid;
  }
	if($qrbt == "enable"){$qr = "yes";}	
	include('../voucher/printbt.php');
?>

<script>
    $(document).ready(function(){
			var w = window.innerWidth;
  			if (w < 800) {
					sendToQuickPrinterChrome();
  			} else if (w > 800) {

					window.open('./voucher/print.php?user=<?= $usermode ?>-<?= $uname ?>&qr=<?= $qr ?>&session=<?= $session ?>','_blank','width=310,height=450').print();
					//window.location.href="./?hotspot-user=<?= $u[1] ?>&session=<?= $session ?>";
  			}
    //sendToQuickPrinterChrome();
});
</script>
<?php } ?>