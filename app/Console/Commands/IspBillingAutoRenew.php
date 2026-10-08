<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\VpnTransaction;
use App\Models\VpnUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class IspBillingAutoRenew extends Command
{
    protected $signature = 'isp-billing:auto-renew';
    protected $description = 'Auto-renew Cloud SaaS Billing instances: debit saldo bulanan, expired, dan perpanjang otomatis';

    public function handle(): int
    {
        $now = now();

        $renewed = 0;
        $expired = 0;

        $tenantsToProcess = Tenant::where(function ($q) {
                if (Schema::hasColumn('tenants', 'vpn_user_id')) {
                    $q->whereNotNull('vpn_user_id');
                }
                $q->orWhereNotNull('settings->vpn_user_id');
            })
            ->where(function ($q) use ($now) {
                $q->where('is_active', true)->where('expired_at', '<=', $now)
                  ->orWhere(function ($sq) {
                      $sq->where('is_active', false);
                  });
            })
            ->get();

        foreach ($tenantsToProcess as $tenant) {
            $vpnUserId = $tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null);
            $user = $vpnUserId ? VpnUser::find($vpnUserId) : ($tenant->email ? VpnUser::where('email', $tenant->email)->first() : null);

            $autoRenew = (bool) ($tenant->auto_renew ?? ($tenant->settings['auto_renew'] ?? false));

            // Resolve package price
            $packageId = $tenant->settings['package_id'] ?? null;
            $package = $packageId ? Package::withoutGlobalScopes()->whereNull('tenant_id')->find($packageId) : null;
            if (!$package) {
                $package = Package::withoutGlobalScopes()->whereNull('tenant_id')->where('monthly_price', '>', 0)->orderBy('monthly_price')->first();
            }

            $price = (float) ($package ? ($package->monthly_price ?: $package->price) : 30000);

            // Auto debit if toggle is ON, user exists, and user has sufficient saldo
            if ($autoRenew && $user && (float) $user->total_saldo >= $price && $price > 0) {
                try {
                    $didRenew = false;
                    DB::transaction(function () use ($user, $tenant, $price, $now, &$didRenew) {
                        $vpnUser = VpnUser::where('id', $user->id)->lockForUpdate()->first();
                        if ((float) $vpnUser->total_saldo < $price) {
                            return;
                        }

                        $saldoBefore = (float) $vpnUser->total_saldo;

                        if ((float) $vpnUser->saldo >= $price) {
                            $vpnUser->decrement('saldo', $price);
                        } else {
                            $remaining = $price - (float) $vpnUser->saldo;
                            $vpnUser->update([
                                'saldo' => 0,
                                'bonus_saldo' => max(0, (float) $vpnUser->bonus_saldo - $remaining),
                            ]);
                        }

                        $vpnUser->refresh();
                        $saldoAfter = (float) $vpnUser->total_saldo;

                        $base = ($tenant->expired_at && $tenant->expired_at->isFuture()) ? $tenant->expired_at : $now;
                        $tenant->expired_at = $base->copy()->addMonth();
                        $tenant->is_active = true;
                        $tenant->save();

                        VpnTransaction::create([
                            'vpn_user_id' => $vpnUser->id,
                            'type' => 'DEBIT',
                            'amount' => $price,
                            'saldo_before' => $saldoBefore,
                            'saldo_after' => $saldoAfter,
                            'description' => "Perpanjangan otomatis Cloud SaaS: {$tenant->slug}",
                            'reference' => 'RENEW-AUTO-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        ]);

                        $didRenew = true;
                    });

                    if ($didRenew) {
                        try {
                            $wa = \App\Services\WhatsappService::forSuperadmin();
                            if ($wa->isEnabled()) {
                                $wa->sendTenantSubscriptionRenewed($tenant, $price);
                            }
                        } catch (\Throwable $e) {
                            Log::warning("WhatsApp SaaS renewed notification error: {$tenant->slug} - " . $e->getMessage());
                        }

                        $renewed++;
                        $this->info("Renewed Cloud SaaS: {$tenant->slug}");
                        continue;
                    }
                } catch (\Throwable $e) {
                    Log::error("Auto renew error for tenant {$tenant->slug}: " . $e->getMessage());
                }
            }

            // If not renewed and expired -> mark inactive (expired)
            if ($tenant->is_active && $tenant->expired_at && $tenant->expired_at <= $now) {
                $tenant->is_active = false;
                $tenant->save();

                try {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled()) {
                        $wa->sendTenantSubscriptionExpired($tenant);
                    }
                } catch (\Throwable $e) {
                    Log::warning("WhatsApp SaaS expired notification error: {$tenant->slug} - " . $e->getMessage());
                }

                $expired++;
                $this->warn("Expired Cloud SaaS: {$tenant->slug}");
            }
        }

        // Send expiring reminders (H-7, H-3, H-1) for active SaaS tenants
        $this->sendTenantExpiringReminders();

        $this->info("Cloud SaaS auto-renew completed: {$renewed} renewed, {$expired} expired.");
        return 0;
    }

    /**
     * Send expiring reminder notifications (H-3, Hari-H) for active SaaS tenants.
     * If auto-renew is enabled and user has sufficient balance, reminder is skipped.
     */
    protected function sendTenantExpiringReminders(): void
    {
        $now = now();
        $expiringTenants = Tenant::where('is_active', true)
            ->whereNotNull('expired_at')
            ->where('expired_at', '>', $now)
            ->where('expired_at', '<=', $now->copy()->addDays(3)->endOfDay())
            ->get();

        foreach ($expiringTenants as $tenant) {
            $vpnUserId = $tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null);
            $user = $vpnUserId ? VpnUser::find($vpnUserId) : ($tenant->email ? VpnUser::where('email', $tenant->email)->first() : null);

            $autoRenew = (bool) ($tenant->auto_renew ?? ($tenant->settings['auto_renew'] ?? false));
            $packageId = $tenant->settings['package_id'] ?? null;
            $package = $packageId ? Package::withoutGlobalScopes()->whereNull('tenant_id')->find($packageId) : null;
            if (!$package) {
                $package = Package::withoutGlobalScopes()->whereNull('tenant_id')->where('monthly_price', '>', 0)->orderBy('monthly_price')->first();
            }
            $price = (float) ($package ? ($package->monthly_price ?: $package->price) : 30000);

            // Jika Auto-Renew aktif dan saldo mencukupi, tidak perlu dikirimi notifikasi
            if ($autoRenew && $user && (float) $user->total_saldo >= $price && $price > 0) {
                continue;
            }

            $phone = $tenant->phone ?? $user?->phone ?? ($tenant->settings['phone'] ?? null);
            if (empty($phone)) {
                continue;
            }

            $daysLeft = (int) ceil($now->floatDiffInDays(\Carbon\Carbon::parse($tenant->expired_at), false));
            if (!in_array($daysLeft, [0, 3])) {
                continue;
            }

            $cacheKey = "saas_tenant_reminder_sent_{$tenant->id}_{$daysLeft}_" . $now->format('Y-m-d');
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                continue;
            }

            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled()) {
                    $wa->sendTenantSubscriptionReminder($tenant, $daysLeft);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addDay());
                }
            } catch (\Throwable $e) {
                Log::warning("WhatsApp SaaS reminder error: {$tenant->slug} - " . $e->getMessage());
            }
        }
    }
}
