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
            $message = sprintf($_fup_msg_rule_saved ?? fup_t('_fup_msg_rule_saved'), htmlspecialchars($targetProfile));
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'delete_fup_rule') {
        $targetProfile = trim($_POST['profile_name'] ?? '');
        if (!empty($targetProfile) && isset($fupConfig['profiles'][$targetProfile])) {
            unset($fupConfig['profiles'][$targetProfile]);
            fup_save_config($session, $fupConfig, $API);
            $message = sprintf($_fup_msg_rule_deleted ?? fup_t('_fup_msg_rule_deleted'), htmlspecialchars($targetProfile));
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'resync_all') {
        fup_save_config($session, $fupConfig, $API);
        $message = $_fup_msg_resync_success ?? fup_t('_fup_msg_resync_success');
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
          <?= $_fup_engine_info ?? fup_t('_fup_engine_info'); ?>
        </div>
      </div>
    </div>

    <div class="card box-bordered">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <h3><i class="fa fa-sliders"></i> <?= $_fup_settings ?? fup_t('_fup_settings'); ?> (Fair Usage Policy)</h3>
        <div>
          <a href="./?bandwidth=top-voucher&session=<?= $session; ?>" class="btn bg-default">
            <i class="fa fa-arrow-left"></i> <?= $_fup_back_top_tier ?? fup_t('_fup_back_top_tier'); ?>
          </a>
        </div>
      </div>

      <div class="card-body">
        <!-- FUP Configuration Form -->
        <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>">
          <input type="hidden" name="action" value="save_fup_rule">

          <div class="box pd-15 mr-b-20 box-bordered radius-5">
            <h4 style="margin-top: 0; margin-bottom: 12px; font-weight: bold; border-bottom: 1px solid rgba(127, 127, 127, 0.25); padding-bottom: 6px;">
              <i class="fa fa-pencil-square-o"></i> <?= $_fup_form_title ?? fup_t('_fup_form_title'); ?>
            </h4>

            <div class="row">
              <!-- Select Profile -->
              <div class="col-6 col-box-12 mr-b-10">
                <label style="font-weight: bold;"><?= $_fup_select_profile ?? fup_t('_fup_select_profile'); ?></label>
                <select name="profile_name" class="form-control" required onchange="window.location.href='./?bandwidth=fup-settings&session=<?= $session; ?>&edit_profile=' + encodeURIComponent(this.value);">
                  <option value="">-- <?= $_fup_select_profile_ph ?? fup_t('_fup_select_profile_ph'); ?> --</option>
                  <?php foreach ($getProfiles as $p): ?>
                    <?php $pName = $p['name'] ?? ''; ?>
                    <?php if (!empty($pName)): ?>
                      <?php $isSelected = ($selectedProfile === $pName) ? 'selected' : ''; ?>
                      <option value="<?= htmlspecialchars($pName); ?>" <?= $isSelected; ?>>
                        <?= htmlspecialchars($pName); ?> (<?= $_fup_normal_rate_lbl ?? fup_t('_fup_normal_rate_lbl'); ?> <?= htmlspecialchars($p['rate-limit'] ?: 'Unlimited'); ?>)
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Status FUP -->
              <div class="col-6 col-box-12 mr-b-10">
                <label style="font-weight: bold;"><?= $_fup_profile_status ?? fup_t('_fup_profile_status'); ?></label>
                <select name="fup_status" class="form-control">
                  <option value="enabled" <?= ($currentRule && !empty($currentRule['enabled'])) ? 'selected' : ''; ?>><?= $_fup_opt_enabled ?? fup_t('_fup_opt_enabled'); ?></option>
                  <option value="disabled" <?= ($currentRule && empty($currentRule['enabled'])) ? 'selected' : ''; ?>><?= $_fup_opt_disabled ?? fup_t('_fup_opt_disabled'); ?></option>
                </select>
              </div>
            </div>

            <div class="row">
              <!-- Threshold Quota -->
              <div class="col-4 col-box-12 mr-b-10">
                <label style="font-weight: bold;"><?= $_fup_threshold_label ?? fup_t('_fup_threshold_label'); ?></label>
                <div style="display: flex; gap: 6px;">
                  <input type="number" step="0.1" min="0.1" name="threshold_num" class="form-control" style="flex: 2;" value="<?= htmlspecialchars($currentRule['threshold_val'] ?? '10'); ?>" required>
                  <select name="threshold_unit" class="form-control" style="flex: 1;">
                    <option value="GB" <?= (($currentRule['threshold_unit'] ?? 'GB') === 'GB') ? 'selected' : ''; ?>>GB</option>
                    <option value="MB" <?= (($currentRule['threshold_unit'] ?? '') === 'MB') ? 'selected' : ''; ?>>MB</option>
                  </select>
                </div>
                <small style="opacity: 0.75;"><?= $_fup_threshold_hint ?? fup_t('_fup_threshold_hint'); ?></small>
              </div>

              <!-- Throttled Rate -->
              <div class="col-4 col-box-12 mr-b-10">
                <label style="font-weight: bold;"><?= $_fup_throttled_rate_label ?? fup_t('_fup_throttled_rate_label'); ?></label>
                <input type="text" name="throttled_rate" class="form-control" value="<?= htmlspecialchars($currentRule['throttled_rate'] ?? '1M/1M'); ?>" placeholder="upload/download" required>
                <small style="opacity: 0.75;"><?= $_fup_throttled_rate_hint ?? fup_t('_fup_throttled_rate_hint'); ?></small>
              </div>

              <!-- Reset Cycle -->
              <div class="col-4 col-box-12 mr-b-10">
                <label style="font-weight: bold;"><?= $_fup_reset_cycle_label ?? fup_t('_fup_reset_cycle_label'); ?></label>
                <select name="reset_cycle" class="form-control">
                  <option value="manual" <?= (($currentRule['reset_cycle'] ?? 'manual') === 'manual') ? 'selected' : ''; ?>><?= $_fup_cycle_manual ?? fup_t('_fup_cycle_manual'); ?></option>
                  <option value="daily" <?= (($currentRule['reset_cycle'] ?? '') === 'daily') ? 'selected' : ''; ?>><?= $_fup_cycle_daily ?? fup_t('_fup_cycle_daily'); ?></option>
                  <option value="monthly" <?= (($currentRule['reset_cycle'] ?? '') === 'monthly') ? 'selected' : ''; ?>><?= $_fup_cycle_monthly ?? fup_t('_fup_cycle_monthly'); ?></option>
                </select>
                <small style="opacity: 0.75;"><?= $_fup_reset_cycle_hint ?? fup_t('_fup_reset_cycle_hint'); ?></small>
              </div>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
              <button type="submit" class="btn bg-primary">
                <i class="fa fa-save"></i> <?= $_fup_save_sync ?? fup_t('_fup_save_sync'); ?>
              </button>
            </div>
          </div>
        </form>

        <!-- Current Configured Rules Table Wrapped with Dedicated Scroll Container -->
        <h4 style="margin-bottom: 10px; font-weight: bold;">
          <i class="fa fa-list"></i> <?= $_fup_rules_list_title ?? fup_t('_fup_rules_list_title'); ?>
        </h4>

        <div class="overflow mr-t-10 box-bordered" style="max-height: 60vh;">
          <table class="table table-bordered table-hover text-nowrap">
            <thead>
              <tr>
                <th><?= $_fup_th_rule_profile ?? fup_t('_fup_th_rule_profile'); ?></th>
                <th><?= $_fup_th_fup_status ?? fup_t('_fup_th_fup_status'); ?></th>
                <th><?= $_fup_th_rule_threshold ?? fup_t('_fup_th_rule_threshold'); ?></th>
                <th><?= $_fup_th_rule_normal_rate ?? fup_t('_fup_th_rule_normal_rate'); ?></th>
                <th><?= $_fup_th_rule_throttled_rate ?? fup_t('_fup_th_rule_throttled_rate'); ?></th>
                <th><?= $_fup_th_rule_cycle ?? fup_t('_fup_th_rule_cycle'); ?></th>
                <th style="text-align: center; width: 140px;"><?= $_fup_th_action ?? fup_t('_fup_th_action'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($fupConfig['profiles'])): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 15px; opacity: 0.7;">
                    <i><?= $_fup_rules_empty ?? fup_t('_fup_rules_empty'); ?></i>
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
                        <span class="badge bg-green"><i class="fa fa-check"></i> <?= $_active ?? fup_t('_active'); ?></span>
                      <?php else: ?>
                        <span class="badge bg-default"><i class="fa fa-ban"></i> <?= $_deactive ?? fup_t('_deactive'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; font-weight: bold;">
                      <?= htmlspecialchars($rule['threshold_val'] . ' ' . $rule['threshold_unit']); ?> 
                      <small style="opacity: 0.75;">(<?= formatBytes2($rule['threshold_bytes'], 2); ?>)</small>
                    </td>
                    <td style="vertical-align: middle;">
                      <span class="badge bg-primary"><?= htmlspecialchars($normRate ?: 'Unlimited'); ?></span>
                    </td>
                    <td style="vertical-align: middle;">
                      <span class="badge bg-red" style="font-weight: bold;"><?= htmlspecialchars($rule['throttled_rate']); ?></span>
                    </td>
                    <td style="vertical-align: middle;">
                      <?php
                        $cLabel = $_fup_cycle_manual_short ?? fup_t('_fup_cycle_manual_short');
                        if ($rule['reset_cycle'] === 'daily') $cLabel = $_fup_cycle_daily_short ?? fup_t('_fup_cycle_daily_short');
                        elseif ($rule['reset_cycle'] === 'monthly') $cLabel = $_fup_cycle_monthly_short ?? fup_t('_fup_cycle_monthly_short');
                      ?>
                      <?= $cLabel; ?>
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                      <div style="display: inline-flex; gap: 4px;">
                        <a href="./?bandwidth=fup-settings&session=<?= $session; ?>&edit_profile=<?= urlencode($profName); ?>" class="btn btn-sm bg-warning" title="Edit aturan FUP">
                          <i class="fa fa-edit"></i> <?= $_edit ?? fup_t('_edit'); ?>
                        </a>
                        <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>" style="display: inline;" onsubmit="return confirm('<?= sprintf(addslashes($_fup_confirm_delete ?? fup_t('_fup_confirm_delete')), htmlspecialchars($profName, ENT_QUOTES)); ?>');">
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
        </div>

        <!-- Resync All Button -->
        <div style="margin-top: 15px;">
          <form method="POST" action="./?bandwidth=fup-settings&session=<?= $session; ?>">
            <input type="hidden" name="action" value="resync_all">
            <button type="submit" class="btn bg-default" title="<?= $_fup_resync_btn_title ?? fup_t('_fup_resync_btn_title'); ?>">
              <i class="fa fa-refresh"></i> <?= $_fup_resync_btn ?? fup_t('_fup_resync_btn'); ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
