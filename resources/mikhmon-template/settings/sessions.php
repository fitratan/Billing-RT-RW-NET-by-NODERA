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

  if (isset($_POST['save'])) {

    $suseradm = trim((string)($_POST['useradm'] ?? ''));
    $spassadm = mikhmon_encrypt((string)($_POST['passadm'] ?? ''));
    $logobt = ($_POST['logobt'] ?? '');
    $qrbt = ($_POST['qrbt'] ?? 'disable');

    $configFile = dirname(__DIR__) . '/include/config.php';
    $data = [];
    if (file_exists($configFile)) {
      include $configFile;
    }
    $data['mikhmon'] = array(
      '1' => 'mikhmon<|<' . $suseradm,
      '2' => 'mikhmon>|>' . $spassadm,
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

    $gen = '<?php $qrbt="' . addslashes($qrbt) . '";?>';
    $key = dirname(__DIR__) . '/include/quickbt.php';
    @file_put_contents($key, $gen);

    if (!headers_sent()) {
      header("Location: ./admin.php?id=sessions");
    }
    echo "<script>window.location='./admin.php?id=sessions'</script>";
    exit;
  }

}
?>
<script>
  function Pass(id){
    var x = document.getElementById(id);
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
</script>

<div class="row">
	<div class="col-12">
  	<div class="card">
  		<div class="card-header">
  			<div class="card-header-flex">
  				<h3 class="card-header-title"><i class="fa fa-gear"></i> <?= $_admin_settings ?></h3>
  				<div class="card-header-actions">
  					<span onclick="location.reload();" class="header-action-btn pointer" title="Reload data"><i class="fa fa-refresh"></i></span>
  				</div>
  			</div>
  		</div>
      <div class="card-body">
        <?php
        include_once __DIR__ . '/../include/license.php';
        $isDesktop = function_exists('mikhmon_is_desktop_mode') ? mikhmon_is_desktop_mode() : false;
        $isLicensed = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : false;
        $expiryDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
        $remainingDays = function_exists('mikhmon_remaining_days') ? mikhmon_remaining_days() : 999;
        $hwid = function_exists('mikhmon_get_hwid') ? mikhmon_get_hwid() : '';
        $licenseKey = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
        $productName = defined('MIKHMON_PRODUCT_NAME') ? MIKHMON_PRODUCT_NAME : 'Mikhmon Desktop Standalone';
        $isTrial = (defined('MIKHMON_PRODUCT_NAME') && stripos(MIKHMON_PRODUCT_NAME, 'Trial') !== false) || (strpos($licenseKey, 'NDR-TRL') === 0);

        if (!$isDesktop): ?>
        <div class="box box-bordered" style="margin-bottom: 16px; padding: 12px 18px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div style="font-size: 13px;">
            <i class="fa fa-calendar-check-o text-primary"></i> <strong><?= $_cloud_subscription_active ?? "Langganan Cloud Aktif" ?></strong> &mdash; <?= $_valid_until ?? "Masa Aktif s/d" ?> : <strong><?= htmlspecialchars($expiryDateText) ?></strong>
            <?php if ($remainingDays <= 30 && $remainingDays > 0): ?>
              <span style="font-size: 12px; opacity: 0.75; margin-left: 6px;">(<?= $remainingDays ?> <?= $_days_remaining ?? "hari lagi" ?>)</span>
            <?php endif; ?>
          </div>
          <div>
            <a href="https://panel.dgtlnetsolution.com" target="_blank" class="text-primary" style="font-size: 12px; font-weight: bold; text-decoration: underline;">
              <i class="fa fa-external-link"></i> <?= $_renew_at_nodera ?? "Perpanjang di NODERA" ?>
            </a>
          </div>
        </div>
        <?php else:
          if (!$isLicensed): ?>
        <div class="box box-bordered" style="margin-bottom: 16px; padding: 14px 18px; border-radius: 8px; border-left: 4px solid #e74c3c; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div>
            <div style="font-size: 14px; font-weight: bold; color: #e74c3c;">
              <i class="fa fa-exclamation-triangle"></i> <?= $_license_inactive ?? "Lisensi Desktop Belum Aktif" ?>
            </div>
            <div style="font-size: 12px; opacity: 0.85; margin-top: 3px;">
              <?= $_desktop_license_required ?? "Mikhmon Desktop memerlukan License Key resmi dari Cloud Panel NODERA." ?>
              <code style="display:inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 4px;">HWID: <?= htmlspecialchars($hwid) ?></code>
            </div>
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" onclick="openDesktopLicenseModal()" class="btn bg-primary" style="padding: 7px 16px; font-weight: bold; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
              <i class="fa fa-key"></i> <?= $_activate_license ?? "Aktivasi Lisensi" ?>
            </button>
            <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="font-size: 12px; font-weight: bold; text-decoration: underline;">
              <i class="fa fa-external-link"></i> <?= $_manage_in_nodera ?? "Dapatkan Lisensi" ?>
            </a>
          </div>
        </div>
        <?php else: ?>
        <div class="box box-bordered" style="margin-bottom: 16px; padding: 12px 18px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px;">
            <i class="fa fa-check-circle text-primary"></i>
            <span><strong><?= $_license_type ?? "Tipe" ?> :</strong> <?= $isTrial ? ($_trial_7_days ?? 'FREE (Trial 7 Hari)') : ($_official_paid ?? 'Official Paid') ?></span>
            <span>&bull;</span>
            <span><strong><?= $_active_license ?? "Lisensi" ?> :</strong> <code style="font-weight: 600; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($licenseKey ?: 'Official Desktop') ?></code></span>
            <span>&bull;</span>
            <span><strong><?= $_validity ?? "Masa Aktif" ?> :</strong> <?= htmlspecialchars($expiryDateText) ?></span>
            <?php if ($remainingDays <= 30 && $remainingDays > 0): ?>
              <span style="font-size: 12px; opacity: 0.75;">(<?= $remainingDays ?> <?= $_days_remaining ?? "hari lagi" ?>)</span>
            <?php endif; ?>
            <span style="display: inline-block; margin-left: 4px; font-size: 11.5px; opacity: 0.75; font-family: monospace;">(HWID: <?= htmlspecialchars($hwid) ?>)</span>
          </div>
          <div style="display: flex; gap: 12px; align-items: center;">
            <button type="button" onclick="openDesktopLicenseModal()" class="text-primary" style="background: none; border: none; padding: 0; font-size: 12px; font-weight: bold; text-decoration: underline; cursor: pointer;">
              <i class="fa fa-refresh"></i> <?= $_change_license ?? "Ganti Lisensi" ?>
            </button>
            <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="font-size: 12px; font-weight: bold; text-decoration: underline;">
              <i class="fa fa-external-link"></i> <?= $_manage_in_nodera ?? "Kelola di NODERA" ?>
            </a>
          </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="row">
          <div class="col-6">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-server"></i> <?= $_router_list ?></h3>
              </div>
            <div class="card-body">
            <div class="row">
              <?php
              // localization variables loaded via lang dictionary
              
              $locConfigFile = file_exists('./include/location_config.php') ? './include/location_config.php' : (file_exists(__DIR__ . '/../include/location_config.php') ? __DIR__ . '/../include/location_config.php' : '');
              $location_data = [];
              if (!empty($locConfigFile) && file_exists($locConfigFile)) {
                include $locConfigFile;
              }

              $allSessions = [];

              // 1. From in-memory $data array
              if (isset($data) && is_array($data)) {
                foreach ($data as $k => $v) {
                  if ($k !== 'mikhmon' && !empty($k)) {
                    $hsName = '';
                    if (is_array($v) && isset($v[4])) {
                      $hsParts = explode('%', $v[4]);
                      $hsName = $hsParts[1] ?? '';
                    }
                    $allSessions[$k] = $hsName ?: $k;
                  }
                }
              }

              // 2. From file scan of config.php (handles multiline, short array, single & double quotes, whitespace)
              $cfgFile = file_exists('./include/config.php') ? './include/config.php' : (file_exists(__DIR__ . '/../include/config.php') ? __DIR__ . '/../include/config.php' : '');
              if (!empty($cfgFile) && file_exists($cfgFile)) {
                $cfgContent = @file_get_contents($cfgFile);
                if (!empty($cfgContent)) {
                  if (preg_match_all('/\$data\[\s*[\'"]([^\'"]+)[\'"]\s*\]\s*=\s*(?:array\s*\(|\[)(.*?)(?:\);|\];)/is', $cfgContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                      $sName = trim($m[1]);
                      if ($sName !== 'mikhmon' && !empty($sName)) {
                        $hsName = '';
                        if (preg_match('/%([^%\'"]*)/', $m[2], $hsm)) {
                          $hsName = trim($hsm[1]);
                        }
                        if (!isset($allSessions[$sName])) {
                          $allSessions[$sName] = $hsName ?: $sName;
                        }
                      }
                    }
                  }
                  foreach (explode("\n", $cfgContent) as $line) {
                    if (preg_match('/\$data\[\s*[\'"]([^\'"]+)[\'"]\s*\]/i', $line, $lm)) {
                      $sName = trim($lm[1]);
                      if ($sName !== 'mikhmon' && !empty($sName) && !isset($allSessions[$sName])) {
                        $hsName = '';
                        if (preg_match('/%([^%\'"]*)/', $line, $hsm)) {
                          $hsName = trim($hsm[1]);
                        }
                        $allSessions[$sName] = $hsName ?: $sName;
                      }
                    }
                  }
                }
              }

              // 3. Fallback check for location_config entries
              if (isset($location_data['locations']) && is_array($location_data['locations'])) {
                foreach ($location_data['locations'] as $locKey => $locVal) {
                  if ($locKey !== 'mikhmon' && !empty($locKey) && !isset($allSessions[$locKey])) {
                    $allSessions[$locKey] = $locVal;
                  }
                }
              }

              if (empty($allSessions)): ?>
                <div class="col-12 text-center" style="padding: 30px 15px;">
                  <i class="fa fa-server" style="font-size: 36px; color: #94a3b8; margin-bottom: 12px; display: block;"></i>
                  <p style="color: #64748b; font-size: 13px; margin-bottom: 15px;">
                    <?= $_no_router_added ?? 'Belum ada router MikroTik yang terhubung.' ?>
                  </p>
                  <a href="./admin.php?id=settings&router=new-<?= rand(1111,9999) ?>" class="btn btn-primary btn-sm" style="font-size: 12px; padding: 6px 16px; border-radius: 6px; text-decoration: none; display: inline-block;">
                    <i class="fa fa-plus"></i> <?= $_add_router_now ?? 'Tambah Router Sekarang' ?>
                  </a>
                </div>
              <?php else:
                foreach ($allSessions as $value => $detectedHsName) {
                  $hsName = '';
                  if (is_array($data) && isset($data[$value]) && is_array($data[$value]) && isset($data[$value][4])) {
                    $hsParts = explode('%', $data[$value][4]);
                    $hsName = trim($hsParts[1] ?? '');
                  }
                  if (empty($hsName) || strtolower($hsName) === 'dns') {
                    $hsName = (strtolower($detectedHsName) !== 'dns' && !empty($detectedHsName)) ? $detectedHsName : ucwords(str_replace(['-', '_'], ' ', $value));
                  }
                  $rawLoc = $location_data['locations'][$value] ?? '';
                  $locName = (!empty($rawLoc) && strtolower($rawLoc) !== 'dns')
                    ? $rawLoc 
                    : (!empty($hsName) && strtolower($hsName) !== 'dns' ? $hsName : ucwords(str_replace(['-', '_'], ' ', $value)));
                  $isPrimary = ($location_data['primary'] ?? '') === $value;
                  ?>
                    <div class="col-12">
                        <div class="box bmh-75 box-bordered <?= $color[rand(1, 11)]; ?>" style="position: relative;">
                                <?php if ($isPrimary): ?>
                                  <span class="badge bg-green" style="position: absolute; top: 8px; right: 10px; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 3px; box-shadow: 0 1px 2px rgba(0,0,0,0.25);">
                                    <i class="fa fa-star"></i> <?= $_primary ?? 'Utama' ?>
                                  </span>
                                <?php endif; ?>
                                <div class="box-group">
                                  
                                  <div class="box-group-icon">
                                    <span class="connect pointer" id="<?= htmlspecialchars($value); ?>">
                                    <i class="fa fa-server"></i>
                                    </span>
                                  </div>
                                
                                  <div class="box-group-area" style="padding-right: 70px;">
                                    <span>
                                      <?= $_hotspot_name ?> : <?= htmlspecialchars($hsName); ?><br>
                                      <?= $_session_name ?> : <?= htmlspecialchars($value); ?><br>
                                      <?= $_location ?? 'Lokasi' ?> : <?= htmlspecialchars($locName); ?><br>
                                      <span class="connect pointer"  id="<?= htmlspecialchars($value); ?>"><i class="fa fa-external-link"></i> <?= $_open ?></span>&nbsp;
                                      <a href="./admin.php?id=settings&session=<?= urlencode($value); ?>"><i class="fa fa-edit"></i> <?= $_edit ?></a>&nbsp;
                                      <a href="javascript:void(0)" onclick="if(confirm('<?= addslashes($_delete_confirm_data ?? 'Apakah Anda yakin ingin menghapus data') ?> <?= htmlspecialchars($value); ?><?= !empty($hsName) ? ' (' . htmlspecialchars($hsName) . ')' : '' ?> ?')){loadpage('./admin.php?id=remove-session&session=<?= urlencode($value); ?>')}else{}"><i class="fa fa-remove"></i> <?= $_delete ?></a>
                                    </span>

                                  </div>
                                </div>
                              
                            </div>
                          </div>
              <?php
                }
              endif;
              ?>
              </div>
            </div>
          </div>
        </div>
			    <div class="col-6">
          <form autocomplete="off" method="post" action="">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-user-circle"></i> <?= $_admin ?></h3>
              </div>
            <div class="card-body">
      <table class="table table-sm">
        <tr>
          <td class="align-middle"><?= $_user_name ?> </td><td><input class="form-control" id="useradm" type="text" size="10" name="useradm" title="User Admin" value="<?= $useradm; ?>" required="1"/></td>
        </tr>
        <tr>
          <td class="align-middle"><?= $_password ?> </td>
          <td>
          <div class="input-group">
          <div class="input-group-11 col-box-10">
                <input class="group-item group-item-l" id="passadm" type="password" size="10" name="passadm" title="Password Admin" value="<?= mikhmon_decrypt($passadm); ?>" required="1"/>
              </div>
                <div class="input-group-1 col-box-2">
                  <div class="group-item group-item-r pd-2p5 text-center align-middle">
                      <input title="Show/Hide Password" type="checkbox" onclick="Pass('passadm')">
                  </div>
                </div>
            </div>
          </td>
        </tr>
        <tr>
          <td class="align-middle"><?= $_quick_print ?> QR</td>
          <td>
            <select class="form-control" name="qrbt">
            <option><?= $qrbt ?></option>
              <option>enable</option>
              <option>disable</option>
            </select>
          </td>
        </tr>
        <tr>
          <td></td><td class="text-right">
              <div class="input-group-4">
                  <input class="group-item group-item-l" type="submit" style="cursor: pointer;" name="save" value="<?= $_save ?>"/>
                </div>
                <div class="input-group-2">
                  <div style="cursor: pointer;" class="group-item group-item-r pd-2p5 text-center" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
                </div>
                </div>
          </td>
        </tr>
        
      </table>
      <div id="loadV">v<?= $_SESSION['v']; ?> </div>
      <div><b id="newVer" class="text-green"></b></div>
    </div>
    </div>
    </form>
  </div>
</div>
</div>
</div>
</div>
</div>

<!-- Modal Aktivasi Lisensi Desktop NODERA -->
<div id="ndrDesktopLicenseModal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.65); backdrop-filter:blur(5px); justify-content:center; align-items:center;">
  <div style="background:#ffffff; color:#333; max-width:480px; width:92%; border-radius:12px; box-shadow:0 15px 35px rgba(0,0,0,0.3); overflow:hidden; border:1px solid rgba(0,0,0,0.1); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <div style="background:#052A4E; color:#ffffff; padding:18px 22px; display:flex; align-items:center; justify-content:space-between;">
      <div style="display:flex; align-items:center; gap:10px;">
        <i class="fa fa-shield" style="font-size:22px; color:#38bdf8;"></i>
        <div>
          <div style="font-size:15px; font-weight:700; letter-spacing:0.3px;"><?= $_desktop_license_activation ?? "Aktivasi Lisensi Desktop"; ?></div>
          <div style="font-size:11px; opacity:0.8; text-transform:uppercase; letter-spacing:0.5px;">NODERA Digital Network</div>
        </div>
      </div>
      <button type="button" onclick="closeDesktopLicenseModal()" style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer; opacity:0.8;">&times;</button>
    </div>
    <div style="padding:22px;">
      <div id="ndrLicenseAlert" style="display:none; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:15px;"></div>
      
      <div style="margin-bottom:14px;">
        <label style="display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:5px;"><?= $_hwid_this_device ?? "Hardware ID (HWID Perangkat Ini):"; ?></label>
        <div style="display:flex; gap:6px;">
          <input type="text" id="ndrDeviceHwid" readonly value="<?= htmlspecialchars($hwid) ?>" style="flex:1; padding:8px 12px; font-family:monospace; font-size:12px; background:#f4f6f8; border:1px solid #d1d5db; border-radius:6px; color:#374151;">
          <button type="button" onclick="copyHwid()" style="padding:8px 14px; background:#e5e7eb; border:1px solid #d1d5db; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; color:#374151;">
            <i class="fa fa-copy"></i> <?= $_copy ?? "Salin"; ?>
          </button>
        </div>
      </div>

      <div style="margin-bottom:18px;">
        <label for="ndrLicenseKeyInput" style="display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:5px;">License Key:</label>
        <input type="text" id="ndrLicenseKeyInput" placeholder="NDR-MKH-XXXX-XXXX-XXXX" value="<?= htmlspecialchars($licenseKey) ?>" style="width:100%; padding:10px 12px; font-family:monospace; font-size:13px; border:1px solid #0284c7; border-radius:6px; outline:none; box-sizing:border-box;">
        <div style="font-size:11.5px; color:#6b7280; margin-top:4px;">
          <?= $_enter_license_key_hint ?? "Masukkan License Key resmi yang Anda peroleh dari Cloud Panel NODERA."; ?>
        </div>
      </div>

      <div style="display:flex; flex-direction:column; gap:8px;">
        <button type="button" id="ndrBtnSubmitLicense" onclick="submitDesktopLicense()" style="width:100%; padding:11px; background:#052A4E; color:#ffffff; font-size:13.5px; font-weight:700; border:none; border-radius:6px; cursor:pointer; box-shadow:0 3px 8px rgba(5,42,78,0.25); display:flex; align-items:center; justify-content:center; gap:8px;">
          <i class="fa fa-check-circle"></i> <?= $_activate_now ?? "Aktivasi Sekarang"; ?>
        </button>
        <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" style="text-align:center; font-size:12.5px; color:#0284c7; font-weight:600; text-decoration:none; padding:4px;">
          <i class="fa fa-shopping-cart"></i> <?= $_buy_manage_license_cloud ?? "Beli / Dapatkan Lisensi di Cloud Panel" ?> &rarr;
        </a>
        <div style="display:flex; flex-direction:column; gap:4px; margin-top:4px; border-top:1px dashed #e5e7eb; padding-top:6px;">
          <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA%20Indonesia,%20saya%20butuh%20bantuan%20aktivasi%20Lisensi%20Mikhmon%20Desktop%20HWID:%20<?= urlencode($hwid ?: 'STANDALONE') ?>" target="_blank" style="text-align:center; font-size:11.5px; color:#16a34a; font-weight:600; text-decoration:none; padding:3px; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
            <i class="fa fa-whatsapp" style="font-size:14px;"></i> 🇮🇩 CS Indonesia (0851-5517-3547)
          </a>
          <a href="https://wa.me/2550788222699?text=Bonjour%20Support%20NODERA%20C%C3%B4te%20d%27Ivoire,%20j%27ai%20besoin%20d%27aide%20pour%20activer%20la%20licence%20Mikhmon%20Desktop%20HWID:%20<?= urlencode($hwid ?: 'STANDALONE') ?>" target="_blank" style="text-align:center; font-size:11.5px; color:#16a34a; font-weight:600; text-decoration:none; padding:3px; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
            <i class="fa fa-whatsapp" style="font-size:14px;"></i> 🇨🇮 CS Côte d'Ivoire (+255 07 88 22 26 99)
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function openDesktopLicenseModal() {
  var m = document.getElementById('ndrDesktopLicenseModal');
  if (m) m.style.display = 'flex';
}
function closeDesktopLicenseModal() {
  var m = document.getElementById('ndrDesktopLicenseModal');
  if (m) m.style.display = 'none';
}
function copyHwid() {
  var h = document.getElementById('ndrDeviceHwid');
  if (h) {
    h.select();
    document.execCommand('copy');
    alert((<?= json_encode($_hwid_copied ?? 'Hardware ID berhasil disalin ke clipboard!') ?>) + ' ' + h.value);
  }
}
function submitDesktopLicense() {
  var key = document.getElementById('ndrLicenseKeyInput').value.trim();
  var hwidEl = document.getElementById('ndrDeviceHwid');
  var hwid = (hwidEl ? hwidEl.value.trim() : '');
  var alertBox = document.getElementById('ndrLicenseAlert');
  var btn = document.getElementById('ndrBtnSubmitLicense');
  if (!key) {
    alertBox.style.display = 'block';
    alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
    alertBox.style.color = '#dc2626';
    alertBox.style.border = '1px solid #f87171';
    alertBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + <?= json_encode($_license_key_required ?? 'License Key wajib diisi!') ?>;
    return;
  }
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($_connecting_to_cloud ?? 'Menghubungkan ke Cloud Server...') ?>;
  alertBox.style.display = 'none';

  // 1. Coba via PHP lokal
  var fd = new FormData();
  fd.append('action', 'ajax_activate_desktop_license');
  fd.append('license_key', key);

  fetch('./admin.php', {
    method: 'POST',
    body: fd
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data && data.success) {
      showSuccessAndReload(data);
    } else {
      tryDirectBrowserActivation(key, hwid);
    }
  })
  .catch(function() {
    tryDirectBrowserActivation(key, hwid);
  });

  function tryDirectBrowserActivation(licenseKey, deviceHwid) {
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($_validating_cloud_direct ?? 'Validasi Cloud Direct HTTPS...') ?>;
    fetch('https://panel.dgtlnetsolution.com/api/v1/desktop-license/activate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        license_key: licenseKey,
        hwid: deviceHwid || 'DESKTOP-AUTO',
        device_name: window.navigator.userAgent || 'Windows PC'
      })
    })
    .then(function(res) { return res.json(); })
    .then(function(cloudRes) {
      if (cloudRes && cloudRes.success && cloudRes.data) {
        var saveFd = new FormData();
        saveFd.append('action', 'ajax_save_activated_license');
        saveFd.append('data', JSON.stringify(cloudRes.data));
        fetch('./admin.php', { method: 'POST', body: saveFd })
        .then(function() {
          showSuccessAndReload(cloudRes);
        })
        .catch(function() {
          showSuccessAndReload(cloudRes);
        });
      } else {
        showError(cloudRes ? cloudRes.message : <?= json_encode($_license_activation_failed ?? 'Aktivasi lisensi gagal. Periksa kembali License Key Anda.') ?>);
      }
    })
    .catch(function(err) {
      showError((<?= json_encode($_failed_connect_cloud_license ?? 'Gagal terhubung ke Cloud License Server: ') ?>) + err.message);
    });
  }

  function showSuccessAndReload(data) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check-circle"></i> <?= $_activate_now ?? "Aktivasi Sekarang"; ?>';
    alertBox.style.display = 'block';
    alertBox.style.background = 'rgba(34, 197, 94, 0.15)';
    alertBox.style.color = '#16a34a';
    alertBox.style.border = '1px solid #86efac';
    alertBox.innerHTML = '<i class="fa fa-check"></i> ' + (data.message || 'Lisensi berhasil diaktivasi!') + '<br><small>Masa aktif s/d: ' + (data.expires_at || (data.data && data.data.expires_at) || '-') + '</small>';
    setTimeout(function() {
      location.reload();
    }, 1500);
  }

  function showError(msg) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check-circle"></i> <?= $_activate_now ?? "Aktivasi Sekarang"; ?>';
    alertBox.style.display = 'block';
    alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
    alertBox.style.color = '#dc2626';
    alertBox.style.border = '1px solid #f87171';
    alertBox.innerHTML = '<i class="fa fa-times-circle"></i> ' + msg;
  }
}

<?php if ($isDesktop && !$isLicensed): ?>
window.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    openDesktopLicenseModal();
  }, 400);
});
<?php endif; ?>
</script>
<script>
  var _0x7470=["\x68\x6F\x73\x74\x6E\x61\x6D\x65","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x2E","\x73\x70\x6C\x69\x74","\x6D\x69\x6B\x68\x6D\x6F\x6E\x2E\x6F\x6E\x6C\x69\x6E\x65","\x78\x62\x61\x6E\x2E\x78\x79\x7A","\x6C\x6F\x67\x61\x6D\x2E\x69\x64","\x6D\x69\x6E\x69\x73\x2E\x69\x64","\x69\x6E\x64\x65\x78\x4F\x66","\x3C\x73\x70\x61\x6E\x20\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x77\x68\x69\x74\x65\x20\x66\x61\x20\x66\x61\x2D\x69\x6E\x66\x6F\x2D\x63\x69\x72\x63\x6C\x65\x22\x3E\x3C\x2F\x69\x3E\x20\x3C\x61\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x62\x6C\x75\x65\x22\x20\x68\x72\x65\x66\x3D\x22\x2E\x2F\x61\x64\x6D\x69\x6E\x2E\x70\x68\x70\x3F\x69\x64\x3D\x61\x62\x6F\x75\x74\x22\x3E\x43\x68\x65\x63\x6B\x20\x55\x70\x64\x61\x74\x65\x3C\x2F\x61\x3E\x3C\x2F\x73\x70\x61\x6E\x3E","\x68\x74\x6D\x6C","\x23\x6E\x65\x77\x56\x65\x72","\x68\x74\x74\x70\x73\x3A\x2F\x2F\x72\x61\x77\x2E\x67\x69\x74\x68\x75\x62\x75\x73\x65\x72\x63\x6F\x6E\x74\x65\x6E\x74\x2E\x63\x6F\x6D\x2F\x6C\x61\x6B\x73\x61\x31\x39\x2F\x6D\x69\x6B\x68\x6D\x6F\x6E\x76\x33\x2F\x6D\x61\x73\x74\x65\x72\x2F\x76\x65\x72\x73\x6F\x6E\x2E\x74\x78\x74\x3F\x74\x3D","\x72\x61\x6E\x64\x6F\x6D","\x66\x6C\x6F\x6F\x72","\x76","\x76\x65\x72\x73\x69\x6F\x6E","","\x72\x65\x70\x6C\x61\x63\x65","\x69\x6E\x6E\x65\x72\x48\x54\x4D\x4C","\x6C\x6F\x61\x64\x56","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x20","\x75\x70\x64\x61\x74\x65\x64","\x2D","\x4E\x65\x77\x20\x56\x65\x72\x73\x69\x6F\x6E\x20","\x3C\x62\x72\x3E\x3C\x73\x70\x61\x6E\x20\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x77\x68\x69\x74\x65\x20\x66\x61\x20\x66\x61\x2D\x69\x6E\x66\x6F\x2D\x63\x69\x72\x63\x6C\x65\x22\x3E\x3C\x2F\x69\x3E\x20\x3C\x61\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x62\x6C\x75\x65\x22\x20\x68\x72\x65\x66\x3D\x22\x2E\x2F\x61\x64\x6D\x69\x6E\x2E\x70\x68\x70\x3F\x69\x64\x3D\x61\x62\x6F\x75\x74\x22\x3E\x43\x68\x65\x63\x6B\x20\x55\x70\x64\x61\x74\x65\x3C\x2F\x61\x3E\x3C\x2F\x73\x70\x61\x6E\x3E","\x67\x65\x74\x4A\x53\x4F\x4E"];var hname=window[_0x7470[1]][_0x7470[0]];var dom=hname[_0x7470[3]](_0x7470[2])[1]+ _0x7470[2]+ hname[_0x7470[3]](_0x7470[2])[2];var domArray=[_0x7470[4],_0x7470[5],_0x7470[6],_0x7470[7]];var a=domArray[_0x7470[8]](hname);var b=domArray[_0x7470[8]](dom);if(dom== _0x7470[4]){$(_0x7470[11])[_0x7470[10]](_0x7470[9])}else {if(a> 0|| b> 0){}else {$[_0x7470[27]](_0x7470[12]+ (Math[_0x7470[14]]((Math[_0x7470[13]]()* 999999999)+ 1))* 128,function(_0xc1b4x6){getNewVer= (_0xc1b4x6[_0x7470[16]])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4x7=parseInt(getNewVer[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4x8=document[_0x7470[21]](_0x7470[20])[_0x7470[19]];var _0xc1b4x9=(_0xc1b4x8[_0x7470[3]](_0x7470[22])[0])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4xa=parseInt(_0xc1b4x9[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4xb=(_0xc1b4x7- _0xc1b4xa);getNewVer= (_0xc1b4x6[_0x7470[16]])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4x7=parseInt(getNewVer[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4x8=document[_0x7470[21]](_0x7470[20])[_0x7470[19]];var _0xc1b4x9=(_0xc1b4x8[_0x7470[3]](_0x7470[22])[0])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4xa=parseInt(_0xc1b4x9[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4xb=(_0xc1b4x7- _0xc1b4xa);getNewD= (_0xc1b4x6[_0x7470[23]])[_0x7470[3]](_0x7470[22])[0];newD= parseInt((getNewD)[_0x7470[3]](_0x7470[24])[2]+ (getNewD)[_0x7470[3]](_0x7470[24])[0]+ (getNewD)[_0x7470[3]](_0x7470[24])[1]);var _0xc1b4xc=parseInt((_0xc1b4x8[_0x7470[3]](_0x7470[22])[1])[_0x7470[3]](_0x7470[24])[2]+ (_0xc1b4x8[_0x7470[3]](_0x7470[22])[1])[_0x7470[3]](_0x7470[24])[0]+ (_0xc1b4x8[_0x7470[3]](_0x7470[22])[1][_0x7470[3]](_0x7470[24]))[1]);var _0xc1b4xd=(newD- _0xc1b4xc);if(_0xc1b4xb> 0|| _0xc1b4xd> 0){$(_0x7470[11])[_0x7470[10]](_0x7470[25]+ _0xc1b4x6[_0x7470[16]]+ _0x7470[22]+ _0xc1b4x6[_0x7470[23]]+ _0x7470[26])}})}}
</script>









