<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TroubleTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TelegramBotService - Sends notifications via Telegram Bot API.
 *
 * This is a service class (not a daemon process) that uses Laravel's HTTP
 * client to call the Telegram Bot API for outgoing notifications.
 *
 * Reads configuration from the settings table:
 *   - TELEGRAM_BOT_TOKEN: Bot API token
 *   - TELEGRAM_ADMIN_CHAT_IDS: Comma-separated list of admin chat IDs
 */
class TelegramBotService
{
    private ?string $botToken;
    private string $apiBaseUrl;
    private array $adminChatIds = [];

    public function __construct()
    {
        // Must bypass TenantAware scope to read global SuperAdmin settings (tenant_id null)
        $this->botToken = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_BOT_TOKEN')->whereNull('tenant_id')->value('value')
            ?: Setting::apiValue('TELEGRAM_BOT_TOKEN', '')
            ?: env('TELEGRAM_BOT_TOKEN', '');

        if (empty($this->botToken)) {
            $this->botToken = null;
            return;
        }

        $this->apiBaseUrl = "https://api.telegram.org/bot{$this->botToken}/";

        $adminIdsRaw = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_ADMIN_CHAT_IDS')->whereNull('tenant_id')->value('value')
            ?: Setting::apiValue('TELEGRAM_ADMIN_CHAT_IDS', '')
            ?: env('TELEGRAM_ADMIN_CHAT_IDS', '');

        if (!empty($adminIdsRaw)) {
            $parts = explode(',', $adminIdsRaw);
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $this->adminChatIds[] = $trimmed;
                }
            }
        }
    }

    /**
     * Check if the bot token is configured.
     */
    public function isConfigured(): bool
    {
        return $this->botToken !== null;
    }

    /**
     * Send a text message to a specific chat ID.
     *
     * @param string $chatId   Target chat ID (numeric or @username)
     * @param string $text     Message text
     * @param string $parseMode Parse mode: 'HTML', 'Markdown', or 'MarkdownV2'
     * @return bool
     */
    public function sendMessage(string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        if (!$this->isConfigured() || empty($chatId) || empty($text)) {
            return false;
        }

        try {
            $response = Http::timeout(15)->post($this->apiBaseUrl . 'sendMessage', [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => $parseMode,
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false) === true) {
                return true;
            }

            Log::warning('TelegramBotService: sendMessage failed', [
                'chat_id' => $chatId,
                'error'   => $body['description'] ?? 'Unknown error',
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('TelegramBotService: sendMessage exception', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a message to all configured admin chat IDs.
     *
     * @param string $message Message text
     * @return int Number of successful sends
     */
    public function sendToAdmin(string $message): int
    {
        if (!$this->isConfigured() || empty($this->adminChatIds)) {
            return 0;
        }

        $success = 0;
        foreach ($this->adminChatIds as $chatId) {
            if ($this->sendMessage($chatId, $message, 'HTML')) {
                $success++;
            }
        }

        return $success;
    }

    /**
     * Send a notification based on type and data.
     *
     * Supported notification types:
     *   - 'invoice_unpaid'     - Reminder about unpaid invoice
     *   - 'payment_received'   - Payment has been received
     *   - 'ticket_created'     - Support ticket created
     *   - 'customer_isolated'  - Customer service isolated
     *
     * @param int|null $tenantId Tenant ID (optional)
     * @param string   $type     Notification type
     * @param array    $data     Contextual data payload
     * @return int Number of successful sends
     */
    public function sendNotification(?int $tenantId, string $type, array $data = []): int
    {
        $message = $this->buildNotificationMessage($type, $data);

        if (empty($message)) {
            Log::warning('TelegramBotService: unknown notification type', ['type' => $type]);
            return 0;
        }

        // Send to admins
        $sent = $this->sendToAdmin($message);

        // If a specific chat_id is provided in data, send there too
        if (!empty($data['chat_id'])) {
            $this->sendMessage($data['chat_id'], $message);
        }

        return $sent;
    }

    /**
     * Send a notification for a newly created VPN account EXCLUSIVELY to SuperAdmin (routed to VPN topic).
     */
    public function sendVpnCreatedNotification($account): int
    {
        $message = $this->buildVpnCreatedMessage(['account' => $account]);

        if (empty($message)) {
            return 0;
        }

        try {
            $tgService = app(\App\Services\TelegramService::class);
            if ($tgService->isConfigured()) {
                $res = $tgService->sendAdminNotification($message, 'vpn', 'HTML');
                if ($res['ok'] ?? false) {
                    return 1;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram sendAdminNotification error: ' . $e->getMessage());
        }

        $sent = 0;

        // Fallback 1: Direct send via TelegramBotService
        if ($this->isConfigured()) {
            $sent = $this->sendToAdmin($message);
        }

        // Fallback 2: Direct HTTP fallback if TelegramBotService constructor had missing state
        if ($sent === 0) {
            try {
                $botToken = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_BOT_TOKEN')->whereNull('tenant_id')->value('value')
                    ?: env('TELEGRAM_BOT_TOKEN', '');
                $adminChatIdsRaw = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_ADMIN_CHAT_IDS')->whereNull('tenant_id')->value('value')
                    ?: env('TELEGRAM_ADMIN_CHAT_IDS', '');

                if (!empty($botToken) && !empty($adminChatIdsRaw)) {
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    foreach (array_filter(array_map('trim', explode(',', $adminChatIdsRaw))) as $cid) {
                        $response = Http::timeout(10)->post($url, [
                            'chat_id'    => $cid,
                            'text'       => $message,
                            'parse_mode' => 'HTML',
                        ]);
                        if ($response->successful() && ($response->json()['ok'] ?? false) === true) {
                            $sent++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Telegram sendVpnCreatedNotification fallback error: ' . $e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * Build a formatted notification message for the given type.
     */
    private function buildNotificationMessage(string $type, array $data): string
    {
        return match ($type) {
            'invoice_unpaid' => $this->buildUnpaidInvoiceMessage($data),
            'payment_received' => $this->buildPaymentReceivedMessage($data),
            'ticket_created' => $this->buildTicketCreatedMessage($data),
            'customer_isolated' => $this->buildCustomerIsolatedMessage($data),
            'vpn_created' => $this->buildVpnCreatedMessage($data),
            default => '',
        };
    }

    /**
     * Format: VPN Account Created Notification.
     */
    public function buildVpnCreatedMessage(array|object $data): string
    {
        $account = $data['account'] ?? $data;
        if (is_array($account) && isset($account['account'])) {
            $account = $account['account'];
        }

        $vpnUser = is_object($account) ? ($account->vpnUser ?? null) : ($account['vpn_user'] ?? null);
        $server  = is_object($account) ? ($account->server ?? null) : ($account['server'] ?? null);

        $userId   = is_object($vpnUser) ? ($vpnUser->telegram_id ?? $vpnUser->id ?? '-') : ($vpnUser['telegram_id'] ?? $vpnUser['id'] ?? '-');
        $userName = is_object($vpnUser) ? ($vpnUser->name ?? 'N/A') : ($vpnUser['name'] ?? 'N/A');
        $tgUsername = is_object($vpnUser) ? ($vpnUser->telegram_username ?? null) : ($vpnUser['telegram_username'] ?? null);
        $tgHandle = $tgUsername ? ' (@' . ltrim($tgUsername, '@') . ')' : '';
        $email    = is_object($vpnUser) ? ($vpnUser->email ?? '-') : ($vpnUser['email'] ?? '-');

        $username = is_object($account) ? ($account->vpn_username ?? '-') : ($account['vpn_username'] ?? '-');
        $password = is_object($account) ? ($account->vpn_password ?? '-') : ($account['vpn_password'] ?? '-');
        $protoVal = is_object($account) ? ($account->protocol ?? 'L2TP') : ($account['protocol'] ?? 'L2TP');
        $protocol = strtoupper(is_array($protoVal) ? ($protoVal[0] ?? 'L2TP') : (string) $protoVal);
        $ipStatic = is_object($account) ? ($account->ip_static ?? '-') : ($account['ip_static'] ?? '-');

        $host = is_object($server)
            ? (!empty($server->server_domain) ? $server->server_domain : (!empty($server->host) ? $server->host : ($server->server_ip ?? '-')))
            : (!empty($server['server_domain']) ? $server['server_domain'] : (!empty($server['host']) ? $server['host'] : ($server['server_ip'] ?? '-')));
        $type = strtoupper(is_object($account) ? ($account->type ?? 'REMOT') : ($account['type'] ?? 'REMOT'));

        $rawPorts = is_object($account) ? ($account->ports ?? []) : ($account['ports'] ?? []);
        $portLines = [];

        $portCount = is_array($rawPorts) ? count($rawPorts) : 0;
        $targetList = [80, 8291, 8728, 22, 8132];
        if ($portCount === 1) {
            $targetList = [8291];
        } elseif ($portCount === 2) {
            $targetList = [8291, 8728];
        }

        $portNames = [
            80 => 'WEB',
            8291 => 'WINBOX',
            8728 => 'API',
            22 => 'SSH',
            8132 => 'OLT',
        ];

        if (is_array($rawPorts)) {
            foreach ($rawPorts as $idx => $publicPort) {
                if (is_array($publicPort)) {
                    $target = (int)($publicPort['target'] ?? $publicPort['target_port'] ?? 80);
                    $pub = (int)($publicPort['port'] ?? $publicPort['public'] ?? $publicPort['public_port'] ?? 80);
                } else {
                    $target = $targetList[$idx] ?? (8000 + $idx);
                    $pub = $publicPort;
                }

                $label = $portNames[$target] ?? ("PORT (" . $target . ")");
                $isWeb = str_contains(strtolower($label), 'web') || str_contains(strtolower($label), 'olt') || $target === 80 || $target === 8132;
                $url = $isWeb ? "http://{$host}:{$pub}" : "{$host}:{$pub}";

                $portLines[] = "{$label}: {$url}";
            }
        }

        $createdAt = (is_object($account) && $account->created_at)
            ? $account->created_at->format('d M Y H:i')
            : now()->format('d M Y H:i');

        $serverName = is_object($server) ? ($server->name ?? $host) : ($server['name'] ?? $host);

        $text = "<b>🔑 VPN {$type} BERHASIL DIBUAT — NODERA</b>\n\n";
        $text .= "┌ {$username}\n";
        $text .= "├ Username: {$username}\n";
        $text .= "├ Password: {$password}\n";
        $text .= "├ Protokol: {$protocol}\n";
        $text .= "├ Server: {$serverName}\n";
        $text .= "├ Host: {$host}\n";
        $text .= "├ IP Static: {$ipStatic}\n";
        $text .= "├ Owner ID: {$userId}\n";
        $text .= "├ Pembuat: " . htmlspecialchars($userName . $tgHandle) . "\n";
        $text .= "├ Email: " . htmlspecialchars($email) . "\n";
        $text .= "└ Dibuat: {$createdAt}\n\n";

        if (!empty($portLines)) {
            $text .= "┌ Akses Port\n";
            $totalPorts = count($portLines);
            foreach ($portLines as $idx => $pline) {
                $isLast = ($idx === $totalPorts - 1);
                $prefix = $isLast ? '└' : '├';
                $text .= "{$prefix} {$pline}\n";
            }
        }

        return $text;
    }

    /**
     * Format: Unpaid invoice reminder.
     */
    private function buildUnpaidInvoiceMessage(array $data): string
    {
        $customer = $data['customer'] ?? [];
        $invoice  = $data['invoice'] ?? [];
        $tenant   = $data['tenant'] ?? [];

        $customerName = $customer['name'] ?? ($data['customer_name'] ?? 'N/A');
        $invoiceId    = $invoice['id'] ?? ($data['invoice_id'] ?? 'N/A');
        $amount       = $invoice['amount'] ?? ($data['amount'] ?? 0);
        $dueDate      = $invoice['due_date'] ?? ($data['due_date'] ?? 'N/A');
        $period       = $invoice['period_month'] ?? ($data['period_month'] ?? '');
        if (!empty($period)) {
            $period .= ' ' . ($invoice['period_year'] ?? ($data['period_year'] ?? ''));
        }

        $company = $tenant['name'] ?? ($data['company_name'] ?? env('APP_NAME', 'ISP'));

        $text = "<b>⚠️ PENGINGAT TAGIHAN — NODERA</b>\n\n";
        $text .= "┌ " . htmlspecialchars($company) . "\n";
        $text .= "├ Pelanggan: <b>" . htmlspecialchars($customerName) . "</b>\n";
        $text .= "├ No. Invoice: <code>#{$invoiceId}</code>\n";
        if (!empty($period)) {
            $text .= "├ Periode: " . htmlspecialchars($period) . "\n";
        }
        $text .= "├ Jumlah: Rp " . number_format((float) $amount, 0, ',', '.') . "\n";
        $text .= "├ Jatuh Tempo: " . htmlspecialchars($dueDate) . "\n";
        $text .= "└ Status: BELUM DIBAYAR\n\n";
        $text .= "Mohon lakukan pembayaran sebelum jatuh tempo untuk menghindari isolir otomatis.";

        return $text;
    }

    /**
     * Format: Payment received notification.
     */
    private function buildPaymentReceivedMessage(array $data): string
    {
        $customer = $data['customer'] ?? [];
        $invoice  = $data['invoice'] ?? [];
        $payment  = $data['payment'] ?? [];
        $tenant   = $data['tenant'] ?? [];

        $customerName = $customer['name'] ?? ($data['customer_name'] ?? 'N/A');
        $invoiceId    = $invoice['id'] ?? ($data['invoice_id'] ?? 'N/A');
        $amount       = $invoice['amount'] ?? ($data['amount'] ?? 0);
        $paidAt       = $payment['paid_at'] ?? ($data['paid_at'] ?? now()->format('d/m/Y H:i:s'));
        $method       = $payment['method'] ?? ($data['payment_method'] ?? '-');

        $company = $tenant['name'] ?? ($data['company_name'] ?? env('APP_NAME', 'ISP'));

        $text = "<b>✅ PEMBAYARAN DITERIMA — NODERA</b>\n\n";
        $text .= "┌ " . htmlspecialchars($company) . "\n";
        $text .= "├ Pelanggan: <b>" . htmlspecialchars($customerName) . "</b>\n";
        $text .= "├ No. Invoice: <code>#{$invoiceId}</code>\n";
        $text .= "├ Jumlah: Rp " . number_format((float) $amount, 0, ',', '.') . "\n";
        $text .= "├ Metode: " . htmlspecialchars($method) . "\n";
        $text .= "├ Status: LUNAS\n";
        $text .= "└ Waktu: " . htmlspecialchars($paidAt);

        return $text;
    }

    /**
     * Format: Trouble ticket created notification.
     */
    private function buildTicketCreatedMessage(array $data): string
    {
        $customer   = $data['customer'] ?? [];
        $ticket     = $data['ticket'] ?? [];
        $tenant     = $data['tenant'] ?? [];

        $customerName = $customer['name'] ?? ($data['customer_name'] ?? 'N/A');
        $customerPhone = $customer['phone'] ?? ($data['customer_phone'] ?? '-');
        $ticketId    = $ticket['id'] ?? ($data['ticket_id'] ?? 'N/A');
        $category    = $ticket['category'] ?? ($data['category'] ?? '-');
        $priority    = $ticket['priority'] ?? ($data['priority'] ?? 'normal');
        $description = $ticket['description'] ?? ($data['description'] ?? '-');

        $company = $tenant['name'] ?? ($data['company_name'] ?? env('APP_NAME', 'ISP'));

        $priorityLabel = match (strtolower($priority)) {
            'high'   => 'HIGH',
            'medium' => 'MEDIUM',
            'low'    => 'LOW',
            default  => ucfirst($priority),
        };

        $text = "<b>🎫 TIKET GANGGUAN BARU — NODERA</b>\n\n";
        $text .= "┌ " . htmlspecialchars($company) . "\n";
        $text .= "├ ID Tiket: <code>#{$ticketId}</code>\n";
        $text .= "├ Pelanggan: <b>" . htmlspecialchars($customerName) . "</b>\n";
        $text .= "├ No. Telepon: " . htmlspecialchars($customerPhone) . "\n";
        $text .= "├ Kategori: " . htmlspecialchars($category) . "\n";
        $text .= "├ Prioritas: {$priorityLabel}\n";
        $text .= "└ Deskripsi: " . htmlspecialchars($description) . "\n\n";
        $text .= "Harap segera ditindaklanjuti oleh teknisi terkait.";

        return $text;
    }

    /**
     * Format: Customer isolated notification.
     */
    private function buildCustomerIsolatedMessage(array $data): string
    {
        $customer = $data['customer'] ?? [];
        $invoice  = $data['invoice'] ?? [];
        $tenant   = $data['tenant'] ?? [];

        $customerName   = $customer['name'] ?? ($data['customer_name'] ?? 'N/A');
        $customerPhone  = $customer['phone'] ?? ($data['customer_phone'] ?? '-');
        $invoiceId      = $invoice['id'] ?? ($data['invoice_id'] ?? 'N/A');
        $amount         = $invoice['amount'] ?? ($data['amount'] ?? 0);
        $overdueDays    = $data['overdue_days'] ?? '-';

        $company = $tenant['name'] ?? ($data['company_name'] ?? env('APP_NAME', 'ISP'));

        $text = "<b>🚫 ISOLIR PELANGGAN — NODERA</b>\n\n";
        $text .= "┌ " . htmlspecialchars($company) . "\n";
        $text .= "├ Pelanggan: <b>" . htmlspecialchars($customerName) . "</b>\n";
        $text .= "├ No. Telepon: " . htmlspecialchars($customerPhone) . "\n";
        $text .= "├ No. Invoice: <code>#{$invoiceId}</code>\n";
        $text .= "├ Tagihan: Rp " . number_format((float) $amount, 0, ',', '.') . "\n";
        $text .= "├ Keterlambatan: {$overdueDays} hari\n";
        $text .= "└ Status: TERISOLIR\n\n";
        $text .= "Layanan akan diaktifkan kembali setelah pelunasan tagihan dilakukan.";

        return $text;
    }
}
