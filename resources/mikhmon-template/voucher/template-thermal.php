<?php
// Load language variables
$thUserLang = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
if (file_exists('../lang/' . $thUserLang . '.php')) {
    include_once('../lang/' . $thUserLang . '.php');
} elseif (file_exists('./lang/' . $thUserLang . '.php')) {
    include_once('./lang/' . $thUserLang . '.php');
}

$vCfgPath = file_exists('../include/voucher_config.php') ? '../include/voucher_config.php' : (file_exists('./include/voucher_config.php') ? './include/voucher_config.php' : '');
if ($vCfgPath) { @include($vCfgPath); }
$thCfgItem = $voucher_config[$session] ?? null;
$vShowPrice = is_array($thCfgItem) ? (($thCfgItem['show_price'] ?? 'yes') !== 'no') : true;
$vShowValidity = is_array($thCfgItem) ? (($thCfgItem['show_validity'] ?? 'yes') !== 'no') : true;
$vShowDataLimit = is_array($thCfgItem) ? (($thCfgItem['show_datalimit'] ?? 'yes') !== 'no') : true;
$vShowLogo = is_array($thCfgItem) ? (($thCfgItem['show_logo'] ?? 'yes') !== 'no') : true;
$vShowHsName = is_array($thCfgItem) ? (($thCfgItem['show_hotspotname'] ?? 'yes') !== 'no') : true;
$vShowDns = is_array($thCfgItem) ? (($thCfgItem['show_dns'] ?? 'yes') !== 'no') : true;
$vShowNum = is_array($thCfgItem) ? (($thCfgItem['show_num'] ?? 'yes') !== 'no') : true;

$thProfile = !empty($profile) ? $profile : ($getuprofile ?? '');
$thValidity = !empty($validity) ? $validity : (!empty($timelimit) ? $timelimit : '');
if ($vShowDataLimit && !empty($datalimit)) {
    $thValidity = trim($thValidity . ' ' . $datalimit);
}
?>
<style>
.thermal-receipt-card {
  background: #ffffff;
  color: #111827;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  text-align: center;
  font-family: 'Courier New', Courier, monospace;
  margin: 0 auto 12px auto;
  max-width: 270px;
  width: 100%;
  box-sizing: border-box;
  page-break-inside: avoid;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  transition: all 0.2s ease;
}
.thermal-receipt-card .th-dashed {
  border-top: 1px dashed #000000;
  margin: 6px 0;
}
.thermal-receipt-card .th-meta-row {
  font-size: 11px;
  text-align: left;
  margin-bottom: 2px;
  line-height: 1.35;
  padding: 0 2px;
}
.thermal-receipt-card .th-code-box {
  border: 2px solid #000000;
  padding: 5px;
  margin: 6px 0;
  background: #f8fafc;
  border-radius: 3px;
}
.thermal-receipt-card .th-code-val {
  font-size: 18px;
  font-weight: 900;
  letter-spacing: 2px;
  color: #000000;
  line-height: 1.2;
  word-break: break-all;
}
.thermal-receipt-card .v-th-qr canvas,
.thermal-receipt-card .v-th-qr .qrcode {
  width: 75px !important;
  height: 75px !important;
  margin: 0 auto;
  display: block;
}

/* Style Variants */
.thermal-receipt-card.th-style-compact {
  padding: 6px 8px;
  max-width: 240px;
}
.thermal-receipt-card.th-style-compact .th-code-box {
  border-width: 1.5px;
  padding: 3px;
  margin: 4px 0;
}
.thermal-receipt-card.th-style-compact .v-th-qr,
.thermal-receipt-card.th-style-compact .th-guide-box {
  display: none !important;
}

.thermal-receipt-card.th-style-modern {
  border: 2px solid #1e293b;
  border-radius: 6px;
  background: #ffffff;
}
.thermal-receipt-card.th-style-modern .th-dashed {
  border-top: 2px solid #1e293b;
}
.thermal-receipt-card.th-style-modern .th-code-box {
  border: 2px dashed #0f172a;
  background: #f1f5f9;
}

.thermal-receipt-card.th-style-detailed .th-guide-box {
  display: block !important;
  text-align: left;
  font-size: 9.5px;
  line-height: 1.4;
  margin: 6px 0;
  padding: 4px 6px;
  background: #f8fafc;
  border-left: 2px solid #000;
}

@media print {
  @page {
    size: 58mm auto;
    margin: 0mm;
  }
  body {
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
  }
  .no-print, .th-card-actions, .thermal-print-bar {
    display: none !important;
  }
  .thermal-receipt-card {
    border: none !important;
    box-shadow: none !important;
    max-width: 58mm !important;
    width: 100% !important;
    margin: 0 auto !important;
    padding: 6px 2px !important;
    border-bottom: 1px dashed #000000 !important;
    page-break-inside: avoid !important;
    page-break-after: always !important;
  }
}
</style>

<div class="voucher v-thermal thermal-receipt-card th-style-classic"
     data-num="<?= $num ?? 1; ?>"
     data-code="<?= htmlspecialchars($username); ?>"
     data-password="<?= htmlspecialchars($password); ?>"
     data-profile="<?= htmlspecialchars($thProfile); ?>"
     data-validity="<?= htmlspecialchars($thValidity); ?>"
     data-price="<?= htmlspecialchars($price); ?>">
  <?php if ($vShowLogo && !empty($logo)): ?>
    <div style="text-align: center; margin-bottom: 4px;">
      <img src="<?= $logo; ?>" alt="Logo" style="max-height: 38px; max-width: 130px; height: auto; object-fit: contain; margin: 0 auto; display: inline-block;">
    </div>
  <?php endif; ?>

  <?php if ($vShowHsName && !empty($hotspotname)): ?>
    <div style="font-weight: bold; font-size: 13px; line-height: 1.2; text-transform: uppercase;" class="v-th-title"><?= htmlspecialchars($hotspotname); ?></div>
  <?php endif; ?>

  <?php if ($vShowDns && !empty($dnsname) && strtolower(trim($dnsname)) !== strtolower(trim($hotspotname))): ?>
    <div style="font-size: 10px; color: #4b5563;" class="v-th-dns"><?= htmlspecialchars($dnsname); ?></div>
  <?php endif; ?>

  <div class="th-dashed"></div>

  <?php if (!empty($thProfile)): ?>
    <div class="th-meta-row">
      <?= $_package ?? 'Paket'; ?>: <b class="v-th-meta v-profile"><?= htmlspecialchars($thProfile); ?></b>
    </div>
  <?php endif; ?>

  <?php if ($vShowValidity && !empty($thValidity)): ?>
    <div class="th-meta-row">
      <?= $_validity ?? 'Masa Aktif'; ?>: <b class="v-th-validity"><?= htmlspecialchars($thValidity); ?></b>
    </div>
  <?php endif; ?>

  <?php if ($vShowPrice && !empty($price)): ?>
    <div class="th-meta-row" style="font-size: 12px; font-weight: bold;">
      <?= $_price ?? 'Harga'; ?>: <span class="v-th-price v-price"><?= htmlspecialchars($price); ?></span>
    </div>
  <?php endif; ?>

  <div class="th-code-box">
    <?php if ($usermode == "vc" || empty($password) || $username === $password): ?>
      <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85;"><?= $_voucher_code ?? 'Kode Voucher'; ?>:</div>
      <div class="th-code-val v-th-code v-code"><?= htmlspecialchars($username); ?></div>
    <?php else: ?>
      <div style="display: flex; justify-content: space-around; align-items: center;">
        <div style="text-align: center; flex: 1; border-right: 1px dashed #000; padding-right: 4px;">
          <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85;"><?= $_username_label ?? 'Username'; ?>:</div>
          <div style="font-size: 14px; font-weight: 900; color: #000;" class="v-th-code v-code"><?= htmlspecialchars($username); ?></div>
        </div>
        <div style="text-align: center; flex: 1; padding-left: 4px;">
          <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85;"><?= $_password_label ?? 'Password'; ?>:</div>
          <div style="font-size: 14px; font-weight: 900; color: #000;"><?= htmlspecialchars($password); ?></div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($qr == "yes" || !empty($qrcode)): ?>
    <div style="margin-top: 4px;" class="v-th-qr">
      <?= $qrcode; ?>
      <div style="font-size: 8.5px; color: #4b5563; margin-top: 2px;"><?= $_scan_qr_to_login ?? 'Scan QR untuk Login Otomatis'; ?></div>
    </div>
  <?php endif; ?>

  <!-- Panduan Login (khusus mode Detailed) -->
  <div class="th-guide-box" style="display: none;">
    <b><?= $_how_to_login ?? 'Cara Login:'; ?></b><br>
    <?= $_step_connect_wifi ?? '1. Sambungkan WiFi:'; ?> <?= htmlspecialchars($hotspotname); ?><br>
    <?= $_step_open_browser ?? '2. Buka browser:'; ?> <?= htmlspecialchars($dnsname); ?><br>
    <?= $_step_enter_code ?? '3. Masukkan kode voucher di atas'; ?>
  </div>

  <div class="th-dashed"></div>

  <div style="font-size: 10px; font-weight: bold;"><?= htmlspecialchars($hotspotname); ?></div>
  <div style="font-size: 9px; margin-top: 2px; color: #6b7280;"><?= $_thank_you_purchase ?? 'Terima kasih atas pembelian Anda'; ?></div>

  <div class="th-card-actions no-print" style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #cbd5e1; display: flex; gap: 6px; justify-content: center; align-items: center;">
    <button type="button" class="btn-th-single" onclick="printSingleThermalCard(this)" title="<?= $_print ?? 'Cetak'; ?> #<?= $num ?? 1; ?>" style="background: #0284c7; color: #fff; border: 1px solid #0284c7; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
      <i class="fa fa-print"></i> <?= $_print_single_voucher ?? 'Cetak Voucher Ini'; ?>
    </button>
    <button type="button" class="btn-th-single-rawbt" onclick="printSingleThermalCardRawBt(this)" title="RawBT Android #<?= $num ?? 1; ?>" style="background: #475569; color: #fff; border: 1px solid #475569; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
      <i class="fa fa-android"></i> RawBT
    </button>
  </div>
</div>
