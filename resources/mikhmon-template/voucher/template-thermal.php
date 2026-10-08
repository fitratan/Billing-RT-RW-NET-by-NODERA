<?php
$thUserLang = $_SESSION['lang'] ?? 'id';
$thIsFr = ($thUserLang === 'fr');
$thLblCode = $thIsFr ? 'Code Voucher' : 'Kode Voucher';
$thLblUser = $thIsFr ? 'Utilisateur' : 'Username';
$thLblPass = $thIsFr ? 'Mot de passe' : 'Password';
$thLblLogin = $thIsFr ? 'Connexion :' : 'Login:';

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
?>
<style>
.v-thermal {
  width: 180px;
  background: #ffffff;
  color: #000000;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace, sans-serif;
  padding: 6px 4px 8px;
  text-align: center;
  box-sizing: border-box;
  page-break-inside: avoid;
  border-bottom: 1px dashed #000000;
  margin: 0 auto 10px;
}
.v-th-logo img {
  max-height: 28px;
  max-width: 90px;
  object-fit: contain;
  margin-bottom: 3px;
}
.v-th-title {
  font-size: 13px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  line-height: 1.2;
}
.v-th-divider {
  border-top: 1px dashed #000000;
  margin: 5px 0;
}
.v-th-code-box {
  border: 1.5px solid #000000;
  border-radius: 4px;
  padding: 5px 4px;
  margin: 5px 0;
}
.v-th-label {
  font-size: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.v-th-code {
  font-family: "Courier New", Courier, monospace;
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 2px;
  line-height: 1.2;
  word-break: break-all;
}
.v-th-up-box {
  display: table;
  width: 100%;
  margin: 4px 0;
}
.v-th-up-col {
  display: table-cell;
  width: 50%;
  border: 1px solid #000000;
  padding: 3px;
  text-align: center;
}
.v-th-qr canvas, .v-th-qr .qrcode {
  width: 80px !important;
  height: 80px !important;
  margin: 4px auto;
  display: block;
}
.v-th-meta {
  font-size: 11px;
  font-weight: 800;
  margin: 4px 0 2px;
}
.v-th-price {
  font-size: 14px;
  font-weight: 900;
  margin: 2px 0 4px;
}
.v-th-footer {
  font-size: 8.5px;
  font-weight: 600;
  line-height: 1.3;
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
  .v-thermal {
    width: 100% !important;
    max-width: 58mm !important;
    margin: 0 auto !important;
    padding: 6px 4px !important;
    border-bottom: 1px dashed #000000 !important;
    page-break-inside: avoid !important;
    page-break-after: always !important;
  }
}
</style>

<div class="voucher v-thermal">
  <?php if ($vShowHsName && !empty($hotspotname)): ?>
    <div class="v-th-title"><?= $hotspotname; ?></div>
  <?php endif; ?>
  <?php if ($vShowLogo && !empty($logo)): ?>
    <div class="v-th-logo">
      <img src="<?= $logo; ?>" alt="logo">
    </div>
  <?php endif; ?>
  
  <div class="v-th-divider"></div>

  <?php if ($usermode == "vc"): ?>
    <div class="v-th-code-box">
      <div class="v-th-label"><?= $thLblCode; ?></div>
      <div class="v-th-code"><?= $username; ?></div>
    </div>
  <?php elseif ($usermode == "up"): ?>
    <div class="v-th-up-box">
      <div class="v-th-up-col" style="border-right: none;">
        <div class="v-th-label"><?= $thLblUser; ?></div>
        <div style="font-family: 'Courier New', monospace; font-size: 12px; font-weight: 900;"><?= $username; ?></div>
      </div>
      <div class="v-th-up-col">
        <div class="v-th-label"><?= $thLblPass; ?></div>
        <div style="font-family: 'Courier New', monospace; font-size: 12px; font-weight: 900;"><?= $password; ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($qr == "yes"): ?>
    <div class="v-th-qr">
      <?= $qrcode; ?>
    </div>
  <?php endif; ?>

  <?php 
    $metaParts = [];
    if ($vShowValidity && !empty($validity)) $metaParts[] = $validity;
    if ($vShowValidity && empty($validity) && !empty($timelimit)) $metaParts[] = $timelimit;
    if ($vShowDataLimit && !empty($datalimit)) $metaParts[] = $datalimit;
    $metaStr = implode(' ', $metaParts);
  ?>
  <?php if (!empty($metaStr)): ?>
    <div class="v-th-meta">
      <?= htmlspecialchars($metaStr); ?>
    </div>
  <?php endif; ?>
  
  <?php if ($vShowPrice && !empty($price)): ?>
    <div class="v-th-price"><?= $price; ?></div>
  <?php endif; ?>

  <?php if ($vShowDns): ?>
    <div class="v-th-divider"></div>

    <div class="v-th-footer">
      <?= $thLblLogin; ?> http://<?= $dnsname; ?><br>
      <span style="font-size: 7.5px; color: #555;"><?= date("d/m/Y H:i"); ?></span>
    </div>
  <?php endif; ?>
</div>
