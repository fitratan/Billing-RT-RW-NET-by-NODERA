<?php
/**
 * LICENSE LOADER — MIKHMON by NODERA (panel.dgtlnetsolution.com)
 * Handles desktop HWID license verification, expiration date checking,
 * and multi-tenant subscription gating.
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
        $t = mikhmon_expiry_ts();
        if ($t === null) {
            return 'LIFETIME (Aktif Selamanya)';
        }
        $m = (int) date('n', $t);
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        return date('d', $t) . ' ' . ($months[$m] ?? date('M', $t)) . ' ' . date('Y', $t);
    }
}

if (!function_exists('mikhmon_remaining_days')) {
    function mikhmon_remaining_days(): int {
        if (mikhmon_is_desktop_mode() && (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)))) {
            return 0;
        }
        $t = mikhmon_expiry_ts();
        if ($t === null) return 999;
        return (int) ceil(($t + 86399 - time()) / 86400);
    }
}

if (!function_exists('mikhmon_activate_desktop_license')) {
    function mikhmon_activate_desktop_license(string $licenseKey): array {
        $licenseKey = trim($licenseKey);
        if (empty($licenseKey)) {
            return ['success' => false, 'message' => 'License Key tidak boleh kosong.'];
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
                'message' => 'Gagal terhubung ke Cloud License Server. Pastikan perangkat terhubung ke internet. (' . ($lastErr ?: 'Timeout') . ')',
            ];
        }

        if (empty($response['success'])) {
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Aktivasi lisensi gagal. Periksa kembali License Key Anda.',
            ];
        }

        // Write activated license file
        $data = $response['data'] ?? [];
        $exp = $data['expires_at'] ?? '';
        $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop Standalone';

        $licensePhp = "<?php
"
            . "/**
"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
"
            . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).
"
            . " */
"
            . "define('MIKHMON_MODE', 'DESKTOP');
"
            . "define('MIKHMON_STATUS', 'ACTIVE');
"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");
"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");
"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");
"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');
"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");
"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");
"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');
";

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
                'message' => 'Gagal terhubung ke Cloud License Server. Pastikan perangkat terhubung ke internet. (' . ($lastErr ?: 'Timeout') . ')',
            ];
        }

        if (empty($response['success'])) {
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Aktivasi masa percobaan gagal.',
            ];
        }

        // Write activated trial license file
        $data = $response['data'] ?? [];
        $licenseKey = $data['license_key'] ?? '';
        $exp = $data['expires_at'] ?? '';
        $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop (Trial 7 Hari)';

        $licensePhp = "<?php
"
            . "/**
"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
"
            . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).
"
            . " */
"
            . "define('MIKHMON_MODE', 'DESKTOP');
"
            . "define('MIKHMON_STATUS', 'ACTIVE');
"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");
"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");
"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");
"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');
"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");
"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");
"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');
";

        $configFile = __DIR__ . '/../config/license.php';
        @file_put_contents($configFile, $licensePhp);

        return [
            'success'      => true,
            'message'      => 'Selamat! Masa percobaan 7 hari gratis berhasil diaktifkan.',
            'license_key'  => $licenseKey,
            'expires_at'   => $expFormatted ?: '7 Hari',
            'product_name' => $prodName,
            'data'         => $data,
        ];
    }
}

if (!function_exists('mikhmon_save_desktop_license_data')) {
    function mikhmon_save_desktop_license_data(array $data): array {
        if (empty($data) || empty($data['license_key'])) {
            return ['success' => false, 'message' => 'Data lisensi tidak valid.'];
        }
        $licenseKey = trim($data['license_key']);
        $hwid = trim($data['hwid'] ?? mikhmon_get_hwid());
        $exp = $data['expires_at'] ?? '';
        $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop Standalone';

        $licensePhp = "<?php
"
            . "/**
"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
"
            . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).
"
            . " */
"
            . "define('MIKHMON_MODE', 'DESKTOP');
"
            . "define('MIKHMON_STATUS', 'ACTIVE');
"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");
"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");
"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");
"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');
"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");
"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");
"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');
";

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

if (!function_exists('mikhmon_reset_local_license')) {
    function mikhmon_reset_local_license(): bool {
        $licensePhp = "<?php
"
            . "/**
"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
"
            . " * Status: UNLICENSED / RESET
"
            . " */
"
            . "define('MIKHMON_MODE', 'DESKTOP');
"
            . "define('MIKHMON_STATUS', 'UNLICENSED');
"
            . "define('MIKHMON_LICENSE_KEY', '');
"
            . "define('MIKHMON_HWID', '');
"
            . "define('MIKHMON_EXPIRY', '0000-00-00');
"
            . "define('MIKHMON_BRAND', 'by NODERA (panel.dgtlnetsolution.com)');
"
            . "define('MIKHMON_PRODUCT_NAME', 'Mikhmon Desktop Standalone');
"
            . "define('MIKHMON_ACTIVATED_AT', '');
"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');
";

        $configFile = __DIR__ . '/../config/license.php';
        @file_put_contents($configFile, $licensePhp);
        return true;
    }
}

if (!function_exists('mikhmon_render_desktop_heartbeat_script')) {
    function mikhmon_render_desktop_heartbeat_script(): void {
        if (!mikhmon_is_desktop_mode()) return;
        $key = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
        $hwid = defined('MIKHMON_HWID') ? MIKHMON_HWID : '';
        if (empty($key) || empty($hwid)) return;
        ?>
        <script>
        (function() {
          var _ndrKey = <?= json_encode($key) ?>;
          var _ndrHwid = <?= json_encode($hwid) ?>;
          if (!_ndrKey || !_ndrHwid) return;

          function _ndrCheckLicenseHeartbeat() {
            fetch('https://panel.dgtlnetsolution.com/api/v1/desktop-license/verify', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
              body: JSON.stringify({ license_key: _ndrKey, hwid: _ndrHwid })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
              if (data && data.success === false && (data.code === 'HWID_MISMATCH' || data.code === 'HWID_UNBOUND' || data.code === 'INVALID_KEY' || data.code === 'LICENSE_SUSPENDED' || data.code === 'LICENSE_EXPIRED')) {
                var fd = new FormData();
                fd.append('action', 'ajax_revoke_local_license');
                fetch('./admin.php', { method: 'POST', body: fd })
                .then(function() {
                  alert("PEMBERITAHUAN LISENSI NODERA:
" + (data.message || "Lisensi perangkat ini telah direset atau dipindahkan dari Cloud Panel."));
                  window.location.href = './admin.php?id=login';
                });
              }
            })
            .catch(function() {});
          }

          if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(_ndrCheckLicenseHeartbeat, 800);
          } else {
            window.addEventListener('DOMContentLoaded', function() {
              setTimeout(_ndrCheckLicenseHeartbeat, 800);
            });
          }
          setInterval(_ndrCheckLicenseHeartbeat, 60000);
        })();
        </script>
        <?php
    }
}
