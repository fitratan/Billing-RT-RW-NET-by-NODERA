<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TripayService
{
    private string $apiKey;
    private string $privateKey;
    private string $merchantCode;
    private string $mode;
    private string $baseUrl;
    private Client $httpClient;

    /**
     * @param string|null $tenantId Resolve keys for a specific tenant (e.g. webhook callbacks).
     *                              When null, uses Setting::apiValue() (active tenant session or env).
     * @param array|null $customConfig Direct credentials override from request/hotspot config.
     */
    public function __construct(?string $tenantId = null, ?array $customConfig = null)
    {
        if (is_array($customConfig)) {
            if (isset($customConfig['tripay']) && is_array($customConfig['tripay'])) {
                $customConfig = array_merge($customConfig, $customConfig['tripay']);
            }
            if (isset($customConfig['gateway_config']) && is_array($customConfig['gateway_config'])) {
                $customConfig = array_merge($customConfig, $customConfig['gateway_config']);
                if (isset($customConfig['tripay']) && is_array($customConfig['tripay'])) {
                    $customConfig = array_merge($customConfig, $customConfig['tripay']);
                }
            }
        }

        $gwRecord = \App\Models\PaymentGateway::withoutGlobalScopes()
            ->where('gateway', 'tripay')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->orderByRaw('tenant_id IS NULL ASC')
            ->first();
        $gwCfg = $gwRecord?->config_json ?? [];

        $this->apiKey = trim((string) (
            $customConfig['api_key']
            ?? $customConfig['TRIPAY_API_KEY']
            ?? $customConfig['np_tripay_api_key']
            ?? $customConfig['tripay_api_key']
            ?? $gwCfg['TRIPAY_API_KEY']
            ?? $gwCfg['api_key']
            ?? ($tenantId ? Setting::where('key', 'TRIPAY_API_KEY')->where('tenant_id', $tenantId)->value('value') : Setting::apiValue('TRIPAY_API_KEY', ''))
            ?? env('TRIPAY_API_KEY', '')
        ));

        $this->privateKey = trim((string) (
            $customConfig['private_key']
            ?? $customConfig['TRIPAY_PRIVATE_KEY']
            ?? $customConfig['np_tripay_private_key']
            ?? $customConfig['tripay_private_key']
            ?? $gwCfg['TRIPAY_PRIVATE_KEY']
            ?? $gwCfg['private_key']
            ?? ($tenantId ? Setting::where('key', 'TRIPAY_PRIVATE_KEY')->where('tenant_id', $tenantId)->value('value') : Setting::apiValue('TRIPAY_PRIVATE_KEY', ''))
            ?? env('TRIPAY_PRIVATE_KEY', '')
        ));

        $this->merchantCode = trim((string) (
            $customConfig['merchant_code']
            ?? $customConfig['code_merchant']
            ?? $customConfig['TRIPAY_MERCHANT_CODE']
            ?? $customConfig['np_tripay_merchant_code']
            ?? $customConfig['tripay_merchant_code']
            ?? $gwCfg['TRIPAY_MERCHANT_CODE']
            ?? $gwCfg['merchant_code']
            ?? ($tenantId ? Setting::where('key', 'TRIPAY_MERCHANT_CODE')->where('tenant_id', $tenantId)->value('value') : Setting::apiValue('TRIPAY_MERCHANT_CODE', ''))
            ?? env('TRIPAY_MERCHANT_CODE', '')
        ));

        $autoMode = 'production';
        if (str_starts_with($this->apiKey, 'DEV-')) {
            $autoMode = 'sandbox';
        }

        $this->mode = strtolower(trim((string) (
            $customConfig['mode']
            ?? $customConfig['gateway_env']
            ?? $customConfig['np_gateway_env']
            ?? $customConfig['TRIPAY_MODE']
            ?? $gwCfg['TRIPAY_MODE']
            ?? $gwCfg['mode']
            ?? ($tenantId ? Setting::where('key', 'TRIPAY_MODE')->where('tenant_id', $tenantId)->value('value') : Setting::apiValue('TRIPAY_MODE', $autoMode))
            ?? env('TRIPAY_MODE', $autoMode)
        )));

        $this->baseUrl = ($this->mode === 'production' || $this->mode === 'live')
            ? 'https://tripay.co.id/api/'
            : 'https://tripay.co.id/api-sandbox/';

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => true,
            'http_errors' => false,
            'timeout' => 20,
        ]);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->privateKey) && !empty($this->merchantCode);
    }

    /**
     * Get available payment channels.
     */
    public function getChannels(): array
    {
        return $this->request('GET', 'merchant/payment-channel');
    }

    /**
     * Create a transaction.
     *
     * Required $invoiceData keys:
     * - method        (string) Payment channel code, e.g. BRIVA
     * - merchant_ref  (string) Unique invoice reference
     * - amount        (int)    Transaction amount
     * - customer_name (string)
     * - customer_email(string)
     * - customer_phone(string)
     * - order_items   (array)  List of items
     * - return_url    (string) Redirect URL after payment
     */
    public function createTransaction(array $invoiceData): array
    {
        $data = [
            'method'         => $invoiceData['method'],
            'merchant_ref'   => $invoiceData['merchant_ref'],
            'amount'         => (int) $invoiceData['amount'],
            'customer_name'  => $invoiceData['customer_name'],
            'customer_email' => $invoiceData['customer_email'],
            'customer_phone' => $invoiceData['customer_phone'],
            'order_items'    => $invoiceData['order_items'],
            'callback_url'   => $invoiceData['callback_url'] ?? url('/api/v1/noderapay/webhook/tripay'),
            'return_url'     => $invoiceData['return_url'] ?? ($invoiceData['callback_url'] ?? url('/')),
            'expired_time'   => now()->addHours(24)->timestamp,
            'signature'      => $this->createSignature($invoiceData['merchant_ref'], (int) $invoiceData['amount']),
        ];

        return $this->request('POST', 'transaction/create', $data);
    }

    /**
     * Get transaction detail by reference.
     */
    public function detailTransaction(string $reference): array
    {
        return $this->request('GET', 'transaction/detail', ['reference' => $reference]);
    }

    /**
     * Create HMAC-SHA256 signature for transaction request.
     */
    private function createSignature(string $merchantRef, int $amount): string
    {
        return hash_hmac('sha256', $this->merchantCode . $merchantRef . $amount, $this->privateKey);
    }

    /**
     * Validate an incoming callback signature.
     */
    public function validateCallback(string $jsonBody, string $signature): bool
    {
        $calculated = hash_hmac('sha256', $jsonBody, $this->privateKey);

        return hash_equals($signature, $calculated);
    }

    /**
     * Send an HTTP request to the Tripay API via Guzzle.
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $options = [];

            if ($method === 'GET' && !empty($payload)) {
                $options['query'] = $payload;
            } elseif ($method === 'POST') {
                $options['json'] = $payload;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'message' => 'Failed to parse Tripay response (HTTP ' . $statusCode . '): ' . substr($body, 0, 200),
                ];
            }

            if ($statusCode >= 400 && !isset($data['success'])) {
                $data['success'] = false;
            }

            return $data;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Tripay Connection Error: ' . $e->getMessage(),
            ];
        }
    }
}
