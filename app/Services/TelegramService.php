<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TelegramService
{
    private ?string $botToken;
    private string $apiUrl;
    private Client $httpClient;

    public function __construct(?string $customToken = null)
    {
        if (!empty($customToken)) {
            $this->botToken = trim($customToken);
        } else {
            $this->botToken = \App\Models\Setting::withoutGlobalScopes()
                ->where('key', 'TELEGRAM_BOT_TOKEN')
                ->whereNull('tenant_id')
                ->value('value')
                ?: \App\Models\Setting::apiValue('TELEGRAM_BOT_TOKEN', '')
                ?: env('TELEGRAM_BOT_TOKEN', '');
        }

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";

        $this->httpClient = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 15,
        ]);
    }

    /**
     * Switch or update the active bot token for this service instance.
     */
    public function setBotToken(string $token): self
    {
        $this->botToken = trim($token);
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
        $this->httpClient = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 15,
        ]);
        return $this;
    }

    /**
     * Get the active bot token.
     */
    public function getBotToken(): ?string
    {
        return $this->botToken;
    }

    /**
     * Retrieve all candidate Telegram bot tokens configured in the system (SuperAdmin & Tenants).
     */
    public static function getAllCandidateBotTokens(): array
    {
        $tokens = [];

        // Global DB setting
        try {
            $globalDb = \App\Models\Setting::withoutGlobalScopes()
                ->where('key', 'TELEGRAM_BOT_TOKEN')
                ->whereNull('tenant_id')
                ->value('value');
            if (!empty($globalDb)) {
                $tokens[] = trim($globalDb);
            }
        } catch (\Throwable $e) {}

        // .env setting
        $envToken = env('TELEGRAM_BOT_TOKEN');
        if (!empty($envToken)) {
            $tokens[] = trim($envToken);
        }

        // All tenant settings
        try {
            $tenants = \App\Models\Tenant::withoutGlobalScopes()->whereNotNull('settings')->get();
            foreach ($tenants as $t) {
                $tToken = $t->settings['telegram_bot_token'] ?? null;
                if (!empty($tToken)) {
                    $tokens[] = trim($tToken);
                }
            }
        } catch (\Throwable $e) {}

        // Tenant-scoped Settings table
        try {
            $scopedSettings = \App\Models\Setting::withoutGlobalScopes()
                ->where('key', 'TELEGRAM_BOT_TOKEN')
                ->whereNotNull('tenant_id')
                ->pluck('value')
                ->toArray();
            foreach ($scopedSettings as $st) {
                if (!empty($st)) {
                    $tokens[] = trim($st);
                }
            }
        } catch (\Throwable $e) {}

        return array_values(array_unique(array_filter($tokens)));
    }

    /**
     * Build the canonical, public HTTPS webhook URL for a given bot token.
     */
    public static function buildWebhookUrl(?string $botToken = null): string
    {
        $appUrl = config('app.url', 'https://nodera.id');
        $host = '';

        try {
            $host = request()?->getHost();
        } catch (\Throwable) {}

        if (empty($host) || in_array($host, ['localhost', '127.0.0.1'])) {
            $host = parse_url($appUrl, PHP_URL_HOST) ?: 'nodera.id';
        }

        $scheme = 'https';
        try {
            if (request()?->isSecure() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
                $scheme = 'https';
            } elseif (str_starts_with($appUrl, 'http://') && in_array($host, ['localhost', '127.0.0.1'])) {
                $scheme = 'http';
            }
        } catch (\Throwable) {}

        $url = "{$scheme}://{$host}/api/webhook/telegram";
        if (!empty($botToken)) {
            $defaultToken = \App\Models\Setting::withoutGlobalScopes()
                ->where('key', 'TELEGRAM_BOT_TOKEN')
                ->whereNull('tenant_id')
                ->value('value')
                ?: \App\Models\Setting::apiValue('TELEGRAM_BOT_TOKEN', '')
                ?: env('TELEGRAM_BOT_TOKEN', '');

            if (!empty($defaultToken) && trim($botToken) === trim($defaultToken)) {
                return $url;
            }

            $url .= '?token=' . urlencode(trim($botToken));
        }

        return $url;
    }

    /**
     * Register or update the webhook URL with Telegram API for this bot.
     */
    public function registerWebhook(?string $webhookUrl = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $url = $webhookUrl ?: self::buildWebhookUrl($this->botToken);

        return $this->apiRequest('setWebhook', [
            'url' => $url,
            'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
        ]);
    }

    /**
     * Ensure the webhook is registered with Telegram for this bot token.
     * Caches registration status to avoid redundant Telegram API calls.
     */
    public function ensureWebhookRegistered(bool $force = false): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $cacheKey = 'telegram_webhook_registered_' . substr(md5($this->botToken), 0, 16);
        if (!$force && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return ['ok' => true, 'description' => 'Webhook already verified'];
        }

        $res = $this->setWebhook();
        if ($res['ok'] ?? false) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addHours(12));
        }

        return $res;
    }

    /**
     * Check if the bot token is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Send a text message with optional forum topic (message_thread_id) support.
     */
    public function sendMessage(string $chatId, string $text, string $parseMode = 'Markdown', ?array $replyMarkup = null, $messageThreadId = null): array
    {
        if (! $this->isConfigured()) {
            \Illuminate\Support\Facades\Log::warning('TelegramService: botToken not configured.');
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        // Auto-ensure webhook registration whenever inline buttons are attached
        if ($replyMarkup !== null) {
            $this->ensureWebhookRegistered();
        }

        $data = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        if (!empty($messageThreadId) && is_numeric($messageThreadId) && (int) $messageThreadId > 0) {
            $data['message_thread_id'] = (int) $messageThreadId;
        }

        $res = $this->apiRequest('sendMessage', $data);

        // Fallback: If sending to a specific topic failed because topic was deleted/not found, retry sending to main group chat
        if (!empty($data['message_thread_id']) && !($res['ok'] ?? false) && isset($res['description']) && (str_contains($res['description'], 'thread not found') || str_contains($res['description'], 'message thread'))) {
            unset($data['message_thread_id']);
            $res = $this->apiRequest('sendMessage', $data);
        }

        return $res;
    }

    /**
     * Get all configured Superadmin / Admin Chat IDs.
     */
    public function getSuperadminChatIds(): array
    {
        $chatIdsStr = (string) (
            Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_SUPERADMIN_CHAT_ID')->whereNull('tenant_id')->value('value')
            ?: Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_ADMIN_CHAT_IDS')->whereNull('tenant_id')->value('value')
            ?: Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_ADMIN_CHAT_ID')->whereNull('tenant_id')->value('value')
            ?: Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_CHAT_ID')->whereNull('tenant_id')->value('value')
            ?: Setting::apiValue('TELEGRAM_SUPERADMIN_CHAT_ID', '')
            ?: Setting::apiValue('TELEGRAM_ADMIN_CHAT_IDS', '')
            ?: env('TELEGRAM_SUPERADMIN_CHAT_ID', '')
            ?: env('TELEGRAM_ADMIN_CHAT_IDS', '')
            ?: env('TELEGRAM_ADMIN_CHAT_ID', '')
            ?: env('TELEGRAM_CHAT_ID', '')
        );

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $chatIdsStr)))));
    }

    /**
     * Retract a specific Telegram message (strip buttons + delete message) across configured chat IDs.
     */
    public function retractMessage(int $messageId, ?string $specificChatId = null): bool
    {
        if (!$this->isConfigured() || $messageId <= 0) {
            return false;
        }

        $chatIds = !empty($specificChatId) ? [$specificChatId] : $this->getSuperadminChatIds();
        $success = false;

        foreach ($chatIds as $cid) {
            try {
                $this->editMessageReplyMarkup((string) $cid, (int) $messageId, ['inline_keyboard' => []]);
            } catch (\Throwable $e) {}

            try {
                $delRes = $this->deleteMessage((string) $cid, (int) $messageId);
                if ($delRes['ok'] ?? false) {
                    $success = true;
                }
            } catch (\Throwable $e) {}
        }

        return $success;
    }

    /**
     * Send an alert or notification to Superadmin / Admin Group, automatically directing to the specified topic if configured.
     *
     * @param string $text Message body
     * @param string $topicType 'error' | 'topup' | 'registration' | 'general' or specific topic thread ID
     * @param string $parseMode 'HTML' or 'Markdown'
     * @param array|null $replyMarkup
     * @return array
     */
    public function sendAdminNotification(string $text, string $topicType = 'general', string $parseMode = 'HTML', ?array $replyMarkup = null): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        if ($replyMarkup !== null) {
            $this->ensureWebhookRegistered();
        }

        $chatIds = $this->getSuperadminChatIds();
        if (! $chatIds) {
            return ['ok' => false, 'description' => 'No Superadmin/Admin Chat ID configured'];
        }

        // Resolve Forum Topic ID (message_thread_id)
        $threadId = null;
        if (is_numeric($topicType) && (int) $topicType > 0) {
            $threadId = (int) $topicType;
        } else {
            $topicKey = match (strtolower((string) $topicType)) {
                'error'                                           => 'TELEGRAM_TOPIC_ERROR_ID',
                'merchant', 'noderapay_merchant', 'merchant_registration', 'noderapay' => 'TELEGRAM_TOPIC_MERCHANT_ID',
                'withdrawal', 'withdraw', 'settlement', 'payout'  => 'TELEGRAM_TOPIC_WITHDRAWAL_ID',
                'topup', 'deposit', 'finance'                     => 'TELEGRAM_TOPIC_TOPUP_ID',
                'registration', 'register', 'tenant', 'fai'       => 'TELEGRAM_TOPIC_REGISTRATION_ID',
                'vpn', 'vpn_created', 'vpn_account'               => 'TELEGRAM_TOPIC_VPN_ID',
                'mikhmon', 'mikhmon_created', 'mikhmon_order'     => 'TELEGRAM_TOPIC_MIKHMON_ID',
                'general', 'umum', 'order', 'pesanan', 'commande' => 'TELEGRAM_TOPIC_GENERAL_ID',
                default                                           => 'TELEGRAM_TOPIC_GENERAL_ID',
            };

            if ($topicKey) {
                $rawVal = Setting::withoutGlobalScopes()->where('key', $topicKey)->whereNull('tenant_id')->value('value')
                    ?: Setting::apiValue($topicKey, '')
                    ?: env($topicKey, '');
                
                // Fallback for specific topics to GENERAL, REGISTRATION, or TOPUP if specific topic is not configured
                if (empty($rawVal) && $topicKey === 'TELEGRAM_TOPIC_MERCHANT_ID') {
                    $rawVal = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_TOPIC_REGISTRATION_ID')->whereNull('tenant_id')->value('value')
                        ?: Setting::apiValue('TELEGRAM_TOPIC_REGISTRATION_ID', '')
                        ?: env('TELEGRAM_TOPIC_REGISTRATION_ID', '');
                }
                if (empty($rawVal) && $topicKey === 'TELEGRAM_TOPIC_WITHDRAWAL_ID') {
                    $rawVal = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_TOPIC_TOPUP_ID')->whereNull('tenant_id')->value('value')
                        ?: Setting::apiValue('TELEGRAM_TOPIC_TOPUP_ID', '')
                        ?: env('TELEGRAM_TOPIC_TOPUP_ID', '');
                }
                if (empty($rawVal) && in_array($topicKey, ['TELEGRAM_TOPIC_VPN_ID', 'TELEGRAM_TOPIC_MIKHMON_ID', 'TELEGRAM_TOPIC_REGISTRATION_ID', 'TELEGRAM_TOPIC_MERCHANT_ID', 'TELEGRAM_TOPIC_TOPUP_ID', 'TELEGRAM_TOPIC_WITHDRAWAL_ID', 'TELEGRAM_TOPIC_ERROR_ID'])) {
                    $rawVal = Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_TOPIC_GENERAL_ID')->whereNull('tenant_id')->value('value')
                        ?: Setting::apiValue('TELEGRAM_TOPIC_GENERAL_ID', '')
                        ?: env('TELEGRAM_TOPIC_GENERAL_ID', '');
                }

                if (!empty($rawVal) && is_numeric(trim((string) $rawVal)) && (int) $rawVal > 0) {
                    $threadId = (int) $rawVal;
                }
            }
        }

        $results = [];
        foreach ($chatIds as $cid) {
            $results[] = $this->sendMessage($cid, $text, $parseMode, $replyMarkup, $threadId);
        }

        $first = $results[0] ?? ['ok' => false, 'description' => 'No chat destination'];
        if (($first['ok'] ?? false) && isset($first['result'])) {
            $first['message_id'] = $first['result']['message_id'] ?? null;
            $first['chat_id'] = $first['result']['chat']['id'] ?? null;
        }

        return $first;
    }

    /**
     * Edit an existing message text with optional reply markup.
     */
    public function editMessage(string $chatId, int $messageId, string $text, string $parseMode = 'Markdown', ?array $replyMarkup = null): array
    {
        $res = $this->editMessageDirect($chatId, $messageId, $text, $parseMode, $replyMarkup);

        // Fallback: If message edit failed on current bot (wrong bot, not found, unauthorized, etc.), try all candidate bot tokens
        if (!($res['ok'] ?? false)) {
            $candidates = self::getAllCandidateBotTokens();
            foreach ($candidates as $candidateToken) {
                if ($candidateToken === $this->botToken) {
                    continue;
                }
                try {
                    $altService = new self($candidateToken);
                    $altRes = $altService->editMessageDirect($chatId, $messageId, $text, $parseMode, $replyMarkup);
                    if ($altRes['ok'] ?? false) {
                        $this->setBotToken($candidateToken);
                        return $altRes;
                    }
                } catch (\Throwable $e) {}
            }
        }

        return $res;
    }

    /**
     * Direct edit message without token hopping.
     */
    public function editMessageDirect(string $chatId, int $messageId, string $text, string $parseMode = 'Markdown', ?array $replyMarkup = null): array
    {
        $data = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        $res = $this->apiRequest('editMessageText', $data);

        // Fallback 1: If HTML/Markdown entity parsing fails, retry with plain text (no parse_mode)
        if (!($res['ok'] ?? false) && isset($res['description']) && str_contains(strtolower($res['description']), 'parse')) {
            $plainData = [
                'chat_id'    => $chatId,
                'message_id' => $messageId,
                'text'       => strip_tags($text),
            ];
            if ($replyMarkup !== null) {
                $plainData['reply_markup'] = json_encode($replyMarkup);
            }
            $res = $this->apiRequest('editMessageText', $plainData);
        }

        return $res;
    }

    /**
     * Edit or remove reply markup (inline keyboard) for an existing message.
     */
    public function editMessageReplyMarkup(string $chatId, int $messageId, ?array $replyMarkup = null): array
    {
        $data = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ];

        if ($replyMarkup !== null) {
            $data['reply_markup'] = json_encode($replyMarkup);
        } else {
            $data['reply_markup'] = json_encode(['inline_keyboard' => []]);
        }

        $res = $this->apiRequest('editMessageReplyMarkup', $data);

        // Fallback for candidate bot tokens on any failure
        if (!($res['ok'] ?? false)) {
            $candidates = self::getAllCandidateBotTokens();
            foreach ($candidates as $candidateToken) {
                if ($candidateToken === $this->botToken) {
                    continue;
                }
                try {
                    $altService = new self($candidateToken);
                    $altRes = $altService->apiRequest('editMessageReplyMarkup', $data);
                    if ($altRes['ok'] ?? false) {
                        $this->setBotToken($candidateToken);
                        return $altRes;
                    }
                } catch (\Throwable $e) {}
            }
        }

        return $res;
    }

    /**
     * Delete an existing message from Telegram chat.
     */
    public function deleteMessage(string $chatId, int $messageId): array
    {
        $res = $this->deleteMessageDirect($chatId, $messageId);

        // Fallback: If message deletion failed on current bot, try all candidate bot tokens
        if (!($res['ok'] ?? false)) {
            $candidates = self::getAllCandidateBotTokens();
            foreach ($candidates as $candidateToken) {
                if ($candidateToken === $this->botToken) {
                    continue;
                }
                try {
                    $altService = new self($candidateToken);
                    $altRes = $altService->deleteMessageDirect($chatId, $messageId);
                    if ($altRes['ok'] ?? false) {
                        $this->setBotToken($candidateToken);
                        return $altRes;
                    }
                } catch (\Throwable $e) {}
            }
        }

        return $res;
    }

    /**
     * Direct delete message without token hopping.
     */
    public function deleteMessageDirect(string $chatId, int $messageId): array
    {
        $data = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ];
        return $this->apiRequest('deleteMessage', $data);
    }

    /**
     * Answer a callback query (prevents loading spinner on inline buttons).
     */
    public function answerCallback(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        if (empty($callbackQueryId)) {
            return ['ok' => false, 'description' => 'Empty callback query ID'];
        }

        $data = [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert,
        ];

        $res = $this->apiRequest('answerCallbackQuery', $data);

        // Fallback: If failed (e.g. wrong bot or unauthorized), attempt fallback with other candidate bot tokens
        if (!($res['ok'] ?? false)) {
            $candidates = self::getAllCandidateBotTokens();
            foreach ($candidates as $candidateToken) {
                if ($candidateToken === $this->botToken) {
                    continue;
                }
                try {
                    $altService = new self($candidateToken);
                    $altRes = $altService->apiRequest('answerCallbackQuery', $data);
                    if ($altRes['ok'] ?? false) {
                        $this->setBotToken($candidateToken);
                        return $altRes;
                    }
                } catch (\Throwable $e) {}
            }
        }

        return $res;
    }

    /**
     * Create a single inline keyboard button.
     */
    public function inlineButton(string $text, string $callbackData): array
    {
        return [
            'text'          => $text,
            'callback_data' => $callbackData,
        ];
    }

    /**
     * Create an inline keyboard markup with rows of buttons.
     */
    public function inlineKeyboard(array $rows): array
    {
        return [
            'inline_keyboard' => $rows,
        ];
    }

    /**
     * Create a URL button (opens a web link instead of sending callback).
     */
    public function urlButton(string $text, string $url): array
    {
        return [
            'text' => $text,
            'url'  => $url,
        ];
    }

    /**
     * Set the webhook URL for the bot.
     */
    public function setWebhook(?string $webhookUrl = null, ?string $secretToken = null): array
    {
        $url = $webhookUrl ?: self::buildWebhookUrl($this->botToken);
        $params = [
            'url' => $url,
            'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
        ];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        return $this->apiRequest('setWebhook', $params);
    }

    /**
     * Get current webhook info.
     */
    public function getWebhookInfo(): array
    {
        return $this->apiRequest('getWebhookInfo', []);
    }

    /**
     * Delete the webhook.
     */
    public function deleteWebhook(): array
    {
        return $this->apiRequest('deleteWebhook', []);
    }

    /**
     * Get basic info about the Telegram bot (getMe).
     */
    public function getMe(): array
    {
        return $this->apiRequest('getMe', []);
    }

    /**
     * Get the configured or cached bot username.
     */
    public function getBotUsername(): ?string
    {
        $username = Setting::withoutGlobalScopes()
            ->where('key', 'TELEGRAM_BOT_USERNAME')
            ->whereNull('tenant_id')
            ->value('value')
            ?: env('TELEGRAM_BOT_USERNAME');

        if (!empty($username)) {
            return ltrim($username, '@');
        }

        if ($this->isConfigured()) {
            $me = $this->getMe();
            if (($me['ok'] ?? false) && !empty($me['result']['username'])) {
                $botUsername = $me['result']['username'];
                Setting::setValue('TELEGRAM_BOT_USERNAME', $botUsername);
                return $botUsername;
            }
        }

        return null;
    }

    /**
     * Make an API request to Telegram via Guzzle.
     */
    private function apiRequest(string $method, array $data): array
    {
        try {
            $response = $this->httpClient->post($method, [
                'form_params' => $data,
            ]);

            $body = $response->getBody()->getContents();

            return json_decode($body, true) ?? [
                'ok'          => false,
                'description' => 'Failed to parse response',
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $rawBody = (string) $e->getResponse()->getBody();
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded) && isset($decoded['description'])) {
                    $desc = $decoded['description'];
                    $hint = match (true) {
                        str_contains($desc, 'chat not found') => 'Chat ID tidak ditemukan. Pastikan bot sudah dimasukkan ke grup atau klik /start jika chat pribadi.',
                        str_contains($desc, 'bot is not a member') || str_contains($desc, 'bot was kicked') => 'Bot belum dimasukkan atau belum menjadi admin di grup Telegram ini.',
                        str_contains($desc, 'message thread not found') => 'ID Topik / Thread ID tidak ditemukan atau fitur Topics belum aktif di grup.',
                        str_contains($desc, 'Unauthorized') => 'Token Bot Telegram salah atau tidak valid.',
                        str_contains($desc, 'not enough rights') => 'Bot tidak memiliki hak akses/izin untuk mengirim pesan di grup ini.',
                        default => null,
                    };
                    if ($hint) {
                        $decoded['description'] = "{$desc} — Solusi: {$hint}";
                    }
                    return $decoded;
                }
            }
            return [
                'ok'          => false,
                'description' => 'Telegram Request Error: ' . $e->getMessage(),
            ];
        } catch (GuzzleException $e) {
            return [
                'ok'          => false,
                'description' => 'HTTP Error: ' . $e->getMessage(),
            ];
        }
    }
}
