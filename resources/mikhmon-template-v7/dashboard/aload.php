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
  $load = $_GET['load'];

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
$_SESSION['m_lang'] = $langid;
if (!file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    $langid = 'id';
}
include_once __DIR__ . '/../lang/' . $langid . '.php';

// load config
  include('../include/config.php');
  include('../include/readcfg.php');

// routeros api
  include_once('../lib/routeros_api.class.php');
  include_once('../lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;



  if ($load == "sysresource") {

    $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

// get MikroTik system clock
    $getclock = $API->comm("/system/clock/print", array(".proplist" => "time,date,time-zone-name"));
    $clock = isset($getclock[0]) ? $getclock[0] : array();
    $timezone = isset($getclock[0]['time-zone-name']) ? $getclock[0]['time-zone-name'] : 'UTC';
    date_default_timezone_set($timezone);

// get system resource MikroTik
    $getresource = $API->comm("/system/resource/print", array(".proplist" => "uptime,board-name,version,cpu-load,free-memory,free-hdd-space,architecture-name"));
    $resource = isset($getresource[0]) ? $getresource[0] : array();

// get routeboard info (cached per router session)
    unset($_SESSION['routerboard']);
    if (isset($_SESSION[$session . '_routerboard']) && is_array($_SESSION[$session . '_routerboard'])) {
      $routerboard = $_SESSION[$session . '_routerboard'];
    } else {
      $getrouterboard = $API->comm("/system/routerboard/print");
      $routerboard = (isset($getrouterboard[0]) && is_array($getrouterboard[0])) ? $getrouterboard[0] : array();
      $_SESSION[$session . '_routerboard'] = $routerboard;
    }
    session_write_close();
    ?>
    
    <div id="r_1" class="row">
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-clock-o"></i></div>
              <div class="box-group-area">
                <span>
                    <b><?= $_time ?></b> : <span class="live-clock-time" data-date="<?= htmlspecialchars($clock['date'] ?? ''); ?>" data-time="<?= htmlspecialchars($clock['time'] ?? ''); ?>" style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($clock['time'] ?? ''); ?></span><br/>
                    <b><?= $_date ?></b> : <span class="live-clock-date" style="font-size: 12.5px;"><?= ucfirst(htmlspecialchars($clock['date'] ?? '')); ?></span><br/>
                    <b><?= $_uptime ?></b> : <?= formatDTM($resource['uptime']); ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-info-circle"></i></div>
              <div class="box-group-area">
                <span>
                    <b><?= $_board_name ?></b> : <?= htmlspecialchars($resource['board-name'] ?? '') ?><br/>
                    <b><?= !empty($_architecture) ? $_architecture : 'Arsitektur' ?></b> : <?= htmlspecialchars($resource['architecture-name'] ?? '-') ?><br/>
                    <b>Router OS</b> : <?= htmlspecialchars($resource['version'] ?? '') ?>
                </span>
              </div>
            </div>
          </div>
        </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-server"></i></div>
              <div class="box-group-area">
                <span>
                    <b><?= $_cpu_load ?></b> : <?= htmlspecialchars($resource['cpu-load'] ?? '0') ?>%<br/>
                    <b><?= $_free_memory ?></b> : <?= formatBytes($resource['free-memory'], 2) ?><br/>
                    <b><?= $_free_hdd ?></b> : <?= formatBytes($resource['free-hdd-space'], 2) ?>
                </span>
                </div>
              </div>
            </div>
          </div> 
      </div>

<?php 
} else if ($load == "hotspot") {

  session_write_close();
  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  // 1. Scan and notify expired vouchers to Telegram
  include_once __DIR__ . '/../include/telegram_helper.php';
  if (function_exists('mikhmon_scan_and_notify_expired_vouchers')) {
    mikhmon_scan_and_notify_expired_vouchers($API, $session, $identity ?? ($session ?? ''));
  }

  $acFile = '../include/autoclean_config.php';
  $autoclean_data = [];
  if (file_exists($acFile)) {
    @include $acFile;
  }
  if (empty($autoclean_data[$session]) || $autoclean_data[$session] !== 'disable') {
    $getExp = $API->comm("/ip/hotspot/user/print", array("?limit-uptime" => "1s", ".proplist" => ".id"));
    if (!empty($getExp) && is_array($getExp)) {
      $expIds = [];
      foreach ($getExp as $eu) {
        if (!empty($eu['.id'])) $expIds[] = $eu['.id'];
      }
      if (!empty($expIds)) {
        foreach (array_chunk($expIds, 50) as $echunk) {
          $API->comm("/ip/hotspot/user/remove", array(".id" => implode(",", $echunk)));
        }
      }
    }
  }

// get & counting hotspot users
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if (is_array($countallusers)) {
    $countallusers = isset($countallusers[0]['ret']) ? $countallusers[0]['ret'] : (isset($countallusers['ret']) ? $countallusers['ret'] : count($countallusers));
  }
  $countallusers = is_numeric($countallusers) ? (int)$countallusers : 0;
  $uunit = ($countallusers > 1) ? "items" : "item";

// get & counting hotspot active
  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if (is_array($counthotspotactive)) {
    $counthotspotactive = isset($counthotspotactive[0]['ret']) ? $counthotspotactive[0]['ret'] : (isset($counthotspotactive['ret']) ? $counthotspotactive['ret'] : count($counthotspotactive));
  }
  $counthotspotactive = is_numeric($counthotspotactive) ? (int)$counthotspotactive : 0;
  $hunit = ($counthotspotactive > 1) ? "items" : "item";

  ?>
    
            <div id="r_2" class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a href="./?hotspot=active&session=<?= $session; ?>">
                          <h1><?= $counthotspotactive; ?> <span style="font-size: 14px;"><?= $hunit; ?></span></h1>
                          <div><i class="fa fa-laptop"></i> <?= $_hotspot_active ?></div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-green bmh-75">
                        <a href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                          <h1><?= $countallusers; ?> <span style="font-size: 14px;"><?= $uunit; ?></span></h1>
                          <div><i class="fa fa-users"></i> <?= $_hotspot_users ?></div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-yellow bmh-75">
                        <a href="./?hotspot-user=add&session=<?= $session; ?>">
                          <h1><i class="fa fa-user-plus"></i> <span style="font-size: 14px;"><?= $_add ?></span></h1>
                          <div><i class="fa fa-user-plus"></i> <?= $_hotspot_users ?></div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-red bmh-75">
                        <a href="./?hotspot-user=generate&session=<?= $session; ?>">
                          <h1><i class="fa fa-user-plus"></i> <span style="font-size: 14px;"><?= $_generate ?></span></h1>
                          <div><i class="fa fa-user-plus"></i> <?= $_hotspot_users ?></div>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
            </div>

<?php 
} else if ($load == "logs") {

  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  // move hotspot log to disk (checked once per session)
  if (!isset($_SESSION['logging_checked_' . $session])) {
    $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
    $logging = $getlogging[0];
    if ($logging['prefix'] != "->") {
      $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
    }
    $_SESSION['logging_checked_' . $session] = true;
  }
  session_write_close();
  
  // get hotspot log
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ".proplist" => ".id,time,message"));
  $log = array_reverse($getlog);
  //$THotspotLog = count($getlog);

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
  }



  ?>
  
              <div id="r_3" class="row">
              <div class="card">
                <div class="card-header">
                  <h3><a href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                    <div class="card-body">
                      <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                        <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                          <thead>
                            <tr>
                            <th><?= $_time .$THotspotLog; ?></th>
                            <th><?= $_users ?> (IP)</th>
                            <th><?= $_messages ?></th>
                            </tr>
                          </thead>
                          <tbody>
                      
  <?php


  for ($i = 0; $i < 20; $i++) {
    $mess = explode(":", $log[$i]['message']);
    $time = $log[$i]['time'];
    echo "<tr>";
    if (substr($log[$i]['message'], 0, 2) == "->") {
      echo "<td>" . $time . "</td>";
    //echo substr($mess[1], 0,2);
      echo "<td>";
      if (count($mess) > 6) {
        echo $mess[1] . ":" . $mess[2] . ":" . $mess[3] . ":" . $mess[4] . ":" . $mess[5] . ":" . $mess[6];
      } else {
        echo $mess[1];
      }
      echo "</td>";
      echo "<td>";
      if (count($mess) > 6) {
        echo str_replace("trying to", "", $mess[7] . " " . $mess[8] . " " . $mess[9] . " " . $mess[10]);
      } else {
        echo str_replace("trying to", "", $mess[2] . " " . $mess[3] . " " . $mess[4] . " " . $mess[5]);
      }
      echo "</td>";
    } else {
    }
    echo "</tr>";
  }
  ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                </div>

<?php 
} else if ($load == "all") {

  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  // Auto-scan and notify expired vouchers to Telegram
  include_once __DIR__ . '/../include/telegram_helper.php';
  if (function_exists('mikhmon_scan_and_notify_expired_vouchers')) {
    mikhmon_scan_and_notify_expired_vouchers($API, $session, $identity ?? ($session ?? ''));
  }

  // 1. Clock & Resource
  $getclock = $API->comm("/system/clock/print", array(".proplist" => "time,date,time-zone-name"));
  $clock = isset($getclock[0]) ? $getclock[0] : array();
  $timezone = isset($clock['time-zone-name']) ? $clock['time-zone-name'] : 'UTC';
  date_default_timezone_set($timezone);

  $getresource = $API->comm("/system/resource/print", array(".proplist" => "uptime,board-name,version,cpu-load,free-memory,free-hdd-space,architecture-name"));
  $resource = isset($getresource[0]) ? $getresource[0] : array();

  unset($_SESSION['routerboard']);
  if (isset($_SESSION[$session . '_routerboard']) && is_array($_SESSION[$session . '_routerboard'])) {
    $routerboard = $_SESSION[$session . '_routerboard'];
  } else {
    $getrouterboard = $API->comm("/system/routerboard/print");
    $routerboard = (isset($getrouterboard[0]) && is_array($getrouterboard[0])) ? $getrouterboard[0] : array();
    $_SESSION[$session . '_routerboard'] = $routerboard;
  }

  // 2. Hotspot User Counts
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if (is_array($countallusers)) {
    $countallusers = isset($countallusers[0]['ret']) ? $countallusers[0]['ret'] : (isset($countallusers['ret']) ? $countallusers['ret'] : count($countallusers));
  }
  $countallusers = is_numeric($countallusers) ? (int)$countallusers : 0;
  $uunit = ($countallusers > 1) ? "items" : "item";

  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if (is_array($counthotspotactive)) {
    $counthotspotactive = isset($counthotspotactive[0]['ret']) ? $counthotspotactive[0]['ret'] : (isset($counthotspotactive['ret']) ? $counthotspotactive['ret'] : count($counthotspotactive));
  }
  $counthotspotactive = is_numeric($counthotspotactive) ? (int)$counthotspotactive : 0;
  $hunit = ($counthotspotactive > 1) ? "items" : "item";

  // 3. Hotspot Logging
  if (!isset($_SESSION['logging_checked_' . $session])) {
    $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
    $logging = isset($getlogging[0]) ? $getlogging[0] : array();
    if (isset($logging['prefix']) && $logging['prefix'] != "->") {
      $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
    }
    $_SESSION['logging_checked_' . $session] = true;
  }
  session_write_close();

  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ".proplist" => ".id,time,message"));
  $log = is_array($getlog) ? array_reverse($getlog) : array();

  if ($livereport == "disable") {
    $logh = "457px";
  } else {
    $logh = "350px";
  }
  ?>
  <div id="r_1" class="row">
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-clock-o"></i></div>
            <div class="box-group-area">
              <span>
                  <b><?= $_time ?></b> : <span class="live-clock-time" data-date="<?= htmlspecialchars($clock['date'] ?? ''); ?>" data-time="<?= htmlspecialchars($clock['time'] ?? ''); ?>" style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($clock['time'] ?? ''); ?></span><br/>
                  <b><?= $_date ?></b> : <span class="live-clock-date" style="font-size: 12.5px;"><?= ucfirst(htmlspecialchars($clock['date'] ?? '')); ?></span><br/>
                  <b><?= $_uptime ?></b> : <?= formatDTM($resource['uptime']); ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
        <div class="box-group-icon"><i class="fa fa-info-circle"></i></div>
            <div class="box-group-area">
              <span>
                  <b><?= $_board_name ?></b> : <?= htmlspecialchars($resource['board-name'] ?? '') ?><br/>
                  <b><?= !empty($_architecture) ? $_architecture : 'Arsitektur' ?></b> : <?= htmlspecialchars($resource['architecture-name'] ?? '-') ?><br/>
                  <b>Router OS</b> : <?= htmlspecialchars($resource['version'] ?? '') ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
        <div class="box-group-icon"><i class="fa fa-server"></i></div>
            <div class="box-group-area">
              <span>
                  <b><?= $_cpu_load ?></b> : <?= htmlspecialchars($resource['cpu-load'] ?? '0') ?>%<br/>
                  <b><?= $_free_memory ?></b> : <?= formatBytes($resource['free-memory'], 2) ?><br/>
                  <b><?= $_free_hdd ?></b> : <?= formatBytes($resource['free-hdd-space'], 2); ?>
              </span>
              </div>
            </div>
          </div>
        </div> 
    </div>

    <div id="r_2" class="card">
      <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
        <div class="card-body">
          <div class="row">
            <div class="col-3 col-box-6">
              <div class="box bg-blue bmh-75">
                <a href="./?hotspot=active&session=<?= $session; ?>">
                  <h1><?= $counthotspotactive; ?> <span style="font-size: 14px;"><?= $hunit; ?></span></h1>
                  <div><i class="fa fa-laptop"></i> <?= $_hotspot_active ?></div>
                </a>
              </div>
            </div>
            <div class="col-3 col-box-6">
              <div class="box bg-green bmh-75">
                <a href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                  <h1><?= $countallusers; ?> <span style="font-size: 14px;"><?= $uunit; ?></span></h1>
                  <div><i class="fa fa-users"></i> <?= $_hotspot_users ?></div>
                </a>
              </div>
            </div>
            <div class="col-3 col-box-6">
              <div class="box bg-yellow bmh-75">
                <a href="./?hotspot-user=add&session=<?= $session; ?>">
                  <h1><i class="fa fa-user-plus"></i> <span style="font-size: 14px;"><?= $_add ?></span></h1>
                  <div><i class="fa fa-user-plus"></i> <?= $_hotspot_users ?></div>
                </a>
              </div>
            </div>
            <div class="col-3 col-box-6">
              <div class="box bg-red bmh-75">
                <a href="./?hotspot-user=generate&session=<?= $session; ?>">
                  <h1><i class="fa fa-user-plus"></i> <span style="font-size: 14px;"><?= $_generate ?></span></h1>
                  <div><i class="fa fa-user-plus"></i> <?= $_hotspot_users ?></div>
                </a>
              </div>
            </div>
          </div>
        </div>
    </div>

  <div id="r_3" class="row">
  <div class="card">
    <div class="card-header">
      <h3><a href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
        <div class="card-body">
          <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
            <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
              <thead>
                <tr>
                <th><?= $_time; ?></th>
                <th><?= $_users ?> (IP)</th>
                <th><?= $_messages ?></th>
                </tr>
              </thead>
              <tbody>
          
  <?php
  $log_count = count($log);
  $max_logs = min(20, $log_count);
  for ($i = 0; $i < $max_logs; $i++) {
    $mess = explode(":", $log[$i]['message']);
    $time = $log[$i]['time'];
    echo "<tr>";
    if (substr($log[$i]['message'], 0, 2) == "->") {
      echo "<td>" . $time . "</td>";
      echo "<td>";
      if (count($mess) > 6) {
        echo $mess[1] . ":" . $mess[2] . ":" . $mess[3] . ":" . $mess[4] . ":" . $mess[5] . ":" . $mess[6];
      } else {
        echo $mess[1];
      }
      echo "</td>";
      echo "<td>";
      if (count($mess) > 6) {
        echo str_replace("trying to", "", $mess[7] . " " . $mess[8] . " " . $mess[9] . " " . $mess[10]);
      } else {
        echo str_replace("trying to", "", $mess[2] . " " . $mess[3] . " " . $mess[4] . " " . $mess[5]);
      }
      echo "</td>";
    }
    echo "</tr>";
  }
  ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    </div>
<?php
}

}

?>
