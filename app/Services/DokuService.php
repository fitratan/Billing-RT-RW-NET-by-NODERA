<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DokuService
{
    private string $clientId;
    private string $secretKey;
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
            $this->clientId = Setting::where('key', 'DOKU_CLIENT_ID')->where('tenant_id', $tenantId)->value('value')
                ?? Setting::where('key', 'JOKUL_CLIENT_ID')->where('tenant_id', $tenantId)->value('value') ?? '';
            $this->secretKey = Setting::where('key', 'DOKU_SECRET_KEY')->where('tenant_id', $tenantId)->value('value')
                ?? Setting::where('key', 'JOKUL_SECRET_KEY')->where('tenant_id', $tenantId)->value('value') ?? '';
            $this->mode = Setting::where('key', 'DOKU_MODE')->where('tenant_id', $tenantId)->value('value')
                ?? Setting::where('key', 'JOKUL_MODE')->where('tenant_id', $tenantId)->value('value') ?? 'sandbox';
        } else {
            $this->clientId = Setting::apiValue('DOKU_CLIENT_ID', '') ?: Setting::apiValue('JOKUL_CLIENT_ID', '');
            $this->secretKey = Setting::apiValue('DOKU_SECRET_KEY', '') ?: Setting::apiValue('JOKUL_SECRET_KEY', '');
            $this->mode = Setting::apiValue('DOKU_MODE', 'sandbox') ?: Setting::apiValue('JOKUL_MODE', 'sandbox');
        }

        $this->baseUrl = ($this->mode === 'production' || $this->mode === 'live')
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com';

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 15,
            'verify'   => true,
        ]);
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->secretKey);
    }

    public function isProduction(): bool
    {
        return in_array(strtolower($this->mode), ['production', 'live']);
    }

    public function getJsUrl(): string
    {
        return $this->isProduction()
            ? 'https://jokul.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js'
            : 'https://sandbox.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js';
    }

    /**
     * Generate HMAC-SHA256 signature according to DOKU Checkout specifications.
     */
    public function generateSignature(string $requestId, string $timestamp, string $targetPath, string $jsonBody): string
    {
        $digest = base64_encode(hash('sha256', $jsonBody, true));
        $component = "Client-Id:" . $this->clientId . "\n"
                   . "Request-Id:" . $requestId . "\n"
                   . "Request-Timestamp:" . $timestamp . "\n"
                   . "Request-Target:" . $targetPath . "\n"
                   . "Digest:" . $digest;

        return 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $this->secretKey, true));
    }

    /**
     * Create DOKU Checkout payment session.
     *
     * @param array $payload
     * @return array
     */
    public function createPayment(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('DOKU payment gateway is not configured.');
        }

        $orderId = (string) ($payload['order_id'] ?? ('INV-' . time()));
        $amount = (int) ($payload['amount'] ?? 0);
        $customerName = (string) ($payload['customer_name'] ?? 'Pelanggan');
        $customerEmail = (string) ($payload['customer_email'] ?? 'customer@dgtlnetsolution.com');
        $customerPhone = $this->normalizePhone((string) ($payload['customer_phone'] ?? '081234567890'));
        $callbackUrl = (string) ($payload['callback_url'] ?? url('/portal/payment'));

        $requestBody = [
            'order' => [
                'invoice_number' => $orderId,
                'amount'         => $amount,
                'callback_url'   => $callbackUrl,
                'line_items'     => [
                    [
                        'name'     => substr((string) ($payload['item_name'] ?? 'Pembayaran Layanan Internet'), 0, 64),
                        'price'    => $amount,
                        'quantity' => 1,
                    ]
                ],
            ],
            'payment' => [
                'payment_due_date' => (int) ($payload['payment_due_date'] ?? 60), // minutes
            ],
            'customer' => [
                'id'    => (string) ($payload['customer_id'] ?? ('CUST-' . substr(md5($customerEmail), 0, 8))),
                'name'  => substr($customerName, 0, 64),
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
        ];

        $jsonBody = json_encode($requestBody, JSON_UNESCAPED_SLASHES);
        $requestId = (string) Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $targetPath = '/checkout/v1/payment';

        $signature = $this->generateSignature($requestId, $timestamp, $targetPath, $jsonBody);

        try {
            $response = $this->httpClient->post($targetPath, [
                'headers' => [
                    'Client-Id'         => $this->clientId,
                    'Request-Id'        => $requestId,
                    'Request-Timestamp' => $timestamp,
                    'Signature'         => $signature,
                    'Content-Type'      => 'application/json',
                ],
                'body' => $jsonBody,
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $paymentUrl = $body['response']['payment']['url'] ?? ($body['payment']['url'] ?? '');
            $tokenId = $body['response']['payment']['token_id'] ?? ($body['payment']['token_id'] ?? '');
            $expiredDate = $body['response']['payment']['expired_date'] ?? ($body['payment']['expired_date'] ?? '');

            if (empty($paymentUrl)) {
                throw new \RuntimeException('DOKU did not return a valid payment URL.');
            }

            return [
                'success'      => true,
                'checkout_url' => $paymentUrl,
                'payment_url'  => $paymentUrl,
                'token_id'     => $tokenId,
                'expired_date' => $expiredDate,
                'js_url'       => $this->getJsUrl(),
                'order_id'     => $orderId,
                'reference'    => $tokenId ?: $orderId,
            ];
        } catch (\Throwable $e) {
            Log::error('[DokuService] createPayment error: ' . $e->getMessage());
            throw new \RuntimeException('Gagal menghubungkan ke DOKU: ' . $e->getMessage());
        }
    }

    /**
     * Check transaction status directly from DOKU API.
     */
    public function checkTransactionStatus(string $invoiceNumber): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'DOKU is not configured'];
        }

        $requestId = (string) Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $targetPath = '/orders/v1/status/' . $invoiceNumber;

        $component = "Client-Id:" . $this->clientId . "\n"
                   . "Request-Id:" . $requestId . "\n"
                   . "Request-Timestamp:" . $timestamp . "\n"
                   . "Request-Target:" . $targetPath;

        $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $this->secretKey, true));

        try {
            $response = $this->httpClient->get($targetPath, [
                'headers' => [
                    'Client-Id'         => $this->clientId,
                    'Request-Id'        => $requestId,
                    'Request-Timestamp' => $timestamp,
                    'Signature'         => $signature,
                    'Content-Type'      => 'application/json',
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $txStatus = strtoupper((string) ($body['transaction']['status'] ?? ($body['status'] ?? '')));

            return [
                'success' => true,
                'status'  => $txStatus,
                'is_paid' => in_array($txStatus, ['SUCCESS', 'PAID', 'SETTLEMENT']),
                'data'    => $body,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Validate incoming DOKU HTTP notification webhook.
     */
    public function verifyWebhook(string $rawBody, array $headers, string $requestPath = '/webhook/doku'): bool
    {
        if (empty($this->secretKey)) {
            Log::warning('[DokuService] Secret key not configured for webhook verification');
            return false;
        }

        $headerSignature = $headers['signature'][0] ?? ($headers['Signature'][0] ?? ($headers['Signature'] ?? ($headers['signature'] ?? '')));
        $headerClientId = $headers['client-id'][0] ?? ($headers['Client-Id'][0] ?? ($headers['Client-Id'] ?? ($headers['client-id'] ?? '')));
        $headerRequestId = $headers['request-id'][0] ?? ($headers['Request-Id'][0] ?? ($headers['Request-Id'] ?? ($headers['request-id'] ?? '')));
        $headerTimestamp = $headers['request-timestamp'][0] ?? ($headers['Request-Timestamp'][0] ?? ($headers['Request-Timestamp'] ?? ($headers['request-timestamp'] ?? '')));

        if (empty($headerSignature)) {
            Log::warning('[DokuService] Missing signature header in webhook');
            return false;
        }

        $digest = base64_encode(hash('sha256', $rawBody, true));
        $component = "Client-Id:" . $headerClientId . "\n"
                   . "Request-Id:" . $headerRequestId . "\n"
                   . "Request-Timestamp:" . $headerTimestamp . "\n"
                   . "Request-Target:" . $requestPath . "\n"
                   . "Digest:" . $digest;

        $expected = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $this->secretKey, true));

        if (!hash_equals($expected, (string) $headerSignature)) {
            Log::warning('[DokuService] Signature mismatch in webhook', [
                'expected' => $expected,
                'received' => $headerSignature,
            ]);
            return false;
        }

        return true;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) >= 10 && $phone[0] === '0') {
            $phone = '62' . substr($phone, 1);
        }
        return $phone ?: '6281234567890';
    }
}
