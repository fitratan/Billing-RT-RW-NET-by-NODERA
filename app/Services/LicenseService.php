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

    /**
     * Check if currently running in Standalone Mode.
     */
    public static function isStandalone(): bool
    {
        return (bool) (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false));
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
     * Verify license status against Cloud License Server.
     */
    public static function verifyLicense(bool $forceRefresh = false): array
    {
        if (!self::isStandalone()) {
            // SaaS Cloud mode is always PRO/ENTERPRISE
            return [
                'valid' => true,
                'status' => 'ACTIVE',
                'tier' => 'ENTERPRISE',
                'is_pro' => true,
                'client_name' => 'NODERA Cloud ISP',
                'expires_at' => null,
            ];
        }

        $licenseKey = self::getLicenseKey();
        if (empty($licenseKey)) {
            return [
                'valid' => true,
                'status' => 'COMMUNITY',
                'tier' => 'COMMUNITY',
                'is_pro' => false,
                'client_name' => 'Community Edition',
                'expires_at' => null,
                'message' => 'Versi Komunitas Gratis (Maksimal ' . self::MAX_FREE_CUSTOMERS . ' Pelanggan).',
            ];
        }

        $cacheKey = 'nodera_standalone_license_status_' . md5($licenseKey);
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 900, function () use ($licenseKey) {
            $lastOkFile = storage_path('app/nodera_license_last_ok.json');

            try {
                $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');
                $domain = request()->getHost() ?? env('APP_URL', 'localhost');
                $hardwareId = class_exists(SetupWizardController::class) ? SetupWizardController::getHardwareId() : 'STANDALONE_HWID';

                $resp = Http::timeout(5)->asJson()->post("{$licenseServer}/api/v1/license/verify", [
                    'license_key' => $licenseKey,
                    'domain' => $domain,
                    'hardware_id' => $hardwareId,
                ]);

                if ($resp->successful() && $resp->json('valid')) {
                    $result = [
                        'valid' => true,
                        'status' => $resp->json('status', 'ACTIVE'),
                        'tier' => strtoupper($resp->json('package_type', 'PRO')),
                        'is_pro' => true,
                        'client_name' => $resp->json('client_name', 'NODERA Pro ISP'),
                        'expires_at' => $resp->json('expires_at'),
                    ];

                    // Save verified license timestamp to local storage for bounded offline grace
                    try {
                        @file_put_contents($lastOkFile, json_encode([
                            'license_key' => $licenseKey,
                            'verified_at' => time(),
                            'data' => $result,
                        ]));
                    } catch (\Throwable $e) {
                    }

                    return $result;
                }

                $status = $resp->json('status', 'INVALID');
                return [
                    'valid' => false,
                    'status' => $status,
                    'tier' => 'COMMUNITY',
                    'is_pro' => false,
                    'message' => $resp->json('message', 'Lisensi tidak valid atau telah dibekukan. Kembali ke Community Edition.'),
                ];
            } catch (\Throwable $e) {
                Log::warning("License verification offline fallback: " . $e->getMessage());

                // Bounded Offline Grace Period (Default: 7 days since last verified online)
                $graceDays = (int) env('NODERA_LICENSE_GRACE_DAYS', 7);
                if (file_exists($lastOkFile)) {
                    $rawSaved = @file_get_contents($lastOkFile);
                    $saved = json_decode($rawSaved, true);
                    if (is_array($saved) && ($saved['license_key'] ?? '') === $licenseKey) {
                        $verifiedAt = (int) ($saved['verified_at'] ?? 0);
                        $elapsedDays = (time() - $verifiedAt) / 86400;
                        if ($elapsedDays <= $graceDays && !empty($saved['data'])) {
                            $cachedData = $saved['data'];
                            $cachedData['offline_grace'] = true;
                            $cachedData['grace_remaining_days'] = max(0, (int) ceil($graceDays - $elapsedDays));
                            return $cachedData;
                        }
                    }
                }

                return [
                    'valid' => false,
                    'status' => 'OFFLINE_UNVERIFIED',
                    'tier' => 'COMMUNITY',
                    'is_pro' => false,
                    'client_name' => 'Community Edition (Offline)',
                    'expires_at' => null,
                    'message' => 'Gagal terhubung ke server lisensi dan masa tenggang offline berakhir. Berjalan dalam mode Komunitas (Maksimal ' . self::MAX_FREE_CUSTOMERS . ' Pelanggan).',
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
}
