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
        <div class="box box-bordered" style="margin-bottom: 18px; padding: 14px 16px; border-radius: 8px;">
          <div style="font-size: 13px; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(128,128,128,0.2); padding-bottom: 8px;">
            <span><i class="fa fa-key text-yellow"></i> <?= $_active_license ?? "Lisensi Mikhmon Standalone" ?></span>
            <button type="button" onclick="openDesktopLicenseModal()" class="btn btn-sm bg-secondary" style="padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer;" title="Ganti Lisensi">
              <i class="fa fa-pencil text-primary"></i> <?= $_change_license ?? "Ganti" ?>
            </button>
          </div>
          <div style="font-size: 12px; line-height: 1.8;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="opacity: 0.8;"><?= $_license_key ?? "Kunci Lisensi" ?>:</span>
              <span style="display: flex; align-items: center; gap: 4px;">
                <code id="sessionActiveKeyText" style="font-size: 11px; font-weight: bold; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($licenseKey ?: 'STANDALONE-FREE-TIER'); ?></code>
                <button type="button" onclick="copySessionLicenseKey()" class="btn btn-default btn-xs" style="padding: 0 4px; font-size: 10px;" title="Salin Kunci">
                  <i class="fa fa-copy"></i>
                </button>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="opacity: 0.8;"><?= $_license_type ?? "Tipe Lisensi" ?>:</span>
              <strong><?= $isTrial ? ($_trial_7_days ?? 'FREE (Trial Mode)') : ($_official_paid ?? 'OFFICIAL PAID (Pro)') ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="opacity: 0.8;"><?= $_validity ?? "Masa Aktif" ?>:</span>
              <strong class="<?= $isTrial ? 'text-yellow' : 'text-green' ?>">
                <?= htmlspecialchars($expiryDateText) ?>
                <?php if ($isTrial && $remainingDays > 0): ?>
                  <span style="font-size: 11px; font-weight: normal; opacity: 0.85;">(<?= $remainingDays ?> <?= $_days_remaining ?? "hari lagi" ?>)</span>
                <?php endif; ?>
              </strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="opacity: 0.8;">Router &amp; Voucher:</span>
              <strong class="text-green">Unlimited (Tanpa Batas Kuota)</strong>
            </div>
          </div>
          <div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid rgba(128,128,128,0.15); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 10.5px; opacity: 0.7; font-family: monospace;">HWID: <?= htmlspecialchars($hwid ?: 'STANDALONE') ?></span>
            <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="font-size: 11.5px; font-weight: bold; text-decoration: underline;">
              <i class="fa fa-external-link"></i> <?= $_manage_in_nodera ?? "Portal Lisensi" ?> &rarr;
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

<!-- Modal Aktivasi / Ganti Lisensi (Native Mikhmon Dark Card) -->
<!-- ── MODAL AKTIVASI / GANTI LISENSI (NATIVE MIKHMON THEME) ── -->
<div id="ndrDesktopLicenseModal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.65); justify-content:center; align-items:center;">
  <div class="card" style="width:92%; max-width:480px; box-shadow:0 10px 30px rgba(0,0,0,0.5); border-radius:4px; margin:0; padding:0; overflow:hidden;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px;">
      <h3 class="card-title" style="margin:0; font-size:15px;">
        <i class="fa fa-key text-primary"></i> Aktivasi / Ganti Lisensi
      </h3>
      <button type="button" onclick="closeDesktopLicenseModal()" style="background:none; border:none; color:inherit; font-size:20px; line-height:1; cursor:pointer; opacity:0.8;" title="Tutup">&times;</button>
    </div>
    <div class="card-body" style="padding:15px;">
      <div id="ndrLicenseAlert" style="display:none; padding:8px 12px; border-radius:3px; font-size:12px; margin-bottom:12px;"></div>

      <!-- HWID Field -->
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;">Hardware ID (HWID Perangkat Ini):</label>
        <div style="display:flex; gap:5px;">
          <input type="text" id="ndrDeviceHwid" value="<?= htmlspecialchars($hwid ?: 'STANDALONE') ?>" readonly class="form-control" style="flex:1; font-family:monospace; font-size:12.5px; font-weight:bold;">
          <button type="button" onclick="copyHwid()" class="btn bg-secondary" style="margin:0; padding:5px 12px;" title="Salin HWID">
            <i class="fa fa-copy" id="copyHwidIcon"></i> Salin
          </button>
        </div>
      </div>

      <!-- License Key Field -->
      <div style="margin-bottom:10px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;">Kode Kunci Lisensi (License Key):</label>
        <input type="text" id="ndrLicenseKeyInput" name="license_key" value="<?= htmlspecialchars($licenseKey) ?>" placeholder="Contoh: NDR-STD-XXXX-XXXX-XXXX-XXXX" class="form-control" style="font-family:monospace; font-size:12.5px; font-weight:bold;">
      </div>

      <!-- Box Info Native Mikhmon -->
      <div class="box box-bordered" style="margin:8px 0 15px 0; padding:8px 10px; font-size:11.5px; line-height:1.45;">
        <i class="fa fa-info-circle text-primary"></i> Masukkan kunci lisensi dari <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="text-decoration:underline;"><b>Portal Cloud NODERA</b></a> atau hubungi bantuan <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20butuh%20bantuan%20aktivasi%20lisensi%20Mikhmon%20HWID:%20<?= urlencode($hwid ?: 'STANDALONE') ?>" target="_blank" class="text-green" style="text-decoration:underline;"><b><i class="fa fa-whatsapp"></i> CS WhatsApp (085155173547)</b></a>. Kosongkan untuk Free Trial.
      </div>

      <!-- Action Buttons -->
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
        <button type="button" onclick="closeDesktopLicenseModal()" class="btn bg-secondary" style="margin:0;">Batal</button>
        <button type="button" id="ndrBtnSubmitLicense" onclick="submitDesktopLicense()" class="btn bg-primary" style="margin:0;">
          <i class="fa fa-check"></i> Simpan &amp; Verifikasi
        </button>
      </div>
    </div>
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
function copySessionLicenseKey() {
  var el = document.getElementById('sessionActiveKeyText');
  if (el) {
    var txt = el.innerText.trim();
    navigator.clipboard.writeText(txt).then(function() {
      alert('Kunci lisensi berhasil disalin: ' + txt);
    }).catch(function() {
      alert('Kunci lisensi: ' + txt);
    });
  }
}
function copyHwid() {
  var h = document.getElementById("ndrDeviceHwid");
  if (h) {
    h.select();
    document.execCommand("copy");
    alert("Hardware ID berhasil disalin ke clipboard: " + h.value);
  }
}

function submitDesktopLicense() {
  var key = document.getElementById("ndrLicenseKeyInput").value.trim();
  var alertBox = document.getElementById("ndrLicenseAlert");
  var btn = document.getElementById("ndrBtnSubmitLicense");
  if (!key) {
    showError("License Key wajib diisi!");
    return;
  }
  btn.disabled = true;
  btn.innerHTML = "<i class=\"fa fa-spinner fa-spin\"></i> Menyimpan...";
  if (alertBox) alertBox.style.display = "none";

  var fd = new FormData();
  fd.append("action", "save_license");
  fd.append("license_key", key);

  fetch("./admin.php?id=update&action=save_license", {
    method: "POST",
    body: fd
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data && data.success) {
      showSuccessAndReload(data);
    } else {
      showError(data ? data.message : "Gagal menyimpan lisensi.");
    }
  })
  .catch(function(err) {
    showError("Terjadi kesalahan: " + err.message);
  });

  function showSuccessAndReload(data) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = "<i class=\"fa fa-check-circle\"></i> Berhasil";
    }
    if (alertBox) {
      alertBox.className = "box bg-green";
      alertBox.style.cssText = "display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;";
      alertBox.innerHTML = "<i class=\"fa fa-check\"></i> " + (data.message || "Lisensi berhasil disimpan & diaktifkan!");
    }
    setTimeout(function() {
      location.reload();
    }, 1200);
  }

  function showError(msg) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = "<i class=\"fa fa-check\"></i> Simpan &amp; Verifikasi";
    }
    if (alertBox) {
      alertBox.className = "box bg-danger";
      alertBox.style.cssText = "display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;";
      alertBox.innerHTML = "<i class=\"fa fa-ban\"></i> " + msg;
    }
  }
}

<?php if (!empty($isDesktop) && empty($isLicensed)): ?>
window.addEventListener("DOMContentLoaded", function() {
  setTimeout(function() {
    openDesktopLicenseModal();
  }, 400);
});
<?php endif; ?>
</script>
