<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * FUP (Fair Usage Policy) Settings & Profile Rules Configurator
 */

session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

include_once __DIR__ . '/fup_helper.php';
include_once __DIR__ . '/../lib/formatbytesbites.php';

$fupConfig = fup_load_config($session, $API);

// Fetch Hotspot User Profiles from MikroTik
$getProfiles = $API->comm("/ip/hotspot/user/profile/print", array(
    ".proplist" => ".id,name,rate-limit,shared-users"
));
$getProfiles = is_array($getProfiles) ? $getProfiles : array();
$profileMap = array();
foreach ($getProfiles as $p) {
    if (!empty($p['name'])) {
        $profileMap[$p['name']] = $p;
    }
}

// Handle Form Submission (Save or Delete FUP Rule)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_fup_rule') {
        $targetProfile = trim($_POST['profile_name'] ?? '');
        $status = trim($_POST['fup_status'] ?? 'enabled');
        $valNum = (float)($_POST['threshold_num'] ?? 10);
        $valUnit = trim($_POST['threshold_unit'] ?? 'GB');
        $throttledRate = trim($_POST['throttled_rate'] ?? '1M/1M');
        $resetCycle = trim($_POST['reset_cycle'] ?? 'manual');

        if (!empty($targetProfile)) {
            // Calculate bytes
            $multiplier = ($valUnit === 'MB') ? 1048576 : 1073741824;
            $threshBytes = $valNum * $multiplier;

            $fupConfig['profiles'][$targetProfile] = array(
                'enabled' => ($status === 'enabled'),
                'threshold_bytes' => $threshBytes,
                'threshold_val' => $valNum,
                'threshold_unit' => $valUnit,
                'throttled_rate' => $throttledRate,
                'reset_cycle' => $resetCycle
            );

            fup_save_config($session, $fupConfig, $API);
            $message = 'Aturan FUP untuk profil \'' . htmlspecialchars($targetProfile) . '\' berhasil disimpan dan disinkronkan ke MikroTik.';
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'delete_fup_rule') {
        $targetProfile = trim($_POST['profile_name'] ?? '');
        if (!empty($targetProfile) && isset($fupConfig['profiles'][$targetProfile])) {
            unset($fupConfig['profiles'][$targetProfile]);
            fup_save_config($session, $fupConfig, $API);
            $message = 'Aturan FUP untuk profil \'' . htmlspecialchars($targetProfile) . '\' berhasil dihapus.';
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'resync_all') {
        fup_save_config($session, $fupConfig, $API);
        $message = 'Seluruh script dan scheduler FUP berhasil disinkronkan ulang ke RouterOS MikroTik.';
        $messageType = 'success';
    }
}

$selectedProfile = isset($_GET['edit_profile']) ? trim($_GET['edit_profile']) : '';
$currentRule = (!empty($selectedProfile) && isset($fupConfig['profiles'][$selectedProfile])) ? $fupConfig['profiles'][$selectedProfile] : null;
?>

<div class="row">
  <div class="col-12">
    <?php if (!empty($message)): ?>
      <div class="box bg-green pd-10 mr-b-10" style="border-radius: 4px; font-weight: bold;">
        <i class="fa fa-check-circle"></i> <?= $message; ?>
      </div>
    <?php endif; ?>

    <!-- Info Banner -->
    <div class="box bg-blue pd-10 mr-b-10" style="border-radius: 4px;">
      <div style="display: flex; gap: 10px; align-items: center;">
        <i class="fa fa-info-circle" style="font-size: 24px;"></i>
        <div>
          <b>Fair Usage Policy (FUP) Engine:</b> Sistem ini mengawasi pemakaian kuota voucher secara otomatis. 
          Ketika pengguna mencapai batas kuota (threshold), kecepatan internet langsung diturunkan (downgrade speed) tanpa memutus sesi login pelanggan. 
          Konfigurasi dan script monitor berjalan mandiri di MikroTik RouterOS 24/7.
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <h3><i class="fa fa-sliders"></i> <?= $_fup_settings ?? 'Pengaturan FUP'; ?> (Fair Usage Policy)</h3>
        <div>
          <a href="./?bandwidth=top-voucher&session=<?= $session; ?>" class="btn bg-default">
            <i class="fa fa-arrow-left"></i> Kembali ke Top Tier
          </a>
        </div>
      </div>

      <div class="card-body">
        <!-- FUP Configuration Form -->
        <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>">
          <input type="hidden" name="action" value="save_fup_rule">

          <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin-top: 0; margin-bottom: 12px; font-weight: bold; border-bottom: 1px solid #ddd; padding-bottom: 6px;">
              <i class="fa fa-pencil-square-o"></i> Form Konfigurasi Aturan FUP per Profil
            </h4>

            <div class="row">
              <!-- Select Profile -->
              <div class="col-6 col-box-12 mr-b-10">
                <label style="font-weight: bold;">Pilih Profil Hotspot:</label>
                <select name="profile_name" class="form-control" required onchange="window.location.href='./?bandwidth=fup-settings&session=<?= $session; ?>&edit_profile=' + encodeURIComponent(this.value);">
                  <option value="">-- Pilih Profil --</option>
                  <?php foreach ($getProfiles as $p): ?>
                    <?php $pName = $p['name'] ?? ''; ?>
                    <?php if (!empty($pName)): ?>
                      <?php $isSelected = ($selectedProfile === $pName) ? 'selected' : ''; ?>
                      <option value="<?= htmlspecialchars($pName); ?>" <?= $isSelected; ?>>
                        <?= htmlspecialchars($pName); ?> (Rate Normal: <?= htmlspecialchars($p['rate-limit'] ?: 'Unlimited'); ?>)
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Status FUP -->
              <div class="col-6 col-box-12 mr-b-10">
                <label style="font-weight: bold;">Status FUP Profil Ini:</label>
                <select name="fup_status" class="form-control">
                  <option value="enabled" <?= ($currentRule && !empty($currentRule['enabled'])) ? 'selected' : ''; ?>>Aktif (Terapkan FUP)</option>
                  <option value="disabled" <?= ($currentRule && empty($currentRule['enabled'])) ? 'selected' : ''; ?>>Nonaktif (Bypass FUP)</option>
                </select>
              </div>
            </div>

            <div class="row">
              <!-- Threshold Quota -->
              <div class="col-4 col-box-12 mr-b-10">
                <label style="font-weight: bold;">Batas Kuota Threshold FUP:</label>
                <div style="display: flex; gap: 6px;">
                  <input type="number" step="0.1" min="0.1" name="threshold_num" class="form-control" style="flex: 2;" value="<?= htmlspecialchars($currentRule['threshold_val'] ?? '10'); ?>" required>
                  <select name="threshold_unit" class="form-control" style="flex: 1;">
                    <option value="GB" <?= (($currentRule['threshold_unit'] ?? 'GB') === 'GB') ? 'selected' : ''; ?>>GB</option>
                    <option value="MB" <?= (($currentRule['threshold_unit'] ?? '') === 'MB') ? 'selected' : ''; ?>>MB</option>
                  </select>
                </div>
                <small style="color: #666;">Contoh: 10 GB atau 5000 MB</small>
              </div>

              <!-- Throttled Rate -->
              <div class="col-4 col-box-12 mr-b-10">
                <label style="font-weight: bold;">Kecepatan Setelah FUP (Throttled Rate):</label>
                <input type="text" name="throttled_rate" class="form-control" value="<?= htmlspecialchars($currentRule['throttled_rate'] ?? '1M/1M'); ?>" placeholder="upload/download (contoh: 512k/1M)" required>
                <small style="color: #666;">Format MikroTik: Rx/Tx (contoh: 512k/512k atau 1M/1M)</small>
              </div>

              <!-- Reset Cycle -->
              <div class="col-4 col-box-12 mr-b-10">
                <label style="font-weight: bold;">Siklus Reset Pemakaian Kuota:</label>
                <select name="reset_cycle" class="form-control">
                  <option value="manual" <?= (($currentRule['reset_cycle'] ?? 'manual') === 'manual') ? 'selected' : ''; ?>>Manual (Hanya via tombol reset / expired)</option>
                  <option value="daily" <?= (($currentRule['reset_cycle'] ?? '') === 'daily') ? 'selected' : ''; ?>>Harian (Otomatis reset tiap 00:00:00)</option>
                  <option value="monthly" <?= (($currentRule['reset_cycle'] ?? '') === 'monthly') ? 'selected' : ''; ?>>Bulanan (Otomatis reset tiap tanggal 1)</option>
                </select>
                <small style="color: #666;">Jadwal pengembalian kuota dan kecepatan normal</small>
              </div>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
              <button type="submit" class="btn bg-primary">
                <i class="fa fa-save"></i> <?= $_fup_save_sync ?? 'Simpan & Sinkronkan ke MikroTik'; ?>
              </button>
            </div>
          </div>
        </form>

        <!-- Current Configured Rules Table -->
        <h4 style="margin-bottom: 10px; font-weight: bold;">
          <i class="fa fa-list"></i> Daftar Aturan FUP yang Terdaftar Saat Ini
        </h4>

        <table class="table table-bordered table-hover text-nowrap">
          <thead>
            <tr>
              <th>Nama Profil</th>
              <th>Status FUP</th>
              <th>Batas Kuota (Threshold)</th>
              <th>Kecepatan Normal</th>
              <th>Kecepatan Setelah FUP</th>
              <th>Siklus Reset</th>
              <th style="text-align: center; width: 140px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($fupConfig['profiles'])): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 15px; color: #888;">
                  <i>Belum ada aturan FUP yang dikonfigurasi. Pilih profil di formulir atas untuk menambahkan.</i>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($fupConfig['profiles'] as $profName => $rule): ?>
                <?php
                  $pDetail = $profileMap[$profName] ?? null;
                  $normRate = $pDetail['rate-limit'] ?? 'Unlimited';
                ?>
                <tr>
                  <td style="vertical-align: middle; font-weight: bold;">
                    <?= htmlspecialchars($profName); ?>
                  </td>
                  <td style="vertical-align: middle;">
                    <?php if (!empty($rule['enabled'])): ?>
                      <span class="badge bg-green"><i class="fa fa-check"></i> Aktif</span>
                    <?php else: ?>
                      <span class="badge bg-default"><i class="fa fa-ban"></i> Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td style="vertical-align: middle; font-weight: bold;">
                    <?= htmlspecialchars($rule['threshold_val'] . ' ' . $rule['threshold_unit']); ?> 
                    <small style="color: #777;">(<?= formatBytes2($rule['threshold_bytes'], 2); ?>)</small>
                  </td>
                  <td style="vertical-align: middle;">
                    <span class="badge bg-primary"><?= htmlspecialchars($normRate ?: 'Unlimited'); ?></span>
                  </td>
                  <td style="vertical-align: middle;">
                    <span class="badge bg-red" style="font-weight: bold;"><?= htmlspecialchars($rule['throttled_rate']); ?></span>
                  </td>
                  <td style="vertical-align: middle;">
                    <?php
                      $cLabel = 'Manual';
                      if ($rule['reset_cycle'] === 'daily') $cLabel = 'Harian (00:00)';
                      elseif ($rule['reset_cycle'] === 'monthly') $cLabel = 'Bulanan (Tgl 1)';
                    ?>
                    <?= $cLabel; ?>
                  </td>
                  <td style="vertical-align: middle; text-align: center;">
                    <div style="display: inline-flex; gap: 4px;">
                      <a href="./?bandwidth=fup-settings&session=<?= $session; ?>&edit_profile=<?= urlencode($profName); ?>" class="btn btn-sm bg-warning" title="Edit aturan FUP">
                        <i class="fa fa-edit"></i> Edit
                      </a>
                      <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>" style="display: inline;" onsubmit="return confirm('Hapus aturan FUP untuk profil \'<?= htmlspecialchars($profName); ?>\'?');">
                        <input type="hidden" name="action" value="delete_fup_rule">
                        <input type="hidden" name="profile_name" value="<?= htmlspecialchars($profName); ?>">
                        <button type="submit" class="btn btn-sm bg-danger" title="Hapus aturan FUP">
                          <i class="fa fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Resync All Button -->
        <div style="margin-top: 15px;">
          <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>">
            <input type="hidden" name="action" value="resync_all">
            <button type="submit" class="btn bg-default" title="Kirim ulang script dan scheduler ke MikroTik RouterOS">
              <i class="fa fa-refresh"></i> Sinkronkan Ulang Script & Scheduler ke RouterOS
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
