<?php
/**
 * LICENSE LOADER — MIKHMON by NODERA (panel.dgtlnetsolution.com)
 * Handles desktop HWID license verification, 7-day free trial on first launch,
 * expiration date checking, and multi-tenant subscription gating.
 */
if (file_exists(__DIR__ . '/compatibility.php')) {
    include_once __DIR__ . '/compatibility.php';
}

$__licenseFile = __DIR__ . '/../config/license.php';
if (!file_exists($__licenseFile)) {
    $__licenseFile = __DIR__ . '/license_data.php';
}
if (file_exists($__licenseFile)) {
    @include_once $__licenseFile;
}

if (!defined('MIKHMON_BRAND')) {
    define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');
}
if (!defined('MIKHMON_SUBDOMAIN')) {
    $folder = basename(dirname(__DIR__));
    define('MIKHMON_SUBDOMAIN', $folder);
}

if (!function_exists('mikhmon_is_desktop_mode')) {
    function mikhmon_is_desktop_mode(): bool {
        if (defined('MIKHMON_MODE') && MIKHMON_MODE === 'DESKTOP') {
            return true;
        }
        $hasCloudMarker = file_exists(__DIR__ . '/../.cloud_mode') || file_exists(__DIR__ . '/.cloud_mode');
        if ($hasCloudMarker) {
            return false;
        }
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(explode(':', $host)[0]);
        $isLocal = in_array($host, ['127.0.0.1', 'localhost', '::1'])
            || (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']));
        return $isLocal;
    }
}

if (!function_exists('mikhmon_get_hwid')) {
    function mikhmon_get_hwid(): string {
        if (defined('MIKHMON_HWID') && !empty(MIKHMON_HWID)) {
            return (string) MIKHMON_HWID;
        }
        $raw = php_uname('s') . '-' . php_uname('n') . '-' . php_uname('m') . '-' . (getenv('COMPUTERNAME') ?: '') . '-' . (getenv('USER') ?: get_current_user());
        if (function_exists('disk_total_space')) {
            $raw .= '-' . @disk_total_space(__DIR__);
        }
        $hash = strtoupper(substr(hash('sha256', $raw), 0, 20));
        return 'NDR-HWID-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
    }
}

// Default constants if not defined in config/license.php
if (!defined('MIKHMON_STATUS')) {
    define('MIKHMON_STATUS', 'UNLICENSED');
}
if (!defined('MIKHMON_EXPIRY')) {
    define('MIKHMON_EXPIRY', '0000-00-00');
}
if (!defined('MIKHMON_LICENSE_KEY')) {
    define('MIKHMON_LICENSE_KEY', '');
}

if (!function_exists('mikhmon_expiry_ts')) {
    function mikhmon_expiry_ts(): ?int {
        $e = trim((string) MIKHMON_EXPIRY);
        if ($e === '' || $e === '0000-00-00' || $e === 'LIFETIME') return null;
        $t = strtotime($e);
        return $t ?: null;
    }
}

if (!function_exists('mikhmon_is_licensed')) {
    function mikhmon_is_licensed(): bool {
        // Desktop / Standalone mode validation
        if (mikhmon_is_desktop_mode()) {
            $isUnlicensed = (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)) || !defined('MIKHMON_STATUS') || in_array(strtoupper(MIKHMON_STATUS), ['UNLICENSED', 'INACTIVE'], true));
            
            // Auto-claim 7-day trial on first run if never attempted
            if ($isUnlicensed) {
                $trialMarker = __DIR__ . '/../config/.trial_attempted';
                if (!file_exists($trialMarker)) {
                    @touch($trialMarker);
                    if (function_exists('mikhmon_activate_trial_license')) {
                        $res = mikhmon_activate_trial_license();
                        if (!empty($res['success'])) {
                            $lFile = __DIR__ . '/../config/license.php';
                            if (file_exists($lFile)) {
                                @include $lFile;
                            }
                        }
                    }
                }
            }

            if (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY))) {
                return false;
            }
            if (!defined('MIKHMON_STATUS') || strtoupper(MIKHMON_STATUS) !== 'ACTIVE') {
                return false;
            }
            if (!defined('MIKHMON_HWID') || empty(trim(MIKHMON_HWID))) {
                return false;
            }
            // Strict HWID matching
            $currentHwid = mikhmon_get_hwid();
            if (strcasecmp(trim(MIKHMON_HWID), trim($currentHwid)) !== 0) {
                return false;
            }
            return !mikhmon_is_expired();
        }

        // Cloud SaaS Mode (when .cloud_mode marker is present)
        if (defined('MIKHMON_STATUS') && in_array(strtoupper(MIKHMON_STATUS), ['SUSPENDED', 'DISABLED', 'BLOCKED', 'EXPIRED', 'UNLICENSED'], true)) {
            return false;
        }

        return !mikhmon_is_expired();
    }
}

if (!function_exists('mikhmon_is_expired')) {
    function mikhmon_is_expired(): bool {
        if (defined('MIKHMON_STATUS') && in_array(strtoupper(MIKHMON_STATUS), ['SUSPENDED', 'DISABLED', 'BLOCKED', 'EXPIRED', 'UNLICENSED'], true)) {
            return true;
        }
        $t = mikhmon_expiry_ts();
        if ($t === null) {
            // If expiry is empty/null in desktop mode without valid key, consider expired
            if (mikhmon_is_desktop_mode() && (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)))) {
                return true;
            }
            return false;
        }
        return time() > ($t + 86399);
    }
}

if (!function_exists('mikhmon_is_suspended')) {
    function mikhmon_is_suspended(): bool {
        return defined('MIKHMON_STATUS') && in_array(strtoupper(MIKHMON_STATUS), ['SUSPENDED', 'DISABLED', 'BLOCKED'], true);
    }
}

if (!function_exists('mikhmon_expiry_text')) {
    function mikhmon_expiry_text(): string {
        if (mikhmon_is_desktop_mode() && (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)) || MIKHMON_STATUS === 'UNLICENSED')) {
            return 'Belum Teraktivasi (Lisensi Diperlukan)';
        }
        if (mikhmon_is_suspended()) {
            return 'Ditangguhkan (Suspended)';
        }
        $t = mikhmon_expiry_ts();
        if ($t === null) {
            return 'Permanen (Lifetime)';
        }
        $diff = ceil(($t + 86399 - time()) / 86400);
        if ($diff < 0) {
            return 'Kedaluwarsa (' . date('d M Y', $t) . ')';
        }
        if ($diff == 0) {
            return 'Berakhir Hari Ini (' . date('d M Y', $t) . ')';
        }
        return $diff . ' Hari Lagi (s/d ' . date('d M Y', $t) . ')';
    }
}

if (!function_exists('mikhmon_verify_desktop_license')) {
    function mikhmon_verify_desktop_license(?string $licenseKey = null, ?string $hwid = null): array {
        if (!mikhmon_is_desktop_mode()) {
            return ['success' => true, 'message' => 'Cloud Mode Active'];
        }

        $key = $licenseKey ?: (defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '');
        $hw = $hwid ?: mikhmon_get_hwid();

        if (empty($key)) {
            return ['success' => false, 'message' => 'Lisensi belum diisi.'];
        }

        $payload = [
            'license_key' => $key,
            'hwid'        => $hw,
            'device_name' => getenv('COMPUTERNAME') ?: (gethostname() ?: 'Mikhmon Desktop Client'),
            'os_info'     => php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')',
        ];

        $endpoints = [
            'https://panel.dgtlnetsolution.com/api/v1/desktop-license/verify',
            'https://gateway.dgtlnetsolution.com/api/v1/desktop-license/verify',
            'https://digitalnet.dgtlnetsolution.com/api/v1/desktop-license/verify',
        ];

        $response = null;
        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mikhmon-Desktop-Client/1.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw && ($httpCode === 200 || $httpCode === 403 || $httpCode === 404)) {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $response = $json;
                    break;
                }
            }
        }

        if (!$response) {
            // Offline Grace Bounded Check
            if (mikhmon_is_licensed() && !mikhmon_is_expired()) {
                return [
                    'success' => true,
                    'offline' => true,
                    'message' => 'Mode Offline (Lisensi lokal aktif).',
                    'license_key' => $key,
                    'expires_at' => defined('MIKHMON_EXPIRY') ? MIKHMON_EXPIRY : '',
                ];
            }
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke Cloud License Server. Pastikan koneksi internet aktif.',
            ];
        }

        return $response;
    }
}

if (!function_exists('mikhmon_save_desktop_license_data')) {
    function mikhmon_save_desktop_license_data(array $data): array {
        if (empty($data)) {
            return ['success' => false, 'message' => 'Data lisensi kosong.'];
        }

        $licenseKey = trim((string)($data['license_key'] ?? ''));
        if (empty($licenseKey)) {
            return ['success' => false, 'message' => 'License Key tidak ditemukan.'];
        }

        $hwid = trim((string)($data['hwid'] ?? mikhmon_get_hwid()));
        $exp = $data['expires_at'] ?? '';
        $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop Standalone';

        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'ACTIVE');\n"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");\n"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");\n"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');\n"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");\n"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");\n"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

        $configFile = __DIR__ . '/../config/license.php';
        @file_put_contents($configFile, $licensePhp);

        return [
            'success'      => true,
            'message'      => 'Lisensi berhasil disimpan dan diaktivasi!',
            'license_key'  => $licenseKey,
            'expires_at'   => $expFormatted ?: 'LIFETIME',
            'product_name' => $prodName,
        ];
    }
}

if (!function_exists('mikhmon_reset_local_license')) {
    function mikhmon_reset_local_license(): bool {
        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Belum diaktivasi / Reset.\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'UNLICENSED');\n"
            . "define('MIKHMON_LICENSE_KEY', '');\n"
            . "define('MIKHMON_HWID', '');\n"
            . "define('MIKHMON_EXPIRY', '');\n"
            . "define('MIKHMON_BRAND', 'by NODERA');\n"
            . "define('MIKHMON_PRODUCT_NAME', 'Mikhmon Standalone');\n"
            . "define('MIKHMON_ACTIVATED_AT', '');\n"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

        $configFile = __DIR__ . '/../config/license.php';
        return (bool) @file_put_contents($configFile, $licensePhp);
    }
}

if (!function_exists('mikhmon_activate_desktop_license')) {
    function mikhmon_activate_desktop_license(string $licenseKey): array {
        $licenseKey = trim($licenseKey);
        if (empty($licenseKey)) {
            return ['success' => false, 'message' => 'Kunci lisensi wajib diisi.'];
        }

        $hwid = mikhmon_get_hwid();
        $deviceName = getenv('COMPUTERNAME') ?: (gethostname() ?: 'Mikhmon Desktop Client');
        $osInfo = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

        $payload = [
            'license_key' => $licenseKey,
            'hwid'        => $hwid,
            'device_name' => $deviceName,
            'os_info'     => $osInfo,
        ];

        $endpoints = [
            'https://panel.dgtlnetsolution.com/api/v1/desktop-license/activate',
            'https://gateway.dgtlnetsolution.com/api/v1/desktop-license/activate',
            'https://digitalnet.dgtlnetsolution.com/api/v1/desktop-license/activate',
        ];

        $response = null;
        $lastErr = '';

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mikhmon-Desktop-Client/1.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw && ($httpCode === 200 || $httpCode === 403 || $httpCode === 404 || $httpCode === 422)) {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $response = $json;
                    break;
                }
            } else if ($err) {
                $lastErr = $err;
            }
        }

        if (!$response) {
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke Cloud License Server. Pastikan koneksi internet aktif. (' . ($lastErr ?: 'Timeout') . ')',
            ];
        }

        if (empty($response['success'])) {
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Aktivasi lisensi gagal.',
            ];
        }

        $data = $response['data'] ?? [];
        $exp = $data['expires_at'] ?? '';
        $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop Standalone';

        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'ACTIVE');\n"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");\n"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");\n"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');\n"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");\n"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");\n"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

        $configFile = __DIR__ . '/../config/license.php';
        @file_put_contents($configFile, $licensePhp);

        return [
            'success'      => true,
            'message'      => 'Lisensi Desktop NODERA berhasil diaktivasi!',
            'license_key'  => $licenseKey,
            'expires_at'   => $expFormatted ?: 'LIFETIME',
            'product_name' => $prodName,
            'data'         => $data,
        ];
    }
}

if (!function_exists('mikhmon_activate_trial_license')) {
    function mikhmon_activate_trial_license(): array {
        $hwid = mikhmon_get_hwid();
        $deviceName = getenv('COMPUTERNAME') ?: (gethostname() ?: 'Mikhmon Desktop Client');
        $osInfo = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

        $payload = [
            'hwid'        => $hwid,
            'device_name' => $deviceName,
            'os_info'     => $osInfo,
        ];

        $endpoints = [
            'https://panel.dgtlnetsolution.com/api/v1/desktop-license/trial',
            'https://gateway.dgtlnetsolution.com/api/v1/desktop-license/trial',
            'https://digitalnet.dgtlnetsolution.com/api/v1/desktop-license/trial',
        ];

        $response = null;
        $lastErr = '';

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mikhmon-Desktop-Client/1.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw && ($httpCode === 200 || $httpCode === 403 || $httpCode === 404 || $httpCode === 422)) {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $response = $json;
                    break;
                }
            } else if ($err) {
                $lastErr = $err;
            }
        }

        // If online API returns success
        if ($response && !empty($response['success'])) {
            $data = $response['data'] ?? [];
            $licenseKey = $data['license_key'] ?? ('NDR-TRL-' . strtoupper(substr(md5($hwid . 'NODERA_TRIAL_2026'), 0, 12)));
            $exp = $data['expires_at'] ?? date('Y-m-d H:i:s', time() + (7 * 86400));
            $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
            $prodName = $data['product_name'] ?? 'Mikhmon Desktop (Trial 7 Hari)';

            $licensePhp = "<?php\n"
                . "/**\n"
                . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
                . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).\n"
                . " */\n"
                . "define('MIKHMON_MODE', 'DESKTOP');\n"
                . "define('MIKHMON_STATUS', 'ACTIVE');\n"
                . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");\n"
                . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
                . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");\n"
                . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');\n"
                . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");\n"
                . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");\n"
                . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

            $configFile = __DIR__ . '/../config/license.php';
            @file_put_contents($configFile, $licensePhp);

            return [
                'success'      => true,
                'message'      => 'Masa percobaan 7 hari gratis berhasil diaktifkan!',
                'license_key'  => $licenseKey,
                'expires_at'   => $expFormatted,
                'product_name' => $prodName,
                'data'         => $data,
            ];
        }

        // If online API returns trial already used
        if ($response && isset($response['code']) && $response['code'] === 'TRIAL_ALREADY_USED') {
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Perangkat ini sudah pernah menggunakan masa percobaan 7 hari gratis.',
            ];
        }

        // Offline Fallback for first launch
        $expFormatted = date('Y-m-d H:i:s', time() + (7 * 86400));
        $licenseKey = 'NDR-TRL-' . strtoupper(substr(md5($hwid . 'NODERA_TRIAL_2026'), 0, 12));
        $prodName = 'Mikhmon Desktop (Trial 7 Hari)';

        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Free 7-Day Trial (First Launch Offline Guard)\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'ACTIVE');\n"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");\n"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");\n"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');\n"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");\n"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");\n"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

        $configFile = __DIR__ . '/../config/license.php';
        @file_put_contents($configFile, $licensePhp);

        return [
            'success'      => true,
            'message'      => 'Masa percobaan 7 hari gratis berhasil diaktifkan!',
            'license_key'  => $licenseKey,
            'expires_at'   => $expFormatted,
            'product_name' => $prodName,
        ];
    }
}
