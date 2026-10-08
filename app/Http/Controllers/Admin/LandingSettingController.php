<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantTelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LandingSettingController extends Controller
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

        return Inertia::render('Admin/Settings/Landing', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
            ],
            'landingSettings' => [
                'landing_title' => $settings['landing_title'] ?? "Selamat Datang di {$tenant->name}",
                'landing_tagline' => $settings['landing_tagline'] ?? 'Solusi Internet Cepat, Stabil, & Terjangkau',
                'landing_description' => $settings['landing_description'] ?? 'Penyedia layanan internet WiFi Hotspot dan paket bulanan berkualitas tinggi.',
                'landing_banner' => $settings['landing_banner'] ?? null,
                'landing_whatsapp' => $settings['landing_whatsapp'] ?? $tenant->phone ?? '',
                'landing_address' => $settings['landing_address'] ?? $tenant->address ?? '',
                'landing_show_vouchers' => (bool) ($settings['landing_show_vouchers'] ?? true),
                'landing_show_products' => (bool) ($settings['landing_show_products'] ?? true),
                'telegram_enabled' => (bool) ($settings['telegram_enabled'] ?? true),
                'telegram_order_notif' => (bool) ($settings['telegram_order_notif'] ?? true),
                'telegram_bot_token' => $settings['telegram_bot_token'] ?? '',
                'telegram_mode' => $settings['telegram_mode'] ?? 'chat', // 'chat' or 'topic'
                'telegram_chat_id' => $settings['telegram_chat_id'] ?? '',
                'telegram_topic_id' => $settings['telegram_topic_id'] ?? '',
                'telegram_topic_nms' => $settings['telegram_topic_nms'] ?? '',
                'shop_slides' => $settings['shop_slides'] ?? [
                    [
                        'badge' => 'OFFICIAL STORE',
                        'title' => 'Pusat Berbagai Kebutuhan Anda',
                        'desc' => 'Menyediakan beragam produk pilihan, mulai dari voucher wifi, perangkat jaringan, elektronik, hingga kebutuhan internet lainnya.',
                        'icon' => 'fa-shopping-bag',
                        'color' => '#00E5FF',
                    ],
                    [
                        'badge' => 'JARINGAN & SERVER',
                        'title' => 'Router MikroTik & Server Siap Pakai',
                        'desc' => 'Routerboard Gigabit, switch manage, mini PC server, dan perlengkapan jaringan handal dengan performa maksimal.',
                        'icon' => 'fa-server',
                        'color' => '#00E5FF',
                    ],
                    [
                        'badge' => 'VOUCHER INTERNET WIFI',
                        'title' => 'Aktivasi Instan & Otomatis',
                        'desc' => 'Beli voucher wifi hotspot instan, bayar via transfer / QRIS, dan akun langsung otomatis aktif di router.',
                        'icon' => 'fa-wifi',
                        'color' => '#00E5FF',
                    ],
                    [
                        'badge' => 'TRANSAKSI MUDAH & AMAN',
                        'title' => 'Pemesanan Praktis via WhatsApp',
                        'desc' => 'Pilih produk favorit Anda, checkout tanpa ribet, dan konfirmasi pesanan terhubung langsung ke admin.',
                        'icon' => 'fa-whatsapp',
                        'color' => '#25D366',
                    ],
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = $this->getTenantId();
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

        $validated = $request->validate([
            'landing_title' => 'required|string|max:200',
            'landing_tagline' => 'nullable|string|max:255',
            'landing_description' => 'nullable|string|max:1000',
            'landing_whatsapp' => 'nullable|string|max:50',
            'landing_address' => 'nullable|string|max:500',
            'landing_show_vouchers' => 'nullable|boolean',
            'landing_show_products' => 'nullable|boolean',
            'telegram_enabled' => 'nullable|boolean',
            'telegram_order_notif' => 'nullable|boolean',
            'telegram_bot_token' => 'nullable|string|max:200',
            'telegram_mode' => 'nullable|in:chat,topic',
            'telegram_chat_id' => 'nullable|string|max:100',
            'telegram_topic_id' => 'nullable|string|max:100',
            'banner_image' => 'nullable|image|max:2048',
            'shop_slides' => 'nullable|array',
            'shop_slides.*.badge' => 'nullable|string|max:100',
            'shop_slides.*.title' => 'nullable|string|max:150',
            'shop_slides.*.desc' => 'nullable|string|max:500',
            'shop_slides.*.icon' => 'nullable|string|max:50',
            'shop_slides.*.color' => 'nullable|string|max:50',
        ]);

        $settings = $tenant->settings ?? [];

        if ($request->hasFile('banner_image')) {
            if (!empty($settings['landing_banner']) && Storage::disk('public')->exists($settings['landing_banner'])) {
                Storage::disk('public')->delete($settings['landing_banner']);
            }
            $settings['landing_banner'] = $request->file('banner_image')->store('tenant-banners', 'public');
        }

        $settings['landing_title'] = $validated['landing_title'];
        $settings['landing_tagline'] = $validated['landing_tagline'] ?? '';
        $settings['landing_description'] = $validated['landing_description'] ?? '';
        $settings['landing_whatsapp'] = $validated['landing_whatsapp'] ?? '';
        $settings['landing_address'] = $validated['landing_address'] ?? '';
        $settings['landing_show_vouchers'] = $request->boolean('landing_show_vouchers', true);
        $settings['landing_show_products'] = $request->boolean('landing_show_products', true);
        $settings['telegram_order_notif'] = $request->boolean('telegram_order_notif', true);
        if ($request->has('telegram_enabled')) {
            $settings['telegram_enabled'] = $request->boolean('telegram_enabled', true);
        }
        if ($request->has('telegram_bot_token')) {
            $settings['telegram_bot_token'] = trim($validated['telegram_bot_token'] ?? '');
        }
        if ($request->has('telegram_mode')) {
            $settings['telegram_mode'] = $validated['telegram_mode'] ?? 'chat';
        }
        if ($request->has('telegram_chat_id')) {
            $settings['telegram_chat_id'] = trim($validated['telegram_chat_id'] ?? '');
        }
        if ($request->has('telegram_topic_id')) {
            $settings['telegram_topic_id'] = trim($validated['telegram_topic_id'] ?? '');
        }
        if ($request->has('shop_slides')) {
            $settings['shop_slides'] = $request->input('shop_slides');
        }

        $tenant->settings = $settings;
        $tenant->save();

        return redirect()->back()->with('msg', 'Pengaturan landing page & notifikasi Telegram berhasil disimpan!');
    }

    /**
     * Test Telegram notification for tenant.
     */
    public function testTelegram(Request $request, TenantTelegramService $telegramService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        
        $request->validate([
            'telegram_bot_token' => 'required|string|max:200',
            'telegram_chat_id' => 'required|string|max:100',
            'telegram_mode' => 'required|in:chat,topic',
            'telegram_topic_id' => 'nullable|string|max:100',
        ], [
            'telegram_bot_token.required' => 'Token Bot Telegram wajib diisi dari @BotFather.',
            'telegram_chat_id.required' => 'Chat ID / Group ID Telegram wajib diisi.',
        ]);

        $chatId = trim((string) $request->input('telegram_chat_id'));
        $mode = $request->input('telegram_mode', 'chat');
        $topicId = $request->input('telegram_topic_id') ? trim((string) $request->input('telegram_topic_id')) : null;
        $token = trim((string) $request->input('telegram_bot_token'));

        $result = $telegramService->testConnection($tenantId, $token, $chatId, $mode, $topicId);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
