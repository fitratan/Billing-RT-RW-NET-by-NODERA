<?php

namespace App\Services;

use App\Http\Controllers\SetupWizardController;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    public const MAX_FREE_CUSTOMERS = 35;
    public const MAX_FREE_STAFF = 1;
    public const TRIAL_DAYS = 7;
    public const GRACE_DAYS = 7;

    /**
     * Check if currently running in Standalone Mode.
     * Uses multi-factor detection (env, config, batch launcher, db connection).
     */
    public static function isStandalone(): bool
    {
        if (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false)) {
            return true;
        }

        // Automatic standalone signature detection (prevent bypass via .env edit)
        if (file_exists(base_path('START-NODERA.bat')) || file_exists(base_path('NODERA-SILENT.vbs'))) {
            return true;
        }

        if (config('database.default') === 'sqlite' || env('DB_CONNECTION') === 'sqlite') {
            return true;
        }

        return false;
    }

    /**
     * Get the active license key.
     */
    public static function getLicenseKey(): string
    {
        $key = env('NODERA_LICENSE_KEY') ?: env('LICENSE_KEY');
        if (empty($key)) {
            try {
                $key = Setting::withoutGlobalScopes()
                    ->whereIn('key', ['NODERA_LICENSE_KEY', 'LICENSE_KEY'])
                    ->whereNull('tenant_id')
                    ->value('value');
            } catch (\Throwable $e) {
            }
        }
        return trim((string) $key);
    }

    /**
     * Generate / retrieve persistent Hardware ID.
     */
    public static function getHardwareId(): string
    {
        $envHwid = env('NODERA_DESKTOP_HWID');
        if (!empty($envHwid)) {
            return trim($envHwid);
        }

        $hwidFile = storage_path('app/.hwid');
        if (file_exists($hwidFile)) {
            $saved = trim((string) @file_get_contents($hwidFile));
            if (!empty($saved) && str_starts_with($saved, 'NDR-HWID-')) {
                return $saved;
            }
        }

        // Check Linux machine-id
        $id = @file_get_contents('/etc/machine-id');
        if (empty($id)) {
            $id = @file_get_contents('/var/lib/dbus/machine-id');
        }

        if (empty($id)) {
            $raw = php_uname('s') . '-' . php_uname('n') . '-' . php_uname('m') . '-' . (getenv('COMPUTERNAME') ?: '') . '-' . (getenv('USER') ?: get_current_user());
            if (function_exists('disk_total_space')) {
                $raw .= '-' . (int) @disk_total_space(base_path());
            }
            $hash = strtoupper(hash('sha256', $raw));
            $hwid = 'NDR-HWID-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
        } else {
            $hash = strtoupper(hash('sha256', trim($id)));
            $hwid = 'NDR-HWID-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
        }

        @file_put_contents($hwidFile, $hwid);
        return $hwid;
    }

    /**
     * Secret key used for local HMAC cryptographic verification.
     */
    private static function getOfflineSecret(): string
    {
        $appKey = (string) (config('app.key') ?: 'NODERA_STANDALONE_FALLBACK_KEY_2026');
        return hash('sha256', $appKey . ':' . self::getHardwareId() . ':NODERA_OFFLINE_SECRET_2026');
    }

    /**
     * Anti-clock rollback guard: prevents time manipulation.
     */
    public static function checkClockGuard(): bool
    {
        $guardFile = storage_path('app/.clock_guard');
        $now = time();

        if (file_exists($guardFile)) {
            $last = (int) @file_get_contents($guardFile);
            if ($last > 0 && $now < ($last - 300)) {
                // System clock rolled back by more than 5 minutes!
                Log::warning("[LicenseService] Clock rollback tampering detected: current {$now} < last {$last}");
                return false;
            }
        }

        @file_put_contents($guardFile, (string) $now);
        return true;
    }

    /**
     * Track and verify Initial 7-Day Free Trial.
     */
    public static function checkTrialStatus(): array
    {
        $trialFile = storage_path('app/.trial_state');
        $hwid = self::getHardwareId();
        $secret = self::getOfflineSecret();

        if (!file_exists($trialFile)) {
            $trialData = [
                'started_at'  => time(),
                'hardware_id' => $hwid,
            ];
            $payload = json_encode($trialData);
            $signature = hash_hmac('sha256', $payload, $secret);
            @file_put_contents($trialFile, json_encode(['payload' => $payload, 'signature' => $signature]));

            return [
                'valid'               => true,
                'status'              => 'TRIAL',
                'tier'                => 'TRIAL',
                'is_pro'              => true,
                'client_name'         => 'Free Trial 7 Hari (Auto-Active)',
                'trial_days_remaining'=> self::TRIAL_DAYS,
                'expires_at'          => date('Y-m-d H:i:s', time() + (self::TRIAL_DAYS * 86400)),
            ];
        }

        $raw = @file_get_contents($trialFile);
        $saved = json_decode($raw, true);
        if (!is_array($saved) || empty($saved['payload']) || empty($saved['signature'])) {
            return [
                'valid'   => false,
                'status'  => 'TRIAL_CORRUPTED',
                'tier'    => 'COMMUNITY',
                'is_pro'  => false,
                'message' => 'Integritas data trial rusak. Silakan aktivasi lisensi resmi.',
            ];
        }

        $calculatedSig = hash_hmac('sha256', $saved['payload'], $secret);
        if (!hash_equals($calculatedSig, $saved['signature'])) {
            Log::warning("[LicenseService] Trial signature tampering detected!");
            return [
                'valid'   => false,
                'status'  => 'TRIAL_TAMPERED',
                'tier'    => 'COMMUNITY',
                'is_pro'  => false,
                'message' => 'Manipulasi trial terdeteksi. Silakan aktivasi lisensi resmi.',
            ];
        }

        $data = json_decode($saved['payload'], true);
        $startedAt = (int) ($data['started_at'] ?? 0);
        $elapsedDays = (time() - $startedAt) / 86400;

        if ($elapsedDays > self::TRIAL_DAYS) {
            return [
                'valid'   => false,
                'status'  => 'TRIAL_EXPIRED',
                'tier'    => 'COMMUNITY',
                'is_pro'  => false,
                'message' => 'Masa uji coba 7 hari telah berakhir. Silakan masukkan License Key resmi.',
            ];
        }

        $remainingDays = max(0, (int) ceil(self::TRIAL_DAYS - $elapsedDays));
        return [
            'valid'               => true,
            'status'              => 'TRIAL',
            'tier'                => 'TRIAL',
            'is_pro'              => true,
            'client_name'         => 'Free Trial 7 Hari (' . $remainingDays . ' hari tersisa)',
            'trial_days_remaining'=> $remainingDays,
            'expires_at'          => date('Y-m-d H:i:s', $startedAt + (self::TRIAL_DAYS * 86400)),
        ];
    }

    /**
     * Verify license status against Cloud License Server with offline fallback,
     * HMAC signature validation, and anti-clock rollback guard.
     */
    public static function verifyLicense(bool $forceRefresh = false): array
    {
        if (!self::isStandalone()) {
            return [
                'valid'       => true,
                'status'      => 'ACTIVE',
                'tier'        => 'ENTERPRISE',
                'is_pro'      => true,
                'client_name' => 'NODERA Cloud ISP',
                'expires_at'  => null,
            ];
        }

        // Anti-clock rollback verification
        if (!self::checkClockGuard()) {
            return [
                'valid'   => false,
                'status'  => 'CLOCK_TAMPER',
                'tier'    => 'COMMUNITY',
                'is_pro'  => false,
                'message' => 'Manipulasi waktu sistem terdeteksi. Silakan sinkronkan jam komputer Anda ke waktu internet.',
            ];
        }

        $licenseKey = self::getLicenseKey();

        // If no license key is installed, verify Free 7-Day Trial status
        if (empty($licenseKey)) {
            return self::checkTrialStatus();
        }

        $cacheKey = 'nodera_standalone_license_status_' . md5($licenseKey);
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 900, function () use ($licenseKey) {
            $lastOkFile = storage_path('app/nodera_license_last_ok.json');
            $hardwareId = self::getHardwareId();
            $secret = self::getOfflineSecret();

            try {
                $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');
                $domain = request()->getHost() ?? env('APP_URL', 'localhost');
                $caBundle = resource_path('certs/cacert.pem');

                $http = Http::timeout(6)->asJson();
                if (file_exists($caBundle)) {
                    $http->withOptions(['verify' => $caBundle]);
                }

                $resp = $http->post("{$licenseServer}/api/v1/license/verify", [
                    'license_key' => $licenseKey,
                    'domain'      => $domain,
                    'hardware_id' => $hardwareId,
                ]);

                if ($resp->successful() && $resp->json('valid')) {
                    $result = [
                        'valid'       => true,
                        'status'      => $resp->json('status', 'ACTIVE'),
                        'tier'        => strtoupper($resp->json('package_type', 'PRO')),
                        'is_pro'      => true,
                        'client_name' => $resp->json('client_name', 'NODERA Pro ISP'),
                        'expires_at'  => $resp->json('expires_at'),
                    ];

                    // Save verified license with HMAC signature for secure offline validation
                    try {
                        $payload = json_encode([
                            'license_key' => $licenseKey,
                            'verified_at' => time(),
                            'hardware_id' => $hardwareId,
                            'data'        => $result,
                        ]);
                        $sig = hash_hmac('sha256', $payload, $secret);
                        @file_put_contents($lastOkFile, json_encode([
                            'payload'   => $payload,
                            'signature' => $sig,
                        ]));
                    } catch (\Throwable $e) {
                    }

                    return $result;
                }

                $status = $resp->json('status', 'INVALID');
                return [
                    'valid'   => false,
                    'status'  => $status,
                    'tier'    => 'COMMUNITY',
                    'is_pro'  => false,
                    'message' => $resp->json('message', 'Lisensi tidak valid atau telah dibekukan.'),
                ];
            } catch (\Throwable $e) {
                Log::warning("License verification offline fallback: " . $e->getMessage());

                // Cryptographically validated offline grace period (7 days)
                $graceDays = (int) env('NODERA_LICENSE_GRACE_DAYS', self::GRACE_DAYS);
                if (file_exists($lastOkFile)) {
                    $rawSaved = @file_get_contents($lastOkFile);
                    $saved = json_decode($rawSaved, true);

                    if (is_array($saved) && !empty($saved['payload']) && !empty($saved['signature'])) {
                        $expectedSig = hash_hmac('sha256', $saved['payload'], $secret);
                        if (hash_equals($expectedSig, $saved['signature'])) {
                            $decodedPayload = json_decode($saved['payload'], true);
                            if (is_array($decodedPayload) && ($decodedPayload['license_key'] ?? '') === $licenseKey && ($decodedPayload['hardware_id'] ?? '') === $hardwareId) {
                                $verifiedAt = (int) ($decodedPayload['verified_at'] ?? 0);
                                $elapsedDays = (time() - $verifiedAt) / 86400;
                                if ($elapsedDays <= $graceDays && !empty($decodedPayload['data'])) {
                                    $cachedData = $decodedPayload['data'];
                                    $cachedData['offline_grace'] = true;
                                    $cachedData['grace_remaining_days'] = max(0, (int) ceil($graceDays - $elapsedDays));
                                    return $cachedData;
                                }
                            }
                        } else {
                            Log::warning("[LicenseService] Offline cache signature tampering detected!");
                        }
                    }
                }

                return [
                    'valid'       => false,
                    'status'      => 'OFFLINE_EXPIRED',
                    'tier'        => 'COMMUNITY',
                    'is_pro'      => false,
                    'client_name' => 'Community Edition (Offline)',
                    'expires_at'  => null,
                    'message'     => 'Gagal terhubung ke server lisensi dan masa tenggang offline 7 hari telah berakhir. Silakan hubungkan komputer ke internet.',
                ];
            }
        });
    }

    /**
     * Check if currently running with Pro / Enterprise capabilities.
     */
    public static function isPro(): bool
    {
        $status = self::verifyLicense();
        return (bool) ($status['is_pro'] ?? false);
    }

    /**
     * Get maximum allowed customers under current license.
     */
    public static function maxCustomers(): int
    {
        if (self::isPro()) {
            return 999999;
        }
        return self::MAX_FREE_CUSTOMERS;
    }

    /**
     * Check whether current customer count has reached or exceeded the license limit.
     */
    public static function canCreateCustomer(): bool
    {
        if (!self::isStandalone() || self::isPro()) {
            return true;
        }

        try {
            $total = Customer::withoutGlobalScopes()->count();
            return $total < self::MAX_FREE_CUSTOMERS;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Check whether user limit has been exceeded.
     */
    public static function canCreateUser(): bool
    {
        if (!self::isStandalone() || self::isPro()) {
            return true;
        }

        try {
            $total = User::withoutGlobalScopes()->where('role', '!=', 'superadmin')->count();
            return $total < self::MAX_FREE_STAFF;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Alias for canCreateCustomer.
     */
    public static function canAddCustomer(): bool
    {
        return self::canCreateCustomer();
    }

    /**
     * Alias for canCreateUser / staff.
     */
    public static function canAddStaff(): bool
    {
        return self::canCreateUser();
    }

    /**
     * Get license payload for Inertia shares.
     */
    public static function getLicensePayload(): array
    {
        $v = self::verifyLicense();
        return [
            'valid'         => $v['valid'] ?? true,
            'status'        => $v['status'] ?? 'ACTIVE',
            'tier'          => $v['tier'] ?? 'ENTERPRISE',
            'is_pro'        => $v['is_pro'] ?? true,
            'client_name'   => $v['client_name'] ?? 'NODERA Cloud',
            'expires_at'    => $v['expires_at'] ?? null,
            'max_customers' => self::maxCustomers(),
        ];
    }
}
