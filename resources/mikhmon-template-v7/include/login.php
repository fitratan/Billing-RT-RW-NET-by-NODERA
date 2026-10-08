<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  MIKHMON by NODERA (panel.dgtlnetsolution.com)
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

include_once __DIR__ . '/license.php';
$isDesktop = function_exists('mikhmon_is_desktop_mode') ? mikhmon_is_desktop_mode() : false;
$isLicensed = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : false;
$isExpired = function_exists('mikhmon_is_expired') ? mikhmon_is_expired() : false;
$isSuspended = function_exists('mikhmon_is_suspended') ? mikhmon_is_suspended() : false;
$expiryDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
$remainingDays = function_exists('mikhmon_remaining_days') ? mikhmon_remaining_days() : 999;
$hwid = function_exists('mikhmon_get_hwid') ? mikhmon_get_hwid() : '';
$licenseKey = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
$brand = defined('MIKHMON_BRAND') ? MIKHMON_BRAND : 'by NODERA (panel.dgtlnetsolution.com)';

// Resolve custom uploaded logo
$adminLogo = 'img/logo.png';
$logoCandidates = [
    "./img/logo-" . ($session ?? '') . ".png",
    "./img/logo-" . ($session ?? '') . ".jpg",
    "./img/logo-" . ($session ?? '') . ".jpeg",
    "./img/logo-" . ($session ?? '') . ".webp",
    "./img/logo-" . strtolower($session ?? '') . ".png",
    "./img/logo-" . strtolower($session ?? '') . ".jpg",
    "./img/logo-" . strtoupper($session ?? '') . ".png",
    "./img/logo.png",
    "./img/logo-kemangi41.png",
    "./img/favicon.png",
];
foreach ($logoCandidates as $cand) {
    if (file_exists($cand) && filesize($cand) > 0) {
        $adminLogo = $cand . '?t=' . filemtime($cand);
        break;
    }
}
?>

<div class="login-box" style="padding-top: 15px; margin: 15px auto;">
  <div class="card">
    <div class="card-header text-center">
      <h3><?= $_please_login ?></h3>
    </div>
    <div class="card-body">
      <div class="text-center" style="padding-top: 6px; padding-bottom: 6px;">
        <img src="<?= $adminLogo; ?>" alt="Logo" style="max-height: 52px; max-width: 180px; height: auto; width: auto; object-fit: contain; margin: 0 auto; display: inline-block;">
      </div>
      <div class="text-center">
        <span style="font-size: 18px; margin: 2px 0 2px; font-weight: bold; display: block; letter-spacing: 0.5px;">MIKHMON</span>
        <div class="text-muted" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
          <?= htmlspecialchars($brand) ?>
        </div>

        <?php if ($isDesktop): ?>
          <?php if (!$isLicensed): ?>
            <div style="margin: 0 auto 14px; max-width: 320px; text-align: left; background: rgba(52, 152, 219, 0.08); border: 1px solid rgba(52, 152, 219, 0.35); border-radius: 8px; padding: 10px 12px;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 12px; font-weight: bold; color: #2980b9;">
                  <i class="fa fa-desktop"></i> Mikhmon Desktop
                </span>
                <span style="font-size: 10px; padding: 2px 6px; background: #e67e22; color: #fff; border-radius: 4px; font-weight: bold;">
                  <?= $_unactivated ?? "Belum Teraktivasi"; ?>
                </span>
              </div>
              <div style="font-size: 11px; opacity: 0.8; line-height: 1.4; margin-bottom: 8px;">
                <?= $_enter_license_key_hint ?? "Masukkan License Key dari Cloud Panel NODERA untuk mengaktifkan fitur penuh."; ?>
              </div>
              <div style="display: flex; gap: 6px;">
                <button type="button" onclick="openDesktopLicenseModal()" class="btn-login bg-primary pointer" style="flex: 1; height: 32px; font-size: 12px; font-weight: bold; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; margin: 0 !important;">
                  <i class="fa fa-key"></i> <?= $_activate_license ?? "Aktivasi Lisensi"; ?>
                </button>
                <button type="button" onclick="copyHwidLogin()" class="btn-login bg-secondary pointer" style="height: 32px; padding: 0 10px; font-size: 12px; font-weight: bold; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; margin: 0 !important;" title="<?= $_copy ?? "Salin"; ?> HWID">
                  <i class="fa fa-copy"></i> HWID
                </button>
              </div>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($isSuspended): ?>
            <div style="margin: 10px auto 14px; text-align: center; max-width: 90%;">
              <div style="background: rgba(231, 76, 60, 0.12); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.4); border-radius: 8px; padding: 10px 14px; font-size: 12px; line-height: 1.5;">
                <i class="fa fa-ban" style="font-size: 18px; margin-bottom: 4px;"></i><br>
                <strong style="font-size: 13px;"><?= $_account_suspended ?? "Akun Ditangguhkan"; ?></strong><br>
                <?= $_service_suspended_desc ?? "Layanan MIKHMON ini sedang ditangguhkan oleh administrator."; ?><br>
                <a href="https://panel.dgtlnetsolution.com" target="_blank" style="color: #3498db; text-decoration: underline; font-weight: bold; display: inline-block; margin-top: 5px;"><?= $_contact_support ?? "Hubungi Bantuan"; ?> &rarr;</a>
              </div>
            </div>
          <?php elseif ($isExpired): ?>
            <div style="margin: 10px auto 14px; text-align: center; max-width: 90%;">
              <div style="background: rgba(231, 76, 60, 0.12); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.4); border-radius: 8px; padding: 10px 14px; font-size: 12px; line-height: 1.5;">
                <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-bottom: 4px;"></i><br>
                <strong style="font-size: 13px;"><?= $_subscription_expired ?? "Masa Aktif Berlangganan Habis!"; ?></strong><br>
                <?= $_subscription_ended_on ?? "Berlangganan telah berakhir pada :"; ?> <b><?= htmlspecialchars($expiryDateText) ?></b><br>
                <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" style="display: inline-block; margin-top: 8px; padding: 5px 12px; background: #e74c3c; color: #ffffff; border-radius: 6px; font-size: 11.5px; font-weight: bold; text-decoration: none;">
                  <i class="fa fa-refresh"></i> <?= $_renew_in_nodera ?? "Perpanjang di NODERA"; ?>
                </a>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div style="display: flex; justify-content: center; width: 100%; padding: 2px 0;">
      <?php if ($isDesktop || (!$isExpired && !$isSuspended)): ?>
      <form autocomplete="on" action="" method="post" id="mikhmonLoginForm" style="width: 100%; max-width: 320px; box-sizing: border-box; text-align: left;">
        
        <div style="margin-bottom: 12px; width: 100%;">
          <input style="width: 100%; height: 38px; font-size: 14px; box-sizing: border-box; padding: 8px 12px; margin: 0 !important;" class="form-control" type="text" name="user" id="_username" placeholder="Username" required="1" autofocus autocomplete="username">
        </div>

        <div style="margin-bottom: 12px; position: relative; width: 100%;">
          <input style="width: 100%; height: 38px; font-size: 14px; box-sizing: border-box; padding: 8px 40px 8px 12px; margin: 0 !important;" class="form-control" type="password" name="pass" id="_password" placeholder="Password" required="1" autocomplete="current-password">
          <button type="button" onclick="toggleMikhmonPass()" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; color: inherit; opacity: 0.6; padding: 6px; font-size: 14px; display: flex; align-items: center; justify-content: center;" aria-label="<?= $_view_password ?? "Lihat Password"; ?>" tabindex="-1">
            <i class="fa fa-eye" id="mikhmonEyeIcon"></i>
          </button>
        </div>

        <div class="mikhmon-rem-row" style="margin-bottom: 14px; text-align: left; display: block;">
          <label for="_remember" class="mikhmon-rem-label" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; font-size: 13px; margin: 0; font-weight: normal; color: inherit; text-align: left;">
            <input type="checkbox" name="remember" id="_remember" value="1" style="width: 16px !important; height: 16px !important; margin: 0 !important; cursor: pointer; flex-shrink: 0;" checked>
            <span style="line-height: 1; user-select: none;"><?= !empty($_remember_me) ? $_remember_me : 'Ingat saya' ?></span>
          </label>
        </div>

        <?php if (!empty($error)): ?>
          <div style="margin-bottom: 12px; text-align: center;">
            <?= $error; ?>
          </div>
        <?php endif; ?>

        <div style="margin-bottom: 10px; width: 100%;">
          <input style="width: 100%; height: 38px; font-weight: bold; font-size: 14px; margin: 0 !important;" class="btn-login bg-primary pointer" type="submit" name="login" value="Login">
        </div>

        <div style="margin-bottom: 6px; width: 100%;">
          <button type="button" id="btnInstallPwa" class="btn-login bg-secondary pointer btn-pwa-install" onclick="triggerPwaInstall()" style="width: 100%; height: 36px; margin: 0 !important; display: none;">
            <i class="fa fa-download" style="margin-right: 6px;"></i> Install Mikhmon
          </button>
        </div>
      </form>
      <?php else: ?>
        <?php if (!empty($error)): ?>
          <div style="width: 100%; max-width: 320px; margin: 10px auto;">
            <?= $error; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal <?= $_desktop_license_activation ?? "Aktivasi Lisensi Desktop"; ?> NODERA -->
<!-- ── MODAL AKTIVASI / GANTI LISENSI (NATIVE MIKHMON THEME) ── -->

<!-- ── MODAL AKTIVASI / GANTI LISENSI (NATIVE MIKHMON THEME) ── -->
<div id="ndrDesktopLicenseModal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.65); justify-content:center; align-items:center;">
  <div class="card" style="width:92%; max-width:480px; box-shadow:0 10px 30px rgba(0,0,0,0.5); border-radius:4px; margin:0; padding:0; overflow:hidden;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px;">
      <h3 class="card-title" style="margin:0; font-size:15px;">
        <i class="fa fa-key text-primary"></i> <?= $_desktop_license_activation ?? "Aktivasi Lisensi Desktop"; ?>
      </h3>
      <button type="button" onclick="closeDesktopLicenseModal()" style="background:none; border:none; color:inherit; font-size:20px; line-height:1; cursor:pointer; opacity:0.8;" title="Tutup">&times;</button>
    </div>
    <div class="card-body" style="padding:15px;">
      <div id="ndrLicenseAlert" style="display:none; padding:8px 12px; border-radius:3px; font-size:12px; margin-bottom:12px;"></div>

      <!-- HWID Field -->
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;"><?= $_hwid_this_device ?? "Hardware ID (HWID Perangkat Ini):"; ?></label>
        <div style="display:flex; gap:5px;">
          <input type="text" id="ndrDeviceHwid" value="<?= htmlspecialchars($hwid ?: 'STANDALONE') ?>" readonly class="form-control" style="flex:1; font-family:monospace; font-size:12.5px; font-weight:bold;">
          <button type="button" onclick="copyHwidLogin()" class="btn bg-secondary" style="margin:0; padding:5px 12px;" title="<?= $_copy ?? "Salin"; ?> HWID">
            <i class="fa fa-copy" id="copyHwidIcon"></i> <?= $_copy ?? "Salin"; ?>
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
        <button type="button" id="ndrBtnSubmitLicense" onclick="submitDesktopLicenseLogin()" class="btn bg-primary" style="margin:0;">
          <i class="fa fa-check"></i> Simpan &amp; Verifikasi
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.mikhmon-rem-row {
  display: block !important;
  text-align: left !important;
}
.mikhmon-rem-label {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 8px !important;
  text-align: left !important;
}
.mikhmon-rem-label input[type="checkbox"] {
  margin: 0 !important;
  vertical-align: middle !important;
}
.btn-pwa-install {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 36px;
  font-size: 14px;
  font-weight: bold;
}
.btn-pwa-install i {
  font-size: 13px;
}
</style>

<script>
var deferredPwaPrompt = null;

function toggleMikhmonPass() {
  var pass = document.getElementById('_password');
  var icon = document.getElementById('mikhmonEyeIcon');
  if (!pass || !icon) return;
  if (pass.type === 'password') {
    pass.type = 'text';
    icon.className = 'fa fa-eye-slash';
  } else {
    pass.type = 'password';
    icon.className = 'fa fa-eye';
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
function copyHwidLogin() {
  var h = document.getElementById('ndrDeviceHwid');
  if (h) {
    h.select();
    document.execCommand('copy');
    alert((<?= json_encode($_hwid_copied_clipboard ?? 'Hardware ID berhasil disalin ke clipboard:') ?>) + '\n' + h.value);
  }
}

function submitDesktopLicenseLogin() {
  var keyEl = document.getElementById('ndrLicenseKeyInput');
  var key = keyEl ? keyEl.value.trim() : '';
  var hwidEl = document.getElementById('ndrDeviceHwid');
  var hwid = (hwidEl ? hwidEl.value.trim() : '');
  var alertBox = document.getElementById('ndrLicenseAlert');
  var btn = document.getElementById('ndrBtnSubmitLicense');
  if (!key) {
    showError(<?= json_encode($_license_key_required ?? 'License Key wajib diisi!') ?>);
    return;
  }
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($_connecting_to_cloud ?? 'Menghubungkan ke Cloud Server...') ?>;
  }
  if (alertBox) alertBox.style.display = 'none';

  // 1. Coba aktivasi via PHP lokal terlebih dahulu
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
      // Jika PHP lokal gagal/timeout, fallback ke direct browser fetch HTTPS
      tryDirectBrowserActivation(key, hwid);
    }
  })
  .catch(function() {
    // Jika AJAX lokal gagal, fallback ke direct browser fetch HTTPS
    tryDirectBrowserActivation(key, hwid);
  });
}

function tryDirectBrowserActivation(licenseKey, deviceHwid) {
    var btn = document.getElementById('ndrBtnSubmitLicense');
    if (btn) {
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($_validating_cloud_direct ?? 'Validasi Cloud Direct HTTPS...') ?>;
    }
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
        // Simpan data aktivasi ke config lokal
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
      showError((<?= json_encode($_failed_connect_cloud_license ?? 'Gagal terhubung ke Cloud License Server:') ?>) + ' ' + err.message);
    });
}

function showSuccessAndReload(data) {
    var btn = document.getElementById('ndrBtnSubmitLicense');
    var alertBox = document.getElementById('ndrLicenseAlert');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> Berhasil';
    }
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
      btn.innerHTML = '<i class="fa fa-check-circle"></i> ' + <?= json_encode($_activate_now ?? 'Aktivasi Sekarang') ?>;
    }
    if (alertBox) {
      alertBox.className = 'box bg-danger';
      alertBox.style.cssText = 'display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;';
      alertBox.innerHTML = '<i class="fa fa-ban"></i> ' + msg;
    }
}

window.addEventListener('beforeinstallprompt', function(e) {
  e.preventDefault();
  deferredPwaPrompt = e;
  var btn = document.getElementById('btnInstallPwa');
  if (btn) {
    btn.style.display = 'inline-flex';
  }
});

window.addEventListener('appinstalled', function() {
  deferredPwaPrompt = null;
  var btn = document.getElementById('btnInstallPwa');
  if (btn) {
    btn.innerHTML = '<i class="fa fa-check-circle"></i> Aplikasi Terpasang';
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.style.cursor = 'default';
  }
});

function triggerPwaInstall() {
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (isStandalone) {
    alert(<?= json_encode($_app_already_installed ?? 'Aplikasi sudah terpasang di perangkat Anda.') ?>);
    return;
  }

  if (deferredPwaPrompt) {
    deferredPwaPrompt.prompt();
    deferredPwaPrompt.userChoice.then(function(choiceResult) {
      if (choiceResult.outcome === 'accepted') {
        var btn = document.getElementById('btnInstallPwa');
        if (btn) {
          btn.innerHTML = '<i class="fa fa-check-circle"></i> Aplikasi Terpasang';
          btn.disabled = true;
        }
      }
      deferredPwaPrompt = null;
    });
  } else {
    var isIOS = /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase());
    if (isIOS) {
      alert("📱 Cara Install di iPhone/iPad (Safari):\n1. Tekan tombol 'Share' (ikon kotak panah ke atas di bilah bawah)\n2. Gulir ke bawah lalu pilih 'Tambah ke Layar Utama' (Add to Home Screen)\n3. Tekan 'Tambah' di pojok kanan atas.");
    } else {
      alert("📱 Cara Install Aplikasi di Browser:\n1. Tekan menu titik tiga (⋮) di pojok kanan atas browser Chrome/browser Anda\n2. Pilih 'Install aplikasi' atau 'Tambahkan ke Layar Utama' (Add to Home Screen)\n3. Ikuti petunjuk untuk menyelesaikan pemasangan.");
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var userInput = document.getElementById('_username');
  var passInput = document.getElementById('_password');
  var remCheckbox = document.getElementById('_remember');

  try {
    var savedUser = localStorage.getItem('mikhmon_saved_user');
    var savedPass = localStorage.getItem('mikhmon_saved_pass');
    var rememberPref = localStorage.getItem('mikhmon_remember_pref');

    if (rememberPref !== null && remCheckbox) {
      remCheckbox.checked = (rememberPref === '1');
    }

    if (savedUser && userInput && !userInput.value) {
      userInput.value = savedUser;
    }
    if (savedPass && passInput && !passInput.value && remCheckbox && remCheckbox.checked) {
      passInput.value = savedPass;
    }
  } catch(e) {}

  var form = document.getElementById('mikhmonLoginForm');
  if (form) {
    form.addEventListener('submit', function() {
      try {
        if (remCheckbox && remCheckbox.checked) {
          if (userInput) localStorage.setItem('mikhmon_saved_user', userInput.value);
          if (passInput) localStorage.setItem('mikhmon_saved_pass', passInput.value);
          localStorage.setItem('mikhmon_remember_pref', '1');
        } else {
          localStorage.removeItem('mikhmon_saved_user');
          localStorage.removeItem('mikhmon_saved_pass');
          localStorage.setItem('mikhmon_remember_pref', '0');
        }
      } catch(e) {}
    });
  }

  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (isStandalone) {
    var btn = document.getElementById('btnInstallPwa');
    if (btn) {
      btn.style.display = 'none';
    }
  }
});
</script>

</body>
</html>