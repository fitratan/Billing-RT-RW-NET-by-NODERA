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

	$getbinding = $API->comm("/ip/hotspot/ip-binding/print", array(
		".proplist" => ".id,mac-address,address,to-address,server,comment,disabled,bypassed"
	));
	$getbinding = is_array($getbinding) ? $getbinding : array();
	$TotalReg = count($getbinding);
	$countbinding = $TotalReg;
}

?>
<div class="row">
<div id="reloadbinding">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-address-book"></i> <?= $_ip_bindings ?> 
<?php
if ($countbinding < 2) {
	echo "$countbinding item";
} elseif ($countbinding > 1) {
	echo "$countbinding items";
};
?>
    </h3>
</div>
<div class="card-body">	   
<div class="w-6">
    <input id="filterTable" type="text" class="form-control" placeholder="<?= $_search ?? "Search"; ?>...">
  </div>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh">  	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap"> 
 <thead>
  <tr>
    <th></th>
    <th></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> To Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
  </tr>
  </thead>
  <tbody> 
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$binding = $getbinding[$i];
	$id = $binding['.id'];

	$maca = $binding['mac-address'];
	$addr = $binding['address'];
	$toaddr = $binding['to-address'];
	$server = $binding['server'];
	$commt = $binding['comment'];
	$bdisabled = $binding['disabled'];

	echo "<tr>";
	?>
  	<td style='text-align:center;'><i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('<?= $_confirm_delete ?? "Are you sure you want to delete?"; ?> (<?= $maca; ?>)')){loadpage('./?remove-ip-binding=<?= $id . '&mac=' . $maca . '&addr=' . $addr; ?>&session=<?= $session; ?>')}else{}" title='<?= $_delete ?? "Remove"; ?> <?= $maca; ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  	<?php

		if ($bdisabled == "true") {
			$uriprocess = "'./?enable-ip-binding=" . $id . "&session=" . $session . "'";
			echo "<span class='text-warning btnsmall pointer' title='" . ($_enable ?? 'Enable') . " " . $addr . "' onclick=loadpage(".$uriprocess.")><i class='fa fa-lock'></span></td>";
		} else {
			$uriprocess = "'./?disable-ip-binding=" . $id . "&session=" . $session . "'";
			echo "<span title='" . ($_disable ?? 'Disable') . " " . $addr . "' class='btnsmall pointer' onclick=loadpage(".$uriprocess.")><i class='fa fa-unlock '></span></td>";
		}
		echo "<td style='text-align:center;'>";
		if ($binding['bypassed'] == "true") {
			echo "<b style='color:#0091EA;'>P</b>";
		} else {
		}
		echo "</td>";
		echo "<td>" . $commt . "</td>";
		echo "<td>" . $maca . "</td>";
		echo "<td>" . $addr . "</a></td>";
		echo "<td>" . $toaddr . "</td>";
		echo "<td>" . $server . "</td>";
		echo "</tr>";
	}
	?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
<div class="modal-window" id="help" aria-hidden="true">
  <div>
  	<header><h1><?= $_help ?? "Help"; ?></h1></header>
  	<a style="font-weight:bold;" href="#" title="<?= $_close ?? "Close"; ?>" class="modal-close">X</a>
	<p> 
		    <?= $_ipbinding_help_desc ?? "<ul><li>Go to Hosts menu.</li><li>Click the IP Address that you want to bind.</li></ul>"; ?>
	</p>
  </div>
</div>
</div>
</div>
