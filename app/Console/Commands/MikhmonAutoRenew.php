<?php

namespace App\Console\Commands;

use App\Models\MikhmonSubscription;
use App\Models\VpnTransaction;
use App\Services\MikhmonProvisioner;
use Illuminate\Console\Command;

class MikhmonAutoRenew extends Command
{
    protected $signature = 'mikhmon:auto-renew';
    protected $description = 'Auto-renew Mikhmon: debit saldo bulanan, expired, lalu suspend setelah 7 hari masa tenggang';

    public function handle(): int
    {
        $now = now();
        $graceDays = 7;
        $price = (float) config('mikhmon.monthly_price');

        $renewed = 0;
        $expired = 0;
        $suspended = 0;

        // 1. EXPIRY & AUTO-RENEW: langganan aktif yang expired atau langganan expired dengan auto_renew ON
        $subsToProcess = MikhmonSubscription::where(function ($q) use ($now) {
                $q->where('status', 'ACTIVE')->where('expires_at', '<=', $now);
            })
            ->orWhere(function ($q) use ($now) {
                $q->where('status', 'EXPIRED')->where('auto_renew', true);
            })
            ->with('user')
            ->get();

        foreach ($subsToProcess as $sub) {
            $user = $sub->user;
            $unitPrice = (float) $sub->price > 0 ? (float) $sub->price : $price;

            // Auto debit hanya kalau toggle ON + punya user VPN + saldo cukup
            if ($sub->auto_renew && $user && $user->total_saldo >= $unitPrice) {
                $saldoBefore = $user->total_saldo;

                if ($user->saldo >= $unitPrice) {
                    $user->decrement('saldo', $unitPrice);
                } else {
                    $remaining = $unitPrice - $user->saldo;
                    $user->update(['saldo' => 0, 'bonus_saldo' => max(0, $user->bonus_saldo - $remaining)]);
                }

                $base = $sub->expires_at && $sub->expires_at->isFuture() ? $sub->expires_at : $now;
                $sub->expires_at = $base->copy()->addMonth();
                $sub->last_billed_at = $now;
                $sub->status = 'ACTIVE';
                $sub->expired_grace_at = null;
                $sub->suspended_at = null;
                $sub->saldo_deducted = ($sub->saldo_deducted ?? 0) + $unitPrice;
                $sub->save();

                // perbarui lisensi di folder (masa aktif baru)
                try {
                    (new MikhmonProvisioner)->writeLicense(
                        (new MikhmonProvisioner)->targetPath($sub->subdomain),
                        $sub
                    );
                } catch (\Exception $e) {
                    $this->error("Gagal tulis lisensi {$sub->subdomain}: " . $e->getMessage());
                }

                VpnTransaction::create([
                    'vpn_user_id' => $user->id,
                    'type' => 'renew',
                    'amount' => $unitPrice,
                    'saldo_before' => $saldoBefore,
                    'saldo_after' => $user->fresh()->total_saldo,
                    'description' => "Perpanjangan otomatis Mikhmon: {$sub->subdomain}",
                    'reference' => $sub->subdomain,
                ]);

                try {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled()) {
                        $wa->sendMikhmonRenewed($sub, $user, $unitPrice, $user->fresh()->total_saldo);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("WhatsApp Mikhmon auto-renew notification error: {$sub->subdomain} - " . $e->getMessage());
                }

                $renewed++;
                $this->info("Renewed Mikhmon: {$sub->subdomain}");
                continue;
            }

            // Jika status masih ACTIVE tapi sudah expired & tidak ter-perpanjang → EXPIRED
            if ($sub->status === 'ACTIVE' && $sub->expires_at <= $now) {
                $sub->update([
                    'status' => 'EXPIRED',
                    'expired_grace_at' => $now,
                ]);

                try {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled()) {
                        $wa->sendMikhmonExpired($sub, $user);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("WhatsApp Mikhmon expired notification error: {$sub->subdomain} - " . $e->getMessage());
                }

                $expired++;
                $this->warn("Expired Mikhmon: {$sub->subdomain}");
            }
        }

        // 2. SUSPEND: lewat masa tenggang 7 hari
        $suspendCutoff = $now->copy()->subDays($graceDays);
        $expiredSubs = MikhmonSubscription::where('status', 'EXPIRED')
            ->where('expired_grace_at', '<=', $suspendCutoff)
            ->get();

        foreach ($expiredSubs as $sub) {
            $sub->update([
                'status' => 'SUSPENDED',
                'suspended_at' => $now,
            ]);

            $suspended++;
            $this->info("Suspended Mikhmon: {$sub->subdomain}");
        }

        // 3. Send expiring reminders (H-3, H-1) for active subscriptions
        $this->sendExpiringReminders();

        $this->info("Mikhmon auto-renew completed: {$renewed} renewed, {$expired} expired, {$suspended} suspended.");
        return 0;
    }

    /**
     * Send expiring reminder notifications (H-3, Hari-H) before Mikhmon instances expire.
     * If auto-renew is enabled and user has sufficient balance, reminder is skipped.
     */
    protected function sendExpiringReminders(): void
    {
        $now = now();
        $expiringSubs = MikhmonSubscription::where('status', 'ACTIVE')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays(3)->endOfDay())
            ->with('user')
            ->get();

        foreach ($expiringSubs as $sub) {
            $user = $sub->user;
            if (!$user || empty($user->phone)) {
                continue;
            }

            $unitPrice = $sub->price > 0 ? (float) $sub->price : 15000;

            // Jika Auto-Renew aktif dan saldo cukup, tidak perlu dikirimi notif pengingat
            if ($sub->auto_renew && (float) $user->total_saldo >= $unitPrice) {
                continue;
            }

            $daysLeft = (int) ceil($now->floatDiffInDays(\Carbon\Carbon::parse($sub->expires_at), false));
            if (!in_array($daysLeft, [0, 3])) {
                continue;
            }

            $cacheKey = "mikhmon_reminder_sent_{$sub->id}_{$daysLeft}_" . $now->format('Y-m-d');
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                continue;
            }

            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled()) {
                    $wa->sendMikhmonExpiringReminder($sub, $user, $daysLeft);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addDay());
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("WhatsApp Mikhmon reminder error: {$sub->subdomain} - " . $e->getMessage());
            }
        }
    }
}
