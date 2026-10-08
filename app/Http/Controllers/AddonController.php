<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\VpnUser;
use App\Services\AddonService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AddonController extends Controller
{
    public function index()
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $addons = Addon::where('is_active', true)->orderBy('id')->get();
        $tenantAddons = TenantAddon::where('tenant_id', $tenantId)->get();

        $vpnUserId = $tenant?->vpn_user_id ?? ($tenant?->settings['vpn_user_id'] ?? null);
        $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
        $masterSaldo = $vpnUser ? (float) $vpnUser->total_saldo : 0;

        $tenantAddonMap = [];
        foreach ($tenantAddons as $ta) {
            $tenantAddonMap[$ta->addon_id] = [
                'id'                  => $ta->id,
                'addon_id'            => $ta->addon_id,
                'is_active'           => (bool) $ta->is_active,
                'status'              => $ta->status ?? ($ta->is_active ? 'approved' : 'inactive'),
                'auto_renew'          => (bool) $ta->auto_renew,
                'expired_at'          => $ta->expired_at?->format('d M Y H:i'),
                'is_expired'          => $ta->isExpired(),
                'is_valid'            => $ta->isValid(),
                'config'              => $ta->config ?? [],
                'total_amount'        => (float) ($ta->total_amount ?? 0),
                'unique_code'         => (int) ($ta->unique_code ?? 0),
                'dynamic_qris_string' => $ta->dynamic_qris_string,
                'qris_svg'            => $ta->dynamic_qris_string ? \App\Services\QrisDynamicService::generateQrSvg($ta->dynamic_qris_string) : null,
                'expires_at_iso'      => $ta->expires_at?->toIso8601String(),
            ];
        }

        $routers = \App\Models\Mikrotik::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'host' => $r->host,
                'port' => (int) ($r->port ?? 8728),
                'username' => $r->username,
                'location' => $r->location,
            ]);

        $bankAccounts = \App\Models\BankAccount::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'account_name' => $b->account_name,
            ]);

        $defaultBank = $bankAccounts->first() ?? [
            'bank_name' => '',
            'account_number' => '',
            'account_name' => $tenant?->name ?? '',
        ];

        return Inertia::render('Admin/Addons', [
            'addons' => $addons->map(fn ($a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'slug'          => $a->slug,
                'description'   => $a->description,
                'price'         => (float) $a->price,
                'billing_cycle' => $a->billing_cycle ?? 'monthly',
                'icon'          => $a->icon ?? 'Puzzle',
                'route_name'    => $a->route_name ?? '',
                'feature_key'   => $a->feature_key ?? $a->slug,
                'is_enabled'    => (bool) $a->is_active,
            ]),
            'tenantAddons'      => $tenantAddonMap,
            'masterSaldo'       => $masterSaldo,
            'hasVpnUser'        => (bool) $vpnUser,
            'registeredRouters' => $routers,
            'bankAccounts'      => $bankAccounts,
            'company' => [
                'name'         => $tenant?->name ?? 'NODERA Billing',
                'phone'        => $tenant?->phone ?? '',
                'email'        => $tenant?->email ?? '',
                'domain'       => $tenant?->domain ?? request()->getHost(),
                'portal_url'   => url('/portal/login'),
                'bank_name'    => $defaultBank['bank_name'] ?? 'BCA / Bank Central Asia',
                'bank_account' => $defaultBank['account_number'] ?? '8293-0192-38',
                'bank_holder'  => $defaultBank['account_name'] ?? ($tenant?->name ?? 'NODERA Billing'),
            ],
        ]);
    }

    /**
     * Instant 1-Click Purchase & Activation via Potong Saldo.
     */
    public function buy(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        if (!$tenant && auth()->check()) {
            $tenant = Tenant::first();
        }
        if (!$tenant) {
            return back()->with('error', 'Sesi tenant tidak valid.');
        }

        $cleanKey = strtolower(trim((string) $addonId));
        $addon = Addon::where('is_active', true)
            ->where(function ($q) use ($addonId, $cleanKey) {
                if (is_numeric($addonId)) {
                    $q->where('id', $addonId);
                } else {
                    $q->where('slug', $cleanKey)
                      ->orWhere('slug', 'like', "%{$cleanKey}%")
                      ->orWhere('feature_key', $cleanKey)
                      ->orWhere('feature_key', 'like', "%{$cleanKey}%");
                }
            })
            ->first();

        // Auto-heal / auto-seed known standard addons if missing from DB
        if (!$addon) {
            if (str_contains($cleanKey, 'genieacs') || str_contains($cleanKey, 'ont')) {
                $addon = Addon::firstOrCreate(['slug' => 'genieacs_management'], [
                    'name' => 'GENIEACS TR-069 & CLOUD ONT',
                    'description' => 'Manajemen massal dan konfigurasi otomatis ONT pelanggan via protokol TR-069.',
                    'price' => 1000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'Wifi',
                    'route_name' => '/admin/ont-devices',
                    'feature_key' => 'genieacs_management',
                    'is_active' => true,
                ]);
            } elseif (str_contains($cleanKey, 'isolir')) {
                $addon = Addon::firstOrCreate(['slug' => 'paket_isolir'], [
                    'name' => 'PAKET ISOLIR & WEBPROXY',
                    'description' => 'Konfigurasi otomatis isolir MikroTik & Web Proxy.',
                    'price' => 10000,
                    'billing_cycle' => 'lifetime',
                    'icon' => 'ShieldAlert',
                    'route_name' => '/admin/addons',
                    'feature_key' => 'paket_isolir',
                    'is_active' => true,
                ]);
            } elseif (str_contains($cleanKey, 'mikhmon')) {
                $addon = Addon::firstOrCreate(['slug' => 'mikhmon_online'], [
                    'name' => 'MIKHMON ONLINE (RouterOS v6 & v7)',
                    'description' => 'Kelola Voucher MikroTik & Hotspot online.',
                    'price' => 10000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'Wifi',
                    'route_name' => '/admin/mikhmon',
                    'feature_key' => 'mikhmon_online',
                    'is_active' => true,
                ]);
            } elseif (str_contains($cleanKey, 'radius')) {
                $addon = Addon::firstOrCreate(['slug' => 'radius_server'], [
                    'name' => 'RADIUS SERVER & RFC 3576 CoA',
                    'description' => 'Autentikasi terpusat PPPoE & Hotspot skala ribuan pelanggan.',
                    'price' => 15000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'Server',
                    'route_name' => '/admin/radius',
                    'feature_key' => 'radius_server',
                    'is_active' => true,
                ]);
            }
        }

        if (!$addon) {
            return back()->with('error', 'Modul add-on tidak ditemukan atau belum aktif di sistem.');
        }

        $autoRenew = (bool) $request->input('auto_renew', true);
        $addonService = app(AddonService::class);
        $result = $addonService->purchaseWithBalance($tenant, $addon, $autoRenew);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        if (!empty($addon->route_name) && str_starts_with($addon->route_name, '/admin/')) {
            return redirect($addon->route_name)->with('msg', $result['message']);
        }

        return back()->with('msg', $result['message']);
    }

    /**
     * Toggle auto-renew status on an active subscription.
     */
    public function toggleAutoRenew(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenantAddon = TenantAddon::where('tenant_id', $tenantId)
            ->where('addon_id', $addonId)
            ->firstOrFail();

        $tenantAddon->auto_renew = !$tenantAddon->auto_renew;
        $tenantAddon->save();

        $statusStr = $tenantAddon->auto_renew ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('msg', "Perpanjangan otomatis (Auto-Renew) berhasil {$statusStr}.");
    }

    /**
     * Legacy toggle/QRIS request fallback.
     */
    public function toggle(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);
        $addon = Addon::findOrFail($addonId);

        $tenantAddon = TenantAddon::firstOrNew([
            'tenant_id' => $tenantId,
            'addon_id'  => $addonId,
        ]);

        if ($tenantAddon->is_active && !$tenantAddon->isExpired()) {
            // Nonaktifkan
            $tenantAddon->is_active = false;
            $tenantAddon->status = 'inactive';
            $tenantAddon->save();
            return back()->with('msg', "Add-on {$addon->name} berhasil dinonaktifkan.");
        }

        // Jalankan pembelian via potong saldo
        return $this->buy($request, $addonId);
    }

    public function cancel(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $addon = Addon::findOrFail($addonId);

        $tenantAddon = TenantAddon::where('tenant_id', $tenantId)
            ->where('addon_id', $addonId)
            ->first();

        if ($tenantAddon) {
            $tenantAddon->status = 'inactive';
            $tenantAddon->is_active = false;
            $tenantAddon->save();
            return back()->with('msg', "Langganan add-on {$addon->name} telah dinonaktifkan.");
        }

        return back()->with('msg', "Add-on tidak ditemukan.");
    }

    public function configure(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenantAddon = TenantAddon::firstOrNew([
            'tenant_id' => $tenantId,
            'addon_id'  => $addonId,
        ]);

        $config = $request->validate([
            // Paket Isolir & Webproxy
            'isolir_profile'      => 'nullable|string|max:50|regex:/^[a-zA-Z0-9\s~!#$%&\-_.]+$/',
            'isolir_rate_limit'   => 'nullable|string|max:50',
            'isolir_address_list' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9\-_.]+$/',
            'proxy_port'          => 'nullable|string|max:10',
            'company_name'        => 'nullable|string|max:100',
            'cs_phone'            => 'nullable|string|max:30',
            'payment_type'        => 'nullable|string|in:portal,bank_transfer',
            'payment_url'         => 'nullable|string|max:255',
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_holder' => 'nullable|string|max:100',
            'bank_info_notes'     => 'nullable|string|max:500',
            'custom_message'      => 'nullable|string|max:1000',
        ]);

        $config = array_filter($config, fn($v) => $v !== null && $v !== '');

        $tenantAddon->config = array_merge($tenantAddon->config ?? [], $config);
        $tenantAddon->save();

        return back()->with('msg', 'Konfigurasi add-on berhasil disimpan.');
    }
}
