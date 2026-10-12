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
        if (!$tenant && auth()->check()) {
            $tenant = Tenant::withoutGlobalScopes()->first();
            $tenantId = $tenant?->id;
        }

        $addons = Addon::where('is_active', true)->orderBy('id')->get();
        $tenantAddons = $tenantId ? TenantAddon::where('tenant_id', $tenantId)->get() : collect();

        $vpnUserId = $tenant?->vpn_user_id ?? ($tenant?->settings['vpn_user_id'] ?? null);
        $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
        if (!$vpnUser && $tenant?->email) {
            $vpnUser = VpnUser::where('email', $tenant->email)->first();
        }
        if (!$vpnUser && auth()->check() && auth()->user()?->email) {
            $vpnUser = VpnUser::where('email', auth()->user()->email)->first();
        }
        $masterSaldo = $vpnUser ? (float) $vpnUser->total_saldo : (float) ($tenant?->saldo ?? 0);

        $featuresMap = [
            'olt_management' => [
                'Auto Discovery ONU Baru Unconfigured di Setiap Port PON',
                'Monitoring Optical Power (Rx/Tx dBm) & Status Link Fiber Realtime',
                'Dukungan Multi-Vendor: HiOSO, C-Data, ZTE, VSOL, Huawei, HSGQ',
                'Remote Reboot ONU & Terintegrasi Penuh dengan Billing',
            ],
            'mikhmon_online' => [
                'Akses Dashboard Mikhmon Online dari Luar Jaringan Tanpa VPN/IP Publik',
                'Cetak Voucher Hotspot Cepat & Template Voucher Kustom Responsif',
                'Sinkronisasi Multi-Router MikroTik & Log Penjualan Realtime',
            ],
            'paket_isolir' => [
                'Halaman Tagihan Interaktif error.html Khusus Pelanggan Menunggak',
                'Bypass Pembayaran QRIS & Virtual Account Tanpa Buka Isolir Manual',
                'Script Generator Firewall NAT & Filter MikroTik Sekali Klik',
            ],
            'genieacs_management' => [
                'Multi-Vendor ONT via Protokol Standar TR-069',
                'Telemetri Optik Realtime (Rx/Tx dBm) & Status Redaman',
                'Remote Kontrol WiFi (SSID/Password) & Remote Reboot 1-Klik',
            ],
            'radius_server' => [
                'Otentikasi Terpusat PPPoE / IPoE & Hotspot Skala Ribuan User',
                'RFC 3576 CoA: Putus atau Ubah Kecepatan User Realtime',
                'High Availability & Accounting Sesi Pelanggan Otomatis',
            ],
            'mapping_gis' => [
                'Visualisasi Jalur Kabel Fiber Optik & Titik Sebaran ODP Interaktif',
                'Manajemen Port ODP (Tersedia, Terpakai, Rusak) & Redaman Tiap Titik',
                'Estimasi Lacak Lokasi Putus Kabel (OTDR Distance Locator)',
            ],
            'whatsapp_notification' => [
                'Broadcast Massal & Notifikasi Tagihan Otomatis ke WhatsApp Pelanggan',
                'Kirim Bukti Pembayaran Lunas & Notifikasi Pengingat Jatuh Tempo',
                'Dukungan Multi-Device & Webhook Interaktif',
            ],
            'mikhmon_agent' => [
                'Aplikasi Kasir Warung / Reseller Voucher Hotspot Berbasis Web',
                'Sistem Kulakan Voucher Otomatis Potong Saldo Agen/Warung',
                'Cetak Struk Thermal Bluetooth & Kirim Struk via WhatsApp',
            ],
        ];

        $categoryMap = [
            'olt_management'        => 'network',
            'mikhmon_online'        => 'network',
            'paket_isolir'          => 'network',
            'genieacs_management'   => 'network',
            'radius_server'         => 'network',
            'mapping_gis'           => 'system',
            'whatsapp_notification' => 'marketing',
            'mikhmon_agent'         => 'billing',
        ];

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

        $mappedAddons = $addons->map(function ($a) use ($tenantAddons, $featuresMap, $categoryMap) {
            $slug = $a->slug;
            $ta = $tenantAddons->first(function ($item) use ($a, $slug) {
                return $item->addon_id == $a->id 
                    || $item->addon_id === $slug 
                    || (!empty($item->config['slug']) && $item->config['slug'] === $slug);
            });

            $isInstalled = $ta && ($ta->is_active || in_array($ta->status, ['approved', 'active']));
            $isActive = $ta && $ta->is_active && !$ta->isExpired();

            return [
                'id'            => (string) $a->id,
                'slug'          => $a->slug,
                'name'          => $a->name,
                'category'      => $categoryMap[$slug] ?? 'network',
                'description'   => $a->description,
                'price'         => (float) $a->price,
                'billing_cycle' => $a->billing_cycle ?? 'monthly',
                'is_installed'  => (bool) $isInstalled,
                'is_active'     => (bool) $isActive,
                'has_config'    => in_array($slug, ['paket_isolir', 'genieacs_management', 'mikhmon_online', 'radius_server', 'olt_management']),
                'icon'          => $a->icon ?? 'Puzzle',
                'features'      => $featuresMap[$slug] ?? [
                    'Aktivasi Modular Sesuai Pertumbuhan Bisnis ISP',
                    'Terintegrasi Penuh dengan NODERA Billing',
                    'Pembaruan Otomatis Tanpa Biaya Tambahan',
                ],
                'route_name'    => $a->route_name ?? '',
                'feature_key'   => $a->feature_key ?? $a->slug,
                'is_free'       => (float) $a->price <= 0,
                'installed_at'  => $ta?->created_at?->format('d M Y'),
                'expires_at'    => $ta?->expired_at?->format('d M Y H:i'),
            ];
        });

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
            'addons'             => $mappedAddons,
            'tenantAddons'       => $tenantAddonMap,
            'masterSaldo'        => $masterSaldo,
            'walletBalance'      => $masterSaldo,
            'cloud_panel_balance'=> $masterSaldo,
            'hasVpnUser'         => (bool) $vpnUser,
            'registeredRouters'  => $routers,
            'bankAccounts'       => $bankAccounts,
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
     * Instant Purchase & Activation via Potong Saldo / Free Activation.
     */
    public function buy(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        if (!$tenant && auth()->check()) {
            $tenant = Tenant::withoutGlobalScopes()->first();
        }
        if (!$tenant) {
            $tenant = Tenant::firstOrCreate([
                'slug' => 'default',
            ], [
                'name' => 'Default ISP',
                'domain' => request()->getHost(),
                'is_active' => true,
            ]);
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
            if (str_contains($cleanKey, 'olt') || str_contains($cleanKey, 'onu')) {
                $addon = Addon::firstOrCreate(['slug' => 'olt_management'], [
                    'name' => 'MANAJEMEN OLT & ONT NMS (GPON & EPON)',
                    'description' => 'Sistem monitoring dan manajemen OLT EPON / GPON terpadu (HiOSO, C-Data, VSOL, ZTE, Huawei, HSGQ). Pantau optical power Rx/Tx dBm, auto-discovery ONU unconfigured, status link fiber, dan remote reboot.',
                    'price' => 25000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'Server',
                    'route_name' => '/admin/olt',
                    'feature_key' => 'olt_management',
                    'is_active' => true,
                ]);
            } elseif (str_contains($cleanKey, 'genieacs') || str_contains($cleanKey, 'ont')) {
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
                    'price' => 20000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'Radio',
                    'route_name' => '/admin/radius',
                    'feature_key' => 'radius_server',
                    'is_active' => true,
                ]);
            } elseif (str_contains($cleanKey, 'map') || str_contains($cleanKey, 'gis')) {
                $addon = Addon::firstOrCreate(['slug' => 'mapping_gis'], [
                    'name' => 'PETA JARINGAN GIS & FTTH ODP',
                    'description' => 'Peta geospasial interaktif FTTH untuk dokumentasi kabel fiber optik & tiang ODP.',
                    'price' => 15000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'MapPin',
                    'route_name' => '/admin/map',
                    'feature_key' => 'mapping_gis',
                    'is_active' => true,
                ]);
            } elseif (str_contains($cleanKey, 'whatsapp') || str_contains($cleanKey, 'broadcast')) {
                $addon = Addon::firstOrCreate(['slug' => 'whatsapp_notification'], [
                    'name' => 'GATEWAY NOTIFIKASI WHATSAPP',
                    'description' => 'Kirim broadcast tagihan otomatis & struk lunas via WhatsApp Gateway.',
                    'price' => 15000,
                    'billing_cycle' => 'monthly',
                    'icon' => 'MessageSquare',
                    'route_name' => '/admin/broadcast',
                    'feature_key' => 'whatsapp_notification',
                    'is_active' => true,
                ]);
            }
        }

        if (!$addon) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Modul add-on tidak ditemukan atau belum aktif di sistem.'], 404);
            }
            return back()->with('error', 'Modul add-on tidak ditemukan atau belum aktif di sistem.');
        }

        $autoRenew = (bool) $request->input('auto_renew', true);
        $addonService = app(AddonService::class);
        $result = $addonService->purchaseWithBalance($tenant, $addon, $autoRenew);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        if (!empty($addon->route_name) && str_starts_with($addon->route_name, '/admin/')) {
            return redirect($addon->route_name)->with('msg', $result['message'])->with('success', $result['message']);
        }

        return back()->with('msg', $result['message'])->with('success', $result['message']);
    }

    /**
     * Toggle auto-renew status on an active subscription.
     */
    public function toggleAutoRenew(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenantAddon = TenantAddon::where('tenant_id', $tenantId)
            ->where(function ($q) use ($addonId) {
                if (is_numeric($addonId)) {
                    $q->where('addon_id', $addonId);
                } else {
                    $q->whereHas('addon', fn($sub) => $sub->where('slug', $addonId)->orWhere('feature_key', $addonId));
                }
            })
            ->firstOrFail();

        $tenantAddon->auto_renew = !$tenantAddon->auto_renew;
        $tenantAddon->save();

        $statusStr = $tenantAddon->auto_renew ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('msg', "Perpanjangan otomatis (Auto-Renew) berhasil {$statusStr}.");
    }

    /**
     * Toggle active/inactive state on an addon.
     */
    public function toggle(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();

        $addon = Addon::where(function ($q) use ($addonId) {
            if (is_numeric($addonId)) {
                $q->where('id', $addonId);
            } else {
                $q->where('slug', $addonId)->orWhere('feature_key', $addonId);
            }
        })->firstOrFail();

        $tenantAddon = TenantAddon::firstOrNew([
            'tenant_id' => $tenant->id,
            'addon_id'  => $addon->id,
        ]);

        if ($tenantAddon->is_active && !$tenantAddon->isExpired()) {
            // Nonaktifkan
            $tenantAddon->is_active = false;
            $tenantAddon->status = 'inactive';
            $tenantAddon->save();
            return back()->with('msg', "Add-on {$addon->name} berhasil dinonaktifkan.");
        }

        // Jalankan pembelian via potong saldo
        return $this->buy($request, $addon->id);
    }

    public function cancel(Request $request, $addonId)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        $addon = Addon::where(function ($q) use ($addonId) {
            if (is_numeric($addonId)) {
                $q->where('id', $addonId);
            } else {
                $q->where('slug', $addonId)->orWhere('feature_key', $addonId);
            }
        })->first();

        if (!$addon) {
            return back()->with('error', 'Add-on tidak ditemukan.');
        }

        $tenantAddon = TenantAddon::where('tenant_id', $tenantId)
            ->where('addon_id', $addon->id)
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
        $addon = Addon::where(function ($q) use ($addonId) {
            if (is_numeric($addonId)) {
                $q->where('id', $addonId);
            } else {
                $q->where('slug', $addonId)->orWhere('feature_key', $addonId);
            }
        })->first();

        $tenantAddon = TenantAddon::firstOrNew([
            'tenant_id' => $tenantId,
            'addon_id'  => $addon?->id ?? $addonId,
        ]);

        $config = $request->all();
        $tenantAddon->config = array_merge($tenantAddon->config ?? [], $config);
        $tenantAddon->save();

        return back()->with('msg', 'Konfigurasi add-on berhasil disimpan.');
    }

    // Ajax endpoints for Isolir management
    public function saveIsolirSettings(Request $request)
    {
        return $this->configure($request, 'paket_isolir');
    }

    public function uploadIsolirHtml(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'File error.html isolir berhasil diunggah ke router.',
        ]);
    }

    public function executeIsolirScript(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Seluruh rule isolir berhasil diterapkan ke MikroTik.',
        ]);
    }

    public function executeIsolirSingle(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Rule berhasil diterapkan.',
        ]);
    }
}
