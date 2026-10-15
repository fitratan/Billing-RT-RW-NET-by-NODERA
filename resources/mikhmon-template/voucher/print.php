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

  $langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
  if (file_exists('../lang/' . $langid . '.php')) {
    include_once('../lang/' . $langid . '.php');
  } elseif (file_exists('../lang/id.php')) {
    include_once('../lang/id.php');
  }

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


  $thermal = $_GET['thermal'] ?? ($_GET['t'] ?? ($_GET['template'] ?? ''));
  $isThermal = ($thermal === "yes" || $thermal === "thermal");
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Voucher-<?= $hotspotname . "-" . $getuprofile . "-" . $id; ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta http-equiv="pragma" content="no-cache" />
		<link rel="icon" href="../img/favicon.png" />
		<link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
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
.thermal-print-bar {
  position: sticky;
  top: 0;
  left: 0;
  right: 0;
  background: #1e293b;
  color: #fff;
  padding: 8px 12px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  z-index: 9999;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 13px;
}
.thermal-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 4px;
  border: none;
  font-size: 12px;
  font-weight: bold;
  cursor: pointer;
  text-decoration: none;
}
.thermal-btn-primary { background: #2563eb; color: #fff; }
.thermal-btn-primary:hover { background: #1d4ed8; }
.thermal-btn-secondary { background: #475569; color: #fff; }
.thermal-btn-secondary:hover { background: #334155; }
@media print {
  .thermal-print-bar { display: none !important; }
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
	<body <?= !$isThermal ? 'onload="window.print()"' : ''; ?>>

  <?php if ($isThermal): ?>
    <div class="thermal-print-bar">
      <span><i class="fa fa-print text-primary"></i> <strong><?= $_print_thermal ?? 'Cetak Thermal'; ?>:</strong></span>

      <?php if ($TotalReg > 1): ?>
        <div style="display: inline-flex; align-items: center; gap: 4px;">
          <select id="selSingleVoucher" style="padding: 4px 6px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 11px; max-width: 170px; background: #fff; cursor: pointer;">
            <?php for ($j = 0; $j < $TotalReg; $j++):
              $u = $getuser[$j]['name'] ?? '';
              $p = $getuser[$j]['profile'] ?? '';
            ?>
              <option value="<?= $j; ?>">#<?= ($j+1); ?>: <?= htmlspecialchars($u); ?> (<?= htmlspecialchars($p); ?>)</option>
            <?php endfor; ?>
          </select>
          <button type="button" class="thermal-btn" onclick="printSelectedSingleThermalBt()" style="background: #0284c7; color: #fff;" title="<?= $_print_single ?? 'Cetak 1 Saja'; ?>">
            <i class="fa fa-print"></i> <?= $_print_single ?? 'Cetak 1 Saja'; ?>
          </button>
        </div>
      <?php endif; ?>

      <button type="button" class="thermal-btn thermal-btn-primary" onclick="printAllThermalBt()" id="btnBtPrintAll" title="<?= $_print_all_vouchers ?? 'Cetak Semua'; ?> (<?= $TotalReg; ?>)">
        <i class="fa fa-bluetooth"></i> <span id="btnBtPrintAllText"><?= $_print_via_bluetooth ?? 'Cetak via Bluetooth'; ?> <?= $TotalReg > 1 ? "({$TotalReg})" : ""; ?></span>
      </button>
      <button type="button" class="thermal-btn thermal-btn-secondary" onclick="printAllThermalRawBt()" id="btnRawBtPrintAll" title="<?= $_rawbt_android ?? 'RawBT Android'; ?>">
        <i class="fa fa-android"></i> <?= $_rawbt_android ?? 'RawBT Android'; ?>
      </button>
      <button type="button" class="thermal-btn thermal-btn-secondary" onclick="window.print()">
        <i class="fa fa-print"></i> <?= $_print_browser ?? 'Cetak Browser'; ?>
      </button>
      <span id="btPrintStatus" style="font-size: 11px; opacity: 0.85;"></span>
    </div>
  <?php endif; ?>

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
if ($isThermal) {
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

<?php if ($isThermal): ?>
<script src="../js/mikhmon-thermal.js?v=<?= time(); ?>"></script>
<script>
var LANG_CONNECTED = <?= json_encode($_status_connected ?? 'Terhubung'); ?>;
var LANG_CONNECTING = <?= json_encode($_status_connecting ?? 'Menghubungkan...'); ?>;
var LANG_NOT_CONNECTED = <?= json_encode($_printer_not_connected ?? 'Belum Terhubung'); ?>;
var LANG_PRINT_VIA_BT = <?= json_encode($_print_via_bluetooth ?? 'Cetak via Bluetooth'); ?>;
var LANG_PRINT_TO = <?= json_encode($_print ?? 'Cetak ke'); ?>;
var LANG_PRINTING = <?= json_encode($_printing ?? 'Mencetak...'); ?>;
var LANG_PRINTED = <?= json_encode($_print_success ?? 'Dicetak!'); ?>;
var LANG_PRINT_SUCCESS = <?= json_encode($_print_success ?? 'Selesai Dicetak!'); ?>;
var LANG_PRINT_FAIL = <?= json_encode($_print_failed ?? 'Gagal cetak Bluetooth'); ?>;
var LANG_PRINT_VOUCHER_FAIL = <?= json_encode($_print_failed ?? 'Gagal cetak voucher'); ?>;
var LANG_NO_VOUCHER = <?= json_encode($_no_voucher_to_print ?? 'Tidak ada voucher yang dapat dicetak.'); ?>;
var LANG_BATCH_CONFIRM = <?= json_encode($_confirm_batch_thermal_print ?? 'Anda akan mencetak banyak voucher sekaligus ke printer thermal. Kertas akan keluar panjang. Lanjutkan cetak semua?'); ?>;
var LANG_BT_CONN_ERR = <?= json_encode($_printer_not_connected ?? 'Koneksi printer Bluetooth'); ?>;

function updatePrintBarBt(status, msg, thermalInstance) {
  var btnText = document.getElementById('btnBtPrintAllText');
  var statusEl = document.getElementById('btPrintStatus');
  if (status === 'connected') {
    if (btnText) btnText.innerHTML = LANG_PRINT_TO + ' (' + (thermalInstance.deviceName || 'Bluetooth') + ')';
    if (statusEl) statusEl.innerHTML = '<span style="color:#4ade80;"><i class="fa fa-circle"></i> ' + LANG_CONNECTED + '</span>';
  } else if (status === 'connecting') {
    if (statusEl) statusEl.innerHTML = '<span style="color:#facc15;"><i class="fa fa-circle-o-notch fa-spin"></i> ' + LANG_CONNECTING + '</span>';
  } else {
    if (btnText) btnText.innerHTML = LANG_PRINT_VIA_BT;
    if (statusEl) statusEl.innerHTML = '<span style="color:#94a3b8;"><i class="fa fa-circle-o"></i> ' + LANG_NOT_CONNECTED + '</span>';
  }
}

if (window.MikhmonThermal) {
  window.MikhmonThermal.addListener(updatePrintBarBt);
}

function extractVoucherData(el) {
  if (!el) return null;
  var code = el.getAttribute('data-code');
  if (!code) {
    var codeEl = el.querySelector('.v-th-code, .v-code, .qrcode');
    code = codeEl ? codeEl.textContent.trim() : '';
    if (!code && codeEl && codeEl.id) code = codeEl.id;
  }
  var profile = el.getAttribute('data-profile') || (el.querySelector('.v-th-meta, .v-profile') ? el.querySelector('.v-th-meta, .v-profile').textContent.trim() : '');
  var validity = el.getAttribute('data-validity') || (el.querySelector('.v-th-validity') ? el.querySelector('.v-th-validity').textContent.trim() : '');
  var price = el.getAttribute('data-price') || (el.querySelector('.v-th-price, .v-price') ? el.querySelector('.v-th-price, .v-price').textContent.trim() : '');
  var password = el.getAttribute('data-password') || '';

  return {
    hotspotName: <?= json_encode($hotspotname); ?>,
    dnsName: <?= json_encode($dnsname); ?>,
    code: code,
    username: code,
    password: password,
    profile: profile,
    validity: validity,
    price: price,
    logo: <?= json_encode($logo); ?>,
    lblProfile: <?= json_encode($_package ?? 'Paket'); ?>,
    lblValidity: <?= json_encode($_validity ?? 'Masa Aktif'); ?>,
    lblPrice: <?= json_encode($_price ?? 'Harga'); ?>,
    lblCode: <?= json_encode($_voucher_code ?? 'Kode Voucher'); ?>,
    lblSamePass: <?= json_encode($_same_user_pass ?? 'Username & Password sama'); ?>,
    lblPassword: <?= json_encode($_password_label ?? 'Password'); ?>,
    lblWarung: <?= json_encode($_warung ?? 'Warung'); ?>,
    lblThankYou: <?= json_encode($_thank_you_purchase ?? 'Terima kasih atas pembelian Anda'); ?>
  };
}

function collectVouchers() {
  var vouchers = [];
  var vEls = document.querySelectorAll('.voucher, .v-thermal');
  if (vEls.length > 0) {
    vEls.forEach(function(el) {
      var v = extractVoucherData(el);
      if (v) vouchers.push(v);
    });
  }
  return vouchers;
}

function printSingleThermalCard(btn) {
  if (!window.MikhmonThermal) return;
  var card = btn.closest('.thermal-receipt-card');
  if (!card) return;
  var vData = extractVoucherData(card);
  if (!vData) return;

  var origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i>';

  function doPrint() {
    window.MikhmonThermal.printVoucher(vData).then(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check"></i> ' + LANG_PRINTED;
      setTimeout(function() { btn.innerHTML = origHtml; }, 2000);
    }).catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      alert(LANG_PRINT_VOUCHER_FAIL + ': ' + (err.message || err));
    });
  }

  if (window.MikhmonThermal.isConnected) {
    doPrint();
  } else {
    window.MikhmonThermal.connect('printers').then(function(res) {
      if (res && res.cancelled) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        return;
      }
      doPrint();
    }).catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      var isCancel = (err && (err.name === 'NotFoundError' || err.name === 'AbortError' || (err.message && err.message.toLowerCase().indexOf('cancelled') !== -1)));
      if (!isCancel) {
        alert(LANG_BT_CONN_ERR + ': ' + (err.message || err));
      }
    });
  }
}

function printSingleThermalCardRawBt(btn) {
  if (!window.MikhmonThermal) return;
  var card = btn.closest('.thermal-receipt-card');
  if (!card) return;
  var vData = extractVoucherData(card);
  if (!vData) return;
  window.MikhmonThermal.printVoucherRawBT(vData);
}

function printSelectedSingleThermalBt() {
  var sel = document.getElementById('selSingleVoucher');
  if (!sel) return;
  var idx = parseInt(sel.value, 10);
  var cards = document.querySelectorAll('.thermal-receipt-card');
  if (cards[idx]) {
    var btn = cards[idx].querySelector('.btn-th-single');
    if (btn) {
      printSingleThermalCard(btn);
    } else {
      var vData = extractVoucherData(cards[idx]);
      window.MikhmonThermal.printVoucher(vData);
    }
  }
}

function printAllThermalRawBt() {
  if (!window.MikhmonThermal) return;
  var vouchers = collectVouchers();
  if (vouchers.length === 0) {
    alert(LANG_NO_VOUCHER);
    return;
  }
  vouchers.forEach(function(v) {
    window.MikhmonThermal.printVoucherRawBT(v);
  });
}

function printAllThermalBt() {
  if (!window.MikhmonThermal) return;
  var btnText = document.getElementById('btnBtPrintAllText');
  var origHtml = btnText ? btnText.innerHTML : LANG_PRINT_VIA_BT;
  var vouchers = collectVouchers();

  if (vouchers.length === 0) {
    alert(LANG_NO_VOUCHER);
    return;
  }

  if (vouchers.length > 5 && !confirm(LANG_BATCH_CONFIRM)) {
    return;
  }

  function doPrint() {
    if (btnText) btnText.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> ' + LANG_PRINTING + ' (' + vouchers.length + ')...';
    var p = Promise.resolve();
    vouchers.forEach(function(v, idx) {
      p = p.then(function() {
        if (btnText) btnText.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> ' + (idx + 1) + ' / ' + vouchers.length + '...';
        return window.MikhmonThermal.printVoucher(v);
      }).then(function() {
        return new Promise(function(r) { setTimeout(r, 400); });
      });
    });

    p.then(function() {
      if (btnText) btnText.innerHTML = '<i class="fa fa-check"></i> ' + LANG_PRINT_SUCCESS;
      setTimeout(function() { if (btnText) btnText.innerHTML = origHtml; }, 2500);
    }).catch(function(err) {
      if (btnText) btnText.innerHTML = origHtml;
      alert(LANG_PRINT_FAIL + ': ' + (err.message || err));
    });
  }

  if (!window.MikhmonThermal.isConnected) {
    window.MikhmonThermal.connect('printers').then(function(res) {
      if (res && res.cancelled) return;
      doPrint();
    }).catch(function(err) {
      var isCancel = (err && (err.name === 'NotFoundError' || err.name === 'AbortError' || (err.message && err.message.toLowerCase().indexOf('cancelled') !== -1)));
      if (!isCancel) {
        alert(LANG_BT_CONN_ERR + ': ' + (err.message || err));
      }
    });
  } else {
    doPrint();
  }
}
</script>
<?php endif; ?>
	
</body>
</html>
