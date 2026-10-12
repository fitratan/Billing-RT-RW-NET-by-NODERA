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

// Ensure language dictionary is loaded
if (!isset($_bandwidth_management)) {
    $lid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
    $lfile = __DIR__ . '/../lang/' . $lid . '.php';
    if (file_exists($lfile)) {
        include_once $lfile;
    }
}

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
?>

<div class="row">
  <div class="col-12">
    <?php if (!empty($notice)): ?>
      <div class="box bg-green pd-10 mr-b-10" style="border-radius: 4px; font-weight: bold;">
        <i class="fa fa-check-circle"></i> <?= htmlspecialchars($notice); ?>
      </div>
    <?php endif; ?>

    <!-- Summary Stat Cards -->
    <div class="row">
      <div class="col-3 col-box-6">
        <div class="box bg-blue bmh-75 stat-filter-btn" data-filter="all" style="cursor: pointer;" title="<?= $_fup_title_filter_all ?? 'Klik untuk tampilkan semua voucher'; ?>">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-users"></i></div>
            <div class="box-group-area">
              <h1 style="margin: 0; font-size: 26px;"><?= $statTotalVouchers; ?></h1>
              <span style="font-size: 13px; font-weight: bold;"><?= $_fup_total_vouchers ?? 'Total Voucher Terpantau'; ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-green bmh-75 stat-filter-btn" data-filter="normal" style="cursor: pointer;" title="<?= $_fup_title_filter_normal ?? 'Klik untuk filter voucher Normal'; ?>">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-check-circle"></i></div>
            <div class="box-group-area">
              <h1 style="margin: 0; font-size: 26px;"><?= $statNormal; ?></h1>
              <span style="font-size: 13px; font-weight: bold;"><?= $_fup_normal_vouchers ?? 'Normal (Belum FUP)'; ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-red bmh-75 stat-filter-btn" data-filter="throttled" style="cursor: pointer;" title="<?= $_fup_title_filter_throttled ?? 'Klik untuk filter voucher Kena FUP'; ?>">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-tachometer"></i></div>
            <div class="box-group-area">
              <h1 style="margin: 0; font-size: 26px;"><?= $statThrottled; ?></h1>
              <span style="font-size: 13px; font-weight: bold;"><?= $_fup_active_vouchers ?? 'Kena FUP (Throttled)'; ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-yellow bmh-75">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-database"></i></div>
            <div class="box-group-area">
              <h1 style="margin: 0; font-size: 22px;"><?= formatBytes2($statTotalBytes, 2); ?></h1>
              <span style="font-size: 13px; font-weight: bold;"><?= $_fup_total_traffic ?? 'Total Kuota Terpakai'; ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Card Container -->
    <div class="card">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <h3>
          <i class="fa fa-bar-chart"></i> <?= $_top_tier_voucher ?? 'Top Tier Voucher'; ?> 
          <span style="font-size: 14px; font-weight: normal; color: #777;"><?= $_bandwidth_leaderboard_sub ?? '(Leaderboard Penggunaan Bandwidth)'; ?></span>
        </h3>
        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
          <a href="./?bandwidth=fup-settings&session=<?= $session; ?>" class="btn bg-primary" title="<?= $_fup_settings_title ?? 'Atur Threshold FUP per Profile'; ?>">
            <i class="fa fa-sliders"></i> <?= $_fup_settings ?? 'Pengaturan FUP'; ?>
          </a>
          <a href="./?bandwidth=action&action=sync_fup_now&session=<?= $session; ?>" class="btn bg-warning" onclick="return confirm('<?= addslashes($_fup_confirm_apply_now ?? 'Terapkan dan sinkronkan aturan kecepatan FUP ke seluruh koneksi aktif sekarang?'); ?>');" title="<?= $_fup_apply_now_title ?? 'Paksa evaluasi kecepatan FUP pada user aktif'; ?>">
            <i class="fa fa-refresh"></i> <?= $_fup_apply_now ?? 'Terapkan FUP Sekarang'; ?>
          </a>
        </div>
      </div>

      <div class="card-body overflow">
        <!-- Controls & Filters Toolbar -->
        <div style="padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <label style="font-weight: bold; margin: 0;"><i class="fa fa-filter"></i> <?= $_fup_filter_profile ?? 'Filter Profil :'; ?></label>
            <select id="selectProfile" class="form-control" style="width: auto; padding: 4px 8px;" onchange="window.location.href='./?bandwidth=top-voucher&session=<?= $session; ?>&prof=' + encodeURIComponent(this.value);">
              <option value="all" <?= ($filterProf === 'all') ? 'selected' : ''; ?>>-- <?= $_fup_all_profiles ?? 'Semua Profil'; ?> --</option>
              <?php foreach ($getProfiles as $prof): ?>
                <?php $pName = $prof['name'] ?? ''; ?>
                <?php if (!empty($pName)): ?>
                  <option value="<?= htmlspecialchars($pName); ?>" <?= ($filterProf === $pName) ? 'selected' : ''; ?>><?= htmlspecialchars($pName); ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>

            <div class="btn-group" style="display: inline-flex;">
              <button type="button" class="btn btn-sm bg-default stat-quick-btn active" data-filter="all"><?= $_fup_stat_all ?? 'Semua'; ?> (<?= count($userRows); ?>)</button>
              <button type="button" class="btn btn-sm bg-default stat-quick-btn" data-filter="throttled"><?= $_fup_stat_throttled ?? 'Kena FUP'; ?> (<?= $statThrottled; ?>)</button>
              <button type="button" class="btn btn-sm bg-default stat-quick-btn" data-filter="online"><?= $_fup_stat_online ?? 'Online'; ?> (<?= $statOnline; ?>)</button>
              <button type="button" class="btn btn-sm bg-default stat-quick-btn" data-filter="normal"><?= $_fup_stat_normal ?? 'Normal'; ?> (<?= $statNormal; ?>)</button>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 6px;">
            <input id="filterTable" type="text" class="form-control" style="max-width: 220px; padding: 5px 10px; margin: 0;" placeholder="<?= $_fup_search_placeholder ?? 'Cari user / IP / MAC...'; ?>">
          </div>
        </div>

        <!-- Leaderboard Table -->
        <table id="dataTable" class="table table-bordered table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width: 45px; text-align: center;"><?= $_fup_th_rank ?? 'Rank'; ?></th>
              <th><?= $_fup_th_voucher ?? 'Voucher / Akun'; ?></th>
              <th><?= $_fup_th_profile ?? 'Profil'; ?></th>
              <th><?= $_fup_th_connection ?? 'Koneksi'; ?></th>
              <th><?= $_fup_th_fup_status ?? 'Status FUP'; ?></th>
              <th><?= $_fup_th_speed ?? 'Speed Aktif (DL / UL)'; ?></th>
              <th><?= $_fup_th_download ?? 'Download'; ?></th>
              <th><?= $_fup_th_upload ?? 'Upload'; ?></th>
              <th><?= $_fup_th_total_quota ?? 'Total Kuota'; ?></th>
              <th><?= $_fup_th_progress_remain ?? 'Progres / Sisa Kuota'; ?></th>
              <th><?= $_fup_th_uptime ?? 'Uptime'; ?></th>
              <th style="text-align: center; width: 110px;"><?= $_fup_th_action ?? 'Aksi'; ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($userRows)): ?>
              <tr>
                <td colspan="12" style="text-align: center; padding: 20px; color: #888;">
                  <i><?= $_fup_no_data ?? 'Tidak ada data voucher pada profil yang dipilih.'; ?></i>
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
                    <?php elseif ($rank === 2): ?>
                      <span class="badge" style="background: #95a5a6; color: #fff; font-weight: bold; font-size: 12px; padding: 3px 7px;">#2</span>
                    <?php elseif ($rank === 3): ?>
                      <span class="badge" style="background: #d35400; color: #fff; font-weight: bold; font-size: 12px; padding: 3px 7px;">#3</span>
                    <?php else: ?>
                      <span class="badge bg-default" style="font-size: 11px;"><?= $rank; ?></span>
                    <?php endif; ?>
                  </td>

                  <!-- Voucher Name -->
                  <td style="vertical-align: middle;">
                    <a href="./?hotspot-user=<?= urlencode($row['name']); ?>&session=<?= $session; ?>" style="font-weight: bold; font-size: 13px;">
                      <?= htmlspecialchars($row['name']); ?>
                    </a>
                    <?php if (!empty($row['comment'])): ?>
                      <br><small style="color: #777;"><?= htmlspecialchars($row['comment']); ?></small>
                    <?php endif; ?>
                  </td>

                  <!-- Profile -->
                  <td style="vertical-align: middle;">
                    <span class="badge bg-primary"><?= htmlspecialchars($row['profile']); ?></span>
                  </td>

                  <!-- Connection Status -->
                  <td style="vertical-align: middle;">
                    <?php if ($row['is_active']): ?>
                      <span class="badge bg-green" title="<?= $_fup_badge_online_desc ?? 'Sedang Login'; ?>"><i class="fa fa-wifi"></i> <?= $_fup_badge_online ?? 'ONLINE'; ?></span>
                      <br><small style="color: #555;"><?= htmlspecialchars($row['address']); ?></small>
                    <?php else: ?>
                      <span class="badge bg-default" style="color: #777;"><i class="fa fa-power-off"></i> <?= $_fup_badge_offline ?? 'OFFLINE'; ?></span>
                    <?php endif; ?>
                  </td>

                  <!-- FUP Status -->
                  <td style="vertical-align: middle;">
                    <?php if ($row['fup_state'] === 'THROTTLED'): ?>
                      <span class="badge bg-red" style="font-weight: bold;" title="<?= sprintf($_fup_badge_throttled_desc ?? 'Batas kuota tembus! Kecepatan diturunkan ke %s', htmlspecialchars($row['fup_rate'])); ?>">
                        <i class="fa fa-tachometer"></i> <?= $_fup_badge_throttled ?? 'KENA FUP'; ?>
                      </span>
                      <br><small style="color: #c0392b; font-weight: bold;"><?= $_fup_limit_prefix ?? 'Limit:'; ?> <?= htmlspecialchars($row['fup_rate']); ?></small>
                    <?php elseif ($row['fup_state'] === 'WARNING'): ?>
                      <span class="badge bg-yellow" title="<?= sprintf($_fup_badge_warning_desc ?? 'Pemakaian kuota mencapai %s%% dari batas FUP', $row['fup_percent']); ?>">
                        <i class="fa fa-warning"></i> <?= sprintf($_fup_badge_warning ?? 'SIAGA (%s%%)', $row['fup_percent']); ?>
                      </span>
                    <?php elseif ($row['fup_state'] === 'NORMAL'): ?>
                      <span class="badge bg-green" title="<?= sprintf($_fup_badge_normal_desc ?? 'Kecepatan penuh normal (%s%% dari FUP)', $row['fup_percent']); ?>">
                        <i class="fa fa-check"></i> <?= $_fup_badge_normal ?? 'NORMAL'; ?>
                      </span>
                    <?php else: ?>
                      <span class="badge bg-default" style="color: #888;"><?= $_fup_badge_no_fup ?? 'Tanpa FUP'; ?></span>
                    <?php endif; ?>
                  </td>

                  <!-- Real-time Speed -->
                  <td style="vertical-align: middle; font-family: monospace; font-size: 12px;">
                    <?php if ($row['is_active'] && $row['rate_str'] !== 'Idle (0 bps)' && $row['rate_str'] !== 'Online'): ?>
                      <span style="color: #27ae60; font-weight: bold;"><?= htmlspecialchars($row['rate_str']); ?></span>
                    <?php elseif ($row['is_active']): ?>
                      <span style="color: #7f8c8d;"><?= htmlspecialchars($row['rate_str']); ?></span>
                    <?php else: ?>
                      <span style="color: #bdc3c7;">-</span>
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
                      <div style="background: #e9ecef; border-radius: 4px; height: 12px; width: 100%; overflow: hidden; margin-bottom: 3px;">
                        <div style="background: <?= $barColor; ?>; height: 100%; width: <?= $barPct; ?>%;"></div>
                      </div>
                      <small style="color: #555; font-size: 11px;">
                        <?= formatBytes2($row['total'], 1); ?> / <?= formatBytes2($row['fup_threshold'], 1); ?> 
                        (<?= $_fup_remaining ?? 'Sisa'; ?>: <?= formatBytes2($remain, 1); ?>)
                      </small>
                    <?php elseif ($row['limit_bytes'] > 0): ?>
                      <?php
                        $lPct = min(100, round(($row['total'] / $row['limit_bytes']) * 100, 1));
                        $lRemain = max(0, $row['limit_bytes'] - $row['total']);
                      ?>
                      <div style="background: #e9ecef; border-radius: 4px; height: 12px; width: 100%; overflow: hidden; margin-bottom: 3px;">
                        <div style="background: #3498db; height: 100%; width: <?= $lPct; ?>%;"></div>
                      </div>
                      <small style="color: #555; font-size: 11px;">
                        <?= formatBytes2($row['total'], 1); ?> / <?= formatBytes2($row['limit_bytes'], 1); ?>
                      </small>
                    <?php else: ?>
                      <small style="color: #888;"><?= $_unlimited ?? 'Unlimited'; ?></small>
                    <?php endif; ?>
                  </td>

                  <!-- Uptime -->
                  <td style="vertical-align: middle;">
                    <?= htmlspecialchars($row['uptime']); ?>
                  </td>

                  <!-- Quick Actions -->
                  <td style="vertical-align: middle; text-align: center;">
                    <div style="display: inline-flex; gap: 4px;">
                      <a href="./?bandwidth=action&action=reset_user&user_id=<?= urlencode($row['id']); ?>&user_name=<?= urlencode($row['name']); ?>&session=<?= $session; ?>" 
                         class="btn btn-sm bg-warning" 
                         title="<?= $_fup_btn_reset_title ?? 'Reset kuota pemakaian akun ini ke 0 MB'; ?>"
                         onclick="return confirm('<?= sprintf(addslashes($_fup_confirm_reset ?? "Reset total pemakaian akun '%s' kembali ke 0 MB?"), htmlspecialchars($row['name'], ENT_QUOTES)); ?>');">
                        <i class="fa fa-refresh"></i> <?= $_fup_btn_reset ?? 'Reset'; ?>
                      </a>

                      <?php if ($row['is_active']): ?>
                        <a href="./?bandwidth=action&action=kick_user&active_id=<?= urlencode($row['act_id']); ?>&user_name=<?= urlencode($row['name']); ?>&session=<?= $session; ?>" 
                           class="btn btn-sm bg-danger" 
                           title="<?= $_fup_btn_kick_title ?? 'Putuskan sesi aktif user ini'; ?>"
                           onclick="return confirm('<?= sprintf(addslashes($_fup_confirm_kick ?? "Kick dan putuskan sesi aktif user '%s'?"), htmlspecialchars($row['name'], ENT_QUOTES)); ?>');">
                          <i class="fa fa-power-off"></i> <?= $_fup_btn_kick ?? 'Kick'; ?>
                        </a>
                      <?php endif; ?>
                    </div>
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

<script>
$(document).ready(function() {
  // Interactive Stat Cards and Quick Filter buttons
  function applyRowTagFilter(filterVal) {
    $(".stat-quick-btn").removeClass("active bg-primary").addClass("bg-default");
    $('.stat-quick-btn[data-filter="' + filterVal + '"]').addClass("active bg-primary").removeClass("bg-default");

    if (filterVal === "all") {
      $(".voucher-row").show();
    } else {
      $(".voucher-row").each(function() {
        var tags = ($(this).attr("data-tag") || "").split(" ");
        if (tags.indexOf(filterVal) > -1) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
    }
  }

  $(".stat-filter-btn, .stat-quick-btn").click(function() {
    var f = $(this).attr("data-filter");
    applyRowTagFilter(f);
  });

  // Search Box filter
  $("#filterTable").on("input keyup paste search change", function() {
    var value = $(this).val().toLowerCase().trim();
    $(".voucher-row").filter(function() {
      if (value === "") {
        $(this).show();
      } else {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
      }
    });
  });
});
</script>
