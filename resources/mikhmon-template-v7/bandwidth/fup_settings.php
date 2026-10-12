<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * FUP (Fair Usage Policy) Settings & Profile Rules Configurator
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

include_once __DIR__ . '/fup_helper.php';
include_once __DIR__ . '/../lib/formatbytesbites.php';

// Ensure language translations are active and synced
fup_ensure_translations();

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
            $message = sprintf(fup_t('_fup_msg_rule_saved'), htmlspecialchars($targetProfile));
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'delete_fup_rule') {
        $targetProfile = trim($_POST['profile_name'] ?? '');
        if (!empty($targetProfile) && isset($fupConfig['profiles'][$targetProfile])) {
            unset($fupConfig['profiles'][$targetProfile]);
            fup_save_config($session, $fupConfig, $API);
            $message = sprintf(fup_t('_fup_msg_rule_deleted'), htmlspecialchars($targetProfile));
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'resync_all') {
        fup_save_config($session, $fupConfig, $API);
        $message = fup_t('_fup_msg_resync_success');
        $messageType = 'success';
    }
}

$notice = $_SESSION['fup_notice'] ?? '';
unset($_SESSION['fup_notice']);
if (!empty($notice)) {
    $message = $notice;
    $messageType = 'success';
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
  </div>
</div>

<div class="row">
  <!-- Form Col-8 (Native Mikhmon Form Style) -->
  <div class="col-8 col-box-12">
    <div class="card box-bordered">
      <div class="card-header">
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-sliders"></i> <?= fup_t('_fup_settings'); ?>
          </h3>
          <div class="card-header-actions">
            <a href="./?bandwidth=action&action=resync_all&session=<?= $session; ?>" onclick="return confirm('<?= addslashes(fup_t('_fup_confirm_apply_now')); ?>');" class="header-action-btn"><i class="fa fa-refresh"></i> <?= fup_t('_sync_short', 'Sinkronkan'); ?></a>
            <a href="./?bandwidth=top-voucher&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-bar-chart"></i> <?= fup_t('_top_tier_voucher', 'Top Tier Voucher'); ?></a>
            <a href="./?bandwidth=interface-traffic&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-exchange"></i> <?= fup_t('_total_bandwidth', 'Total Bandwidth'); ?></a>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>">
          <input type="hidden" name="action" value="save_fup_rule">

          <div style="margin-bottom: 15px;">
            <a class="btn bg-warning" href="./?bandwidth=top-voucher&session=<?= $session; ?>">
              <i class="fa fa-close btn-mrg"></i> <?= fup_t('_close', 'Tutup'); ?>
            </a>
            <button type="submit" name="save" class="btn bg-primary btn-mrg">
              <i class="fa fa-save btn-mrg"></i> <?= fup_t('_save', 'Simpan'); ?>
            </button>
          </div>

          <table class="table">
            <tr>
              <td class="align-middle" style="width: 35%;"><?= fup_t('_fup_select_profile', 'Profil Hotspot'); ?></td>
              <td>
                <select name="profile_name" class="form-control" required onchange="window.location.href='./?bandwidth=fup-settings&session=<?= $session; ?>&edit_profile=' + encodeURIComponent(this.value);">
                  <option value="">-- <?= fup_t('_fup_select_profile_ph', 'Pilih Profil'); ?> --</option>
                  <?php foreach ($getProfiles as $p): ?>
                    <?php $pName = $p['name'] ?? ''; ?>
                    <?php if (!empty($pName)): ?>
                      <?php $isSelected = ($selectedProfile === $pName) ? 'selected' : ''; ?>
                      <option value="<?= htmlspecialchars($pName); ?>" <?= $isSelected; ?>>
                        <?= htmlspecialchars($pName); ?> (<?= fup_t('_fup_normal_rate_lbl', 'Rate Limit:'); ?> <?= htmlspecialchars($p['rate-limit'] ?: 'Unlimited'); ?>)
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= fup_t('_fup_profile_status', 'Status FUP'); ?></td>
              <td>
                <select name="fup_status" class="form-control">
                  <option value="enabled" <?= ($currentRule && !empty($currentRule['enabled'])) ? 'selected' : ''; ?>><?= fup_t('_fup_opt_enabled', 'Aktif (Terapkan FUP)'); ?></option>
                  <option value="disabled" <?= ($currentRule && empty($currentRule['enabled'])) ? 'selected' : ''; ?>><?= fup_t('_fup_opt_disabled', 'Nonaktif (Bypass FUP)'); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= fup_t('_fup_threshold_label', 'Batas Kuota FUP'); ?></td>
              <td>
                <div class="input-group">
                  <div class="input-group-9 col-box-8">
                    <input class="group-item group-item-l" type="number" step="0.1" min="0.1" name="threshold_num" value="<?= htmlspecialchars($currentRule['threshold_val'] ?? '10'); ?>" required>
                  </div>
                  <div class="input-group-3 col-box-4">
                    <select style="padding:4.2px;" class="group-item group-item-r" name="threshold_unit">
                      <option value="GB" <?= (($currentRule['threshold_unit'] ?? 'GB') === 'GB') ? 'selected' : ''; ?>>GB</option>
                      <option value="MB" <?= (($currentRule['threshold_unit'] ?? '') === 'MB') ? 'selected' : ''; ?>>MB</option>
                    </select>
                  </div>
                </div>
                <small style="opacity: 0.75; display: block; margin-top: 4px;"><?= fup_t('_fup_threshold_hint', 'Contoh: 10 GB atau 5000 MB'); ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= fup_t('_fup_throttled_rate_label', 'Kecepatan Setelah FUP'); ?></td>
              <td>
                <input type="text" name="throttled_rate" class="form-control" value="<?= htmlspecialchars($currentRule['throttled_rate'] ?? '1M/1M'); ?>" placeholder="1M/1M" required>
                <small style="opacity: 0.75; display: block; margin-top: 4px;"><?= fup_t('_fup_throttled_rate_hint', 'Format MikroTik Rx/Tx (contoh: 512k/512k atau 1M/1M)'); ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= fup_t('_fup_reset_cycle_label', 'Siklus Reset'); ?></td>
              <td>
                <select name="reset_cycle" class="form-control">
                  <option value="manual" <?= (($currentRule['reset_cycle'] ?? 'manual') === 'manual') ? 'selected' : ''; ?>><?= fup_t('_fup_cycle_manual', 'Manual'); ?></option>
                  <option value="daily" <?= (($currentRule['reset_cycle'] ?? '') === 'daily') ? 'selected' : ''; ?>><?= fup_t('_fup_cycle_daily', 'Harian'); ?></option>
                  <option value="monthly" <?= (($currentRule['reset_cycle'] ?? '') === 'monthly') ? 'selected' : ''; ?>><?= fup_t('_fup_cycle_monthly', 'Bulanan'); ?></option>
                </select>
                <small style="opacity: 0.75; display: block; margin-top: 4px;"><?= fup_t('_fup_reset_cycle_hint', 'Frekuensi reset kuota kembali ke normal'); ?></small>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>

  <!-- Info Col-4 (Readme & Status Card) -->
  <div class="col-4 col-box-12">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-book"></i> <?= fup_t('_readme', 'Info & Panduan'); ?></h3>
      </div>
      <div class="card-body">
        <p style="margin: 0 0 12px 0; font-size: 13px; line-height: 1.5;">
          <?= fup_t('_fup_engine_info'); ?>
        </p>
        <div style="border-top: 1px solid rgba(127,127,127,0.25); padding-top: 12px;">
          <a href="./?bandwidth=action&action=resync_all&session=<?= $session; ?>" class="btn bg-primary" onclick="return confirm('<?= addslashes(fup_t('_fup_confirm_apply_now')); ?>');" style="display: block; text-align: center;">
            <i class="fa fa-refresh btn-mrg"></i> <?= fup_t('_sync_short', 'Sinkronkan'); ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Configured Rules List Table Section (Col-12) -->
<div class="row mr-t-15">
  <div class="col-12">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-list"></i> <?= fup_t('_fup_rules_list_title', 'Daftar Aturan FUP'); ?></h3>
      </div>
      <div class="card-body">
        <div class="overflow box-bordered mr-t-10" style="max-height: 60vh;">
          <table id="fupRulesTable" class="table table-bordered table-hover text-nowrap">
            <thead>
              <tr>
                <th><?= fup_t('_fup_th_rule_profile', 'Profil'); ?></th>
                <th><?= fup_t('_fup_th_fup_status', 'Status'); ?></th>
                <th><?= fup_t('_fup_th_rule_threshold', 'Batas Kuota'); ?></th>
                <th><?= fup_t('_fup_th_rule_normal_rate', 'Rate Normal'); ?></th>
                <th><?= fup_t('_fup_th_rule_throttled_rate', 'Rate FUP'); ?></th>
                <th><?= fup_t('_fup_th_rule_cycle', 'Siklus Reset'); ?></th>
                <th style="text-align: center; width: 100px;"><?= fup_t('_fup_th_action', 'Aksi'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($fupConfig['profiles'])): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 15px;">
                    <i><?= fup_t('_fup_rules_empty', 'Belum ada aturan FUP.'); ?></i>
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
                        <span class="badge bg-green"><i class="fa fa-check"></i> <?= fup_t('_active', 'Aktif'); ?></span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><i class="fa fa-ban"></i> <?= fup_t('_deactive', 'Nonaktif'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; font-weight: bold;">
                      <?= htmlspecialchars($rule['threshold_val'] . ' ' . $rule['threshold_unit']); ?> 
                      <small>(<?= formatBytes2($rule['threshold_bytes'], 2); ?>)</small>
                    </td>
                    <td style="vertical-align: middle;">
                      <span class="badge bg-primary"><?= htmlspecialchars($normRate ?: 'Unlimited'); ?></span>
                    </td>
                    <td style="vertical-align: middle;">
                      <span class="badge bg-red" style="font-weight: bold;"><?= htmlspecialchars($rule['throttled_rate']); ?></span>
                    </td>
                    <td style="vertical-align: middle;">
                      <?php
                        $cLabel = fup_t('_fup_cycle_manual_short', 'Manual');
                        if ($rule['reset_cycle'] === 'daily') $cLabel = fup_t('_fup_cycle_daily_short', 'Harian');
                        elseif ($rule['reset_cycle'] === 'monthly') $cLabel = fup_t('_fup_cycle_monthly_short', 'Bulanan');
                      ?>
                      <?= $cLabel; ?>
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                      <a href="./?bandwidth=fup-settings&session=<?= $session; ?>&edit_profile=<?= urlencode($profName); ?>" title="<?= fup_t('_edit'); ?>">
                        <i class="fa fa-edit text-warning pointer"></i> <?= fup_t('_edit'); ?>
                      </a>
                      &nbsp;&nbsp;&nbsp;
                      <i class="fa fa-minus-square text-danger pointer" onclick="if(confirm('<?= sprintf(addslashes(fup_t('_fup_confirm_delete')), htmlspecialchars($profName, ENT_QUOTES)); ?>')){ var f=document.createElement('form'); f.method='POST'; f.action='./?bandwidth=fup-settings&session=<?= $session; ?>'; var a=document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete_fup_rule'; f.appendChild(a); var p=document.createElement('input'); p.type='hidden'; p.name='profile_name'; p.value='<?= htmlspecialchars($profName, ENT_QUOTES); ?>'; f.appendChild(p); document.body.appendChild(f); f.submit(); }else{}" title="<?= fup_t('_delete'); ?>"></i>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
