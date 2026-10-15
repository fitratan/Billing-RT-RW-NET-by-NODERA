<?php
// Load language
$langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
if (empty($langid)) {
    if (file_exists(__DIR__ . '/../include/lang.php')) {
        include __DIR__ . '/../include/lang.php';
    } elseif (file_exists(__DIR__ . '/lang.php')) {
        include __DIR__ . '/lang.php';
    }
}
if (empty($langid)) {
    $langid = 'id';
}
if (file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    include __DIR__ . '/../lang/' . $langid . '.php';
} elseif (file_exists(__DIR__ . '/lang/' . $langid . '.php')) {
    include __DIR__ . '/lang/' . $langid . '.php';
}

error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:./admin.php?id=login");
  exit;
}

if (empty($session)) {
  $session = $_GET['session'] ?? ($_POST['session'] ?? ($_SESSION['session'] ?? ($_SESSION['mikhmon'] ?? '')));
}
$session = trim((string)$session);

$thermal_cfg_file = "./include/thermal_config.php";
$thermal_config = array();
if (file_exists($thermal_cfg_file)) {
  @include($thermal_cfg_file);
}
if (!is_array($thermal_config)) {
  $thermal_config = array();
}

$cfgSession = $thermal_config[$session] ?? array();
$paperSize = $cfgSession['paper_size'] ?? '58';
$printMethod = $cfgSession['print_method'] ?? 'bluetooth';
$templateStyle = $cfgSession['template_style'] ?? 'classic';
$footerNote = $cfgSession['footer_note'] ?? 'Terima kasih atas pembelian Anda';
$autoCut = ($cfgSession['auto_cut'] ?? 'yes') === 'yes';

$notifyMsg = '';

if (isset($_POST['save_thermal_cfg'])) {
  $paperSize = in_array($_POST['paper_size'] ?? '', ['58', '80']) ? $_POST['paper_size'] : '58';
  $printMethod = in_array($_POST['print_method'] ?? '', ['bluetooth', 'rawbt', 'browser']) ? $_POST['print_method'] : 'bluetooth';
  $templateStyle = in_array($_POST['template_style'] ?? '', ['classic', 'compact', 'modern', 'detailed']) ? $_POST['template_style'] : 'classic';
  $footerNote = trim($_POST['footer_note'] ?? '');
  $autoCut = isset($_POST['auto_cut']);

  $thermal_config[$session] = array(
    'paper_size' => $paperSize,
    'print_method' => $printMethod,
    'template_style' => $templateStyle,
    'footer_note' => $footerNote,
    'auto_cut' => $autoCut ? 'yes' : 'no',
    'updated_at' => date('Y-m-d H:i:s'),
  );

  $cfgContent = '<' . '?php' . "\n" .
                'if(substr($_SERVER["REQUEST_URI"], -18) == "thermal_config.php"){header("Location:./");};' . "\n" .
                '$thermal_config = ' . var_export($thermal_config, true) . ";\n";
  @file_put_contents($thermal_cfg_file, $cfgContent);

  $notifyMsg = '<div class="box bg-success" style="margin-bottom: 12px;"><i class="fa fa-check-circle"></i> ' . ($_settings_saved ?? 'Pengaturan printer thermal berhasil disimpan!') . '</div>';
}
?>

<div class="row">
  <div class="col-12">
    <?= $notifyMsg; ?>
  </div>

  <!-- KOLOM 1: KONEKSI & SCANNER BLUETOOTH -->
  <div class="col-6 col-box-12">
    <div class="card box-bordered">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;"><i class="fa fa-bluetooth-b text-primary"></i> <?= $_thermal_printer ?? 'Printer Thermal Bluetooth'; ?></h3>
        <span id="printerStatusBadge" class="badge" style="background: #64748b; color: #ffffff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
          <i class="fa fa-circle-o"></i> <?= $_printer_not_connected ?? 'Belum Terhubung'; ?>
        </span>
      </div>
      <div class="card-body">
        <div id="btAlertArea" style="display: none; margin-bottom: 12px;"></div>

        <table class="table">
          <tr>
            <td class="align-middle" style="width: 35%;"><?= $_printer_device ?? 'Nama Printer'; ?></td>
            <td class="align-middle">
              <strong id="printerDeviceName" class="text-secondary">-</strong>
            </td>
          </tr>
          <tr>
            <td class="align-middle"><?= $_connection_status ?? 'Status Koneksi'; ?></td>
            <td class="align-middle">
              <span id="printerConnText" style="font-weight: bold; color: #64748b;"><?= $_status_disconnected ?? 'Belum Tersambung'; ?></span>
            </td>
          </tr>
          <tr>
            <td class="align-middle"><?= $_test_print_thermal ?? 'Uji Coba Cetak'; ?></td>
            <td class="align-middle">
              <small class="text-muted" style="display: block; margin-bottom: 6px;">
                <?= $_thermal_receipt_notice ?? 'Mencetak 1 baris konfirmasi: <code>sukses terhubung</code>'; ?>
              </small>
              <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <button type="button" class="btn bg-success" id="btnTestPrint" onclick="handleTestPrint()" disabled>
                  <i class="fa fa-print"></i> <?= $_test_print_thermal ?? 'Cetak Struk Uji Coba'; ?>
                </button>
                <button type="button" class="btn bg-secondary" id="btnTestPrintRawbt" onclick="testPrintViaRawBt()" title="<?= $_test_rawbt ?? 'Cetak via RawBT Android'; ?>">
                  <i class="fa fa-android"></i> <?= $_test_rawbt ?? 'Tes RawBT'; ?>
                </button>
              </div>
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn bg-primary" id="btnConnect" onclick="openScanModal()">
                  <i class="fa fa-search"></i> <?= $_scan_bluetooth_printer ?? 'Pindai Printer Bluetooth'; ?>
                </button>
                <button type="button" class="btn bg-danger" id="btnDisconnect" onclick="handleDisconnectPrinter()" style="display: none;">
                  <i class="fa fa-times"></i> <?= $_disconnect_printer ?? 'Putuskan'; ?>
                </button>
              </div>
            </td>
          </tr>
        </table>

        <div style="margin-top: 15px; padding: 10px 12px; background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6; border-radius: 4px; font-size: 11.5px; line-height: 1.5;">
          <strong><i class="fa fa-info-circle text-primary"></i> <?= $_thermal_guide_title ?? 'Panduan Koneksi:'; ?></strong><br>
          1. <?= $_thermal_guide_1 ?? 'Nyalakan printer thermal Bluetooth Anda (58mm / 80mm).'; ?><br>
          2. <?= $_thermal_guide_2 ?? 'Klik tombol <strong>"Pindai Printer Bluetooth"</strong> untuk membuka dialog panduan &amp; pemilihan printer.'; ?><br>
          3. <?= $_thermal_guide_3 ?? 'Setelah status berubah <span style="color: #16a34a; font-weight: bold;">Terhubung</span>, klik <strong>"Cetak Struk Uji Coba"</strong> untuk memastikan teks tercetak rapi.'; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- KOLOM 2: PENGATURAN FORMAT KERTAS & STRUK -->
  <div class="col-6 col-box-12">
    <div class="card box-bordered">
      <div class="card-header">
        <h3 style="margin: 0;"><i class="fa fa-sliders"></i> <?= $_thermal_settings ?? 'Pengaturan Format Kertas & Struk'; ?></h3>
      </div>
      <div class="card-body">
        <form action="" method="post">
          <input type="hidden" name="session" value="<?= htmlspecialchars($session); ?>">
          <table class="table">
            <tr>
              <td class="align-middle" style="width: 38%;"><?= $_paper_size ?? 'Ukuran Kertas Thermal'; ?></td>
              <td>
                <select class="form-control" name="paper_size" id="selectPaperSize" onchange="syncPaperSizeLocal(this.value)">
                  <option value="58" <?= ($paperSize === '58') ? 'selected' : ''; ?>><?= $_paper_width_58 ?? '58 mm (Lebar standar 32 Karakter)'; ?></option>
                  <option value="80" <?= ($paperSize === '80') ? 'selected' : ''; ?>><?= $_paper_width_80 ?? '80 mm (Lebar besar 48 Karakter)'; ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_print_method ?? 'Metode Pencetakan'; ?></td>
              <td>
                <select class="form-control" name="print_method" id="selectPrintMethod" onchange="syncPrintMethodLocal(this.value)">
                  <option value="bluetooth" <?= ($printMethod === 'bluetooth') ? 'selected' : ''; ?>><?= $_print_method_bt_desc ?? 'Direct Web Bluetooth (Rekomendasi PC / BLE)'; ?></option>
                  <option value="rawbt" <?= ($printMethod === 'rawbt') ? 'selected' : ''; ?>><?= $_print_method_rawbt_desc ?? 'RawBT Android App (Bluetooth Classic & USB)'; ?></option>
                  <option value="browser" <?= ($printMethod === 'browser') ? 'selected' : ''; ?>><?= $_print_method_browser_desc ?? 'Browser System Print (window.print)'; ?></option>
                </select>
                <small class="text-muted" style="display: block; margin-top: 4px; font-size: 11px;">
                  <?= $_print_method_rawbt_hint ?? 'Gunakan RawBT Android App jika printer Bluetooth Anda tipe Classic SPP (bukan BLE) atau kabel USB OTG.'; ?>
                </small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_thermal_template_style ?? 'Model Template Voucher'; ?></td>
              <td>
                <select class="form-control" name="template_style" id="selectTemplateStyle" onchange="syncTemplateStyleLocal(this.value)">
                  <option value="classic" <?= ($templateStyle === 'classic') ? 'selected' : ''; ?>><?= $_thermal_style_classic ?? 'Standar (Klasik)'; ?></option>
                  <option value="compact" <?= ($templateStyle === 'compact') ? 'selected' : ''; ?>><?= $_thermal_style_compact ?? 'Minimalis (Hemat Kertas)'; ?></option>
                  <option value="modern" <?= ($templateStyle === 'modern') ? 'selected' : ''; ?>><?= $_thermal_style_modern ?? 'Modern Box (Bingkai Elegan)'; ?></option>
                  <option value="detailed" <?= ($templateStyle === 'detailed') ? 'selected' : ''; ?>><?= $_thermal_style_detailed ?? 'Lengkap (Panduan Login)'; ?></option>
                </select>
                <small class="text-muted" style="display: block; margin-top: 4px; font-size: 11px;">
                  Pilih gaya format voucher thermal untuk cetak struk admin &amp; warung voucher.
                </small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_footer_note ?? 'Catatan Kaki Struk'; ?></td>
              <td>
                <input type="text" class="form-control" name="footer_note" id="inputFooterNote" value="<?= htmlspecialchars($footerNote); ?>" placeholder="<?= $_footer_note ?? 'Terima kasih atas pembelian Anda'; ?>" oninput="syncFooterLocal(this.value)">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_auto_cut_paper ?? 'Auto Cut Kertas'; ?></td>
              <td>
                <label style="font-weight: normal; cursor: pointer;">
                  <input type="checkbox" name="auto_cut" value="1" <?= $autoCut ? 'checked' : ''; ?> onchange="syncAutoCutLocal(this.checked)">
                  <?= $_enable_auto_cut ?? 'Kirim sinyal potong kertas otomatis jika printer mendukung'; ?>
                </label>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" name="save_thermal_cfg" class="btn bg-primary">
                  <i class="fa fa-save"></i> <?= $_save ?? 'Simpan Pengaturan'; ?>
                </button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- BARIS 2: PRATINJAU LANGSUNG MODEL VOUCHER THERMAL -->
<div class="row" style="margin-top: 15px;">
  <div class="col-12">
    <div class="card box-bordered">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;"><i class="fa fa-eye"></i> <?= $_preview ?? 'Pratinjau Format Voucher Thermal'; ?>: <b id="previewStyleName" class="text-primary">-</b></h3>
        <span class="badge" style="background: #0284c7; color: #fff; font-size: 11px; padding: 4px 8px; border-radius: 4px;">
          58mm / 80mm ESC/POS
        </span>
      </div>
      <div class="card-body" style="background: #f8fafc; padding: 20px; display: flex; flex-direction: column; align-items: center;">
        <div style="font-size: 11px; color: #64748b; margin-bottom: 12px; text-align: center;">
          <?= $_thermal_preview_hint ?? 'Pratinjau otomatis menyesuaikan pilihan model template di atas.'; ?>
        </div>
        <div id="receiptPreviewBox" style="background: #ffffff; color: #111827; width: 100%; max-width: 280px; padding: 14px 12px; border: 1px dashed #94a3b8; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); font-family: 'Courier New', Courier, monospace; font-size: 11.5px; line-height: 1.35; box-sizing: border-box;">
          <!-- Pratinjau dihasilkan via JavaScript updatePreviewReceipt() -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: SCAN PRINTER THERMAL & PANDUAN IZIN ANDROID -->
<div id="dialogScanBtModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
  <div class="card" style="width: 100%; max-width: 440px; margin: 0 auto; box-shadow: 0 12px 35px rgba(0,0,0,0.45); border-radius: 6px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px;">
      <h3 style="margin: 0; font-size: 14px; font-weight: bold;">
        <i class="fa fa-bluetooth-b text-primary"></i> <?= $_connect_thermal_printer ?? 'Hubungkan Printer Thermal'; ?>
      </h3>
      <a href="javascript:void(0)" onclick="closeScanModal()" class="text-secondary" style="font-size: 22px; text-decoration: none; line-height: 1; padding: 0 4px;" aria-label="<?= $_close ?? 'Tutup'; ?>">&times;</a>
    </div>
    
    <div class="card-body" style="padding: 15px; overflow-y: auto;">
      <!-- STEP CHECKLIST IZIN ANDROID -->
      <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 6px; padding: 10px 12px; margin-bottom: 15px;">
        <div style="font-weight: bold; font-size: 12px; margin-bottom: 6px; color: #2563eb;">
          <i class="fa fa-shield"></i> <?= $_android_perm_title ?? 'Syarat & Perizinan Android:'; ?>
        </div>
        <div style="font-size: 11.5px; line-height: 1.5; color: inherit;">
          <div style="display: flex; gap: 8px; margin-bottom: 5px;">
            <i class="fa fa-map-marker text-danger" style="margin-top: 2px;"></i>
            <span><?= $_android_perm_gps ?? '<b>GPS / Lokasi HP Aktif:</b> Android mewajibkan akses lokasi agar Chrome dapat memindai Bluetooth.'; ?></span>
          </div>
          <div style="display: flex; gap: 8px; margin-bottom: 5px;">
            <i class="fa fa-bluetooth text-primary" style="margin-top: 2px;"></i>
            <span><?= $_android_perm_bt ?? '<b>Bluetooth HP & Printer ON:</b> Pastikan printer menyala dan lampu indikator aktif.'; ?></span>
          </div>
          <div style="display: flex; gap: 8px;">
            <i class="fa fa-check-circle text-green" style="margin-top: 2px;"></i>
            <span><?= $_android_perm_nearby ?? '<b>Izin Perangkat Sekitar:</b> Jika Chrome meminta izin <i>"Nearby devices"</i>, pilih <b>Izinkan</b>.'; ?></span>
          </div>
        </div>
      </div>

      <!-- PILIHAN SCAN TOMBOL -->
      <div style="margin-bottom: 15px;">
        <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;" class="text-secondary">
          <?= $_choose_bt_scan_mode ?? 'Pilih Mode Pindai Bluetooth:'; ?>
        </div>
        <button type="button" class="btn bg-primary btn-block" onclick="startBtScan('printers')" style="padding: 10px; font-size: 12.5px; font-weight: bold; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <i class="fa fa-magic"></i> <?= $_scan_pos_printers_smart ?? 'Pindai Printer POS (Cerdas - Bersih)'; ?>
        </button>
        <div style="font-size: 10.5px; margin-bottom: 10px; line-height: 1.3;" class="text-muted">
          *<?= $_scan_pos_printers_smart_hint ?? 'Menyaring daftar agar hanya menampilkan printer POS (Panda, VSC, Eppos, RPP, MPT, PT-210, dll) dan menyembunyikan TV/Smartwatch.'; ?>
        </div>

        <button type="button" class="btn bg-secondary btn-block" onclick="startBtScan('all')" style="padding: 8px; font-size: 11.5px; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <i class="fa fa-search"></i> <?= $_scan_all_ble_devices ?? 'Pindai Semua Perangkat BLE'; ?>
        </button>
        <div style="font-size: 10.5px; line-height: 1.3; margin-top: 4px;" class="text-muted">
          *<?= $_scan_all_ble_devices_hint ?? 'Gunakan jika nama printer kustom Anda tidak muncul pada mode cerdas.'; ?>
        </div>
      </div>

      <!-- KARTU KHUSUS ANDROID / RAWBT -->
      <div style="border-top: 1px dashed rgba(128,128,128,0.3); padding-top: 12px; margin-top: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <b style="font-size: 11.5px;"><i class="fa fa-android text-green"></i> <?= $_android_rawbt_option ?? 'Opsi Android: RawBT Print'; ?></b>
          <span class="badge" style="background: #16a34a; color: #fff; font-size: 9px; padding: 2px 5px; border-radius: 3px;"><?= $_rawbt_compatibility_badge ?? '100% Kompatibel'; ?></span>
        </div>
        <div style="font-size: 11px; line-height: 1.4; margin-bottom: 10px;" class="text-secondary">
          <?= $_rawbt_desc ?? 'Printer Anda tipe <b>Bluetooth Classic SPP</b> (bukan BLE) atau kabel <b>USB OTG</b>? Gunakan <b>RawBT Android</b> untuk mencetak tanpa batasan browser!'; ?>
        </div>
        <div style="display: flex; gap: 6px;">
          <button type="button" class="btn bg-success" onclick="testPrintViaRawBt()" style="flex: 1; padding: 8px 6px; font-size: 11px; font-weight: bold;">
            <i class="fa fa-print"></i> <?= $_test_rawbt ?? 'Tes Cetak RawBT'; ?>
          </button>
          <a href="https://play.google.com/store/apps/details?id=ru.a402d.rawbtprinter" target="_blank" class="btn bg-secondary" style="padding: 8px 10px; font-size: 11px;">
            <i class="fa fa-download"></i> <?= $_install_rawbt ?? 'Pasang RawBT'; ?>
          </a>
        </div>
      </div>
    </div>

    <div class="card-footer" style="padding: 10px 15px; text-align: right; background: rgba(0,0,0,0.03); border-top: 1px solid rgba(0,0,0,0.08);">
      <button type="button" class="btn bg-secondary" onclick="closeScanModal()" style="padding: 6px 14px; font-size: 11.5px;">
        <?= $_close ?? 'Tutup'; ?>
      </button>
    </div>
  </div>
</div>

<script src="./js/mikhmon-thermal.js?v=<?= time(); ?>"></script>
<script>
var LANG_CONNECTED = <?= json_encode($_status_connected ?? 'Terhubung'); ?>;
var LANG_CONNECTING = <?= json_encode($_status_connecting ?? 'Menghubungkan...'); ?>;
var LANG_READY = <?= json_encode($_status_ready ?? 'Siap Mencetak'); ?>;
var LANG_NOT_CONNECTED = <?= json_encode($_printer_not_connected ?? 'Belum Terhubung'); ?>;
var LANG_CHANGE_PRINTER = <?= json_encode($_change_printer ?? 'Ganti Printer'); ?>;
var LANG_SCAN_PRINTER = <?= json_encode($_scan_bluetooth_printer ?? 'Pindai Printer Bluetooth'); ?>;

function openScanModal() {
  var modal = document.getElementById('dialogScanBtModal');
  if (modal) modal.style.display = 'flex';
}

function closeScanModal() {
  var modal = document.getElementById('dialogScanBtModal');
  if (modal) modal.style.display = 'none';
}

function startBtScan(mode) {
  closeScanModal();
  if (!window.MikhmonThermal) return;
  var alertEl = document.getElementById('btAlertArea');
  if (alertEl) alertEl.style.display = 'none';

  window.MikhmonThermal.connect(mode).catch(function(err) {
    console.error('Koneksi printer gagal:', err);
  });
}

function updateUiStatus(status, msg, thermalInstance) {
  var badge = document.getElementById('printerStatusBadge');
  var nameEl = document.getElementById('printerDeviceName');
  var connEl = document.getElementById('printerConnText');
  var btnTest = document.getElementById('btnTestPrint');
  var btnConn = document.getElementById('btnConnect');
  var btnDisconn = document.getElementById('btnDisconnect');
  var alertEl = document.getElementById('btAlertArea');

  if (status === 'connected') {
    badge.style.background = '#16a34a';
    badge.innerHTML = '<i class="fa fa-check-circle"></i> ' + LANG_CONNECTED;
    nameEl.textContent = thermalInstance.deviceName || 'Printer Thermal';
    nameEl.className = 'text-green font-bold';
    connEl.textContent = '🟢 ' + LANG_READY;
    connEl.style.color = '#16a34a';
    btnTest.disabled = false;
    btnDisconn.style.display = 'inline-block';
    btnConn.innerHTML = '<i class="fa fa-refresh"></i> ' + LANG_CHANGE_PRINTER;

    if (alertEl) {
      alertEl.style.display = 'block';
      alertEl.className = 'box bg-success';
      alertEl.innerHTML = '<i class="fa fa-check-circle"></i> ' + LANG_CONNECTED + ': <b>' + (thermalInstance.deviceName || '') + '</b>';
    }
  } else if (status === 'connecting') {
    badge.style.background = '#eab308';
    badge.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> ' + LANG_CONNECTING;
    connEl.textContent = msg || LANG_CONNECTING;
    connEl.style.color = '#eab308';
    btnTest.disabled = true;
  } else if (status === 'cancelled' || status === 'idle') {
    if (!thermalInstance || !thermalInstance.isConnected) {
      badge.style.background = '#64748b';
      badge.innerHTML = '<i class="fa fa-circle-o"></i> ' + LANG_NOT_CONNECTED;
      connEl.textContent = '⚪ ' + LANG_NOT_CONNECTED;
      connEl.style.color = '#64748b';
      btnTest.disabled = true;
    }
    if (alertEl) alertEl.style.display = 'none';
  } else {
    badge.style.background = '#64748b';
    badge.innerHTML = '<i class="fa fa-circle-o"></i> ' + LANG_NOT_CONNECTED;
    nameEl.textContent = '-';
    nameEl.className = 'text-secondary';
    connEl.textContent = '⚪ ' + LANG_NOT_CONNECTED;
    connEl.style.color = '#64748b';
    btnTest.disabled = true;
    btnDisconn.style.display = 'none';
    btnConn.innerHTML = '<i class="fa fa-search"></i> ' + LANG_SCAN_PRINTER;

    if (status === 'error' && alertEl) {
      alertEl.style.display = 'block';
      alertEl.className = 'box bg-danger';
      alertEl.innerHTML = '<i class="fa fa-warning"></i> ' + (msg || 'Error');
    }
  }
}

if (window.MikhmonThermal) {
  window.MikhmonThermal.addListener(updateUiStatus);

  // Sync settings to localStorage
  window.MikhmonThermal.paperSize = <?= json_encode($paperSize); ?>;
  window.MikhmonThermal.printMethod = <?= json_encode($printMethod); ?>;
  window.MikhmonThermal.templateStyle = <?= json_encode($templateStyle); ?>;
  window.MikhmonThermal.footerText = <?= json_encode($footerNote); ?>;
  window.MikhmonThermal.autoCut = <?= $autoCut ? 'true' : 'false'; ?>;

  localStorage.setItem('mikhmon_thermal_paper', <?= json_encode($paperSize); ?>);
  localStorage.setItem('mikhmon_thermal_method', <?= json_encode($printMethod); ?>);
  localStorage.setItem('mikhmon_thermal_style', <?= json_encode($templateStyle); ?>);
  localStorage.setItem('mikhmon_thermal_footer', <?= json_encode($footerNote); ?>);
  localStorage.setItem('mikhmon_thermal_autocut', <?= $autoCut ? "'yes'" : "'no'"; ?>);
}

function handleConnectPrinter() {
  openScanModal();
}

function handleDisconnectPrinter() {
  if (window.MikhmonThermal) {
    window.MikhmonThermal.disconnect();
  }
}

function handleTestPrint() {
  if (!window.MikhmonThermal) return;
  var btn = document.getElementById('btnTestPrint');
  var origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Mencetak...';

  window.MikhmonThermal.testPrint().then(function() {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check"></i> Berhasil!';
    setTimeout(function() {
      btn.innerHTML = origHtml;
    }, 2500);
  }).catch(function(err) {
    btn.disabled = false;
    btn.innerHTML = origHtml;
    alert('Gagal cetak uji coba: ' + (err.message || err));
  });
}

function testPrintViaRawBt() {
  if (window.MikhmonThermal) {
    window.MikhmonThermal.testPrintRawBT();
  }
}

function syncPaperSizeLocal(val) {
  if (window.MikhmonThermal) window.MikhmonThermal.paperSize = val;
  localStorage.setItem('mikhmon_thermal_paper', val);
}

function syncPrintMethodLocal(val) {
  if (window.MikhmonThermal) window.MikhmonThermal.printMethod = val;
  localStorage.setItem('mikhmon_thermal_method', val);
}

function syncTemplateStyleLocal(val) {
  if (window.MikhmonThermal) window.MikhmonThermal.templateStyle = val;
  localStorage.setItem('mikhmon_thermal_style', val);
  updatePreviewReceipt(val);
}

function syncFooterLocal(val) {
  if (window.MikhmonThermal) window.MikhmonThermal.footerText = val;
  localStorage.setItem('mikhmon_thermal_footer', val);
  updatePreviewReceipt(document.getElementById('selectTemplateStyle') ? document.getElementById('selectTemplateStyle').value : 'classic');
}

function syncAutoCutLocal(val) {
  if (window.MikhmonThermal) window.MikhmonThermal.autoCut = val;
  localStorage.setItem('mikhmon_thermal_autocut', val ? 'yes' : 'no');
}

function updatePreviewReceipt(style) {
  var box = document.getElementById('receiptPreviewBox');
  var lbl = document.getElementById('previewStyleName');
  if (!box) return;

  var styleNames = {
    'classic': <?= json_encode($_thermal_style_classic ?? 'Standar (Klasik)'); ?>,
    'compact': <?= json_encode($_thermal_style_compact ?? 'Minimalis (Hemat Kertas)'); ?>,
    'modern': <?= json_encode($_thermal_style_modern ?? 'Modern Box (Bingkai Elegan)'); ?>,
    'detailed': <?= json_encode($_thermal_style_detailed ?? 'Lengkap (Panduan Login)'); ?>
  };
  if (lbl) lbl.textContent = styleNames[style] || style;

  var hs = <?= json_encode($hotspotname ?? 'HOTSPOT NODERA'); ?>;
  var dns = <?= json_encode($dnsname ?? 'wifi.hotspot'); ?>;
  var foot = (document.getElementById('inputFooterNote') ? document.getElementById('inputFooterNote').value : '') || <?= json_encode($footerNote); ?>;

  var html = '';
  if (style === 'compact') {
    html += '<div style="text-align: center; font-weight: bold; font-size: 13px;">' + hs + '</div>';
    html += '<div style="border-top: 1px dashed #000; margin: 4px 0;"></div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px;"><span>1 Jam</span><b>Rp 3.000</b></div>';
    html += '<div style="text-align: center; font-size: 18px; font-weight: 900; letter-spacing: 2px; margin: 6px 0; border: 1px dashed #000; padding: 4px;">ABC1234</div>';
    html += '<div style="border-top: 1px dashed #000; margin: 4px 0;"></div>';
    html += '<div style="text-align: center; font-size: 9px; color: #4b5563;">' + dns + '</div>';
  } else if (style === 'modern') {
    html += '<div style="text-align: center; font-weight: 900; font-size: 14px; letter-spacing: 1px;">================================</div>';
    html += '<div style="text-align: center; font-weight: bold; font-size: 13px; margin: 2px 0;">' + hs + '</div>';
    html += '<div style="text-align: center; font-size: 10px; color: #64748b; margin-bottom: 2px;">' + dns + '</div>';
    html += '<div style="text-align: center; font-weight: 900; font-size: 14px; letter-spacing: 1px;">================================</div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px; margin: 2px 0;"><span>Paket:</span><b>3 Jam Reguler</b></div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px; margin: 2px 0;"><span>Masa Aktif:</span><b>3 Jam</b></div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px; margin: 2px 0;"><span>Harga:</span><b>Rp 5.000</b></div>';
    html += '<div style="border: 2px solid #000; border-radius: 4px; padding: 6px; margin: 8px 0; text-align: center; background: #f8fafc;">';
    html += '  <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 1px;">KODE VOUCHER</div>';
    html += '  <div style="font-size: 20px; font-weight: 900; letter-spacing: 3px; color: #000; margin: 2px 0;">XYZ-7890</div>';
    html += '</div>';
    html += '<div style="text-align: center; font-weight: 900; font-size: 14px; letter-spacing: 1px;">================================</div>';
    html += '<div style="text-align: center; font-size: 9px; color: #64748b; margin-top: 4px;">' + foot + '</div>';
  } else if (style === 'detailed') {
    html += '<div style="text-align: center; font-weight: bold; font-size: 13px;">' + hs + '</div>';
    html += '<div style="text-align: center; font-size: 10px; color: #64748b;">' + dns + '</div>';
    html += '<div style="border-top: 1px dashed #000; margin: 6px 0;"></div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px;"><span>Paket:</span><b>1 Hari Unlimited</b></div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px;"><span>Masa Aktif:</span><b>24 Jam</b></div>';
    html += '<div style="display: flex; justify-content: space-between; font-size: 11px;"><span>Harga:</span><b>Rp 10.000</b></div>';
    html += '<div style="border: 2px solid #000; padding: 6px; margin: 6px 0; text-align: center; background: #f8fafc;">';
    html += '  <div style="font-size: 9px; text-transform: uppercase;">Kode Voucher:</div>';
    html += '  <div style="font-size: 18px; font-weight: 900; letter-spacing: 2px;">VCH-2026</div>';
    html += '</div>';
    html += '<div style="border: 1px dashed #cbd5e1; border-radius: 4px; padding: 6px; margin: 6px 0; background: #f8fafc; font-size: 10px; text-align: left;">';
    html += '  <b style="display: block; margin-bottom: 3px;"><i class="fa fa-info-circle"></i> ' + (<?= json_encode($_how_to_login ?? 'Cara Login:'); ?>) + '</b>';
    html += '  <div>' + (<?= json_encode($_step_connect_wifi ?? '1. Sambungkan ke WiFi Hotspot'); ?>) + '</div>';
    html += '  <div>' + (<?= json_encode($_step_open_browser ?? '2. Buka browser: '); ?>) + 'http://' + dns + '</div>';
    html += '  <div>' + (<?= json_encode($_step_enter_code ?? '3. Masukkan kode voucher di atas'); ?>) + '</div>';
    html += '</div>';
    html += '<div style="border-top: 1px dashed #000; margin: 6px 0;"></div>';
    html += '<div style="text-align: center; font-size: 9px; color: #64748b;">' + foot + '</div>';
  } else { // classic
    html += '<div style="text-align: center; font-weight: bold; font-size: 13px;">' + hs + '</div>';
    html += '<div style="text-align: center; font-size: 10px; color: #64748b;">' + dns + '</div>';
    html += '<div style="border-top: 1px dashed #000; margin: 6px 0;"></div>';
    html += '<div style="font-size: 11px;">Paket: <b>5 Jam Reguler</b></div>';
    html += '<div style="font-size: 11px;">Masa Aktif: <b>5 Jam</b></div>';
    html += '<div style="font-size: 12px; font-weight: bold; margin: 2px 0;">Harga: Rp 7.000</div>';
    html += '<div style="border: 2px solid #000; padding: 6px; margin: 6px 0; text-align: center; background: #f8fafc;">';
    html += '  <div style="font-size: 9px; text-transform: uppercase;">Kode Voucher:</div>';
    html += '  <div style="font-size: 19px; font-weight: 900; letter-spacing: 2px;">N7X-4421</div>';
    html += '</div>';
    html += '<div style="border-top: 1px dashed #000; margin: 6px 0;"></div>';
    html += '<div style="text-align: center; font-size: 9px; color: #64748b;">' + foot + '</div>';
  }

  box.innerHTML = html;
}

$(document).ready(function() {
  updatePreviewReceipt(<?= json_encode($templateStyle); ?>);
});
</script>
