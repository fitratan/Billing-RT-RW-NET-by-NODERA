<?php

namespace App\Services;

use App\Models\NoderaPayMerchant;
use App\Models\ReferralCommission;
use App\Models\ReferralPartner;
use App\Models\ReferralWithdrawal;
use App\Models\Tenant;
use App\Models\VpnTransaction;
use App\Models\VpnUser;
use App\Models\WaMerchant;
use App\Models\WaTopup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    /**
     * Validate and retrieve an active referral partner by code
     */
    public static function validateReferralCode(?string $code): ?ReferralPartner
    {
        if (empty($code)) {
            return null;
        }

        $cleanCode = strtoupper(trim($code));
        return ReferralPartner::where('referral_code', $cleanCode)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * Distribute commission and downline bonus on successful Cloud Panel VPN Top-up
     */
    public static function distributeTopupCommission($topup): ?ReferralCommission
    {
        return null;
    }

    /**
     * Distribute commission and downline bonus on successful WhatsApp Gateway Top-up
     */
    public static function distributeWaTopupCommission(WaTopup $topup): ?ReferralCommission
    {
        try {
            $merchant = $topup->merchant ?? WaMerchant::find($topup->merchant_id);
            if (!$merchant || !$merchant->referred_by_partner_id) {
                return null;
            }

            $partner = ReferralPartner::where('id', $merchant->referred_by_partner_id)
                ->where('status', 'approved')
                ->first();

            if (!$partner) {
                return null;
            }

            // Check if already rewarded
            $existing = ReferralCommission::where('source_platform', 'wa')
                ->where('source_id', $topup->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $topupAmount = (float) $topup->amount;
            if ($topupAmount <= 0) {
                return null;
            }

            $rate = (float) ($partner->commission_rate > 0 ? $partner->commission_rate : 10.00);
            $commissionAmount = round($topupAmount * ($rate / 100), 2);
            $bonusDownline = round($topupAmount * 0.10, 2); // 10% bonus saldo deposit to downline

            if ($commissionAmount <= 0) {
                return null;
            }

            return DB::transaction(function () use ($partner, $merchant, $topup, $topupAmount, $rate, $commissionAmount, $bonusDownline) {
                // 1. Credit 10% bonus credit balance to merchant if credit_topup
                if ($topup->type === 'credit_topup' && $bonusDownline > 0) {
                    $merchant->increment('credit_balance', $bonusDownline);
                }

                // 2. Record referral commission to partner
                $commission = ReferralCommission::create([
                    'referral_partner_id' => $partner->id,
                    'wa_merchant_id'      => $merchant->id,
                    'source_platform'     => 'wa',
                    'source_id'           => $topup->id,
                    'source_invoice'      => $topup->invoice_number,
                    'topup_amount'        => $topupAmount,
                    'commission_rate'     => $rate,
                    'commission_amount'   => $commissionAmount,
                    'status'              => 'credited',
                    'notes'               => "Komisi {$rate}% dari top-up WhatsApp Gateway {$merchant->name} ({$topup->invoice_number})",
                ]);

                $partner->increment('commission_balance', $commissionAmount);
                $partner->increment('total_commission_earned', $commissionAmount);

                // 3. Notify Telegram Superadmin
                try {
                    $telegram = app(TelegramService::class);
                    if ($telegram->isConfigured()) {
                        $msg = "<b>🎁 KOMISI REFERRAL WHATSAPP GATEWAY — NODERA</b>\n\n"
                            . "┌ Detail Komisi\n"
                            . "├ Mitra: {$partner->name} (<code>{$partner->referral_code}</code>)\n"
                            . "├ Platform: WhatsApp Gateway\n"
                            . "├ Merchant: {$merchant->name} ({$merchant->merchant_code})\n"
                            . "├ Top-Up: Rp " . number_format($topupAmount, 0, ',', '.') . "\n"
                            . "├ Bonus Downline (+10%): Rp " . number_format($bonusDownline, 0, ',', '.') . "\n"
                            . "├ Rate Komisi: {$rate}%\n"
                            . "├ Komisi Masuk: Rp " . number_format($commissionAmount, 0, ',', '.') . "\n"
                            . "├ Saldo Komisi: Rp " . number_format($partner->fresh()->commission_balance, 0, ',', '.') . "\n"
                            . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                        $telegram->sendAdminNotification($msg, 'topup', 'HTML');
                    }
                } catch (\Throwable $e) {
                    Log::warning('Telegram WA referral notification failed: ' . $e->getMessage());
                }

                // 4. Notify WhatsApp to Partner
                try {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled() && !empty($partner->phone)) {
                        $msg = "🎁 *KOMISI REFERRAL WA GATEWAY MASUK!*\n\n"
                            . "Halo *{$partner->name}*,\n"
                            . "Anda baru saja menerima komisi referral baru dari aktivitas merchant WhatsApp Gateway.\n\n"
                            . "📋 *Rincian Komisi*\n"
                            . "• Platform: *WhatsApp Gateway*\n"
                            . "• Merchant: *{$merchant->name}*\n"
                            . "• Nominal Top-Up: *Rp " . number_format($topupAmount, 0, ',', '.') . "*\n"
                            . "• Komisi Anda ({$rate}%): *Rp " . number_format($commissionAmount, 0, ',', '.') . "*\n"
                            . "• Total Saldo Komisi: *Rp " . number_format((float)$partner->fresh()->commission_balance, 0, ',', '.') . "*\n\n"
                            . "Saldo komisi dapat dicairkan atau dikonversi ke saldo di menu Referral Cloud Panel.";
                        $wa->sendMessage($partner->phone, $msg);
                    }
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp referral commission notification failed: ' . $e->getMessage());
                }

                return $commission;
            });
        } catch (\Throwable $e) {
            Log::error('Error distributing WA referral commission: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Distribute commission on successful Payment Gateway deposit or fee settlement
     */
    public static function distributeNoderaPayCommission(NoderaPayMerchant $merchant, float $amount, string $invoiceNumber, string $notes = ''): ?ReferralCommission
    {
        try {
            if (!$merchant->referred_by_partner_id) {
                return null;
            }

            $partner = ReferralPartner::where('id', $merchant->referred_by_partner_id)
                ->where('status', 'approved')
                ->first();

            if (!$partner || $amount <= 0) {
                return null;
            }

            $rate = (float) ($partner->commission_rate > 0 ? $partner->commission_rate : 10.00);
            $commissionAmount = round($amount * ($rate / 100), 2);

            if ($commissionAmount <= 0) {
                return null;
            }

            return DB::transaction(function () use ($partner, $merchant, $amount, $invoiceNumber, $notes, $rate, $commissionAmount) {
                $commission = ReferralCommission::create([
                    'referral_partner_id'    => $partner->id,
                    'nodera_pay_merchant_id' => $merchant->id,
                    'source_platform'        => 'gateway',
                    'source_invoice'         => $invoiceNumber,
                    'topup_amount'           => $amount,
                    'commission_rate'        => $rate,
                    'commission_amount'      => $commissionAmount,
                    'status'                 => 'credited',
                    'notes'                  => $notes ?: "Komisi {$rate}% dari transaksi Payment Gateway {$merchant->name} ({$invoiceNumber})",
                ]);

                $partner->increment('commission_balance', $commissionAmount);
                $partner->increment('total_commission_earned', $commissionAmount);

                return $commission;
            });
        } catch (\Throwable $e) {
            Log::error('Error distributing NoderaPay referral commission: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Submit Bank Account details for Superadmin Verification
     */
    public static function submitBankAccount(VpnUser $user, array $data): array
    {
        $partner = ReferralPartner::where('vpn_user_id', $user->id)->first();
        if (!$partner) {
            return ['success' => false, 'message' => 'Akun Anda belum terdaftar sebagai mitra referral.'];
        }

        $bankName = trim($data['bank_name'] ?? '');
        $accountNumber = trim($data['bank_account_number'] ?? '');
        $accountHolder = trim($data['bank_account_holder'] ?? '');

        if (empty($bankName) || empty($accountNumber) || empty($accountHolder)) {
            return ['success' => false, 'message' => 'Semua kolom bank, nomor rekening, dan nama pemilik wajib diisi.'];
        }

        $partner->update([
            'bank_name'                     => $bankName,
            'bank_account_number'           => $accountNumber,
            'bank_account_holder'           => $accountHolder,
            'bank_account_status'           => 'pending',
            'bank_account_rejection_reason' => null,
        ]);

        // Notify Telegram Superadmin for verification review
        try {
            $telegram = app(TelegramService::class);
            if ($telegram->isConfigured()) {
                $msg = "<b>🏦 PENGAJUAN VERIFIKASI REKENING MITRA REFERRAL — NODERA</b>\n\n"
                    . "┌ Detail Mitra\n"
                    . "├ Nama: {$partner->name} (<code>{$partner->referral_code}</code>)\n"
                    . "├ Bank / E-Wallet: {$bankName}\n"
                    . "├ Nomor Rekening: <code>{$accountNumber}</code>\n"
                    . "├ Atas Nama: {$accountHolder}\n"
                    . "├ Status Rekening: Menunggu Persetujuan Superadmin\n"
                    . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                $telegram->sendAdminNotification($msg, 'topup', 'HTML');
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram bank account notification failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Data rekening berhasil diajukan! Menunggu verifikasi dan persetujuan Superadmin.',
            'partner' => $partner->fresh(),
        ];
    }

    /**
     * Superadmin verify or update partner bank account
     */
    public static function verifyBankAccount(ReferralPartner $partner, int $adminId, bool $isApproved, ?string $reason = null): bool
    {
        if ($isApproved) {
            $partner->update([
                'bank_account_status'          => 'verified',
                'bank_account_verified_at'      => now(),
                'bank_account_verified_by'      => $adminId,
                'bank_account_rejection_reason'=> null,
            ]);

            // Notify WhatsApp to Partner
            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled() && !empty($partner->phone)) {
                    $msg = "✅ *REKENING MITRA REFERRAL DISETUJUI*\n\n"
                        . "Halo *{$partner->name}*,\n"
                        . "Pengajuan rekening bank/e-wallet untuk pencairan komisi referral Anda telah *DIVERIFIKASI & DISETUJUI* oleh Superadmin.\n\n"
                        . "🏦 *Rincian Rekening*\n"
                        . "• Bank/E-Wallet: *{$partner->bank_name}*\n"
                        . "• No. Rekening: *{$partner->bank_account_number}*\n"
                        . "• Atas Nama: *{$partner->bank_account_holder}*\n\n"
                        . "Sekarang Anda dapat melakukan penarikan komisi kapan saja jika telah mencapai ambang batas minimal (Rp " . number_format((float)$partner->min_withdrawal_threshold, 0, ',', '.') . ").";
                    $wa->sendMessage($partner->phone, $msg);
                }
            } catch (\Throwable $e) {}
        } else {
            $partner->update([
                'bank_account_status'          => 'rejected',
                'bank_account_rejection_reason'=> $reason ?: 'Data rekening tidak valid atau tidak cocok.',
            ]);

            // Notify WhatsApp to Partner
            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled() && !empty($partner->phone)) {
                    $msg = "❌ *REKENING MITRA REFERRAL DITOLAK*\n\n"
                        . "Halo *{$partner->name}*,\n"
                        . "Pengajuan rekening pencairan komisi Anda belum dapat disetujui.\n\n"
                        . "Alasan Penolakan: *" . ($reason ?: 'Data rekening tidak sesuai') . "*\n\n"
                        . "Silakan perbarui data rekening Anda melalui menu Referral Cloud Panel.";
                    $wa->sendMessage($partner->phone, $msg);
                }
            } catch (\Throwable $e) {}
        }

        return true;
    }

    /**
     * 1-Click Convert Commission directly to User Main Saldo
     */
    public static function convertCommissionToSaldo(VpnUser $user, float $amount): array
    {
        $partner = ReferralPartner::where('vpn_user_id', $user->id)->first();
        if (!$partner || $partner->status !== 'approved') {
            return ['success' => false, 'message' => 'Status mitra referral Anda belum disetujui / aktif.'];
        }

        if ($amount < 5000) {
            return ['success' => false, 'message' => 'Minimal konversi komisi adalah Rp 5.000.'];
        }

        if ((float) $partner->commission_balance < $amount) {
            return ['success' => false, 'message' => 'Saldo komisi Anda tidak mencukupi.'];
        }

        return DB::transaction(function () use ($partner, $user, $amount) {
            $partner = ReferralPartner::lockForUpdate()->find($partner->id);
            $user = VpnUser::lockForUpdate()->find($user->id);

            if ((float) $partner->commission_balance < $amount) {
                return ['success' => false, 'message' => 'Saldo komisi tidak mencukupi saat proses.'];
            }

            $saldoBefore = $user->total_saldo;

            // 1. Deduct commission balance
            $partner->decrement('commission_balance', $amount);

            // 2. Add to user main saldo
            $user->increment('saldo', $amount);

            // 3. Record withdrawal
            ReferralWithdrawal::create([
                'referral_partner_id' => $partner->id,
                'vpn_user_id'         => $user->id,
                'type'                => 'convert_saldo',
                'amount'              => $amount,
                'fee'                 => 0.00,
                'net_amount'          => $amount,
                'status'              => 'completed',
                'admin_notes'         => 'Konversi otomatis 1-klik ke saldo utama member',
                'processed_at'        => now(),
            ]);

            // 4. Log VpnTransaction
            VpnTransaction::create([
                'vpn_user_id'  => $user->id,
                'type'         => 'topup',
                'amount'       => $amount,
                'saldo_before' => $saldoBefore,
                'saldo_after'  => $user->fresh()->total_saldo,
                'description'  => 'Konversi Komisi Referral Mitra (' . $partner->referral_code . ')',
                'reference'    => 'REFCONV-' . strtoupper(uniqid()),
            ]);

            return [
                'success' => true,
                'message' => 'Berhasil mengonversi Rp ' . number_format($amount, 0, ',', '.') . ' ke saldo utama Anda!',
                'new_commission_balance' => (float) $partner->fresh()->commission_balance,
                'new_user_saldo' => (float) $user->fresh()->total_saldo,
            ];
        });
    }

    /**
     * Request Cash Withdrawal to Superadmin-Verified Bank Account (Nodera Gateway standard)
     */
    public static function requestBankWithdrawal(VpnUser $user, array $data): array
    {
        $partner = ReferralPartner::where('vpn_user_id', $user->id)->first();
        if (!$partner || $partner->status !== 'approved') {
            return ['success' => false, 'message' => 'Status kemitraan referral Anda belum aktif.'];
        }

        // Must have verified bank account set by Superadmin
        if ($partner->bank_account_status !== 'verified') {
            return [
                'success' => false,
                'message' => 'Rekening penarikan Anda belum diverifikasi oleh Superadmin. Silakan ajukan rekening dan tunggu verifikasi.',
            ];
        }

        $minThreshold = (float) ($partner->min_withdrawal_threshold > 0 ? $partner->min_withdrawal_threshold : 50000.00);
        $amount = (float) $data['amount'];

        if ($amount < $minThreshold) {
            return [
                'success' => false,
                'message' => 'Nominal penarikan di bawah ambang batas minimal (Min. Rp ' . number_format($minThreshold, 0, ',', '.') . ').',
            ];
        }

        if ((float) $partner->commission_balance < $amount) {
            return ['success' => false, 'message' => 'Saldo komisi Anda tidak mencukupi untuk penarikan ini.'];
        }

        return DB::transaction(function () use ($partner, $user, $amount, $minThreshold) {
            $partner = ReferralPartner::lockForUpdate()->find($partner->id);
            if ((float) $partner->commission_balance < $amount) {
                return ['success' => false, 'message' => 'Saldo komisi tidak mencukupi saat proses.'];
            }

            // Hold/deduct the commission balance
            $partner->decrement('commission_balance', $amount);

            $withdrawal = ReferralWithdrawal::create([
                'referral_partner_id' => $partner->id,
                'vpn_user_id'         => $user->id,
                'type'                => 'bank_withdrawal',
                'amount'              => $amount,
                'fee'                 => 0.00,
                'net_amount'          => $amount,
                'bank_name'           => $partner->bank_name,
                'account_number'      => $partner->bank_account_number,
                'account_name'        => $partner->bank_account_holder,
                'status'              => 'pending',
            ]);

            // Notify Telegram Superadmin
            try {
                $telegram = app(TelegramService::class);
                if ($telegram->isConfigured()) {
                    $msg = "<b>💸 PENGAJUAN PENARIKAN KOMISI REFERRAL — NODERA</b>\n\n"
                        . "┌ Detail Penarikan\n"
                        . "├ Mitra: {$partner->name} (<code>{$partner->referral_code}</code>)\n"
                        . "├ Nominal Ditarik: Rp " . number_format($amount, 0, ',', '.') . "\n"
                        . "├ Ambang Batas: Rp " . number_format($minThreshold, 0, ',', '.') . "\n"
                        . "├ Bank Tujuan: {$partner->bank_name}\n"
                        . "├ No. Rekening: <code>{$partner->bank_account_number}</code>\n"
                        . "├ Atas Nama: {$partner->bank_account_holder} (VERIFIED)\n"
                        . "├ Sisa Saldo: Rp " . number_format((float)$partner->fresh()->commission_balance, 0, ',', '.') . "\n"
                        . "└ Waktu: " . now()->format('d/m/Y H:i:s');

                    $keyboard = $telegram->inlineKeyboard([[
                        $telegram->inlineButton('Setujui Transfer', 'ref_payout_approve:' . $withdrawal->id),
                        $telegram->inlineButton('Tolak & Refund', 'ref_payout_reject:' . $withdrawal->id),
                    ]]);

                    $tgRes = $telegram->sendAdminNotification($msg, 'topup', 'HTML', $keyboard);
                    $msgId = $tgRes['result']['message_id'] ?? ($tgRes['message_id'] ?? null);
                    $chatId = $tgRes['result']['chat']['id'] ?? ($tgRes['chat_id'] ?? null);

                    if (!empty($msgId) && !empty($chatId)) {
                        $withdrawal->update([
                            'telegram_chat_id'    => (string) $chatId,
                            'telegram_message_id' => (string) $msgId,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Telegram referral withdrawal notification failed: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => 'Permintaan penarikan berhasil dikirim! Superadmin akan memproses transfer Anda.',
                'withdrawal_id' => $withdrawal->id,
            ];
        });
    }

    /**
     * Superadmin approve bank withdrawal
     */
    public static function approveWithdrawal(ReferralWithdrawal $withdrawal, ?int $adminId, ?string $adminNotes = null, ?string $transferProof = null): bool
    {
        if ($withdrawal->status !== 'pending') {
            return false;
        }

        $withdrawal->update([
            'status'         => 'completed',
            'admin_notes'    => $adminNotes,
            'transfer_proof' => $transferProof,
            'processed_at'   => now(),
            'processed_by'   => $adminId,
        ]);

        ReferralWithdrawal::retractAndNotifyApproval($withdrawal, $adminNotes);

        try {
            $partner = $withdrawal->partner ?? ReferralPartner::find($withdrawal->referral_partner_id);
            $wa = \App\Services\WhatsappService::forSuperadmin();
            if ($wa->isEnabled() && !empty($partner->phone)) {
                $msg = "💸 *PENCAIRAN KOMISI REFERRAL SUKSES*\n\n"
                    . "Halo *{$partner->name}*,\n"
                    . "Pengajuan penarikan komisi referral Anda sebesar *Rp " . number_format((float)$withdrawal->amount, 0, ',', '.') . "* telah *DITRANSFER* oleh Superadmin.\n\n"
                    . "📋 *Rincian Transfer*\n"
                    . "• Bank/E-Wallet: *{$withdrawal->bank_name}*\n"
                    . "• No. Rekening: *{$withdrawal->account_number}*\n"
                    . "• Atas Nama: *{$withdrawal->account_name}*\n"
                    . "• Nominal Bersih: *Rp " . number_format((float)($withdrawal->net_amount ?: $withdrawal->amount), 0, ',', '.') . "*\n"
                    . "• Tanggal Cair: *" . now()->format('d/m/Y H:i') . " WIB*\n\n"
                    . "Terima kasih atas kontribusi aktif Anda dalam program kemitraan NODERA.";
                $wa->sendMessage($partner->phone, $msg);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp referral withdrawal notification failed: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Superadmin reject bank withdrawal (refunds balance)
     */
    public static function rejectWithdrawal(ReferralWithdrawal $withdrawal, ?int $adminId, string $reason): bool
    {
        if ($withdrawal->status !== 'pending') {
            return false;
        }

        return DB::transaction(function () use ($withdrawal, $adminId, $reason) {
            $partner = ReferralPartner::lockForUpdate()->find($withdrawal->referral_partner_id);
            if ($partner) {
                // Refund held amount
                $partner->increment('commission_balance', $withdrawal->amount);
            }

            $withdrawal->update([
                'status'       => 'rejected',
                'admin_notes'  => $reason,
                'processed_at' => now(),
                'processed_by' => $adminId,
            ]);

            ReferralWithdrawal::retractAndNotifyRejection($withdrawal, $reason);

            try {
                $partner = $withdrawal->partner ?? ReferralPartner::find($withdrawal->referral_partner_id);
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled() && !empty($partner->phone)) {
                    $msg = "❌ *PENARIKAN KOMISI REFERRAL DITOLAK*\n\n"
                        . "Halo *{$partner->name}*,\n"
                        . "Pengajuan penarikan komisi referral sebesar *Rp " . number_format((float)$withdrawal->amount, 0, ',', '.') . "* ditolak oleh Superadmin.\n\n"
                        . "Alasan: *" . $reason . "*\n\n"
                        . "Dana sebesar *Rp " . number_format((float)$withdrawal->amount, 0, ',', '.') . "* telah dikembalikan ke saldo komisi referral Anda.";
                    $wa->sendMessage($partner->phone, $msg);
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp referral withdrawal reject notification failed: ' . $e->getMessage());
            }

            return true;
        });
    }
}
