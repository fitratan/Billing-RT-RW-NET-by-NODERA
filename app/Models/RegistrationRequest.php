<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'slug', 'username', 'password_hash', 'email', 'phone', 'company', 'package_id',
        'duration', 'referral_code', 'payment_proof', 'payment_bank', 'payment_notes',
        'unique_code', 'total_amount', 'dynamic_qris_string', 'expires_at', 'paid_at',
        'telegram_chat_id', 'telegram_message_id',
        'status', 'notes', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at'   => 'datetime',
            'expires_at'    => 'datetime',
            'paid_at'       => 'datetime',
            'duration'      => 'integer',
            'unique_code'   => 'integer',
            'total_amount'  => 'float',
        ];
    }

    public function package()
    {
        return $this->belongsTo(Package::class)->withoutGlobalScopes();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function isPaid(): bool
    {
        return $this->status === 'approved' || $this->paid_at !== null;
    }

    public function isExpired(): bool
    {
        return !$this->isPaid() && $this->expires_at && $this->expires_at->isPast();
    }

    public function getOrderNumberAttribute(): string
    {
        $datePrefix = $this->created_at ? $this->created_at->format('Ymd') : date('Ymd');
        return $datePrefix . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Remove inline keyboard / buttons from the initial Telegram request message.
     */
     public static function dismissTelegramNotification(self $req): void
     {
         if (empty($req->telegram_message_id)) {
             return;
         }

         try {
             $telegram = app(\App\Services\TelegramService::class);
             if ($telegram->isConfigured()) {
                 $chatIds = !empty($req->telegram_chat_id) ? [$req->telegram_chat_id] : $telegram->getSuperadminChatIds();
                 foreach ($chatIds as $cid) {
                     try {
                         $telegram->editMessageReplyMarkup((string) $cid, (int) $req->telegram_message_id, ['inline_keyboard' => []]);
                     } catch (\Throwable $e) {}
                 }
             }
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::warning('[RegistrationRequest] Failed to dismiss Telegram keyboard: ' . $e->getMessage());
         }
     }

    /**
     * Retract (delete) initial Telegram message and send approval alert to Superadmin.
     */
    public static function retractAndNotifyApproval(self $req, ?Tenant $tenant = null, ?User $user = null): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            // 1. Tarik / Hapus pesan pendaftaran lama dari grup Telegram
            if (! empty($req->telegram_message_id)) {
                $telegram->retractMessage((int) $req->telegram_message_id, $req->telegram_chat_id);
            }

            // 2. Kirim notifikasi status terbaru ke grup superadmin
            $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost());
            $formattedPrice = number_format((float) $req->total_amount, 0, ',', '.');
            $pkgName = $tenant?->package_name ?? ($req->package?->name ?? 'Paket ISP');
            $adminUsername = $user?->username ?? ($req->username ?: $req->slug);
            $companyName = $req->company ?: ($tenant?->name ?: $req->name);

            $msg = "✅ <b>PENDAFTARAN MITRA TELAH DISETUJUI & AKTIF</b>\n\n"
                . "┌ <b>Nama Mitra :</b> " . htmlspecialchars($req->name) . "\n"
                . "├ <b>Perusahaan / ISP :</b> " . htmlspecialchars($companyName) . "\n"
                . "├ <b>Subdomain / URL :</b> <code>https://" . htmlspecialchars($req->slug) . "." . $baseDomain . "/login</code>\n"
                . "├ <b>Username Admin :</b> <code>" . htmlspecialchars($adminUsername) . "</code>\n"
                . "├ <b>Paket Langganan :</b> " . htmlspecialchars($pkgName) . " (" . $req->duration . " Bulan)\n"
                . "├ <b>Total Pembayaran :</b> Rp " . $formattedPrice . "\n"
                . "├ <b>WhatsApp :</b> <code>" . htmlspecialchars($req->phone) . "</code>\n"
                . "├ <b>Status :</b> ✅ <b>DISETUJUI / SUKSES (APPROVED)</b>\n"
                . "└ <b>Waktu Persetujuan :</b> " . now()->format('d/m/Y H:i:s') . "\n\n"
                . "<i>Notifikasi aktivasi dan petunjuk login telah otomatis dikirimkan ke WhatsApp mitra.</i>";

            $telegram->sendAdminNotification($msg, 'registration', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RegistrationRequest] Failed to retract and notify approval: ' . $e->getMessage());
        }
    }

    /**
     * Retract (delete) initial Telegram message and send rejection alert to Superadmin.
     */
    public static function retractAndNotifyRejection(self $req, string $reason = 'Permohonan pendaftaran ditolak oleh Superadmin.'): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            // 1. Tarik / Hapus pesan pendaftaran lama dari grup Telegram
            if (! empty($req->telegram_message_id)) {
                $telegram->retractMessage((int) $req->telegram_message_id, $req->telegram_chat_id);
            }

            // 2. Kirim notifikasi status penolakan ke grup superadmin
            $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost());
            $formattedPrice = number_format((float) $req->total_amount, 0, ',', '.');
            $pkgName = $req->package?->name ?? 'Paket ISP';

            $msg = "🚫 <b>PENDAFTARAN MITRA DITOLAK</b>\n\n"
                . "┌ <b>Nama Mitra :</b> " . htmlspecialchars($req->name) . "\n"
                . "├ <b>Perusahaan / ISP :</b> " . htmlspecialchars($req->company) . "\n"
                . "├ <b>Subdomain :</b> <code>" . htmlspecialchars($req->slug) . "." . $baseDomain . "</code>\n"
                . "├ <b>Paket Langganan :</b> " . htmlspecialchars($pkgName) . " (" . $req->duration . " Bulan)\n"
                . "├ <b>Total Tagihan :</b> Rp " . $formattedPrice . "\n"
                . "├ <b>WhatsApp :</b> <code>" . htmlspecialchars($req->phone) . "</code>\n"
                . "├ <b>Status :</b> 🚫 <b>DITOLAK (REJECTED)</b>\n"
                . "├ <b>Alasan :</b> " . htmlspecialchars($reason) . "\n"
                . "└ <b>Waktu :</b> " . now()->format('d/m/Y H:i:s');

            $telegram->sendAdminNotification($msg, 'registration', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RegistrationRequest] Failed to retract and notify rejection: ' . $e->getMessage());
        }
    }

    /**
     * Retract (delete) initial Telegram message and send cancellation alert to Superadmin.
     */
    public static function retractAndNotifyCancellation(self $req, string $reason = 'Pendaftar telah menutup halaman pembayaran QRIS / membatalkan pendaftaran.'): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            // 1. Tarik / Hapus pesan pendaftaran lama dari grup Telegram
            if (! empty($req->telegram_message_id)) {
                $telegram->retractMessage((int) $req->telegram_message_id, $req->telegram_chat_id);
            }

            // 2. Kirim pesan baru ke grup superadmin
            $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost());
            $formattedPrice = number_format((float) $req->total_amount, 0, ',', '.');
            $pkgName = $req->package?->name ?? 'Paket ISP';

            $msg = "❌ <b>PENDAFTARAN DIBATALKAN OLEH PENDAFTAR</b>\n\n"
                . "┌ <b>Nama Mitra :</b> " . htmlspecialchars($req->name) . "\n"
                . "├ <b>Perusahaan / ISP :</b> " . htmlspecialchars($req->company) . "\n"
                . "├ <b>Subdomain :</b> <code>" . htmlspecialchars($req->slug) . "." . $baseDomain . "</code>\n"
                . "├ <b>Paket Langganan :</b> " . htmlspecialchars($pkgName) . " (" . $req->duration . " Bulan)\n"
                . "├ <b>Total Tagihan :</b> Rp " . $formattedPrice . "\n"
                . "├ <b>WhatsApp :</b> <code>" . htmlspecialchars($req->phone) . "</code>\n"
                . "├ <b>Status :</b> ❌ <b>DIBATALKAN (CANCELLED)</b>\n"
                . "└ <b>Waktu Pembatalan :</b> " . now()->format('d/m/Y H:i:s') . "\n\n"
                . "<i>" . htmlspecialchars($reason) . "</i>";

            $telegram->sendAdminNotification($msg, 'registration', 'HTML');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RegistrationRequest] Failed to retract and notify cancellation: ' . $e->getMessage());
        }
    }
}
