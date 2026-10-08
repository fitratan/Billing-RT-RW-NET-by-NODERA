<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\VpnTransaction;
use App\Models\VpnUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddonService
{
    /**
     * Check if a tenant can access a specific addon feature.
     */
    public function canAccess(int|Tenant|null $tenant, string $addonSlug): bool
    {
        return TenantAddon::hasAccess($tenant, $addonSlug);
    }

    /**
     * Return list of route paths locked for a tenant because they haven't subscribed to the required paid Addon.
     *
     * @return string[]
     */
    public static function getLockedRoutesForTenant(int|Tenant|null $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        if (!$tenantId) {
            return [];
        }

        try {
            $paidAddons = Addon::withoutGlobalScopes()
                ->where('is_active', true)
                ->where('price', '>', 0)
                ->get();

            if ($paidAddons->isEmpty()) {
                return [];
            }

            $routeMap = [
                'mikhmon_online'        => ['/admin/mikhmon'],
                'mikhmon'               => ['/admin/mikhmon'],
                'genieacs_tr069'        => ['/admin/genieacs'],
                'genieacs'              => ['/admin/genieacs'],
                'genieacs_management'   => ['/admin/genieacs'],
                'mapping_gis'           => ['/admin/map', '/admin/odp'],
                'map'                   => ['/admin/map', '/admin/odp'],
                'olt_onu'               => ['/admin/olt', '/admin/onus'],
                'olt'                   => ['/admin/olt', '/admin/onus'],
                'olt_management'        => ['/admin/olt', '/admin/onus'],
                'radius_server'         => ['/admin/radius'],
                'radius'                => ['/admin/radius'],
                'mikrotik_tools'        => ['/tools/mikrotik', '/mikrotik-tools'],
                'arp_binding'           => ['/admin/arp', '/admin/arp-binding'],
                'arp'                   => ['/admin/arp', '/admin/arp-binding'],
                'telegram_bot'          => ['/admin/telegram'],
                'telegram'              => ['/admin/telegram'],
                'whatsapp_notification' => ['/admin/whatsapp-templates', '/admin/broadcast'],
                'whatsapp'              => ['/admin/whatsapp-templates', '/admin/broadcast'],
                'toko_online'           => ['/admin/shop/products', '/admin/shop/orders', '/admin/landing-settings'],
                'shop'                  => ['/admin/shop/products', '/admin/shop/orders', '/admin/landing-settings'],
                'inventory'             => ['/admin/inventory'],
                'api_webhook'           => ['/admin/api-apps'],
                'api_apps'              => ['/admin/api-apps'],
                'employee_management'   => ['/admin/employees', '/admin/agents', '/admin/collectors', '/admin/technicians'],
                'employees'             => ['/admin/employees', '/admin/agents', '/admin/collectors', '/admin/technicians'],
                'trouble_ticket'        => ['/admin/trouble', '/admin/tickets'],
                'trouble'               => ['/admin/trouble', '/admin/tickets'],
            ];

            $activeSubAddonIds = TenantAddon::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('status', ['approved', 'active'])
                ->where(function ($q) {
                    $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                })
                ->pluck('addon_id')
                ->toArray();

            $lockedRoutes = [];
            foreach ($paidAddons as $addon) {
                if (!in_array($addon->id, $activeSubAddonIds, true)) {
                    $slug = $addon->slug;
                    if (!empty($routeMap[$slug])) {
                        foreach ($routeMap[$slug] as $r) {
                            $lockedRoutes[] = $r;
                        }
                    }
                    if (!empty($addon->route_name) && !in_array($addon->route_name, ['/admin/addons', '/dashboard', '/admin/semua-fitur', '/admin/my-settings', '/admin/billing/customers', '/admin/billing/invoices', '/admin/billing/packages', '/admin/payments/gateway', '/admin/payments/qris', '/admin/finance', '/admin/expenses', '/admin/mikrotik/routers', '/admin/mikrotik/profiles', '/admin/top-bandwidth', '/admin/notifications'])) {
                        $lockedRoutes[] = $addon->route_name;
                    }
                }
            }

            return array_values(array_unique($lockedRoutes));
        } catch (\Throwable $e) {
            Log::warning('[AddonService] getLockedRoutesForTenant warning: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return list of addon slugs locked for a tenant because they haven't subscribed to the required paid Addon.
     *
     * @return string[]
     */
    public static function getLockedSlugsForTenant(int|Tenant|null $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        if (!$tenantId) {
            return [];
        }

        try {
            $paidAddons = Addon::withoutGlobalScopes()
                ->where('is_active', true)
                ->where('price', '>', 0)
                ->get();

            if ($paidAddons->isEmpty()) {
                return [];
            }

            $activeSubAddonIds = TenantAddon::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('status', ['approved', 'active'])
                ->where(function ($q) {
                    $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                })
                ->pluck('addon_id')
                ->toArray();

            $lockedSlugs = [];
            foreach ($paidAddons as $addon) {
                if (!in_array($addon->id, $activeSubAddonIds, true)) {
                    $lockedSlugs[] = $addon->slug;
                }
            }

            return array_values(array_unique($lockedSlugs));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Purchase / Activate / Extend an Addon via master VpnUser Saldo.
     *
     * @return array{success: bool, message?: string, tenant_addon?: TenantAddon, error?: string}
     */
    public function purchaseWithBalance(Tenant $tenant, Addon $addon, bool $autoRenew = true): array
    {
        $price = (float) $addon->price;
        $vpnUserId = $tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null);

        $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
        if (!$vpnUser) {
            $vpnUser = VpnUser::where('tenant_id', $tenant->id)
                ->orWhere('email', $tenant->email)
                ->orWhere('username', $tenant->slug)
                ->first();

            if (!$vpnUser) {
                $vpnUser = VpnUser::create([
                    'tenant_id'   => $tenant->id,
                    'username'    => $tenant->slug ?: ('tenant_' . $tenant->id),
                    'email'       => $tenant->email ?: ($tenant->slug . '@nodera.cloud'),
                    'password'    => bcrypt('nodera' . rand(1000, 9999)),
                    'saldo'       => 0,
                    'bonus_saldo' => 0,
                ]);
            }

            $tenant->vpn_user_id = $vpnUser->id;
            $tenant->saveQuietly();
        }

        if ($price > 0 && (float) $vpnUser->total_saldo < $price) {
            $currFmt = number_format($vpnUser->total_saldo, 0, ',', '.');
            $priceFmt = number_format($price, 0, ',', '.');
            return [
                'success' => false,
                'error'   => "Saldo tidak mencukupi (Tersedia: Rp {$currFmt}, Biaya Add-on: Rp {$priceFmt}). Silakan lakukan Top Up Saldo terlebih dahulu.",
            ];
        }

        try {
            $tenantAddon = DB::transaction(function () use ($tenant, $addon, $vpnUser, $price, $autoRenew) {
                $userLocked = VpnUser::lockForUpdate()->find($vpnUser->id);
                if ($price > 0 && (float) $userLocked->total_saldo < $price) {
                    throw new \Exception("Saldo tidak mencukupi saat proses transaksi.");
                }

                // Deduct balance
                if ($price > 0) {
                    $bonus = (float) $userLocked->bonus_saldo;
                    $deductFromBonus = min($bonus, $price);
                    $deductFromMain = $price - $deductFromBonus;

                    if ($deductFromBonus > 0) {
                        $userLocked->decrement('bonus_saldo', $deductFromBonus);
                    }
                    if ($deductFromMain > 0) {
                        $userLocked->decrement('saldo', $deductFromMain);
                    }

                    VpnTransaction::create([
                        'vpn_user_id' => $userLocked->id,
                        'type'        => 'DEBIT',
                        'amount'      => $price,
                        'description' => "Aktivasi Add-on {$addon->name} ({$tenant->slug})",
                    ]);
                }

                // Determine expiration
                $cycle = strtolower($addon->billing_cycle ?? 'monthly');
                $tenantAddon = TenantAddon::withoutGlobalScopes()
                    ->firstOrNew([
                        'tenant_id' => $tenant->id,
                        'addon_id'  => $addon->id,
                    ]);

                $newExpiredAt = null;
                if ($cycle === 'monthly') {
                    if ($tenantAddon->is_active && $tenantAddon->expired_at && $tenantAddon->expired_at->isFuture()) {
                        $newExpiredAt = $tenantAddon->expired_at->addDays(30);
                    } else {
                        $newExpiredAt = now()->addDays(30);
                    }
                } elseif ($cycle === 'yearly') {
                    if ($tenantAddon->is_active && $tenantAddon->expired_at && $tenantAddon->expired_at->isFuture()) {
                        $newExpiredAt = $tenantAddon->expired_at->addDays(365);
                    } else {
                        $newExpiredAt = now()->addDays(365);
                    }
                } elseif ($cycle === 'lifetime') {
                    $newExpiredAt = null;
                }

                $tenantAddon->fill([
                    'is_active'      => true,
                    'status'         => 'approved',
                    'auto_renew'     => $autoRenew,
                    'expired_at'     => $newExpiredAt,
                    'paid_at'        => now(),
                    'last_billed_at' => now(),
                    'total_amount'   => $price,
                ]);
                $tenantAddon->save();

                return $tenantAddon;
            });

            // Post-activation provisioning hook if needed (e.g. Mikhmon)
            if (in_array($addon->slug, ['mikhmon_online', 'mikhmon'])) {
                try {
                    MikhmonProvisioner::syncForTenant($tenant, $tenantAddon);
                } catch (\Throwable $e) {
                    Log::warning('[AddonService] Auto-deploy Mikhmon warning: ' . $e->getMessage());
                }
            }

            return [
                'success'      => true,
                'message'      => "Add-on {$addon->name} berhasil diaktifkan dengan sistem Potong Saldo!",
                'tenant_addon' => $tenantAddon,
            ];
        } catch (\Throwable $e) {
            Log::error('[AddonService] Purchase failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Gagal memproses aktivasi add-on: ' . $e->getMessage(),
            ];
        }
    }
}
