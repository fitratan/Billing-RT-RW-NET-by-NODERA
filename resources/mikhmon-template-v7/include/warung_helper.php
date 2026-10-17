<?php
/**
 * WARUNG ENGINE HELPER — MIKHMON by NODERA
 * Manajemen Warung Reseller, Saldo Kulakan, Generate Voucher, & WhatsApp Gateway
 * dgtlnetsolution.com
 */

if (session_status() === PHP_SESSION_NONE) {
    $dir = __DIR__;
    $isRoot = (file_exists($dir . '/buy.php') || file_exists($dir . '/admin.php') || file_exists($dir . '/include/config.php'));
    $mikhRoot = $isRoot ? $dir : (file_exists(dirname($dir) . '/buy.php') || file_exists(dirname($dir) . '/admin.php') ? dirname($dir) : $dir);
    $instanceHash = substr(md5($mikhRoot), 0, 10);
    @session_name('MIKH_' . $instanceHash);
    $cookiePath = $isRoot 
        ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') 
        : rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    @ini_set('session.cookie_path', !empty($cookiePath) ? $cookiePath : '/');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (PHP_VERSION_ID >= 70300) {
        @ini_set('session.cookie_samesite', 'Lax');
    }
    @ini_set('session.cookie_httponly', '1');
    if ($isSecure) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}

include_once __DIR__ . '/license.php';
include_once __DIR__ . '/whatsapp_helper.php';
/**
 * Get localized string from global language dictionary
 */
if (!function_exists('warung_get_lang_str')) {
    function warung_get_lang_str($key, $default, $args = []) {
        $val = $GLOBALS['_' . $key] ?? $GLOBALS[$key] ?? $default;
        if (!empty($args)) {
            return vsprintf($val, $args);
        }
        return $val;
    }
}

if (!defined('WARUNG_DATA_FILE')) {
    define('WARUNG_DATA_FILE', __DIR__ . '/warung_data.json');
}

/**
 * Format money string dynamically based on currency setting and language locale.
 */
if (!function_exists('mikhmon_format_money')) {
    function mikhmon_format_money($amount, $curr = 'Rp', $lang = 'id') {
        $num = (float) $amount;
        $currUpper = strtoupper(trim((string)$curr));
        $decimals = (floor($num) != $num) ? 2 : 0;
        if ($currUpper === 'FCFA' || $lang === 'fr') {
            return number_format($num, $decimals, ',', ' ') . ' FCFA';
        } elseif ($currUpper === '$' || $currUpper === 'USD') {
            return '$ ' . number_format($num, 2, '.', ',');
        } elseif ($currUpper === 'EUR' || $currUpper === '€') {
            return '€ ' . number_format($num, 2, ',', '.');
        } else {
            $currStr = !empty($curr) ? trim((string)$curr) : 'Rp';
            return $currStr . ' ' . number_format($num, $decimals, ',', '.');
        }
    }
}

/**
 * Format profit margin with sign (+ / -) dynamically based on currency.
 */
if (!function_exists('mikhmon_format_margin')) {
    function mikhmon_format_margin($margin, $curr = 'Rp', $lang = 'id') {
        $num = (float) $margin;
        $sign = ($num >= 0) ? '+' : '';
        $currUpper = strtoupper(trim((string)$curr));
        $decimals = (floor($num) != $num) ? 2 : 0;
        if ($currUpper === 'FCFA' || $lang === 'fr') {
            return $sign . number_format($num, $decimals, ',', ' ') . ' FCFA';
        } elseif ($currUpper === '$' || $currUpper === 'USD') {
            return $sign . '$ ' . number_format($num, 2, '.', ',');
        } elseif ($currUpper === 'EUR' || $currUpper === '€') {
            return $sign . '€ ' . number_format($num, 2, ',', '.');
        } else {
            $currStr = !empty($curr) ? trim((string)$curr) : 'Rp';
            return $sign . $currStr . ' ' . number_format($num, $decimals, ',', '.');
        }
    }
}

/**
 * Calculate margin nominal and markup ratio percentage safely.
 */
if (!function_exists('mikhmon_calc_margin_ratio')) {
    function mikhmon_calc_margin_ratio($cost, $sell) {
        $cost = (float) $cost;
        $sell = (float) $sell;
        $margin = $sell - $cost;
        $ratio = 0.0;
        if ($cost > 0) {
            $ratio = round(($margin / $cost) * 100, 1);
        } elseif ($sell > 0) {
            $ratio = 100.0;
        }
        return [
            'cost'      => $cost,
            'sell'      => $sell,
            'margin'    => $margin,
            'ratio'     => $ratio,
            'sign'      => ($margin >= 0 ? '+' : ''),
            'is_profit' => ($margin >= 0),
        ];
    }
}

/**
 * Normalize Indonesian phone number for display (e.g. 085812345678).
 */
function warung_normalize_phone_display($phone) {
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (empty($clean)) return '';
    if (substr($clean, 0, 2) === '62') {
        return '0' . substr($clean, 2);
    }
    if (substr($clean, 0, 1) === '8') {
        return '0' . $clean;
    }
    return $clean;
}

/**
 * Normalize phone number for robust comparison (e.g. 85812345678).
 */
function warung_normalize_phone_compare($phone) {
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (empty($clean)) return '';
    if (substr($clean, 0, 2) === '62') {
        return substr($clean, 2);
    }
    if (substr($clean, 0, 1) === '0') {
        return substr($clean, 1);
    }
    return $clean;
}

/**
 * Load all warung data from JSON file with file locking.
 */
function warung_get_data() {
    $file = WARUNG_DATA_FILE;
    if (!file_exists($file)) {
        $initial = [
            'warungs'        => [],
            'profile_prices' => [],
            'transactions'   => [],
            'topup_orders'   => [],
            'settings'       => [
                'stock_mode'      => 'auto',
                'voucher_prefix'  => 'VC-',
                'char_length'     => 6,
                'char_format'     => 'mix_lower',
                'auto_purge_days' => 30,
                'last_purged_at'  => time()
            ]
        ];
        @file_put_contents($file, json_encode($initial, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $initial;
    }

    $raw = @file_get_contents($file);
    $data = @json_decode($raw, true);
    if (!is_array($data)) {
        $data = [
            'warungs'        => [],
            'profile_prices' => [],
            'transactions'   => [],
            'topup_orders'   => [],
            'settings'       => []
        ];
    }
    if (!isset($data['warungs'])) $data['warungs'] = [];
    if (!isset($data['profile_prices'])) $data['profile_prices'] = [];
    if (!isset($data['transactions'])) $data['transactions'] = [];
    if (!isset($data['topup_orders'])) $data['topup_orders'] = [];
    if (!isset($data['settings'])) $data['settings'] = [];

    // Normalize phone numbers for display across all loaded warungs
    foreach ($data['warungs'] as &$w) {
        if (!empty($w['phone'])) {
            $w['phone'] = warung_normalize_phone_display($w['phone']);
        }
    }

    // Periodic auto purge check (once a day)
    $autoPurgeDays = intval($data['settings']['auto_purge_days'] ?? 30);
    $lastPurged = intval($data['settings']['last_purged_at'] ?? 0);
    if ($autoPurgeDays > 0 && (time() - $lastPurged) > 86400) {
        $cutoff = time() - ($autoPurgeDays * 86400);
        $newTxs = [];
        foreach ($data['transactions'] as $tx) {
            $tTime = !empty($tx['created_at']) ? strtotime($tx['created_at']) : 0;
            if ($tTime >= $cutoff) {
                $newTxs[] = $tx;
            }
        }
        $newTopups = [];
        foreach ($data['topup_orders'] as $ordId => $ord) {
            $oTime = !empty($ord['created_at']) ? strtotime($ord['created_at']) : 0;
            if ($oTime >= $cutoff) {
                $newTopups[$ordId] = $ord;
            }
        }
        $data['transactions'] = $newTxs;
        $data['topup_orders'] = $newTopups;
        $data['settings']['last_purged_at'] = time();
        warung_save_data($data);
    }

    return $data;
}

/**
 * Save all warung data to JSON with atomic lock.
 */
function warung_save_data($data) {
    $file = WARUNG_DATA_FILE;
    $fp = @fopen($file, 'w');
    if ($fp) {
        if (@flock($fp, LOCK_EX)) {
            @fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            @fflush($fp);
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);
        return true;
    }
    return (bool) @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Get operational settings for Warung.
 */
function warung_get_settings() {
    $data = warung_get_data();
    $defaults = [
        'stock_mode'      => 'auto', // 'pool' | 'ondemand' | 'auto'
        'voucher_prefix'  => 'VC-',
        'char_length'     => 6,
        'char_format'     => 'mix_lower', // 'mix_lower' | 'mix_upper' | 'numeric'
        'auto_purge_days' => 30, // 30, 60, 90, 0
        'last_purged_at'  => time()
    ];
    return array_merge($defaults, $data['settings'] ?? []);
}

/**
 * Save operational settings for Warung.
 */
function warung_save_settings($settings) {
    $data = warung_get_data();
    $data['settings'] = array_merge($data['settings'] ?? [], $settings);
    warung_save_data($data);
    return true;
}

/**
 * Purge old transactions and logs exceeding specified days.
 */
function warung_purge_old_data($days = 30) {
    $days = intval($days);
    if ($days <= 0) return 0;

    $data = warung_get_data();
    $cutoff = time() - ($days * 86400);
    $initialCount = count($data['transactions'] ?? []);

    $newTxs = [];
    foreach ($data['transactions'] ?? [] as $tx) {
        $tTime = !empty($tx['created_at']) ? strtotime($tx['created_at']) : 0;
        if ($tTime >= $cutoff) {
            $newTxs[] = $tx;
        }
    }

    $newTopups = [];
    foreach ($data['topup_orders'] ?? [] as $ordId => $ord) {
        $oTime = !empty($ord['created_at']) ? strtotime($ord['created_at']) : 0;
        if ($oTime >= $cutoff) {
            $newTopups[$ordId] = $ord;
        }
    }

    $data['transactions'] = $newTxs;
    $data['topup_orders'] = $newTopups;
    $data['settings']['last_purged_at'] = time();
    warung_save_data($data);

    return ($initialCount - count($newTxs));
}

/**
 * Check if Fitur Warung add-on is active.
 */
function warung_check_addon_status($subdomain = null, $forceRefresh = false) {
    if (function_exists('mikhmon_is_desktop_mode') && mikhmon_is_desktop_mode()) {
        $isLicensed = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : true;
        return [
            'success' => true,
            'addon' => [
                'name' => 'FITUR WARUNG',
                'slug' => 'mikhmon_agent',
                'price' => 0,
                'formatted_price' => 'Gratis (Desktop)',
                'is_subscribed' => $isLicensed,
                'is_active' => $isLicensed
            ],
            'tenant' => [
                'name' => 'MIKHMON Desktop',
                'subdomain' => 'desktop',
                'is_desktop' => true,
                'has_enough_balance' => true,
                'balance' => 999999999,
                'formatted_balance' => 'Rp 0'
            ]
        ];
    }
    if (empty($subdomain)) {
        $subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(dirname(__DIR__));
    }
    if (empty($subdomain) || in_array($subdomain, ['include', 'mikhmon-template', 'mikhmon-template-v7', 'public', 'html'])) {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $hostParts = explode('.', $_SERVER['HTTP_HOST']);
            $subdomain = $hostParts[0] ?? '';
        }
    }

    $sessionHint = $_SESSION['m_session'] ?? ($_GET['session'] ?? '');
    $hostHint = $_SERVER['HTTP_HOST'] ?? '';

    $now = time();
    if (!$forceRefresh && !isset($_GET['refresh']) && isset($_SESSION['warung_addon_cache']) && ($_SESSION['warung_addon_cache']['time'] ?? 0) > ($now - 20)) {
        $cached = $_SESSION['warung_addon_cache']['data'] ?? null;
        if ($cached && !empty($cached['tenant'])) {
            return $cached;
        }
    }

    $domain = function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : (
        (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'airnetsolution.com') !== false) || (defined('MIKHMON_BRAND') && strpos(MIKHMON_BRAND, 'airnetsolution.com') !== false) ? 'airnetsolution.com' : 'dgtlnetsolution.com'
    );
    $panelUrl = function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.' . $domain;
    $apiBaseUrl = function_exists('mikhmon_get_api_url') ? mikhmon_get_api_url() : 'https://' . $domain;

    $query = '?subdomain=' . urlencode($subdomain)
        . (!empty($sessionHint) ? '&session=' . urlencode($sessionHint) : '')
        . (!empty($hostHint) ? '&host=' . urlencode($hostHint) : '');

    $candidateUrls = array_unique([
        $panelUrl . '/api/v1/mikhmon/addon/status' . $query,
        $apiBaseUrl . '/api/v1/mikhmon/addon/status' . $query,
        $panelUrl . '/api/mikhmon/addon/status' . $query,
        $apiBaseUrl . '/api/mikhmon/addon/status' . $query,
    ]);

    $resp = null;
    $httpCode = 0;
    foreach ($candidateUrls as $apiUrl) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $resp) {
            $json = @json_decode($resp, true);
            if ($json && isset($json['success']) && $json['success'] === true && !empty($json['tenant'])) {
                $_SESSION['warung_addon_cache'] = [
                    'time' => $now,
                    'data' => $json
                ];
                return $json;
            }
        }
    }

    if ($httpCode === 200 && $resp) {
        $json = @json_decode($resp, true);
        if ($json && isset($json['success'])) {
            $_SESSION['warung_addon_cache'] = [
                'time' => $now,
                'data' => $json
            ];
            return $json;
        }
    }

    $isAfrica = ($domain === 'airnetsolution.com' || $domain === 'nodera.africa' || $domain === 'nodera.ci');
    $fallback = [
        'success' => false,
        'addon' => [
            'name' => $isAfrica ? 'MODULE REVENDEUR & BOUTIQUE DE VOUCHERS' : 'FITUR WARUNG',
            'slug' => 'mikhmon_agent',
            'price' => 15000,
            'formatted_price' => $isAfrica ? '15 000 FCFA' : 'Rp 15.000',
            'is_subscribed' => false,
            'is_active' => false
        ],
        'tenant' => null
    ];
    return $fallback;
}

/**
 * Activate Addon Fitur Warung via Nodera API.
 */
function warung_activate_addon($subdomain = null) {
    if (empty($subdomain)) {
        $subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(dirname(__DIR__));
    }
    if (empty($subdomain) || in_array($subdomain, ['include', 'mikhmon-template', 'mikhmon-template-v7', 'public', 'html'])) {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $hostParts = explode('.', $_SERVER['HTTP_HOST']);
            $subdomain = $hostParts[0] ?? '';
        }
    }

    $sessionHint = $_SESSION['m_session'] ?? ($_GET['session'] ?? '');
    $hostHint = $_SERVER['HTTP_HOST'] ?? '';

    unset($_SESSION['warung_addon_cache']);

    $domain = function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : (
        (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'airnetsolution.com') !== false) || (defined('MIKHMON_BRAND') && strpos(MIKHMON_BRAND, 'airnetsolution.com') !== false) ? 'airnetsolution.com' : 'dgtlnetsolution.com'
    );
    $panelUrl = function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.' . $domain;
    $apiBaseUrl = function_exists('mikhmon_get_api_url') ? mikhmon_get_api_url() : 'https://' . $domain;

    $candidateUrls = array_unique([
        $panelUrl . '/api/v1/mikhmon/addon/activate',
        $apiBaseUrl . '/api/v1/mikhmon/addon/activate',
        $panelUrl . '/api/mikhmon/addon/activate',
        $apiBaseUrl . '/api/mikhmon/addon/activate',
    ]);

    $postData = json_encode([
        'subdomain'  => $subdomain,
        'session'    => $sessionHint,
        'host'       => $hostHint,
        'addon_slug' => 'mikhmon_agent'
    ]);

    $resp = null;
    $httpCode = 0;
    foreach ($candidateUrls as $apiUrl) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $resp) {
            break;
        }
    }

    unset($_SESSION['warung_addon_cache']);

    if ($resp) {
        $json = @json_decode($resp, true);
        if ($json) return $json;
    }

    return [
        'success' => false,
        'error' => warung_get_lang_str('warung_alert_failed_connect_nodera', 'Gagal menghubungi server pusat Nodera. Silakan coba beberapa saat lagi.')
    ];
}

/**
 * Generate secure hash for PIN.
 */
function warung_hash_pin($pin) {
    return hash('sha256', trim($pin) . '_nodera_warung_secret_salt_2026');
}

/**
 * Authenticate Warung login.
 */
function warung_login($usernameOrPhone, $pin, $remember = false) {
    $data = warung_get_data();
    $target = null;
    $rawIdentifier = trim($usernameOrPhone);
    $compIdentifier = warung_normalize_phone_compare($rawIdentifier);

    foreach ($data['warungs'] as &$w) {
        $wUsername = trim($w['username'] ?? '');
        $wPhone = trim($w['phone'] ?? '');
        $wCompPhone = warung_normalize_phone_compare($wPhone);

        $isUsernameMatch = (strcasecmp($wUsername, $rawIdentifier) === 0);
        $isPhoneMatch = (!empty($compIdentifier) && !empty($wCompPhone) && $compIdentifier === $wCompPhone) || ($wPhone === $rawIdentifier);

        if (($isUsernameMatch || $isPhoneMatch) && ($w['status'] ?? 'active') === 'active') {
            $pinHash = warung_hash_pin($pin);
            if ($w['pin_hash'] === $pinHash || (isset($w['pin']) && $w['pin'] === $pin)) {
                $target = $w;
                break;
            }
        }
    }

    if (!$target) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_login_invalid_credentials', 'Username/No. WhatsApp atau PIN salah, atau akun dinonaktifkan.')];
    }

    $_SESSION['warung_user'] = [
        'id'       => $target['id'],
        'name'     => $target['name'],
        'owner'    => $target['owner'] ?? '',
        'phone'    => $target['phone'],
        'username' => $target['username']
    ];

    if ($remember) {
        $token = base64_encode($target['id'] . '|' . $target['pin_hash'] . '|' . time());
        @setcookie('nodera_warung_auth', $token, time() + (86400 * 365), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }

    return ['success' => true, 'warung' => $target];
}

/**
 * Get current logged in Warung from session or persistent cookie.
 */
function warung_get_current_user() {
    $data = warung_get_data();

    if (isset($_SESSION['warung_user']['id'])) {
        $id = $_SESSION['warung_user']['id'];
        foreach ($data['warungs'] as $w) {
            if ($w['id'] === $id && ($w['status'] ?? 'active') === 'active') {
                return $w;
            }
        }
    }

    if (!empty($_COOKIE['nodera_warung_auth'])) {
        $decoded = @base64_decode($_COOKIE['nodera_warung_auth']);
        if ($decoded && strpos($decoded, '|') !== false) {
            list($wId, $wHash) = explode('|', $decoded, 3);
            foreach ($data['warungs'] as $w) {
                if ($w['id'] === $wId && $w['pin_hash'] === $wHash && ($w['status'] ?? 'active') === 'active') {
                    $_SESSION['warung_user'] = [
                        'id'       => $w['id'],
                        'name'     => $w['name'],
                        'owner'    => $w['owner'] ?? '',
                        'phone'    => $w['phone'],
                        'username' => $w['username']
                    ];
                    return $w;
                }
            }
        }
    }

    return null;
}

/**
 * Logout Warung.
 */
function warung_logout() {
    unset($_SESSION['warung_user']);
    if (isset($_COOKIE['nodera_warung_auth'])) {
        @setcookie('nodera_warung_auth', '', time() - 3600, '/');
        unset($_COOKIE['nodera_warung_auth']);
    }
}

/**
 * Add or Update Warung account.
 */
function warung_save_account($id, $name, $owner, $phone, $username, $pin, $status = 'active', $routerSession = '', $customPrices = []) {
    $data = warung_get_data();
    $phone = warung_normalize_phone_display($phone);
    $compPhone = warung_normalize_phone_compare($phone);

    foreach ($data['warungs'] as $w) {
        if ($w['id'] !== $id) {
            if (strcasecmp($w['username'], $username) === 0) {
                return ['success' => false, 'error' => 'Username sudah digunakan oleh warung lain.'];
            }
            if (!empty($compPhone) && warung_normalize_phone_compare($w['phone']) === $compPhone) {
                return ['success' => false, 'error' => 'Nomor WhatsApp sudah digunakan oleh warung lain.'];
            }
        }
    }

    $cleanCustomPrices = [];
    if (is_array($customPrices)) {
        foreach ($customPrices as $pName => $pData) {
            $pName = trim($pName);
            if (empty($pName)) continue;
            $sellPrice = (float) ($pData['sell_price'] ?? 0);
            $costPrice = (float) ($pData['cost_price'] ?? 0);
            if ($sellPrice > 0 || $costPrice > 0) {
                $cleanCustomPrices[$pName] = [
                    'name'       => $pName,
                    'sell_price' => $sellPrice,
                    'cost_price' => $costPrice,
                    'validity'   => $pData['validity'] ?? 'Aktif'
                ];
            }
        }
    }

    $cleanPin = !empty($pin) ? preg_replace('/[^0-9]/', '', (string) $pin) : '';
    if (!empty($pin) && strlen($cleanPin) !== 4) {
        return ['success' => false, 'error' => 'PIN transaksi harus berupa 4 digit angka.'];
    }

    if ($id && isset($data['warungs'][$id])) {
        $data['warungs'][$id]['name'] = $name;
        $data['warungs'][$id]['owner'] = $owner;
        $data['warungs'][$id]['phone'] = $phone;
        $data['warungs'][$id]['username'] = $username;
        $data['warungs'][$id]['status'] = $status;
        $data['warungs'][$id]['router_session'] = $routerSession;
        $data['warungs'][$id]['custom_prices'] = $cleanCustomPrices;
        if (!empty($cleanPin)) {
            $data['warungs'][$id]['pin_hash'] = warung_hash_pin($cleanPin);
        }
        $data['warungs'][$id]['updated_at'] = date('Y-m-d H:i:s');
    } else {
        if (empty($cleanPin)) {
            $cleanPin = str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }
        $newId = 'WRG-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $data['warungs'][$newId] = [
            'id'             => $newId,
            'name'           => $name,
            'owner'          => $owner,
            'phone'          => $phone,
            'username'       => $username,
            'pin_hash'       => warung_hash_pin($cleanPin),
            'router_session' => $routerSession,
            'custom_prices'  => $cleanCustomPrices,
            'balance'        => 0,
            'status'         => $status,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s')
        ];
        $id = $newId;
    }

    warung_save_data($data);
    return ['success' => true, 'id' => $id, 'pin' => $cleanPin];
}

/**
 * Reset PIN Warung account (Manual or Random 4-digit).
 */
function warung_reset_pin($id, $newPin = null) {
    $data = warung_get_data();
    if (!isset($data['warungs'][$id])) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_account_not_found', 'Akun warung tidak ditemukan.')];
    }

    if (empty($newPin)) {
        $newPin = str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    } else {
        $newPin = preg_replace('/[^0-9]/', '', (string) $newPin);
        if (strlen($newPin) !== 4) {
            return ['success' => false, 'error' => 'PIN baru harus terdiri dari tepat 4 angka.'];
        }
    }

    $data['warungs'][$id]['pin_hash'] = warung_hash_pin($newPin);
    $data['warungs'][$id]['updated_at'] = date('Y-m-d H:i:s');
    warung_save_data($data);

    return [
        'success' => true,
        'new_pin' => $newPin,
        'warung'  => $data['warungs'][$id],
        'message' => warung_get_lang_str('warung_pin_reset_success_msg', 'PIN warung %s berhasil direset.', [$data['warungs'][$id]['name']])
    ];
}

/**
 * Delete Warung account.
 */
function warung_delete_account($id) {
    $data = warung_get_data();
    if (isset($data['warungs'][$id])) {
        unset($data['warungs'][$id]);
        warung_save_data($data);
        return true;
    }
    return false;
}

/**
 * Manual Topup Saldo by Admin (with anti-duplicate protection).
 */
function warung_admin_topup_balance($warungId, $amount, $notes = 'Top-up manual oleh Admin') {
    $amount = (float) $amount;
    if ($amount <= 0) {
        return ['success' => false, 'error' => 'Nominal top-up harus lebih dari 0.'];
    }

    $data = warung_get_data();
    if (!isset($data['warungs'][$warungId])) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_data_not_found', 'Data warung tidak ditemukan.')];
    }

    // Deduplication check: check if the exact same manual topup was executed within the last 6 seconds
    if (!empty($data['transactions'])) {
        $recentTxs = array_slice($data['transactions'], -5);
        foreach ($recentTxs as $tx) {
            if (
                ($tx['warung_id'] ?? '') === $warungId &&
                ($tx['type'] ?? '') === 'manual_topup' &&
                (float)($tx['amount'] ?? 0) === $amount &&
                !empty($tx['created_at']) &&
                (time() - strtotime($tx['created_at'])) < 6
            ) {
                return ['success' => true, 'new_balance' => (float)($data['warungs'][$warungId]['balance'] ?? 0), 'tx_id' => $tx['id'] ?? '', 'duplicate' => true];
            }
        }
    }

    $currentBalance = (float) ($data['warungs'][$warungId]['balance'] ?? 0);
    $newBalance = $currentBalance + $amount;
    $data['warungs'][$warungId]['balance'] = $newBalance;
    $data['warungs'][$warungId]['updated_at'] = date('Y-m-d H:i:s');

    $txId = 'TX-TP-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $data['transactions'][] = [
        'id'          => $txId,
        'warung_id'   => $warungId,
        'warung_name' => $data['warungs'][$warungId]['name'],
        'type'        => 'manual_topup',
        'amount'      => $amount,
        'balance'     => $newBalance,
        'notes'       => $notes,
        'created_at'  => date('Y-m-d H:i:s')
    ];

    warung_save_data($data);
    return ['success' => true, 'new_balance' => $newBalance, 'tx_id' => $txId];
}

/**
 * Delete / Rollback Manual Topup and pull back warung balance.
 */
function warung_admin_delete_topup($txId) {
    $txId = trim((string) $txId);
    if (empty($txId)) {
        return ['success' => false, 'error' => 'ID Transaksi tidak valid.'];
    }

    $data = warung_get_data();
    $foundIndex = -1;
    $targetTx = null;

    if (!empty($data['transactions'])) {
        foreach ($data['transactions'] as $idx => $tx) {
            if (($tx['id'] ?? '') === $txId && ($tx['type'] ?? '') === 'manual_topup') {
                $foundIndex = $idx;
                $targetTx = $tx;
                break;
            }
        }
    }

    if ($foundIndex === -1 || !$targetTx) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_topup_tx_not_found', 'Transaksi top-up manual tidak ditemukan atau sudah dibatalkan.')];
    }

    $wId = $targetTx['warung_id'] ?? '';
    $amount = (float)($targetTx['amount'] ?? 0);
    $warungName = $targetTx['warung_name'] ?? ($data['warungs'][$wId]['name'] ?? 'Mitra Warung');

    $newBal = 0;
    if (isset($data['warungs'][$wId])) {
        $currentBal = (float)($data['warungs'][$wId]['balance'] ?? 0);
        $newBal = max(0, $currentBal - $amount);
        $data['warungs'][$wId]['balance'] = $newBal;
        $data['warungs'][$wId]['updated_at'] = date('Y-m-d H:i:s');
    }

    // Remove the target topup record from history
    array_splice($data['transactions'], $foundIndex, 1);

    // Save data
    warung_save_data($data);

    return [
        'success'     => true,
        'warung_name' => $warungName,
        'amount'      => $amount,
        'new_balance' => $newBal,
    ];
}

/**
 * Save profile pricing mappings for a specific router session.
 */
function warung_save_profile_prices($prices, $session = '') {
    $data = warung_get_data();
    if (empty($session)) {
        $creds = warung_get_router_credentials();
        $session = $creds['session'] ?? ($_SESSION['session'] ?? 'default');
    }
    if (!isset($data['session_profile_prices']) || !is_array($data['session_profile_prices'])) {
        $data['session_profile_prices'] = [];
    }
    $data['session_profile_prices'][$session] = $prices;
    // Keep global fallback for backward compatibility
    $data['profile_prices'] = $prices;
    warung_save_data($data);
    return true;
}

/**
 * Safely parse and retrieve Mikhmon router configuration.
 */
function warung_get_mikhmon_config() {
    $cfgPath = file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : (file_exists('./include/config.php') ? './include/config.php' : '');
    if (empty($cfgPath) || !file_exists($cfgPath)) {
        return [];
    }
    $data = [];
    @include $cfgPath;
    return $data;
}

/**
 * Reliably resolve router credentials from config.
 */
function warung_get_router_credentials($session = '') {
    $cfgData = warung_get_mikhmon_config();
    $targetSes = '';

    if (!empty($session) && isset($cfgData[$session]) && is_array($cfgData[$session])) {
        $targetSes = $session;
    } else {
        $sesInSession = $_SESSION['session'] ?? '';
        if (!empty($sesInSession) && isset($cfgData[$sesInSession]) && is_array($cfgData[$sesInSession])) {
            $targetSes = $sesInSession;
        } else {
            foreach ($cfgData as $k => $v) {
                if ($k !== 'mikhmon' && is_array($v) && !empty($v[1])) {
                    $targetSes = $k;
                    break;
                }
            }
        }
    }

    if (empty($targetSes) || !isset($cfgData[$targetSes])) {
        return null;
    }

    $sessCfg = $cfgData[$targetSes];
    $rawIp = $sessCfg[1] ?? '';
    $rawUser = $sessCfg[2] ?? '';
    $rawPass = $sessCfg[3] ?? '';
    $rawHs = $sessCfg[4] ?? 'Hotspot WiFi';
    $rawDns = $sessCfg[5] ?? 'hotspot.local';

    return [
        'session'     => $targetSes,
        'ip'          => $rawIp,
        'user'        => $rawUser,
        'pass'        => $rawPass,
        'hotspotname' => strpos($rawHs, '%') !== false ? (explode('%', $rawHs)[1] ?? explode('%', $rawHs)[0]) : $rawHs,
        'dnsname'     => strpos($rawDns, '^') !== false ? (explode('^', $rawDns)[1] ?? explode('^', $rawDns)[0]) : $rawDns,
    ];
}

/**
 * Get configured profiles strictly scoped to the target router session.
 * Prevents profiles from multiple MikroTik routers from leaking or mixing together.
 * Includes real-time pool stock calculation when stock mode is pool or auto.
 */
function warung_get_available_profiles($session = '', $warung = null) {
    $data = warung_get_data();

    if (is_string($warung) && isset($data['warungs'][$warung])) {
        $warung = $data['warungs'][$warung];
    }

    if (is_array($warung) && !empty($warung['router_session'])) {
        $session = $warung['router_session'];
    }

    $creds = warung_get_router_credentials($session);
    $resolvedSession = $creds['session'] ?? $session;

    // Load saved prices specifically for THIS router session
    $sessionPrices = [];
    if (!empty($resolvedSession) && isset($data['session_profile_prices'][$resolvedSession]) && is_array($data['session_profile_prices'][$resolvedSession])) {
        $sessionPrices = $data['session_profile_prices'][$resolvedSession];
    } elseif (!empty($data['profile_prices']) && is_array($data['profile_prices'])) {
        $sessionPrices = $data['profile_prices'];
    }

    $settings = warung_get_settings();
    $stockMode = $settings['stock_mode'] ?? 'auto';

    $configured = [];
    include_once __DIR__ . '/../lib/routeros_api.class.php';
    include_once __DIR__ . '/order_helper.php';

    $connectedToRouter = false;

    if ($creds) {
        $API = mikhmon_get_router_api($creds['ip'], $creds['user'], $creds['pass']);
        if ($API) {
            $connectedToRouter = true;
            $rawProfs = $API->comm('/ip/hotspot/user/profile/print');
            $rawProfs = is_array($rawProfs) ? $rawProfs : [];

            // Calculate real-time pool stock if stock_mode is pool or auto
            $stockCount = [];
            if ($stockMode === 'pool' || $stockMode === 'auto') {
                $rawUsers = $API->comm('/ip/hotspot/user/print', ['?disabled' => 'false']);
                $rawUsers = is_array($rawUsers) ? $rawUsers : [];
                $soldUsernames = [];
                if (!empty($data['transactions'])) {
                    foreach ($data['transactions'] as $tx) {
                        if (!empty($tx['username'])) {
                            $soldUsernames[] = $tx['username'];
                        }
                    }
                }
                foreach ($rawUsers as $u) {
                    $uProf = trim((string)($u['profile'] ?? ''));
                    if (empty($uProf) || $uProf === 'default') continue;
                    if (function_exists('mikhmon_is_unused_voucher') && mikhmon_is_unused_voucher($u, $soldUsernames)) {
                        $stockCount[$uProf] = ($stockCount[$uProf] ?? 0) + 1;
                    }
                }
            }

            foreach ($rawProfs as $p) {
                $pName = $p['name'] ?? '';
                if (empty($pName) || $pName === 'default') continue;

                $onLogin = $p['on-login'] ?? '';
                $sPrice = 0;

                if (preg_match('/,([0-9]+),/i', $onLogin, $pm)) {
                    $sPrice = (float) $pm[1];
                }

                $savedPrice = $sessionPrices[$pName] ?? null;
                $savedValidity = $savedPrice['validity'] ?? '';

                $validity = function_exists('mikhmon_resolve_profile_validity')
                    ? mikhmon_resolve_profile_validity($p, $pName, [], false)
                    : (function_exists('mikhmon_infer_validity') ? mikhmon_infer_validity($pName, $savedValidity, false) : '1 Hari');

                $curSell = isset($savedPrice['sell_price']) && (float)$savedPrice['sell_price'] > 0
                    ? (float)$savedPrice['sell_price']
                    : $sPrice;
                $curCost = isset($savedPrice['cost_price']) && (float)$savedPrice['cost_price'] > 0
                    ? (float)$savedPrice['cost_price']
                    : ($curSell > 0 ? floor($curSell * 0.9) : 0);
                $curStatus = ($savedPrice['status'] ?? 'enable') === 'disable' ? 'disable' : 'enable';

                $configured[$pName] = [
                    'name'       => $pName,
                    'validity'   => $validity,
                    'sell_price' => $curSell,
                    'cost_price' => $curCost,
                    'status'     => $curStatus,
                    'stock'      => $stockCount[$pName] ?? 0,
                    'stock_mode' => $stockMode,
                    'auto_found' => true
                ];
            }

            $API->disconnect();
        }
    }

    // Fallback: If router is unreachable, load ONLY profiles configured specifically for this router session
    if (!$connectedToRouter && !empty($sessionPrices)) {
        foreach ($sessionPrices as $pName => $pData) {
            if ($pName === 'default' || empty($pName)) continue;
            $configured[$pName] = [
                'name'       => $pName,
                'validity'   => $pData['validity'] ?? 'Aktif',
                'sell_price' => (float)($pData['sell_price'] ?? 0),
                'cost_price' => (float)($pData['cost_price'] ?? 0),
                'status'     => $pData['status'] ?? 'enable',
                'stock'      => 0,
                'stock_mode' => $stockMode,
            ];
        }
    }

    // Apply Warung-specific custom prices (only for profiles belonging to this router)
    if (is_array($warung) && !empty($warung['custom_prices']) && is_array($warung['custom_prices'])) {
        foreach ($warung['custom_prices'] as $pName => $pData) {
            if (isset($configured[$pName])) {
                if (isset($pData['sell_price']) && (float)$pData['sell_price'] > 0) {
                    $configured[$pName]['sell_price'] = (float)$pData['sell_price'];
                }
                if (isset($pData['cost_price']) && (float)$pData['cost_price'] > 0) {
                    $configured[$pName]['cost_price'] = (float)$pData['cost_price'];
                }
            }
        }
    }

    // Filter out disabled profiles
    $activeProfiles = [];
    foreach ($configured as $pName => $pData) {
        if (($pData['status'] ?? 'enable') === 'disable') {
            continue;
        }
        $activeProfiles[$pName] = $pData;
    }

    return $activeProfiles;
}

/**
 * Generate/Fulfill Voucher via Warung Kasir & Deduct Warung Balance.
 * Supports Pool (Stok MikroTik), On-Demand, and Auto (Pool + Fallback).
 */
function warung_process_voucher_purchase($warungId, $profileName, $buyerPhone = '', $session = '') {
    $data = warung_get_data();
    if (!isset($data['warungs'][$warungId])) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_account_not_found', 'Data akun warung tidak ditemukan.')];
    }

    $warung = $data['warungs'][$warungId];
    if (($warung['status'] ?? 'active') !== 'active') {
        return ['success' => false, 'error' => 'Akun warung Anda sedang dinonaktifkan. Hubungi Admin.'];
    }

    if (!empty($warung['router_session'])) {
        $session = $warung['router_session'];
    }

    $sellPrice = 0;
    $costPrice = 0;
    if (!empty($warung['custom_prices'][$profileName])) {
        $sellPrice = (float) ($warung['custom_prices'][$profileName]['sell_price'] ?? 0);
        $costPrice = (float) ($warung['custom_prices'][$profileName]['cost_price'] ?? 0);
    }

    if ($sellPrice <= 0 && $costPrice <= 0) {
        $credsForPricing = warung_get_router_credentials($session);
        $resolvedSession = $credsForPricing['session'] ?? $session;
        $profilePrice = (!empty($resolvedSession) && isset($data['session_profile_prices'][$resolvedSession][$profileName]))
            ? $data['session_profile_prices'][$resolvedSession][$profileName]
            : ($data['profile_prices'][$profileName] ?? null);
        $sellPrice = (float) ($profilePrice['sell_price'] ?? 0);
        $costPrice = (float) ($profilePrice['cost_price'] ?? 0);
    }

    if ($costPrice <= 0 && $sellPrice > 0) {
        $costPrice = floor($sellPrice * 0.9);
    }
    if ($sellPrice <= 0 && $costPrice > 0) {
        $sellPrice = floor($costPrice / 0.9);
    }
    if ($costPrice <= 0 && $sellPrice <= 0) {
        $sellPrice = 5000;
        $costPrice = 4500;
    }

    $currentBalance = (float) ($warung['balance'] ?? 0);
    if ($currentBalance < $costPrice) {
        $kurang = $costPrice - $currentBalance;
        return [
            'success' => false,
            'error'   => warung_get_lang_str('warung_insufficient_deposit_balance', 'Saldo deposit warung tidak mencukupi. Sisa saldo: Rp %s (Kurang Rp %s). Silakan lakukan Top-Up Saldo terlebih dahulu.', [number_format($currentBalance, 0, ',', '.'), number_format($kurang, 0, ',', '.')])
        ];
    }

    include_once __DIR__ . '/../lib/routeros_api.class.php';
    include_once __DIR__ . '/order_helper.php';

    $creds = warung_get_router_credentials($session);
    if (!$creds) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_router_session_not_active', 'Sesi router MikroTik belum aktif atau tidak ditemukan di config Mikhmon. Hubungi Admin.')];
    }

    $hotspotname = $creds['hotspotname'];
    $dnsname = $creds['dnsname'];

    $API = mikhmon_get_router_api($creds['ip'], $creds['user'], $creds['pass']);
    if (!$API) {
        return ['success' => false, 'error' => warung_get_lang_str('warung_failed_connect_mikrotik', 'Gagal menghubungkan ke router MikroTik (%s). Periksa koneksi API/VPN router.', [$creds['ip']])];
    }

    $settings = warung_get_settings();
    $stockMode = $settings['stock_mode'] ?? 'auto';

    $soldUsernames = [];
    if (!empty($data['transactions'])) {
        foreach ($data['transactions'] as $tx) {
            if (!empty($tx['username'])) {
                $soldUsernames[] = $tx['username'];
            }
        }
    }

    $allocatedUser = null;

    // 1. If stock_mode == 'pool' or 'auto': search for available unused voucher in MikroTik pool
    if ($stockMode === 'pool' || $stockMode === 'auto') {
        $users = $API->comm('/ip/hotspot/user/print', [
            '?disabled' => 'false'
        ]);
        $users = is_array($users) ? $users : [];

        foreach ($users as $u) {
            $uProf = trim((string)($u['profile'] ?? ''));
            if (strcasecmp($uProf, $profileName) !== 0) {
                continue;
            }
            if (function_exists('mikhmon_is_unused_voucher') && mikhmon_is_unused_voucher($u, $soldUsernames)) {
                $allocatedUser = [
                    'username' => $u['name'],
                    'password' => $u['password'] ?? $u['name'],
                ];
                break;
            }
        }
    }

    // 2. If Pool mode is STRICT and no stock available in MikroTik
    if ($stockMode === 'pool' && !$allocatedUser) {
        $API->disconnect();
        return [
            'success' => false,
            'error'   => 'Stok voucher untuk profil "' . $profileName . '" sedang habis di MikroTik router. Hubungi Admin untuk generate stok voucher baru.'
        ];
    }

    // 3. If ondemand or auto (fallback): Generate voucher on-demand
    if (!$allocatedUser) {
        $prefix = $settings['voucher_prefix'] ?? 'VC-';
        $charLen = max(3, min(8, intval($settings['char_length'] ?? 6)));
        $charFmt = $settings['char_format'] ?? 'mix1';

        include_once __DIR__ . '/../lib/routeros_api.class.php';

        if ($charFmt === 'mix1' || $charFmt === 'mix_upper') {
            $vCode = function_exists('randNUC') ? randNUC($charLen) : substr(md5(uniqid()), 0, $charLen);
        } elseif ($charFmt === 'mix' || $charFmt === 'mix_lower') {
            $vCode = function_exists('randNLC') ? randNLC($charLen) : substr(md5(uniqid()), 0, $charLen);
        } elseif ($charFmt === 'mix2') {
            $vCode = function_exists('randNULC') ? randNULC($charLen) : substr(md5(uniqid()), 0, $charLen);
        } elseif ($charFmt === 'lower') {
            $vCode = function_exists('randLC') ? randLC($charLen) : substr(md5(uniqid()), 0, $charLen);
        } elseif ($charFmt === 'upper') {
            $vCode = function_exists('randUC') ? randUC($charLen) : substr(md5(uniqid()), 0, $charLen);
        } elseif ($charFmt === 'upplow') {
            $vCode = function_exists('randULC') ? randULC($charLen) : substr(md5(uniqid()), 0, $charLen);
        } elseif ($charFmt === 'num' || $charFmt === 'numeric') {
            $vCode = function_exists('randN') ? randN($charLen) : substr(str_shuffle('01234567890123456789'), 0, $charLen);
        } else {
            $vCode = function_exists('randNUC') ? randNUC($charLen) : substr(md5(uniqid()), 0, $charLen);
        }

        $vUser = $prefix . $vCode;
        $vPass = $vUser;

        $comment = 'vc-' . $warung['id'] . '-' . date('d.m.y') . '-wrg';
        $addUserRes = $API->comm('/ip/hotspot/user/add', [
            'server'   => 'all',
            'name'     => $vUser,
            'password' => $vPass,
            'profile'  => $profileName,
            'comment'  => $comment
        ]);

        $allocatedUser = [
            'username' => $vUser,
            'password' => $vPass,
        ];
    }

    $API->disconnect();

    $vUser = $allocatedUser['username'];
    $vPass = $allocatedUser['password'];

    // Resolve validity for this profile
    $validity = '';
    $savedPrice = (!empty($resolvedSession) && isset($data['session_profile_prices'][$resolvedSession][$profileName]))
        ? $data['session_profile_prices'][$resolvedSession][$profileName]
        : ($data['profile_prices'][$profileName] ?? null);
    $savedValidity = $savedPrice['validity'] ?? '';

    if (function_exists('mikhmon_infer_validity')) {
        $validity = mikhmon_infer_validity($profileName, $savedValidity, false);
    } else {
        $validity = !empty($savedValidity) ? $savedValidity : '1 Hari';
    }

    // Deduct Warung Balance
    $newBalance = $currentBalance - $costPrice;
    $data['warungs'][$warungId]['balance'] = $newBalance;
    $data['warungs'][$warungId]['updated_at'] = date('Y-m-d H:i:s');

    // Record Transaction
    $txId = 'TX-VC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $txRecord = [
        'id'          => $txId,
        'warung_id'   => $warungId,
        'warung_name' => $warung['name'],
        'type'        => 'voucher',
        'profile'     => $profileName,
        'validity'    => $validity,
        'username'    => $vUser,
        'password'    => $vPass,
        'sell_price'  => $sellPrice,
        'cost_price'  => $costPrice,
        'margin'      => ($sellPrice - $costPrice),
        'buyer_phone' => $buyerPhone,
        'balance'     => $newBalance,
        'created_at'  => date('Y-m-d H:i:s')
    ];
    $data['transactions'][] = $txRecord;
    warung_save_data($data);

    // Send WhatsApp to buyer if phone is provided
    $waSent = false;
    $waError = null;
    if (!empty($buyerPhone)) {
        include_once __DIR__ . '/whatsapp_helper.php';

        $cleanPhone = preg_replace('/[^0-9]/', '', $buyerPhone);
        if (substr($cleanPhone, 0, 1) === '0') $cleanPhone = '62' . substr($cleanPhone, 1);
        if (substr($cleanPhone, 0, 1) === '8') $cleanPhone = '62' . $cleanPhone;

        $waParams = [
            'order_id'      => $txId,
            'brand'         => $warung['name'] ?? $hotspotname,
            'location_name' => $hotspotname,
            'package'       => $profileName,
            'price'         => $sellPrice,
            'validity'      => $validity,
            'username'      => $vUser,
            'password'      => $vPass,
            'dnsname'       => $dnsname,
        ];

        if (function_exists('mikhmon_send_voucher_wa')) {
            $waRes = mikhmon_send_voucher_wa($session, $cleanPhone, $waParams);
            $waSent = $waRes['ok'] ?? false;
            if (!$waSent) $waError = $waRes['description'] ?? warung_get_lang_str('warung_failed_send_wa', 'Gagal kirim WA');
        }
    }

    // Send Telegram Notification to Admin
    include_once __DIR__ . '/telegram_helper.php';
    if (function_exists('mikhmon_send_warung_voucher_telegram')) {
        $txRecord['hotspot_name'] = $hotspotname;
        $txRecord['dns_name'] = $dnsname;
        $txRecord['validity'] = $validity;
        @mikhmon_send_warung_voucher_telegram($session, $txRecord, 'Rp', null);
    }

    // Check and alert admin if remaining unused voucher stock is low
    if (!empty($profileName)) {
        include_once __DIR__ . '/order_helper.php';
        if (function_exists('mikhmon_check_and_alert_low_stock') && !empty($creds['ip'])) {
            @mikhmon_check_and_alert_low_stock($session, $profileName, $creds['ip'], $creds['user'], $creds['pass'], '', $hotspotname);
        }
    }

    return [
        'success'      => true,
        'tx_id'        => $txId,
        'username'     => $vUser,
        'password'     => $vPass,
        'profile'      => $profileName,
        'validity'     => $validity,
        'sell_price'   => $sellPrice,
        'cost_price'   => $costPrice,
        'buyer_phone'  => $buyerPhone,
        'hotspot_name' => $hotspotname,
        'dns_name'     => $dnsname,
        'new_balance'  => $newBalance,
        'wa_sent'      => $waSent,
        'wa_error'     => $waError
    ];
}