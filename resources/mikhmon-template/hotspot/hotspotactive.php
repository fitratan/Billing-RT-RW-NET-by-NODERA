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

// load session MikroTik
	$session = $_GET['session'];
	$serveractive = $_GET['server'];

// load config
	include('../include/config.php');
	include('../include/readcfg.php');
	
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

// routeros api
	include_once('../lib/routeros_api.class.php');
	include_once('../lib/formatbytesbites.php');
	$API = new RouterosAPI();
	$API->debug = false;
	$API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

	$active_props = ".id,server,user,address,mac-address,uptime,bytes-in,bytes-out,time-left,login-by,comment";
	if ($serveractive != "") {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"?server" => "" . $serveractive . "",
			".proplist" => "$active_props"
		));
	} else {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print", array(
			".proplist" => "$active_props"
		));
	}
	$gethotspotactive = is_array($gethotspotactive) ? $gethotspotactive : array();
	$TotalReg = count($gethotspotactive);
	$counthotspotactive = $TotalReg;
}
?>
<div class="row">
<div id="reloadHotspotActive">
<div class="col-12">
	<div class="card">
		<div class="card-header">
			<div class="card-header-flex">
				<h3 class="card-header-title">
					<i class="fa fa-wifi"></i> <?= $_hotspot_active ?>
					<?php if ($serveractive != ""): ?><span style="font-size: 13px; font-weight: normal;">[<?= htmlspecialchars($serveractive); ?>]</span><?php endif; ?>
					<small class="text-muted" style="font-size: 13px; font-weight: normal; opacity: 0.85;">
						(<?= ($counthotspotactive < 2) ? "$counthotspotactive item" : "$counthotspotactive items"; ?>)
					</small>
				</h3>
				<div class="card-header-actions">
					<?php if ($serveractive != ""): ?>
						<a href="./?hotspot=active&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-search"></i> <?= $_show_all ?? "Show all"; ?></a>
					<?php endif; ?>
					<span onclick="location.reload();" class="header-action-btn pointer" title="<?= $_reload_data ?? "Reload data"; ?>"><i class="fa fa-refresh"></i></span>
				</div>
			</div>
		</div>
         <div class="card-body overflow">
<div style="padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
  <input id="filterTable" type="text" class="form-control" style="max-width: 220px; padding: 5px 10px; margin: 0;" placeholder="<?= $_search ?>">
</div>
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th></th>
    <th><?= $_server ?? "Server"; ?></th>
    <th><?= $_user_name ?? "User"; ?></th>
    <th><?= $_address ?? "Address"; ?></th>
    <th><?= $_mac_address ?? "Mac Address"; ?></th>
    <th class="text-right"><?= $_uptime_user ?? "Uptime"; ?></th>
    <th class="text-right"><?= $_bytes_in ?? "Bytes In"; ?></th>
    <th class="text-right"><?= $_bytes_out ?? "Bytes Out"; ?></th>
    <th class="text-right"><?= $_time_left ?? "Time Left"; ?></th>
    <th><?= $_login_by ?? "Login By"; ?></th>
    <th><?= $_comment; ?></th>
  </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$hotspotactive = $gethotspotactive[$i];
	$id = $hotspotactive['.id'];
	$server = $hotspotactive['server'];
	$user = $hotspotactive['user'];
	$address = $hotspotactive['address'];
	$mac = $hotspotactive['mac-address'];
	$uptime = formatDTM($hotspotactive['uptime']);
	$usesstime = formatDTM($hotspotactive['session-time-left']);
	$bytesi = formatBytes($hotspotactive['bytes-in'], 2);
	$byteso = formatBytes($hotspotactive['bytes-out'], 2);
	$loginby = $hotspotactive['login-by'];
	$comment = $hotspotactive['comment'];
	$uriprocess = "'./?remove-user-active=" . $id . "&session=" . $session . "'";
	echo "<tr>";
	echo "<td style='text-align:center;'><span class='pointer'  title='Remove " . $user . "' onclick=loadpage(".$uriprocess.")><i class='fa fa-minus-square text-danger'></i></span></td>";
	echo "<td><a  title='filter " . $server . "' href='./?hotspot=active&server=" . $server . "&session=" . $session . "'><i class='fa fa-server'></i> " . $server . "</a></td>";
	echo "<td><a title='Open User " . $user . "' href=./?hotspot-user=" . $user . "&session=" . $session . "><i class='fa fa-edit'></i> " . $user . "</a></td>";
	echo "<td>" . $address . "</td>";
	echo "<td>" . $mac . "</td>";
	echo "<td style='text-align:right;'>" . $uptime . "</td>";
	echo "<td style='text-align:right;'>" . $bytesi . "</td>";
	echo "<td style='text-align:right;'>" . $byteso . "</td>";
	echo "<td style='text-align:right;'>" . $usesstime . "</td>";
	echo "<td>" . $loginby . "</td>";
	echo "<td>" . $comment . "</td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>