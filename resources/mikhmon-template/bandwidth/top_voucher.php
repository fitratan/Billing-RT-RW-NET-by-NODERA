<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * Top Tier Voucher & Real-time Traffic Leaderboard
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

$filterProf = isset($_GET['prof']) ? trim($_GET['prof']) : 'all';
$fupConfig = fup_load_config($session, $API);

// Fetch Hotspot Users
$users_props = ".id,name,profile,uptime,bytes-in,bytes-out,limit-bytes-total,limit-uptime,comment,disabled";
$getUsers = $API->comm("/ip/hotspot/user/print", array(
    ".proplist" => "$users_props"
));
$getUsers = is_array($getUsers) ? $getUsers : array();

// Fetch Active Users
$active_props = ".id,server,user,address,mac-address,uptime,bytes-in,bytes-out,time-left";
$getActive = $API->comm("/ip/hotspot/active/print", array(
    ".proplist" => "$active_props"
));
$getActive = is_array($getActive) ? $getActive : array();
$activeMap = array();
foreach ($getActive as $act) {
    if (!empty($act['user'])) {
        $activeMap[$act['user']] = $act;
    }
}

// Fetch Queues for Real-time Rates
$getQueues = $API->comm("/queue/simple/print", array(
    ".proplist" => ".id,name,rate,max-limit"
));
$getQueues = is_array($getQueues) ? $getQueues : array();
$queueMap = array();
foreach ($getQueues as $q) {
    if (!empty($q['name'])) {
        $queueMap[$q['name']] = $q;
    }
}

// Fetch Profiles for dropdown
$getProfiles = $API->comm("/ip/hotspot/user/profile/print", array(
    ".proplist" => ".id,name,rate-limit"
));
$getProfiles = is_array($getProfiles) ? $getProfiles : array();
$profileRateMap = array();
foreach ($getProfiles as $p) {
    if (!empty($p['name'])) {
        $profileRateMap[$p['name']] = $p['rate-limit'] ?? '';
    }
}

// Process and Sort Users by Total Bandwidth
$userRows = array();
$statTotalVouchers = 0;
$statNormal = 0;
$statThrottled = 0;
$statOnline = 0;
$statTotalBytes = 0;

foreach ($getUsers as $u) {
    $uName = $u['name'] ?? '';
    if (empty($uName) || $uName === 'default-trial') continue;

    $uProfile = $u['profile'] ?? '';
    if ($filterProf !== 'all' && $uProfile !== $filterProf) {
        continue;
    }

    $bIn = (float)($u['bytes-in'] ?? 0);
    $bOut = (float)($u['bytes-out'] ?? 0);
    $total = $bIn + $bOut;
    $statTotalVouchers++;
    $statTotalBytes += $total;

    $isActive = isset($activeMap[$uName]);
    $actInfo = $isActive ? $activeMap[$uName] : null;
    if ($isActive) {
        $statOnline++;
    }

    // Determine Real-time Transfer Rate
    $rateStr = '-';
    $qKey = '<hotspot-' . $uName . '>';
    if ($isActive && isset($queueMap[$qKey])) {
        $rawRate = $queueMap[$qKey]['rate'] ?? '0/0';
        $parts = explode('/', $rawRate);
        $upBps = isset($parts[0]) ? (float)$parts[0] : 0;
        $downBps = isset($parts[1]) ? (float)$parts[1] : 0;
        if ($upBps > 0 || $downBps > 0) {
            $rateStr = formatBites($downBps, 1) . ' / ' . formatBites($upBps, 1);
        } else {
            $rateStr = 'Idle (0 bps)';
        }
    } elseif ($isActive) {
        $rateStr = 'Online';
    }

    // Determine FUP State
    $fupRule = $fupConfig['profiles'][$uProfile] ?? null;
    $hasFup = ($fupRule && !empty($fupRule['enabled']) && !empty($fupRule['threshold_bytes']));
    $fupThreshold = $hasFup ? (float)$fupRule['threshold_bytes'] : 0;
    $fupRate = $hasFup ? ($fupRule['throttled_rate'] ?? '1M/1M') : '';

    $fupState = 'NONE';
    $fupPercent = 0;
    if ($hasFup && $fupThreshold > 0) {
        $fupPercent = round(($total / $fupThreshold) * 100, 1);
        if ($total >= $fupThreshold) {
            $fupState = 'THROTTLED';
            $statThrottled++;
        } elseif ($fupPercent >= 80) {
            $fupState = 'WARNING';
            $statNormal++;
        } else {
            $fupState = 'NORMAL';
            $statNormal++;
        }
    } else {
        $statNormal++;
    }

    $limitBytes = (float)($u['limit-bytes-total'] ?? 0);

    $userRows[] = array(
        'id' => $u['.id'] ?? '',
        'name' => $uName,
        'profile' => $uProfile,
        'bytes_in' => $bIn,
        'bytes_out' => $bOut,
        'total' => $total,
        'uptime' => $u['uptime'] ?? '-',
        'comment' => $u['comment'] ?? '',
        'disabled' => $u['disabled'] ?? 'false',
        'is_active' => $isActive,
        'act_id' => $actInfo['.id'] ?? '',
        'address' => $actInfo['address'] ?? '-',
        'mac' => $actInfo['mac-address'] ?? '-',
        'rate_str' => $rateStr,
        'has_fup' => $hasFup,
        'fup_threshold' => $fupThreshold,
        'fup_rate' => $fupRate,
        'fup_state' => $fupState,
        'fup_percent' => $fupPercent,
        'limit_bytes' => $limitBytes
    );
}

// Sort descending by Total Bytes
usort($userRows, function($a, $b) {
    if ($a['total'] == $b['total']) return 0;
    return ($a['total'] < $b['total']) ? 1 : -1;
});

$notice = $_SESSION['fup_notice'] ?? '';
unset($_SESSION['fup_notice']);

$statTrafficFormatted = formatBytes2($statTotalBytes, 2);
$partsTraffic = explode(' ', $statTrafficFormatted, 2);
$statTrafficNum = $partsTraffic[0] ?? $statTrafficFormatted;
$statTrafficUnit = $partsTraffic[1] ?? '';
?>

<div class="row">
  <div class="col-12">
    <?php if (!empty($notice)): ?>
      <div class="box bg-green pd-10 mr-b-10" style="border-radius: 4px; font-weight: bold;">
        <i class="fa fa-check-circle"></i> <?= htmlspecialchars($notice); ?>
      </div>
    <?php endif; ?>

    <!-- Summary Stat Boxes in Native Mikhmon Style -->
    <div class="row">
      <div class="col-3 col-box-6">
        <div class="box bg-blue bmh-75 pointer" onclick="filterVoucherStatus('all');" title="<?= fup_t('_fup_title_filter_all'); ?>">
          <h1><?= $statTotalVouchers; ?></h1>
          <div><i class="fa fa-users"></i> <?= fup_t('_fup_total_vouchers'); ?></div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-green bmh-75 pointer" onclick="filterVoucherStatus('normal');" title="<?= fup_t('_fup_title_filter_normal'); ?>">
          <h1><?= $statNormal; ?></h1>
          <div><i class="fa fa-check-circle"></i> <?= fup_t('_fup_normal_vouchers'); ?></div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-red bmh-75 pointer" onclick="filterVoucherStatus('throttled');" title="<?= fup_t('_fup_title_filter_throttled'); ?>">
          <h1><?= $statThrottled; ?></h1>
          <div><i class="fa fa-tachometer"></i> <?= fup_t('_fup_active_vouchers'); ?></div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-yellow bmh-75">
          <h1><?= $statTrafficNum; ?> <span style="font-size: 14px;"><?= $statTrafficUnit; ?></span></h1>
          <div><i class="fa fa-database"></i> <?= fup_t('_fup_total_traffic'); ?></div>
        </div>
      </div>
    </div>

    <!-- Main Card Container -->
    <div class="card box-bordered mr-t-10">
      <div class="card-header">
        <h3><i class="fa fa-bar-chart"></i> <?= fup_t('_top_tier_voucher', 'Top Tier Voucher'); ?>
          <span style="font-size: 14px;">
            &nbsp; | &nbsp; <a href="./?bandwidth=fup-settings&session=<?= $session; ?>"><i class="fa fa-sliders"></i> <?= fup_t('_fup_settings', 'Pengaturan FUP'); ?></a>
            &nbsp; | &nbsp; <a href="./?bandwidth=action&action=sync_fup_now&session=<?= $session; ?>" onclick="return confirm('<?= addslashes(fup_t('_fup_confirm_apply_now')); ?>');"><i class="fa fa-refresh"></i> <?= fup_t('_fup_apply_now', 'Terapkan FUP'); ?></a>
            &nbsp; | &nbsp; <a href="./?bandwidth=interface-traffic&session=<?= $session; ?>"><i class="fa fa-exchange"></i> <?= fup_t('_total_bandwidth', 'Total Bandwidth'); ?></a>
          </span>
        </h3>
      </div>

      <div class="card-body">
        <!-- Native Search & Filter Toolbar -->
        <div class="row">
          <div class="col-6 col-box-12 pd-t-5 pd-b-5">
            <div class="input-group">
              <div class="input-group-4 col-box-4">
                <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= fup_t('_search', 'Cari'); ?>...">
              </div>
              <div class="input-group-4 col-box-4">
                <select id="selectProfile" style="padding:5px;" class="group-item group-item-m" onchange="window.location.href='./?bandwidth=top-voucher&session=<?= $session; ?>&prof=' + encodeURIComponent(this.value);">
                  <option value="all" <?= ($filterProf === 'all') ? 'selected' : ''; ?>>-- <?= fup_t('_fup_all_profiles', 'Semua Profil'); ?> --</option>
                  <?php foreach ($getProfiles as $prof): ?>
                    <?php $pName = $prof['name'] ?? ''; ?>
                    <?php if (!empty($pName)): ?>
                      <option value="<?= htmlspecialchars($pName); ?>" <?= ($filterProf === $pName) ? 'selected' : ''; ?>><?= htmlspecialchars($pName); ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="input-group-4 col-box-4">
                <select id="selectStatus" style="padding:5px;" class="group-item group-item-r">
                  <option value="all"><?= fup_t('_fup_stat_all', 'Semua'); ?> (<?= count($userRows); ?>)</option>
                  <option value="throttled"><?= fup_t('_fup_stat_throttled', 'Kena FUP'); ?> (<?= $statThrottled; ?>)</option>
                  <option value="online"><?= fup_t('_fup_stat_online', 'Online'); ?> (<?= $statOnline; ?>)</option>
                  <option value="normal"><?= fup_t('_fup_stat_normal', 'Normal'); ?> (<?= $statNormal; ?>)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Leaderboard Table Wrapped with Mikhmon Native Scroll Container -->
        <div class="overflow box-bordered mr-t-10" style="max-height: 75vh;">
          <table id="dataTable" class="table table-bordered table-hover text-nowrap">
            <thead>
              <tr>
                <th style="width: 45px; text-align: center;"><?= fup_t('_fup_th_rank', 'Rank'); ?></th>
                <th><?= fup_t('_fup_th_voucher', 'Voucher / Akun'); ?></th>
                <th><?= fup_t('_fup_th_profile', 'Profil'); ?></th>
                <th><?= fup_t('_fup_th_connection', 'Koneksi'); ?></th>
                <th><?= fup_t('_fup_th_fup_status', 'Status FUP'); ?></th>
                <th><?= fup_t('_fup_th_speed', 'Speed Aktif'); ?></th>
                <th><?= fup_t('_fup_th_download', 'Download'); ?></th>
                <th><?= fup_t('_fup_th_upload', 'Upload'); ?></th>
                <th><?= fup_t('_fup_th_total_quota', 'Total Kuota'); ?></th>
                <th><?= fup_t('_fup_th_progress_remain', 'Progres / Sisa'); ?></th>
                <th><?= fup_t('_fup_th_uptime', 'Uptime'); ?></th>
                <th style="text-align: center; width: 80px;"><?= fup_t('_fup_th_action', 'Aksi'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($userRows)): ?>
                <tr>
                  <td colspan="12" style="text-align: center; padding: 20px;">
                    <i><?= fup_t('_fup_no_data', 'Tidak ada data voucher'); ?></i>
                  </td>
                </tr>
              <?php else: ?>
                <?php $rank = 1; ?>
                <?php foreach ($userRows as $row): ?>
                  <?php
                    $filterTag = 'normal';
                    if ($row['fup_state'] === 'THROTTLED') {
                        $filterTag = 'throttled';
                    }
                    if ($row['is_active']) {
                        $filterTag .= ' online';
                    }
                  ?>
                  <tr class="voucher-row" data-tag="<?= $filterTag; ?>">
                    <!-- Rank -->
                    <td style="text-align: center; vertical-align: middle;">
                      <?php if ($rank === 1): ?>
                        <span class="badge bg-yellow" style="font-weight: bold; font-size: 12px; padding: 3px 7px;">#1</span>
                      <?php elseif ($rank === 2 || $rank === 3): ?>
                        <span class="badge bg-secondary" style="font-weight: bold; font-size: 12px; padding: 3px 7px;">#<?= $rank; ?></span>
                      <?php else: ?>
                        <span class="badge bg-secondary" style="font-size: 11px;"><?= $rank; ?></span>
                      <?php endif; ?>
                    </td>

                    <!-- Voucher Name -->
                    <td style="vertical-align: middle;">
                      <a href="./?hotspot-user=<?= urlencode($row['name']); ?>&session=<?= $session; ?>" style="font-weight: bold; font-size: 13px;">
                        <?= htmlspecialchars($row['name']); ?>
                      </a>
                      <?php if (!empty($row['comment'])): ?>
                        <br><small><?= htmlspecialchars($row['comment']); ?></small>
                      <?php endif; ?>
                    </td>

                    <!-- Profile -->
                    <td style="vertical-align: middle;">
                      <span class="badge bg-primary"><?= htmlspecialchars($row['profile']); ?></span>
                    </td>

                    <!-- Connection Status -->
                    <td style="vertical-align: middle;">
                      <?php if ($row['is_active']): ?>
                        <span class="badge bg-green" title="<?= fup_t('_fup_badge_online_desc'); ?>"><i class="fa fa-wifi"></i> <?= fup_t('_fup_badge_online'); ?></span>
                        <br><small><?= htmlspecialchars($row['address']); ?></small>
                      <?php else: ?>
                        <span class="badge bg-secondary"><i class="fa fa-power-off"></i> <?= fup_t('_fup_badge_offline'); ?></span>
                      <?php endif; ?>
                    </td>

                    <!-- FUP Status -->
                    <td style="vertical-align: middle;">
                      <?php if ($row['fup_state'] === 'THROTTLED'): ?>
                        <span class="badge bg-red" style="font-weight: bold;" title="<?= sprintf(fup_t('_fup_badge_throttled_desc'), htmlspecialchars($row['fup_rate'])); ?>">
                          <i class="fa fa-tachometer"></i> <?= fup_t('_fup_badge_throttled'); ?>
                        </span>
                        <br><small class="text-danger" style="font-weight: bold;"><?= fup_t('_fup_limit_prefix'); ?> <?= htmlspecialchars($row['fup_rate']); ?></small>
                      <?php elseif ($row['fup_state'] === 'WARNING'): ?>
                        <span class="badge bg-yellow" title="<?= sprintf(fup_t('_fup_badge_warning_desc'), $row['fup_percent']); ?>">
                          <i class="fa fa-warning"></i> <?= sprintf(fup_t('_fup_badge_warning'), $row['fup_percent']); ?>
                        </span>
                      <?php elseif ($row['fup_state'] === 'NORMAL'): ?>
                        <span class="badge bg-green" title="<?= sprintf(fup_t('_fup_badge_normal_desc'), $row['fup_percent']); ?>">
                          <i class="fa fa-check"></i> <?= fup_t('_fup_badge_normal'); ?>
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?= fup_t('_fup_badge_no_fup'); ?></span>
                      <?php endif; ?>
                    </td>

                    <!-- Real-time Speed -->
                    <td style="vertical-align: middle; font-family: monospace; font-size: 12px;">
                      <?php if ($row['is_active'] && $row['rate_str'] !== 'Idle (0 bps)' && $row['rate_str'] !== 'Online'): ?>
                        <strong class="text-green"><?= htmlspecialchars($row['rate_str']); ?></strong>
                      <?php elseif ($row['is_active']): ?>
                        <span><?= htmlspecialchars($row['rate_str']); ?></span>
                      <?php else: ?>
                        <span>-</span>
                      <?php endif; ?>
                    </td>

                    <!-- Download -->
                    <td style="vertical-align: middle;">
                      <?= formatBytes2($row['bytes_out'], 2); ?>
                    </td>

                    <!-- Upload -->
                    <td style="vertical-align: middle;">
                      <?= formatBytes2($row['bytes_in'], 2); ?>
                    </td>

                    <!-- Total -->
                    <td style="vertical-align: middle; font-weight: bold;">
                      <?= formatBytes2($row['total'], 2); ?>
                    </td>

                    <!-- Progress / Sisa Kuota -->
                    <td style="vertical-align: middle; min-width: 140px;">
                      <?php if ($row['has_fup'] && $row['fup_threshold'] > 0): ?>
                        <?php
                          $barPct = min(100, $row['fup_percent']);
                          $barColor = ($row['total'] >= $row['fup_threshold']) ? '#e74c3c' : (($barPct >= 80) ? '#f39c12' : '#2ecc71');
                          $remain = max(0, $row['fup_threshold'] - $row['total']);
                        ?>
                        <div style="background: rgba(127, 127, 127, 0.25); border-radius: 4px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 3px;">
                          <div style="background: <?= $barColor; ?>; height: 100%; width: <?= $barPct; ?>%;"></div>
                        </div>
                        <small style="font-size: 11px;">
                          <?= formatBytes2($row['total'], 1); ?> / <?= formatBytes2($row['fup_threshold'], 1); ?> 
                          (<?= fup_t('_fup_remaining'); ?>: <?= formatBytes2($remain, 1); ?>)
                        </small>
                      <?php elseif ($row['limit_bytes'] > 0): ?>
                        <?php
                          $lPct = min(100, round(($row['total'] / $row['limit_bytes']) * 100, 1));
                          $lRemain = max(0, $row['limit_bytes'] - $row['total']);
                        ?>
                        <div style="background: rgba(127, 127, 127, 0.25); border-radius: 4px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 3px;">
                          <div style="background: #3498db; height: 100%; width: <?= $lPct; ?>%;"></div>
                        </div>
                        <small style="font-size: 11px;">
                          <?= formatBytes2($row['total'], 1); ?> / <?= formatBytes2($row['limit_bytes'], 1); ?>
                        </small>
                      <?php else: ?>
                        <small><?= fup_t('_unlimited'); ?></small>
                      <?php endif; ?>
                    </td>

                    <!-- Uptime -->
                    <td style="vertical-align: middle;">
                      <?= htmlspecialchars($row['uptime']); ?>
                    </td>

                    <!-- Quick Actions with Mikhmon Native Pointer Icons -->
                    <td style="vertical-align: middle; text-align: center;">
                      <a href="./?bandwidth=action&action=reset_user&user_id=<?= urlencode($row['id']); ?>&user_name=<?= urlencode($row['name']); ?>&session=<?= $session; ?>" 
                         title="<?= fup_t('_fup_btn_reset_title'); ?>"
                         onclick="return confirm('<?= sprintf(addslashes(fup_t('_fup_confirm_reset')), htmlspecialchars($row['name'], ENT_QUOTES)); ?>');">
                        <i class="fa fa-refresh text-warning pointer"></i>
                      </a>

                      <?php if ($row['is_active']): ?>
                        &nbsp;&nbsp;
                        <a href="./?bandwidth=action&action=kick_user&active_id=<?= urlencode($row['act_id']); ?>&user_name=<?= urlencode($row['name']); ?>&session=<?= $session; ?>" 
                           title="<?= fup_t('_fup_btn_kick_title'); ?>"
                           onclick="return confirm('<?= sprintf(addslashes(fup_t('_fup_confirm_kick')), htmlspecialchars($row['name'], ENT_QUOTES)); ?>');">
                          <i class="fa fa-power-off text-danger pointer"></i>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php $rank++; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  function applyVoucherFilters() {
    var searchVal = ($("#filterTable").val() || "").toLowerCase().trim();
    var statusVal = $("#selectStatus").val() || "all";

    $(".voucher-row").each(function() {
      var row = $(this);
      var tags = (row.attr("data-tag") || "").split(" ");
      var text = row.text().toLowerCase();

      var matchStatus = (statusVal === "all") || (tags.indexOf(statusVal) > -1);
      var matchSearch = (searchVal === "") || (text.indexOf(searchVal) > -1);

      if (matchStatus && matchSearch) {
        row.show();
      } else {
        row.hide();
      }
    });
  }

  $("#filterTable").on("input keyup paste search change", applyVoucherFilters);
  $("#selectStatus").on("change", applyVoucherFilters);

  window.filterVoucherStatus = function(stat) {
    $("#selectStatus").val(stat).trigger("change");
  };
});
</script>
