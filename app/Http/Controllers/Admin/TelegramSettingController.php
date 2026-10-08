<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantTelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TelegramSettingController extends Controller
{
    private function getTenantId(): int
    {
        return (int) (auth()->user()->tenant_id ?? session('tenant_id'));
    }

    public function index()
    {
        $tenantId = $this->getTenantId();
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);
        $settings = $tenant->settings ?? [];

        $superadminBotUsername = \App\Models\Setting::withoutGlobalScopes()
            ->where('key', 'TELEGRAM_BOT_USERNAME')
            ->whereNull('tenant_id')
            ->value('value')
            ?: env('TELEGRAM_BOT_USERNAME', 'NoderaCenterBot');

        return Inertia::render('Admin/Settings/Telegram', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'superadminBotUsername' => ltrim($superadminBotUsername, '@'),
            'telegramSettings' => [
                'telegram_bot_token' => $settings['telegram_bot_token'] ?? '',
                'telegram_mode' => $settings['telegram_mode'] ?? 'chat',
                'telegram_chat_id' => $settings['telegram_chat_id'] ?? '',
                'telegram_topic_id' => $settings['telegram_topic_id'] ?? '',
                'telegram_topic_nms' => $settings['telegram_topic_nms'] ?? '',
                'telegram_topic_router' => $settings['telegram_topic_router'] ?? '',
                'telegram_topic_pppoe' => $settings['telegram_topic_pppoe'] ?? '',
                'telegram_topic_hotspot' => $settings['telegram_topic_hotspot'] ?? '',
                'telegram_topic_arp' => $settings['telegram_topic_arp'] ?? '',
                'telegram_enabled' => (bool) ($settings['telegram_enabled'] ?? true),
                'telegram_order_notif' => (bool) ($settings['telegram_order_notif'] ?? true),
                'telegram_nms_notif' => (bool) ($settings['telegram_nms_notif'] ?? true),
                'telegram_nms_router' => (bool) ($settings['telegram_nms_router'] ?? true),
                'telegram_nms_pppoe' => (bool) ($settings['telegram_nms_pppoe'] ?? true),
                'telegram_nms_hotspot' => (bool) ($settings['telegram_nms_hotspot'] ?? false),
                'telegram_nms_arp' => (bool) ($settings['telegram_nms_arp'] ?? false),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = $this->getTenantId();
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

        $validated = $request->validate([
            'telegram_bot_token' => 'nullable|string|max:200',
            'telegram_mode' => 'required|in:chat,topic',
            'telegram_chat_id' => 'nullable|string|max:100',
            'telegram_topic_id' => 'nullable|string|max:100',
            'telegram_topic_nms' => 'nullable|string|max:100',
            'telegram_topic_router' => 'nullable|string|max:100',
            'telegram_topic_pppoe' => 'nullable|string|max:100',
            'telegram_topic_hotspot' => 'nullable|string|max:100',
            'telegram_topic_arp' => 'nullable|string|max:100',
            'telegram_enabled' => 'nullable|boolean',
            'telegram_order_notif' => 'nullable|boolean',
            'telegram_nms_notif' => 'nullable|boolean',
            'telegram_nms_router' => 'nullable|boolean',
            'telegram_nms_pppoe' => 'nullable|boolean',
            'telegram_nms_hotspot' => 'nullable|boolean',
            'telegram_nms_arp' => 'nullable|boolean',
        ]);

        $settings = $tenant->settings ?? [];

        $settings['telegram_bot_token'] = trim($validated['telegram_bot_token'] ?? '');
        $settings['telegram_mode'] = $validated['telegram_mode'];
        $settings['telegram_chat_id'] = trim($validated['telegram_chat_id'] ?? '');
        $settings['telegram_topic_id'] = trim($validated['telegram_topic_id'] ?? '');
        $settings['telegram_topic_nms'] = trim($validated['telegram_topic_nms'] ?? '');
        $settings['telegram_topic_router'] = trim($validated['telegram_topic_router'] ?? '');
        $settings['telegram_topic_pppoe'] = trim($validated['telegram_topic_pppoe'] ?? '');
        $settings['telegram_topic_hotspot'] = trim($validated['telegram_topic_hotspot'] ?? '');
        $settings['telegram_topic_arp'] = trim($validated['telegram_topic_arp'] ?? '');
        $settings['telegram_enabled'] = $request->boolean('telegram_enabled', true);
        $settings['telegram_order_notif'] = $request->boolean('telegram_order_notif', true);
        $settings['telegram_nms_notif'] = $request->boolean('telegram_nms_notif', true);
        $settings['telegram_nms_router'] = $request->boolean('telegram_nms_router', true);
        $settings['telegram_nms_pppoe'] = $request->boolean('telegram_nms_pppoe', true);
        $settings['telegram_nms_hotspot'] = $request->boolean('telegram_nms_hotspot', false);
        $settings['telegram_nms_arp'] = $request->boolean('telegram_nms_arp', false);

        $tenant->settings = $settings;
        $tenant->save();

        // Auto-register webhook for this custom bot token so callback buttons work immediately
        if (!empty($settings['telegram_bot_token'])) {
            try {
                $tenantBot = new \App\Services\TelegramService($settings['telegram_bot_token']);
                $tenantBot->setWebhook();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Auto setWebhook failed for tenant {$tenantId}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('msg', 'Pengaturan Telegram Bot & Notifikasi NMS berhasil disimpan!');
    }

    public function testTelegram(Request $request, TenantTelegramService $telegramService): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $request->validate([
            'telegram_bot_token' => 'required|string|max:200',
            'telegram_chat_id' => 'required|string|max:100',
            'telegram_mode' => 'required|in:chat,topic',
            'telegram_topic_id' => 'nullable|string|max:100',
            'telegram_topic_nms' => 'nullable|string|max:100',
            'telegram_topic_router' => 'nullable|string|max:100',
            'telegram_topic_pppoe' => 'nullable|string|max:100',
            'telegram_topic_hotspot' => 'nullable|string|max:100',
            'telegram_topic_arp' => 'nullable|string|max:100',
            'test_type' => 'nullable|in:order,nms,pppoe,hotspot,arp,router,general',
        ], [
            'telegram_bot_token.required' => 'Token Bot Telegram wajib diisi dari @BotFather.',
            'telegram_chat_id.required' => 'Chat ID / Group ID Telegram wajib diisi.',
        ]);

        $chatId = trim((string) $request->input('telegram_chat_id'));
        $mode = $request->input('telegram_mode', 'chat');
        $token = trim((string) $request->input('telegram_bot_token'));
        $testType = $request->input('test_type', 'general');

        $topicId = null;
        if ($mode === 'topic') {
            if ($testType === 'order') {
                $topicId = trim((string) $request->input('telegram_topic_id'));
            } elseif ($testType === 'pppoe') {
                $topicId = trim((string) $request->input('telegram_topic_pppoe')) 
                    ?: trim((string) $request->input('telegram_topic_nms')) 
                    ?: trim((string) $request->input('telegram_topic_id'));
            } elseif ($testType === 'hotspot') {
                $topicId = trim((string) $request->input('telegram_topic_hotspot')) 
                    ?: trim((string) $request->input('telegram_topic_nms')) 
                    ?: trim((string) $request->input('telegram_topic_id'));
            } elseif ($testType === 'router') {
                $topicId = trim((string) $request->input('telegram_topic_router')) 
                    ?: trim((string) $request->input('telegram_topic_nms')) 
                    ?: trim((string) $request->input('telegram_topic_id'));
            } elseif ($testType === 'arp') {
                $topicId = trim((string) $request->input('telegram_topic_arp')) 
                    ?: trim((string) $request->input('telegram_topic_nms')) 
                    ?: trim((string) $request->input('telegram_topic_id'));
            } else {
                // nms / general
                $topicId = trim((string) $request->input('telegram_topic_nms')) 
                    ?: trim((string) $request->input('telegram_topic_id'));
            }
        }

        $result = $telegramService->testConnection($tenantId, $token, $chatId, $mode, $topicId, $testType);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
