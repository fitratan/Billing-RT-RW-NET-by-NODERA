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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
	echo '
<html>
<head><title>403 Forbidden</title></head>
<body bgcolor="white">
<center><h1>403 Forbidden</h1></center>
<hr><center>nginx/1.14.0</center>
</body>
</html>
';
} else {

	// get user profile reliably without .proplist
	$getprofile = $API->comm("/ip/hotspot/user/profile/print");
	$getprofile = is_array($getprofile) ? $getprofile : array();
	$TotalReg = count($getprofile);
	$countprofile = $TotalReg;

	// pre-fetch schedulers to avoid N queries inside loop
	$allschedulers = $API->comm("/system/scheduler/print");
	$sched_by_name = array();
	if (is_array($allschedulers)) {
		foreach ($allschedulers as $s) {
			if (isset($s['name'])) {
				$sched_by_name[$s['name']] = $s;
			}
		}
	}

	// Load NODERA Pay / buy.php config to show online status
	$npConfigFile = dirname(__DIR__) . '/include/noderapay_config.php';
	$noderapay_data = [];
	if (file_exists($npConfigFile)) {
		include $npConfigFile;
	}
	$currNp = $noderapay_data[$session] ?? $noderapay_data['default'] ?? [];
	$npProfileMode = $currNp['profile_mode'] ?? 'all';
	$npAllowed = is_array($currNp['allowed_profiles'] ?? null) ? array_map('strtolower', array_map('trim', $currNp['allowed_profiles'])) : [];
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class=" fa fa-pie-chart"></i> <?= $_user_profile ?> 
    <?php
	if ($countprofile < 2) {
		echo "$countprofile item";
	} elseif ($countprofile > 1) {
		echo "$countprofile items";
	}
	?>
    &nbsp; | &nbsp; <a href="./?user-profile=add&session=<?= $session; ?>" title="<?= $_add_user_profile ?? "Add Profile"; ?>"><i class="fa fa-user-plus"></i> <?= $_add ?></a>
    &nbsp; | &nbsp; <a href="./?hotspot=noderapay&session=<?= $session; ?>" title="<?= $_online_store_settings ?? "Online Store Settings"; ?>"><i class="fa fa-shopping-cart"></i> <?= $_shop ?? "Online Store"; ?></a>
    &nbsp; | &nbsp; <a href="./?hotspot=sync-schedulers&session=<?= $session; ?>" onclick="return confirm('<?= $_confirm_sync_schedulers ?? "Update all monitor schedulers and clean expired vouchers in MikroTik automatically?"; ?>');" title="<?= $_sync_schedulers_title ?? "Update All Schedulers & Clean Expired Automatically"; ?>"><i class="fa fa-magic"></i> <?= $_sync_and_fix ?? "Fix & Sync"; ?></a>
    &nbsp; | &nbsp; <i onclick="location.reload();" class="fa fa-refresh pointer" title="<?= $_reload_data ?? "Reload data"; ?>"></i>
	</h3>
</div>
<!-- /.card-header -->
<div class="card-body">
<div class="row">
  <div class="col-6">
    <input id="filterTable" type="text" class="form-control" placeholder="<?= $_search ?>...">
  </div>
</div>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh"> 			   
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr> 
		<th style="min-width:50px;" class="text-center">#</th>
		<th class="align-middle"><?= $_name ?></th>
		<th class="align-middle"><?= $_shared_users ?? "Shared Users"; ?></th>
		<th class="align-middle"><?= $_rate_limit ?? "Rate Limit"; ?></th>
		<th class="align-middle"><?= $_expired_mode ?></th>
		<th class="align-middle"><?= $_validity ?></th>
		<th class="text-right align-middle"><?= $_price." ".$currency; ?></th>
		<th class="text-right align-middle"><?= $_selling_price." ".$currency; ?></th>
		<th class="align-middle"><?= $_lock_user ?></th>
		<th class="align-middle"><?= $_single_session ?? 'Single Session' ?></th>
		<th class="text-center align-middle"><?= $_online_store ?? "Online Store"; ?></th>
    </tr>
  </thead>
  <tbody>
<?php

for ($i = 0; $i < $TotalReg; $i++) {

	$profiledetalis = $getprofile[$i];
	$pid = isset($profiledetalis['.id']) ? $profiledetalis['.id'] : '';
	$pname = isset($profiledetalis['name']) ? $profiledetalis['name'] : '';
	$psharedu = isset($profiledetalis['shared-users']) ? $profiledetalis['shared-users'] : '1';
	$pratelimit = isset($profiledetalis['rate-limit']) && $profiledetalis['rate-limit'] !== '' ? $profiledetalis['rate-limit'] : '-';
	$ponlogin = isset($profiledetalis['on-login']) ? $profiledetalis['on-login'] : '';
	$monexpired = isset($sched_by_name[$pname]) ? $sched_by_name[$pname] : array();
	$monid = isset($monexpired['.id']) ? $monexpired['.id'] : '';
	$pmon = isset($monexpired['name']) ? $monexpired['name'] : '';
	$chkpmon = isset($monexpired['disabled']) ? $monexpired['disabled'] : '';
	if(empty($pmon) || $chkpmon == "true"){$moncolor = "text-orange";}else{$moncolor = "text-green";}
	echo "<tr>";
	?>
  <td style='text-align:center;'><i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('<?= $_confirm_delete ?? "Are you sure you want to delete?"; ?> (<?= htmlspecialchars($pname, ENT_QUOTES); ?>)')){loadpage('./?remove-user-profile=<?= $pid; ?>&pname=<?= urlencode($pname); ?>&session=<?= $session; ?>')}else{}" title='<?= $_delete ?? "Remove"; ?> <?= htmlspecialchars($pname, ENT_QUOTES); ?>'></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
  <?php
	echo "<a title='" . ($_user_list ?? "User List") . " (" . htmlspecialchars($pname, ENT_QUOTES) . ")' href='./?hotspot=users&profile=" . urlencode($pname) . "&session=" . $session . "'><i class='fa fa-users'></i></a></td>";
	echo "<td><a title='" . ($_user_profile ?? "User Profile") . " " . htmlspecialchars($pname, ENT_QUOTES) . "' href='./?user-profile=" . $pid . "&session=" . $session . "'><i class='fa fa-edit'></i> <i class='fa fa-ci fa-circle ".$moncolor."'></i> " . htmlspecialchars($pname, ENT_QUOTES) . "</a></td>";
	echo "<td>" . htmlspecialchars($psharedu, ENT_QUOTES) . "</td>";
	echo "<td>" . htmlspecialchars($pratelimit, ENT_QUOTES) . "</td>";

	echo "<td>";
	$getexpmode = explode(",", (string)$ponlogin);
	$expmode = isset($getexpmode[1]) ? $getexpmode[1] : '';
	if ($expmode == "rem") {
		echo "Remove";
	} elseif ($expmode == "ntf") {
		echo "Notice";
	} elseif ($expmode == "remc") {
		echo "Remove & Record";
	} elseif ($expmode == "ntfc") {
		echo "Notice & Record";
	} else {
		echo "-";
	}
	echo "</td>";
	echo "<td>";
	$getvalid = explode(",", (string)$ponlogin);
	echo isset($getvalid[3]) && $getvalid[3] !== '' ? htmlspecialchars($getvalid[3], ENT_QUOTES) : '-';
	echo "</td>";

	echo "<td style='text-align:right;'>";
	$getprice = explode(",", (string)$ponlogin);
	$price = isset($getprice[2]) ? trim($getprice[2]) : '';
	if ($price == "" || $price == "0") {
		echo "-";
	} else {
		if ($currency == in_array($currency, $cekindo['indo'])) {
			echo number_format((float)$price, 0, ",", ".");
		} else {
			echo number_format((float)$price, 2);
		}
	}
	echo "</td>";
	echo "<td style='text-align:right;'>";
	$getsprice = explode(",", (string)$ponlogin);
	$sprice = isset($getsprice[4]) ? trim($getsprice[4]) : '';
	if ($sprice == "" || $sprice == "0") {
		echo "-";
	} else {
		if ($currency == in_array($currency, $cekindo['indo'])) {
			echo number_format((float)$sprice, 0, ",", ".");
		} else {
			echo number_format((float)$sprice, 2);
		}
	}
	echo "</td>";
	$getlocku = explode(",", (string)$ponlogin)[6] ?? '';
	echo "<td>";
	if ($getlocku === "Enable") {
		echo "<span class='text-green' title='Lock User: Enable'><i class='fa fa-lock'></i> Enable</span>";
	} else {
		echo "<span class='text-muted' title='Lock User: Disable'>Disable</span>";
	}
	echo "</td>";

	$getsinglesession = explode(",", (string)$ponlogin)[7] ?? '';
	if (empty($getsinglesession) || ($getsinglesession !== 'Enable' && $getsinglesession !== 'Disable' && $getsinglesession !== 'Username' && $getsinglesession !== 'MAC')) {
		if (strpos((string)$ponlogin, '/ip hotspot active remove') !== false) {
			if (strpos((string)$ponlogin, 'mac-address') !== false) {
				$getsinglesession = 'MAC';
			} else {
				$getsinglesession = 'Username';
			}
		} else {
			$getsinglesession = 'Disable';
		}
	}
	echo "<td>";
	if ($getsinglesession === "Username" || $getsinglesession === "Enable") {
		echo "<span class='text-green' title='" . ($_auto_kick_by_username ?? "Auto-Kick Old Session by Username: Enabled") . "'><i class='fa fa-user-times'></i> Username</span>";
	} elseif ($getsinglesession === "MAC") {
		echo "<span class='text-blue' title='" . ($_auto_kick_by_mac ?? "Auto-Kick Old Session by MAC: Enabled") . "'><i class='fa fa-laptop'></i> MAC</span>";
	} else {
		echo "<span class='text-muted' title='" . ($_single_session ?? "Single Session") . ": " . ($_disable ?? "Disable") . "'>Disable</span>";
	}
	echo "</td>";

	// Online status in Toko Online
	$hasPrice = (!empty($price) && $price !== "0") || (!empty($sprice) && $sprice !== "0");
	$isProfileOnline = false;
	if ($hasPrice) {
		if ($npProfileMode === 'all') {
			$isProfileOnline = true;
		} else {
			$isProfileOnline = in_array(strtolower($pname), $npAllowed, true);
		}
	}
	echo "<td style='text-align:center; vertical-align:middle;'>";
	if (!$hasPrice) {
		echo "<span class='text-secondary' title='" . ($_no_price_in_onlogin_desc ?? "No price in on-login script (Hidden from Online Store)") . "' style='font-size:11px;'><i class='fa fa-ban'></i> No Price</span>";
	} elseif ($isProfileOnline) {
		echo "<a href='./?hotspot=noderapay&session=" . urlencode($session) . "' class='btn bg-green' title='" . ($_active_in_online_store_click ?? "Active in Online Store (Click to manage)") . "' style='font-size:11px; padding:2px 8px; margin:0;'><i class='fa fa-globe'></i> " . ($_active_status ?? "Active") . "</a>";
	} else {
		echo "<a href='./?hotspot=noderapay&session=" . urlencode($session) . "' class='btn bg-secondary' title='" . ($_hidden_from_online_store_click ?? "Hidden from Online Store (Click to manage)") . "' style='font-size:11px; padding:2px 8px; margin:0;'><i class='fa fa-eye-slash'></i> " . ($_hidden ?? "Hidden") . "</a>";
	}
	echo "</td>";

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
