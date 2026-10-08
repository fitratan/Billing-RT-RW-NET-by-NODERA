<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'vpn_user_id',
        'referral_code',
        'name',
        'phone',
        'email',
        'promotion_channel',
        'reason',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'bank_account_status',
        'bank_account_verified_at',
        'bank_account_verified_by',
        'bank_account_rejection_reason',
        'min_withdrawal_threshold',
        'commission_rate',
        'commission_balance',
        'total_commission_earned',
        'status',
        'telegram_chat_id',
        'telegram_message_id',
        'rejection_reason',
        'admin_notes',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'commission_balance' => 'decimal:2',
            'total_commission_earned' => 'decimal:2',
            'min_withdrawal_threshold' => 'decimal:2',
            'approved_at' => 'datetime',
            'bank_account_verified_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function verifiedBankByAdmin()
    {
        return $this->belongsTo(User::class, 'bank_account_verified_by');
    }

    public function referredUsers()
    {
        return $this->hasMany(VpnUser::class, 'referred_by_partner_id');
    }

    public function referredWaMerchants()
    {
        return $this->hasMany(WaMerchant::class, 'referred_by_partner_id');
    }

    public function referredGatewayMerchants()
    {
        return $this->hasMany(NoderaPayMerchant::class, 'referred_by_partner_id');
    }

    public function commissions()
    {
        return $this->hasMany(ReferralCommission::class, 'referral_partner_id')->latest();
    }

    public function withdrawals()
    {
        return $this->hasMany(ReferralWithdrawal::class, 'referral_partner_id')->latest();
    }

    /**
     * Dismiss inline keyboard from the initial Telegram request message.
     */
    public static function dismissTelegramNotification(self $partner): void
    {
        if (empty($partner->telegram_message_id)) {
            return;
        }

        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                $chatIds = !empty($partner->telegram_chat_id) ? [$partner->telegram_chat_id] : $telegram->getSuperadminChatIds();
                foreach ($chatIds as $cid) {
                    try {
                        $telegram->editMessageReplyMarkup((string) $cid, (int) $partner->telegram_message_id, ['inline_keyboard' => []]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ReferralPartner] Dismiss markup failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract initial Telegram message and notify Superadmin on partner approval.
     */
    public static function retractAndNotifyApproval(self $partner): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            if (!empty($partner->telegram_message_id)) {
                $telegram->retractMessage((int) $partner->telegram_message_id, $partner->telegram_chat_id);
            }

            $msg = "✅ <b>PENGAJUAN MITRA REFERRAL DISETUJUI</b>\n\n"
                . "┌ <b>Data Mitra</b>\n"
                . "├ Nama: <b>" . htmlspecialchars($partner->name) . "</b>\n"
                . "├ Kode Referral: <code>{$partner->referral_code}</code>\n"
                . "├ Email: " . htmlspecialchars($partner->email) . "\n"
                . "├ WhatsApp: <code>" . htmlspecialchars($partner->phone) . "</code>\n"
                . "├ Komisi: <b>" . number_format((float) $partner->commission_rate, 1) . "%</b>\n"
                . "├ Status: ✅ <b>AKTIF (APPROVED)</b>\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            $telegram->sendAdminNotification($msg, 'general', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ReferralPartner] Retract and notify approval failed: ' . $e->getMessage());
        }
    }

    /**
     * Retract initial Telegram message and notify Superadmin on partner rejection.
     */
    public static function retractAndNotifyRejection(self $partner, string $reason): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            if (!empty($partner->telegram_message_id)) {
                $telegram->retractMessage((int) $partner->telegram_message_id, $partner->telegram_chat_id);
            }

            $msg = "❌ <b>PENGAJUAN MITRA REFERRAL DITOLAK</b>\n\n"
                . "┌ <b>Data Mitra</b>\n"
                . "├ Nama: <b>" . htmlspecialchars($partner->name) . "</b>\n"
                . "├ Email: " . htmlspecialchars($partner->email) . "\n"
                . "├ WhatsApp: <code>" . htmlspecialchars($partner->phone) . "</code>\n"
                . "├ Status: ❌ <b>DITOLAK (REJECTED)</b>\n"
                . "├ Alasan: " . htmlspecialchars($reason) . "\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            $telegram->sendAdminNotification($msg, 'general', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ReferralPartner] Retract and notify rejection failed: ' . $e->getMessage());
        }
    }
}
