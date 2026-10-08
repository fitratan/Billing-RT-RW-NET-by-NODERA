<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\MikhmonSubscription;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\VpnUser;
use App\Services\AddonService;
use App\Services\MikhmonProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminMikhmonController extends Controller
{
    /**
     * Display the MIKHMON management page for the current tenant.
     */
    public function index(Request $request, AddonService $addonService)
    {
        $tenantId = session('tenant_id') ?? Auth::user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        // Check if Mikhmon Online is gated as a paid Add-on
        $hasAccess = $addonService->canAccess($tenant, 'mikhmon_online') || $addonService->canAccess($tenant, 'mikhmon');
        $mikhmonAddon = Addon::whereIn('slug', ['mikhmon_online', 'mikhmon'])->where('is_active', true)->first();

        $vpnUserId = $tenant?->vpn_user_id ?? ($tenant?->settings['vpn_user_id'] ?? null);
        $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
        $masterSaldo = $vpnUser ? (float) $vpnUser->total_saldo : 0;

        $tenantAddon = $tenant && $mikhmonAddon ? TenantAddon::where('tenant_id', $tenant->id)->where('addon_id', $mikhmonAddon->id)->first() : null;

        if (!$hasAccess && $mikhmonAddon && (float) $mikhmonAddon->price > 0) {
            return Inertia::render('Admin/Mikhmon', [
                'isLocked'    => true,
                'addon'       => [
                    'id'            => $mikhmonAddon->id,
                    'name'          => $mikhmonAddon->name,
                    'slug'          => $mikhmonAddon->slug,
                    'description'   => $mikhmonAddon->description,
                    'price'         => (float) $mikhmonAddon->price,
                    'billing_cycle' => $mikhmonAddon->billing_cycle ?? 'monthly',
                ],
                'masterSaldo' => $masterSaldo,
                'hasVpnUser'  => (bool) $vpnUser,
                'tenantAddon' => $tenantAddon ? [
                    'is_active'   => (bool) $tenantAddon->is_active,
                    'status'      => $tenantAddon->status,
                    'expired_at'  => $tenantAddon->expired_at?->format('d M Y H:i'),
                    'is_expired'  => $tenantAddon->isExpired(),
                    'auto_renew'  => (bool) $tenantAddon->auto_renew,
                ] : null,
                'tenant' => [
                    'name' => $tenant?->name ?? 'NODERA Billing',
                    'slug' => $tenant?->slug ?? 'admin',
                ],
            ]);
        }

        $rosVersion = (string) ($tenant?->settings['mikhmon_ros_version'] ?? '6');
        if (! in_array($rosVersion, ['6', '7'])) {
            $rosVersion = '6';
        }

        // Auto-provision or sync Mikhmon subscription & files for this tenant
        $sub = null;
        if ($tenant) {
            $sub = MikhmonProvisioner::syncForTenant($tenant, $tenantAddon, $rosVersion);
        }

        $folderName = $sub ? $sub->subdomain : ($tenant ? 'hotspot-' . $tenant->slug : 'hotspot-admin');
        $rosVersion = $sub ? ($sub->ros_version ?? $rosVersion) : $rosVersion;

        $adminUrl = $sub ? $sub->admin_url : (url('/') . '/' . $folderName . '/login');
        $buyUrl = $sub ? $sub->buy_url : (url('/') . '/' . $folderName . '/');
        $subpathUrl = $sub ? $sub->subpath_url : (url('/') . '/' . $folderName . '/login');

        $isExpired = $tenantAddon ? $tenantAddon->isExpired() : ($tenant ? $tenant->isExpired() : false);
        $isActive = $tenant ? (bool) $tenant->is_active : true;
        $expiresAt = $tenantAddon?->expired_at 
            ? $tenantAddon->expired_at->toISOString() 
            : ($sub?->expires_at ? $sub->expires_at->toISOString() : ($tenant?->expired_at ? $tenant->expired_at->toISOString() : ($tenant?->trial_ends_at ? $tenant->trial_ends_at->toISOString() : null)));
        $expiresFormatted = $tenantAddon?->expired_at 
            ? $tenantAddon->expired_at->format('d/m/Y') 
            : ($sub?->expires_at ? $sub->expires_at->format('d/m/Y') : ($tenant?->expired_at ? $tenant->expired_at->format('d/m/Y') : ($tenant?->trial_ends_at ? $tenant->trial_ends_at->format('d/m/Y') : null)));

        $financialIncome = $this->getFinancialIncome($tenant, $sub, $folderName);

        return Inertia::render('Admin/Mikhmon', [
            'isLocked'     => false,
            'mikhmonUrl'   => $adminUrl,
            'adminUrl'     => $adminUrl,
            'buyUrl'       => $buyUrl,
            'subpathUrl'   => $subpathUrl,
            'folderName'   => $folderName,
            'rosVersion'   => $rosVersion,
            'masterSaldo'  => $masterSaldo,
            'financialIncome' => $financialIncome,
            'tenantAddon'  => $tenantAddon ? [
                'id'          => $tenantAddon->id,
                'addon_id'    => $tenantAddon->addon_id,
                'is_active'   => (bool) $tenantAddon->is_active,
                'status'      => $tenantAddon->status,
                'auto_renew'  => (bool) $tenantAddon->auto_renew,
                'expired_at'  => $tenantAddon->expired_at?->format('d M Y H:i'),
                'is_expired'  => $tenantAddon->isExpired(),
            ] : null,
            'addon'        => $mikhmonAddon ? [
                'id'            => $mikhmonAddon->id,
                'name'          => $mikhmonAddon->name,
                'price'         => (float) $mikhmonAddon->price,
                'billing_cycle' => $mikhmonAddon->billing_cycle ?? 'monthly',
            ] : null,
            'tenant' => [
                'name'              => $tenant?->name ?? 'NODERA Billing',
                'slug'              => $tenant?->slug ?? 'admin',
                'is_active'         => $isActive,
                'is_expired'        => $isExpired,
                'expires_at'        => $expiresAt,
                'expires_formatted' => $expiresFormatted,
            ],
            'defaultCredentials' => [
                'username' => 'mikhmon_admin',
                'password' => \Illuminate\Support\Str::random(16),
            ],
        ]);
    }

    /**
     * Direct full redirect to Mikhmon panel.
     */
    public function open(Request $request, AddonService $addonService)
    {
        $tenantId = session('tenant_id') ?? Auth::user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (!$addonService->canAccess($tenant, 'mikhmon_online') && !$addonService->canAccess($tenant, 'mikhmon')) {
            return redirect('/admin/mikhmon')->with('error', 'Fitur Mikhmon Online terkunci. Silakan aktifkan Add-on terlebih dahulu.');
        }

        $mikhmonAddon = Addon::whereIn('slug', ['mikhmon_online', 'mikhmon'])->where('is_active', true)->first();
        $tenantAddon = $tenant && $mikhmonAddon ? TenantAddon::where('tenant_id', $tenant->id)->where('addon_id', $mikhmonAddon->id)->first() : null;

        $sub = $tenant ? MikhmonProvisioner::syncForTenant($tenant, $tenantAddon) : null;
        $targetUrl = $sub ? $sub->admin_url : (url('/') . '/hotspot-' . ($tenant?->slug ?? 'admin') . '/login');

        if ($request->header('X-Inertia')) {
            return Inertia::location($targetUrl);
        }

        return redirect()->away($targetUrl);
    }

    /**
     * Switch between RouterOS v6 (Mikhmon v3) and RouterOS v7 (Mikhmon Agent v7).
     */
    public function switchRosVersion(Request $request)
    {
        $data = $request->validate([
            'ros_version' => 'required|in:6,7',
        ]);

        $rosVersion = $data['ros_version'];
        $tenantId = session('tenant_id') ?? Auth::user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if ($tenant) {
            $settings = $tenant->settings ?? [];
            $settings['mikhmon_ros_version'] = $rosVersion;
            $tenant->settings = $settings;
            $tenant->save();

            $mikhmonAddon = Addon::whereIn('slug', ['mikhmon_online', 'mikhmon'])->where('is_active', true)->first();
            $tenantAddon = $mikhmonAddon ? TenantAddon::where('tenant_id', $tenant->id)->where('addon_id', $mikhmonAddon->id)->first() : null;

            MikhmonProvisioner::syncForTenant($tenant, $tenantAddon, $rosVersion);
        }

        return back()->with('msg', "Versi MIKHMON berhasil dialihkan ke RouterOS v{$rosVersion}.");
    }

    /**
     * Reset admin credentials back to default (nodera / nodera).
     */
    public function resetPassword(Request $request)
    {
        $tenantId = session('tenant_id') ?? Auth::user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $sub = $tenant ? MikhmonSubscription::where('tenant_id', $tenant->id)->first() : null;
        $folderName = $sub ? $sub->subdomain : ($tenant ? 'hotspot-' . $tenant->slug : 'hotspot-admin');

        $provisioner = new MikhmonProvisioner();
        $targetPath = $provisioner->targetPath($folderName);

        $ok = $provisioner->resetAdminPassword($targetPath);

        if (! $ok) {
            return back()->with('error', "Gagal mereset: Instance MIKHMON tidak ditemukan.");
        }

        return back()->with('msg', "Password MIKHMON berhasil direset — Username : nodera | Password : nodera");
    }

    /**
     * Live JSON endpoint for Financial Income widget auto-refresh.
     */
    public function liveIncome(Request $request)
    {
        $tenantId = session('tenant_id') ?? Auth::user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $sub = $tenant ? MikhmonSubscription::where('tenant_id', $tenant->id)->first() : null;
        $folderName = $sub ? $sub->subdomain : ($tenant ? 'hotspot-' . $tenant->slug : 'hotspot-admin');

        $income = $this->getFinancialIncome($tenant, $sub, $folderName);

        return response()->json([
            'success' => true,
            'income'  => $income,
        ]);
    }

    /**
     * Calculate or aggregate live financial income (Today and This Month).
     */
    protected function getFinancialIncome(?Tenant $tenant, ?MikhmonSubscription $sub, string $folderName): array
    {
        $currency = 'Rp';
        $todayVouchers = 0;
        $todayIncome = 0.0;
        $monthVouchers = 0;
        $monthIncome = 0.0;

        $tenantId = $tenant?->id;

        // 1. Aggregation from database ShopOrder (Voucher Sales)
        if ($tenantId) {
            $todayOrders = \App\Models\ShopOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('voucher_username')
                ->where('payment_status', 'paid')
                ->whereDate('paid_at', now()->toDateString())
                ->get();

            $todayVouchers += $todayOrders->count();
            $todayIncome += (float) $todayOrders->sum('total_amount');

            $monthOrders = \App\Models\ShopOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('voucher_username')
                ->where('payment_status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->get();

            $monthVouchers += $monthOrders->count();
            $monthIncome += (float) $monthOrders->sum('total_amount');
        }

        // 2. Read from Mikhmon instance orders_data.json if exists
        try {
            $paths = [
                public_path($folderName . '/orders_data.json'),
                public_path($folderName . '/orders_data_' . ($tenant?->slug ?? 'default') . '.json'),
            ];

            foreach ($paths as $p) {
                if (File::exists($p)) {
                    $json = json_decode(File::get($p), true);
                    if (is_array($json)) {
                        $todayStr = now()->format('Y-m-d');
                        $monthStr = now()->format('Y-m');

                        foreach ($json as $row) {
                            $paid = strtolower($row['payment_status'] ?? ($row['status'] ?? ''));
                            if (in_array($paid, ['paid', 'success', 'completed', 'verified'])) {
                                $dateStr = substr($row['paid_at'] ?? ($row['created_at'] ?? ($row['date'] ?? '')), 0, 10);
                                $amt = (float) ($row['total_amount'] ?? ($row['amount'] ?? ($row['price'] ?? 0)));

                                if (str_starts_with($dateStr, $todayStr)) {
                                    $todayVouchers++;
                                    $todayIncome += $amt;
                                }
                                if (str_starts_with($dateStr, $monthStr)) {
                                    $monthVouchers++;
                                    $monthIncome += $amt;
                                }
                            }
                        }
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {}

        // 3. Fallback: Query live script report from tenant's first active MikroTik router if available
        if ($todayVouchers === 0 && $monthVouchers === 0 && $tenantId) {
            try {
                $router = \App\Models\Mikrotik::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                if ($router) {
                    $cacheKey = "mikhmon_live_income_{$tenantId}_{$router->id}";
                    $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
                    if ($cached && is_array($cached)) {
                        return $cached;
                    }

                    $mikrotikService = new \App\Services\MikrotikService($router);
                    $scripts = $mikrotikService->query('/system/script/print', [
                        '?.proplist' => '.id,name,owner,source,comment',
                    ]);

                    if (is_array($scripts)) {
                        $thisD = now()->format('d');
                        $thisM = strtolower(now()->format('M'));
                        $thisY = now()->format('Y');
                        $thisD2 = strlen($thisD) === 1 ? '0' . $thisD : $thisD;

                        $idhr1 = $thisM . '/' . $thisD . '/' . $thisY;
                        $idhr2 = $thisM . '/' . $thisD2 . '/' . $thisY;
                        $idhrIso = now()->format('Y-m-d');
                        $idbl = $thisM . $thisY;
                        $idblIso = now()->format('Y-m');

                        foreach ($scripts as $sc) {
                            $name = $sc['name'] ?? '';
                            $source = strtolower(trim($sc['source'] ?? ''));
                            $owner = strtolower(trim($sc['owner'] ?? ''));
                            $comment = $sc['comment'] ?? '';

                            $price = 0;
                            $parts = explode('-|-', $name);
                            if (isset($parts[3]) && is_numeric(trim($parts[3]))) {
                                $price = (float) trim($parts[3]);
                            } elseif (isset($parts[2]) && is_numeric(trim($parts[2]))) {
                                $price = (float) trim($parts[2]);
                            }

                            if ($source === strtolower($idhr1) || $source === strtolower($idhr2) || $source === $idhrIso || str_contains($source, $idhrIso)) {
                                $todayVouchers++;
                                $todayIncome += $price;
                            }

                            if ($owner === strtolower($idbl) || $owner === $idblIso || str_contains($source, $idblIso) || str_contains($owner, $thisM)) {
                                $monthVouchers++;
                                $monthIncome += $price;
                            }
                        }

                        $res = [
                            'today_vouchers' => (int) $todayVouchers,
                            'today_income'   => (float) $todayIncome,
                            'month_vouchers' => (int) $monthVouchers,
                            'month_income'   => (float) $monthIncome,
                            'updated_at'     => now()->format('Y-m-d H:i:s'),
                            'currency'       => $currency,
                        ];

                        \Illuminate\Support\Facades\Cache::put($cacheKey, $res, now()->addMinutes(2));
                        return $res;
                    }
                }
            } catch (\Throwable $e) {}
        }

        return [
            'today_vouchers' => (int) $todayVouchers,
            'today_income'   => (float) $todayIncome,
            'month_vouchers' => (int) $monthVouchers,
            'month_income'   => (float) $monthIncome,
            'updated_at'     => now()->format('Y-m-d H:i:s'),
            'currency'       => $currency,
        ];
    }
}
