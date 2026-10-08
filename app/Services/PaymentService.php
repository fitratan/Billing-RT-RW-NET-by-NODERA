<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Services\TripayService;
use App\Services\WijayaPayService;
use App\Services\XenditService;
use App\Services\DuitkuService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Available payment methods.
     */
    const METHODS = ['tripay', 'midtrans', 'manual', 'qris', 'digiflazz', 'wijayapay'];

    /**
     * Create a pending payment transaction.
     */
    public function createTransaction(Invoice $invoice, string $method, ?string $gateway = null): PaymentTransaction
    {
        $data = [
            'invoice_id'  => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'tenant_id'   => $invoice->tenant_id,
            'amount'      => $invoice->amount,
            'method'      => $method,
            'gateway'     => $gateway ?? $method,
            'status'      => 'pending',
        ];

        return PaymentTransaction::create($data);
    }

    /**
     * Process payment via Tripay gateway — generate payment URL.
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, reference: string}
     */
    public function processTripay(Invoice $invoice, string $channel = 'QRIS'): array
    {
        $transaction = $this->createTransaction($invoice, 'tripay', 'tripay');

        $customer = $invoice->customer;
        $tripay = new TripayService();

        $merchantRef = 'INV-' . $invoice->id . '-' . $transaction->id;

        $paymentData = [
            'method'         => $channel,
            'merchant_ref'   => $merchantRef,
            'amount'         => (int) $invoice->amount,
            'customer_name'  => $customer->name ?? 'Pelanggan',
            'customer_email' => $customer->email ?? '',
            'customer_phone' => $customer->phone ?? '',
            'order_items'    => [
                [
                    'sku'    => 'INV-' . $invoice->id,
                    'name'   => 'Pembayaran Tagihan #' . $invoice->invoice_number,
                    'price'  => (int) $invoice->amount,
                    'quantity' => 1,
                ],
            ],
            'return_url' => url('/portal/dashboard'),
        ];

        $result = $tripay->createTransaction($paymentData);

        if (!empty($result['data']['reference'])) {
            $resData = $result['data'] ?? [];
            $vaNumber = $resData['pay_code'] ?? ($resData['pay_url'] ?? null);
            $qrString = $resData['qr_string'] ?? null;
            $qrImage = $resData['qr_url'] ?? null;
            $checkoutUrl = $resData['checkout_url'] ?? null;
            $totalBayar = (float) ($resData['amount'] ?? $invoice->amount);
            $expiredAt = !empty($resData['expired_time']) ? date('c', $resData['expired_time']) : null;
            $instructions = $resData['instructions'] ?? null;

            $transaction->update([
                'gateway_ref' => $result['data']['reference'],
                'notes'       => json_encode([
                    'channel'      => $channel,
                    'va_number'    => $vaNumber,
                    'pay_code'     => $vaNumber,
                    'qr_string'    => $qrString,
                    'qr_image'     => $qrImage,
                    'total_amount' => $totalBayar,
                    'expired_at'   => $expiredAt,
                    'instructions' => $instructions,
                    'checkout_url' => $checkoutUrl,
                    'raw'          => $resData,
                ]),
            ]);
        }

        return [
            'transaction'  => $transaction,
            'payment_url'  => $result['data']['checkout_url'] ?? '',
            'reference'    => $result['data']['reference'] ?? $merchantRef,
        ];
    }

    /**
     * Process payment via Midtrans gateway — generate Snap redirect URL / token.
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, token: string, order_id: string}
     */
    public function processMidtrans(Invoice $invoice, string $method = 'midtrans'): array
    {
        $tenantId = $invoice->tenant_id
            ?: ($invoice->customer?->tenant_id
            ?: (\App\Models\Scopes\TenantScope::currentTenantId()
            ?: (session('customer_tenant_id')
            ?: (session('tenant_id') ? (int) session('tenant_id') : null))));

        $transaction = $this->createTransaction($invoice, 'midtrans', 'midtrans');

        $midtrans = new MidtransService($tenantId ? (string) $tenantId : null);

        // Always use standard Midtrans Snap transaction for full multi-channel pop-up & redirect
        $result = $midtrans->createSnapTransaction([
            'invoice'        => $invoice,
            'customer'       => $invoice->customer,
            'transaction_id' => $transaction->id,
            'method'         => $method,
            'return_path'    => '/portal/invoices',
        ]);

        if (!empty($result['token']) || !empty($result['order_id'])) {
            $transaction->update([
                'gateway_ref' => $result['token'] ?? $result['order_id'],
                'notes'       => json_encode($result),
            ]);
        }

        return [
            'transaction'    => $transaction,
            'payment_url'    => $result['redirect_url'] ?? ($result['payment_url'] ?? ''),
            'checkout_url'   => $result['redirect_url'] ?? ($result['payment_url'] ?? ''),
            'token'          => $result['token'] ?? '',
            'snap_token'     => $result['token'] ?? '',
            'order_id'       => $result['order_id'] ?? '',
            'client_key'     => $midtrans->getClientKey(),
            'snap_js_url'    => $midtrans->getSnapJsUrl(),
            'is_production'  => $midtrans->isProduction(),
            'qris_string'    => null,
            'qris_svg'       => null,
            'qris_image_url' => null,
        ];
    }

    /**
     * Process payment via Xendit gateway — generate checkout/invoice URL.
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, reference: string, order_id: string}
     */
    public function processXendit(Invoice $invoice, string $method = 'xendit'): array
    {
        $transaction = $this->createTransaction($invoice, 'xendit', 'xendit');

        $result = app(XenditService::class)->createInvoice([
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'method' => $method,
            'order_prefix' => 'INV',
            'transaction_id' => $transaction->id,
            'item_name' => 'Pembayaran Tagihan #' . $invoice->invoice_number,
            'return_path' => '/portal/dashboard',
        ]);

        if (!empty($result['reference'])) {
            $transaction->update([
                'gateway_ref' => $result['reference'],
            ]);
        }

        return [
            'transaction' => $transaction,
            'payment_url' => $result['link'] ?? '',
            'reference' => $result['reference'] ?? '',
            'order_id' => $result['order_id'] ?? '',
        ];
    }

    /**
     * Process payment via Duitku gateway — generate payment URL.
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, reference: string, order_id: string}
     */
    public function processDuitku(Invoice $invoice, string $method = 'duitku'): array
    {
        $transaction = $this->createTransaction($invoice, 'duitku', 'duitku');

        $result = app(DuitkuService::class)->createTransaction([
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'method' => $method,
            'order_prefix' => 'INV',
            'transaction_id' => $transaction->id,
            'item_name' => 'Tagihan #' . $invoice->invoice_number,
            'callback_path' => '/webhook/duitku',
            'return_path' => '/portal/dashboard',
        ]);

        if (!empty($result['reference'])) {
            $transaction->update([
                'gateway_ref' => $result['reference'],
            ]);
        }

        return [
            'transaction' => $transaction,
            'payment_url' => $result['link'] ?? '',
            'reference' => $result['reference'] ?? '',
            'order_id' => $result['order_id'] ?? '',
        ];
    }

    /**
     * Process payment via DOKU (Jokul Checkout).
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, checkout_url: string, reference: string, order_id: string, js_url: string, token_id: string}
     */
    public function processDoku(Invoice $invoice, string $method = 'doku'): array
    {
        $transaction = $this->createTransaction($invoice, 'doku', 'doku');
        $tenantId = $invoice->tenant_id ? (string) $invoice->tenant_id : null;
        $dokuService = new DokuService($tenantId);

        $orderId = 'INV-' . $invoice->id . '-' . $transaction->id;
        $res = $dokuService->createPayment([
            'order_id'       => $orderId,
            'amount'         => (int) $invoice->amount,
            'customer_name'  => $invoice->customer?->name ?: 'Pelanggan',
            'customer_email' => $invoice->customer?->email ?: 'customer@dgtlnetsolution.com',
            'customer_phone' => $invoice->customer?->phone ?: '081234567890',
            'item_name'      => 'Tagihan #' . $invoice->invoice_number,
            'callback_url'   => url('/portal/payment'),
        ]);

        if (!empty($res['reference'])) {
            $transaction->update([
                'gateway_ref' => $res['reference'],
                'order_id'    => $orderId,
            ]);
        }

        return [
            'transaction'  => $transaction,
            'payment_url'  => $res['checkout_url'] ?? '',
            'checkout_url' => $res['checkout_url'] ?? '',
            'reference'    => $res['reference'] ?? '',
            'order_id'     => $orderId,
            'js_url'       => $res['js_url'] ?? '',
            'token_id'     => $res['token_id'] ?? '',
        ];
    }

    /**
     * Process manual payment — mark as paid directly.
     */
    
    /**
     * Process payment via WijayaPay gateway — direct or routed via NODERA PAY Universal Engine.
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, qr_string: string, qr_image: string, reference: string, ref_id: string}
     */
    public function processWijayapay(Invoice $invoice, string $channel = 'QRIS'): array
    {
        $tenantId = $invoice->tenant_id
            ?: ($invoice->customer?->tenant_id
            ?: (\App\Models\Scopes\TenantScope::currentTenantId()
            ?: (session('customer_tenant_id')
            ?: (session('tenant_id') ? (int) session('tenant_id') : null))));

        // Cancel existing pending transactions for this invoice before creating a new one
        PaymentTransaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $transaction = $this->createTransaction($invoice, 'wijayapay', 'wijayapay');
        $wpSvc = new WijayaPayService($tenantId ? (string) $tenantId : null);

        if (!$wpSvc->isConfigured()) {
            return $this->processNoderapay($invoice, $channel);
        }

        $refId = 'INV-' . $invoice->id . '-' . $transaction->id;
        $res = $wpSvc->createTransaction([
            'ref_id'       => $refId,
            'nominal'      => (int) $invoice->amount,
            'code_payment' => $channel,
            'callback_url' => url('/api/webhook/wijayapay'),
        ]);

        if (!($res['success'] ?? false)) {
            throw new \Exception($res['message'] ?? 'Gagal membuat transaksi di WijayaPay.');
        }

        $qrString = $res['qr_string'] ?? '';
        $qrImage  = $res['qr_image'] ?? '';
        $trxRef   = $res['trx_reference'] ?? $refId;
        $totalBayar = (float) ($res['total_bayar'] ?? $invoice->amount);

        $transaction->update([
            'gateway_ref' => $trxRef,
            'notes'       => json_encode([
                'channel'      => $channel,
                'qr_string'    => $qrString,
                'qr_image'     => $qrImage,
                'total_amount' => $totalBayar,
                'raw'          => $res['data'] ?? [],
            ]),
        ]);

        return [
            'transaction' => $transaction,
            'payment_url' => $qrImage ?: $qrString,
            'qr_string'   => $qrString,
            'qr_image'    => $qrImage,
            'reference'   => $trxRef,
            'ref_id'      => $refId,
        ];
    }

    /**
     * Process payment via NODERA PAY gateway (Universal Engine: WijayaPay & Midtrans).
     *
     * @return array{transaction: PaymentTransaction, payment_url: string, reference: string, qr_string?: string}
     */
    public function processNoderapay(Invoice $invoice, string $channel = 'QRIS'): array
    {
        // Cancel existing pending transactions for this invoice before creating a new one
        PaymentTransaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $transaction = $this->createTransaction($invoice, 'noderapay', 'noderapay');

        $tenantId = $invoice->tenant_id;
        $npGw = \App\Models\PaymentGateway::withoutGlobalScopes()
            ->where('gateway', 'noderapay')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->first();

        // Fallback to global gateway if tenant gateway not set
        if (!$npGw) {
            $npGw = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->where('gateway', 'noderapay')
                ->whereNull('tenant_id')
                ->first();
        }

        $npCfg = $npGw?->config_json ?? [];
        $merchantCode = $npCfg['NODERAPAY_MERCHANT_CODE'] ?? ($npCfg['merchant_code'] ?? null);
        $apiKey = $npCfg['NODERAPAY_API_KEY'] ?? ($npCfg['api_key'] ?? null);

        $merchant = null;
        if (!empty($merchantCode)) {
            $merchant = \App\Models\NoderaPayMerchant::where('merchant_code', $merchantCode)->first();
        }
        if (!$merchant && !empty($apiKey)) {
            $merchant = \App\Models\NoderaPayMerchant::where('api_key', $apiKey)->first();
        }
        if (!$merchant) {
            $merchant = \App\Models\NoderaPayMerchant::where('status', 'active')->first();
        }

        if (!$merchant) {
            try {
                $merchant = \App\Models\NoderaPayMerchant::create([
                    'merchant_code' => $merchantCode ?: ('NP-' . strtoupper(\Illuminate\Support\Str::random(8))),
                    'name'          => 'NODERA PAY Merchant',
                    'owner_name'    => 'Admin',
                    'email'         => 'admin@dgtlnetsolution.com',
                    'phone'         => '08123456789',
                    'password'      => bcrypt(\Illuminate\Support\Str::random(16)),
                    'api_key'       => $apiKey ?: ('np_live_' . \Illuminate\Support\Str::random(32)),
                    'secret_key'    => 'np_sec_' . \Illuminate\Support\Str::random(32),
                    'status'        => 'active',
                ]);
            } catch (\Throwable $e) {
                Log::warning('[PaymentService] Failed to auto-create merchant: ' . $e->getMessage());
            }
        }

        if (!$merchant) {
            throw new \Exception('Akun Payment Gateway (NODERA PAY) belum dikonfigurasi oleh ISP / Admin.');
        }

        /** @var NoderaPayEngineService $engine */
        $engine = app(NoderaPayEngineService::class);
        $refId = 'INV-' . $invoice->id . '-' . $transaction->id;

        $npRes = $engine->createTransaction($merchant, [
            'ref_id'         => $refId,
            'amount'         => (float) $invoice->amount,
            'payment_method' => strtolower($channel),
            'customer_name'  => $invoice->customer?->name ?? 'Pelanggan',
            'customer_email' => $invoice->customer?->email ?? '',
            'customer_phone' => $invoice->customer?->phone ?? '',
            'callback_url'   => url('/api/v1/noderapay/webhook'),
            'return_url'     => url('/portal/dashboard'),
        ]);

        if (!($npRes['status'] === 'success' || ($npRes['success'] ?? false)) || empty($npRes['data'])) {
            throw new \Exception($npRes['message'] ?? 'Gagal membuat transaksi NODERA PAY.');
        }

        $qrData = $npRes['data'] ?? [];
        $trxRef = $qrData['trx_reference'] ?? $refId;
        $checkoutUrl = $qrData['checkout_url'] ?? null;
        $vaNumber = $qrData['va_number'] ?? ($qrData['pay_code'] ?? ($qrData['payment_code'] ?? null));
        $qrString = $qrData['qr_string'] ?? ($qrData['qr_content'] ?? null);
        $qrImage  = $qrData['qr_image'] ?? ($qrData['qr_svg'] ?? null);
        $totalBayar = (float) ($qrData['total_bayar'] ?? ($qrData['total_amount'] ?? ($qrData['gross_amount'] ?? $invoice->amount)));
        $expiredAt = $qrData['expired_at'] ?? null;
        $instructions = $qrData['instructions'] ?? ($qrData['panduan_pembayaran'] ?? null);

        $transaction->update([
            'gateway_ref' => $trxRef,
            'notes'       => json_encode([
                'channel'      => $channel,
                'va_number'    => $vaNumber,
                'pay_code'     => $vaNumber,
                'qr_string'    => $qrString,
                'qr_image'     => $qrImage,
                'total_amount' => $totalBayar,
                'expired_at'   => $expiredAt,
                'instructions' => $instructions,
                'checkout_url' => $checkoutUrl,
                'raw'          => $qrData,
            ]),
        ]);

        return [
            'transaction' => $transaction,
            'payment_url' => $checkoutUrl ?: ($qrImage ?: $qrString),
            'reference'   => $trxRef,
            'qr_string'   => $qrString,
            'qr_image'    => $qrImage,
        ];
    }

    public function processManual(Invoice $invoice, ?string $notes = null): PaymentTransaction
    {
        $transaction = $this->createTransaction($invoice, 'manual', 'manual');
        $transaction->update([
            'status'  => 'success',
            'paid_at' => now(),
            'notes'   => $notes,
        ]);

        $this->markInvoicePaid($invoice, 'manual', $transaction->id);

        return $transaction;
    }

    /**
     * Process QRIS payment — mark as paid (local QRIS).
     */
    public function processQris(Invoice $invoice): PaymentTransaction
    {
        $transaction = $this->createTransaction($invoice, 'qris', 'qris');
        $transaction->update([
            'status'  => 'success',
            'paid_at' => now(),
        ]);

        $this->markInvoicePaid($invoice, 'qris', $transaction->id);

        return $transaction;
    }

    /**
     * Process Dynamic QRIS payment with unique nominal code & EMVCo payload generation.
     *
     * @return array{
     *   success: bool,
     *   invoice_id: int,
     *   invoice_number: string,
     *   amount: float,
     *   unique_code: int,
     *   unique_amount: float,
     *   qris_string: ?string,
     *   qris_svg: ?string,
     *   merchant_name: ?string,
     *   merchant_city: ?string,
     *   expires_at: string,
     *   is_dynamic: bool
     * }
     */
    public function processDynamicQris(Invoice $invoice, bool $forceNew = false): array
    {
        $tenantId = $invoice->tenant_id
            ?: ($invoice->customer?->tenant_id
            ?: (\App\Models\Scopes\TenantScope::currentTenantId()
            ?: (session('customer_tenant_id')
            ?: (session('tenant_id') ? (int) session('tenant_id') : null))));

        // 1. Check if an active Payment Gateway is configured for this tenant / system
        $usageGw = \App\Models\PaymentGateway::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->where('gateway', '_usage_settings')
            ->orderByRaw('tenant_id IS NULL ASC')
            ->first();

        if (!$usageGw) {
            $usageGw = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->whereIn('gateway', ['_usage_settings', '_platform_payment_settings'])
                ->first();
        }

        $defaultGw = strtolower(trim((string) ($usageGw?->config_json['default_gateway'] ?? '')));
        $timeoutMinutes = (int) ($usageGw?->config_json['expiry_minutes']
            ?? $usageGw?->config_json['qris_timeout_minutes']
            ?? 15);

        $candidateGws = [];

        // 1. Collect all active gateways specifically for this tenant
        $tenantActiveGws = [];
        if ($tenantId) {
            $tenantActiveGws = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNotIn('gateway', ['manual', '_usage_settings', '_platform_payment_settings'])
                ->pluck('gateway')
                ->toArray();
        }

        if (!empty($tenantActiveGws)) {
            // STRICT TENANT ISOLATION: When tenant has active gateways configured, ONLY route through tenant gateways!
            if (!empty($defaultGw) && in_array($defaultGw, $tenantActiveGws, true)) {
                $candidateGws[] = $defaultGw;
            }
            foreach ($tenantActiveGws as $tag) {
                if (!in_array($tag, $candidateGws, true)) {
                    $candidateGws[] = $tag;
                }
            }
        } else {
            // Fallback to global/superadmin active gateways ONLY when tenant has NO active gateway configured
            $globalActiveGws = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('is_active', true)
                ->whereNotIn('gateway', ['manual', '_usage_settings', '_platform_payment_settings'])
                ->pluck('gateway')
                ->toArray();

            if (!empty($defaultGw) && in_array($defaultGw, $globalActiveGws, true)) {
                $candidateGws[] = $defaultGw;
            }
            foreach ($globalActiveGws as $gag) {
                if (!in_array($gag, $candidateGws, true)) {
                    $candidateGws[] = $gag;
                }
            }
        }

        $lastErrorMsg = null;

        // 2. Try routing through available Payment Gateways (tenant prioritized)
        foreach ($candidateGws as $gw) {
            try {
                if ($gw === 'midtrans') {
                    $midtransService = new MidtransService($tenantId ? (string) $tenantId : null);
                    if ($midtransService->isConfigured()) {
                        $res = $this->processMidtrans($invoice, 'midtrans');
                        $tx = $res['transaction'] ?? null;
                        $qrisString = $res['qris_string'] ?? null;
                        $qrisSvg = $res['qris_svg'] ?? null;
                        $qrisImage = $res['qris_image_url'] ?? null;
                        $checkoutUrl = $res['payment_url'] ?? ($res['checkout_url'] ?? null);
                        $snapToken = $res['token'] ?? ($res['snap_token'] ?? null);

                        if (!empty($checkoutUrl) || !empty($snapToken)) {
                            return [
                                'success'          => true,
                                'transaction_id'   => $tx?->id,
                                'invoice_id'       => $invoice->id,
                                'invoice_number'   => $invoice->invoice_number,
                                'amount'           => (float) $invoice->amount,
                                'unique_code'      => 0,
                                'unique_amount'    => (float) $invoice->amount,
                                'qris_string'      => null,
                                'qris_svg'         => null,
                                'qris_image_url'   => null,
                                'checkout_url'     => $checkoutUrl,
                                'snap_token'       => $snapToken,
                                'snap_js_url'      => $res['snap_js_url'] ?? $midtransService->getSnapJsUrl(),
                                'client_key'       => $res['client_key'] ?? $midtransService->getClientKey(),
                                'merchant_name'    => $midtransService->getMerchantName(),
                                'merchant_city'    => null,
                                'expires_at'       => now()->addMinutes($timeoutMinutes)->toIso8601String(),
                                'timeout_minutes'  => $timeoutMinutes,
                                'duration_seconds' => $timeoutMinutes * 60,
                                'is_dynamic'       => true,
                                'gateway'          => 'midtrans',
                            ];
                        }
                    }
                } elseif ($gw === 'wijayapay') {
                    $res = $this->processWijayapay($invoice, 'QRIS');
                    $tx = $res['transaction'];
                    $notes = is_string($tx->notes) ? json_decode($tx->notes, true) : ($tx->notes ?? []);
                    $raw = $notes['raw'] ?? [];
                    $qrisString = $notes['qr_string'] ?? ($res['qr_string'] ?? ($raw['qr_string'] ?? null));
                    $qrisImage = $notes['qr_image'] ?? ($res['qr_image'] ?? ($raw['qr_image'] ?? null));
                    $totalBayar = (float) ($notes['total_amount'] ?? ($raw['total_bayar'] ?? $invoice->amount));
                    $checkoutUrl = $notes['checkout_url'] ?? ($res['payment_url'] ?? null);

                    if (!empty($qrisString) || !empty($qrisImage) || !empty($checkoutUrl)) {
                        return [
                            'success'          => true,
                            'transaction_id'   => $tx->id,
                            'invoice_id'       => $invoice->id,
                            'invoice_number'   => $invoice->invoice_number,
                            'amount'           => (float) $invoice->amount,
                            'unique_code'      => 0,
                            'unique_amount'    => $totalBayar,
                            'qris_string'      => $qrisString,
                            'qris_svg'         => $qrisImage,
                            'qris_image_url'   => $qrisImage ?: $qrisString,
                            'checkout_url'     => $checkoutUrl,
                            'merchant_name'    => $raw['merchant_name'] ?? 'WIJAYAPAY',
                            'expires_at'       => $raw['expired'] ?? now()->addMinutes($timeoutMinutes)->toIso8601String(),
                            'timeout_minutes'  => $timeoutMinutes,
                            'duration_seconds' => $timeoutMinutes * 60,
                            'is_dynamic'       => true,
                            'gateway'          => 'wijayapay',
                        ];
                    }
                } elseif ($gw === 'tripay') {
                    $res = $this->processTripay($invoice, 'QRIS');
                    $tx = $res['transaction'];
                    $notes = is_string($tx->notes) ? json_decode($tx->notes, true) : ($tx->notes ?? []);
                    $raw = $notes['raw'] ?? [];
                    $qrisString = $notes['qr_string'] ?? ($raw['qr_string'] ?? null);
                    $qrisImage = $notes['qr_image'] ?? ($raw['qr_url'] ?? null);
                    $totalBayar = (float) ($notes['total_amount'] ?? ($raw['amount'] ?? $invoice->amount));
                    $checkoutUrl = $notes['checkout_url'] ?? ($res['payment_url'] ?? null);
                    $expiresAt = !empty($raw['expired_time']) ? date('c', $raw['expired_time']) : now()->addMinutes($timeoutMinutes)->toIso8601String();

                    if (!empty($qrisString) || !empty($qrisImage) || !empty($checkoutUrl)) {
                        return [
                            'success'          => true,
                            'transaction_id'   => $tx->id,
                            'invoice_id'       => $invoice->id,
                            'invoice_number'   => $invoice->invoice_number,
                            'amount'           => (float) $invoice->amount,
                            'unique_code'      => 0,
                            'unique_amount'    => $totalBayar,
                            'qris_string'      => $qrisString,
                            'qris_svg'         => $qrisImage,
                            'qris_image_url'   => $qrisImage ?: $qrisString,
                            'checkout_url'     => $checkoutUrl,
                            'merchant_name'    => 'TRIPAY',
                            'expires_at'       => $expiresAt,
                            'timeout_minutes'  => $timeoutMinutes,
                            'duration_seconds' => $timeoutMinutes * 60,
                            'is_dynamic'       => true,
                            'gateway'          => 'tripay',
                        ];
                    }
                } elseif ($gw === 'noderapay') {
                    $res = $this->processNoderapay($invoice, 'QRIS');
                    $tx = $res['transaction'];
                    $notes = is_string($tx->notes) ? json_decode($tx->notes, true) : ($tx->notes ?? []);
                    $raw = $notes['raw'] ?? [];
                    $qrisString = $notes['qr_string'] ?? ($raw['qr_string'] ?? ($raw['qr_content'] ?? null));
                    $qrisImage = $notes['qr_image'] ?? ($raw['qr_image'] ?? ($raw['qr_svg'] ?? null));
                    $totalBayar = (float) ($notes['total_amount'] ?? ($raw['total_bayar'] ?? ($raw['total_amount'] ?? $invoice->amount)));
                    $checkoutUrl = $notes['checkout_url'] ?? ($raw['checkout_url'] ?? null);
                    $expiresAt = $notes['expired_at'] ?? ($raw['expired_at'] ?? now()->addMinutes($timeoutMinutes)->toIso8601String());

                    if (!empty($qrisString) || !empty($qrisImage) || !empty($checkoutUrl)) {
                        return [
                            'success'          => true,
                            'transaction_id'   => $tx->id,
                            'invoice_id'       => $invoice->id,
                            'invoice_number'   => $invoice->invoice_number,
                            'amount'           => (float) $invoice->amount,
                            'unique_code'      => 0,
                            'unique_amount'    => $totalBayar,
                            'qris_string'      => $qrisString,
                            'qris_svg'         => $qrisImage,
                            'qris_image_url'   => $qrisImage ?: $qrisString,
                            'checkout_url'     => $checkoutUrl,
                            'merchant_name'    => $raw['merchant_name'] ?? 'NODERA PAY',
                            'merchant_city'    => $raw['merchant_city'] ?? null,
                            'expires_at'       => $expiresAt,
                            'timeout_minutes'  => $timeoutMinutes,
                            'duration_seconds' => $timeoutMinutes * 60,
                            'is_dynamic'       => true,
                            'gateway'          => 'noderapay',
                        ];
                    }
                } elseif ($gw === 'duitku') {
                    $res = $this->processDuitku($invoice, 'duitku');
                    $tx = $res['transaction'];
                    $checkoutUrl = $res['payment_url'] ?? null;
                    $qrString = $res['qr_string'] ?? null;

                    if (!empty($checkoutUrl) || !empty($qrString)) {
                        return [
                            'success'          => true,
                            'transaction_id'   => $tx->id,
                            'invoice_id'       => $invoice->id,
                            'invoice_number'   => $invoice->invoice_number,
                            'amount'           => (float) $invoice->amount,
                            'unique_code'      => 0,
                            'unique_amount'    => (float) $invoice->amount,
                            'qris_string'      => $qrString,
                            'qris_svg'         => null,
                            'qris_image_url'   => null,
                            'checkout_url'     => $checkoutUrl,
                            'merchant_name'    => 'DUITKU',
                            'expires_at'       => now()->addMinutes($timeoutMinutes)->toIso8601String(),
                            'timeout_minutes'  => $timeoutMinutes,
                            'duration_seconds' => $timeoutMinutes * 60,
                            'is_dynamic'       => true,
                            'gateway'          => 'duitku',
                        ];
                    }
                } elseif ($gw === 'xendit') {
                    $res = $this->processXendit($invoice, 'xendit');
                    $tx = $res['transaction'];
                    $checkoutUrl = $res['payment_url'] ?? null;

                    if (!empty($checkoutUrl)) {
                        return [
                            'success'          => true,
                            'transaction_id'   => $tx->id,
                            'invoice_id'       => $invoice->id,
                            'invoice_number'   => $invoice->invoice_number,
                            'amount'           => (float) $invoice->amount,
                            'unique_code'      => 0,
                            'unique_amount'    => (float) $invoice->amount,
                            'qris_string'      => null,
                            'qris_svg'         => null,
                            'qris_image_url'   => null,
                            'checkout_url'     => $checkoutUrl,
                            'merchant_name'    => 'XENDIT',
                            'expires_at'       => now()->addMinutes($timeoutMinutes)->toIso8601String(),
                            'timeout_minutes'  => $timeoutMinutes,
                            'duration_seconds' => $timeoutMinutes * 60,
                            'is_dynamic'       => true,
                            'gateway'          => 'xendit',
                        ];
                    }
                } elseif ($gw === 'doku') {
                    $res = $this->processDoku($invoice, 'doku');
                    $tx = $res['transaction'];
                    $checkoutUrl = $res['payment_url'] ?? null;

                    if (!empty($checkoutUrl)) {
                        return [
                            'success'          => true,
                            'transaction_id'   => $tx->id,
                            'invoice_id'       => $invoice->id,
                            'invoice_number'   => $invoice->invoice_number,
                            'amount'           => (float) $invoice->amount,
                            'unique_code'      => 0,
                            'unique_amount'    => (float) $invoice->amount,
                            'qris_string'      => null,
                            'qris_svg'         => null,
                            'qris_image_url'   => null,
                            'checkout_url'     => $checkoutUrl,
                            'js_url'           => $res['js_url'] ?? '',
                            'token_id'         => $res['token_id'] ?? '',
                            'merchant_name'    => 'DOKU',
                            'expires_at'       => now()->addMinutes($timeoutMinutes)->toIso8601String(),
                            'timeout_minutes'  => $timeoutMinutes,
                            'duration_seconds' => $timeoutMinutes * 60,
                            'is_dynamic'       => true,
                            'gateway'          => 'doku',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[PaymentService] Failed to process dynamic payment via ' . $gw . ': ' . $e->getMessage());
                $lastErrorMsg = $e->getMessage();
            }
        }

        // No active payment gateway configured or enabled
        return [
            'success' => false,
            'message' => $lastErrorMsg ?: 'Metode pembayaran online belum dikonfigurasi atau belum aktif. Silakan hubungi admin.',
        ];
    }

    /**
     * Check payment status from the gateway reference.
     */
    public function checkStatus(string $ref, string $gateway = 'tripay'): ?PaymentTransaction
    {
        $transaction = PaymentTransaction::where('gateway_ref', $ref)->first();

        if (!$transaction) {
            return null;
        }

        if ($gateway === 'tripay') {
            $tripay = new TripayService();
            $result = $tripay->detailTransaction($ref);

            if (!empty($result['data']['status'])) {
                $status = match ($result['data']['status']) {
                    'PAID'   => 'success',
                    'EXPIRED' => 'expired',
                    'FAILED'  => 'failed',
                    default   => 'pending',
                };

                if ($status === 'success' && $transaction->status !== 'success') {
                    $transaction->update([
                        'status'  => 'success',
                        'paid_at' => now(),
                    ]);

                    $this->markInvoicePaid($transaction->invoice, 'tripay', $transaction->id);
                } elseif (in_array($status, ['expired', 'failed'])) {
                    $transaction->update(['status' => $status]);
                }
            }
        }

        return $transaction->fresh();
    }

    /**
     * Handle incoming payment gateway callback / webhook (Idempotent with Redis Lock).
     *
     * @return array{success: bool, message: string, transaction: ?PaymentTransaction}
     */
    public function handleCallback(string $gateway, array $data): array
    {
        $payloadRef = $data['reference'] ?? ($data['merchant_ref'] ?? ($data['order_id'] ?? ($data['external_id'] ?? md5(json_encode($data)))));
        $lockKey = "payment_cb_lock_{$gateway}_" . md5((string) $payloadRef);

        $lock = Cache::lock($lockKey, 30);
        if (!$lock->get()) {
            Log::warning("[PaymentService] Duplicate callback skipped for gateway {$gateway} (ref: {$payloadRef})");
            return ['success' => true, 'message' => 'Duplicate callback ignored', 'transaction' => null];
        }

        try {
            return match ($gateway) {
                'tripay'                 => $this->handleTripayCallback($data),
                'midtrans'               => $this->handleMidtransCallback($data),
                'xendit'                 => $this->handleXenditCallback($data),
                'duitku'                 => $this->handleDuitkuCallback($data),
                'doku'                   => $this->handleDokuCallback($data),
                'wijayapay'              => $this->handleWijayapayCallback($data),
                'noderapay'              => $this->handleNoderapayCallback($data),
                'qris', 'gopay', 'gobiz' => $this->handleQrisCallback($data),
                default                  => ['success' => false, 'message' => 'Unknown gateway: ' . $gateway, 'transaction' => null],
            };
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Handle NODERA PAY webhook callback.
     */
    private function handleNoderapayCallback(array $data): array
    {
        $orderId = $data['order_id'] ?? ($data['ref_id'] ?? '');
        $status = strtolower((string) ($data['status'] ?? ''));

        $transaction = null;
        if (!empty($orderId)) {
            $transaction = PaymentTransaction::where('gateway_ref', $orderId)->first();
            if (!$transaction && preg_match('/^INV-(\d+)-(\d+)$/', $orderId, $matches)) {
                $transaction = PaymentTransaction::find((int) $matches[2]);
            }
        }

        if (!$transaction) {
            Log::warning('[NoderaPay Callback] Transaction not found: ' . $orderId);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null, 'handled' => false];
        }

        return DB::transaction(function () use ($transaction, $status, $orderId, $data) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null, 'handled' => false];
            }

            // Terminal state guard: once SUCCESS, immutable
            if ($lockedTx->status === 'success') {
                Log::info("[NoderaPay Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = in_array($status, ['paid', 'success', 'berhasil', 'settlement', 'settled', 'completed', '200'], true);
            $newStatus = match (true) {
                $isSuccess                   => 'success',
                $status === 'expired'        => 'expired',
                $status === 'failed'         => 'failed',
                default                      => $lockedTx->status,
            };

            // Amount validation guard
            $paidAmount = (float) ($data['amount'] ?? ($data['total_amount'] ?? 0));
            if ($isSuccess && $paidAmount > 0 && (float) $lockedTx->amount > 0 && round($paidAmount) < round((float) $lockedTx->amount)) {
                Log::warning("[NoderaPay Callback] Underpaid transaction #{$lockedTx->id}: received {$paidAmount} < expected {$lockedTx->amount}");
                $lockedTx->update(['notes' => "Underpaid: received {$paidAmount} expected {$lockedTx->amount}"]);
                return ['success' => false, 'message' => 'Underpaid transaction', 'transaction' => $lockedTx, 'handled' => false];
            }

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $orderId ?: $lockedTx->gateway_ref,
                'paid_at'     => $newStatus === 'success' ? now() : $lockedTx->paid_at,
            ]);

            if ($newStatus === 'success' && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'noderapay', $lockedTx->id);
            }

            Log::info('[NoderaPay Callback] Processed: ' . $orderId . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

    /**
     * Handle Tripay webhook callback.
     */
    private function handleTripayCallback(array $data): array
    {
        $merchantRef = $data['merchant_ref'] ?? '';
        $tripayStatus = strtoupper((string) ($data['status'] ?? ''));
        $reference = $data['reference'] ?? '';

        $transaction = $reference
            ? PaymentTransaction::where('gateway_ref', $reference)->first()
            : null;

        if (!$transaction) {
            $parts = explode('-', $merchantRef);
            $transactionId = end($parts);
            if (is_numeric($transactionId)) {
                $transaction = PaymentTransaction::find((int) $transactionId);
            }
        }

        if (!$transaction) {
            Log::warning('[Tripay Callback] Transaction not found: ' . $merchantRef);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null, 'handled' => false];
        }

        return DB::transaction(function () use ($transaction, $tripayStatus, $reference, $merchantRef, $data) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null, 'handled' => false];
            }

            // Terminal state guard: once SUCCESS, immutable
            if ($lockedTx->status === 'success') {
                Log::info("[Tripay Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = ($tripayStatus === 'PAID');
            $newStatus = match (true) {
                $isSuccess                   => 'success',
                $tripayStatus === 'EXPIRED'  => 'expired',
                $tripayStatus === 'FAILED'   => 'failed',
                default                      => $lockedTx->status,
            };

            // Amount validation guard
            $paidAmount = (float) ($data['total_amount'] ?? ($data['amount_received'] ?? ($data['amount'] ?? 0)));
            if ($isSuccess && $paidAmount > 0 && (float) $lockedTx->amount > 0 && round($paidAmount) < round((float) $lockedTx->amount)) {
                Log::warning("[Tripay Callback] Underpaid transaction #{$lockedTx->id}: received {$paidAmount} < expected {$lockedTx->amount}");
                $lockedTx->update(['notes' => "Underpaid: received {$paidAmount} expected {$lockedTx->amount}"]);
                return ['success' => false, 'message' => 'Underpaid transaction', 'transaction' => $lockedTx, 'handled' => false];
            }

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $reference ?: $lockedTx->gateway_ref,
                'paid_at'     => $newStatus === 'success' ? now() : $lockedTx->paid_at,
            ]);

            if ($newStatus === 'success' && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'tripay', $lockedTx->id);
            }

            Log::info('[Tripay Callback] Processed: ' . $merchantRef . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

    /**
     * Handle Midtrans webhook callback.
     */
    private function handleMidtransCallback(array $data): array
    {
        $orderId = (string) ($data['order_id'] ?? '');
        $transactionStatus = strtolower((string) ($data['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($data['fraud_status'] ?? ''));

        // 1. Resolve transaction by gateway_ref or notes
        $transaction = null;
        if (!empty($orderId)) {
            $transaction = PaymentTransaction::where('gateway_ref', $orderId)->first();
            if (!$transaction && !empty($data['transaction_id'])) {
                $transaction = PaymentTransaction::where('gateway_ref', (string) $data['transaction_id'])->first();
            }
        }

        // 2. Resolve by parsing INV-{invoiceId}-...
        if (!$transaction && preg_match('/^INV-(\d+)/', $orderId, $matches)) {
            $invId = (int) $matches[1];
            $transaction = PaymentTransaction::where('invoice_id', $invId)
                ->where('gateway', 'midtrans')
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();
        }

        // 3. Fallback to extracting transaction ID from orderId parts
        if (!$transaction) {
            $parts = explode('-', $orderId);
            $transactionId = end($parts);
            if (is_numeric($transactionId)) {
                $transaction = PaymentTransaction::find((int) $transactionId);
            }
        }

        if (!$transaction) {
            Log::warning('[Midtrans Callback] Transaction not found for order: ' . $orderId);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
        }

        return DB::transaction(function () use ($transaction, $transactionStatus, $fraudStatus, $data, $orderId) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
            }

            // Terminal state guard: once SUCCESS, immutable
            if ($lockedTx->status === 'success') {
                Log::info("[Midtrans Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = ($transactionStatus === 'capture' && $fraudStatus === 'accept') || ($transactionStatus === 'settlement');
            $newStatus = match (true) {
                $isSuccess                                                       => 'success',
                $transactionStatus === 'pending'                                 => 'pending',
                $transactionStatus === 'deny' || $transactionStatus === 'cancel' => 'failed',
                $transactionStatus === 'expire'                                  => 'expired',
                default                                                          => $lockedTx->status,
            };

            // Amount validation guard
            $paidAmount = (float) ($data['gross_amount'] ?? 0);
            if ($isSuccess && $paidAmount > 0 && (float) $lockedTx->amount > 0 && round($paidAmount) < round((float) $lockedTx->amount)) {
                Log::warning("[Midtrans Callback] Underpaid transaction #{$lockedTx->id}: received {$paidAmount} < expected {$lockedTx->amount}");
                $lockedTx->update(['notes' => "Underpaid: received {$paidAmount} expected {$lockedTx->amount}"]);
                return ['success' => false, 'message' => 'Underpaid transaction', 'transaction' => $lockedTx, 'handled' => false];
            }

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $data['transaction_id'] ?? ($orderId ?: $lockedTx->gateway_ref),
                'paid_at'     => $newStatus === 'success' ? now() : $lockedTx->paid_at,
            ]);

            if ($newStatus === 'success' && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'midtrans', $lockedTx->id);
            }

            Log::info('[Midtrans Callback] Processed: ' . $orderId . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

    /**
     * Handle Xendit webhook callback.
     */
    private function handleXenditCallback(array $data): array
    {
        $orderId = $data['external_id'] ?? '';
        $status = strtoupper((string) ($data['status'] ?? ''));

        // external_id format: INV-{invoiceId}-{transactionId}
        $parts = explode('-', (string) $orderId);
        $transactionId = (int) end($parts);

        $transaction = PaymentTransaction::find($transactionId);

        if (!$transaction) {
            Log::warning('[Xendit Callback] Transaction not found: ' . $orderId);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
        }

        return DB::transaction(function () use ($transaction, $status, $data, $orderId) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
            }

            if ($lockedTx->status === 'success') {
                Log::info("[Xendit Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = ($status === 'PAID');
            $newStatus = match (true) {
                $isSuccess          => 'success',
                $status === 'EXPIRED' => 'expired',
                $status === 'FAILED'  => 'failed',
                default             => $lockedTx->status,
            };

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $data['id'] ?? $lockedTx->gateway_ref,
                'paid_at'     => $newStatus === 'success' ? now() : $lockedTx->paid_at,
            ]);

            if ($newStatus === 'success' && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'xendit', $lockedTx->id);
            }

            Log::info('[Xendit Callback] Processed: ' . $orderId . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

    /**
     * Handle Duitku webhook callback.
     */
    private function handleDuitkuCallback(array $data): array
    {
        $orderId = $data['merchantOrderId'] ?? '';
        $resultCode = (string) ($data['resultCode'] ?? '');

        // merchantOrderId format: INV-{invoiceId}-{transactionId}
        $parts = explode('-', (string) $orderId);
        $transactionId = (int) end($parts);

        $transaction = PaymentTransaction::find($transactionId);

        if (!$transaction) {
            Log::warning('[Duitku Callback] Transaction not found: ' . $orderId);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
        }

        return DB::transaction(function () use ($transaction, $resultCode, $data, $orderId) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
            }

            if ($lockedTx->status === 'success') {
                Log::info("[Duitku Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = ($resultCode === '00');
            $newStatus = match (true) {
                $isSuccess                           => 'success',
                in_array($resultCode, ['01', '02'])  => 'failed',
                $resultCode === '07'                 => 'expired',
                default                              => $lockedTx->status,
            };

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $data['reference'] ?? $lockedTx->gateway_ref,
                'paid_at'     => $isSuccess ? now() : $lockedTx->paid_at,
            ]);

            if ($isSuccess && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'duitku', $lockedTx->id);
            }

            Log::info('[Duitku Callback] Processed: ' . $orderId . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

    /**
     * Handle DOKU (Jokul) payment notification webhook.
     */
    public function handleDokuCallback(array $data): array
    {
        $order = $data['order'] ?? [];
        $transactionData = $data['transaction'] ?? [];
        $orderId = $order['invoice_number'] ?? ($data['invoice_number'] ?? '');
        $status = strtoupper((string) ($transactionData['status'] ?? ($data['status'] ?? '')));

        // Format: INV-{invoiceId}-{transactionId}
        $parts = explode('-', (string) $orderId);
        $transactionId = (int) end($parts);

        $transaction = PaymentTransaction::find($transactionId);
        if (!$transaction && !empty($orderId)) {
            $transaction = PaymentTransaction::where('order_id', $orderId)
                ->orWhere('gateway_ref', $orderId)
                ->first();
        }

        if (!$transaction) {
            Log::warning('[Doku Callback] Transaction not found: ' . $orderId);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
        }

        return DB::transaction(function () use ($transaction, $status, $transactionData, $data, $orderId) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null];
            }

            if ($lockedTx->status === 'success') {
                Log::info("[Doku Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = in_array($status, ['SUCCESS', 'PAID', 'SETTLEMENT'], true);
            $newStatus = match (true) {
                $isSuccess                                 => 'success',
                in_array($status, ['EXPIRED', 'EXPIRE'], true) => 'expired',
                in_array($status, ['FAILED', 'FAILURE'], true) => 'failed',
                default                                    => $lockedTx->status,
            };

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $transactionData['original_request_id'] ?? ($data['reference'] ?? $lockedTx->gateway_ref),
                'paid_at'     => $isSuccess ? now() : $lockedTx->paid_at,
            ]);

            if ($isSuccess && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'doku', $lockedTx->id);
            }

            Log::info('[Doku Callback] Processed: ' . $orderId . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

    /**
     * Handle QRIS (GoPay/GoBiz) automated webhook callback.
     */
    public function handleQrisCallback(array $data): array
    {
        $rawAmount = $data['amount'] ?? null;
        $rawText = $data['raw_message'] ?? ($data['text'] ?? ($data['message'] ?? ($data['body'] ?? '')));
        $tenantId = isset($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        $amount = is_numeric($rawAmount)
            ? (float) $rawAmount
            : QrisDynamicService::parseAmountFromText((string) $rawText);

        if (!$amount || $amount <= 0) {
            Log::warning('[QRIS Callback] Could not resolve valid amount from payload: ' . json_encode($data));
            return ['success' => false, 'message' => 'Invalid or missing payment amount', 'transaction' => null];
        }

        $service = app(NominalUnikService::class);
        $invoice = $service->matchAmount($amount, $tenantId);

        if (!$invoice) {
            // Check if matches a pending VpnTopupRequest ONLY for platform Superadmin callbacks (tenantId is explicitly null)
            if ($tenantId === null && class_exists(\App\Models\VpnTopupRequest::class)) {
                $topupReq = \App\Models\VpnTopupRequest::where('status', 'pending')
                    ->where(function ($q) use ($amount) {
                        $q->whereRaw('ROUND(total_amount) = ?', [(int) round($amount)])
                          ->orWhereRaw('ROUND(amount) = ?', [(int) round($amount)]);
                    })
                    ->latest()
                    ->first();

                if ($topupReq) {
                    \App\Models\VpnTopupRequest::settleTopup($topupReq, $amount, 'Auto-Settled via Platform QRIS Webhook');
                    Log::info("[QRIS Callback] Successfully settled platform topup #{$topupReq->invoice_number} for Rp {$amount}");
                    return [
                        'success'     => true,
                        'message'     => "Topup deposit #{$topupReq->invoice_number} settled successfully",
                        'transaction' => null,
                        'invoice'     => $topupReq->invoice_number,
                        'amount'      => $amount,
                    ];
                }
            }

            Log::warning("[QRIS Callback] No unpaid invoice matching amount: Rp {$amount} for tenant: " . ($tenantId ?? 'platform'));
            return ['success' => false, 'message' => "No invoice matching amount Rp {$amount}", 'transaction' => null];
        }

        return DB::transaction(function () use ($invoice, $amount, $data) {
            // Find or create PaymentTransaction with lock
            $transaction = PaymentTransaction::where('invoice_id', $invoice->id)
                ->where('gateway', 'qris')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if (!$transaction) {
                $transaction = PaymentTransaction::create([
                    'invoice_id'  => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'tenant_id'   => $invoice->tenant_id,
                    'amount'      => $amount,
                    'method'      => 'qris',
                    'gateway'     => 'qris',
                    'gateway_ref' => $data['transaction_id'] ?? ('QRIS-' . $invoice->id . '-' . time()),
                    'status'      => 'pending',
                ]);
            }

            if ($transaction->status !== 'success') {
                $transaction->update([
                    'status'      => 'success',
                    'gateway_ref' => $data['transaction_id'] ?? $transaction->gateway_ref,
                    'paid_at'     => now(),
                    'notes'       => 'Auto-Settled via QRIS: ' . ($data['sender'] ?? 'GoBiz/GoPay'),
                ]);

                $this->markInvoicePaid($invoice->id, 'qris_gopay', $transaction->id);

                // Dispatch WhatsApp receipt asynchronously
                $customer = $invoice->customer ?? \App\Models\Customer::withoutGlobalScopes()->find($invoice->customer_id);
                if ($customer && !empty($customer->phone)) {
                    try {
                        $waService = new \App\Services\WhatsappService($invoice->tenant_id ?? $customer->tenant_id ?? null);
                        if ($waService->isConfigured()) {
                            $waService->sendPaymentSuccess($customer->toArray(), $invoice->toArray());
                        }
                    } catch (\Throwable $e) {
                        Log::error('[QRIS Callback] WhatsApp dispatch failed: ' . $e->getMessage());
                    }
                }
            }

            Log::info("[QRIS Callback] Successfully settled invoice #{$invoice->invoice_number} for Rp {$amount}");

            return [
                'success'     => true,
                'message'     => 'QRIS Payment confirmed successfully',
                'transaction' => $transaction->fresh(),
                'invoice'     => $invoice->invoice_number,
                'amount'      => $amount,
            ];
        });
    }

    /**
     * Mark the related invoice as paid with database locking and idempotent protection.
     */
    public function markInvoicePaid(Invoice|int $invoiceOrId, string $method, int $transactionId): void
    {
        $invoiceId = is_numeric($invoiceOrId) ? (int) $invoiceOrId : (int) $invoiceOrId->id;

        DB::transaction(function () use ($invoiceId, $method, $transactionId) {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::withoutGlobalScopes()->where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) {
                Log::warning("[Payment] Invoice #{$invoiceId} not found during markInvoicePaid.");
                return;
            }

            if ($invoice->paid || $invoice->status === 'paid') {
                Log::info("[Payment] Invoice #{$invoice->invoice_number} is already marked as PAID. Skipping duplicate settlement.");
                return;
            }

            $invoice->update([
                'paid'           => true,
                'status'         => 'paid',
                'paid_at'        => now(),
                'payment_method' => $method,
                'payment_ref'    => (string) $transactionId,
                'processed_by'   => 'Pembayaran Online',
            ]);

            // Auto-unisolate customer asynchronously via UnisolateCustomerJob
            if ($invoice->customer_id) {
                $customer = \App\Models\Customer::withoutGlobalScopes()->find($invoice->customer_id);
                if ($customer && $customer->status === 'isolated') {
                    $hasOtherUnpaid = Invoice::withoutGlobalScopes()
                        ->where('customer_id', $customer->id)
                        ->where('id', '!=', $invoice->id)
                        ->where('paid', false)
                        ->where('due_date', '<', now()->format('Y-m-d'))
                        ->exists();

                    if (!$hasOtherUnpaid) {
                        try {
                            \App\Jobs\UnisolateCustomerJob::dispatch($customer, "Payment Gateway ({$method})");
                        } catch (\Throwable $e) {
                            Log::error("[Payment] Failed to dispatch UnisolateCustomerJob for customer #{$customer->id}: " . $e->getMessage());
                        }
                    }
                }
            }

            Log::info("[Payment] Invoice #{$invoice->invoice_number} marked as PAID via {$method}");
        });
    }

    /**
     * Handle WijayaPay webhook callback.
     */
    private function handleWijayapayCallback(array $data): array
    {
        $dataPayload = $data['data'] ?? $data;
        $refId = $dataPayload['ref_id'] ?? ($data['ref_id'] ?? ($dataPayload['order_id'] ?? ($data['order_id'] ?? '')));
        $rawStatus = $dataPayload['status_pembayaran']
            ?? ($data['status_pembayaran']
            ?? ($dataPayload['status']
            ?? ($data['status'] ?? '')));

        if (is_bool($rawStatus) || $rawStatus === '1' || $rawStatus === 'true' || strtolower((string) $rawStatus) === 'success') {
            $nested = $dataPayload['status_pembayaran'] ?? ($data['status_pembayaran'] ?? null);
            if (!empty($nested) && !is_bool($nested)) {
                $rawStatus = $nested;
            }
        }

        $status = strtolower((string) $rawStatus);
        $trxRef = $dataPayload['trx_reference'] ?? ($data['trx_reference'] ?? '');

        $transaction = $trxRef
            ? PaymentTransaction::where('gateway_ref', $trxRef)->first()
            : null;

        if (!$transaction && $refId) {
            $parts = explode('-', $refId);
            $transactionId = end($parts);
            if (is_numeric($transactionId)) {
                $transaction = PaymentTransaction::find((int) $transactionId);
            }
        }

        if (!$transaction && $refId) {
            $transaction = PaymentTransaction::where('gateway_ref', $refId)->first();
        }

        if (!$transaction) {
            Log::warning('[WijayaPay Callback] Transaction not found: ' . $refId);
            return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null, 'handled' => false];
        }

        return DB::transaction(function () use ($transaction, $status, $trxRef, $refId, $dataPayload) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return ['success' => false, 'message' => 'Transaction not found', 'transaction' => null, 'handled' => false];
            }

            if ($lockedTx->status === 'success') {
                Log::info("[WijayaPay Callback] Transaction #{$lockedTx->id} already settled. Skipping duplicate callback.");
                return ['success' => true, 'message' => 'Transaction already settled', 'transaction' => $lockedTx, 'handled' => true];
            }

            $isSuccess = in_array($status, ['paid', 'success', 'berhasil', 'settlement', 'settled', 'completed', '200'], true);
            $newStatus = match (true) {
                $isSuccess                   => 'success',
                $status === 'expired'        => 'expired',
                $status === 'failed'         => 'failed',
                default                      => $lockedTx->status,
            };

            // Amount validation guard
            $paidAmount = (float) ($dataPayload['amount'] ?? ($dataPayload['nominal'] ?? ($dataPayload['total_bayar'] ?? 0)));
            if ($isSuccess && $paidAmount > 0 && (float) $lockedTx->amount > 0 && round($paidAmount) < round((float) $lockedTx->amount)) {
                Log::warning("[WijayaPay Callback] Underpaid transaction #{$lockedTx->id}: received {$paidAmount} < expected {$lockedTx->amount}");
                $lockedTx->update(['notes' => "Underpaid: received {$paidAmount} expected {$lockedTx->amount}"]);
                return ['success' => false, 'message' => 'Underpaid transaction', 'transaction' => $lockedTx, 'handled' => false];
            }

            $lockedTx->update([
                'status'      => $newStatus,
                'gateway_ref' => $trxRef ?: ($refId ?: $lockedTx->gateway_ref),
                'paid_at'     => $newStatus === 'success' ? now() : $lockedTx->paid_at,
            ]);

            if ($newStatus === 'success' && $lockedTx->invoice_id) {
                $this->markInvoicePaid($lockedTx->invoice_id, 'wijayapay', $lockedTx->id);
            }

            Log::info('[WijayaPay Callback] Processed: ' . $refId . ' -> ' . $newStatus);
            return ['success' => true, 'message' => 'Callback processed', 'transaction' => $lockedTx->fresh(), 'handled' => true];
        });
    }

}
