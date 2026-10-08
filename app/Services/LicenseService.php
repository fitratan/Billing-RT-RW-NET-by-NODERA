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
                    return [
                        'valid' => true,
                        'status' => $resp->json('status', 'ACTIVE'),
                        'tier' => strtoupper($resp->json('package_type', 'PRO')),
                        'is_pro' => true,
                        'client_name' => $resp->json('client_name', 'NODERA Pro ISP'),
                        'expires_at' => $resp->json('expires_at'),
                    ];
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
                return [
                    'valid' => true,
                    'status' => 'ACTIVE',
                    'tier' => 'PRO',
                    'is_pro' => true,
                    'client_name' => 'NODERA License (Offline Grace)',
                    'expires_at' => null,
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
     * Check if tenant/system can add more customers under current license.
     */
    public static function canAddCustomer(int $additionalCount = 1): bool
    {
        if (self::isPro()) {
            return true;
        }

        try {
            $currentCount = Customer::count();
            return ($currentCount + $additionalCount) <= self::MAX_FREE_CUSTOMERS;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Check if tenant/system can add more staff (Technician / Collector).
     */
    public static function canAddStaff(int $additionalCount = 1): bool
    {
        if (self::isPro()) {
            return true;
        }

        try {
            $staffCount = User::whereIn('role', ['technician', 'collector', 'admin_staff'])->count();
            return ($staffCount + $additionalCount) <= self::MAX_FREE_STAFF;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Full summary payload for UI & Inertia.
     */
    public static function getLicensePayload(): array
    {
        $info = self::verifyLicense();
        $customerCount = 0;
        $staffCount = 0;
        try {
            $customerCount = Customer::count();
            $staffCount = User::whereIn('role', ['technician', 'collector', 'admin_staff'])->count();
        } catch (\Throwable $e) {
        }

        $key = self::getLicenseKey();
        $maskedKey = $key ? (substr($key, 0, 4) . '••••' . substr($key, -4)) : '';

        return [
            'is_standalone' => self::isStandalone(),
            'is_pro' => self::isPro(),
            'tier' => $info['tier'] ?? (self::isPro() ? 'PRO' : 'COMMUNITY'),
            'status' => $info['status'] ?? 'COMMUNITY',
            'client_name' => $info['client_name'] ?? 'Community Edition',
            'masked_key' => $maskedKey,
            'expires_at' => $info['expires_at'] ?? null,
            'customer_count' => $customerCount,
            'max_free_customers' => self::MAX_FREE_CUSTOMERS,
            'staff_count' => $staffCount,
            'max_free_staff' => self::MAX_FREE_STAFF,
            'customer_quota_used_pct' => min(100, (int) round(($customerCount / self::MAX_FREE_CUSTOMERS) * 100)),
        ];
    }
}
