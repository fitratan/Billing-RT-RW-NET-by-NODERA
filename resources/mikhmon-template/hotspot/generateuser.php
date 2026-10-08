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

ini_set('max_execution_time', 300);
@set_time_limit(300);

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {
// time zone
date_default_timezone_set($_SESSION['timezone']);

	$genprof = $_GET['genprof'];
	if ($genprof != "") {
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array(
			"?name" => "$genprof",
		));
		$ponlogin = $getprofile[0]['on-login'];
		$getprice = explode(",", $ponlogin)[2];
		if ($getprice == "0") {
			$getprice = "";
		} else {
			$getprice = $getprice;
		}

		$getvalid = explode(",", $ponlogin)[3];

		$getlocku = explode(",", $ponlogin)[6];
		if ($getlocku == "") {
			$getprice = "Disable";
		} else {
			$getlocku = $getlocku;
		}

		if ($currency == in_array($currency, $cekindo['indo'])) {
			$getprice = $currency . " " . number_format((float)$getprice, 0, ",", ".");
		} else {
			$getprice = $currency . " " . number_format((float)$getprice);
		}
		$ValidPrice = "<b>Validity : " . $getvalid . " | Price : " . $getprice . " | Lock User : " . $getlocku . "</b>";
	} else {
	}

	$srvlist = $API->comm("/ip/hotspot/print");

	if (isset($_POST['qty'])) {
		
		$qty = !empty($_POST['qty']) ? min(500, max(1, intval($_POST['qty']))) : 1;
		$server = $_POST['server'] ?? 'all';
		$user = ($_POST['user'] ?? 'vc') === 'up' ? 'up' : 'vc';
		$userl = !empty($_POST['userl']) ? intval($_POST['userl']) : 6;
		if ($userl < 3 || $userl > 8) {
			$userl = 6;
		}
		$prefix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['prefix'] ?? '');
		$char = $_POST['char'] ?? 'mix1';
		$profile = $_POST['profile'] ?? '';
		$timelimit = $_POST['timelimit'] ?? '';
		$datalimit = $_POST['datalimit'] ?? '';
		$adcomment = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['adcomment'] ?? '');
		$mbgb = intval($_POST['mbgb'] ?? 1048576);
		if ($timelimit == "") {
			$timelimit = "0";
		} else {
			$timelimit = $timelimit;
		}
		if ($datalimit == "") {
			$datalimit = "0";
		} else {
			$datalimit = $datalimit * $mbgb;
		}
		if ($adcomment == "") {
			$adcomment = "";
		} else {
			$adcomment = $adcomment;
		}
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile"));
		$ponlogin = $getprofile[0]['on-login'];
		$getvalid = explode(",", $ponlogin)[3];
		$getprice = explode(",", $ponlogin)[2];
		$getsprice = explode(",", $ponlogin)[4];
		$getlock = explode(",", $ponlogin)[6];
		$_SESSION['ubp'] = $profile;
		$commt = $user . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $adcomment;
		$gentemp = $commt . "|~" . $profile . "~" . $getvalid . "~" . $getprice . "!".$getsprice."~" . $timelimit . "~" . $datalimit . "~" . $getlock;
		$gen = '<?php $genu="'.mikhmon_encrypt($gentemp).'";?>';
		$temp = './voucher/temp.php';
		@file_put_contents($temp, $gen);

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
			if ($user == "up") {
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
			} else { // $user == "vc" (Username = Password)
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

		// Add users directly to MikroTik Hotspot
		session_write_close();
		@set_time_limit(300);
		if (is_object($API)) {
			$API->timeout = 15;
			if (is_resource($API->socket)) {
				@socket_set_timeout($API->socket, 15);
			}
		}

		for ($i = 1; $i <= $actualQty; $i++) {
			$usrPass = ($user == "up") ? $p[$i] : $u[$i];
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

		if ($actualQty < 2) {
			echo "<script>window.location='./?hotspot-user=" . ($u[1] ?? '') . "&session=" . $session . "'</script>";
		} else {
			echo "<script>window.location='./?hotspot-user=generate&session=" . $session . "'</script>";
		}
	}

	$getprofile = $API->comm("/ip/hotspot/user/profile/print");
	$getprofile = is_array($getprofile) ? $getprofile : array();
	include_once('./voucher/temp.php');
	$genuser = explode("-", mikhmon_decrypt($genu));
	$genuser1 = explode("~", mikhmon_decrypt($genu));
	$umode = $genuser[0];
	$ucode = $genuser[1];
	$udate = $genuser[2];
	$uprofile = $genuser1[1];
	$uvalid = $genuser1[2];
	$ucommt = $genuser[3];
	if ($uvalid == "") {
		$uvalid = "-";
	} else {
		$uvalid = $uvalid;
	}
	$uprice = explode("!",$genuser1[3])[0];
	if ($uprice == "0") {
		$uprice = "-";
	} else {
		$uprice = $uprice;
	}
	$suprice = explode("!",$genuser1[3])[1];
	if ($suprice == "0") {
		$suprice = "-";
	} else {
		$suprice = $suprice;
	}
	$utlimit = $genuser1[4];
	if ($utlimit == "0") {
		$utlimit = "-";
	} else {
		$utlimit = $utlimit;
	}
	$udlimit = $genuser1[5];
	if ($udlimit == "0") {
		$udlimit = "-";
	} else {
		$udlimit = formatBytes($udlimit, 2);
	}
	$ulock = $genuser1[6];
	//$urlprint = "$umode-$ucode-$udate-$ucommt";
	$urlprint = explode("|", mikhmon_decrypt($genu))[0];
	if ($currency == in_array($currency, $cekindo['indo'])) {
		$uprice = $currency . " " . number_format((float)$uprice, 0, ",", ".");
		$suprice = $currency . " " . number_format((float)$suprice, 0, ",", ".");
	} else {
		$uprice = $currency . " " . number_format((float)$uprice);
		$suprice = $currency . " " . number_format((float)$suprice);

	}

}
?>
<div class="row">
	
<div class="col-8">
<div class="card box-bordered">
	<div class="card-header">
	<h3><i class="fa fa-user-plus"></i> <?= $_generate_user ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
	</div>
	<div class="card-body">
<form id="genUserForm" autocomplete="off" method="post" action="" onsubmit="return submitGenForm(event);">
	<div>
		<?php if ($_SESSION['ubp'] != "") {
		echo "    <a class='btn bg-warning' href='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	} elseif ($_SESSION['vcr'] = "active") {
		echo "    <a class='btn bg-warning' href='./?hotspot=users-by-profile&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	} else {
		echo "    <a class='btn bg-warning' href='./?hotspot=users&profile=all&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	}

	?>
	<a class="btn bg-pink" title="Open User List by Profile 
<?php if ($_SESSION['ubp'] == "") {
	echo "all";
} else {
	echo $uprofile;
} ?>" href="./?hotspot=users&profile=
<?php if ($_SESSION['ubp'] == "") {
	echo "all";
} else {
	echo $uprofile;
} ?>&session=<?= $session; ?>"> <i class="fa fa-users"></i> <?= $_user_list ?></a>
    <button type="submit" id="genBtn" name="save" class="btn bg-primary" title="<?= $_generate_user ?? "Generate User"; ?>"> <i class="fa fa-save"></i> <?= $_generate ?></button>
    <a class="btn bg-secondary" title="<?= $_print_default ?? "Print Default"; ?>" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=no&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print ?></a>
    <a class="btn bg-danger" title="<?= $_print_qr ?? "Print QR"; ?>" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-qrcode"></i> <?= $_print_qr ?></a>
    <a class="btn bg-info" title="<?= $_print_small ?? "Print Small"; ?>" href="./voucher/print.php?id=<?= $urlprint; ?>&small=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print_small ?></a>
    <a class="btn bg-warning" title="<?= $_print_thermal ?? "Print Thermal"; ?>" href="./voucher/print.php?id=<?= $urlprint; ?>&thermal=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-ticket"></i> <?= $_print_thermal ?? 'Thermal' ?></a>
</div>

<div id="genProgressBar" style="display:none; margin:12px 0 10px 0; background:#f4f9fd; border:1px solid #bce2f5; border-radius:6px; padding:10px;">
    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:bold; margin-bottom:6px; color:#008CCA;">
        <span id="genProgressText"><i class="fa fa-circle-o-notch fa-spin"></i> <?= $_initializing_vouchers ?? "Initializing vouchers..."; ?></span>
        <span id="genProgressPercent">0%</span>
    </div>
    <div style="background:#e0e0e0; border-radius:4px; height:12px; overflow:hidden;">
        <div id="genProgressFill" style="background:#008CCA; width:0%; height:100%; transition:width 0.2s ease-in-out;"></div>
    </div>
</div>
<table class="table">
  <tr>
    <td class="align-middle"><?= $_qty ?></td><td><div><input class="form-control " type="number" name="qty" min="1" max="500" value="1" required="1"></div></td>
  </tr>
  <tr>
    <td class="align-middle">Server</td>
    <td>
		<select class="form-control " name="server" required="1">
			<option value="all"><?= $_all ?? "all"; ?></option>
				<?php $TotalReg = count($srvlist);
			for ($i = 0; $i < $TotalReg; $i++) {
				echo "<option>" . $srvlist[$i]['name'] . "</option>";
			}
			?>
		</select>
	</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_user_mode ?></td><td>
			<select class="form-control " onchange="defUserl();" id="user" name="user" required="1">
				<option value="up"><?= $_user_pass ?></option>
				<option value="vc"><?= $_user_user ?></option>
			</select>
		</td>
	</tr>
  <tr>
    <td class="align-middle"><?= $_user_length ?></td><td>
      <select class="form-control " id="userl" name="userl" required="1">
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
        <option value="6" selected>6</option>
        <option value="7">7</option>
        <option value="8">8</option>
      </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_prefix ?></td><td><input class="form-control " type="text" size="6" maxlength="6" autocomplete="off" name="prefix" value=""></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_character ?></td><td>
      <select class="form-control " name="char" required="1">
				<option id="lower" style="display:block;" value="lower"><?= $_random ?> abcd</option>
				<option id="upper" style="display:block;" value="upper"><?= $_random ?> ABCD</option>
				<option id="upplow" style="display:block;" value="upplow"><?= $_random ?> aBcD</option>
				<option id="lower1" style="display:none;" value="lower"><?= $_random ?> abcd2345</option>
				<option id="upper1" style="display:none;" value="upper"><?= $_random ?> ABCD2345</option>
				<option id="upplow1" style="display:none;" value="upplow"><?= $_random ?> aBcD2345</option>
				<option id="mix" style="display:block;" value="mix"><?= $_random ?> 5ab2c34d</option>
				<option id="mix1" style="display:block;" value="mix1"><?= $_random ?> 5AB2C34D</option>
				<option id="mix2" style="display:block;" value="mix2"><?= $_random ?> 5aB2c34D</option>
				<option id="num" style="display:none;" value="num"><?= $_random ?> 1234</option>
			</select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_profile ?></td><td>
			<select class="form-control " onchange="GetVP();" id="uprof" name="profile" required="1">
				<?php if ($genprof != "") {
				echo "<option>" . $genprof . "</option>";
			} else {
			}
			$TotalReg = count($getprofile);
			for ($i = 0; $i < $TotalReg; $i++) {
				echo "<option>" . $getprofile[$i]['name'] . "</option>";
			}
			?>
			</select>
		</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_time_limit ?></td><td><input class="form-control " type="text" size="4" autocomplete="off" name="timelimit" value=""></td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_data_limit ?></td><td>
      <div class="input-group">
      	<div class="input-group-10 col-box-9">
        	<input class="group-item group-item-l" type="number" min="0" max="9999" name="datalimit" value="<?= $udatalimit; ?>">
    	</div>
          <div class="input-group-2 col-box-3">
              <select style="padding:4.2px;" class="group-item group-item-r" name="mbgb" required="1">
				        <option value=1048576>MB</option>
				        <option value=1073741824>GB</option>
			        </select>
          </div>
      </div>
    </td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_comment ?></td><td><input class="form-control " type="text" title="<?= $_no_special_characters ?? "No special characters"; ?>" id="comment" autocomplete="off" name="adcomment" value=""></td>
  </tr>
   <tr >
    <td  colspan="4" class="align-middle w-12"  id="GetValidPrice">
    	<?php if ($genprof != "") {
					echo $ValidPrice;
				} ?>
    </td>
  </tr>
</table>
</form>
</div>
</div>
</div>

<div class="col-4">
	<div class="card">
		<div class="card-header">
			<h3><i class="fa fa-ticket"></i> <?= $_last_generate ?></h3>
		</div>
		<div class="card-body">
<table class="table table-bordered">
  <tr>
  	<td><?= $_generate_code ?></td><td><?= $ucode ?></td>
  </tr>
  <tr>
  	<td><?= $_date ?></td><td><?= $udate ?></td>
  </tr>
  <tr>
  	<td><?= $_profile ?></td><td><?= $uprofile ?></td>
  </tr>
  <tr>
  	<td><?= $_validity ?></td><td><?= $uvalid ?></td>
  <tr>
  	<td><?= $_time_limit ?></td><td><?= $utlimit ?></td>
  </tr>
  <tr>
  	<td><?= $_data_limit ?></td><td><?= $udlimit ?></td>
  </tr>
  <tr>
  	<td><?= $_price ?></td><td><?= $uprice ?></td>
  </tr>
  <tr>
  	<td><?= $_selling_price ?></td><td><?= $suprice ?></td>
  </tr>
  <tr>
  	<td><?= $_lock_user ?></td><td><?= $ulock ?></td>
  </tr>
  <tr>
    <td colspan="2">
		<p style="padding:0px 5px;">
      <?= $_format_time_limit ?>
    </p>
    <p style="padding:0px 5px;">
      <?= $_details_add_user ?>
    </p>
    </td>
  </tr>
</table>
</div>
</div>
</div>
<script>
// get valid $ price
function GetVP(){
  var prof = document.getElementById('uprof').value;
  $("#GetValidPrice").load("./process/getvalidprice.php?name="+prof+"&session=<?= $session; ?> #getdata");
} 

function defUserl() {
  var userEl = document.getElementById("user");
  var e = userEl ? userEl.value : "vc";
  var num = document.getElementById("num");
  var lower = document.getElementById("lower");
  var upper = document.getElementById("upper");
  var upplow = document.getElementById("upplow");
  var lower1 = document.getElementById("lower1");
  var upper1 = document.getElementById("upper1");
  var upplow1 = document.getElementById("upplow1");
  var mix = document.getElementById("mix");
  var mix1 = document.getElementById("mix1");
  var mix2 = document.getElementById("mix2");

  if ("up" === e) {
    if (lower) lower.style.display = "block";
    if (upper) upper.style.display = "block";
    if (upplow) upplow.style.display = "block";
    if (lower1) lower1.style.display = "none";
    if (upper1) upper1.style.display = "none";
    if (upplow1) upplow1.style.display = "none";
    if (num) num.style.display = "none";
  } else {
    if (lower) lower.style.display = "none";
    if (upper) upper.style.display = "none";
    if (upplow) upplow.style.display = "none";
    if (lower1) lower1.style.display = "block";
    if (upper1) upper1.style.display = "block";
    if (upplow1) upplow1.style.display = "block";
    if (num) num.style.display = "block";
  }
  if (mix) mix.style.display = "block";
  if (mix1) mix1.style.display = "block";
  if (mix2) mix2.style.display = "block";
}

function submitGenForm(e) {
  var form = document.getElementById("genUserForm") || document.querySelector("form[action='']");
  if (!form) return true;

  var qtyInput = form.querySelector("input[name='qty']");
  var qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
  var btn = document.getElementById("genBtn");
  var pBar = document.getElementById("genProgressBar");
  var pFill = document.getElementById("genProgressFill");
  var pText = document.getElementById("genProgressText");
  var pPercent = document.getElementById("genProgressPercent");

  if (btn) {
    btn.disabled = true;
    btn.style.pointerEvents = "none";
    btn.style.opacity = "0.7";
    btn.innerHTML = "<i class='fa fa-circle-o-notch fa-spin'></i> Preparing " + qty + " Vouchers...";
  }
  if (pBar) {
    pBar.style.display = "block";
    pFill.style.width = "0%";
    pPercent.innerText = "0%";
    pText.innerHTML = "<i class='fa fa-circle-o-notch fa-spin'></i> Initializing " + qty + " unique vouchers...";
  }

  if (e && e.preventDefault) {
    e.preventDefault();
  }

  var formData = new FormData(form);
  formData.append("action", "init");

  fetch("./process/generate_batch.php?session=" + encodeURIComponent("<?= $session; ?>"), {
    method: "POST",
    body: formData
  })
  .then(function(res) {
    if (!res.ok) {
      throw new Error("HTTP " + res.status + " error");
    }
    return res.json();
  })
  .then(function(data) {
    if (!data.success || !data.users || data.users.length === 0) {
      throw new Error(data.error || "Failed to initialize vouchers");
    }

    var users = data.users;
    var total = users.length;
    var batchSize = 25;
    var batches = [];
    for (var i = 0; i < total; i += batchSize) {
      batches.push(users.slice(i, i + batchSize));
    }

    var processed = 0;

    function processBatch(idx) {
      if (idx >= batches.length) {
        pFill.style.width = "100%";
        pPercent.innerText = "100%";
        pText.innerHTML = "<i class='fa fa-check-circle text-success'></i> <?= $_success_added_vouchers ?? "Successfully added"; ?> " + total + " <?= $_vouchers ?? "vouchers"; ?>! <?= $_loading ?? "Loading..."; ?>";
        setTimeout(function() {
          window.location.href = "./?hotspot-user=" + (total < 2 ? encodeURIComponent(users[0].name) : "generate") + "&session=" + encodeURIComponent("<?= $session; ?>");
        }, 500);
        return;
      }

      var curBatch = batches[idx];
      var batchData = new FormData();
      batchData.append("action", "batch");
      batchData.append("server", data.server);
      batchData.append("profile", data.profile);
      batchData.append("timelimit", data.timelimit);
      batchData.append("datalimit", data.datalimit);
      batchData.append("commt", data.commt);
      batchData.append("batch", JSON.stringify(curBatch));

      fetch("./process/generate_batch.php?session=" + encodeURIComponent("<?= $session; ?>"), {
        method: "POST",
        body: batchData
      })
      .then(function(res) {
        if (!res.ok) {
          throw new Error("HTTP " + res.status + " on batch " + (idx + 1));
        }
        return res.json();
      })
      .then(function(resData) {
        if (!resData.success) {
          throw new Error(resData.error || "Failed to add batch " + (idx + 1));
        }
        processed += curBatch.length;
        var pct = Math.min(100, Math.round((processed / total) * 100));
        pFill.style.width = pct + "%";
        pPercent.innerText = pct + "%";
        pText.innerHTML = "<i class='fa fa-circle-o-notch fa-spin'></i> <?= $_adding_vouchers_mikrotik ?? "Adding vouchers to MikroTik..."; ?> " + processed + " / " + total + " (" + pct + "%)";
        processBatch(idx + 1);
      })
      .catch(function(err) {
        pText.innerHTML = "<i class='fa fa-exclamation-triangle text-danger'></i> <?= $_error ?? "Error"; ?>: " + processed + "/" + total + ": " + err.message;
        if (btn) {
          btn.disabled = false;
          btn.style.pointerEvents = "auto";
          btn.style.opacity = "1";
          btn.innerHTML = "<i class='fa fa-save'></i> <?= $_generate ?>";
        }
      });
    }

    processBatch(0);
  })
  .catch(function(err) {
    if (pText) {
      pText.innerHTML = "<i class='fa fa-exclamation-triangle text-danger'></i> " + err.message;
    }
    if (btn) {
      btn.disabled = false;
      btn.style.pointerEvents = "auto";
      btn.style.opacity = "1";
      btn.innerHTML = "<i class='fa fa-save'></i> <?= $_generate ?>";
    }
  });

  return false;
}

function startGenLoader() {
  return submitGenForm(event);
}
</script>
</div>
