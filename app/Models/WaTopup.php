<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WaTopup extends Model
{
    use HasFactory;

    protected $table = 'wa_topups';

    protected $fillable = [
        'merchant_id',
        'invoice_number',
        'type',
        'amount',
        'admin_fee',
        'total_amount',
        'payment_method',
        'payment_status',
        'telegram_chat_id',
        'telegram_message_id',
        'payment_reference',
        'qris_content',
        'paid_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    public static function generateInvoiceNumber(): string
    {
        return 'INV-WA-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Settle WaTopup (Mark as Paid & Apply Benefits)
     */
    public static function settleTopup(self $topup, string $reference = 'Superadmin / Payment Gateway'): bool
    {
        if ($topup->payment_status === 'paid') {
            return true;
        }

        $topup->update([
            'payment_status'    => 'paid',
            'paid_at'           => now(),
            'payment_reference' => $reference,
        ]);

        $merchant = $topup->merchant;
        if ($merchant) {
            if ($topup->type === 'pro_subscription') {
                $merchant->plan_type = 'pro';
                $merchant->device_limit = max(5, $merchant->device_limit);
                $merchant->subscription_expires_at = ($merchant->subscription_expires_at && Carbon::parse($merchant->subscription_expires_at)->isFuture())
                    ? Carbon::parse($merchant->subscription_expires_at)->addMonth()
                    : now()->addMonth();
            } elseif ($topup->type === 'device_slot') {
                $merchant->device_limit += 1;
            } elseif ($topup->type === 'credit_topup') {
                $merchant->credit_balance += (float) $topup->amount;
            }
            $merchant->save();

            // Distribute referral commission & downline bonus if merchant registered with referral
            try {
                \App\Services\ReferralService::distributeWaTopupCommission($topup);
            } catch (\Throwable $e) {
                Log::warning('[WaTopup] Referral commission failed on WA Topup settle: ' . $e->getMessage());
            }

            // WhatsApp Notification to Merchant on SUCCESS
            try {
                if (!empty($merchant->phone)) {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled()) {
                        $planDesc = $topup->type === 'pro_subscription' ? 'Upgrade Paket Pro Unlimited' : ($topup->type === 'device_slot' ? 'Penambahan Slot Device' : 'Deposit Saldo WhatsApp');
                        $userMsg = "✅ *PEMBAYARAN WA GATEWAY BERHASIL*\n\n"
                            . "Halo *{$merchant->name}*,\n"
                            . "Pembayaran untuk invoice *{$topup->invoice_number}* telah kami terima dan diverifikasi.\n\n"
                            . "📋 *Rincian*\n"
                            . "• Layanan: *{$planDesc}*\n"
                            . "• Nominal: *Rp " . number_format((float)$topup->total_amount, 0, ',', '.') . "*\n"
                            . "• Status Paket: *" . strtoupper($merchant->plan_type) . "*\n"
                            . "• Limit Slot Perangkat: *{$merchant->device_limit} Device*\n"
                            . "• Saldo Tersedia: *Rp " . number_format((float)$merchant->credit_balance, 0, ',', '.') . "*\n\n"
                            . "Fitur/saldo Anda telah aktif dan dapat langsung digunakan.\n\n"
                            . "Terima kasih telah menggunakan *NODERA WhatsApp Gateway*.";
                        $wa->sendMessage($merchant->phone, $userMsg);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[WaTopup] WhatsApp success notification failed: ' . $e->getMessage());
            }
        }

        // Telegram notification and retraction
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                if (!empty($topup->telegram_message_id)) {
                    $telegram->retractMessage((int) $topup->telegram_message_id, $topup->telegram_chat_id);
                }

                $merchant = $topup->merchant;
                $merchantName = $merchant ? $merchant->name : 'Merchant';
                $merchantCode = $merchant ? $merchant->merchant_code : '-';
                $planDesc = $topup->metadata['plan_name'] ?? ($topup->type === 'pro_subscription' ? 'Pro Unlimited' : ($topup->type === 'device_slot' ? '+1 Slot Device' : 'Deposit Saldo'));
                $formattedAmount = number_format((float)$topup->total_amount, 0, ',', '.');

                $msg = "<b>✅ PEMBAYARAN WA GATEWAY BERHASIL (DISETUJUI) — NODERA</b>\n\n"
                    . "┌ <b>Detail Transaksi</b>\n"
                    . "├ Merchant: <b>" . htmlspecialchars($merchantName) . "</b> ({$merchantCode})\n"
                    . "├ No. Invoice: <code>{$topup->invoice_number}</code>\n"
                    . "├ Layanan: " . htmlspecialchars($planDesc) . "\n"
                    . "├ Nominal: <b>Rp {$formattedAmount}</b>\n"
                    . "├ Metode: " . htmlspecialchars($topup->payment_method ?: 'QRIS Otomatis') . "\n"
                    . "├ Status: ✅ <b>SUKSES / LUNAS (APPROVED)</b>\n"
                    . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                $telegram->sendAdminNotification($msg, 'topup', 'HTML');
            }
        } catch (\Throwable $e) {
            Log::warning('[WaTopup] Telegram success alert failed: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Reject WaTopup
     */
    public static function rejectTopup(self $topup, string $reason = 'Ditolak oleh Admin'): bool
    {
        if ($topup->payment_status !== 'pending') {
            return false;
        }

        $topup->update([
            'payment_status'    => 'rejected',
            'payment_reference' => $reason,
        ]);

        $merchant = $topup->merchant;
        if ($merchant && !empty($merchant->phone)) {
            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled()) {
                    $userMsg = "❌ *PERMINTAAN TOPUP DITOLAK*\n\n"
                        . "Halo *{$merchant->name}*,\n"
                        . "Permintaan topup / upgrade WA Gateway dengan invoice *{$topup->invoice_number}* sebesar *Rp " . number_format((float)$topup->total_amount, 0, ',', '.') . "* tidak dapat disetujui.\n\n"
                        . "Alasan: _{$reason}_\n\n"
                        . "Silakan periksa kembali atau buat permintaan baru melalui dashboard.\n\n"
                        . "Terima kasih,\n*NODERA WhatsApp Gateway*";
                    $wa->sendMessage($merchant->phone, $userMsg);
                }
            } catch (\Throwable $e) {
                Log::warning('[WaTopup] WhatsApp reject notification failed: ' . $e->getMessage());
            }
        }

        // Telegram notification and retraction on rejection
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                if (!empty($topup->telegram_message_id)) {
                    $telegram->retractMessage((int) $topup->telegram_message_id, $topup->telegram_chat_id);
                }

                $merchant = $topup->merchant;
                $merchantName = $merchant ? $merchant->name : 'Merchant';
                $merchantCode = $merchant ? $merchant->merchant_code : '-';
                $formattedAmount = number_format((float)$topup->total_amount, 0, ',', '.');

                $msg = "<b>🚫 TOPUP WA GATEWAY DITOLAK — NODERA</b>\n\n"
                    . "┌ <b>Detail Transaksi</b>\n"
                    . "├ Merchant: <b>" . htmlspecialchars($merchantName) . "</b> ({$merchantCode})\n"
                    . "├ No. Invoice: <code>{$topup->invoice_number}</code>\n"
                    . "├ Nominal: <b>Rp {$formattedAmount}</b>\n"
                    . "├ Status: 🚫 <b>DITOLAK (REJECTED)</b>\n"
                    . "├ Alasan: " . htmlspecialchars($reason) . "\n"
                    . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                $telegram->sendAdminNotification($msg, 'topup', 'HTML');
            }
        } catch (\Throwable $e) {
            Log::warning('[WaTopup] Telegram reject alert failed: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Remove inline keyboard from Telegram message
     */
    public static function dismissTelegramNotification(self $topup): void
    {
        if (empty($topup->telegram_message_id)) {
            return;
        }

        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                $chatIds = !empty($topup->telegram_chat_id) ? [$topup->telegram_chat_id] : $telegram->getSuperadminChatIds();
                foreach ($chatIds as $cid) {
                    try {
                        $telegram->editMessageReplyMarkup((string) $cid, (int) $topup->telegram_message_id, ['inline_keyboard' => []]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[WaTopup] Telegram dismiss markup failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract (delete) initial Telegram message and send cancellation alert to Superadmin.
     */
    public static function retractAndNotifyCancellation(self $topup, string $reason = 'Pengguna membatalkan pembayaran / menutup checkout.'): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            if (!empty($topup->telegram_message_id)) {
                $telegram->retractMessage((int) $topup->telegram_message_id, $topup->telegram_chat_id);
            }

            // 2. Send cancellation notice to Superadmin
            $merchant = $topup->merchant;
            $merchantName = $merchant ? $merchant->name : 'Merchant';
            $merchantCode = $merchant ? $merchant->merchant_code : '-';
            $formattedAmount = number_format((float)$topup->total_amount, 0, ',', '.');
            $planDesc = $topup->metadata['plan_name'] ?? ($topup->type === 'pro_subscription' ? 'Pro Unlimited' : ($topup->type === 'device_slot' ? '+1 Slot Device' : 'Deposit Saldo'));

            $msg = "❌ <b>WA GATEWAY — TOPUP DIBATALKAN</b>\n\n"
                . "┌ <b>Detail Transaksi</b>\n"
                . "├ Merchant: " . htmlspecialchars($merchantName) . " (" . htmlspecialchars($merchantCode) . ")\n"
                . "├ No. Invoice: <code>" . htmlspecialchars($topup->invoice_number) . "</code>\n"
                . "├ Layanan: " . htmlspecialchars($planDesc) . "\n"
                . "├ Nominal: Rp " . $formattedAmount . "\n"
                . "├ Status: ❌ <b>DIBATALKAN (CANCELLED)</b>\n"
                . "└ Waktu Pembatalan: " . now()->format('d/m/Y H:i:s') . "\n\n"
                . "<i>" . htmlspecialchars($reason) . "</i>";

            $telegram->sendAdminNotification($msg, 'topup', 'HTML');
        } catch (\Throwable $e) {
            Log::warning('[WaTopup] Failed to retract and notify cancellation: ' . $e->getMessage());
        }
    }
}
