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
     */
    public function __construct(?string $tenantId = null)
    {
        if ($tenantId) {
            $this->apiKey = Setting::where('key', 'TRIPAY_API_KEY')->where('tenant_id', $tenantId)->value('value') ?? '';
            $this->privateKey = Setting::where('key', 'TRIPAY_PRIVATE_KEY')->where('tenant_id', $tenantId)->value('value') ?? '';
            $this->merchantCode = Setting::where('key', 'TRIPAY_MERCHANT_CODE')->where('tenant_id', $tenantId)->value('value') ?? '';
            $this->mode = Setting::where('key', 'TRIPAY_MODE')->where('tenant_id', $tenantId)->value('value') ?? 'sandbox';
        } else {
            $this->apiKey = Setting::apiValue('TRIPAY_API_KEY', '');
            $this->privateKey = Setting::apiValue('TRIPAY_PRIVATE_KEY', '');
            $this->merchantCode = Setting::apiValue('TRIPAY_MERCHANT_CODE', '');
            $this->mode = Setting::apiValue('TRIPAY_MODE', 'sandbox');
        }

        $this->baseUrl = ($this->mode === 'production')
            ? 'https://tripay.co.id/api/'
            : 'https://tripay.co.id/api-sandbox/';

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'verify' => true,
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
            'amount'         => $invoiceData['amount'],
            'customer_name'  => $invoiceData['customer_name'],
            'customer_email' => $invoiceData['customer_email'],
            'customer_phone' => $invoiceData['customer_phone'],
            'order_items'    => $invoiceData['order_items'],
            'callback_url'   => url('/webhook/payment'),
            'return_url'     => $invoiceData['return_url'],
            'expired_time'   => now()->addHours(24)->timestamp,
            'signature'      => $this->createSignature($invoiceData['merchant_ref'], $invoiceData['amount']),
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
                $options['form_params'] = $payload;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true) ?? [
                'success' => false,
                'message' => 'Failed to parse response',
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => 'HTTP Error: ' . $e->getMessage(),
            ];
        }
    }
}
