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
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}
// hide all error
error_reporting(0);

if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
  header("Location:./admin.php?id=login");
  exit;
} else {

  include ('./include/version.php');

  $btnmenuactive = "font-weight: bold;background-color: #f9f9f9; color: #000000";
  if ($hotspot == "dashboard" || substr(end(explode("/", $url)), 0, 8) == "?session") {
    $shome = "active";
    $mpage = $_dashboard;
  } elseif ($hotspot == "quick-print") {
    $squick = "active";
    $mpage = $_quick_print;   
    $quickmenu = "menu-open";
  } elseif ($hotspot == "list-quick-print") {
    $slistquick = "active";
    $mpage = $_quick_print;
    $quickmenu = "menu-open";
  } elseif ($hotspot == "users" || $userbyprofile != "" || $hotspot == "export-users" || $removehotspotuserbycomment != "" || $removehotspotuser != "" || $removehotspotusers != "" || $disablehotspotuser || $enablehotspotuser != "") {
    $susersl = "active";
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspotuser == "add") {
    $sadduser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($hotspotuser == "generate") {
    $sgenuser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($userbyname != ""  || $resethotspotuser != "") {
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspot == "user-profiles") {
    $suserprofiles = "active";
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "active" || $removeuseractive != "") {
    $sactive = "active";
    $mpage = $_hotspot_active;
    $hamenu = "menu-open";
  } elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta" || $removehost != "") {
    $shosts = "active";
    $mpage = $_hosts;
    $hmenu = "menu-open";
  } elseif ($hotspot == "dhcp-leases") {
    $slease = "active";
    $mpage = $_dhcp_leases;
  } elseif ($minterface == "traffic-monitor") {
    $strafficmonitor = "active";
    $mpage = $_traffic_monitor;  
  } elseif ($hotspot == "ipbinding" || $hotspot == "binding" || $removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    $sipbind = "active";
    $mpage = $_ip_bindings;
    $ibmenu = "menu-open";
  } elseif ($hotspot == "template-selector" || $hotspot == "template-editor") {
    $ssett = "active";
    $tselector = "active";
    $mpage = $_template_editor ?? "Template Voucher";
    $settmenu = "menu-open";
  } elseif ($hotspot == "store-template" || $hotspot == "shop-template" || $id == "store-template" || $id == "shop-template") {
    $ssett = "active";
    $stselector = "active";
    $mpage = $_store_template ?? "Template Toko Online";
    $settmenu = "menu-open";
  } elseif ($hotspot == "uplogo") {
    $ssett = "active";
    $uplogo = "active";
    $mpage = $_upload_logo;
    $settmenu = "menu-open";
  } elseif ($hotspot == "cookies" || $removecookie != "") {
    $scookies = "active";
    $mpage = $_hotspot_cookies;
    $cmenu = "menu-open";
  } elseif ($hotspot == "log") {
    $log = "active";
    $slog = "active";
    $mpage = $_hotspot_log;
    $lmenu = "menu-open";
  } elseif ($report == "userlog") {
    $log = "active";
    $sulog = "active";
    $mpage = $_user_log;
    $lmenu = "menu-open";
  } elseif ($ppp == "secrets" || $ppp == "addsecret" || $enablesecr != "" || $disablesecr != "" || $removesecr != "" || $secretbyname != "") {
    $mppp = "active";
    $ssecrets = "active";
    $mpage = $_ppp_secrets;
    $pppmenu = "menu-open";
  } elseif ($ppp == "profiles" || $removepprofile != "" || $ppp == "add-profile" || $ppp == "edit-profile"  ) {
    $mppp = "active";
    $spprofile = "active";
    $mpage = $_ppp_profiles;
    $pppmenu = "menu-open";
  } elseif ($ppp == "active" || $removepactive != "") {
    $mppp = "active";
    $spactive = "active";
    $mpage = $_ppp_active;
    $pppmenu = "menu-open";
  } elseif ($sys == "scheduler" || $enablesch != "" || $disablesch != "" || $removesch != "") {
    $sysmenu = "active";
    $ssch = "active";
    $mpage = $_system_scheduler;
    $schmenu = "menu-open";
  } elseif ($report == "selling" || $report == "resume-report") {
    $sselling = "active";
    $mpage = $_report;
  } elseif ($userprofile == "add") {
    $suserprof = "active";
    $sadduserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($userprofilebyname != "") {
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "users-by-profile") {
    $susersbp = "active";
    $mpage = $_vouchers;
  } elseif ($userbyname != "") {
    $mpage = $_users;
    $susers = "active";
  } elseif ($hotspot == "billing" || $id == "billing") {
    $sbilling = "active";
    $mpage = $_billing_cloud ?? 'NODERA Billing Cloud';
    $noderamenu = "menu-open";
  } elseif ($hotspot == "shop" || $id == "shop") {
    $sshop = "active";
    $mpage = $_shop_online ?? 'NODERA Store';
    $noderamenu = "menu-open";
  } elseif ($hotspot == "telegram" || $id == "telegram") {
    $stelegram = "active";
    $mpage = $_telegram_bot ?? "Telegram Bot";
  } elseif ($hotspot == "whatsapp" || $id == "whatsapp") {
    $swhatsapp = "active";
    $mpage = $_whatsapp_gateway ?? "WhatsApp Gateway";
  } elseif ($hotspot == "noderapay" || $id == "noderapay" || $hotspot == "qris" || $id == "qris") {
    $snoderapay = "active";
    $mpage = "NODERA Pay";
    $noderamenu = "menu-open";
  } elseif ($hotspot == "warung" || $id == "warung" || $hotspot == "agent" || $id == "agent") {
    $swarung = "active";
    $warungmenu = "menu-open";
    $tab = $_GET['tab'] ?? 'list';
    if ($tab == 'add') {
      $swarung_add = "active";
      $mpage = $_add_warung ?? "Tambah Warung";
    } elseif ($tab == 'topup') {
      $swarung_topup = "active";
      $mpage = $_warung_topup ?? "Top Up Saldo Warung";
    } elseif ($tab == 'settings') {
      $swarung_settings = "active";
      $mpage = $_warung_settings ?? "Pengaturan Warung";
    } elseif ($tab == 'prices') {
      $swarung_prices = "active";
      $mpage = $_profile_cost_price ?? "Harga Modal Profil Warung";
    } elseif ($tab == 'reports') {
      $swarung_reports = "active";
      $mpage = $_selling_report ?? "Laporan Penjualan Warung";
    } elseif ($tab == 'edit') {
      $swarung_list = "active";
      $mpage = $_edit_warung ?? "Edit Warung";
    } else {
      $swarung_list = "active";
      $mpage = $_list_warung ?? "Daftar Warung";
    }
  } elseif ($hotspot == "about") {
    $mpage = $_about;
    $sabout = "active";
  } elseif ($id == "sessions" || $id == "remove" || $router == "new") {
    $ssesslist = "active";
    $mpage = $_admin_settings;
  } elseif ($id == "settings" && $session == "new") {
    $snsettings = "active";
    $mpage = $_add_router;
  } elseif ($id == "settings" || $id == "connect") {
    $ssettings = "active";
    $mpage = $_session_settings;
  } elseif ($id == "about") {
    $sabout = "active";
    $mpage = $_about;
  } elseif ($id == "uplogo") {
    $suplogo = "active";
    $mpage = $_upload_logo;
  } elseif ($id == "editor") {
    $seditor = "active";
    $mpage = $_template_editor;
  }
}

if($idleto != "disable"){
  $didleto = 'display:block;';
}else{
  $didleto = 'display:none;';
}
$cleanLangUrl = preg_replace('/([?&])setlang=[^&]*(&|$)/', '$1', $url);
$cleanLangUrl = rtrim($cleanLangUrl, '?&');
$langSep = (strpos($cleanLangUrl, '?') !== false) ? '&' : '?';

$cleanThemeUrl = preg_replace('/([?&])set-theme=[^&]*(&|$)/', '$1', $url);
$cleanThemeUrl = rtrim($cleanThemeUrl, '?&');
$themeSep = (strpos($cleanThemeUrl, '?') !== false) ? '&' : '?';

?>
<span style="display:none;" id="idto"><?= $idleto ;?></span>


<?php if ($id != "") { ?>

<div id="navbar" class="navbar">
  <div class="navbar-left">
    <a id="brand" class="text-center" href="javascript:void(0)">MIKHMON</a>

    <a id="openNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
    <a id="closeNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
    <a id="cpage" class="navbar-left" href="javascript:void(0)"><?= !empty($mpage) ? $mpage : 'MIKHMON'; ?></a>
  </div>
  <div class="navbar-right">
    <a id="logout" href="./admin.php?id=logout" title="<?= $_logout ?>"><i class="fa fa-sign-out mr-1"></i> <span class="logout-text"><?= $_logout ?></span></a>
    <select class="stheme ses text-right mr-t-10 pd-5">
      <option value=""> <?= $_theme ?></option>
      <?php for ($i = 0; $i < count($mtheme); $i++) {
        $selTheme = (isset($_SESSION['theme']) && $_SESSION['theme'] == $mtheme[$i]) ? 'selected' : '';
        echo '<option value="'.$cleanThemeUrl.$themeSep.'set-theme='.$mtheme[$i].'" '.$selTheme.'>'.ucfirst($mtheme[$i]).'</option>';
      }
      ?>
    </select>
    <select class="slang ses text-right mr-t-10 pd-5">
      <option value=""> <?= $language ?></option>
      <?php foreach ($isocodelang as $code => $name): 
        $selLang = (isset($langid) && $langid == $code) ? 'selected' : '';
        echo '<option value="'.$cleanLangUrl.$langSep.'setlang=' . $code . '" '.$selLang.'>'. $name . '</option>'; 
      endforeach; ?>
    </select>
    <a title="Idle Timeout" style="<?= $didleto; ?>"><span style="width:70px;" class="pd-5 radius-3"><i class="fa fa-clock-o mr-1"></i>  <span class="mr-1" id="timer"></span></span></a>
    
    <!-- Mobile 3-Dots Kebab Trigger -->
    <a href="javascript:void(0)" id="topKebabBtn" class="top-kebab-btn" title="<?= $_menu ?? "Menu"; ?>"><i class="fa fa-ellipsis-v"></i></a>
  </div>

  <!-- Mobile Kebab Dropdown Menu -->
  <div id="topKebabDropdown" class="top-kebab-dropdown" style="display:none;">
    <div class="kebab-item-header">
      <span><i class="fa fa-sliders"></i> <?= !empty($_quick_settings) ? $_quick_settings : (!empty($_settings) ? $_settings : 'Pengaturan Cepat'); ?></span>
      <a href="javascript:void(0)" id="closeKebabBtn" class="close-kebab-btn">&times;</a>
    </div>
    
    <div class="kebab-section">
      <label><i class="fa fa-paint-brush"></i> <?= !empty($_choose_theme) ? $_choose_theme : (!empty($_theme) ? $_theme : 'Tema'); ?></label>
      <select class="form-control stheme-mobile" onchange="notify('<?= $_loading_theme ?>'); stheme(this.value);">
        <option value="">-- <?= !empty($_choose_theme) ? $_choose_theme : 'Pilih Tema'; ?> --</option>
        <?php for ($i = 0; $i < count($mtheme); $i++) {
          $selTheme = (isset($_SESSION['theme']) && $_SESSION['theme'] == $mtheme[$i]) ? 'selected' : '';
          echo '<option value="'.$cleanThemeUrl.$themeSep.'set-theme='.$mtheme[$i].'" '.$selTheme.'>'.ucfirst($mtheme[$i]).'</option>';
        } ?>
      </select>
    </div>

    <div class="kebab-section">
      <label><i class="fa fa-language"></i> <?= !empty($_choose_language) ? $_choose_language : (!empty($language) ? $language : 'Bahasa'); ?></label>
      <select class="form-control slang-mobile" onchange="notify('<?= $_loading ?>'); stheme(this.value);">
        <option value="">-- <?= !empty($_choose_language) ? $_choose_language : 'Pilih Bahasa'; ?> --</option>
        <?php foreach ($isocodelang as $code => $name): 
          $selLang = (isset($langid) && $langid == $code) ? 'selected' : '';
          echo '<option value="'.$cleanLangUrl.$langSep.'setlang=' . $code . '" '.$selLang.'>'. $name . '</option>'; 
        endforeach; ?>
      </select>
    </div>

    <div class="kebab-divider"></div>

    <a href="./admin.php?id=logout" class="kebab-logout-btn">
      <i class="fa fa-sign-out"></i> <?= !empty($_logout) ? $_logout : 'Keluar'; ?>
    </a>
  </div>
</div>

<div id="sidenav" class="sidenav">
<?php if (($id == "settings" && $session == "new") || $id == "settings" && $router == "new") {
} else if ($id == "settings" || $id == "editor" || $id == "uplogo" || $id == "connect" || $id == "telegram" || $id == "whatsapp" || $id == "noderapay" || $id == "qris" || $id == "billing" || $id == "shop" || $id == "store-template" || $id == "shop-template") {
?>  
  <div class="menu text-center align-middle card-header" style="border-radius:0;"><h3 id="MikhmonSession"><?= $session; ?></h3></div>
  <a class="connect menu <?= $shome ?? ''; ?>" id="<?= $session; ?>&c=settings"><i class='fa fa-tachometer'></i> <?= $_dashboard ?></a>
  <div class="dropdown-btn <?= ($sbilling ?? '') . ($sshop ?? '') . ($snoderapay ?? ''); ?>"><i class="fa fa-cubes"></i> NODERA
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $noderamenu ?? ''; ?>">
    <a href="./admin.php?id=noderapay<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $snoderapay ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-qrcode"></i> NODERA Pay </a>
    <a href="./admin.php?id=billing" class="<?= $sbilling ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-cloud"></i> <?= $_billing_cloud ?? 'NODERA Billing Cloud'; ?> </a>
    <a href="./admin.php?id=shop" class="<?= $sshop ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-shopping-cart"></i> <?= $_shop_online ?? 'NODERA Store'; ?> </a>
  </div>

  <div class="dropdown-btn <?= ($swarung ?? '') . ($swarung_add ?? '') . ($swarung_list ?? '') . ($swarung_topup ?? '') . ($swarung_prices ?? '') . ($swarung_reports ?? ''); ?>"><i class="fa fa-shopping-basket"></i> <?= $_warung ?? "Warung"; ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $warungmenu ?? ''; ?>">
    <a href="./admin.php?id=warung&tab=add<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $swarung_add ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-user-plus"></i> <?= $_add_warung ?? "Tambah Warung"; ?> </a>
    <a href="./admin.php?id=warung&tab=list<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $swarung_list ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-users"></i> <?= $_list_warung ?? "Daftar Warung"; ?> </a>
    <a href="./admin.php?id=warung&tab=topup<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $swarung_topup ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-plus-circle"></i> <?= $_topup_warung ?? "Top Up Warung"; ?> </a>
    <a href="./admin.php?id=warung&tab=prices<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $swarung_prices ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-tags"></i> <?= $_profile_cost_price ?? "Harga Modal Profil"; ?> </a>
    <a href="./admin.php?id=warung&tab=reports<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $swarung_reports ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-bar-chart"></i> <?= $_selling_report ?? "Laporan Penjualan"; ?> </a>
  </div>
  <a href="./admin.php?id=settings&session=<?= $session; ?>" class="menu <?= $ssettings ?? ''; ?>" title="Mikhmon Settings"><i class='fa fa-gear'></i> <?= $_session_settings ?></a>
  <a href="./admin.php?id=whatsapp&session=<?= $session; ?>" class="menu <?= $swhatsapp ?? ''; ?>" title="WhatsApp Gateway"><i class="fa fa-whatsapp"></i> <?= $_whatsapp_gateway ?? "WhatsApp Gateway"; ?></a>
  <a href="./admin.php?id=telegram&session=<?= $session; ?>" class="menu <?= $stelegram ?? ''; ?>" title="Telegram Bot"><i class="fa fa-paper-plane"></i> Telegram Bot</a>
  <a href="./admin.php?id=uplogo&session=<?= $session; ?>" class="menu <?= $suplogo ?? ''; ?>"><i class="fa fa-upload "></i> <?= $_upload_logo ?></a>
  <a href="./admin.php?id=editor&template=default&session=<?= $session; ?>" class="menu <?= $seditor ?? ''; ?>"><i class="fa fa-edit"></i> <?= $_template_editor ?></a>
  <a href="./admin.php?id=store-template&session=<?= $session; ?>" class="menu <?= $stselector ?? ''; ?>"><i class="fa fa-desktop"></i> Template Toko Online</a>
  <div class="menu spa"></div>
<?php 
} ?>  
      <a href="./admin.php?id=sessions" class="menu <?= $ssesslist ?? ''; ?>"><i class="fa fa-gear"></i> <span><?= $_admin_settings ?></span></a>
  <a href="./admin.php?id=settings&router=new-<?= rand(1111,9999) ?>" class="menu <?= $snsettings ?? ''; ?>"><i class="fa fa-plus"></i> <span><?= $_add_router ?></span></a>
  <div class="dropdown-btn <?= ($sbilling ?? '') . ($sshop ?? '') . ($snoderapay ?? ''); ?>"><i class="fa fa-cubes"></i> NODERA
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $noderamenu ?? ''; ?>">
    <a href="./admin.php?id=noderapay" class="<?= $snoderapay ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-qrcode"></i> NODERA Pay </a>
    <a href="./admin.php?id=billing" class="<?= $sbilling ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-cloud"></i> <?= $_billing_cloud ?? 'NODERA Billing Cloud'; ?> </a>
    <a href="./admin.php?id=shop" class="<?= $sshop ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-shopping-cart"></i> <?= $_shop_online ?? 'NODERA Store'; ?> </a>
  </div>

  <div class="dropdown-btn <?= ($swarung ?? '') . ($swarung_add ?? '') . ($swarung_list ?? '') . ($swarung_topup ?? '') . ($swarung_prices ?? '') . ($swarung_reports ?? ''); ?>"><i class="fa fa-shopping-basket"></i> <?= $_warung ?? "Warung"; ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $warungmenu ?? ''; ?>">
    <a href="./admin.php?id=warung&tab=add" class="<?= $swarung_add ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-user-plus"></i> <?= $_add_warung ?? "Tambah Warung"; ?> </a>
    <a href="./admin.php?id=warung&tab=list" class="<?= $swarung_list ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-users"></i> <?= $_list_warung ?? "Daftar Warung"; ?> </a>
    <a href="./admin.php?id=warung&tab=topup" class="<?= $swarung_topup ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-plus-circle"></i> <?= $_topup_warung ?? "Top Up Warung"; ?> </a>
    <a href="./admin.php?id=warung&tab=prices" class="<?= $swarung_prices ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-tags"></i> <?= $_profile_cost_price ?? "Harga Modal Profil"; ?> </a>
    <a href="./admin.php?id=warung&tab=reports" class="<?= $swarung_reports ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-bar-chart"></i> <?= $_selling_report ?? "Laporan Penjualan"; ?> </a>
  </div>
  <a href="./admin.php?id=whatsapp" class="menu <?= $swhatsapp ?? ''; ?>"><i class="fa fa-whatsapp"></i> <span>WhatsApp Gateway</span></a>
  <a href="./admin.php?id=telegram" class="menu <?= $stelegram ?? ''; ?>"><i class="fa fa-paper-plane"></i> <span>Telegram Bot</span></a>
  <a href="./admin.php?id=update" class="menu <?= ($id == 'update') ? 'active' : ''; ?>"><i class="fa fa-refresh"></i> <span><?= !empty($_system_update) ? $_system_update : 'Pembaruan Sistem'; ?></span></a>
  <a href="./admin.php?id=about" class="menu <?= $sabout ?? ''; ?>"><i class="fa fa-info-circle"></i> <span><?= $_about ?></span></a>

</div>
<script>
$(document).ready(function(){
  $(".connect").click(function(){
    if (typeof showMikhmonOverlay === "function") {
      showMikhmonOverlay("Loading...", "");
    }
    notify("<?= $_connecting ?>");
    connect(this.id);
  });
  $(".stheme").change(function(){
    if (typeof showMikhmonOverlay === "function") {
      showMikhmonOverlay("Loading...", "");
    }
    notify("<?= $_loading_theme ?>");
    stheme(this.value);
  });
  $(".slang").change(function(){
    if (typeof showMikhmonOverlay === "function") {
      showMikhmonOverlay("Loading...", "");
    }
    notify("<?= $_loading ?>");
    stheme(this.value);
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./info.php');
} else { ?>

<div id="navbar" class="navbar">
  <div class="navbar-left">
    <a id="brand" class="text-center" href="./?session=<?= $session; ?>">MIKHMON</a>

    <a id="openNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
    <a id="closeNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
    <a id="cpage" class="navbar-left" href="javascript:void(0)"><?= !empty($mpage) ? $mpage : 'MIKHMON'; ?></a>
  </div>
  <div class="navbar-right">
    <a id="logout" href="./?hotspot=logout&session=<?= $session; ?>" title="<?= $_logout ?>"><i class="fa fa-sign-out mr-1"></i> <span class="logout-text"><?= $_logout ?></span></a>
    <select class="stheme ses text-right mr-t-10 pd-5">
      <option value=""> <?= $_theme ?></option>
      <?php for ($i = 0; $i < count($mtheme); $i++) {
        $selTheme = (isset($_SESSION['theme']) && $_SESSION['theme'] == $mtheme[$i]) ? 'selected' : '';
        echo '<option value="'.$cleanThemeUrl.$themeSep.'set-theme='.$mtheme[$i].'" '.$selTheme.'>'.ucfirst($mtheme[$i]).'</option>';
      }
      ?>
    </select>
    <select class="slang ses text-right mr-t-10 pd-5">
      <option value=""> <?= $language ?></option>
      <?php foreach ($isocodelang as $code => $name): 
        $selLang = (isset($langid) && $langid == $code) ? 'selected' : '';
        echo '<option value="'.$cleanLangUrl.$langSep.'setlang=' . $code . '" '.$selLang.'>'. $name . '</option>'; 
      endforeach; ?>
    </select>
    <select class="connect optfa ses text-right mr-t-10 pd-5">
      <option id="MikhmonSession" value="<?= $session; ?>"><?= $hotspotname; ?></option>
      <?php
      $cfgPath = file_exists('./include/config.php') ? './include/config.php' : (file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : '');
      $allNavSessions = [];
      if (isset($data) && is_array($data)) {
        foreach ($data as $k => $v) {
          if ($k !== 'mikhmon' && !empty($k)) $allNavSessions[$k] = $k;
        }
      }
      if (!empty($cfgPath) && file_exists($cfgPath)) {
        foreach (file($cfgPath) as $line) {
          if (preg_match('/\$data\[[\'"]([^\'"]+)[\'"]\]/i', $line, $m)) {
            if ($m[1] !== 'mikhmon' && !empty($m[1])) $allNavSessions[$m[1]] = $m[1];
          }
        }
      }
      foreach ($allNavSessions as $sesname) {
        if ($sesname == $session) {
          echo '<option value="' . htmlspecialchars($sesname) . '">' . htmlspecialchars($sesname) . ' &#x2666;</option>';
        } else {
          echo '<option value="' . htmlspecialchars($sesname) . '">' . htmlspecialchars($sesname) . '</option>';
        }
      }
      ?>
    </select>
    <a title="Idle Timeout" style="<?= $didleto; ?>"><span style="width:70px;" class="pd-5 radius-3"><i class="fa fa-clock-o mr-1"></i>  <span class="mr-1" id="timer"></span></span></a>
    
    <!-- Mobile 3-Dots Kebab Trigger -->
    <a href="javascript:void(0)" id="topKebabBtn" class="top-kebab-btn" title="<?= $_menu ?? "Menu"; ?>"><i class="fa fa-ellipsis-v"></i></a>
  </div>

  <!-- Mobile Kebab Dropdown Menu -->
  <div id="topKebabDropdown" class="top-kebab-dropdown" style="display:none;">
    <div class="kebab-item-header">
      <span><i class="fa fa-sliders"></i> <?= !empty($session) ? htmlspecialchars($session) : 'Menu'; ?></span>
      <a href="javascript:void(0)" id="closeKebabBtn" class="close-kebab-btn">&times;</a>
    </div>

    <div class="kebab-section">
      <label><i class="fa fa-server"></i> <?= !empty($_choose_router) ? $_choose_router : (!empty($_routers) ? $_routers : 'Pilih Router / Sesi'); ?></label>
      <select class="form-control connect-mobile" onchange="if(typeof showMikhmonOverlay==='function')showMikhmonOverlay('Loading...', ''); notify('<?= $_connecting ?>'); connect(this.value);">
        <option value="<?= $session; ?>"><?= $hotspotname ?: $session; ?> (Aktif)</option>
        <?php
        foreach ($allNavSessions as $sesname) {
          if ($sesname != $session) {
            echo '<option value="' . htmlspecialchars($sesname) . '">' . htmlspecialchars($sesname) . '</option>';
          }
        }
        ?>
      </select>
    </div>

    <div class="kebab-section">
      <label><i class="fa fa-paint-brush"></i> <?= !empty($_choose_theme) ? $_choose_theme : (!empty($_theme) ? $_theme : 'Tema'); ?></label>
      <select class="form-control stheme-mobile" onchange="notify('<?= $_loading_theme ?>'); stheme(this.value);">
        <option value="">-- <?= !empty($_choose_theme) ? $_choose_theme : 'Pilih Tema'; ?> --</option>
        <?php for ($i = 0; $i < count($mtheme); $i++) {
          $selTheme = (isset($_SESSION['theme']) && $_SESSION['theme'] == $mtheme[$i]) ? 'selected' : '';
          echo '<option value="'.$cleanThemeUrl.$themeSep.'set-theme='.$mtheme[$i].'" '.$selTheme.'>'.ucfirst($mtheme[$i]).'</option>';
        } ?>
      </select>
    </div>

    <div class="kebab-section">
      <label><i class="fa fa-language"></i> <?= !empty($_choose_language) ? $_choose_language : (!empty($language) ? $language : 'Bahasa'); ?></label>
      <select class="form-control slang-mobile" onchange="notify('<?= $_loading ?>'); stheme(this.value);">
        <option value="">-- <?= !empty($_choose_language) ? $_choose_language : 'Pilih Bahasa'; ?> --</option>
        <?php foreach ($isocodelang as $code => $name): 
          $selLang = (isset($langid) && $langid == $code) ? 'selected' : '';
          echo '<option value="'.$cleanLangUrl.$langSep.'setlang=' . $code . '" '.$selLang.'>'. $name . '</option>'; 
        endforeach; ?>
      </select>
    </div>

    <div class="kebab-divider"></div>

    <a href="./?hotspot=logout&session=<?= $session; ?>" class="kebab-logout-btn">
      <i class="fa fa-sign-out"></i> <?= !empty($_logout) ? $_logout : 'Keluar'; ?>
    </a>
  </div>
</div>

<div id="sidenav" class="sidenav">
  <div class="menu text-center align-middle card-header" style="border-radius:0;"><h3><?= $identity; ?></h3></div>
  <a href="./?session=<?= $session; ?>" class="menu <?= $shome ?? ''; ?>"><i class="fa fa-dashboard"></i> <?= $_dashboard ?></a>
  <!--nodera-->
  <div class="dropdown-btn <?= ($sbilling ?? '') . ($sshop ?? '') . ($snoderapay ?? ''); ?>"><i class="fa fa-cubes"></i> NODERA
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $noderamenu ?? ''; ?>">
    <a href="./?hotspot=noderapay&session=<?= $session; ?>" class="<?= $snoderapay ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-qrcode"></i> NODERA Pay </a>
    <a href="./?hotspot=billing&session=<?= $session; ?>" class="<?= $sbilling ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-cloud"></i> <?= $_billing_cloud ?? 'NODERA Billing Cloud'; ?> </a>
    <a href="./?hotspot=shop&session=<?= $session; ?>" class="<?= $sshop ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-shopping-cart"></i> <?= $_shop_online ?? 'NODERA Store'; ?> </a>
  </div>

  <!--warung-->
  <div class="dropdown-btn <?= ($swarung ?? '') . ($swarung_add ?? '') . ($swarung_list ?? '') . ($swarung_topup ?? '') . ($swarung_prices ?? '') . ($swarung_settings ?? '') . ($swarung_reports ?? ''); ?>"><i class="fa fa-shopping-basket"></i> <?= $_warung ?? "Warung"; ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $warungmenu ?? ''; ?>">
    <a href="./?hotspot=warung&tab=add&session=<?= $session; ?>" class="<?= $swarung_add ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-user-plus"></i> <?= $_add_warung ?? "Tambah Warung"; ?> </a>
    <a href="./?hotspot=warung&tab=list&session=<?= $session; ?>" class="<?= $swarung_list ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-users"></i> <?= $_list_warung ?? "Daftar Warung"; ?> </a>
    <a href="./?hotspot=warung&tab=topup&session=<?= $session; ?>" class="<?= $swarung_topup ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-plus-circle"></i> <?= $_topup_warung ?? "Top Up Warung"; ?> </a>
    <a href="./?hotspot=warung&tab=prices&session=<?= $session; ?>" class="<?= $swarung_prices ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-tags"></i> <?= $_profile_cost_price ?? "Harga Modal Profil"; ?> </a>
    <a href="./?hotspot=warung&tab=settings&session=<?= $session; ?>" class="<?= $swarung_settings ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-cog"></i> <?= $_warung_settings ?? "Pengaturan Warung"; ?> </a>
    <a href="./?hotspot=warung&tab=reports&session=<?= $session; ?>" class="<?= $swarung_reports ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-bar-chart"></i> <?= $_selling_report ?? "Laporan Penjualan"; ?> </a>
  </div>
  <!--hotspot-->
  <div class="dropdown-btn <?= ($susers ?? '') . ($susersl ?? '') . ($sadduser ?? '') . ($sgenuser ?? '') . ($suserprofiles ?? '') . ($sadduserprof ?? '') . ($suserprof ?? '') . ($sactive ?? '') . ($shosts ?? '') . ($sipbind ?? '') . ($scookies ?? ''); ?>"><i class="fa fa-wifi"></i> Hotspot
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= ($umenu ?? '') . ($upmenu ?? '') . ($hamenu ?? '') . ($hmenu ?? '') . ($ibmenu ?? '') . ($cmenu ?? ''); ?>">
    <a href="./?hotspot=users&profile=all&session=<?= $session; ?>" class="menu <?= $susersl ?? ''; ?>"><i class="fa fa-list"></i> <?= $_user_list ?></a>
    <a href="./?hotspot-user=add&session=<?= $session; ?>" class="menu <?= $sadduser ?? ''; ?>"><i class="fa fa-user-plus"></i> <?= $_add_user ?></a>
    <a href="./?hotspot-user=generate&session=<?= $session; ?>" class="menu <?= $sgenuser ?? ''; ?>"><i class="fa fa-user-plus"></i> <?= $_generate ?></a>        
    <a href="./?hotspot=user-profiles&session=<?= $session; ?>" class="menu <?= $suserprofiles ?? ''; ?>"><i class="fa fa-pie-chart"></i> <?= $_user_profile_list ?></a>
    <a href="./?user-profile=add&session=<?= $session; ?>" class="menu <?= $sadduserprof ?? ''; ?>"><i class="fa fa-plus-square"></i> <?= $_add_user_profile ?></a>
    <a href="./?hotspot=active&session=<?= $session; ?>" class="menu <?= $sactive ?? ''; ?>"><i class="fa fa-wifi"></i> <?= $_hotspot_active ?></a>
    <a href="./?hotspot=hosts&session=<?= $session; ?>" class="menu <?= $shosts ?? ''; ?>"><i class="fa fa-laptop"></i> <?= $_hosts ?></a>
    <a href="./?hotspot=ipbinding&session=<?= $session; ?>" class="menu <?= $sipbind ?? ''; ?>"><i class="fa fa-address-book"></i> <?= $_ip_bindings ?></a>
    <a href="./?hotspot=cookies&session=<?= $session; ?>" class="menu <?= $scookies ?? ''; ?>"><i class="fa fa-hourglass"></i> <?= $_hotspot_cookies ?></a>
  </div>
  <!--quick print-->
  <div class="dropdown-btn <?= ($squick ?? '') . ($slistquick ?? ''); ?>"><i class="fa fa-print"></i> <?= $_quick_print ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $quickmenu ?? ''; ?>">
    <a href="./?hotspot=quick-print&session=<?= $session; ?>" class="<?= $squick ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-print "></i> <?= $_quick_print ?> </a>
    <a href="./?hotspot=list-quick-print&session=<?= $session; ?>" class="<?= $slistquick ?? ''; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-list "></i> <?= (!empty($_package) ? $_package : 'Paket'); ?> <?= $_quick_print ?> </a>
  </div>
  <!--vouchers-->
  <a href="./?hotspot=users-by-profile&session=<?= $session; ?>" class="menu <?= $susersbp ?? ''; ?>"> <i class="fa fa-ticket"></i> <?= $_vouchers ?> </a>
   <!--log-->
  <div class="dropdown-btn <?= $log ?? ''; ?>"><i class=" fa fa-align-justify"></i> <?= $_log ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $lmenu ?? ''; ?>">
    <a href="./?hotspot=log&session=<?= $session; ?>" class="<?= $slog ?? ''; ?>"> <i class="fa fa-wifi "></i> <?= $_hotspot_log ?> </a>
    <a href="./?report=userlog&idbl=<?= strtolower(date("M")) . date("Y"); ?>&session=<?= $session; ?>" class=" <?= $sulog ?? ''; ?>"> <i class="fa fa-users "></i> <?= $_user_log ?> </a>
  </div>
  <!--system-->
  <div class="dropdown-btn <?= $sysmenu ?? ''; ?>"><i class=" fa fa-gear"></i> <?= $_system ?>
    <i class="fa fa-caret-down"></i> &nbsp;
  </div>
  <div class="dropdown-container <?= $schmenu ?? ''; ?>">
    <a href="./?system=scheduler&session=<?= $session; ?>" class="<?= $ssch ?? ''; ?>"> <i class="fa fa-clock-o "></i> <?= $_system_scheduler ?> </a>
    <a href="./admin.php?id=reboot&session=<?= $session; ?>" class=""> <i class="fa fa-power-off "></i> <?= $_system_reboot ?> </a>            
    <a href="./admin.php?id=shutdown&session=<?= $session; ?>" class=""> <i class="fa fa-power-off "></i> <?= $_system_off ?> </a> 
  </div>
  <!--dhcp leases-->
  <a href="./?hotspot=dhcp-leases&session=<?= $session; ?>" class="menu <?= $slease ?? ''; ?>"><i class=" fa fa-sitemap"></i> <?= $_dhcp_leases ?></a>
  <!--traffic monitor-->
  <a href="./?interface=traffic-monitor&session=<?= $session; ?>" class="menu <?= $strafficmonitor ?? ''; ?>"><i class=" fa fa-area-chart"></i> <?= $_traffic_monitor ?></a>
  <!--report-->
  <a href="./?report=selling&idbl=<?= date("m") . date("Y"); ?>&session=<?= $session; ?>" class="menu <?= $sselling ?? ''; ?>"><i class="nav-icon fa fa-money"></i> <?= $_report ?></a>
  <!--settings-->
  <div class="dropdown-btn <?= ($ssett ?? '') . ($stelegram ?? '') . ($swhatsapp ?? '') . ($stselector ?? ''); ?>"><i class=" fa fa-gear"></i> <?= $_settings ?> 
    <i class="fa fa-caret-down"></i> &nbsp;
  </div>
  <div class="dropdown-container <?= $settmenu ?? ''; ?>">
    <a href="./admin.php?id=settings&session=<?= $session; ?>" class="menu "> <i class="fa fa-gear "></i> <?= $_session_settings ?> </a>
    <a href="./?hotspot=whatsapp&session=<?= $session; ?>" class="menu <?= $swhatsapp ?? ''; ?>"> <i class="fa fa-whatsapp"></i> <?= $_whatsapp_gateway ?? "WhatsApp Gateway"; ?> </a>
    <a href="./?hotspot=template-selector&session=<?= $session; ?>" class="menu <?= $tselector ?? ''; ?>"> <i class="fa fa-paint-brush "></i> <?= $_template_editor ?? 'Template Voucher' ?> </a>
    <a href="./?hotspot=store-template&session=<?= $session; ?>" class="menu <?= $stselector ?? ''; ?>"> <i class="fa fa-desktop "></i> <?= $_store_template ?? "Template Toko Online"; ?> </a>
    <a href="./?hotspot=telegram&session=<?= $session; ?>" class="menu <?= $stelegram ?? ''; ?>"> <i class="fa fa-paper-plane "></i> <?= $_telegram_bot ?? "Telegram Bot"; ?> </a>
    <a href="./admin.php?id=sessions" class="menu "> <i class="fa fa-gear "></i> <?= $_admin_settings ?> </a>
    <a href="./?hotspot=uplogo&session=<?= $session; ?>" class="menu <?= $uplogo ?? ''; ?>"> <i class="fa fa-upload "></i> <?= $_upload_logo ?> </a>
  </div>
  <!--about-->
  <a href="./?hotspot=about&session=<?= $session; ?>" class="menu <?= $sabout ?? ''; ?>"><i class="fa fa-info-circle"></i> <span><?= $_about ?></span></a>

</div>
</div>
<script>
$(document).ready(function(){
  $(".connect").change(function(){
    if (typeof showMikhmonOverlay === "function") {
      showMikhmonOverlay("Loading...", "");
    }
    notify("<?= $_connecting ?>");
    connect(this.value);
  });
  $(".stheme").change(function(){
    if (typeof showMikhmonOverlay === "function") {
      showMikhmonOverlay("Loading...", "");
    }
    notify("<?= $_loading_theme ?>");
    stheme(this.value);
  });
  $(".slang").change(function(){
    if (typeof showMikhmonOverlay === "function") {
      showMikhmonOverlay("Loading...", "");
    }
    notify("<?= $_loading ?>");
    stheme(this.value);
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./include/info.php');
} ?>

<div id="main">  
<div id="loading" class="lds-dual-ring"></div>
<div class="main-container" style="display:none">
