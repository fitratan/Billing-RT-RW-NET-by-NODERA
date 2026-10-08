<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NoderaPayMerchantWithdrawal extends Model
{
    use HasFactory;

    protected $table = 'nodera_pay_merchant_withdrawals';

    protected $fillable = [
        'merchant_id',
        'withdrawal_code',
        'amount',
        'fee',
        'net_amount',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'proof_image',
        'status',
        'telegram_chat_id',
        'telegram_message_id',
        'admin_notes',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'fee'          => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(NoderaPayMerchant::class, 'merchant_id');
    }

    public static function generateWithdrawalCode(): string
    {
        return 'NPWD-' . date('Ymd') . '-' . strtoupper(Str::random(5));
    }

    /**
     * Remove inline keyboard / buttons from the initial Telegram settlement message.
     */
    public static function dismissTelegramNotification(self $wd): void
    {
        if (empty($wd->telegram_message_id)) {
            return;
        }

        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                $chatIds = !empty($wd->telegram_chat_id) ? [$wd->telegram_chat_id] : $telegram->getSuperadminChatIds();
                foreach ($chatIds as $cid) {
                    try {
                        $telegram->editMessageReplyMarkup((string) $cid, (int) $wd->telegram_message_id, ['inline_keyboard' => []]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[NoderaPayWithdrawal] Dismiss markup failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract initial Telegram message and notify Superadmin on settlement approval.
     */
    public static function retractAndNotifyApproval(self $wd, ?string $notes = null): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            if (!empty($wd->telegram_message_id)) {
                $telegram->retractMessage((int) $wd->telegram_message_id, $wd->telegram_chat_id);
            }

            $merchant = $wd->merchant;
            $merchantName = $merchant ? $merchant->name : 'Merchant';
            $msg = "✅ <b>PENARIKAN SALDO DISETUJUI & DITRANSFER — NODERA PAY</b>\n\n"
                . "┌ <b>Detail Payout</b>\n"
                . "├ Merchant: <b>" . htmlspecialchars($merchantName) . "</b>\n"
                . "├ Kode: <code>{$wd->withdrawal_code}</code>\n"
                . "├ Nominal: <b>Rp " . number_format((float) $wd->amount, 0, ',', '.') . "</b>\n"
                . "├ Net Cair: <b>Rp " . number_format((float) $wd->net_amount, 0, ',', '.') . "</b>\n"
                . "├ Rekening Tujuan: {$wd->bank_name} - <code>{$wd->bank_account_number}</code> a.n {$wd->bank_account_name}\n"
                . "├ Status: ✅ <b>SUKSES (COMPLETED)</b>\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');
            if ($notes) {
                $msg .= "\n\n<i>Catatan: " . htmlspecialchars($notes) . "</i>";
            }

            $telegram->sendAdminNotification($msg, 'withdrawal', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[NoderaPayWithdrawal] Retract and notify approval failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract initial Telegram message and notify Superadmin on settlement rejection.
     */
    public static function retractAndNotifyRejection(self $wd, string $reason): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            if (!empty($wd->telegram_message_id)) {
                $telegram->retractMessage((int) $wd->telegram_message_id, $wd->telegram_chat_id);
            }

            $merchant = $wd->merchant;
            $merchantName = $merchant ? $merchant->name : 'Merchant';
            $msg = "❌ <b>PENARIKAN SALDO DITOLAK & REFUND — NODERA PAY</b>\n\n"
                . "┌ <b>Detail Payout</b>\n"
                . "├ Merchant: <b>" . htmlspecialchars($merchantName) . "</b>\n"
                . "├ Kode: <code>{$wd->withdrawal_code}</code>\n"
                . "├ Nominal: <b>Rp " . number_format((float) $wd->amount, 0, ',', '.') . "</b>\n"
                . "├ Rekening: {$wd->bank_name} - <code>{$wd->bank_account_number}</code> a.n {$wd->bank_account_name}\n"
                . "├ Status: ❌ <b>DITOLAK (REJECTED)</b>\n"
                . "├ Alasan: " . htmlspecialchars($reason) . "\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            $telegram->sendAdminNotification($msg, 'withdrawal', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[NoderaPayWithdrawal] Retract and notify rejection failed: ' . $e->getMessage());
        }
    }
}
