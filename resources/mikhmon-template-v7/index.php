<?php
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', 300);
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
// check url

ob_start("ob_gzhandler");


$url = $_SERVER['REQUEST_URI'];

// load session MikroTik

$session = $_GET['session'];

// license check
include_once __DIR__ . '/include/license.php';
if (function_exists('mikhmon_is_expired') && mikhmon_is_expired()) {
  @session_destroy();
  header("Location:./admin.php?id=login");
  exit;
}

// Check Remember Me cookie
if (!isset($_SESSION["mikhmon"]) && !empty($_COOKIE['mikhmon_remember'])) {
  @include_once('./include/config.php');
  @include_once('./include/readcfg.php');
  $decodedRemember = @base64_decode($_COOKIE['mikhmon_remember']);
  if ($decodedRemember && strpos($decodedRemember, '|') !== false) {
    list($rUser, $rHash) = explode('|', $decodedRemember, 2);
    $expectedHash = hash('sha256', ($useradm ?? '') . ':' . ($passadm ?? '') . ':mikhmon_nodera_auth');
    if (!empty($useradm) && $rUser === $useradm && hash_equals($expectedHash, $rHash)) {
      if (!(function_exists('mikhmon_is_expired') && mikhmon_is_expired()) && !(function_exists('mikhmon_is_suspended') && mikhmon_is_suspended())) {
        $_SESSION["mikhmon"] = $useradm;
      }
    }
  }
}

if (!isset($_SESSION["mikhmon"])) {
  $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
  header("Location: ./login.php" . $qs);
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

if (empty($session)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} else {
  $_SESSION["$session"] = $session;
  $setsession = $_SESSION["$session"];

  $_SESSION["connect"] = "";

// time zone
  date_default_timezone_set($_SESSION['timezone']);

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

// load config
  include('./include/config.php');
  include('./include/readcfg.php');

// theme  
  include('./include/theme.php');
  include('./settings/settheme.php');
  if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
  }

// routeros api
  $nonRouterPages = ['telegram', 'whatsapp', 'billing', 'shop', 'noderapay', 'qris', 'template-selector', 'store-template', 'uplogo'];
  $currentHotspot = $_GET['hotspot'] ?? '';
  $isNonRouterPage = in_array($currentHotspot, $nonRouterPages);

  include_once('./lib/routeros_api.class.php');
  include_once('./lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->timeout = 3;
  $API->attempts = 1;
  $API->delay = 0;

  if (!$isNonRouterPage) {
    $passwdhostDecrypted = function_exists('mikhmon_decrypt') ? mikhmon_decrypt($passwdhost) : mikhmon_decrypt($passwdhost);
    @$API->connect($iphost, $userhost, $passwdhostDecrypted);
  }

  if (isset($_SESSION[$session . '_identity']) && !empty($_SESSION[$session . '_identity'])) {
    $identity = $_SESSION[$session . '_identity'];
  } else {
    $getidentity = $API->comm("/system/identity/print");
    $identity = isset($getidentity[0]['name']) ? $getidentity[0]['name'] : 'MikroTik';
    $_SESSION[$session . '_identity'] = $identity;
  }
  

// get variable
  $hotspot = $_GET['hotspot'];
  $hotspotuser = $_GET['hotspot-user'];
  $userbyname = $_GET['hotspot-user'];
  $removeuseractive = $_GET['remove-user-active'];
  $removehost = $_GET['remove-host'];
  $removecookie = $_GET['remove-cookie'];
  $removeipbinding = $_GET['remove-ip-binding'];
  $removehotspotuser = $_GET['remove-hotspot-user'];
  $removehotspotusers = $_GET['remove-hotspot-users'];
  $removeuserprofile = $_GET['remove-user-profile'];
  $resethotspotuser = $_GET['reset-hotspot-user'];
  $removehotspotuserbycomment = $_GET['remove-hotspot-user-by-comment'];
  $removeexpiredhotspotuser = $_GET['remove-hotspot-user-expired'];
  $enablehotspotuser = $_GET['enable-hotspot-user'];
  $disablehotspotuser = $_GET['disable-hotspot-user'];
  $enableipbinding = $_GET['enable-ip-binding'];
  $disableipbinding = $_GET['disable-ip-binding'];
  $userprofile = $_GET['user-profile'];
  $userprofilebyname = $_GET['user-profile'];
  $sys = $_GET['system'];
  $enablesch = $_GET['enable-scheduler'];
  $disablesch = $_GET['disable-scheduler'];
  $removesch = $_GET['remove-scheduler'];
  $macbinding = $_GET['mac'];
  $ipbinding = $_GET['addr'];
  $ppp = $_GET['ppp'];
  $secretbyname = $_GET['secret'];
  $enablesecr = $_GET['enable-pppsecret'];
  $disablesecr = $_GET['disable-pppsecret'];
  $removesecr = $_GET['remove-pppsecret'];
  $removepprofile = $_GET['remove-pprofile'];
  $removepactive = $_GET['remove-pactive'];
  $srv = $_GET['srv'];
  $prof = $_GET['profile'];
  $comm = $_GET['comment'];
  $serveractive = $_GET['server'];
  $report = $_GET['report'];
  $removereport = $_GET['remove-report'];
  $minterface = $_GET['interface'];


  $pagehotspot = array('users','hosts','ipbinding','cookies','log','dhcp-leases');
  $pageppp = array('secrets','profiles','active',);
  $pagereport = array('userlog','selling');

  include_once('./include/headhtml.php');

  include_once('./include/menu.php');

  $disable_sci = '<script>
  document.getElementById("comment").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,.~".indexOf(chr) >= 0)
        return false;
};
</script>';


// logout
  if ($hotspot == "logout") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";

    if (isset($_COOKIE['mikhmon_remember'])) {
      @setcookie('mikhmon_remember', '', time() - 3600, '/');
      unset($_COOKIE['mikhmon_remember']);
    }
    session_destroy();
    echo "<script>sessionStorage.clear();</script>";
    echo "<script>window.location='./admin.php?id=login'</script>";
  }
// redirect to home
  elseif (substr(explode("=", $url)[0],-9) == "/?session") {

    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";
  }

// redirect to home
  elseif ($hotspot == "dashboard") {
    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";

  }

// hotspot log
  elseif ($hotspot == "log") {
    include_once('./hotspot/log.php');
  }

// hotspot log
  elseif ($report == "userlog") {
    include_once('./report/userlog.php');
  }

// billing
  elseif ($hotspot == "billing") {
    include_once('./include/billing.php');
  }

// shop
  elseif ($hotspot == "shop") {
    include_once('./include/shop.php');
  }

// telegram
  elseif ($hotspot == "telegram") {
    include_once('./include/telegram.php');
  }

// whatsapp
  elseif ($hotspot == "whatsapp") {
    include_once('./include/whatsapp.php');
  }

// warung
  elseif ($hotspot == "warung") {
    include_once('./include/warung.php');
  }

// noderapay / qris
  elseif ($hotspot == "noderapay" || $hotspot == "qris") {
    include_once('./include/noderapay.php');
  }

// sync schedulers & clean expired vouchers
  elseif ($hotspot == "sync-schedulers") {
    include_once('./hotspot/sync_schedulers.php');
  }

// about
  elseif ($hotspot == "about") {
    include_once('./include/about.php');
  }

// bad request
  elseif (substr($url, -1) == "=") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Bad request! redirect to Home......</b>";

    echo "<script>window.location='./'</script>";
  }

// hotspot add users
  elseif ($hotspot == "add-user") {
    $_SESSION['hua'] = "";
    include_once('./hotspot/adduser.php');
  }

// hotspot users
  elseif ($hotspot == "users" && $prof == "all") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by profile
  elseif ($hotspot == "users" && $prof != "") {
    $_SESSION['ubp'] = $prof;
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by comment
  elseif ($hotspot == "users" && $comm != "") {
    $_SESSION['ubc'] = $comm;
    $_SESSION['hua'] = "";
    $_SESSION['ubp'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot by profile
  elseif ($hotspot == "users-by-profile") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "active";
    include_once('./hotspot/userbyprofile.php');
  }
// export hotspot users
  elseif ($hotspot == "export-users") {
    include_once('./hotspot/exportusers.php');
  }

// quick print
  elseif ($hotspot == "quick-print") {
    include_once('./hotspot/quickprint.php');
  }

// quick print
elseif ($hotspot == "list-quick-print") {
  include_once('./hotspot/listquickprint.php');
}  

// add hotspot user
  elseif ($hotspotuser == "add") {
    include_once('./hotspot/adduser.php');
    echo $disable_sci;
  }

// add hotspot user
  elseif ($hotspotuser == "generate") {
    include_once('./hotspot/generateuser.php');
    echo $disable_sci;
  }

// hotspot users filter by name
  elseif (substr($hotspotuser, 0, 1) == "*") {
    $_SESSION['ubn'] = $hotspotuser;
    $_SESSION['hua'] = "";
    include_once('./hotspot/userbyname.php');
  } elseif ($hotspotuser != "") {
    $_SESSION['ubn'] = $hotspotuser;
    include_once('./hotspot/userbyname.php');
  }

// remove hotspot user
  elseif ($removehotspotuser != "" || $removehotspotusers != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuser.php');
  }

// remove hotspot user by comment
  elseif ($removehotspotuserbycomment != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuserbycomment.php');
  }

// remove expired hotspot user
elseif ($removeexpiredhotspotuser != "") {
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

  include_once('./process/removeexpiredhotspotuser.php');
}  

// reset hotspot user
  elseif ($resethotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/resethotspotuser.php');
  }

// enable hotspot user
  elseif ($enablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/enablehotspotuser.php');
  }

// disable hotspot user
  elseif ($disablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/disablehotspotuser.php');
  }

// user profile
  elseif ($hotspot == "user-profiles") {
    include_once('./hotspot/userprofile.php');
  }

// add  user profile
  elseif ($userprofile == "add") {
    include_once('./hotspot/adduserprofile.php');
  }

// User profile by name
  elseif (substr($userprofile, 0, 1) == "*") {
    include_once('./hotspot/userprofilebyname.php');
  } elseif ($userprofile != "") {
    include_once('./hotspot/userprofilebyname.php');
  }


// remove user profile
  elseif ($removeuserprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuserprofile.php');
  }

// hotspot active
  elseif ($hotspot == "active") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "hotspotactive";
    $_SESSION['ubc'] = "";
    include_once('./hotspot/hotspotactive.php');
  }

// dhcp leases
  elseif ($hotspot == "dhcp-leases") {
    include_once('./dhcp/dhcpleases.php');
  }

// traffic monitor
  elseif ($minterface == "traffic-monitor") {
  include_once('./traffic/trafficmonitor.php');
}

// hotspot hosts
  elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta") {
    include_once('./hotspot/hosts.php');
  }

// hotspot bindings
  elseif ($hotspot == "binding") {
    include_once('./hotspot/binding.php');
  }

// template selector (voucher)
  elseif ($hotspot == "template-selector") {
    include_once('./settings/templateselector.php');
  }

// store template selector (online shop / buy.php)
  elseif ($hotspot == "store-template" || $hotspot == "shop-template") {
    include_once('./settings/storetemplateselector.php');
  }

// template editor (redirected to selector)
  elseif ($hotspot == "template-editor") {
    echo "<script>window.location='./?hotspot=template-selector&session=" . $session . "'</script>";
  }

// upload logo
  elseif ($hotspot == "uplogo") {
    include_once('./settings/uplogo.php');
  }

// hotspot Cookies
  elseif ($hotspot == "cookies") {
    include_once('./hotspot/cookies.php');
  }

// remove hotspot Cookies
  elseif ($removecookie != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removecookie.php');
  }

// hotspot Ip Bindings
  elseif ($hotspot == "ipbinding") {
    include_once('./hotspot/ipbinding.php');
  }

// remove enable disable ipbinding
  elseif ($removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pipbinding.php');
  }


// remove user active
  elseif ($removeuseractive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuseractive.php');
  }

// remove host
  elseif ($removehost != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehost.php');
  }


// makebinding
  elseif ($macbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/makebinding.php');
  }

// selling
  elseif ($report == "selling") {
    include_once('./report/selling.php');
  }

// selling
elseif ($report == "resume-report") {
  include_once('./report/resumereport.php');
}

// selling
elseif ($report == "export") {
  include_once('./report/export.php');
}

// selling
  elseif ($removereport != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removereport.php');
  }

// ppp secret
  elseif ($ppp == "secrets") {
    include_once('./ppp/pppsecrets.php');
  }

// ppp addsecret
  elseif ($ppp == "addsecret") {
    include_once('./ppp/addsecret.php');
  }

// ppp secretbyname
  elseif ($secretbyname != "") {
    include_once('./ppp/secretbyname.php');
  }

// remove enable disable secret
  elseif ($removesecr != "" || $enablesecr != "" || $disablesecr != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/psecret.php');
  }


// ppp profile
  elseif ($ppp == "profiles") {
    include_once('./ppp/pppprofile.php');
  }

// add ppp profile
  elseif ($ppp == "add-profile") {
    include_once('./ppp/addpppprofile.php');
  }

// add ppp profile
elseif ($ppp == "edit-profile") {
  include_once('./ppp/profilebyname.php');
}
// remove enable disable profile
  elseif ($removepprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepprofile.php');
  }

// ppp active connection
  elseif ($ppp == "active") {
    include_once('./ppp/pppactive.php');
  }

// remove ppp active connection
  elseif ($removepactive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepactive.php');
  }

// sys scheduler
  elseif ($sys == "scheduler") {
    include_once('./system/scheduler.php');
  }
// remove enable disable scheduler
  elseif ($removesch != "" || $enablesch != "" || $disablesch != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pscheduler.php');
  }

  ?>

</div>
</div>
</div>
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= $theme; ?>.js"></script>
<script src="./js/mikhmon-ui.<?= $theme; ?>.min.js?v=3.20.2"></script>
<script src="./js/mikhmon.js?v=3.20.3&t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>

<?php
if ($hotspot == "dashboard" || substr(end(explode("/", $url)), 0, 8) == "?session") {
  echo '<script>
    if (typeof window.registerMikhmonInitialLoad === "function") {
      window.registerMikhmonInitialLoad();
    }
    $("#r_3").load("./dashboard/aload.php?session=' . $session . '&load=logs #r_3", function() {
      if (typeof window.completeMikhmonInitialLoad === "function") {
        window.completeMikhmonInitialLoad();
      }
    });  
    var interval1 = "' . ($areload * 1000) . '";
    var dashboard = setInterval(function() {
      $.get("./dashboard/aload.php?session=' . $session . '&load=all", function(data) {
        if (!data || data.trim() === "") return;
        var $h = $("<div>").html(data);
        var r1 = $h.find("#r_1");
        var r2 = $h.find("#r_2");
        var r3 = $h.find("#r_3");
        if (r1.length && r1.children().length > 0) {
          $("#r_1").replaceWith(r1);
          if (typeof initMikhmonLiveClock === "function") initMikhmonLiveClock();
        }
        if (r2.length && r2.children().length > 0) {
          $("#r_2").replaceWith(r2);
        }
        if (r3.length && r3.children().length > 0) {
          $("#r_3").replaceWith(r3);
        }
      });
    }, interval1);

';
if ($livereport == "enable" || $livereport == "") {
  echo '
  function updateLiveReport() {
    $.get("./report/livereport.php?session=' . $session . '", function(data) {
      var newR4 = $(data).filter("#r_4");
      if (!newR4.length) newR4 = $(data).find("#r_4");
      if (newR4.length) {
        $("#r_4").replaceWith(newR4);
      }
    });
  }

  // Load immediately on page ready
  updateLiveReport();

  var interval2 = "65432";
  var livereport = setInterval(function() {
    updateLiveReport();
  }, interval2);
';}
  echo ' 
  function cancelPage(){
    window.stop();
    clearInterval(dashboard);';
    if ($livereport == "enable" || $livereport == "") {
    echo '
    clearInterval(livereport);';
    }
  echo '
    }
</script>';

} elseif ($hotspot == "active" && $serveractive != "") {
  echo '<script>
  $(document).ready(function(){
    var interval = "' . ($areload * 1000) . '";
    setInterval(function() {
    $("#reloadHotspotActive").load("./hotspot/hotspotactive.php?server=' . $serveractive . '&session=' . $session . '"); }, interval);})
</script>
';
} elseif ($hotspot == "active" && $serveractive == "") {
  echo '<script>
  $(document).ready(function(){
    var interval = "' . ($areload * 1000) . '";
    setInterval(function() {
    $("#reloadHotspotActive").load("./hotspot/hotspotactive.php?session=' . $session . '"); }, interval);})
</script>
';
} elseif ($userprofile == "add" || substr($userprofile, 0, 1) == "*" || $userprofile != "") {
  echo "<script>
  //enable disable input on ready
$(document).ready(function(){
    var exp = document.getElementById('expmode').value;
    var val = document.getElementById('validity').style;
    var vali = document.getElementById('validi');
    if (exp === 'rem' || exp === 'remc') {
      val.display= 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else if (exp === 'ntf' || exp === 'ntfc') {
      val.display = 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else {
      val.display = 'none';
      vali.type = 'hidden';
    }
});
</script>";

} elseif (in_array($hotspot, $pagehotspot) || in_array($ppp, $pageppp) || in_array($report, $pagereport) || $sys == "scheduler") {
echo '
<script>
$(document).ready(function(){
  makeAllSortable();
  $("#filterTable").on("input keyup paste search change", function() {
    var value = $(this).val().toLowerCase().trim();
    $("#dataTable tbody tr, #tFilter tbody tr").filter(function() {
      if (value === "") {
        $(this).show();
      } else {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
      }
    });
  });
});

</script>
';
}
}
?>
</body>
</html>

