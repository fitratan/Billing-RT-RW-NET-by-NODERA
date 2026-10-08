<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    private string $serverKey = '';
    private string $clientKey = '';
    private string $merchantId = '';
    private bool $isProduction = false;
    private bool $isActive = false;

    public const CHANNELS = [
        [
            'code' => 'midtrans:all',
            'name' => 'Midtrans Snap (Semua Metode)',
            'group' => 'Midtrans Payment Gateway',
            'fee_flat' => 0,
            'fee_percent' => 0,
            'description' => 'QRIS, GoPay, ShopeePay, Virtual Account BCA/Mandiri/BRI/BNI, Indomaret, Alfamart, Kartu Kredit',
        ],
        [
            'code' => 'midtrans:qris',
            'name' => 'QRIS (Midtrans)',
            'group' => 'E-Wallet & QRIS (Midtrans)',
            'fee_flat' => 0,
            'fee_percent' => 0.7,
            'description' => 'QRIS All Payment, GoPay & ShopeePay',
        ],
        [
            'code' => 'midtrans:shopeepay',
            'name' => 'ShopeePay & GoPay (Midtrans)',
            'group' => 'E-Wallet & QRIS (Midtrans)',
            'fee_flat' => 0,
            'fee_percent' => 1.5,
            'description' => 'Pembayaran instan via ShopeePay & GoPay',
        ],
        [
            'code' => 'midtrans:va',
            'name' => 'Virtual Account Bank (Midtrans)',
            'group' => 'Virtual Account (Midtrans)',
            'fee_flat' => 4000,
            'fee_percent' => 0,
            'description' => 'BCA, Mandiri, BNI, BRI, Permata VA',
        ],
        [
            'code' => 'midtrans:bca_va',
            'name' => 'BCA Virtual Account (Midtrans)',
            'group' => 'Virtual Account (Midtrans)',
            'fee_flat' => 4000,
            'fee_percent' => 0,
            'description' => 'Transfer via BCA Virtual Account',
        ],
        [
            'code' => 'midtrans:bni_va',
            'name' => 'BNI Virtual Account (Midtrans)',
            'group' => 'Virtual Account (Midtrans)',
            'fee_flat' => 4000,
            'fee_percent' => 0,
            'description' => 'Transfer via BNI Virtual Account',
        ],
        [
            'code' => 'midtrans:bri_va',
            'name' => 'BRI Virtual Account (Midtrans)',
            'group' => 'Virtual Account (Midtrans)',
            'fee_flat' => 4000,
            'fee_percent' => 0,
            'description' => 'Transfer via BRI Virtual Account',
        ],
        [
            'code' => 'midtrans:mandiri_bill',
            'name' => 'Mandiri Bill Payment (Midtrans)',
            'group' => 'Virtual Account (Midtrans)',
            'fee_flat' => 4000,
            'fee_percent' => 0,
            'description' => 'Transfer via Mandiri Bill Payment',
        ],
        [
            'code' => 'midtrans:indomaret',
            'name' => 'Indomaret (Midtrans)',
            'group' => 'Gerai Ritel (Midtrans)',
            'fee_flat' => 5000,
            'fee_percent' => 0,
            'description' => 'Bayar tunai di kasir Indomaret',
        ],
        [
            'code' => 'midtrans:alfamart',
            'name' => 'Alfamart (Midtrans)',
            'group' => 'Gerai Ritel (Midtrans)',
            'fee_flat' => 5000,
            'fee_percent' => 0,
            'description' => 'Bayar tunai di kasir Alfamart',
        ],
        [
            'code' => 'midtrans:cstore',
            'name' => 'Gerai Ritel (Midtrans)',
            'group' => 'Gerai Ritel (Midtrans)',
            'fee_flat' => 5000,
            'fee_percent' => 0,
            'description' => 'Indomaret & Alfamart',
        ],
    ];

    public function __construct(?string $tenantId = null)
    {
        $tenantId = $tenantId ?? (session('tenant_id') ? (string) session('tenant_id') : null);

        $query = PaymentGateway::withoutGlobalScopes()->where('gateway', 'midtrans');
        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })->orderByRaw('tenant_id IS NULL ASC'); // Prioritize tenant-specific config over global null
        } else {
            $query->whereNull('tenant_id');
        }

        $gw = $query->first();
        $config = $gw?->config_json ?? [];

        // Fallback hierarchy: payment_gateway_settings -> settings table -> env
        $rawKey = $config['MIDTRANS_SERVER_KEY']
            ?? $config['server_key']
            ?? Setting::apiValue('MIDTRANS_SERVER_KEY', '')
            ?? env('MIDTRANS_SERVER_KEY', '');
        $this->serverKey = trim(preg_replace('/[\x00-\x1F\x7F\xC2\xA0\'"]/', '', (string) $rawKey));

        $rawClientKey = $config['MIDTRANS_CLIENT_KEY']
            ?? $config['client_key']
            ?? Setting::apiValue('MIDTRANS_CLIENT_KEY', '')
            ?? env('MIDTRANS_CLIENT_KEY', '');
        $this->clientKey = trim(preg_replace('/[\x00-\x1F\x7F\xC2\xA0\'"]/', '', (string) $rawClientKey));

        $rawMerchantId = $config['MIDTRANS_MERCHANT_ID']
            ?? $config['merchant_id']
            ?? Setting::apiValue('MIDTRANS_MERCHANT_ID', '')
            ?? env('MIDTRANS_MERCHANT_ID', '');
        $this->merchantId = trim(preg_replace('/[\x00-\x1F\x7F\xC2\xA0\'"]/', '', (string) $rawMerchantId));

        // Auto-fix if Server Key and Client Key were swapped in input form
        if (str_starts_with($this->serverKey, 'Mid-client-') || str_starts_with($this->serverKey, 'SB-Mid-client-')) {
            if (str_starts_with($this->clientKey, 'Mid-server-') || str_starts_with($this->clientKey, 'SB-Mid-server-')) {
                $temp = $this->serverKey;
                $this->serverKey = $this->clientKey;
                $this->clientKey = $temp;
            }
        }

        // Auto-detect production mode from Server Key prefix or config
        if (str_starts_with($this->serverKey, 'Mid-server-')) {
            $this->isProduction = true;
        } elseif (str_starts_with($this->serverKey, 'SB-Mid-server-')) {
            $this->isProduction = false;
        } else {
            $isProd = ($config['MIDTRANS_IS_PRODUCTION'] ?? null);
            if ($isProd !== null) {
                $this->isProduction = (bool) $isProd;
            } else {
                $mode = $config['MIDTRANS_MODE']
                    ?? $config['mode']
                    ?? Setting::apiValue('MIDTRANS_MODE', 'sandbox')
                    ?? env('MIDTRANS_MODE', 'sandbox');
                $this->isProduction = strtolower(trim((string) $mode)) === 'production';
            }
        }
        $this->isActive = $gw ? (bool) $gw->is_active : !empty($this->serverKey);
    }

    public function isConfigured(): bool
    {
        return !empty($this->serverKey);
    }

    public function isActive(): bool
    {
        return $this->isConfigured() && $this->isActive;
    }

    public function getMerchantName(): string
    {
        return !empty($this->merchantId) ? 'MIDTRANS (' . $this->merchantId . ')' : 'MIDTRANS';
    }

    public function getServerKey(): string
    {
        return $this->serverKey;
    }

    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    public function isProduction(): bool
    {
        return $this->isProduction;
    }

    public function getSnapBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    public function getCoreApiBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    public function getSnapJsUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /**
     * Create Midtrans Core API QRIS transaction (with automatic Snap fallback).
     */
    public function createQrisTransaction(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Midtrans Server Key belum diatur dalam sistem.');
        }

        /** @var Invoice $invoice */
        $invoice = $data['invoice'];
        $customer = $data['customer'] ?? $invoice->customer;
        $orderPrefix = strtoupper($data['order_prefix'] ?? 'INV');
        $orderId = $orderPrefix . '-' . $invoice->id . '-' . time() . '-' . rand(100, 999);
        $amount = (int) round($invoice->amount);
        if ($amount <= 0) {
            $amount = 10000;
        }

        $email = $customer?->email ?: ($customer?->pppoe_username ? "{$customer->pppoe_username}@nodera.local" : 'customer@nodera.local');
        $phone = $this->normalizePhone($customer?->phone ?? '');
        $firstName = preg_replace('/[^a-zA-Z0-9\s]/', '', $customer?->name ?: 'Pelanggan') ?: 'Pelanggan';

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'qris' => [
                'acquirer' => 'gopay',
            ],
            'customer_details' => [
                'first_name' => substr($firstName, 0, 45),
                'email'      => $email,
                'phone'      => $phone,
            ],
            'item_details' => [
                [
                    'id'       => 'INV-' . $invoice->id,
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => substr('Tagihan #' . ($invoice->invoice_number ?? $invoice->id), 0, 45),
                ],
            ],
        ];

        try {
            $authHeader = 'Basic ' . base64_encode($this->serverKey . ':');
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ])
                ->post($this->getCoreApiBaseUrl() . '/charge', $payload);

            $body = $response->json();

            if ($response->successful() && !empty($body['qr_string'])) {
                $qrImageUrl = null;
                if (!empty($body['actions'])) {
                    foreach ($body['actions'] as $act) {
                        if (($act['name'] ?? '') === 'generate-qr-code') {
                            $qrImageUrl = $act['url'] ?? null;
                            break;
                        }
                    }
                }

                return [
                    'success'        => true,
                    'qris_string'    => $body['qr_string'] ?? '',
                    'qris_image_url' => $qrImageUrl ?: ($body['qr_string'] ?? ''),
                    'order_id'       => $orderId,
                    'raw'            => $body,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[Midtrans] Core API QRIS charge failed: ' . $e->getMessage() . ', falling back to Snap.');
        }

        // Fallback to Snap Transaction
        $data['order_id'] = $orderId;
        $snap = $this->createSnapTransaction($data);
        return [
            'success'        => true,
            'qris_string'    => null,
            'qris_image_url' => null,
            'checkout_url'   => $snap['payment_url'] ?? '',
            'snap_token'     => $snap['token'] ?? '',
            'order_id'       => $orderId,
            'raw'            => $snap,
        ];
    }

    /**
     * Create a Midtrans Snap transaction (checkout redirect / popup token).
     *
     * @param array $data Parameters containing invoice, customer, and optional options
     * @return array{success: bool, token: string, payment_url: string, redirect_url: string, order_id: string}
     */
    public function createSnapTransaction(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Midtrans Server Key belum diatur dalam sistem.');
        }

        /** @var Invoice $invoice */
        $invoice = $data['invoice'];
        $customer = $data['customer'] ?? $invoice->customer;
        $orderPrefix = strtoupper($data['order_prefix'] ?? 'INV');
        $orderId = $data['order_id'] ?? ($orderPrefix . '-' . $invoice->id . '-' . time() . '-' . rand(100, 999));
        $amount = (int) round($invoice->amount);
        if ($amount <= 0) {
            $amount = 10000;
        }
        $returnPath = $data['return_path'] ?? '/portal/invoices';

        $phone = $this->normalizePhone($customer?->phone ?? '');
        $email = $customer?->email ?: ($customer?->pppoe_username ? "{$customer->pppoe_username}@nodera.local" : 'customer@nodera.local');
        $firstName = preg_replace('/[^a-zA-Z0-9\s]/', '', $customer?->name ?: 'Pelanggan') ?: 'Pelanggan';

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => substr($firstName, 0, 45),
                'email'      => $email,
                'phone'      => $phone,
            ],
            'item_details' => [
                [
                    'id'       => 'INV-' . $invoice->id,
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => substr('Tagihan #' . ($invoice->invoice_number ?? $invoice->id), 0, 45),
                ],
            ],
            'callbacks' => [
                'finish' => url($returnPath),
                'error'  => url($returnPath),
                'close'  => url($returnPath),
            ],
        ];

        // Specific channel filters if requested
        $channel = $data['method'] ?? 'midtrans';
        $enabledPayments = $this->resolveEnabledPayments($channel);
        if (!empty($enabledPayments)) {
            $payload['enabled_payments'] = $enabledPayments;
        }

        try {
            $authHeader = 'Basic ' . base64_encode($this->serverKey . ':');
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ])
                ->post($this->getSnapBaseUrl(), $payload);

            $body = $response->json();

            if (!$response->successful() || (empty($body['token']) && empty($body['redirect_url']))) {
                $status = $response->status();
                $errMsg = $body['error_messages'][0] ?? ($body['message'] ?? 'Respon Midtrans tidak valid (HTTP ' . $status . ')');
                if ($status === 401) {
                    $errMsg .= ' (Akses Ditolak: Pastikan Server Key diawali ' . ($this->isProduction ? "'Mid-server-'" : "'SB-Mid-server-'") . ' dan bukan Client Key. Pastikan IP server 127.0.0.1 diizinkan atau kosongkan kolom Allowed IP di Dashboard Midtrans).';
                }
                Log::error("[Midtrans] Snap API Error: {$errMsg}", ['payload' => $payload, 'response' => $body]);
                throw new \RuntimeException("Midtrans Error: {$errMsg}");
            }

            return [
                'success'      => true,
                'token'        => $body['token'] ?? '',
                'payment_url'  => $body['redirect_url'] ?? '',
                'redirect_url' => $body['redirect_url'] ?? '',
                'order_id'     => $orderId,
            ];
        } catch (\Throwable $e) {
            Log::error("[Midtrans] Snap API Exception: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Map payment method string to Midtrans enabled_payments array.
     */
    public function resolveEnabledPayments(string $method): ?array
    {
        $channel = strtolower(trim(str_replace('midtrans:', '', $method)));
        return match ($channel) {
            'qris'        => ['qris', 'gopay', 'shopeepay'],
            'gopay'       => ['gopay', 'qris'],
            'shopeepay'   => ['shopeepay', 'qris'],
            'va', 'bca_va', 'bni_va', 'bri_va', 'echannel', 'permata_va' => [
                'bca_va', 'bni_va', 'bri_va', 'echannel', 'permata_va', 'other_va'
            ],
            'cstore', 'indomaret', 'alfamart' => ['indomaret', 'alfamart'],
            'credit_card' => ['credit_card'],
            default       => null,
        };
    }

    /**
     * Verify Midtrans notification signature key.
     * signature_key = sha512(order_id + status_code + gross_amount + server_key)
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        if (empty($this->serverKey) || empty($signatureKey)) {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($expected, $signatureKey);
    }

    /**
     * Get real-time transaction status from Midtrans API.
     */
    public function getTransactionStatus(string $orderId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Midtrans Server Key belum diatur.'];
        }

        $cleanOrderId = trim($orderId);
        $url = $this->isProduction
            ? "https://api.midtrans.com/v2/{$cleanOrderId}/status"
            : "https://api.sandbox.midtrans.com/v2/{$cleanOrderId}/status";

        try {
            $authHeader = 'Basic ' . base64_encode($this->serverKey . ':');
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Accept'        => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'            => true,
                    'data'               => $data,
                    'transaction_status' => $data['transaction_status'] ?? '',
                    'fraud_status'       => $data['fraud_status'] ?? '',
                    'payment_type'       => $data['payment_type'] ?? '',
                    'gross_amount'       => $data['gross_amount'] ?? '',
                    'order_id'           => $data['order_id'] ?? $orderId,
                ];
            }

            return [
                'success' => false,
                'status'  => $response->status(),
                'message' => 'Status check failed: HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal cek status Midtrans: ' . $e->getMessage()];
        }
    }

    /**
     * Test connection to Midtrans API with Server Key.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Server Key Midtrans belum diisi.'];
        }

        $url = $this->isProduction
            ? 'https://api.midtrans.com/v2/token'
            : 'https://api.sandbox.midtrans.com/v2/token';

        $authHeader = 'Basic ' . base64_encode($this->serverKey . ':');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            $status = $response->status();
            // Midtrans returns 400 for GET on /v2/token because query params are omitted,
            // but returns 401/403 if Server Key is invalid.
            if ($status !== 401 && $status !== 403) {
                return [
                    'success' => true,
                    'message' => 'Koneksi Midtrans Berhasil! Server Key Valid (Mode: ' . ($this->isProduction ? 'PRODUCTION' : 'SANDBOX') . ').',
                ];
            }

            return ['success' => false, 'message' => 'Gagal koneksi Midtrans: Server Key tidak valid atau mode salah (HTTP ' . $status . ').'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal terhubung ke server Midtrans: ' . $e->getMessage()];
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone ?: '6281234567890';
    }
}
