<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $guarded = ['id'];

    /**
     * Cache in-memory per-request untuk menghindari query berulang
     */
    protected static array $runtimeCache = [];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantScope::currentTenantId();
            if ($tenantId !== null) {
                $builder->where('tenant_id', $tenantId);
            }
        });

        static::saved(function (Setting $setting) {
            $tId = $setting->tenant_id;
            Cache::forget("inertia_tenant_branding_" . ($tId ?? 'global'));
            Cache::forget("inertia_tenant_branding_global");
            Cache::forget("role_perms_matrix_" . ($tId ?? 'all'));
            Cache::forget("role_perms_matrix_all");
            Cache::forget('setting_tg_community_url');
        });

        static::deleted(function (Setting $setting) {
            $tId = $setting->tenant_id;
            Cache::forget("inertia_tenant_branding_" . ($tId ?? 'global'));
            Cache::forget("inertia_tenant_branding_global");
            Cache::forget("role_perms_matrix_" . ($tId ?? 'all'));
            Cache::forget("role_perms_matrix_all");
            Cache::forget('setting_tg_community_url');
        });
    }

    /**
     * Cek apakah sebuah key setting adalah key integrasi/sensitif yang terisolasi per-tenant
     * dan TIDAK BOLEH fallback ke global Superadmin jika tenant belum mengonfigurasinya.
     */
    public static function isTenantIsolatedKey(string $key): bool
    {
        $upperKey = strtoupper($key);
        $isolatedPrefixes = [
            'WHATSAPP_',
            'FONNTE_',
            'WA_',
            'TELEGRAM_',
            'MIDTRANS_',
            'TRIPAY_',
            'WIJAYAPAY_',
            'XENDIT_',
            'DUITKU_',
            'PAKASIR_',
            'PAYDISINI_',
            'GENIEACS_',
            'TR069_',
        ];

        foreach ($isolatedPrefixes as $prefix) {
            if (str_starts_with($upperKey, $prefix)) {
                return true;
            }
        }

        $isolatedExact = [
            'IS_WHATSAPP_ACTIVE',
            'IS_TELEGRAM_ACTIVE',
            'IS_GENIEACS_ACTIVE',
        ];

        return in_array($upperKey, $isolatedExact, true);
    }

    /**
     * Ambil setting tenant/user saat ini (dengan fallback ke global superadmin).
     */
    public static function getValue(string $key, $default = null)
    {
        $tenantId = TenantScope::currentTenantId() ?? 0;
        $runtimeKey = "{$tenantId}:{$key}";

        if (array_key_exists($runtimeKey, static::$runtimeCache)) {
            return static::$runtimeCache[$runtimeKey] ?? $default;
        }

        try {
            $val = Cache::remember("setting_val_{$runtimeKey}", 300, function () use ($key, $tenantId, $default) {
                if ($tenantId) {
                    $setting = static::withoutGlobalScopes()
                        ->where('key', $key)
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($setting && $setting->value !== null && $setting->value !== '') {
                        return $setting->value;
                    }

                    // 🔒 STRICT TENANT ISOLATION:
                    // Jika key integrasi/sensitif dan tenant belum mengonfigurasinya,
                    // JANGAN PERNAH fallback ke global Superadmin setting (tenant_id IS NULL).
                    if (static::isTenantIsolatedKey($key)) {
                        return $default;
                    }
                }

                // Fallback / Superadmin platform context: read global setting (tenant_id IS NULL)
                $globalSetting = static::withoutGlobalScopes()
                    ->where('key', $key)
                    ->whereNull('tenant_id')
                    ->first();

                return $globalSetting ? $globalSetting->value : $default;
            });

            static::$runtimeCache[$runtimeKey] = $val;
            return $val ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Alias untuk getValue
     */
    public static function get(string $key, $default = null)
    {
        return static::getValue($key, $default);
    }

    /**
     * Set setting tenant/user saat ini.
     */
    public static function setValue(string $key, $value, $tenantId = null): self
    {
        if ($tenantId === null) {
            $tenantId = TenantScope::currentTenantId();
        }

        $setting = static::withoutGlobalScopes()->updateOrCreate(
            ['key' => $key, 'tenant_id' => $tenantId],
            ['value' => $value]
        );

        $tid = $tenantId ?? 0;
        unset(static::$runtimeCache["{$tid}:{$key}"]);
        unset(static::$runtimeCache["{$tid}_{$key}"]);
        Cache::forget("setting_val_{$tid}:{$key}");
        Cache::forget("setting_val_{$tid}_{$key}");
        Cache::forget("setting:{$tid}:{$key}");
        Cache::forget("setting_api_{$tid}:{$key}");
        Cache::forget("setting_api_0:{$key}");
        Cache::forget('company_global_info');

        return $setting;
    }

    /**
     * Alias untuk setValue
     */
    public static function set(string $key, $value, $tenantId = null): self
    {
        return static::setValue($key, $value, $tenantId);
    }

    /**
     * Ambil setting API key/token untuk integrasi
     */
    public static function apiValue(string $key, $default = null)
    {
        $tenantId = TenantScope::currentTenantId() ?? 0;
        $runtimeKey = "{$tenantId}:{$key}";

        if (array_key_exists($runtimeKey, static::$runtimeCache)) {
            return static::$runtimeCache[$runtimeKey] ?? $default;
        }

        try {
            $val = Cache::remember("setting_api_{$runtimeKey}", 300, function () use ($key, $tenantId, $default) {
                if ($tenantId && session('admin_role') !== 'superadmin') {
                    $setting = static::withoutGlobalScopes()
                        ->where('key', $key)
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($setting && $setting->value !== null && $setting->value !== '') {
                        return $setting->value;
                    }

                    // 🔒 STRICT TENANT ISOLATION:
                    // Jika key integrasi/sensitif dan tenant belum mengonfigurasinya,
                    // JANGAN PERNAH fallback ke global Superadmin setting atau env().
                    if (static::isTenantIsolatedKey($key)) {
                        return $default;
                    }
                }

                // Superadmin global setting (whereNull tenant_id) fallback
                $globalSetting = static::withoutGlobalScopes()
                    ->where('key', $key)
                    ->whereNull('tenant_id')
                    ->first();

                if ($globalSetting && $globalSetting->value !== null && $globalSetting->value !== '') {
                    return $globalSetting->value;
                }

                if (empty($tenantId) || session('admin_role') === 'superadmin') {
                    return env($key, $default);
                }

                return $default;
            });

            static::$runtimeCache[$runtimeKey] = $val;
            return $val ?? $default;
        } catch (\Throwable $e) {
            return env($key, $default);
        }
    }

    /**
     * Dapatkan nomor WhatsApp / HP Superadmin dari profil akun superadmin terlebih dahulu
     */
    public static function getSuperadminPhone(): string
    {
        try {
            $superadmin = \App\Models\User::withoutGlobalScopes()
                ->where(function ($q) {
                    $q->where('role', 'superadmin')
                      ->orWhere('role', 'SUPERADMIN')
                      ->orWhereNull('tenant_id');
                })
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('id', 'asc')
                ->first();

            if ($superadmin && !empty($superadmin->phone)) {
                return $superadmin->phone;
            }

            $val = static::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->whereIn('key', ['COMPANY_PHONE', 'ADMIN_WA', 'COMPANY_WA', 'WHATSAPP_NUMBER'])
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->value('value');

            return $val ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Info perusahaan GLOBAL (superadmin) — satu sumber nama/WA/email
     * untuk SEMUA landing & halaman publik.
     */
    public static function company(): array
    {
        try {
            $superadminPhone = static::getSuperadminPhone();
            $global = static::withoutGlobalScopes()->whereNull('tenant_id')->pluck('value', 'key');

            $name = $global['COMPANY_NAME'] ?? 'DN Solution';
            $phone = !empty($superadminPhone) ? $superadminPhone : ($global['COMPANY_PHONE'] ?? '');
            $email = $global['COMPANY_EMAIL'] ?? '';

            if (empty($email)) {
                $superadmin = \App\Models\User::withoutGlobalScopes()
                    ->where(function ($q) {
                        $q->where('role', 'superadmin')->orWhere('role', 'SUPERADMIN')->orWhereNull('tenant_id');
                    })
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->first();
                if ($superadmin) {
                    $email = $superadmin->email;
                }
            }

            $logo = $global['COMPANY_LOGO'] ?? null;
            $logoUrl = static::resolveLogoUrl($logo, asset('images/logo.png?v=33'));

            return [
                'name' => $name,
                'phone' => $phone,
                'phone_wa' => static::waNumber($phone),
                'email' => $email,
                'address' => $global['COMPANY_ADDRESS'] ?? '',
                'logo' => $logoUrl,
                'raw_logo' => $logo,
                'bank_name' => $global['BANK_NAME'] ?? 'BCA / Bank Central Asia',
                'bank_account' => $global['BANK_ACCOUNT_NO'] ?? '8293-0192-38',
                'bank_holder' => $global['BANK_ACCOUNT_NAME'] ?? 'PT NODERA DIGITAL SOLUTION',
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'DN Solution',
                'phone' => '',
                'phone_wa' => '',
                'email' => '',
                'address' => '',
                'logo' => asset('images/logo.png?v=33'),
                'raw_logo' => null,
                'bank_name' => 'BCA / Bank Central Asia',
                'bank_account' => '8293-0192-38',
                'bank_holder' => 'PT NODERA DIGITAL SOLUTION',
            ];
        }
    }

    /**
     * Dapatkan nomor WhatsApp / HP Admin Tenant (dengan fallback ke profil admin tenant, setting tenant, lalu model tenant).
     */
    public static function getTenantPhone(?int $tenantId): string
    {
        if (!$tenantId) {
            return static::getSuperadminPhone();
        }

        try {
            // 1. User admin dari tenant tersebut
            $tenantAdminUser = \App\Models\User::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhere('role', 'owner')
                      ->orWhere('role', 'ADMIN')
                      ->orWhere('role', 'OWNER')
                      ->orWhereNull('role');
                })
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('id', 'ASC')
                ->first();

            if ($tenantAdminUser && !empty(trim((string) $tenantAdminUser->phone))) {
                return trim((string) $tenantAdminUser->phone);
            }

            // 2. Model Tenant phone
            $tenantModel = \App\Models\Tenant::withoutGlobalScopes()->find($tenantId);
            if ($tenantModel && !empty(trim((string) $tenantModel->phone))) {
                return trim((string) $tenantModel->phone);
            }

            // 3. Settings khusus tenant_id ini
            $tenantSettings = static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('key', ['ADMIN_WA', 'WA_ADMIN', 'COMPANY_PHONE', 'PHONE', 'WHATSAPP'])
                ->pluck('value', 'key');

            $adminWa = $tenantSettings['ADMIN_WA'] 
                ?? ($tenantSettings['WA_ADMIN'] 
                ?? ($tenantSettings['COMPANY_PHONE'] 
                ?? ($tenantSettings['WHATSAPP'] 
                ?? ($tenantSettings['PHONE'] ?? ''))));

            if (!empty($adminWa)) {
                return (string) $adminWa;
            }

            // Fallback: Superadmin phone if tenant has none
            return static::getSuperadminPhone();
        } catch (\Throwable $e) {
            return static::getSuperadminPhone();
        }
    }

    /**
     * Info perusahaan Tenant (dengan fallback ke global jika tidak ada)
     */
    public static function tenantCompany(?int $tenantId): array
    {
        if (!$tenantId) {
            return static::company();
        }

        try {
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($tenantId);
            $tenantPhone = static::getTenantPhone($tenantId);
            $tenantSettings = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('value', 'key');

            $name = $tenantSettings['COMPANY_NAME'] ?? ($tenant?->name ?? 'DN Solution');
            $phone = !empty($tenantPhone) ? $tenantPhone : ($tenantSettings['COMPANY_PHONE'] ?? '');
            $email = $tenantSettings['COMPANY_EMAIL'] ?? ($tenant?->email ?? '');
            $address = $tenantSettings['COMPANY_ADDRESS'] ?? ($tenant?->address ?? '');
            $logo = $tenant?->logo ?: ($tenantSettings['COMPANY_LOGO'] ?? null);
            $logoUrl = static::resolveLogoUrl($logo, null);

            return [
                'name' => $name,
                'phone' => $phone,
                'phone_wa' => static::waNumber($phone),
                'email' => $email,
                'address' => $address,
                'logo' => $logoUrl,
                'raw_logo' => $logo,
                'bank_name' => $tenantSettings['BANK_NAME'] ?? '',
                'bank_account' => $tenantSettings['BANK_ACCOUNT_NO'] ?? '',
                'bank_holder' => $tenantSettings['BANK_ACCOUNT_NAME'] ?? '',
            ];
        } catch (\Throwable $e) {
            return static::company();
        }
    }

    /**
     * Resolves a stored logo path or URL into a fully-qualified public URL.
     */
    public static function resolveLogoUrl(?string $logo, ?string $default = null): ?string
    {
        if (empty($logo)) {
            return $default;
        }

        $logo = trim((string) $logo);
        if ($logo === '') {
            return $default;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, 'data:')) {
            return $logo;
        }

        $clean = ltrim($logo, '/');

        if (str_starts_with($clean, 'storage/')) {
            return asset($clean);
        }

        if (str_starts_with($clean, 'uploads/')) {
            return asset($clean);
        }

        if (str_starts_with($clean, 'images/')) {
            return asset($clean);
        }

        if (str_starts_with($clean, 'hotspot-') || str_starts_with($clean, 'img/')) {
            return asset($clean);
        }

        return asset('storage/' . $clean);
    }

    /**
     * Ambil nama perusahaan berdasarkan tenant atau fallback ke global.
     */
    public static function getCompanyName(?int $tenantId = null): string
    {
        try {
            $company = static::tenantCompany($tenantId);
            return $company['name'] ?? config('app.name', 'NODERA Billing');
        } catch (\Throwable $e) {
            return config('app.name', 'NODERA Billing');
        }
    }

    /**
     * Normalisasi nomor WA: 0812... → 62812..., +62812 → 62812
     */
    public static function waNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || $digits === null) {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * Hapus setting.
     */
    public static function remove(string $key): bool
    {
        $tenantId = TenantScope::currentTenantId();
        $runtimeKey = ($tenantId ?? 0) . ":{$key}";

        unset(static::$runtimeCache[$runtimeKey]);
        Cache::forget("setting_val_" . ($tenantId ?? 0) . ":{$key}");
        Cache::forget("setting_val_" . ($tenantId ?? 0) . "_{$key}");
        Cache::forget("setting:" . ($tenantId ?? 0) . ":{$key}");
        Cache::forget('company_global_info');

        return (bool) static::withoutGlobalScopes()
            ->where('key', $key)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    /**
     * Flush all runtime & memory cache.
     */
    public static function flushCache(): void
    {
        static::$runtimeCache = [];
        Cache::forget('company_global_info');
    }
}
