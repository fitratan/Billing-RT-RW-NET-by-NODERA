<?php
/**
 * MIKHMON — Portal Beli Voucher Hotspot Online (QRIS & Auto WhatsApp)
 * by NODERA (nodera.id)
 * Versi: Indonesia (IDR / QRIS & E-Wallet)
 * 5 Model Tema Fisik Berbeda + Focused Desktop-Centric UI
 */

if (!defined('IS_BUY_PAGE')) {
    define('IS_BUY_PAGE', true);
}
$isBuyPage = true;

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}
error_reporting(0);

// 1. Path Resolution
$currentDir = __DIR__;
$includeDir = $currentDir . '/include';
if (!file_exists($includeDir) && file_exists($currentDir . '/../include')) {
    $includeDir = $currentDir . '/../include';
}

// 1.1 Multi-Language Support
$langDir = file_exists($currentDir . '/lang') ? $currentDir . '/lang' : (file_exists($includeDir . '/../lang') ? $includeDir . '/../lang' : '');
if (!empty($langDir) && file_exists($langDir . '/isocodelang.php')) {
    include_once $langDir . '/isocodelang.php';
}
if (!empty($_GET['setlang']) && !empty($isocodelang[$_GET['setlang']])) {
    $langid = $_GET['setlang'];
    $_SESSION['lang'] = $langid;
    @setcookie('mikhmon_lang', $langid, time() + 31536000, '/');
    @file_put_contents($includeDir . '/lang.php', '<?php $langid="' . addslashes($langid) . '";?>');
} else {
    $langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
    if (empty($langid) || (isset($isocodelang) && empty($isocodelang[$langid]))) {
        if (file_exists($includeDir . '/lang.php')) {
            include $includeDir . '/lang.php';
        }
        if (empty($langid) || (isset($isocodelang) && empty($isocodelang[$langid]))) {
            $langid = 'id';
        }
    }
}
$_SESSION['lang'] = $langid;
if (!empty($langDir) && file_exists($langDir . '/' . $langid . '.php')) {
    include_once $langDir . '/' . $langid . '.php';
}

// localization via lang dictionary

if (file_exists($includeDir . '/compatibility.php')) {
    include_once $includeDir . '/compatibility.php';
} elseif (file_exists($currentDir . '/compatibility.php')) {
    include_once $currentDir . '/compatibility.php';
}

// 2. Load Mikhmon & Config Files
$data = [];
if (file_exists($includeDir . '/config.php')) {
    include $includeDir . '/config.php';
} elseif (file_exists($currentDir . '/config.php')) {
    include $currentDir . '/config.php';
}

// License Check (STRICT: Block purchase if Mikhmon expired / suspended)
if (file_exists($includeDir . '/license.php')) {
    include_once $includeDir . '/license.php';
} elseif (file_exists($currentDir . '/include/license.php')) {
    include_once $currentDir . '/include/license.php';
}

if (function_exists('mikhmon_is_expired') && mikhmon_is_expired()) {
    $expDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="<?= htmlspecialchars($langid); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $_hotspot_service_unavailable ?? 'Layanan Hotspot Tidak Tersedia' ?></title>
        <style>
            body { background-color: #0b0e14; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; box-sizing: border-box; }
            .card { background: #121720; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px; max-width: 440px; width: 100%; padding: 28px 24px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
            .icon-box { width: 64px; height: 64px; background: rgba(239, 68, 68, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #ef4444; font-size: 28px; }
            h2 { font-size: 18px; font-weight: 700; margin: 0 0 8px; color: #ffffff; }
            p { font-size: 13.5px; color: #94a3b8; line-height: 1.5; margin: 0 0 16px; }
            .badge { display: inline-block; background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; margin-bottom: 20px; }
            .footer-info { font-size: 12px; color: #64748b; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 14px; margin-top: 14px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-box">⚠️</div>
            <h2><?= $_hotspot_sub_expired ?? 'Masa Aktif Hotspot Berakhir' ?></h2>
            <p><?= $_hotspot_sub_expired_desc ?? 'Mohon maaf, layanan pembelian voucher hotspot di lokasi ini sedang ditangguhkan atau masa aktifnya telah berakhir.' ?></p>
            <div class="badge"><?= ($_expired_on ?? 'Kedaluwarsa :') . ' ' . htmlspecialchars($expDateText) ?></div>
            <div class="footer-info">
                <?= $_contact_hotspot_admin_renew ?? 'Silakan hubungi pengelola / pemilik hotspot untuk melakukan perpanjangan langganan.' ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$location_data = [];
if (file_exists($includeDir . '/location_config.php')) {
    include $includeDir . '/location_config.php';
} elseif (file_exists($currentDir . '/location_config.php')) {
    include $currentDir . '/location_config.php';
}

$noderapay_data = [];
if (file_exists($includeDir . '/noderapay_config.php')) {
    include $includeDir . '/noderapay_config.php';
} elseif (file_exists($currentDir . '/noderapay_config.php')) {
    include $currentDir . '/noderapay_config.php';
}

$wa_data = [];
if (file_exists($includeDir . '/whatsapp_config.php')) {
    include $includeDir . '/whatsapp_config.php';
} elseif (file_exists($currentDir . '/whatsapp_config.php')) {
    include $currentDir . '/whatsapp_config.php';
}

// 3. Multi-Session / Multi-Location Discovery
$availableLocations = [];
$sessionKeys = [];
if (!empty($data) && is_array($data)) {
    foreach ($data as $sKey => $sVal) {
        if ($sKey === 'mikhmon' || !is_array($sVal)) continue;
        $sessionKeys[] = $sKey;
        
        $locCustom = trim($location_data['locations'][$sKey] ?? '');
        $hsCustom = explode('%', $sVal[4] ?? '')[1] ?? '';
        $dnsCustom = explode('^', $sVal[5] ?? '')[1] ?? '';
        
        $displayName = !empty($locCustom) ? $locCustom : (!empty($hsCustom) ? $hsCustom : ucwords(str_replace(['-', '_'], ' ', (string)$sKey)));
        
        $availableLocations[] = [
            'session' => $sKey,
            'name'    => $displayName,
            'dns'     => $dnsCustom
        ];
    }
}
if (!empty($location_data['locations']) && is_array($location_data['locations'])) {
    $existingSess = array_column($availableLocations, 'session');
    foreach ($location_data['locations'] as $sKey => $locName) {
        if ($sKey === 'mikhmon' || empty($sKey) || in_array($sKey, $existingSess)) continue;
        $displayName = trim($locName) ?: ucwords(str_replace(['-', '_'], ' ', (string)$sKey));
        $availableLocations[] = [
            'session' => $sKey,
            'name'    => $displayName,
            'dns'     => ''
        ];
        $sessionKeys[] = $sKey;
        $existingSess[] = $sKey;
    }
}
if (!empty($noderapay_data) && is_array($noderapay_data)) {
    $existingSess = array_column($availableLocations, 'session');
    foreach ($noderapay_data as $sKey => $sVal) {
        if ($sKey === 'mikhmon' || $sKey === 'default' || empty($sKey) || in_array($sKey, $existingSess)) continue;
        $displayName = trim($sVal['merchant_name'] ?? ($sVal['store_title'] ?? '')) ?: ucwords(str_replace(['-', '_'], ' ', (string)$sKey));
        $availableLocations[] = [
            'session' => $sKey,
            'name'    => $displayName,
            'dns'     => ''
        ];
        $sessionKeys[] = $sKey;
        $existingSess[] = $sKey;
    }
}

// 4. Resolve Active Router Session
$session = trim($_GET['session'] ?? ($_GET['loc'] ?? ''));
if (!empty($session) && !isset($data[$session]) && !empty($data) && is_array($data)) {
    foreach ($data as $k => $v) {
        if ($k === 'mikhmon') continue;
        if (strcasecmp($k, $session) === 0 || strpos($k, $session) !== false || strpos($session, $k) !== false) {
            $session = $k;
            break;
        }
    }
}
if (empty($session) && !empty($location_data['primary']) && isset($data[$location_data['primary']])) {
    $session = $location_data['primary'];
}
if (empty($session) && !empty($_SESSION['mikhmon_session']) && isset($data[$_SESSION['mikhmon_session']])) {
    $session = $_SESSION['mikhmon_session'];
}
if (empty($session) && !empty($sessionKeys)) {
    $session = $sessionKeys[0];
}
if (empty($session)) {
    $session = 'default';
}
if (!empty($session)) {
    $_SESSION['mikhmon_session'] = $session;
}

// 5. Resolve Merchant / Hotspot Name
$sessionCfg = $data[$session] ?? [];
$rawHotspot = !empty($sessionCfg[4]) ? (explode('%', $sessionCfg[4])[1] ?? '') : '';
$rawDns = !empty($sessionCfg[5]) ? (explode('^', $sessionCfg[5])[1] ?? '') : '';
$locName = trim($location_data['locations'][$session] ?? '');

$npCfg = $noderapay_data[$session] ?? $noderapay_data['default'] ?? [];
$waCfg = !empty($wa_data[$session]) ? $wa_data[$session] : (!empty($wa_data['default']) ? $wa_data['default'] : (reset($wa_data) ?: []));

$customMerchant = trim($npCfg['merchant_name'] ?? '');
$customStoreTitle = trim($npCfg['store_title'] ?? '');

$excludedMerchants = [
    'NODERA Pay Billing', 'NODERA Pay', 'NODERA HOTSPOT', 'WiFi Hotspot', 'Wave Merchant',
    'CV. Digital Network Solut', 'CV. DIGITAL NETWORK SOLUT', 'CV. Digital Network Solution',
    'CV. DIGITAL NETWORK SOLUTION', 'DIGITAL NETWORK SOLUTION', 'DgtlNet', 'NODERA', 'by panel.dgtlnetsolution.com'
];

$merchantName = '';
if (!empty($rawHotspot) && strtolower($rawHotspot) !== 'dns') {
    $merchantName = $rawHotspot;
} elseif (!empty($locName) && strtolower($locName) !== 'dns') {
    $merchantName = $locName;
} elseif (!empty($customStoreTitle) && !in_array($customStoreTitle, ['NODERA Pay Billing', 'NODERA Pay', 'NODERA HOTSPOT', 'Voucher WiFi Online', 'Portail Pass WiFi'])) {
    $merchantName = $customStoreTitle;
} elseif (!empty($customMerchant) && !in_array($customMerchant, $excludedMerchants)) {
    $merchantName = $customMerchant;
} else {
    $merchantName = ucwords(str_replace(['-', '_'], ' ', (string)$session));
}

$storeSubtitle = !empty($npCfg['store_subtitle']) ? $npCfg['store_subtitle'] : 'Internet Cepat, Murah & Aktif Otomatis';

$customHeaderBadge = trim($npCfg['header_badge_text'] ?? $npCfg['top_badge_text'] ?? '');
if (!empty($customHeaderBadge)) {
    $topBadgeText = $customHeaderBadge;
} else {
    $hotspotLocationBadge = (!empty($locName) && $locName !== $merchantName) ? $locName : (!empty($rawHotspot) && $rawHotspot !== $merchantName ? $rawHotspot : '');
    $topBadgeText = !empty($hotspotLocationBadge) ? 'Hotspot • ' . $hotspotLocationBadge : 'Hotspot Voucher Portal';
}

// CS Phone
$csPhone = !empty($waCfg['cs_phone']) ? $waCfg['cs_phone'] : (!empty($waCfg['admin_phone']) ? $waCfg['admin_phone'] : (!empty($npCfg['cs_phone']) ? $npCfg['cs_phone'] : (!empty($npCfg['admin_phone']) ? $npCfg['admin_phone'] : '6281234567890')));
$csPhoneClean = preg_replace('/[^0-9]/', '', $csPhone);
if (substr($csPhoneClean, 0, 1) === '0') $csPhoneClean = '62' . substr($csPhoneClean, 1);

// Theme Selection (16 Distinct Visual Physical Themes)
$validThemes = [
    'standard', 'linear', 'sunset', 'neumorphic',
    'stripe', 'emerald', 'voucher', 'obsidian',
    'matrix', 'luxury', 'cyberpunk', 'gaming',
    'retro', 'aurora', 'swiss', 'nordic'
];
$selectedTheme = $npCfg['portal_theme'] ?? 'standard';
$themeOverride = trim($_GET['theme'] ?? '');
if (!empty($themeOverride) && in_array($themeOverride, $validThemes)) {
    $selectedTheme = $themeOverride;
} elseif (!in_array($selectedTheme, $validThemes)) {
    $selectedTheme = 'standard';
}

// Display Elements Configuration (supports both new show_* and legacy keys)
$elemHeaderBadge = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_header_badge'] ?? 'yes') !== 'no';

// Check logo visibility preferences from include/logo_config.php
$logoCfgFile = './include/logo_config.php';
$logoCfgData = [];
if (file_exists($logoCfgFile)) {
    @include($logoCfgFile);
}
$useLogoInWeb = !isset($logoCfgData[$session]['use_in_web']) || $logoCfgData[$session]['use_in_web'] !== 'no';

// Discover merchant logo from uploaded mikhmon logo
$merchantLogoUrl = null;
if ($useLogoInWeb) {
    $logoCandidates = [
        "./img/logo-" . $session . ".png",
        "./img/logo-" . $session . ".jpg",
        "./img/logo-" . $session . ".jpeg",
        "./img/logo-" . $session . ".webp",
        "./img/logo-" . strtolower($session) . ".png",
        "./img/logo-" . strtolower($session) . ".jpg",
        "./img/logo-" . strtolower($session) . ".webp",
        "./img/logo-" . strtoupper($session) . ".png",
        "./img/logo.png",
        "./img/logo.jpg",
    ];
    foreach ($logoCandidates as $cand) {
        if (file_exists($cand) && filesize($cand) > 0) {
            $merchantLogoUrl = $cand . '?t=' . filemtime($cand);
            break;
        }
    }
}

$elemHeaderLogo  = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_header_logo'] ?? $npCfg['store_elements']['header_logo'] ?? 'yes') !== 'no';
$elemSubtitle    = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_header_subtitle'] ?? $npCfg['store_elements']['subtitle'] ?? 'yes') !== 'no';
$elemLocation    = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_location_select'] ?? $npCfg['store_elements']['location_selector'] ?? 'yes') !== 'no';
$elemPhoneInput  = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_phone_input'] ?? $npCfg['store_elements']['phone_input'] ?? 'yes') !== 'no';
$elemSortBar     = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_sort_bar'] ?? $npCfg['store_elements']['sort_bar'] ?? 'yes') !== 'no';
$elemStockBadge  = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_stock_badge'] ?? $npCfg['store_elements']['stock_badge'] ?? 'yes') !== 'no';
$elemValidity    = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_validity_badge'] ?? $npCfg['store_elements']['validity_badge'] ?? 'yes') !== 'no';
$elemTicker      = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_ticker'] ?? $npCfg['store_elements']['promo_ticker'] ?? 'yes') !== 'no';
$elemBottomNav   = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_bottom_nav'] ?? $npCfg['store_elements']['bottom_nav'] ?? 'yes') !== 'no';
$elemQuotaModal  = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_check_quota'] ?? $npCfg['store_elements']['quota_modal'] ?? 'yes') !== 'no';
$elemGuideModal  = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_guide_modal'] ?? $npCfg['store_elements']['guide_modal'] ?? 'yes') !== 'no';
$elemCsButton    = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_cs_contact'] ?? $npCfg['store_elements']['cs_button'] ?? 'yes') !== 'no';
$elemResumeDock  = !isset($npCfg['store_elements']) || ($npCfg['store_elements']['show_resume_dock'] ?? $npCfg['store_elements']['resume_payment'] ?? 'yes') !== 'no';

$customBannerText = trim($npCfg['custom_banner_text'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($merchantName); ?> — <?= $_buy_hotspot_voucher ?? 'Beli Voucher Hotspot'; ?></title>
  <?php if (!empty($merchantLogoUrl)): ?>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($merchantLogoUrl); ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($merchantLogoUrl); ?>">
  <?php else: ?>
  <link rel="icon" type="image/png" href="./favicon.ico">
  <?php endif; ?>
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($merchantName ?: 'Beli Voucher'); ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#0284C7">
  <link rel="manifest" href="./manifest-buy.json?v=4">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('./sw.js?v=11', { scope: './', updateViaCache: 'none' }).catch(function(){});
      });
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: background-color 0.25s ease, color 0.25s ease;
      -webkit-font-smoothing: antialiased;
      padding-bottom: 90px;
    }
    .portal-shell {
      width: 100%;
      max-width: 640px;
      margin: 0 auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    
    /* THEME 1: STANDARD (NEO CARDS) */
    body.theme-standard {
      background: #F8FAFC;
      color: #0F172A;
    }
    .theme-standard .hero-card {
      background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%);
      color: #FFFFFF;
      border-radius: 20px;
      padding: 22px;
      box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.3);
    }
    .theme-standard .card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 18px;
      box-shadow: 0 4px 15px -2px rgba(15, 23, 42, 0.04);
    }
    .theme-standard .pkg-card {
      background: #FFFFFF;
      border: 1.5px solid #E2E8F0;
      border-radius: 14px;
      padding: 16px;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .theme-standard .pkg-card:hover {
      border-color: #38BDF8;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px -4px rgba(2, 132, 199, 0.15);
    }
    .theme-standard .pkg-card.selected {
      border-color: #0284C7;
      background: #F0F9FF;
      box-shadow: 0 0 0 2px #0284C7, 0 8px 20px -4px rgba(2, 132, 199, 0.2);
    }
    .theme-standard .pkg-price {
      font-size: 17px;
      font-weight: 800;
      color: #0284C7;
    }
    .theme-standard .btn-buy {
      background: #0284C7;
      color: #FFFFFF;
    }
    .theme-standard .btn-buy:hover {
      background: #0369A1;
    }
    .theme-standard .bottom-nav-bar {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid #E2E8F0;
      color: #64748B;
    }
    .theme-standard .nav-item.active {
      color: #0284C7;
    }
    .theme-standard .input-phone-group {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      color: #0F172A;
      border-radius: 12px;
    }
    .theme-standard .input-phone-group:focus-within {
      border-color: #0284C7;
      box-shadow: 0 0 0 1px #0284C7;
    }
    .theme-standard .phone-prefix {
      background: #F1F5F9;
      border-right: 1px solid #CBD5E1;
      color: #0284C7;
    }
    .theme-standard .input-contact-icon {
      color: #0284C7;
    }
    .theme-standard .select-location {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      color: #0F172A;
      border-radius: 10px;
    }
    .theme-standard .select-location option {
      background: #FFFFFF;
      color: #0F172A;
    }
    .theme-standard .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FFFFFF;
      color: #0F172A;
      border: 1px solid #E2E8F0;
    }
    .theme-standard .modal-close {
      background: #F1F5F9;
      color: #64748B;
    }
    .theme-standard #input_check_code {
      background: #FFFFFF !important;
      border: 1.5px solid #CBD5E1 !important;
      color: #0F172A !important;
    }

    /* THEME 2: STRIPE (HORIZONTAL ROW LIST) */
    body.theme-stripe {
      background: #F9FAFB;
      color: #111827;
    }
    .theme-stripe .hero-card {
      background: #FFFFFF;
      color: #111827;
      border: 1px solid #E5E7EB;
      border-radius: 12px;
      padding: 20px;
    }
    .theme-stripe .card {
      background: #FFFFFF;
      border: 1px solid #E5E7EB;
      border-radius: 12px;
      padding: 16px;
    }
    .theme-stripe .pkg-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .theme-stripe .pkg-card {
      background: #FFFFFF;
      border: 1px solid #E5E7EB;
      border-radius: 10px;
      padding: 14px 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: border-color 0.15s ease;
    }
    .theme-stripe .pkg-card:hover {
      border-color: #9CA3AF;
    }
    .theme-stripe .pkg-card.selected {
      border-color: #6366F1;
      background: #EEF2FF;
      box-shadow: 0 0 0 1px #6366F1;
    }
    .theme-stripe .stripe-radio {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      border: 2px solid #D1D5DB;
      margin-right: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .theme-stripe .pkg-card.selected .stripe-radio {
      border-color: #6366F1;
      background: #6366F1;
    }
    .theme-stripe .pkg-card.selected .stripe-radio::after {
      content: '';
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #FFFFFF;
    }
    .theme-stripe .pkg-price {
      font-size: 16px;
      font-weight: 700;
      color: #111827;
    }
    .theme-stripe .btn-buy {
      background: #6366F1;
      color: #FFFFFF;
    }
    .theme-stripe .btn-buy:hover {
      background: #4F46E5;
    }
    .theme-stripe .bottom-nav-bar {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid #E5E7EB;
      color: #6B7280;
    }
    .theme-stripe .nav-item.active {
      color: #6366F1;
    }
    .theme-stripe .input-phone-group {
      background: #FFFFFF;
      border: 1px solid #E5E7EB;
      color: #111827;
      border-radius: 10px;
    }
    .theme-stripe .input-phone-group:focus-within {
      border-color: #6366F1;
      box-shadow: 0 0 0 1px #6366F1;
    }
    .theme-stripe .phone-prefix {
      background: #F9FAFB;
      border-right: 1px solid #E5E7EB;
      color: #6366F1;
    }
    .theme-stripe .input-contact-icon {
      color: #6366F1;
    }
    .theme-stripe .select-location {
      background: #FFFFFF;
      border: 1px solid #E5E7EB;
      color: #111827;
      border-radius: 8px;
    }
    .theme-stripe .select-location option {
      background: #FFFFFF;
      color: #111827;
    }
    .theme-stripe .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FFFFFF;
      color: #111827;
      border: 1px solid #E5E7EB;
    }
    .theme-stripe .modal-close {
      background: #F3F4F6;
      color: #6B7280;
    }
    .theme-stripe #input_check_code {
      background: #FFFFFF !important;
      border: 1px solid #E5E7EB !important;
      color: #111827 !important;
    }

    /* THEME 3: LINEAR (BENTO GRID TECH) */
    body.theme-linear {
      background: #09090B;
      color: #FAFAFA;
      font-family: 'Inter', -apple-system, sans-serif;
    }
    .theme-linear .hero-card {
      background: #18181B;
      color: #FAFAFA;
      border: 1px solid #27272A;
      border-radius: 14px;
      padding: 20px;
    }
    .theme-linear .card {
      background: #121215;
      border: 1px solid #27272A;
      border-radius: 12px;
      padding: 16px;
    }
    .theme-linear .bento-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .theme-linear .bento-hero {
      grid-column: span 2;
      background: linear-gradient(180deg, #1C1C21 0%, #121215 100%);
      border: 1px solid #3F3F46;
    }
    .theme-linear .pkg-card {
      background: #18181B;
      border: 1px solid #27272A;
      border-radius: 10px;
      padding: 14px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-linear .pkg-card:hover {
      border-color: #52525B;
    }
    .theme-linear .pkg-card.selected {
      border-color: #FAFAFA;
      background: #27272A;
      box-shadow: 0 0 0 1px #FAFAFA;
    }
    .theme-linear .pkg-price {
      font-size: 15px;
      font-weight: 700;
      color: #FAFAFA;
    }
    .theme-linear .btn-buy {
      background: #FAFAFA;
      color: #09090B;
      font-weight: 700;
    }
    .theme-linear .btn-buy:hover {
      background: #E4E4E7;
    }
    .theme-linear .bottom-nav-bar {
      background: rgba(18, 18, 21, 0.95);
      border: 1px solid #27272A;
      color: #71717A;
    }
    .theme-linear .nav-item.active {
      color: #FAFAFA;
    }
    .theme-linear .input-phone-group {
      background: #18181B;
      border: 1px solid #27272A;
      color: #FAFAFA;
      border-radius: 10px;
    }
    .theme-linear .input-phone-group:focus-within {
      border-color: #52525B;
      box-shadow: 0 0 0 1px #52525B;
    }
    .theme-linear .phone-prefix {
      background: #27272A;
      border-right: 1px solid #3F3F46;
      color: #A1A1AA;
    }
    .theme-linear .input-contact-icon {
      color: #FAFAFA;
    }
    .theme-linear .select-location {
      background: #18181B;
      border: 1px solid #27272A;
      color: #FAFAFA;
      border-radius: 8px;
    }
    .theme-linear .select-location option {
      background: #18181B;
      color: #FAFAFA;
    }
    .theme-linear .modal-sheet:not(.fintech-checkout-sheet) {
      background: #18181B;
      color: #FAFAFA;
      border: 1px solid #27272A;
    }
    .theme-linear .modal-close {
      background: #27272A;
      color: #FAFAFA;
    }
    .theme-linear #input_check_code {
      background: #121215 !important;
      border: 1px solid #27272A !important;
      color: #FAFAFA !important;
    }

    /* THEME 4: VOUCHER (RETRO PERFORATED TICKET SLIP) */
    body.theme-voucher {
      background: #FDFBF7;
      color: #292524;
    }
    .theme-voucher .hero-card {
      background: #78350F;
      color: #FEF3C7;
      border-radius: 14px;
      padding: 20px;
    }
    .theme-voucher .card {
      background: #FFFFFF;
      border: 1.5px dashed #D6D3D1;
      border-radius: 12px;
      padding: 16px;
    }
    .theme-voucher .pkg-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .theme-voucher .pkg-card {
      background: #FFFFFF;
      border: 1.5px solid #E7E5E4;
      border-radius: 10px;
      display: flex;
      overflow: hidden;
      cursor: pointer;
      position: relative;
    }
    .theme-voucher .pkg-card.selected {
      border-color: #D97706;
      background: #FFFBEB;
    }
    .theme-voucher .ticket-stub {
      width: 70px;
      background: #F5F5F4;
      border-right: 2px dashed #D6D3D1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 800;
      color: #78716C;
      padding: 10px;
    }
    .theme-voucher .ticket-body {
      flex: 1;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .theme-voucher .pkg-price {
      font-size: 16px;
      font-weight: 800;
      color: #B45309;
    }
    .theme-voucher .btn-buy {
      background: #D97706;
      color: #FFFFFF;
    }
    .theme-voucher .btn-buy:hover {
      background: #B45309;
    }
    .theme-voucher .bottom-nav-bar {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid #E7E5E4;
      color: #78716C;
    }
    .theme-voucher .nav-item.active {
      color: #D97706;
    }
    .theme-voucher .input-phone-group {
      background: #FFFFFF;
      border: 1.5px dashed #D6D3D1;
      color: #292524;
      border-radius: 10px;
    }
    .theme-voucher .input-phone-group:focus-within {
      border-color: #D97706;
      box-shadow: 0 0 0 1px #D97706;
    }
    .theme-voucher .phone-prefix {
      background: #F5F5F4;
      border-right: 1.5px dashed #D6D3D1;
      color: #B45309;
    }
    .theme-voucher .input-contact-icon {
      color: #D97706;
    }
    .theme-voucher .select-location {
      background: #FFFFFF;
      border: 1.5px dashed #D6D3D1;
      color: #292524;
      border-radius: 8px;
    }
    .theme-voucher .select-location option {
      background: #FFFFFF;
      color: #292524;
    }
    .theme-voucher .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FFFBEB;
      color: #292524;
      border: 1.5px dashed #D97706;
    }
    .theme-voucher .modal-close {
      background: #FEF3C7;
      color: #78350F;
    }
    .theme-voucher #input_check_code {
      background: #FFFFFF !important;
      border: 1.5px dashed #D6D3D1 !important;
      color: #292524 !important;
    }

    /* THEME 5: OBSIDIAN (COBALT CYBER DARK) */
    body.theme-obsidian {
      background: #07090E;
      color: #F8FAFC;
    }
    .theme-obsidian .hero-card {
      background: linear-gradient(135deg, #0B0F19 0%, #111827 100%);
      border: 1px solid #1E2633;
      border-radius: 16px;
      padding: 20px;
      color: #F8FAFC;
    }
    .theme-obsidian .card {
      background: #0D121F;
      border: 1px solid #1E2633;
      border-radius: 14px;
      padding: 16px;
    }
    .theme-obsidian .pkg-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .theme-obsidian .pkg-card {
      background: #0A0E1A;
      border: 1px solid #1E2633;
      border-radius: 12px;
      padding: 14px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.2s ease;
    }
    .theme-obsidian .pkg-card:hover {
      border-color: #00C2FF;
    }
    .theme-obsidian .pkg-card.selected {
      border-color: #00C2FF;
      background: #0F172A;
      box-shadow: 0 0 15px rgba(0, 194, 255, 0.25);
    }
    .theme-obsidian .pkg-price {
      font-size: 16px;
      font-weight: 800;
      color: #00C2FF;
    }
    .theme-obsidian .btn-buy {
      background: #00C2FF;
      color: #07090E;
      font-weight: 800;
    }
    .theme-obsidian .btn-buy:hover {
      background: #38BDF8;
    }
    .theme-obsidian .bottom-nav-bar {
      background: rgba(13, 18, 31, 0.95);
      border: 1px solid #1E2633;
      color: #64748B;
    }
    .theme-obsidian .nav-item.active {
      color: #00C2FF;
    }
    .theme-obsidian .input-phone-group {
      background: #0A0E1A;
      border: 1px solid #1E2633;
      color: #F8FAFC;
      border-radius: 12px;
    }
    .theme-obsidian .input-phone-group:focus-within {
      border-color: #00C2FF;
      box-shadow: 0 0 12px rgba(0, 194, 255, 0.25);
    }
    .theme-obsidian .phone-prefix {
      background: #111827;
      border-right: 1px solid #1E2633;
      color: #00C2FF;
    }
    .theme-obsidian .input-contact-icon {
      color: #00C2FF;
    }
    .theme-obsidian .select-location {
      background: #0A0E1A;
      border: 1px solid #1E2633;
      color: #F8FAFC;
      border-radius: 10px;
    }
    .theme-obsidian .select-location option {
      background: #0A0E1A;
      color: #F8FAFC;
    }
    .theme-obsidian .modal-sheet:not(.fintech-checkout-sheet) {
      background: #0D121F;
      color: #F8FAFC;
      border: 1px solid #1E2633;
    }
    .theme-obsidian .modal-close {
      background: #1E2633;
      color: #F8FAFC;
    }
    .theme-obsidian #input_check_code {
      background: #07090E !important;
      border: 1px solid #1E2633 !important;
      color: #F8FAFC !important;
    }

    /* THEME 6: AURORA (FROSTED GLASS & GLOWING AURA) */
    body.theme-aurora {
      background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.25) 0%, transparent 40%),
                  radial-gradient(circle at 90% 80%, rgba(236, 72, 153, 0.2) 0%, transparent 40%),
                  radial-gradient(circle at 50% 50%, rgba(6, 182, 212, 0.15) 0%, transparent 50%),
                  #0B0F19;
      color: #F8FAFC;
      background-attachment: fixed;
    }
    .theme-aurora .hero-card {
      background: rgba(255, 255, 255, 0.07);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 20px;
      padding: 22px;
      color: #FFFFFF;
      box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    .theme-aurora .card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 18px;
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    }
    .theme-aurora .aurora-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .theme-aurora .pkg-card {
      background: rgba(255, 255, 255, 0.06);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1.5px solid rgba(255, 255, 255, 0.12);
      border-radius: 14px;
      padding: 16px;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .theme-aurora .pkg-card:hover {
      border-color: rgba(168, 85, 247, 0.6);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px -5px rgba(168, 85, 247, 0.3);
    }
    .theme-aurora .pkg-card.selected {
      border-color: #C084FC;
      background: rgba(168, 85, 247, 0.18);
      box-shadow: 0 0 20px rgba(192, 132, 252, 0.4), inset 0 0 15px rgba(192, 132, 252, 0.1);
    }
    .theme-aurora .glass-pill {
      font-size: 10px;
      font-weight: 800;
      color: #E0E7FF;
      background: rgba(255, 255, 255, 0.12);
      padding: 2px 7px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .theme-aurora .pkg-price {
      font-size: 16px;
      font-weight: 800;
      color: #38BDF8;
    }
    .theme-aurora .btn-buy {
      background: linear-gradient(135deg, #8B5CF6 0%, #06B6D4 100%);
      color: #FFFFFF;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 25px rgba(139, 92, 246, 0.35);
    }
    .theme-aurora .btn-buy:hover {
      background: linear-gradient(135deg, #7C3AED 0%, #0891B2 100%);
    }
    .theme-aurora .bottom-nav-bar {
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #94A3B8;
    }
    .theme-aurora .nav-item.active {
      color: #C084FC;
    }
    .theme-aurora .input-phone-group {
      background: rgba(255, 255, 255, 0.07);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1.5px solid rgba(255, 255, 255, 0.15);
      color: #FFFFFF;
      border-radius: 12px;
    }
    .theme-aurora .input-phone-group:focus-within {
      border-color: #C084FC;
      box-shadow: 0 0 15px rgba(192, 132, 252, 0.35);
    }
    .theme-aurora .phone-prefix {
      background: rgba(255, 255, 255, 0.1);
      border-right: 1px solid rgba(255, 255, 255, 0.15);
      color: #C084FC;
    }
    .theme-aurora .input-contact-icon {
      color: #C084FC;
    }
    .theme-aurora .select-location {
      background: rgba(255, 255, 255, 0.07);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1.5px solid rgba(255, 255, 255, 0.15);
      color: #FFFFFF;
      border-radius: 10px;
    }
    .theme-aurora .select-location option {
      background: #0F172A;
      color: #FFFFFF;
    }
    .theme-aurora .modal-sheet:not(.fintech-checkout-sheet) {
      background: #0F172A;
      color: #F8FAFC;
      border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .theme-aurora .modal-close {
      background: rgba(255, 255, 255, 0.1);
      color: #FFFFFF;
    }
    .theme-aurora #input_check_code {
      background: rgba(15, 23, 42, 0.6) !important;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      color: #F8FAFC !important;
    }

    /* THEME 7: EMERALD (WAVE FINTECH & GOLD) */
    body.theme-emerald {
      background: #F0FDF4;
      color: #064E3B;
    }
    .theme-emerald .hero-card {
      background: linear-gradient(135deg, #065F46 0%, #047857 50%, #059669 100%);
      color: #FFFFFF;
      border-radius: 20px;
      padding: 22px;
      box-shadow: 0 12px 30px -5px rgba(5, 150, 105, 0.35);
    }
    .theme-emerald .card {
      background: #FFFFFF;
      border: 1px solid #D1FAE5;
      border-radius: 16px;
      padding: 18px;
      box-shadow: 0 4px 15px -2px rgba(6, 78, 59, 0.05);
    }
    .theme-emerald .pkg-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .theme-emerald .pkg-card {
      background: #FFFFFF;
      border: 1.5px solid #E5E7EB;
      border-radius: 14px;
      padding: 14px 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: all 0.2s ease;
    }
    .theme-emerald .pkg-card:hover {
      border-color: #10B981;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px -2px rgba(16, 185, 129, 0.15);
    }
    .theme-emerald .pkg-card.selected {
      border-color: #059669;
      background: #ECFDF5;
      box-shadow: 0 0 0 2px #059669, 0 8px 20px -4px rgba(5, 150, 105, 0.2);
    }
    .theme-emerald .emerald-icon-box {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: #E0F2FE;
      color: #0284C7;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
      transition: all 0.2s ease;
    }
    .theme-emerald .pkg-card.selected .emerald-icon-box {
      background: #059669;
      color: #FFFFFF;
    }
    .theme-emerald .emerald-tag {
      font-size: 10px;
      font-weight: 800;
      background: #FEF3C7;
      color: #92400E;
      padding: 2px 6px;
      border-radius: 4px;
    }
    .theme-emerald .pkg-price {
      font-size: 17px;
      font-weight: 800;
      color: #065F46;
    }
    .theme-emerald .btn-buy {
      background: #059669;
      color: #FFFFFF;
      box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
    }
    .theme-emerald .btn-buy:hover {
      background: #047857;
    }
    .theme-emerald .bottom-nav-bar {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid #D1FAE5;
      color: #065F46;
    }
    .theme-emerald .nav-item.active {
      color: #059669;
    }
    .theme-emerald .input-phone-group {
      background: #FFFFFF;
      border: 1.5px solid #D1FAE5;
      color: #064E3B;
      border-radius: 12px;
    }
    .theme-emerald .input-phone-group:focus-within {
      border-color: #059669;
      box-shadow: 0 0 0 1px #059669;
    }
    .theme-emerald .phone-prefix {
      background: #ECFDF5;
      border-right: 1px solid #D1FAE5;
      color: #059669;
    }
    .theme-emerald .input-contact-icon {
      color: #059669;
    }
    .theme-emerald .select-location {
      background: #FFFFFF;
      border: 1.5px solid #D1FAE5;
      color: #064E3B;
      border-radius: 10px;
    }
    .theme-emerald .select-location option {
      background: #FFFFFF;
      color: #064E3B;
    }
    .theme-emerald .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FFFFFF;
      color: #064E3B;
      border: 1px solid #D1FAE5;
    }
    .theme-emerald .modal-close {
      background: #ECFDF5;
      color: #065F46;
    }
    .theme-emerald #input_check_code {
      background: #FFFFFF !important;
      border: 1.5px solid #D1FAE5 !important;
      color: #064E3B !important;
    }

    /* THEME 8: CYBERPUNK (HIGH-VOLTAGE ARCADE HUD) */
    body.theme-cyberpunk {
      background: #040608;
      color: #00FF88;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-cyberpunk .hero-card {
      background: #0B0F14;
      color: #00FF88;
      border: 1.5px solid #00FF88;
      clip-path: polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 14px 100%, 0 calc(100% - 14px));
      padding: 22px;
      box-shadow: 0 0 25px rgba(0, 255, 136, 0.15), inset 0 0 15px rgba(0, 255, 136, 0.05);
    }
    .theme-cyberpunk .card {
      background: #080C10;
      border: 1px solid #1A2820;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.8);
    }
    .theme-cyberpunk .cyber-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .theme-cyberpunk .pkg-card {
      background: #0B0F14;
      border: 1px solid #1F3628;
      border-radius: 6px;
      padding: 14px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      transition: all 0.15s ease;
    }
    .theme-cyberpunk .pkg-card:hover {
      border-color: #00F0FF;
      box-shadow: 0 0 15px rgba(0, 240, 255, 0.3);
    }
    .theme-cyberpunk .pkg-card.selected {
      border-color: #00FF88;
      background: rgba(0, 255, 136, 0.08);
      box-shadow: 0 0 20px rgba(0, 255, 136, 0.4), inset 0 0 10px rgba(0, 255, 136, 0.1);
    }
    .theme-cyberpunk .cyber-code-badge {
      font-size: 9px;
      font-weight: 800;
      background: rgba(0, 240, 255, 0.15);
      color: #00F0FF;
      border: 1px solid rgba(0, 240, 255, 0.4);
      padding: 1px 5px;
      border-radius: 3px;
      letter-spacing: 0.5px;
    }
    .theme-cyberpunk .pkg-price {
      font-size: 16px;
      font-weight: 900;
      color: #00FF88;
      text-shadow: 0 0 8px rgba(0, 255, 136, 0.5);
    }
    .theme-cyberpunk .btn-buy {
      background: #00FF88;
      color: #000000;
      font-weight: 900;
      border: none;
      box-shadow: 0 0 25px rgba(0, 255, 136, 0.4);
      letter-spacing: 0.5px;
    }
    .theme-cyberpunk .btn-buy:hover {
      background: #39FFA0;
      box-shadow: 0 0 35px rgba(0, 255, 136, 0.6);
    }
    .theme-cyberpunk .bottom-nav-bar {
      background: rgba(8, 12, 16, 0.95);
      border: 1px solid #1A2820;
      color: #4B6E5B;
    }
    .theme-cyberpunk .nav-item.active {
      color: #00FF88;
    }
    .theme-cyberpunk .input-phone-group {
      background: #0B0F14;
      border: 1px solid #1F3628;
      color: #00FF88;
      border-radius: 6px;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-cyberpunk .input-phone-group:focus-within {
      border-color: #00FF88;
      box-shadow: 0 0 15px rgba(0, 255, 136, 0.35);
    }
    .theme-cyberpunk .phone-prefix {
      background: #080C10;
      border-right: 1px solid #1F3628;
      color: #00FF88;
    }
    .theme-cyberpunk .input-contact-icon {
      color: #00FF88;
    }
    .theme-cyberpunk .select-location {
      background: #0B0F14;
      border: 1px solid #1F3628;
      color: #00FF88;
      border-radius: 6px;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-cyberpunk .select-location option {
      background: #0B0F14;
      color: #00FF88;
    }
    .theme-cyberpunk .modal-sheet:not(.fintech-checkout-sheet) {
      background: #080C10;
      color: #00FF88;
      border: 1.5px solid #00FF88;
      font-family: 'JetBrains Mono', monospace;
      box-shadow: 0 0 25px rgba(0, 255, 136, 0.25);
    }
    .theme-cyberpunk .modal-close {
      background: #1A2820;
      color: #00FF88;
    }
    .theme-cyberpunk #input_check_code {
      background: #0B0F14 !important;
      border: 1px solid #1F3628 !important;
      color: #00FF88 !important;
    }

    /* THEME 9: SWISS (BAUHAUS MINIMALIST PRINT) */
    body.theme-swiss {
      background: #F4F4F0;
      color: #111111;
    }
    .theme-swiss .hero-card {
      background: #111111;
      color: #F4F4F0;
      border-radius: 0;
      padding: 24px;
      border-left: 6px solid #FF4400;
      box-shadow: 5px 5px 0px #CCCCCC;
    }
    .theme-swiss .card {
      background: #FFFFFF;
      border: 2px solid #111111;
      border-radius: 0;
      padding: 18px;
      box-shadow: 4px 4px 0px #111111;
    }
    .theme-swiss .swiss-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .theme-swiss .pkg-card {
      background: #FFFFFF;
      border: 2px solid #111111;
      border-radius: 0;
      padding: 0;
      cursor: pointer;
      display: flex;
      align-items: stretch;
      box-shadow: 4px 4px 0px #111111;
      transition: all 0.15s ease;
    }
    .theme-swiss .pkg-card:hover {
      transform: translate(-2px, -2px);
      box-shadow: 6px 6px 0px #111111;
    }
    .theme-swiss .pkg-card.selected {
      background: #FF4400;
      color: #FFFFFF;
      transform: translate(-2px, -2px);
      box-shadow: 6px 6px 0px #111111;
    }
    .theme-swiss .swiss-num {
      width: 50px;
      background: #111111;
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 900;
      flex-shrink: 0;
    }
    .theme-swiss .pkg-card.selected .swiss-num {
      background: #000000;
      color: #FF4400;
    }
    .theme-swiss .swiss-content {
      flex: 1;
      padding: 14px 16px;
    }
    .theme-swiss .pkg-price {
      font-size: 16px;
      font-weight: 900;
      color: inherit;
    }
    .theme-swiss .btn-buy {
      background: #111111;
      color: #FFFFFF;
      border: 2px solid #111111;
      border-radius: 0;
      box-shadow: 4px 4px 0px #FF4400;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .theme-swiss .btn-buy:hover {
      background: #FF4400;
      box-shadow: 4px 4px 0px #111111;
    }
    .theme-swiss .bottom-nav-bar {
      background: #FFFFFF;
      border: 2px solid #111111;
      border-radius: 0;
      box-shadow: 4px 4px 0px #111111;
      color: #111111;
    }
    .theme-swiss .nav-item.active {
      color: #FF4400;
    }
    .theme-swiss .input-phone-group {
      background: #FFFFFF;
      border: 2px solid #111111;
      color: #111111;
      border-radius: 0;
      box-shadow: 4px 4px 0px #111111;
    }
    .theme-swiss .input-phone-group:focus-within {
      border-color: #FF4400;
      box-shadow: 4px 4px 0px #FF4400;
    }
    .theme-swiss .phone-prefix {
      background: #111111;
      border-right: 2px solid #111111;
      color: #FFFFFF;
      font-weight: 900;
    }
    .theme-swiss .input-contact-icon {
      color: #111111;
    }
    .theme-swiss .select-location {
      background: #FFFFFF;
      border: 2px solid #111111;
      color: #111111;
      border-radius: 0;
      box-shadow: 4px 4px 0px #111111;
      font-weight: 700;
    }
    .theme-swiss .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FFFFFF;
      color: #111111;
      border: 3px solid #111111;
      border-radius: 0;
      box-shadow: 8px 8px 0px #111111;
    }
    .theme-swiss .modal-close {
      background: #111111;
      color: #FFFFFF;
      border-radius: 0;
    }
    .theme-swiss #input_check_code {
      background: #FFFFFF !important;
      border: 2px solid #111111 !important;
      color: #111111 !important;
      border-radius: 0 !important;
      box-shadow: 3px 3px 0px #111111 !important;
    }

    /* THEME 10: SUNSET (WARM CORAL & PEACH) */
    body.theme-sunset {
      background: #FFF7ED;
      color: #431407;
    }
    .theme-sunset .hero-card {
      background: linear-gradient(135deg, #F97316 0%, #EC4899 100%);
      color: #FFFFFF;
      border-radius: 22px;
      padding: 22px;
      box-shadow: 0 12px 30px -5px rgba(249, 115, 22, 0.35);
    }
    .theme-sunset .card {
      background: #FFFFFF;
      border: 1px solid #FFEDD5;
      border-radius: 18px;
      padding: 18px;
      box-shadow: 0 4px 20px -2px rgba(249, 115, 22, 0.08);
    }
    .theme-sunset .sunset-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .theme-sunset .pkg-card {
      background: #FFFFFF;
      border: 1.5px solid #FED7AA;
      border-radius: 16px;
      padding: 16px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .theme-sunset .pkg-card:hover {
      border-color: #F97316;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px -4px rgba(249, 115, 22, 0.2);
    }
    .theme-sunset .pkg-card.selected {
      border-color: #EA580C;
      background: #FFF7ED;
      box-shadow: 0 0 0 2px #EA580C, 0 8px 24px -4px rgba(234, 88, 12, 0.25);
    }
    .theme-sunset .sunset-pill-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      min-height: 18px;
    }
    .theme-sunset .sunset-validity {
      font-size: 10px;
      font-weight: 800;
      background: #FFEDD5;
      color: #EA580C;
      padding: 2px 7px;
      border-radius: 999px;
    }
    .theme-sunset .sunset-price-row {
      margin-top: 12px;
      padding-top: 8px;
      border-top: 1px solid #FFEDD5;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .theme-sunset .sunset-arrow-icon {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #FFEDD5;
      color: #EA580C;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
    }
    .theme-sunset .pkg-card.selected .sunset-arrow-icon {
      background: #EA580C;
      color: #FFFFFF;
    }
    .theme-sunset .pkg-price {
      font-size: 16.5px;
      font-weight: 800;
      color: #EA580C;
    }
    .theme-sunset .btn-buy {
      background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
      color: #FFFFFF;
      border-radius: 14px;
      box-shadow: 0 8px 25px rgba(249, 115, 22, 0.35);
    }
    .theme-sunset .btn-buy:hover {
      background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%);
    }
    .theme-sunset .bottom-nav-bar {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid #FFEDD5;
      color: #7C2D12;
    }
    .theme-sunset .nav-item.active {
      color: #EA580C;
    }
    .theme-sunset .input-phone-group {
      background: #FFFFFF;
      border: 1.5px solid #FED7AA;
      color: #431407;
      border-radius: 14px;
    }
    .theme-sunset .input-phone-group:focus-within {
      border-color: #EA580C;
      box-shadow: 0 0 0 1px #EA580C;
    }
    .theme-sunset .phone-prefix {
      background: #FFF7ED;
      border-right: 1px solid #FED7AA;
      color: #EA580C;
    }
    .theme-sunset .input-contact-icon {
      color: #EA580C;
    }
    .theme-sunset .select-location {
      background: #FFFFFF;
      border: 1.5px solid #FED7AA;
      color: #431407;
      border-radius: 12px;
    }
    .theme-sunset .select-location option {
      background: #FFFFFF;
      color: #431407;
    }
    .theme-sunset .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FFFFFF;
      color: #431407;
      border: 1px solid #FED7AA;
    }
    .theme-sunset .modal-close {
      background: #FFEDD5;
      color: #C2410C;
    }
    .theme-sunset #input_check_code {
      background: #FFFFFF !important;
      border: 1.5px solid #FED7AA !important;
      color: #431407 !important;
    }

    /* THEME 11: NEUMORPHIC (SOFT 3D CLAY & PEARL) */
    body.theme-neumorphic {
      background: #E8ECEF;
      color: #2D3748;
    }
    .theme-neumorphic .hero-card {
      background: #E8ECEF;
      border-radius: 20px;
      padding: 22px;
      color: #1A202C;
      box-shadow: 8px 8px 16px #c5c9cc, -8px -8px 16px #ffffff;
      border: none;
    }
    .theme-neumorphic .card {
      background: #E8ECEF;
      border-radius: 16px;
      padding: 16px;
      box-shadow: 6px 6px 12px #c5c9cc, -6px -6px 12px #ffffff;
      border: none;
    }
    .theme-neumorphic .neumorphic-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .theme-neumorphic .pkg-card {
      background: #E8ECEF;
      border-radius: 14px;
      padding: 16px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 6px 6px 12px #c5c9cc, -6px -6px 12px #ffffff;
      border: 2px solid transparent;
      transition: all 0.2s ease;
    }
    .theme-neumorphic .pkg-card:hover {
      transform: translateY(-2px);
      box-shadow: 8px 8px 16px #bcc0c3, -8px -8px 16px #ffffff;
    }
    .theme-neumorphic .pkg-card.selected {
      background: #E8ECEF;
      box-shadow: inset 4px 4px 8px #c5c9cc, inset -4px -4px 8px #ffffff;
      border-color: #3B82F6;
    }
    .theme-neumorphic .pkg-price {
      font-size: 16px;
      font-weight: 800;
      color: #2563EB;
    }
    .theme-neumorphic .btn-buy {
      background: #E8ECEF;
      color: #2563EB;
      font-weight: 800;
      border-radius: 12px;
      box-shadow: 5px 5px 10px #c5c9cc, -5px -5px 10px #ffffff;
      border: none;
    }
    .theme-neumorphic .btn-buy:hover {
      box-shadow: inset 3px 3px 6px #c5c9cc, inset -3px -3px 6px #ffffff;
    }
    .theme-neumorphic .bottom-nav-bar {
      background: #E8ECEF;
      box-shadow: 0 -4px 15px rgba(0,0,0,0.06);
      border: none;
      color: #64748B;
    }
    .theme-neumorphic .nav-item.active {
      color: #2563EB;
    }
    .theme-neumorphic .input-phone-group {
      background: #E8ECEF;
      border-radius: 12px;
      border: none;
      box-shadow: inset 3px 3px 6px #c5c9cc, inset -3px -3px 6px #ffffff;
      color: #2D3748;
    }
    .theme-neumorphic .phone-prefix {
      background: #E8ECEF;
      border-right: 1px solid #c5c9cc;
      color: #2563EB;
    }
    .theme-neumorphic .input-contact-icon {
      color: #2563EB;
    }
    .theme-neumorphic .select-location {
      background: #E8ECEF;
      border: none;
      box-shadow: inset 3px 3px 6px #c5c9cc, inset -3px -3px 6px #ffffff;
      color: #2D3748;
      border-radius: 10px;
    }
    .theme-neumorphic .select-location option {
      background: #E8ECEF;
      color: #2D3748;
    }
    .theme-neumorphic .modal-sheet:not(.fintech-checkout-sheet) {
      background: #E8ECEF;
      color: #2D3748;
      box-shadow: 12px 12px 24px #c5c9cc, -12px -12px 24px #ffffff;
      border: none;
    }
    .theme-neumorphic .modal-close {
      background: #E8ECEF;
      color: #64748B;
      box-shadow: 3px 3px 6px #c5c9cc, -3px -3px 6px #ffffff;
    }
    .theme-neumorphic #input_check_code {
      background: #E8ECEF !important;
      border: none !important;
      box-shadow: inset 3px 3px 6px #c5c9cc, inset -3px -3px 6px #ffffff !important;
      color: #2D3748 !important;
    }

    /* THEME 12: MATRIX (PHOSPHOR GREEN TERMINAL CLI) */
    body.theme-matrix {
      background: #0D1117;
      color: #00FF66;
      font-family: 'JetBrains Mono', 'Courier New', monospace;
    }
    .theme-matrix .hero-card {
      background: #05080C;
      border: 1px solid #00FF66;
      border-radius: 4px;
      padding: 18px;
      color: #00FF66;
      box-shadow: 0 0 15px rgba(0, 255, 102, 0.15);
    }
    .theme-matrix .card {
      background: #05080C;
      border: 1px solid #1F3628;
      border-radius: 4px;
      padding: 14px;
    }
    .theme-matrix .matrix-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .theme-matrix .pkg-card {
      background: #05080C;
      border: 1px solid #1F3628;
      border-radius: 4px;
      padding: 12px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.15s ease;
    }
    .theme-matrix .pkg-card:hover {
      border-color: #00FF66;
      box-shadow: 0 0 10px rgba(0, 255, 102, 0.25);
    }
    .theme-matrix .pkg-card.selected {
      border-color: #00FF66;
      background: rgba(0, 255, 102, 0.08);
      box-shadow: 0 0 16px rgba(0, 255, 102, 0.35), inset 0 0 8px rgba(0, 255, 102, 0.1);
    }
    .theme-matrix .matrix-cli-tag {
      font-size: 9.5px;
      font-weight: 700;
      color: #00FF66;
      opacity: 0.8;
    }
    .theme-matrix .pkg-price {
      font-size: 15.5px;
      font-weight: 800;
      color: #00FF66;
      text-shadow: 0 0 6px rgba(0, 255, 102, 0.4);
    }
    .theme-matrix .btn-buy {
      background: #00FF66;
      color: #05080C;
      font-weight: 900;
      border-radius: 3px;
      letter-spacing: 1px;
      text-transform: uppercase;
      box-shadow: 0 0 15px rgba(0, 255, 102, 0.3);
    }
    .theme-matrix .btn-buy:hover {
      background: #33FF85;
      box-shadow: 0 0 25px rgba(0, 255, 102, 0.5);
    }
    .theme-matrix .bottom-nav-bar {
      background: #05080C;
      border: 1px solid #1F3628;
      color: #3B664B;
    }
    .theme-matrix .nav-item.active {
      color: #00FF66;
    }
    .theme-matrix .input-phone-group {
      background: #05080C;
      border: 1px solid #1F3628;
      color: #00FF66;
      border-radius: 4px;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-matrix .input-phone-group:focus-within {
      border-color: #00FF66;
      box-shadow: 0 0 10px rgba(0, 255, 102, 0.3);
    }
    .theme-matrix .phone-prefix {
      background: #080C10;
      border-right: 1px solid #1F3628;
      color: #00FF66;
    }
    .theme-matrix .input-contact-icon {
      color: #00FF66;
    }
    .theme-matrix .select-location {
      background: #05080C;
      border: 1px solid #1F3628;
      color: #00FF66;
      border-radius: 4px;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-matrix .select-location option {
      background: #05080C;
      color: #00FF66;
    }
    .theme-matrix .modal-sheet:not(.fintech-checkout-sheet) {
      background: #05080C;
      color: #00FF66;
      border: 1px solid #00FF66;
      box-shadow: 0 0 25px rgba(0, 255, 102, 0.2);
    }
    .theme-matrix .modal-close {
      background: #0D1117;
      color: #00FF66;
    }
    .theme-matrix #input_check_code {
      background: #05080C !important;
      border: 1px solid #1F3628 !important;
      color: #00FF66 !important;
    }

    /* THEME 13: LUXURY (CHAMPAGNE GOLD VIP / NOIR ROYALE) */
    body.theme-luxury {
      background: #0A0A0C;
      color: #F5E6C8;
    }
    .theme-luxury .hero-card {
      background: linear-gradient(135deg, #181510 0%, #0D0B08 100%);
      border: 1.5px solid #D4AF37;
      border-radius: 16px;
      padding: 22px;
      color: #F5E6C8;
      box-shadow: 0 10px 30px rgba(212, 175, 55, 0.15);
    }
    .theme-luxury .card {
      background: #12100C;
      border: 1px solid #3A3222;
      border-radius: 14px;
      padding: 16px;
    }
    .theme-luxury .luxury-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .theme-luxury .pkg-card {
      background: #12100C;
      border: 1.5px solid #3A3222;
      border-radius: 12px;
      padding: 16px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.2s ease;
    }
    .theme-luxury .pkg-card:hover {
      border-color: #ECC875;
      box-shadow: 0 6px 20px rgba(212, 175, 55, 0.2);
    }
    .theme-luxury .pkg-card.selected {
      border-color: #D4AF37;
      background: #1C1810;
      box-shadow: 0 0 20px rgba(212, 175, 55, 0.35);
    }
    .theme-luxury .vip-badge {
      font-size: 9.5px;
      font-weight: 800;
      color: #D4AF37;
      background: rgba(212, 175, 55, 0.12);
      border: 1px solid rgba(212, 175, 55, 0.3);
      padding: 2px 6px;
      border-radius: 4px;
      letter-spacing: 0.5px;
    }
    .theme-luxury .pkg-price {
      font-size: 16.5px;
      font-weight: 800;
      color: #ECC875;
    }
    .theme-luxury .btn-buy {
      background: linear-gradient(135deg, #D4AF37 0%, #AA8010 100%);
      color: #0A0A0C;
      font-weight: 800;
      border: none;
      box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
    }
    .theme-luxury .btn-buy:hover {
      background: linear-gradient(135deg, #ECC875 0%, #C59815 100%);
    }
    .theme-luxury .bottom-nav-bar {
      background: rgba(18, 16, 12, 0.95);
      border: 1px solid #3A3222;
      color: #8C7B5D;
    }
    .theme-luxury .nav-item.active {
      color: #ECC875;
    }
    .theme-luxury .input-phone-group {
      background: #12100C;
      border: 1px solid #3A3222;
      color: #F5E6C8;
      border-radius: 12px;
    }
    .theme-luxury .input-phone-group:focus-within {
      border-color: #D4AF37;
      box-shadow: 0 0 12px rgba(212, 175, 55, 0.25);
    }
    .theme-luxury .phone-prefix {
      background: #181510;
      border-right: 1px solid #3A3222;
      color: #D4AF37;
    }
    .theme-luxury .input-contact-icon {
      color: #D4AF37;
    }
    .theme-luxury .select-location {
      background: #12100C;
      border: 1px solid #3A3222;
      color: #F5E6C8;
      border-radius: 10px;
    }
    .theme-luxury .select-location option {
      background: #12100C;
      color: #F5E6C8;
    }
    .theme-luxury .modal-sheet:not(.fintech-checkout-sheet) {
      background: #12100C;
      color: #F5E6C8;
      border: 1.5px solid #D4AF37;
      box-shadow: 0 10px 35px rgba(212, 175, 55, 0.2);
    }
    .theme-luxury .modal-close {
      background: #181510;
      color: #D4AF37;
    }
    .theme-luxury #input_check_code {
      background: #0D0B08 !important;
      border: 1px solid #3A3222 !important;
      color: #F5E6C8 !important;
    }

    /* THEME 14: GAMING (APEX ESPORTS RANK TIERS) */
    body.theme-gaming {
      background: #0E1015;
      color: #F1F5F9;
    }
    .theme-gaming .hero-card {
      background: linear-gradient(135deg, #1E1B4B 0%, #0F172A 100%);
      border: 1.5px solid #6366F1;
      border-radius: 16px;
      padding: 20px;
      color: #FFFFFF;
      box-shadow: 0 10px 30px rgba(99, 102, 241, 0.25);
    }
    .theme-gaming .card {
      background: #151821;
      border: 1px solid #232936;
      border-radius: 14px;
      padding: 16px;
    }
    .theme-gaming .gaming-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .theme-gaming .pkg-card {
      background: #151821;
      border: 1.5px solid #232936;
      border-radius: 12px;
      padding: 14px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.2s ease;
    }
    .theme-gaming .pkg-card:hover {
      border-color: #818CF8;
      box-shadow: 0 6px 20px rgba(99, 102, 241, 0.2);
    }
    .theme-gaming .pkg-card.selected {
      border-color: #818CF8;
      background: #1E2230;
      box-shadow: 0 0 18px rgba(129, 140, 248, 0.35);
    }
    .theme-gaming .rank-badge {
      font-size: 9.5px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      gap: 3px;
    }
    .theme-gaming .pkg-price {
      font-size: 16px;
      font-weight: 800;
      color: #818CF8;
    }
    .theme-gaming .btn-buy {
      background: linear-gradient(135deg, #6366F1 0%, #A855F7 100%);
      color: #FFFFFF;
      font-weight: 800;
      border: none;
      box-shadow: 0 8px 25px rgba(99, 102, 241, 0.35);
    }
    .theme-gaming .btn-buy:hover {
      background: linear-gradient(135deg, #4F46E5 0%, #9333EA 100%);
    }
    .theme-gaming .bottom-nav-bar {
      background: rgba(21, 24, 33, 0.95);
      border: 1px solid #232936;
      color: #64748B;
    }
    .theme-gaming .nav-item.active {
      color: #818CF8;
    }
    .theme-gaming .input-phone-group {
      background: #151821;
      border: 1px solid #232936;
      color: #F1F5F9;
      border-radius: 12px;
    }
    .theme-gaming .input-phone-group:focus-within {
      border-color: #818CF8;
      box-shadow: 0 0 12px rgba(99, 102, 241, 0.3);
    }
    .theme-gaming .phone-prefix {
      background: #1E2230;
      border-right: 1px solid #232936;
      color: #818CF8;
    }
    .theme-gaming .input-contact-icon {
      color: #818CF8;
    }
    .theme-gaming .select-location {
      background: #151821;
      border: 1px solid #232936;
      color: #F1F5F9;
      border-radius: 10px;
    }
    .theme-gaming .select-location option {
      background: #151821;
      color: #F1F5F9;
    }
    .theme-gaming .modal-sheet:not(.fintech-checkout-sheet) {
      background: #151821;
      color: #F1F5F9;
      border: 1.5px solid #6366F1;
      box-shadow: 0 10px 35px rgba(99, 102, 241, 0.3);
    }
    .theme-gaming .modal-close {
      background: #232936;
      color: #F1F5F9;
    }
    .theme-gaming #input_check_code {
      background: #0E1015 !important;
      border: 1px solid #232936 !important;
      color: #F1F5F9 !important;
    }

    /* THEME 15: RETRO (90s CLASSIC OS WINDOW) */
    body.theme-retro {
      background: #008080;
      color: #000000;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .theme-retro .hero-card {
      background: #C0C0C0;
      border-top: 2px solid #FFFFFF;
      border-left: 2px solid #FFFFFF;
      border-right: 2px solid #000000;
      border-bottom: 2px solid #000000;
      padding: 3px;
      color: #000000;
    }
    .theme-retro .retro-titlebar {
      background: linear-gradient(90deg, #000080, #1084D0);
      color: #FFFFFF;
      padding: 3px 6px;
      font-weight: 700;
      font-size: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    .theme-retro .card {
      background: #C0C0C0;
      border-top: 2px solid #FFFFFF;
      border-left: 2px solid #FFFFFF;
      border-right: 2px solid #000000;
      border-bottom: 2px solid #000000;
      padding: 12px;
      margin-bottom: 10px;
    }
    .theme-retro .retro-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .theme-retro .pkg-card {
      background: #FFFFFF;
      border-top: 2px solid #808080;
      border-left: 2px solid #808080;
      border-right: 2px solid #DFDFDF;
      border-bottom: 2px solid #DFDFDF;
      padding: 10px 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: #000000;
    }
    .theme-retro .pkg-card.selected {
      background: #000080;
      color: #FFFFFF;
      border-top: 2px solid #000000;
      border-left: 2px solid #000000;
      border-right: 2px solid #808080;
      border-bottom: 2px solid #808080;
    }
    .theme-retro .pkg-price {
      font-size: 15px;
      font-weight: 800;
      color: inherit;
    }
    .theme-retro .btn-buy {
      background: #C0C0C0;
      border-top: 2px solid #FFFFFF;
      border-left: 2px solid #FFFFFF;
      border-right: 2px solid #000000;
      border-bottom: 2px solid #000000;
      color: #000000;
      font-weight: 800;
      border-radius: 0;
    }
    .theme-retro .btn-buy:active {
      border-top: 2px solid #000000;
      border-left: 2px solid #000000;
      border-right: 2px solid #FFFFFF;
      border-bottom: 2px solid #FFFFFF;
    }
    .theme-retro .bottom-nav-bar {
      background: #C0C0C0;
      border-top: 2px solid #FFFFFF;
      border-left: 2px solid #FFFFFF;
      border-right: 2px solid #000000;
      border-bottom: 2px solid #000000;
      color: #000000;
    }
    .theme-retro .nav-item.active {
      font-weight: 800;
      color: #000080;
    }
    .theme-retro .input-phone-group {
      background: #FFFFFF;
      border-top: 2px solid #808080;
      border-left: 2px solid #808080;
      border-right: 2px solid #DFDFDF;
      border-bottom: 2px solid #DFDFDF;
      color: #000000;
      border-radius: 0;
    }
    .theme-retro .phone-prefix {
      background: #C0C0C0;
      border-right: 2px solid #808080;
      color: #000080;
      font-weight: 700;
    }
    .theme-retro .input-contact-icon {
      color: #000080;
    }
    .theme-retro .select-location {
      background: #FFFFFF;
      border-top: 2px solid #808080;
      border-left: 2px solid #808080;
      border-right: 2px solid #DFDFDF;
      border-bottom: 2px solid #DFDFDF;
      color: #000000;
      border-radius: 0;
    }
    .theme-retro .select-location option {
      background: #FFFFFF;
      color: #000000;
    }
    .theme-retro .modal-sheet:not(.fintech-checkout-sheet) {
      background: #C0C0C0;
      color: #000000;
      border-top: 2px solid #FFFFFF;
      border-left: 2px solid #FFFFFF;
      border-right: 2px solid #000000;
      border-bottom: 2px solid #000000;
      border-radius: 0;
    }
    .theme-retro .modal-close {
      background: #C0C0C0;
      border-top: 2px solid #FFFFFF;
      border-left: 2px solid #FFFFFF;
      border-right: 2px solid #000000;
      border-bottom: 2px solid #000000;
      color: #000000;
      border-radius: 0;
    }
    .theme-retro #input_check_code {
      background: #FFFFFF !important;
      border-top: 2px solid #808080 !important;
      border-left: 2px solid #808080 !important;
      border-right: 2px solid #DFDFDF !important;
      border-bottom: 2px solid #DFDFDF !important;
      color: #000000 !important;
      border-radius: 0 !important;
    }

    /* THEME 16: NORDIC (WARM HYGGE LINEN MINIMALIST) */
    body.theme-nordic {
      background: #FAF8F5;
      color: #2C2623;
    }
    .theme-nordic .hero-card {
      background: #EFECE6;
      border-radius: 20px;
      padding: 22px;
      color: #2C2623;
      border: 1px solid #E0DBD1;
    }
    .theme-nordic .card {
      background: #FFFFFF;
      border: 1px solid #E5E0D8;
      border-radius: 18px;
      padding: 18px;
      box-shadow: 0 2px 10px rgba(44, 38, 35, 0.03);
    }
    .theme-nordic .nordic-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .theme-nordic .pkg-card {
      background: #FFFFFF;
      border: 1.5px solid #E5E0D8;
      border-radius: 16px;
      padding: 16px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.2s ease;
    }
    .theme-nordic .pkg-card:hover {
      border-color: #5F7A61;
      transform: translateY(-2px);
    }
    .theme-nordic .pkg-card.selected {
      border-color: #5F7A61;
      background: #F4F7F4;
      box-shadow: 0 0 0 2px #5F7A61;
    }
    .theme-nordic .nordic-leaf-tag {
      font-size: 10px;
      font-weight: 700;
      color: #5F7A61;
      background: #EAF0EB;
      padding: 2px 7px;
      border-radius: 999px;
    }
    .theme-nordic .pkg-price {
      font-size: 16px;
      font-weight: 800;
      color: #5F7A61;
    }
    .theme-nordic .btn-buy {
      background: #2C2623;
      color: #FAF8F5;
      font-weight: 700;
      border-radius: 14px;
      border: none;
    }
    .theme-nordic .btn-buy:hover {
      background: #443B37;
    }
    .theme-nordic .bottom-nav-bar {
      background: rgba(250, 248, 245, 0.95);
      border: 1px solid #E5E0D8;
      color: #786C65;
    }
    .theme-nordic .nav-item.active {
      color: #5F7A61;
    }
    .theme-nordic .input-phone-group {
      background: #FFFFFF;
      border: 1.5px solid #E5E0D8;
      color: #2C2623;
      border-radius: 14px;
    }
    .theme-nordic .input-phone-group:focus-within {
      border-color: #5F7A61;
      box-shadow: 0 0 0 1px #5F7A61;
    }
    .theme-nordic .phone-prefix {
      background: #EFECE6;
      border-right: 1px solid #E5E0D8;
      color: #5F7A61;
    }
    .theme-nordic .input-contact-icon {
      color: #5F7A61;
    }
    .theme-nordic .select-location {
      background: #FFFFFF;
      border: 1.5px solid #E5E0D8;
      color: #2C2623;
      border-radius: 12px;
    }
    .theme-nordic .select-location option {
      background: #FFFFFF;
      color: #2C2623;
    }
    .theme-nordic .modal-sheet:not(.fintech-checkout-sheet) {
      background: #FAF8F5;
      color: #2C2623;
      border: 1px solid #E5E0D8;
    }
    .theme-nordic .modal-close {
      background: #EFECE6;
      color: #2C2623;
    }
    .theme-nordic #input_check_code {
      background: #FFFFFF !important;
      border: 1.5px solid #E5E0D8 !important;
      color: #2C2623 !important;
    }

    /* COMMON FORM & WIDGETS */
    .input-label {
      font-size: 12.5px;
      font-weight: 700;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .input-phone-group {
      display: flex;
      align-items: center;
      border: 1.5px solid #CBD5E1;
      border-radius: 12px;
      overflow: hidden;
      background: #FFFFFF;
      transition: border-color 0.15s;
    }
    .input-phone-group:focus-within {
      border-color: #0284C7;
    }
    .phone-prefix {
      padding: 12px 14px;
      font-size: 14px;
      font-weight: 700;
      background: #F1F5F9;
      border-right: 1px solid #CBD5E1;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .input-phone-group input {
      flex: 1;
      border: none !important;
      padding: 12px 14px;
      font-size: 15px;
      font-weight: 600;
      outline: none;
      background: transparent !important;
      color: inherit !important;
      -webkit-text-fill-color: inherit !important;
      box-shadow: none !important;
      width: 100%;
    }
    .select-location {
      width: 100%;
      padding: 11px 14px;
      border-radius: 10px;
      border: 1.5px solid var(--theme-border, #CBD5E1);
      font-size: 13.5px;
      font-weight: 600;
      outline: none;
      background: var(--theme-card-bg, #FFFFFF);
      color: var(--theme-text, #1E293B);
      cursor: pointer;
    }
    .select-location option {
      background: #FFFFFF;
      color: #1E293B;
    }
    /* BASE & PER-THEME SORT BUTTON ADAPTATIONS (16 THEMES) */
    .sort-icon-btn {
      padding: 5px 11px;
      font-size: 11.5px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      user-select: none;
      border: 1px solid currentColor;
      background: transparent;
    }
    .sort-icon-btn:active {
      transform: scale(0.96);
    }
    
    /* 1. Theme Standard (Neo Cards Bento) */
    .theme-standard .sort-icon-btn {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      color: #0284C7;
      border-radius: 999px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .theme-standard .sort-icon-btn:hover, .theme-standard .sort-icon-btn.active {
      background: #0284C7;
      border-color: #0284C7;
      color: #FFFFFF;
      box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3);
    }

    /* 2. Theme Stripe (Clean Minimal) */
    .theme-stripe .sort-icon-btn {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      color: #6366F1;
      border-radius: 6px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .theme-stripe .sort-icon-btn:hover, .theme-stripe .sort-icon-btn.active {
      background: #6366F1;
      border-color: #6366F1;
      color: #FFFFFF;
    }

    /* 3. Theme Linear (Hero Dark) */
    .theme-linear .sort-icon-btn {
      background: #111827;
      border: 1px solid #1F2937;
      color: #60A5FA;
      border-radius: 8px;
    }
    .theme-linear .sort-icon-btn:hover, .theme-linear .sort-icon-btn.active {
      background: #1E293B;
      border-color: #3B82F6;
      color: #93C5FD;
      box-shadow: 0 0 10px rgba(59, 130, 246, 0.25);
    }

    /* 4. Theme Voucher (Perforated Ticket) */
    .theme-voucher .sort-icon-btn {
      background: #FFFFFF;
      border: 1px dashed #CBD5E1;
      color: #E11D48;
      border-radius: 6px;
    }
    .theme-voucher .sort-icon-btn:hover, .theme-voucher .sort-icon-btn.active {
      background: #FFF1F2;
      border-color: #E11D48;
      color: #BE123C;
    }

    /* 5. Theme Obsidian (Cyber Glass) */
    .theme-obsidian .sort-icon-btn {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #38BDF8;
      backdrop-filter: blur(10px);
      border-radius: 10px;
    }
    .theme-obsidian .sort-icon-btn:hover, .theme-obsidian .sort-icon-btn.active {
      background: rgba(56, 189, 248, 0.15);
      border-color: #38BDF8;
      color: #E0F2FE;
      box-shadow: 0 0 12px rgba(56, 189, 248, 0.3);
    }

    /* 6. Theme Aurora (Vibrant Gradient) */
    .theme-aurora .sort-icon-btn {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.18);
      color: #6EE7B7;
      backdrop-filter: blur(8px);
      border-radius: 999px;
    }
    .theme-aurora .sort-icon-btn:hover, .theme-aurora .sort-icon-btn.active {
      background: linear-gradient(90deg, #8B5CF6, #10B981);
      border-color: transparent;
      color: #FFFFFF;
      box-shadow: 0 0 14px rgba(16, 185, 129, 0.35);
    }

    /* 7. Theme Emerald (Fintech Trust) */
    .theme-emerald .sort-icon-btn {
      background: #0B2920;
      border: 1px solid #059669;
      color: #34D399;
      border-radius: 8px;
    }
    .theme-emerald .sort-icon-btn:hover, .theme-emerald .sort-icon-btn.active {
      background: #059669;
      border-color: #10B981;
      color: #FFFFFF;
      box-shadow: 0 0 12px rgba(5, 150, 105, 0.4);
    }

    /* 8. Theme Cyberpunk (Hi-Tech Neon) */
    .theme-cyberpunk .sort-icon-btn {
      background: #18181B;
      border: 1.5px solid #FACC15;
      color: #FACC15;
      border-radius: 0px;
      font-family: monospace;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .theme-cyberpunk .sort-icon-btn:hover, .theme-cyberpunk .sort-icon-btn.active {
      background: #FACC15;
      color: #000000;
      box-shadow: 0 0 14px #FACC15;
    }

    /* 9. Theme Swiss (Editorial Precision) */
    .theme-swiss .sort-icon-btn {
      background: #FFFFFF;
      border: 2px solid #000000;
      color: #000000;
      border-radius: 0px;
      font-weight: 900;
      text-transform: uppercase;
    }
    .theme-swiss .sort-icon-btn:hover, .theme-swiss .sort-icon-btn.active {
      background: #000000;
      border-color: #000000;
      color: #FFFFFF;
    }

    /* 10. Theme Sunset (Warm Amber) */
    .theme-sunset .sort-icon-btn {
      background: #FFF7ED;
      border: 1px solid #FDBA74;
      color: #EA580C;
      border-radius: 8px;
    }
    .theme-sunset .sort-icon-btn:hover, .theme-sunset .sort-icon-btn.active {
      background: #EA580C;
      border-color: #EA580C;
      color: #FFFFFF;
      box-shadow: 0 2px 8px rgba(234, 88, 12, 0.3);
    }

    /* 11. Theme Gaming (Esports Crimson) */
    .theme-gaming .sort-icon-btn {
      background: #1C0B10;
      border: 1px solid #E11D48;
      color: #FB7185;
      border-radius: 6px;
      font-weight: 800;
      text-transform: uppercase;
    }
    .theme-gaming .sort-icon-btn:hover, .theme-gaming .sort-icon-btn.active {
      background: #E11D48;
      border-color: #F43F5E;
      color: #FFFFFF;
      box-shadow: 0 0 12px rgba(225, 29, 72, 0.5);
    }

    /* 12. Theme Nordic (Scandinavian Sand) */
    .theme-nordic .sort-icon-btn {
      background: #E7E5E4;
      border: 1px solid #D6D3D1;
      color: #292524;
      border-radius: 6px;
      font-weight: 600;
    }
    .theme-nordic .sort-icon-btn:hover, .theme-nordic .sort-icon-btn.active {
      background: #44403C;
      border-color: #44403C;
      color: #FFFFFF;
    }

    /* 13. Theme Matrix (Terminal Hacker) */
    .theme-matrix .sort-icon-btn {
      background: rgba(34, 197, 94, 0.06);
      border: 1px solid #22C55E;
      color: #22C55E;
      border-radius: 2px;
      font-family: monospace;
      font-weight: 700;
    }
    .theme-matrix .sort-icon-btn:hover, .theme-matrix .sort-icon-btn.active {
      background: #22C55E;
      border-color: #22C55E;
      color: #020804;
      box-shadow: 0 0 10px rgba(34, 197, 94, 0.6);
    }

    /* 14. Theme Luxury (Royal VIP Gold) */
    .theme-luxury .sort-icon-btn {
      background: #131B2E;
      border: 1px solid #D4AF37;
      color: #D4AF37;
      border-radius: 8px;
      font-weight: 800;
      letter-spacing: 0.3px;
    }
    .theme-luxury .sort-icon-btn:hover, .theme-luxury .sort-icon-btn.active {
      background: #D4AF37;
      border-color: #D4AF37;
      color: #090D16;
      box-shadow: 0 0 12px rgba(212, 175, 55, 0.4);
    }

    /* 15. Theme Neumorphic (Soft 3D Tactile) */
    .theme-neumorphic .sort-icon-btn {
      background: #E0E5EC;
      box-shadow: 3px 3px 6px #B8B9BE, -3px -3px 6px #FFFFFF;
      border: 1px solid rgba(255, 255, 255, 0.4);
      color: #2563EB;
      border-radius: 10px;
    }
    .theme-neumorphic .sort-icon-btn:active, .theme-neumorphic .sort-icon-btn.active {
      box-shadow: inset 2px 2px 4px #B8B9BE, inset -2px -2px 4px #FFFFFF;
      color: #1D4ED8;
    }

    /* 16. Theme Retro (Windows 98) */
    .theme-retro .sort-icon-btn {
      background: #C0C0C0;
      border: 2px outset #FFFFFF;
      color: #000000;
      border-radius: 0px;
      font-family: Tahoma, sans-serif;
      font-weight: bold;
      box-shadow: 1px 1px 0px #000000;
    }
    .theme-retro .sort-icon-btn:active, .theme-retro .sort-icon-btn.active {
      border-style: inset;
      padding-top: 6px;
      padding-bottom: 4px;
    }
    .btn-buy-block {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: opacity 0.15s, transform 0.1s;
    }
    .btn-buy-block:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .btn-buy-block:not(:disabled):active {
      transform: scale(0.98);
    }
    
    /* TOP FLOATING NOTIFICATION TICKER */
    .top-floating-ticker {
      position: fixed;
      top: 16px;
      left: 50%;
      transform: translateX(-50%) translateY(-120px);
      opacity: 0;
      visibility: hidden;
      z-index: 1000;
      background: rgba(15, 23, 42, 0.94);
      color: #FFFFFF;
      padding: 8px 16px;
      border-radius: 999px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.12);
      font-size: 11.5px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease, visibility 0.4s;
      pointer-events: none;
      white-space: nowrap;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      max-width: 92vw;
      box-sizing: border-box;
    }
    .top-floating-ticker.visible {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
      visibility: visible;
    }
    .ticker-pulse-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #10B981;
      box-shadow: 0 0 8px #10B981;
      flex-shrink: 0;
      animation: pulseDot 2s infinite;
    }
    @keyframes pulseDot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.35; transform: scale(0.85); }
    }

    
    /* THEME-SPECIFIC FLOATING TICKER STYLES (ALL 16 THEMES) */
    body.theme-standard .top-floating-ticker {
      background: rgba(255, 255, 255, 0.96);
      color: #0F172A;
      border: 1px solid #E2E8F0;
      box-shadow: 0 10px 25px rgba(0, 132, 227, 0.15);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    body.theme-standard .top-floating-ticker i {
      color: #0084E3 !important;
    }

    body.theme-stripe .top-floating-ticker {
      background: rgba(255, 255, 255, 0.96);
      color: #0A2540;
      border: 1px solid #E3E8EE;
      box-shadow: 0 8px 24px rgba(99, 91, 255, 0.15);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    body.theme-stripe .top-floating-ticker i {
      color: #635BFF !important;
    }

    body.theme-linear .top-floating-ticker {
      background: rgba(18, 19, 26, 0.94);
      color: #F1F5F9;
      border: 1px solid rgba(255, 255, 255, 0.15);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }
    body.theme-linear .top-floating-ticker i {
      color: #818CF8 !important;
    }

    body.theme-voucher .top-floating-ticker {
      background: #FEF3C7;
      color: #92400E;
      border: 1.5px dashed #F59E0B;
      box-shadow: 0 8px 20px rgba(217, 119, 6, 0.15);
    }
    body.theme-voucher .top-floating-ticker i {
      color: #D97706 !important;
    }

    body.theme-obsidian .top-floating-ticker {
      background: rgba(7, 9, 14, 0.94);
      color: #38BDF8;
      border: 1px solid rgba(56, 189, 248, 0.4);
      box-shadow: 0 0 25px rgba(56, 189, 248, 0.3);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      text-shadow: 0 0 8px rgba(56, 189, 248, 0.4);
    }
    body.theme-obsidian .top-floating-ticker i {
      color: #00F5FF !important;
    }

    body.theme-aurora .top-floating-ticker {
      background: rgba(15, 23, 42, 0.92);
      color: #F8FAFC;
      border: 1px solid rgba(192, 132, 252, 0.4);
      box-shadow: 0 0 25px rgba(192, 132, 252, 0.3);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }
    body.theme-aurora .top-floating-ticker i {
      color: #C084FC !important;
    }

    body.theme-emerald .top-floating-ticker {
      background: #FFFFFF;
      color: #065F46;
      border: 1px solid #A7F3D0;
      box-shadow: 0 8px 24px rgba(5, 150, 105, 0.15);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    body.theme-emerald .top-floating-ticker i {
      color: #059669 !important;
    }

    body.theme-cyberpunk .top-floating-ticker {
      background: #080C10;
      color: #00FF88;
      border: 1px solid #00FF88;
      box-shadow: 0 0 20px rgba(0, 255, 136, 0.35);
      text-shadow: 0 0 5px rgba(0, 255, 136, 0.5);
    }
    body.theme-cyberpunk .top-floating-ticker i {
      color: #00FF88 !important;
    }

    body.theme-swiss .top-floating-ticker {
      background: #111111;
      color: #FFFFFF;
      border: 2px solid #FF4400;
      box-shadow: 4px 4px 0px #111111;
      border-radius: 0;
    }
    body.theme-swiss .top-floating-ticker i {
      color: #FF4400 !important;
    }

    body.theme-sunset .top-floating-ticker {
      background: #FFFFFF;
      color: #7C2D12;
      border: 1px solid #FED7AA;
      box-shadow: 0 8px 24px rgba(249, 115, 22, 0.18);
    }
    body.theme-sunset .top-floating-ticker i {
      color: #F97316 !important;
    }

    body.theme-neumorphic .top-floating-ticker {
      background: #E8EDF5;
      color: #334155;
      border: 1px solid #CBD5E1;
      box-shadow: 4px 4px 10px #cad3e0, -4px -4px 10px #ffffff;
    }
    body.theme-neumorphic .top-floating-ticker i {
      color: #3B82F6 !important;
    }

    body.theme-matrix .top-floating-ticker {
      background: rgba(0, 0, 0, 0.95);
      color: #00FF66;
      border: 1px solid #00FF66;
      box-shadow: 0 0 20px rgba(0, 255, 102, 0.35);
      font-family: 'JetBrains Mono', monospace;
      text-shadow: 0 0 5px rgba(0, 255, 102, 0.5);
    }
    body.theme-matrix .top-floating-ticker i {
      color: #00FF66 !important;
    }

    body.theme-luxury .top-floating-ticker {
      background: rgba(9, 13, 22, 0.95);
      color: #ECC875;
      border: 1px solid #D4AF37;
      box-shadow: 0 10px 30px rgba(212, 175, 55, 0.25);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }
    body.theme-luxury .top-floating-ticker i {
      color: #D4AF37 !important;
    }

    body.theme-gaming .top-floating-ticker {
      background: rgba(24, 24, 27, 0.95);
      color: #E4E4E7;
      border: 1px solid #8B5CF6;
      box-shadow: 0 0 25px rgba(139, 92, 246, 0.35);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }
    body.theme-gaming .top-floating-ticker i {
      color: #F59E0B !important;
    }

    body.theme-retro .top-floating-ticker {
      background: #000080;
      color: #FFFFFF;
      border: 2px solid #DFDFDF;
      box-shadow: 3px 3px 0px #000000;
      border-radius: 0;
      font-family: 'JetBrains Mono', monospace;
    }
    body.theme-retro .top-floating-ticker i {
      color: #FFFF00 !important;
    }

    body.theme-nordic .top-floating-ticker {
      background: rgba(253, 251, 247, 0.96);
      color: #292524;
      border: 1px solid #D6D3D1;
      box-shadow: 0 8px 24px rgba(41, 37, 36, 0.08);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }
    body.theme-nordic .top-floating-ticker i {
      color: #78716C !important;
    }

    /* BOTTOM FLOATING NAV DOCK */
    .bottom-nav-bar {
      position: fixed;
      bottom: 12px;
      left: 50%;
      transform: translateX(-50%);
      width: calc(100% - 24px);
      max-width: 520px;
      height: 60px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: space-around;
      backdrop-filter: blur(12px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
      z-index: 900;
    }
    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-size: 10.5px;
      font-weight: 700;
      text-decoration: none;
      color: inherit;
      opacity: 0.7;
      cursor: pointer;
      gap: 3px;
    }
    .nav-item i {
      font-size: 16px;
    }
    .nav-item.active {
      opacity: 1;
    }

        /* MODAL SHEETS & FINTECH CHECKOUT */
    .modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100vh;
      height: 100dvh;
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: 99999;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 10px;
      box-sizing: border-box;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }
    .modal-sheet {
      background: #FFFFFF;
      color: #0F172A;
      width: 100%;
      max-width: 440px;
      border-radius: 20px;
      padding: 22px;
      position: relative;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
      animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box;
    }
    .fintech-checkout-sheet {
      padding: 0 !important;
      max-width: 360px !important;
      width: 100% !important;
      border-radius: 20px !important;
      overflow: hidden !important;
      max-height: calc(100dvh - 32px) !important;
      max-height: calc(100vh - 32px) !important;
      display: flex !important;
      flex-direction: column !important;
      border: 1px solid #E2E8F0 !important;
      box-sizing: border-box !important;
      box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3) !important;
      margin: auto !important;
    }
    @keyframes modalPop {
      from { transform: scale(0.92); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: #F1F5F9;
      border: none;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      font-size: 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748B;
      transition: all 0.15s ease;
    }
    .modal-close:hover {
      background: #E2E8F0;
      color: #0F172A;
    }
  
    /* RESPONSIVE MOBILE FIX FOR CHECKOUT & QRIS */
    @media (max-width: 480px) {
      .modal-backdrop {
        padding: 14px 12px !important;
      }
      .modal-sheet {
        padding: 16px !important;
        border-radius: 18px !important;
      }
      .fintech-checkout-sheet {
        max-width: 350px !important;
        width: 100% !important;
        max-height: calc(100dvh - 24px) !important;
        max-height: calc(100vh - 24px) !important;
        border-radius: 20px !important;
      }
      #qris_canvas, #qris_img {
        width: 175px !important;
        max-width: 100% !important;
        height: 175px !important;
        aspect-ratio: 1 / 1 !important;
      }
    }
    
  
    /* RESUME PAYMENT FLOATING DOCK STYLING PER THEME */
    .resume-payment-dock {
      display: none;
      position: fixed;
      bottom: 76px;
      left: 50%;
      transform: translateX(-50%);
      width: calc(100% - 32px);
      max-width: 440px;
      z-index: 99;
      padding: 11px 14px;
      border-radius: 14px;
      animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      transition: all 0.2s ease;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }
    .theme-standard .resume-payment-dock {
      background: #FFFFFF;
      border: 1.5px solid #0284C7;
      color: #0F172A;
      box-shadow: 0 10px 30px -5px rgba(2, 132, 199, 0.3);
    }
    .theme-standard .resume-dock-icon { background: #0284C7; color: #FFFFFF; }
    .theme-standard .resume-dock-title { color: #0F172A; }
    .theme-standard .resume-dock-sub { color: #64748B; }
    .theme-standard .resume-dock-amount { color: #0284C7; }
    .theme-standard .resume-dock-btn { background: #0284C7; color: #FFFFFF; }
    .theme-standard .resume-dock-close { color: #94A3B8; }

    .theme-stripe .resume-payment-dock {
      background: #FFFFFF;
      border: 1.5px solid #6366F1;
      color: #111827;
      box-shadow: 0 10px 30px -5px rgba(99, 102, 241, 0.25);
    }
    .theme-stripe .resume-dock-icon { background: #6366F1; color: #FFFFFF; }
    .theme-stripe .resume-dock-title { color: #111827; }
    .theme-stripe .resume-dock-sub { color: #6B7280; }
    .theme-stripe .resume-dock-amount { color: #6366F1; }
    .theme-stripe .resume-dock-btn { background: #6366F1; color: #FFFFFF; }
    .theme-stripe .resume-dock-close { color: #9CA3AF; }

    .theme-linear .resume-payment-dock {
      background: rgba(24, 24, 27, 0.95);
      border: 1.5px solid #3F3F46;
      color: #FAFAFA;
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.7);
    }
    .theme-linear .resume-dock-icon { background: #27272A; color: #FAFAFA; border: 1px solid #3F3F46; }
    .theme-linear .resume-dock-title { color: #FAFAFA; }
    .theme-linear .resume-dock-sub { color: #A1A1AA; }
    .theme-linear .resume-dock-amount { color: #FAFAFA; }
    .theme-linear .resume-dock-btn { background: #FAFAFA; color: #09090B; }
    .theme-linear .resume-dock-close { color: #71717A; }

    .theme-voucher .resume-payment-dock {
      background: #FFFBEB;
      border: 1.5px dashed #D97706;
      color: #292524;
      box-shadow: 0 10px 30px -5px rgba(217, 119, 6, 0.25);
    }
    .theme-voucher .resume-dock-icon { background: #D97706; color: #FFFFFF; }
    .theme-voucher .resume-dock-title { color: #78350F; }
    .theme-voucher .resume-dock-sub { color: #78716C; }
    .theme-voucher .resume-dock-amount { color: #B45309; }
    .theme-voucher .resume-dock-btn { background: #D97706; color: #FFFFFF; }
    .theme-voucher .resume-dock-close { color: #A8A29E; }

    .theme-obsidian .resume-payment-dock {
      background: rgba(13, 18, 31, 0.95);
      border: 1.5px solid #00C2FF;
      color: #F8FAFC;
      box-shadow: 0 12px 35px rgba(0, 194, 255, 0.25);
    }
    .theme-obsidian .resume-dock-icon { background: #00C2FF; color: #07090E; }
    .theme-obsidian .resume-dock-title { color: #F8FAFC; }
    .theme-obsidian .resume-dock-sub { color: #94A3B8; }
    .theme-obsidian .resume-dock-amount { color: #00C2FF; }
    .theme-obsidian .resume-dock-btn { background: #00C2FF; color: #07090E; }
    .theme-obsidian .resume-dock-close { color: #64748B; }

    .theme-aurora .resume-payment-dock {
      background: rgba(15, 23, 42, 0.95);
      border: 1.5px solid #C084FC;
      color: #F8FAFC;
      box-shadow: 0 12px 35px rgba(192, 132, 252, 0.25);
    }
    .theme-aurora .resume-dock-icon { background: #C084FC; color: #0F172A; }
    .theme-aurora .resume-dock-title { color: #F8FAFC; }
    .theme-aurora .resume-dock-sub { color: #94A3B8; }
    .theme-aurora .resume-dock-amount { color: #C084FC; }
    .theme-aurora .resume-dock-btn { background: #C084FC; color: #0F172A; }
    .theme-aurora .resume-dock-close { color: #64748B; }

    .theme-emerald .resume-payment-dock {
      background: #FFFFFF;
      border: 1.5px solid #059669;
      color: #065F46;
      box-shadow: 0 10px 30px -5px rgba(5, 150, 105, 0.25);
    }
    .theme-emerald .resume-dock-icon { background: #059669; color: #FFFFFF; }
    .theme-emerald .resume-dock-title { color: #065F46; }
    .theme-emerald .resume-dock-sub { color: #6B7280; }
    .theme-emerald .resume-dock-amount { color: #059669; }
    .theme-emerald .resume-dock-btn { background: #059669; color: #FFFFFF; }
    .theme-emerald .resume-dock-close { color: #9CA3AF; }

    .theme-cyberpunk .resume-payment-dock {
      background: rgba(8, 12, 16, 0.96);
      border: 1.5px solid #00FF88;
      color: #00FF88;
      box-shadow: 0 0 25px rgba(0, 255, 136, 0.35);
    }
    .theme-cyberpunk .resume-dock-icon { background: #00FF88; color: #080C10; }
    .theme-cyberpunk .resume-dock-title { color: #00FF88; }
    .theme-cyberpunk .resume-dock-sub { color: #A7F3D0; }
    .theme-cyberpunk .resume-dock-amount { color: #00FF88; }
    .theme-cyberpunk .resume-dock-btn { background: #00FF88; color: #080C10; font-weight: 800; }
    .theme-cyberpunk .resume-dock-close { color: #FF0055; }

    .theme-swiss .resume-payment-dock {
      background: #111111;
      border: 2px solid #FF4400;
      color: #FFFFFF;
      box-shadow: 4px 4px 0px #111111;
      border-radius: 0;
    }
    .theme-swiss .resume-dock-icon { background: #FF4400; color: #FFFFFF; border-radius: 0; }
    .theme-swiss .resume-dock-title { color: #FFFFFF; }
    .theme-swiss .resume-dock-sub { color: #D1D5DB; }
    .theme-swiss .resume-dock-amount { color: #FF4400; }
    .theme-swiss .resume-dock-btn { background: #FF4400; color: #FFFFFF; border-radius: 0; }
    .theme-swiss .resume-dock-close { color: #9CA3AF; }

    .theme-sunset .resume-payment-dock {
      background: #FFFFFF;
      border: 1.5px solid #F97316;
      color: #7C2D12;
      box-shadow: 0 10px 30px -5px rgba(249, 115, 22, 0.25);
    }
    .theme-sunset .resume-dock-icon { background: #F97316; color: #FFFFFF; }
    .theme-sunset .resume-dock-title { color: #7C2D12; }
    .theme-sunset .resume-dock-sub { color: #9A3412; }
    .theme-sunset .resume-dock-amount { color: #EA580C; }
    .theme-sunset .resume-dock-btn { background: #F97316; color: #FFFFFF; }
    .theme-sunset .resume-dock-close { color: #FB923C; }

    .theme-neumorphic .resume-payment-dock {
      background: #E8EDF5;
      border: 1.5px solid #CBD5E1;
      color: #334155;
      box-shadow: 4px 4px 12px #cad3e0, -4px -4px 12px #ffffff;
    }
    .theme-neumorphic .resume-dock-icon { background: #3B82F6; color: #FFFFFF; }
    .theme-neumorphic .resume-dock-title { color: #1E293B; }
    .theme-neumorphic .resume-dock-sub { color: #64748B; }
    .theme-neumorphic .resume-dock-amount { color: #2563EB; }
    .theme-neumorphic .resume-dock-btn { background: #3B82F6; color: #FFFFFF; box-shadow: 2px 2px 5px #cad3e0; }
    .theme-neumorphic .resume-dock-close { color: #94A3B8; }

    .theme-matrix .resume-payment-dock {
      background: rgba(0, 0, 0, 0.96);
      border: 1.5px solid #00FF66;
      color: #00FF66;
      box-shadow: 0 0 25px rgba(0, 255, 102, 0.35);
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-matrix .resume-dock-icon { background: #00FF66; color: #000000; }
    .theme-matrix .resume-dock-title { color: #00FF66; }
    .theme-matrix .resume-dock-sub { color: #86EFAC; }
    .theme-matrix .resume-dock-amount { color: #00FF66; }
    .theme-matrix .resume-dock-btn { background: #00FF66; color: #000000; font-weight: 800; }
    .theme-matrix .resume-dock-close { color: #EF4444; }

    .theme-luxury .resume-payment-dock {
      background: rgba(9, 13, 22, 0.96);
      border: 1.5px solid #D4AF37;
      color: #ECC875;
      box-shadow: 0 12px 35px rgba(212, 175, 55, 0.25);
    }
    .theme-luxury .resume-dock-icon { background: #D4AF37; color: #090D16; }
    .theme-luxury .resume-dock-title { color: #F8FAFC; }
    .theme-luxury .resume-dock-sub { color: #ECC875; }
    .theme-luxury .resume-dock-amount { color: #D4AF37; }
    .theme-luxury .resume-dock-btn { background: #D4AF37; color: #090D16; font-weight: 800; }
    .theme-luxury .resume-dock-close { color: #94A3B8; }

    .theme-gaming .resume-payment-dock {
      background: rgba(24, 24, 27, 0.96);
      border: 1.5px solid #8B5CF6;
      color: #E4E4E7;
      box-shadow: 0 0 25px rgba(139, 92, 246, 0.35);
    }
    .theme-gaming .resume-dock-icon { background: #8B5CF6; color: #FFFFFF; }
    .theme-gaming .resume-dock-title { color: #FFFFFF; }
    .theme-gaming .resume-dock-sub { color: #A1A1AA; }
    .theme-gaming .resume-dock-amount { color: #F59E0B; }
    .theme-gaming .resume-dock-btn { background: #8B5CF6; color: #FFFFFF; }
    .theme-gaming .resume-dock-close { color: #71717A; }

    .theme-retro .resume-payment-dock {
      background: #000080;
      border: 2px solid #DFDFDF;
      color: #FFFFFF;
      box-shadow: 4px 4px 0px #000000;
      border-radius: 0;
      font-family: 'JetBrains Mono', monospace;
    }
    .theme-retro .resume-dock-icon { background: #FFFF00; color: #000080; border-radius: 0; }
    .theme-retro .resume-dock-title { color: #FFFFFF; }
    .theme-retro .resume-dock-sub { color: #DFDFDF; }
    .theme-retro .resume-dock-amount { color: #FFFF00; }
    .theme-retro .resume-dock-btn { background: #DFDFDF; color: #000000; border-radius: 0; }
    .theme-retro .resume-dock-close { color: #FF6666; }

    .theme-nordic .resume-payment-dock {
      background: #FDFBF7;
      border: 1.5px solid #D6D3D1;
      color: #292524;
      box-shadow: 0 10px 30px -5px rgba(41, 37, 36, 0.15);
    }
    .theme-nordic .resume-dock-icon { background: #78716C; color: #FFFFFF; }
    .theme-nordic .resume-dock-title { color: #1C1917; }
    .theme-nordic .resume-dock-sub { color: #78716C; }
    .theme-nordic .resume-dock-amount { color: #44403C; }
    .theme-nordic .resume-dock-btn { background: #292524; color: #FDFBF7; }
    .theme-nordic .resume-dock-close { color: #A8A29E; }

    /* CONTACT PICKER THEME HARMONY (ALL 16 THEMES) */
    .btn-pick-contact {
      border-radius: 6px;
      padding: 3px 8px;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.15s ease;
    }
    .theme-standard .btn-pick-contact { background: #E0F2FE; color: #0284C7; border: 1px solid #BAE6FD; }
    .theme-standard .input-contact-icon { color: #0284C7; }

    .theme-stripe .btn-pick-contact { background: #EEF2FF; color: #6366F1; border: 1px solid #C7D2FE; }
    .theme-stripe .input-contact-icon { color: #6366F1; }

    .theme-linear .btn-pick-contact { background: #27272A; color: #FAFAFA; border: 1px solid #3F3F46; }
    .theme-linear .input-contact-icon { color: #FAFAFA; }

    .theme-voucher .btn-pick-contact { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
    .theme-voucher .input-contact-icon { color: #D97706; }

    .theme-obsidian .btn-pick-contact { background: rgba(0, 194, 255, 0.12); color: #00C2FF; border: 1px solid rgba(0, 194, 255, 0.3); }
    .theme-obsidian .input-contact-icon { color: #00C2FF; }

    .theme-aurora .btn-pick-contact { background: rgba(192, 132, 252, 0.12); color: #C084FC; border: 1px solid rgba(192, 132, 252, 0.3); }
    .theme-aurora .input-contact-icon { color: #C084FC; }

    .theme-emerald .btn-pick-contact { background: #DCFCE7; color: #059669; border: 1px solid #A7F3D0; }
    .theme-emerald .input-contact-icon { color: #059669; }

    .theme-cyberpunk .btn-pick-contact { background: rgba(0, 255, 136, 0.12); color: #00FF88; border: 1px solid rgba(0, 255, 136, 0.3); }
    .theme-cyberpunk .input-contact-icon { color: #00FF88; }

    .theme-swiss .btn-pick-contact { background: #FF4400; color: #FFFFFF; border: 1px solid #FF4400; }
    .theme-swiss .input-contact-icon { color: #FF4400; }

    .theme-sunset .btn-pick-contact { background: #FFEDD5; color: #EA580C; border: 1px solid #FED7AA; }
    .theme-sunset .input-contact-icon { color: #EA580C; }

    .theme-neumorphic .btn-pick-contact { background: #E8EDF5; color: #3B82F6; border: 1px solid #CBD5E1; box-shadow: inset 1px 1px 2px #FFF; }
    .theme-neumorphic .input-contact-icon { color: #3B82F6; }

    .theme-matrix .btn-pick-contact { background: rgba(0, 255, 102, 0.12); color: #00FF66; border: 1px solid rgba(0, 255, 102, 0.3); }
    .theme-matrix .input-contact-icon { color: #00FF66; }

    .theme-luxury .btn-pick-contact { background: rgba(212, 175, 55, 0.12); color: #D4AF37; border: 1px solid rgba(212, 175, 55, 0.3); }
    .theme-luxury .input-contact-icon { color: #D4AF37; }

    .theme-gaming .btn-pick-contact { background: rgba(139, 92, 246, 0.12); color: #A78BFA; border: 1px solid rgba(139, 92, 246, 0.3); }
    .theme-gaming .input-contact-icon { color: #A78BFA; }

    .theme-retro .btn-pick-contact { background: #C0C0C0; color: #000080; border: 1px solid #808080; }
    .theme-retro .input-contact-icon { color: #000080; }

    .theme-nordic .btn-pick-contact { background: #F5F5F4; color: #57534E; border: 1px solid #E7E5E4; }
    .theme-nordic .input-contact-icon { color: #78716C; }

  
    /* PERFECT MOBILE CARD & BADGE ALIGNMENT */
    .pkg-header-row {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      gap: 4px !important;
      width: 100% !important;
      margin-bottom: 6px !important;
      box-sizing: border-box !important;
    }
    .pkg-badge-validity {
      font-size: 10.5px !important;
      font-weight: 700 !important;
      line-height: 1 !important;
      color: #0284C7 !important;
      background: #E0F2FE !important;
      padding: 3px 6px !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 3px !important;
      white-space: nowrap !important;
      flex-shrink: 0 !important;
      max-width: 58% !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
    }
    .pkg-badge-stock {
      font-size: 10px !important;
      font-weight: 700 !important;
      line-height: 1 !important;
      padding: 3px 6px !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 3px !important;
      white-space: nowrap !important;
      flex-shrink: 0 !important;
    }
    .pkg-badge-stock.in-stock {
      color: #16A34A !important;
      background: #DCFCE7 !important;
    }
    .pkg-badge-stock.auto-ready {
      color: #0284C7 !important;
      background: #E0F2FE !important;
    }
    .pkg-badge-stock.out-of-stock {
      color: #DC2626 !important;
      background: #FEE2E2 !important;
    }

    /* Obsidian Theme specific badge styles */
    body.theme-obsidian .pkg-badge-validity {
      color: #00C2FF !important;
      background: rgba(0, 194, 255, 0.12) !important;
      border: 1px solid rgba(0, 194, 255, 0.25) !important;
    }
    body.theme-obsidian .pkg-badge-stock.in-stock {
      color: #10B981 !important;
      background: rgba(16, 185, 129, 0.12) !important;
      border: 1px solid rgba(16, 185, 129, 0.25) !important;
    }
    body.theme-obsidian .pkg-badge-stock.auto-ready {
      color: #00C2FF !important;
      background: rgba(0, 194, 255, 0.12) !important;
      border: 1px solid rgba(0, 194, 255, 0.25) !important;
    }
    body.theme-obsidian .pkg-badge-stock.out-of-stock {
      color: #EF4444 !important;
      background: rgba(239, 68, 68, 0.12) !important;
      border: 1px solid rgba(239, 68, 68, 0.25) !important;
    }

    /* Matrix Theme badge styles */
    body.theme-matrix .pkg-badge-validity {
      color: #00FF66 !important;
      background: rgba(0, 255, 102, 0.12) !important;
      border: 1px solid rgba(0, 255, 102, 0.25) !important;
    }
    body.theme-matrix .pkg-badge-stock.in-stock {
      color: #00FF66 !important;
      background: rgba(0, 255, 102, 0.12) !important;
      border: 1px solid rgba(0, 255, 102, 0.25) !important;
    }
    body.theme-matrix .pkg-badge-stock.auto-ready {
      color: #00FF66 !important;
      background: rgba(0, 255, 102, 0.15) !important;
      border: 1px solid rgba(0, 255, 102, 0.3) !important;
    }
    body.theme-matrix .pkg-badge-stock.out-of-stock {
      color: #EF4444 !important;
      background: rgba(239, 68, 68, 0.15) !important;
      border: 1px solid rgba(239, 68, 68, 0.3) !important;
    }

    /* Cyberpunk Theme badge styles */
    body.theme-cyberpunk .pkg-badge-validity {
      color: #00F0FF !important;
      background: rgba(0, 240, 255, 0.12) !important;
      border: 1px solid rgba(0, 240, 255, 0.25) !important;
    }
    body.theme-cyberpunk .pkg-badge-stock.in-stock {
      color: #00FF88 !important;
      background: rgba(0, 255, 136, 0.12) !important;
      border: 1px solid rgba(0, 255, 136, 0.25) !important;
    }
    body.theme-cyberpunk .pkg-badge-stock.auto-ready {
      color: #00F0FF !important;
      background: rgba(0, 240, 255, 0.15) !important;
      border: 1px solid rgba(0, 240, 255, 0.3) !important;
    }
    body.theme-cyberpunk .pkg-badge-stock.out-of-stock {
      color: #FF0055 !important;
      background: rgba(255, 0, 85, 0.15) !important;
      border: 1px solid rgba(255, 0, 85, 0.3) !important;
    }

    /* Linear Theme badge styles */
    body.theme-linear .pkg-badge-validity {
      color: #FAFAFA !important;
      background: #27272A !important;
      border: 1px solid #3F3F46 !important;
    }
    body.theme-linear .pkg-badge-stock.in-stock {
      color: #4ADE80 !important;
      background: rgba(74, 222, 128, 0.12) !important;
      border: 1px solid rgba(74, 222, 128, 0.25) !important;
    }
    body.theme-linear .pkg-badge-stock.auto-ready {
      color: #FAFAFA !important;
      background: #27272A !important;
      border: 1px solid #3F3F46 !important;
    }
    body.theme-linear .pkg-badge-stock.out-of-stock {
      color: #F87171 !important;
      background: rgba(248, 113, 113, 0.12) !important;
      border: 1px solid rgba(248, 113, 113, 0.25) !important;
    }

    /* Luxury Theme badge styles */
    body.theme-luxury .pkg-badge-validity {
      color: #D4AF37 !important;
      background: rgba(212, 175, 55, 0.12) !important;
      border: 1px solid rgba(212, 175, 55, 0.25) !important;
    }
    body.theme-luxury .pkg-badge-stock.in-stock {
      color: #ECC875 !important;
      background: rgba(236, 200, 117, 0.12) !important;
      border: 1px solid rgba(236, 200, 117, 0.25) !important;
    }
    body.theme-luxury .pkg-badge-stock.auto-ready {
      color: #D4AF37 !important;
      background: rgba(212, 175, 55, 0.15) !important;
      border: 1px solid rgba(212, 175, 55, 0.3) !important;
    }
    body.theme-luxury .pkg-badge-stock.out-of-stock {
      color: #EF4444 !important;
      background: rgba(239, 68, 68, 0.15) !important;
      border: 1px solid rgba(239, 68, 68, 0.3) !important;
    }

    /* Gaming Theme badge styles */
    body.theme-gaming .pkg-badge-validity {
      color: #818CF8 !important;
      background: rgba(129, 140, 248, 0.12) !important;
      border: 1px solid rgba(129, 140, 248, 0.25) !important;
    }
    body.theme-gaming .pkg-badge-stock.in-stock {
      color: #34D399 !important;
      background: rgba(52, 211, 153, 0.12) !important;
      border: 1px solid rgba(52, 211, 153, 0.25) !important;
    }
    body.theme-gaming .pkg-badge-stock.auto-ready {
      color: #818CF8 !important;
      background: rgba(129, 140, 248, 0.15) !important;
      border: 1px solid rgba(129, 140, 248, 0.3) !important;
    }
    body.theme-gaming .pkg-badge-stock.out-of-stock {
      color: #F87171 !important;
      background: rgba(248, 113, 113, 0.15) !important;
      border: 1px solid rgba(248, 113, 113, 0.3) !important;
    }

    /* Aurora Theme badge styles */
    body.theme-aurora .pkg-badge-validity {
      color: #C084FC !important;
      background: rgba(192, 132, 252, 0.12) !important;
      border: 1px solid rgba(192, 132, 252, 0.25) !important;
    }
    body.theme-aurora .pkg-badge-stock.in-stock {
      color: #34D399 !important;
      background: rgba(52, 211, 153, 0.12) !important;
      border: 1px solid rgba(52, 211, 153, 0.25) !important;
    }
    body.theme-aurora .pkg-badge-stock.auto-ready {
      color: #38BDF8 !important;
      background: rgba(56, 189, 248, 0.15) !important;
      border: 1px solid rgba(56, 189, 248, 0.3) !important;
    }
    body.theme-aurora .pkg-badge-stock.out-of-stock {
      color: #F87171 !important;
      background: rgba(248, 113, 113, 0.15) !important;
      border: 1px solid rgba(248, 113, 113, 0.3) !important;
    }

  
    /* ================================================================= */
    /* FINTECH CHECKOUT & REGISTER SUKSES 1:1 EXACT VISUAL PARITY STYLES */
    /* ================================================================= */
    #modal_payment.modal-backdrop {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      background: rgba(7, 10, 16, 0.88) !important;
      backdrop-filter: blur(12px) !important;
      -webkit-backdrop-filter: blur(12px) !important;
      z-index: 99999 !important;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px 12px;
      box-sizing: border-box;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }

    .checkout-ambient-glow {
      position: absolute;
      top: -80px;
      left: 50%;
      transform: translateX(-50%);
      width: 450px;
      height: 300px;
      background: rgba(0, 115, 198, 0.22);
      border-radius: 50%;
      filter: blur(80px);
      -webkit-filter: blur(80px);
      pointer-events: none;
      z-index: 1;
    }

    .checkout-outer-container {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 360px;
      margin: auto;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .checkout-page-header {
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 5px;
    }

    .checkout-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: 9999px;
      background: rgba(0, 115, 198, 0.15);
      border: 1px solid rgba(0, 194, 255, 0.35);
      color: #00C2FF;
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: -0.1px;
    }

    .checkout-badge-icon {
      width: 12px;
      height: 12px;
      color: #00C2FF;
      flex-shrink: 0;
    }

    .checkout-main-title {
      font-size: 17px;
      font-weight: 800;
      color: #FFFFFF;
      letter-spacing: -0.3px;
      margin: 0;
      line-height: 1.25;
      font-family: system-ui, -apple-system, sans-serif;
    }

    .checkout-main-subtitle {
      font-size: 11px;
      color: #94A3B8;
      max-width: 340px;
      margin: 0 auto;
      line-height: 1.45;
    }

    .fintech-checkout-sheet {
      background: #FFFFFF !important;
      color: #111827 !important;
      width: 100% !important;
      max-width: 360px !important;
      border-radius: 20px !important;
      overflow: hidden !important;
      max-height: calc(100vh - 30px) !important;
      max-height: calc(100dvh - 30px) !important;
      display: flex !important;
      flex-direction: column !important;
      border: 1px solid rgba(226, 232, 240, 0.9) !important;
      box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.4) !important;
      margin: auto !important;
      padding: 0 !important;
      text-align: left !important;
      animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box !important;
      position: relative !important;
    }

    .fintech-navy-header {
      background: #052A4E;
      color: #FFFFFF;
      padding: 11px 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .fintech-navy-brand {
      display: flex;
      align-items: center;
      gap: 7px;
      min-width: 0;
    }

    .fintech-navy-brand-icon {
      width: 24px;
      height: 24px;
      border-radius: 6px;
      background: rgba(255, 255, 255, 0.15);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      flex-shrink: 0;
    }

    .fintech-navy-title {
      font-size: 12.5px;
      font-weight: 800;
      letter-spacing: 0.5px;
      color: #FFFFFF;
      margin: 0;
    }

    .fintech-navy-close,
    .fintech-navy-close-btn {
      background: rgba(255, 255, 255, 0.15);
      border: none;
      color: #FFFFFF;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      transition: background 0.15s;
    }

    .fintech-navy-close:hover,
    .fintech-navy-close-btn:hover {
      background: rgba(255, 255, 255, 0.25);
    }

    .fintech-body-scroll {
      flex: 1 1 auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 24px;
      box-sizing: border-box;
    }

    .fintech-loading-box,
    .fintech-loading-state {
      padding: 40px 20px 32px 20px;
      text-align: center;
      background: #FFFFFF;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 200px;
      box-sizing: border-box;
      border-bottom-left-radius: 20px;
      border-bottom-right-radius: 20px;
    }

    .fintech-loading-spinner {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #E0F2FE;
      color: #0084E3;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px auto;
      font-size: 20px;
    }

    .fintech-loading-title {
      font-size: 15px;
      font-weight: 800;
      color: #0F172A;
      margin: 0 0 6px 0;
      text-align: center;
    }

    .fintech-loading-desc {
      font-size: 12px;
      color: #64748B;
      margin: 0;
      text-align: center;
      max-width: 280px;
      line-height: 1.45;
    }

    .fintech-amount-header {
      padding: 12px 16px 8px 16px;
      background: #FFFFFF;
    }

    .fintech-amount-row {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .fintech-amount-value {
      font-size: 22px;
      font-weight: 800;
      color: #030712;
      font-family: system-ui, -apple-system, sans-serif;
      letter-spacing: -0.5px;
      line-height: 1.1;
      display: inline-flex;
      align-items: center;
    }

    .fintech-copy-btn,
    .fintech-copy-order-btn {
      background: none !important;
      border: none !important;
      color: #0084E3 !important;
      cursor: pointer !important;
      padding: 2px 4px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 3px !important;
      font-size: 13px !important;
      line-height: 1 !important;
      transition: color 0.15s;
      vertical-align: middle !important;
      box-shadow: none !important;
      outline: none !important;
    }

    .fintech-copy-btn:hover,
    .fintech-copy-order-btn:hover {
      color: #006BB8 !important;
      background: none !important;
    }

    .fintech-copy-toast {
      font-size: 9.5px;
      font-weight: 800;
      color: #16A34A;
      background: #DCFCE7;
      border: 1px solid #BBF7D0;
      padding: 1px 5px;
      border-radius: 4px;
    }

    .fintech-copy-check {
      font-size: 9.5px;
      font-weight: 800;
      color: #16A34A;
    }

    .fintech-meta-row {
      margin-top: 4px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 11.5px;
      color: #4B5563;
    }

    .fintech-order-id-box,
    .fintech-order-id-group {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-weight: 600;
      color: #4B5563;
      font-size: 11.5px;
    }

    .fintech-order-id-val {
      font-size: 11px;
      letter-spacing: -0.2px;
    }

    .fintech-rincian-toggle {
      background: none;
      border: none;
      color: #0084E3;
      font-size: 11.5px;
      font-weight: 700;
      cursor: pointer;
      padding: 0;
      display: inline-flex;
      align-items: center;
      gap: 3px;
    }

    .fintech-rincian-toggle:hover {
      text-decoration: underline;
    }

    .fintech-rincian-drawer {
      display: none;
      margin-top: 8px;
      padding: 8px 10px;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 10px;
      font-size: 11px;
      color: #334155;
      gap: 5px;
      flex-direction: column;
    }

    .fintech-rincian-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .fintech-rincian-label {
      color: #64748B;
    }

    .fintech-rincian-val {
      font-weight: 600;
      color: #0F172A;
    }

    .fintech-rincian-divider {
      border-top: 1px solid #CBD5E1;
      margin: 3px 0;
    }

    .fintech-countdown-strip {
      background: #F1F5F9;
      border-top: 1px solid #E2E8F0;
      border-bottom: 1px solid #E2E8F0;
      padding: 6px 14px;
      text-align: center;
    }

    .fintech-countdown-text {
      font-size: 11.5px;
      font-weight: 500;
      color: #1E293B;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }

    .fintech-countdown-timer {
      font-family: monospace;
      font-size: 13px;
      font-weight: 800;
      color: #030712;
    }

    .fintech-standee-section {
      padding: 8px 14px 2px 14px;
      box-sizing: border-box;
    }

    .fintech-standee-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 14px;
      padding: 8px 10px 6px 10px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
      box-sizing: border-box;
      position: relative;
    }

    .fintech-standee-merchant-box {
      margin-bottom: 4px;
    }

    .fintech-standee-merchant-title {
      font-size: 12px;
      font-weight: 800;
      color: #0F172A;
      text-transform: uppercase;
      margin: 0;
      letter-spacing: -0.2px;
      line-height: 1.25;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .fintech-standee-nmid {
      font-size: 10px;
      font-family: monospace;
      font-weight: 700;
      color: #64748B;
      margin-top: 1px;
    }

    .fintech-qr-wrapper {
      position: relative;
      margin: 2px auto 4px auto;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .fintech-qr-box {
      position: relative;
      padding: 6px;
      background: #FFFFFF;
      border-radius: 10px;
      border: 1px solid #F1F5F9;
      box-shadow: inset 0 1px 3px rgba(0,0,0,0.04);
      display: inline-block;
    }

    .fintech-bracket {
      position: absolute;
      width: 12px;
      height: 12px;
      pointer-events: none;
    }

    .fintech-bracket-tl {
      top: 3px;
      left: 3px;
      border-top: 2.5px solid #0084E3;
      border-left: 2.5px solid #0084E3;
      border-top-left-radius: 3px;
    }

    .fintech-bracket-tr {
      top: 3px;
      right: 3px;
      border-top: 2.5px solid #0084E3;
      border-right: 2.5px solid #0084E3;
      border-top-right-radius: 3px;
    }

    .fintech-bracket-bl {
      bottom: 3px;
      left: 3px;
      border-bottom: 2.5px solid #0084E3;
      border-left: 2.5px solid #0084E3;
      border-bottom-left-radius: 3px;
    }

    .fintech-bracket-br {
      bottom: 3px;
      right: 3px;
      border-bottom: 2.5px solid #0084E3;
      border-right: 2.5px solid #0084E3;
      border-bottom-right-radius: 3px;
    }

    .fintech-qr-element {
      width: 170px !important;
      height: 170px !important;
      max-width: 100%;
      aspect-ratio: 1/1;
      display: block;
      margin: 0 auto;
      border-radius: 6px;
    }

    .fintech-standee-caption {
      font-size: 10px;
      font-weight: 500;
      color: #64748B;
      margin: 3px 0 1px 0;
    }

    .fintech-standee-footer {
      margin-top: 4px;
      padding-top: 4px;
      border-top: 1px solid #F1F5F9;
      font-size: 9.5px;
      color: #94A3B8;
      font-weight: 500;
    }

    .fintech-actions-section {
      padding: 8px 16px 12px 16px;
      display: flex;
      flex-direction: column;
      gap: 7px;
      box-sizing: border-box;
    }

    .fintech-btn-gateway {
      width: 100%;
      padding: 9px 12px;
      border-radius: 10px;
      border: none;
      background: #0084E3;
      color: #FFFFFF;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      box-shadow: 0 3px 6px rgba(0,132,227,0.2);
      box-sizing: border-box;
    }

    .fintech-btn-download {
      width: 100%;
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid #CBD5E1;
      background: #FFFFFF;
      color: #334155;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.04);
      transition: background 0.15s;
    }

    .fintech-btn-download:hover {
      background: #F8FAFC;
    }

    .fintech-btn-check-status {
      width: 100%;
      padding: 9px 12px;
      border-radius: 10px;
      border: none;
      background: #0084E3;
      color: #FFFFFF;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      box-shadow: 0 3px 8px rgba(0,132,227,0.2);
      transition: background 0.15s;
    }

    .fintech-btn-check-status:hover {
      background: #0070C0;
    }

    .fintech-status-alert {
      padding: 10px 12px;
      background: #EFF6FF;
      border: 1px solid #BFDBFE;
      border-radius: 12px;
      font-size: 11.5px;
      color: #1E3A8A;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      animation: modalPop 0.15s ease;
    }

    .fintech-status-alert-icon {
      color: #0084E3;
      font-size: 14px;
      margin-top: 1px;
      flex-shrink: 0;
    }

    .fintech-guide-accordion {
      border-top: 1px solid #F1F5F9;
      padding-top: 6px;
    }

    .fintech-guide-toggle {
      background: none;
      border: none;
      color: #374151;
      font-weight: 600;
      font-size: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      padding: 4px 2px;
      transition: color 0.15s;
    }

    .fintech-guide-toggle:hover {
      color: #111827;
    }

    .fintech-guide-toggle-left {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .fintech-guide-qmark {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: #0084E3;
      color: #FFFFFF;
      font-size: 10px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .fintech-guide-arrow {
      font-size: 10px;
      color: #9CA3AF;
      transition: transform 0.2s;
    }

    .fintech-guide-drawer {
      margin-top: 6px;
      padding: 10px 12px;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 12px;
      font-size: 11.5px;
      color: #475569;
      line-height: 1.55;
    }

    .fintech-guide-list {
      margin: 0;
      padding-left: 18px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .fintech-national-footer {
      padding-top: 6px;
      text-align: center;
      font-size: 10px;
      color: #94A3B8;
      line-height: 1.4;
    }

    .fintech-national-footer p {
      margin: 0;
    }

    .fintech-encrypted-sub {
      font-size: 9.5px;
      color: #94A3B8;
      margin-top: 2px !important;
    }

    .fintech-state-box {
      padding: 36px 20px;
      text-align: center;
    }

    .fintech-rejected-icon {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: #FEE2E2;
      color: #DC2626;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin: 0 auto 12px auto;
    }

    .fintech-rejected-title {
      font-size: 17px;
      font-weight: 900;
      color: #DC2626;
      margin: 0 0 4px 0;
    }

    .fintech-rejected-desc {
      font-size: 12px;
      color: #64748B;
      margin: 0 0 16px 0;
    }

    .fintech-btn-back-choose {
      width: 100%;
      padding: 11px;
      font-size: 12.5px;
      border-radius: 12px;
      background: #64748B;
      color: #FFFFFF;
      border: none;
      cursor: pointer;
      font-weight: 700;
    }

    /* Success State Parity */
    .fintech-success-wrapper {
      animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .fintech-success-amount-header {
      padding: 16px 20px 10px 20px;
      background: #FFFFFF;
    }

    .fintech-success-amount-val {
      font-size: 26px;
      font-weight: 900;
      color: #030712;
      letter-spacing: -0.5px;
    }

    .fintech-success-meta {
      margin-top: 4px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      color: #4B5563;
    }

    .fintech-success-lunas-tag {
      font-weight: 700;
      color: #059669;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .fintech-success-banner-strip {
      background: #ECFDF5;
      border-top: 1px solid #A7F3D0;
      border-bottom: 1px solid #A7F3D0;
      padding: 8px 16px;
      text-align: center;
      font-size: 12px;
      font-weight: 700;
      color: #047857;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .fintech-success-body {
      padding: 20px 18px;
      text-align: center;
    }

    .fintech-success-check-circle {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: #ECFDF5;
      border: 2px solid #A7F3D0;
      color: #059669;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin: 0 auto 12px auto;
    }

    .fintech-success-title {
      font-size: 18px;
      font-weight: 900;
      color: #0F172A;
      margin: 0 0 2px 0;
    }

    .fintech-success-subtitle {
      font-size: 12px;
      color: #64748B;
      margin: 0 0 14px 0;
    }

    .fintech-voucher-box {
      background: #F0FDF4;
      border: 2px dashed #16A34A;
      border-radius: 14px;
      padding: 14px;
      margin-bottom: 14px;
    }

    .fintech-voucher-box-label {
      font-size: 10px;
      font-weight: 800;
      color: #15803D;
      letter-spacing: 0.5px;
    }

    .fintech-voucher-code-val {
      font-size: 22px;
      font-weight: 900;
      letter-spacing: 2px;
      color: #0F172A;
      font-family: monospace;
      margin: 4px 0 8px 0;
    }

    .fintech-btn-copy-voucher {
      padding: 6px 14px;
      font-size: 11.5px;
      border-radius: 8px;
      background: #16A34A;
      color: #FFFFFF;
      border: none;
      cursor: pointer;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .fintech-receipt-card {
      border: 1px solid #E2E8F0;
      background: #F8FAFC;
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      text-align: left;
      margin-bottom: 14px;
    }

    .fintech-receipt-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .fintech-receipt-label {
      color: #64748B;
    }

    .fintech-receipt-val {
      color: #0F172A;
    }

    .fintech-receipt-total {
      border-top: 1px solid #CBD5E1;
      padding-top: 6px;
      margin-top: 2px;
      font-weight: 700;
    }

    .fintech-btn-connect-now {
      width: 100%;
      padding: 13px 16px;
      border-radius: 12px;
      background: #052A4E;
      color: #FFFFFF;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      box-shadow: 0 4px 10px rgba(5,42,78,0.25);
      box-sizing: border-box;
    }

    .fintech-btn-connect-now:hover {
      background: #031E38;
    }

    @media (max-width: 480px) {
      .checkout-main-title {
        font-size: 18px;
      }
      .checkout-main-subtitle {
        font-size: 11px;
      }
      .fintech-checkout-sheet {
        max-height: calc(100dvh - 90px) !important;
        max-height: calc(100vh - 90px) !important;
        border-radius: 20px !important;
      }
      .fintech-qr-element {
        width: 195px !important;
        height: 195px !important;
      }
    }

  </style>
  <script src="js/qrious.min.js"></script>
</head>
<body class="theme-<?= htmlspecialchars($selectedTheme); ?>">

  <!-- TOP TICKER (Real-Time Live Social Proof Feed) -->
  <?php if ($elemTicker): ?>
  <div id="top_ticker" class="top-floating-ticker">
    <div class="ticker-pulse-dot"></div>
    <span id="ticker_text"></span>
  </div>
  <?php endif; ?>

  <div class="portal-shell">

    <!-- HERO MERCHANT BANNER -->
    <div class="hero-card">
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 2px 4px;">
        <div style="display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1;">
          <?php if ($elemHeaderLogo): ?>
            <?php if (!empty($merchantLogoUrl)): ?>
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.12); padding: 3px;">
              <img src="<?= htmlspecialchars($merchantLogoUrl); ?>" alt="<?= htmlspecialchars($merchantName); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain; display: block;">
            </div>
            <?php else: ?>
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
              <i class="fa fa-wifi"></i>
            </div>
            <?php endif; ?>
          <?php endif; ?>
          <div style="min-width: 0; flex: 1;">
            <?php if ($elemHeaderBadge): ?>
            <span id="hero_top_badge" style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; display: block; margin-bottom: 2px;">
              <?= htmlspecialchars($topBadgeText); ?>
            </span>
            <?php endif; ?>
            <h1 id="hero_merchant_name" style="font-size: 22px; font-weight: 800; line-height: 1.2; margin: 0; word-break: break-word;"><?= htmlspecialchars($merchantName); ?></h1>
            <?php if ($elemSubtitle): ?>
            <p id="hero_store_subtitle" style="font-size: 12.5px; opacity: 0.9; margin: 3px 0 0 0; line-height: 1.35;"><?= htmlspecialchars($storeSubtitle); ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div style="background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0;">
          <span style="width: 7px; height: 7px; border-radius: 50%; background: #4ADE80; display: inline-block;"></span> Online
        </div>
      </div>
    </div>

    <!-- MULTI-ROUTER LOCATION SELECTOR -->
    <?php if ($elemLocation && count($availableLocations) > 1): ?>
    <div class="card" style="padding: 12px 16px;">
      <label class="input-label" style="margin-bottom: 6px;">
        <i class="fa fa-map-marker-alt"></i> <?= $_select_hotspot_location ?? 'Pilih Lokasi / Server Hotspot:' ?>
      </label>
      <select class="select-location" id="location_select" onchange="switchLocation(this.value)">
        <?php foreach ($availableLocations as $loc): ?>
        <option value="<?= htmlspecialchars($loc['session']); ?>" <?= $loc['session'] === $session ? 'selected' : ''; ?>>
          <?= htmlspecialchars($loc['name']); ?> (<?= htmlspecialchars($loc['session']); ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php elseif ($elemLocation && count($availableLocations) === 1): ?>
    <div class="card" style="padding: 10px 16px; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-map-marker-alt" style="color: var(--theme-primary, #0284c7);"></i>
        <span style="font-size: 13px; font-weight: 700;"><?= htmlspecialchars($availableLocations[0]['name']); ?></span>
      </div>
      <span style="font-size: 11px; opacity: 0.75; font-weight: 600;"><?= $_server_hotspot ?? 'Server Hotspot' ?></span>
    </div>
    <?php endif; ?>

    <!-- WHATSAPP NUMBER INPUT WITH CONTACT PICKER -->
    <?php if ($elemPhoneInput): ?>
    <div class="card">
      <div style="margin-bottom: 6px;">
        <label class="input-label" for="phone" style="margin-bottom: 0;">
          <i class="fa fa-phone"></i> <?= $_buyer_whatsapp_number ?? 'Nomor WhatsApp Pembeli:' ?>
        </label>
      </div>
      <div class="input-phone-group">
        <div class="phone-prefix">+62</div>
        <input type="tel" id="phone" placeholder="<?= $_phone_placeholder ?? '81234567890' ?>"  maxlength="14" oninput="validatePhone()">
        <button type="button" id="btn_pick_contact" class="input-contact-icon" onclick="pickContactNumber()" style="border: none; background: none; padding: 0 12px; cursor: pointer; font-size: 15px;" title="<?= $_pick_from_contacts ?? 'Pilih dari Kontak WhatsApp' ?>" >
          <i class="fa fa-address-book"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>

    <!-- PACKAGE CATALOG & SORT BAR -->
    <div>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 0 4px;">
        <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">
          <?= $_choose_voucher_package ?? 'PILIH PAKET VOUCHER' ?>
        </span>
        <?php if ($elemSortBar): ?>
        <button type="button" class="sort-icon-btn" id="btn_sort_toggle" onclick="togglePriceSort()" title="<?= $_sort_by_price ?? 'Urutkan Berdasarkan Harga' ?>" >
          <i id="sort_icon" class="fa fa-sort"></i>
          <span id="sort_label"><?= $_sort ?? 'Urutkan' ?></span>
        </button>
        <?php endif; ?>
      </div>

      <!-- PACKAGES CONTAINER -->
      <div id="package_container">
        <div style="text-align: center; padding: 40px 20px; opacity: 0.7;">
          <i class="fa fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 8px;"></i>
          <p style="font-size: 13px; font-weight: 600;"><?= $_loading_voucher_packages ?? 'Memuat paket voucher...' ?></p>
        </div>
      </div>
    </div>

    <!-- CHECKOUT CTA BUTTON -->
    <div style="margin-top: 6px;">
      <button type="button" id="btn_checkout" class="btn-buy btn-buy-block" onclick="proceedCheckout()" disabled>
        <i class="fa fa-bolt"></i> <?= $_buy_now ?? 'Beli Sekarang' ?>
      </button>
    </div>

  </div>

  <!-- BOTTOM FLOATING NAV DOCK -->
  <!-- FLOATING RESUME PENDING PAYMENT DOCK -->
  <?php if ($elemResumeDock): ?>
  <div id="dock_resume_payment" class="resume-payment-dock">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
      <div style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; min-width: 0;" onclick="resumePendingPayment()">
        <div class="resume-dock-icon" style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
          <i class="fa fa-qrcode"></i>
        </div>
        <div style="flex: 1; min-width: 0;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span class="resume-dock-title" style="font-size: 12.5px; font-weight: 800;"><?= $_resume_qris_payment ?? 'Lanjutkan Pembayaran QRIS' ?></span>
            <span id="dock_resume_timer" style="font-size: 10px; font-weight: 800; background: #EF4444; color: #FFFFFF; padding: 1.5px 5px; border-radius: 5px; font-family: monospace;">--:--</span>
          </div>
          <div class="resume-dock-sub" style="font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px;">
            <span id="dock_resume_pkg">-</span> • <b id="dock_resume_amount" class="resume-dock-amount">-</b>
          </div>
        </div>
      </div>
      <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
        <button type="button" onclick="resumePendingPayment()" class="resume-dock-btn" style="padding: 6px 13px; font-size: 11.5px; border-radius: 8px; font-weight: 800; border: none; cursor: pointer;">
          Buka
        </button>
        <button type="button" onclick="cancelPendingPayment()" class="resume-dock-close" style="background: none; border: none; cursor: pointer; padding: 6px; font-size: 14px;" title="<?= $_cancel_this_order ?? 'Batalkan pesanan ini' ?>" >
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($elemBottomNav): ?>
  <nav class="bottom-nav-bar">
    <a class="nav-item active" onclick="window.scrollTo({top:0, behavior:'smooth'})">
      <i class="fa fa-ticket"></i>
      <span><?= $_buy_package ?? 'Beli Paket' ?></span>
    </a>
    <?php if ($elemQuotaModal): ?>
    <a class="nav-item" onclick="openModal('modal_quota')">
      <i class="fa fa-gauge-high"></i>
      <span><?= $_check_quota ?? 'Cek Kuota' ?></span>
    </a>
    <?php endif; ?>
    <?php if ($elemGuideModal): ?>
    <a class="nav-item" onclick="openModal('modal_guide')">
      <i class="fa fa-book-open"></i>
      <span><?= $_guide ?? 'Panduan' ?></span>
    </a>
    <?php endif; ?>
    <?php if ($elemCsButton): ?>
    <a class="nav-item" href="https://wa.me/<?= htmlspecialchars($csPhoneClean); ?>?text=Halo%20Admin%2C%20saya%20butuh%20bantuan%20voucher%20WiFi%20di%20<?= urlencode($merchantName); ?>" target="_blank">
      <i class="fa fa-headset"></i>
      <span><?= $_cs_support ?? 'Bantuan CS' ?></span>
    </a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

  <!-- MODAL: CEK KUOTA & STATUS -->
  <div id="modal_quota" class="modal-backdrop">
    <div class="modal-sheet">
      <button type="button" class="modal-close" onclick="closeModal('modal_quota')">&times;</button>
      <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 8px;">
        <i class="fa fa-gauge-high" style="color: #0284C7;"></i> <?= $_check_quota_status_title ?? 'Cek Kuota & Status Voucher' ?>
      </h3>
      <p style="font-size: 12.5px; opacity: 0.8; margin-bottom: 14px;">
        <?= $_check_quota_desc ?? 'Masukkan kode voucher Anda untuk melihat sisa kuota dan masa aktif.' ?>
      </p>
      <input type="text" id="input_check_code" placeholder="<?= $_voucher_code_example ?? 'Contoh: VC-8921' ?>"  style="width: 100%; padding: 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 14px; font-weight: 700; text-transform: uppercase; outline: none; margin-bottom: 12px;">
      <button type="button" class="btn-buy-block btn-buy" onclick="submitCheckQuota()">
        <i class="fa fa-search"></i> <?= $_check_status ?? 'Cek Status' ?>
      </button>
      <div id="quota_result" style="margin-top: 14px; display: none;"></div>
    </div>
  </div>

  <!-- MODAL: PANDUAN PEMBELIAN -->
  <div id="modal_guide" class="modal-backdrop">
    <div class="modal-sheet">
      <button type="button" class="modal-close" onclick="closeModal('modal_guide')">&times;</button>
      <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 12px;">
        <i class="fa fa-book-open" style="color: #0284C7;"></i> <?= $_buy_voucher_guide_title ?? 'Panduan Beli Voucher' ?>
      </h3>
      <div style="font-size: 13px; line-height: 1.6; display: flex; flex-direction: column; gap: 10px;">
        <p><b>1. <?= $_guide_step1_title ?? 'Pilih Paket:' ?></b> <?= $_guide_step1_desc ?? 'Tentukan paket voucher dan masukkan nomor WhatsApp Anda.' ?></p>
        <p><b>2. <?= $_guide_step2_title ?? 'Bayar QRIS:' ?></b> <?= $_guide_step2_desc ?? 'Scan kode QRIS menggunakan GoPay, OVO, DANA, BCA, BRI, Mandiri, atau E-Wallet / Mobile Banking apapun.' ?></p>
        <p><b>3. <?= $_guide_step3_title ?? 'Langsung Terhubung:' ?></b> <?= $_guide_step3_desc ?? 'Kode voucher langsung aktif dan Anda otomatis tersambung ke jaringan internet.' ?></p>
      </div>
    </div>
  </div>

        <!-- MODAL CHECKOUT QRIS FINTECH (EXACT PARITY WITH NODERA FINTECH) -->

    <!-- MODAL CHECKOUT FINTECH QRIS (100% EXACT PARITY WITH REGISTER SUKSES & FINTECH CHECKOUT) -->
  <div id="modal_payment" class="modal-backdrop" onclick="if(event.target===this) closeModal('modal_payment')">
    
    <!-- Ambient glowing light (Parity with RegisterSukses.tsx) -->
    <div class="checkout-ambient-glow"></div>

    <div class="checkout-outer-container">
      


      <!-- The QrisFintechCheckout White Card Container -->
      <div class="modal-sheet fintech-checkout-sheet">
        
        <!-- 1. Deep Navy Header Bar -->
        <div class="fintech-navy-header">
          <div class="fintech-navy-brand">
            <span id="pay_header_brand">
              <?= htmlspecialchars($hotspotname ?: 'NODERA PAY'); ?>
            </span>
          </div>
          <div id="pay_header_lunas_badge" class="fintech-lunas-badge" style="display: none;">
            <i class="fa fa-check-circle"></i>
            <span><?= $_paid_lunas ?? 'LUNAS' ?></span>
          </div>
          <button type="button" id="pay_header_close_btn" onclick="cancelAndCloseModal()" class="fintech-navy-close-btn" title="Tutup">
            ✕
          </button>
        </div>

        <!-- Scrollable Body Content -->
        <div class="fintech-body-scroll">
          
          <!-- STATE 1: LOADING -->
          <div id="pay_loading" class="fintech-loading-box">
            <div class="fintech-loading-spinner">
              <i class="fa fa-spinner fa-spin"></i>
            </div>
            <h3 class="fintech-loading-title"><?= $_creating_qris_barcode ?? 'Membuat Barcode QRIS...' ?></h3>
            <p class="fintech-loading-desc"><?= $_connecting_payment_gateway ?? 'Menghubungkan ke gateway pembayaran otomatis.' ?></p>
          </div>

          <!-- STATE 2: ACTIVE CHECKOUT (EXACT PARITY WITH QrisFintechCheckout.tsx) -->
          <div id="pay_ready" style="display: none;">
            
            <!-- 2. Amount & Order ID Header -->
            <div class="fintech-amount-header">
              <!-- Large Amount & Copy with Toast -->
              <div class="fintech-amount-row">
                <span id="pay_total_amount" class="fintech-amount-value">Rp 0</span>
                <button type="button" onclick="copyPayAmount()" class="fintech-copy-btn" title="Salin nominal tagihan">
                  <span id="toast_copy_amount" class="fintech-copy-toast" style="display: none;"><?= $_copied ?? 'Tersalin!' ?></span>
                  <svg id="icon_copy_amount" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #0084E3;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                </button>
              </div>

              <!-- Order ID & Rincian Toggle -->
              <div class="fintech-meta-row">
                <div class="fintech-order-id-box">
                  <span>Order ID #<span id="pay_order_id">-</span></span>
                  <button type="button" onclick="copyOrderId()" class="fintech-copy-order-btn" title="Salin Order ID">
                    <span id="toast_copy_order" class="fintech-copy-check" style="display: none;">✓</span>
                    <svg id="icon_copy_order" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #0084E3;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                  </button>
                </div>

                <button type="button" onclick="toggleDetailsDrawer()" class="fintech-rincian-toggle">
                  <span><?= $_details ?? 'Rincian' ?></span>
                  <svg id="details_arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
              </div>

              <!-- Expandable Rincian Drawer -->
              <div id="details_drawer" class="fintech-rincian-drawer" style="display: none;">
                <div class="fintech-rincian-row">
                  <span class="fintech-rincian-label"><?= $_service_package ?? 'Paket Layanan:' ?></span>
                  <span id="pay_pkg_title" class="fintech-rincian-val">-</span>
                </div>
                <div class="fintech-rincian-row">
                  <span class="fintech-rincian-label"><?= $_validity ?? 'Masa Aktif:' ?></span>
                  <span id="pay_pkg_validity" class="fintech-rincian-val">-</span>
                </div>
                <div class="fintech-rincian-row">
                  <span class="fintech-rincian-label"><?= $_package_price ?? 'Harga Paket:' ?></span>
                  <span id="pay_pkg_price" class="fintech-rincian-val">Rp 0</span>
                </div>
                <div id="pay_fee_row" class="fintech-rincian-row" style="display: none;">
                  <span id="pay_fee_label" class="fintech-rincian-label"><?= $_service_fee_qris ?? 'Biaya Layanan (QRIS):' ?></span>
                  <span id="pay_fee_val" class="fintech-rincian-val">Rp 0</span>
                </div>
                <div class="fintech-rincian-row fintech-rincian-total">
                  <span><?= $_total_bill ?? 'Total Tagihan:' ?></span>
                  <span id="pay_drawer_total" class="fintech-rincian-total-amt">Rp 0</span>
                </div>
              </div>
            </div>

            <!-- 3. Countdown Strip -->
            <div class="fintech-countdown-strip">
              <span class="fintech-countdown-text">
                <span><?= $_pay_within ?? 'Bayar dalam' ?></span>
                <span id="pay_countdown" class="fintech-countdown-timer">15:00</span>
              </span>
            </div>

            <!-- 4. Standee Card Container (Exact Parity with QrisFintechCheckout.tsx) -->
            <div class="fintech-standee-section">
              <div class="fintech-standee-card">
                
                <!-- Merchant Name & NMID -->
                <div class="fintech-standee-merchant-box">
                  <h4 id="pay_merchant_title" class="fintech-standee-merchant-title">
                    <?= htmlspecialchars($hotspotname ?: 'NODERA HOTSPOT'); ?>
                  </h4>
                  <div id="pay_nmid_box" class="fintech-standee-nmid">
                    NMID: ID1024366211885
                  </div>
                </div>

                <!-- QR Code Center with Blue Viewfinder Corner Brackets -->
                <div class="fintech-qr-wrapper">
                  <div class="fintech-qr-box">
                    <!-- Top-Left Corner Bracket -->
                    <div class="fintech-bracket fintech-bracket-tl"></div>
                    <!-- Top-Right Corner Bracket -->
                    <div class="fintech-bracket fintech-bracket-tr"></div>
                    <!-- Bottom-Left Corner Bracket -->
                    <div class="fintech-bracket fintech-bracket-bl"></div>
                    <!-- Bottom-Right Corner Bracket -->
                    <div class="fintech-bracket fintech-bracket-br"></div>

                    <!-- Canvas & Image -->
                    <canvas id="qris_canvas" class="fintech-qr-element"></canvas>
                    <img id="qris_img" src="" alt="QRIS Barcode" class="fintech-qr-element" style="display: none;">
                  </div>
                </div>

                <p class="fintech-standee-caption"><?= $_scan_camera_hint ?? 'Arahkan kamera m-Banking atau E-Wallet Anda' ?></p>

                <!-- Card Footer: Acquirer Notice -->
                <div class="fintech-standee-footer">
                  <span><?= $_printed_by ?? 'Dicetak oleh:' ?> <strong style="color: #1E293B;">NODERA</strong></span>
                </div>
              </div>
            </div>

            <!-- 5. Action Buttons -->
            <div class="fintech-actions-section">
              <!-- Cara Bayar Accordion (Diletakkan di atas tombol unduh QRIS) -->
              <div class="fintech-guide-accordion">
                <button type="button" onclick="toggleInstructions()" class="fintech-guide-toggle">
                  <div class="fintech-guide-toggle-left">
                    <div class="fintech-guide-qmark">?</div>
                    <span><?= $_how_to_pay ?? 'Cara bayar' ?></span>
                  </div>
                  <svg id="inst_arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="fintech-guide-arrow" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <div id="instructions_box" class="fintech-guide-drawer" style="display: none;">
                  <ol class="fintech-guide-list">
                    <li><?= $_pay_inst_1 ?? 'Buka aplikasi <b>m-Banking</b> (BCA, Mandiri, BRI, BNI, dll) atau <b>E-Wallet</b> (GoPay, OVO, DANA, ShopeePay, LinkAja).' ?></li>
                    <li><?= $_pay_inst_2 ?? 'Pilih menu <b>Bayar / Scan / QRIS</b>.' ?></li>
                    <li><?= $_pay_inst_3 ?? 'Arahkan kamera ke kode QR di atas atau gunakan gambar QR yang telah diunduh.' ?></li>
                    <li><?= $_pay_inst_4 ?? 'Periksa nama merchant dan pastikan nominal tagihan sesuai.' ?></li>
                    <li><?= $_pay_inst_5 ?? 'Masukkan PIN transaksi Anda untuk menyelesaikan pembayaran.' ?></li>
                    <li><?= $_pay_inst_6 ?? 'Setelah berhasil, klik tombol <b>Cek status</b> atau tunggu beberapa detik hingga voucher muncul otomatis.' ?></li>
                  </ol>
                </div>
              </div>

              <a id="btn_open_gateway_checkout" href="#" target="_blank" class="fintech-btn-gateway" style="display: none;">
                <i class="fa fa-external-link"></i>
                <span><?= $_pay_via_gateway ?? 'Bayar via VA / E-Wallet / Retail' ?></span>
              </a>

              <button type="button" onclick="downloadQrisImage()" class="fintech-btn-download">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <span><?= $_download_qr ?? 'Download QR' ?></span>
              </button>

              <button type="button" onclick="manualCheckStatus()" id="btn_manual_check" class="fintech-btn-check-status">
                <svg id="manual_check_spin" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                <span><?= $_check_status ?? 'Cek status' ?></span>
              </button>

              <!-- Interactive Live Status Toast / Alert -->
              <div id="payment_status_drawer" class="fintech-status-alert" style="display: none;">
                <div class="fintech-status-alert-icon">
                  <i class="fa fa-info-circle"></i>
                </div>
                <div class="fintech-status-alert-text">
                  <span id="status_alert_custom_msg"><?= $_payment_not_detected_yet ?? 'Pembayaran belum terdeteksi. Silakan selesaikan pembayaran di aplikasi Anda lalu tekan tombol Cek status kembali.' ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- STATE: PAYMENT UNAVAILABLE -->
          <div id="pay_unavailable" class="fintech-state-box" style="display: none;">
            <div class="fintech-rejected-icon" style="background: rgba(245, 158, 11, 0.12); color: #F59E0B;">
              <i class="fa fa-exclamation-triangle"></i>
            </div>
            <h3 class="fintech-rejected-title" style="color: #F59E0B;"><?= $_payment_method_unavailable ?? 'Metode Pembayaran Belum Tersedia' ?></h3>
            <p id="unavailable_reason" class="fintech-rejected-desc"><?= $_payment_method_unavailable_desc ?? 'Layanan pembayaran online otomatis belum dikonfigurasi pada router ini. Silakan hubungi admin hotspot untuk melakukan pembelian manual.' ?></p>
            
            <?php if (!empty($admin_wa_num)): ?>
            <a href="https://wa.me/<?= preg_replace('/\D/', '', $admin_wa_num) ?>?text=<?= urlencode('Halo Admin, saya ingin membeli voucher internet hotspot untuk lokasi ' . ($hotspotname ?: 'Hotspot')) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 16px; margin-bottom: 12px; border-radius: 12px; background: #10B981; color: #FFFFFF; font-size: 13px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
              <i class="fa fa-whatsapp" style="font-size: 16px;"></i> <?= $_contact_whatsapp_admin ?? 'Hubungi WhatsApp Admin' ?>
            </a>
            <?php endif; ?>

            <button type="button" class="fintech-btn-back-choose" onclick="cancelAndCloseModal()">
              <i class="fa fa-arrow-left"></i> <?= $_close ?? 'Tutup' ?>
            </button>
          </div>

          <!-- STATE 4: PAYMENT REJECTED / CANCELLED -->
          <div id="pay_rejected" class="fintech-state-box" style="display: none;">
            <div class="fintech-rejected-icon">
              <i class="fa fa-times-circle"></i>
            </div>
            <h3 class="fintech-rejected-title"><?= $_order_rejected_cancelled ?? 'Pesanan Ditolak / Dibatalkan' ?></h3>
            <p id="rejected_reason" class="fintech-rejected-desc"><?= $_order_rejected_desc ?? 'Pesanan voucher ini telah ditolak atau dibatalkan. Silakan pilih paket kembali atau hubungi admin.' ?></p>
            
            <button type="button" class="fintech-btn-back-choose" onclick="cancelAndCloseModal()">
              <i class="fa fa-arrow-left"></i> <?= $_back_choose_other_pkg ?? 'Kembali & Pilih Paket Lain' ?>
            </button>
          </div>

          <!-- STATE 5: PAYMENT EXPIRED -->
          <div id="pay_expired" class="fintech-state-box" style="display: none;">
            <div class="fintech-rejected-icon" style="background: rgba(239, 68, 68, 0.12); color: #EF4444;">
              <i class="fa fa-clock-o"></i>
            </div>
            <h3 class="fintech-rejected-title"><?= $_qris_expired_title ?? 'QR Telah Kedaluwarsa' ?></h3>
            <p class="fintech-rejected-desc"><?= $_qris_expired_desc ?? 'Batas waktu pembayaran telah habis. Kode QR dinonaktifkan demi keamanan. Silakan buat pesanan baru.' ?></p>
            
            <button type="button" class="fintech-btn-back-choose" onclick="cancelAndCloseModal()">
              <i class="fa fa-arrow-left"></i> <?= $_create_new_order ?? 'Buat Pesanan Baru' ?>
            </button>
          </div>

          <!-- STATE 3: PAYMENT SUCCESS (EXACT PARITY WITH QrisFintechCheckout.tsx Success State) -->
          <div id="pay_success" class="fintech-success-wrapper" style="display: none;">
            <!-- Amount Header -->
            <div class="fintech-success-amount-header">
              <div class="fintech-success-amount-val" id="success_amount_display">Rp 0</div>
              <div class="fintech-success-meta">
                <span class="font-mono">Order ID #<span id="success_order_id_txt">-</span></span>
                <span class="fintech-success-lunas-tag">
                  <i class="fa fa-check-circle"></i> <?= $_paid_lunas ?? 'Lunas' ?>
                </span>
              </div>
            </div>

            <!-- Success Banner Strip -->
            <div class="fintech-success-banner-strip">
              <i class="fa fa-check-circle"></i>
              <span><?= $_payment_verified_success ?? 'Pembayaran Berhasil Diverifikasi!' ?></span>
            </div>

            <!-- Success Content Body -->
            <div class="fintech-success-body">
              <div class="fintech-success-check-circle">
                <i class="fa fa-check"></i>
              </div>

              <h3 class="fintech-success-title"><?= $_payment_success_title ?? 'Pembelian Berhasil!' ?></h3>
              <p class="fintech-success-subtitle"><?= $_payment_success_desc ?? 'Kode voucher hotspot Anda siap digunakan:' ?></p>
              
              <!-- Hotspot Voucher Highlight Box -->
              <div class="fintech-voucher-box">
                <div class="fintech-voucher-box-label"><?= $_your_hotspot_voucher_code ?? 'KODE VOUCHER HOTSPOT ANDA' ?></div>
                <div id="success_voucher_code" class="fintech-voucher-code-val">------</div>
                <button type="button" class="fintech-btn-copy-voucher" onclick="copyVoucherCode()">
                  <i class="fa fa-clone"></i> <?= $_copy_voucher_code ?? 'Salin Kode Voucher' ?>
                </button>
              </div>

              <!-- Receipt Breakdown Table -->
              <div class="fintech-receipt-card">
                <div class="fintech-receipt-row">
                  <span class="fintech-receipt-label"><?= $_receipt_order_id ?? 'Nomor Order ID' ?></span>
                  <span id="receipt_order_id" class="fintech-receipt-val font-mono font-bold">-</span>
                </div>
                <div class="fintech-receipt-row">
                  <span class="fintech-receipt-label"><?= $_receipt_service_package ?? 'Paket Layanan' ?></span>
                  <span id="receipt_pkg_title" class="fintech-receipt-val font-semibold">-</span>
                </div>
                <div class="fintech-receipt-row">
                  <span class="fintech-receipt-label"><?= $_receipt_payment_method ?? 'Metode' ?></span>
                  <span class="fintech-receipt-val font-semibold">QRIS (Otomatis)</span>
                </div>
                <div class="fintech-receipt-row fintech-receipt-total">
                  <span class="fintech-receipt-label"><?= $_receipt_total_payment ?? 'Total Pembayaran' ?></span>
                  <span id="receipt_total" class="fintech-receipt-val font-mono font-bold text-emerald-600">Rp 0</span>
                </div>
              </div>

              <a id="btn_auto_connect" href="#" class="fintech-btn-connect-now">
                <i class="fa fa-wifi"></i> <?= $_connect_to_internet_now ?? 'Hubungkan ke Internet Sekarang' ?>
              </a>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>

<script>
const I18N = {
  checkout_badge_pay_ready: <?= json_encode($_checkout_badge_pay_ready ?? "Pembelian Voucher Hotspot — QRIS Otomatis"); ?>,
  checkout_title_pay_ready: <?= json_encode($_checkout_title_pay_ready ?? "Selesaikan Pembayaran QRIS"); ?>,
  checkout_sub_pay_ready: <?= json_encode($_checkout_sub_pay_ready ?? "Scan barcode QRIS di bawah menggunakan m-Banking atau e-Wallet apapun. Pembayaran akan terverifikasi secara otomatis."); ?>,
  checkout_badge_success: <?= json_encode($_checkout_badge_success ?? "Pembelian Berhasil & Terverifikasi"); ?>,
  checkout_title_success: <?= json_encode($_checkout_title_success ?? "Pembelian Voucher Berhasil!"); ?>,
  checkout_sub_success: <?= json_encode($_checkout_sub_success ?? "Voucher hotspot Anda telah aktif dan siap digunakan."); ?>,
  sort_default: <?= json_encode($_sort ?? 'Urutkan'); ?>,
  sort_cheapest: <?= json_encode($_sort_cheapest ?? 'Termurah'); ?>,
  sort_highest: <?= json_encode($_sort_highest ?? 'Termahal'); ?>,
  prompt_phone: <?= json_encode($_prompt_phone_number ?? 'Masukkan atau tempel nomor telepon/WhatsApp :'); ?>,
  no_active_packages: <?= json_encode($_no_active_packages ?? 'Tidak ada paket voucher aktif.'); ?>,
  stock_ready: <?= json_encode($_stock_ready ?? 'Siap'); ?>,
  stock_count: <?= json_encode($_stock_count ?? 'Stok: %s'); ?>,
  stock_out: <?= json_encode($_stock_out ?? 'Habis'); ?>,
  best_seller: <?= json_encode($_best_seller ?? 'Terlaris'); ?>,
  unlimited: <?= json_encode($_unlimited ?? 'Unlimited'); ?>,
  hotspot_validity: <?= json_encode($_hotspot_validity ?? 'Masa Aktif Hotspot'); ?>,
  service_fee_qris: <?= json_encode($_service_fee_qris ?? 'Biaya Layanan (QRIS):'); ?>,
  open_payment_page: <?= json_encode($_open_payment_page ?? 'Buka Halaman Pembayaran (%s)'); ?>,
  voucher_hotspot: <?= json_encode($_voucher_hotspot ?? 'Voucher Hotspot'); ?>,
  order_rejected_admin: <?= json_encode($_order_rejected_admin ?? 'Pesanan voucher ini telah ditolak oleh Admin.'); ?>,
  voucher_copied_alert: <?= json_encode($_voucher_copied_alert ?? 'Kode voucher berhasil disalin!'); ?>,
  checking_status: <?= json_encode($_checking_status ?? 'Memeriksa status...'); ?>,
  voucher_not_found: <?= json_encode($_voucher_not_found ?? 'Voucher tidak ditemukan'); ?>,
  failed_connect_router: <?= json_encode($_failed_connect_router ?? 'Gagal terhubung ke router.'); ?>,
  status_active: <?= json_encode($_status_active ?? 'Aktif'); ?>,
  status_expired: <?= json_encode($_status_expired ?? 'Expired'); ?>,
  uptime: <?= json_encode($_uptime ?? 'Uptime'); ?>,
  data_usage: <?= json_encode($_data_usage ?? 'Pemakaian Data'); ?>,
  validity: <?= json_encode($_validity ?? 'Masa Aktif'); ?>,
  just_bought: <?= json_encode($_just_bought ?? 'baru saja membeli'); ?>,
  from: <?= json_encode($_from ?? 'dari'); ?>,
  ago: <?= json_encode($_ago ?? 'lalu'); ?>,
  seconds: <?= json_encode($_sec ?? 'detik'); ?>,
  minutes: <?= json_encode($_min ?? 'menit'); ?>,
};
</script>
<script>
    const currentTheme = <?= json_encode($selectedTheme); ?>;
    const currencyStr = <?= json_encode($currency); ?>;
    let activeSession = <?= json_encode($session); ?>;
    let availablePackages = [];
    let selectedPackage = null;
    let sortState = 'default';
    let currentSort = 'default';
    let pollTimer = null;
    let countdownTimer = null;

    function switchLocation(newSession) {
      if (!newSession) return;
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('session', newSession);
        url.searchParams.delete('loc');
        url.searchParams.delete('location');
        window.location.href = url.toString();
      } catch (e) {
        window.location.href = window.location.pathname + '?session=' + encodeURIComponent(newSession);
      }
    }

    function togglePriceSort() {
      const icon = document.getElementById('sort_icon');
      const label = document.getElementById('sort_label');
      const btn = document.getElementById('btn_sort_toggle');
      if (sortState === 'default') {
        sortState = 'cheap';
        icon.className = 'fa fa-sort-amount-asc';
        label.innerText = I18N.sort_cheapest;
        if (btn) btn.classList.add('active');
      } else if (sortState === 'cheap') {
        sortState = 'expensive';
        icon.className = 'fa fa-sort-amount-desc';
        label.innerText = I18N.sort_highest;
        if (btn) btn.classList.add('active');
      } else {
        sortState = 'default';
        icon.className = 'fa fa-sort';
        label.innerText = I18N.sort_default;
        if (btn) btn.classList.remove('active');
      }
      renderPackages();
    }

        async function pickContactNumber() {
      if ('contacts' in navigator && 'ContactsManager' in window) {
        try {
          const props = ['tel'];
          const opts = { multiple: false };
          const contacts = await navigator.contacts.select(props, opts);
          if (contacts && contacts.length > 0 && contacts[0].tel && contacts[0].tel.length > 0) {
            let rawTel = contacts[0].tel[0].replace(/[^0-9+]/g, '');
            rawTel = rawTel.replace(/^\+62|^0062/, '');
            rawTel = rawTel.replace(/^0+/, '');
            const input = document.getElementById('phone');
            if (input) {
              input.value = rawTel;
              validatePhone();
            }
          }
        } catch (ex) {
          console.warn("Contact picker error:", ex);
        }
      } else {
        const promptNum = prompt(I18N.prompt_phone);
        if (promptNum) {
          let clean = promptNum.replace(/[^0-9+]/g, '').replace(/^\+62|^0062/, '').replace(/^0+/, '');
          const input = document.getElementById('phone');
          if (input) {
            input.value = clean;
            validatePhone();
          }
        }
      }
    }

    const showStockBadge = <?= json_encode($elemStockBadge); ?>;
    const showValidityBadge = <?= json_encode($elemValidity); ?>;
    const showPhoneInput = <?= json_encode($elemPhoneInput); ?>;

    function validatePhone() {
      const val = (document.getElementById('phone')?.value || '').replace(/[^0-9]/g, '');
      const btn = document.getElementById('btn_checkout');
      const isValid = (!showPhoneInput || val.length >= 9) && selectedPackage !== null;
      if (btn) btn.disabled = !isValid;
    }

    function renderPackages() {
      const container = document.getElementById('package_container');
      if (!availablePackages || availablePackages.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding:30px; opacity:0.7;"><p>${I18N.no_active_packages}</p></div>`;
        return;
      }

      let pkgs = [...availablePackages];
      const sState = (typeof sortState !== 'undefined') ? sortState : 'default';
      if (sState === 'cheap' || sState === 'price-asc') {
        pkgs.sort((a, b) => (Number(a.price) || 0) - (Number(b.price) || 0));
      } else if (sState === 'expensive' || sState === 'price-desc') {
        pkgs.sort((a, b) => (Number(b.price) || 0) - (Number(a.price) || 0));
      }

      let html = '';

      function getStockBadge(pkg) {
        if (!showStockBadge) return '';
        if (pkg.is_auto_generate) {
          return `<span class="pkg-badge-stock auto-ready"><i class="fa fa-bolt"></i> ${I18N.stock_ready}</span>`;
        }
        const count = Number(pkg.stock_count) || 0;
        if (count > 0) {
          return `<span class="pkg-badge-stock in-stock"><i class="fa fa-ticket"></i> ${I18N.stock_count.replace('%s', count).replace('%d', count)}</span>`;
        }
        return `<span class="pkg-badge-stock out-of-stock"><i class="fa fa-times-circle"></i> ${I18N.stock_out}</span>`;
      }

      function getValidityBadge(pkg) {
        if (!showValidityBadge || !pkg.validity || pkg.validity === '-' || pkg.validity === '') return '';
        return `<span class="pkg-badge-validity"><i class="fa fa-clock-o"></i> ${escapeHtml(pkg.validity)}</span>`;
      }

      if (currentTheme === 'stripe') {
        html = '<div class="pkg-list">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div class="stripe-radio"></div>
                <div>
                  <h4 style="font-size: 15px; font-weight: 800; color: inherit; margin: 0 0 4px 0;">${escapeHtml(pkg.name)}</h4>
                  <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    ${getStockBadge(pkg)}
                    ${getValidityBadge(pkg)}
                  </div>
                </div>
              </div>
              <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'linear') {
        html = '<div class="bento-grid">';
        pkgs.forEach((pkg, idx) => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          const isHero = idx === 0 && pkgs.length > 2;
          html += `
            <div class="pkg-card ${isHero ? 'bento-hero' : ''} ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; min-height: 18px;">
                  ${getValidityBadge(pkg)}
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 15px; font-weight: 800; margin: 4px 0; color: inherit;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="display: flex; justify-content: flex-end; align-items: flex-end; margin-top: 10px;">
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'voucher') {
        html = '<div class="pkg-list">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div class="ticket-stub">
                <i class="fa fa-ticket" style="font-size: 16px; margin-bottom: 4px;"></i>
                <span>HOTSPOT</span>
              </div>
              <div class="ticket-body">
                <div>
                  <h4 style="font-size: 15px; font-weight: 800; color: inherit; margin: 0 0 4px 0;">${escapeHtml(pkg.name)}</h4>
                  <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    ${getStockBadge(pkg)}
                    ${getValidityBadge(pkg)}
                  </div>
                </div>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'obsidian') {
        html = '<div class="pkg-grid">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 4px;">
                  ${getValidityBadge(pkg)}
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 14.5px; font-weight: 800; margin: 2px 0 0 0; color: inherit; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #1E2633; display: flex; justify-content: flex-end; align-items: center;">
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'aurora') {
        html = '<div class="aurora-grid">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 6px;">
                  <span class="glass-pill"><i class="fa fa-wifi"></i> VOUCHER</span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 15px; font-weight: 800; margin: 2px 0 0 0; color: #FFFFFF; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10.5px; color: rgba(255,255,255,0.7); font-weight: 600;">Harga</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'emerald') {
        html = '<div class="pkg-list">';
        pkgs.forEach((pkg, idx) => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          const isPopular = idx === 0;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                <div class="emerald-icon-box">
                  <i class="fa fa-shield"></i>
                </div>
                <div style="flex: 1;">
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <h4 style="font-size: 15px; font-weight: 800; color: inherit; margin: 0;">${escapeHtml(pkg.name)}</h4>
                    ${isPopular ? '<span class="emerald-tag">Favorit</span>' : ''}
                  </div>
                  <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                    ${getStockBadge(pkg)}
                    ${getValidityBadge(pkg)}
                  </div>
                </div>
              </div>
              <div style="text-align: right;">
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
                <div style="font-size: 10.5px; font-weight: 700; color: #059669; margin-top: 2px;"><i class="fa fa-bolt"></i> QRIS Instan</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'cyberpunk') {
        html = '<div class="cyber-grid">';
        pkgs.forEach((pkg, idx) => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          const codeTag = 'NODE-0' + (idx + 1);
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 6px;">
                  <span class="cyber-code-badge">${codeTag}</span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 14px; font-weight: 800; margin: 4px 0 0 0; color: #00FF88; text-transform: uppercase; letter-spacing: 0.5px;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 12px; padding-top: 6px; border-top: 1px dashed rgba(0,255,136,0.3); display: flex; justify-content: space-between; align-items: flex-end;">
                <span style="font-size: 9.5px; color: #00F0FF; font-weight: 700;">CREDITS</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'swiss') {
        html = '<div class="swiss-list">';
        pkgs.forEach((pkg, idx) => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          const num = String(idx + 1).padStart(2, '0');
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div class="swiss-num">${num}</div>
              <div class="swiss-content">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                  <h4 style="font-size: 16px; font-weight: 900; letter-spacing: -0.5px; color: inherit; margin: 0; text-transform: uppercase;">${escapeHtml(pkg.name)}</h4>
                  <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                  ${getStockBadge(pkg)}
                  ${getValidityBadge(pkg)}
                  <span style="font-size: 10.5px; font-weight: 800; text-transform: uppercase; opacity: 0.7;">• AKSES INSTAN</span>
                </div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'sunset') {
        html = '<div class="sunset-grid">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div class="sunset-pill-header">
                <span class="sunset-validity"><i class="fa fa-sun-o"></i> PAKET</span>
                ${getStockBadge(pkg)}
              </div>
              <h4 style="font-size: 15px; font-weight: 800; margin: 6px 0 0 0; color: #431407; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              <div class="sunset-price-row">
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
                <div class="sunset-arrow-icon"><i class="fa fa-arrow-right"></i></div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'neumorphic') {
        html = '<div class="neumorphic-grid">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 6px;">
                  <span style="font-size: 10px; font-weight: 800; color: #64748B; background: #E2E8F0; padding: 2px 6px; border-radius: 6px;">SOFT 3D</span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 15px; font-weight: 800; color: #1E293B; margin: 2px 0 0 0; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #CBD5E1; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10.5px; color: #64748B; font-weight: 600;">Tarif</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'matrix') {
        html = '<div class="matrix-grid">';
        pkgs.forEach((pkg, idx) => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          const hexCode = '0x' + (idx + 1).toString(16).toUpperCase().padStart(2, '0');
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 4px;">
                  <span class="matrix-cli-tag">[${hexCode}] RUN</span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 13.5px; font-weight: 800; margin: 2px 0 0 0; color: #00FF66; letter-spacing: -0.2px;">&gt; ${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 10px; padding-top: 6px; border-top: 1px dashed rgba(0,255,102,0.3); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 9.5px; color: #00FF66; opacity: 0.7;">VAL:</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'luxury') {
        html = '<div class="luxury-grid">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 6px;">
                  <span class="vip-badge"><i class="fa fa-diamond"></i> VIP</span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 15px; font-weight: 800; color: #F5E6C8; margin: 2px 0 0 0; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #3A3222; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10px; color: #8C7B5D; font-weight: 700; text-transform: uppercase;">Akses</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'gaming') {
        const rankNames = ['BRONZE', 'SILVER', 'GOLD', 'PLATINUM', 'DIAMOND', 'MASTER', 'APEX'];
        const rankColors = ['#CD7F32', '#94A3B8', '#F59E0B', '#38BDF8', '#818CF8', '#EC4899', '#EF4444'];
        html = '<div class="gaming-grid">';
        pkgs.forEach((pkg, idx) => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          const rIdx = Math.min(idx, rankNames.length - 1);
          const rName = rankNames[rIdx];
          const rColor = rankColors[rIdx];
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 6px;">
                  <span class="rank-badge" style="background: ${rColor}22; color: ${rColor}; border: 1px solid ${rColor}55;">
                    <i class="fa fa-trophy"></i> ${rName}
                  </span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 14.5px; font-weight: 800; color: #F1F5F9; margin: 2px 0 0 0; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #232936; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10px; color: #64748B; font-weight: 700;">TIER PASS</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'retro') {
        html = '<div class="retro-list">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-desktop" style="font-size: 14px;"></i>
                <div>
                  <h4 style="font-size: 14px; font-weight: 800; margin: 0; color: inherit;">${escapeHtml(pkg.name)}</h4>
                  <div style="margin-top: 2px;">
                    ${getStockBadge(pkg)}
                  </div>
                </div>
              </div>
              <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
            </div>`;
        });
        html += '</div>';
      } else if (currentTheme === 'nordic') {
        html = '<div class="nordic-grid">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px; margin-bottom: 6px;">
                  <span class="nordic-leaf-tag"><i class="fa fa-leaf"></i> PASS</span>
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 15px; font-weight: 800; color: #2C2623; margin: 2px 0 0 0; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #E5E0D8; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10.5px; color: #786C65; font-weight: 600;">Val</span>
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      } else {
        // STANDARD (NEO CARDS)
        html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">';
        pkgs.forEach(pkg => {
          const isSel = selectedPackage && selectedPackage.name === pkg.name;
          html += `
            <div class="pkg-card ${isSel ? 'selected' : ''}" onclick="selectPackage('${escapeHtml(pkg.name).replace(/'/g, "\\'")}')">
              <div>
                <div style="display: flex; justify-content: flex-end; align-items: center; min-height: 18px; margin-bottom: 4px;">
                  ${getStockBadge(pkg)}
                </div>
                <h4 style="font-size: 15px; font-weight: 800; color: inherit; margin: 2px 0 0 0; line-height: 1.25;">${escapeHtml(pkg.name)}</h4>
              </div>
              <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; align-items: center;">
                <div class="pkg-price">Rp ${formatRupiah(pkg.price)}</div>
              </div>
            </div>`;
        });
        html += '</div>';
      }

      container.innerHTML = html;
      updateCheckoutBtn();
    }

    function selectPackage(pkgName) {
      selectedPackage = availablePackages.find(p => p.name === pkgName) || null;
      renderPackages();
      validatePhone();
    }

    function updateCheckoutBtn() {
      const btn = document.getElementById('btn_checkout');
      if (!btn) return;
      if (selectedPackage) {
        btn.innerHTML = `<i class="fa fa-bolt"></i> Beli ${escapeHtml(selectedPackage.name)} — Rp ${formatRupiah(selectedPackage.price)}`;
      } else {
        btn.innerHTML = `<i class="fa fa-bolt"></i> <?= $_buy_now ?? 'Beli Sekarang' ?>`;
      }
    }

        async function loadPackages() {
      const cacheKey = 'nodera_pkgs_' + encodeURIComponent(activeSession);
      try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
          const cachedData = JSON.parse(cached);
          if (Array.isArray(cachedData) && cachedData.length > 0 && (!availablePackages || availablePackages.length === 0)) {
            availablePackages = cachedData;
            renderPackages();
          }
        }
      } catch(e) {}

      try {
        const res = await fetch(`buy_process.php?action=get_packages&session=${encodeURIComponent(activeSession)}`);
        const data = await res.json();
        const isSuccess = data.success === true || data.status === 'success';
        const pkgs = data.packages || [];

        if (data.merchant_name || data.merchant) {
          const heroMerchant = document.getElementById('hero_merchant_name');
          if (heroMerchant) heroMerchant.innerText = data.merchant_name || data.merchant;
        }

        if (isSuccess && Array.isArray(pkgs) && pkgs.length > 0) {
          availablePackages = pkgs;
          try { localStorage.setItem(cacheKey, JSON.stringify(pkgs)); } catch(e) {}
          renderPackages();
        } else if (!availablePackages || availablePackages.length === 0) {
          const emptyMsg = data.message || 'Tidak ada paket voucher aktif saat ini untuk lokasi ini.';
          document.getElementById('package_container').innerHTML = `
            <div style="text-align:center; padding:32px 16px; opacity:0.8;">
              <i class="fa fa-info-circle fa-2x" style="margin-bottom: 10px; display: block; opacity: 0.5;"></i>
              <p style="font-size: 13px; font-weight: 600; margin: 0;">${emptyMsg}</p>
            </div>
          `;
        }
      } catch (e) {
        if (!availablePackages || availablePackages.length === 0) {
          document.getElementById('package_container').innerHTML = `
            <div style="text-align:center; padding:32px 16px; color:#EF4444;">
              <i class="fa fa-exclamation-triangle fa-2x" style="margin-bottom: 10px; display: block;"></i>
              <p style="font-size: 13px; font-weight: 600; margin: 0;">Gagal memuat daftar paket voucher.</p>
              <button onclick="loadPackages()" style="margin-top: 12px; padding: 6px 14px; border-radius: 8px; border: 1px solid #EF4444; background: transparent; color: #EF4444; font-size: 12px; font-weight: 700; cursor: pointer;">
                <i class="fa fa-refresh"></i> Coba Lagi
              </button>
            </div>
          `;
        }
      }
    }

    function setPaymentModalState(state) {
      const states = ['pay_loading', 'pay_ready', 'pay_success', 'pay_rejected', 'pay_expired', 'pay_unavailable'];
      states.forEach(s => {
        const el = document.getElementById(s);
        if (el) el.style.display = (s === state ? (s === 'pay_loading' ? 'flex' : 'block') : 'none');
      });
      const closeBtn = document.getElementById('pay_header_close_btn');
      if (closeBtn) {
        closeBtn.style.display = (state === 'pay_loading' ? 'none' : 'flex');
      }
    }

    async function proceedCheckout() {
      if (!selectedPackage) {
        alert('Pilih paket voucher terlebih dahulu.');
        return;
      }
      const phoneInput = (document.getElementById('phone')?.value || '').trim();
      const fullPhone = phoneInput ? ('62' + phoneInput.replace(/^0+/, '')) : '628000000000';

      openModal('modal_payment');
      setPaymentModalState('pay_loading');

      try {
        const res = await fetch('buy_process.php?action=create_order', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            session: activeSession,
            profile: selectedPackage.name,
            price: selectedPackage.price,
            validity: selectedPackage.validity || '',
            phone: fullPhone,
            payment_method: 'qris'
          })
        });
        const text = await res.text();
        let data = null;
        try {
          data = JSON.parse(text);
        } catch (e) {
          console.error("Server response:", text);
        }

        if (data && (data.success === true || data.status === 'success' || data.order_id || (data.order && data.order.order_id))) {
          const orderObj = data.order || data;
          showPaymentReady(orderObj);
          startPolling(orderObj.order_id || data.order_id);
        } else {
          const reason = (data && data.message) ? data.message : 'Metode pembayaran online belum dikonfigurasi atau belum aktif. Silakan hubungi admin.';
          const unEl = document.getElementById('unavailable_reason');
          if (unEl) unEl.innerText = reason;
          setPaymentModalState('pay_unavailable');
        }
      } catch (err) {
        const unEl = document.getElementById('unavailable_reason');
        if (unEl) unEl.innerText = 'Terjadi kesalahan koneksi ke server pembayaran. Silakan hubungi admin.';
        setPaymentModalState('pay_unavailable');
      }
    }

    function startCountdown(val, isTimestamp = false) {
      if (countdownTimer) clearInterval(countdownTimer);
      const targetTimestamp = isTimestamp ? Number(val) : (Date.now() + ((Number(val) || 300) * 1000));
      const el = document.getElementById('pay_countdown');
      if (!el) return;

      function tick() {
        const rem = Math.max(0, Math.floor((targetTimestamp - Date.now()) / 1000));
        if (rem <= 0) {
          clearInterval(countdownTimer);
          el.innerText = '00:00 (Kedaluwarsa)';
          el.style.color = '#EF4444';
          if (pollTimer) clearInterval(pollTimer);
          
          let expiredOrderId = '';
          const raw = localStorage.getItem(getPendingStorageKey());
          if (raw) {
            try {
              const pending = JSON.parse(raw);
              expiredOrderId = pending.order_id || '';
            } catch (e) {}
          }
          if (!expiredOrderId) {
            const elId = document.getElementById('pay_order_id');
            if (elId && elId.innerText && elId.innerText !== '-') expiredOrderId = elId.innerText.trim();
          }

          clearPendingOrder();
          setPaymentModalState('pay_expired');

          if (expiredOrderId) {
            fetch('buy_process.php?action=notify_order_expired', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ order_id: expiredOrderId, session: activeSession })
            }).catch(err => console.warn('Expire order sync error:', err));
          }

          setTimeout(() => {
            closeModal('modal_payment');
          }, 2500);
          return;
        }
        const m = Math.floor(rem / 60).toString().padStart(2, '0');
        const s = (rem % 60).toString().padStart(2, '0');
        el.innerText = `${m}:${s}`;
      }

      tick();
      countdownTimer = setInterval(tick, 1000);
    }

    function startPolling(orderId) {
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(async () => {
        try {
          const res = await fetch(`buy_process.php?action=check_order_status&order_id=${encodeURIComponent(orderId)}&session=${encodeURIComponent(activeSession)}`);
          const data = await res.json();
          const st = (data && (data.status || data.payment_status || '')).toLowerCase();
          if (st === 'paid' || st === 'approved' || st === 'success' || data.voucher_code) {
            clearInterval(pollTimer);
            if (countdownTimer) clearInterval(countdownTimer);
            showPaymentSuccess(data);
          } else if (st === 'rejected' || st === 'cancelled' || st === 'failed' || st === 'expired') {
            clearInterval(pollTimer);
            if (countdownTimer) clearInterval(countdownTimer);
            showPaymentRejected(data);
          }
        } catch (e) {}
      }, 3000);
    }

    function getPendingStorageKey() {
      return 'nodera_pending_order_' + (activeSession || 'default');
    }

    function savePendingOrder(order, durSec) {
      try {
        const totalAmt = order.total_amount || (selectedPackage ? selectedPackage.price : (order.amount || 0));
        let targetExpiresAt = 0;

        const existingRaw = localStorage.getItem(getPendingStorageKey());
        if (existingRaw) {
          try {
            const existing = JSON.parse(existingRaw);
            if (existing && existing.order_id === order.order_id && Number(existing.expires_at) > Date.now()) {
              targetExpiresAt = Number(existing.expires_at);
            }
          } catch (e) {}
        }

        if (!targetExpiresAt && order.expires_at) {
          const parsed = new Date(String(order.expires_at).replace(' ', 'T')).getTime();
          if (!isNaN(parsed) && parsed > Date.now()) {
            targetExpiresAt = parsed;
          }
        }

        if (!targetExpiresAt) {
          const sec = Number(durSec) || Number(order.timeout) || Number(order.duration_seconds) || 300;
          targetExpiresAt = Date.now() + (sec * 1000);
        }

        const dataToSave = {
          order_id: order.order_id,
          total_amount: totalAmt,
          package_name: (selectedPackage ? selectedPackage.name : (order.profile || 'Voucher Hotspot')),
          package_validity: (selectedPackage ? selectedPackage.validity : (order.validity || 'Masa Aktif Hotspot')),
          merchant_name: order.merchant_name || order.merchant || activeSession,
          checkout_url: order.checkout_url || order.payment_url || '',
          qr_string: order.qr_string || order.qr_content || order.qr_code || '',
          qr_image_url: order.qr_image_url || '',
          expires_at: targetExpiresAt,
          raw_order: order
        };
        localStorage.setItem(getPendingStorageKey(), JSON.stringify(dataToSave));
        checkPendingOrderDock();
        return targetExpiresAt;
      } catch (e) {
        console.warn("Storage error:", e);
        return Date.now() + 300000;
      }
    }

    function clearPendingOrder() {
      try {
        localStorage.removeItem(getPendingStorageKey());
        const dock = document.getElementById('dock_resume_payment');
        if (dock) dock.style.display = 'none';
      } catch (e) {}
    }

    function checkPendingOrderDock() {
      try {
        const raw = localStorage.getItem(getPendingStorageKey());
        if (!raw) {
          const dock = document.getElementById('dock_resume_payment');
          if (dock) dock.style.display = 'none';
          return;
        }
        const pending = JSON.parse(raw);
        const remSec = Math.floor((Number(pending.expires_at) - Date.now()) / 1000);
        if (remSec <= 0) {
          clearPendingOrder();
          return;
        }

        const dock = document.getElementById('dock_resume_payment');
        const timerEl = document.getElementById('dock_resume_timer');
        const pkgEl = document.getElementById('dock_resume_pkg');
        const amtEl = document.getElementById('dock_resume_amount');

        const modal = document.getElementById('modal_payment');
        const isModalOpen = modal && modal.style.display === 'flex';

        if (dock) {
          dock.style.display = isModalOpen ? 'none' : 'block';
        }

        if (timerEl) {
          const m = Math.floor(remSec / 60).toString().padStart(2, '0');
          const s = (remSec % 60).toString().padStart(2, '0');
          timerEl.innerText = `${m}:${s}`;
        }
        if (pkgEl) pkgEl.innerText = pending.package_name || 'Voucher Hotspot';
        if (amtEl) amtEl.innerText = `Rp ${formatRupiah(pending.total_amount)}`;

        if (!pollTimer && pending.order_id) {
          startPolling(pending.order_id);
        }
      } catch (e) {
        console.warn("Pending check error:", e);
      }
    }

    function resumePendingPayment() {
      try {
        const raw = localStorage.getItem(getPendingStorageKey());
        if (!raw) return;
        const pending = JSON.parse(raw);
        const targetExpiresAt = Number(pending.expires_at);
        const remSec = Math.floor((targetExpiresAt - Date.now()) / 1000);
        if (remSec <= 0) {
          alert("Waktu pembayaran untuk pesanan ini telah habis.");
          clearPendingOrder();
          return;
        }

        openModal('modal_payment');
        showPaymentReady(pending.raw_order || pending, targetExpiresAt);
        startPolling(pending.order_id);

        const dock = document.getElementById('dock_resume_payment');
        if (dock) dock.style.display = 'none';
      } catch (e) {
        console.error("Resume error:", e);
      }
    }

    function cancelPendingPayment() {
      if (confirm("Apakah Anda ingin membatalkan pesanan pembayaran ini?")) {
        const raw = localStorage.getItem(getPendingStorageKey());
        let orderId = '';
        if (raw) {
          try {
            const pending = JSON.parse(raw);
            orderId = pending.order_id || '';
          } catch (e) {}
        }
        if (!orderId) {
          const el = document.getElementById('pay_order_id');
          if (el && el.innerText && el.innerText !== '-') orderId = el.innerText.trim();
        }

        if (pollTimer) clearInterval(pollTimer);
        if (countdownTimer) clearInterval(countdownTimer);
        clearPendingOrder();
        closeModal('modal_payment');

        if (orderId) {
          fetch('buy_process.php?action=cancel_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, session: activeSession })
          }).catch(err => console.warn('Cancel order sync error:', err));
        }
      }
    }

    function cancelAndCloseModal() {
      if (confirm("Tutup jendela pembayaran? Anda dapat melanjutkannya nanti melalui bilah bawah.")) {
        closeModal('modal_payment');
      }
    }

    function toggleDetailsDrawer() {
      const el = document.getElementById('details_drawer');
      const arrow = document.getElementById('details_arrow');
      if (!el) return;
      if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'flex';
        if (arrow) arrow.style.transform = 'rotate(180deg)';
      } else {
        el.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
      }
    }

    function toggleInstructions() {
      const el = document.getElementById('instructions_box');
      const arrow = document.getElementById('inst_arrow');
      if (!el) return;
      if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
        if (arrow) arrow.style.transform = 'rotate(180deg)';
      } else {
        el.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
      }
    }

    function copyPayAmount() {
      const el = document.getElementById('pay_total_amount');
      const text = el ? el.innerText.replace(/[^\d]/g, '') : '';
      const amount = text || (selectedPackage ? String(selectedPackage.price) : '0');
      navigator.clipboard.writeText(amount).then(() => {
        const t = document.getElementById('toast_copy_amount');
        const icon = document.getElementById('icon_copy_amount');
        if (t) { 
          t.style.display = 'inline-block'; 
          if (icon) icon.style.display = 'none';
          setTimeout(() => { 
            t.style.display = 'none'; 
            if (icon) icon.style.display = 'inline-block';
          }, 2000); 
        }
      });
    }

    function copyOrderId() {
      const el = document.getElementById('pay_order_id');
      const orderId = el ? el.innerText : '';
      navigator.clipboard.writeText(orderId).then(() => {
        const t = document.getElementById('toast_copy_order');
        const icon = document.getElementById('icon_copy_order');
        if (t) { 
          t.style.display = 'inline-block'; 
          if (icon) icon.style.display = 'none';
          setTimeout(() => { 
            t.style.display = 'none'; 
            if (icon) icon.style.display = 'inline-block';
          }, 2000); 
        }
      });
    }

    function renderQr(order) {
      const canvas = document.getElementById('qris_canvas');
      const img = document.getElementById('qris_img');
      
      const rawPayload = order.qr_string || order.dynamic_qris_string || order.qris_string || order.raw_qr || (order.qris ? (order.qris.qr_string || order.qris.raw_qr) : '') || order.checkout_url || '';
      
      let imgSrc = '';
      if (order.qr_png_uri && order.qr_png_uri.startsWith('data:image/')) imgSrc = order.qr_png_uri;
      else if (order.qr_data_uri && order.qr_data_uri.startsWith('data:image/')) imgSrc = order.qr_data_uri;
      else if (order.qr_svg && order.qr_svg.startsWith('data:image/')) imgSrc = order.qr_svg;
      else if (order.qris && order.qris.svg && order.qris.svg.startsWith('data:image/')) imgSrc = order.qris.svg;
      else if (order.qris && order.qris.image_url && order.qris.image_url.startsWith('http')) imgSrc = order.qris.image_url;
      else if (order.qr_image_url && order.qr_image_url.startsWith('http')) imgSrc = order.qr_image_url;

      // 1. Prioritize client-side offline canvas QRious rendering
      if (rawPayload && typeof QRious !== 'undefined' && canvas) {
        try {
          new QRious({
            element: canvas,
            value: rawPayload,
            size: 300,
            level: 'M'
          });
          canvas.style.display = 'block';
          if (img) img.style.display = 'none';
          return;
        } catch (e) {
          console.warn('QRious render fallback:', e);
        }
      }

      // 2. Fallback to image tag
      if (imgSrc && img) {
        img.src = imgSrc;
        img.style.display = 'block';
        if (canvas) canvas.style.display = 'none';
      } else if (rawPayload && img) {
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' + encodeURIComponent(rawPayload);
        img.style.display = 'block';
        if (canvas) canvas.style.display = 'none';
      }
    }

    function downloadQrisImage() {
      const qrCanvas = document.getElementById('qris_canvas');
      const qrImg = document.getElementById('qris_img');
      const elOrd = document.getElementById('pay_order_id');
      const orderId = (elOrd ? elOrd.innerText : 'payment') || 'payment';

      const fallbackDownload = (dataUrl) => {
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = `QRIS-${orderId}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      };

      try {
        if (qrCanvas && qrCanvas.style.display !== 'none' && qrCanvas.width > 0) {
          const pngUrl = qrCanvas.toDataURL('image/png');
          fallbackDownload(pngUrl);
          return;
        }

        if (qrImg && qrImg.src) {
          const img = new Image();
          img.crossOrigin = 'anonymous';
          img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth || 600;
            canvas.height = img.naturalHeight || 600;
            const ctx = canvas.getContext('2d');
            if (ctx) {
              ctx.fillStyle = '#FFFFFF';
              ctx.fillRect(0, 0, canvas.width, canvas.height);
              ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
              fallbackDownload(canvas.toDataURL('image/png'));
              return;
            }
            fallbackDownload(qrImg.src);
          };
          img.onerror = () => fallbackDownload(qrImg.src);
          img.src = qrImg.src;
          return;
        }
      } catch (e) {
        if (qrCanvas) fallbackDownload(qrCanvas.toDataURL('image/png'));
      }
    }

    async function manualCheckStatus() {
      const spin = document.getElementById('manual_check_spin');
      const btn = document.getElementById('btn_manual_check');
      const alertBox = document.getElementById('payment_status_drawer');
      const raw = localStorage.getItem(getPendingStorageKey());
      if (!raw) return;

      if (spin) spin.classList.add('fa-spin');
      if (btn) btn.style.opacity = '0.7';

      try {
        const pending = JSON.parse(raw);
        const orderId = pending.order_id;
        const res = await fetch(`buy_process.php?action=check_order_status&order_id=${encodeURIComponent(orderId)}&session=${encodeURIComponent(activeSession)}`);
        const data = await res.json();
        const st = (data && (data.status || data.payment_status || '')).toLowerCase();

        if (st === 'paid' || st === 'approved' || st === 'success' || data.voucher_code) {
          if (pollTimer) clearInterval(pollTimer);
          if (countdownTimer) clearInterval(countdownTimer);
          showPaymentSuccess(data);
          return;
        } else if (st === 'rejected' || st === 'cancelled' || st === 'failed' || st === 'expired') {
          if (pollTimer) clearInterval(pollTimer);
          if (countdownTimer) clearInterval(countdownTimer);
          showPaymentRejected(data);
          return;
        } else {
          if (alertBox) {
            alertBox.style.display = 'flex';
          }
        }
      } catch (e) {
        if (alertBox) {
          alertBox.style.display = 'flex';
        }
      } finally {
        setTimeout(() => {
          if (spin) spin.classList.remove('fa-spin');
          if (btn) btn.style.opacity = '1';
        }, 600);
      }
    }

    function showPaymentReady(order, overrideExpiresAt = null) {
      setPaymentModalState('pay_ready');

      // Reset page header in modal
      const headerPill = document.getElementById('checkout_badge_text');
      if (headerPill) headerPill.innerText = I18N.checkout_badge_pay_ready;
      const headerTitle = document.getElementById('checkout_main_title');
      if (headerTitle) headerTitle.innerText = I18N.checkout_title_pay_ready;
      const headerSub = document.getElementById('checkout_main_subtitle');
      if (headerSub) headerSub.innerText = I18N.checkout_sub_pay_ready;

      const lunasBadge = document.getElementById('pay_header_lunas_badge');
      if (lunasBadge) lunasBadge.style.display = 'none';
      const closeBtn = document.getElementById('pay_header_close_btn');
      if (closeBtn) closeBtn.style.display = 'inline-flex';

      const alertBox = document.getElementById('payment_status_drawer');
      if (alertBox) alertBox.style.display = 'none';

      const basePkgPrice = Number(order.amount || order.price || order.package_price || (selectedPackage ? selectedPackage.price : 0)) || 0;
      const totalAmt = Number(order.total_amount || (selectedPackage ? selectedPackage.price : 0)) || 0;
      const adminFee = Math.max(0, totalAmt - basePkgPrice);

      const formattedAmt = formatCurrency(totalAmt);
      const formattedBasePrice = formatCurrency(basePkgPrice);
      const formattedFee = formatCurrency(adminFee);

      if (selectedPackage) {
        document.getElementById('pay_pkg_title').innerText = selectedPackage.name;
        document.getElementById('pay_pkg_validity').innerText = selectedPackage.validity || I18N.hotspot_validity;
      }

      const pkgPriceEl = document.getElementById('pay_pkg_price');
      if (pkgPriceEl) pkgPriceEl.innerText = formattedBasePrice;

      const feeRow = document.getElementById('pay_fee_row');
      const feeValEl = document.getElementById('pay_fee_val');
      const feeLabelEl = document.getElementById('pay_fee_label');
      if (feeRow && feeValEl) {
        if (adminFee > 0) {
          feeValEl.innerText = `+${formattedFee}`;
          if (feeLabelEl) {
            feeLabelEl.innerText = I18N.service_fee_qris;
          }
          feeRow.style.display = 'flex';
        } else {
          feeRow.style.display = 'none';
        }
      }

      document.getElementById('pay_total_amount').innerText = formattedAmt;
      const drawerTotal = document.getElementById('pay_drawer_total');
      if (drawerTotal) drawerTotal.innerText = formattedAmt;

      document.getElementById('pay_order_id').innerText = order.order_id;
      
      const mTitle = document.getElementById('pay_merchant_title');
      if (mTitle) {
        mTitle.innerText = (order.merchant_name || order.merchant || activeSession || 'NODERA HOTSPOT').toUpperCase();
      }

      const nmidBox = document.getElementById('pay_nmid_box');
      if (nmidBox) {
        if (order.nmid && order.nmid.trim() !== '') {
          nmidBox.innerText = `NMID: ${order.nmid}`;
          nmidBox.style.display = 'block';
        } else {
          nmidBox.style.display = 'none';
        }
      }

      const btnGw = document.getElementById('btn_open_gateway_checkout');
      if (btnGw) {
        const chkUrl = order.checkout_url || (order.raw_order ? order.raw_order.checkout_url : '') || (order.qris ? order.qris.checkout_url : '');
        if (chkUrl && String(chkUrl).startsWith('http')) {
          const gwName = (order.gateway_provider || (order.raw_order ? order.raw_order.gateway_provider : '') || 'Payment Gateway').toUpperCase();
          btnGw.href = chkUrl;
          btnGw.innerHTML = `<i class="fa fa-external-link" style="margin-right: 6px;"></i> <span>${I18N.open_payment_page.replace('%s', gwName)}</span>`;
          btnGw.style.display = 'inline-flex';
        } else {
          btnGw.style.display = 'none';
        }
      }

      const durSec = Number(order.timeout) || Number(order.duration_seconds) || 300;
      const targetExpiresAt = overrideExpiresAt || savePendingOrder(order, durSec);
      startCountdown(targetExpiresAt, true);

      renderQr(order);
    }

    function showPaymentSuccess(order) {
      clearPendingOrder();
      setPaymentModalState('pay_success');

      // Update page header in modal
      const headerPill = document.getElementById('checkout_badge_text');
      if (headerPill) headerPill.innerText = I18N.checkout_badge_success;
      const headerTitle = document.getElementById('checkout_main_title');
      if (headerTitle) headerTitle.innerText = I18N.checkout_title_success;
      const headerSub = document.getElementById('checkout_main_subtitle');
      if (headerSub) headerSub.innerText = I18N.checkout_sub_success;
      
      const lunasBadge = document.getElementById('pay_header_lunas_badge');
      if (lunasBadge) lunasBadge.style.display = 'inline-flex';
      const closeBtn = document.getElementById('pay_header_close_btn');
      if (closeBtn) closeBtn.style.display = 'none';

      let vCode = order.voucher_code || order.username || '';
      let vPass = order.password || '';
      if (!vCode && order.voucher) {
        if (typeof order.voucher === 'string') {
          vCode = order.voucher;
        } else if (typeof order.voucher === 'object') {
          vCode = order.voucher.username || order.voucher.code || order.voucher.user || '';
          vPass = order.voucher.password || order.voucher.pass || '';
        }
      }
      if (!vCode) vCode = 'TERDAFTAR';
      if (!vPass) vPass = vCode;

      document.getElementById('success_voucher_code').innerText = vCode;
      
      const totalAmt = order.total_amount || (selectedPackage ? selectedPackage.price : 0);
      const formattedAmt = formatCurrency(totalAmt);
      
      const dispAmt = document.getElementById('success_amount_display');
      if (dispAmt) dispAmt.innerText = formattedAmt;
      
      const ordIdTxt = document.getElementById('success_order_id_txt');
      if (ordIdTxt) ordIdTxt.innerText = order.order_id || '-';
      
      const recOrd = document.getElementById('receipt_order_id');
      if (recOrd) recOrd.innerText = order.order_id || '-';
      
      const recPkg = document.getElementById('receipt_pkg_title');
      if (recPkg) recPkg.innerText = order.package_name || (selectedPackage ? selectedPackage.name : I18N.voucher_hotspot);
      
      const recTot = document.getElementById('receipt_total');
      if (recTot) recTot.innerText = formattedAmt;

      const autoLink = document.getElementById('btn_auto_connect');
      if (autoLink) {
        autoLink.href = `http://${window.location.hostname}/login?username=${encodeURIComponent(vCode)}&password=${encodeURIComponent(vPass)}`;
      }
    }

    function showPaymentRejected(data) {
      clearPendingOrder();
      const msg = (data && (data.message || data.description)) || I18N.order_rejected_admin;
      const rejReason = document.getElementById('rejected_reason');
      if (rejReason) rejReason.innerText = msg;
    }

    function copyVoucherCode() {
      const code = document.getElementById('success_voucher_code').innerText;
      navigator.clipboard.writeText(code).then(() => alert(I18N.voucher_copied_alert));
    }

    async function submitCheckQuota() {
      const code = document.getElementById('input_check_code').value.trim();
      const resBox = document.getElementById('quota_result');
      if (!code) return;

      resBox.style.display = 'block';
      resBox.innerHTML = `<p style="text-align:center; font-size:12.5px; opacity:0.7;"><i class="fa fa-spinner fa-spin"></i> ${I18N.checking_status}</p>`;

      try {
        const res = await fetch(`buy_process.php?action=check_voucher_quota&code=${encodeURIComponent(code)}&session=${encodeURIComponent(activeSession)}`);
        const data = await res.json();

        if (data.success) {
          resBox.innerHTML = `
            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 12px; border-radius: 10px; font-size: 13px;">
              <b style="color: #16A34A;"><i class="fa fa-check-circle"></i> Voucher Aktif</b>
              <div style="margin-top: 6px; display: flex; justify-content: space-between;">
                <span>Sisa Waktu:</span> <b>${escapeHtml(data.uptime_left || 'Aktif')}</b>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span>Kuota Terpakai:</span> <b>${escapeHtml(data.bytes_used || '0 MB')}</b>
              </div>
            </div>`;
        } else {
          resBox.innerHTML = `<div style="background: #FEF2F2; border: 1px solid #FECACA; padding: 10px; border-radius: 10px; font-size: 12.5px; color: #DC2626;">${escapeHtml(data.message || I18N.voucher_not_found)}</div>`;
        }
      } catch (e) {
        resBox.innerHTML = `<div style="color: #DC2626; font-size: 12.5px;">${I18N.failed_connect_router}</div>`;
      }
    }

    async function initTopTicker() {
      const tickerEl = document.getElementById('top_ticker');
      if (!tickerEl) return;

      let orders = [];
      try {
        const res = await fetch(`buy_process.php?action=get_recent_orders&session=${encodeURIComponent(currentSession)}&_t=${Date.now()}`);
        const data = await res.json();
        if (data && data.success && Array.isArray(data.data) && data.data.length > 0) {
          orders = data.data;
        }
      } catch (e) {
        // Fallback below
      }

      // Fallback local realistic purchases if empty so ticker always cycles smoothly
      if (!orders || orders.length === 0) {
        const samplePkgs = (Array.isArray(allPackages) && allPackages.length > 0) 
          ? allPackages.map(p => p.name || p.profile) 
          : [I18N.voucher_hotspot];
        const prefixes = ['0812', '0857', '0821', '0878', '0852', '0896', '0813', '0853'];
        orders = prefixes.map((pfx, i) => {
          const sfx = String(Math.floor(1000 + Math.random() * 9000));
          return {
            phone: `${pfx}-****-${sfx}`,
            profile: samplePkgs[i % samplePkgs.length] || I18N.voucher_hotspot,
            time_ago: '',
            type: 'order'
          };
        });
      }

      let idx = 0;
      function showNextTicker() {
        if (!orders || orders.length === 0) return;
        const ord = orders[idx % orders.length];
        const txtEl = document.getElementById('ticker_text');
        if (txtEl) {
          const timeStr = ord.time_ago ? ` (${ord.time_ago})` : '';
          txtEl.innerText = `${ord.phone} ${I18N.just_bought} ${ord.profile}${timeStr}`;
        }

        // Slide in & stay for 4.2s
        tickerEl.classList.add('visible');

        // Auto slide out / hide
        setTimeout(() => {
          tickerEl.classList.remove('visible');
        }, 4200);

        idx++;
      }

      // Start first notification after 1.5s, cycle every 8.5s (4.2s visible, 4.3s hidden)
      setTimeout(() => {
        showNextTicker();
        setInterval(showNextTicker, 8500);
      }, 1500);
    }

    function openModal(id) {
      const el = document.getElementById(id);
      if (el) {
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    }

    function closeModal(id) {
      const el = document.getElementById(id);
      if (el) {
        el.style.display = 'none';
        document.body.style.overflow = '';
      }
      if (id === 'modal_payment') {
        if (pollTimer) clearInterval(pollTimer);
        if (countdownTimer) clearInterval(countdownTimer);
        setPaymentModalState('pay_loading');
      }
    }

    function formatNumber(num) {
      return (Number(num) || 0).toLocaleString('id-ID');
    }

    function formatRupiah(num) {
      return (Number(num) || 0).toLocaleString('id-ID');
    }

    function formatCurrency(num) {
      return (Number(num) || 0).toLocaleString('id-ID');
    }

    function escapeHtml(str) {
      return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadPackages();
    checkPendingOrderDock();
    setInterval(checkPendingOrderDock, 1000);
      initTopTicker();
    });
  </script>
</body>
</html>