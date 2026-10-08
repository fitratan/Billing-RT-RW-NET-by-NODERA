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

  $getallqueue = $API->comm("/queue/simple/print", array(
    "?dynamic" => "false",
  ));

  $getpool = $API->comm("/ip/pool/print");

  if (isset($_POST['name'])) {
    $name = (preg_replace('/\s+/', '-',$_POST['name']));
    $sharedusers = ($_POST['sharedusers']);
    $ratelimit = ($_POST['ratelimit']);
    $expmode = ($_POST['expmode']);
    $validity = ($_POST['validity']);
    $graceperiod = ($_POST['graceperiod']);
    $getprice = ($_POST['price']);
    $getsprice = ($_POST['sprice']);
    $addrpool = ($_POST['ppool']);
    if ($getprice == "") {
      $price = "0";
    } else {
      $price = $getprice;
    }
    if ($getsprice == "") {
      $sprice = "0";
    } else {
      $sprice = $getsprice;
    }
    $getlock = ($_POST['lockunlock']);
    if ($getlock == "Enable") {
      $lock = '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]';
    } else {
      $lock = "";
    }

    $getsinglesession = ($_POST['singlesession'] ?? 'Disable');
    if ($getsinglesession == "Enable" || $getsinglesession == "Username") {
      $singlescript = ' { :local antiMacAcakLatestWins "yes"; :local curuser $user; :local curmac $"mac-address"; :local curaddr $address; :if (($curuser = "") or ($curmac = "")) do={} else={ :delay 1s; :local curid ""; :foreach a in=[/ip hotspot active find where user=$curuser] do={ :local amac [/ip hotspot active get $a mac-address]; :local aaddr [/ip hotspot active get $a address]; :if (($amac = $curmac) and (($curaddr = "") or ($aaddr = $curaddr))) do={ :set curid $a; }; }; :if ($curid != "") do={ :foreach a in=[/ip hotspot active find where user=$curuser] do={ :if ($a = $curid) do={} else={ /ip hotspot active remove $a; }; }; } else={ :foreach a in=[/ip hotspot active find where user=$curuser] do={ :local amac [/ip hotspot active get $a mac-address]; :if ($amac = $curmac) do={} else={ /ip hotspot active remove $a; }; }; }; :foreach c in=[/ip hotspot cookie find where user=$curuser] do={ :local cmac [/ip hotspot cookie get $c mac-address]; :if (($cmac = "") or ($cmac = $curmac)) do={} else={ /ip hotspot cookie remove $c; }; }; } };';
      if ((int)$sharedusers < 2) {
        $sharedusers = "2";
      }
    } elseif ($getsinglesession == "MAC") {
      $singlescript = ' { :local antiMacAcakLatestWins "yes"; :local curuser $user; :local curmac $"mac-address"; :local curaddr $address; :if (($curuser = "") or ($curmac = "")) do={} else={ :delay 1s; :local curid ""; :foreach a in=[/ip hotspot active find where user=$curuser] do={ :local amac [/ip hotspot active get $a mac-address]; :local aaddr [/ip hotspot active get $a address]; :if (($amac = $curmac) and (($curaddr = "") or ($aaddr = $curaddr))) do={ :set curid $a; }; }; :if ($curid != "") do={ :foreach a in=[/ip hotspot active find where user=$curuser] do={ :if ($a = $curid) do={} else={ /ip hotspot active remove $a; }; }; } else={ :foreach a in=[/ip hotspot active find where user=$curuser] do={ :local amac [/ip hotspot active get $a mac-address]; :if ($amac = $curmac) do={} else={ /ip hotspot active remove $a; }; }; }; :foreach c in=[/ip hotspot cookie find where user=$curuser] do={ :local cmac [/ip hotspot cookie get $c mac-address]; :if (($cmac = "") or ($cmac = $curmac)) do={} else={ /ip hotspot cookie remove $c; }; }; } };';
      if ((int)$sharedusers < 2) {
        $sharedusers = "2";
      }
    } else {
      $singlescript = "";
    }

    $randstarttime = "0".rand(1,5).":".rand(10,59).":".rand(10,59);
    $randinterval = "00:02:".rand(10,59);

    $parent = ($_POST['parent']);
    
    $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; /system script add name="$date-|-$time-|-$user-|-'.$price.'-|-$address-|-$mac-|-' . $validity . '-|-'.$name.'-|-$comment" owner="$month$year" source="$date" comment="mikhmon"';
    
    $expHead = '{:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local isStamped "no"; :if ([:len $comment] >= 14 and ([:find $comment "/"] != -1 or [:find $comment "-"] != -1) and [:find $comment ":"] != -1) do={ :set isStamped "yes"; }; :if ($isStamped = "no") do={ :local date [ /system clock get date ]; :local dlen [:len $date]; :local year; :local month; :if ([:pick $date 3] = "/") do={ :set month [:pick $date 0 3]; :set year [:pick $date ($dlen - 4) $dlen]; } else={ :set year [:pick $date 0 4]; :set month [:pick $date 5 7]; }; /sys sch add name="$user" disable=no start-date=$date interval="' . $validity . '" policy=read,write,policy,test; :delay 5s; :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run]; :local getxp [:len $exp]; :if ($getxp = 8) do={ /ip hotspot user set comment="$date $exp" [find where name="$user"]; } else={ :local sp [:find $exp " "]; :if ([:typeof $sp] = "num") do={ :local d [:pick $exp 0 $sp]; :local t [:pick $exp ($sp + 1) [:len $exp]]; :if ([:find $d "/"] != -1 and [:len $d] <= 6) do={ /ip hotspot user set comment="$d/$year $t" [find where name="$user"]; } else={ :if ([:find $d "-"] != -1 and [:len $d] <= 6) do={ /ip hotspot user set comment="$year-$d $t" [find where name="$user"]; } else={ /ip hotspot user set comment="$exp" [find where name="$user"]; }; }; } else={ :if ($getxp > 0) do={ /ip hotspot user set comment="$exp" [find where name="$user"]; }; }; }; :delay 2s; /sys sch remove [find where name="$user"]';
    
    if ($expmode == "rem") {
      $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . ' ' . $expHead . $lock . "}}";
      $mode = "remove";
    } elseif ($expmode == "ntf") {
      $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . ' ' . $expHead . $lock . "}}";
      $mode = "set limit-uptime=1s";
    } elseif ($expmode == "remc") {
      $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . ' ' . $expHead . $record . $lock . "}}";
      $mode = "remove";
    } elseif ($expmode == "ntfc") {
      $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . ' ' . $expHead . $record . $lock . "}}";
      $mode = "set limit-uptime=1s";
    } elseif ($expmode == "0" && $price != "") {
      $onlogin = ':put (",,' . $price . ',,,noexp,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . $lock;
    } elseif ($getsinglesession == "Enable" || $getsinglesession == "Username" || $getsinglesession == "MAC" || $getlock == "Enable") {
      $onlogin = ':put (",,0,,,,noexp,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . $lock;
    } else {
      $onlogin = "";
    }

    $bgservice = ':local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local dateint do={ :local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"); :local days 0; :local month 0; :local year 0; :local dlen [:len $d]; :if ($dlen >= 10 and [:pick $d 4] = "-" and [:pick $d 7] = "-") do={ :set year [$cleanNum val=[:pick $d 0 4]]; :set month [$cleanNum val=[:pick $d 5 7]]; :set days [$cleanNum val=[:pick $d 8 10]]; } else={ :local s1 [:find $d "/"]; :if ([:typeof $s1] = "num") do={ :local s2 [:find $d "/" ($s1 + 1)]; :if ([:typeof $s2] = "num") do={ :local p1 [:pick $d 0 $s1]; :local p2 [:pick $d ($s1 + 1) $s2]; :local p3 [:pick $d ($s2 + 1) $dlen]; :local mpos [:find $montharray $p1]; :if ([:typeof $mpos] = "num") do={ :set month (($mpos % 12) + 1); :set days [$cleanNum val=$p2]; :set year [$cleanNum val=$p3]; } else={ :set month [$cleanNum val=$p1]; :set days [$cleanNum val=$p2]; :set year [$cleanNum val=$p3]; }; }; }; }; :if ($year > 2000 and $month > 0 and $month <= 12 and $days > 0 and $days <= 31) do={ :local mstr "$month"; :if ($month < 10) do={ :set mstr ("0" . "$month"); }; :local dstr "$days"; :if ($days < 10) do={ :set dstr ("0" . "$days"); }; :return [:tonum ("$year" . "$mstr" . "$dstr")]; } else={ :return 0; }; }; :local timeint do={ :local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local c1 [:find $t ":"]; :if ([:typeof c1] = "num") do={ :local h [$cleanNum val=[:pick $t 0 $c1]]; :local c2 [:find $t ":" ($c1 + 1)]; :local mstr ""; :if ([:typeof c2] = "num") do={ :set mstr [:pick $t ($c1 + 1) $c2]; } else={ :set mstr [:pick $t ($c1 + 1) [:len $t]]; }; :local m [$cleanNum val=$mstr]; :return (($h * 60) + $m); }; :return -1; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date]; :local curtime [$timeint t=$time]; :if ($today > 20000000 and $curtime >= 0) do={ :foreach i in [ /ip hotspot user find where profile="'.$name.'" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local sp [:find $comment " "]; :if ([:typeof $sp] = "num") do={ :local expdate [:pick $comment 0 $sp]; :local exptime [:pick $comment ($sp + 1) [:len $comment]]; :local expd [$dateint d=$expdate]; :local expt [$timeint t=$exptime]; :if ($expd > 20000000 and $expt >= 0 and ($today > $expd or ($today = $expd and $curtime >= $expt))) do={ :do { /system script add name=("EXP-|-" . $date . "-|-" . $time . "-|-" . $name . "-|-" . '.$name.' . "-|-expired") owner="mikhmon" source="expired" comment="mikhmon_expired"; } on-error={}; /ip hotspot user '.$mode.' $i; /ip hotspot active remove [find where user=$name]; }; }; }; };';

    $API->comm("/ip/hotspot/user/profile/add", array(
			  		  /*"add-mac-cookie" => "yes",*/
      "name" => "$name",
      "address-pool" => "$addrpool",
      "rate-limit" => "$ratelimit",
      "shared-users" => "$sharedusers",
      "status-autorefresh" => "1m",
      //"transparent-proxy" => "yes",
      "on-login" => "$onlogin",
      "parent-queue" => "$parent",
    ));

    if ($expmode != "0") {
      if (empty($monid)) {
        $API->comm("/system/scheduler/add", array(
          "name" => "$name",
          "start-time" => "$randstarttime",
          "interval" => "$randinterval",
          "on-event" => "$bgservice",
          "disabled" => "no",
          "policy" => "read,write,policy,test",
          "comment" => "Monitor Profile $name",
        ));
      } else {
        $API->comm("/system/scheduler/set", array(
          ".id" => "$monid",
          "name" => "$name",
          "start-time" => "$randstarttime",
          "interval" => "$randinterval",
          "on-event" => "$bgservice",
          "disabled" => "no",
          "policy" => "read,write,policy,test",
          "comment" => "Monitor Profile $name",
        ));
      }
    } else {
      if (!empty($monid)) {
        $API->comm("/system/scheduler/remove", array(
          ".id" => "$monid"
        ));
      }
    }

    echo "<script>window.location='./?hotspot=user-profiles&session=" . $session . "'</script>";
  }
}
?>
<div class="row">
<div class="col-8">
<div class="card box-bordered">
  <div class="card-header">
    <h3><i class="fa fa-plus"></i> <?= $_add.' '.$_user_profile ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> Processing... </i></small></h3>
  </div>
  <div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?hotspot=user-profiles&session=<?= $session; ?>"> <i class="fa fa-close btn-mrg"></i> <?= $_close ?></a>
    <button type="submit" name="save" class="btn bg-primary btn-mrg" ><i class="fa fa-save btn-mrg"></i> <?= $_save ?></button>
  </div>
<table class="table">
  <tr>
    <td class="align-middle"><?= $_name ?></td><td><input class="form-control" type="text" onchange="remSpace();" autocomplete="off" name="name" value="" required="1" autofocus></td>
  </tr>
  <tr>
    <td class="align-middle">Address Pool</td>
    <td>
    <select class="form-control " name="ppool">
      <option value="none"><?= $_none ?? "none"; ?></option>
        <?php $TotalReg = count($getpool);
        for ($i = 0; $i < $TotalReg; $i++) {

          echo "<option>" . $getpool[$i]['name'] . "</option>";
        }
        ?>
    </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle">Shared Users</td><td><input class="form-control" type="text" size="4" autocomplete="off" name="sharedusers" value="1" required="1"></td>
  </tr>
  <tr>
    <td class="align-middle">Rate limit [up/down]</td><td><input class="form-control" type="text" name="ratelimit" autocomplete="off" value="" placeholder="Example : 512k/1M" ></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_expired_mode ?></td><td>
      <select class="form-control" onchange="RequiredV();" id="expmode" name="expmode" required="1">
        <option value="">Select...</option>
        <option value="0">None</option>
        <option value="rem">Remove</option>
        <option value="ntf">Notice</option>
        <option value="remc">Remove & Record</option>
        <option value="ntfc">Notice & Record</option>
      </select>
    </td>
  </tr>
  <tr id="validity" style="display:none;">
    <td class="align-middle"><?= $_validity ?></td><td><input class="form-control" type="text" id="validi" size="4" autocomplete="off" name="validity" value="" required="1"></td>
  </tr>
  <tr id="graceperiod" style="display:none;">
    <td class="align-middle"><?= $_grace_period ?></td><td><input class="form-control" type="text" id="gracepi" size="4" autocomplete="off" name="graceperiod" placeholder="5m" value="5m" required="1"></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_price.' '.$currency; ?></td><td><input class="form-control" type="text" size="10" min="0" name="price" value="" ></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_selling_price.' '.$currency; ?></td><td><input class="form-control" type="text" size="10" min="0" name="sprice" value="" ></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_lock_user ?></td><td>
      <select class="form-control" id="lockunlock" name="lockunlock" required="1">
        <option value="Disable">Disable</option>
        <option value="Enable">Enable</option>
      </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_single_session ?? 'Single Session' ?></td><td>
      <select class="form-control" id="singlesession" name="singlesession" required="1">
        <option value="Disable">Disable</option>
        <option value="Username">Enable (Kick by Username + Clear Cookie)</option>
        <option value="MAC">Kick by MAC Address</option>
      </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle">Parent Queue</td>
    <td>
    <select class="form-control " name="parent">
      <option value="none"><?= $_none ?? "none"; ?></option>
        <?php $TotalReg = count($getallqueue);
        for ($i = 0; $i < $TotalReg; $i++) {

          echo "<option>" . $getallqueue[$i]['name'] . "</option>";
        }
        ?>
    </select>
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
      <h3><i class="fa fa-book"></i> <?= $_readme ?></h3>
    </div>
    <div class="card-body">
<table class="table">
    <tr>
    <td colspan="2">
      <p style="padding:0px 5px;">
        <?= $_details_user_profile ?>
      </p>
      <p style="padding:0px 5px;">
        <?= $_format_validity ?>
      </p>
    </td>
  </tr>
</table>
</div>
</div>
</div>
</div>
<script type="text/javascript">
function remSpace() {
  var upName = document.getElementsByName("name")[0];
  var newUpName = upName.value.replace(/\s/g, "-");
  //alert("<?php if ($currency == in_array($currency, $cekindo['indo'])) {
            echo "Nama Profile tidak boleh berisi spasi";
          } else {
            echo "Profile name can't containing white space!";
          } ?>");
  upName.value = newUpName;
  upName.focus();
}
</script>
