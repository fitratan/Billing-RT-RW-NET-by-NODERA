<?php
/**
 * MIKHMON Engine — WhatsApp Gateway & Notifications
 * by NODERA (nodera.id)
 */
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    echo "<script>window.location='./admin.php?id=login'</script>";
    exit;
}

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

$session = isset($_GET['session']) && !empty($_GET['session']) 
    ? $_GET['session'] 
    : (isset($_SESSION['session']) && !empty($_SESSION['session']) ? $_SESSION['session'] : '');

if (empty($session) && isset($data) && is_array($data)) {
    $locConfigFile = __DIR__ . '/location_config.php';
    if (file_exists($locConfigFile)) {
        $location_data = [];
        include $locConfigFile;
        if (!empty($location_data['primary']) && isset($data[$location_data['primary']])) {
            $session = $location_data['primary'];
        }
    }
    if (empty($session)) {
        foreach ($data as $k => $v) {
            if ($k !== 'mikhmon') {
                $session = $k;
                break;
            }
        }
    }
}

if (!isset($data) || !is_array($data)) {
    $cfgFile = __DIR__ . '/config.php';
    if (file_exists($cfgFile)) {
        include_once $cfgFile;
    }
}

// Load WhatsApp config file
$wa_config_file = __DIR__ . '/whatsapp_config.php';
$wa_data = [];
if (file_exists($wa_config_file)) {
    include_once $wa_config_file;
}

$default_template = "*PEMBAYARAN BERHASIL — {brand}*\n"
    . "----------------------------------------\n"
    . "Terima kasih telah membeli voucher WiFi.\n\n"
    . "Paket: {package}\n"
    . "Total Bayar: Rp {price}\n\n"
    . "DETAIL LOGIN:\n"
    . "{login_detail}\n\n"
    . "Simpan pesan ini jika sewaktu-waktu perangkat Anda terputus.";

// STRICT: muat data konfigurasi terisolasi per-sesi lokal
$existingCfg = null;
if (!empty($session) && isset($wa_data[$session]) && is_array($wa_data[$session])) {
    $existingCfg = $wa_data[$session];
} else {
    foreach ($wa_data as $k => $c) {
        if ($k !== '_global' && is_array($c) && !empty($c)) {
            $existingCfg = $c;
            break;
        }
    }
}

$cfg = $existingCfg ?: [
    'enabled'            => 'yes',
    'provider'           => 'nodera',
    'api_url'            => 'https://dgtlnetsolution.com/api/message/send',
    'api_token'          => '',
    'session_id'         => '',
    'admin_phone'        => '',
    'cs_phone'           => '',
    'brand_name'         => 'NODERA WIFI',
    'notif_admin_sale'   => 'yes',
    'notif_daily_report' => 'yes',
    'notif_low_stock'    => 'yes',
    'low_stock_threshold' => '5',
    'buyer_msg_template' => $default_template,
];

if (empty($cfg['buyer_msg_template'])) {
    $cfg['buyer_msg_template'] = $default_template;
}

$alert_msg = '';
$alert_type = '';

include_once __DIR__ . '/whatsapp_helper.php';

// Function to save WhatsApp config
if (!function_exists('mikhmon_save_whatsapp_config')) {
    function mikhmon_save_whatsapp_config($file, $allData) {
        $out = "<?php\n";
        $out .= 'if(substr($_SERVER["REQUEST_URI"], -19) == "whatsapp_config.php"){header("Location:./");};' . "\n";
        $out .= '$wa_data = ' . var_export($allData, true) . ";\n";
        return file_put_contents($file, $out) !== false;
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_wa'])) {
        $cfg['enabled']            = isset($_POST['wa_enabled']) && $_POST['wa_enabled'] === 'yes' ? 'yes' : 'no';
        $cfg['provider']           = trim($_POST['wa_provider'] ?? 'nodera');
        $cfg['api_url']            = trim($_POST['wa_api_url'] ?? 'https://dgtlnetsolution.com/api/message/send');
        $cfg['api_token']          = trim($_POST['wa_api_token'] ?? '');
        $cfg['session_id']         = trim($_POST['wa_session_id'] ?? '');
        $cfg['admin_phone']        = trim($_POST['wa_admin_phone'] ?? '');
        $cfg['cs_phone']           = trim($_POST['wa_cs_phone'] ?? '');
        $cfg['brand_name']         = trim($_POST['wa_brand_name'] ?? 'NODERA WIFI');
        $cfg['notif_admin_sale']   = isset($_POST['wa_notif_admin_sale']) && $_POST['wa_notif_admin_sale'] === 'yes' ? 'yes' : 'no';
        $cfg['notif_daily_report'] = isset($_POST['wa_notif_daily_report']) && $_POST['wa_notif_daily_report'] === 'yes' ? 'yes' : 'no';
        $cfg['notif_failed']       = isset($_POST['wa_notif_failed']) && $_POST['wa_notif_failed'] === 'yes' ? 'yes' : 'no';
        $cfg['notif_low_stock']    = isset($_POST['wa_notif_low_stock']) && $_POST['wa_notif_low_stock'] === 'yes' ? 'yes' : 'no';
        $cfg['low_stock_threshold'] = max(1, intval($_POST['wa_low_stock_threshold'] ?? 5));
        $cfg['buyer_msg_template'] = $default_template;

        if (!empty($session)) {
            $wa_data[$session] = $cfg;
        } else {
            $wa_data['default'] = $cfg;
        }

        $is_apply_all = isset($_POST['wa_apply_all']) && $_POST['wa_apply_all'] === 'yes';
        $cfg['apply_all'] = $is_apply_all ? 'yes' : 'no';
        $applied_count = 0;
        // Terapkan ke sesi router lokal lainnya jika dicentang
        if ($is_apply_all && isset($data) && is_array($data)) {
            foreach ($data as $k => $v) {
                if ($k !== 'mikhmon' && is_array($v)) {
                    $wa_data[$k] = $cfg;
                    $applied_count++;
                }
            }
        }

        unset($wa_data['_global']);

        if (mikhmon_save_whatsapp_config($wa_config_file, $wa_data)) {
            $alert_type = 'success';
            if ($is_apply_all && $applied_count > 0) {
                $alert_msg = sprintf(($_alert_wa_saved_multi ?? 'Pengaturan WhatsApp Gateway berhasil disimpan dan diterapkan ke %s sesi router!'), $applied_count);
            } else {
                $alert_msg = $_alert_wa_saved_session ?? 'Pengaturan WhatsApp Gateway berhasil disimpan untuk sesi ini!';
            }
        } else {
            $alert_type = 'danger';
            $alert_msg = $_alert_save_config_failed ?? 'Gagal menyimpan konfigurasi. Pastikan izin folder include/ dapat ditulis (writable).';
        }
    } elseif (isset($_POST['test_wa'])) {
        $testPhone   = trim($_POST['test_phone'] ?? '');
        $testToken   = trim($_POST['wa_api_token'] ?? $cfg['api_token']);
        $testUrl     = trim($_POST['wa_api_url'] ?? $cfg['api_url']);
        $testProv    = trim($_POST['wa_provider'] ?? $cfg['provider']);
        $testBrand   = trim($_POST['wa_brand_name'] ?? ($cfg['brand_name'] ?: 'NODERA WIFI'));
        $sessionId   = trim($_POST['wa_session_id'] ?? ($cfg['session_id'] ?? ''));

        if (empty($testToken)) {
            $alert_type = 'danger';
            $alert_msg = $_alert_wa_token_empty ?? 'API Token / Key belum diisi. Silakan simpan konfigurasi terlebih dahulu.';
        } elseif (empty($testPhone)) {
            $alert_type = 'danger';
            $alert_msg = $_alert_wa_phone_empty ?? 'Masukkan nomor WhatsApp tujuan untuk melakukan tes kirim.';
        } else {
            $sampleMsg = sprintf(($_wa_sample_test_msg ?? "Halo! Ini adalah pesan verifikasi dari WhatsApp Gateway %s.\n\nGateway telah terhubung dan siap mengirimkan kode voucher WiFi secara otomatis kepada pelanggan setelah pembayaran divalidasi.\n\nWaktu: %s"), $testBrand, date('d/m/Y H:i:s'));

            $res = mikhmon_send_whatsapp($testToken, $testUrl, $testPhone, $sampleMsg, $testProv, $sessionId);
            if ($res['ok'] ?? false) {
                $alert_type = 'success';
                $alert_msg = sprintf(($_alert_wa_test_success ?? 'Pesan uji coba WhatsApp BERHASIL terkirim ke %s!'), htmlspecialchars($testPhone));
            } else {
                $alert_type = 'danger';
                $alert_msg = ($_alert_wa_test_failed ?? 'Gagal mengirim pesan WhatsApp: ') . htmlspecialchars($res['description'] ?? ($_alert_wa_check_creds ?? 'Periksa API Token dan Nomor HP.'));
            }
        }
    }
}
?>

<div class="row">
  <!-- Kolom Kiri: Form Kredensial & Pengaturan -->
  <div class="col-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-whatsapp"></i> <?= $_wa_gateway_settings ?? "Pengaturan WhatsApp Gateway"; ?> <?= !empty($session) ? '<small>(' . htmlspecialchars($session) . ')</small>' : ''; ?></h3>
      </div>
      <div class="card-body">

        <?php if (!empty($alert_msg)): ?>
          <div class="alert bg-<?= $alert_type; ?>" style="padding: 10px 14px; margin-bottom: 15px; border-radius: 4px;">
            <i class="fa <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?= $alert_msg; ?>
          </div>
        <?php endif; ?>

        <form autocomplete="off" method="post" action="">
          <table class="table table-sm">
            <tr>
              <td class="align-middle" style="width: 35%;"><?= $_wa_status ?? "Status WhatsApp Gateway"; ?></td>
              <td>
                <select class="form-control" name="wa_enabled">
                  <option value="yes" <?= ($cfg['enabled'] ?? 'yes') === 'yes' ? 'selected' : ''; ?>><?= $_active ?? "Aktif" ?></option>
                  <option value="no" <?= ($cfg['enabled'] ?? 'yes') !== 'yes' ? 'selected' : ''; ?>><?= $_inactive ?? "Nonaktif" ?></option>
                </select>
                <small class="text-secondary"><?= $_send_auto_wa_on_payment ?? "Kirim kode voucher otomatis ke WhatsApp pembeli saat bayar QRIS sukses." ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wifi_brand_name ?? "Nama WiFi / Brand"; ?></td>
              <td>
                <input class="form-control" type="text" name="wa_brand_name" value="<?= htmlspecialchars($cfg['brand_name'] ?: 'NODERA WIFI'); ?>" placeholder="<?= $_placeholder_wa_brand ?? "Contoh: NODERA WIFI"; ?>" required="1">
                <small class="text-secondary"><?= $_wa_header_brand_hint ?? "Nama yang dicantumkan pada header pesan WhatsApp pembeli." ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_provider ?? "Provider Gateway"; ?></td>
              <td>
                <select class="form-control" name="wa_provider" id="wa_provider_select" onchange="updateProviderUrl(true)">
                  <option value="nodera" <?= ($cfg['provider'] ?? 'nodera') === 'nodera' ? 'selected' : ''; ?>><?= $_wa_prov_nodera ?? "NODERA WhatsApp Gateway (wa.dgtlnetsolution.com) [Direkomendasikan]"; ?></option>
                  <option value="fonnte" <?= ($cfg['provider'] ?? '') === 'fonnte' ? 'selected' : ''; ?>><?= $_wa_prov_fonnte ?? "Fonnte (api.fonnte.com)"; ?></option>
                  <option value="mhwa" <?= ($cfg['provider'] ?? '') === 'mhwa' ? 'selected' : ''; ?>><?= $_wa_prov_mhwa ?? "MHWA Gateway (mhwa.biz.id)"; ?></option>
                  <option value="mpwa" <?= ($cfg['provider'] ?? '') === 'mpwa' ? 'selected' : ''; ?>><?= $_wa_prov_mpwa ?? "MPWA / Baileys Gateway (/send-message)"; ?></option>
                  <option value="wablas" <?= ($cfg['provider'] ?? '') === 'wablas' ? 'selected' : ''; ?>><?= $_wa_prov_wablas ?? "Wablas (api.wablas.com)"; ?></option>
                  <option value="starsender" <?= ($cfg['provider'] ?? '') === 'starsender' ? 'selected' : ''; ?>><?= $_wa_prov_starsender ?? "Starsender (starsender.online)"; ?></option>
                  <option value="whacenter" <?= ($cfg['provider'] ?? '') === 'whacenter' ? 'selected' : ''; ?>><?= $_wa_prov_whacenter ?? "WhaCenter (app.whacenter.com)"; ?></option>
                  <option value="custom" <?= ($cfg['provider'] ?? '') === 'custom' ? 'selected' : ''; ?>><?= $_wa_prov_custom ?? "Custom API Endpoint (Universal JSON & Form)"; ?></option>
                </select>
                <small class="text-secondary"><?= $_wa_provider_hint ?? "Pilih provider WhatsApp Gateway yang Anda gunakan." ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_api_url ?? "API URL Endpoint"; ?></td>
              <td>
                <input class="form-control" type="text" name="wa_api_url" id="wa_api_url_input" value="<?= htmlspecialchars($cfg['api_url'] ?: 'https://dgtlnetsolution.com/api/message/send'); ?>" placeholder="https://dgtlnetsolution.com/api/message/send" required="1">
                <small class="text-secondary"><?= $_wa_endpoint_hint ?? "URL API endpoint pengiriman pesan dari provider WhatsApp." ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_api_token ?? "API Token / Key"; ?></td>
              <td>
                <div class="input-group">
                  <div class="input-group-11 col-box-10">
                    <input class="group-item group-item-l" id="wa_token" type="password" name="wa_api_token" value="<?= htmlspecialchars($cfg['api_token']); ?>" placeholder="<?= $_wa_token_placeholder ?? 'Masukkan API Key / Token WhatsApp...'; ?>" required="1">
                  </div>
                  <div class="input-group-1 col-box-2">
                    <div class="group-item group-item-r pd-2p5 text-center align-middle">
                      <input title="<?= $_wa_show_token ?? 'Tampilkan Token'; ?>" type="checkbox" onclick="var x=document.getElementById('wa_token'); x.type=(x.type==='password'?'text':'password');">
                    </div>
                  </div>
                </div>
                <small class="text-secondary" id="hint_api_token"><?= $_wa_get_key_hint ?? "Dapatkan API Key dari dashboard provider WhatsApp Anda."; ?></small>
              </td>
            </tr>
            <tr id="row_session_id">
              <td class="align-middle" id="label_session_id"><?= $_wa_session_id ?? "Session ID Perangkat WhatsApp"; ?></td>
              <td>
                <input class="form-control" type="text" name="wa_session_id" id="input_session_id" value="<?= htmlspecialchars($cfg['session_id'] ?? ''); ?>" placeholder="<?= $_placeholder_wa_session_id ?? "Contoh: dgtl_m1_abc123 (lihat di menu Perangkat)"; ?>">
                <small class="text-secondary" id="hint_session_id">
                  <?= $_wa_session_id_hint ?? "Session ID perangkat WhatsApp pengirim dari dashboard provider. Kosongkan jika memakai perangkat default."; ?>
                </small>
              </td>
            </tr>
            <tr id="row_admin_phone">
              <td class="align-middle" id="label_admin_phone"><?= $_wa_admin_phone ?? "No. WA Admin (CS & Notifikasi)"; ?></td>
              <td>
                <input class="form-control" type="text" name="wa_admin_phone" id="input_admin_phone" value="<?= htmlspecialchars($cfg['admin_phone'] ?? ''); ?>" placeholder="<?= $_placeholder_wa_admin_phone ?? "Contoh: 081234567890"; ?>">
                <small class="text-secondary" id="hint_admin_phone">
                  <?= $_wa_admin_phone_hint ?? "Nomor WhatsApp Admin aktif untuk menerima laporan penjualan dan tombol Hubungi CS di voucher pembeli."; ?>
                </small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_sales_notif ?? "Notifikasi Penjualan"; ?></td>
              <td>
                <label style="cursor: pointer;">
                  <input type="checkbox" name="wa_notif_admin_sale" value="yes" <?= ($cfg['notif_admin_sale'] ?? 'yes') === 'yes' ? 'checked' : ''; ?>> <?= $_wa_notify_sales_checkbox ?? "Kirim notifikasi ke nomor Admin saat voucher terjual" ?>
                </label>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_low_stock ?? "Stok Voucher Menipis"; ?></td>
              <td>
                <label style="cursor: pointer;">
                  <input type="checkbox" name="wa_notif_low_stock" value="yes" <?= ($cfg['notif_low_stock'] ?? 'yes') === 'yes' ? 'checked' : ''; ?>> <?= $_wa_notify_low_stock_checkbox ?? "Kirim WhatsApp ke Admin saat stok voucher hampir habis" ?>
                </label>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_min_stock_threshold ?? "Batas Minimal Stok" ?></td>
              <td>
                <div class="input-group">
                  <div class="input-group-10">
                    <input class="group-item group-item-l" type="number" min="1" max="500" name="wa_low_stock_threshold" value="<?= htmlspecialchars((string)($cfg['low_stock_threshold'] ?? 5)); ?>" required="1">
                  </div>
                  <div class="input-group-2">
                    <span class="group-item group-item-r pd-2p5 text-center align-middle"><?= $_voucher ?? "Voucher" ?></span>
                  </div>
                </div>
                <small class="text-secondary"><?= $_wa_min_stock_hint ?? "Peringatan otomatis terkirim jika sisa voucher pada profil mencapai atau di bawah angka ini."; ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_failed_notif ?? "Notifikasi Gagal / Rejek"; ?></td>
              <td>
                <label style="cursor: pointer;">
                  <input type="checkbox" name="wa_notif_failed" value="yes" <?= ($cfg['notif_failed'] ?? 'yes') === 'yes' ? 'checked' : ''; ?>> <?= $_wa_notify_failed_checkbox ?? "Kirim WhatsApp ke Pelanggan saat pembayaran gagal atau ditolak"; ?>
                </label>
                <small class="text-secondary" style="display: block; margin-top: 2px;"><?= $_wa_notify_failed_hint ?? "Kirim pesan otomatis ke pembeli jika transaksi kedaluwarsa atau ditolak admin."; ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_wa_sync_session ?? "Sinkronisasi Sesi"; ?></td>
              <td>
                <label style="cursor: pointer;">
                  <input type="checkbox" name="wa_apply_all" value="yes" <?= ($cfg['apply_all'] ?? 'yes') === 'yes' ? 'checked' : ''; ?>> <?= $_wa_apply_all_checkbox ?? "Terapkan konfigurasi ini ke semua sesi router"; ?>
                </label>
                <small class="text-secondary" style="display: block; margin-top: 2px;"><?= $_wa_apply_all_hint ?? "Centang opsi ini untuk menduplikat pengaturan WhatsApp Gateway ke semua sesi router saat disimpan."; ?></small>
              </td>
            </tr>
            <tr>
              <td colspan="2">
                <button class="btn bg-primary" type="submit" name="save_wa"><i class="fa fa-save"></i> <?= $_save ?? 'Simpan' ?></button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Uji Coba & Panduan -->
  <div class="col-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-paper-plane"></i> <?= $_wa_test_card ?? "Uji Coba WhatsApp"; ?></h3>
      </div>
      <div class="card-body">
        <p style="margin-top: 0; font-size: 12.5px;" class="text-secondary"><?= $_wa_test_desc ?? "Kirim pesan verifikasi ke nomor tujuan untuk memastikan koneksi gateway terhubung sempurna."; ?></p>
        <form method="post" action="" id="form_test_wa" onsubmit="syncTestFields()">
          <input type="hidden" name="wa_provider" id="test_wa_provider" value="<?= htmlspecialchars($cfg['provider']); ?>">
          <input type="hidden" name="wa_api_url" id="test_wa_api_url" value="<?= htmlspecialchars($cfg['api_url']); ?>">
          <input type="hidden" name="wa_api_token" id="test_wa_api_token" value="<?= htmlspecialchars($cfg['api_token']); ?>">
          <input type="hidden" name="wa_brand_name" id="test_wa_brand_name" value="<?= htmlspecialchars($cfg['brand_name'] ?: 'NODERA WIFI'); ?>">
          <input type="hidden" name="wa_session_id" id="test_wa_session_id" value="<?= htmlspecialchars($cfg['session_id'] ?? ''); ?>">
          <input type="hidden" name="wa_admin_phone" id="test_wa_admin_phone" value="<?= htmlspecialchars($cfg['admin_phone'] ?? ''); ?>">

          <div class="form-group" style="margin-bottom: 12px;">
            <label style="font-weight: bold; font-size: 12px;"><?= $_wa_test_dest_phone ?? "Nomor WhatsApp Tujuan Tes:"; ?></label>
            <input class="form-control" type="text" name="test_phone" id="test_phone_input" placeholder="<?= $_wa_test_phone_placeholder ?? 'Contoh: 081234567890 atau 628xxx'; ?>" required="1">
            <small class="text-secondary"><?= $_wa_test_phone_hint ?? "Masukkan nomor WhatsApp aktif Anda untuk menerima pesan tes."; ?></small>
          </div>
          <button class="btn bg-primary btn-block" type="submit" name="test_wa"><i class="fa fa-send"></i> <?= $_wa_send_test_btn ?? "Tes Kirim WhatsApp"; ?></button>
        </form>
      </div>
    </div>

    <div class="card" style="margin-top: 15px;">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-book"></i> <?= $_wa_guide ?? "Panduan Penggunaan"; ?></h3>
      </div>
      <div class="card-body" style="font-size: 12.5px; line-height: 1.6;">
        <ol style="padding-left: 18px; margin-top: 0; margin-bottom: 0;">
          <li><b><?= $_wa_provider ?? "Provider Gateway"; ?>:</b> <?= $_wa_guide_step1 ?? "Pilih <b>NODERA WhatsApp Gateway</b>, <b>Fonnte</b>, atau provider pilihan Anda."; ?></li>
          <li><b><?= $_wa_api_token ?? "API Token / Key"; ?>:</b> <?= $_wa_guide_step2 ?? "Masukkan API Token/Key dari dashboard provider WhatsApp Anda."; ?></li>
          <li><b><?= $_wa_session_id ?? "Session ID Perangkat"; ?>:</b> <?= $_wa_guide_step3 ?? "Untuk provider multi-device (NODERA/MHWA/WhaCenter), masukkan Session ID. Untuk provider berbasis Token (Fonnte/Wablas/Starsender), kolom Session ID otomatis disembunyikan."; ?></li>
          <li><b><?= $_wa_admin_phone ?? "No. WA Admin"; ?>:</b> <?= $_wa_guide_step4 ?? "Masukkan nomor WhatsApp admin Anda untuk menerima laporan & tombol bantuan pelanggan."; ?></li>
          <li><b><?= $_wa_test_card ?? "Uji Coba"; ?>:</b> <?= $_wa_guide_step5 ?? "Masukkan nomor WA tujuan Anda di form sebelah kanan lalu klik <b>Tes Kirim WhatsApp</b>."; ?></li>
          <li><b><?= $_status ?? "Status"; ?>:</b> <?= $_wa_guide_step6 ?? "Setelah koneksi aktif, pembeli yang membayar via QRIS di Toko Online akan otomatis menerima kode voucher resmi via WhatsApp."; ?></li>
        </ol>
      </div>
    </div>
  </div>
</div>

<script>
function syncTestFields() {
  var prov = document.getElementById('wa_provider_select');
  var url = document.getElementById('wa_api_url_input');
  var token = document.getElementById('wa_token');
  var brand = document.querySelector('input[name="wa_brand_name"]');
  var sess = document.getElementById('input_session_id');
  var phone = document.getElementById('input_admin_phone');

  if (prov) document.getElementById('test_wa_provider').value = prov.value;
  if (url) document.getElementById('test_wa_api_url').value = url.value;
  if (token) document.getElementById('test_wa_api_token').value = token.value;
  if (brand) document.getElementById('test_wa_brand_name').value = brand.value;
  if (sess) document.getElementById('test_wa_session_id').value = sess.value;
  if (phone) document.getElementById('test_wa_admin_phone').value = phone.value;
}

function updateProviderUrl(isChange) {
  var provSelect = document.getElementById('wa_provider_select');
  if (!provSelect) return;
  var prov = provSelect.value;
  var urlInput = document.getElementById('wa_api_url_input');
  var rowSess = document.getElementById('row_session_id');
  var labelSess = document.getElementById('label_session_id');
  var inputSess = document.getElementById('input_session_id');
  var hintSess = document.getElementById('hint_session_id');
  var hintToken = document.getElementById('hint_api_token');

  var defaultUrls = {
    'nodera': 'https://dgtlnetsolution.com/api/message/send',
    'fonnte': 'https://api.fonnte.com/send',
    'mhwa': 'https://mhwa.biz.id/api/message/send',
    'wablas': 'https://api.wablas.com/api/send-message',
    'starsender': 'https://api.starsender.online/api/send',
    'whacenter': 'https://app.whacenter.com/api/send'
  };

  if (isChange && defaultUrls[prov] && urlInput) {
    urlInput.value = defaultUrls[prov];
  }

  // Provider specific settings & session ID row visibility:
  if (prov === 'fonnte') {
    if (rowSess) rowSess.style.display = 'none';
    if (urlInput && !urlInput.value) urlInput.value = 'https://api.fonnte.com/send';
    if (hintToken) hintToken.innerHTML = 'Dapatkan API Token dari menu Device di dashboard <a href="https://fonnte.com" target="_blank">fonnte.com</a> (Token-based, tidak memerlukan Session ID).';
  } else if (prov === 'wablas') {
    if (rowSess) rowSess.style.display = 'none';
    if (urlInput && !urlInput.value) urlInput.value = 'https://api.wablas.com/api/send-message';
    if (hintToken) hintToken.innerHTML = 'Dapatkan API Key Authorization dari dashboard <a href="https://wablas.com" target="_blank">wablas.com</a> (Token-based, tidak memerlukan Session ID).';
  } else if (prov === 'starsender') {
    if (rowSess) rowSess.style.display = 'none';
    if (urlInput && !urlInput.value) urlInput.value = 'https://api.starsender.online/api/send';
    if (hintToken) hintToken.innerHTML = 'Dapatkan ApiKey dari dashboard <a href="https://starsender.online" target="_blank">starsender.online</a> (Token-based, tidak memerlukan Session ID).';
  } else if (prov === 'nodera') {
    if (rowSess) rowSess.style.display = '';
    if (urlInput && !urlInput.value) urlInput.value = 'https://dgtlnetsolution.com/api/message/send';
    if (labelSess) labelSess.innerHTML = 'Session ID / Device ID (Opsional)';
    if (inputSess) inputSess.placeholder = 'Contoh: dgtl_m1_abc123 (lihat di wa.dgtlnetsolution.com)';
    if (hintSess) hintSess.innerHTML = 'Session ID perangkat dari portal <a href="https://wa.dgtlnetsolution.com" target="_blank">wa.dgtlnetsolution.com</a>. Kosongkan jika ingin memakai perangkat default.';
    if (hintToken) hintToken.innerHTML = 'Dapatkan API Key dari dashboard <a href="https://wa.dgtlnetsolution.com" target="_blank">wa.dgtlnetsolution.com</a>.';
  } else if (prov === 'mhwa') {
    if (rowSess) rowSess.style.display = '';
    if (urlInput && !urlInput.value) urlInput.value = 'https://mhwa.biz.id/api/message/send';
    if (labelSess) labelSess.innerHTML = 'Session ID Perangkat (Wajib MHWA)';
    if (inputSess) inputSess.placeholder = 'Contoh: dev_21_1789530016696 (lihat di mhwa.biz.id)';
    if (hintSess) hintSess.innerHTML = '<b>Wajib diisi untuk MHWA:</b> Masukkan <code>Session ID</code> perangkat WhatsApp Anda dari dashboard <a href="https://mhwa.biz.id" target="_blank">mhwa.biz.id</a>.';
    if (hintToken) hintToken.innerHTML = 'Dapatkan API Key (x-api-key) dari dashboard <a href="https://mhwa.biz.id" target="_blank">mhwa.biz.id</a>.';
  } else if (prov === 'whacenter') {
    if (rowSess) rowSess.style.display = '';
    if (urlInput && !urlInput.value) urlInput.value = 'https://app.whacenter.com/api/send';
    if (labelSess) labelSess.innerHTML = 'Device ID WhaCenter';
    if (inputSess) inputSess.placeholder = 'Device ID WhaCenter';
    if (hintSess) hintSess.innerHTML = 'Device ID perangkat di dashboard WhaCenter.';
    if (hintToken) hintToken.innerHTML = 'Dapatkan kredensial dari dashboard WhaCenter.';
  } else if (prov === 'mpwa') {
    if (rowSess) rowSess.style.display = '';
    if (urlInput && !urlInput.value) urlInput.value = 'http://localhost:8000/send-message';
    if (labelSess) labelSess.innerHTML = 'Sender / Session ID (Opsional)';
    if (inputSess) inputSess.placeholder = 'Nomor pengirim / sender ID';
    if (hintSess) hintSess.innerHTML = 'Sender atau session ID pada server MPWA/Baileys Anda.';
    if (hintToken) hintToken.innerHTML = 'API Key / Bearer Token server MPWA Anda.';
  } else {
    // custom
    if (rowSess) rowSess.style.display = '';
    if (labelSess) labelSess.innerHTML = 'Session ID / Device ID (Opsional)';
    if (inputSess) inputSess.placeholder = 'Opsional';
    if (hintSess) hintSess.innerHTML = 'Diisi jika endpoint custom Anda memerlukan parameter session_id / device_id.';
    if (hintToken) hintToken.innerHTML = 'API Key / Token untuk otentikasi endpoint custom.';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  updateProviderUrl(false);
});
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  updateProviderUrl(false);
}
</script>
