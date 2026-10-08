<?php

use App\Http\Controllers\Api\DeployWebhookController;
use App\Http\Controllers\Api\NoderaPayGatewayController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClientLogController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\QRISController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// ==================== WEBHOOKS & PAYMENT CALLBACKS ====================
Route::match(['GET', 'POST'], 'webhook/deploy', [DeployWebhookController::class, 'handle']);
Route::match(['GET', 'POST'], 'api/payment/callback/{gateway}', [WebhookController::class, 'universalPaymentCallback'])->middleware('throttle:120,1');
Route::match(['GET', 'POST'], 'api/callback/{gateway}', [WebhookController::class, 'universalPaymentCallback'])->middleware('throttle:120,1');
Route::match(['GET', 'POST'], 'webhook/cinetpay', [WebhookController::class, 'cinetpay'])->middleware('throttle:60,1');
Route::post('webhook/wave', [WebhookController::class, 'wave'])->middleware('throttle:60,1');
Route::post('webhook/paytech', [WebhookController::class, 'paytech'])->middleware('throttle:60,1');
Route::post('webhook/fedapay', [WebhookController::class, 'fedapay'])->middleware('throttle:60,1');
Route::match(['GET', 'POST'], 'webhook/telegram', [WebhookController::class, 'telegram'])->middleware('throttle:60,1');
Route::post('webhook/whatsapp', [WebhookController::class, 'whatsapp'])->middleware('throttle:60,1');
Route::match(['GET', 'POST'], 'webhook/wijayapay', [WebhookController::class, 'wijayapay'])->middleware('throttle:60,1');
Route::match(['GET', 'POST'], 'webhook/noderapay', [WebhookController::class, 'noderapay'])->middleware('throttle:60,1');
Route::match(['GET', 'POST'], 'api/v1/noderapay/webhook/{deviceKey?}', [WebhookController::class, 'noderapay'])->middleware('throttle:60,1');
Route::post('webhook/payment', [WebhookController::class, 'payment'])->middleware('throttle:60,1');
Route::post('webhook/midtrans', [WebhookController::class, 'midtrans'])->middleware('throttle:60,1');
Route::post('webhook/xendit', [WebhookController::class, 'xendit'])->middleware('throttle:60,1');
Route::post('webhook/duitku', [WebhookController::class, 'duitku'])->middleware('throttle:60,1');
Route::post('webhook/nominal-unik', [WebhookController::class, 'nominalUnique'])->middleware('throttle:60,1');
Route::match(['GET', 'POST'], 'webhook/qris', [WebhookController::class, 'qrisWebhook'])->middleware('throttle:120,1');
Route::match(['GET', 'POST'], 'webhook/gobiz', [WebhookController::class, 'qrisWebhook'])->middleware('throttle:120,1');
Route::match(['GET', 'POST'], 'api/v1/payments/qris/callback', [WebhookController::class, 'qrisWebhook'])->middleware('throttle:120,1');
Route::match(['GET', 'POST'], 'webhook/{gateway}', [WebhookController::class, 'universalPaymentCallback'])->where('gateway', '^(?!deploy|telegram|whatsapp|qris|gobiz).*$')->middleware('throttle:120,1');

// Payment status & checkout
Route::get('api/v1/payments/qris/status/{invoice_id}', [QRISController::class, 'status'])->middleware('throttle:60,1');
Route::post('api/v1/payments/qris/regenerate/{invoice_id}', [QRISController::class, 'regenerate'])->middleware('throttle:30,1');
Route::get('portal/payment/{invoice_id}/status', [QRISController::class, 'status'])->middleware('throttle:60,1');
Route::post('portal/payment/{invoice_id}/regenerate', [QRISController::class, 'regenerate'])->middleware('throttle:30,1');
Route::get('noderapay/pay/{order_id}', [NoderaPayGatewayController::class, 'showCheckout'])->name('noderapay.checkout')->middleware('throttle:30,1');
Route::get('receipt/{id}', [BillingController::class, 'printInvoice'])->name('public.receipt')->middleware('throttle:30,1');
Route::get('invoice/{id}', [BillingController::class, 'printInvoice'])->name('public.invoice')->middleware('throttle:30,1');

// ==================== CRON HTTP TRIGGER ====================
Route::get('cron/run/{job}', [CronController::class, 'run'])->middleware('throttle:12,1');
Route::get('cron/status', [CronController::class, 'status'])->middleware('throttle:12,1');

// ==================== CLIENT-ERROR LOGGING ====================
Route::match(['GET', 'POST'], 'client-error', [ClientLogController::class, 'store'])->middleware('throttle:120,1');
Route::match(['GET', 'POST'], 'client-logs', [ClientLogController::class, 'store'])->middleware('throttle:120,1');
