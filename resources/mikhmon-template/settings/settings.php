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
  // localization variables loaded via lang dictionary

  if ($id == "settings" && explode("-", (string)$router)[0] == "new") {
    $configFile = dirname(__DIR__) . '/include/config.php';
    $data = [];
    if (file_exists($configFile)) {
      @include $configFile;
    }
    if (!isset($data['mikhmon']) || !is_array($data['mikhmon'])) {
      $data['mikhmon'] = array('1' => 'mikhmon<|<nodera', '2' => 'mikhmon>|>pqCWnaOT');
    }
    $data[$router] = array(
      '1'  => $router . '!',
      '2'  => $router . '@|@',
      '3'  => $router . '#|#',
      '4'  => $router . '%',
      '5'  => $router . '^',
      '6'  => $router . '&' . ($isFr ? 'FCFA' : 'Rp'),
      '7'  => $router . '*10',
      '8'  => $router . '(1',
      '9'  => $router . ')',
      '10' => $router . '=10',
      '11' => $router . '@!@enable',
    );
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
    if (!headers_sent()) {
      header("Location: ./admin.php?id=settings&session=" . $router);
    }
    echo "<script>window.location='./admin.php?id=settings&session=" . $router . "'</script>";
    exit;
  }

  if (isset($_POST['save'])) {

    $siphost = (preg_replace('/\s+/', '', $_POST['ipmik']));
    $suserhost = ($_POST['usermik']);
    $spasswdhost = mikhmon_encrypt($_POST['passmik']);
    $shotspotname = str_replace("'","",$_POST['hotspotname']);
    $sdnsname = ($_POST['dnsname']);
    $scurrency = ($_POST['currency']);
    $sreload = ($_POST['areload']);
    if ($sreload < 10) {
      $sreload = 10;
    } else {
      $sreload = $sreload;
    }
    $siface = ($_POST['iface']);
    $sinfolp = implode(unpack("H*", $_POST['infolp']));
    //$sinfolp = mikhmon_encrypt($_POST['infolp']);
    //$sinfolp = ($_POST['infolp']);
    $sidleto = ($_POST['idleto']);

    $sesname = (preg_replace('/\s+/', '-', $_POST['sessname']));
    $slivereport = ($_POST['livereport']);

    $configFile = dirname(__DIR__) . '/include/config.php';
    $data = [];
    if (file_exists($configFile)) {
      include $configFile;
    }
    if (!isset($data['mikhmon']) || !is_array($data['mikhmon'])) {
      $data['mikhmon'] = array('1' => 'mikhmon<|<' . ($useradm ?: 'nodera'), '2' => 'mikhmon>|>' . ($passadm ?: 'pqCWnaOT'));
    }

    $data[$sesname] = array(
      '1'  => $sesname . '!' . $siphost,
      '2'  => $sesname . '@|@' . $suserhost,
      '3'  => $sesname . '#|#' . $spasswdhost,
      '4'  => $sesname . '%' . $shotspotname,
      '5'  => $sesname . '^' . $sdnsname,
      '6'  => $sesname . '&' . $scurrency,
      '7'  => $sesname . '*' . $sreload,
      '8'  => $sesname . '(' . $siface,
      '9'  => $sesname . ')' . $sinfolp,
      '10' => $sesname . '=' . $sidleto,
      '11' => $sesname . '@!@' . $slivereport,
    );

    if ($session !== $sesname && !empty($session) && isset($data[$session])) {
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

    // Save location and primary setting
    $locConfigFile = dirname(__DIR__) . '/include/location_config.php';

    $location_data = ['primary' => '', 'locations' => []];
    if (file_exists($locConfigFile)) {
      include $locConfigFile;
    }
    if (!isset($location_data['locations']) || !is_array($location_data['locations'])) {
      $location_data['locations'] = [];
    }

    $slocname = trim(str_replace("'", "", $_POST['location_name'] ?? ''));
    if (empty($slocname) || strtolower($slocname) === 'dns') {
      $slocname = (!empty($shotspotname) && strtolower($shotspotname) !== 'dns') 
        ? $shotspotname 
        : ((!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : ucwords(str_replace(['-', '_'], ' ', $sesname)));
    }
    $location_data['locations'][$sesname] = $slocname;
    if ($session !== $sesname && isset($location_data['locations'][$session])) {
      unset($location_data['locations'][$session]);
    }

    if (!empty($_POST['is_primary'])) {
      $location_data['primary'] = $sesname;
    } else {
      if (($location_data['primary'] ?? '') === $session) {
        if ($session !== $sesname) {
          $location_data['primary'] = $sesname;
        } else {
          $location_data['primary'] = '';
        }
      }
    }

    $locOut = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"location_config.php\"){header(\"Location:./\");};\n\$location_data = " . var_export($location_data, true) . ";\n";
    @file_put_contents($locConfigFile, $locOut);

    // Migrate all user configs if session name was renamed
    if ($session !== $sesname && !empty($session)) {
      // 1. WhatsApp config migration
      $waFile = dirname(__DIR__) . '/include/whatsapp_config.php';
      if (file_exists($waFile)) {
        $wa_data = [];
        include $waFile;
        if (isset($wa_data[$session])) {
          $wa_data[$sesname] = $wa_data[$session];
          unset($wa_data[$session]);
          $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"whatsapp_config.php\"){header(\"Location:./\");};\n\$wa_data = " . var_export($wa_data, true) . ";\n";
          @file_put_contents($waFile, $out);
        }
      }

      // 2. Telegram config migration
      $tgFile = dirname(__DIR__) . '/include/telegram_config.php';
      if (file_exists($tgFile)) {
        $tg_data = [];
        include $tgFile;
        if (isset($tg_data[$session])) {
          $tg_data[$sesname] = $tg_data[$session];
          unset($tg_data[$session]);
          $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"telegram_config.php\"){header(\"Location:./\");};\n\$tg_data = " . var_export($tg_data, true) . ";\n";
          @file_put_contents($tgFile, $out);
        }
      }

      // 3. NODERA Pay config migration
      $npFile = dirname(__DIR__) . '/include/noderapay_config.php';
      if (file_exists($npFile)) {
        $noderapay_data = [];
        include $npFile;
        if (isset($noderapay_data[$session])) {
          $noderapay_data[$sesname] = $noderapay_data[$session];
          unset($noderapay_data[$session]);
          $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -20) == \"noderapay_config.php\"){header(\"Location:./\");};\n\$noderapay_data = " . var_export($noderapay_data, true) . ";\n";
          @file_put_contents($npFile, $out);
        }
      }

      // 4. Voucher config migration
      $vcFile = dirname(__DIR__) . '/include/voucher_config.php';
      if (file_exists($vcFile)) {
        $voucher_config = [];
        include $vcFile;
        if (isset($voucher_config[$session])) {
          $voucher_config[$sesname] = $voucher_config[$session];
          unset($voucher_config[$session]);
          $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -18) == \"voucher_config.php\"){header(\"Location:./\");};\n\$voucher_config = " . var_export($voucher_config, true) . ";\n";
          @file_put_contents($vcFile, $out);
        }
      }

      // 5. Auto clean expired config migration
      $acFile = dirname(__DIR__) . '/include/autoclean_config.php';
      if (file_exists($acFile)) {
        $autoclean_data = [];
        include $acFile;
        if (isset($autoclean_data[$session])) {
          $autoclean_data[$sesname] = $autoclean_data[$session];
          unset($autoclean_data[$session]);
          $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -20) == \"autoclean_config.php\"){header(\"Location:./\");};\n\$autoclean_data = " . var_export($autoclean_data, true) . ";\n";
          @file_put_contents($acFile, $out);
        }
      }
    }

    // Save auto clean expired setting
    $acFile = dirname(__DIR__) . '/include/autoclean_config.php';
    $autoclean_data = [];
    if (file_exists($acFile)) {
      @include $acFile;
    }
    $autoclean_data[$sesname] = (!empty($_POST['auto_clean_expired']) && $_POST['auto_clean_expired'] === 'enable') ? 'enable' : 'disable';
    $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -20) == \"autoclean_config.php\"){header(\"Location:./\");};\n\$autoclean_data = " . var_export($autoclean_data, true) . ";\n";
    @file_put_contents($acFile, $out);

    $_SESSION["connect"] = "";
    if (!headers_sent()) {
      header("Location: ./admin.php?id=settings&session=" . $sesname);
    }
    echo "<script>window.location='./admin.php?id=settings&session=" . $sesname . "'</script>";
    exit;
  }

  $locConfigFile = dirname(__DIR__) . '/include/location_config.php';
  $location_data = ['primary' => '', 'locations' => []];
  if (file_exists($locConfigFile)) {
    include $locConfigFile;
  }
  $rawLoc = $location_data['locations'][$session] ?? '';
  $cleanLoc = (strtolower($rawLoc) === 'dns' || empty($rawLoc)) ? '' : $rawLoc;
  $cleanHs = (strtolower($hotspotname) === 'dns' || empty($hotspotname)) ? '' : $hotspotname;
  $locName = !empty($cleanLoc) ? $cleanLoc : (!empty($cleanHs) ? $cleanHs : ucwords(str_replace(['-', '_'], ' ', $session)));
  $isPrimary = ($location_data['primary'] ?? '') === $session;
  if (empty($location_data['primary']) && !empty($session)) {
    $configFileCheck = dirname(__DIR__) . '/include/config.php';
    $cfgLines = file_exists($configFileCheck) ? file($configFileCheck) : [];
    $nonMikhmonCount = 0;
    foreach ($cfgLines as $l) {
      $p = explode("'", $l);
      if (!empty($p[1]) && $p[1] !== 'mikhmon') $nonMikhmonCount++;
    }
    if ($nonMikhmonCount <= 1) {
      $isPrimary = true;
    }
  }

  $autocleanFile = dirname(__DIR__) . '/include/autoclean_config.php';
  $autoclean_data = [];
  if (file_exists($autocleanFile)) {
    @include $autocleanFile;
  }
  $autoCleanExpired = $autoclean_data[$session] ?? 'disable';

  if ($currency == "" && !empty($session)) {
    echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
    exit;
  }
}
?>
<script>
  function PassMk(){
    var x = document.getElementById('passmk');
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
    function PassAdm(){
    var x = document.getElementById('passadm');
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
  }}
  
</script>

<form autocomplete="off" method="post" action="" name="settings">  
<div class="row">
	<div class="col-12">
  		<div class="card" >
  			<div class="card-header">
  				<h3 class="card-title"><i class="fa fa-gear"></i> <?= $_session_settings ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
  			</div>
        <div class="card-body">
    	   <div class="row">
			     <div class="col-6">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><?= $_session ?></h3>
                </div>
                <div class="card-body">
                  <table class="table">
                    <tr>
                      <td><?= $_session_name ?></td>
                      <td><input class="form-control" id="sessname" type="text" name="sessname" title="Session Name" value="<?php if (explode("-",$session)[0] == "new") {
                                                                                                                              echo "";
                                                                                                                            } else {
                                                                                                                              echo $session;
                                                                                                                            } ?>" required="1"/></td>
                    </tr>
                  </table>
                </div>
              </div>
            </div>
            <div class="col-12">
				      <div class="card">
        	     <div class="card-header">
            	   <h3 class="card-title">MikroTik <?= $_SESSION["connect"]; ?></h3>
        	     </div>
        	     <div class="card-body">
				<table class="table table-sm">
					<tr>
	  					<td class="align-middle">IP MikroTik </td><td><input class="form-control" type="text" size="15" name="ipmik" title="IP MikroTik / IP Cloud MikroTik" value="<?= $iphost; ?>" required="1"/></td>
					</tr>
					<tr>
						<td class="align-middle">Username  </td><td><input class="form-control" id="usermk" type="text" size="10" name="usermik" title="User MikroTik" value="<?= $userhost; ?>" required="1"/></td>
					</tr>
					<tr>
						<td class="align-middle">Password  </td><td>
							<div class="input-group">
								<div class="input-group-11 col-box-10">
        						<input class="group-item group-item-l" id="passmk" type="password" name="passmik" title="Password MikroTik" value="<?= mikhmon_decrypt($passwdhost); ?>" required="1"/>
        						</div>
            					<div class="input-group-1 col-box-2">
            						<div class="group-item group-item-r pd-2p5 text-center align-middle">
                						<input title="Show/Hide Password" type="checkbox" onclick="PassMk()">
            						</div>
            					</div>
    						</div>
						</td>
					</tr>
					<tr>
						<td colspan="2">
								<div class="input-group-4">
									<input class="group-item group-item-md" type="submit" style="cursor: pointer;" name="save" value="<?= $_save ?>" />
								</div>
								<div class="input-group-4">	
                  <span class="connect pointer group-item group-item-md pd-2p5 text-center align-middle" id="<?= $session; ?>&c=settings"><?= $_connect ?></span>
								</div>
								<div class="input-group-3">	
                  <span class="pointer group-item group-item-md pd-2p5 text-center align-middle" id="ping_test"><?= $_ping ?? 'Ping' ?></span>
              	</div>
              	<div class="input-group-1">	
									<div style="cursor: pointer;" class="group-item group-item-r pd-2p5 text-center" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
								</div>
            		</div>	
    					</td>
    				</tr>
				</table>
			</div>
    </div>  	
    <div id="ping">
    </div>	
	</div>
</div>
<div class="col-6">
<div class="col-12">
	<div class="card">
        <div class="card-header">
            <h3 class="card-title">Mikhmon Data</h3>
        </div>
    <div class="card-body">    
	<table class="table table-sm">
	<tr>
	<td class="align-middle"><?= $_hotspot_name ?>  </td><td><input class="form-control" type="text" size="15" maxlength="50" name="hotspotname" title="Hotspot Name" value="<?= $hotspotname; ?>" required="1"/></td>
	</tr>
	<tr>
	<td class="align-middle"><?= $_hotspot_location ?? 'Lokasi Hotspot'; ?></td><td>
      <input class="form-control" type="text" size="20" maxlength="100" name="location_name" placeholder="<?= $_hotspot_location_placeholder ?? 'Contoh: Kafe Sentosa, Warkop 363, Kost Putra' ?>"  value="<?= htmlspecialchars($locName ?? ''); ?>"/>
      <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 3px;"><?= $_hotspot_location_help ?? 'Nama cabang/lokasi yang muncul di pilihan pembeli voucher online jika memiliki &gt; 1 router.' ?></small>
    </td>
	</tr>
	<tr>
	<td class="align-middle"><?= $_primary_mikrotik ?? 'MikroTik Utama'; ?></td><td>
      <label style="font-weight: bold; cursor: pointer; display: flex; align-items: flex-start; gap: 8px; margin: 4px 0 0 0;">
        <input type="checkbox" name="is_primary" value="1" <?= $isPrimary ? 'checked' : ''; ?> style="width: 17px; height: 17px; margin-top: 1px; cursor: pointer;">
        <span style="font-size: 12px; line-height: 1.4;"><?= $_make_primary_router_desc ?? 'Jadikan Router Utama (Default Portal Pembeli)' ?></span>
      </label>
      <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 4px; line-height: 1.35;">
        <?= $_primary_router_help ?? 'MikroTik Utama otomatis ditampilkan saat pelanggan baru pertama kali membuka link pembelian. Jika pelanggan berpindah dan membeli voucher di lokasi lain, sistem akan mengingat lokasi terakhir tersebut untuk pembukaan berikutnya.' ?>
      </small>
    </td>
	</tr>
	<tr>
	<td class="align-middle"><?= $_dns_name ?>  </td><td><input class="form-control" type="text" size="15" maxlength="500" name="dnsname" title="DNS Name [IP->Hotspot->Server Profiles->DNS Name]" value="<?= $dnsname; ?>" required="1"/></td>
	</tr>
	<tr>
	<td class="align-middle"><?= $_currency ?>  </td><td><input class="form-control" type="text" size="3" maxlength="4" name="currency" title="currency" value="<?= $currency; ?>" required="1"/></td>
	</tr>
	<tr> 
	<td class="align-middle"><?= $_auto_reload ?></td><td>
	<div class="input-group">
		<div class="input-group-10">
        	<input class="group-item group-item-l" type="number" min="10" max="3600" name="areload" title="Auto Reload in sec [min 10]" value="<?= $areload; ?>" required="1"/>
    	</div>
            <div class="input-group-2">
                <span class="group-item group-item-r pd-2p5 text-center align-middle"><?= $_sec ?></span>
            </div>
        </div>
	</td>
  </tr>
  <tr>
  <td class="align-middle"><?= $_idle_timeout ?></td>
  <td>
  <div class="input-group">
  <div class="input-group-9">
      <select class="group-item group-item-l" name="idleto" required="1">
          <option value="<?= $idleto; ?>"><?= $idleto; ?></option>
				  <option value="5">5</option>
          <option value="10">10</option>
          <option value="30">30</option>
          <option value="60">60</option>
          <option value="disable">disable</option>
      </select>
  </div>
  <div class="input-group-3">
                <span class="group-item group-item-r pd-3p5 text-center align-middle"><?= $_min ?></span>
            </div>
        </div>
    </td>
	</tr>
	<tr>
	<td class="align-middle"><?= $_traffic_interface ?></td><td><input class="form-control" type="number" min="1" max="99" name="iface" title="Traffic Interface" value="<?= $iface; ?>" required="1"/></td>
	</tr>
  <tr>
    <td class="align-middle"><?= $_auto_clean_expired ?? 'Auto Clean Expired' ?></td>
    <td>
      <select class="form-control" name="auto_clean_expired">
        <option value="disable" <?= ($autoCleanExpired === 'disable' || empty($autoCleanExpired)) ? 'selected' : ''; ?>><?= $_disable_manual ?? 'Disable (Manual)' ?></option>
        <option value="enable" <?= ($autoCleanExpired === 'enable') ? 'selected' : ''; ?>><?= $_enable_auto ?? 'Enable (Otomatis)' ?></option>
      </select>
      <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 4px; line-height: 1.35;">
        <?= $_auto_clean_expired_help ?? 'Otomatis menghapus voucher expired di MikroTik saat membuka Mikhmon. Laporan omset &amp; data billing tetap aman.' ?>
      </small>
    </td>
  </tr>
  <?php if (empty($livereport)) {
  } else { ?>
  <tr>
    <td><?= $_live_report ?></td>
    <td>
      <select class="form-control" name="livereport" >
          <option value="<?= $livereport; ?>"><?= ucfirst($livereport); ?></option>
				  <option value="enable">Enable</option>
				  <option value="disable">Disable</option>
		  </select>
    </td>
  </tr>
  <?php 
} ?>
</table>
</div>
</div>
</div>
</div>
</div>
</form>
<script type="text/javascript">

var _0x1d39=["\x68\x6F\x73\x74\x6E\x61\x6D\x65","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x2E","\x73\x70\x6C\x69\x74","","\x78\x62\x61\x6E\x2E\x78\x79\x7A","\x6C\x6F\x67\x61\x6D\x2E\x69\x64","\x6D\x69\x6E\x69\x73\x2E\x69\x64","\x69\x6E\x64\x65\x78\x4F\x66","\x69\x6E\x6E\x65\x72\x48\x54\x4D\x4C","\x70\x69\x6E\x67","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x3C\x64\x69\x76\x20\x69\x64\x3D\x22\x70\x69\x6E\x67\x58\x22\x20\x63\x6C\x61\x73\x73\x3D\x22\x63\x6F\x6C\x2D\x31\x32\x22\x3E\x3C\x64\x69\x76\x20\x63\x6C\x61\x73\x73\x3D\x22\x63\x61\x72\x64\x22\x3E\x3C\x64\x69\x76\x20\x63\x6C\x61\x73\x73\x3D\x22\x63\x61\x72\x64\x2D\x68\x65\x61\x64\x65\x72\x22\x3E\x3C\x68\x33\x20\x63\x6C\x61\x73\x73\x3D\x22\x63\x61\x72\x64\x2D\x74\x69\x74\x6C\x65\x22\x3E\x50\x69\x6E\x67\x20\x54\x65\x73\x74\x20\x3C\x2F\x68\x33\x3E\x09\x3C\x2F\x64\x69\x76\x3E\x09\x3C\x64\x69\x76\x20\x63\x6C\x61\x73\x73\x3D\x22\x63\x61\x72\x64\x2D\x62\x6F\x64\x79\x22\x3E\x3C\x68\x33\x3E\x46\x69\x74\x75\x72\x20\x74\x69\x64\x61\x6B\x20\x73\x75\x70\x70\x6F\x72\x74\x2E\x3C\x2F\x68\x33\x3E\x3C\x73\x70\x61\x6E\x20\x63\x6C\x61\x73\x73\x3D\x22\x70\x6F\x69\x6E\x74\x65\x72\x20\x62\x74\x6E\x22\x20\x6F\x6E\x63\x6C\x69\x63\x6B\x3D\x22\x63\x6C\x6F\x73\x65\x58\x28\x29\x22\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x66\x61\x20\x66\x61\x2D\x63\x6C\x6F\x73\x65\x20\x74\x65\x78\x74\x2D\x72\x65\x64\x20\x22\x3E\x3C\x2F\x69\x3E\x20\x43\x6C\x6F\x73\x65\x3C\x2F\x73\x70\x61\x6E\x3E\x3C\x2F\x64\x69\x76\x3E\x3C\x2F\x64\x69\x76\x3E\x3C\x2F\x64\x69\x76\x3E","\x6F\x6E\x63\x6C\x69\x63\x6B","\x70\x69\x6E\x67\x5F\x74\x65\x73\x74","\x2E\x2F\x73\x74\x61\x74\x75\x73\x2F\x70\x69\x6E\x67\x2D\x74\x65\x73\x74\x2E\x70\x68\x70\x3F\x70\x69\x6E\x67\x26\x73\x65\x73\x73\x69\x6F\x6E\x3D","\x6C\x6F\x61\x64","\x23\x70\x69\x6E\x67","\x76\x61\x6C\x75\x65","\x73\x65\x73\x73\x6E\x61\x6D\x65","\x68\x69\x64\x65","\x23\x70\x69\x6E\x67\x58"];var _0x8202=["\x62\x72\x61\x6E\x64","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x69\x6E\x6E\x65\x72\x48\x54\x4D\x4C","\x4D\x49\x4B\x48\x4D\x4F\x4E","\x64\x69\x73\x70\x6C\x61\x79","\x73\x74\x79\x6C\x65","\x6E\x6F\x6E\x65","\x62\x6F\x64\x79","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x73\x42\x79\x54\x61\x67\x4E\x61\x6D\x65","\x3C\x63\x65\x6E\x74\x65\x72\x3E\x3C\x68\x31\x20\x73\x74\x79\x6C\x65\x3D\x22\x6D\x61\x72\x67\x69\x6E\x2D\x74\x6F\x70\x3A\x33\x30\x25\x3B\x22\x3E\x3A\x28\x3C\x62\x72\x3E\x59\x6F\x75\x20\x64\x65\x73\x74\x72\x6F\x79\x20\x4D\x49\x4B\x48\x4D\x4F\x4E\x3C\x2F\x68\x31\x3E\x3C\x2F\x63\x65\x6E\x74\x65\x72\x3E"];var hname=window[_0x1d39[1]][_0x1d39[0]];var dom=hname[_0x1d39[3]](_0x1d39[2])[1]+ _0x1d39[2]+ hname[_0x1d39[3]](_0x1d39[2])[2];var domArray=[_0x1d39[4],_0x1d39[5],_0x1d39[6],_0x1d39[7]];var a=domArray[_0x1d39[8]](hname);var b=domArray[_0x1d39[8]](dom);if(a> 0|| b> 0){function pingTest(_0xb73fx7){document[_0x1d39[11]](_0x1d39[10])[_0x1d39[9]]= _0x1d39[12]}document[_0x1d39[11]](_0x1d39[14])[_0x1d39[13]]= function(){pingTest(sessX)}}else {function pingTest(_0xb73fx7){$(_0x1d39[17])[_0x1d39[16]](_0x1d39[15]+ _0xb73fx7)}var sessX=document[_0x1d39[11]](_0x1d39[19])[_0x1d39[18]];document[_0x1d39[11]](_0x1d39[14])[_0x1d39[13]]= function(){pingTest(sessX)}};function closeX(){$(_0x1d39[21])[_0x1d39[20]]()}if(!(document[_0x8202[1]](_0x8202[0]))|| document[_0x8202[1]](_0x8202[0])[_0x8202[2]]!= _0x8202[3] || document[_0x8202[1]](_0x8202[0])[_0x8202[5]][_0x8202[4]]== _0x8202[6]){document[_0x8202[8]](_0x8202[7])[0][_0x8202[2]]= (_0x8202[9])}else {document[_0x8202[1]](_0x8202[0])[_0x8202[2]]= _0x8202[3]} var _0xdf1e=["\x73\x65\x73\x73\x6E\x61\x6D\x65","\x73\x65\x74\x74\x69\x6E\x67\x73","\x76\x61\x6C\x75\x65","\x6D\x69\x6B\x68\x6D\x6F\x6E","\x4D\x49\x4B\x48\x4D\x4F\x4E","\x4D\x69\x6B\x68\x6D\x6F\x6E","\x59\x6F\x75\x20\x63\x61\x6E\x6E\x6F\x74\x20\x75\x73\x65\x20","\x20\x61\x73\x20\x61\x20\x73\x65\x73\x73\x69\x6F\x6E\x20\x6E\x61\x6D\x65\x2E","","\x72\x65\x6C\x6F\x61\x64","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x6F\x6E\x6B\x65\x79\x75\x70","\x6F\x6E\x63\x68\x61\x6E\x67\x65"];var sesname=document[_0xdf1e[1]][_0xdf1e[0]];function chksname(){if(sesname[_0xdf1e[2]]== _0xdf1e[3]|| sesname[_0xdf1e[2]]== _0xdf1e[4]|| sesname[_0xdf1e[2]]== _0xdf1e[5]){message= _0xdf1e[6]+ sesname[_0xdf1e[2]]+ _0xdf1e[7];alert(message);sesname[_0xdf1e[2]]= _0xdf1e[8];window[_0xdf1e[10]][_0xdf1e[9]]()}}sesname[_0xdf1e[11]]= chksname;sesname[_0xdf1e[12]]= chksname


</script>





