<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrder extends Model
{
    use HasFactory;

    protected $table = 'shop_orders';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'customer_notes',
        'voucher_username',
        'voucher_password',
        'voucher_profile',
        'voucher_created_in_mikrotik',
        'telegram_message_id',
        'telegram_chat_id',
        'subtotal',
        'shipping_fee',
        'total_amount',
        'unique_code',
        'dynamic_qris_string',
        'expires_at',
        'paid_at',
        'payment_method',
        'payment_bank',
        'payment_channel_id',
        'payment_proof',
        'payment_status',
        'order_status',
        'tracking_number',
        'admin_notes',
    ];

    protected $casts = [
        'subtotal'                    => 'float',
        'shipping_fee'                => 'float',
        'total_amount'                => 'float',
        'unique_code'                 => 'integer',
        'expires_at'                  => 'datetime',
        'paid_at'                     => 'datetime',
        'voucher_created_in_mikrotik' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        if ($tenantId !== null) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'order_id');
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getCleanShippingAddressAttribute(): string
    {
        if (empty($this->shipping_address) || $this->shipping_address === 'Layanan Voucher Online') {
            return 'Layanan Voucher Online';
        }
        // Normalize newlines, remove duplicates and collapse extra spaces
        $raw = str_replace(["\r\n", "\r"], "\n", (string) $this->shipping_address);
        $lines = array_filter(array_map('trim', explode("\n", $raw)));
        $uniqueLines = array_values(array_unique($lines));
        return !empty($uniqueLines) ? implode(', ', $uniqueLines) : 'Alamat belum diisi';
    }

    public function getFormattedPaymentMethodAttribute(): string
    {
        $method = $this->payment_method ?? 'transfer';
        $bank = $this->payment_bank ? " ({$this->payment_bank})" : '';

        if (stripos($method, 'qris') !== false) {
            return 'QRIS / E-Wallet' . ($this->payment_bank ? " ({$this->payment_bank})" : ' (QRIS Instant)');
        }
        if (stripos($method, 'transfer') !== false || stripos($method, 'bank') !== false) {
            return 'Transfer Bank' . $bank;
        }
        if (stripos($method, 'cod') !== false) {
            return 'COD (Bayar di Tempat)';
        }
        return ucwords(str_replace('_', ' ', $method)) . $bank;
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->order_status) {
            'completed' => ['label' => 'Selesai', 'class' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'],
            'shipped' => ['label' => 'Dikirim', 'class' => 'bg-blue-500/10 text-blue-400 border-blue-500/20'],
            'processing' => ['label' => 'Diproses', 'class' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20'],
            'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-rose-500/10 text-rose-400 border-rose-500/20'],
            default => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-amber-500/10 text-amber-400 border-amber-500/20'],
        };
    }

    /**
     * Build standard customer-to-admin checkout confirmation WhatsApp message.
     */
    public function buildWhatsAppCheckoutMessage(string $storeName = 'NODERA', ?array $customItems = null): string
    {
        $dateStr = $this->created_at ? $this->created_at->format('d/m/Y H:i') . ' WIB' : date('d/m/Y H:i') . ' WIB';
        $formattedTotal = $this->formatted_total;
        $cleanAddress = $this->clean_shipping_address;
        $payMethod = $this->formatted_payment_method;

        $lines = [];
        $lines[] = "*KONFIRMASI PESANAN - " . strtoupper($storeName) . " SHOP*";
        $lines[] = "No. Order: *{$this->order_number}*";
        $lines[] = "Tanggal: {$dateStr}";
        $lines[] = "----------------------------------------";
        $lines[] = "*Data Pembeli:*";
        $lines[] = "- Nama: {$this->customer_name}";
        $lines[] = "- No. WhatsApp: {$this->customer_phone}";
        if (!empty($this->customer_email)) {
            $lines[] = "- Email: {$this->customer_email}";
        }
        $lines[] = "- Alamat Kirim: {$cleanAddress}";
        if (!empty($this->customer_notes)) {
            $lines[] = "- Catatan: {$this->customer_notes}";
        }
        $lines[] = "----------------------------------------";
        $lines[] = "*Rincian Barang yang Dipesan:*";

        $items = $customItems ?? $this->items()->get()->map(function ($it) {
            return [
                'product_name' => $it->product_name,
                'quantity' => $it->quantity,
                'price' => $it->price,
                'subtotal' => $it->subtotal,
            ];
        })->toArray();

        $no = 1;
        foreach ($items as $item) {
            $pName = $item['product_name'] ?? 'Produk';
            $qty = $item['quantity'] ?? 1;
            $priceFormatted = 'Rp ' . number_format($item['price'] ?? 0, 0, ',', '.');
            $subtotalFormatted = 'Rp ' . number_format($item['subtotal'] ?? 0, 0, ',', '.');
            $lines[] = "{$no}. *{$pName}*";
            $lines[] = "   Qty: {$qty} x {$priceFormatted} = {$subtotalFormatted}";
            $no++;
        }

        $lines[] = "----------------------------------------";
        $lines[] = "*TOTAL PEMBAYARAN: {$formattedTotal}*";
        $lines[] = "Metode: {$payMethod}";
        $lines[] = "";
        $lines[] = "Halo Admin {$storeName}, saya sudah melakukan pemesanan dan ingin melampirkan *bukti transfer pembayaran*. Mohon segera diverifikasi dan diproses pengirimannya. Terima kasih.";

        return implode("\n", $lines);
    }

    /**
     * Build clean, professional admin-to-customer approved (ACC) WhatsApp message (NO emoticons).
     */
    public function buildWhatsAppApprovedMessage(string $storeName = 'NODERA', array $voucherList = []): string
    {
        $dateStr = $this->created_at ? $this->created_at->format('d/m/Y H:i') . ' WIB' : date('d/m/Y H:i') . ' WIB';
        $formattedTotal = $this->formatted_total;
        $cleanAddress = $this->clean_shipping_address;
        $payMethod = $this->formatted_payment_method;

        $lines = [];
        $lines[] = "*PESANAN DISETUJUI & DIPROSES - " . strtoupper($storeName) . " SHOP*";
        $lines[] = "No. Order: *{$this->order_number}*";
        $lines[] = "Tanggal: {$dateStr}";
        $lines[] = "----------------------------------------";
        $lines[] = "*Status: PEMBAYARAN DITERIMA / DISETUJUI*";
        $lines[] = "----------------------------------------";
        $lines[] = "*Data Pembeli:*";
        $lines[] = "- Nama: {$this->customer_name}";
        $lines[] = "- No. WhatsApp: {$this->customer_phone}";
        $lines[] = "- Alamat Kirim: {$cleanAddress}";
        $lines[] = "----------------------------------------";
        $lines[] = "*Rincian Pesanan:*";

        $items = $this->items()->get();
        $no = 1;
        foreach ($items as $item) {
            $priceFormatted = 'Rp ' . number_format($item->price, 0, ',', '.');
            $subtotalFormatted = 'Rp ' . number_format($item->subtotal, 0, ',', '.');
            $lines[] = "{$no}. *{$item->product_name}*";
            $lines[] = "   Qty: {$item->quantity} x {$priceFormatted} = {$subtotalFormatted}";
            $no++;
        }

        $lines[] = "----------------------------------------";
        $lines[] = "*TOTAL PEMBAYARAN: {$formattedTotal}*";
        $lines[] = "Metode: {$payMethod}";

        if (!empty($voucherList)) {
            $lines[] = "----------------------------------------";
            $lines[] = "*Rincian Akun Voucher Hotspot (" . count($voucherList) . " Akun):*";
            foreach ($voucherList as $idx => $v) {
                $num = $idx + 1;
                $pkgTitle = $v['package_name'] ?? "Voucher #{$num}";
                $lines[] = "{$num}. *{$pkgTitle}* (Profil: {$v['profile']})";
                $lines[] = "   Username: {$v['username']}";
                $lines[] = "   Password: {$v['password']}";
            }
            $lines[] = "----------------------------------------";
            $lines[] = "Silakan hubungkan perangkat Anda ke jaringan WiFi kami lalu login pada portal menggunakan username dan password di atas.";
        }

        $lines[] = "";
        $lines[] = "Halo {$this->customer_name}, pembayaran pesanan Anda telah kami verifikasi dan disetujui. Pesanan Anda saat ini sedang diproses. Terima kasih telah berbelanja di {$storeName}.";

        return implode("\n", $lines);
    }

    /**
     * Build clean, professional admin-to-customer rejected WhatsApp message (NO emoticons).
     */
    public function buildWhatsAppRejectedMessage(string $storeName = 'NODERA', string $reason = ''): string
    {
        $dateStr = $this->created_at ? $this->created_at->format('d/m/Y H:i') . ' WIB' : date('d/m/Y H:i') . ' WIB';
        $formattedTotal = $this->formatted_total;
        $reasonText = !empty($reason) ? $reason : ($this->admin_notes ?: 'Bukti pembayaran tidak sesuai atau dibatalkan oleh pembeli');

        $lines = [];
        $lines[] = "*PEMBERITAHUAN PESANAN DIBATALKAN - " . strtoupper($storeName) . " SHOP*";
        $lines[] = "No. Order: *{$this->order_number}*";
        $lines[] = "Tanggal: {$dateStr}";
        $lines[] = "----------------------------------------";
        $lines[] = "*Status: DITOLAK / DIBATALKAN*";
        $lines[] = "Alasan: {$reasonText}";
        $lines[] = "----------------------------------------";
        $lines[] = "*Data Pembeli:*";
        $lines[] = "- Nama: {$this->customer_name}";
        $lines[] = "- No. WhatsApp: {$this->customer_phone}";
        $lines[] = "----------------------------------------";
        $lines[] = "*TOTAL: {$formattedTotal}*";
        $lines[] = "----------------------------------------";
        $lines[] = "";
        $lines[] = "Halo {$this->customer_name}, mohon maaf pesanan Anda dengan No. Order #{$this->order_number} belum dapat kami setujui atau telah dibatalkan. Jika Anda memiliki pertanyaan atau ingin konfirmasi lebih lanjut, silakan hubungi admin kami. Terima kasih.";

        return implode("\n", $lines);
    }

    /**
     * Build clean, professional status update WhatsApp message (NO emoticons).
     */
    public function buildWhatsAppStatusMessage(string $storeName = 'NODERA', ?string $status = null, ?string $trackingNumber = null): string
    {
        $st = $status ?? $this->order_status;
        $dateStr = $this->created_at ? $this->created_at->format('d/m/Y H:i') . ' WIB' : date('d/m/Y H:i') . ' WIB';
        $formattedTotal = $this->formatted_total;
        $cleanAddress = $this->clean_shipping_address;
        $tracking = $trackingNumber ?? $this->tracking_number;

        $lines = [];

        if ($st === 'shipped') {
            $lines[] = "*STATUS PESANAN: DALAM PENGIRIMAN - " . strtoupper($storeName) . " SHOP*";
            $lines[] = "No. Order: *{$this->order_number}*";
            $lines[] = "Tanggal: {$dateStr}";
            $lines[] = "----------------------------------------";
            $lines[] = "*Status: SEDANG DIKIRIM*";
            if (!empty($tracking)) {
                $lines[] = "No. Resi / Kurir: *{$tracking}*";
            }
            $lines[] = "----------------------------------------";
            $lines[] = "*Data Pembeli:*";
            $lines[] = "- Nama: {$this->customer_name}";
            $lines[] = "- Alamat Kirim: {$cleanAddress}";
            $lines[] = "----------------------------------------";
            $lines[] = "*Rincian Pesanan:*";
            $items = $this->items()->get();
            $no = 1;
            foreach ($items as $item) {
                $lines[] = "{$no}. *{$item->product_name}* ({$item->quantity}x)";
                $no++;
            }
            $lines[] = "----------------------------------------";
            $lines[] = "";
            $lines[] = "Halo {$this->customer_name}, pesanan Anda telah kami serahkan ke pihak ekspedisi/kurir untuk dikirimkan ke alamat tujuan. Silakan lakukan pengecekan status pengiriman secara berkala. Terima kasih.";
        } elseif ($st === 'completed') {
            $lines[] = "*STATUS PESANAN: SELESAI - " . strtoupper($storeName) . " SHOP*";
            $lines[] = "No. Order: *{$this->order_number}*";
            $lines[] = "Tanggal: {$dateStr}";
            $lines[] = "----------------------------------------";
            $lines[] = "*Status: PESANAN SELESAI*";
            $lines[] = "----------------------------------------";
            $lines[] = "*Data Pembeli:*";
            $lines[] = "- Nama: {$this->customer_name}";
            $lines[] = "----------------------------------------";
            $lines[] = "*Rincian Pesanan:*";
            $items = $this->items()->get();
            $no = 1;
            foreach ($items as $item) {
                $lines[] = "{$no}. *{$item->product_name}* ({$item->quantity}x)";
                $no++;
            }
            $lines[] = "----------------------------------------";
            $lines[] = "*TOTAL: {$formattedTotal}*";
            $lines[] = "----------------------------------------";
            $lines[] = "";
            $lines[] = "Halo {$this->customer_name}, transaksi pesanan Anda telah dinyatakan selesai. Terima kasih banyak telah berbelanja di {$storeName}. Kami nantikan pesanan Anda berikutnya.";
        } else {
            $lines[] = "*STATUS PESANAN: SEDANG DIPROSES - " . strtoupper($storeName) . " SHOP*";
            $lines[] = "No. Order: *{$this->order_number}*";
            $lines[] = "Tanggal: {$dateStr}";
            $lines[] = "----------------------------------------";
            $lines[] = "*Status: SEDANG DIPROSES*";
            $lines[] = "----------------------------------------";
            $lines[] = "*Data Pembeli:*";
            $lines[] = "- Nama: {$this->customer_name}";
            $lines[] = "- Alamat Kirim: {$cleanAddress}";
            $lines[] = "----------------------------------------";
            $lines[] = "*Rincian Pesanan:*";
            $items = $this->items()->get();
            $no = 1;
            foreach ($items as $item) {
                $lines[] = "{$no}. *{$item->product_name}* ({$item->quantity}x)";
                $no++;
            }
            $lines[] = "----------------------------------------";
            $lines[] = "";
            $lines[] = "Halo {$this->customer_name}, pesanan Anda saat ini sedang disiapkan dan diproses oleh tim {$storeName}. Kami akan mengabari kembali setelah pesanan siap/dikirim. Terima kasih.";
        }
        return implode("\n", $lines);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid' || $this->paid_at !== null || $this->order_status === 'completed';
    }

    public function isExpired(): bool
    {
        return !$this->isPaid() && $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Remove inline keyboard / buttons from the initial Telegram order message.
     */
    public static function dismissTelegramNotification(self $order): void
    {
        if (empty($order->telegram_message_id)) {
            return;
        }

        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                $chatIds = !empty($order->telegram_chat_id) ? [$order->telegram_chat_id] : $telegram->getSuperadminChatIds();
                foreach ($chatIds as $cid) {
                    try {
                        $telegram->editMessageReplyMarkup((string) $cid, (int) $order->telegram_message_id, ['inline_keyboard' => []]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ShopOrder] Failed to dismiss Telegram keyboard: ' . $e->getMessage());
        }
    }

    /**
     * Settle paid order: Mark as paid, create MikroTik vouchers if applicable, notify WhatsApp & Telegram.
     */
    public static function settleOrder(self $order, float $amountReceived, string $note = ''): bool
    {
        if ($order->isPaid()) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $amountReceived, $note) {
            $locked = self::withoutGlobalScopes()->lockForUpdate()->find($order->id);
            if (!$locked || $locked->isPaid()) {
                return true;
            }

            // 1. If voucher order, create users in MikroTik
            $voucherList = \App\Services\TenantTelegramService::parseVoucherList($locked);
            $mikrotikSuccessCount = 0;
            $mikrotikErrors = [];

            if (!empty($voucherList)) {
                $router = \App\Models\Mikrotik::withoutGlobalScopes()
                    ->when($locked->tenant_id, fn($q) => $q->where('tenant_id', $locked->tenant_id))
                    ->where('is_active', true)
                    ->first()
                    ?? \App\Models\Mikrotik::withoutGlobalScopes()
                    ->when($locked->tenant_id, fn($q) => $q->where('tenant_id', $locked->tenant_id))
                    ->first();

                if ($router) {
                    try {
                        $mikrotikService = new \App\Services\MikrotikService($router);
                        if ($mikrotikService->isConnected()) {
                            foreach ($voucherList as $vch) {
                                $vUser = $vch['username'] ?? null;
                                $vPass = $vch['password'] ?? $vUser;
                                $vProf = $vch['profile'] ?? ($locked->voucher_profile ?: 'default');
                                if ($vUser) {
                                    $added = $mikrotikService->addHotspotUser(
                                        $vUser,
                                        $vPass,
                                        $vProf,
                                        '',
                                        0,
                                        'all',
                                        "Order #{$locked->order_number} (Auto-Settled)"
                                    );
                                    if ($added) {
                                        $mikrotikSuccessCount++;
                                    } else {
                                        $err = $mikrotikService->getLastError() ?: 'Gagal menambahkan user';
                                        $mikrotikErrors[] = "User {$vUser}: {$err}";
                                    }
                                }
                            }
                            $locked->voucher_created_in_mikrotik = ($mikrotikSuccessCount === count($voucherList) && $mikrotikSuccessCount > 0);
                        } else {
                            $locked->voucher_created_in_mikrotik = false;
                            $mikrotikErrors[] = 'Koneksi Router API Gagal';
                        }
                    } catch (\Throwable $e) {
                        $locked->voucher_created_in_mikrotik = false;
                        $mikrotikErrors[] = $e->getMessage();
                        \Illuminate\Support\Facades\Log::error('[ShopOrder Settle] MikroTik error: ' . $e->getMessage());
                    }
                }
            }

            if (!empty($mikrotikErrors)) {
                $locked->admin_notes = implode(' | ', $mikrotikErrors);
            }

            $locked->payment_status = 'paid';
            $locked->order_status = 'completed';
            $locked->paid_at = now();
            if (!empty($note)) {
                $locked->admin_notes = ($locked->admin_notes ? $locked->admin_notes . ' | ' : '') . $note;
            }
            $locked->save();

            // 2. Dismiss or update Telegram notification
            if ($locked->tenant_id) {
                try {
                    $tenantTg = app(\App\Services\TenantTelegramService::class);
                    $tenantTg->updateOrderAccMessage($locked, 'Auto-Settled (QRIS)');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[ShopOrder Settle] Tenant Telegram update failed: ' . $e->getMessage());
                    self::dismissTelegramNotification($locked);
                }
            } else {
                self::dismissTelegramNotification($locked);
                try {
                    $telegram = app(\App\Services\TelegramService::class);
                    if ($telegram->isConfigured()) {
                        $msg = "<b>✅ PESANAN SELESAI (AUTO-SETTLED) — NODERA</b>\n\n"
                            . "┌ Detail Pesanan\n"
                            . "├ Order: <code>#{$locked->order_number}</code>\n"
                            . "├ Pembeli: {$locked->customer_name} ({$locked->customer_phone})\n"
                            . "├ Nominal: Rp " . number_format($locked->total_amount, 0, ',', '.') . "\n"
                            . "├ Diterima: Rp " . number_format($amountReceived, 0, ',', '.') . "\n"
                            . "├ Catatan: {$note}\n"
                            . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                        $telegram->sendAdminNotification($msg, 'order');
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[ShopOrder Settle] Superadmin Telegram notify failed: ' . $e->getMessage());
                }
            }

            // 3. Send WhatsApp confirmation / voucher details to customer
            if (!empty($locked->customer_phone)) {
                try {
                    $tenantName = $locked->tenant?->name ?? 'NODERA Store';
                    $waMsg = $locked->buildWhatsAppApprovedMessage($tenantName, $voucherList);
                    \App\Jobs\SendWhatsappNotificationJob::dispatch($locked->customer_phone, $waMsg);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[ShopOrder Settle] WhatsApp dispatch failed: ' . $e->getMessage());
                }
            }

            return true;
        });
    }
}

