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

	$getcookies = $API->comm("/ip/hotspot/cookie/print", array(
		".proplist" => ".id,user,mac-address,domain,expires-in"
	));
	$getcookies = is_array($getcookies) ? $getcookies : array();
	$TotalReg = count($getcookies);
	$countcookies = $TotalReg;
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
  <div class="card-header-flex">
    <h3 class="card-header-title">
      <i class="fa fa-sitemap"></i> Hotspot Cookies
      <small class="text-muted" style="font-size: 13px; font-weight: normal; opacity: 0.85;">
        (<?= ($countcookies < 2) ? "$countcookies item" : "$countcookies items"; ?>)
      </small>
    </h3>
    <div class="card-header-actions">
      <span onclick="location.reload();" class="header-action-btn pointer" title="<?= $_reload_data ?? "Reload data"; ?>"><i class="fa fa-refresh"></i></span>
    </div>
  </div>
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
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> User</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Domain</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Expires In</th>
  </tr>
  </thead>
  <tbody> 
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$cookies = $getcookies[$i];
	$id = $cookies['.id'];
	$user = $cookies['user'];
	$maca = $cookies['mac-address'];
	$domain = $cookies['domain'];
	$exp = formatDTM($cookies['expires-in']);

	$uriprocess = "'./?remove-cookie=" . $id . "&session=" . $session . "'";

	echo "<tr>";
	echo "<td style='text-align:center;'><span class='pointer'  title='Remove " . $user . "' onclick=loadpage(".$uriprocess.")><i class='fa fa-minus-square text-danger'></i></span></td>";
	echo "<td>" . $user . "</td>";
	echo "<td>" . $maca . "</td>";
	echo "<td>" . $domain . "</td>";
	echo "<td>" . $exp . "</a></td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>