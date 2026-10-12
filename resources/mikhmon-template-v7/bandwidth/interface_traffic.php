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
?>

<div class="row">
  <div class="col-12">
    <!-- Header Title & Back Link -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 15px; gap: 8px;">
      <div>
        <h2 style="margin: 0; font-size: 20px; font-weight: bold;">
          <i class="fa fa-exchange"></i> <?= $_total_bandwidth ?? fup_t('_total_bandwidth', 'Total Bandwidth'); ?>
        </h2>
        <small style="opacity: 0.8; font-size: 12px;">
          <?= $_interface_bandwidth ?? fup_t('_interface_bandwidth', 'Bandwidth Interface'); ?> &bull; MikroTik RouterOS
        </small>
      </div>
      <div>
        <a href="./?bandwidth=top-voucher&session=<?= $session; ?>" class="btn bg-default">
          <i class="fa fa-bar-chart"></i> <?= $_top_tier_voucher ?? fup_t('_top_tier_voucher', 'Top Tier Voucher'); ?>
        </a>
        <a href="./?bandwidth=fup-settings&session=<?= $session; ?>" class="btn bg-primary">
          <i class="fa fa-sliders"></i> <?= $_fup_settings ?? fup_t('_fup_settings', 'Pengaturan FUP'); ?>
        </a>
      </div>
    </div>

    <!-- Summary Stat Cards -->
    <div class="row">
      <!-- Total Traffic All Interfaces -->
      <div class="col-3 col-box-6 mr-b-10">
        <div class="card box-bordered" style="padding: 12px; border-radius: 6px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <span style="font-size: 11px; text-transform: uppercase; opacity: 0.8; font-weight: bold;"><?= $_iface_card_total_traffic ?? fup_t('_iface_card_total_traffic', 'Total Trafik'); ?></span>
              <h3 style="margin: 4px 0 0 0; font-size: 18px; font-weight: bold;"><?= formatBytes2($totalBytesAll, 2); ?></h3>
              <small style="opacity: 0.75; font-size: 11px;"><?= round($totalBytesAll / 1073741824, 2); ?> GB (Rx + Tx)</small>
            </div>
            <div style="font-size: 28px; opacity: 0.35;">
              <i class="fa fa-area-chart"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Download (Rx) -->
      <div class="col-3 col-box-6 mr-b-10">
        <div class="card box-bordered" style="padding: 12px; border-radius: 6px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <span style="font-size: 11px; text-transform: uppercase; opacity: 0.8; font-weight: bold;"><?= $_iface_card_total_download ?? fup_t('_iface_card_total_download', 'Total Download (Rx)'); ?></span>
              <h3 style="margin: 4px 0 0 0; font-size: 18px; font-weight: bold; color: #2ecc71;"><?= formatBytes2($totalRxAll, 2); ?></h3>
              <small style="opacity: 0.75; font-size: 11px;"><?= round($totalRxAll / 1073741824, 2); ?> GB</small>
            </div>
            <div style="font-size: 28px; opacity: 0.35; color: #2ecc71;">
              <i class="fa fa-arrow-circle-down"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Upload (Tx) -->
      <div class="col-3 col-box-6 mr-b-10">
        <div class="card box-bordered" style="padding: 12px; border-radius: 6px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <span style="font-size: 11px; text-transform: uppercase; opacity: 0.8; font-weight: bold;"><?= $_iface_card_total_upload ?? fup_t('_iface_card_total_upload', 'Total Upload (Tx)'); ?></span>
              <h3 style="margin: 4px 0 0 0; font-size: 18px; font-weight: bold; color: #3498db;"><?= formatBytes2($totalTxAll, 2); ?></h3>
              <small style="opacity: 0.75; font-size: 11px;"><?= round($totalTxAll / 1073741824, 2); ?> GB</small>
            </div>
            <div style="font-size: 28px; opacity: 0.35; color: #3498db;">
              <i class="fa fa-arrow-circle-up"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Active Interfaces -->
      <div class="col-3 col-box-6 mr-b-10">
        <div class="card box-bordered" style="padding: 12px; border-radius: 6px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <span style="font-size: 11px; text-transform: uppercase; opacity: 0.8; font-weight: bold;"><?= $_iface_card_active_interfaces ?? fup_t('_iface_card_active_interfaces', 'Interface Aktif'); ?></span>
              <h3 style="margin: 4px 0 0 0; font-size: 18px; font-weight: bold;"><?= $activeCount; ?> / <?= count($ifaceRows); ?></h3>
              <small style="opacity: 0.75; font-size: 11px;">Running / Total</small>
            </div>
            <div style="font-size: 28px; opacity: 0.35;">
              <i class="fa fa-sitemap"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Table Card -->
    <div class="card box-bordered mr-t-10">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3><i class="fa fa-list"></i> <?= $_interface_bandwidth ?? fup_t('_interface_bandwidth', 'Trafik Bandwidth Setiap Interface'); ?></h3>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <div class="btn-group" style="display: inline-flex;">
            <button type="button" class="btn btn-sm bg-default iface-quick-btn active" data-filter="all"><?= $_fup_stat_all ?? fup_t('_fup_stat_all', 'Semua'); ?> (<?= count($ifaceRows); ?>)</button>
            <button type="button" class="btn btn-sm bg-default iface-quick-btn" data-filter="running"><?= $_active ?? fup_t('_active', 'Running'); ?> (<?= $activeCount; ?>)</button>
            <button type="button" class="btn btn-sm bg-default iface-quick-btn" data-filter="inactive"><?= $_deactive ?? fup_t('_deactive', 'Down / Inaktif'); ?> (<?= count($ifaceRows) - $activeCount; ?>)</button>
          </div>
          <input id="filterIfaceTable" type="text" class="form-control" style="max-width: 240px; padding: 5px 10px; margin: 0;" placeholder="<?= $_iface_search_placeholder ?? fup_t('_iface_search_placeholder', 'Cari interface...'); ?>">
        </div>
      </div>

      <div class="card-body">
        <style>
          #ifaceTableScrollContainer::-webkit-scrollbar:horizontal {
            display: none !important;
            height: 0px !important;
          }
          #ifaceTopScrollWrapper::-webkit-scrollbar {
            height: 12px;
          }
          #ifaceTopScrollWrapper::-webkit-scrollbar-thumb {
            background: rgba(127, 127, 127, 0.45);
            border-radius: 6px;
          }
          #ifaceTopScrollWrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(127, 127, 127, 0.8);
          }
        </style>

        <!-- Top Horizontal Scroll Control Slider -->
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
          <button type="button" class="btn btn-sm bg-default" id="btnIfaceScrollLeft" title="<?= $_iface_scroll_left ?? fup_t('_iface_scroll_left', 'Geser Kiri'); ?>" style="padding: 4px 10px; cursor: pointer;">
            <i class="fa fa-chevron-left"></i>
          </button>
          <div id="ifaceTopScrollWrapper" style="flex: 1; overflow-x: auto; overflow-y: hidden; height: 16px; border: 1px solid rgba(127,127,127,0.3); border-radius: 4px; background: rgba(0,0,0,0.06);">
            <div id="ifaceTopScrollDummy" style="height: 1px; width: 100%;"></div>
          </div>
          <button type="button" class="btn btn-sm bg-default" id="btnIfaceScrollRight" title="<?= $_iface_scroll_right ?? fup_t('_iface_scroll_right', 'Geser Kanan'); ?>" style="padding: 4px 10px; cursor: pointer;">
            <i class="fa fa-chevron-right"></i>
          </button>
        </div>

        <div id="ifaceTableScrollContainer" class="overflow mr-t-5 box-bordered" style="max-height: 72vh; overflow-x: auto; overflow-y: auto;">
          <table id="ifaceDataTable" class="table table-bordered table-hover text-nowrap">
            <thead>
              <tr>
                <th style="width: 40px; text-align: center;">#</th>
                <th><?= $_iface_th_name ?? fup_t('_iface_th_name', 'Interface'); ?></th>
                <th><?= $_iface_th_type ?? fup_t('_iface_th_type', 'Tipe'); ?></th>
                <th><?= $_iface_th_status ?? fup_t('_iface_th_status', 'Status'); ?></th>
                <th><?= $_iface_th_download ?? fup_t('_iface_th_download', 'Download (Rx)'); ?></th>
                <th><?= $_iface_th_upload ?? fup_t('_iface_th_upload', 'Upload (Tx)'); ?></th>
                <th><?= $_iface_th_total ?? fup_t('_iface_th_total', 'Total Trafik'); ?></th>
                <th style="min-width: 140px;"><?= $_iface_th_share ?? fup_t('_iface_th_share', 'Part (%)'); ?></th>
                <th><?= $_iface_th_packets ?? fup_t('_iface_th_packets', 'Paket (Rx / Tx)'); ?></th>
                <th style="text-align: center; width: 90px;"><?= $_iface_th_action ?? fup_t('_iface_th_action', 'Monitor'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ifaceRows)): ?>
                <tr>
                  <td colspan="10" style="text-align: center; padding: 20px; opacity: 0.7;">
                    <i><?= $_fup_no_data ?? fup_t('_fup_no_data', 'Tidak ada data interface'); ?></i>
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
                    <!-- No -->
                    <td style="text-align: center; vertical-align: middle;">
                      <?= $no; ?>
                    </td>

                    <!-- Interface Name -->
                    <td style="vertical-align: middle;">
                      <strong><?= htmlspecialchars($row['name']); ?></strong>
                      <?php if (!empty($row['comment'])): ?>
                        <br><small style="opacity: 0.75;"><?= htmlspecialchars($row['comment']); ?></small>
                      <?php endif; ?>
                      <?php if (!empty($row['mac'])): ?>
                        <br><small style="font-family: monospace; opacity: 0.65;"><?= htmlspecialchars($row['mac']); ?></small>
                      <?php endif; ?>
                    </td>

                    <!-- Type -->
                    <td style="vertical-align: middle;">
                      <span class="badge bg-default" style="font-size: 11px;"><?= htmlspecialchars($row['type']); ?></span>
                    </td>

                    <!-- Status -->
                    <td style="vertical-align: middle;">
                      <?php if ($row['disabled']): ?>
                        <span class="badge bg-red"><i class="fa fa-ban"></i> Disabled</span>
                      <?php elseif ($row['running']): ?>
                        <span class="badge bg-green"><i class="fa fa-check"></i> Running</span>
                      <?php else: ?>
                        <span class="badge bg-yellow"><i class="fa fa-pause"></i> Inactive</span>
                      <?php endif; ?>
                    </td>

                    <!-- Download Rx in GB -->
                    <td style="vertical-align: middle;">
                      <strong style="color: #27ae60;"><?= $rxGb; ?> GB</strong>
                      <br><small style="opacity: 0.75;"><?= formatBytes2($row['rx_bytes'], 2); ?></small>
                    </td>

                    <!-- Upload Tx in GB -->
                    <td style="vertical-align: middle;">
                      <strong style="color: #2980b9;"><?= $txGb; ?> GB</strong>
                      <br><small style="opacity: 0.75;"><?= formatBytes2($row['tx_bytes'], 2); ?></small>
                    </td>

                    <!-- Total in GB -->
                    <td style="vertical-align: middle;">
                      <strong style="font-size: 13px;"><?= $totGb; ?> GB</strong>
                      <br><small style="opacity: 0.75;"><?= formatBytes2($row['total_bytes'], 2); ?></small>
                    </td>

                    <!-- Traffic Share Progress Bar -->
                    <td style="vertical-align: middle;">
                      <div style="background: rgba(127, 127, 127, 0.25); border-radius: 4px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 3px;">
                        <div style="background: #3498db; height: 100%; width: <?= min(100, $sharePct); ?>%;"></div>
                      </div>
                      <small style="opacity: 0.8; font-size: 11px; font-weight: bold;"><?= $sharePct; ?>%</small>
                    </td>

                    <!-- Packets -->
                    <td style="vertical-align: middle; font-family: monospace; font-size: 11px;">
                      <?= number_format($row['rx_packet']); ?> / <?= number_format($row['tx_packet']); ?>
                    </td>

                    <!-- Monitor Action -->
                    <td style="vertical-align: middle; text-align: center;">
                      <a href="./?interface=traffic-monitor&session=<?= $session; ?>" class="btn btn-sm bg-default" title="Traffic Monitor">
                        <i class="fa fa-area-chart"></i>
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
  // Synchronized Top Horizontal Scrollbar
  function initIfaceTopScrollSync() {
    var tableContainer = document.getElementById('ifaceTableScrollContainer');
    var topWrapper = document.getElementById('ifaceTopScrollWrapper');
    var topDummy = document.getElementById('ifaceTopScrollDummy');
    var dataTable = document.getElementById('ifaceDataTable');

    if (!tableContainer || !topWrapper || !topDummy) return;

    function updateScrollWidth() {
      var w = dataTable ? dataTable.scrollWidth : tableContainer.scrollWidth;
      topDummy.style.width = w + 'px';
    }

    updateScrollWidth();
    $(window).on('resize', updateScrollWidth);

    var isSyncingTop = false;
    var isSyncingTable = false;

    topWrapper.addEventListener('scroll', function() {
      if (!isSyncingTable) {
        isSyncingTop = true;
        tableContainer.scrollLeft = topWrapper.scrollLeft;
      }
      isSyncingTable = false;
    });

    tableContainer.addEventListener('scroll', function() {
      if (!isSyncingTop) {
        isSyncingTable = true;
        topWrapper.scrollLeft = tableContainer.scrollLeft;
      }
      isSyncingTop = false;
    });

    $('#btnIfaceScrollLeft').on('click', function() {
      tableContainer.scrollBy({ left: -260, behavior: 'smooth' });
    });
    $('#btnIfaceScrollRight').on('click', function() {
      tableContainer.scrollBy({ left: 260, behavior: 'smooth' });
    });
  }

  initIfaceTopScrollSync();

  // Quick Filter Buttons (All, Running, Inactive)
  $(".iface-quick-btn").click(function() {
    var filterVal = $(this).attr("data-filter");
    $(".iface-quick-btn").removeClass("active bg-primary").addClass("bg-default");
    $(this).addClass("active bg-primary").removeClass("bg-default");

    if (filterVal === "all") {
      $(".iface-row").show();
    } else {
      $(".iface-row").each(function() {
        var tag = $(this).attr("data-tag") || "";
        if (tag === filterVal) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
    }
  });

  // Search Filter
  $("#filterIfaceTable").on("input keyup paste search change", function() {
    var val = $(this).val().toLowerCase().trim();
    $(".iface-row").filter(function() {
      if (val === "") {
        $(this).show();
      } else {
        $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
      }
    });
  });
});
</script>
