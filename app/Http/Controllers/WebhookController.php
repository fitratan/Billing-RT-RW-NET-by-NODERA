<?php

namespace App\Http\Controllers;

use App\Services\MikrotikService;
use App\Services\PaymentService;
use App\Services\TelegramService;
use App\Services\TripayService;
use App\Services\WhatsappService;
use App\Models\Setting;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\VpnUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected ?MikrotikService $mikrotik = null;

    /**
     * Tripay Payment Webhook
     */
    
    /**
     * WijayaPay Payment Webhook
     */
    public function wijayapay(Request $request)
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Signature') ?? ($request->header('x-signature') ?? '');

        Log::info('WijayaPay Webhook Received: ' . $rawPayload . ' | Sig: ' . $signature);

        $data = json_decode($rawPayload, true);
        if (!$data) {
            $this->logToDb('wijayapay', $rawPayload, 400, 'Invalid JSON');
            return response()->json(['status' => false, 'message' => 'Invalid JSON payload'], 400);
        }

        $innerData = $data['data'] ?? $data;
        $refId = $innerData['ref_id'] ?? ($data['ref_id'] ?? ($innerData['order_id'] ?? ($data['order_id'] ?? '')));
        
        // Extract status with robust fallback across all gateway conventions (handling boolean true/status_pembayaran)
        $rawStatus = $innerData['status_pembayaran']
            ?? ($data['status_pembayaran']
            ?? ($innerData['status']
            ?? ($data['status']
            ?? ($innerData['payment_status']
            ?? ($data['payment_status'] ?? '')))));

        if (is_bool($rawStatus) || $rawStatus === '1' || $rawStatus === 'true' || strtolower((string) $rawStatus) === 'success') {
            $nested = $innerData['status_pembayaran'] ?? ($data['status_pembayaran'] ?? null);
            if (!empty($nested) && !is_bool($nested)) {
                $rawStatus = $nested;
            }
        }

        $status = strtolower((string) $rawStatus);
        $isPaid = in_array($status, ['paid', 'success', 'berhasil', 'settlement', 'settled', 'completed', '200'])
            || strtolower((string) ($innerData['status_pembayaran'] ?? ($data['status_pembayaran'] ?? ''))) === 'paid';

        // Resolve tenant from ref_id (e.g. INV-{invoiceId}-{txId} or ORD-...)
        $tenantId = null;
        if (preg_match('/^INV-(\d+)-/', $refId, $matches)) {
            $invoiceId = (int) $matches[1];
            $tenantId = DB::table('invoices')->where('id', $invoiceId)->value('tenant_id');
        }

        $wijayapay = new \App\Services\WijayaPayService($tenantId ? (string) $tenantId : null);
        
        // Validate signature if configured - FAIL CLOSED if missing or invalid
        if ($wijayapay->isConfigured()) {
            if (empty($signature) || !$wijayapay->validateCallback($refId, $signature)) {
                $this->logToDb('wijayapay', $rawPayload, 401, 'Invalid or missing signature');
                return response()->json(['status' => false, 'message' => 'Invalid signature'], 401);
            }
        }

        // 1. Unified flow: update PaymentTransaction + invoice via PaymentService
        $paymentService = app(\App\Services\PaymentService::class);
        $result = $paymentService->handleCallback('wijayapay', $data);

        // 2. Legacy/Direct invoice resolution
        $invoice = $result['transaction']->invoice ?? null;
        if (!$invoice && !empty($refId)) {
            $invoice = $this->resolveInvoiceByRef($refId);
        }

        if ($invoice && $isPaid) {
            $this->handlePaidInvoice($invoice, $data);
        } elseif ($invoice && ($status === 'expired' || $status === 'failed')) {
            $this->handleFailedInvoice($invoice->invoice_number, $status);
        }

        // 3. NoderaPay Merchant & Hotspot Voucher Transaction resolution
        try {
            /** @var \App\Services\NoderaPayEngineService $npEngine */
            $npEngine = app(\App\Services\NoderaPayEngineService::class);
            $npEngine->handleWijayapayWebhook($data, $signature);
        } catch (\Throwable $e) {
            Log::warning("[WijayaPay] NoderaPayEngine handling error: " . $e->getMessage());
        }

        $npTx = \App\Models\NoderaPayTransaction::where('order_id', $refId)->first();
        if ($npTx) {
            if ($isPaid) {
                $npTx->update([
                    'status'           => 'paid',
                    'paid_at'          => now(),
                    'raw_notification' => $rawPayload,
                ]);
            } elseif ($status === 'expired' || $status === 'failed') {
                $npTx->update([
                    'status'           => $status,
                    'raw_notification' => $rawPayload,
                ]);
            }
        }

        // 4. Registration Request auto-approval (Tenant Online Register)
        if (str_starts_with($refId, 'REG-')) {
            $slugPart = preg_replace('/^REG-/', '', $refId);
            $slugClean = preg_replace('/-\d+$/', '', $slugPart);
            $regReq = \App\Models\RegistrationRequest::where('status', 'pending')
                ->where(function ($q) use ($slugPart, $slugClean) {
                    $q->where('slug', $slugClean)
                      ->orWhere('slug', $slugPart)
                      ->orWhere('id', $slugClean);
                })->first();

            if ($regReq && $isPaid) {
                try {
                    $regReq->update([
                        'paid_at' => now(),
                        'status'  => 'approved',
                    ]);
                    (new \App\Actions\Tenant\ApproveRegistration)->execute($regReq);
                    Log::info("[WijayaPay] Registration request approved for slug: {$regReq->slug}");
                } catch (\Throwable $e) {
                    Log::error('[WijayaPay] Auto-approve registration error: ' . $e->getMessage());
                }
            } elseif ($regReq && in_array($status, ['expired', 'failed', 'cancelled', 'dibatalkan', 'kedaluwarsa'])) {
                try {
                    $regReq->update(['status' => 'expired']);
                    \App\Models\RegistrationRequest::retractAndNotifyCancellation($regReq, 'Waktu pembayaran QRIS telah kedaluwarsa (Expired dari Gateway WijayaPay).');
                    Log::info("[WijayaPay] Registration request expired/cancelled for slug: {$regReq->slug}");
                } catch (\Throwable $e) {
                    Log::error('[WijayaPay] Cancel registration error: ' . $e->getMessage());
                }
            }
        }

        // 5. Member / VPN Topup Request auto-settle
        if (str_starts_with($refId, 'TOP/') || \App\Models\VpnTopupRequest::where('invoice_number', $refId)->exists()) {
            $topup = \App\Models\VpnTopupRequest::where('invoice_number', $refId)->first();
            if ($topup && $topup->status === 'pending' && $isPaid) {
                try {
                    \App\Models\VpnTopupRequest::settleTopup(
                        $topup,
                        (float) ($topup->total_amount ?: $topup->amount),
                        'Payment Gateway Otomatis (WijayaPay Webhook)'
                    );
                    Log::info("[WijayaPay] Topup settled successfully for invoice: {$topup->invoice_number}");
                } catch (\Throwable $e) {
                    Log::error('[WijayaPay] Auto-settle topup error: ' . $e->getMessage());
                }
            } elseif ($topup && $topup->status === 'pending' && in_array($status, ['expired', 'failed', 'cancelled', 'dibatalkan', 'kedaluwarsa'])) {
                try {
                    $topup->update(['status' => 'cancelled']);
                    \App\Models\VpnTopupRequest::retractAndNotifyCancellation($topup, 'Waktu pembayaran QRIS telah kedaluwarsa (Expired dari Gateway WijayaPay).');
                    Log::info("[WijayaPay] Topup expired/cancelled for invoice: {$topup->invoice_number}");
                } catch (\Throwable $e) {
                    Log::error('[WijayaPay] Cancel topup error: ' . $e->getMessage());
                }
            }
        }

        // 6. WA Gateway Topup / Subscription Request auto-settle
        if (str_starts_with($refId, 'INV-WA-') || \App\Models\WaTopup::where('invoice_number', $refId)->exists()) {
            $waTopup = \App\Models\WaTopup::where('invoice_number', $refId)->first();
            if ($waTopup && $waTopup->payment_status === 'pending' && $isPaid) {
                try {
                    \App\Models\WaTopup::settleTopup(
                        $waTopup,
                        'Payment Gateway Otomatis (WijayaPay Webhook)'
                    );
                    Log::info("[WijayaPay] WA Topup settled successfully for invoice: {$waTopup->invoice_number}");
                } catch (\Throwable $e) {
                    Log::error('[WijayaPay] Auto-settle WA topup error: ' . $e->getMessage());
                }
            } elseif ($waTopup && $waTopup->payment_status === 'pending' && in_array($status, ['expired', 'failed', 'cancelled', 'dibatalkan', 'kedaluwarsa'])) {
                try {
                    $waTopup->update(['payment_status' => 'expired']);
                    \App\Models\WaTopup::retractAndNotifyCancellation($waTopup, 'Waktu pembayaran QRIS telah kedaluwarsa (Expired dari Gateway WijayaPay).');
                    Log::info("[WijayaPay] WA Topup expired/cancelled for invoice: {$waTopup->invoice_number}");
                } catch (\Throwable $e) {
                    Log::error('[WijayaPay] Cancel WA topup error: ' . $e->getMessage());
                }
            }
        }

        $this->logToDb('wijayapay', $rawPayload, 200, "Processed: {$status}");
        
        // WijayaPay documentation standard response:
        return response()->json(['status' => true]);
    }

    public function noderapay(Request $request)
    {
        $payload = $request->all();
        $rawPayload = json_encode($payload);

        Log::info('[WebhookController] NODERA PAY Webhook Received: ' . $rawPayload);

        // Verify Signature
        $signature = $request->header('X-NoderaPay-Signature') ?? $request->header('X-Signature') ?? ($payload['signature'] ?? null);
        $secretKey = config('services.noderapay.secret_key') ?: env('NODERAPAY_SECRET_KEY');
        if (empty($secretKey)) {
            $secretKey = \App\Models\Setting::where('key', 'NODERAPAY_SECRET_KEY')->whereNull('tenant_id')->value('value');
        }

        $refId = $payload['ref_id'] ?? ($payload['order_id'] ?? ($payload['trx_reference'] ?? ''));
        $status = strtolower((string) ($payload['status'] ?? ($payload['status_pembayaran'] ?? '')));
        $isPaid = in_array($status, ['paid', 'success', 'berhasil', 'settlement', 'approved', '200']);

        // Enforce signature verification (fail-closed if configured or in production)
        if (!empty($secretKey)) {
            $expectedSignature = hash_hmac('sha256', (string) $refId . ':' . (string) $status, $secretKey);
            if (empty($signature) || (!hash_equals($expectedSignature, (string) $signature) && !hash_equals($secretKey, (string) $signature))) {
                $this->logToDb('noderapay', $rawPayload, 401, 'Invalid or missing signature');
                return response()->json(['status' => false, 'message' => 'Unauthorized signature'], 401);
            }
        } elseif (empty($signature) && !app()->environment('local', 'testing')) {
            $this->logToDb('noderapay', $rawPayload, 401, 'Missing signature');
            return response()->json(['status' => false, 'message' => 'Signature required'], 401);
        }

        // Jika request datang dari MacroDroid (push notification forwarding)
        if ($request->has('raw_text') || $request->has('title') || $request->has('body') || $request->has('text')) {
            $engine = app(\App\Services\NoderaPayEngineService::class);
            $res = $engine->handleMacdroidNotification($payload);
            $this->logToDb('noderapay_macdroid', $rawPayload, $res['success'] ? 200 : 422, $res['message'] ?? 'Processed');
            return response()->json($res);
        }

        $invoice = null;
        if (preg_match('/^INV-(\d+)-/', $refId, $matches)) {
            $invoiceId = (int) $matches[1];
            $invoice = Invoice::withoutGlobalScopes()->with(['customer.package', 'tenant'])->find($invoiceId);
        }

        if ($invoice) {
            // Prevent state downgrade if invoice is already PAID
            if (in_array(strtoupper($invoice->status), ['PAID', 'LUNAS', 'COMPLETED']) && !$isPaid) {
                $this->logToDb('noderapay', $rawPayload, 200, "Ignored state downgrade for already paid invoice #{$invoice->id}");
                return response()->json(['status' => true, 'message' => 'Invoice already settled']);
            }

            if ($isPaid) {
                $this->handlePaidInvoice($invoice, $payload);
                $this->logToDb('noderapay', $rawPayload, 200, "Processed: {$status}");
                return response()->json(['status' => true, 'message' => 'Invoice payment settled successfully']);
            } elseif ($status === 'expired' || $status === 'failed') {
                $this->handleFailedInvoice($invoice->invoice_number, $status);
                $this->logToDb('noderapay', $rawPayload, 200, "Processed: {$status}");
                return response()->json(['status' => true, 'message' => 'Invoice marked as failed/expired']);
            }
        }

        // Registration Request auto-approval
        if (str_starts_with($refId, 'REG-')) {
            $slugPart = preg_replace('/^REG-/', '', $refId);
            $slugClean = preg_replace('/-\d+$/', '', $slugPart);
            $regReq = \App\Models\RegistrationRequest::where('status', 'pending')
                ->where(function ($q) use ($slugPart, $slugClean) {
                    $q->where('slug', $slugClean)
                      ->orWhere('slug', $slugPart)
                      ->orWhere('id', $slugClean);
                })->first();

            if ($regReq && $isPaid) {
                try {
                    $regReq->update([
                        'paid_at' => now(),
                        'status'  => 'approved',
                    ]);
                    (new \App\Actions\Tenant\ApproveRegistration)->execute($regReq);
                    Log::info("[NODERA PAY Webhook] Registration request approved for slug: {$regReq->slug}");
                    $this->logToDb('noderapay', $rawPayload, 200, "Registration auto-approved: {$regReq->slug}");
                    return response()->json(['status' => true, 'message' => 'Registration auto-approved successfully']);
                } catch (\Throwable $e) {
                    Log::error('[NODERA PAY Webhook] Auto-approve registration error: ' . $e->getMessage());
                }
            } elseif ($regReq && in_array($status, ['expired', 'failed', 'cancelled', 'dibatalkan', 'kedaluwarsa'])) {
                try {
                    $regReq->update(['status' => 'expired']);
                    \App\Models\RegistrationRequest::retractAndNotifyCancellation($regReq, 'Waktu pembayaran QRIS telah kedaluwarsa (Expired dari NODERA PAY).');
                    Log::info("[NODERA PAY Webhook] Registration request expired/cancelled for slug: {$regReq->slug}");
                } catch (\Throwable $e) {
                    Log::error('[NODERA PAY Webhook] Cancel registration error: ' . $e->getMessage());
                }
            }
        }

        // Member / VPN Topup Request auto-settle
        if (str_starts_with($refId, 'TOP/') || \App\Models\VpnTopupRequest::where('invoice_number', $refId)->exists()) {
            $topup = \App\Models\VpnTopupRequest::where('invoice_number', $refId)->first();
            if ($topup && $topup->status === 'pending' && $isPaid) {
                try {
                    \App\Models\VpnTopupRequest::settleTopup(
                        $topup,
                        (float) ($topup->total_amount ?: $topup->amount),
                        'Payment Gateway Otomatis (NODERA PAY Webhook)'
                    );
                    Log::info("[NODERA PAY Webhook] Topup settled successfully for invoice: {$topup->invoice_number}");
                    $this->logToDb('noderapay', $rawPayload, 200, "Topup auto-settled: {$topup->invoice_number}");
                    return response()->json(['status' => true, 'message' => 'Topup auto-settled successfully']);
                } catch (\Throwable $e) {
                    Log::error('[NODERA PAY Webhook] Auto-settle topup error: ' . $e->getMessage());
                }
            } elseif ($topup && $topup->status === 'pending' && in_array($status, ['expired', 'failed', 'cancelled', 'dibatalkan', 'kedaluwarsa'])) {
                try {
                    $topup->update(['status' => 'cancelled']);
                    \App\Models\VpnTopupRequest::retractAndNotifyCancellation($topup, 'Waktu pembayaran QRIS telah kedaluwarsa (Expired dari NODERA PAY).');
                    Log::info("[NODERA PAY Webhook] Topup expired/cancelled for invoice: {$topup->invoice_number}");
                } catch (\Throwable $e) {
                    Log::error('[NODERA PAY Webhook] Cancel topup error: ' . $e->getMessage());
                }
            }
        }

        $this->logToDb('noderapay', $rawPayload, 200, "Processed: {$status}");
        return response()->json(['status' => true]);
    }

    public function payment(Request $request)
    {
        $json = $request->getContent();
        $callbackSignature = $request->header('X-Callback-Signature');

        Log::info('Tripay Webhook Received: ' . $json);

        $data = json_decode($json, true);
        if (!$data) {
            $this->logToDb('tripay', $json, 400, 'Invalid JSON');
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        // Resolve the tenant that owns this invoice so the signature is
        // validated against that tenant's Tripay private key.
        $merchantRef = $data['merchant_ref'] ?? '';
        $invoiceId = (int) preg_replace('/^INV-(\d+)-.*$/', '$1', $merchantRef);
        $tenantId = $invoiceId ? DB::table('invoices')->where('id', $invoiceId)->value('tenant_id') : null;

        $tripay = new TripayService($tenantId ? (string) $tenantId : null);
        if (!$tripay->validateCallback($json, $callbackSignature)) {
            $this->logToDb('tripay', $json, 401, 'Invalid signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $status = $data['status'];

        // 1. Unified flow: update PaymentTransaction + invoice via PaymentService (idempotent).
        $paymentService = app(PaymentService::class);
        $result = $paymentService->handleCallback('tripay', $data);

        // 2. Legacy business flow (WhatsApp receipt + un-isolate).
        $invoice = $result['transaction']->invoice ?? null;
        if (!$invoice) {
            $invoice = $this->resolveInvoiceByRef($merchantRef);
        }

        if ($invoice && $status === 'PAID') {
            $this->handlePaidInvoice($invoice, $data);
        } elseif ($invoice && ($status === 'EXPIRED' || $status === 'FAILED')) {
            $this->handleFailedInvoice($invoice->invoice_number, $status);
        }

        $this->logToDb('tripay', $json, 200, "Processed: {$status}");
        return response()->json(['success' => true]);
    }

    /**
     * Resolve an invoice from a merchant reference. Supports both the legacy
     * format (invoice_number) and the unified format (INV-{invoiceId}-{txId}).
     */
    private function resolveInvoiceByRef(string $merchantRef)
    {
        $invoice = DB::table('invoices')->where('invoice_number', $merchantRef)->first();

        if (!$invoice && preg_match('/^INV-(\d+)-(\d+)$/', $merchantRef, $m)) {
            $invoice = DB::table('invoices')->where('id', (int) $m[1])->first();
        }

        return $invoice;
    }

    private function handlePaidInvoice($invoice, $paymentData)
    {
        $invoiceNumber = $invoice->invoice_number;

        Log::info("Invoice {$invoiceNumber} marked as PAID.");

        $customerId = $invoice->customer_id;

        // Get Customer
        $customer = DB::table('customers')->where('id', $customerId)->first();

        // Dispatch WhatsApp Payment Receipt using template system
        if ($customer && !empty($customer->phone)) {
            try {
                $waService = new \App\Services\WhatsappService($invoice->tenant_id ?? $customer->tenant_id ?? null);
                if ($waService->isConfigured()) {
                    $waService->sendPaymentSuccess((array) $customer, (array) $invoice);
                }
            } catch (\Throwable $e) {
                Log::error('[Webhook Paid Invoice] WhatsApp dispatch failed: ' . $e->getMessage());
            }
        }

        if ($customer && $customer->status === 'isolated') {
            // Check any other unpaid overdue invoices
            $unpaidCount = DB::table('invoices')
                ->where('customer_id', $customerId)
                ->where('status', 'pending')
                ->where('due_date', '<', now()->format('Y-m-d'))
                ->count();

            if ($unpaidCount === 0) {
                // Restore Connection asynchronously via UnisolateCustomerJob
                try {
                    \App\Jobs\UnisolateCustomerJob::dispatch(
                        $customer->id,
                        'Payment Gateway Webhook (' . ($paymentData['payment_method'] ?? 'Online') . ')'
                    );
                } catch (\Throwable $e) {
                    Log::error('[Webhook Paid Invoice] UnisolateCustomerJob dispatch failed: ' . $e->getMessage());
                }
            }
        }
    }

    private function handleFailedInvoice($invoiceNumber, $status)
    {
        Log::info("Invoice {$invoiceNumber} status: {$status}");
    }

    private function unisolateCustomer($customer)
    {
        app(\App\Services\IsolationService::class)->unisolateCustomer($customer->id ?? $customer, 'Webhook Auto-Unisolir');
    }

    /**
     * Telegram Bot Webhook Handler
     * Handle incoming Telegram messages and callbacks
     */
    public function telegram(Request $request)
    {
        if ($request->isMethod('get')) {
            // Require authenticated admin to view or manage webhook setup
            if (!$request->user() && !session('admin_logged_in') && !session('superadmin_logged_in')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $candidateTokens = TelegramService::getAllCandidateBotTokens();
            $shouldSetup = $request->boolean('setup') || $request->boolean('set') || $request->has('register');
            $botReports = [];

            foreach ($candidateTokens as $token) {
                try {
                    $botService = new TelegramService($token);
                    $maskedToken = substr($token, 0, 6) . '...' . substr($token, -4);
                    $me = $botService->getMe();
                    $botName = ($me['ok'] ?? false) ? ('@' . ($me['result']['username'] ?? 'bot')) : 'Invalid Token';
                    $setResult = null;

                    if ($shouldSetup) {
                        $setResult = $botService->setWebhook();
                    }

                    $info = $botService->getWebhookInfo();
                    $botReports[] = [
                        'bot_token' => $maskedToken,
                        'bot_username' => $botName,
                        'webhook_url' => $info['result']['url'] ?? null,
                        'has_custom_certificate' => $info['result']['has_custom_certificate'] ?? false,
                        'pending_update_count' => $info['result']['pending_update_count'] ?? 0,
                        'last_error_date' => isset($info['result']['last_error_date']) ? date('Y-m-d H:i:s', $info['result']['last_error_date']) : null,
                        'last_error_message' => $info['result']['last_error_message'] ?? null,
                        'setup_performed' => $shouldSetup,
                        'setup_result' => $setResult,
                    ];
                } catch (\Throwable $e) {
                    $botReports[] = [
                        'bot_token' => substr($token, 0, 6) . '...',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'status' => 'operational',
                'service' => 'Telegram Bot Webhook Engine',
                'host' => request()->getHost(),
                'canonical_webhook_url' => TelegramService::buildWebhookUrl(),
                'bots_count' => count($candidateTokens),
                'bots' => $botReports,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $input = $request->getContent();
        $update = json_decode($input, true);

        if (!$update) {
            return response()->json(['ok' => false]);
        }

        Log::info('Telegram Webhook Payload:', [
            'update_id' => $update['update_id'] ?? null,
            'data' => $update,
        ]);

        // Verifikasi keaslian webhook Telegram (Fail-Closed jika secret dikonfigurasi)
        $expectedSecret = \App\Models\Setting::where('key', 'TELEGRAM_WEBHOOK_SECRET')->whereNull('tenant_id')->value('value');
        if (!empty($expectedSecret)) {
            $receivedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (empty($receivedSecret) || !hash_equals($expectedSecret, (string) $receivedSecret)) {
                Log::warning('Telegram webhook secret token mismatch or missing, rejecting unauthorized request', [
                    'received' => substr((string) $receivedSecret, 0, 8) . '...',
                ]);
                return response()->json(['ok' => false, 'message' => 'Unauthorized secret token'], 403);
            }
        }

        // Token passed in webhook URL for multi-bot identification
        $tokenParam = $request->query('token') ?: $request->input('bot_token');
        $telegram = new TelegramService($tokenParam);
        $mikrotik = new MikrotikService();
        $this->mikrotik = $mikrotik;

        // Handle bot added to group / status update
        if (isset($update['my_chat_member'])) {
            $myMember = $update['my_chat_member'];
            $chat = $myMember['chat'] ?? [];
            $newStatus = $myMember['new_chat_member']['status'] ?? '';
            $chatId = (string) ($chat['id'] ?? '');

            if (in_array($newStatus, ['member', 'administrator'])) {
                $groupTitle = $chat['title'] ?? 'Grup Telegram';
                $this->sendGroupInfoMessage($chatId, $groupTitle, null, $telegram);
                return response()->json(['ok' => true]);
            }
        }

        // Handle regular messages
        if (isset($update['message'])) {
            $message = $update['message'];
            $chat = $message['chat'] ?? [];
            $chatType = $chat['type'] ?? 'private';
            $chatId = (string) ($chat['id'] ?? '');
            $text = trim($message['text'] ?? '');
            $messageThreadId = $message['message_thread_id'] ?? null;

            $from = $message['from'] ?? [];
            $senderName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
            if (empty($senderName) && !empty($from['username'])) {
                $senderName = '@' . $from['username'];
            }
            $senderName = $senderName ?: ($from['id'] ?? null) . '';

            // Handle when bot is added via new_chat_members
            if (isset($message['new_chat_members']) || isset($message['group_chat_created']) || isset($message['supergroup_chat_created'])) {
                $groupTitle = $chat['title'] ?? 'Grup Telegram';
                $this->sendGroupInfoMessage($chatId, $groupTitle, $messageThreadId, $telegram);
                return response()->json(['ok' => true]);
            }

            // Handle Group & Supergroup commands (/getid, /id, /chatid, /start)
            if ($chatType === 'group' || $chatType === 'supergroup') {
                $cleanCmd = strtolower(explode(' ', explode('@', $text)[0])[0]);
                if (in_array($cleanCmd, ['/getid', '/id', '/chatid', '/start', '/info', 'getid', 'id', 'chatid', '/myid', '/groupid'])) {
                    $groupTitle = $chat['title'] ?? 'Grup Telegram';
                    $this->sendGroupInfoMessage($chatId, $groupTitle, $messageThreadId, $telegram);
                    return response()->json(['ok' => true]);
                }
                return response()->json(['ok' => true]);
            }

            // In Private Chat:
            $this->processTelegramCommand($chatId, $text, $telegram, $mikrotik, $senderName, $from);
        }

        // Handle callback queries (inline button clicks)
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $chatId = $callback['message']['chat']['id'] ?? ($callback['from']['id'] ?? null);
            $messageId = $callback['message']['message_id'] ?? null;
            $threadId = $callback['message']['message_thread_id'] ?? null;
            $data = $callback['data'] ?? '';
            $callbackId = $callback['id'] ?? '';
            $senderId = (string) ($callback['from']['id'] ?? '');

            $this->processTelegramCallback($chatId, $messageId, $data, $callbackId, $telegram, $mikrotik, $threadId, $senderId);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Send Group & Topic ID Information to a Telegram Group/Supergroup.
     */
    private function sendGroupInfoMessage(string $chatId, string $groupTitle, ?int $threadId, TelegramService $telegram): void
    {
        $msg = "<b>ℹ️ INFORMASI ID GRUP TELEGRAM — NODERA</b>\n\n";
        $msg .= "┌ " . htmlspecialchars($groupTitle) . "\n";
        $msg .= "├ Group ID: <code>{$chatId}</code>\n";
        if (!empty($threadId)) {
            $msg .= "├ Topic ID: <code>{$threadId}</code> (ID Topik Forum)\n";
        }
        $msg .= "└ Status: TERHUBUNG\n\n";
        $msg .= "<i>Salin <b>Group ID</b> di atas dan masukkan ke kolom <b>Chat ID / Group ID</b> di menu Bot Telegram NODERA.</i>";
        if (!empty($threadId)) {
            $msg .= "\n<i>Salin juga <b>Topic ID</b> ke kolom target topik di pengaturan bot.</i>";
        }

        $res = $telegram->sendMessage($chatId, $msg, 'HTML', null, $threadId);
        if (!($res['ok'] ?? false)) {
            // Fallback: Try sending with tenant bots if configured
            $tenants = \App\Models\Tenant::whereNotNull('settings')->get();
            foreach ($tenants as $t) {
                $token = $t->settings['telegram_bot_token'] ?? null;
                if (!empty($token)) {
                    $altTelegram = new TelegramService($token);
                    $altRes = $altTelegram->sendMessage($chatId, $msg, 'HTML', null, $threadId);
                    if ($altRes['ok'] ?? false) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Process Telegram text commands
     */
    private function processTelegramCommand($chatId, $text, $telegram, $mikrotik, $senderName = null, $from = [])
    {
        $text = trim($text);
        $command = strtoupper($text);

        // Check admin (simple check - can be enhanced)
        $isAdmin = $this->isTelegramAdmin($chatId);

        // === START & GETID COMMANDS (Private Chat) ===
        if (in_array($command, ['/START', '/ID', '/GETID', '/CHATID', '/MYID'])) {
            $username = !empty($from['username']) ? '@' . $from['username'] : '-';
            $senderLabel = !empty($from['first_name']) ? htmlspecialchars($from['first_name']) : 'Akun Telegram';
            $currentTime = date('Y-m-d | H:i:s');

            $msg = "<b>ℹ️ DATA ID AKUN TELEGRAM — NODERA</b>\n\n";
            $msg .= "┌ {$senderLabel}\n";
            $msg .= "├ Chat ID: <code>{$chatId}</code>\n";
            $msg .= "├ Username: {$username}\n";
            $msg .= "└ Waktu: {$currentTime}\n\n";
            $msg .= "<b>Petunjuk Penggunaan:</b>\n";
            $msg .= "1. Salin nomor <b>Chat ID</b> di atas (sentuh/klik nomornya).\n";
            $msg .= "2. Buka dashboard NODERA > menu <b>Bot & Notifikasi Telegram</b>.\n";
            $msg .= "3. Tempelkan pada kolom <b>Telegram Chat ID</b>.\n\n";
            $msg .= "<b>Untuk Notifikasi Grup:</b>\n";
            $msg .= "Tambahkan bot ke grup lalu kirim pesan apa saja di grup.";

            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === HELP COMMAND ===
        if ($command === '/HELP' || $command === 'HELP') {
            $this->sendTelegramHelp($chatId, $telegram, $isAdmin);
            return;
        }

        // === PING - Test MikroTik Connection ===
        if ($command === 'PING') {
            if ($mikrotik->isConnected()) {
                $msg = "<b>🟢 TES KONEKSI MIKROTIK — NODERA</b>\n\n"
                    . "┌ MikroTik Router\n"
                    . "├ Status: ONLINE\n"
                    . "├ Info: Koneksi berhasil terhubung\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>🔴 TES KONEKSI MIKROTIK — NODERA</b>\n\n"
                    . "┌ MikroTik Router\n"
                    . "├ Status: OFFLINE\n"
                    . "├ Info: Gagal koneksi (" . htmlspecialchars($mikrotik->getLastError()) . ")\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === STATUS - Check MikroTik Status ===
        if ($command === 'STATUS') {
            if (!$mikrotik->isConnected()) {
                $msg = "<b>🔴 STATUS MIKROTIK — NODERA</b>\n\n"
                    . "┌ MikroTik Router\n"
                    . "├ Status: OFFLINE\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            try {
                $activePppoe = count($mikrotik->getActivePppoe());
                $activeHotspot = count($mikrotik->getActiveHotspotUsers());

                $msg = "<b>🟢 STATUS MIKROTIK — NODERA</b>\n\n"
                    . "┌ MikroTik Router\n"
                    . "├ PPPoE Aktif: {$activePppoe} user\n"
                    . "├ Hotspot Aktif: {$activeHotspot} user\n"
                    . "├ Status: ONLINE\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } catch (\Exception $e) {
                $telegram->sendMessage($chatId, "Error: " . $e->getMessage());
            }
            return;
        }

        // === Admin Commands ===
        if (!$isAdmin) {
            $msg = "<b>🚫 ACCESS DENIED — NODERA</b>\n\n"
                . "┌ Akses Ditolak\n"
                . "├ Status: TIDAK MEMILIKI AKSES ADMIN\n"
                . "└ Info: Ketik /help untuk melihat perintah yang tersedia.";
            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === TAMBAH - Add PPPoE Secret ===
        if (preg_match('/^TAMBAH\s+(\S+)\s+(\S+)\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];
            $password = $matches[2];
            $profile = $matches[3];

            $result = $mikrotik->addPppoeSecret($username, $password, $profile);

            if ($result) {
                $msg = "<b>✅ TAMBAH USER PPPOE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Username: <code>{$username}</code>\n"
                    . "├ Password: <code>{$password}</code>\n"
                    . "├ Profile: <code>{$profile}</code>\n"
                    . "├ Status: BERHASIL DITAMBAHKAN\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❌ TAMBAH USER PPPOE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($mikrotik->getLastError()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === EDIT - Update PPPoE Profile ===
        if (preg_match('/^EDIT\s+(\S+)\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];
            $newProfile = $matches[2];

            $result = $mikrotik->updatePppoeSecret($username, ['profile' => $newProfile]);

            if ($result) {
                $msg = "<b>✅ EDIT USER PPPOE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Username: <code>{$username}</code>\n"
                    . "├ Profile Baru: <code>{$newProfile}</code>\n"
                    . "├ Status: BERHASIL DIUPDATE\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❌ EDIT USER PPPOE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($mikrotik->getLastError()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === HAPUS - Delete PPPoE Secret ===
        if (preg_match('/^HAPUS\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];

            $result = $mikrotik->deletePppoeSecret($username);

            if ($result) {
                $msg = "<b>✅ HAPUS USER PPPOE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Username: <code>{$username}</code>\n"
                    . "├ Status: BERHASIL DIHAPUS\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❌ HAPUS USER PPPOE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($mikrotik->getLastError()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === BILLING COMMANDS (Admin Only) ===

        // === CUSTOMER - Search Customer Info ===
        if (preg_match('/^CUSTOMER\s+(.+)$/i', $text, $matches)) {
            $search = trim($matches[1]);

            // Search by name or phone
            $customer = DB::table('customers')
                ->select('customers.*', 'packages.name as package_name', 'packages.price as package_price')
                ->join('packages', 'packages.id', '=', 'customers.package_id', 'left')
                ->where(function ($q) use ($search) {
                    $q->where('customers.name', 'like', "%{$search}%")
                      ->orWhere('customers.phone', 'like', "%{$search}%")
                      ->orWhere('customers.pppoe_username', $search);
                })
                ->first();

            if ($customer) {
                // Check unpaid invoices
                $unpaid = DB::table('invoices')
                    ->where('customer_id', $customer->id)
                    ->where('paid', 0)
                    ->count();

                $statusLabel = ($customer->status === 'active' ? 'AKTIF' : 'ISOLIR');

                $msg = "<b>👤 DATA CUSTOMER — NODERA</b>\n\n";
                $msg .= "┌ " . htmlspecialchars($customer->name) . "\n";
                $msg .= "├ Phone: <code>{$customer->phone}</code>\n";
                $msg .= "├ PPPoE: <code>{$customer->pppoe_username}</code>\n";
                $msg .= "├ Paket: " . htmlspecialchars($customer->package_name ?? '-') . "\n";
                $msg .= "├ Harga: Rp " . number_format($customer->package_price ?? 0, 0, ',', '.') . "\n";
                $msg .= "├ Tgl Isolir: " . ($customer->isolation_date ?? '-') . "\n";
                $msg .= "├ Unpaid: {$unpaid} invoice\n";
                $msg .= "└ Status: {$statusLabel}";

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❓ DATA CUSTOMER — NODERA</b>\n\n"
                    . "┌ Customer Tidak Ditemukan\n"
                    . "├ Status: TIDAK DITEMUKAN\n"
                    . "└ Info: Coba search dengan Nama, No HP, atau PPPoE Username.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === ISOLIR - Isolate Customer ===
        if (preg_match('/^ISOLIR\s+(.+)$/i', $text, $matches)) {
            $search = trim($matches[1]);

            $customer = DB::table('customers')
                ->select('customers.*', 'packages.profile_isolir')
                ->join('packages', 'packages.id', '=', 'customers.package_id', 'left')
                ->where(function ($q) use ($search) {
                    $q->where('customers.name', 'like', "%{$search}%")
                      ->orWhere('customers.phone', 'like', "%{$search}%")
                      ->orWhere('customers.pppoe_username', $search);
                })
                ->first();

            if (!$customer) {
                $msg = "<b>❓ ISOLIR CUSTOMER — NODERA</b>\n\n"
                    . "┌ Customer\n"
                    . "├ Status: TIDAK DITEMUKAN\n"
                    . "└ Info: Data customer tidak ditemukan.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            if ($customer->status === 'isolated') {
                $msg = "<b>⚠️ ISOLIR CUSTOMER — NODERA</b>\n\n"
                    . "┌ " . htmlspecialchars($customer->name) . "\n"
                    . "├ Status: SUDAH TERISOLIR\n"
                    . "└ Info: Customer sudah dalam status isolir.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            // Isolate in DB
            DB::table('customers')->where('id', $customer->id)->update(['status' => 'isolated']);

            // Isolate in MikroTik
            if (!empty($customer->pppoe_username)) {
                if (!empty($customer->profile_isolir)) {
                    $mikrotik->setPppoeUserProfile($customer->pppoe_username, $customer->profile_isolir);
                } else {
                    $mikrotik->disablePppoeSecret($customer->pppoe_username);
                }
            }

            $msg = "<b>🚫 ISOLIR CUSTOMER — NODERA</b>\n\n"
                . "┌ " . htmlspecialchars($customer->name) . "\n"
                . "├ PPPoE: <code>{$customer->pppoe_username}</code>\n"
                . "├ Status: TERISOLIR\n"
                . "└ Info: Layanan telah diisolir.";
            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === UNISOLIR - Activate Customer ===
        if (preg_match('/^UNISOLIR\s+(.+)$/i', $text, $matches)) {
            $search = trim($matches[1]);

            $customer = DB::table('customers')
                ->select('customers.*', 'packages.profile_normal')
                ->join('packages', 'packages.id', '=', 'customers.package_id', 'left')
                ->where(function ($q) use ($search) {
                    $q->where('customers.name', 'like', "%{$search}%")
                      ->orWhere('customers.phone', 'like', "%{$search}%")
                      ->orWhere('customers.pppoe_username', $search);
                })
                ->first();

            if (!$customer) {
                $msg = "<b>❓ AKTIVASI CUSTOMER — NODERA</b>\n\n"
                    . "┌ Customer\n"
                    . "├ Status: TIDAK DITEMUKAN\n"
                    . "└ Info: Data customer tidak ditemukan.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            if ($customer->status === 'active') {
                $msg = "<b>ℹ️ AKTIVASI CUSTOMER — NODERA</b>\n\n"
                    . "┌ " . htmlspecialchars($customer->name) . "\n"
                    . "├ Status: SUDAH AKTIF\n"
                    . "└ Info: Customer sudah dalam status aktif.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            // Activate in DB
            DB::table('customers')->where('id', $customer->id)->update(['status' => 'active']);

            // Activate in MikroTik
            if (!empty($customer->pppoe_username) && !empty($customer->profile_normal)) {
                $mikrotik->setPppoeUserProfile($customer->pppoe_username, $customer->profile_normal);
                $mikrotik->enablePppoeSecret($customer->pppoe_username);
            }

            $msg = "<b>✅ AKTIVASI CUSTOMER — NODERA</b>\n\n"
                . "┌ " . htmlspecialchars($customer->name) . "\n"
                . "├ PPPoE: <code>{$customer->pppoe_username}</code>\n"
                . "├ Status: DIAKTIFKAN\n"
                . "└ Info: Layanan telah diaktifkan kembali.";
            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === BAYAR - Mark Invoice as Paid ===
        if (preg_match('/^BAYAR\s+(.+)$/i', $text, $matches)) {
            $search = trim($matches[1]);

            $customer = DB::table('customers')
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                })
                ->first();

            if (!$customer) {
                $msg = "<b>❓ PEMBAYARAN TAGIHAN — NODERA</b>\n\n"
                    . "┌ Customer\n"
                    . "├ Status: TIDAK DITEMUKAN\n"
                    . "└ Info: Data customer tidak ditemukan.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            // Get unpaid invoices
            $invoices = DB::table('invoices')
                ->where('customer_id', $customer->id)
                ->where('paid', 0)
                ->orderBy('due_date', 'ASC')
                ->get();

            if ($invoices->isEmpty()) {
                $msg = "<b>ℹ️ PEMBAYARAN TAGIHAN — NODERA</b>\n\n"
                    . "┌ " . htmlspecialchars($customer->name) . "\n"
                    . "├ Status: TIDAK ADA TAGIHAN\n"
                    . "└ Info: Customer tidak memiliki tagihan yang belum dibayar.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            // Mark all as paid
            $paidCount = 0;
            foreach ($invoices as $inv) {
                DB::table('invoices')->where('id', $inv->id)->update([
                    'paid' => 1,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => 'Telegram Bot',
                    'processed_by' => $senderName ?: 'Telegram Bot',
                ]);
                $paidCount++;
            }

            // Unisolate customer in database
            DB::table('customers')->where('id', $customer->id)->update([
                'status' => 'active',
                'updated_at' => now()
            ]);

            // Activate in MikroTik
            $mikrotikSuccess = false;
            $packageData = DB::table('packages')->where('id', $customer->package_id)->first();
            if ($packageData && !empty($customer->pppoe_username)) {
                $mikrotik->setPppoeUserProfile($customer->pppoe_username, $packageData->profile_normal);
                $mikrotik->enablePppoeSecret($customer->pppoe_username);
                $mikrotikSuccess = true;
            }

            $totalPaid = array_sum(array_column($invoices->toArray(), 'amount'));

            $msg = "<b>✅ PEMBAYARAN TAGIHAN BERHASIL — NODERA</b>\n\n";
            $msg .= "┌ " . htmlspecialchars($customer->name) . "\n";
            $msg .= "├ Phone: <code>{$customer->phone}</code>\n";
            $msg .= "├ Jumlah Invoice: {$paidCount}\n";
            $msg .= "├ Total Bayar: Rp " . number_format($totalPaid, 0, ',', '.') . "\n";
            $msg .= "├ MikroTik: " . ($mikrotikSuccess ? 'AKTIF' : 'NORMAL') . "\n";
            $msg .= "├ Status: LUNAS\n";
            $msg .= "└ Waktu: " . now()->format('Y-m-d H:i:s');

            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === TAGIHAN - List Unpaid Invoices ===
        if (preg_match('/^TAGIHAN$/i', $text)) {
            $invoices = DB::table('invoices')
                ->select('invoices.*', 'customers.name as customer_name', 'customers.phone')
                ->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->where('invoices.paid', 0)
                ->where('invoices.due_date', '<', now()->format('Y-m-d'))
                ->orderBy('invoices.due_date', 'ASC')
                ->limit(10)
                ->get();

            if ($invoices->isEmpty()) {
                $msg = "<b>ℹ️ TAGIHAN OVERDUE — NODERA</b>\n\n"
                    . "┌ Status Tagihan\n"
                    . "├ Status: TIDAK ADA TAGIHAN OVERDUE\n"
                    . "└ Info: Semua invoice sudah dibayar!";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            $msg = "<b>⚠️ TAGIHAN OVERDUE — NODERA</b>\n\n";
            $msg .= "┌ Total: " . count($invoices) . " invoice\n";
            foreach ($invoices as $idx => $inv) {
                $num = $idx + 1;
                $isLast = ($num === count($invoices));
                $prefix = $isLast ? '└' : '├';
                $msg .= "{$prefix} {$num}. <code>{$inv->invoice_number}</code> - " . htmlspecialchars($inv->customer_name) . " (Rp " . number_format($inv->amount, 0, ',', '.') . ")\n";
            }

            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === INVOICE - Generate Monthly Invoices ===
        if (preg_match('/^INVOICE$/i', $text)) {
            $currentMonth = now()->format('Y-m');
            $customers = DB::table('customers')
                ->select('customers.*', 'packages.name as package_name', 'packages.price')
                ->join('packages', 'packages.id', '=', 'customers.package_id')
                ->where('customers.status', 'active')
                ->get();

            $count = 0;
            foreach ($customers as $c) {
                $exists = DB::table('invoices')
                    ->where('customer_id', $c->id)
                    ->where('created_at', 'like', "{$currentMonth}%")
                    ->count();

                if ($exists == 0) {
                    $isoDay = (int) ($c->isolation_date ?? 20);
                    if ($isoDay < 1 || $isoDay > 31) {
                        $isoDay = 20;
                    }
                    $targetDue = now()->day(min($isoDay, now()->daysInMonth));
                    if (!empty($c->created_at)) {
                        $createdAt = \Carbon\Carbon::parse($c->created_at);
                        if ($createdAt->format('Y-m') === now()->format('Y-m') && $createdAt->day >= $isoDay) {
                            $targetDue = now()->addMonth()->day(min($isoDay, now()->addMonth()->daysInMonth));
                        }
                    }
                    $dueDate = $targetDue->format('Y-m-d');
                    $invNumber = 'INV-' . now()->format('Ym') . '-' . $c->id;

                    DB::table('invoices')->insert([
                        'customer_id' => $c->id,
                        'invoice_number' => $invNumber,
                        'amount' => $c->price,
                        'description' => 'Tagihan Bulan ' . now()->format('F Y'),
                        'due_date' => $dueDate,
                        'paid' => 0,
                        'status' => 'pending'
                    ]);
                    $count++;
                }
            }

            $msg = "<b>📄 GENERATE INVOICE BULANAN — NODERA</b>\n\n"
                . "┌ Invoice Bulanan\n"
                . "├ Jumlah: {$count} invoice baru dibuat\n"
                . "├ Status: SELESAI\n"
                . "└ Waktu: " . now()->format('Y-m-d H:i:s');
            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === MEMBER - Generate Permanent Hotspot User ===
        if (preg_match('/^MEMBER\s+(\S+)\s+(\S+)\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];
            $password = $matches[2];
            $profile = $matches[3];

            // Add permanent user (no limit-uptime = empty string)
            $result = $mikrotik->addHotspotUser($username, $password, $profile, '');

            if ($result) {
                $msg = "<b>✅ MEMBER HOTSPOT DIBUAT — NODERA</b>\n\n";
                $msg .= "┌ <code>{$username}</code>\n";
                $msg .= "├ Username: <code>{$username}</code>\n";
                $msg .= "├ Password: <code>{$password}</code>\n";
                $msg .= "├ Profile: <code>{$profile}</code>\n";
                $msg .= "├ Tipe: Permanent User\n";
                $msg .= "├ Status: AKTIF\n";
                $msg .= "└ Waktu: " . now()->format('Y-m-d H:i:s');

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❌ MEMBER HOTSPOT GAGAL — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($mikrotik->getLastError()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === VCR - Voucher Custom (Manual Username, Auto Password) ===
        if (preg_match('/^VCR\s+(\S+)\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];
            $profile = $matches[2];

            // Auto-generate password: gabungan username + profile
            $password = $username . $profile;

            // Add voucher with limit-uptime
            $result = $mikrotik->addHotspotUser($username, $password, $profile, '24h');

            if ($result) {
                $msg = "<b>🎫 VOUCHER HOTSPOT DIBUAT — NODERA</b>\n\n";
                $msg .= "┌ <code>{$username}</code>\n";
                $msg .= "├ Username: <code>{$username}</code>\n";
                $msg .= "├ Password: <code>{$password}</code>\n";
                $msg .= "├ Profile: <code>{$profile}</code>\n";
                $msg .= "├ Limit: 24 jam\n";
                $msg .= "├ Status: SIAP DIGUNAKAN\n";
                $msg .= "└ Waktu: " . now()->format('Y-m-d H:i:s');

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❌ VOUCHER HOTSPOT GAGAL — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($mikrotik->getLastError()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === VOUCHER - Generate Hotspot Voucher ===
        if (preg_match('/^VOUCHER\s+(\S+)$/i', $text, $matches)) {
            $profile = $matches[1];

            // Fetch profile info from MikroTik
            $profileInfo = $mikrotik->getHotspotProfileInfo($profile);

            // Auto-generate username: 5 digit angka (10000-99999)
            $username = (string) rand(10000, 99999);
            $password = $username; // Same as username
            $comment = 'vc-gembok-tele';

            $result = $mikrotik->addHotspotUser($username, $password, $profile, '24h', $comment);

            if ($result) {
                $msg = "<b>🎫 VOUCHER HOTSPOT DIBUAT — NODERA</b>\n\n";
                $msg .= "┌ <code>{$username}</code>\n";
                $msg .= "├ Username: <code>{$username}</code>\n";
                $msg .= "├ Password: <code>{$password}</code>\n";
                $msg .= "├ Profile: <code>{$profile}</code>\n";

                // Show price and duration from profile if available
                if ($profileInfo) {
                    if (!empty($profileInfo['price'])) {
                        $msg .= "├ Harga: Rp " . number_format($profileInfo['price'], 0, ',', '.') . "\n";
                    }
                    if (!empty($profileInfo['duration'])) {
                        $msg .= "├ Durasi: {$profileInfo['duration']}\n";
                    }
                }
                $msg .= "├ Status: SIAP DIGUNAKAN\n";
                $msg .= "└ Waktu: " . now()->format('Y-m-d H:i:s');

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } else {
                $msg = "<b>❌ VOUCHER HOTSPOT GAGAL — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($mikrotik->getLastError()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === INFO - System Information (Admin Only) ===
        if ($command === 'INFO') {
            try {
                // Get statistics
                $totalCustomers = DB::table('customers')->count();
                $activeCustomers = DB::table('customers')->where('status', 'active')->count();
                $isolatedCustomers = DB::table('customers')->where('status', 'isolated')->count();

                $unpaidInvoices = DB::table('invoices')->where('paid', 0)->count();
                $paidThisMonth = DB::table('invoices')
                    ->where('paid', 1)
                    ->whereRaw("DATE_FORMAT(paid_at, '%Y-%m') = ?", [now()->format('Y-m')])
                    ->count();

                $revenueThisMonth = DB::table('invoices')
                    ->where('paid', 1)
                    ->whereRaw("DATE_FORMAT(paid_at, '%Y-%m') = ?", [now()->format('Y-m')])
                    ->sum('amount');

                // MikroTik stats
                $activePppoe = 0;
                $activeHotspot = 0;
                if ($mikrotik->isConnected()) {
                    $activePppoe = count($mikrotik->getActivePppoe());
                    $activeHotspot = count($mikrotik->getActiveHotspotUsers());
                }

                $msg = "<b>📊 INFO SISTEM — NODERA</b>\n\n";
                $msg .= "┌ Customer\n";
                $msg .= "├ Total: {$totalCustomers}\n";
                $msg .= "├ Aktif: {$activeCustomers}\n";
                $msg .= "└ Isolir: {$isolatedCustomers}\n\n";

                $msg .= "┌ Billing\n";
                $msg .= "├ Unpaid: {$unpaidInvoices} invoice\n";
                $msg .= "├ Paid: {$paidThisMonth} invoice (bulan ini)\n";
                $msg .= "└ Revenue: Rp " . number_format($revenueThisMonth, 0, ',', '.') . "\n\n";

                $msg .= "┌ MikroTik\n";
                $msg .= "├ PPPoE: {$activePppoe} online\n";
                $msg .= "├ Hotspot: {$activeHotspot} online\n";
                $msg .= "└ Status: ONLINE\n\n";

                $msg .= "Waktu: " . now()->format('d M Y H:i');

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } catch (\Exception $e) {
                $telegram->sendMessage($chatId, "Error: " . $e->getMessage());
            }
            return;
        }

        // === KICK - Kick Active User (Admin Only) ===
        if (preg_match('/^KICK\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];

            try {
                $mikrotik->kickPppoeUser($username);
                $msg = "<b>👢 KICK USER ONLINE — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: KONEKSI DIPUTUS\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            } catch (\Exception $e) {
                $msg = "<b>❌ KICK USER GAGAL — NODERA</b>\n\n"
                    . "┌ <code>{$username}</code>\n"
                    . "├ Status: GAGAL\n"
                    . "├ Error: " . htmlspecialchars($e->getMessage()) . "\n"
                    . "└ Waktu: " . now()->format('Y-m-d H:i:s');
                $telegram->sendMessage($chatId, $msg, 'HTML');
            }
            return;
        }

        // === CARI - Search PPPoE User in MikroTik (Admin Only) ===
        if (preg_match('/^CARI\s+(\S+)$/i', $text, $matches)) {
            $username = $matches[1];

            try {
                $users = $mikrotik->query('/ppp/secret/print', ['?name' => $username]);

                if (empty($users)) {
                    $msg = "<b>🔍 DATA USER PPPOE — NODERA</b>\n\n"
                        . "┌ <code>{$username}</code>\n"
                        . "├ Status: TIDAK DITEMUKAN\n"
                        . "└ Info: Username tidak ada di MikroTik.";
                    $telegram->sendMessage($chatId, $msg, 'HTML');
                    return;
                }

                $user = $users[0];
                $active = $mikrotik->query('/ppp/active/print', ['?name' => $username]);
                $isOnline = !empty($active);
                $statusText = $isOnline ? 'ONLINE' : 'OFFLINE';

                $msg = "<b>🔍 DATA USER PPPOE — NODERA</b>\n\n";
                $msg .= "┌ <code>{$user['name']}</code>\n";
                $msg .= "├ Username: <code>{$user['name']}</code>\n";
                $msg .= "├ Password: <code>{$user['password']}</code>\n";
                $msg .= "├ Profile: <code>{$user['profile']}</code>\n";
                $msg .= "├ Service: <code>{$user['service']}</code>\n";
                if ($isOnline) {
                    $msg .= "├ IP: <code>{$active[0]['address']}</code>\n";
                    $msg .= "├ Uptime: {$active[0]['uptime']}\n";
                }
                $msg .= "└ Status: {$statusText}";

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } catch (\Exception $e) {
                $telegram->sendMessage($chatId, "Error: " . $e->getMessage());
            }
            return;
        }

        // === AKTIF - List Active Customers (Admin Only) ===
        if ($command === 'AKTIF') {
            $customers = DB::table('customers')
                ->select('customers.name', 'customers.phone', 'customers.pppoe_username', 'packages.name as package_name')
                ->join('packages', 'packages.id', '=', 'customers.package_id', 'left')
                ->where('customers.status', 'active')
                ->orderBy('customers.name', 'ASC')
                ->limit(15)
                ->get();

            if ($customers->isEmpty()) {
                $msg = "<b>👥 CUSTOMER AKTIF — NODERA</b>\n\n"
                    . "┌ Data Customer\n"
                    . "├ Status: TIDAK ADA CUSTOMER AKTIF\n"
                    . "└ Info: Belum ada customer dengan status aktif.";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            $msg = "<b>👥 CUSTOMER AKTIF — NODERA</b>\n\n";
            $msg .= "┌ Total: " . count($customers) . " customer\n";
            foreach ($customers as $idx => $c) {
                $num = $idx + 1;
                $isLast = ($num === count($customers));
                $prefix = $isLast ? '└' : '├';
                $msg .= "{$prefix} {$num}. " . htmlspecialchars($c->name) . " (<code>{$c->pppoe_username}</code>) - " . htmlspecialchars($c->package_name ?? '-') . "\n";
            }

            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === NONAKTIF - List Isolated Customers (Admin Only) ===
        if ($command === 'NONAKTIF') {
            $customers = DB::table('customers')
                ->select('customers.name', 'customers.phone', 'customers.pppoe_username', 'packages.name as package_name')
                ->join('packages', 'packages.id', '=', 'customers.package_id', 'left')
                ->where('customers.status', 'isolated')
                ->orderBy('customers.name', 'ASC')
                ->limit(15)
                ->get();

            if ($customers->isEmpty()) {
                $msg = "<b>🚫 CUSTOMER ISOLIR — NODERA</b>\n\n"
                    . "┌ Data Customer\n"
                    . "├ Status: TIDAK ADA CUSTOMER ISOLIR\n"
                    . "└ Info: Semua customer saat ini aktif!";
                $telegram->sendMessage($chatId, $msg, 'HTML');
                return;
            }

            $msg = "<b>🚫 CUSTOMER ISOLIR — NODERA</b>\n\n";
            $msg .= "┌ Total: " . count($customers) . " customer\n";
            foreach ($customers as $idx => $c) {
                $num = $idx + 1;
                $isLast = ($num === count($customers));
                $prefix = $isLast ? '└' : '├';
                $msg .= "{$prefix} {$num}. " . htmlspecialchars($c->name) . " (<code>{$c->pppoe_username}</code>) - " . htmlspecialchars($c->package_name ?? '-') . "\n";
            }

            $telegram->sendMessage($chatId, $msg, 'HTML');
            return;
        }

        // === LAPORAN - Daily Report (Admin Only) ===
        if ($command === 'LAPORAN') {
            try {
                $today = now()->format('Y-m-d');

                // Payments today
                $paymentsToday = DB::table('invoices')
                    ->where('paid', 1)
                    ->whereRaw("DATE(paid_at) = ?", [$today])
                    ->count();

                $revenueToday = DB::table('invoices')
                    ->where('paid', 1)
                    ->whereRaw("DATE(paid_at) = ?", [$today])
                    ->sum('amount');

                // New customers today
                $newCustomers = DB::table('customers')
                    ->whereRaw("DATE(created_at) = ?", [$today])
                    ->count();

                // Isolated today
                $isolatedToday = DB::table('customers')
                    ->where('status', 'isolated')
                    ->whereRaw("DATE(updated_at) = ?", [$today])
                    ->count();

                // Overdue invoices
                $overdueCount = DB::table('invoices')
                    ->where('paid', 0)
                    ->where('due_date', '<', $today)
                    ->count();

                $msg = "<b>📊 LAPORAN HARIAN — NODERA</b>\n\n";
                $msg .= "┌ Pembayaran Hari Ini\n";
                $msg .= "├ Jumlah: {$paymentsToday} invoice\n";
                $msg .= "└ Total: Rp " . number_format($revenueToday, 0, ',', '.') . "\n\n";

                $msg .= "┌ Customer\n";
                $msg .= "├ Baru: {$newCustomers} customer\n";
                $msg .= "└ Isolir: {$isolatedToday} customer\n\n";

                $msg .= "┌ Perhatian\n";
                $msg .= "├ Overdue: {$overdueCount} tagihan\n";
                $msg .= "└ Waktu: " . now()->format('d M Y H:i');

                $telegram->sendMessage($chatId, $msg, 'HTML');
            } catch (\Exception $e) {
                $telegram->sendMessage($chatId, "Error: " . $e->getMessage());
            }
            return;
        }

        // === Default - Unknown Command ===
        // Silently ignore unknown commands
        return;
    }

    /**
     * Process callback query (inline button clicks)
     */
    private function processTelegramCallback($chatId, $messageId, $data, $callbackId, $telegram, $mikrotik, $threadId = null)
    {
        Log::info('Telegram Callback Received:', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'data' => $data,
            'callback_id' => $callbackId,
            'thread_id' => $threadId,
        ]);

        // Immediate acknowledgement to stop loading spinner on Telegram client
        if (!empty($callbackId)) {
            try {
                $telegram->answerCallback($callbackId);
            } catch (\Throwable $e) {}
        }

        if (str_starts_with((string) $data, 'acc_vch:')) {
            $action = 'acc_vch';
            $param = substr((string) $data, 8);
        } elseif (str_starts_with((string) $data, 'rej_vch:')) {
            $action = 'rej_vch';
            $param = substr((string) $data, 8);
        } elseif (str_starts_with((string) $data, 'acc_')) {
            $action = 'acc_vch';
            $param = substr((string) $data, 4);
        } elseif (str_starts_with((string) $data, 'rej_')) {
            $action = 'rej_vch';
            $param = substr((string) $data, 4);
        } elseif (str_starts_with((string) $data, 'acc:')) {
            $action = 'acc_vch';
            $param = substr((string) $data, 4);
        } elseif (str_starts_with((string) $data, 'rej:')) {
            $action = 'rej_vch';
            $param = substr((string) $data, 4);
        } else {
            $parts = explode(':', (string) $data, 2);
            $action = $parts[0] ?? '';
            $param = $parts[1] ?? '';
        }

        switch ($action) {
            case 'cmd':
                $telegram->answerCallback($callbackId);
                $this->processTelegramCommand($chatId, $param, $telegram, $mikrotik);
                break;

            case 'menu':
                $telegram->answerCallback($callbackId);
                $this->showTelegramMenu($chatId, $messageId, $param, $telegram, $mikrotik);
                break;

            case 'voucher':
                $telegram->answerCallback($callbackId);
                $this->showVoucherProfiles($chatId, $messageId, $telegram, $mikrotik);
                break;

            case 'gen_voucher':
                $telegram->answerCallback($callbackId);
                $this->generateVoucherInteractive($chatId, $param, $telegram, $mikrotik, $messageId);
                break;

            case 'mik':
                $telegram->answerCallback($callbackId);
                $this->handleMikrotikAction($chatId, $messageId, $param, $telegram, $mikrotik);
                break;

            case 'back':
                $telegram->answerCallback($callbackId);
                $this->showMainMenu($chatId, $messageId, $telegram);
                break;

            case 'reg_approve':
                $this->handleRegApprove($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'reg_reject':
                $this->handleRegReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'addon_approve':
                $this->handleAddonApprove($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'addon_reject':
                $this->handleAddonReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'vpn_topup_verify':
                $this->handleVpnTopupVerify($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'vpn_topup_reject':
                $this->handleVpnTopupReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'ref_approve':
            case 'referral_partner_approve':
                $this->handleReferralPartnerApprove($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'ref_reject':
            case 'referral_partner_reject':
                $this->handleReferralPartnerReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'ref_payout_approve':
            case 'referral_payout_approve':
                $this->handleReferralPayoutApprove($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'ref_payout_reject':
            case 'referral_payout_reject':
                $this->handleReferralPayoutReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'np_wd_approve':
            case 'noderapay_wd_approve':
                $this->handleNoderaPayWdApprove($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'np_wd_reject':
            case 'noderapay_wd_reject':
                $this->handleNoderaPayWdReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'acc_vch':
                $this->handleVoucherOrderAcc($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'rej_vch':
                $this->handleVoucherOrderReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'acc_inv':
                $this->handleCustomerInvoiceAcc($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'rej_inv':
                $this->handleCustomerInvoiceReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'watopup_acc':
            case 'watopup_approve':
                $this->handleWaTopupApprove($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;

            case 'watopup_rej':
            case 'watopup_reject':
                $this->handleWaTopupReject($chatId, $messageId, $param, $telegram, $threadId, $callbackId);
                break;
            default:
                $telegram->answerCallback($callbackId);
                break;
        }
    }

    private function handleVoucherOrderAcc($chatId, $messageId, $orderId, $telegram, $threadId = null, $callbackId = null)
    {
        $tenantTelegram = app(\App\Services\TenantTelegramService::class);
        $order = \App\Models\ShopOrder::withoutGlobalScopes()->with('items')
            ->where(function ($q) use ($orderId) {
                $q->where('id', $orderId)->orWhere('order_number', $orderId);
            })
            ->first();

        if (!$order) {
            if ($callbackId) {
                $telegram->answerCallback($callbackId, "Pesanan #{$orderId} tidak ditemukan.", true);
            }
            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, "<b>PESANAN TIDAK DITEMUKAN</b>\n\nNomor pesanan #{$orderId} tidak ditemukan dalam database.", 'HTML');
            }
            return;
        }

        $config = $tenantTelegram->getTenantConfig($order->tenant_id);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if ($order->order_status === 'completed' || $order->payment_status === 'paid') {
            if ($callbackId) {
                $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Pesanan #{$order->order_number} sudah pernah di-ACC.", true);
            }
            $tenantTelegram->updateOrderAccMessage($order, 'Admin via Telegram', (string) $chatId, (string) $messageId);
            return;
        }

        // Answer callback query immediately so the button loading spinner stops
        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Memproses ACC pesanan #{$order->order_number}...", false);
        }

        // If it's a voucher order, provision all voucher accounts to tenant's MikroTik
        if (!empty($order->voucher_username)) {
            $tenantId = $order->tenant_id;
            $router = \App\Models\Mikrotik::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('is_active', true)
                ->first()
                ?? \App\Models\Mikrotik::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->first();

            if ($router) {
                try {
                    $mikrotikService = new \App\Services\MikrotikService($router);
                    if ($mikrotikService->isConnected()) {
                        $voucherList = \App\Services\TenantTelegramService::parseVoucherList($order);
                        foreach ($voucherList as $v) {
                            $uName = $v['username'];
                            $uPass = $v['password'] ?: $uName;
                            $uProf = $v['profile'] ?: 'default';
                            $mikrotikService->addHotspotUser(
                                $uName,
                                $uPass,
                                $uProf,
                                "Online Shop Order #{$order->order_number}"
                            );
                        }
                        $order->voucher_created_in_mikrotik = true;
                    } else {
                        \Illuminate\Support\Facades\Log::warning("MikroTik router offline for tenant {$tenantId} during voucher ACC.");
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Error provisioning voucher on MikroTik: " . $e->getMessage());
                }
            }
        }

        $order->payment_status = 'paid';
        $order->order_status = 'completed';
        if ($messageId) {
            $order->telegram_message_id = (string) $messageId;
        }
        $order->save();

        // Update telegram message with credentials & WA link and dismiss buttons
        $tenantTelegram->updateOrderAccMessage($order, 'Admin via Telegram', (string) $chatId, (string) $messageId);

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Pesanan #{$order->order_number} BERHASIL DI-ACC & AKTIF!", true);
        }
    }

    private function handleVoucherOrderReject($chatId, $messageId, $orderId, $telegram, $threadId = null, $callbackId = null)
    {
        $tenantTelegram = app(\App\Services\TenantTelegramService::class);
        $order = \App\Models\ShopOrder::withoutGlobalScopes()
            ->where(function ($q) use ($orderId) {
                $q->where('id', $orderId)->orWhere('order_number', $orderId);
            })
            ->first();

        if (!$order) {
            if ($callbackId) {
                $telegram->answerCallback($callbackId, "Pesanan #{$orderId} tidak ditemukan.", true);
            }
            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, "<b>PESANAN TIDAK DITEMUKAN</b>\n\nNomor pesanan #{$orderId} tidak ditemukan dalam database.", 'HTML');
            }
            return;
        }

        $config = $tenantTelegram->getTenantConfig($order->tenant_id);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Memproses penolakan pesanan...", false);
        }

        $order->payment_status = 'rejected';
        $order->order_status = 'cancelled';
        if ($messageId) {
            $order->telegram_message_id = (string) $messageId;
        }
        $order->save();

        $tenantTelegram->updateOrderRejectMessage($order, 'Admin via Telegram', (string) $chatId, (string) $messageId);

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Pesanan #{$order->order_number} BERHASIL DITOLAK.", true);
        }
    }

    private function handleCustomerInvoiceAcc($chatId, $messageId, $invoiceId, $telegram, $threadId = null, $callbackId = null)
    {
        $tenantTelegram = app(\App\Services\TenantTelegramService::class);
        $invoice = \App\Models\Invoice::withoutGlobalScopes()->with(['customer.package', 'tenant'])->find($invoiceId);

        if (!$invoice) {
            if ($callbackId) {
                $telegram->answerCallback($callbackId, "Invoice #{$invoiceId} tidak ditemukan.", true);
            }
            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TAGIHAN TIDAK DITEMUKAN</b>\n\nTagihan #{$invoiceId} tidak ditemukan dalam database.", 'HTML');
            }
            return;
        }

        $config = $tenantTelegram->getTenantConfig($invoice->tenant_id ?? 0);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if ($invoice->paid || $invoice->status === 'paid') {
            if ($callbackId) {
                $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Tagihan #{$invoice->invoice_number} sudah pernah disetujui (lunas).", true);
            }
            $tenantTelegram->updateInvoiceAccMessage($invoice, 'Admin via Telegram', (string) $chatId, (string) $messageId);
            return;
        }

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Memproses ACC tagihan #{$invoice->invoice_number}...", false);
        }

        // 1. Mark invoice as paid
        $invoice->update([
            'paid' => 1,
            'status' => 'paid',
            'paid_at' => now(),
            'processed_by' => 'Admin via Telegram',
            'telegram_message_id' => (string) $messageId,
            'telegram_chat_id' => (string) $chatId,
        ]);

        // 2. Unisolate customer on MikroTik and Database
        if ($invoice->customer) {
            try {
                app(\App\Services\IsolationService::class)->unisolateCustomer($invoice->customer, 'Admin via Telegram', true);
            } catch (\Throwable $e) {
                Log::warning("Unisolate on telegram ACC error: " . $e->getMessage());
            }

            // 3. Dispatch WhatsApp and Push notification
            try {
                \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($invoice->customer, $invoice, 'payment_success');
            } catch (\Throwable $e) {}

            try {
                app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($invoice->customer, $invoice);
            } catch (\Throwable $e) {}
        }

        // 4. Update Telegram message and dismiss inline buttons
        $tenantTelegram->updateInvoiceAccMessage($invoice, 'Admin via Telegram', (string) $chatId, (string) $messageId);

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Tagihan #{$invoice->invoice_number} BERHASIL DI-ACC & LUNAS!", true);
        }
    }

    private function handleCustomerInvoiceReject($chatId, $messageId, $invoiceId, $telegram, $threadId = null, $callbackId = null)
    {
        $tenantTelegram = app(\App\Services\TenantTelegramService::class);
        $invoice = \App\Models\Invoice::withoutGlobalScopes()->with(['customer', 'tenant'])->find($invoiceId);

        if (!$invoice) {
            if ($callbackId) {
                $telegram->answerCallback($callbackId, "Invoice #{$invoiceId} tidak ditemukan.", true);
            }
            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TAGIHAN TIDAK DITEMUKAN</b>\n\nTagihan #{$invoiceId} tidak ditemukan dalam database.", 'HTML');
            }
            return;
        }

        $config = $tenantTelegram->getTenantConfig($invoice->tenant_id ?? 0);
        if ($chatId) {
            $config['chat_id'] = (string) $chatId;
        }

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Memproses penolakan pembayaran...", false);
        }

        if ($messageId) {
            $invoice->update([
                'telegram_message_id' => (string) $messageId,
                'telegram_chat_id' => (string) $chatId,
            ]);
        }

        $tenantTelegram->updateInvoiceRejectMessage($invoice, 'Admin via Telegram', (string) $chatId, (string) $messageId);

        if ($callbackId) {
            $tenantTelegram->answerCallbackWithConfig($config, $callbackId, "Konfirmasi pembayaran invoice #{$invoice->invoice_number} DITOLAK.", true);
        }
    }

    private function handleReferralPartnerApprove($chatId, $messageId, $partnerId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            \App\Services\ReferralService::ensureTablesExist();
            $partner = \App\Models\ReferralPartner::with('user')->find($partnerId);
            if (!$partner) {
                if ($messageId) $telegram->editMessage((string) $chatId, (int) $messageId, "<b>PENGAJUAN MITRA TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Mitra\n├ Status: TIDAK DITEMUKAN\n└ Info: Data mitra ID {$partnerId} tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Pengajuan mitra tidak ditemukan", true);
                return;
            }

            if ($partner->status === 'approved') {
                if ($messageId) $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Mitra sudah disetujui sebelumnya", true);
                return;
            }

            $partner->update([
                'status' => 'approved',
                'commission_rate' => $partner->commission_rate > 0 ? $partner->commission_rate : 10.00,
                'approved_at' => now(),
                'approved_by' => null,
                'rejection_reason' => null,
            ]);

            $partnerName = htmlspecialchars($partner->user?->name ?? $partner->name ?? 'Mitra');
            $refCode = htmlspecialchars($partner->referral_code ?? '-');

            $editedText = "<b>PENGAJUAN MITRA REFERRAL DISETUJUI — NODERA</b>\n\n"
                . "┌ {$partnerName}\n"
                . "├ Kode Referral: <code>{$refCode}</code>\n"
                . "├ Rate Komisi: {$partner->commission_rate}%\n"
                . "├ Status: DISETUJUI\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            // Send WhatsApp notification to Partner
            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled()) {
                    $wa->sendReferralApproved($partner, $partner->user);
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp referral approve via telegram failed: ' . $e->getMessage());
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Mitra {$partnerName} BERHASIL DISETUJUI!", true);
        } catch (\Throwable $e) {
            Log::error('handleReferralPartnerApprove error: ' . $e->getMessage());
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleReferralPartnerReject($chatId, $messageId, $partnerId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            \App\Services\ReferralService::ensureTablesExist();
            $partner = \App\Models\ReferralPartner::with('user')->find($partnerId);
            if (!$partner) {
                if ($messageId) $telegram->editMessage((string) $chatId, (int) $messageId, "<b>PENGAJUAN MITRA TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Mitra\n├ Status: TIDAK DITEMUKAN\n└ Info: Data mitra ID {$partnerId} tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Pengajuan mitra tidak ditemukan", true);
                return;
            }

            if ($partner->status === 'rejected') {
                if ($messageId) $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Pengajuan mitra sudah ditolak sebelumnya", true);
                return;
            }

            $partner->update([
                'status' => 'rejected',
                'rejection_reason' => 'Ditolak via Telegram',
            ]);

            $partnerName = htmlspecialchars($partner->user?->name ?? $partner->name ?? 'Mitra');
            $refCode = htmlspecialchars($partner->referral_code ?? '-');

            $editedText = "<b>PENGAJUAN MITRA REFERRAL DITOLAK — NODERA</b>\n\n"
                . "┌ {$partnerName}\n"
                . "├ Kode Referral: <code>{$refCode}</code>\n"
                . "├ Status: DITOLAK\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Pengajuan mitra {$partnerName} DITOLAK.", true);
        } catch (\Throwable $e) {
            Log::error('handleReferralPartnerReject error: ' . $e->getMessage());
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleReferralPayoutApprove($chatId, $messageId, $withdrawalId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            \App\Services\ReferralService::ensureTablesExist();
            $w = \App\Models\ReferralWithdrawal::with(['partner', 'user'])->find($withdrawalId);
            if (!$w) {
                if ($messageId) $telegram->editMessage((string) $chatId, (int) $messageId, "<b>PENARIKAN KOMISI TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Penarikan\n├ Status: TIDAK DITEMUKAN\n└ Info: Data penarikan ID {$withdrawalId} tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Data penarikan tidak ditemukan", true);
                return;
            }

            if ($w->status !== 'pending') {
                if ($messageId) $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Penarikan sudah diproses sebelumnya ({$w->status})", true);
                return;
            }

            \App\Services\ReferralService::approveWithdrawal($w, null, 'Disetujui dan ditransfer');

            $partnerName = htmlspecialchars($w->partner?->name ?? $w->user?->name ?? 'Mitra');
            $refCode = htmlspecialchars($w->partner?->referral_code ?? '-');
            $bankName = htmlspecialchars($w->bank_name ?? '-');
            $accNumber = htmlspecialchars($w->account_number ?? '-');
            $accName = htmlspecialchars($w->account_name ?? '-');

            $editedText = "<b>PENCAIRAN KOMISI REFERRAL DISETUJUI — NODERA</b>\n\n"
                . "┌ {$partnerName}\n"
                . "├ Kode Referral: <code>{$refCode}</code>\n"
                . "├ Jumlah: Rp " . number_format($w->amount, 0, ',', '.') . "\n"
                . "├ Bank / Tujuan: {$bankName}\n"
                . "├ No. Rekening: <code>{$accNumber}</code>\n"
                . "├ Atas Nama: {$accName}\n"
                . "├ Status: SELESAI\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Pencairan komisi Rp " . number_format($w->amount, 0, ',', '.') . " BERHASIL DISETUJUI!", true);
        } catch (\Throwable $e) {
            Log::error('handleReferralPayoutApprove error: ' . $e->getMessage());
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleReferralPayoutReject($chatId, $messageId, $withdrawalId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            \App\Services\ReferralService::ensureTablesExist();
            $w = \App\Models\ReferralWithdrawal::with(['partner', 'user'])->find($withdrawalId);
            if (!$w) {
                if ($messageId) $telegram->editMessage((string) $chatId, (int) $messageId, "<b>PENARIKAN KOMISI TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Penarikan\n├ Status: TIDAK DITEMUKAN\n└ Info: Data penarikan ID {$withdrawalId} tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Data penarikan tidak ditemukan", true);
                return;
            }

            if ($w->status !== 'pending') {
                if ($messageId) $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Penarikan sudah diproses sebelumnya ({$w->status})", true);
                return;
            }

            \App\Services\ReferralService::rejectWithdrawal($w, null, 'Ditolak');

            $partnerName = htmlspecialchars($w->partner?->name ?? $w->user?->name ?? 'Mitra');
            $refCode = htmlspecialchars($w->partner?->referral_code ?? '-');

            $editedText = "<b>PENCAIRAN KOMISI REFERRAL DITOLAK — NODERA</b>\n\n"
                . "┌ {$partnerName}\n"
                . "├ Kode Referral: <code>{$refCode}</code>\n"
                . "├ Jumlah: Rp " . number_format($w->amount, 0, ',', '.') . "\n"
                . "├ Status: DITOLAK (SALDO DIKEMBALIKAN)\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Pencairan komisi DITOLAK (saldo dikembalikan).", true);
        } catch (\Throwable $e) {
            Log::error('handleReferralPayoutReject error: ' . $e->getMessage());
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleNoderaPayWdApprove($chatId, $messageId, $withdrawalId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $w = \App\Models\NoderaPayMerchantWithdrawal::with('merchant')->find($withdrawalId);
            if (!$w) {
                if ($messageId) $telegram->editMessage((string) $chatId, (int) $messageId, "<b>SETTLEMENT TIDAK DITEMUKAN — NODERA PAY</b>\n\nData penarikan ID {$withdrawalId} tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Data settlement tidak ditemukan", true);
                return;
            }

            if ($w->status !== 'pending') {
                if ($messageId) $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Settlement sudah diproses sebelumnya ({$w->status})", true);
                return;
            }

            /** @var \App\Services\NoderaPayEngineService $engine */
            $engine = app(\App\Services\NoderaPayEngineService::class);
            $engine->completeWithdrawal($w, null, 'Disetujui via Telegram');

            $merchantName = htmlspecialchars($w->merchant?->name ?? 'Merchant');
            $wdCode = htmlspecialchars($w->withdrawal_code);
            $bankName = htmlspecialchars($w->bank_name ?? '-');
            $bankAccNum = htmlspecialchars($w->bank_account_number ?? '-');
            $bankAccName = htmlspecialchars($w->bank_account_name ?? '-');

            $editedText = "<b>✅ SETTLEMENT NODERA PAY DISETUJUI & DITRANSFER</b>\n\n"
                . "┌ <b>Data Settlement</b>\n"
                . "├ Merchant: <b>{$merchantName}</b>\n"
                . "├ Kode: <code>{$wdCode}</code>\n"
                . "├ Jumlah: <b>Rp " . number_format($w->amount, 0, ',', '.') . "</b>\n"
                . "├ Rekening: <b>{$bankName}</b> - <code>{$bankAccNum}</code>\n"
                . "├ Atas Nama: <b>{$bankAccName}</b>\n"
                . "├ Status: <b>SELESAI (SUKSES)</b>\n"
                . "├ Diproses oleh: SuperAdmin via Telegram\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s') . "\n\n"
                . "<i>Notifikasi WhatsApp konfirmasi transfer telah dikirim ke merchant.</i>";

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Settlement Rp " . number_format($w->amount, 0, ',', '.') . " BERHASIL DISETUJUI & WA TERKIRIM!", true);
        } catch (\Throwable $e) {
            Log::error('handleNoderaPayWdApprove error: ' . $e->getMessage());
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleNoderaPayWdReject($chatId, $messageId, $withdrawalId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $w = \App\Models\NoderaPayMerchantWithdrawal::with('merchant')->find($withdrawalId);
            if (!$w) {
                if ($messageId) $telegram->editMessage((string) $chatId, (int) $messageId, "<b>SETTLEMENT TIDAK DITEMUKAN — NODERA PAY</b>\n\nData penarikan ID {$withdrawalId} tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Data settlement tidak ditemukan", true);
                return;
            }

            if ($w->status !== 'pending') {
                if ($messageId) $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                if ($callbackId) $telegram->answerCallback($callbackId, "Settlement sudah diproses sebelumnya ({$w->status})", true);
                return;
            }

            /** @var \App\Services\NoderaPayEngineService $engine */
            $engine = app(\App\Services\NoderaPayEngineService::class);
            $engine->rejectWithdrawal($w, 'Ditolak via Telegram oleh Admin');

            $merchantName = htmlspecialchars($w->merchant?->name ?? 'Merchant');
            $wdCode = htmlspecialchars($w->withdrawal_code);

            $editedText = "<b>❌ SETTLEMENT NODERA PAY DITOLAK</b>\n\n"
                . "┌ <b>Data Settlement</b>\n"
                . "├ Merchant: <b>{$merchantName}</b>\n"
                . "├ Kode: <code>{$wdCode}</code>\n"
                . "├ Jumlah: <b>Rp " . number_format($w->amount, 0, ',', '.') . "</b>\n"
                . "├ Status: <b>DITOLAK (SALDO DIKEMBALIKAN KE MERCHANT)</b>\n"
                . "├ Diproses oleh: SuperAdmin via Telegram\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s') . "\n\n"
                . "<i>Notifikasi penolakan dan refund saldo telah dikirim ke WhatsApp merchant.</i>";

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Settlement DITOLAK & saldo telah dikembalikan ke merchant.", true);
        } catch (\Throwable $e) {
            Log::error('handleNoderaPayWdReject error: ' . $e->getMessage());
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleVpnTopupReject($chatId, $messageId, $topupId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $topup = \App\Models\VpnTopupRequest::with('user')->find($topupId);
            if (!$topup) {
                $topup = \App\Models\VpnTopupRequest::with('user')
                    ->where('invoice_number', $topupId)
                    ->orWhere('invoice_number', 'LIKE', "%{$topupId}%")
                    ->first();
            }

            if (!$topup) {
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Topup\n├ Status: TIDAK DITEMUKAN\n└ Info: Permintaan topup tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    }
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Topup tidak ditemukan", true);
                return;
            }
            if ($topup->status !== 'pending') {
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP SUDAH DIPROSES — NODERA</b>\n\n┌ Data Topup\n├ Status: SUDAH DIPROSES\n└ Info: Permintaan topup ini sudah diproses sebelumnya ({$topup->status}).", 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    }
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Sudah diproses sebelumnya ({$topup->status})", true);
                return;
            }
            $topup->update(['status' => 'rejected']);
            $user = $topup->user;
            $userName = htmlspecialchars($user?->name ?? 'User');
            $invNum = htmlspecialchars($topup->invoice_number ?? '');
            $editedText = "<b>TOPUP SALDO DITOLAK — NODERA</b>\n\n"
                . "┌ {$userName}\n"
                . "├ No. Invoice: <code>{$invNum}</code>\n"
                . "├ Nominal: Rp " . number_format($topup->amount, 0, ',', '.') . "\n"
                . "├ Status: DITOLAK\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            // Send automated WhatsApp rejection
            try {
                $wa = \App\Services\WhatsappService::forSuperadmin();
                if ($wa->isEnabled() && $user && !empty($user->phone)) {
                    $wa->sendTopupRejected($topup, $user, 'Ditolak via Telegram Admin');
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp notification on Telegram topup reject failed: ' . $e->getMessage());
            }

            $cleanUserPhone = preg_replace('/[^0-9]/', '', $topup->user?->phone ?? '');
            if (str_starts_with($cleanUserPhone, '0')) {
                $cleanUserPhone = '62' . substr($cleanUserPhone, 1);
            }
            $waTopupRej = "Halo {$userName}, permintaan topup saldo Anda (#{$invNum}) belum dapat disetujui. Mohon periksa kembali bukti transfer Anda.";
            $waLink = !empty($cleanUserPhone) ? "https://wa.me/{$cleanUserPhone}?text=" . urlencode($waTopupRej) : null;

            $replyMarkup = [
                'inline_keyboard' => $waLink ? [
                    [
                        ['text' => 'Kirim WhatsApp ke User', 'url' => $waLink],
                    ]
                ] : []
            ];

            if ($messageId) {
                $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', $replyMarkup);
                if (!($editRes['ok'] ?? false)) {
                    $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, $replyMarkup);
                    $telegram->sendMessage((string) $chatId, $editedText, 'HTML', $replyMarkup, $threadId);
                }
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', $replyMarkup, $threadId);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Topup saldo DITOLAK.", true);
        } catch (\Throwable $e) {
            Log::error('handleVpnTopupReject error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleVpnTopupVerify($chatId, $messageId, $topupId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $topup = \App\Models\VpnTopupRequest::with('user')->find($topupId);
            if (!$topup) {
                $topup = \App\Models\VpnTopupRequest::with('user')
                    ->where('invoice_number', $topupId)
                    ->orWhere('invoice_number', 'LIKE', "%{$topupId}%")
                    ->first();
            }

            if (!$topup) {
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Topup\n├ Status: TIDAK DITEMUKAN\n└ Info: Permintaan topup tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    }
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Topup tidak ditemukan", true);
                return;
            }
            if ($topup->status !== 'pending') {
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP SUDAH DIPROSES — NODERA</b>\n\n┌ Data Topup\n├ Status: SUDAH DIPROSES\n└ Info: Permintaan topup ini sudah diproses sebelumnya ({$topup->status}).", 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    }
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Sudah diproses sebelumnya ({$topup->status})", true);
                return;
            }
            $settled = \App\Models\VpnTopupRequest::settleTopup(
                $topup,
                (float) ($topup->total_amount ?: $topup->amount),
                'Diverifikasi via Telegram Admin'
            );

            if (!$settled) {
                if ($callbackId) $telegram->answerCallback($callbackId, "Sudah diproses sebelumnya", true);
                return;
            }

            $user = $topup->user ?? \App\Models\VpnUser::find($topup->vpn_user_id);

            $userName = htmlspecialchars($user?->name ?? 'User');
            $invNum = htmlspecialchars($topup->invoice_number ?? '');
            $bankDest = htmlspecialchars($topup->bank_destination ?? '-');
            $editedText = "<b>TOPUP SALDO DIVERIFIKASI — NODERA</b>\n\n"
                . "┌ {$userName}\n"
                . "├ No. Invoice: <code>{$invNum}</code>\n"
                . "├ Nominal: Rp " . number_format($topup->amount, 0, ',', '.') . "\n"
                . "├ Bank Tujuan: {$bankDest}\n"
                . "├ Status: DISETUJUI\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            $cleanUserPhone = preg_replace('/[^0-9]/', '', $topup->user?->phone ?? '');
            if (str_starts_with($cleanUserPhone, '0')) {
                $cleanUserPhone = '62' . substr($cleanUserPhone, 1);
            }
            $waTopupMsg = "Halo {$userName}, topup saldo Anda sejumlah Rp " . number_format($topup->amount, 0, ',', '.') . " (#{$invNum}) telah diverifikasi & masuk ke saldo akun Anda. Terima kasih!";
            $waLink = !empty($cleanUserPhone) ? "https://wa.me/{$cleanUserPhone}?text=" . urlencode($waTopupMsg) : null;

            $replyMarkup = [
                'inline_keyboard' => $waLink ? [
                    [
                        ['text' => 'Kirim WhatsApp ke User', 'url' => $waLink],
                    ]
                ] : []
            ];

            if ($messageId) {
                $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', $replyMarkup);
                if (!($editRes['ok'] ?? false)) {
                    $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, $replyMarkup);
                    $telegram->sendMessage((string) $chatId, $editedText, 'HTML', $replyMarkup, $threadId);
                }
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', $replyMarkup, $threadId);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Topup Rp " . number_format($topup->amount, 0, ',', '.') . " BERHASIL DISETUJUI!", true);
        } catch (\Throwable $e) {
            Log::error('handleVpnTopupVerify error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleWaTopupApprove($chatId, $messageId, $topupId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $topup = \App\Models\WaTopup::with('merchant')->find($topupId);
            if (!$topup) {
                $topup = \App\Models\WaTopup::with('merchant')
                    ->where('invoice_number', $topupId)
                    ->orWhere('invoice_number', 'LIKE', "%{$topupId}%")
                    ->first();
            }

            if (!$topup) {
                if ($messageId) {
                    $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP WA GATEWAY TIDAK DITEMUKAN</b>\n\nPermintaan topup tidak ditemukan dalam sistem.", 'HTML', ['inline_keyboard' => []]);
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Topup WA Gateway tidak ditemukan", true);
                return;
            }

            if ($topup->payment_status !== 'pending') {
                if ($messageId) {
                    $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP SUDAH DIPROSES</b>\n\nPermintaan topup ini sudah berstatus " . strtoupper($topup->payment_status), 'HTML', ['inline_keyboard' => []]);
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Sudah diproses ({$topup->payment_status})", true);
                return;
            }

            \App\Models\WaTopup::settleTopup($topup, 'Diverifikasi manual oleh Admin via Telegram');

            $merchant = $topup->merchant;
            $merchantName = htmlspecialchars($merchant?->name ?? 'Merchant');
            $merchantCode = htmlspecialchars($merchant?->merchant_code ?? '-');
            $invNum = htmlspecialchars($topup->invoice_number);
            $planDesc = htmlspecialchars($topup->metadata['plan_name'] ?? ($topup->type === 'pro_subscription' ? 'Pro Unlimited' : ($topup->type === 'device_slot' ? '+1 Slot Device' : 'Deposit Saldo')));

            $editedText = "<b>✅ WA GATEWAY — TOPUP DISETUJUI & AKTIF</b>\n\n"
                . "┌ <b>Detail Transaksi</b>\n"
                . "├ Merchant: <b>{$merchantName}</b> ({$merchantCode})\n"
                . "├ No. Invoice: <code>{$invNum}</code>\n"
                . "├ Layanan: {$planDesc}\n"
                . "├ Nominal: Rp " . number_format((float)$topup->total_amount, 0, ',', '.') . "\n"
                . "├ Status: <b>DISETUJUI (LUNAS)</b>\n"
                . "├ Paket Saat Ini: <b>" . strtoupper($merchant?->plan_type ?? 'FREE') . "</b> (Limit: {$merchant?->device_limit} Device)\n"
                . "├ Saldo Merchant: Rp " . number_format((float)($merchant?->credit_balance ?? 0), 0, ',', '.') . "\n"
                . "├ Diverifikasi oleh: Admin via Telegram\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Topup WA Gateway Rp " . number_format((float)$topup->total_amount, 0, ',', '.') . " BERHASIL DISETUJUI!", true);
        } catch (\Throwable $e) {
            Log::error('handleWaTopupApprove error: ' . $e->getMessage());
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleWaTopupReject($chatId, $messageId, $topupId, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $topup = \App\Models\WaTopup::with('merchant')->find($topupId);
            if (!$topup) {
                $topup = \App\Models\WaTopup::with('merchant')
                    ->where('invoice_number', $topupId)
                    ->orWhere('invoice_number', 'LIKE', "%{$topupId}%")
                    ->first();
            }

            if (!$topup) {
                if ($messageId) {
                    $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP WA GATEWAY TIDAK DITEMUKAN</b>\n\nPermintaan topup tidak ditemukan dalam sistem.", 'HTML', ['inline_keyboard' => []]);
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Topup tidak ditemukan", true);
                return;
            }

            if ($topup->payment_status !== 'pending') {
                if ($messageId) {
                    $telegram->editMessage((string) $chatId, (int) $messageId, "<b>TOPUP SUDAH DIPROSES</b>\n\nPermintaan topup ini sudah berstatus " . strtoupper($topup->payment_status), 'HTML', ['inline_keyboard' => []]);
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Sudah diproses ({$topup->payment_status})", true);
                return;
            }

            \App\Models\WaTopup::rejectTopup($topup, 'Ditolak via Telegram oleh Admin');

            $merchant = $topup->merchant;
            $merchantName = htmlspecialchars($merchant?->name ?? 'Merchant');
            $merchantCode = htmlspecialchars($merchant?->merchant_code ?? '-');
            $invNum = htmlspecialchars($topup->invoice_number);

            $editedText = "<b>❌ WA GATEWAY — TOPUP DITOLAK</b>\n\n"
                . "┌ <b>Detail Transaksi</b>\n"
                . "├ Merchant: <b>{$merchantName}</b> ({$merchantCode})\n"
                . "├ No. Invoice: <code>{$invNum}</code>\n"
                . "├ Nominal: Rp " . number_format((float)$topup->total_amount, 0, ',', '.') . "\n"
                . "├ Status: <b>DITOLAK</b>\n"
                . "├ Ditolak oleh: Admin via Telegram\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');

            if ($messageId) {
                $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }

            if ($callbackId) $telegram->answerCallback($callbackId, "Topup WA Gateway DITOLAK.", true);
        } catch (\Throwable $e) {
            Log::error('handleWaTopupReject error: ' . $e->getMessage());
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleAddonApprove($chatId, $messageId, $id, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $ta = \App\Models\TenantAddon::with('addon')->find($id);
            if (!$ta) {
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, "<b>ADD-ON TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Add-on\n├ Status: TIDAK DITEMUKAN\n└ Info: Permohonan add-on tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    }
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Add-on tidak ditemukan", true);
                return;
            }
            $ta->update(['is_active' => true, 'status' => 'approved', 'paid_at' => now()]);
            if ($ta->addon && in_array($ta->addon->slug, ['mikhmon_online', 'mikhmon']) && $ta->tenant) {
                try {
                    \App\Services\MikhmonProvisioner::syncForTenant($ta->tenant, $ta);
                } catch (\Throwable $e) {
                    Log::warning('[WebhookController] syncForTenant error: ' . $e->getMessage());
                }
            }
            $addonName = htmlspecialchars($ta->addon?->name ?? 'Add-on');
            $editedText = "<b>AKTIVASI ADD-ON DISETUJUI — NODERA</b>\n\n"
                . "┌ {$addonName}\n"
                . "├ Modul Fitur: {$addonName}\n"
                . "├ Tenant ID: {$ta->tenant_id}\n"
                . "├ Status: DISETUJUI\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');
            if ($messageId) {
                $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
                if (!($editRes['ok'] ?? false)) {
                    $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
                }
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Add-on {$addonName} BERHASIL DISETUJUI!", true);
        } catch (\Throwable $e) {
            Log::error('handleAddonApprove error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleAddonReject($chatId, $messageId, $id, $telegram, $threadId = null, $callbackId = null)
    {
        try {
            $ta = \App\Models\TenantAddon::with('addon')->find($id);
            if (!$ta) {
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, "<b>ADD-ON TIDAK DITEMUKAN — NODERA</b>\n\n┌ Data Add-on\n├ Status: TIDAK DITEMUKAN\n└ Info: Permohonan add-on tidak ditemukan.", 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    }
                }
                if ($callbackId) $telegram->answerCallback($callbackId, "Add-on tidak ditemukan", true);
                return;
            }
            $addonName = htmlspecialchars($ta->addon?->name ?? 'Add-on');
            $tenantId = $ta->tenant_id;
            $ta->delete();
            $editedText = "<b>AKTIVASI ADD-ON DITOLAK — NODERA</b>\n\n"
                . "┌ {$addonName}\n"
                . "├ Modul Fitur: {$addonName}\n"
                . "├ Tenant ID: {$tenantId}\n"
                . "├ Status: DITOLAK\n"
                . "└ Waktu: " . now()->format('d/m/Y H:i:s');
            if ($messageId) {
                $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $editedText, 'HTML', ['inline_keyboard' => []]);
                if (!($editRes['ok'] ?? false)) {
                    $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                    $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
                }
            } else {
                $telegram->sendMessage((string) $chatId, $editedText, 'HTML', null, $threadId);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Add-on {$addonName} DITOLAK.", true);
        } catch (\Throwable $e) {
            Log::error('handleAddonReject error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) $telegram->answerCallback($callbackId, "Gagal: " . $e->getMessage(), true);
        }
    }

    private function handleRegApprove($chatId, $messageId, $slug, $telegram, $threadId = null, $callbackId = null)
    {
        $isFr = app()->getLocale() === 'fr' 
            || env('APP_LOCALE') === 'fr' 
            || config('app.locale') === 'fr'
            || str_contains(request()->getHost(), 'airnetsolution')
            || str_contains(config('app.url'), 'airnetsolution');

        $slug = trim((string) $slug);
        $host = request()->getHost();
        $baseDomain = config('app.base_domain');
        if (!$baseDomain || $baseDomain === 'localhost' || $baseDomain === '127.0.0.1') {
            $baseDomain = ($host && $host !== 'localhost' && $host !== '127.0.0.1')
                ? preg_replace('/^(?:vpn|portal|panel|api|admin)\./i', '', $host)
                : parse_url(config('app.url'), PHP_URL_HOST);
        }
        if (!$baseDomain || $baseDomain === 'localhost' || $baseDomain === '127.0.0.1') {
            $baseDomain = $isFr ? 'airnetsolution.com' : 'nodera.id';
        }

        try {
            $req = \App\Models\RegistrationRequest::where('status', 'pending')
                ->where(function ($q) use ($slug) {
                    $q->where('slug', $slug)
                      ->orWhere('id', $slug)
                      ->orWhereRaw('LOWER(slug) = ?', [strtolower($slug)]);
                })->first();

            if ($req) {
                $result = (new \App\Actions\Tenant\ApproveRegistration)->execute($req);
                $tenant = $result['tenant'];
                $admin = $result['user'];
                $passDisplay = $result['password'];
                $quotaText = $tenant->max_customers > 0 
                    ? ($isFr ? "{$tenant->max_customers} Clients" : "{$tenant->max_customers} Pelanggan") 
                    : ($isFr ? "Illimité" : "Unlimited");

                $compName = htmlspecialchars($req->company ?? '');
                $reqName = htmlspecialchars($req->name ?? '');
                $reqSlug = htmlspecialchars($tenant->slug ?? $req->slug ?? '');
                $reqEmail = htmlspecialchars($req->email ?? '');
                $adminUser = htmlspecialchars($admin->username ?? '');
                $passShow = htmlspecialchars($passDisplay ?? '');
                $pkgName = htmlspecialchars($tenant->package_name ?? '');
                $quotaShow = htmlspecialchars($quotaText ?? '');
                $domShow = htmlspecialchars($baseDomain ?? '');

                if ($isFr) {
                    $approvalText = "<b>INSCRIPTION APPROUVÉE — {$reqName}</b>\n\n"
                        . "┌ {$compName}\n"
                        . "├ Sous-domaine: <code>{$reqSlug}.{$domShow}</code>\n"
                        . "├ E-mail: <code>{$reqEmail}</code>\n"
                        . "├ Identifiant: <code>{$adminUser}</code>\n"
                        . "├ Mot de passe: <code>{$passShow}</code>\n"
                        . "├ Forfait: <code>{$pkgName} ({$quotaShow})</code>\n"
                        . "├ Statut: APPROUVÉ\n"
                        . "├ Connexion: https://{$reqSlug}.{$domShow}/login\n"
                        . "└ Heure: " . now()->format('d/m/Y H:i:s');
                } else {
                    $approvalText = "<b>PENDAFTARAN TENANT DISETUJUI — NODERA</b>\n\n"
                        . "┌ {$reqName}\n"
                        . "├ Mitra / ISP: <code>{$compName}</code>\n"
                        . "├ Domain: <code>{$reqSlug}.{$domShow}</code>\n"
                        . "├ Email: <code>{$reqEmail}</code>\n"
                        . "├ Username: <code>{$adminUser}</code>\n"
                        . "├ Password: <code>{$passShow}</code>\n"
                        . "├ Paket: <code>{$pkgName} ({$quotaShow})</code>\n"
                        . "├ Status: DISETUJUI\n"
                        . "├ Login URL: https://{$reqSlug}.{$domShow}/login\n"
                        . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                }

                $tenantPhone = preg_replace('/[^0-9]/', '', $req->phone ?? '');
                if (str_starts_with($tenantPhone, '0')) {
                    $tenantPhone = '62' . substr($tenantPhone, 1);
                }
                $loginUrl = "https://{$reqSlug}.{$domShow}/login";
                $waText = "Halo {$req->name} ({$req->company}),\n\n"
                    . "Pendaftaran instansi ISP Anda di NODERA telah disetujui dan aktif!\n\n"
                    . "Detail Akun Panel Anda:\n"
                    . "• Subdomain / URL: {$loginUrl}\n"
                    . "• Username Admin: {$admin->username}\n"
                    . "• Password: (sesuai saat pendaftaran)\n"
                    . "• Paket: {$pkgName}\n\n"
                    . "Silakan login ke panel Anda untuk mulai konfigurasi router MikroTik dan pelanggan. Terima kasih telah bergabung!";

                $replyMarkup = ['inline_keyboard' => []];
                if (!empty($tenantPhone)) {
                    $replyMarkup['inline_keyboard'][] = [
                        $telegram->inlineButton('Kirim WhatsApp ke Calon Tenant', null, 'https://wa.me/' . $tenantPhone . '?text=' . rawurlencode($waText))
                    ];
                }

                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $approvalText, 'HTML', $replyMarkup);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, $replyMarkup);
                        $telegram->sendMessage((string) $chatId, $approvalText, 'HTML', $replyMarkup, $threadId);
                    }
                } else {
                    $telegram->sendMessage((string) $chatId, $approvalText, 'HTML', $replyMarkup, $threadId);
                }

                if ($callbackId) {
                    $telegram->answerCallback($callbackId, $isFr ? "Inscription approuvée avec succès!" : "Pendaftaran berhasil disetujui!", true);
                }
                return;
            }

            $existing = \App\Models\RegistrationRequest::where('slug', $slug)
                ->orWhere('id', $slug)
                ->orWhereRaw('LOWER(slug) = ?', [strtolower($slug)])
                ->first();

            if ($existing) {
                $compName = htmlspecialchars($existing->company ?? '');
                $reqName = htmlspecialchars($existing->name ?? '');
                $reqSlug = htmlspecialchars($existing->slug ?? '');
                $domShow = htmlspecialchars($baseDomain ?? '');

                if ($existing->status === 'approved') {
                    $tenant = \App\Models\Tenant::where('slug', $existing->slug)->first();
                    $admin = $tenant ? \App\Models\User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'admin')->first() : null;
                    $username = $admin ? $admin->username : ($existing->username ?: $existing->slug);
                    $quotaText = ($tenant && $tenant->max_customers > 0) 
                        ? ($isFr ? "{$tenant->max_customers} Clients" : "{$tenant->max_customers} Pelanggan") 
                        : ($isFr ? "Illimité" : "Unlimited");
                    $packageName = $tenant ? ($tenant->package_name ?? $existing->package?->name ?? 'Forfait') : ($existing->package?->name ?? 'Forfait');

                    $adminUser = htmlspecialchars($username ?? '');
                    $reqEmail = htmlspecialchars($existing->email ?? '');
                    $pkgName = htmlspecialchars($packageName ?? '');
                    $quotaShow = htmlspecialchars($quotaText ?? '');

                    if ($isFr) {
                        $approvalText = "<b>INSCRIPTION APPROUVÉE — {$reqName}</b>\n\n"
                            . "┌ {$compName}\n"
                            . "├ Sous-domaine: <code>{$reqSlug}.{$domShow}</code>\n"
                            . "├ E-mail: <code>{$reqEmail}</code>\n"
                            . "├ Identifiant: <code>{$adminUser}</code>\n"
                            . "├ Mot de passe: <code>(défini lors de l'inscription)</code>\n"
                            . "├ Forfait: <code>{$pkgName} ({$quotaShow})</code>\n"
                            . "├ Statut: APPROUVÉ (DÉJÀ TRAITÉ)\n"
                            . "├ Connexion: https://{$reqSlug}.{$domShow}/login\n"
                            . "└ Heure: " . now()->format('d/m/Y H:i:s');
                    } else {
                        $approvalText = "<b>PENDAFTARAN TENANT DISETUJUI — NODERA</b>\n\n"
                            . "┌ {$reqName}\n"
                            . "├ Mitra / ISP: <code>{$compName}</code>\n"
                            . "├ Domain: <code>{$reqSlug}.{$domShow}</code>\n"
                            . "├ Email: <code>{$reqEmail}</code>\n"
                            . "├ Username: <code>{$adminUser}</code>\n"
                            . "├ Password: <code>(sesuai saat pendaftaran)</code>\n"
                            . "├ Paket: <code>{$pkgName} ({$quotaShow})</code>\n"
                            . "├ Status: DISETUJUI (SUDAH DIPROSES)\n"
                            . "├ Login URL: https://{$reqSlug}.{$domShow}/login\n"
                            . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                    }

                    $tenantPhone = preg_replace('/[^0-9]/', '', $existing->phone ?? '');
                    if (str_starts_with($tenantPhone, '0')) {
                        $tenantPhone = '62' . substr($tenantPhone, 1);
                    }
                    $loginUrl = "https://{$reqSlug}.{$domShow}/login";
                    $waText = "Halo {$existing->name} ({$existing->company}),\n\n"
                        . "Pendaftaran instansi ISP Anda di NODERA telah disetujui dan aktif!\n\n"
                        . "Detail Akun Panel Anda:\n"
                        . "• Subdomain / URL: {$loginUrl}\n"
                        . "• Username Admin: {$username}\n"
                        . "• Password: (sesuai saat pendaftaran)\n"
                        . "• Paket: {$packageName}\n\n"
                        . "Silakan login ke panel Anda untuk mulai konfigurasi router MikroTik dan pelanggan. Terima kasih telah bergabung!";

                    $replyMarkup = ['inline_keyboard' => []];
                    if (!empty($tenantPhone)) {
                        $replyMarkup['inline_keyboard'][] = [
                            $telegram->inlineButton('Kirim WhatsApp ke Calon Tenant', null, 'https://wa.me/' . $tenantPhone . '?text=' . rawurlencode($waText))
                        ];
                    }

                    if ($messageId) {
                        $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $approvalText, 'HTML', $replyMarkup);
                        if (!($editRes['ok'] ?? false)) {
                            $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, $replyMarkup);
                        }
                    } else {
                        $telegram->sendMessage((string) $chatId, $approvalText, 'HTML', $replyMarkup, $threadId);
                    }

                    if ($callbackId) {
                        $telegram->answerCallback($callbackId, $isFr ? "Demande déjà approuvée" : "Pendaftaran sudah disetujui sebelumnya", true);
                    }
                } else {
                    if ($isFr) {
                        $rejectText = "<b>INSCRIPTION REFUSÉE — {$reqName}</b>\n\n"
                            . "┌ {$compName}\n"
                            . "├ Sous-domaine: <code>{$reqSlug}</code>\n"
                            . "├ Statut: REFUSÉ\n"
                            . "└ Heure: " . now()->format('d/m/Y H:i:s');
                    } else {
                        $rejectText = "<b>PENDAFTARAN TENANT DITOLAK — NODERA</b>\n\n"
                            . "┌ {$reqName}\n"
                            . "├ Mitra / ISP: <code>{$compName}</code>\n"
                            . "├ Subdomain: <code>{$reqSlug}</code>\n"
                            . "├ Status: DITOLAK\n"
                            . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                    }
                    if ($messageId) {
                        $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $rejectText, 'HTML', ['inline_keyboard' => []]);
                        if (!($editRes['ok'] ?? false)) {
                            $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                        }
                    } else {
                        $telegram->sendMessage((string) $chatId, $rejectText, 'HTML', null, $threadId);
                    }

                    if ($callbackId) {
                        $telegram->answerCallback($callbackId, $isFr ? "Demande déjà rejetée" : "Pendaftaran sudah ditolak sebelumnya", true);
                    }
                }
                return;
            }

            if ($messageId) {
                $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $isFr ? "<b>DEMANDE INTROUVABLE</b>\n\n┌ Demande\n├ Statut: INTROUVABLE\n└ Info: La demande d'inscription `{$slug}` est introuvable." : "<b>PENDAFTARAN TIDAK DITEMUKAN — NODERA</b>\n\n┌ Pendaftaran\n├ Status: TIDAK DITEMUKAN\n└ Info: Data pendaftaran `{$slug}` tidak ditemukan dalam sistem.", 'HTML', ['inline_keyboard' => []]);
                if (!($editRes['ok'] ?? false)) {
                    $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                }
            } else {
                $telegram->sendMessage((string) $chatId, $isFr ? "<b>DEMANDE INTROUVABLE</b>\n\n┌ Demande\n├ Statut: INTROUVABLE\n└ Info: La demande d'inscription `{$slug}` est introuvable." : "<b>PENDAFTARAN TIDAK DITEMUKAN — NODERA</b>\n\n┌ Pendaftaran\n├ Status: TIDAK DITEMUKAN\n└ Info: Data pendaftaran `{$slug}` tidak ditemukan dalam sistem.", 'HTML', null, $threadId);
            }

            if ($callbackId) {
                $telegram->answerCallback($callbackId, $isFr ? "Demande introuvable" : "Pendaftaran tidak ditemukan", true);
            }
        } catch (\Throwable $e) {
            Log::error('handleRegApprove error: ' . $e->getMessage(), ['slug' => $slug, 'trace' => $e->getTraceAsString()]);
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) {
                $telegram->answerCallback($callbackId, "Gagal memproses persetujuan: " . $e->getMessage(), true);
            }
        }
    }

    private function handleRegReject($chatId, $messageId, $slug, $telegram, $threadId = null, $callbackId = null)
    {
        $isFr = app()->getLocale() === 'fr' 
            || env('APP_LOCALE') === 'fr' 
            || config('app.locale') === 'fr'
            || str_contains(request()->getHost(), 'airnetsolution')
            || str_contains(config('app.url'), 'airnetsolution');

        $slug = trim((string) $slug);

        try {
            $req = \App\Models\RegistrationRequest::where('status', 'pending')
                ->where(function ($q) use ($slug) {
                    $q->where('slug', $slug)
                      ->orWhere('id', $slug)
                      ->orWhereRaw('LOWER(slug) = ?', [strtolower($slug)]);
                })->first();

            if ($req) {
                $req->update(['status' => 'rejected']);

                try {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled() && !empty($req->phone)) {
                        $wa->sendRegistrationRejected($req, $isFr ? 'Refusé par l\'administrateur' : 'Ditolak oleh Admin');
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[WebhookController] WhatsApp reject registration notif failed: ' . $e->getMessage());
                }

                $compName = htmlspecialchars($req->company ?? '');
                $reqName = htmlspecialchars($req->name ?? '');
                $reqSlug = htmlspecialchars($req->slug ?? '');
                if ($isFr) {
                    $rejectText = "<b>INSCRIPTION REFUSÉE — {$reqName}</b>\n\n"
                        . "┌ {$compName}\n"
                        . "├ Sous-domaine: <code>{$reqSlug}</code>\n"
                        . "├ Statut: REFUSÉ\n"
                        . "└ Heure: " . now()->format('d/m/Y H:i:s');
                } else {
                    $rejectText = "<b>PENDAFTARAN TENANT DITOLAK — NODERA</b>\n\n"
                        . "┌ {$reqName}\n"
                        . "├ Mitra / ISP: <code>{$compName}</code>\n"
                        . "├ Subdomain: <code>{$reqSlug}</code>\n"
                        . "├ Status: DITOLAK\n"
                        . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                }
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $rejectText, 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                        $telegram->sendMessage((string) $chatId, $rejectText, 'HTML', null, $threadId);
                    }
                } else {
                    $telegram->sendMessage((string) $chatId, $rejectText, 'HTML', null, $threadId);
                }
                if ($callbackId) {
                    $telegram->answerCallback($callbackId, $isFr ? "Inscription rejetée avec succès." : "Pendaftaran berhasil ditolak.", true);
                }
                return;
            }

            $existing = \App\Models\RegistrationRequest::where('slug', $slug)
                ->orWhere('id', $slug)
                ->orWhereRaw('LOWER(slug) = ?', [strtolower($slug)])
                ->first();

            if ($existing) {
                $compName = htmlspecialchars($existing->company ?? '');
                $reqName = htmlspecialchars($existing->name ?? '');
                $reqSlug = htmlspecialchars($existing->slug ?? '');
                if ($isFr) {
                    $rejectText = "<b>INSCRIPTION REFUSÉE — {$reqName}</b>\n\n"
                        . "┌ {$compName}\n"
                        . "├ Sous-domaine: <code>{$reqSlug}</code>\n"
                        . "├ Statut: REFUSÉ\n"
                        . "└ Heure: " . now()->format('d/m/Y H:i:s');
                } else {
                    $rejectText = "<b>PENDAFTARAN TENANT DITOLAK — NODERA</b>\n\n"
                        . "┌ {$reqName}\n"
                        . "├ Mitra / ISP: <code>{$compName}</code>\n"
                        . "├ Subdomain: <code>{$reqSlug}</code>\n"
                        . "├ Status: DITOLAK\n"
                        . "└ Waktu: " . now()->format('d/m/Y H:i:s');
                }
                if ($messageId) {
                    $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $rejectText, 'HTML', ['inline_keyboard' => []]);
                    if (!($editRes['ok'] ?? false)) {
                        $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                        $telegram->sendMessage((string) $chatId, $rejectText, 'HTML', null, $threadId);
                    }
                } else {
                    $telegram->sendMessage((string) $chatId, $rejectText, 'HTML', null, $threadId);
                }
                if ($callbackId) {
                    $telegram->answerCallback($callbackId, $isFr ? "Demande déjà traitée" : "Pendaftaran sudah diproses sebelumnya", true);
                }
                return;
            }

            if ($messageId) {
                $editRes = $telegram->editMessage((string) $chatId, (int) $messageId, $isFr ? "<b>❓ DEMANDE INTROUVABLE</b>\n\n┌ Demande\n├ Statut: INTROUVABLE\n└ Info: La demande d'inscription `{$slug}` est introuvable." : "<b>❓ PENDAFTARAN TIDAK DITEMUKAN — NODERA</b>\n\n┌ Pendaftaran\n├ Status: TIDAK DITEMUKAN\n└ Info: Data pendaftaran `{$slug}` tidak ditemukan dalam sistem.", 'HTML', ['inline_keyboard' => []]);
                if (!($editRes['ok'] ?? false)) {
                    $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
                }
            } else {
                $telegram->sendMessage((string) $chatId, $isFr ? "<b>❓ DEMANDE INTROUVABLE</b>\n\n┌ Demande\n├ Statut: INTROUVABLE\n└ Info: La demande d'inscription `{$slug}` est introuvable." : "<b>❓ PENDAFTARAN TIDAK DITEMUKAN — NODERA</b>\n\n┌ Pendaftaran\n├ Status: TIDAK DITEMUKAN\n└ Info: Data pendaftaran `{$slug}` tidak ditemukan dalam sistem.", 'HTML', null, $threadId);
            }
            if ($callbackId) {
                $telegram->answerCallback($callbackId, $isFr ? "Demande introuvable" : "Data pendaftaran tidak ditemukan", true);
            }
        } catch (\Throwable $e) {
            Log::error('handleRegReject error: ' . $e->getMessage(), ['slug' => $slug, 'trace' => $e->getTraceAsString()]);
            if ($messageId) {
                $telegram->editMessageReplyMarkup((string) $chatId, (int) $messageId, ['inline_keyboard' => []]);
            }
            if ($callbackId) {
                $telegram->answerCallback($callbackId, "Gagal menolak pendaftaran: " . $e->getMessage(), true);
            }
        }
    }

    /**
     * Handle MikroTik interactive menu actions
     */
    private function handleMikrotikAction($chatId, $messageId, $action, $telegram, $mikrotik)
    {
        try {
            switch ($action) {
                case 'ping':
                    if ($mikrotik->isConnected()) {
                        $msg = "🟢 *MIKROTIK ONLINE*\n\nKoneksi berhasil!";
                    } else {
                        $msg = "🔴 *MIKROTIK OFFLINE*\n\nError: " . $mikrotik->getLastError();
                    }
                    break;

                case 'status':
                    if (!$mikrotik->isConnected()) {
                        $msg = "🔴 *MIKROTIK OFFLINE*";
                    } else {
                        $pppoe = count($mikrotik->getActivePppoe());
                        $hotspot = count($mikrotik->getActiveHotspotUsers());

                        $msg = "📊 *STATUS MIKROTIK*\n\n";
                        $msg .= "PPPoE Aktif: *{$pppoe}*\n";
                        $msg .= "Hotspot Aktif: *{$hotspot}*\n";
                        $msg .= "Status: *ONLINE*";
                    }
                    break;

                case 'pppoe':
                    if (!$mikrotik->isConnected()) {
                        $msg = "🔴 *MIKROTIK OFFLINE*";
                    } else {
                        $users = $mikrotik->getActivePppoe();
                        $count = count($users);
                        $msg = "👥 *PPPoE ACTIVE ({$count})*\n\n";

                        // Show top 20
                        foreach (array_slice($users, 0, 20) as $u) {
                            $name = $u['name'] ?? '?';
                            $ip = $u['address'] ?? '?';
                            $msg .= "• `{$name}` ({$ip})\n";
                        }

                        if ($count > 20) $msg .= "\n_...and " . ($count - 20) . " more_";
                    }
                    break;

                case 'hotspot':
                    if (!$mikrotik->isConnected()) {
                        $msg = "🔴 *MIKROTIK OFFLINE*";
                    } else {
                        $users = $mikrotik->getActiveHotspotUsers();
                        $count = count($users);
                        $msg = "🎫 *HOTSPOT ACTIVE ({$count})*\n\n";

                        // Show top 20
                        foreach (array_slice($users, 0, 20) as $u) {
                            $name = $u['user'] ?? '?';
                            $ip = $u['address'] ?? '?';
                            $msg .= "• `{$name}` ({$ip})\n";
                        }

                        if ($count > 20) $msg .= "\n_...and " . ($count - 20) . " more_";
                    }
                    break;

                default:
                    $msg = "❓ Unknown action: {$action}";
            }
        } catch (\Exception $e) {
            $msg = "⚠️ *ERROR MIKROTIK*\n\n" . $e->getMessage();
        }

        $keyboard = $telegram->inlineKeyboard([
            [$telegram->inlineButton('◀ Kembali', 'menu:mikrotik')]
        ]);

        $telegram->editMessage($chatId, $messageId, $msg, 'Markdown', $keyboard);
    }

    /**
     * Show different menu based on selection
     */
    private function showTelegramMenu($chatId, $messageId, $menu, $telegram, $mikrotik)
    {
        switch ($menu) {
            case 'voucher':
                $this->showVoucherProfiles($chatId, $messageId, $telegram, $mikrotik);
                break;

            case 'harga':
                $this->showPriceList($chatId, $messageId, $telegram, $mikrotik);
                break;

            case 'mikrotik':
                $this->showMikrotikMenu($chatId, $messageId, $telegram);
                break;

            case 'billing':
                $this->showBillingMenu($chatId, $messageId, $telegram);
                break;

            case 'help':
                $this->sendTelegramHelp($chatId, $telegram, true);
                break;
        }
    }

    /**
     * Show main menu
     */
    private function showMainMenu($chatId, $messageId, $telegram)
    {
        $keyboard = $telegram->inlineKeyboard([
            [
                $telegram->inlineButton(' Generate Voucher', 'menu:voucher'),
                $telegram->inlineButton(' Harga Paket', 'menu:harga')
            ],
            [
                $telegram->inlineButton(' MikroTik Menu', 'menu:mikrotik'),
                $telegram->inlineButton(' Help', 'menu:help')
            ]
        ]);

        $msg = "🤖 *NODERA BOT SUPPORT*\n\n";
        $msg .= "Pilih menu yang tersedia:";

        $telegram->editMessage($chatId, $messageId, $msg, 'Markdown', $keyboard);
    }

    /**
     * Show voucher profiles for selection
     */
    private function showVoucherProfiles($chatId, $messageId, $telegram, $mikrotik)
    {
        try {
            $profiles = $mikrotik->getHotspotProfiles();

            $buttons = [];
            foreach (array_slice($profiles, 0, 8) as $profile) {
                $name = $profile['name'] ?? 'unknown';
                $buttons[] = [$telegram->inlineButton(" {$name}", "gen_voucher:{$name}")];
            }

            $buttons[] = [$telegram->inlineButton('◀ Kembali', 'back:main')];

            $keyboard = $telegram->inlineKeyboard($buttons);

            $msg = "🎫 *PILIH PROFIL VOUCHER*\n\n";
            $msg .= "Klik profile untuk generate voucher:";

            $telegram->editMessage($chatId, $messageId, $msg, 'Markdown', $keyboard);
        } catch (\Exception $e) {
            $telegram->sendMessage($chatId, "Error: " . $e->getMessage());
        }
    }

    /**
     * Generate voucher from callback
     */
    private function generateVoucherInteractive($chatId, $profile, $telegram, $mikrotik)
    {
        // Fetch profile info from MikroTik
        $profileInfo = $mikrotik->getHotspotProfileInfo($profile);

        // Generate 5-digit random number (10000-99999)
        $username = (string) rand(10000, 99999);
        $password = $username;
        $comment = 'vc-gembok-tele';

        $result = $mikrotik->addHotspotUser($username, $password, $profile, '24h', $comment);

        if ($result) {
            $msg = "🎫 *VOUCHER CREATED*\n\n";
            $msg .= " Username: `{$username}`\n";
            $msg .= " Password: `{$password}`\n";
            $msg .= " Profile: `{$profile}`\n";

            // Show price and duration from profile if available
            if ($profileInfo) {
                if (!empty($profileInfo['price'])) {
                    $msg .= " Harga: Rp " . number_format($profileInfo['price'], 0, ',', '.') . "\n";
                }
                if (!empty($profileInfo['duration'])) {
                    $msg .= " Durasi: {$profileInfo['duration']}\n";
                }
            }

            $msg .= "\nVoucher siap digunakan!";

            $keyboard = $telegram->inlineKeyboard([
                [$telegram->inlineButton(' Generate Lagi', "menu:voucher")],
                [$telegram->inlineButton(' Main Menu', 'back:main')]
            ]);

            $telegram->sendMessage($chatId, $msg, 'Markdown', $keyboard);
        } else {
            $telegram->sendMessage($chatId, "❌ *GAGAL*\n\n" . $mikrotik->getLastError());
        }
    }

    /**
     * Show price list
     */
    private function showPriceList($chatId, $messageId, $telegram, $mikrotik)
    {
        try {
            $packages = DB::table('packages')->get();

            $msg = "💰 *DAFTAR HARGA PAKET*\n\n";
            foreach ($packages as $pkg) {
                $msg .= " *{$pkg->name}*\n";
                $msg .= " Rp " . number_format($pkg->price, 0, ',', '.') . "\n";
                if ($pkg->description) {
                    $msg .= " {$pkg->description}\n";
                }
                $msg .= "\n";
            }

            $keyboard = $telegram->inlineKeyboard([
                [$telegram->inlineButton('◀ Kembali', 'back:main')]
            ]);

            $telegram->editMessage($chatId, $messageId, $msg, 'Markdown', $keyboard);
        } catch (\Exception $e) {
            $telegram->sendMessage($chatId, "Error: " . $e->getMessage());
        }
    }

    /**
     * Show MikroTik menu
     */
    private function showMikrotikMenu($chatId, $messageId, $telegram)
    {
        $keyboard = $telegram->inlineKeyboard([
            [
                $telegram->inlineButton(' PING', 'mik:ping'),
                $telegram->inlineButton(' STATUS', 'mik:status')
            ],
            [
                $telegram->inlineButton(' PPPoE Active', 'mik:pppoe'),
                $telegram->inlineButton(' Hotspot Active', 'mik:hotspot')
            ],
            [
                $telegram->inlineButton('◀ Kembali', 'back:main')
            ]
        ]);

        $msg = "🤖 *NODERA BOT SUPPORT*\n\n";
        $msg .= "Pilih menu monitoring:";

        $telegram->editMessage($chatId, $messageId, $msg, 'Markdown', $keyboard);
    }

    /**
     * Show Billing menu
     */
    private function showBillingMenu($chatId, $messageId, $telegram)
    {
        $keyboard = $telegram->inlineKeyboard([
            [
                $telegram->inlineButton(' TAGIHAN', 'cmd:TAGIHAN'),
                $telegram->inlineButton(' INVOICE', 'cmd:INVOICE')
            ],
            [
                $telegram->inlineButton(' AKTIF', 'cmd:AKTIF'),
                $telegram->inlineButton(' NONAKTIF', 'cmd:NONAKTIF')
            ],
            [
                $telegram->inlineButton('◀ Kembali', 'back:main')
            ]
        ]);

        $msg = "💳 *BILLING MENU*\n\n";
        $msg .= "Quick Actions:\n";
        $msg .= "• TAGIHAN - List overdue\n";
        $msg .= "• INVOICE - Generate monthly\n";
        $msg .= "• AKTIF - List active customers\n";
        $msg .= "• NONAKTIF - List isolated\n\n";
        $msg .= "Atau ketik perintah:\n";
        $msg .= "• CUSTOMER [nama]\n";
        $msg .= "• ISOLIR [nama]\n";
        $msg .= "• BAYAR [nama]";

        $telegram->editMessage($chatId, $messageId, $msg, 'Markdown', $keyboard);
    }

    /**
     * Send help message
     */
    private function sendTelegramHelp($chatId, $telegram, $isAdmin)
    {
        $msg = "📖 *BANTUAN NODERA BOT SUPPORT*\n\n";
        $msg .= "━━━━━━━━━━━━━━━━\n\n";
        $msg .= "*PERINTAH UMUM:*\n\n";
        $msg .= " `PING` - Test koneksi MikroTik\n";
        $msg .= " `STATUS` - Cek status MikroTik\n";
        $msg .= " `VOUCHER [profile]` - Generate voucher\n";
        $msg .= "   Contoh: `VOUCHER 3k`\n\n";

        if ($isAdmin) {
            $msg .= "*PERINTAH INFO & LAPORAN:*\n\n";
            $msg .= " `INFO` - Info sistem lengkap\n";
            $msg .= "   (Customer, Revenue, MikroTik)\n\n";

            $msg .= " `LAPORAN` - Laporan harian\n";
            $msg .= "   (Pembayaran, Customer baru)\n\n";

            $msg .= "*PERINTAH ADMIN MIKROTIK:*\n\n";
            $msg .= " `TAMBAH [user] [pass] [profile]`\n";
            $msg .= "   Tambah PPPoE user baru\n";
            $msg .= "   Contoh: `TAMBAH user01 pass123 20Mbps`\n\n";

            $msg .= " `EDIT [user] [profile]`\n";
            $msg .= "   Update profile PPPoE user\n";
            $msg .= "   Contoh: `EDIT user01 50Mbps`\n\n";

            $msg .= " `HAPUS [user]`\n";
            $msg .= "   Hapus PPPoE user\n";
            $msg .= "   Contoh: `HAPUS user01`\n\n";

            $msg .= " `CARI [user]`\n";
            $msg .= "   Cari detail PPPoE user\n";
            $msg .= "   Contoh: `CARI user01`\n\n";

            $msg .= " `KICK [user]`\n";
            $msg .= "   Kick user online\n";
            $msg .= "   Contoh: `KICK user01`\n\n";

            $msg .= "*PERINTAH BILLING:*\n\n";
            $msg .= " `CUSTOMER [nama/hp/username]`\n";
            $msg .= "   Cari data customer\n";
            $msg .= "   Contoh: `CUSTOMER Budi`\n\n";

            $msg .= " `ISOLIR [nama/hp]`\n";
            $msg .= "   Isolir customer (non-aktif)\n";
            $msg .= "   Contoh: `ISOLIR Budi`\n\n";

            $msg .= " `UNISOLIR [nama/hp]`\n";
            $msg .= "   Aktifkan customer kembali\n";
            $msg .= "   Contoh: `UNISOLIR Budi`\n\n";

            $msg .= " `BAYAR [nama/hp]`\n";
            $msg .= "   Tandai invoice lunas & aktifkan\n";
            $msg .= "   Contoh: `BAYAR Budi`\n\n";

            $msg .= " `TAGIHAN`\n";
            $msg .= "   List tagihan overdue (10 teratas)\n\n";

            $msg .= " `INVOICE`\n";
            $msg .= "   Generate invoice bulanan\n\n";

            $msg .= " `AKTIF`\n";
            $msg .= "   List customer aktif (15 teratas)\n\n";

            $msg .= " `NONAKTIF`\n";
            $msg .= "   List customer isolir (15 teratas)\n\n";

            $msg .= "*PERINTAH VOUCHER:*\n\n";
            $msg .= " `VCR [username] [profile]`\n";
            $msg .= "   Voucher custom (24 jam)\n";
            $msg .= "   Contoh: `VCR wifi123 3k`\n\n";

            $msg .= " `MEMBER [user] [pass] [profile]`\n";
            $msg .= "   Member permanent (unlimited)\n";
            $msg .= "   Contoh: `MEMBER cafe01 pass123 3k`\n\n";
        }

        $msg .= "━━━━━━━━━━━━━━━━\n";
        $msg .= " Gunakan menu interaktif dengan /start";

        $telegram->sendMessage($chatId, $msg);
    }

    /**
     * Check if chat ID is admin
     */
    private function isTelegramAdmin($chatId)
    {
        // Tenant keys from settings, superadmin/platform keys from env.
        $adminIdsString = Setting::apiValue('TELEGRAM_ADMIN_CHAT_IDS', '');
        $adminChatIds = explode(',', $adminIdsString);
        return in_array((string) $chatId, array_map('trim', $adminChatIds));
    }

    /**
     * WhatsApp Webhook (inbound)
     *
     * - Handshake verification (hub.challenge) via WHATSAPP_VERIFY_TOKEN.
     * - Authenticates inbound calls via WHATSAPP_TOKEN (payload `token`
     *   or `X-Webhook-Token` header).
     * - Supports simple customer self-service commands (CEK / STATUS).
     */
    public function whatsapp(Request $request)
    {
        // 1) Webhook verification handshake (Meta/Fonnte-style subscribe)
        if ($request->has('hub_mode') && $request->has('hub_verify_token')) {
            $expected = Setting::apiValue('WHATSAPP_VERIFY_TOKEN', '');
            $received = (string) $request->input('hub_verify_token');
            if ($request->input('hub_mode') === 'subscribe' && $expected && hash_equals($expected, $received)) {
                return response($request->input('hub_challenge', 'ok'));
            }
            Log::warning('WhatsApp webhook verification failed');
            return response()->json(['error' => 'Invalid verify token'], 403);
        }

        $json = $request->getContent();
        $data = json_decode($json, true) ?: $request->all();

        // 2) Authenticate with WHATSAPP_TOKEN (Fail-Closed if configured)
        $expectedToken = Setting::apiValue('WHATSAPP_TOKEN', '') ?: env('WHATSAPP_WEBHOOK_SECRET', '');
        if (!empty($expectedToken)) {
            $receivedToken = (string) ($data['token'] ?? $request->header('X-Webhook-Token', '') ?: $request->header('X-Hub-Signature-256', ''));
            if (empty($receivedToken) || !hash_equals($expectedToken, $receivedToken)) {
                Log::warning('WhatsApp webhook rejected: invalid or missing token');
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // 3) Interpret common inbound gateway shapes (Fonnte, Wablas, Starsender, Kirimi, MPWA, Meta WABA, Baileys)
        $payload = $data['data'] ?? $data;
        $message = $data['message'] ?? $data['text'] ?? $data['pesan'] ?? ($payload['message'] ?? ($payload['text'] ?? null));
        $from = $data['from'] ?? $data['sender'] ?? $data['phone'] ?? $data['nomor'] ?? ($payload['from'] ?? ($payload['sender'] ?? ($payload['phone'] ?? null)));
        $senderName = $data['name'] ?? $data['pushName'] ?? ($payload['name'] ?? ($payload['pushName'] ?? ''));

        // Meta Cloud API / WABA shape fallback
        if (empty($message) && isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $metaMsg = $data['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $metaMsg['from'] ?? $from;
            $message = $metaMsg['text']['body'] ?? ($metaMsg['caption'] ?? '');
            $senderName = $data['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? $senderName;
        }

        $this->logToDb('whatsapp', $json, 200, 'Received');

        if (is_string($message) && is_string($from) && !empty(trim($message))) {
            $this->handleInboundWhatsapp($from, trim($message), $senderName);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process a WhatsApp inbound message: full interactive customer self-service.
     */
    private function handleInboundWhatsapp(string $from, string $text, $senderName = '')
    {
        $phone = preg_replace('/\D+/', '', $from);
        $normalizedPhone = $phone;
        if (str_starts_with($phone, '62')) {
            $normalizedPhone = '0' . substr($phone, 2);
        }

        $last9 = substr($phone, -9);
        $last8 = substr($phone, -8);

        $customer = \App\Models\Customer::withoutGlobalScopes()
            ->where(function ($q) use ($phone, $normalizedPhone, $last9, $last8) {
                $q->where('phone', $phone)
                  ->orWhere('phone', $normalizedPhone)
                  ->orWhere('phone', 'like', '%' . $last9)
                  ->orWhere('phone', 'like', '%' . $last8);
            })
            ->first();

        if (!$customer) {
            // Unregistered sender reply
            try {
                $wa = new WhatsappService(null, true);
                if ($wa->isEnabled()) {
                    $wa->sendMessage($from, "Halo! Nomor WhatsApp Anda (*{$from}*) belum terdaftar sebagai pelanggan aktif kami.\n\nUntuk info pendaftaran paket internet baru atau bantuan teknisi, silakan hubungi Customer Service kami.\n\nTerima kasih.");
                }
            } catch (\Throwable $e) {}
            return;
        }

        $tenantId = $customer->tenant_id ?? 1;
        $tenant = \App\Models\Tenant::find($tenantId);
        $tenantName = $tenant?->company_name ?: ($tenant?->name ?: 'Nodera Internet');
        $portalUrl = $tenant ? 'https://' . $tenant->slug . '.dgtlnetsolution.com/portal' : 'https://digitalnet.dgtlnetsolution.com/portal';

        $wa = new WhatsappService($tenantId);
        if (!$wa->isEnabled()) {
            $wa = new WhatsappService(null, true);
        }

        $textTrim = trim($text);
        $commandUpper = strtoupper($textTrim);

        // 1. Command GANTIWIFI: !gantiwifi <SSID> <PASSWORD>
        if (preg_match('/^!?gantiwifi\s+([^\s]+)\s+(.+)$/i', $textTrim, $matches)) {
            $newSsid = trim($matches[1]);
            $newPassword = trim($matches[2]);

            $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
            if (!$ontDevice && !empty($customer->pppoe_username)) {
                $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($customer) {
                        $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                          ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                    })->first();
            }

            if (!$ontDevice) {
                $wa->sendMessage($from, "Mohon maaf *{$customer->name}*, modem rumah Anda belum terhubung ke sistem manajemen otomasi TR-069 kami. Silakan hubungi teknisi untuk bantuan.");
                return;
            }

            if (strlen($newPassword) < 8) {
                $wa->sendMessage($from, "⚠️ Password WiFi minimal 8 karakter.\n\nContoh format yang benar:\n`!gantiwifi {$newSsid} rahasia123`");
                return;
            }

            try {
                /** @var \App\Services\GenieAcsService $acsService */
                $acsService = app(\App\Services\GenieAcsService::class);
                $acsService->setWifiConfig($ontDevice->serial_number, $newSsid, $newPassword, $tenantId);
                $ontDevice->update([
                    'wifi_ssid'     => $newSsid,
                    'wifi_password' => $newPassword,
                ]);

                $reply = "✅ *Permintaan Ganti WiFi Terkirim!*\n\n";
                $reply .= "• Nama WiFi (SSID): *{$newSsid}*\n";
                $reply .= "• Password Baru: *{$newPassword}*\n\n";
                $reply .= "Pengaturan baru sedang di-push ke modem Anda dan akan aktif dalam beberapa detik. Perangkat HP/laptop Anda mungkin perlu terhubung kembali menggunakan password baru.";
                $wa->sendMessage($from, $reply);
            } catch (\Throwable $e) {
                $wa->sendMessage($from, "Gagal mengubah WiFi modem: " . $e->getMessage());
            }
            return;
        }

        // 2. Command REBOOT: !reboot / reboot / !restart / restart
        if (preg_match('/^!?(REBOOT|RESTART)$/i', $commandUpper)) {
            $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
            if (!$ontDevice && !empty($customer->pppoe_username)) {
                $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($customer) {
                        $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                          ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                    })->first();
            }

            if (!$ontDevice) {
                $wa->sendMessage($from, "Mohon maaf *{$customer->name}*, perangkat modem rumah Anda tidak ditemukan di sistem otomasi TR-069.");
                return;
            }

            try {
                /** @var \App\Services\GenieAcsService $acsService */
                $acsService = app(\App\Services\GenieAcsService::class);
                $acsService->rebootDevice($ontDevice->serial_number, $tenantId);

                $reply = "🔄 *Perintah Restart Terkirim!*\n\n";
                $reply .= "Modem rumah Anda (*{$ontDevice->serial_number}*) sedang menyala ulang (reboot).\n";
                $reply .= "Mohon tunggu 1-2 menit hingga lampu indikator PON/Internet kembali menyala stabil.";
                $wa->sendMessage($from, $reply);
            } catch (\Throwable $e) {
                $wa->sendMessage($from, "Gagal restart modem: " . $e->getMessage());
            }
            return;
        }

        // 3. Command STATUS: !status / status / cek status / !cek
        if (preg_match('/^!?(STATUS|CEK STATUS|CEK)$/i', $commandUpper)) {
            $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
            if (!$ontDevice && !empty($customer->pppoe_username)) {
                $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($customer) {
                        $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                          ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                    })->first();
            }

            $accStatus = $customer->status === 'active' ? '🟢 Aktif' : '🔴 Terisolir';
            $packageName = $customer->package_name ?: ($customer->profile ?: 'Paket Internet');

            $reply = "📊 *STATUS LAYANAN INTERNET*\n\n";
            $reply .= "• Pelanggan: *{$customer->name}*\n";
            $reply .= "• ID/User: *{$customer->pppoe_username}*\n";
            $reply .= "• Paket: *{$packageName}*\n";
            $reply .= "• Status Akun: *{$accStatus}*\n\n";

            if ($ontDevice) {
                $rxVal = (float) $ontDevice->rx_power;
                $rxLabel = '⚪ Unknown';
                if ($rxVal !== 0.0) {
                    if ($rxVal >= -23.5 && $rxVal <= -14.0) {
                        $rxLabel = "🟢 {$ontDevice->rx_power} dBm (Sinyal Prima)";
                    } elseif ($rxVal < -23.5 && $rxVal >= -27.0) {
                        $rxLabel = "🟡 {$ontDevice->rx_power} dBm (Waspada)";
                    } else {
                        $rxLabel = "🔴 {$ontDevice->rx_power} dBm (Drop / Tekuk)";
                    }
                } else {
                    $rxLabel = $ontDevice->rx_power ? "{$ontDevice->rx_power} dBm" : '-';
                }

                $modemOnline = $ontDevice->status === 'online' ? '🟢 Online (TR-069 Aktif)' : '🔴 Offline';
                $reply .= "📡 *Data Modem ONT Rumah:*\n";
                $reply .= "• Serial Number: *{$ontDevice->serial_number}*\n";
                $reply .= "• Status Modem: *{$modemOnline}*\n";
                $reply .= "• Redaman Optik (Rx): *{$rxLabel}*\n";
                $reply .= "• Nama WiFi (SSID): *{$ontDevice->wifi_ssid}*\n";
                $reply .= "• Perangkat Konek: *{$ontDevice->connected_devices_count} perangkat*\n";
                if ($ontDevice->temperature) {
                    $reply .= "• Suhu Modem: *{$ontDevice->temperature} °C*\n";
                }
                $reply .= "\n";
            }

            $reply .= "🌐 *Portal Mandiri:* {$portalUrl}";
            $wa->sendMessage($from, $reply);
            return;
        }

        // 4. Command TAGIHAN / INVOICE: !tagihan / tagihan / invoice / !invoice / cek tagihan / bayar
        if (preg_match('/^!?(INVOICE|TAGIHAN|BILL|TAGIH|BAYAR|CEK TAGIHAN|CEK INVOICE)$/i', $commandUpper)) {
            $unpaid = DB::table('invoices')
                ->where('customer_id', $customer->id)
                ->where('paid', 0)
                ->orderBy('due_date')
                ->get();

            if ($unpaid->isEmpty()) {
                $wa->sendMessage($from, "Halo *{$customer->name}*!\n\nAlhamdulillah semua tagihan internet Anda di *{$tenantName}* telah *LUNAS*. Terima kasih atas kerjasamanya! 🚀");
                return;
            }

            $totalUnpaid = $unpaid->sum('amount');
            $msg = "🧾 *RINCIAN TAGIHAN INTERNET*\n\n";
            $msg .= "Halo *{$customer->name}*, berikut tagihan Anda yang belum dibayar di *{$tenantName}*:\n\n";

            foreach ($unpaid as $inv) {
                $dueDate = date('d M Y', strtotime($inv->due_date));
                $msg .= "• *{$inv->invoice_number}*\n";
                $msg .= "   Nominal: *Rp " . number_format($inv->amount, 0, ',', '.') . "*\n";
                $msg .= "   Jatuh Tempo: *{$dueDate}*\n\n";
            }

            $msg .= "💵 *Total Tagihan:* Rp " . number_format($totalUnpaid, 0, ',', '.') . "\n\n";
            $msg .= "💳 *Bayar Online di Portal Mandiri:*\n{$portalUrl}\n\n";
            $msg .= "Silakan lakukan pembayaran sebelum tanggal jatuh tempo agar koneksi internet tetap lancar.";

            $wa->sendMessage($from, rtrim($msg));
            return;
        }

        // 5. Default / Menu Bantuan: !menu / menu / !help / help / !bantuan / bantuan / etc.
        $menuMsg = "Halo *{$customer->name}*! 👋\n";
        $menuMsg .= "Selamat datang di Layanan Otomatis WhatsApp *{$tenantName}*.\n\n";
        $menuMsg .= "Berikut daftar perintah yang dapat Anda ketik:\n\n";
        $menuMsg .= "1️⃣ *!status*\n";
        $menuMsg .= "Cek status koneksi internet, sinyal redaman optik modem, dan jumlah perangkat yang terhubung.\n\n";
        $menuMsg .= "2️⃣ *!tagihan*\n";
        $menuMsg .= "Cek rincian tagihan belum dibayar & link pembayaran online.\n\n";
        $menuMsg .= "3️⃣ *!gantiwifi <Nama_WiFi> <Password_Baru>*\n";
        $menuMsg .= "Ganti nama WiFi (SSID) dan password router rumah secara instan.\n";
        $menuMsg .= "Contoh: `!gantiwifi WiFiRumah rahasia123`\n\n";
        $menuMsg .= "4️⃣ *!reboot*\n";
        $menuMsg .= "Restart modem dari jauh jika koneksi internet terasa lambat.\n\n";
        $menuMsg .= "5️⃣ *!bantuan*\n";
        $menuMsg .= "Menampilkan daftar menu perintah ini.\n\n";
        $menuMsg .= "🌐 *Portal Pelanggan:* {$portalUrl}\n\n";
        $menuMsg .= "_Ketik salah satu perintah di atas untuk memulai._";

        $wa->sendMessage($from, $menuMsg);
    }

    /**
     * Midtrans Webhook
     *
     * Verifies the SNAP notification signature_key before processing so a
     * forged `settlement` notification cannot mark an invoice as paid.
     * signature_key = sha512(order_id + status_code + gross_amount + server_key)
     */
    public function midtrans(Request $request)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->logToDb('midtrans', $json, 400, 'Invalid JSON');
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $orderId = (string) ($data['order_id'] ?? '');
        $signature = (string) ($data['signature_key'] ?? '');
        $statusCode = (string) ($data['status_code'] ?? '');
        $grossAmount = (string) ($data['gross_amount'] ?? '');

        // 0. Check & delegate to NoderaPayEngine (Universal Gateway, Tenant Registration REG-*, & Topup TOP/*)
        try {
            /** @var \App\Services\NoderaPayEngineService $npEngine */
            $npEngine = app(\App\Services\NoderaPayEngineService::class);
            $npResult = $npEngine->handleMidtransWebhook($data);
            if ($npResult['success']) {
                $this->logToDb('midtrans', $json, 200, $npResult['message']);
                return response()->json(['success' => true, 'message' => $npResult['message']]);
            }
        } catch (\Throwable $e) {
            Log::info('[Midtrans] NoderaPayEngine delegation note: ' . $e->getMessage());
        }

        // Resolve the tenant that owns this order for its server key.
        $tenantId = null;
        if (preg_match('/^INV-(\d+)-/', $orderId, $m)) {
            $tenantId = DB::table('invoices')->where('id', (int) $m[1])->value('tenant_id');
        }

        $midtransService = new \App\Services\MidtransService($tenantId ? (string) $tenantId : null);

        if (!$midtransService->isConfigured()) {
            Log::warning('[Midtrans] Server key not configured for order ' . $orderId);
            return response()->json(['success' => false, 'message' => 'Not configured'], 503);
        }

        if (!$midtransService->verifySignature($orderId, $statusCode, $grossAmount, $signature)) {
            Log::warning('[Midtrans] Invalid signature for ' . $orderId);
            $this->logToDb('midtrans', $json, 401, 'Invalid signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        // Safe: signature verified — process through the unified flow.
        $result = app(PaymentService::class)->handleCallback('midtrans', $data);

        $message = $result['message'] ?? 'Processed';
        $this->logToDb('midtrans', $json, $result['success'] ? 200 : 400, $message);

        // Legacy business flow (WhatsApp receipt + un-isolate on settlement).
        if ($result['success'] && ($data['transaction_status'] ?? '') === 'settlement') {
            $tx = $result['transaction'];
            $invoice = $tx?->invoice;
            if ($invoice) {
                $this->handlePaidInvoice((object) [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id'    => $invoice->customer_id,
                    'amount'         => $invoice->amount,
                ], $data);
            }
        }

        return response()->json(['success' => $result['success'] ?? false]);
    }

    /**
     * Log webhook activity to database
     */
    private function logToDb($source, $payload, $code, $msg)
    {
        try {
            DB::table('webhook_logs')->insert([
                'source' => $source,
                'payload' => $payload,
                'response_code' => $code,
                'response_message' => $msg,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log webhook to DB: ' . $e->getMessage());
        }
    }

/**
     * Generic unique-amount payment confirmation webhook.
     *
     * Customer transfers a nominal = invoice amount + unique code (1-999).
     * This endpoint receives the amount and matches it to the pending invoice,
     * marking it paid.
     */
    public function nominalUnique(Request $request)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->logToDb('nominal-unik', $json, 400, 'Invalid JSON');
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $service = app(\App\Services\NominalUnikService::class);
        $secret = (string) ($data['secret'] ?? $request->header('X-Nominal-Secret', ''));
        if (!$service->verifySecret($secret)) {
            $this->logToDb('nominal-unik', $json, 401, 'Invalid secret');
            return response()->json(['success' => false, 'message' => 'Invalid secret'], 401);
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->logToDb('nominal-unik', $json, 422, 'Invalid amount');
            return response()->json(['success' => false, 'message' => 'Invalid amount'], 422);
        }

        $invoice = $service->matchAmount($amount);
        if (!$invoice) {
            $this->logToDb('nominal-unik', $json, 404, 'No matching invoice for amount');
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $lockKey = "webhook_nominal_unik_" . md5((string)$amount . '_' . $invoice->id);
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 15);
        if (!$lock->get()) {
            \Illuminate\Support\Facades\Log::warning("[NominalUnik Webhook] Duplicate callback ignored for invoice {$invoice->invoice_number}");
            return response()->json(['success' => true, 'message' => 'Duplicate callback ignored']);
        }

        try {
            \Illuminate\Support\Facades\DB::table('invoices')->where('id', $invoice->id)->update([
                'paid' => true,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'Bank Transfer (Nominal Unik)',
                'payment_ref' => 'nominal-unik',
                'processed_by' => 'Webhook Nominal Unik',
            ]);

            $this->handlePaidInvoice((object) [
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'amount' => $invoice->amount,
            ], $data);

            $this->logToDb('nominal-unik', $json, 200, 'Invoice ' . $invoice->invoice_number . ' marked PAID');
            return response()->json(['success' => true, 'invoice' => $invoice->invoice_number]);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * QRIS Automated Webhook Callback (GoPay, GoBiz, MacroDroid, HTTP Shortcuts, Android Notif Forwarder).
     */
    public function qrisWebhook(Request $request)
    {
        $json = $request->getContent();
        $data = json_decode($json, true) ?: $request->all();

        Log::info('[Webhook QRIS] Received payload: ' . json_encode($data));

        $service = app(\App\Services\NominalUnikService::class);
        $secret = (string) (
            $data['secret']
            ?? $request->bearerToken()
            ?? $request->header('X-Qris-Secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->header('X-Nominal-Secret')
            ?? $request->query('secret', '')
        );

        $tenantId = isset($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        if (!$service->verifySecret($secret, $tenantId)) {
            $this->logToDb('qris', $json, 401, 'Invalid secret token');
            return response()->json(['success' => false, 'message' => 'Unauthorized: Invalid secret token'], 401);
        }

        $paymentService = app(\App\Services\PaymentService::class);
        $result = $paymentService->handleCallback('qris', $data);

        if (!$result['success']) {
            $this->logToDb('qris', $json, 422, $result['message']);
            return response()->json($result, 422);
        }

        $this->logToDb('qris', $json, 200, 'QRIS Payment Processed: ' . ($result['invoice'] ?? 'OK'));

        return response()->json([
            'success' => true,
            'message' => 'QRIS payment settled successfully',
            'invoice' => $result['invoice'] ?? null,
            'amount'  => $result['amount'] ?? null,
        ]);
    }

    /**
     * Xendit Payment Webhook.
     * Verified using the x-callback-token header.
     */
    public function xendit(Request $request)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->logToDb('xendit', $json, 400, 'Invalid JSON');
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $externalId = (string) ($data['external_id'] ?? $data['merchant_ref'] ?? '');
        $tenantId = null;
        if (preg_match('/^INV-(\d+)-/', $externalId, $m)) {
            $tenantId = DB::table('invoices')->where('id', (int) $m[1])->value('tenant_id');
        }

        $xendit = new \App\Services\XenditService($tenantId ? (string) $tenantId : null);
        if (!$xendit->verifyWebhook((string) $request->header('x-callback-token', ''))) {
            Log::warning('[Xendit] Invalid callback token for ' . $externalId);
            $this->logToDb('xendit', $json, 401, 'Invalid token');
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        $result = app(\App\Services\PaymentService::class)->handleCallback('xendit', $data);

        if ($result['success'] && ($data['status'] ?? '') === 'PAID') {
            $tx = $result['transaction'];
            $invoice = $tx?->invoice;
            if ($invoice) {
                $this->handlePaidInvoice((object) [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id'    => $invoice->customer_id,
                    'amount'         => $invoice->amount,
                ], $data);
            }
        }

        $this->logToDb('xendit', $json, $result['success'] ? 200 : 400, $result['message'] ?? 'Processed');
        return response()->json(['success' => $result['success'] ?? false]);
    }

    /**
     * Duitku Payment Webhook.
     * Verified using md5 signature.
     */
    public function duitku(Request $request)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->logToDb('duitku', $json, 400, 'Invalid JSON');
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $merchantOrderId = (string) ($data['merchantOrderId'] ?? '');
        $tenantId = null;
        if (preg_match('/^INV-(\d+)-/', $merchantOrderId, $m)) {
            $tenantId = DB::table('invoices')->where('id', (int) $m[1])->value('tenant_id');
        }

        $duitku = new \App\Services\DuitkuService($tenantId ? (string) $tenantId : null);
        if (!$duitku->verifyWebhook($data)) {
            Log::warning('[Duitku] Invalid signature for ' . $merchantOrderId);
            $this->logToDb('duitku', $json, 401, 'Invalid signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $result = app(\App\Services\PaymentService::class)->handleCallback('duitku', $data);

        if ($result['success'] && (string) ($data['resultCode'] ?? '') === '00') {
            $tx = $result['transaction'];
            $invoice = $tx?->invoice;
            if ($invoice) {
                $this->handlePaidInvoice((object) [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id'    => $invoice->customer_id,
                    'amount'         => $invoice->amount,
                ], $data);
            }
        }

        $this->logToDb('duitku', $json, $result['success'] ? 200 : 400, $result['message'] ?? 'Processed');
        return response()->json(['success' => $result['success'] ?? false]);
    }

    /**
     * CinetPay Payment Webhook Handler.
     */
    public function cinetpay(Request $request)
    {
        $json = $request->getContent();
        Log::info('[CinetPay Webhook] Received payload: ' . $json);
        $this->logToDb('cinetpay', $json, 200, 'Received');
        return response()->json(['status' => 'ACCEPTED', 'message' => 'Notification received']);
    }

    /**
     * Wave Payment Webhook Handler.
     */
    public function wave(Request $request)
    {
        $json = $request->getContent();
        Log::info('[Wave Webhook] Received payload: ' . $json);
        $this->logToDb('wave', $json, 200, 'Received');
        return response()->json(['status' => 'ok', 'message' => 'Webhook received']);
    }

    /**
     * PayTech Payment Webhook Handler.
     */
    public function paytech(Request $request)
    {
        $json = $request->getContent();
        Log::info('[PayTech Webhook] Received payload: ' . $json);
        $this->logToDb('paytech', $json, 200, 'Received');
        return response()->json(['status' => 'success', 'message' => 'Payment notification processed']);
    }

    /**
     * DOKU (Jokul) Payment Notification Webhook Handler.
     */
    public function doku(Request $request)
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true) ?: $request->all();

        Log::info('[Doku Webhook] Received payload: ' . $raw);

        $order = $payload['order'] ?? [];
        $orderId = (string) ($order['invoice_number'] ?? ($payload['invoice_number'] ?? ''));

        // Identify tenant from order ID: INV-{invoiceId}-{transactionId}
        $tenantId = null;
        if (!empty($orderId)) {
            $parts = explode('-', $orderId);
            $transactionId = (int) end($parts);
            $tx = \App\Models\PaymentTransaction::find($transactionId);
            if ($tx) {
                $tenantId = $tx->tenant_id ?: $tx->invoice?->tenant_id;
            }
        }

        // Verify DOKU signature
        $dokuService = new \App\Services\DokuService($tenantId ? (string) $tenantId : null);
        $headers = $request->headers->all();
        $requestPath = $request->getPathInfo();

        if ($dokuService->isConfigured()) {
            $isValid = $dokuService->verifyWebhook($raw, $headers, $requestPath);
            if (!$isValid) {
                Log::warning('[Doku Webhook] Invalid signature from ' . $request->ip());
                $this->logToDb('doku', $raw, 400, 'Invalid signature');
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }
        }

        $result = app(\App\Services\PaymentService::class)->handleCallback('doku', $payload);
        $this->logToDb('doku', $raw, 200, $result['message'] ?? 'OK');

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification processed successfully',
            'data'    => $result,
        ]);
    }

    /**
     * Universal Gateway Webhook Dispatcher
     * Handles /api/payment/callback/{gateway}, /api/callback/{gateway}, etc.
     */
    public function universalPaymentCallback(Request $request, string $gateway)
    {
        $gw = strtolower(trim($gateway));
        if ($gw === 'deploy') {
            return app(\App\Http\Controllers\Api\DeployWebhookController::class)->handle($request);
        }
        return match ($gw) {
            'noderapay' => $this->noderapay($request),
            'wijayapay' => $this->wijayapay($request),
            'tripay' => $this->payment($request),
            'midtrans' => $this->midtrans($request),
            'xendit' => $this->xendit($request),
            'duitku' => $this->duitku($request),
            'doku' => $this->doku($request),
            'cinetpay' => $this->cinetpay($request),
            'wave' => $this->wave($request),
            'paytech' => $this->paytech($request),
            'fedapay' => $this->fedapay($request),
            'qris', 'gobiz' => $this->qrisWebhook($request),
            default => response()->json(app(\App\Services\PaymentService::class)->handleCallback($gw, $request->all())),
        };
    }
    /**
     * FedaPay Webhook Handler.
     */
    public function fedapay(Request $request)
    {
        $json = $request->getContent();
        $data = json_decode($json, true) ?: $request->all();
        \Illuminate\Support\Facades\Log::info('[Webhook FedaPay] Received callback: ' . json_encode($data));

        $secret = env('FEDAPAY_WEBHOOK_SECRET', '');
        $receivedSig = (string) ($request->header('X-FedaPay-Signature') ?: $request->header('X-Signature', ''));

        if (!empty($secret)) {
            if (empty($receivedSig) || !hash_equals($secret, $receivedSig)) {
                \Illuminate\Support\Facades\Log::warning('[Webhook FedaPay] Signature verification failed');
                return response()->json(['status' => 'error', 'message' => 'Unauthorized signature'], 401);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'FedaPay webhook received']);
    }
}
