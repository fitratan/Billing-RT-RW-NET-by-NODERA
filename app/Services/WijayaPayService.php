<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WijayaPayService
{
    private string $codeMerchant;
    private string $apiKey;
    private string $mode;
    private string $baseUrl;
    private ?string $tenantId;

    /**
     * @param string|null $tenantId Resolve keys for a specific tenant (e.g. webhook callbacks).
     *                              When null, uses PaymentGateway / Setting / active tenant session.
     * @param array|null $customConfig Optional custom credentials override.
     */
    public function __construct(?string $tenantId = null, ?array $customConfig = null)
    {
        $this->tenantId = $tenantId;

        $upstreamGw = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', 'noderapay_upstream')
            ->first();
        $upConfig = $upstreamGw?->config_json ?? [];

        $globalGw = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', 'wijayapay')
            ->first();
        $globalCfg = $globalGw?->config_json ?? [];

        $fallbackCode = trim((string) (
            $globalCfg['WIJAYAPAY_CODE_MERCHANT']
            ?? $globalCfg['code_merchant']
            ?? $globalCfg['merchant_code']
            ?? $upConfig['wijayapay_code_merchant']
            ?? $upConfig['code_merchant']
            ?? Setting::apiValue('WIJAYAPAY_CODE_MERCHANT', '')
            ?? env('WIJAYAPAY_CODE_MERCHANT', '')
        ));

        $fallbackKey = trim((string) (
            $globalCfg['WIJAYAPAY_API_KEY']
            ?? $globalCfg['api_key']
            ?? $upConfig['wijayapay_api_key']
            ?? $upConfig['api_key']
            ?? Setting::apiValue('WIJAYAPAY_API_KEY', '')
            ?? env('WIJAYAPAY_API_KEY', '')
        ));

        $fallbackMode = $globalCfg['WIJAYAPAY_MODE']
            ?? $globalCfg['mode']
            ?? $upConfig['wijayapay_mode']
            ?? Setting::apiValue('WIJAYAPAY_MODE', 'production');

        if (!empty($customConfig['code_merchant']) || !empty($customConfig['WIJAYAPAY_CODE_MERCHANT'])) {
            $this->codeMerchant = $customConfig['WIJAYAPAY_CODE_MERCHANT'] ?? ($customConfig['code_merchant'] ?? '');
            $this->apiKey       = $customConfig['WIJAYAPAY_API_KEY'] ?? ($customConfig['api_key'] ?? '');
            $this->mode         = $customConfig['WIJAYAPAY_MODE'] ?? ($customConfig['mode'] ?? 'production');
        } elseif ($tenantId) {
            $gw = PaymentGateway::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('gateway', 'wijayapay')
                ->first();

            $cfg = $gw?->config_json ?? [];
            $tenantCode = trim((string) ($cfg['WIJAYAPAY_CODE_MERCHANT'] ?? ($cfg['code_merchant'] ?? ($cfg['merchant_code'] ?? ''))));
            $tenantKey  = trim((string) ($cfg['WIJAYAPAY_API_KEY'] ?? ($cfg['api_key'] ?? '')));

            if (!empty($tenantCode) && !empty($tenantKey)) {
                $this->codeMerchant = $tenantCode;
                $this->apiKey       = $tenantKey;
                $this->mode         = $cfg['WIJAYAPAY_MODE'] ?? ($cfg['mode'] ?? 'production');
            } else {
                // Fallback to superadmin global / upstream config
                $this->codeMerchant = $fallbackCode;
                $this->apiKey       = $fallbackKey;
                $this->mode         = $fallbackMode;
            }
        } else {
            $gw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'wijayapay')->first();
            $cfg = $gw?->config_json ?? [];

            $this->codeMerchant = trim((string) ($cfg['WIJAYAPAY_CODE_MERCHANT'] ?? ($cfg['code_merchant'] ?? ($cfg['merchant_code'] ?? $fallbackCode))));
            $this->apiKey       = trim((string) ($cfg['WIJAYAPAY_API_KEY'] ?? ($cfg['api_key'] ?? $fallbackKey)));
            $this->mode         = $cfg['WIJAYAPAY_MODE'] ?? ($cfg['mode'] ?? $fallbackMode);
        }

        $this->codeMerchant = trim($this->codeMerchant);
        $this->apiKey       = trim($this->apiKey);
        $this->baseUrl      = 'https://gateway.wijayapay.com/api';
    }

    public function isConfigured(): bool
    {
        return !empty($this->codeMerchant) && !empty($this->apiKey);
    }

    /**
     * Generate signature for API request and webhook validation.
     * Formula: md5(code_merchant . api_key . ref_id)
     */
    public function generateSignature(string $refId): string
    {
        return md5($this->codeMerchant . $this->apiKey . $refId);
    }

    /**
     * Get available active payment channels from WijayaPay.
     */
    public function getChannels(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'WijayaPay belum dikonfigurasi (Code Merchant & API Key wajib diisi).'];
        }

        try {
            $res = Http::timeout(12)
                ->get("{$this->baseUrl}/get-payment", [
                    'code_merchant' => $this->codeMerchant,
                    'api_key'       => $this->apiKey,
                ]);

            return $res->json() ?? ['success' => false, 'message' => 'Respon WijayaPay kosong.'];
        } catch (\Exception $e) {
            Log::error('[WijayaPay] Get Channels Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal terhubung ke WijayaPay: ' . $e->getMessage()];
        }
    }

    /**
     * Create a payment transaction (QRIS / Virtual Account / Retail).
     *
     * @param array $params [
     *    'ref_id'       => string (required),
     *    'nominal'      => int (required),
     *    'code_payment' => string (optional, default 'QRIS'),
     *    'callback_url' => string (optional),
     * ]
     */
    public function createTransaction(array $params): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'WijayaPay belum dikonfigurasi.'];
        }

        $refId = trim($params['ref_id'] ?? ('ORD-' . date('ymdHis') . rand(100, 999)));
        $nominal = (int) ($params['nominal'] ?? ($params['amount'] ?? 0));
        $codePayment = trim($params['code_payment'] ?? ($params['channel'] ?? 'QRIS'));
        $callbackUrl = $params['callback_url'] ?? url('/api/webhook/wijayapay');

        if ($nominal <= 0) {
            return ['success' => false, 'message' => 'Nominal transaksi tidak valid.'];
        }

        $signature = $this->generateSignature($refId);

        $payload = [
            'code_merchant' => $this->codeMerchant,
            'api_key'       => $this->apiKey,
            'code_payment'  => $codePayment,
            'ref_id'        => $refId,
            'nominal'       => $nominal,
            'callback_url'  => $callbackUrl,
        ];

        try {
            $res = Http::asForm()
                ->timeout(15)
                ->withHeaders([
                    'X-Signature' => $signature,
                    'User-Agent'  => 'Nodera-Billing/1.0',
                ])
                ->post("{$this->baseUrl}/transaction/create", $payload);

            $body = $res->json();

            if ($res->successful() && ($body['success'] ?? false)) {
                return [
                    'success'       => true,
                    'data'          => $body['data'] ?? [],
                    'qr_string'     => $body['data']['qr_string'] ?? '',
                    'qr_image'      => $body['data']['qr_image'] ?? '',
                    'trx_reference' => $body['data']['trx_reference'] ?? '',
                    'ref_id'        => $body['data']['ref_id'] ?? $refId,
                    'total_bayar'   => $body['data']['total_bayar'] ?? $nominal,
                    'expired'       => $body['data']['expired'] ?? null,
                ];
            }

            $errMsg = $body['message'] ?? ($body['error'] ?? 'Gagal membuat transaksi di WijayaPay (HTTP ' . $res->status() . ')');
            Log::error('[WijayaPay] Create Transaction Error: ' . $errMsg, ['payload' => $payload, 'response' => $body]);
            return ['success' => false, 'message' => $errMsg];
        } catch (\Exception $e) {
            Log::error('[WijayaPay] Create Transaction Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Koneksi ke WijayaPay gagal: ' . $e->getMessage()];
        }
    }

    /**
     * Check transaction status by ref_id.
     */
    public function checkStatus(string $refId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'WijayaPay belum dikonfigurasi.'];
        }

        try {
            $res = Http::timeout(10)
                ->get("{$this->baseUrl}/get-status", [
                    'code_merchant' => $this->codeMerchant,
                    'api_key'       => $this->apiKey,
                    'ref_id'        => $refId,
                ]);

            $json = $res->json();
            $dataPayload = $json['data'] ?? $json;
            $rawStatus = $dataPayload['status_pembayaran']
                ?? ($json['status_pembayaran']
                ?? ($dataPayload['status']
                ?? ($json['status'] ?? '')));

            if (is_bool($rawStatus) || $rawStatus === '1' || $rawStatus === 'true') {
                $rawStatus = $dataPayload['status_pembayaran'] ?? ($json['status_pembayaran'] ?? ($dataPayload['status'] ?? 'pending'));
            }

            if ($res->successful() && !empty($rawStatus)) {
                return [
                    'success'           => true,
                    'status_pembayaran' => strtolower((string) $rawStatus),
                    'data'              => $dataPayload,
                ];
            }

            return ['success' => false, 'message' => $json['message'] ?? 'Gagal mendapatkan status dari WijayaPay.'];
        } catch (\Exception $e) {
            Log::error('[WijayaPay] Check Status Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal mengecek status WijayaPay: ' . $e->getMessage()];
        }
    }

    /**
     * Validate incoming webhook callback.
     */
    public function validateCallback(string $refId, ?string $incomingSignature): bool
    {
        if (empty($incomingSignature) || empty($refId)) {
            return false;
        }

        $expected = $this->generateSignature($refId);
        return hash_equals(strtolower($expected), strtolower($incomingSignature));
    }

    /**
     * Test connection to WijayaPay API.
     */
    public function testConnection(?string $codeMerchant = null, ?string $apiKey = null): array
    {
        $code = trim($codeMerchant ?: $this->codeMerchant);
        $key  = trim($apiKey ?: $this->apiKey);

        if (empty($code) || empty($key)) {
            return ['success' => false, 'message' => 'Code Merchant dan API Key WijayaPay wajib diisi.'];
        }

        try {
            $res = Http::timeout(10)->get("https://gateway.wijayapay.com/api/get-payment", [
                'code_merchant' => $code,
                'api_key'       => $key,
            ]);

            $json = $res->json();
            if ($res->successful() && ($json['success'] ?? false)) {
                $count = count($json['data'] ?? []);
                return [
                    'success' => true,
                    'message' => "Koneksi WijayaPay Berhasil! Akun terverifikasi ({$count} channel pembayaran aktif).",
                ];
            }

            $msg = $json['message'] ?? ($json['error'] ?? 'Kredensial WijayaPay tidak valid.');
            return ['success' => false, 'message' => "Gagal koneksi WijayaPay: {$msg}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal terhubung ke WijayaPay: ' . $e->getMessage()];
        }
    }
}
