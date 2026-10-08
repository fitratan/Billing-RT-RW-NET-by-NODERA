<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_partner_id',
        'vpn_user_id',
        'type', // 'convert_saldo', 'bank_withdrawal'
        'amount',
        'fee',
        'net_amount',
        'bank_name',
        'account_number',
        'account_name',
        'status', // 'pending', 'approved', 'rejected', 'completed'
        'telegram_chat_id',
        'telegram_message_id',
        'admin_notes',
        'transfer_proof',
        'processed_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function partner()
    {
        return $this->belongsTo(ReferralPartner::class, 'referral_partner_id');
    }

    public function user()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function processedByAdmin()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Dismiss inline keyboard from the initial Telegram request message.
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
            \Illuminate\Support\Facades\Log::warning('[ReferralWithdrawal] Dismiss markup failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract initial Telegram message and notify Superadmin on referral withdrawal approval.
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

            $partner = $wd->partner;
            $partnerName = $partner ? $partner->name : 'Mitra Referral';
            $msg = "✅ <b>PENARIKAN KOMISI REFERRAL DISETUJUI & DITRANSFER</b>\n\n"
                . "┌ <b>Detail Payout</b>\n"
                . "├ Mitra: <b>" . htmlspecialchars($partnerName) . "</b>\n"
                . "├ Nominal: <b>Rp " . number_format((float) $wd->amount, 0, ',', '.') . "</b>\n"
                . "├ Rekening Tujuan: {$wd->bank_name} - <code>{$wd->account_number}</code> a.n {$wd->account_name}\n"
                . "├ Status: ✅ <b>SUKSES (APPROVED)</b>\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');
            if ($notes) {
                $msg .= "\n\n<i>Catatan: " . htmlspecialchars($notes) . "</i>";
            }

            $telegram->sendAdminNotification($msg, 'topup', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ReferralWithdrawal] Retract and notify approval failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract initial Telegram message and notify Superadmin on referral withdrawal rejection.
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

            $partner = $wd->partner;
            $partnerName = $partner ? $partner->name : 'Mitra Referral';
            $msg = "❌ <b>PENARIKAN KOMISI REFERRAL DITOLAK & REFUND</b>\n\n"
                . "┌ <b>Detail Payout</b>\n"
                . "├ Mitra: <b>" . htmlspecialchars($partnerName) . "</b>\n"
                . "├ Nominal: <b>Rp " . number_format((float) $wd->amount, 0, ',', '.') . "</b>\n"
                . "├ Rekening: {$wd->bank_name} - <code>{$wd->account_number}</code> a.n {$wd->account_name}\n"
                . "├ Status: ❌ <b>DITOLAK (REJECTED)</b>\n"
                . "├ Alasan: " . htmlspecialchars($reason) . "\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            $telegram->sendAdminNotification($msg, 'topup', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ReferralWithdrawal] Retract and notify rejection failed: ' . $e->getMessage());
        }
    }
}
