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

// hide all error
error_reporting(0);
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  $user_props = ".id,server,name,password,profile,mac-address,uptime,bytes-in,bytes-out,comment,disabled,limit-uptime,limit-bytes-total";
  if ($prof == "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      ".proplist" => "$user_props"
    ));
  } elseif ($prof != "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?profile" => "$prof",
      ".proplist" => "$user_props"
    ));
  }
  if ($comm != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?comment" => "$comm",
      ".proplist" => "$user_props"
    ));
  }
  $exp = isset($_GET['exp']) ? $_GET['exp'] : '';
  if ($exp != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?limit-uptime" => "1s",
      ".proplist" => "$user_props"
    ));
  }
  $getuser = is_array($getuser) ? $getuser : array();
  $TotalReg = count($getuser);
  $counttuser = $TotalReg;

  $getprofile = $API->comm("/ip/hotspot/user/profile/print");
  $getprofile = is_array($getprofile) ? $getprofile : array();
  $TotalReg2 = count($getprofile);
}
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> <?= $_users ?>
      <span style="font-size: 14px">
        <?php
        if ($counttuser == 0 && $prof != "all" && $comm == "") {
          echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "';</script>";
        } ?>
         &nbsp; | &nbsp; <a href="./?hotspot-user=add&session=<?= $session; ?>" title="<?= $_add_user ?? "Add User"; ?>"><i class="fa fa-user-plus"></i> <?= $_add ?></a>
        &nbsp; | &nbsp; <a href="./?hotspot-user=generate&session=<?= $session; ?>" title="<?= $_generate_user ?? "Generate User"; ?>"><i class="fa fa-users"></i> <?= $_generate ?></a>
         &nbsp; | &nbsp; <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=script" title="<?= $_download_script ?? "Download User List as MikroTik Script"; ?>"><i class="fa fa-download"></i> Script</a>&nbsp; | &nbsp; <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=csv" title="<?= $_download_csv ?? "Download User List as CSV"; ?>"><i class="fa fa-download"></i> CSV</a>
        </span>  &nbsp;
        <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
    </h3>
    
</div>
<div class="card-body">
  <div class="row">
   <div class="col-6 pd-t-5 pd-b-5">
  <div class="input-group">
    <div class="input-group-4 col-box-4">
      <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?>">
    </div>
    <div class="input-group-4 col-box-4">
      <select style="padding:5px;" class="group-item group-item-m" onchange="location = this.value; loader()" title="<?= $_filter_by_profile ?? "Filter by Profile"; ?>">
        <option><?= $_profile ?> </option>
        <option value="./?hotspot=users&profile=all&session=<?= $session; ?>"><?= $_show_all ?></option>
      <?php
      for ($i = 0; $i < $TotalReg2; $i++) {
        $profile = $getprofile[$i];
        echo "<option value='./?hotspot=users&profile=" . $profile['name'] . "&session=" . $session . "'>" . $profile['name'] . "</option>";
      }
      ?>
    </select>
  </div>
  <div class="input-group-4 col-box-4">
    <select style="padding:5px;" class="group-item group-item-r" id="comment" name="comment" onchange="location = './?hotspot=users&comment='+ encodeURIComponent(this.value) +'&session=<?= $session;?>';">
    <?php
    if ($comm != "") {
      echo "<option value=''>" . $_comment . " (" . (($_SESSION['lang'] ?? '') === 'fr' ? 'Tous / Réinitialiser' : 'Semua / Reset') . ")</option>";
    } else {
      echo "<option value=''>" . $_comment . "</option>";
    }
    $comments = array();
    $onlineComments = array();
    foreach ($getuser as $u) {
      $ucomment = isset($u['comment']) ? $u['comment'] : '';
      $uprofile = isset($u['profile']) ? $u['profile'] : '';
      if ($ucomment !== '') {
        $key = $ucomment . '#' . $uprofile;
        if (str_starts_with($ucomment, 'NP-') || stripos($ucomment, 'BUY') !== false || stripos($ucomment, 'AUTO') !== false) {
          $onlineComments[$key] = ($onlineComments[$key] ?? 0) + 1;
        } else {
          $comments[$key] = ($comments[$key] ?? 0) + 1;
        }
      }
    }

    if (!empty($comments)) {
      echo "<optgroup label='-- " . ($_batch_voucher_generator ?? "Batch Voucher Generator") . " --'>";
      foreach ($comments as $tcomment => $value) {
        $cparts = explode("#", $tcomment);
        $selected = ($comm === $cparts[0]) ? "selected" : "";
        echo "<option value='" . htmlspecialchars($cparts[0], ENT_QUOTES) . "' " . $selected . ">" . htmlspecialchars($cparts[0]) . " " . (isset($cparts[1]) ? htmlspecialchars($cparts[1]) : '') . " [" . $value . "]</option>";
      }
      echo "</optgroup>";
    }

    if (!empty($onlineComments)) {
      echo "<optgroup label='-- " . ($_online_purchases_web_qris ?? "Online Purchases (Web/QRIS)") . " --'>";
      foreach ($onlineComments as $tcomment => $value) {
        $cparts = explode("#", $tcomment);
        $selected = ($comm === $cparts[0]) ? "selected" : "";
        echo "<option value='" . htmlspecialchars($cparts[0], ENT_QUOTES) . "' " . $selected . ">" . htmlspecialchars($cparts[0]) . " " . (isset($cparts[1]) ? htmlspecialchars($cparts[1]) : '') . " [" . $value . "]</option>";
      }
      echo "</optgroup>";
    }
    ?>
    </select>
  </div>
  </div>
  </div>
 
  <div class="col-6 pd-t-5 pd-b-5" style="display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
    <?php if ($comm != "") { ?>
  <button class="btn bg-red" onclick="if(confirm('<?= $_confirm_delete_by_comment ?? 'Apakah Anda yakin ingin menghapus user ini?'; ?>')){loadpage('./?remove-hotspot-user-by-comment=<?= $comm; ?>&session=<?= $session; ?>');loader();}else{}" title="<?= $_delete_by_comment ?? "Delete users by comment"; ?> <?= htmlspecialchars($comm, ENT_QUOTES); ?>">  <i class="fa fa-trash"></i> <?= $_by_comment ?></button>
    <?php ; }else if ($exp == "1"){ ?>
  <button class="btn bg-red" onclick="if(confirm('<?= $_confirm_delete_expired ?? 'Apakah Anda yakin ingin menghapus user expired?'; ?>')){loadpage('./?remove-hotspot-user-expired=1&session=<?= $session; ?>');loader();}else{}" title="<?= $_delete ?? 'Hapus'; ?>">  <i class="fa fa-trash"></i> <?= $_user_expired_btn ?? 'User Expired'; ?></button>
      <?php } ?>
  <script>
    function printV(a,b){
    var comm = document.getElementById('comment').value;
    var url = "./voucher/print.php?id="+comm+"&"+a+"="+b+"&session=<?= $session; ?>";
    if (comm === "" ){
      alert('<?= $_alert_select_comment_first ?? 'Silakan pilih salah satu Comment terlebih dulu!'; ?>');
    }else{
      var win = window.open(url, '_blank');
      win.focus();
    }}
  </script>
  <button class="btn bg-primary" title='<?= $_print ?? 'Cetak'; ?>' onclick="printV('qr','no');"><i class="fa fa-print"></i> <?= $_print_default ?></button>
  <button class="btn bg-primary" title='<?= $_print_qr ?? "Cetak QR"; ?>' onclick="printV('qr','yes');"><i class="fa fa-print"></i> <?= $_print_qr ?></button>
  <button class="btn bg-primary" title='<?= $_print_small ?? "Cetak Small"; ?>' onclick="printV('small','yes');"><i class="fa fa-print"></i> <?= $_print_small ?></button>
  <button class="btn bg-warning" title='<?= $_print_thermal ?? "Cetak Thermal (58/80mm)"; ?>' onclick="printV('thermal','yes');"><i class="fa fa-ticket"></i> <?= $_print_thermal ?? 'Thermal' ?></button>
  </div>
</div>
<div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="min-width:50px;" class="align-middle text-center" id="cuser"><?= $counttuser; ?></th>
    <th style="min-width:50px;" class="pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_server ?? "Server"; ?></th>
    <th class="pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_name; ?></th>
    <th><?= $_print; ?></th>
    <th class="pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_profile; ?></th>
    <th class="pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_mac_address ?? "MAC Address"; ?></th>
    <th class="text-right align-middle pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_uptime_user; ?></th>
    <th class="text-right align-middle pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_bytes_in ?? "Bytes In"; ?></th>
    <th class="text-right align-middle pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_bytes_out ?? "Bytes Out"; ?></th>
    <th class="pointer" title="<?= $_click_to_sort ?? "Click to sort"; ?>"><i class="fa fa-sort"></i> <?= $_comment; ?></th>
    </tr>
  </thead>
  <tbody id="tbody">
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $userdetails = $getuser[$i];
  $uid = $userdetails['.id'];
  $userver = $userdetails['server'];
  $uname = $userdetails['name'];
  $upass = $userdetails['password'];
  $uprofile = $userdetails['profile'];
  $umacadd = $userdetails['mac-address'];
  $uuptime = formatDTM($userdetails['uptime']);
  $ubytesi = formatBytes($userdetails['bytes-in'], 2);
  $ubyteso = formatBytes($userdetails['bytes-out'], 2);

  $ucomment = $userdetails['comment'];
  $udisabled = $userdetails['disabled'];
  $utimelimit = $userdetails['limit-uptime'];
  if ($utimelimit == '1s') {
    $utimelimit = ' expired';
  } else {
    $utimelimit = ' ' . $utimelimit;
  }
  $udatalimit = $userdetails['limit-bytes-total'];
  if ($udatalimit == '') {
    $udatalimit = '';
  } else {
    $udatalimit = ' ' . formatBytes($udatalimit, 2);
  }

  echo "<tr>";
  ?>
  <td style='text-align:center;'>  <i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('<?= $_confirm_delete ?? "Are you sure you want to delete?"; ?> (<?= htmlspecialchars($uname, ENT_QUOTES); ?>)')){loadpage('./?remove-hotspot-user=<?= $uid; ?>&session=<?= $session; ?>')}else{}" title='<?= $_delete ?? "Remove"; ?> <?= htmlspecialchars($uname, ENT_QUOTES); ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  <?php
  if ($udisabled == "true") {
    $uriprocess = "'./?enable-hotspot-user=" . $uid . "&session=" . $session."'";
    echo '<span class="text-warning pointer" title="' . ($_enable ?? "Enable") . " " . ($_users ?? "User") . " " . htmlspecialchars($uname, ENT_QUOTES) . '"  onclick="loadpage('.$uriprocess.')"><i class="fa fa-lock "></i></span></td>';
  } else {
    $uriprocess = "'./?disable-hotspot-user=" . $uid . "&session=" . $session."'";
    echo '<span class="pointer" title="' . ($_disable ?? "Disable") . " " . ($_users ?? "User") . " " . htmlspecialchars($uname, ENT_QUOTES) . '"  onclick="loadpage('.$uriprocess.')"><i class="fa fa-unlock "></i></span></td>';
  }
  echo "<td>" . $userver . "</td>";
  if ($uname == $upass) {
    $usermode = "vc";
  } else {
    $usermode = "up";
  }
  $popup = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . $uname . "&qr=no&session=" . $session . "','_blank','width=320,height=550').print();";
  $popupQR = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . $uname . "&qr=yes&session=" . $session . "','_blank','width=320,height=550').print();";
  $popupThermal = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . $uname . "&thermal=yes&session=" . $session . "','_blank','width=320,height=550').print();";
  echo "<td><a title='" . ($_open ?? "Open") . " " . htmlspecialchars($uname, ENT_QUOTES) . "' href=./?hotspot-user=" . $uid . "&session=" . $session . "><i class='fa fa-edit'></i> " . $uname . " </a>";
  echo '</td><td class"text-center"><a title="' . ($_print ?? "Print") . " " . htmlspecialchars($uname, ENT_QUOTES) . '" href="' . $popup . '"><i class="fa fa-print"></i></a> &nbsp; <a title="' . ($_print_qr ?? "Print QR") . " " . htmlspecialchars($uname, ENT_QUOTES) . '" href="' . $popupQR . '"><i class="fa fa-qrcode"></i></a> &nbsp; <a title="' . ($_print_thermal ?? "Thermal") . " " . htmlspecialchars($uname, ENT_QUOTES) . '" href="' . $popupThermal . '"><i class="fa fa-ticket"></i></a></td>';
  echo "<td>" . $uprofile . "</td>";
  echo "<td style=' text-align:left'>" . $umacadd . "</td>";
  echo "<td style=' text-align:right'>" . $uuptime . "</td>";
  echo "<td style=' text-align:right'>" . $ubytesi . "</td>";
  echo "<td style=' text-align:right'>" . $ubyteso . "</td>";
  echo "<td>";
  if ($uname == "default-trial") {
  } else if (substr($ucomment,0,3) == "vc-" || substr($ucomment,0,3) == "up-") {
    echo "<a href=./?hotspot=users&comment=" . $ucomment . "&session=" . $session . " title='" . ($_filter_by ?? "Filter by") . " " . htmlspecialchars($ucomment, ENT_QUOTES) . "'><i class='fa fa-search'></i> ". $ucomment." ". $udatalimit ." ".$utimelimit . "</a>";
  } else if ($utimelimit == ' expired') {
    echo "<a href=./?hotspot=users&profile=all&exp=1&session=" . $session . " title='" . ($_filter_by_expired ?? "Filter expired users") . "'><i class='fa fa-search'></i> " . $ucomment." ". $udatalimit ." ".$utimelimit . "</a>";
  }else{
    echo $ucomment.' ';
  }
  echo  "</td>";


}
?>
  </tr>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>

	
	
