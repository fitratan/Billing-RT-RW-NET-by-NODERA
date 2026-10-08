<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantTelegramService
{
    private TelegramService $telegram;

    public function __construct()
    {
        $this->telegram = app(TelegramService::class);
    }

    /**
     * Get telegram configuration for a specific tenant (cached for 5 minutes).
     */
    public function getTenantConfig(int $tenantId): array
    {
        return Cache::remember("tenant_tg_cfg_{$tenantId}", 300, function () use ($tenantId) {
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
            $settings = $tenant?->settings ?? [];

            // Fallbacks from tenant settings or general settings
            $chatId = $settings['telegram_chat_id'] 
                ?? Setting::where('tenant_id', $tenantId)->where('key', 'TELEGRAM_ADMIN_CHAT_ID')->value('value')
                ?? Setting::where('tenant_id', $tenantId)->where('key', 'TELEGRAM_CHAT_ID')->value('value')
                ?? '';

            $botToken = $settings['telegram_bot_token']
                ?? Setting::where('tenant_id', $tenantId)->where('key', 'TELEGRAM_BOT_TOKEN')->value('value')
                ?? null;

            $mode = $settings['telegram_mode'] ?? 'chat'; // 'chat' or 'topic'
            $topicId = $settings['telegram_topic_id'] ?? null;
            $topicNms = $settings['telegram_topic_nms'] ?? null;
            $topicRouter = $settings['telegram_topic_router'] ?? null;
            $topicPppoe = $settings['telegram_topic_pppoe'] ?? null;
            $topicHotspot = $settings['telegram_topic_hotspot'] ?? null;
            $topicArp = $settings['telegram_topic_arp'] ?? null;

            $globalEnabled = (bool) ($settings['telegram_enabled'] ?? true);
            $orderNotifEnabled = (bool) ($settings['telegram_order_notif'] ?? true);
            $nmsNotifEnabled = (bool) ($settings['telegram_nms_notif'] ?? true);
            $nmsRouter = (bool) ($settings['telegram_nms_router'] ?? true);
            $nmsPppoe = (bool) ($settings['telegram_nms_pppoe'] ?? true);
            $nmsHotspot = (bool) ($settings['telegram_nms_hotspot'] ?? false);
            $nmsArp = (bool) ($settings['telegram_nms_arp'] ?? false);

            return [
                'enabled' => $globalEnabled && !empty($chatId) && !empty($botToken),
                'order_notif_enabled' => $orderNotifEnabled,
                'nms_notif_enabled' => $nmsNotifEnabled,
                'nms_router' => $nmsRouter,
                'nms_pppoe' => $nmsPppoe,
                'nms_hotspot' => $nmsHotspot,
                'nms_arp' => $nmsArp,
                'bot_token' => $botToken,
                'chat_id' => $chatId,
                'mode' => $mode,
                'topic_id' => $mode === 'topic' ? $topicId : null,
                'topic_nms' => $mode === 'topic' ? $topicNms : null,
                'topic_router' => $mode === 'topic' ? ($topicRouter ?: $topicNms ?: $topicId) : null,
                'topic_pppoe' => $mode === 'topic' ? ($topicPppoe ?: $topicNms ?: $topicId) : null,
                'topic_hotspot' => $mode === 'topic' ? ($topicHotspot ?: $topicNms ?: $topicId) : null,
                'topic_arp' => $mode === 'topic' ? ($topicArp ?: $topicNms ?: $topicId) : null,
                'tenant_name' => $tenant?->name ?? 'Admin',
            ];
        });
    }

    /**
     * Internal method to send message via custom bot token or system default bot.
     */
    private function sendMessageWithConfig(array $config, string $text, string $parseMode = 'Markdown', ?array $replyMarkup = null): array
    {
        $chatId = $config['chat_id'];
        $topicId = $config['topic_id'] ?? null;
        $customToken = $config['bot_token'] ?? null;

        if (!empty($customToken)) {
            $url = "https://api.telegram.org/bot{$customToken}/sendMessage";
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];
            if (!empty($topicId)) {
                $payload['message_thread_id'] = $topicId;
            }
            if (!empty($replyMarkup)) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            $response = Http::timeout(10)->post($url, $payload);
            return $response->json() ?? ['ok' => false, 'description' => 'Response error'];
        }

        return $this->telegram->sendMessage($chatId, $text, $parseMode, $replyMarkup, $topicId);
    }

    /**
     * Internal method to edit message via custom bot token or system default bot.
     */
    public function editMessageWithConfig(array $config, string $messageId, string $text, string $parseMode = 'HTML', ?array $replyMarkup = null): array
    {
        $chatId = (string) ($config['chat_id'] ?? '');
        $customToken = $config['bot_token'] ?? null;

        // Ensure replyMarkup URLs are safe (< 500 chars) for Telegram API
        if (!empty($replyMarkup['inline_keyboard'])) {
            foreach ($replyMarkup['inline_keyboard'] as &$row) {
                foreach ($row as &$btn) {
                    if (!empty($btn['url']) && strlen($btn['url']) > 500) {
                        $parsed = parse_url($btn['url']);
                        $btn['url'] = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'wa.me') . ($parsed['path'] ?? '');
                    }
                }
            }
        }

        if (!empty($customToken) && !empty($chatId)) {
            $url = "https://api.telegram.org/bot{$customToken}/editMessageText";
            $payload = [
                'chat_id' => $chatId,
                'message_id' => (int) $messageId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];
            if ($replyMarkup !== null) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            try {
                $response = Http::asForm()->timeout(10)->post($url, $payload);
                $json = $response->json();
                if ($json && ($json['ok'] ?? false)) {
                    return $json;
                }
                // Fallback: If entity parsing fails, retry with stripped tags without parse_mode
                if ($json && isset($json['description']) && str_contains(strtolower($json['description']), 'parse')) {
                    $payload['text'] = strip_tags($text);
                    unset($payload['parse_mode']);
                    $response = Http::asForm()->timeout(10)->post($url, $payload);
                    $json = $response->json();
                    if ($json && ($json['ok'] ?? false)) {
                        return $json;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("editMessageWithConfig custom token failed, falling back: " . $e->getMessage());
            }
        }

        return $this->telegram->editMessage($chatId, (int) $messageId, $text, $parseMode, $replyMarkup);
    }

    /**
     * Answer a callback query via custom bot token or system default bot.
     */
    public function answerCallbackWithConfig(array $config, string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        $customToken = $config['bot_token'] ?? null;
        if (!empty($customToken)) {
            $url = "https://api.telegram.org/bot{$customToken}/answerCallbackQuery";
            $payload = [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert ? '1' : '0',
            ];
            try {
                $response = Http::asForm()->timeout(5)->post($url, $payload);
                $json = $response->json();
                if ($json && ($json['ok'] ?? false)) {
                    return $json;
                }
            } catch (\Exception $e) {
                Log::warning("answerCallbackWithConfig custom token failed: " . $e->getMessage());
            }
        }

        return $this->telegram->answerCallback($callbackQueryId, $text, $showAlert);
    }

    /**
     * Test telegram connection for tenant.
     */
    public function testConnection(int $tenantId, ?string $token, string $chatId, string $mode = 'chat', ?string $topicId = null, string $testType = 'general'): array
    {
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token Bot Telegram wajib diisi! Dapatkan token dari @BotFather.',
            ];
        }

        if (empty($chatId)) {
            return [
                'success' => false,
                'message' => 'Chat ID / Group ID Telegram wajib diisi!',
            ];
        }

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $tenantName = $tenant?->name ?? 'Admin Tenant';

        $config = [
            'bot_token' => trim($token),
            'chat_id' => trim($chatId),
            'mode' => $mode,
            'topic_id' => $mode === 'topic' && !empty($topicId) ? trim($topicId) : null,
        ];

        $targetLabel = $mode === 'topic' && !empty($topicId) 
            ? "Group ID: {$chatId} (Topic #{$topicId})" 
            : "Chat ID: {$chatId}";

        $dateStr = now()->format('Y-m-d');
        $timeStr = now()->format('H:i:s');

        if ($testType === 'pppoe' || $testType === 'nms') {
            $text = "<b>🟢 PPPOE ONLINE — NODERA</b>\n\n"
                . "┌ <code>holip</code>\n"
                . "├ Status: ONLINE\n"
                . "├ IP: <code>41.33.55.114</code>\n"
                . "├ CallerID: <code>14:AD:CA:0A:E6:29</code>\n"
                . "├ Total Online: 125 user\n"
                . "└ Waktu: {$dateStr} {$timeStr}";
        } elseif ($testType === 'hotspot') {
            $text = "<b>🟢 HOTSPOT ONLINE — NODERA</b>\n\n"
                . "┌ <code>voucher_demo</code>\n"
                . "├ Status: ONLINE\n"
                . "├ IP: <code>192.168.88.50</code>\n"
                . "├ MAC: <code>14:AD:CA:0A:E6:29</code>\n"
                . "├ Total Online: 45 user\n"
                . "└ Waktu: {$dateStr} {$timeStr}";
        } elseif ($testType === 'router') {
            $text = "<b>🟢 ROUTER ONLINE — NODERA</b>\n\n"
                . "┌ Router NOC Utama\n"
                . "├ Status: ONLINE\n"
                . "├ Host: <code>103.150.190.1:8728</code>\n"
                . "├ CPU: 12%\n"
                . "├ Uptime: 14d 06:22:15\n"
                . "└ Waktu: {$dateStr} {$timeStr}";
        } elseif ($testType === 'arp') {
            $text = "<b>🟢 ARP HOST ONLINE — NODERA</b>\n\n"
                . "┌ <code>192.168.1.100</code>\n"
                . "├ Status: ONLINE\n"
                . "├ Router: Router NOC Utama\n"
                . "├ MAC: <code>14:AD:CA:0A:E6:29</code>\n"
                . "├ Interface: ether2-lan\n"
                . "└ Waktu: {$dateStr} {$timeStr}";
        } elseif ($testType === 'order') {
            $text = "<b>📦 UJI COBA PESANAN ONLINE — NODERA</b>\n\n"
                . "┌ " . htmlspecialchars($tenantName) . "\n"
                . "├ Nomor Pesanan: #TEST-" . rand(1000, 9999) . "\n"
                . "├ Produk: Voucher WiFi 24 Jam (Demo)\n"
                . "├ Nominal: Rp 10.000\n"
                . "├ Target: " . htmlspecialchars($targetLabel) . "\n"
                . "└ Waktu: {$dateStr} {$timeStr}";
        } else {
            $text = "<b>🔔 UJI COBA NOTIFIKASI TELEGRAM BERHASIL — NODERA</b>\n\n"
                . "┌ " . htmlspecialchars($tenantName) . "\n"
                . "├ Bot Telegram: Terhubung &amp; Aktif\n"
                . "├ Target: " . htmlspecialchars($targetLabel) . "\n"
                . "└ Waktu: {$dateStr} {$timeStr}";
        }

        try {
            $res = $this->sendMessageWithConfig($config, $text, 'HTML');

            if ($res['ok'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Pesan uji coba (' . strtoupper($testType) . ') berhasil terkirim ke Telegram!',
                ];
            }

            $desc = $res['description'] ?? 'Pastikan bot sudah di-start di Telegram atau dimasukkan ke dalam grup.';
            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan ke Telegram: ' . $desc,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse voucher accounts from order (handles single and multi-voucher).
     */
    public static function parseVoucherList(ShopOrder $order): array
    {
        $raw = $order->voucher_username;
        if (empty($raw)) {
            return [];
        }

        if (str_starts_with(trim($raw), '[') && str_ends_with(trim($raw), ']')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        return [
            [
                'package_name' => $order->items->first()?->product_name ?? 'Voucher WiFi',
                'username' => $order->voucher_username,
                'password' => $order->voucher_password ?: $order->voucher_username,
                'profile' => $order->voucher_profile ?: 'default',
            ]
        ];
    }

    /**
     * Send NMS (Offline/Online Router, PPPoE, Hotspot, ARP) notification to tenant telegram.
     */
    public function sendNmsNotification(int $tenantId, string $htmlText, ?string $customTopicId = null, string $category = 'nms'): bool
    {
        $config = $this->getTenantConfig($tenantId);
        if (!$config['enabled'] || !$config['nms_notif_enabled']) {
            return false;
        }

        $targetTopic = $customTopicId ?: ($config["topic_{$category}"] ?? $config['topic_nms'] ?? $config['topic_id']);
        $config['topic_id'] = $config['mode'] === 'topic' ? $targetTopic : null;

        try {
            $res = $this->sendMessageWithConfig($config, $htmlText, 'HTML');
            return (bool) ($res['ok'] ?? false);
        } catch (\Throwable $e) {
            Log::error("TenantTelegramService error sending NMS notification [{$category}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send new order notification to tenant telegram.
     */
    public function sendOrderNotification(ShopOrder $order): ?string
    {
        if (!$order->tenant_id) {
            return null;
        }

        $config = $this->getTenantConfig($order->tenant_id);
        if (!$config['enabled'] || !$config['order_notif_enabled']) {
            return null;
        }

        $voucherList = self::parseVoucherList($order);

        $text = "<b>📦 PESANAN BARU MASUK — " . htmlspecialchars($config['tenant_name']) . "</b>\n\n";
        $text .= "┌ " . htmlspecialchars($config['tenant_name']) . "\n";
        $text .= "├ Order ID: <code>" . htmlspecialchars($order->order_number) . "</code>\n";
        $text .= "├ Pembeli: " . htmlspecialchars($order->customer_name) . " (" . htmlspecialchars($order->customer_phone) . ")\n";

        if (!empty($voucherList)) {
            if (count($voucherList) === 1) {
                $v = $voucherList[0];
                $text .= "├ Tipe: Voucher Hotspot Online\n";
                $text .= "├ User: <code>" . htmlspecialchars($v['username']) . "</code> / Pass: <code>" . htmlspecialchars($v['password']) . "</code>\n";
                $text .= "├ Profil: <code>" . htmlspecialchars($v['profile']) . "</code>\n";
            } else {
                $count = count($voucherList);
                $text .= "├ Akun: " . $count . " Voucher\n";
            }
        }

        if (!empty($order->shipping_address) && $order->shipping_address !== 'Layanan Voucher Online') {
            $text .= "├ Alamat: " . htmlspecialchars($order->shipping_address) . "\n";
        }

        $text .= "├ Total: Rp " . number_format($order->total_amount, 0, ',', '.') . "\n";
        $text .= "├ Metode: " . htmlspecialchars($order->payment_method) . ($order->payment_bank ? " (" . htmlspecialchars($order->payment_bank) . ")" : "") . "\n";
        if ($order->customer_notes) {
            $text .= "├ Catatan: " . htmlspecialchars($order->customer_notes) . "\n";
        }
        $text .= "└ Waktu: " . $order->created_at->format('d/m/Y H:i') . " WIB\n\n";

        if (!empty($voucherList) && count($voucherList) > 1) {
            $text .= "┌ <b>Daftar Akun Voucher (" . count($voucherList) . " Akun)</b>\n";
            foreach ($voucherList as $idx => $v) {
                $num = $idx + 1;
                $isLast = ($num === count($voucherList));
                $prefix = $isLast ? '└' : '├';
                $pkgTitle = $v['package_name'] ?? "Voucher #{$num}";
                $text .= "{$prefix} {$num}. " . htmlspecialchars($pkgTitle) . " (<code>" . htmlspecialchars($v['profile']) . "</code>): <code>" . htmlspecialchars($v['username']) . "</code> / <code>" . htmlspecialchars($v['password']) . "</code>\n";
            }
            $text .= "\n";
        }

        $text .= "Silakan periksa mutasi rekening / bukti pembayaran, lalu pilih aksi di bawah:";

        // Inline Keyboard for ACC or Reject
        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'ACC & Aktifkan', 'callback_data' => "acc_vch:{$order->id}"],
                    ['text' => 'Tolak Pesanan', 'callback_data' => "rej_vch:{$order->id}"],
                ]
            ]
        ];

        try {
            if (!empty($config['bot_token'])) {
                try {
                    $tenantBot = new \App\Services\TelegramService($config['bot_token']);
                    $tenantBot->setWebhook();
                } catch (\Exception $we) {}
            }

            $res = $this->sendMessageWithConfig($config, $text, 'HTML', $replyMarkup);

            if (($res['ok'] ?? false) && isset($res['result']['message_id'])) {
                $msgId = (string) $res['result']['message_id'];
                $order->update(['telegram_message_id' => $msgId]);
                return $msgId;
            }
        } catch (\Exception $e) {
            Log::error("TenantTelegramService error sending order notification: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Update telegram message when order is ACC.
     */
    public function updateOrderAccMessage(ShopOrder $order, string $adminName = 'Admin', ?string $chatId = null, ?string $messageId = null): bool
    {
        if (!$order->tenant_id) {
            return false;
        }

        if ($messageId) {
            $order->telegram_message_id = (string) $messageId;
            $order->saveQuietly();
        }

        if (empty($order->telegram_message_id)) {
            return false;
        }

        $config = $this->getTenantConfig($order->tenant_id);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if (empty($config['chat_id'])) {
            return false;
        }

        $voucherList = self::parseVoucherList($order);
        $tenantName = $config['tenant_name'] ?? 'NODERA';
        $cleanAddress = $order->clean_shipping_address;

        $text = "<b>PESANAN DISETUJUI &amp; DIPROSES — " . htmlspecialchars($tenantName) . "</b>\n\n";
        $text .= "┌ " . htmlspecialchars($tenantName) . "\n";
        $text .= "├ Order ID: <code>" . htmlspecialchars($order->order_number) . "</code>\n";
        $text .= "├ Pembeli: " . htmlspecialchars($order->customer_name) . " (" . htmlspecialchars($order->customer_phone) . ")\n";
        $text .= "├ Alamat: " . htmlspecialchars($cleanAddress) . "\n";
        $text .= "├ Total: Rp " . number_format($order->total_amount, 0, ',', '.') . "\n";
        $text .= "└ Status: AKTIF\n\n";

        if (!empty($voucherList)) {
            $text .= "┌ <b>Daftar Akun Hotspot (" . count($voucherList) . " Akun)</b>\n";
            foreach ($voucherList as $idx => $v) {
                $num = $idx + 1;
                $isLast = ($num === count($voucherList));
                $prefix = $isLast ? '└' : '├';
                $pkgTitle = $v['package_name'] ?? "Voucher #{$num}";
                $text .= "{$prefix} {$num}. " . htmlspecialchars($pkgTitle) . " (<code>" . htmlspecialchars($v['profile']) . "</code>): <code>" . htmlspecialchars($v['username']) . "</code> / <code>" . htmlspecialchars($v['password']) . "</code>\n";
            }
            $text .= "\n";
        }

        $text .= "<b>Teks WhatsApp Siap Kirim ke Pembeli:</b>\n";
        
        $waSendText = $order->buildWhatsAppApprovedMessage($tenantName, $voucherList);
        $text .= "<pre>" . htmlspecialchars($waSendText) . "</pre>";

        $cleanPhone = preg_replace('/[^0-9]/', '', $order->customer_phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $shortWaAcc = "Halo {$order->customer_name}, pesanan #{$order->order_number} di {$tenantName} telah disetujui & aktif.";
        $waLink = !empty($cleanPhone) ? ("https://wa.me/{$cleanPhone}?text=" . urlencode($shortWaAcc)) : "https://wa.me/";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Kirim WhatsApp ke Pembeli', 'url' => $waLink],
                ]
            ]
        ];

        try {
            $res = $this->editMessageWithConfig($config, (string) $order->telegram_message_id, $text, 'HTML', $replyMarkup);
            return (bool) ($res['ok'] ?? false);
        } catch (\Exception $e) {
            Log::error("TenantTelegramService error editing ACC message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update telegram message when order is rejected.
     */
    public function updateOrderRejectMessage(ShopOrder $order, string $adminName = 'Admin', ?string $chatId = null, ?string $messageId = null): bool
    {
        if (!$order->tenant_id) {
            return false;
        }

        if ($messageId) {
            $order->telegram_message_id = (string) $messageId;
            $order->saveQuietly();
        }

        if (empty($order->telegram_message_id)) {
            return false;
        }

        $config = $this->getTenantConfig($order->tenant_id);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if (empty($config['chat_id'])) {
            return false;
        }

        $tenantName = $config['tenant_name'] ?? 'NODERA';
        $reason = $order->admin_notes ?: 'Dibatalkan oleh Admin';

        $text = "<b>PEMBERITAHUAN PESANAN DIBATALKAN — " . htmlspecialchars($tenantName) . "</b>\n\n";
        $text .= "┌ " . htmlspecialchars($tenantName) . "\n";
        $text .= "├ Order ID: <code>" . htmlspecialchars($order->order_number) . "</code>\n";
        $text .= "├ Pembeli: " . htmlspecialchars($order->customer_name) . " (" . htmlspecialchars($order->customer_phone) . ")\n";
        $text .= "├ Total: Rp " . number_format($order->total_amount, 0, ',', '.') . "\n";
        $text .= "├ Alasan: " . htmlspecialchars($reason) . "\n";
        $text .= "└ Status: DIBATALKAN\n\n";

        $text .= "<b>Teks WhatsApp Siap Kirim ke Pembeli:</b>\n";

        $waSendText = $order->buildWhatsAppRejectedMessage($tenantName, $reason);
        $text .= "<pre>" . htmlspecialchars($waSendText) . "</pre>";

        $cleanPhone = preg_replace('/[^0-9]/', '', $order->customer_phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $shortWaRej = "Halo {$order->customer_name}, pesanan #{$order->order_number} di {$tenantName} dibatalkan: {$reason}";
        $waLink = !empty($cleanPhone) ? ("https://wa.me/{$cleanPhone}?text=" . urlencode(mb_substr($shortWaRej, 0, 100))) : "https://wa.me/";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Kirim WhatsApp ke Pembeli', 'url' => $waLink],
                ]
            ]
        ];

        try {
            $res = $this->editMessageWithConfig($config, (string) $order->telegram_message_id, $text, 'HTML', $replyMarkup);
            return (bool) ($res['ok'] ?? false);
        } catch (\Exception $e) {
            Log::error("TenantTelegramService error editing reject message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Telegram notification to Tenant's bot when customer pays invoice manually (Bank Transfer / QRIS Static).
     */
    public function sendInvoicePaymentNotification(Invoice $invoice, ?Customer $customer = null, string $method = 'bank'): ?string
    {
        if (!$invoice->tenant_id) {
            return null;
        }

        $config = $this->getTenantConfig($invoice->tenant_id);
        if (!$config['enabled'] || empty($config['chat_id'])) {
            return null;
        }

        if (!$customer) {
            $customer = Customer::withoutGlobalScopes()->with('package')->find($invoice->customer_id);
        }

        $tenantName = $config['tenant_name'] ?? 'NODERA';
        $custName = htmlspecialchars($customer?->name ?? $invoice->customer_name ?? 'Pelanggan');
        $custPhone = htmlspecialchars($customer?->phone ?? '-');
        $custUsername = htmlspecialchars($customer?->pppoe_username ?? $customer?->code ?? '-');
        $pkgName = htmlspecialchars($customer?->package?->name ?? $customer?->profile ?? 'Paket Internet');
        $invNumber = htmlspecialchars($invoice->invoice_number);
        $period = htmlspecialchars($invoice->period ?: now()->format('F Y'));
        $amountFormatted = number_format($invoice->amount, 0, ',', '.');
        $methodLabel = match(strtolower($method)) {
            'qris', 'qris_static' => 'QRIS Static',
            'collector' => 'Kolektor',
            default => 'Transfer Bank Manual',
        };

        $text = "<b>KONFIRMASI PEMBAYARAN TAGIHAN — " . htmlspecialchars($tenantName) . "</b>\n\n";
        $text .= "┌ Detail Tagihan\n";
        $text .= "├ No. Invoice: <code>{$invNumber}</code>\n";
        $text .= "├ Pelanggan: {$custName} ({$custUsername})\n";
        $text .= "├ Kontak / WA: {$custPhone}\n";
        $text .= "├ Layanan: {$pkgName}\n";
        $text .= "├ Periode: {$period}\n";
        $text .= "├ Total: Rp {$amountFormatted}\n";
        $text .= "├ Metode: {$methodLabel}\n";
        $text .= "└ Status: MENUNGGU VERIFIKASI\n\n";
        $text .= "Silakan periksa mutasi rekening / bukti pembayaran, lalu pilih aksi di bawah:";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Setujui / ACC (Lunas)', 'callback_data' => "acc_inv:{$invoice->id}"],
                    ['text' => 'Tolak / Reject', 'callback_data' => "rej_inv:{$invoice->id}"],
                ]
            ]
        ];

        try {
            if (!empty($config['bot_token'])) {
                try {
                    $tenantBot = new \App\Services\TelegramService($config['bot_token']);
                    $tenantBot->setWebhook();
                } catch (\Throwable $we) {}
            }

            $res = $this->sendMessageWithConfig($config, $text, 'HTML', $replyMarkup);

            if (($res['ok'] ?? false) && isset($res['result']['message_id'])) {
                $msgId = (string) $res['result']['message_id'];
                $chatId = (string) ($res['result']['chat']['id'] ?? $config['chat_id']);
                $invoice->updateQuietly([
                    'telegram_message_id' => $msgId,
                    'telegram_chat_id' => $chatId,
                ]);
                return $msgId;
            }
        } catch (\Throwable $e) {
            Log::error("TenantTelegramService error sending invoice notification: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Update telegram message when invoice is ACC (Lunas).
     */
    public function updateInvoiceAccMessage(Invoice $invoice, string $adminName = 'Admin via Telegram', ?string $chatId = null, ?string $messageId = null): bool
    {
        if (!$invoice->tenant_id) {
            return false;
        }

        if ($messageId) {
            $invoice->telegram_message_id = (string) $messageId;
        }
        if ($chatId) {
            $invoice->telegram_chat_id = (string) $chatId;
        }
        $invoice->saveQuietly();

        if (empty($invoice->telegram_message_id)) {
            return false;
        }

        $config = $this->getTenantConfig($invoice->tenant_id);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if (empty($config['chat_id'])) {
            return false;
        }

        $customer = $invoice->customer ?: Customer::withoutGlobalScopes()->with('package')->find($invoice->customer_id);
        $tenantName = $config['tenant_name'] ?? 'NODERA';
        $custName = htmlspecialchars($customer?->name ?? $invoice->customer_name ?? 'Pelanggan');
        $custPhone = htmlspecialchars($customer?->phone ?? '-');
        $custUsername = htmlspecialchars($customer?->pppoe_username ?? $customer?->code ?? '-');
        $pkgName = htmlspecialchars($customer?->package?->name ?? $customer?->profile ?? 'Paket Internet');
        $invNumber = htmlspecialchars($invoice->invoice_number);
        $period = htmlspecialchars($invoice->period ?: now()->format('F Y'));
        $amountFormatted = number_format($invoice->amount, 0, ',', '.');

        $text = "<b>PEMBAYARAN TAGIHAN DISETUJUI (LUNAS) — " . htmlspecialchars($tenantName) . "</b>\n\n";
        $text .= "┌ Detail Tagihan\n";
        $text .= "├ No. Invoice: <code>{$invNumber}</code>\n";
        $text .= "├ Pelanggan: {$custName} ({$custUsername})\n";
        $text .= "├ Layanan: {$pkgName}\n";
        $text .= "├ Periode: {$period}\n";
        $text .= "├ Total: Rp {$amountFormatted}\n";
        $text .= "├ Status: LUNAS\n";
        $text .= "├ Diproses oleh: {$adminName}\n";
        $text .= "└ Waktu Lunas: " . now()->format('d/m/Y H:i') . " WIB\n\n";
        $text .= "Layanan internet pelanggan telah aktif kembali.";

        $cleanPhone = preg_replace('/[^0-9]/', '', $customer?->phone ?? '');
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $receiptUrl = url('/receipt/' . $invoice->invoice_number);
        $shortWaAcc = "Halo {$custName}, pembayaran tagihan #{$invNumber} ({$period}) sebesar Rp {$amountFormatted} telah diterima & LUNAS.\n\nLihat Bukti Pembayaran / Struk:\n{$receiptUrl}\n\nLayanan internet Anda aktif kembali. Terima kasih.";
        $waLink = !empty($cleanPhone) ? ("https://wa.me/{$cleanPhone}?text=" . urlencode($shortWaAcc)) : null;

        $replyMarkup = [
            'inline_keyboard' => $waLink ? [
                [
                    ['text' => 'Kirim WhatsApp ke Pelanggan', 'url' => $waLink],
                ]
            ] : []
        ];

        try {
            $res = $this->editMessageWithConfig($config, (string) $invoice->telegram_message_id, $text, 'HTML', $replyMarkup);
            return (bool) ($res['ok'] ?? false);
        } catch (\Throwable $e) {
            Log::error("TenantTelegramService error editing invoice ACC message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update telegram message when invoice payment is rejected.
     */
    public function updateInvoiceRejectMessage(Invoice $invoice, string $adminName = 'Admin via Telegram', ?string $chatId = null, ?string $messageId = null): bool
    {
        if (!$invoice->tenant_id) {
            return false;
        }

        if ($messageId) {
            $invoice->telegram_message_id = (string) $messageId;
        }
        if ($chatId) {
            $invoice->telegram_chat_id = (string) $chatId;
        }
        $invoice->saveQuietly();

        if (empty($invoice->telegram_message_id)) {
            return false;
        }

        $config = $this->getTenantConfig($invoice->tenant_id);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if (empty($config['chat_id'])) {
            return false;
        }

        $customer = $invoice->customer ?: Customer::withoutGlobalScopes()->find($invoice->customer_id);
        $tenantName = $config['tenant_name'] ?? 'NODERA';
        $custName = htmlspecialchars($customer?->name ?? $invoice->customer_name ?? 'Pelanggan');
        $custPhone = htmlspecialchars($customer?->phone ?? '-');
        $custUsername = htmlspecialchars($customer?->pppoe_username ?? $customer?->code ?? '-');
        $invNumber = htmlspecialchars($invoice->invoice_number);
        $amountFormatted = number_format($invoice->amount, 0, ',', '.');

        $text = "<b>KONFIRMASI PEMBAYARAN TAGIHAN DITOLAK — " . htmlspecialchars($tenantName) . "</b>\n\n";
        $text .= "┌ Detail Tagihan\n";
        $text .= "├ No. Invoice: <code>{$invNumber}</code>\n";
        $text .= "├ Pelanggan: {$custName} ({$custUsername})\n";
        $text .= "├ Total: Rp {$amountFormatted}\n";
        $text .= "├ Status: DITOLAK\n";
        $text .= "├ Diproses oleh: {$adminName}\n";
        $text .= "└ Waktu: " . now()->format('d/m/Y H:i') . " WIB";

        $cleanPhone = preg_replace('/[^0-9]/', '', $customer?->phone ?? '');
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $shortWaRej = "Halo {$custName}, konfirmasi pembayaran tagihan #{$invNumber} belum sesuai atau ditolak oleh admin. Silakan periksa kembali bukti transfer Anda.";
        $waLink = !empty($cleanPhone) ? ("https://wa.me/{$cleanPhone}?text=" . urlencode($shortWaRej)) : null;

        $replyMarkup = [
            'inline_keyboard' => $waLink ? [
                [
                    ['text' => 'Kirim WhatsApp ke Pelanggan', 'url' => $waLink],
                ]
            ] : []
        ];

        try {
            $res = $this->editMessageWithConfig($config, (string) $invoice->telegram_message_id, $text, 'HTML', $replyMarkup);
            return (bool) ($res['ok'] ?? false);
        } catch (\Throwable $e) {
            Log::error("TenantTelegramService error editing invoice reject message: " . $e->getMessage());
            return false;
        }
    }
}
