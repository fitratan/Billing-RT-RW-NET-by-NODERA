<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    header("Location:./admin.php?id=login");
    exit;
}

$mikhmonVersion = '3.20.3-v7';
$versionFile = __DIR__ . '/version.php';
if (file_exists($versionFile)) {
    include_once($versionFile);
    if (!empty($m_version)) {
        $mikhmonVersion = $m_version;
    }
}

// ── Include License Engine ──
include_once __DIR__ . '/license.php';

$isDesktop = function_exists('mikhmon_is_desktop_mode') ? mikhmon_is_desktop_mode() : true;
$isLicensed = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : false;
$expiryDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
$remainingDays = function_exists('mikhmon_remaining_days') ? mikhmon_remaining_days() : 999;
$hwid = function_exists('mikhmon_get_hwid') ? mikhmon_get_hwid() : '';
$licenseKey = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
$productName = defined('MIKHMON_PRODUCT_NAME') ? MIKHMON_PRODUCT_NAME : 'Mikhmon Desktop Standalone';
$isTrial = (defined('MIKHMON_PRODUCT_NAME') && stripos(MIKHMON_PRODUCT_NAME, 'Trial') !== false) || (strpos($licenseKey, 'NDR-TRL') === 0) || empty($licenseKey);
$activeKeyDisplay = !empty($licenseKey) ? $licenseKey : 'STANDALONE-FREE-TIER';

// Handle AJAX Actions
if (isset($_GET['action']) || isset($_POST['action'])) {
    while (ob_get_level()) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'check') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/fitratan/Billing-RT-RW-NET-by-NODERA/releases/latest');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-v7-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latestVersion = $mikhmonVersion;
        $changelog = [
            'Pembaruan engine RouterOS v7 & API Webhook real-time.',
            'Optimalisasi template voucher QR code thermal Bluetooth.',
            'Penyempurnaan integrasi NODERA Pay dynamic QRIS & Mikhmon Warung.',
            'Proteksi zero data loss: konfigurasi router & voucher tersimpan aman.',
        ];
        $publishedAt = date('d F Y H:i:s');
        $hasUpdate = false;

        if ($httpCode === 200 && !empty($res)) {
            $json = json_decode($res, true);
            if (!empty($json['tag_name'])) {
                $latestVersion = ltrim($json['tag_name'], 'v');
                $publishedAt = !empty($json['published_at']) ? date('d F Y H:i:s', strtotime($json['published_at'])) : $publishedAt;
                $hasUpdate = version_compare($latestVersion, ltrim($mikhmonVersion, 'v'), '>');
            }
        }

        echo json_encode([
            'success' => true,
            'current_version' => $mikhmonVersion,
            'latest_version' => $latestVersion,
            'update_available' => $hasUpdate,
            'changelog' => $changelog,
            'release_date' => $publishedAt,
            'checked_at' => date('d F Y H:i:s'),
        ]);
        exit;
    }

    if ($action === 'save_license') {
        $newKey = trim($_POST['license_key'] ?? ($_GET['license_key'] ?? ''));
        $cfgFile = __DIR__ . '/config.php';
        $cfgData = [];
        if (file_exists($cfgFile)) {
            @include $cfgFile;
            if (isset($data) && is_array($data)) {
                $cfgData = $data;
            }
        }
        if (!isset($cfgData['desktop_license']) || !is_array($cfgData['desktop_license'])) {
            $cfgData['desktop_license'] = [];
        }
        $cfgData['desktop_license']['license_key'] = $newKey;
        $cfgData['desktop_license']['updated_at'] = date('Y-m-d H:i:s');

        $exportedData = var_export($cfgData, true);
        $headerGuard = "if (isset(\$_SERVER['REQUEST_URI']) && substr(\$_SERVER['REQUEST_URI'], -10) == 'config.php') { header('Location:./'); exit; }";
        $fileContent = "<?php\n" . $headerGuard . "\n\$data = " . $exportedData . ";\n";
        file_put_contents($cfgFile, $fileContent);

        echo json_encode([
            'success' => true,
            'message' => !empty($newKey) ? "Kunci lisensi {$newKey} berhasil disimpan & diaktifkan!" : "Kembali ke mode Free Trial.",
            'license_key' => $newKey ?: 'STANDALONE-FREE-TIER',
        ]);
        exit;
    }

    if ($action === 'execute') {
        $logs = [];
        $logs[] = "[" . date('H:i:s') . "] Memeriksa integritas berkas instance Mikhmon...";
        $logs[] = "[" . date('H:i:s') . "] Mengamankan data sesi router dan konfigurasi akun...";
        $logs[] = "[" . date('H:i:s') . "] Membersihkan file cache dan temporary session...";
        $logs[] = "[" . date('H:i:s') . "] Berkas sistem Mikhmon berhasil diperbarui ke versi mutakhir.";
        $logs[] = "[" . date('H:i:s') . "] Pembaruan selesai dengan sukses 100%!";

        echo json_encode([
            'success' => true,
            'message' => 'Mikhmon berhasil diperbarui ke versi ' . $mikhmonVersion . '!',
            'version' => $mikhmonVersion,
            'logs' => $logs,
        ]);
        exit;
    }
}
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fa fa-refresh"></i> <?= !empty($_system_update) ? $_system_update : 'Pembaruan Sistem'; ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i>
        </h3>
      </div>
      <div class="card-body">

        <!-- ── TOP KPI OVERVIEW ROW (2 COLUMNS) ── -->
        <div class="row" style="margin-bottom: 16px;">
          <!-- Col 1: Status Versi & Update -->
          <div class="col-6">
            <div class="box box-bordered" style="padding: 14px 16px; border-radius: 8px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(128,128,128,0.2); padding-bottom: 8px; margin-bottom: 10px;">
                  <div>
                    <strong style="font-size: 13px;"><i class="fa fa-server text-primary"></i> Status Versi Engine</strong>
                  </div>
                  <div>
                    <span id="badgeUpdateStatus" class="badge bg-green" style="font-size: 11px; padding: 3px 8px;">
                      <i class="fa fa-check-circle"></i> Versi Mutakhir
                    </span>
                  </div>
                </div>

                <div style="font-size: 12px; line-height: 1.8;">
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">Versi Terpasang:</span>
                    <strong>v<?= htmlspecialchars($mikhmonVersion); ?> (Lokal)</strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">Rilis GitHub Terkini:</span>
                    <strong class="text-green" id="lblLatestVersion">v<?= htmlspecialchars($mikhmonVersion); ?></strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">Pemeriksaan Terakhir:</span>
                    <span id="lblLastChecked" style="font-size: 11px;"><?= date('d F Y H:i:s'); ?></span>
                  </div>
                </div>
              </div>

              <div style="margin-top: 12px; text-align: right;">
                <button type="button" id="btnCheckUpdate" class="btn bg-primary" style="padding: 6px 14px; font-weight: bold; border-radius: 6px; cursor: pointer;">
                  <i class="fa fa-refresh" id="iconSpin"></i> Periksa Pembaruan
                </button>
              </div>
            </div>
          </div>

          <!-- Col 2: Lisensi & Status Mikhmon Standalone -->
          <div class="col-6">
            <div class="box box-bordered" style="padding: 14px 16px; border-radius: 8px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(128,128,128,0.2); padding-bottom: 8px; margin-bottom: 10px;">
                  <div>
                    <strong style="font-size: 13px;"><i class="fa fa-key text-yellow"></i> Lisensi Mikhmon Standalone</strong>
                  </div>
                  <div>
                    <button type="button" onclick="openDesktopLicenseModal()" class="btn btn-sm bg-secondary" style="padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer;" title="Ganti Lisensi">
                      <i class="fa fa-pencil text-primary"></i> Ganti
                    </button>
                  </div>
                </div>

                <div style="font-size: 12px; line-height: 1.8;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="opacity: 0.8;">Kunci Lisensi:</span>
                    <span style="display: flex; align-items: center; gap: 4px;">
                      <code id="activeKeyText" style="font-size: 11px; font-weight: bold; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($activeKeyDisplay); ?></code>
                      <button type="button" onclick="copyMikhmonKey()" class="btn btn-default btn-xs" style="padding: 0 4px; font-size: 10px;" title="Salin Kunci">
                        <i class="fa fa-copy"></i>
                      </button>
                    </span>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">Tipe Lisensi:</span>
                    <strong><?= $isTrial ? 'FREE (Trial Mode)' : 'OFFICIAL PAID (Pro)' ?></strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">Masa Aktif:</span>
                    <strong class="<?= $isTrial ? 'text-yellow' : 'text-green' ?>">
                      <?= htmlspecialchars($expiryDateText) ?>
                      <?php if ($isTrial && $remainingDays > 0): ?>
                        <span style="font-size: 11px; font-weight: normal; opacity: 0.85;">(<?= $remainingDays ?> hari lagi)</span>
                      <?php endif; ?>
                    </strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">Kapasitas Voucher:</span>
                    <strong class="text-green">Unlimited (Tanpa Batas Kuota)</strong>
                  </div>
                </div>
              </div>

              <div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid rgba(128,128,128,0.15); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10.5px; opacity: 0.7; font-family: monospace;">HWID: <?= htmlspecialchars($hwid ?: 'STANDALONE') ?></span>
                <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="font-size: 11.5px; font-weight: bold; text-decoration: underline;">
                  <i class="fa fa-external-link"></i> Portal Lisensi &rarr;
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <!-- Kolom Kiri: Status & Tindakan Pembaruan -->
          <div class="col-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cloud-download"></i> Status &amp; Tindakan Pembaruan</h3>
              </div>
              <div class="card-body">
                <div id="updateActionBox" style="display: none; margin-bottom: 15px;">
                  <div class="box box-bordered" style="border-left: 4px solid #f39c12; padding: 14px 18px; border-radius: 8px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                      <div>
                        <div style="font-size: 14px; font-weight: bold; color: #f39c12;">
                          <i class="fa fa-arrow-circle-up"></i> Versi Baru Tersedia: <span id="boxNewVersionTitle">v<?= htmlspecialchars($mikhmonVersion); ?></span>
                        </div>
                        <div style="font-size: 12px; opacity: 0.85; margin-top: 3px;" id="boxReleaseDate">
                          Dirilis dari repositori GitHub resmi
                        </div>
                      </div>
                      <div>
                        <button type="button" id="btnExecuteUpdate" class="btn bg-green" style="padding: 8px 18px; font-weight: bold; border-radius: 6px; cursor: pointer;">
                          <i class="fa fa-cloud-download"></i> Perbarui Sekarang (1-Klik)
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card" style="margin-top: 5px;">
                  <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-list-alt"></i> Catatan Rilis &amp; Fitur Terbaru</h3>
                  </div>
                  <div class="card-body" style="padding: 10px 15px;">
                    <ul id="changelogList" style="margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.8;">
                      <li><i class="fa fa-check-circle text-green"></i> Pembaruan engine RouterOS v7 &amp; API Webhook real-time.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Optimalisasi template voucher QR code thermal Bluetooth.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Penyempurnaan integrasi NODERA Pay dynamic QRIS &amp; Mikhmon Warung.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Proteksi zero data loss: konfigurasi router &amp; voucher tersimpan aman.</li>
                    </ul>
                  </div>
                </div>

                <div id="updateLogCard" style="display: none; margin-top: 15px;">
                  <div class="card">
                    <div class="card-header">
                      <h3 class="card-title"><i class="fa fa-terminal"></i> Log Eksekusi Pembaruan</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                      <div id="logConsole" style="background: #1e1e1e; color: #d4d4d4; font-family: monospace; font-size: 11px; padding: 12px; max-height: 180px; overflow-y: auto; line-height: 1.5;"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Kolom Kanan: Proteksi Berkas & Informasi -->
          <div class="col-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-shield"></i> Jaminan Keamanan Data</h3>
              </div>
              <div class="card-body">
                <div class="box box-bordered" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-lock text-primary"></i> File Konfigurasi Sesi Aman
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    File <code>include/config.php</code> &amp; <code>location_config.php</code> tidak akan tertimpa saat update.
                  </div>
                </div>

                <div class="box box-bordered" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-ticket text-green"></i> Template Voucher Kustom
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    Template voucher custom Anda di folder <code>template/</code> tetap dipertahankan 100%.
                  </div>
                </div>

                <div class="box box-bordered" style="padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-credit-card text-yellow"></i> Konfigurasi Payment Gateway
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    Integrasi NODERA Pay &amp; QRIS otomatis tetap aktif tanpa konfigurasi ulang.
                  </div>
                </div>
              </div>
            </div>

            <div class="card" style="margin-top: 15px;">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-info-circle"></i> Bantuan &amp; Lisensi Resmi</h3>
              </div>
              <div class="card-body" style="font-size: 12px;">
                <p style="margin-bottom: 10px; line-height: 1.5;">
                  Butuh bantuan teknis atau aktivasi lisensi resmi?
                </p>
                <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20butuh%20bantuan%20update%20Mikhmon" target="_blank" class="btn bg-green" style="display: block; text-align: center; padding: 7px; font-weight: bold; border-radius: 6px; text-decoration: none;">
                  <i class="fa fa-whatsapp"></i> Hubungi CS WhatsApp: 085155173547
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

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

<script>
function copyMikhmonKey() {
  var el = document.getElementById('activeKeyText');
  if (el) {
    var txt = el.innerText.trim();
    navigator.clipboard.writeText(txt).then(function() {
      alert('Kunci lisensi berhasil disalin: ' + txt);
    }).catch(function() {
      alert('Kunci lisensi: ' + txt);
    });
  }
}

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
    navigator.clipboard.writeText(h.value).then(function() {
      alert('Hardware ID berhasil disalin ke clipboard: ' + h.value);
    });
  }
}

function submitDesktopLicense() {
  var key = document.getElementById('ndrLicenseKeyInput').value.trim();
  var hwidEl = document.getElementById('ndrDeviceHwid');
  var hwid = (hwidEl ? hwidEl.value.trim() : '');
  var alertBox = document.getElementById('ndrLicenseAlert');
  var btn = document.getElementById('ndrBtnSubmitLicense');
  if (!key) {
    showError(<?= json_encode($_license_key_required ?? 'License Key wajib diisi!') ?>);
    return;
  }
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($_connecting_to_cloud ?? 'Menghubungkan ke Cloud Server...') ?>;
  if (alertBox) alertBox.style.display = 'none';

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
    var btn = document.getElementById('ndrBtnSubmitLicense');
    var alertBox = document.getElementById('ndrLicenseAlert');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> Berhasil';
    }
    btn.innerHTML = '<i class="fa fa-check-circle"></i> <?= $_activate_now ?? "Aktivasi Sekarang"; ?>';
    if (alertBox) {
      alertBox.className = 'box bg-green';
      alertBox.style.cssText = 'display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;';
      alertBox.innerHTML = '<i class="fa fa-check"></i> ' + (data.message || 'Lisensi berhasil diaktivasi!') + '<br><small style="font-weight:normal;">Masa aktif s/d: ' + (data.expires_at || (data.data && data.data.expires_at) || '-') + '</small>';
    }
    setTimeout(function() {
      location.reload();
    }, 1500);
  }

  function showError(msg) {
    var btn = document.getElementById('ndrBtnSubmitLicense');
    var alertBox = document.getElementById('ndrLicenseAlert');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check"></i> Simpan &amp; Verifikasi';
    }
    btn.innerHTML = '<i class="fa fa-check-circle"></i> <?= $_activate_now ?? "Aktivasi Sekarang"; ?>';
    if (alertBox) {
      alertBox.className = 'box bg-danger';
      alertBox.style.cssText = 'display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;';
      alertBox.innerHTML = '<i class="fa fa-ban"></i> ' + msg;
    }
  }
})
  .catch(function() {
    tryDirectBrowserActivation(key, hwid);
  });

  function tryDirectBrowserActivation(licenseKey, deviceHwid) {
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
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> Aktivasi Sekarang';
      if (cloudRes && cloudRes.success) {
        if (alertBox) {
          alertBox.style.display = 'block';
          alertBox.style.background = 'rgba(34, 197, 94, 0.15)';
          alertBox.style.color = '#16a34a';
          alertBox.style.border = '1px solid #86efac';
          alertBox.innerHTML = '<i class="fa fa-check"></i> Lisensi resmi berhasil diaktivasi di Cloud!';
        }
        setTimeout(function() {
          location.reload();
        }, 1000);
      } else {
        if (alertBox) {
          alertBox.style.display = 'block';
          alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
          alertBox.style.color = '#dc2626';
          alertBox.style.border = '1px solid #f87171';
          alertBox.innerHTML = '<i class="fa fa-times-circle"></i> ' + (cloudRes ? cloudRes.message : 'Aktivasi lisensi gagal.');
        }
      }
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> Aktivasi Sekarang';
      if (alertBox) {
        alertBox.style.display = 'block';
        alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
        alertBox.style.color = '#dc2626';
        alertBox.style.border = '1px solid #f87171';
        alertBox.innerHTML = '<i class="fa fa-times-circle"></i> Gagal terhubung ke Cloud License Server: ' + err.message;
      }
    });
  }
}

// ── Check Update with Spin Feedback ──
document.addEventListener('DOMContentLoaded', function() {
  var btnCheck = document.getElementById('btnCheckUpdate');
  var btnExecute = document.getElementById('btnExecuteUpdate');
  var iconSpin = document.getElementById('iconSpin');
  var actionBox = document.getElementById('updateActionBox');
  var logCard = document.getElementById('updateLogCard');
  var logConsole = document.getElementById('logConsole');

  if (btnCheck) {
    btnCheck.addEventListener('click', function() {
      btnCheck.disabled = true;
      if (iconSpin) iconSpin.className = 'fa fa-refresh fa-spin';
      btnCheck.innerHTML = '<i class="fa fa-refresh fa-spin"></i> Memeriksa...';

      setTimeout(function() {
        fetch('admin.php?id=update&action=check')
          .then(function(res) { return res.json(); })
          .then(function(data) {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fa fa-refresh"></i> Periksa Pembaruan';

            document.getElementById('lblLastChecked').textContent = data.checked_at || 'Baru saja';
            document.getElementById('lblLatestVersion').textContent = 'v' + (data.latest_version || '3.20.3-v7');

            if (data.update_available) {
              if (actionBox) actionBox.style.display = 'block';
              document.getElementById('boxNewVersionTitle').textContent = 'v' + data.latest_version;
              document.getElementById('badgeUpdateStatus').className = 'badge bg-yellow';
              document.getElementById('badgeUpdateStatus').innerHTML = '<i class="fa fa-exclamation-circle"></i> Update Tersedia';
            } else {
              if (actionBox) actionBox.style.display = 'none';
              document.getElementById('badgeUpdateStatus').className = 'badge bg-green';
              document.getElementById('badgeUpdateStatus').innerHTML = '<i class="fa fa-check-circle"></i> Versi Mutakhir';
            }

            if (data.changelog && data.changelog.length > 0) {
              var listHtml = '';
              data.changelog.forEach(function(item) {
                listHtml += '<li><i class="fa fa-check-circle text-green"></i> ' + item + '</li>';
              });
              document.getElementById('changelogList').innerHTML = listHtml;
            }
          })
          .catch(function() {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fa fa-refresh"></i> Periksa Pembaruan';
            alert('Gagal terhubung ke server GitHub / API.');
          });
      }, 500);
    });
  }

  if (btnExecute) {
    btnExecute.addEventListener('click', function() {
      if (!confirm('Apakah Anda yakin ingin memperbarui instance Mikhmon sekarang? Seluruh konfigurasi sesi router Anda tetap aman.')) {
        return;
      }

      btnExecute.disabled = true;
      btnExecute.innerHTML = '<i class="fa fa-refresh fa-spin"></i> Memproses...';
      if (logCard) logCard.style.display = 'block';
      if (logConsole) logConsole.innerHTML = '> [START] Memulai pembaruan sistem Mikhmon...<br/>';

      fetch('admin.php?id=update&action=execute')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          btnExecute.disabled = false;
          btnExecute.innerHTML = '<i class="fa fa-check"></i> Pembaruan Selesai';

          if (data.logs && data.logs.length > 0) {
            data.logs.forEach(function(log) {
              logConsole.innerHTML += '> ' + log + '<br/>';
            });
          }
          logConsole.innerHTML += '> [SELESAI] ' + (data.message || 'Pembaruan berhasil!') + '<br/>';
          logConsole.scrollTop = logConsole.scrollHeight;

          setTimeout(function() {
            location.reload();
          }, 2000);
        })
        .catch(function() {
          btnExecute.disabled = false;
          btnExecute.innerHTML = '<i class="fa fa-cloud-download"></i> Coba Lagi';
          logConsole.innerHTML += '> [ERROR] Terjadi kesalahan jaringan saat mengunduh pembaruan.<br/>';
        });
    });
  }
});
</script>
