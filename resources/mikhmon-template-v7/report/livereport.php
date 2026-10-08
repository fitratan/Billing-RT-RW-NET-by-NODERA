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
// set  timezone
  if (!empty($_SESSION['timezone'])) {
    @date_default_timezone_set($_SESSION['timezone']);
  }

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

// load config
  include('../include/config.php');
  include('../include/readcfg.php');

// routeros api
  include_once('../lib/routeros_api.class.php');
  include_once('../lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  $logh = "350px";
  $lreport = "style='display:block;'";

  // 1. Get Router Clock & Timezone
    $getclock = $API->comm("/system/clock/print", array(".proplist" => "time,date,time-zone-name"));
    $routerTz = $getclock[0]['time-zone-name'] ?? '';
    if (!empty($routerTz) && @timezone_open($routerTz) !== false) {
      @date_default_timezone_set($routerTz);
      $_SESSION['timezone'] = $routerTz;
    }

    $routerDate = trim($getclock[0]['date'] ?? '');

    // Month lookup map
    $monthNames = array(
      1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
      5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
      9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec'
    );
    $monthNums = array_flip($monthNames);

    // Parse date components directly from Router Clock string if present
    $d_num = (int) date("d");
    $m_num = (int) date("m");
    $y_num = (int) date("Y");
    $m_str = $monthNames[$m_num] ?? 'sep';

    if (!empty($routerDate)) {
      $rd = strtolower($routerDate);
      // Format: mmm/dd/yyyy or mmm-dd-yyyy (ROS6 standard, e.g. sep/22/2026 or sep/9/2026)
      if (preg_match('/^([a-z]{3})[\/-](\d{1,2})[\/-](\d{4})$/', $rd, $m)) {
        if (isset($monthNums[$m[1]])) {
          $m_str = $m[1];
          $m_num = $monthNums[$m[1]];
        }
        $d_num = (int) $m[2];
        $y_num = (int) $m[3];
      }
      // Format: yyyy-mm-dd or yyyy/mm/dd (ROS7 standard, e.g. 2026-09-22)
      elseif (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $rd, $m)) {
        $y_num = (int) $m[1];
        $m_num = (int) $m[2];
        $d_num = (int) $m[3];
        if (isset($monthNames[$m_num])) {
          $m_str = $monthNames[$m_num];
        }
      }
      // Format: dd/mmm/yyyy or dd-mmm-yyyy (e.g. 22/sep/2026)
      elseif (preg_match('/^(\d{1,2})[\/-]([a-z]{3})[\/-](\d{4})$/', $rd, $m)) {
        $d_num = (int) $m[1];
        if (isset($monthNums[$m[2]])) {
          $m_str = $m[2];
          $m_num = $monthNums[$m[2]];
        }
        $y_num = (int) $m[3];
      }
      // Format: mm/dd/yyyy (e.g. 09/22/2026)
      elseif (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $rd, $m)) {
        $m_num = (int) $m[1];
        $d_num = (int) $m[2];
        $y_num = (int) $m[3];
        if (isset($monthNames[$m_num])) {
          $m_str = $monthNames[$m_num];
        }
      }
    }

    $thisD = sprintf('%02d', $d_num);
    $thisD_nozero = (string) $d_num;
    $thisM = sprintf('%02d', $m_num);
    $thisM_nozero = (string) $m_num;
    $thisMAlpha = $m_str;
    $thisY = (string) $y_num;
    $thisY_short = sprintf('%02d', $y_num % 100);

    $idhr = $thisY . "-" . $thisM . "-" . $thisD;
    $idbl = $thisM . $thisY;
    $idblRos6 = $thisMAlpha . $thisY;

    $_SESSION[$session.'idhr'] = $idhr;

    // 2. Build Search Targets for Owner, Source, and Date Matching
    $ownersToTry = array(
      $idbl,
      $thisM_nozero . $thisY,
      $idblRos6,
      ucfirst($idblRos6),
      strtoupper($idblRos6),
      $thisMAlpha . "/" . $thisY,
      $thisMAlpha . "-" . $thisY,
      $thisM . "/" . $thisY,
      $thisM . "-" . $thisY,
    );
    $ownersToTry = array_unique(array_filter($ownersToTry));

    $sourcesToTry = array(
      $idhr,
      $thisY . "/" . $thisM . "/" . $thisD,
      $thisMAlpha . "/" . $thisD . "/" . $thisY,
      ucfirst($thisMAlpha) . "/" . $thisD . "/" . $thisY,
      strtoupper($thisMAlpha) . "/" . $thisD . "/" . $thisY,
      $thisMAlpha . "/" . $thisD_nozero . "/" . $thisY,
      ucfirst($thisMAlpha) . "/" . $thisD_nozero . "/" . $thisY,
      $thisM . "/" . $thisD . "/" . $thisY,
      $thisM_nozero . "/" . $thisD_nozero . "/" . $thisY,
      $thisD . "/" . $thisMAlpha . "/" . $thisY,
      $thisD . "/" . $thisM . "/" . $thisY,
      $thisD . "-" . $thisM . "-" . $thisY,
      $thisD . "-" . $thisMAlpha . "-" . $thisY,
      $thisMAlpha . "-" . $thisD . "-" . $thisY,
    );
    if (!empty($routerDate)) {
      $sourcesToTry[] = $routerDate;
    }
    $sourcesToTry = array_unique(array_filter($sourcesToTry));

    $validDailyDates = array();
    foreach ($sourcesToTry as $st) {
      $validDailyDates[] = strtolower(trim($st));
    }
    $validDailyDates = array_unique(array_filter($validDailyDates));

    // 3. Fast Single-Query Collection & De-duplication (1 Single Network Roundtrip)
    $allScripts = array();
    $seenIds = array();

    // Query 3A: Fetch scripts directly with comment=mikhmon (Fastest)
    $allM = $API->comm("/system/script/print", array(
      "?comment" => "mikhmon",
      ".proplist" => ".id,name,source,owner,comment"
    ));
    if (!empty($allM) && is_array($allM)) {
      foreach ($allM as $sc) {
        $scKey = $sc['.id'] ?? ($sc['name'] ?? '');
        if (!empty($scKey) && !isset($seenIds[$scKey])) {
          $seenIds[$scKey] = true;
          $allScripts[] = $sc;
        }
      }
    }

    // Query 3B: If comment query returned empty, fetch all scripts in 1 single call as fallback
    if (empty($allScripts)) {
      $allRaw = $API->comm("/system/script/print", array(
        ".proplist" => ".id,name,source,owner,comment"
      ));
      if (!empty($allRaw) && is_array($allRaw)) {
        foreach ($allRaw as $sc) {
          $name = $sc['name'] ?? '';
          if (strpos($name, '-|-') !== false) {
            $scKey = $sc['.id'] ?? $name;
            if (!empty($scKey) && !isset($seenIds[$scKey])) {
              $seenIds[$scKey] = true;
              $allScripts[] = $sc;
            }
          }
        }
      }
    }

    // 4. Calculate Daily & Monthly Statistics
    $tHr = 0;
    $tBl = 0;
    $TotalRHr = 0;
    $TotalRBl = 0;

    $normalizedOwners = array_map('strtolower', $ownersToTry);

    foreach ($allScripts as $row) {
      $name = $row['name'] ?? '';
      $source = strtolower(trim($row['source'] ?? ''));
      $owner = strtolower(trim($row['owner'] ?? ''));

      $parts = explode("-|-", $name);
      if (count($parts) < 4) {
        continue;
      }

      $datePart = strtolower(trim($parts[0] ?? ''));
      $price = (float) preg_replace('/[^0-9.]/', '', $parts[3] ?? '0');

      // Check This Month
      $isMonth = false;
      if (in_array($owner, $normalizedOwners)) {
        $isMonth = true;
      } elseif (
        (strpos($datePart, $thisY) !== false || strpos($source, $thisY) !== false) &&
        (
          strpos($datePart, $thisMAlpha) !== false ||
          strpos($source, $thisMAlpha) !== false ||
          strpos($datePart, '-' . $thisM . '-') !== false ||
          strpos($datePart, '/' . $thisM . '/') !== false ||
          strpos($source, '-' . $thisM . '-') !== false ||
          strpos($source, '/' . $thisM . '/') !== false
        )
      ) {
        $isMonth = true;
      }

      // Check Today
      $isToday = false;
      if (in_array($datePart, $validDailyDates) || in_array($source, $validDailyDates)) {
        $isToday = true;
      } else {
        // Fallback component check
        foreach (array($datePart, $source) as $target) {
          if (empty($target)) continue;
          $hasY = (strpos($target, $thisY) !== false || strpos($target, $thisY_short) !== false);
          $hasM = (
            strpos($target, $thisMAlpha) !== false ||
            strpos($target, '-' . $thisM . '-') !== false ||
            strpos($target, '/' . $thisM . '/') !== false ||
            str_starts_with($target, $thisM . '/') ||
            str_starts_with($target, $thisM_nozero . '/')
          );
          $hasD = (
            strpos($target, '/' . $thisD . '/') !== false ||
            strpos($target, '/' . $thisD_nozero . '/') !== false ||
            strpos($target, '-' . $thisD . '-') !== false ||
            str_starts_with($target, $thisD . '/') ||
            str_starts_with($target, $thisD . '-') ||
            str_ends_with($target, '/' . $thisD) ||
            str_ends_with($target, '/' . $thisD_nozero) ||
            str_ends_with($target, '-' . $thisD)
          );
          if ($hasY && $hasM && $hasD) {
            $isToday = true;
            break;
          }
        }
      }

      if ($isMonth) {
        $tBl += $price;
        $TotalRBl++;
      }
      if ($isToday) {
        $tHr += $price;
        $TotalRHr++;
      }
    }

    $_SESSION[$session.'totalHr'] = (string) $TotalRHr;
    $_SESSION[$session.'totalBl'] = (string) $TotalRBl;
}
?>

            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span>
                        <div id="reloadLreport">
                        <?php 
                          if ($currency == in_array($currency, $cekindo['indo'])) {
                            $dincome = number_format((float)$tHr, 0, ",", ".");
                            $mincome = number_format((float)$tBl, 0, ",", ".");
                            $_SESSION[$session.'dincome'] = $dincome;
                            $_SESSION[$session.'mincome'] = $mincome;
                          }else{
                            $dincome = number_format((float)$tHr, 2);
                            $mincome = number_format((float)$tBl, 2);
                            $_SESSION[$session.'dincome'] = $dincome;
                            $_SESSION[$session.'mincome'] = $mincome;
                          }
                            echo $_income."<br/>" . "
                          ".$_today." " . $TotalRHr . "vcr : " . $currency . " " . $dincome . "<br/>
                          ".$_this_month." " . $TotalRBl . "vcr : " . $currency . " " . $mincome;
                          ?>
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>