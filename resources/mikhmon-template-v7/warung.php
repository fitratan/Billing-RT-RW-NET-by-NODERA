<?php
/**
 * PORTAL KASIR WARUNG (RESELLER VOUCHER) — MIKHMON by NODERA
 * Aplikasi Web PWA Kasir Warung, Kulakan Saldo & Cetak Thermal
 * 1:1 Identik dengan Shell & Visual Dashboard MIKHMON Native
 * panel.dgtlnetsolution.com
 */

if (!defined('IS_WARUNG_PAGE')) {
    define('IS_WARUNG_PAGE', true);
}
$isWarungPage = true;

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}

error_reporting(0);
ob_start("ob_gzhandler");

$url = $_SERVER['REQUEST_URI'];

include_once __DIR__ . '/include/license.php';
if (function_exists('mikhmon_is_expired') && mikhmon_is_expired()) {
    $expDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="<?= !empty($isFr) ? 'fr' : (!empty($isIndo) ? 'id' : 'en') ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= !empty($isFr) ? 'Service Expiré' : (!empty($isIndo) ? 'Masa Aktif Berakhir' : 'Service Expired') ?></title>
        <style>
            body { background-color: #0b0e14; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; box-sizing: border-box; }
            .card { background: #121720; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px; max-width: 440px; width: 100%; padding: 28px 24px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
            .icon-box { width: 64px; height: 64px; background: rgba(239, 68, 68, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #ef4444; font-size: 28px; }
            h2 { font-size: 18px; font-weight: 700; margin: 0 0 8px; color: #ffffff; }
            p { font-size: 13.5px; color: #94a3b8; line-height: 1.5; margin: 0 0 16px; }
            .badge { display: inline-block; background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-box">⚠️</div>
            <h2><?= !empty($isFr) ? 'Abonnement MIKHMON Expiré' : (!empty($isIndo) ? 'Masa Aktif MIKHMON Berakhir' : 'MIKHMON Subscription Expired') ?></h2>
            <p><?= !empty($isFr) ? 'Le module kiosque/revendeur ne peut pas être utilisé car l\'abonnement MIKHMON de cette instance a expiré.' : (!empty($isIndo) ? 'Modul kasir / reseller warung tidak dapat digunakan karena langganan MIKHMON pada instance ini telah berakhir.' : 'Kiosk / reseller module cannot be used because MIKHMON subscription has expired.') ?></p>
            <div class="badge"><?= !empty($isFr) ? 'Expiré le : ' . htmlspecialchars($expDateText) : (!empty($isIndo) ? 'Kedaluwarsa : ' . htmlspecialchars($expDateText) : 'Expired on : ' . htmlspecialchars($expDateText)) ?></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

include_once __DIR__ . '/include/warung_helper.php';
include_once __DIR__ . '/include/config.php';
include_once __DIR__ . '/include/readcfg.php';

// Sync language directly with Admin Mikhmon selection
include_once __DIR__ . '/lang/isocodelang.php';
if (!empty($_GET['setlang']) && !empty($isocodelang[$_GET['setlang']])) {
    $_SESSION['lang'] = $_GET['setlang'];
    @setcookie('mikhmon_lang', $_GET['setlang'], time() + 31536000, '/');
    $langid = $_GET['setlang'];
    @file_put_contents(__DIR__ . '/include/lang.php', '<?php $langid="' . $_GET['setlang'] . '";?>');
    $cleanUri = preg_replace('/([?&])setlang=[^&]+(&|$)/', '$1', $_SERVER['REQUEST_URI']);
    $cleanUri = rtrim(rtrim($cleanUri, '&'), '?');
    header('Location: ' . $cleanUri);
    exit;
} else {
    $langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
    if (empty($langid) || empty($isocodelang[$langid])) {
        if (file_exists(__DIR__ . '/include/lang.php')) {
            include __DIR__ . '/include/lang.php';
        }
        if (empty($langid) || empty($isocodelang[$langid])) {
            $langid = 'id';
        }
    }
}
$_SESSION['lang'] = $langid;
if (!file_exists(__DIR__ . '/lang/' . $langid . '.php')) {
    $langid = 'id';
}
include __DIR__ . '/lang/' . $langid . '.php';

// Theme handling khusus Warung (Isolasi total dari Admin Mikhmon)
$mtheme = ["dark", "light", "blue", "green", "pink"];
$theme_color = [
    "dark" => "#3a4149",
    "light" => "#008BC9",
    "blue" => "#008BC9",
    "green" => "#4dbd74",
    "pink" => "#e83e8c",
];

// Handle local theme switch di warung (simpan di sesi/cookie warung, jangan sentuh admin)
if (!empty($_GET['set-theme'])) {
    $reqTheme = strtolower(trim($_GET['set-theme']));
    if (in_array($reqTheme, $mtheme)) {
        $_SESSION['m_warung_theme'] = $reqTheme;
        $_SESSION['m_warung_themecolor'] = $theme_color[$reqTheme] ?? '#3a4149';
        @setcookie('m_warung_theme', $reqTheme, time() + (86400 * 365), '/');
    }
    // Clean redirect tanpa query set-theme
    $cleanUri = preg_replace('/([?&])set-theme=[^&]+(&|$)/', '$1', $_SERVER['REQUEST_URI']);
    $cleanUri = rtrim(rtrim($cleanUri, '&'), '?');
    header('Location: ' . $cleanUri);
    exit;
}

$theme = $_SESSION['m_warung_theme'] ?? $_COOKIE['m_warung_theme'] ?? 'dark';
if (!in_array($theme, $mtheme)) {
    $theme = 'dark';
}
$themecolor = $theme_color[$theme] ?? '#3a4149';

$subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(__DIR__);
$session = $_GET['session'] ?? '';

// Check active router session
if (empty($session) && !empty($data)) {
    foreach (array_keys($data) as $sk) {
        if ($sk !== 'mikhmon' && !empty($sk)) {
            $session = $sk;
            break;
        }
    }
}

$hotspotname = 'Hotspot Internet';
$dnsname = 'hotspot.local';
if (!empty($session) && isset($data[$session])) {
    $rawHs = $data[$session][4] ?? 'Hotspot Internet';
    if (strpos($rawHs, '%') !== false) {
        $parts = explode('%', $rawHs);
        $hotspotname = !empty($parts[0]) ? $parts[0] : (!empty($parts[1]) ? $parts[1] : 'Hotspot Internet');
    } else {
        $hotspotname = $rawHs;
    }

    $rawDns = $data[$session][5] ?? 'hotspot.local';
    if (strpos($rawDns, '^') !== false) {
        $dParts = explode('^', $rawDns);
        $dnsname = !empty($dParts[1]) ? $dParts[1] : (!empty($dParts[0]) ? $dParts[0] : 'hotspot.local');
    } else {
        $dnsname = $rawDns;
    }

    if ($hotspotname === $dnsname && !empty($session)) {
        $hotspotname = $session;
    }
}

// Check Fitur Warung add-on status
if (function_exists('mikhmon_is_desktop_mode') && mikhmon_is_desktop_mode()) {
    $isAddonActive = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : true;
} else {
    $addonStatus = warung_check_addon_status($subdomain);
    $isAddonActive = $addonStatus['addon']['is_subscribed'] ?? false;
}

// If Addon is inactive or expired, block access to portal
if (!$isAddonActive) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $_warung_service_inactive_desc ?? 'Layanan Fitur Warung belum aktif atau telah berakhir pada instansi ini. Hubungi administrator hotspot untuk aktivasi.'
        ]);
        exit;
    }

    include_once __DIR__ . '/include/headhtml.php';
    ?>
    <div class="login-box" style="padding-top: 25px; margin: 20px auto; max-width: 440px;">
      <div class="card">
        <div class="card-header text-center">
          <h3 style="color: #e74c3c;"><i class="fa fa-lock"></i> <?= $_warung_feature_not_active ?? 'Fitur Warung Belum Aktif'; ?></h3>
        </div>
        <div class="card-body text-center" style="padding: 24px 20px;">
          <div style="font-size: 48px; color: #e74c3c; margin-bottom: 12px;">
            <i class="fa fa-shopping-basket"></i>
          </div>
          <h4 style="margin: 0 0 8px; font-size: 17px; font-weight: bold;"><?= $_warung_service_not_active ?? 'Layanan Warung Belum Aktif'; ?></h4>
          <p class="text-secondary" style="font-size: 13px; line-height: 1.6; margin-bottom: 20px;">
            <?= $_warung_service_not_active_desc ?? 'Fitur Warung / Kasir Reseller Voucher belum diaktifkan atau masa aktif langganan bulanan telah berakhir pada server hotspot ini.'; ?>
          </p>
          <div style="background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3); border-radius: 4px; padding: 10px 14px; font-size: 12px; margin-bottom: 20px; text-align: left;">
            <i class="fa fa-info-circle text-danger"></i> <?= $_warung_contact_admin_activate ?? 'Silakan hubungi <b>Administrator Hotspot</b> Anda untuk mengaktifkan add-on Fitur Warung melalui menu MIKHMON Admin.'; ?>
          </div>
          <a href="./login.php" class="btn bg-primary btn-block" style="width: 100%; box-sizing: border-box; font-weight: bold;">
            <i class="fa fa-sign-in"></i> <?= $_login_as_admin ?? 'Masuk Sebagai Administrator'; ?>
          </a>
        </div>
      </div>
      <div class="text-center" style="margin-top: 15px; font-size: 11px; opacity: 0.7;">
        &copy; <?= date('Y'); ?> <?= htmlspecialchars($hotspotname); ?> &bull; Powered by NODERA
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Check logged in warung user
$currentWarung = warung_get_current_user();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$activeTab = $_GET['tab'] ?? 'kasir';

// Handle Logout
if ($action === 'logout') {
    warung_logout();
    header('Location: ./warung.php' . (!empty($session) ? '?session='.$session : ''));
    exit;
}

// Handle Login POST
$loginError = '';
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userOrPhone = trim($_POST['username_or_phone'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $remember = !empty($_POST['remember']);

    $res = warung_login($userOrPhone, $pin, $remember);
    if ($res['success']) {
        header('Location: ./warung.php?tab=kasir' . (!empty($session) ? '&session='.$session : ''));
        exit;
    } else {
        $loginError = $res['error'] ?? ($_warung_login_failed ?? 'Login warung gagal. Periksa username dan PIN.');
    }
}

// Handle AJAX Actions (Buy Voucher, Topup Status)
if (!empty($currentWarung) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'ajax_buy_voucher') {
        header('Content-Type: application/json');
        $profile = trim($_POST['profile'] ?? '');
        $buyerPhone = trim($_POST['buyer_phone'] ?? '');

        $buyRes = warung_process_voucher_purchase($currentWarung['id'], $profile, $buyerPhone, $session);
        echo json_encode($buyRes);
        exit;
    } elseif ($action === 'ajax_create_topup') {
        header('Content-Type: application/json');
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount < 5000) {
            echo json_encode(['success' => false, 'error' => 'Minimal top-up Rp 5.000']);
            exit;
        }

        // Generate dynamic QRIS topup via Mikhmon NODERA PAY gateway
        $npConfigFile = __DIR__ . '/include/noderapay_config.php';
        $npApiKey = '';
        if (file_exists($npConfigFile)) {
            $noderapay_data = [];
            @include $npConfigFile;
            $npCfg = $noderapay_data[$session] ?? (reset($noderapay_data) ?: []);
            $npApiKey = $npCfg['api_key'] ?? '';
        }

        $orderId = 'WRG-TP-' . $currentWarung['id'] . '-' . time();
        $apiUrl = 'https://gateway.dgtlnetsolution.com/api/v1/noderapay/create-qris';
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'api_key'       => $npApiKey,
            'order_id'      => $orderId,
            'amount'        => $amount,
            'customer_name' => $currentWarung['name'],
            'customer_phone'=> $currentWarung['phone'],
            'description'   => 'Top-up Saldo Warung: ' . $currentWarung['name']
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $resp = curl_exec($ch);
        $json = @json_decode($resp, true);
        curl_close($ch);

        if ($json && ($json['success'] ?? false)) {
            $data = warung_get_data();
            $data['topup_orders'][$orderId] = [
                'warung_id'   => $currentWarung['id'],
                'amount'      => $amount,
                'status'      => 'PENDING',
                'created_at'  => date('Y-m-d H:i:s')
            ];
            warung_save_data($data);

            echo json_encode([
                'success'     => true,
                'order_id'    => $orderId,
                'qr_string'   => $json['data']['qr_string'] ?? $json['data']['qris_content'] ?? '',
                'amount'      => $json['data']['amount'] ?? $amount,
                'total_bayar' => $json['data']['total_bayar'] ?? $json['data']['gross_amount'] ?? $amount
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error'   => $json['message'] ?? 'Payment Gateway sedang offline. Hubungi Admin untuk setor tunai manual.'
            ]);
        }
        exit;
    }
}

// Load Warung data & Profile pricing
$warungData = warung_get_data();
if ($currentWarung) {
    if (!empty($currentWarung['router_session'])) {
        $session = $currentWarung['router_session'];
    }
}
$warungSettings = warung_get_settings();
$stockMode = $warungSettings['stock_mode'] ?? 'auto';
$showStockColumn = ($stockMode === 'pool' || $stockMode === 'auto');

$priceMap = warung_get_available_profiles($session, $currentWarung);
if (empty($priceMap)) {
    $priceMap = (!empty($session) && isset($warungData['session_profile_prices'][$session]) && is_array($warungData['session_profile_prices'][$session]))
        ? $warungData['session_profile_prices'][$session]
        : ($warungData['profile_prices'] ?? []);
}

// Get recent transactions for this warung
$myTransactions = [];
$totalTransactionsCount = 0;
$totalMarginEarned = 0;
if ($currentWarung) {
    foreach (array_reverse($warungData['transactions'] ?? []) as $tx) {
        if (($tx['warung_id'] ?? '') === $currentWarung['id']) {
            if (empty($tx['validity']) || $tx['validity'] === 'Aktif' || $tx['validity'] === '-') {
                if (function_exists('mikhmon_infer_validity')) {
                    $tx['validity'] = mikhmon_infer_validity($tx['profile'] ?? '', '', false);
                }
            }
            $myTransactions[] = $tx;
            if (($tx['type'] ?? '') === 'voucher') {
                $totalTransactionsCount++;
                $totalMarginEarned += (float) ($tx['margin'] ?? 0);
            }
        }
    }
}

// Refresh current warung data for fresh balance
if ($currentWarung && isset($warungData['warungs'][$currentWarung['id']])) {
    $currentWarung = $warungData['warungs'][$currentWarung['id']];
}

// Resolve custom uploaded logo for this router session / warung
$warungLogo = 'img/logo.png';
$logoCandidates = [
    "./img/logo-" . ($session ?? '') . ".png",
    "./img/logo-" . ($session ?? '') . ".jpg",
    "./img/logo-" . ($session ?? '') . ".jpeg",
    "./img/logo-" . ($session ?? '') . ".webp",
    "./img/logo-" . strtolower($session ?? '') . ".png",
    "./img/logo-" . strtolower($session ?? '') . ".jpg",
    "./img/logo-" . strtoupper($session ?? '') . ".png",
    "./img/logo.png",
    "./img/logo-kemangi41.png",
    "./img/favicon.png",
];
foreach ($logoCandidates as $cand) {
    if (file_exists($cand) && filesize($cand) > 0) {
        $warungLogo = $cand . '?t=' . filemtime($cand);
        break;
    }
}

// Include Mikhmon native header
include_once __DIR__ . '/include/headhtml.php';
?>

<style>
/* Warung Responsive & Mobile-First Optimization */
@media (max-width: 800px) {
  #main .container {
    padding-left: 8px !important;
    padding-right: 8px !important;
    padding-bottom: 75px !important;
  }
  .warung-desktop-system-boxes {
    display: none !important;
  }
  .warung-metrics-grid {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 6px !important;
    margin-bottom: 12px !important;
  }
  .warung-metric-card {
    padding: 8px 4px !important;
    min-height: auto !important;
    height: auto !important;
    border-radius: 6px !important;
    text-align: center !important;
    box-sizing: border-box !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: center !important;
    margin-bottom: 0 !important;
  }
  .warung-metric-card h1 {
    font-size: 13.5px !important;
    margin: 2px 0 !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
  }
  .warung-metric-card .metric-label {
    font-size: 9.5px !important;
    opacity: 0.9 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.2px !important;
    white-space: nowrap !important;
  }
  table.warung-desktop-table,
  table.table.warung-desktop-table,
  .warung-desktop-table {
    display: none !important;
  }
  .warung-mobile-card-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
    padding: 8px !important;
  }
  .warung-pkg-card {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    background: rgba(128,128,128,0.06);
    border: 1px solid rgba(128,128,128,0.18);
    border-radius: 6px;
    padding: 10px 12px;
    gap: 8px;
    cursor: pointer;
    transition: background 0.15s;
  }
  .warung-pkg-card:active {
    background: rgba(128,128,128,0.15);
  }
  .warung-pkg-info {
    flex: 1;
    min-width: 0;
  }
  .warung-pkg-title {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .warung-pkg-price-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
  }
  .warung-pkg-sell-price {
    font-size: 15.5px;
    font-weight: 800;
    color: #22c55e;
  }
  .warung-pkg-cost-tag {
    font-size: 11px;
    opacity: 0.75;
  }
  .warung-pkg-margin-badge {
    font-size: 10px;
    padding: 1px 5px;
    border-radius: 3px;
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    font-weight: bold;
  }
  .warung-pkg-action {
    flex-shrink: 0;
  }
  .warung-pkg-action .btn {
    padding: 8px 14px !important;
    font-size: 13px !important;
    font-weight: bold !important;
    border-radius: 4px !important;
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
  }
  .warung-tx-mobile-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
    padding: 6px !important;
  }
  .warung-tx-item {
    display: block !important;
    background: rgba(128,128,128,0.06);
    border: 1px solid rgba(128,128,128,0.18);
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 11.5px;
  }
}

/* OTP PIN Boxes (4 Digits) */
.otp-pin-grid {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin: 8px 0 14px;
}
.otp-pin-box {
  width: 54px !important;
  height: 54px !important;
  font-size: 24px !important;
  font-weight: 800 !important;
  text-align: center !important;
  border-radius: 8px !important;
  border: 1.5px solid rgba(128,128,128,0.35) !important;
  background: rgba(128,128,128,0.08) !important;
  color: inherit !important;
  outline: none !important;
  transition: all 0.2s ease !important;
  padding: 0 !important;
  margin: 0 !important;
  box-sizing: border-box !important;
}
.otp-pin-box:focus {
  border-color: #008BC9 !important;
  box-shadow: 0 0 0 3px rgba(0, 139, 201, 0.25) !important;
  background: rgba(0, 139, 201, 0.05) !important;
}

/* Modern Transaction Cards */
.warung-tx-card {
  background: rgba(128,128,128,0.06);
  border: 1px solid rgba(128,128,128,0.18);
  border-radius: 7px;
  padding: 10px 12px;
  margin-bottom: 8px;
  transition: background 0.15s;
}
.warung-tx-card:hover {
  background: rgba(128,128,128,0.1);
}

@media (min-width: 801px) {
  .warung-desktop-system-boxes {
    display: flex !important;
  }
  .warung-mobile-card-list {
    display: none !important;
  }
  .warung-tx-mobile-list {
    display: none !important;
  }
  table.warung-desktop-table,
  table.table.warung-desktop-table,
  .warung-desktop-table {
    display: table !important;
  }
  .warung-metrics-grid {
    display: flex !important;
    flex-wrap: wrap !important;
  }
}
</style>

<?php if (!$currentWarung): ?>
  <!-- =========================================================================
       HALAMAN LOGIN WARUNG (1:1 DENGAN INCLUDE/LOGIN.PHP MIKHMON NATIVE)
       ========================================================================= -->
  <div class="login-box" style="padding-top: 15px; margin: 15px auto;">
    <div class="card">
      <div class="card-header text-center">
        <h3><?= $_warung_login_header ?? 'Login Warung'; ?></h3>
      </div>
      <div class="card-body">
        <div class="text-center" style="padding-top: 15px; padding-bottom: 6px;">
          <img src="<?= $warungLogo; ?>" alt="Logo" style="max-height: 95px; max-width: 220px; height: auto; width: auto; object-fit: contain; margin: 0 auto; display: inline-block;">
        </div>
        <div class="text-center">
          <span style="font-size: 22px; margin: 6px 0 2px; font-weight: bold; display: block; letter-spacing: 0.5px;"><?= $_warung_voucher ?? 'WARUNG VOUCHER'; ?></span>
          <div style="font-size: 11px; font-weight: 600; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
            <?= htmlspecialchars($hotspotname); ?>
          </div>
        </div>

        <div style="display: flex; justify-content: center; width: 100%; padding: 4px 0;">
        <form autocomplete="on" action="./warung.php<?= !empty($session) ? '?session='.$session : ''; ?>" method="post" id="warungLoginForm" style="width: 100%; max-width: 320px; box-sizing: border-box; text-align: left;" onsubmit="return validateOtpLogin();">
          <input type="hidden" name="action" value="login">

          <div style="margin-bottom: 12px; width: 100%;">
            <input style="width: 100%; height: 38px; font-size: 14.5px; box-sizing: border-box; padding: 8px 12px; margin: 0 !important;" class="form-control" type="text" name="username_or_phone" id="_username" placeholder="<?= $_warung_username_or_phone ?? 'Username / No. WhatsApp'; ?>" required="1" autofocus autocomplete="username">
          </div>

          <div style="margin-bottom: 14px; width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label style="font-size: 12px; font-weight: bold; margin: 0; opacity: 0.9;"><?= $_warung_pin_label ?? 'PIN Transaksi (4 Angka)'; ?></label>
              <button type="button" onclick="toggleOtpVisibility()" style="background: none; border: none; font-size: 11.5px; color: inherit; opacity: 0.75; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 0;" tabindex="-1">
                <i class="fa fa-eye" id="otpEyeIcon"></i> <span id="otpEyeText"><?= $_show ?? 'Lihat'; ?></span>
              </button>
            </div>
            <div class="otp-pin-grid">
              <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-pin-box" id="otp_1" data-index="1" autocomplete="off">
              <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-pin-box" id="otp_2" data-index="2" autocomplete="off">
              <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-pin-box" id="otp_3" data-index="3" autocomplete="off">
              <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-pin-box" id="otp_4" data-index="4" autocomplete="off">
            </div>
            <input type="hidden" name="pin" id="_combinedPin" value="" required>
          </div>

          <div class="mikhmon-rem-row" style="margin-bottom: 14px; text-align: left; display: block;">
            <label for="_remember" class="mikhmon-rem-label" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; font-size: 13.5px; margin: 0; font-weight: normal; color: inherit; text-align: left;">
              <input type="checkbox" name="remember" id="_remember" value="1" style="width: 17px !important; height: 17px !important; margin: 0 !important; cursor: pointer; flex-shrink: 0;" checked>
              <span style="line-height: 1; user-select: none;"><?= $_remember_me ?? 'Ingat saya'; ?></span>
            </label>
          </div>

          <?php if (!empty($loginError)): ?>
            <div style="margin-bottom: 12px; text-align: center; color: #e74c3c; font-size: 13px;">
              <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($loginError); ?>
            </div>
          <?php endif; ?>

          <div style="margin-bottom: 10px; width: 100%;">
            <input style="width: 100%; height: 38px; font-weight: bold; font-size: 15px; margin: 0 !important;" class="btn-login bg-primary pointer" type="submit" name="login" value="<?= $_login_warung_btn ?? 'Masuk ke Warung'; ?>">
          </div>

          <div style="margin-bottom: 6px; width: 100%;">
            <button type="button" id="btnInstallPwa" class="btn-login bg-secondary pointer btn-pwa-install" onclick="triggerPwaInstall()" style="width: 100%; height: 38px; margin: 0 !important; display: none;">
              <i class="fa fa-download" style="margin-right: 6px;"></i> <?= $_install_warung_app ?? 'Install Aplikasi Warung'; ?>
            </button>
          </div>
        </form>
        </div>

        <div class="text-center" style="margin-top: 15px; font-size: 11px; opacity: 0.7;">
          &copy; <?= date('Y'); ?> <?= htmlspecialchars($hotspotname); ?> &bull; Powered by NODERA
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- =========================================================================
       1:1 MIKHMON ADMIN SHELL LAYOUT (NAVBAR + SIDEBAR + MAIN CONTAINER)
       ========================================================================= -->

  <!-- Top Navbar 1:1 Mikhmon -->
  <div id="navbar" class="navbar">
    <div class="navbar-left">
      <a id="brand" class="text-center" href="./warung.php?tab=kasir<?= !empty($session) ? '&session='.$session : ''; ?>">MIKHMON</a>
      <a id="openNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
      <a id="closeNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
      <a id="cpage" class="navbar-left" href="javascript:void(0)">
        <?php
          if ($activeTab === 'topup') echo htmlspecialchars($_topup_balance ?? 'ISI SALDO KULAKAN');
          elseif ($activeTab === 'riwayat') echo htmlspecialchars($_sales_history ?? 'RIWAYAT PENJUALAN');
          elseif ($activeTab === 'akun') echo htmlspecialchars($_warung_settings ?? 'AKUN WARUNG');
          else echo htmlspecialchars($_warung_voucher ?? 'WARUNG VOUCHER');
        ?>
      </a>
    </div>

    <div class="navbar-right">
      <a id="logout" href="./warung.php?action=logout" title="<?= $_logout ?? 'Keluar'; ?>" onclick="return confirm('<?= $_confirm_logout_warung ?? 'Keluar dari Warung?'; ?>');">
        <i class="fa fa-sign-out mr-1"></i> <span class="logout-text"><?= $_logout ?? 'Keluar'; ?></span>
      </a>
      <select class="stheme ses text-right mr-t-10 pd-5" onchange="location = this.value;">
        <option value=""> <?= !empty($_choose_theme) ? $_choose_theme : (!empty($_theme) ? $_theme : 'Tema'); ?></option>
        <?php
          $mtheme = ["dark", "light", "blue", "green", "pink"];
          foreach ($mtheme as $tName) {
            $sel = ($theme === $tName) ? 'selected' : '';
            echo '<option value="./warung.php?tab='.$activeTab.'&set-theme='.$tName.(!empty($session)?'&session='.$session:'').'" '.$sel.'>'.ucfirst($tName).'</option>';
          }
        ?>
      </select>
      <select class="slang ses text-right mr-t-10 pd-5" onchange="location = this.value;">
        <option value=""> <?= !empty($_choose_language) ? $_choose_language : (!empty($language) ? $language : 'Bahasa'); ?></option>
        <?php foreach ($isocodelang as $code => $name): 
          $selLang = (isset($langid) && $langid == $code) ? 'selected' : '';
          echo '<option value="./warung.php?tab='.$activeTab.'&setlang=' . $code . (!empty($session)?'&session='.$session:'') . '" '.$selLang.'>'. $name . '</option>'; 
        endforeach; ?>
      </select>

      <!-- Mobile Kebab Trigger -->
      <a href="javascript:void(0)" id="topKebabBtn" class="top-kebab-btn" title="Menu"><i class="fa fa-ellipsis-v"></i></a>
    </div>
  </div>

  <!-- Mobile Kebab Dropdown Menu -->
  <div id="topKebabDropdown" class="top-kebab-dropdown" style="display:none;">
    <div class="kebab-item-header">
      <span><i class="fa fa-sliders"></i> <?= htmlspecialchars($currentWarung['name']); ?></span>
      <a href="javascript:void(0)" id="closeKebabBtn" class="close-kebab-btn">&times;</a>
    </div>

    <div class="kebab-section">
      <label><i class="fa fa-paint-brush"></i> <?= !empty($_choose_theme) ? $_choose_theme : (!empty($_theme) ? $_theme : 'Tema Tampilan'); ?></label>
      <select class="form-control stheme-mobile" onchange="location = this.value;">
        <option value="">-- <?= !empty($_choose_theme) ? $_choose_theme : 'Pilih Tema'; ?> --</option>
        <?php foreach ($mtheme as $tName): ?>
          <option value="./warung.php?tab=<?= $activeTab; ?>&set-theme=<?= $tName; ?><?= !empty($session) ? '&session='.$session : ''; ?>" <?= ($theme === $tName) ? 'selected' : ''; ?>>
            <?= ucfirst($tName); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="kebab-section">
      <label><i class="fa fa-language"></i> <?= !empty($_choose_language) ? $_choose_language : (!empty($language) ? $language : 'Bahasa'); ?></label>
      <select class="form-control slang-mobile" onchange="location = this.value;">
        <option value="">-- <?= !empty($_choose_language) ? $_choose_language : 'Pilih Bahasa'; ?> --</option>
        <?php foreach ($isocodelang as $code => $name): 
          $selLang = (isset($langid) && $langid == $code) ? 'selected' : '';
          echo '<option value="./warung.php?tab=' . $activeTab . '&setlang=' . $code . (!empty($session)?'&session='.$session:'') . '" ' . $selLang . '>' . $name . '</option>'; 
        endforeach; ?>
      </select>
    </div>

    <div class="kebab-divider"></div>

    <a href="./warung.php?action=logout" class="kebab-logout-btn" onclick="return confirm('<?= $_confirm_logout_warung ?? 'Keluar dari Warung?'; ?>');">
      <i class="fa fa-sign-out"></i> <?= !empty($_logout_warung) ? $_logout_warung : (!empty($_logout) ? $_logout : 'Keluar Warung'); ?>
    </a>
  </div>

  <!-- Sidenav Sidebar 1:1 Mikhmon -->
  <div id="sidenav" class="sidenav">
    <div class="menu text-center align-middle card-header" style="border-radius:0;">
      <h3><?= htmlspecialchars($currentWarung['name']); ?></h3>
    </div>

    <a href="./warung.php?tab=kasir<?= !empty($session) ? '&session='.$session : ''; ?>" class="menu <?= $activeTab === 'kasir' ? 'active' : ''; ?>">
      <i class="fa fa-tachometer"></i> <span><?= $_dashboard ?? 'Dashboard'; ?></span>
    </a>

    <a href="./warung.php?tab=kasir<?= !empty($session) ? '&session='.$session : ''; ?>" class="menu">
      <i class="fa fa-ticket"></i> <span><?= $_hotspot_vouchers ?? 'Voucher Hotspot'; ?></span>
    </a>

    <a href="./warung.php?tab=topup<?= !empty($session) ? '&session='.$session : ''; ?>" class="menu <?= $activeTab === 'topup' ? 'active' : ''; ?>">
      <i class="fa fa-plus-circle"></i> <span><?= $_topup_balance ?? 'Isi Saldo Kulakan'; ?></span>
    </a>

    <a href="./warung.php?tab=riwayat<?= !empty($session) ? '&session='.$session : ''; ?>" class="menu <?= $activeTab === 'riwayat' ? 'active' : ''; ?>">
      <i class="fa fa-history"></i> <span><?= $_sales_history ?? 'Riwayat Penjualan'; ?></span>
    </a>

    <a href="./warung.php?tab=akun<?= !empty($session) ? '&session='.$session : ''; ?>" class="menu <?= $activeTab === 'akun' ? 'active' : ''; ?>">
      <i class="fa fa-user"></i> <span><?= $_warung_settings ?? 'Akun Warung'; ?></span>
    </a>

    <a href="javascript:void(0)" onclick="triggerPwaInstall()" class="menu" id="sidePwaBtn">
      <i class="fa fa-download"></i> <span>Install PWA</span>
    </a>

    <div class="menu spa"></div>

    <a href="./warung.php?action=logout" class="menu" onclick="return confirm('<?= $_confirm_logout_warung ?? 'Keluar dari Warung?'; ?>');">
      <i class="fa fa-sign-out"></i> <span><?= $_logout ?? 'Keluar'; ?></span>
    </a>
  </div>

  <!-- Main Container -->
  <div id="main" class="main">
    <div class="container">

      <!-- =======================================================================
           TAB 1: KASIR VOUCHER (1:1 DENGAN DASHBOARD HOME.PHP MIKHMON)
           ======================================================================= -->
      <?php if ($activeTab === 'kasir'): ?>
        <!-- Top Row 1: System Info Box Group 1:1 Mikhmon (Desktop Only) -->
        <div id="r_1" class="row warung-desktop-system-boxes">
          <div class="col-4 col-box-12">
            <div class="box bmh-75 box-bordered">
              <div class="box-group">
                <div class="box-group-icon"><i class="fa fa-shopping-basket"></i></div>
                <div class="box-group-area">
                  <span>
                    <b><?= $_warung ?? 'Warung'; ?>:</b> <?= htmlspecialchars($currentWarung['name']); ?><br>
                    <b><?= $_owner ?? 'Pemilik'; ?>:</b> <?= htmlspecialchars($currentWarung['owner'] ?: '-'); ?><br>
                    <b><?= $_whatsapp_number ?? 'No. WA'; ?>:</b> <?= htmlspecialchars($currentWarung['phone'] ?: '-'); ?>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="col-4 col-box-12">
            <div class="box bmh-75 box-bordered">
              <div class="box-group">
                <div class="box-group-icon"><i class="fa fa-wifi"></i></div>
                <div class="box-group-area">
                  <span>
                    <b>Hotspot:</b> <?= htmlspecialchars($hotspotname); ?><br>
                    <b>Domain:</b> <?= htmlspecialchars($dnsname); ?><br>
                    <b><?= $_status ?? 'Status'; ?>:</b> <span class="text-green"><i class="fa fa-circle"></i> <?= $_online ?? 'Online'; ?></span>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="col-4 col-box-12">
            <div class="box bmh-75 box-bordered">
              <div class="box-group">
                <div class="box-group-icon"><i class="fa fa-clock-o"></i></div>
                <div class="box-group-area">
                  <span>
                    <b><?= $_time ?? 'Waktu'; ?>:</b> <?= date('H:i'); ?><br>
                    <b><?= $_date ?? 'Tanggal'; ?>:</b> <?= date('d M Y'); ?><br>
                    <b><?= $_status ?? 'Status'; ?>:</b> <span class="text-green"><i class="fa fa-check-circle"></i> <?= $_warung_active ?? 'Warung Aktif'; ?></span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Middle Row: 3 Action/Metric Widgets (Desktop Row & Mobile 3-Columns Grid) -->
        <div class="row warung-metrics-grid">
          <div class="col-4 col-box-4" style="width: 100%;">
            <div class="box bg-green bmh-75 warung-metric-card">
              <a href="./warung.php?tab=topup<?= !empty($session) ? '&session='.$session : ''; ?>" style="color: inherit; text-decoration: none; display: block; width: 100%;">
                <h1 id="lblCurrentBalance" style="font-size: 15px; margin: 3px 0 2px 0;">Rp <?= number_format((float)($currentWarung['balance'] ?? 0), 0, ',', '.'); ?></h1>
                <div class="metric-label" style="font-size: 11px;"><i class="fa fa-plus-circle"></i> <?= $_wholesale_balance ?? 'Saldo Kulakan'; ?></div>
              </a>
            </div>
          </div>
          <div class="col-4 col-box-4" style="width: 100%;">
            <div class="box bg-blue bmh-75 warung-metric-card">
              <a href="./warung.php?tab=riwayat<?= !empty($session) ? '&session='.$session : ''; ?>" style="color: inherit; text-decoration: none; display: block; width: 100%;">
                <h1 style="font-size: 15px; margin: 3px 0 2px 0;"><?= $totalTransactionsCount; ?> <span style="font-size: 11px; font-weight: normal;">vcr</span></h1>
                <div class="metric-label" style="font-size: 11px;"><i class="fa fa-ticket"></i> <?= $_sold_count ?? 'Terjual'; ?></div>
              </a>
            </div>
          </div>
          <div class="col-4 col-box-4" style="width: 100%;">
            <div class="box bg-teal bmh-75 warung-metric-card">
              <a href="./warung.php?tab=riwayat<?= !empty($session) ? '&session='.$session : ''; ?>" style="color: inherit; text-decoration: none; display: block; width: 100%;">
                <h1 style="font-size: 15px; margin: 3px 0 2px 0;">Rp <?= number_format($totalMarginEarned, 0, ',', '.'); ?></h1>
                <div class="metric-label" style="font-size: 11px;"><i class="fa fa-line-chart"></i> <?= $_profit_earned ?? 'Keuntungan'; ?></div>
              </a>
            </div>
          </div>
        </div>

        <!-- Bottom Row: 2 Kolom (Kiri 8 Kolom: Katalog Paket, Kanan 4 Kolom: Transaksi Terakhir) -->
        <div class="row">
          <!-- Kolom Kiri: Katalog Paket Voucher Hotspot -->
          <div class="col-8 col-box-12">
            <div class="card">
              <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <h3><i class="fa fa-ticket"></i> <?= $_available_voucher_packages ?? 'Daftar Paket Voucher Tersedia'; ?></h3>
                <a href="./warung.php?tab=kasir<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-primary btn-sm" title="<?= $_refresh ?? 'Refresh'; ?>">
                  <i class="fa fa-refresh"></i> <?= $_refresh ?? 'Refresh'; ?>
                </a>
              </div>

              <div class="card-body" style="padding: 0;">
                <?php if (empty($priceMap)): ?>
                  <div style="padding: 40px 20px; text-align: center;">
                    <i class="fa fa-ticket" style="font-size: 42px; opacity: 0.35; margin-bottom: 12px; display: block;"></i>
                    <div style="font-size: 15px; font-weight: bold; margin-bottom: 6px;"><?= $_no_vouchers_detected ?? 'Belum Ada Profil Voucher Terdeteksi'; ?></div>
                    <div style="font-size: 12px; opacity: 0.7; max-width: 420px; margin: 0 auto 16px auto; line-height: 1.5;">
                      <?= $_no_vouchers_detected_hint ?? 'Pastikan profil user hotspot telah dibuat di MikroTik router, atau klik tombol di bawah untuk membaca ulang profil dari router.'; ?>
                    </div>
                    <a href="./warung.php?tab=kasir<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-primary btn-sm">
                      <i class="fa fa-refresh"></i> <?= $_refresh_mikrotik_profiles ?? 'Refresh Profil MikroTik'; ?>
                    </a>
                  </div>
                <?php else: ?>
                  <!-- 1. Desktop Table View -->
                  <table class="table table-bordered table-hover table-sm warung-desktop-table" style="margin-bottom: 0;">
                    <thead>
                      <tr>
                        <th><?= $_package_hotspot ?? 'Paket Hotspot'; ?></th>
                        <?php if ($showStockColumn): ?>
                          <th style="width: 110px;"><?= $_stock ?? 'Stok'; ?></th>
                        <?php endif; ?>
                        <th><?= $_wholesale_cost ?? 'Modal Kulakan'; ?></th>
                        <th><?= $_selling_price ?? 'Harga Jual'; ?></th>
                        <th><?= $_profit_earned ?? 'Keuntungan'; ?></th>
                        <th class="text-center" style="width: 100px;"><?= $_action ?? 'Aksi'; ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($priceMap as $pName => $pData): ?>
                        <?php
                          $sellPrice = (float) ($pData['sell_price'] ?? 0);
                          $costPrice = (float) ($pData['cost_price'] ?? 0);
                          if ($costPrice <= 0 && $sellPrice > 0) $costPrice = floor($sellPrice * 0.9);
                          if ($sellPrice <= 0 && $costPrice > 0) $sellPrice = floor($costPrice / 0.9);
                          if ($costPrice <= 0 && $sellPrice <= 0) { $sellPrice = 5000; $costPrice = 4500; }
                          $margin = $sellPrice - $costPrice;
                          $stock = intval($pData['stock'] ?? 0);
                          $isOutOfStock = ($stockMode === 'pool' && $stock <= 0);
                        ?>
                        <tr style="<?= $isOutOfStock ? 'opacity: 0.6;' : 'cursor: pointer;'; ?>" <?= !$isOutOfStock ? 'onclick="openBuyDialog(\''.htmlspecialchars(addslashes($pName)).'\', '.$sellPrice.', '.$costPrice.')"' : ''; ?>>
                          <td class="align-middle" style="padding-left: 12px;">
                            <b><?= htmlspecialchars($pName); ?></b><br>
                            <small class="text-secondary"><i class="fa fa-wifi"></i> <?= htmlspecialchars($hotspotname); ?></small>
                          </td>
                          <?php if ($showStockColumn): ?>
                            <td class="align-middle">
                              <?php if ($stock > 0): ?>
                                <span class="badge bg-primary" style="padding: 3px 6px; font-size: 11px;"><?= sprintf($_available_stock ?? '%s Tersedia', $stock); ?></span>
                              <?php else: ?>
                                <span class="badge bg-danger" style="padding: 3px 6px; font-size: 11px;"><?= $_out_of_stock ?? '0 (Habis)'; ?></span>
                              <?php endif; ?>
                            </td>
                          <?php endif; ?>
                          <td class="align-middle">
                            <b>Rp <?= number_format($costPrice, 0, ',', '.'); ?></b>
                          </td>
                          <td class="align-middle">
                            <b class="text-green">Rp <?= number_format($sellPrice, 0, ',', '.'); ?></b>
                          </td>
                          <td class="align-middle">
                            <b class="text-primary">+Rp <?= number_format($margin, 0, ',', '.'); ?></b>
                          </td>
                          <td class="text-center align-middle" style="white-space: nowrap;">
                            <?php if ($isOutOfStock): ?>
                              <button type="button" class="btn bg-secondary btn-sm" disabled title="<?= $_out_of_stock_tooltip ?? 'Stok Habis'; ?>">
                                <?= $_out_of_stock_btn ?? 'Habis'; ?>
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn bg-primary btn-sm" onclick="event.stopPropagation(); openBuyDialog('<?= htmlspecialchars(addslashes($pName)); ?>', <?= $sellPrice; ?>, <?= $costPrice; ?>)" title="<?= $_buy_voucher_tooltip ?? 'Beli &amp; Terbitkan Voucher'; ?>">
                                <i class="fa fa-shopping-cart"></i> <?= $_buy ?? 'Beli'; ?>
                              </button>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <!-- 2. Mobile Cards View (Thumb-Friendly, No Horizontal Clipping) -->
                  <div class="warung-mobile-card-list">
                    <?php foreach ($priceMap as $pName => $pData): ?>
                      <?php
                        $sellPrice = (float) ($pData['sell_price'] ?? 0);
                        $costPrice = (float) ($pData['cost_price'] ?? 0);
                        if ($costPrice <= 0 && $sellPrice > 0) $costPrice = floor($sellPrice * 0.9);
                        if ($sellPrice <= 0 && $costPrice > 0) $sellPrice = floor($costPrice / 0.9);
                        if ($costPrice <= 0 && $sellPrice <= 0) { $sellPrice = 5000; $costPrice = 4500; }
                        $margin = $sellPrice - $costPrice;
                        $stock = intval($pData['stock'] ?? 0);
                        $isOutOfStock = ($stockMode === 'pool' && $stock <= 0);
                      ?>
                      <div class="warung-pkg-card" style="<?= $isOutOfStock ? 'opacity: 0.6;' : ''; ?>" <?= !$isOutOfStock ? 'onclick="openBuyDialog(\''.htmlspecialchars(addslashes($pName)).'\', '.$sellPrice.', '.$costPrice.')"' : ''; ?>>
                        <div class="warung-pkg-info">
                          <div class="warung-pkg-title">
                            <span><?= htmlspecialchars($pName); ?></span>
                            <?php if ($showStockColumn): ?>
                              <?php if ($stock > 0): ?>
                                <span class="badge bg-primary" style="padding: 1px 5px; font-size: 10px; font-weight: normal;"><?= $stock; ?> <?= $_stock ?? 'Stok'; ?></span>
                              <?php else: ?>
                                <span class="badge bg-danger" style="padding: 1px 5px; font-size: 10px; font-weight: normal;"><?= $_out_of_stock_btn ?? 'Habis'; ?></span>
                              <?php endif; ?>
                            <?php endif; ?>
                          </div>
                          <div class="warung-pkg-price-row">
                            <span class="warung-pkg-sell-price">Rp <?= number_format($sellPrice, 0, ',', '.'); ?></span>
                            <span class="warung-pkg-cost-tag"><?= $_modal ?? 'Modal'; ?>: Rp <?= number_format($costPrice, 0, ',', '.'); ?></span>
                            <span class="warung-pkg-margin-badge">+Rp <?= number_format($margin, 0, ',', '.'); ?></span>
                          </div>
                        </div>
                        <div class="warung-pkg-action">
                          <?php if ($isOutOfStock): ?>
                            <button type="button" class="btn bg-secondary btn-sm" disabled style="padding: 8px 12px; font-size: 12px;"><?= $_out_of_stock_btn ?? 'Habis'; ?></button>
                          <?php else: ?>
                            <button type="button" class="btn bg-primary btn-sm" onclick="event.stopPropagation(); openBuyDialog('<?= htmlspecialchars(addslashes($pName)); ?>', <?= $sellPrice; ?>, <?= $costPrice; ?>)">
                              <i class="fa fa-shopping-cart"></i> <?= $_buy ?? 'Beli'; ?>
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Kolom Kanan: Log Transaksi Terakhir (1:1 Log Mikhmon Dashboard) -->
          <div class="col-4 col-box-12">
            <div class="card">
              <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <h3><i class="fa fa-history"></i> <?= $_recent_transactions ?? 'Transaksi Terakhir'; ?></h3>
                <a href="./warung.php?tab=riwayat<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-secondary btn-sm" style="font-size: 11px;">
                  <?= $_see_all ?? 'Semua'; ?> &rarr;
                </a>
              </div>
              <div class="card-body" style="padding: 0; max-height: 480px; overflow-y: auto;">
                <?php if (empty($myTransactions)): ?>
                  <div style="padding: 40px 20px; text-align: center;">
                    <i class="fa fa-history" style="font-size: 42px; opacity: 0.35; margin-bottom: 12px; display: block;"></i>
                    <div style="font-size: 14px; font-weight: bold; margin-bottom: 4px;"><?= $_no_transactions_yet ?? 'Belum Ada Transaksi'; ?></div>
                    <div style="font-size: 12px; opacity: 0.7;"><?= $_no_transactions_desc ?? 'Voucher yang Anda terbitkan akan tercatat di sini.'; ?></div>
                  </div>
                <?php else: ?>
                  <!-- Desktop Table View -->
                  <table class="table table-bordered table-striped table-hover table-sm warung-desktop-table" style="margin-bottom: 0;">
                    <thead>
                      <tr>
                        <th><?= $_time_package ?? 'Waktu / Paket'; ?></th>
                        <th><?= $_code ?? 'Kode'; ?></th>
                        <th class="text-center" style="width: 50px;"><?= $_action ?? 'Aksi'; ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach (array_slice($myTransactions, 0, 10) as $tx): ?>
                        <tr>
                          <td class="align-middle" style="font-size: 11px; padding-left: 10px;">
                            <b><?= htmlspecialchars($tx['profile'] ?? '-'); ?></b><br>
                            <small class="text-secondary"><?= htmlspecialchars($tx['created_at'] ?? ''); ?></small>
                          </td>
                          <td class="align-middle" style="font-size: 11px;">
                            <code><?= htmlspecialchars($tx['username'] ?? '-'); ?></code><br>
                            <b class="text-green">Rp <?= number_format((float)($tx['sell_price'] ?? 0), 0, ',', '.'); ?></b>
                          </td>
                          <td class="text-center align-middle">
                            <button type="button" class="btn bg-secondary btn-sm" style="padding: 2px 6px;" onclick="reprintReceipt(<?= htmlspecialchars(json_encode($tx)); ?>)" title="<?= $_print_receipt_btn ?? 'Cetak Struk'; ?>">
                              <i class="fa fa-print"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <!-- Mobile Item List View -->
                  <div class="warung-tx-mobile-list" style="padding: 6px;">
                    <?php foreach (array_slice($myTransactions, 0, 5) as $tx): ?>
                      <div class="warung-tx-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                          <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="badge bg-primary" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; font-weight: 600;">
                              <i class="fa fa-ticket"></i> <?= htmlspecialchars($tx['profile'] ?? '-'); ?>
                            </span>
                            <span style="font-size: 10.5px; opacity: 0.7;">
                              <?= date('d/m H:i', strtotime($tx['created_at'] ?? 'now')); ?>
                            </span>
                          </div>
                          <div style="font-size: 14px; font-weight: 800; color: #22c55e;">
                            Rp <?= number_format((float)($tx['sell_price'] ?? 0), 0, ',', '.'); ?>
                          </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.12); border-radius: 6px; padding: 6px 10px; margin-bottom: 8px;">
                          <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 10px; opacity: 0.7; text-transform: uppercase;"><?= $_code ?? 'Kode'; ?>:</span>
                            <code style="font-size: 13px; font-weight: bold; letter-spacing: 0.5px; color: #38bdf8; background: transparent; padding: 0;">
                              <?= htmlspecialchars($tx['username'] ?? '-'); ?>
                            </code>
                            <button type="button" onclick="copyVoucherCode('<?= htmlspecialchars($tx['username'] ?? ''); ?>', this)" style="background: none; border: none; padding: 2px 4px; color: inherit; opacity: 0.7; cursor: pointer;" title="<?= $_copy_code ?? 'Salin Kode'; ?>">
                              <i class="fa fa-copy"></i>
                            </button>
                          </div>
                          <div style="font-size: 10.5px; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.12); padding: 2px 7px; border-radius: 4px;">
                            +Rp <?= number_format((float)($tx['margin'] ?? 0), 0, ',', '.'); ?> <?= $_profit_label ?? 'Untung'; ?>
                          </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                          <div style="opacity: 0.8; display: flex; align-items: center; gap: 4px;">
                            <?php if (!empty($tx['buyer_phone'])): ?>
                              <i class="fa fa-whatsapp text-green"></i> <?= htmlspecialchars($tx['buyer_phone']); ?>
                            <?php else: ?>
                              <span style="opacity: 0.6;"><i class="fa fa-user-o"></i> <?= $_direct_customer ?? 'Konsumen Langsung'; ?></span>
                            <?php endif; ?>
                          </div>
                          <div style="display: flex; gap: 6px;">
                            <?php if (!empty($tx['buyer_phone'])): ?>
                              <button type="button" class="btn bg-success btn-sm" style="padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick="sendTxWhatsApp(<?= htmlspecialchars(json_encode($tx)); ?>)" title="<?= $_resend_wa_tooltip ?? 'Kirim Ulang WhatsApp'; ?>">
                                <i class="fa fa-whatsapp"></i> WA
                              </button>
                            <?php endif; ?>
                            <button type="button" class="btn bg-secondary btn-sm" style="padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick="reprintReceipt(<?= htmlspecialchars(json_encode($tx)); ?>)" title="<?= $_print_receipt_btn ?? 'Cetak Struk'; ?>">
                              <i class="fa fa-print"></i> <?= $_receipt ?? 'Struk'; ?>
                            </button>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      <!-- =======================================================================
           TAB 2: ISI SALDO KULAKAN (QRIS OTOMATIS)
           ======================================================================= -->
      <?php elseif ($activeTab === 'topup'): ?>
        <div class="row">
          <div class="col-8 col-box-12">
            <div class="card">
              <div class="card-header">
                <h3><i class="fa fa-plus-circle"></i> <?= $_topup_qris_title ?? 'Isi Saldo Kulakan via QRIS Otomatis'; ?></h3>
              </div>
              <div class="card-body">
                <p style="margin-top: 0; margin-bottom: 15px; opacity: 0.85;">
                  <?= $_topup_qris_desc ?? 'Pilih atau masukkan nominal top-up saldo kulakan. Saldo akan otomatis bertambah secara realtime (0-detik) setelah scan QRIS berhasil dibayar.'; ?>
                </p>

                <table class="table table-sm">
                  <tr>
                    <td class="align-middle" style="width: 30%;"><?= $_quick_choices ?? 'Pilihan Cepat'; ?></td>
                    <td>
                      <div class="btn-group" style="display: flex; flex-wrap: wrap; gap: 4px;">
                        <button type="button" class="btn bg-secondary" onclick="requestTopup(20000)">Rp 20.000</button>
                        <button type="button" class="btn bg-secondary" onclick="requestTopup(50000)">Rp 50.000</button>
                        <button type="button" class="btn bg-secondary" onclick="requestTopup(100000)">Rp 100.000</button>
                        <button type="button" class="btn bg-secondary" onclick="requestTopup(200000)">Rp 200.000</button>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td class="align-middle"><?= $_custom_amount ?? 'Nominal Bebas (Rp)'; ?></td>
                    <td>
                      <div class="input-group">
                        <input type="number" id="inputCustomTopup" class="form-control" placeholder="<?= $_custom_topup_placeholder ?? 'Contoh: 150000'; ?>" min="5000" step="5000">
                        <span class="input-group-btn">
                          <button type="button" class="btn bg-primary" onclick="requestCustomTopup()"><i class="fa fa-qrcode"></i> <?= $_pay_qris_btn ?? 'Bayar QRIS'; ?></button>
                        </span>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>

          <div class="col-4 col-box-12">
            <div class="card">
              <div class="card-header">
                <h3><i class="fa fa-info-circle"></i> <?= $_remaining_balance ?? 'Sisa Saldo'; ?></h3>
              </div>
              <div class="card-body text-center">
                <small class="text-secondary" style="text-transform: uppercase; font-size: 11px;"><?= $_warung_deposit_balance ?? 'Sisa Saldo Deposit Warung'; ?></small>
                <div style="font-size: 24px; font-weight: bold; margin: 8px 0;" class="text-green">
                  Rp <?= number_format((float)($currentWarung['balance'] ?? 0), 0, ',', '.'); ?>
                </div>
                <small class="text-secondary"><?= $_account ?? 'Akun'; ?>: <b><?= htmlspecialchars($currentWarung['name']); ?></b></small>
                <hr style="margin: 15px 0; border: 0; border-top: 1px solid rgba(128,128,128,0.2);">
                <p style="font-size: 12px; opacity: 0.85; line-height: 1.6; text-align: left; margin-bottom: 0;">
                  <?= $_cash_topup_hint ?? 'Jika ingin mengisi saldo secara tunai tanpa QRIS, Anda dapat menyerahkan uang tunai langsung kepada Admin ISP untuk di-topup manual.'; ?>
                </p>
              </div>
            </div>
          </div>
        </div>

      <!-- =======================================================================
           TAB 3: RIWAYAT PENJUALAN
           ======================================================================= -->
      <?php elseif ($activeTab === 'riwayat'): ?>
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3><i class="fa fa-history"></i> <?= $_sales_history ?? 'Riwayat Penjualan Voucher'; ?></h3>
              </div>
              <div class="card-body">
                <?php if (empty($myTransactions)): ?>
                  <div style="padding: 30px; text-align: center; opacity: 0.7;">
                    <i class="fa fa-history" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                    <?= $_no_vouchers_issued_yet ?? 'Belum ada riwayat voucher yang diterbitkan.'; ?>
                  </div>
                <?php else: ?>
                  <!-- Desktop Table View -->
                  <table class="table table-bordered table-hover table-sm warung-desktop-table">
                    <thead>
                      <tr>
                        <th><?= $_time ?? 'Waktu'; ?></th>
                        <th><?= $_package_hotspot ?? 'Paket Hotspot'; ?></th>
                        <th><?= $_voucher_code ?? 'Kode Voucher'; ?></th>
                        <th><?= $_sell_cost_price ?? 'Harga Jual / Modal'; ?></th>
                        <th><?= $_profit_earned ?? 'Keuntungan'; ?></th>
                        <th><?= $_buyer_phone ?? 'No. WA Pembeli'; ?></th>
                        <th class="text-center" style="width: 100px;"><?= $_action ?? 'Aksi'; ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($myTransactions as $tx): ?>
                        <tr>
                          <td style="font-size: 11px;" class="text-secondary align-middle"><?= htmlspecialchars($tx['created_at']); ?></td>
                          <td class="align-middle"><b><?= htmlspecialchars($tx['profile'] ?? '-'); ?></b></td>
                          <td class="align-middle"><code><?= htmlspecialchars($tx['username'] ?? '-'); ?></code></td>
                          <td class="align-middle">
                            <span class="text-green">Rp <?= number_format((float)($tx['sell_price'] ?? 0), 0, ',', '.'); ?></span> /
                            <b>Rp <?= number_format((float)($tx['cost_price'] ?? 0), 0, ',', '.'); ?></b>
                          </td>
                          <td class="align-middle">
                            <b class="text-primary">+Rp <?= number_format((float)($tx['margin'] ?? 0), 0, ',', '.'); ?></b>
                          </td>
                          <td style="font-size: 12px;" class="align-middle"><?= htmlspecialchars($tx['buyer_phone'] ?: '-'); ?></td>
                          <td class="text-center align-middle">
                            <button type="button" class="btn bg-secondary btn-sm" onclick="reprintReceipt(<?= htmlspecialchars(json_encode($tx)); ?>)" title="<?= $_print_receipt_btn ?? 'Cetak Struk'; ?>">
                              <i class="fa fa-print"></i> <?= $_print ?? 'Cetak'; ?>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <!-- Mobile Item List View -->
                  <div class="warung-tx-mobile-list" style="padding: 6px;">
                    <?php foreach ($myTransactions as $tx): ?>
                      <div class="warung-tx-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                          <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="badge bg-primary" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; font-weight: 600;">
                              <i class="fa fa-ticket"></i> <?= htmlspecialchars($tx['profile'] ?? '-'); ?>
                            </span>
                            <span style="font-size: 10.5px; opacity: 0.7;">
                              <?= date('d/m H:i', strtotime($tx['created_at'] ?? 'now')); ?>
                            </span>
                          </div>
                          <div style="font-size: 14px; font-weight: 800; color: #22c55e;">
                            Rp <?= number_format((float)($tx['sell_price'] ?? 0), 0, ',', '.'); ?>
                          </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.12); border-radius: 6px; padding: 6px 10px; margin-bottom: 8px;">
                          <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 10px; opacity: 0.7; text-transform: uppercase;"><?= $_code ?? 'Kode'; ?>:</span>
                            <code style="font-size: 13px; font-weight: bold; letter-spacing: 0.5px; color: #38bdf8; background: transparent; padding: 0;">
                              <?= htmlspecialchars($tx['username'] ?? '-'); ?>
                            </code>
                            <button type="button" onclick="copyVoucherCode('<?= htmlspecialchars($tx['username'] ?? ''); ?>', this)" style="background: none; border: none; padding: 2px 4px; color: inherit; opacity: 0.7; cursor: pointer;" title="<?= $_copy_code ?? 'Salin Kode'; ?>">
                              <i class="fa fa-copy"></i>
                            </button>
                          </div>
                          <div style="font-size: 10.5px; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.12); padding: 2px 7px; border-radius: 4px;">
                            +Rp <?= number_format((float)($tx['margin'] ?? 0), 0, ',', '.'); ?> <?= $_profit_label ?? 'Untung'; ?>
                          </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                          <div style="opacity: 0.8; display: flex; align-items: center; gap: 4px;">
                            <?php if (!empty($tx['buyer_phone'])): ?>
                              <i class="fa fa-whatsapp text-green"></i> <?= htmlspecialchars($tx['buyer_phone']); ?>
                            <?php else: ?>
                              <span style="opacity: 0.6;"><i class="fa fa-user-o"></i> <?= $_direct_customer ?? 'Konsumen Langsung'; ?></span>
                            <?php endif; ?>
                          </div>
                          <div style="display: flex; gap: 6px;">
                            <?php if (!empty($tx['buyer_phone'])): ?>
                              <button type="button" class="btn bg-success btn-sm" style="padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick="sendTxWhatsApp(<?= htmlspecialchars(json_encode($tx)); ?>)" title="<?= $_resend_wa_tooltip ?? 'Kirim Ulang WhatsApp'; ?>">
                                <i class="fa fa-whatsapp"></i> WA
                              </button>
                            <?php endif; ?>
                            <button type="button" class="btn bg-secondary btn-sm" style="padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick="reprintReceipt(<?= htmlspecialchars(json_encode($tx)); ?>)" title="<?= $_print_receipt_btn ?? 'Cetak Struk'; ?>">
                              <i class="fa fa-print"></i> <?= $_receipt ?? 'Struk'; ?>
                            </button>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      <!-- =======================================================================
           TAB 4: AKUN WARUNG
           ======================================================================= -->
      <?php elseif ($activeTab === 'akun'): ?>
        <div class="row">
          <div class="col-8 col-box-12">
            <div class="card">
              <div class="card-header">
                <h3><i class="fa fa-user"></i> <?= $_account_info_title ?? 'Informasi Akun Warung'; ?></h3>
              </div>
              <div class="card-body">
                <table class="table table-sm">
                  <tr>
                    <td class="align-middle" style="width: 35%;"><?= $_warung_name_store ?? 'Nama Warung / Toko'; ?></td>
                    <td><b><?= htmlspecialchars($currentWarung['name']); ?></b></td>
                  </tr>
                  <tr>
                    <td class="align-middle"><?= $_owner_name ?? 'Nama Pemilik / Pengelola'; ?></td>
                    <td><?= htmlspecialchars($currentWarung['owner'] ?: '-'); ?></td>
                  </tr>
                  <tr>
                    <td class="align-middle"><?= $_whatsapp_number ?? 'No. WhatsApp Pemilik'; ?></td>
                    <td><span class="text-green"><i class="fa fa-whatsapp"></i> <?= htmlspecialchars($currentWarung['phone'] ?: '-'); ?></span></td>
                  </tr>
                  <tr>
                    <td class="align-middle"><?= $_warung_login_username ?? 'Username Login Warung'; ?></td>
                    <td><code><?= htmlspecialchars($currentWarung['username'] ?? ''); ?></code></td>
                  </tr>
                  <tr>
                    <td class="align-middle"><?= $_warung_partner_id ?? 'ID Mitra Warung'; ?></td>
                    <td><code><?= htmlspecialchars($currentWarung['id']); ?></code></td>
                  </tr>
                  <tr>
                    <td class="align-middle"><?= $_remaining_wholesale_balance ?? 'Sisa Saldo Kulakan'; ?></td>
                    <td><b class="text-green" style="font-size: 16px;">Rp <?= number_format((float)($currentWarung['balance'] ?? 0), 0, ',', '.'); ?></b></td>
                  </tr>
                </table>

                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(128,128,128,0.2);">
                  <a href="./warung.php?action=logout" class="btn bg-danger" onclick="return confirm('<?= addslashes($_confirm_logout_warung ?? 'Keluar dari Warung?'); ?>');">
                    <i class="fa fa-sign-out"></i> <?= $_logout_warung ?? 'Keluar dari Warung'; ?>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-4 col-box-12">
            <div class="card">
              <div class="card-header">
                <h3><i class="fa fa-mobile"></i> <?= $_pwa_app_title ?? 'Aplikasi PWA Warung'; ?></h3>
              </div>
              <div class="card-body text-center">
                <p style="font-size: 12px; opacity: 0.85; line-height: 1.6; margin-top: 0;">
                  <?= $_pwa_app_desc ?? 'Pasang aplikasi warung di layar utama smartphone Anda agar bisa membuka warung secara instan tanpa mengetik URL lagi.'; ?>
                </p>
                <button type="button" class="btn bg-success btn-block" onclick="triggerPwaInstall()">
                  <i class="fa fa-download"></i> Install Aplikasi Warung
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- NATIVE MIKHMON MOBILE BOTTOM NAVIGATION (4 BUTTONS) -->
  <div id="mikhmonBottomNav" class="navbar mikhmon-bottom-nav">
    <a href="./warung.php?tab=kasir<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $activeTab === 'kasir' ? 'active' : ''; ?>">
      <i class="fa fa-ticket"></i>
      <span><?= $_bottom_nav_voucher ?? 'Voucher'; ?></span>
    </a>
    <a href="./warung.php?tab=topup<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $activeTab === 'topup' ? 'active' : ''; ?>">
      <i class="fa fa-plus-circle"></i>
      <span><?= $_bottom_nav_topup ?? 'Isi Saldo'; ?></span>
    </a>
    <a href="./warung.php?tab=riwayat<?= !empty($session) ? '&session='.$session : ''; ?>" class="<?= $activeTab === 'riwayat' ? 'active' : ''; ?>">
      <i class="fa fa-history"></i>
      <span><?= $_bottom_nav_history ?? 'Riwayat'; ?></span>
    </a>
    <a href="javascript:void(0)" id="bottomNavToggleMenu">
      <i class="fa fa-bars"></i>
      <span><?= $_bottom_nav_menu ?? 'Menu'; ?></span>
    </a>
  </div>

  <!-- DIALOG: BELI VOUCHER (100% MIKHMON NATIVE CARD) -->
  <div id="dialogBuy" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
    <div class="card" style="width: 100%; max-width: 440px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.4); border-radius: 4px; overflow: hidden;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 15px;"><i class="fa fa-shopping-cart"></i> <?= $_checkout_voucher_package ?? 'Checkout Paket Voucher'; ?></h3>
        <a href="javascript:void(0)" onclick="closeWarungModal('dialogBuy')" class="text-secondary" style="font-size: 20px; text-decoration: none; line-height: 1;">&times;</a>
      </div>
      <div class="card-body" style="padding: 15px;">
        <table class="table table-bordered table-sm" style="margin-bottom: 12px;">
          <tbody>
            <tr>
              <td style="width: 40%; font-weight: bold;"><?= $_warung_voucher_profile ?? 'Paket Voucher'; ?></td>
              <td><b id="buyProfileName">-</b></td>
            </tr>
            <tr>
              <td style="font-weight: bold;"><?= $_selling_price ?? 'Harga Jual'; ?></td>
              <td><b class="text-green" id="buySellPrice" style="font-size: 15px;">Rp 0</b></td>
            </tr>
            <tr>
              <td style="font-weight: bold;"><?= $_wholesale_cost ?? 'Harga Modal'; ?></td>
              <td><b id="buyCostPrice" style="font-weight: 600;">Rp 0</b></td>
            </tr>
            <tr>
              <td style="font-weight: bold;"><?= $_profit_earned ?? 'Keuntungan'; ?></td>
              <td><b class="text-primary" id="buyMarginPrice">+Rp 0</b></td>
            </tr>
            <tr>
              <td style="font-weight: bold; vertical-align: middle;"><?= $_whatsapp_number ?? 'No. WhatsApp'; ?></td>
              <td>
                <input type="tel" id="buyBuyerPhone" class="form-control" placeholder="<?= $_buyer_phone_placeholder ?? '081234567890 (Opsional)'; ?>" style="width: 100%; box-sizing: border-box; font-size: 14px;">
                <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 3px;"><?= $_auto_send_voucher_hint ?? 'Kirim otomatis rincian voucher ke pembeli.'; ?></small>
              </td>
            </tr>
          </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 15px; flex-wrap: wrap;">
          <button type="button" class="btn bg-secondary" onclick="closeWarungModal('dialogBuy')">
            <i class="fa fa-times"></i> <?= $_cancel ?? 'Batal'; ?>
          </button>
          <button type="button" class="btn bg-primary" id="btnSubmitBuy" onclick="submitBuyVoucher()">
            <i class="fa fa-shopping-cart"></i> <?= $_buy_voucher_btn ?? 'Beli Voucher'; ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- DIALOG: STRUK THERMAL & PRINT (100% MIKHMON NATIVE CARD) -->
  <div id="dialogReceipt" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 12px; box-sizing: border-box;">
    <div class="card" style="width: 100%; max-width: 360px; max-height: 92vh; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border-radius: 4px; display: flex; flex-direction: column; overflow: hidden;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; flex-shrink: 0;">
        <h3 style="margin: 0; font-size: 14px; color: #16a34a;"><i class="fa fa-check-circle"></i> <?= $_voucher_issued_success ?? 'Voucher Berhasil Terbit'; ?></h3>
        <a href="javascript:void(0)" onclick="closeWarungModal('dialogReceipt')" class="text-secondary" style="font-size: 20px; text-decoration: none; line-height: 1;">&times;</a>
      </div>
      <div class="card-body" style="padding: 12px; overflow-y: auto; max-height: calc(92vh - 46px);">
        <div class="thermal-receipt" id="printArea" style="background: #ffffff; color: #111827; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 4px; text-align: center; font-family: 'Courier New', Courier, monospace; margin: 0 auto 10px auto; max-width: 270px; box-sizing: border-box;">
          <?php if (!empty($warungLogo) && file_exists(explode('?', $warungLogo)[0])): ?>
            <div style="text-align: center; margin-bottom: 4px;">
              <img src="<?= $warungLogo; ?>" alt="Logo" style="max-height: 38px; max-width: 130px; height: auto; object-fit: contain; margin: 0 auto; display: inline-block;">
            </div>
          <?php endif; ?>
          <div style="font-weight: bold; font-size: 13px;"><?= htmlspecialchars($hotspotname); ?></div>
          <div style="font-size: 10px; color: #4b5563;"><?= htmlspecialchars($dnsname); ?></div>
          <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

          <div style="font-size: 11px; margin-bottom: 2px;"><?= $_package ?? 'Paket'; ?>: <b id="rcProfile">-</b></div>
          <div style="font-size: 11px; margin-bottom: 3px;"><?= $_validity ?? 'Masa Aktif'; ?>: <b id="rcValidity">-</b></div>
          <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;"><?= $_price ?? 'Harga'; ?>: <span id="rcPrice">Rp 0</span></div>

          <div style="border: 2px solid #000; padding: 5px; margin: 6px 0; background: #f8fafc;">
            <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px;"><?= $_voucher_code ?? 'Kode Voucher'; ?>:</div>
            <div style="font-size: 18px; font-weight: 900; letter-spacing: 2px; color: #000; line-height: 1.2;" id="rcCode">------</div>
            <div style="font-size: 8.5px; opacity: 0.8; margin-top: 2px;"><?= $_same_user_pass ?? 'Username &amp; Password sama'; ?></div>
          </div>

          <div style="margin-top: 4px;">
            <img id="rcQrImg" src="" alt="QR Login" style="width: 75px; height: 75px; margin: 0 auto; display: block;">
          </div>

          <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
          <div style="font-size: 10px;"><?= $_warung ?? 'Warung'; ?>: <b id="rcWarung"><?= htmlspecialchars($currentWarung['name'] ?? ''); ?></b></div>
          <div style="font-size: 9px; margin-top: 2px; color: #6b7280;"><?= $_thank_you_purchase ?? 'Terima kasih atas pembelian Anda'; ?></div>
        </div>

        <div id="rcWaStatus" style="display: none; background: rgba(34, 197, 94, 0.15); color: #16a34a; padding: 5px 8px; border-radius: 4px; font-size: 10.5px; text-align: center; margin-bottom: 8px;">
          <i class="fa fa-check"></i> <?= $_voucher_sent_wa_success ?? 'Pesan voucher terkirim ke WhatsApp pembeli!'; ?>
        </div>

        <div style="display: flex; gap: 6px; width: 100%; justify-content: center; align-items: center; box-sizing: border-box;">
          <button type="button" class="btn bg-primary" onclick="window.print()" title="<?= $_print_receipt_btn ?? 'Cetak Struk Thermal'; ?>" style="flex: 1; padding: 7px 6px; font-size: 12px; font-weight: bold; white-space: nowrap; text-align: center;">
            <i class="fa fa-print"></i> <?= $_print ?? 'Cetak'; ?>
          </button>
          <button type="button" class="btn bg-success" onclick="sendReceiptWhatsapp()" title="<?= $_send_to_whatsapp ?? 'Kirim ke WhatsApp'; ?>" style="flex: 1; padding: 7px 6px; font-size: 12px; font-weight: bold; white-space: nowrap; text-align: center;">
            <i class="fa fa-whatsapp"></i> WhatsApp
          </button>
          <button type="button" class="btn bg-secondary" onclick="copyReceiptCode()" title="<?= $_copy_voucher_code ?? 'Salin Kode Voucher'; ?>" style="padding: 7px 11px; font-size: 12px;" aria-label="Salin">
            <i class="fa fa-copy"></i>
          </button>
          <button type="button" class="btn bg-secondary" onclick="closeWarungModal('dialogReceipt')" title="<?= $_new_transaction ?? 'Transaksi Baru'; ?>" style="padding: 7px 11px; font-size: 12px;" aria-label="Tutup">
            <i class="fa fa-plus"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- DIALOG: QRIS TOPUP SALDO (100% MIKHMON NATIVE CARD) -->
  <div id="dialogTopupQris" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
    <div class="card" style="width: 100%; max-width: 380px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.4); border-radius: 4px; overflow: hidden; text-align: center;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 15px;"><i class="fa fa-qrcode"></i> <?= $_scan_qris_topup ?? 'Scan QRIS Top-Up'; ?></h3>
        <a href="javascript:void(0)" onclick="closeWarungModal('dialogTopupQris')" class="text-secondary" style="font-size: 20px; text-decoration: none; line-height: 1;">&times;</a>
      </div>
      <div class="card-body" style="padding: 15px;">
        <div style="font-size: 12px; margin-bottom: 2px;" class="text-secondary"><?= $_total_payment ?? 'Total Pembayaran'; ?>:</div>
        <div style="font-size: 22px; font-weight: bold;" class="text-green" id="qrisGrossAmount">Rp 0</div>

        <div style="background: #ffffff; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; display: inline-block; margin: 12px 0;">
          <img id="qrisImage" src="" alt="QRIS Topup" style="width: 180px; height: 180px; display: block;">
        </div>

        <div style="font-size: 12px; margin-bottom: 15px;" class="text-secondary" id="qrisStatusNotice">
          <i class="fa fa-spinner fa-spin"></i> <?= $_waiting_qris_payment ?? 'Menunggu pembayaran otomatis...'; ?>
        </div>

        <button type="button" class="btn bg-secondary btn-block" style="width: 100%; box-sizing: border-box;" onclick="closeWarungModal('dialogTopupQris')">
          <i class="fa fa-times"></i> <?= $_close ?? 'Tutup'; ?>
        </button>
      </div>
    </div>
  </div>

<?php endif; ?>

<!-- Core Mikhmon JS -->
<script src="js/mikhmon-ui.<?= $theme; ?>.min.js?v=3.20.2"></script>
<script src="js/mikhmon.js?v=3.20.2"></script>

<script>
var currentSelectedProfile = '';
var currentSell = 0;
var currentCost = 0;
var currentCode = '';
var topupPollTimer = null;

var lastTransactionData = null;

function sendReceiptWhatsapp() {
  var lang = '<?= $langid ?? "id"; ?>';
  var promptMap = {
    'id': 'Masukkan No. WhatsApp Tujuan (contoh: 081234567890):',
    'en': 'Enter Customer WhatsApp Number (e.g., 6281234567890):',
    'fr': 'Entrez le numéro WhatsApp du client (ex: 2250700000000):',
    'es': 'Ingrese el número de WhatsApp del cliente:',
    'tl': 'Ilagay ang WhatsApp Number ng Customer:'
  };
  var phonePrompt = promptMap[lang] || promptMap['id'];

  var phone = (lastTransactionData && lastTransactionData.buyer_phone) ? lastTransactionData.buyer_phone : '';
  if (!phone) {
    phone = prompt(phonePrompt, '');
    if (!phone) return;
    if (lastTransactionData) {
      lastTransactionData.buyer_phone = phone;
    }
  }

  var code = $('#rcCode').text();
  var prof = $('#rcProfile').text();
  var val = $('#rcValidity').text();
  var price = $('#rcPrice').text();
  var warungName = '<?= htmlspecialchars(addslashes($currentWarung['name'] ?? '')); ?>';
  var hsName = '<?= htmlspecialchars(addslashes($hotspotname)); ?>';

  var cleanPhone = phone.replace(/[^0-9]/g, '');
  if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.slice(1);
  if (cleanPhone.startsWith('8')) cleanPhone = '62' + cleanPhone;

  var receiptTemplates = {
    'id': '*PEMBELIAN VOUCHER HOTSPOT*\n-----------------------------------\n*Lokasi:* ' + hsName + '\n*Paket:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Masa Aktif:* ' + val + '\n') : '') + '*Harga:* ' + price + '\n\n*Kode Voucher:*\n*' + code + '*\n*(Username & Password sama)*\n\n*Warung:* ' + warungName + '\n-----------------------------------\n_Terima kasih atas pembelian Anda._',
    'en': '*HOTSPOT VOUCHER PURCHASE*\n-----------------------------------\n*Location:* ' + hsName + '\n*Package:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Validity:* ' + val + '\n') : '') + '*Price:* ' + price + '\n\n*Voucher Code:*\n*' + code + '*\n*(Username & Password are the same)*\n\n*Reseller:* ' + warungName + '\n-----------------------------------\n_Thank you for your purchase._',
    'fr': '*ACHAT DE COUPON HOTSPOT*\n-----------------------------------\n*Emplacement :* ' + hsName + '\n*Forfait :* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Validité :* ' + val + '\n') : '') + '*Prix :* ' + price + '\n\n*Code Coupon :*\n*' + code + '*\n*(Nom d\'utilisateur et mot de passe identiques)*\n\n*Point de Vente :* ' + warungName + '\n-----------------------------------\n_Merci pour votre achat._',
    'es': '*COMPRA DE CUPÓN HOTSPOT*\n-----------------------------------\n*Ubicación:* ' + hsName + '\n*Paquete:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Validez:* ' + val + '\n') : '') + '*Precio:* ' + price + '\n\n*Código de Cupón:*\n*' + code + '*\n*(Usuario y contraseña iguales)*\n\n*Punto de Venta:* ' + warungName + '\n-----------------------------------\n_Gracias por su compra._',
    'tl': '*PAGBILI NG HOTSPOT VOUCHER*\n-----------------------------------\n*Lokasyon:* ' + hsName + '\n*Package:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Bisa:* ' + val + '\n') : '') + '*Presyo:* ' + price + '\n\n*Voucher Code:*\n*' + code + '*\n*(Pareho ang Username at Password)*\n\n*Tindahan:* ' + warungName + '\n-----------------------------------\n_Salamat sa iyong pagbili._'
  };

  var text = receiptTemplates[lang] || receiptTemplates['id'];
  window.open('https://api.whatsapp.com/send?phone=' + cleanPhone + '&text=' + encodeURIComponent(text), '_blank');
}

function inferValidityText(profile, validity) {
  if (validity && validity !== 'Aktif' && validity !== '-' && validity !== 'Standar' && validity !== 'Masa Aktif Standar') {
    return validity;
  }
  if (!profile) return '1 Hari';
  var p = profile.toLowerCase();
  var m = p.match(/(\d+)\s*[-_]?\s*(menit|min|jam|hours?|hrs?|heures?|hari|days?|jours?|minggu|weeks?|semaines?|bulan|months?|mois|d\b|h\b|m\b)/i);
  if (m) {
    var num = m[1];
    var unit = m[2].toLowerCase();
    if (unit === 'd' || unit === 'hari' || unit === 'day' || unit === 'days' || unit === 'jour' || unit === 'jours') return num + ' Hari';
    if (unit === 'h' || unit === 'jam' || unit === 'hour' || unit === 'hours' || unit === 'hr' || unit === 'hrs' || unit === 'heure' || unit === 'heures') return num + ' Jam';
    if (unit === 'm' || unit === 'menit' || unit === 'min') return num + ' Menit';
    if (unit === 'minggu' || unit === 'week' || unit === 'weeks' || unit === 'semaine' || unit === 'semaines') return num + ' Minggu';
    if (unit === 'bulan' || unit === 'month' || unit === 'months' || unit === 'mois') return num + ' Bulan';
    return num + ' ' + unit;
  }
  if (p.indexOf('hari') !== -1 || p.indexOf('day') !== -1) return '24 Jam';
  if (p.indexOf('bulan') !== -1 || p.indexOf('month') !== -1) return '30 Hari';
  if (p.indexOf('minggu') !== -1 || p.indexOf('week') !== -1) return '7 Hari';
  if (p.indexOf('jam') !== -1 || p.indexOf('hour') !== -1) return '1 Jam';
  return '1 Hari';
}

function openBuyDialog(profile, sell, cost) {
  currentSelectedProfile = profile;
  currentSell = sell;
  currentCost = cost;
  var margin = sell - cost;
  $('#buyProfileName').text(profile);
  $('#buySellPrice').text('Rp ' + Number(sell).toLocaleString('id-ID'));
  $('#buyCostPrice').text('Rp ' + Number(cost).toLocaleString('id-ID'));
  $('#buyMarginPrice').text((margin >= 0 ? '+' : '') + 'Rp ' + Number(margin).toLocaleString('id-ID'));
  $('#buyBuyerPhone').val('');
  $('#dialogBuy').css('display', 'flex');
  setTimeout(function() {
    $('#buyBuyerPhone').focus();
  }, 100);
}

function submitBuyVoucher() {
  var btn = $('#btnSubmitBuy');
  var origHtml = btn.html();
  btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + (<?= json_encode($_processing ?? 'Memproses...'); ?>));

  var phone = $('#buyBuyerPhone').val() || '';

  $.post('./warung.php?session=<?= urlencode($session); ?>', {
    action: 'ajax_buy_voucher',
    profile: currentSelectedProfile,
    buyer_phone: phone
  }, function(res) {
    btn.prop('disabled', false).html(origHtml);

    if (res && res.success) {
      closeWarungModal('dialogBuy');
      currentCode = res.username;
      lastTransactionData = res;
      if (!lastTransactionData.buyer_phone && phone) {
        lastTransactionData.buyer_phone = phone;
      }
      $('#lblCurrentBalance').text('Rp ' + Number(res.new_balance).toLocaleString('id-ID'));

      $('#rcProfile').text(res.profile);
      $('#rcValidity').text(inferValidityText(res.profile, res.validity));
      $('#rcPrice').text('Rp ' + Number(res.sell_price).toLocaleString('id-ID'));
      $('#rcCode').text(res.username);
      $('#rcQrImg').attr('src', 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent('http://' + (res.dns_name || '<?= htmlspecialchars($dnsname); ?>') + '/login?username=' + res.username + '&password=' + res.password));

      if (res.wa_sent) {
        $('#rcWaStatus').html('<i class="fa fa-check"></i> ' + (<?= json_encode($_voucher_sent_wa_success ?? 'Pesan rincian voucher otomatis terkirim ke WhatsApp pembeli!'); ?>)).show();
      } else {
        $('#rcWaStatus').hide();
      }

      $('#dialogReceipt').css('display', 'flex');
    } else {
      alert(res ? res.error : (<?= json_encode($_buy_voucher_failed ?? 'Gagal membeli voucher.'); ?>));
    }
  }).fail(function() {
    btn.prop('disabled', false).html(origHtml);
    alert(<?= json_encode($_network_error ?? 'Terjadi kesalahan jaringan.'); ?>);
  });
}

function copyReceiptCode() {
  if (currentCode) {
    navigator.clipboard.writeText(currentCode).then(function() {
      alert((<?= json_encode($_voucher_code_copied_prefix ?? 'Kode voucher '); ?>) + currentCode + (<?= json_encode($_voucher_code_copied_suffix ?? ' berhasil disalin!'); ?>));
    });
  }
}

function reprintReceipt(tx) {
  currentCode = tx.username;
  lastTransactionData = tx;
  $('#rcProfile').text(tx.profile);
  $('#rcValidity').text(inferValidityText(tx.profile, tx.validity));
  $('#rcPrice').text('Rp ' + Number(tx.sell_price || 0).toLocaleString('id-ID'));
  $('#rcCode').text(tx.username);
  $('#rcWarung').text(tx.warung_name || '');
  $('#rcQrImg').attr('src', 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent('http://<?= htmlspecialchars($dnsname); ?>/login?username=' + tx.username + '&password=' + tx.password));
  $('#rcWaStatus').hide();
  $('#dialogReceipt').css('display', 'flex');
}

function requestTopup(amount) {
  showTopupQris(amount);
}

function requestCustomTopup() {
  var amt = parseFloat($('#inputCustomTopup').val()) || 0;
  if (amt < 5000) {
    alert(<?= json_encode($_min_topup_alert ?? 'Minimal top-up adalah Rp 5.000'); ?>);
    return;
  }
  showTopupQris(amt);
}

function showTopupQris(amount) {
  if (topupPollTimer) clearInterval(topupPollTimer);

  $.post('./warung.php?session=<?= urlencode($session); ?>', {
    action: 'ajax_create_topup',
    amount: amount
  }, function(res) {
    if (res && res.success) {
      $('#qrisGrossAmount').text('Rp ' + Number(res.total_bayar || res.amount).toLocaleString('id-ID'));
      var qrData = res.qr_string;
      $('#qrisImage').attr('src', 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(qrData));
      $('#dialogTopupQris').css('display', 'flex');

      var orderId = res.order_id;
      topupPollTimer = setInterval(function() {
        $.get('https://gateway.dgtlnetsolution.com/api/v1/noderapay/check/' + encodeURIComponent(orderId), function(st) {
          if (st && st.status === 'PAID') {
            clearInterval(topupPollTimer);
            $('#qrisStatusNotice').html('<b class="text-green"><i class="fa fa-check-circle"></i> ' + (<?= json_encode($_payment_success_credited ?? 'Pembayaran Berhasil! Saldo telah masuk.'); ?>) + '</b>');
            setTimeout(function() {
              window.location.reload();
            }, 1200);
          }
        });
      }, 3000);
    } else {
      alert(res ? res.error : (<?= json_encode($_qris_invoice_failed ?? 'Gagal membuat invoice QRIS.'); ?>));
    }
  }).fail(function() {
    alert(<?= json_encode($_qris_network_error ?? 'Terjadi kesalahan jaringan saat membuat QRIS.'); ?>);
  });
}

function closeWarungModal(dialogId) {
  $('#' + dialogId).css('display', 'none');
  if (dialogId === 'dialogTopupQris' && topupPollTimer) {
    clearInterval(topupPollTimer);
  }
}

function copyVoucherCode(code, btn) {
  if (!code) return;
  navigator.clipboard.writeText(code).then(function() {
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check text-green"></i>';
    setTimeout(function() { btn.innerHTML = orig; }, 1500);
  });
}

function sendTxWhatsApp(tx) {
  if (!tx || !tx.buyer_phone) return;
  var lang = '<?= $langid ?? "id"; ?>';
  var phone = tx.buyer_phone.replace(/[^0-9]/g, '');
  if (phone.startsWith('0')) phone = '62' + phone.substring(1);
  if (phone.startsWith('8')) phone = '62' + phone;

  var code = tx.username || '-';
  var prof = tx.profile || '-';
  var val = tx.validity || '';
  var price = 'Rp ' + Number(tx.sell_price || 0).toLocaleString('id-ID');
  var warungName = '<?= htmlspecialchars(addslashes($currentWarung['name'] ?? '')); ?>';
  var hsName = '<?= htmlspecialchars(addslashes($hotspotname)); ?>';

  var receiptTemplates = {
    'id': '*PEMBELIAN VOUCHER HOTSPOT*\n-----------------------------------\n*Lokasi:* ' + hsName + '\n*Paket:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Masa Aktif:* ' + val + '\n') : '') + '*Harga:* ' + price + '\n\n*Kode Voucher:*\n*' + code + '*\n*(Username & Password sama)*\n\n*Warung:* ' + warungName + '\n-----------------------------------\n_Terima kasih atas pembelian Anda._',
    'en': '*HOTSPOT VOUCHER PURCHASE*\n-----------------------------------\n*Location:* ' + hsName + '\n*Package:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Validity:* ' + val + '\n') : '') + '*Price:* ' + price + '\n\n*Voucher Code:*\n*' + code + '*\n*(Username & Password are the same)*\n\n*Reseller:* ' + warungName + '\n-----------------------------------\n_Thank you for your purchase._',
    'fr': '*ACHAT DE COUPON HOTSPOT*\n-----------------------------------\n*Emplacement :* ' + hsName + '\n*Forfait :* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Validité :* ' + val + '\n') : '') + '*Prix :* ' + price + '\n\n*Code Coupon :*\n*' + code + '*\n*(Nom d\'utilisateur et mot de passe identiques)*\n\n*Point de Vente :* ' + warungName + '\n-----------------------------------\n_Merci pour votre achat._',
    'es': '*COMPRA DE CUPÓN HOTSPOT*\n-----------------------------------\n*Ubicación:* ' + hsName + '\n*Paquete:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Validez:* ' + val + '\n') : '') + '*Precio:* ' + price + '\n\n*Código de Cupón:*\n*' + code + '*\n*(Usuario y contraseña iguales)*\n\n*Punto de Venta:* ' + warungName + '\n-----------------------------------\n_Gracias por su compra._',
    'tl': '*PAGBILI NG HOTSPOT VOUCHER*\n-----------------------------------\n*Lokasyon:* ' + hsName + '\n*Package:* ' + prof + '\n' + (val && val !== '-' && val !== 'Aktif' ? ('*Bisa:* ' + val + '\n') : '') + '*Presyo:* ' + price + '\n\n*Voucher Code:*\n*' + code + '*\n*(Pareho ang Username at Password)*\n\n*Tindahan:* ' + warungName + '\n-----------------------------------\n_Salamat sa iyong pagbili._'
  };

  var text = receiptTemplates[lang] || receiptTemplates['id'];
  window.open('https://api.whatsapp.com/send?phone=' + phone + '&text=' + encodeURIComponent(text), '_blank');
}

// 4-Box OTP PIN Logic
var otpInputs = ['otp_1', 'otp_2', 'otp_3', 'otp_4'];
function initOtpBoxes() {
  otpInputs.forEach(function(id, idx) {
    var el = document.getElementById(id);
    if (!el) return;

    el.addEventListener('input', function(e) {
      var val = this.value.replace(/[^0-9]/g, '');
      this.value = val ? val.slice(-1) : '';
      if (this.value && idx < 3) {
        var nextEl = document.getElementById(otpInputs[idx + 1]);
        if (nextEl) nextEl.focus();
      }
      syncOtpValue();
    });

    el.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && !this.value && idx > 0) {
        var prevEl = document.getElementById(otpInputs[idx - 1]);
        if (prevEl) {
          prevEl.focus();
          prevEl.value = '';
        }
        syncOtpValue();
      }
    });

    el.addEventListener('paste', function(e) {
      e.preventDefault();
      var pasteData = (e.clipboardData || window.clipboardData).getData('text');
      var digits = pasteData.replace(/[^0-9]/g, '').slice(0, 4);
      for (var i = 0; i < 4; i++) {
        var box = document.getElementById(otpInputs[i]);
        if (box) {
          box.value = digits[i] || '';
        }
      }
      if (digits.length >= 4) {
        var last = document.getElementById(otpInputs[3]);
        if (last) last.focus();
      }
      syncOtpValue();
    });
  });
}

function syncOtpValue() {
  var pin = '';
  otpInputs.forEach(function(id) {
    var el = document.getElementById(id);
    if (el) pin += el.value;
  });
  var combined = document.getElementById('_combinedPin');
  if (combined) combined.value = pin;
  return pin;
}

function validateOtpLogin() {
  var pin = syncOtpValue();
  if (pin.length !== 4) {
    alert(<?= json_encode($_enter_4digit_pin_alert ?? 'Harap masukkan 4 digit angka PIN transaksi.'); ?>);
    for (var i = 0; i < 4; i++) {
      var el = document.getElementById(otpInputs[i]);
      if (el && !el.value) {
        el.focus();
        break;
      }
    }
    return false;
  }
  return true;
}

var isOtpVisible = false;
function toggleOtpVisibility() {
  isOtpVisible = !isOtpVisible;
  otpInputs.forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
      el.type = isOtpVisible ? 'tel' : 'password';
    }
  });
  var ico = document.getElementById('otpEyeIcon');
  var txt = document.getElementById('otpEyeText');
  if (ico) ico.className = isOtpVisible ? 'fa fa-eye-slash' : 'fa fa-eye';
  if (txt) txt.innerText = isOtpVisible ? (<?= json_encode($_hide ?? 'Sembunyikan'); ?>) : (<?= json_encode($_show ?? 'Lihat'); ?>);
}

$(document).ready(function() {
  initOtpBoxes();
});

// Mobile Kebab Logic
$(document).ready(function() {
  $('#topKebabBtn').on('click', function(e) {
    e.stopPropagation();
    $('#topKebabDropdown').toggle();
  });
  $('#closeKebabBtn').on('click', function(e) {
    e.stopPropagation();
    $('#topKebabDropdown').hide();
  });
  $(document).on('click', function(e) {
    if (!$(e.target).closest('#topKebabDropdown, #topKebabBtn').length) {
      $('#topKebabDropdown').hide();
    }
  });
});

// PWA Install Logic
window.deferredPwaPrompt = null;
window.addEventListener('beforeinstallprompt', function(e) {
  e.preventDefault();
  window.deferredPwaPrompt = e;
  var btn = document.getElementById('btnInstallPwa');
  if (btn) {
    btn.style.display = 'block';
  }
});

window.addEventListener('appinstalled', function() {
  window.deferredPwaPrompt = null;
  var btn = document.getElementById('btnInstallPwa');
  if (btn) btn.style.display = 'none';
  console.log('Warung PWA installed successfully');
});

function triggerPwaInstall() {
  if (window.deferredPwaPrompt) {
    window.deferredPwaPrompt.prompt();
    window.deferredPwaPrompt.userChoice.then(function(choiceResult) {
      if (choiceResult.outcome === 'accepted') {
        console.log('User accepted Warung PWA install');
      }
      window.deferredPwaPrompt = null;
      var btn = document.getElementById('btnInstallPwa');
      if (btn) btn.style.display = 'none';
    });
  } else {
    alert(<?= json_encode($_pwa_install_guide ?? 'Panduan Install Aplikasi Warung PWA:

• Desktop (Chrome / Edge / Brave):
Klik ikon Install (+) di sebelah kanan kolom URL / Address Bar browser Anda, atau klik menu Titik Tiga (⋮) > "Install Warung Voucher".

• Mobile (Android / iOS):
Buka menu browser lalu pilih "Tambahkan ke Layar Utama" / "Add to Home Screen".'); ?>);
  }
}
</script>
</body>
</html>