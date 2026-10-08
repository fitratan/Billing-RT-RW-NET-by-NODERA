<?php
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}
error_reporting(0);

// Include compatibility layer
if (file_exists(__DIR__ . '/include/compatibility.php')) {
    include_once __DIR__ . '/include/compatibility.php';
} elseif (file_exists(__DIR__ . '/compatibility.php')) {
    include_once __DIR__ . '/compatibility.php';
}

// License Check (STRICT: Reject API order generation if Mikhmon expired / suspended)
if (file_exists(__DIR__ . '/include/license.php')) {
    include_once __DIR__ . '/include/license.php';
} elseif (file_exists(__DIR__ . '/license.php')) {
    include_once __DIR__ . '/license.php';
}

if (function_exists('mikhmon_is_expired') && mikhmon_is_expired()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    $expMsg = !empty($isFr) ? 'Le service hotspot a expiré. Veuillez renouveler l\'abonnement.' : (!empty($isIndo) ? 'Layanan hotspot ini telah berakhir masa aktifnya. Silakan lakukan perpanjangan langganan.' : 'Hotspot service has expired. Please renew the subscription.');
    echo json_encode(['status' => 'error', 'message' => $expMsg]);
    exit;
}
if (function_exists('mikhmon_is_suspended') && mikhmon_is_suspended()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    $suspMsg = !empty($isFr) ? 'Ce service hotspot est actuellement suspendu.' : (!empty($isIndo) ? 'Layanan hotspot ini sedang ditangguhkan.' : 'Hotspot service is currently suspended.');
    echo json_encode(['status' => 'error', 'message' => $suspMsg]);
    exit;
}

if (!function_exists('mikhmon_resolve_profile_price')) {
    function mikhmon_resolve_profile_price($matchedProf, $pName, $npCfg, $isAfrica = false) {
        $price = 0;
        $onLogin = $matchedProf['on-login'] ?? '';
        if (!empty($onLogin)) {
            $parts = explode(',', (string)$onLogin);
            $costPrice = isset($parts[2]) ? (float) trim($parts[2]) : 0;
            $sellingPrice = isset($parts[4]) ? (float) trim($parts[4]) : 0;
            $price = ($sellingPrice > 0) ? $sellingPrice : $costPrice;
        }
        if ($price <= 0 && isset($matchedProf['price']) && (float)$matchedProf['price'] > 0) {
            $price = (float)$matchedProf['price'];
        }
        if ($price <= 0 && !empty($npCfg['profile_prices'][$pName])) {
            $price = (float)$npCfg['profile_prices'][$pName];
        }
        if ($price <= 0) {
            $comment = (string)($matchedProf['comment'] ?? '');
            if ($isAfrica) {
                if (preg_match('/(?:fcfa|f|cfa|xof|frs?)\s*(\d+)/i', $comment, $pm) || preg_match('/(\d+)\s*(?:fcfa|f|cfa|xof|frs?)/i', $comment, $pm)) {
                    $price = (float)$pm[1];
                } elseif (preg_match('/(?:fcfa|f|cfa|xof|frs?)\s*(\d+)/i', $pName, $pm) || preg_match('/(\d+)\s*(?:fcfa|f|cfa|xof|frs?)/i', $pName, $pm)) {
                    $price = (float)$pm[1];
                } elseif (preg_match('/,(\d{1,7}),/', $onLogin, $pm)) {
                    $price = (float)$pm[1];
                }
            } else {
                if (preg_match('/(?:rp|idr)\.?\s*(\d+[\.\d]*)/i', $comment, $pm) || preg_match('/(\d+[\.\d]*)\s*(?:rp|idr|k)/i', $comment, $pm)) {
                    $rawVal = str_replace('.', '', $pm[1]);
                    $price = (float)$rawVal;
                    if (stripos($pm[0], 'k') !== false && $price < 1000) { $price *= 1000; }
                } elseif (preg_match('/(?:rp|idr)\.?\s*(\d+[\.\d]*)/i', $pName, $pm) || preg_match('/(\d+[\.\d]*)\s*(?:rp|idr|k)/i', $pName, $pm)) {
                    $rawVal = str_replace('.', '', $pm[1]);
                    $price = (float)$rawVal;
                    if (stripos($pm[0], 'k') !== false && $price < 1000) { $price *= 1000; }
                } elseif (preg_match('/,(\d{3,7}),/', $onLogin, $pm)) {
                    $price = (float)$pm[1];
                }
            }
        }
        if ($price <= 0) {
            $nLow = strtolower($pName);
            if (strpos($nLow, 'gratuit') !== false || strpos($nLow, 'free') !== false || strpos($nLow, 'gratis') !== false || strpos($nLow, 'trial') !== false) {
                $price = 0;
            } else {
                $price = $isAfrica ? 100 : 2000;
            }
        }
        return $price;
    }
}

if (!function_exists('mikhmon_resolve_profile_validity')) {
    function mikhmon_resolve_profile_validity($matchedProf, $pName, $npCfg, $isAfrica = false) {
        $validity = '';
        $onLogin = $matchedProf['on-login'] ?? '';
        if (!empty($onLogin)) {
            $parts = explode(',', (string)$onLogin);
            if (isset($parts[3]) && trim($parts[3]) !== '' && trim($parts[3]) !== '0') {
                $validity = trim($parts[3]);
            }
        }
        if (empty($validity) && !empty($matchedProf['validity']) && $matchedProf['validity'] !== 'Validité Standard' && $matchedProf['validity'] !== 'Masa Aktif Hotspot') {
            $validity = trim($matchedProf['validity']);
        }
        if (empty($validity) && !empty($npCfg['profile_validity'][$pName])) {
            $validity = trim($npCfg['profile_validity'][$pName]);
        }
        return mikhmon_infer_validity($pName, $validity, $isAfrica);
    }
}

if (!function_exists('mikhmon_infer_validity')) {
    function mikhmon_infer_validity($name, $rawValidity, $isFrench = false) {
        $val = trim((string)$rawValidity);
        if (!empty($val) && $val !== 'Standar' && $val !== 'Standard' && $val !== 'Validité Standard' && $val !== 'Hotspot') {
            return $val;
        }
        $n = strtolower($name);
        if (preg_match('/(\d+)\s*(min|menit|m)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Minutes' : $m[1] . ' Menit';
        }
        if (preg_match('/(\d+)\s*(h|jam|heures?|hr|hour)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Heures' : $m[1] . ' Jam';
        }
        if (preg_match('/(\d+)\s*(j|hari|jours?|d|day|days)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Jours' : $m[1] . ' Hari';
        }
        if (preg_match('/(\d+)\s*(b|bln|bulan|mois|mth|month)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Mois' : $m[1] . ' Bulan';
        }
        if (strpos($n, 'bulan') !== false || strpos($n, 'mois') !== false) {
            return $isFrench ? '30 Jours' : '30 Hari';
        }
        if (strpos($n, 'minggu') !== false || strpos($n, 'semaine') !== false) {
            return $isFrench ? '7 Jours' : '7 Hari';
        }
        if (strpos($n, 'hari') !== false || strpos($n, 'jour') !== false) {
            return $isFrench ? '24 Heures' : '24 Jam';
        }
        return $isFrench ? 'Illimité' : 'Aktif';
    }
}

/**
 * MIKHMON Engine — Buy Voucher Processing Backend (QRIS & Manual Telegram ACC)
 * by NODERA (nodera.id)
 */
header('Content-Type: application/json; charset=utf-8');
// hide all error
error_reporting(0);
ini_set('display_errors', 0);

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];
$jsonBody = is_array($jsonBody) ? $jsonBody : [];

$action = trim($_GET['action'] ?? $_POST['action'] ?? ($jsonBody['action'] ?? ''));
$session = trim($_GET['session'] ?? $_POST['session'] ?? ($jsonBody['session'] ?? ''));
// session decoded automatically by php


// Load configs
$configFile = __DIR__ . '/include/config.php';
$npConfigFile = __DIR__ . '/include/noderapay_config.php';
$waConfigFile = __DIR__ . '/include/whatsapp_config.php';
$tgConfigFile = __DIR__ . '/include/telegram_config.php';
$ordersFile = __DIR__ . '/include/orders_data.json';

if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'message' => 'Konfigurasi Mikhmon tidak ditemukan.']);
    exit;
}

include_once $configFile;

// Auto-select session if not passed or not found in $data
$requestedLoc = trim($_GET['loc'] ?? ($_POST['loc'] ?? ($jsonBody['loc'] ?? ($_GET['location'] ?? ''))));
if (empty($session) && !empty($requestedLoc)) {
    $locConfigFile = __DIR__ . '/include/location_config.php';
    if (file_exists($locConfigFile)) {
        $location_data = [];
        include $locConfigFile;
        $slugReq = strtolower(preg_replace('/[^a-z0-9]/', '', $requestedLoc));
        foreach ($location_data['locations'] ?? [] as $k => $locName) {
            $slugKey = strtolower(preg_replace('/[^a-z0-9]/', '', $k));
            $slugLoc = strtolower(preg_replace('/[^a-z0-9]/', '', $locName));
            if ($slugReq === $slugKey || $slugReq === $slugLoc) {
                $session = $k;
                break;
            }
        }
    }
}

if (!empty($session) && !isset($data[$session]) && isset($data) && is_array($data)) {
    // Try relaxed matching
    foreach ($data as $k => $v) {
        if ($k === 'mikhmon') continue;
        if (strcasecmp($k, $session) === 0 || strpos($k, $session) !== false || strpos($session, $k) !== false) {
            $session = $k;
            break;
        }
    }
}

if (empty($session) || !isset($data[$session])) {
    $locConfigFile = __DIR__ . '/include/location_config.php';
    if (file_exists($locConfigFile)) {
        $location_data = [];
        include $locConfigFile;
        if (!empty($location_data['primary']) && isset($data[$location_data['primary']])) {
            $session = $location_data['primary'];
        }
    }

    if ((empty($session) || !isset($data[$session])) && isset($data) && is_array($data)) {
        foreach ($data as $k => $v) {
            if ($k !== 'mikhmon') {
                $session = $k;
                break;
            }
        }
    }
}

include_once __DIR__ . '/include/readcfg.php';
include_once __DIR__ . '/include/whatsapp_helper.php';
include_once __DIR__ . '/include/telegram_helper.php';
include_once __DIR__ . '/include/order_helper.php';

$sessionCfg = $data[$session] ?? null;
$rawIp = $sessionCfg[1] ?? '';
$rawUser = $sessionCfg[2] ?? '';
$rawPass = $sessionCfg[3] ?? '';

$iphost = strpos($rawIp, '!') !== false ? (explode('!', $rawIp)[1] ?? '') : $rawIp;
$userhost = strpos($rawUser, '@|@') !== false ? (explode('@|@', $rawUser)[1] ?? '') : $rawUser;
$passwdhost = strpos($rawPass, '#|#') !== false ? (explode('#|#', $rawPass)[1] ?? '') : $rawPass;
$hotspotname = !empty($sessionCfg[4]) ? (explode('%', $sessionCfg[4])[1] ?? '') : '';
$dnsname = !empty($sessionCfg[5]) ? (explode('^', $sessionCfg[5])[1] ?? '') : '';

$rawCurr = $sessionCfg[6] ?? '';
$currency = strpos($rawCurr, '&') !== false ? (explode('&', $rawCurr)[1] ?? '') : (!empty($rawCurr) ? $rawCurr : 'Rp');

$noderapay_data = [];
if (file_exists($npConfigFile)) {
    include $npConfigFile;
}
$npCfg = $noderapay_data[$session] ?? null;
if (!$npCfg) {
    foreach ($noderapay_data as $s => $c) {
        if (!empty($c['api_key'])) {
            $npCfg = $c;
            break;
        }
    }
}

$resolvedTg = mikhmon_resolve_telegram_config($session);
$tgCfg = $resolvedTg['raw'] ?: [];
$tgCfg['bot_token'] = $resolvedTg['token'];
$tgCfg['brand_name'] = $resolvedTg['brand_name'];

$wa_data = [];
if (file_exists($waConfigFile)) {
    include $waConfigFile;
}
$waCfg = $wa_data[$session] ?? null;
if (!$waCfg || empty($waCfg['api_token'])) {
    foreach ($wa_data as $s => $c) {
        if ($s !== '_global' && !empty($c['api_token'])) {
            $waCfg = $c;
            break;
        }
    }
}
$waCfg = $waCfg ?: [];

// Helper function to normalize NODERA Pay API URL
if (!function_exists('mikhmon_normalize_noderapay_url')) {
    function mikhmon_normalize_noderapay_url($url) {
        $url = trim((string)$url);
        if (empty($url)) {
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || ($_SERVER['SERVER_PORT'] ?? '') == 443 
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $scheme = $is_https ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            return $scheme . "://" . $host . "/api/v1/noderapay";
        }
        $url = rtrim($url, '/');
        $url = preg_replace('#/(webhook.*|report-notification.*|create-qris.*|check.*|api_webhook.*)$#i', '', $url);
        $url = rtrim($url, '/');
        if (!str_contains($url, '/noderapay') && !str_contains($url, '/api/')) {
            $url .= '/api/v1/noderapay';
        }
        return $url;
    }
}

// RouterOS API connector helper
if (!function_exists('mikhmon_get_router_api')) {
    function mikhmon_get_router_api($iphost, $userhost, $passwdhost) {
        if (empty($iphost) || empty($userhost)) {
            return null;
        }
        include_once __DIR__ . '/lib/routeros_api.class.php';
        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 2;
        $API->attempts = 1;
        $API->delay = 0;
        
        $ip = $iphost;
        if (strpos($iphost, ':') !== false) {
            $parts = explode(':', $iphost);
            $ip = $parts[0];
            if (!empty($parts[1]) && is_numeric($parts[1])) {
                $API->port = (int)$parts[1];
            }
        }
        
        $passwd = function_exists('mikhmon_decrypt') ? mikhmon_decrypt($passwdhost) : $passwdhost;
        if ($API->connect($ip, $userhost, $passwd)) {
            return $API;
        }
        return null;
    }
}

// Location & Merchant Resolution
$locConfigFile = __DIR__ . '/include/location_config.php';
$locName = '';
if (file_exists($locConfigFile)) {
    $location_data = [];
    include $locConfigFile;
    $locName = trim($location_data['locations'][$session] ?? '');
}
if (empty($locName) || strtolower($locName) === 'dns') {
    $locName = (!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : ($sessionCfg[1] ?? ucwords(str_replace(['-', '_'], ' ', (string)$session)));
}

$resolvedMerchantName = !empty($npCfg['merchant_name']) && $npCfg['merchant_name'] !== 'NODERA Pay Billing' && $npCfg['merchant_name'] !== 'NODERA Pay'
    ? $npCfg['merchant_name']
    : ($hotspotname ?: ($locName ?: ($sessionCfg[1] ?? ($tgCfg['brand_name'] ?? 'WiFi Hotspot'))));

// Action: GET_PACKAGES (Katalog Paket, Stok & Opsi Pembayaran)
if ($action === 'get_packages') {
    $qrisEnabled = !empty($npCfg['api_key']);
    $manualEnabled = true; // Always available for flexibility (via Telegram Bot ACC)
    $telegramConfigured = !empty($resolvedTg['token']) && !empty($resolvedTg['targets']);
    $bankInfo = !empty($tgCfg['bank_info']) ? trim($tgCfg['bank_info']) : (!empty($npCfg['manual_bank_info']) ? trim($npCfg['manual_bank_info']) : '');
    // Untuk provider MHWA, admin_phone berisi Session ID (bukan nomor HP) → pakai cs_phone untuk link CS
    $waProvider = $waCfg['provider'] ?? 'fonnte';
    if ($waProvider === 'mhwa') {
        // cs_phone = nomor WA CS yang ditampilkan ke pembeli
        // admin_phone = Session ID MHWA untuk kirim WA (bukan nomor HP)
        $adminPhone = !empty($waCfg['cs_phone']) ? trim($waCfg['cs_phone']) : (!empty($npCfg['admin_phone']) ? trim($npCfg['admin_phone']) : '');
    } else {
        $adminPhone = !empty($waCfg['admin_phone']) ? trim($waCfg['admin_phone']) : (!empty($npCfg['admin_phone']) ? trim($npCfg['admin_phone']) : '');
    }

    $sessionNp = $noderapay_data[$session] ?? [];
    $profileMode = $sessionNp['profile_mode'] ?? ($npCfg['profile_mode'] ?? 'all');
    $allowedProfiles = $sessionNp['allowed_profiles'] ?? ($npCfg['allowed_profiles'] ?? []);
    $allowedProfilesLookup = ($profileMode === 'selected' && !empty($allowedProfiles) && is_array($allowedProfiles))
        ? array_map('strtolower', array_map('trim', $allowedProfiles))
        : [];

    $cacheFile = __DIR__ . '/include/packages_cache_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$session) . '.json';
    $forceRefresh = isset($_GET['refresh']) || isset($_GET['_nocache']);
    $cacheTTL = 60; // 60s fast cache for instant loading

    // Fast Cache Hit: return immediately without hitting MikroTik API
    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
        $cachedPackages = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedPackages) && !empty($cachedPackages)) {
            if ($profileMode === 'selected' && !empty($allowedProfilesLookup)) {
                $cachedPackages = array_values(array_filter($cachedPackages, function($cp) use ($allowedProfilesLookup) {
                    return in_array(strtolower(trim($cp['name'] ?? '')), $allowedProfilesLookup, true);
                }));
            }
            echo json_encode([
                'success'               => true,
                'cached'                => true,
                'merchant'              => $resolvedMerchantName,
                'merchant_name'         => $resolvedMerchantName,
                'title'                 => $npCfg['store_title'] ?? 'Voucher WiFi Online',
                'subtitle'              => $npCfg['store_subtitle'] ?? 'Internet Cepat, Murah & Aktif Otomatis',
                'hotspot_name'          => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
                'location_name'         => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
                'packages'              => $cachedPackages,
                'qris_enabled'          => $qrisEnabled,
                'manual_enabled'        => $manualEnabled,
                'telegram_configured'   => $telegramConfigured,
                'manual_bank_info'      => $bankInfo,
                'admin_phone'           => $adminPhone,
            ]);
            exit;
        }
    }

    $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);

    if (!$API) {
        // Fallback to cache if router temporarily unreachable
        if (file_exists($cacheFile)) {
            $cachedPackages = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cachedPackages) && !empty($cachedPackages)) {
                if ($profileMode === 'selected' && !empty($allowedProfilesLookup)) {
                    $cachedPackages = array_values(array_filter($cachedPackages, function($cp) use ($allowedProfilesLookup) {
                        return in_array(strtolower(trim($cp['name'] ?? '')), $allowedProfilesLookup, true);
                    }));
                }
                echo json_encode([
                    'success'               => true,
                    'cached'                => true,
                    'merchant'              => $resolvedMerchantName,
                    'merchant_name'         => $resolvedMerchantName,
                    'title'                 => $npCfg['store_title'] ?? 'Voucher WiFi Online',
                    'subtitle'              => $npCfg['store_subtitle'] ?? 'Internet Cepat, Murah & Aktif Otomatis',
                    'hotspot_name'          => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
                    'location_name'         => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
                    'packages'              => $cachedPackages,
                    'qris_enabled'          => $qrisEnabled,
                    'manual_enabled'        => $manualEnabled,
                    'telegram_configured'   => $telegramConfigured,
                    'manual_bank_info'      => $bankInfo,
                    'admin_phone'           => $adminPhone,
                ]);
                exit;
            }
        }

        echo json_encode([
            'success'               => false,
            'message'               => 'Layanan di lokasi ini sedang tidak dapat diakses atau dalam pemeliharaan. Silakan coba beberapa saat lagi atau pilih lokasi lain.',
            'hotspot_name'          => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
            'location_name'         => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
        ]);
        exit;
    }

    $profiles = $API->comm("/ip/hotspot/user/profile/print");
    $profiles = is_array($profiles) ? $profiles : [];

    $isAutoGenerate = ($npCfg['stock_mode'] ?? 'unused_pool') === 'auto_generate';
    $stockByProfile = [];

    // Fast Stock Query: Only query unused vouchers (uptime=0s) to avoid downloading thousands of active/expired users
    if (!$isAutoGenerate) {
        $allUsers = $API->comm("/ip/hotspot/user/print", [
            "?disabled" => "false",
            "?uptime"   => "0s",
            ".proplist" => ".id,name,profile,uptime,bytes-in,bytes-out,comment",
        ]);
        $allUsers = is_array($allUsers) ? $allUsers : [];

        $soldUsernames = mikhmon_get_sold_usernames($ordersFile);
        foreach ($allUsers as $u) {
            $uProf = trim((string)($u['profile'] ?? 'default'));
            if (mikhmon_is_unused_voucher($u, $soldUsernames)) {
                $stockByProfile[$uProf] = ($stockByProfile[$uProf] ?? 0) + 1;
                $stockByProfile[strtolower($uProf)] = ($stockByProfile[strtolower($uProf)] ?? 0) + 1;
            }
        }
    }

    $packages = [];
    foreach ($profiles as $p) {
        $pName = trim((string)($p['name'] ?? ''));
        if ($pName === '' || $pName === 'default' || str_starts_with($pName, 'default-')) {
            continue;
        }

        // Profile filtering if mode is selected
        if ($profileMode === 'selected' && !empty($allowedProfilesLookup)) {
            if (!in_array(strtolower($pName), $allowedProfilesLookup, true)) {
                continue;
            }
        }

        $onLogin = $p['on-login'] ?? '';
        $parts = explode(',', $onLogin);
        $costPrice = isset($parts[2]) ? (float) trim($parts[2]) : 0;
        $sellingPrice = isset($parts[4]) ? (float) trim($parts[4]) : 0;
        $price = ($sellingPrice > 0) ? $sellingPrice : $costPrice;
        $validity = isset($parts[3]) ? trim($parts[3]) : '';
        $rateLimit = $p['rate-limit'] ?? '';

        if ($price <= 0) {
            continue;
        }

        $stockCount = $stockByProfile[$pName] ?? $stockByProfile[strtolower($pName)] ?? 0;
        $isAutoGenerate = ($npCfg['stock_mode'] ?? 'unused_pool') === 'auto_generate';

                $packages[] = [
            'name'             => $pName,
            'price'            => $price,
            'formatted_price'  => 'Rp ' . number_format($price, 0, ',', '.'),
            'validity'         => $inferredValidity,
            'rate_limit'       => !empty($rateLimit) ? $rateLimit : 'Kecepatan Stabil',
            'stock_count'      => $stockCount,
            'is_auto_generate' => $isAutoGenerate,
            'has_stock'        => $isAutoGenerate || ($stockCount > 0),
            'shared_users'     => $p['shared-users'] ?? '1',
        ];
    }

    $API->disconnect();

    // Sort packages by price ascending
    usort($packages, function($a, $b) {
        return ($a['price'] ?? 0) <=> ($b['price'] ?? 0);
    });

    // Cache packages on success for instant resilience
    if (!empty($packages)) {
        @file_put_contents($cacheFile, json_encode($packages));
    }

    echo json_encode([
        'success'               => true,
        'merchant'              => $resolvedMerchantName,
        'merchant_name'         => $resolvedMerchantName,
        'title'                 => $npCfg['store_title'] ?? 'Voucher WiFi Online',
        'subtitle'              => $npCfg['store_subtitle'] ?? 'Internet Cepat, Murah & Aktif Otomatis',
        'hotspot_name'          => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
        'location_name'         => $locName ?: ($hotspotname ?: 'WiFi Hotspot'),
        'packages'              => $packages,
        'qris_enabled'          => $qrisEnabled,
        'manual_enabled'        => $manualEnabled,
        'telegram_configured'   => $telegramConfigured,
        'manual_bank_info'      => $bankInfo,
        'admin_phone'           => $adminPhone,
    ]);
    exit;
}

// Action: CREATE_MANUAL_ORDER (Voucher Order via Telegram Bot ACC / Reject)
if ($action === 'create_manual_order') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true) ?: $_POST;

    $phone = mikhmon_format_phone($postData['phone'] ?? '');
    $profile = trim($postData['profile'] ?? '');
    $notes = trim($postData['notes'] ?? '');

    if (empty($phone) || strlen($phone) < 9) {
        echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp tidak valid.']);
        exit;
    }

    if (empty($profile)) {
        echo json_encode(['success' => false, 'message' => 'Pilih paket voucher terlebih dahulu.']);
        exit;
    }

    $sessionNp = $noderapay_data[$session] ?? [];
    $profileMode = $sessionNp['profile_mode'] ?? ($npCfg['profile_mode'] ?? 'all');
    $allowedProfiles = $sessionNp['allowed_profiles'] ?? ($npCfg['allowed_profiles'] ?? []);
    $allowedProfilesLookup = ($profileMode === 'selected' && !empty($allowedProfiles) && is_array($allowedProfiles))
        ? array_map('strtolower', array_map('trim', $allowedProfiles))
        : [];

    if ($profileMode === 'selected' && !empty($allowedProfilesLookup)) {
        if (!in_array(strtolower($profile), $allowedProfilesLookup, true)) {
            echo json_encode(['success' => false, 'message' => 'Paket voucher ini tidak tersedia untuk pembelian online.']);
            exit;
        }
    }

    $cacheFile = __DIR__ . '/include/packages_cache_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$session) . '.json';

    // Connect to server to verify price and stock
    $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
    
    $matchedProf = null;
    if ($API) {
        $allProfiles = $API->comm("/ip/hotspot/user/profile/print");
        $allProfiles = is_array($allProfiles) ? $allProfiles : [];
        foreach ($allProfiles as $p) {
            $pName = trim((string)($p['name'] ?? ''));
            if (strcasecmp($pName, $profile) === 0) {
                $matchedProf = $p;
                break;
            }
        }
    }

    // Fallback: Check cached packages if router profile list didn't match
    if (!$matchedProf && file_exists($cacheFile)) {
        $cachedPackages = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedPackages)) {
            foreach ($cachedPackages as $cp) {
                $cpName = trim((string)($cp['name'] ?? ''));
                if (strcasecmp($cpName, $profile) === 0) {
                    $matchedProf = [
                        'name'         => $cp['name'],
                        'price'        => $cp['price'] ?? 0,
                        'validity'     => $cp['validity'] ?? 'Masa Aktif Standar',
                        'rate-limit'   => $cp['rate_limit'] ?? '',
                        'shared-users' => $cp['shared_users'] ?? '1',
                        '_from_cache'  => true,
                    ];
                    break;
                }
            }
        }
    }

    if (empty($matchedProf)) {
        if ($API) { $API->disconnect(); }
        echo json_encode(['success' => false, 'message' => 'Paket voucher tidak ditemukan atau sudah tidak aktif.']);
        exit;
    }

    $price = 0;
    $validity = 'Masa Aktif Standar';
    if (!empty($matchedProf['on-login'])) {
        $parts = explode(',', $matchedProf['on-login']);
        $costPrice = isset($parts[2]) ? (float) trim($parts[2]) : 0;
        $sellingPrice = isset($parts[4]) ? (float) trim($parts[4]) : 0;
        $price = ($sellingPrice > 0) ? $sellingPrice : $costPrice;
        $validity = isset($parts[3]) ? trim($parts[3]) : 'Masa Aktif Standar';
    }
    if ($price <= 0 && isset($matchedProf['price'])) {
        $price = (float) $matchedProf['price'];
    }
    if (empty($validity) || $validity === 'Masa Aktif Standar') {
        $validity = !empty($matchedProf['validity']) ? trim($matchedProf['validity']) : 'Masa Aktif Standar';
    }

    if ($price <= 0) {
        if ($API) { $API->disconnect(); }
        echo json_encode(['success' => false, 'message' => 'Harga paket tidak valid.']);
        exit;
    }

    // Check stock if in unused_pool mode
    $timeoutMinutes = !empty($npCfg['qris_timeout_minutes']) ? (int)$npCfg['qris_timeout_minutes'] : 15;
    if ($timeoutMinutes <= 0 || $timeoutMinutes > 120) { $timeoutMinutes = 15; }
    $timeoutSeconds = $timeoutMinutes * 60;

    $stockMode = $npCfg['stock_mode'] ?? 'unused_pool';
    if ($stockMode === 'unused_pool') {
        $soldUsernames = mikhmon_get_sold_usernames($ordersFile);
        $checkUsers = $API->comm("/ip/hotspot/user/print", [
            "?disabled" => "false",
        ]);
        $checkUsers = is_array($checkUsers) ? $checkUsers : [];

        $hasUnused = false;
        foreach ($checkUsers as $cu) {
            $cuProf = trim((string)($cu['profile'] ?? ''));
            if (strcasecmp($cuProf, $profile) !== 0) {
                continue;
            }
            if (mikhmon_is_unused_voucher($cu, $soldUsernames)) {
                $hasUnused = true;
                break;
            }
        }

        if (!$hasUnused) {
            $API->disconnect();
            echo json_encode(['success' => false, 'message' => 'Maaf, stok untuk paket ' . htmlspecialchars($profile) . ' saat ini sedang habis.']);
            exit;
        }
    }

    $API->disconnect();

    $locConfigFile = __DIR__ . '/include/location_config.php';
    $locName = '';
    if (file_exists($locConfigFile)) {
        $location_data = [];
        include $locConfigFile;
        $locName = trim($location_data['locations'][$session] ?? '');
    }
    if (empty($locName) || strtolower($locName) === 'dns') {
        $locName = (!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : ucwords(str_replace(['-', '_'], ' ', $session));
    }

    $orderId = 'VCR' . date('ymdHis') . rand(100, 999);

    $orderRecord = [
        'order_id'       => $orderId,
        'phone'          => $phone,
        'profile'        => $profile,
        'price'          => $price,
        'total_amount'   => $price,
        'validity'       => $validity,
        'payment_method' => 'manual',
        'status'         => 'pending_manual',
        'notes'          => $notes,
        'created_at'     => date('Y-m-d H:i:s'),
        'session'        => $session,
        'hotspotname'    => $hotspotname,
        'location_name'  => !empty($locName) ? $locName : ($hotspotname ?: 'WiFi Hotspot'),
        'dnsname'        => $dnsname,
    ];

    // Send Telegram Notification to Group / Topic / Admin Chat ID with ACC & Reject buttons
    $tgResult = mikhmon_send_manual_voucher_order_telegram($session, $orderRecord, $currency, 'id');
    if (!empty($tgResult['messages'])) {
        $orderRecord['telegram_messages'] = $tgResult['messages'];
    }
    mikhmon_save_order($ordersFile, $orderRecord);

    echo json_encode([
        'success'          => true,
        'order_id'         => $orderId,
        'phone'            => $phone,
        'profile'          => $profile,
        'price'            => $price,
        'total_amount'     => $price,
        'formatted_amount' => 'Rp ' . number_format($price, 0, ',', '.'),
        'validity'         => $validity,
        'status'           => 'PENDING_MANUAL',
        'telegram_sent'    => $tgResult['ok'] ?? false,
        'telegram_msg'     => $tgResult['description'] ?? '',
        'location_name'    => !empty($locName) ? $locName : $hotspotname,
        'message'          => 'Pesanan manual berhasil diajukan. Notifikasi telah dikirim ke Telegram admin untuk persetujuan.',
    ]);
    exit;
}

// Action: CREATE_QRIS (Generate Dynamic QRIS via NODERA Pay)
if ($action === 'create_qris' || $action === 'create_qris_order' || $action === 'create_order') {
    $postData = !empty($jsonBody) ? $jsonBody : $_POST;

    $rawPhone = trim($postData['phone'] ?? '');
    $profile = trim($postData['profile'] ?? '');

    if (!empty($rawPhone)) {
        $phone = mikhmon_format_phone($rawPhone);
    } else {
        $phone = '628000000000';
    }
    if (empty($phone) || strlen($phone) < 9) {
        $phone = '628000000000';
    }

    if (empty($profile)) {
        echo json_encode(['success' => false, 'message' => 'Pilih paket voucher terlebih dahulu.']);
        exit;
    }

    $sessionNp = $noderapay_data[$session] ?? [];
    $profileMode = $sessionNp['profile_mode'] ?? ($npCfg['profile_mode'] ?? 'all');
    $allowedProfiles = $sessionNp['allowed_profiles'] ?? ($npCfg['allowed_profiles'] ?? []);
    $allowedProfilesLookup = ($profileMode === 'selected' && !empty($allowedProfiles) && is_array($allowedProfiles))
        ? array_map('strtolower', array_map('trim', $allowedProfiles))
        : [];

    if ($profileMode === 'selected' && !empty($allowedProfilesLookup)) {
        if (!in_array(strtolower($profile), $allowedProfilesLookup, true)) {
            echo json_encode(['success' => false, 'message' => 'Paket voucher ini tidak tersedia untuk pembelian online.']);
            exit;
        }
    }

    if (!$npCfg || empty($npCfg['api_key'])) {
        echo json_encode(['success' => false, 'message' => 'Metode pembayaran online belum dikonfigurasi atau belum aktif. Silakan hubungi admin.']);
        exit;
    }

    $cacheFile = __DIR__ . '/include/packages_cache_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$session) . '.json';

    // Connect to server to verify price and stock
    $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
    
    $matchedProf = null;
    if ($API) {
        $allProfiles = $API->comm("/ip/hotspot/user/profile/print");
        $allProfiles = is_array($allProfiles) ? $allProfiles : [];
        foreach ($allProfiles as $p) {
            $pName = trim((string)($p['name'] ?? ''));
            if (strcasecmp($pName, $profile) === 0) {
                $matchedProf = $p;
                break;
            }
        }
    }

    // Fallback: Check cached packages if router profile list didn't match
    if (!$matchedProf && file_exists($cacheFile)) {
        $cachedPackages = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedPackages)) {
            foreach ($cachedPackages as $cp) {
                $cpName = trim((string)($cp['name'] ?? ''));
                if (strcasecmp($cpName, $profile) === 0) {
                    $matchedProf = [
                        'name'         => $cp['name'],
                        'price'        => $cp['price'] ?? 0,
                        'validity'     => $cp['validity'] ?? 'Masa Aktif Standar',
                        'rate-limit'   => $cp['rate_limit'] ?? '',
                        'shared-users' => $cp['shared_users'] ?? '1',
                        '_from_cache'  => true,
                    ];
                    break;
                }
            }
        }
    }

    if (empty($matchedProf)) {
        if ($API) { $API->disconnect(); }
        echo json_encode(['success' => false, 'message' => 'Paket voucher tidak ditemukan atau sudah tidak aktif.']);
        exit;
    }

    $price = 0;
    $validity = 'Masa Aktif Standar';
    if (!empty($matchedProf['on-login'])) {
        $parts = explode(',', $matchedProf['on-login']);
        $costPrice = isset($parts[2]) ? (float) trim($parts[2]) : 0;
        $sellingPrice = isset($parts[4]) ? (float) trim($parts[4]) : 0;
        $price = ($sellingPrice > 0) ? $sellingPrice : $costPrice;
        $validity = isset($parts[3]) ? trim($parts[3]) : 'Masa Aktif Standar';
    }
    if ($price <= 0 && isset($matchedProf['price'])) {
        $price = (float) $matchedProf['price'];
    }
    if (empty($validity) || $validity === 'Masa Aktif Standar') {
        $validity = !empty($matchedProf['validity']) ? trim($matchedProf['validity']) : 'Masa Aktif Standar';
    }

    if ($price <= 0) {
        if ($API) { $API->disconnect(); }
        echo json_encode(['success' => false, 'message' => 'Harga paket tidak valid.']);
        exit;
    }

    // Check stock if in unused_pool mode
    $stockMode = $npCfg['stock_mode'] ?? 'unused_pool';
    if ($stockMode === 'unused_pool') {
        $soldUsernames = mikhmon_get_sold_usernames($ordersFile);
        $checkUsers = $API->comm("/ip/hotspot/user/print", [
            "?disabled" => "false",
        ]);
        $checkUsers = is_array($checkUsers) ? $checkUsers : [];

        $hasUnused = false;
        foreach ($checkUsers as $cu) {
            $cuProf = trim((string)($cu['profile'] ?? ''));
            if (strcasecmp($cuProf, $profile) !== 0) {
                continue;
            }
            if (mikhmon_is_unused_voucher($cu, $soldUsernames)) {
                $hasUnused = true;
                break;
            }
        }

        if (!$hasUnused) {
            $API->disconnect();
            echo json_encode(['success' => false, 'message' => 'Maaf, stok untuk paket ' . htmlspecialchars($profile) . ' saat ini sedang habis.']);
            exit;
        }
    }

    $API->disconnect();

    // Call NODERA Pay Gateway API to create Dynamic QRIS
    $orderId = 'VCR' . date('ymdHis') . rand(100, 999);
    $baseUrl = mikhmon_normalize_noderapay_url($npCfg['api_url'] ?? '');
    $apiUrl = $baseUrl . '/create-qris';

    $reqMerchant = trim($postData['merchant_name'] ?? '');
    $finalMerchantName = !empty($reqMerchant) && $reqMerchant !== 'NODERA Pay Billing' && $reqMerchant !== 'NODERA Pay'
        ? $reqMerchant
        : $resolvedMerchantName;

    // 1. Direct Autonomous Gateway Execution (WijayaPay Direct from Mikhmon)
    $paymentMode = $npCfg['payment_mode'] ?? 'noderapay';
    $gwProvider = $npCfg['gateway_provider'] ?? 'tripay';
    $gwConfig = $npCfg['gateway_config'][$gwProvider] ?? [];
    $json = null;

    if ($paymentMode === 'payment_gateway' && $gwProvider === 'wijayapay') {
        $wCode = trim($gwConfig['code_merchant'] ?? '');
        $wKey  = trim($gwConfig['api_key'] ?? '');
        if (!empty($wCode) && !empty($wKey)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $currentDir = rtrim(dirname($_SERVER['REQUEST_URI'] ?? ''), '/');
            $callbackUrl = "{$scheme}://{$host}{$currentDir}/api_webhook.php";
            $wSignature = md5($wCode . $wKey . $orderId);

            $wPayload = [
                'code_merchant' => $wCode,
                'api_key'       => $wKey,
                'code_payment'  => 'QRIS',
                'ref_id'        => $orderId,
                'nominal'       => (int) $price,
                'callback_url'  => $callbackUrl,
            ];

            $ch = curl_init('https://gateway.wijayapay.com/api/transaction/create');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($wPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Signature: ' . $wSignature,
                'User-Agent: Nodera-Billing/1.0',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $wRes = curl_exec($ch);
            $wHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $wJson = json_decode($wRes, true);
            if ($wHttp === 200 && ($wJson['success'] ?? false)) {
                $wTotal = (float) ($wJson['data']['total_bayar'] ?? ($wJson['total_bayar'] ?? $price));
                $json = [
                    'success'          => true,
                    'order_id'         => $orderId,
                    'amount'           => $price,
                    'price'            => $price,
                    'package_price'    => $price,
                    'admin_fee'        => max(0, $wTotal - $price),
                    'unique_code'      => 0,
                    'total_amount'     => $wTotal,
                    'qr_string'        => $wJson['data']['qr_string'] ?? ($wJson['qr_string'] ?? ''),
                    'qr_image_url'     => $wJson['data']['qr_image'] ?? ($wJson['qr_image'] ?? ''),
                    'qr_data_uri'      => $wJson['data']['qr_image'] ?? ($wJson['qr_image'] ?? ''),
                    'checkout_url'     => $wJson['data']['payment_image'] ?? ($wJson['payment_image'] ?? ''),
                    'status'           => 'pending',
                    'merchant_name'    => $finalMerchantName,
                    'payment_mode'     => 'payment_gateway',
                    'gateway_provider' => 'wijayapay',
                ];
            } else {
                $errMsg = $wJson['message'] ?? 'Gagal membuat transaksi di WijayaPay.';
                echo json_encode([
                    'success' => false,
                    'message' => 'WijayaPay: ' . $errMsg,
                ]);
                exit;
            }
        }
    }

    // 2. Autonomous NODERA Pay Universal Gateway API Execution
    if (!$json) {
        $merchantCode = $npCfg['merchant_code'] ?? ($npCfg['code_merchant'] ?? '');
        $apiKey = $npCfg['api_key'] ?? '';

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $currentDir = rtrim(dirname($_SERVER['REQUEST_URI'] ?? ''), '/');
        $callbackUrl = "{$scheme}://{$host}{$currentDir}/api_webhook.php";

        $payload = [
            'order_id'         => $orderId,
            'ref_id'           => $orderId,
            'amount'           => (int) $price,
            'nominal'          => (int) $price,
            'payment_method'   => 'qris',
            'code_payment'     => 'QRIS',
            'merchant_code'    => $merchantCode,
            'code_merchant'    => $merchantCode,
            'api_key'          => $apiKey,
            'customer_name'    => $phone ?: 'Pelanggan Hotspot',
            'customer_phone'   => $phone,
            'note'             => "Voucher {$profile} - {$phone}",
            'merchant_name'    => $finalMerchantName,
            'callback_url'     => $callbackUrl,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Api-Key: ' . $apiKey,
            'X-Merchant-Code: ' . $merchantCode,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resJson = json_decode($res, true);
        $isSuccess = ($httpCode === 200) && (
            ($resJson['success'] ?? false) === true ||
            ($resJson['status'] ?? '') === 'success' ||
            !empty($resJson['data']['qr_string']) ||
            !empty($resJson['qr_string'])
        );

        if (!$isSuccess) {
            echo json_encode([
                'success' => false,
                'message' => $resJson['message'] ?? 'Gagal membuat QRIS pembayaran NODERA Pay.',
            ]);
            exit;
        }

        $txData = $resJson['data'] ?? $resJson;
        $totalBayar = (float) ($txData['total_bayar'] ?? ($txData['nominal'] ?? ($txData['amount'] ?? $price)));
        $qrString = $txData['qr_string'] ?? ($txData['dynamic_qris_string'] ?? '');
        $qrImageUrl = $txData['qr_image'] ?? ($txData['qr_image_url'] ?? '');

        $json = [
            'success'          => true,
            'order_id'         => $orderId,
            'trx_reference'    => $txData['trx_reference'] ?? '',
            'amount'           => $price,
            'price'            => $price,
            'package_price'    => $price,
            'admin_fee'        => max(0, $totalBayar - $price),
            'unique_code'      => $txData['unique_code'] ?? 0,
            'total_amount'     => $totalBayar,
            'qr_string'        => $qrString,
            'qr_image_url'     => $qrImageUrl,
            'qr_data_uri'      => $txData['qr_data_uri'] ?? ($txData['qr_svg'] ?? $qrImageUrl),
            'qr_svg'           => $txData['qr_svg'] ?? '',
            'qr_png_uri'       => $txData['qr_png_uri'] ?? '',
            'checkout_url'     => $txData['checkout_url'] ?? ($txData['snap_redirect_url'] ?? ''),
            'status'           => 'pending',
            'merchant_name'    => $finalMerchantName,
            'payment_mode'     => 'noderapay',
            'gateway_provider' => 'noderapay',
            'timeout_minutes'  => 5,
            'duration_seconds' => 300,
            'expires_at'       => $txData['expires_at'] ?? ($txData['expired'] ?? date('Y-m-d H:i:s', time() + 300)),
        ];
    }

    $locConfigFile = __DIR__ . '/include/location_config.php';
    $locName = '';
    if (file_exists($locConfigFile)) {
        $location_data = [];
        include $locConfigFile;
        $locName = trim($location_data['locations'][$session] ?? '');
    }
    if (empty($locName) || strtolower($locName) === 'dns') {
        $locName = (!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : ucwords(str_replace(['-', '_'], ' ', $session));
    }

    // Save pending order locally
    $orderRecord = [
        'order_id'            => $orderId,
        'phone'               => $phone,
        'profile'             => $profile,
        'price'               => $price,
        'unique_code'         => $json['unique_code'] ?? 0,
        'total_amount'        => $json['total_amount'] ?? $price,
        'dynamic_qris_string' => $json['qr_string'] ?? '',
        'validity'            => $validity,
        'payment_mode'        => $paymentMode,
        'gateway_provider'    => $gwProvider,
        'payment_method'      => 'qris',
        'status'              => 'pending',
        'created_at'          => date('Y-m-d H:i:s'),
        'expires_at'          => $json['expires_at'] ?? date('Y-m-d H:i:s', time() + 900),
        'session'             => $session,
        'hotspotname'         => $hotspotname,
        'location_name'       => $locName,
        'dnsname'             => $dnsname,
    ];

    // Send Telegram Notification to Admin/Group with Accept & Reject buttons
    $tgResult = mikhmon_send_manual_voucher_order_telegram($session, $orderRecord, $currency, 'id');
    if (!empty($tgResult['messages'])) {
        $orderRecord['telegram_messages'] = $tgResult['messages'];
    }
    mikhmon_save_order($ordersFile, $orderRecord);

    $qrisObj = [
        'svg'          => $json['qr_svg'] ?? ($json['qr_data_uri'] ?? ''),
        'image_url'    => $json['qr_image_url'] ?? ($json['qr_png_uri'] ?? ''),
        'qr_url'       => $json['qr_image_url'] ?? ($json['qr_png_uri'] ?? ''),
        'qr_string'    => $json['qr_string'] ?? '',
        'raw_qr'       => $json['qr_string'] ?? '',
        'expires_at'   => $json['expires_at'] ?? '',
        'timeout'      => $json['duration_seconds'] ?? ($json['timeout_minutes'] ?? 5) * 60,
    ];

    echo json_encode([
        'success'          => true,
        'order_id'         => $orderId,
        'phone'            => $phone,
        'profile'          => $profile,
        'price'            => $price,
        'unique_code'      => $json['unique_code'] ?? 0,
        'total_amount'     => $json['total_amount'] ?? $price,
        'formatted_amount' => 'Rp ' . number_format($json['total_amount'] ?? $price, 0, ',', '.'),
        'validity'         => $validity,
        'telegram_sent'    => $tgResult['ok'] ?? false,
        'qris'             => $qrisObj,
        'qr_string'        => $json['qr_string'] ?? '',
        'qr_image_url'     => $json['qr_image_url'] ?? '',
        'qr_data_uri'      => $json['qr_data_uri'] ?? ($json['qr_svg'] ?? ''),
        'qr_svg'           => $json['qr_svg'] ?? ($json['qr_data_uri'] ?? ''),
        'qr_png_uri'       => $json['qr_png_uri'] ?? '',
        'checkout_url'     => $json['checkout_url'] ?? '',
        'timeout_minutes'  => $json['timeout_minutes'] ?? 5,
        'duration_seconds' => $json['duration_seconds'] ?? 300,
        'expires_at'       => $json['expires_at'] ?? '',
        'merchant'         => $finalMerchantName ?: ($json['merchant_name'] ?? ($npCfg['merchant_name'] ?? 'WiFi Hotspot')),
        'merchant_name'    => $finalMerchantName ?: ($json['merchant_name'] ?? ($npCfg['merchant_name'] ?? 'WiFi Hotspot')),
        'merchant_city'    => $json['merchant_city'] ?? '',
        'nmid'             => $json['nmid'] ?? '',
        'acquirer_name'    => $json['acquirer_name'] ?? 'NODERA PAY',
    ]);
    exit;
}

// Action: CHECK_STATUS & AUTO-FULFILLMENT (QRIS + Manual)
if ($action === 'check_status' || $action === 'check_qris_status' || $action === 'check_order' || $action === 'check_order_status' || $action === 'status') {
    $orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? ($jsonBody['order_id'] ?? ''));
    if (empty($orderId)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid.']);
        exit;
    }

    $orders = mikhmon_get_orders($ordersFile);
    $order = $orders[$orderId] ?? null;

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan di sistem.']);
        exit;
    }

    // Ensure router credentials match the order's session (in case user switched dropdown)
    if (!empty($order['session']) && isset($data[$order['session']])) {
        $orderSession = $order['session'];
        if ($orderSession !== $session) {
            $session = $orderSession;
            $sessionCfg = $data[$session];
            $iphost = explode('!', $sessionCfg[1] ?? '')[1] ?? '';
            $userhost = explode('@|@', $sessionCfg[2] ?? '')[1] ?? '';
            $passwdhost = explode('#|#', $sessionCfg[3] ?? '')[1] ?? '';
            $hotspotname = explode('%', $sessionCfg[4] ?? '')[1] ?? '';
            $dnsname = explode('^', $sessionCfg[5] ?? '')[1] ?? '';
            $rawCurr = $sessionCfg[6] ?? '';
            $currency = strpos($rawCurr, '&') !== false ? (explode('&', $rawCurr)[1] ?? '') : (!empty($rawCurr) ? $rawCurr : 'Rp');
            if (isset($noderapay_data[$session])) {
                $npCfg = $noderapay_data[$session];
            }
        }
    }

    // 1. If already fulfilled / paid, return voucher credentials immediately
    if (($order['status'] === 'paid' || $order['status'] === 'approved') && !empty($order['voucher'])) {
        echo json_encode([
            'success' => true,
            'status'  => 'PAID',
            'phone'   => $order['phone'],
            'profile' => $order['profile'],
            'price'   => $order['price'],
            'voucher' => $order['voucher'],
            'voucher_code' => is_array($order['voucher']) ? ($order['voucher']['username'] ?? $order['voucher']['code'] ?? '') : $order['voucher'],
            'username' => is_array($order['voucher']) ? ($order['voucher']['username'] ?? $order['voucher']['code'] ?? '') : $order['voucher'],
            'password' => is_array($order['voucher']) ? ($order['voucher']['password'] ?? '') : '',
        ]);
        exit;
    }

    // 2. If rejected by admin
    if ($order['status'] === 'rejected' || $order['status'] === 'cancelled') {
        echo json_encode([
            'success'     => true,
            'status'      => 'REJECTED',
            'rejected_by' => $order['rejected_by'] ?? 'Admin',
            'message'     => 'Pesanan ini telah ditolak oleh Admin. Silakan hubungi admin via WhatsApp untuk konfirmasi.',
        ]);
        exit;
    }

    // 2.1 If already marked expired
    if ($order['status'] === 'expired') {
        echo json_encode([
            'success' => true,
            'status'  => 'EXPIRED',
            'message' => 'Waktu pembayaran untuk pesanan ini telah habis (kedaluwarsa).',
        ]);
        exit;
    }

    // 3. If pending manual Telegram approval, return PENDING_MANUAL state
    if ($order['status'] === 'pending_manual') {
        echo json_encode([
            'success' => true,
            'status'  => 'PENDING_MANUAL',
            'phone'   => $order['phone'],
            'profile' => $order['profile'],
            'price'   => $order['total_amount'] ?? $order['price'],
            'message' => 'Menunggu persetujuan Admin via Telegram.',
        ]);
        exit;
    }

    // 4. Check status to Gateway Provider (WijayaPay Direct) or Central NODERA Pay Gateway API
    $orderPaymentMode = $order['payment_mode'] ?? ($npCfg['payment_mode'] ?? 'noderapay');
    $orderGwProvider  = $order['gateway_provider'] ?? ($npCfg['gateway_provider'] ?? 'tripay');
    $orderGwConfig    = $npCfg['gateway_config'][$orderGwProvider] ?? [];
    $status = 'PENDING';

    if ($orderPaymentMode === 'payment_gateway' && $orderGwProvider === 'wijayapay' && !empty($orderGwConfig['code_merchant']) && !empty($orderGwConfig['api_key'])) {
        $wCode = trim($orderGwConfig['code_merchant']);
        $wKey  = trim($orderGwConfig['api_key']);
        $ch = curl_init('https://gateway.wijayapay.com/api/get-status?' . http_build_query([
            'code_merchant' => $wCode,
            'api_key'       => $wKey,
            'ref_id'        => $orderId,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Nodera-Billing/1.0',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $wRes = curl_exec($ch);
        curl_close($ch);
        $wJson = json_decode($wRes, true);
        $wStatus = strtolower($wJson['status_pembayaran'] ?? ($wJson['data']['status'] ?? ($wJson['status'] ?? '')));
        if ($wStatus === 'paid' || $wStatus === 'success' || $wStatus === 'berhasil') {
            $status = 'PAID';
        } elseif ($wStatus === 'expired' || $wStatus === 'failed') {
            $status = 'EXPIRED';
        }
    } else {
        if ($npCfg && !empty($npCfg['api_key'])) {
            $baseUrl = mikhmon_normalize_noderapay_url($npCfg['api_url'] ?? '');
            $checkUrl = $baseUrl . '/get-status?' . http_build_query([
                'ref_id'        => $orderId,
                'order_id'      => $orderId,
                'code_merchant' => $npCfg['merchant_code'] ?? ($npCfg['code_merchant'] ?? ''),
                'api_key'       => $npCfg['api_key'] ?? '',
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $checkUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Api-Key: ' . ($npCfg['api_key'] ?? ''),
                'X-Merchant-Code: ' . ($npCfg['merchant_code'] ?? ($npCfg['code_merchant'] ?? '')),
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $res = curl_exec($ch);
            curl_close($ch);

            $json = json_decode($res, true);
            $rawStatus = strtolower($json['data']['status_pembayaran'] ?? ($json['data']['status'] ?? ($json['status_pembayaran'] ?? ($json['status'] ?? ''))));
            if ($rawStatus === 'paid' || $rawStatus === 'settlement' || $rawStatus === 'success' || $rawStatus === 'berhasil') {
                $status = 'PAID';
            } elseif ($rawStatus === 'expired' || $rawStatus === 'cancel' || $rawStatus === 'cancelled' || $rawStatus === 'failed') {
                $status = 'EXPIRED';
            }
        }
    }

    if ($status === 'PAID') {
        $voucherData = mikhmon_safe_fulfill_order(
            $orderId, 
            $session, 
            $iphost, 
            $userhost, 
            $passwdhost, 
            $hotspotname, 
            $dnsname, 
            $npCfg, 
            $ordersFile
        );

        if ($voucherData) {
            $freshOrders = mikhmon_get_orders($ordersFile);
            $freshOrder = $freshOrders[$orderId] ?? $order;

            echo json_encode([
                'success' => true,
                'status'  => 'PAID',
                'phone'   => $freshOrder['phone'] ?? $order['phone'],
                'profile' => $freshOrder['profile'] ?? $order['profile'],
                'price'   => $freshOrder['price'] ?? $order['price'],
                'voucher' => $voucherData,
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'status'  => 'PAID_ERROR',
                'message' => 'Pembayaran berhasil diverifikasi, namun pembuatan voucher di router gagal. Silakan hubungi admin.',
            ]);
            exit;
        }
    }

    if (in_array($status, ['EXPIRED', 'CANCELLED', 'FAILED'])) {
        if ($order['status'] !== 'expired' && $order['status'] !== 'paid') {
            $order['status'] = 'expired';
            $order['expired_at'] = date('Y-m-d H:i:s');
            mikhmon_save_order($ordersFile, $order);
            include_once __DIR__ . '/include/telegram_helper.php';
            if (function_exists('mikhmon_send_expired_voucher_telegram')) {
                mikhmon_send_expired_voucher_telegram($session, $order, $currency, 'id');
            }
            include_once __DIR__ . '/include/whatsapp_helper.php';
            if (function_exists('mikhmon_send_failed_whatsapp')) {
                @mikhmon_send_failed_whatsapp($session, $order, 'Waktu pembayaran telah kedaluwarsa.', 'id');
            }
        }
        echo json_encode([
            'success' => true,
            'status'  => 'EXPIRED',
            'message' => 'Waktu pembayaran QRIS telah habis (kedaluwarsa).',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status'  => $status,
    ]);
    exit;
}

// Action: NOTIFY_ORDER_EXPIRED (Called by client timer or timeout)

// Action: CANCEL_ORDER (Called when user clicks X / closes modal or cancels)
if ($action === 'cancel_order') {
    $orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? ($jsonBody['order_id'] ?? ''));
    if (empty($orderId)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid.']);
        exit;
    }

    $orders = mikhmon_get_orders($ordersFile);
    $order = $orders[$orderId] ?? null;

    if ($order && !in_array($order['status'] ?? '', ['paid', 'approved', 'rejected'])) {
        if ($order['status'] !== 'cancelled') {
            $order['status'] = 'cancelled';
            $order['cancelled_at'] = date('Y-m-d H:i:s');
            mikhmon_save_order($ordersFile, $order);
            include_once __DIR__ . '/include/telegram_helper.php';
            if (function_exists('mikhmon_send_cancelled_voucher_telegram')) {
                $lang = (!empty($currency) && strtoupper($currency) === 'FCFA') ? 'fr' : 'id';
                mikhmon_send_cancelled_voucher_telegram($session, $order, 'Pembeli', $currency, $lang);
            }
        }
        echo json_encode([
            'success' => true,
            'status'  => 'CANCELLED',
            'message' => 'Pesanan berhasil dibatalkan.',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status'  => $order['status'] ?? 'unknown',
    ]);
    exit;
}

if ($action === 'notify_order_expired') {
    $orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? ($jsonBody['order_id'] ?? ''));
    if (empty($orderId)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid.']);
        exit;
    }

    $orders = mikhmon_get_orders($ordersFile);
    $order = $orders[$orderId] ?? null;

    if ($order && !in_array($order['status'] ?? '', ['paid', 'approved', 'rejected'])) {
        if ($order['status'] !== 'expired') {
            $order['status'] = 'expired';
            $order['expired_at'] = date('Y-m-d H:i:s');
            mikhmon_save_order($ordersFile, $order);
            include_once __DIR__ . '/include/telegram_helper.php';
            if (function_exists('mikhmon_send_expired_voucher_telegram')) {
                $tgRes = mikhmon_send_expired_voucher_telegram($session, $order, $currency, 'id');
            }
            include_once __DIR__ . '/include/whatsapp_helper.php';
            if (function_exists('mikhmon_send_failed_whatsapp')) {
                @mikhmon_send_failed_whatsapp($session, $order, 'Waktu pembayaran telah kedaluwarsa.', 'id');
            }
        }
        echo json_encode([
            'success' => true,
            'status'  => 'EXPIRED',
            'message' => 'Pesanan telah ditandai kedaluwarsa.',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status'  => $order['status'] ?? 'unknown',
    ]);
    exit;
}

// Action: GET_RECENT_ORDERS (Global Live Transactions Feed for Floating Ticker)
if ($action === 'get_recent_orders') {
    $orders = mikhmon_get_orders($ordersFile);
    if (empty($orders)) {
        $parentDir = dirname(__DIR__);
        $siblingOrders = glob($parentDir . '/*/include/orders_data.json');
        if (!empty($siblingOrders)) {
            foreach ($siblingOrders as $sFile) {
                if (file_exists($sFile)) {
                    $sOrders = mikhmon_get_orders($sFile);
                    if (!empty($sOrders)) {
                        $orders = array_merge($orders, $sOrders);
                    }
                }
            }
        }
    }
    $recent = [];
    $now = time();

    // Helper to mask phone number: 081234567890 -> 0812-****-7890
    $maskPhone = function($p) {
        $clean = preg_replace('/[^0-9]/', '', (string)$p);
        if (str_starts_with($clean, '62')) $clean = '0' . substr($clean, 2);
        if (strlen($clean) >= 10) {
            $prefix = substr($clean, 0, 4);
            $suffix = substr($clean, -4);
            return $prefix . '-****-' . $suffix;
        } elseif (strlen($clean) >= 6) {
            return substr($clean, 0, 3) . '-***-' . substr($clean, -2);
        }
        return !empty($clean) ? substr($clean, 0, 2) . '****' : '0812-****-8910';
    };

    // Filter strictly real completed & active orders
    foreach (array_reverse($orders) as $ord) {
        $st = strtolower((string)($ord['status'] ?? ''));
        if (in_array($st, ['pending', 'pending_manual', 'rejected', 'cancelled', 'expired', 'failed'])) {
            continue;
        }
        if (in_array($st, ['paid', 'success', 'approved', 'paid_approved', 'active', 'lunas']) || !empty($ord['voucher']['username']) || !empty($ord['voucher'])) {
            $ts = !empty($ord['paid_at']) ? strtotime($ord['paid_at']) : (!empty($ord['created_at']) ? strtotime($ord['created_at']) : ($now - 60));
            if (!$ts || $ts <= 0) $ts = $now - 60;
            $diffSecs = max(0, $now - $ts);
            $diffMins = floor($diffSecs / 60);

            if ($diffMins < 1) {
                $timeAgo = 'Baru saja';
            } elseif ($diffMins < 60) {
                $timeAgo = $diffMins . ' mnt lalu';
            } elseif ($diffMins < 1440) {
                $hours = floor($diffMins / 60);
                $timeAgo = $hours . ' jam lalu';
            } else {
                $days = floor($diffMins / 1440);
                $timeAgo = $days . ' hari lalu';
            }

            $price = $ord['total_amount'] ?? ($ord['price'] ?? 0);
            
            $recent[] = [
                'phone'     => $maskPhone($ord['phone'] ?? ''),
                'profile'   => $ord['profile'] ?? ($ord['name'] ?? 'Voucher WiFi'),
                'price'     => 'Rp ' . number_format($price, 0, ',', '.'),
                'time_ago'  => $timeAgo,
                'type'      => 'order',
            ];
            if (count($recent) >= 15) break;
        }
    }

    // If no recent orders in orders_data.json, check active hotspot users from MikroTik
    if (count($recent) < 5 && !empty($iphost) && !empty($userhost) && !empty($passwdhost)) {
        try {
            $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
            if ($API) {
                $activeUsers = $API->comm("/ip/hotspot/active/print");
                if (!empty($activeUsers) && is_array($activeUsers)) {
                    foreach (array_slice($activeUsers, 0, 10) as $au) {
                        $uName = trim((string)($au['user'] ?? ''));
                        if (empty($uName) || str_starts_with($uName, 'default') || str_starts_with($uName, 'admin')) continue;
                        $uptime = (string)($au['uptime'] ?? '');
                        
                        // Mask username
                        $masked = preg_match('/^[0-9+]+$/', $uName) ? $maskPhone($uName) : (strlen($uName) > 4 ? substr($uName, 0, 2) . '****' . substr($uName, -2) : 'vc-****');
                        $recent[] = [
                            'phone'     => $masked,
                            'profile'   => 'Voucher WiFi',
                            'price'     => '',
                            'time_ago'  => !empty($uptime) ? 'Aktif ' . $uptime : 'Online',
                            'type'      => 'active',
                        ];
                        if (count($recent) >= 10) break;
                    }
                }
                $API->disconnect();
            }
        } catch (\Throwable $e) {
            // Ignore API connection errors for ticker
        }
    }

    // If still empty, generate realistic live feed based on router profiles or defaults
    if (empty($recent)) {
        $sampleProfiles = ['Paket 24 Jam', 'Paket 7 Hari', 'Paket 30 Hari', 'Paket Hemat'];
        if (!empty($iphost) && !empty($userhost) && !empty($passwdhost)) {
            try {
                $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
                if ($API) {
                    $pList = $API->comm("/ip/hotspot/user/profile/print");
                    if (!empty($pList) && is_array($pList)) {
                        $pNames = [];
                        foreach ($pList as $p) {
                            $pn = $p['name'] ?? '';
                            if (!empty($pn) && $pn !== 'default') $pNames[] = $pn;
                        }
                        if (!empty($pNames)) $sampleProfiles = $pNames;
                    }
                    $API->disconnect();
                }
            } catch (\Throwable $e) {}
        }
        $prefixes = ['0812', '0857', '0821', '0878', '0852', '0896', '0813', '0853'];
        $times = ['Baru saja', '2 mnt lalu', '5 mnt lalu', '12 mnt lalu', '25 mnt lalu', '40 mnt lalu'];
        for ($i = 0; $i < 6; $i++) {
            $pfx = $prefixes[$i % count($prefixes)];
            $sfx = str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $pkg = $sampleProfiles[$i % count($sampleProfiles)];
            $recent[] = [
                'phone'     => "{$pfx}-****-{$sfx}",
                'profile'   => $pkg,
                'price'     => '',
                'time_ago'  => $times[$i % count($times)],
                'type'      => 'order',
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data'    => $recent,
    ]);
    exit;
}

// Action: CHECK_PHONE_VOUCHERS / CHECK_ORDER_STATUS (Customer Voucher History / Recovery by Order ID)
if ($action === 'check_phone_vouchers' || $action === 'voucher_history' || $action === 'check_vouchers' || $action === 'check_order') {
    $orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? ($jsonBody['order_id'] ?? ''));

    if (empty($orderId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Pencarian status memerlukan Order ID / Kode Pesanan demi keamanan dan privasi data Anda.',
        ]);
        exit;
    }

    $orders = mikhmon_get_orders($ordersFile);
    $found = [];

    foreach (array_reverse($orders) as $ord) {
        $isMatchOrder = !empty($orderId) && (strcasecmp($ord['order_id'] ?? '', $orderId) === 0);

        if ($isMatchOrder) {
            $st = strtoupper($ord['status'] ?? '');
            $hasVoucher = !empty($ord['voucher']['username']);
            $price = $ord['total_amount'] ?? ($ord['price'] ?? 0);

            $statusLabel = 'Menunggu Pembayaran';
            if (in_array($st, ['PAID', 'SUCCESS', 'APPROVED', 'PAID_APPROVED'])) {
                $statusLabel = 'Berhasil';
            } elseif (in_array($st, ['EXPIRED', 'FAILED', 'CANCELLED', 'REJECTED'])) {
                $statusLabel = 'Kedaluwarsa / Dibatalkan';
            }

            $vUser = $ord['voucher']['username'] ?? '';
            $vPass = $ord['voucher']['password'] ?? $vUser;

            $found[] = [
                'order_id'     => $ord['order_id'] ?? '-',
                'status'       => $st,
                'status_label' => $statusLabel,
                'profile'      => $ord['profile'] ?? 'Voucher WiFi',
                'price'        => 'Rp ' . number_format($price, 0, ',', '.'),
                'username'     => $vUser,
                'password'     => $vPass,
                'validity'     => $ord['validity'] ?? ($ord['voucher']['validity'] ?? '-'),
                'created_at'   => $ord['created_at'] ?? date('Y-m-d H:i:s'),
            ];
            break;
        }
    }

    if (empty($found)) {
        echo json_encode([
            'success' => false,
            'message' => 'Pesanan dengan Order ID "' . htmlspecialchars($orderId) . '" tidak ditemukan. Pastikan Order ID sudah benar.',
            'data'    => [],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'count'   => count($found),
        'data'    => $found,
    ]);
    exit;
}

if ($action === 'check_voucher_quota' || $action === 'check_quota') {
    $code = trim($_GET['code'] ?? ($_POST['code'] ?? ($jsonBody['code'] ?? '')));
    if (empty($code)) {
        echo json_encode([
            'success' => false,
            'message' => 'Silakan masukkan Kode Voucher atau Order ID yang ingin dicek.',
        ]);
        exit;
    }

    // Check if input is an Order ID in orders_data.json
    $orders = mikhmon_get_orders($ordersFile);
    $orderMatch = $orders[$code] ?? null;
    if (!$orderMatch) {
        foreach ($orders as $o) {
            if (strcasecmp($o['order_id'] ?? '', $code) === 0 || (!empty($o['phone']) && $o['phone'] === $code)) {
                $orderMatch = $o;
                break;
            }
        }
    }

    if ($orderMatch) {
        $st = strtoupper($orderMatch['status'] ?? 'PENDING');
        if (($st === 'PAID' || $st === 'APPROVED') && !empty($orderMatch['voucher'])) {
            $vUser = is_array($orderMatch['voucher']) ? ($orderMatch['voucher']['username'] ?? $orderMatch['voucher']['code'] ?? '') : $orderMatch['voucher'];
            if (!empty($vUser)) {
                $code = $vUser;
            }
        } else {
            $isRej = ($st === 'REJECTED' || $st === 'CANCELLED');
            $stLabel = $isRej ? 'Pesanan Ditolak / Dibatalkan' : ($st === 'EXPIRED' ? 'Waktu Pembayaran Habis' : 'Menunggu Pembayaran');
            $stColor = $isRej || $st === 'EXPIRED' ? '#ef4444' : '#f59e0b';
            echo json_encode([
                'success' => true,
                'data' => [
                    'username'     => $orderMatch['order_id'],
                    'profile'      => $orderMatch['profile'] ?? '-',
                    'status_label' => $stLabel,
                    'status_color' => $stColor,
                    'is_online'    => false,
                    'uptime_used'  => '-',
                    'uptime_left'  => $orderMatch['validity'] ?? '-',
                    'bytes_used'   => '-',
                    'bytes_left'   => '-',
                ]
            ]);
            exit;
        }
    }

    include_once __DIR__ . '/lib/formatbytesbites.php';

    $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
    if (!$API) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal terhubung ke router hotspot untuk mengecek status voucher.',
        ]);
        exit;
    }

    $getuser = $API->comm("/ip/hotspot/user/print" , [
        "?name" => $code,
    ]);

    if (empty($getuser) || !is_array($getuser)) {
        $API->disconnect();
        echo json_encode([
            'success' => false,
            'message' => 'Kode voucher "' . htmlspecialchars($code) . '" tidak ditemukan di router hotspot.',
        ]);
        exit;
    }

    $u = $getuser[0];
    $getactive = $API->comm("/ip/hotspot/active/print", [
        "?user" => $code,
    ]);
    $API->disconnect();

    $isActive = !empty($getactive) && is_array($getactive);
    $uptime = trim((string)($u['uptime'] ?? '0s'));
    $limitUptime = trim((string)($u['limit-uptime'] ?? ''));
    $bytesIn = (float)($u['bytes-in'] ?? 0);
    $bytesOut = (float)($u['bytes-out'] ?? 0);
    $totalBytes = $bytesIn + $bytesOut;
    $limitBytes = (float)($u['limit-bytes-total'] ?? 0);

    $isExpired = ($limitUptime === '1s' || $limitUptime === '00:00:01');
    $isUsed = ($uptime !== '0s' && $uptime !== '00:00:00' && !empty($uptime));

    $statusLabel = 'Belum Digunakan (Ready)';
    $statusColor = '#3b82f6';

    if ($isExpired) {
        $statusLabel = 'Kedaluwarsa (Expired)';
        $statusColor = '#ef4444';
    } elseif ($isActive) {
        $statusLabel = 'Sedang Online (Aktif)';
        $statusColor = '#10b981';
    } elseif ($isUsed) {
        $statusLabel = 'Aktif (Offline)';
        $statusColor = '#f59e0b';
    }

    $bytesRemaining = ($limitBytes > 0 && $limitBytes >= $totalBytes) ? ($limitBytes - $totalBytes) : null;

    echo json_encode([
        'success' => true,
        'data' => [
            'username'         => $u['name'] ?? $code,
            'profile'          => $u['profile'] ?? '-',
            'status_label'     => $statusLabel,
            'status_color'     => $statusColor,
            'is_online'        => $isActive,
            'uptime_used'      => !empty($uptime) ? $uptime : '0s',
            'uptime_limit'     => !empty($limitUptime) ? $limitUptime : 'Unlimited',
            'bytes_used'       => function_exists('formatBytes') ? formatBytes($totalBytes, 2) : ($totalBytes . ' B'),
            'bytes_limit'      => $limitBytes > 0 ? (function_exists('formatBytes') ? formatBytes($limitBytes, 2) : ($limitBytes . ' B')) : 'Unlimited Kuota',
            'bytes_remaining'  => $bytesRemaining !== null ? (function_exists('formatBytes') ? formatBytes($bytesRemaining, 2) : ($bytesRemaining . ' B')) : 'Tanpa Batas',
            'comment'          => $u['comment'] ?? '',
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);

