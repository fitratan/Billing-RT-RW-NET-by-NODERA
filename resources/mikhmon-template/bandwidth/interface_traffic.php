<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * Total Bandwidth & Per-Interface Traffic Usage (Download / Upload in GB)
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

// Fetch all interfaces from MikroTik RouterOS
$getInterfaces = $API->comm("/interface/print");
$getInterfaces = is_array($getInterfaces) ? $getInterfaces : array();

$ifaceRows = array();
$totalRxAll = 0;
$totalTxAll = 0;
$totalBytesAll = 0;
$activeCount = 0;

foreach ($getInterfaces as $iface) {
    $name = $iface['name'] ?? '';
    if (empty($name)) continue;

    $rx = (float)($iface['rx-byte'] ?? 0);
    $tx = (float)($iface['tx-byte'] ?? 0);
    $total = $rx + $tx;

    $totalRxAll += $rx;
    $totalTxAll += $tx;
    $totalBytesAll += $total;

    $isRunning = (($iface['running'] ?? 'false') === 'true');
    $isDisabled = (($iface['disabled'] ?? 'false') === 'true');
    if ($isRunning && !$isDisabled) {
        $activeCount++;
    }

    $rxPacket = (int)($iface['rx-packet'] ?? 0);
    $txPacket = (int)($iface['tx-packet'] ?? 0);

    $ifaceRows[] = array(
        'id' => $iface['.id'] ?? '',
        'name' => $name,
        'type' => $iface['type'] ?? 'ether',
        'running' => $isRunning,
        'disabled' => $isDisabled,
        'comment' => $iface['comment'] ?? '',
        'mac' => $iface['mac-address'] ?? '',
        'rx_bytes' => $rx,
        'tx_bytes' => $tx,
        'total_bytes' => $total,
        'rx_packet' => $rxPacket,
        'tx_packet' => $txPacket,
    );
}

// Sort interfaces descending by total traffic
usort($ifaceRows, function($a, $b) {
    if ($a['total_bytes'] == $b['total_bytes']) return 0;
    return ($a['total_bytes'] < $b['total_bytes']) ? 1 : -1;
});

$totAllStr = formatBytes2($totalBytesAll, 2);
$pTot = explode(' ', $totAllStr, 2);
$trafficNum = $pTot[0] ?? $totAllStr;
$trafficUnit = $pTot[1] ?? '';

$rxAllStr = formatBytes2($totalRxAll, 2);
$pRx = explode(' ', $rxAllStr, 2);
$rxNum = $pRx[0] ?? $rxAllStr;
$rxUnit = $pRx[1] ?? '';

$txAllStr = formatBytes2($totalTxAll, 2);
$pTx = explode(' ', $txAllStr, 2);
$txNum = $pTx[0] ?? $txAllStr;
$txUnit = $pTx[1] ?? '';
?>

<div class="row">
  <div class="col-12">
    <!-- Summary Stat Boxes in Native Mikhmon Style -->
    <div class="row">
      <div class="col-3 col-box-6">
        <div class="box bg-purple bmh-75">
          <h1><?= $trafficNum; ?> <span style="font-size: 14px;"><?= $trafficUnit; ?></span></h1>
          <div><i class="fa fa-area-chart"></i> <?= fup_t('_iface_card_total_traffic', 'Total Trafik'); ?></div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-green bmh-75">
          <h1><?= $rxNum; ?> <span style="font-size: 14px;"><?= $rxUnit; ?></span></h1>
          <div><i class="fa fa-arrow-circle-down"></i> <?= fup_t('_iface_card_total_download', 'Total Download (Rx)'); ?></div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-blue bmh-75">
          <h1><?= $txNum; ?> <span style="font-size: 14px;"><?= $txUnit; ?></span></h1>
          <div><i class="fa fa-arrow-circle-up"></i> <?= fup_t('_iface_card_total_upload', 'Total Upload (Tx)'); ?></div>
        </div>
      </div>

      <div class="col-3 col-box-6">
        <div class="box bg-yellow bmh-75">
          <h1><?= $activeCount; ?> <span style="font-size: 14px;">/ <?= count($ifaceRows); ?></span></h1>
          <div><i class="fa fa-sitemap"></i> <?= fup_t('_iface_card_active_interfaces', 'Interface Aktif'); ?></div>
        </div>
      </div>
    </div>

    <!-- Main Card Container -->
    <div class="card box-bordered mr-t-10">
      <div class="card-header">
        <h3><i class="fa fa-exchange"></i> <?= fup_t('_total_bandwidth', 'Total Bandwidth'); ?>
          <span style="font-size: 14px;">
            &nbsp; | &nbsp; <a href="./?bandwidth=top-voucher&session=<?= $session; ?>"><i class="fa fa-bar-chart"></i> <?= fup_t('_top_tier_voucher', 'Top Tier Voucher'); ?></a>
            &nbsp; | &nbsp; <a href="./?bandwidth=fup-settings&session=<?= $session; ?>"><i class="fa fa-sliders"></i> <?= fup_t('_fup_settings', 'Pengaturan FUP'); ?></a>
            &nbsp; | &nbsp; <a href="./?bandwidth=interface-traffic&session=<?= $session; ?>"><i class="fa fa-refresh"></i> <?= fup_t('_reload', 'Muat Ulang'); ?></a>
          </span>
        </h3>
      </div>

      <div class="card-body">
        <!-- Native Search & Filter -->
        <div class="row">
          <div class="col-6 col-box-12 pd-t-5 pd-b-5">
            <div class="input-group">
              <div class="input-group-6 col-box-6">
                <input id="filterIfaceTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= fup_t('_search', 'Cari'); ?>...">
              </div>
              <div class="input-group-6 col-box-6">
                <select id="filterIfaceStatus" style="padding:5px;" class="group-item group-item-r">
                  <option value="all"><?= fup_t('_fup_stat_all', 'Semua'); ?> (<?= count($ifaceRows); ?>)</option>
                  <option value="running"><?= fup_t('_running', 'Running'); ?> (<?= $activeCount; ?>)</option>
                  <option value="inactive"><?= fup_t('_deactive', 'Down / Inaktif'); ?> (<?= count($ifaceRows) - $activeCount; ?>)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Mikhmon Native Horizontal & Vertical Scrollable Table -->
        <div class="overflow box-bordered mr-t-10" style="max-height: 75vh;">
          <table id="dataTable" class="table table-bordered table-hover text-nowrap">
            <thead>
              <tr>
                <th style="width: 40px; text-align: center;">#</th>
                <th><?= fup_t('_iface_th_name', 'Interface'); ?></th>
                <th><?= fup_t('_iface_th_type', 'Tipe'); ?></th>
                <th><?= fup_t('_iface_th_status', 'Status'); ?></th>
                <th><?= fup_t('_iface_th_download', 'Download (Rx)'); ?></th>
                <th><?= fup_t('_iface_th_upload', 'Upload (Tx)'); ?></th>
                <th><?= fup_t('_iface_th_total', 'Total Trafik'); ?></th>
                <th style="min-width: 130px;"><?= fup_t('_iface_th_share', 'Porsi (%)'); ?></th>
                <th><?= fup_t('_iface_th_packets', 'Paket (Rx / Tx)'); ?></th>
                <th style="text-align: center; width: 70px;"><?= fup_t('_iface_th_action', 'Aksi'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ifaceRows)): ?>
                <tr>
                  <td colspan="10" style="text-align: center; padding: 20px;">
                    <i><?= fup_t('_fup_no_data', 'Tidak ada data interface'); ?></i>
                  </td>
                </tr>
              <?php else: ?>
                <?php $no = 1; ?>
                <?php foreach ($ifaceRows as $row): ?>
                  <?php
                    $tag = ($row['running'] && !$row['disabled']) ? 'running' : 'inactive';
                    $sharePct = ($totalBytesAll > 0) ? round(($row['total_bytes'] / $totalBytesAll) * 100, 1) : 0;
                    $rxGb = round($row['rx_bytes'] / 1073741824, 2);
                    $txGb = round($row['tx_bytes'] / 1073741824, 2);
                    $totGb = round($row['total_bytes'] / 1073741824, 2);
                  ?>
                  <tr class="iface-row" data-tag="<?= $tag; ?>">
                    <td style="text-align: center; vertical-align: middle;"><?= $no; ?></td>
                    <td style="vertical-align: middle;">
                      <strong><?= htmlspecialchars($row['name']); ?></strong>
                      <?php if (!empty($row['comment'])): ?>
                        <br><small><?= htmlspecialchars($row['comment']); ?></small>
                      <?php endif; ?>
                      <?php if (!empty($row['mac'])): ?>
                        <br><small style="font-family: monospace;"><?= htmlspecialchars($row['mac']); ?></small>
                      <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      <span class="badge bg-secondary"><?= htmlspecialchars($row['type']); ?></span>
                    </td>
                    <td style="vertical-align: middle;">
                      <?php if ($row['disabled']): ?>
                        <span class="badge bg-danger"><i class="fa fa-ban"></i> <?= fup_t('_disabled', 'Disabled'); ?></span>
                      <?php elseif ($row['running']): ?>
                        <span class="badge bg-green"><i class="fa fa-check"></i> <?= fup_t('_running', 'Running'); ?></span>
                      <?php else: ?>
                        <span class="badge bg-warning"><i class="fa fa-pause"></i> <?= fup_t('_deactive', 'Inactive'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      <strong class="text-green"><?= $rxGb; ?> GB</strong>
                      <br><small><?= formatBytes2($row['rx_bytes'], 2); ?></small>
                    </td>
                    <td style="vertical-align: middle;">
                      <strong class="text-blue"><?= $txGb; ?> GB</strong>
                      <br><small><?= formatBytes2($row['tx_bytes'], 2); ?></small>
                    </td>
                    <td style="vertical-align: middle;">
                      <strong><?= $totGb; ?> GB</strong>
                      <br><small><?= formatBytes2($row['total_bytes'], 2); ?></small>
                    </td>
                    <td style="vertical-align: middle;">
                      <div style="background: rgba(127, 127, 127, 0.25); border-radius: 4px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 3px;">
                        <div style="background: #3498db; height: 100%; width: <?= min(100, $sharePct); ?>%;"></div>
                      </div>
                      <small style="font-weight: bold;"><?= $sharePct; ?>%</small>
                    </td>
                    <td style="vertical-align: middle; font-family: monospace;">
                      <?= number_format($row['rx_packet']); ?> / <?= number_format($row['tx_packet']); ?>
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                      <a href="./?interface=traffic-monitor&session=<?= $session; ?>" title="Traffic Monitor">
                        <i class="fa fa-area-chart text-primary pointer"></i>
                      </a>
                    </td>
                  </tr>
                  <?php $no++; ?>
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
  function applyIfaceFilter() {
    var searchVal = ($("#filterIfaceTable").val() || "").toLowerCase().trim();
    var statusVal = $("#filterIfaceStatus").val() || "all";

    $(".iface-row").each(function() {
      var row = $(this);
      var tag = row.attr("data-tag") || "";
      var text = row.text().toLowerCase();

      var matchStatus = (statusVal === "all") || (tag === statusVal);
      var matchSearch = (searchVal === "") || (text.indexOf(searchVal) > -1);

      if (matchStatus && matchSearch) {
        row.show();
      } else {
        row.hide();
      }
    });
  }

  $("#filterIfaceTable").on("input keyup paste search change", applyIfaceFilter);
  $("#filterIfaceStatus").on("change", applyIfaceFilter);
});
</script>
