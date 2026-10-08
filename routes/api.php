<?php

use App\Http\Controllers\Api\V1\AdminApiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CollectorApiController;
use App\Http\Controllers\Api\V1\CustomerApiController;
use App\Http\Controllers\Api\V1\TechnicianApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NODERA REST API V1 Routes (For Flutter Mobile Native Apps)
|--------------------------------------------------------------------------
|
| - App 1: NODERA Client (Customer Portal Mobile)
| - App 2: NODERA Field & Ops (Admin, Kolektor, & Teknisi Mobile)
|
*/

Route::prefix('v1')->group(function () {
    // ── PUBLIC AUTHENTICATION ──
    Route::post('/auth/login-staff', [AuthController::class, 'loginStaff']);
    Route::post('/auth/login-customer', [AuthController::class, 'loginCustomer']);

    // ── PROTECTED ROUTES (SANCTUM TOKEN AUTH) ──
    Route::middleware('auth:sanctum')->group(function () {
        // Universal Auth endpoints
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/update-fcm-token', [AuthController::class, 'updateFcmToken']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ════════════════════════════════════════════════════════════
        // 📱 APP 1: NODERA CLIENT (CUSTOMER MOBILE)
        // ════════════════════════════════════════════════════════════
        Route::prefix('customer')->group(function () {
            Route::get('/dashboard', [CustomerApiController::class, 'dashboard']);
            Route::get('/invoices', [CustomerApiController::class, 'invoices']);
            Route::post('/pay-invoice', [CustomerApiController::class, 'payInvoice']);
            Route::get('/wifi', [CustomerApiController::class, 'getWifi']);
            Route::post('/wifi/change-password', [CustomerApiController::class, 'changeWifiPassword']);
            Route::post('/wifi/reboot', [CustomerApiController::class, 'rebootModem']);
            Route::post('/profile/pin', [CustomerApiController::class, 'updatePin']);
            Route::get('/realtime-traffic', [CustomerApiController::class, 'realtimeTraffic']);
            Route::get('/speedtest/ping', [CustomerApiController::class, 'speedtestPing']);
            Route::get('/speedtest/download', [CustomerApiController::class, 'speedtestDownload']);
            Route::post('/speedtest/upload', [CustomerApiController::class, 'speedtestUpload']);
            Route::get('/tickets', [CustomerApiController::class, 'tickets']);
            Route::post('/tickets', [CustomerApiController::class, 'createTicket']);
        });

        // ════════════════════════════════════════════════════════════
        // 🛵 APP 2: NODERA FIELD & OPS - KOLEKTOR (POS & STRUK BLUETOOTH)
        // ════════════════════════════════════════════════════════════
        Route::prefix('collector')->group(function () {
            Route::get('/dashboard', [CollectorApiController::class, 'dashboard']);
            Route::get('/unpaid-customers', [CollectorApiController::class, 'unpaidCustomers']);
            Route::post('/pay-cash', [CollectorApiController::class, 'payCash']);
            Route::get('/receipt/{id}', [CollectorApiController::class, 'receipt']);
        });

        // ════════════════════════════════════════════════════════════
        // 🔧 APP 2: NODERA FIELD & OPS - TEKNISI (ODP, OLT, TIKET)
        // ════════════════════════════════════════════════════════════
        Route::prefix('technician')->group(function () {
            Route::get('/dashboard', [TechnicianApiController::class, 'dashboard']);
            Route::get('/odp-locations', [TechnicianApiController::class, 'odpLocations']);
            Route::post('/odp-locations', [TechnicianApiController::class, 'createOdp']);
            Route::get('/onu-status', [TechnicianApiController::class, 'onuStatus']);
            Route::get('/tickets', [TechnicianApiController::class, 'tickets']);
            Route::post('/tickets/{id}/update', [TechnicianApiController::class, 'updateTicket']);
        });

        // ════════════════════════════════════════════════════════════
        // 👑 APP 2: NODERA FIELD & OPS - ADMIN (MONITORING & ISOLIR)
        // ════════════════════════════════════════════════════════════
        Route::prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminApiController::class, 'dashboard']);
            Route::get('/routers', [AdminApiController::class, 'routers']);
            Route::get('/routers/{id}/traffic', [AdminApiController::class, 'routerTraffic']);
            Route::post('/customers/{id}/toggle-status', [AdminApiController::class, 'toggleCustomerStatus']);
        });
    });

    // ════════════════════════════════════════════════════════════
    // 💳 QRIS DYNAMIC & GOBIZ WEBHOOKS & STATUS POLLING
    // ════════════════════════════════════════════════════════════
    Route::match(['GET', 'POST'], '/payments/qris/callback', [\App\Http\Controllers\WebhookController::class, 'qrisWebhook']);
    Route::get('/payments/qris/status/{invoice_id}', [\App\Http\Controllers\QRISController::class, 'status']);
    Route::post('/payments/qris/regenerate/{invoice_id}', [\App\Http\Controllers\QRISController::class, 'regenerate'])->middleware('throttle:30,1');

    // ════════════════════════════════════════════════════════════
    // 💎 NODERA PAY — OPEN GATEWAY & ANDROID NOTIFICATION INGESTION
    // ════════════════════════════════════════════════════════════
    Route::get('/noderapay/auto-config', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'autoConfig']);
    Route::post('/noderapay/sync-gateway-config', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'syncGatewayConfig']);
    Route::post('/noderapay/create-qris', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'createQris']);
    Route::get('/noderapay/check/{order_id}', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'checkStatus']);
    Route::get('/noderapay/qr-image/{txId}', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'qrImage']);
    Route::match(['GET', 'POST'], '/noderapay/ping', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'ping']);
    Route::post('/noderapay/report-notification', [\App\Http\Controllers\Api\NoderaPayGatewayController::class, 'reportNotification']);
    // ════════════════════════════════════════════════════════════
    // 🏪 MIKHMON ADDONS & WARUNG ENGINE
    // ════════════════════════════════════════════════════════════
    Route::prefix('mikhmon/addon')->group(function () {
        Route::match(['GET', 'POST'], '/status', [\App\Http\Controllers\Api\MikhmonAddonController::class, 'status']);
        Route::post('/activate', [\App\Http\Controllers\Api\MikhmonAddonController::class, 'activate']);
    });

    // ════════════════════════════════════════════════════════════
    // 💻 MIKHMON DESKTOP STANDALONE LICENSING API
    // ════════════════════════════════════════════════════════════
    Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/activate', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activate']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/trial', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activateTrial']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/verify', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/heartbeat', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
    
    Route::prefix('desktop/license')->group(function () {
        Route::match(['GET', 'POST', 'OPTIONS'], '/activate', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activate']);
        Route::match(['GET', 'POST', 'OPTIONS'], '/trial', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activateTrial']);
        Route::match(['GET', 'POST', 'OPTIONS'], '/verify', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
        Route::match(['GET', 'POST', 'OPTIONS'], '/heartbeat', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
    });
    Route::prefix('license')->group(function () {
        Route::match(['GET', 'POST', 'OPTIONS'], '/activate', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activate']);
        Route::match(['GET', 'POST', 'OPTIONS'], '/trial', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activateTrial']);
        Route::match(['GET', 'POST', 'OPTIONS'], '/verify', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
        Route::match(['GET', 'POST', 'OPTIONS'], '/heartbeat', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
    });
});

Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/activate', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activate']);
Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/trial', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activateTrial']);
Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/verify', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
Route::match(['GET', 'POST', 'OPTIONS'], '/desktop-license/heartbeat', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);

Route::prefix('desktop/license')->group(function () {
    Route::match(['GET', 'POST', 'OPTIONS'], '/activate', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activate']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/trial', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activateTrial']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/verify', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/heartbeat', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
});
Route::prefix('license')->group(function () {
    Route::match(['GET', 'POST', 'OPTIONS'], '/activate', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activate']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/trial', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'activateTrial']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/verify', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/heartbeat', [\App\Http\Controllers\Api\DesktopLicenseApiController::class, 'verify']);
});

// ════════════════════════════════════════════════════════════
// 📡 MIKROTIK PPPOE BANDWIDTH & DISCONNECT ACCOUNTING SYNC
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/v1/mikrotik/session-disconnect', [\App\Http\Controllers\Api\MikrotikBandwidthApiController::class, 'recordDisconnect']);
Route::match(['GET', 'POST'], '/mikrotik/session-disconnect', [\App\Http\Controllers\Api\MikrotikBandwidthApiController::class, 'recordDisconnect']);

// ════════════════════════════════════════════════════════════
// 🤖 TELEGRAM, GOBIZ & AUTO-DEPLOY WEBHOOKS
// ════════════════════════════════════════════════════════════


Route::match(['GET', 'POST'], '/webhook/qris', [\App\Http\Controllers\WebhookController::class, 'qrisWebhook']);
Route::match(['GET', 'POST'], '/webhook/gobiz', [\App\Http\Controllers\WebhookController::class, 'qrisWebhook']);
Route::match(['GET', 'POST'], '/payments/qris/callback', [\App\Http\Controllers\WebhookController::class, 'qrisWebhook']);
Route::get('/payments/qris/status/{invoice_id}', [\App\Http\Controllers\QRISController::class, 'status']);
Route::match(['GET', 'POST'], '/webhook/telegram', [\App\Http\Controllers\WebhookController::class, 'telegram']);
Route::match(['GET', 'POST'], '/api/webhook/telegram', [\App\Http\Controllers\WebhookController::class, 'telegram']);

// ════════════════════════════════════════════════════════════
// 💳 UNIVERSAL PAYMENT GATEWAY WEBHOOKS & NOTIFICATIONS
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/payment/notification/midtrans', [\App\Http\Controllers\WebhookController::class, 'midtrans']);
Route::match(['GET', 'POST'], '/payment/callback/{gateway}', [\App\Http\Controllers\WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/callback/{gateway}', [\App\Http\Controllers\WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/webhook/payment', [\App\Http\Controllers\WebhookController::class, 'payment']);
Route::match(['GET', 'POST'], '/webhook/midtrans', [\App\Http\Controllers\WebhookController::class, 'midtrans']);
Route::match(['GET', 'POST'], '/webhook/tripay', [\App\Http\Controllers\WebhookController::class, 'tripay']);
Route::match(['GET', 'POST'], '/webhook/duitku', [\App\Http\Controllers\WebhookController::class, 'duitku']);
Route::match(['GET', 'POST'], '/webhook/xendit', [\App\Http\Controllers\WebhookController::class, 'xendit']);
Route::match(['GET', 'POST'], '/webhook/doku', [\App\Http\Controllers\WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/webhook/{gateway}', [\App\Http\Controllers\WebhookController::class, 'universalPaymentCallback'])
    ->where('gateway', '^(?!deploy|telegram|whatsapp|qris|gobiz|client-logs|client-error).*$');

Route::match(['GET', 'POST'], '/v1/payment/notification/midtrans', [\App\Http\Controllers\WebhookController::class, 'midtrans']);
Route::match(['GET', 'POST'], '/v1/payment/callback/{gateway}', [\App\Http\Controllers\WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/v1/payments/qris/callback', [\App\Http\Controllers\WebhookController::class, 'qrisWebhook']);
Route::get('/v1/payments/qris/status/{invoice_id}', [\App\Http\Controllers\QRISController::class, 'status']);
Route::post('/v1/payments/qris/regenerate/{invoice_id}', [\App\Http\Controllers\QRISController::class, 'regenerate'])->middleware('throttle:30,1');

// ════════════════════════════════════════════════════════════
// 🚨 UNIVERSAL CLIENT LOGS & FRONTEND ERROR REPORTING
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/client-logs', [\App\Http\Controllers\ClientLogController::class, 'store']);
Route::match(['GET', 'POST'], '/client-error', [\App\Http\Controllers\ClientLogController::class, 'store']);

// ════════════════════════════════════════════════════════════
// 🌐 NODERA PAY — STANDALONE REST API (WijayaPay Compatible)
// ════════════════════════════════════════════════════════════


Route::prefix('gateway')->group(function () {
    Route::match(['GET', 'POST'], '/ping', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'ping']);
    Route::post('/create-transaction', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'createTransaction']);
    Route::post('/create-qris', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'createTransaction']);
    Route::get('/get-status', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'getStatus']);
    Route::get('/check-status', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'getStatus']);
    Route::get('/get-payment', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'getStatus']);
    Route::get('/check-payment', [\App\Http\Controllers\NoderaPay\NoderaPayApiController::class, 'getStatus']);
    Route::match(['GET', 'POST'], '/webhook/wijayapay', [\App\Http\Controllers\NoderaPay\NoderaPayWebhookController::class, 'handleWijayapay']);
    Route::match(['GET', 'POST'], '/webhook/midtrans', [\App\Http\Controllers\NoderaPay\NoderaPayWebhookController::class, 'handleMidtrans']);
    Route::match(['GET', 'POST'], '/webhook', [\App\Http\Controllers\NoderaPay\NoderaPayWebhookController::class, 'handleGeneric']);
});

// ════════════════════════════════════════════════════════════
// 💬 UNIVERSAL WHATSAPP GATEWAY REST API (Fonnte & MHWA Compatible)
// ════════════════════════════════════════════════════════════


Route::match(['GET', 'POST'], '/v1/whatsapp/send', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'send']);
Route::match(['GET', 'POST'], '/webhook', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'handleWebhook']);

Route::prefix('v1/wa')->group(function () {
    
    
    Route::match(['GET', 'POST'], '/send-media', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'send']);
            Route::get('/device/status', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'deviceStatus']);
    Route::get('/devices', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'deviceStatus']);
    Route::get('/quota', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'quota']);
    Route::get('/profile', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'quota']);
    Route::match(['GET', 'POST'], '/webhook', [\App\Http\Controllers\WaGateway\WaGatewayApiController::class, 'handleWebhook']);
    });

// ── UJIAN PKL TELEGRAM BOT WEBHOOK (ISOLATED BOT) ──
Route::match(['GET', 'POST'], '/telegram/pkl-exam-webhook', [\App\Http\Controllers\PklExamWebhookController::class, 'handle']);


