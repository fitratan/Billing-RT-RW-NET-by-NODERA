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
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  // get quick print
  $getquickprint = $API->comm("/system/script/print", array("?comment" => "QuickPrintMikhmon", ".proplist" => ".id,name,source,comment"));
  $TotalReg = is_array($getquickprint) ? count($getquickprint) : 0;
  ?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<div class="row" style="align-items: center; display: flex;">
		<div class="col-6">
			<h3><i class="fa fa-print"></i> <?= $_quick_print ?></h3>
		</div>
		<div class="col-6 text-right">
			<a class="btn bg-primary pointer" onclick="location.href='./?hotspot=list-quick-print&session=<?= $session ?>';" title="<?= $_package . ' ' . $_quick_print ?>"><i class="fa fa-plus-circle"></i> <?= $_package . ' ' . $_quick_print ?></a>
		</div>
	</div>
</div>
<div class="card-body">
<div class="overflow" style="max-height: 80vh">	
<div class="row">
<?php
if ($TotalReg == 0) {
    $isFr = ($langid ?? '') === 'fr';
    $emptyTitle = $_no_quickprint_packages ?? "Belum Ada Paket Cetak Cepat";
    $emptyDesc = $isFr ? "La fonction d'impression rapide permet de générer et d'imprimer un coupon en un seul clic à partir de forfaits prédéfinis. Veuillez d'abord créer un forfait." : "Fitur Cetak Cepat adalah pintasan untuk mencetak 1 voucher instan dengan sekali klik berdasarkan paket yang telah dibuat. Silakan buat paket terlebih dahulu.";
    $emptyBtn = $_create_quickprint_now ?? "+ Buat Paket Cetak Cepat Sekarang";
?>
  <div class="col-12 text-center" style="padding: 40px 15px;">
    <div style="font-size: 56px; color: #b0bec5; margin-bottom: 15px;">
      <i class="fa fa-ticket"></i>
    </div>
    <h3 style="color: #455a64; margin-bottom: 10px; font-weight: 600;"><?= $emptyTitle ?></h3>
    <p style="color: #78909c; max-width: 540px; margin: 0 auto 20px; font-size: 14px; line-height: 1.5;">
      <?= $emptyDesc ?>
    </p>
    <a class="btn bg-primary pointer" style="padding: 9px 22px; font-size: 14px; display: inline-block;" onclick="location.href='./?hotspot=list-quick-print&session=<?= $session ?>';">
      <i class="fa fa-plus-circle"></i> <?= $emptyBtn ?>
    </a>
  </div>
<?php
} else {
  for ($i = 0; $i < $TotalReg; $i++) {
    $quickprintdetails = $getquickprint[$i];
    $qpname = $quickprintdetails['name'] ?? '';
    $qpid = $quickprintdetails['.id'] ?? '';
    $quickprintsource = explode("#", $quickprintdetails['source'] ?? '');
    $package = $quickprintsource[1] ?? '';
    $server = $quickprintsource[2] ?? '';
    $usermode = $quickprintsource[3] ?? '';
    $userlength = $quickprintsource[4] ?? '';
    $prefix = $quickprintsource[5] ?? '';
    $char = $quickprintsource[6] ?? '';
    $profile = $quickprintsource[7] ?? '';
    $timelimit = $quickprintsource[8] ?? '';
    $datalimit = $quickprintsource[9] ?? 0;
    $comment = $quickprintsource[10] ?? '';
    $validity = $quickprintsource[11] ?? '';
    $priceParts = explode("_", $quickprintsource[12] ?? '');
    $getprice = $priceParts[0] ?? 0;
    $getsprice = $priceParts[1] ?? 0;
    $userlock = $quickprintsource[13] ?? '';
    if (isset($cekindo['indo']) && in_array($currency, $cekindo['indo'])) {
      $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
      $sprice = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
    } else {
      $price = $currency . " " . number_format((float)$getprice);
      $sprice = $currency . " " . number_format((float)$getsprice);
    }
    $colorClass = $color[($i % 11) + 1] ?? 'bg-blue';
    ?>
       <div class="col-4">
        <div id='./hotspot/quickuser.php?quickprint=<?= urlencode($qpname) ?>&session=<?= $session; ?>' class="quick pointer box bmh-75 box-bordered <?= $colorClass; ?>" title='<?= $_print . " " . $_package . " " . htmlspecialchars($package, ENT_QUOTES); ?>'>
          <div class="box-group">
            <div class="box-group-icon">
              <i class="fa fa-print"></i>
            </div>
              <div class="box-group-area">
                <h3><?= $_package ?> : <?= htmlspecialchars($package); ?></h3>
                <span><?= $_time_limit ?> : <?= htmlspecialchars($timelimit) ?> | <?= $_data_limit ?> : <?= formatBytes($datalimit, 2) ?> <br> <?= $_validity ?> : <?= htmlspecialchars($validity) ?> | <?= $_price ?> : <?= $price ?> | <?= $_selling_price ?> : <?= $sprice ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php 
      }
    }
?>
</div>
</div>
</div>
</div>
</div>

<script>
$(document).ready(function(){
  $(".quick").click(function(){
    loadpage(this.id);
  });
});
</script>
<?php } ?>