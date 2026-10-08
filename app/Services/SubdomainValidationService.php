<?php

namespace App\Services;

use App\Models\MikhmonSubscription;
use App\Models\Tenant;
use Illuminate\Support\Str;

class SubdomainValidationService
{
    /**
     * List of system reserved subdomains that cannot be registered by users.
     */
    protected static array $reservedSubdomains = [
        'www', 'panel', 'api', 'admin', 'vpn', 'mikhmon', 'kas', 'pembukuan', 'ftth',
        'billing', 'mail', 'demo', 'test', 'app', 'root', 'superadmin', 'support',
        'help', 'dev', 'staging', 'portal', 'dashboard', 'status', 'bot', 'telegram',
        'whatsapp', 'pay', 'payment', 'sys', 'system', 'nodera', 'dgtlnetsolution',
    ];

    /**
     * Check if a subdomain is available across ALL SaaS services and system reserved words.
     */
    public static function checkAvailability(string $subdomain, ?string $ignoreType = null, ?int $ignoreId = null): array
    {
        $subdomain = Str::slug(strtolower(trim($subdomain)));

        if (strlen($subdomain) < 3) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => 'Subdomain minimal 3 karakter.',
            ];
        }

        if (strlen($subdomain) > 30) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => 'Subdomain maksimal 30 karakter.',
            ];
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $subdomain)) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => 'Subdomain hanya boleh berisi huruf kecil, angka, dan tanda hubung (-).',
            ];
        }

        if (in_array($subdomain, self::$reservedSubdomains, true)) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => "Subdomain '{$subdomain}' adalah kata cadangan sistem dan tidak dapat digunakan.",
            ];
        }

        // Check Mikhmon Subscriptions
        $mikhmonQuery = MikhmonSubscription::where('subdomain', $subdomain);
        if ($ignoreType === 'mikhmon' && $ignoreId) {
            $mikhmonQuery->where('id', '!=', $ignoreId);
        }
        if ($mikhmonQuery->exists()) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => "Subdomain '{$subdomain}' sudah digunakan oleh instansi MIKHMON.",
            ];
        }

        // Check Bookkeeping Subscriptions
        $bkQuery = \App\Models\BookkeepingSubscription::where('subdomain', $subdomain);
        if ($ignoreType === 'bookkeeping' && $ignoreId) {
            $bkQuery->where('id', '!=', $ignoreId);
        }
        if ($bkQuery->exists()) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => "Subdomain '{$subdomain}' sudah digunakan oleh instansi Pembukuan PWA.",
            ];
        }

        // Check Arisan Subscriptions
        if (\Illuminate\Support\Facades\Schema::hasTable('arisan_subscriptions')) {
            $arisanQuery = \App\Models\ArisanSubscription::where('subdomain', $subdomain);
            if ($ignoreType === 'arisan' && $ignoreId) {
                $arisanQuery->where('id', '!=', $ignoreId);
            }
            if ($arisanQuery->exists()) {
                return [
                    'available' => false,
                    'subdomain' => $subdomain,
                    'message' => "Subdomain '{$subdomain}' sudah digunakan oleh instansi Pembukuan Arisan.",
                ];
            }
        }

        // Check Tenants domain / slug
        $tenantQuery = Tenant::where('slug', $subdomain);
        if ($ignoreType === 'tenant' && $ignoreId) {
            $tenantQuery->where('id', '!=', $ignoreId);
        }
        if ($tenantQuery->exists()) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => "Subdomain '{$subdomain}' sudah terdaftar sebagai domain Tenant ISP.",
            ];
        }

        // Check Pending Tenant Registration Requests
        $regQuery = \App\Models\RegistrationRequest::where('slug', $subdomain)->whereIn('status', ['pending', 'approved']);
        if ($ignoreType === 'registration' && $ignoreId) {
            $regQuery->where('id', '!=', $ignoreId);
        }
        if ($regQuery->exists()) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => "Subdomain '{$subdomain}' sedang dalam proses pendaftaran Tenant ISP.",
            ];
        }

        return [
            'available' => true,
            'subdomain' => $subdomain,
            'message' => "Subdomain '{$subdomain}.dgtlnetsolution.com' tersedia!",
        ];
    }
}
