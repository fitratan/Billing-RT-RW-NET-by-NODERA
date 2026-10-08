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

ob_start("ob_gzhandler");

// check url
$url = $_SERVER['REQUEST_URI'];

// load session MikroTik
$session = $_GET['session'];
$id = $_GET['id'];
$c = $_GET['c'];
$router = $_GET['router'];
$logo = $_GET['logo'];

$ids = array(
  "editor",
  "uplogo",
  "settings",
);

// license
include_once('./include/license.php');

if (function_exists('mikhmon_is_expired') && mikhmon_is_expired() && !mikhmon_is_desktop_mode()) {
    if ($id !== "login") {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
        header("Location:./admin.php?id=login");
        echo "<script>window.location='./admin.php?id=login'</script>";
        exit;
    }
}

if ((isset($_POST['action']) && $_POST['action'] === 'ajax_activate_desktop_license') || (isset($_GET['action']) && $_GET['action'] === 'ajax_activate_desktop_license')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $licenseKey = $_POST['license_key'] ?? ($_GET['license_key'] ?? '');
    echo json_encode(mikhmon_activate_desktop_license($licenseKey));
    exit;
}

if ((isset($_POST['action']) && $_POST['action'] === 'ajax_activate_trial_license') || (isset($_GET['action']) && $_GET['action'] === 'ajax_activate_trial_license')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(mikhmon_activate_trial_license());
    exit;
}

if ((isset($_POST['action']) && $_POST['action'] === 'ajax_save_activated_license') || (isset($_GET['action']) && $_GET['action'] === 'ajax_save_activated_license')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $raw = $_POST['data'] ?? ($_GET['data'] ?? []);
    if (is_string($raw)) {
        $raw = json_decode($raw, true) ?: [];
    }
    echo json_encode(mikhmon_save_desktop_license_data($raw));
    exit;
}

if ((isset($_POST['action']) && $_POST['action'] === 'ajax_revoke_local_license') || (isset($_GET['action']) && $_GET['action'] === 'ajax_revoke_local_license')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    mikhmon_reset_local_license();
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
    echo json_encode(['success' => true, 'message' => 'Local license revoked.']);
    exit;
}

if ((isset($_POST['action']) && $_POST['action'] === 'ajax_activate_addon') || (isset($_GET['action']) && $_GET['action'] === 'ajax_activate_addon')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    include_once __DIR__ . '/include/warung_helper.php';
    header('Content-Type: application/json; charset=utf-8');
    $subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(__DIR__);
    if (empty($subdomain) || in_array($subdomain, ['public', 'html', 'include', 'mikhmon-template-v7', 'mikhmon-template'])) {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $hostParts = explode('.', $_SERVER['HTTP_HOST']);
            $subdomain = $hostParts[0] ?? '';
        }
    }
    echo json_encode(warung_activate_addon($subdomain));
    exit;
}

// lang
include_once(__DIR__ . '/lang/isocodelang.php');
if (!empty($_GET['setlang']) && !empty($isocodelang[$_GET['setlang']])) {
  $langid = $_GET['setlang'];
  $_SESSION['lang'] = $langid;
  $_SESSION['m_lang'] = $langid;
  @setcookie('mikhmon_lang', $langid, time() + 31536000, '/');
  @file_put_contents(__DIR__ . '/include/lang.php', '<?php $langid="' . addslashes($langid) . '";?>');
} else {
  $langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
  if (empty($langid) || empty($isocodelang[$langid])) {
    if (file_exists(__DIR__ . '/include/lang.php')) {
      include(__DIR__ . '/include/lang.php');
    }
    if (empty($langid) || empty($isocodelang[$langid])) {
      $langid = 'id';
    }
  }
}
$_SESSION['lang'] = $langid;
$_SESSION['m_lang'] = $langid;
if (!file_exists(__DIR__ . '/lang/'.$langid.'.php')) {
  $langid = 'id';
}
include_once(__DIR__ . '/lang/'.$langid.'.php');

// quick bt
include('./include/quickbt.php');

// theme
include('./include/theme.php');
include('./settings/settheme.php');
include('./settings/setlang.php');
if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
}


// load config
include_once('./include/headhtml.php');
include('./include/config.php');
include('./include/readcfg.php');

// Check Remember Me cookie
$isExplicitLogin = (isset($id) && $id === "login") || (isset($_GET['id']) && $_GET['id'] === "login");
if (!isset($_SESSION["mikhmon"]) && !empty($_COOKIE['mikhmon_remember']) && !$isExplicitLogin) {
  $decodedRemember = @base64_decode($_COOKIE['mikhmon_remember']);
  if ($decodedRemember && strpos($decodedRemember, '|') !== false) {
    list($rUser, $rHash) = explode('|', $decodedRemember, 2);
    $expectedHash = hash('sha256', ($useradm ?? '') . ':' . ($passadm ?? '') . ':mikhmon_nodera_auth');
    if (!empty($useradm) && $rUser === $useradm && hash_equals($expectedHash, $rHash)) {
      if (!(function_exists('mikhmon_is_expired') && mikhmon_is_expired()) && !(function_exists('mikhmon_is_suspended') && mikhmon_is_suspended())) {
        $_SESSION["mikhmon"] = $useradm;
        if (empty($id)) {
          header("Location:./admin.php?id=sessions");
          echo "<script>window.location='./admin.php?id=sessions'</script>";
          exit;
        }
      }
    }
  }
}

include_once('./lib/routeros_api.class.php');
include_once('./lib/formatbytesbites.php');
?>
    
<?php
if ($id == "login" || (empty($id) && !isset($_SESSION["mikhmon"]))) {

  // Brute force rate limiting check
  $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
  if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
  }
  $throttleFile = __DIR__ . '/include/login_throttle.json';

  if (!function_exists('mikhmon_check_login_throttle')) {
    function mikhmon_check_login_throttle($ip, $file) {
      if (!file_exists($file)) return ['locked' => false];
      $data = @json_decode(@file_get_contents($file), true) ?: [];
      $now = time();
      if (isset($data[$ip])) {
        $item = $data[$ip];
        if (!empty($item['locked_until']) && $item['locked_until'] > $now) {
          $remMinutes = max(1, ceil(($item['locked_until'] - $now) / 60));
          return ['locked' => true, 'remaining_minutes' => $remMinutes];
        }
        if (!empty($item['last_attempt']) && ($now - $item['last_attempt']) > 300 && empty($item['locked_until'])) {
          unset($data[$ip]);
          @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
        }
      }
      return ['locked' => false];
    }
  }

  if (!function_exists('mikhmon_record_failed_login')) {
    function mikhmon_record_failed_login($ip, $file) {
      $data = file_exists($file) ? (@json_decode(@file_get_contents($file), true) ?: []) : [];
      $now = time();
      foreach ($data as $k => $v) {
        if (($now - ($v['last_attempt'] ?? 0)) > 86400) {
          unset($data[$k]);
        }
      }
      $item = $data[$ip] ?? ['attempts' => 0, 'last_attempt' => 0];
      if (($now - ($item['last_attempt'] ?? 0)) > 300) {
        $item['attempts'] = 0;
      }
      $item['attempts'] = ($item['attempts'] ?? 0) + 1;
      $item['last_attempt'] = $now;
      if ($item['attempts'] >= 5) {
        $item['locked_until'] = $now + 900;
      }
      $data[$ip] = $item;
      @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
    }
  }

  if (!function_exists('mikhmon_clear_failed_login')) {
    function mikhmon_clear_failed_login($ip, $file) {
      if (!file_exists($file)) return;
      $data = @json_decode(@file_get_contents($file), true) ?: [];
      if (isset($data[$ip])) {
        unset($data[$ip]);
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
      }
    }
  }

  $throttleStatus = mikhmon_check_login_throttle($clientIp, $throttleFile);

  if (isset($_POST['login'])) {
    if ($throttleStatus['locked']) {
      $error = '<div style="width: 100%; padding:8px 5px; border-radius:5px;" class="bg-danger text-center"><i class="fa fa-ban"></i> ' . ($_too_many_failed_logins ?? 'Terlalu banyak percobaan login gagal!') . '<br>' . ($_account_locked_temporary ?? 'Akun terkunci sementara demi keamanan.') . '<br>' . ($_please_try_again_in ?? 'Silakan coba kembali dalam') . ' ' . $throttleStatus['remaining_minutes'] . ' ' . ($_minutes ?? 'menit.') . '</div>';
    } elseif (function_exists('mikhmon_is_suspended') && mikhmon_is_suspended()) {
      $error = '<div style="width: 100%; padding:8px 5px; border-radius:5px;" class="bg-danger text-center"><i class="fa fa-ban"></i> ' . ($_access_denied ?? 'Akses Ditolak !') . '<br>' . ($_service_suspended ?? 'Layanan MIKHMON ini sedang ditangguhkan.') . '<br>' . ($_contact_support_at ?? 'Silakan hubungi bantuan di panel.dgtlnetsolution.com.') . '</div>';
    } elseif (function_exists('mikhmon_is_expired') && mikhmon_is_expired()) {
      $error = '<div style="width: 100%; padding:8px 5px; border-radius:5px;" class="bg-danger text-center"><i class="fa fa-ban"></i> ' . ($_subscription_expired ?? 'Masa Aktif Berakhir !') . '<br>' . ($_subscription_expired_desc ?? 'Masa aktif langganan telah berakhir pada') . ' ' . htmlspecialchars(mikhmon_expiry_text()) . '.<br>' . ($_please_renew_at ?? 'Silakan lakukan perpanjangan lisensi di <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" style="color:#ffffff; text-decoration:underline; font-weight:bold;">Portal Cloud NODERA</a> atau WhatsApp CS (<a href="https://wa.me/6285155173547" target="_blank" style="color:#ffffff; text-decoration:underline; font-weight:bold;">085155173547</a>).') . '</div>';
    } else {
      $user = $_POST['user'];
      $pass = $_POST['pass'];
      $passadmDecrypted = function_exists('mikhmon_decrypt') ? mikhmon_decrypt($passadm) : $passadm;
      if ($user == $useradm && $pass == $passadmDecrypted) {
        mikhmon_clear_failed_login($clientIp, $throttleFile);
        $_SESSION["mikhmon"] = $user;
        if (!empty($_POST['remember'])) {
          $rToken = base64_encode($useradm . '|' . hash('sha256', $useradm . ':' . $passadm . ':mikhmon_nodera_auth'));
          @setcookie('mikhmon_remember', $rToken, time() + (86400 * 90), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        } else {
          @setcookie('mikhmon_remember', '', time() - 3600, '/');
          unset($_COOKIE['mikhmon_remember']);
        }
        echo "<script>window.location='./admin.php?id=sessions'</script>";
      } else {
        mikhmon_record_failed_login($clientIp, $throttleFile);
        $updatedThrottle = mikhmon_check_login_throttle($clientIp, $throttleFile);
        if ($updatedThrottle['locked']) {
          $error = '<div style="width: 100%; padding:8px 5px; border-radius:5px;" class="bg-danger text-center"><i class="fa fa-ban"></i> ' . ($_too_many_failed_logins ?? 'Terlalu banyak percobaan login gagal!') . '<br>' . ($_account_locked_temporary ?? 'Akun terkunci sementara demi keamanan.') . '<br>' . ($_please_try_again_in ?? 'Silakan coba kembali dalam') . ' ' . $updatedThrottle['remaining_minutes'] . ' ' . ($_minutes ?? 'menit.') . '</div>';
        } else {
          $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger text-center"><i class="fa fa-ban"></i> ' . (!empty($_alert) ? $_alert : 'Peringatan !') . '<br>' . (!empty($_username_or_password_incorrect) ? $_username_or_password_incorrect : 'Username atau password salah.') . '</div>';
        }
      }
    }
  }

  include_once('./include/login.php');
} elseif (function_exists('mikhmon_is_expired') && mikhmon_is_expired()) {
  @session_destroy();
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif (!isset($_SESSION["mikhmon"])) {
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif (empty($id)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";

} elseif ($id == "sessions") {
  $_SESSION["connect"] = "";
  include_once('./include/menu.php');
  include_once('./settings/sessions.php');
  /*echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';*/
} elseif ($id == "settings" && !empty($session) || $id == "settings" && !empty($router)) {
  include_once('./include/menu.php');
  include_once('./settings/settings.php');
  echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';
} elseif ($id == "connect"  && !empty($session)) {
  @ini_set('memory_limit', '512M');
@ini_set("max_execution_time",5);  
  include_once('./include/menu.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $passwdhostDecrypted = function_exists('mikhmon_decrypt') ? mikhmon_decrypt($passwdhost) : mikhmon_decrypt($passwdhost);
  if ($API->connect($iphost, $userhost, $passwdhostDecrypted)){
    $_SESSION["connect"] = "<b class='text-green'>Connected</b>";
    unset($_SESSION['routerboard']);
    unset($_SESSION[$session . '_routerboard']);
    echo "<script>window.location='./?session=" . $session . "'</script>";
  } else {
    $_SESSION["connect"] = "<b class='text-red'>Not Connected</b>";
    $notConnMsg = ($_mikhmon_not_connected ?? 'Mikhmon tidak terhubung!') . "\n" . ($_check_ip_user_port ?? 'Silakan periksa kembali IP, User, Password dan port API harus enable.') . "\n" . ($_vpn_check_notice ?? 'Jika menggunakan koneksi VPN, pastikan VPN tersebut terkoneksi.');
    echo "<script>if(typeof hideMikhmonOverlay==='function')hideMikhmonOverlay(); alert(" . json_encode($notConnMsg) . ")</script>";
    if($c == "settings"){
      echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
    }else{
      echo "<script>window.location='./admin.php?id=sessions'</script>";
    }
  }
} elseif ($id == "uplogo"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/uplogo.php');
} elseif ($id == "reboot"  && !empty($session)) {
  include_once('./process/reboot.php');
} elseif ($id == "shutdown"  && !empty($session)) {
  include_once('./process/shutdown.php');
} elseif ($id == "remove-session" && $session != "") {
  include_once('./include/menu.php');
  $configFile = './include/config.php';
  $data = [];
  if (file_exists($configFile)) {
    @include $configFile;
  }
  if (!isset($data['mikhmon']) || !is_array($data['mikhmon'])) {
    $data['mikhmon'] = array('1' => 'mikhmon<|<nodera', '2' => 'mikhmon>|>pqCWnaOT');
  }
  if (isset($data[$session])) {
    unset($data[$session]);
  }
  $cfgOut = "<?php \nif(substr(\$_SERVER[\"REQUEST_URI\"], -10) == \"config.php\"){header(\"Location:./\");}; \n";
  foreach ($data as $dKey => $dVal) {
    if (is_array($dVal)) {
      $cfgOut .= '$data[\'' . addslashes((string)$dKey) . '\'] = array(';
      $items = [];
      foreach ($dVal as $k => $v) {
        $items[] = '\'' . addslashes((string)$k) . '\' => \'' . addslashes((string)$v) . '\'';
      }
      $cfgOut .= implode(', ', $items) . ");\n";
    }
  }
  @file_put_contents($configFile, $cfgOut);

  $locConfigFile = './include/location_config.php';
  if (file_exists($locConfigFile)) {
    $location_data = ['primary' => '', 'locations' => []];
    include $locConfigFile;
    if (isset($location_data['locations'][$session])) {
      unset($location_data['locations'][$session]);
    }
    if (($location_data['primary'] ?? '') === $session) {
      $location_data['primary'] = '';
    }
    $locOut = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"location_config.php\"){header(\"Location:./\");};\n\$location_data = " . var_export($location_data, true) . ";\n";
    @file_put_contents($locConfigFile, $locOut);
  }

  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif ($id == "billing") {
  include_once('./include/menu.php');
  include_once('./include/billing.php');
} elseif ($id == "shop") {
  include_once('./include/menu.php');
  include_once('./include/shop.php');
} elseif ($id == "telegram") {
  include_once('./include/menu.php');
  include_once('./include/telegram.php');
} elseif ($id == "whatsapp") {
  include_once('./include/menu.php');
  include_once('./include/whatsapp.php');
} elseif ($id == "noderapay" || $id == "qris") {
  include_once('./include/menu.php');
  include_once('./include/noderapay.php');
} elseif ($id == "warung" || $id == "agent") {
  include_once('./include/menu.php');
  include_once('./include/warung.php');
} elseif ($id == "update") {
  if (!empty($_GET['action']) || !empty($_POST['action'])) {
    include_once('./include/update.php');
    exit;
  }
  include_once('./include/menu.php');
  include_once('./include/update.php');
} elseif ($id == "about") {
  include_once('./include/menu.php');
  include_once('./include/about.php');
} elseif ($id == "logout") {
  include_once('./include/menu.php');
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";
  if (isset($_COOKIE['mikhmon_remember'])) {
    @setcookie('mikhmon_remember', '', time() - 3600, '/');
    unset($_COOKIE['mikhmon_remember']);
  }
  session_destroy();
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif ($id == "remove-logo" && $logo != ""  && !empty($session)) {
  include_once('./include/menu.php');
  $logopath = "./img/";
  $remlogo = $logopath . $logo;
  unlink("$remlogo");
  echo "<script>window.location='./admin.php?id=uplogo&session=" . $session . "'</script>";
} elseif ($id == "template-selector" && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/templateselector.php');
} elseif ($id == "store-template" || $id == "shop-template") {
  include_once('./include/menu.php');
  include_once('./settings/storetemplateselector.php');
} elseif ($id == "editor"  && !empty($session)) {
  echo "<script>window.location='./?hotspot=template-selector&session=" . $session . "'</script>";
} elseif (empty($id)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif(in_array($id, $ids) && empty($session)){
	echo "<script>window.location='./admin.php?id=sessions'</script>";
}
?>
<script src="js/mikhmon-ui.<?= $theme; ?>.min.js?v=3.20.2"></script>
<script src="js/mikhmon.js?v=3.20.3&t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>
<?php include('./include/info.php'); ?>
</body>
</html>

