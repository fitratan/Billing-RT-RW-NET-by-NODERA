<?php

namespace App\Console\Commands;

use App\Models\BookkeepingSubscription;
use App\Models\VpnTransaction;
use App\Services\BookkeepingProvisioner;
use Illuminate\Console\Command;

class BookkeepingAutoRenew extends Command
{
    protected $signature = 'bookkeeping:auto-renew';
    protected $description = 'Auto-renew Pembukuan: debit saldo bulanan, expired, lalu suspend setelah 7 hari masa tenggang';

    public function handle(): int
    {
        $now = now();
        $graceDays = 7;
        $price = (float) config('bookkeeping.monthly_price', 10000);

        $renewed = 0;
        $expired = 0;
        $suspended = 0;

        // 1. EXPIRY: langganan aktif yang lewat masa aktif
        $activeSubs = BookkeepingSubscription::where('status', 'ACTIVE')
            ->where('expires_at', '<=', $now)
            ->with('user')
            ->get();

        foreach ($activeSubs as $sub) {
            $user = $sub->user;

            // Auto debit hanya kalau toggle ON + punya user VPN + saldo cukup
            if ($sub->auto_renew && $user && $user->total_saldo >= $price) {
                $saldoBefore = $user->total_saldo;

                if ($user->saldo >= $price) {
                    $user->decrement('saldo', $price);
                } else {
                    $remaining = $price - $user->saldo;
                    $user->update(['saldo' => 0, 'bonus_saldo' => max(0, $user->bonus_saldo - $remaining)]);
                }

                $base = $sub->expires_at && $sub->expires_at->isFuture() ? $sub->expires_at : $now;
                $sub->expires_at = $base->copy()->addMonth();
                $sub->last_billed_at = $now;
                $sub->saldo_deducted = ($sub->saldo_deducted ?? 0) + $price;
                $sub->save();

                // Perbarui lisensi di folder (masa aktif baru)
                try {
                    (new BookkeepingProvisioner)->writeLicense(
                        (new BookkeepingProvisioner)->targetPath($sub->subdomain),
                        $sub,
                        $sub->expires_at
                    );
                } catch (\Exception $e) {
                    $this->error("Gagal tulis lisensi {$sub->subdomain}: " . $e->getMessage());
                }

                VpnTransaction::create([
                    'vpn_user_id' => $user->id,
                    'type' => 'renew',
                    'amount' => $price,
                    'saldo_before' => $saldoBefore,
                    'saldo_after' => $user->fresh()->total_saldo,
                    'description' => "Perpanjangan otomatis Pembukuan: {$sub->subdomain}",
                ]);

                $renewed++;
                $this->info("Renewed Pembukuan: {$sub->subdomain}");
                continue;
            }

            // Tidak ter-perpanjang → EXPIRED
            $sub->update([
                'status' => 'EXPIRED',
                'expired_grace_at' => $now,
            ]);

            $expired++;
            $this->warn("Expired Pembukuan: {$sub->subdomain}");
        }

        // 2. SUSPEND: lewat masa tenggang 7 hari
        $suspendCutoff = $now->copy()->subDays($graceDays);
        $expiredSubs = BookkeepingSubscription::where('status', 'EXPIRED')
            ->where('expired_grace_at', '<=', $suspendCutoff)
            ->get();

        foreach ($expiredSubs as $sub) {
            $sub->update([
                'status' => 'SUSPENDED',
                'suspended_at' => $now,
            ]);

            $suspended++;
            $this->info("Suspended Pembukuan: {$sub->subdomain}");
        }

        $this->info("Bookkeeping auto-renew completed: {$renewed} renewed, {$expired} expired, {$suspended} suspended.");
        return 0;
    }
}
