<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified by NODERA (nodera.id)
 */
session_start();
error_reporting(0);

include_once __DIR__ . '/../include/telegram_helper.php';

$getuser = $API->comm("/ip/hotspot/user/print", array(
  "?comment" => "$removehotspotuserbycomment",
  ".proplist" => ".id,name,profile,comment,uptime,mac-address,caller-id"
));
$getuser = is_array($getuser) ? $getuser : array();
$TotalReg = count($getuser);

$_SESSION['ubp'] = isset($getuser[0]['profile']) ? $getuser[0]['profile'] : '';
$_SESSION['ubc'] = "";

if ($TotalReg > 0) {
  $ids = array();
  for ($i = 0; $i < $TotalReg; $i++) {
    $u = $getuser[$i];
    $uname = $u['name'] ?? '';

    if (!empty($uname) && function_exists('mikhmon_send_user_voucher_expired_telegram')) {
      mikhmon_send_user_voucher_expired_telegram($session, [
        'username'      => $uname,
        'profile'       => $u['profile'] ?? '',
        'uptime'        => $u['uptime'] ?? '',
        'mac'           => $u['mac-address'] ?? ($u['caller-id'] ?? ''),
        'location_name' => $identity ?: ($session ?: 'Hotspot'),
        'expired_at'    => date('Y-m-d H:i:s'),
      ], 'Rp', null, true);
    }

    if (!empty($u['.id'])) {
      $ids[] = $u['.id'];
    }

    if (!empty($uname)) {
      $getscr = $API->comm("/system/script/print", array("?name" => "$uname"));
      if (!empty($getscr[0]['.id'])) {
        $API->comm("/system/script/remove", array(".id" => $getscr[0]['.id']));
      }
      $getsch = $API->comm("/system/scheduler/print", array("?name" => "$uname"));
      if (!empty($getsch[0]['.id'])) {
        $API->comm("/system/scheduler/remove", array(".id" => $getsch[0]['.id']));
      }
    }
  }

  $chunks = array_chunk($ids, 50);
  foreach ($chunks as $chunk) {
    $API->comm("/ip/hotspot/user/remove", array(
      ".id" => implode(",", $chunk),
    ));
  }
}

if ($_SESSION['ubp'] != "") {
  echo "<script>window.location='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'</script>";
} else {
  echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
}
?>
