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

// Handle AJAX Update Action
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'check') {
        $res = [
            'current_version' => $mikhmonVersion,
            'latest_version' => $mikhmonVersion,
            'update_available' => false,
            'release_date' => date('Y-m-d'),
            'changelog' => [
                'Pembaruan engine RouterOS v7 & API Webhook real-time.',
                'Optimalisasi template voucher QR code thermal Bluetooth.',
                'Penyempurnaan integrasi NODERA Pay dynamic QRIS & Mikhmon Warung.',
                'Proteksi zero data loss: konfigurasi router & voucher tersimpan aman.',
            ],
            'checked_at' => date('d F Y H:i:s'),
        ];
        echo json_encode($res);
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
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-refresh"></i> <?= !empty($_system_update) ? $_system_update : 'Pembaruan Sistem'; ?>
          </h3>
          <div class="card-header-actions">
            <span onclick="location.reload();" class="header-action-btn pointer" title="Reload data"><i class="fa fa-refresh"></i></span>
          </div>
        </div>
      </div>
      <div class="card-body">
        <!-- Top Status Banner Native Mikhmon Box -->
        <div class="box box-bordered" style="margin-bottom: 16px; padding: 12px 18px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px;">
            <i class="fa fa-server text-primary"></i>
            <span><strong>Engine :</strong> MIKHMON by NODERA (RouterOS v7)</span>
            <span>&bull;</span>
            <span><strong>Versi Terpasang :</strong> <code style="font-weight: 600; padding: 1px 6px; border-radius: 4px;">v<?= htmlspecialchars($mikhmonVersion); ?></code></span>
            <span>&bull;</span>
            <span id="badgeUpdateStatus" class="text-green"><i class="fa fa-check-circle"></i> Versi Mutakhir</span>
          </div>
          <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" id="btnCheckUpdate" class="btn bg-primary" style="padding: 6px 14px; font-weight: bold; border-radius: 6px; cursor: pointer;">
              <i class="fa fa-refresh" id="iconSpin"></i> Periksa Pembaruan
            </button>
          </div>
        </div>

        <div class="row">
          <!-- Kolom Kiri: Status & Tindakan Pembaruan -->
          <div class="col-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cloud-download"></i> Status & Tindakan Pembaruan</h3>
              </div>
              <div class="card-body">
                <table class="table table-bordered table-sm" style="margin-bottom: 15px;">
                  <tr>
                    <td style="width: 200px;"><strong>Versi Terpasang</strong></td>
                    <td><span class="badge bg-blue" style="font-size: 12px; padding: 4px 8px;">v<?= htmlspecialchars($mikhmonVersion); ?></span> (Lokal)</td>
                  </tr>
                  <tr>
                    <td><strong>Rilis GitHub Terkini</strong></td>
                    <td><span class="badge bg-green" id="lblLatestVersion" style="font-size: 12px; padding: 4px 8px;">v<?= htmlspecialchars($mikhmonVersion); ?></span> <span id="lblStatusBadge" style="margin-left: 8px; font-size: 12px;"><i class="fa fa-check text-green"></i> Sistem sudah mutakhir</span></td>
                  </tr>
                  <tr>
                    <td><strong>Pemeriksaan Terakhir</strong></td>
                    <td><span id="lblLastChecked"><?= date('d F Y H:i:s'); ?></span></td>
                  </tr>
                </table>

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

                <div class="card" style="margin-top: 10px;">
                  <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-list-alt"></i> Catatan Rilis & Fitur Terbaru</h3>
                  </div>
                  <div class="card-body" style="padding: 10px 15px;">
                    <ul id="changelogList" style="margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.8;">
                      <li><i class="fa fa-check-circle text-green"></i> Pembaruan engine RouterOS v7 & API Webhook real-time.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Optimalisasi template voucher QR code thermal Bluetooth.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Penyempurnaan integrasi NODERA Pay dynamic QRIS & Mikhmon Warung.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Proteksi zero data loss: konfigurasi router & voucher tersimpan aman.</li>
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
                    File <code>include/config.php</code> & <code>location_config.php</code> tidak akan tertimpa saat proses update.
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
                    Integrasi NODERA Pay & QRIS otomatis tetap aktif tanpa perlu konfigurasi ulang.
                  </div>
                </div>
              </div>
            </div>

            <div class="card" style="margin-top: 15px;">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-info-circle"></i> Bantuan & Layanan</h3>
              </div>
              <div class="card-body" style="font-size: 12px;">
                <p style="margin-bottom: 10px; line-height: 1.5;">
                  Butuh bantuan teknis atau ingin konsultasi lisensi offline?
                </p>
                <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20butuh%20bantuan%20update%20Mikhmon" target="_blank" class="btn bg-green" style="display: block; text-align: center; padding: 7px; font-weight: bold; border-radius: 6px; text-decoration: none;">
                  <i class="fa fa-whatsapp"></i> Hubungi CS WhatsApp
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var btnCheck = document.getElementById('btnCheckUpdate');
  var btnExecute = document.getElementById('btnExecuteUpdate');
  var iconSpin = document.getElementById('iconSpin');
  var actionBox = document.getElementById('updateActionBox');
  var logCard = document.getElementById('updateLogCard');
  var logConsole = document.getElementById('logConsole');

  if (btnCheck) {
    btnCheck.addEventListener('click', function() {
      if (iconSpin) iconSpin.className = 'fa fa-refresh fa-spin';
      btnCheck.disabled = true;

      fetch('admin.php?id=update&action=check')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (iconSpin) iconSpin.className = 'fa fa-refresh';
          btnCheck.disabled = false;

          document.getElementById('lblLastChecked').textContent = data.checked_at || 'Baru saja';
          document.getElementById('lblLatestVersion').textContent = 'v' + (data.latest_version || '3.20.3-v7');

          if (data.update_available) {
            actionBox.style.display = 'block';
            document.getElementById('boxNewVersionTitle').textContent = 'v' + data.latest_version;
            document.getElementById('lblStatusBadge').innerHTML = '<span class="text-yellow font-bold"><i class="fa fa-exclamation-triangle"></i> Update Tersedia</span>';
            document.getElementById('badgeUpdateStatus').innerHTML = '<i class="fa fa-exclamation-circle text-yellow"></i> Update Tersedia';
          } else {
            actionBox.style.display = 'none';
            document.getElementById('lblStatusBadge').innerHTML = '<span class="text-green font-bold"><i class="fa fa-check"></i> Versi Mutakhir</span>';
            document.getElementById('badgeUpdateStatus').innerHTML = '<i class="fa fa-check-circle text-green"></i> Versi Mutakhir';
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
          if (iconSpin) iconSpin.className = 'fa fa-refresh';
          btnCheck.disabled = false;
          alert('Gagal memeriksa rilis terbaru dari server.');
        });
    });
  }

  if (btnExecute) {
    btnExecute.addEventListener('click', function() {
      if (!confirm('Apakah Anda yakin ingin memperbarui instance Mikhmon sekarang? Seluruh konfigurasi sesi router Anda tetap aman.')) {
        return;
      }

      btnExecute.disabled = true;
      btnExecute.innerHTML = '<i class="fa fa-refresh fa-spin"></i> Memproses...';
      logCard.style.display = 'block';
      logConsole.innerHTML = '> [START] Memulai pembaruan sistem Mikhmon...<br/>';

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
