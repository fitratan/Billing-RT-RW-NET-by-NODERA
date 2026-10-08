<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\DesktopLicenseApiController;
use App\Http\Controllers\Api\MikrotikBandwidthApiController;
use App\Http\Controllers\Api\MikhmonAddonController;
use App\Http\Controllers\Api\NoderaPayGatewayController;
use App\Http\Controllers\ClientLogController;
use App\Http\Controllers\QRISController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ════════════════════════════════════════════════════════════
// 📱 MOBILE & PORTAL REST APIS
// ════════════════════════════════════════════════════════════
Route::prefix('v1')->group(function () {
    Route::post('/login', [CustomerApiController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [CustomerApiController::class, 'profile']);
        Route::get('/invoices', [CustomerApiController::class, 'invoices']);
        Route::get('/invoices/{id}', [CustomerApiController::class, 'invoiceDetail']);
        Route::get('/speedtest/config', [CustomerApiController::class, 'speedtestConfig']);
        Route::get('/trouble-tickets', [CustomerApiController::class, 'tickets']);
        Route::post('/trouble-tickets', [CustomerApiController::class, 'createTicket']);
        Route::get('/traffic-history', [CustomerApiController::class, 'trafficHistory']);
        Route::get('/live-traffic', [CustomerApiController::class, 'liveTraffic']);
        Route::get('/speedtest-nodes', [CustomerApiController::class, 'speedtestNodes']);
        Route::post('/fcm-token', [CustomerApiController::class, 'updateFcmToken']);

        Route::prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminApiController::class, 'dashboard']);
            Route::get('/routers', [AdminApiController::class, 'routers']);
            Route::get('/routers/{id}/traffic', [AdminApiController::class, 'routerTraffic']);
            Route::post('/customers/{id}/toggle-status', [AdminApiController::class, 'toggleCustomerStatus']);
        });
    });

    // QRIS API
    Route::match(['GET', 'POST'], '/payments/qris/callback', [WebhookController::class, 'qrisWebhook']);
    Route::get('/payments/qris/status/{invoice_id}', [QRISController::class, 'status']);
    Route::post('/payments/qris/regenerate/{invoice_id}', [QRISController::class, 'regenerate'])->middleware('throttle:30,1');

    // Webhook Aliases v1
    Route::match(['GET', 'POST'], '/payment/notification/midtrans', [WebhookController::class, 'midtrans']);
    Route::match(['GET', 'POST'], '/payment/callback/{gateway}', [WebhookController::class, 'universalPaymentCallback']);
    Route::match(['GET', 'POST'], '/mikrotik/session-disconnect', [MikrotikBandwidthApiController::class, 'recordDisconnect']);
});

// ════════════════════════════════════════════════════════════
// 💳 QRIS DYNAMIC & GOBIZ WEBHOOKS & STATUS POLLING
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/payments/qris/callback', [WebhookController::class, 'qrisWebhook']);
Route::get('/payments/qris/status/{invoice_id}', [QRISController::class, 'status']);
Route::post('/payments/qris/regenerate/{invoice_id}', [QRISController::class, 'regenerate'])->middleware('throttle:30,1');
Route::match(['GET', 'POST'], '/webhook/qris', [WebhookController::class, 'qrisWebhook']);
Route::match(['GET', 'POST'], '/webhook/gobiz', [WebhookController::class, 'qrisWebhook']);

// ════════════════════════════════════════════════════════════
// 💎 NODERA PAY — OPEN GATEWAY & NOTIFICATION INGESTION
// ════════════════════════════════════════════════════════════
Route::get('/noderapay/auto-config', [NoderaPayGatewayController::class, 'autoConfig']);
Route::post('/noderapay/sync-gateway-config', [NoderaPayGatewayController::class, 'syncGatewayConfig']);
Route::post('/noderapay/create-qris', [NoderaPayGatewayController::class, 'createQris']);
Route::get('/noderapay/check/{order_id}', [NoderaPayGatewayController::class, 'checkStatus']);
Route::get('/noderapay/qr-image/{txId}', [NoderaPayGatewayController::class, 'qrImage']);
Route::match(['GET', 'POST'], '/noderapay/ping', [NoderaPayGatewayController::class, 'ping']);
Route::post('/noderapay/report-notification', [NoderaPayGatewayController::class, 'reportNotification']);

// ════════════════════════════════════════════════════════════
// 🏪 MIKHMON ADDONS & WARUNG ENGINE
// ════════════════════════════════════════════════════════════
Route::prefix('mikhmon/addon')->group(function () {
    Route::match(['GET', 'POST'], '/status', [MikhmonAddonController::class, 'status']);
    Route::post('/activate', [MikhmonAddonController::class, 'activate']);
});

// ════════════════════════════════════════════════════════════
// 💻 MIKHMON DESKTOP STANDALONE LICENSING API
// ════════════════════════════════════════════════════════════
Route::prefix('desktop-license')->group(function () {
    Route::match(['GET', 'POST', 'OPTIONS'], '/activate', [DesktopLicenseApiController::class, 'activate']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/trial', [DesktopLicenseApiController::class, 'activateTrial']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/verify', [DesktopLicenseApiController::class, 'verify']);
    Route::match(['GET', 'POST', 'OPTIONS'], '/heartbeat', [DesktopLicenseApiController::class, 'verify']);
});

// ════════════════════════════════════════════════════════════
// 📡 MIKROTIK PPPOE BANDWIDTH & DISCONNECT ACCOUNTING SYNC
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/mikrotik/session-disconnect', [MikrotikBandwidthApiController::class, 'recordDisconnect']);

// ════════════════════════════════════════════════════════════
// 🤖 TELEGRAM & UNIVERSAL PAYMENT WEBHOOKS
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/webhook/telegram', [WebhookController::class, 'telegram']);
Route::match(['GET', 'POST'], '/api/webhook/telegram', [WebhookController::class, 'telegram']);
Route::match(['GET', 'POST'], '/payment/notification/midtrans', [WebhookController::class, 'midtrans']);
Route::match(['GET', 'POST'], '/payment/callback/{gateway}', [WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/callback/{gateway}', [WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/webhook/payment', [WebhookController::class, 'payment']);
Route::match(['GET', 'POST'], '/webhook/midtrans', [WebhookController::class, 'midtrans']);
Route::match(['GET', 'POST'], '/webhook/tripay', [WebhookController::class, 'tripay']);
Route::match(['GET', 'POST'], '/webhook/duitku', [WebhookController::class, 'duitku']);
Route::match(['GET', 'POST'], '/webhook/xendit', [WebhookController::class, 'xendit']);
Route::match(['GET', 'POST'], '/webhook/doku', [WebhookController::class, 'universalPaymentCallback']);
Route::match(['GET', 'POST'], '/webhook/{gateway}', [WebhookController::class, 'universalPaymentCallback'])
    ->where('gateway', '^(?!deploy|telegram|whatsapp|qris|gobiz|client-logs|client-error).*$');

// ════════════════════════════════════════════════════════════
// 🚨 UNIVERSAL CLIENT LOGS & FRONTEND ERROR REPORTING
// ════════════════════════════════════════════════════════════
Route::match(['GET', 'POST'], '/client-logs', [ClientLogController::class, 'store']);
Route::match(['GET', 'POST'], '/client-error', [ClientLogController::class, 'store']);
