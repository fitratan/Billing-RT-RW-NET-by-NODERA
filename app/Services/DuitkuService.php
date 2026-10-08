<?php

namespace App\Services;

use App\Models\PaymentGateway;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class DuitkuService
{
    private object $config;

    public function __construct(?string $tenantId = null)
    {
        $query = PaymentGateway::where('gateway', 'duitku');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $this->config = (object) ($query->first()?->config_json ?? []);
    }

    public function isConfigured(): bool
    {
        return !empty($this->config->DUITKU_MERCHANT_CODE) && !empty($this->config->DUITKU_API_KEY);
    }

    public function isActive(string $gateway = 'duitku'): bool
    {
        return PaymentGateway::where('gateway', $gateway)->where('is_active', true)->exists();
    }

    /**
     * Create a Duitku transaction (checkout link via inquiry).
     *
     * @return array{success: bool, link: string, reference: string, order_id: string, message?: string}
     */
    public function createTransaction(array $data): array
    {
        $invoice = $data['invoice'] ?? null;
        $customer = $data['customer'] ?? null;
        $method = $data['method'] ?? 'duitku';
        $prefix = strtoupper($data['order_prefix'] ?? 'INV');

        $merchantCode = trim((string) ($this->config->DUITKU_MERCHANT_CODE ?? ''));
        $apiKey = trim((string) ($this->config->DUITKU_API_KEY ?? ''));
        if (!$merchantCode || !$apiKey) {
            throw new \RuntimeException('Duitku Merchant Code atau API Key belum diatur.');
        }

        $mode = strtolower((string) ($this->config->DUITKU_MODE ?? 'sandbox'));
        $baseUrl = in_array($mode, ['live', 'production'])
            ? 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
            : 'https://passport-sandbox.duitku.com/webapi/api/merchant/v2/inquiry';

        $orderId = $data['ref_id'] ?? ($invoice ? ($prefix . '-' . $invoice->id . '-' . ($data['transaction_id'] ?? time())) : ('PLT-' . time()));
        $amount = (int) ($data['amount'] ?? ($invoice?->amount ?? 0));

        $productDetails = trim((string) ($data['item_name'] ?? '')) ?: ('Tagihan ' . ($invoice?->invoice_number ?? ($invoice?->id ?? $orderId)));
        $callbackPath = $data['callback_path'] ?? '/webhook/duitku';
        $returnPath = $data['return_path'] ?? '/portal/dashboard';

        $customerEmail = $data['customer_email'] ?? ($customer?->email ?? '');
        $customerPhone = $data['customer_phone'] ?? ($customer?->phone ?? '');
        $customerName = $data['customer_name'] ?? ($customer?->name ?? 'Pelanggan');

        $signature = md5($merchantCode . $orderId . $amount . $apiKey);

        $payload = [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'merchantOrderId' => $orderId,
            'productDetails' => substr($productDetails, 0, 255),
            'email' => $customerEmail,
            'phoneNumber' => $this->normalizePhone($customerPhone),
            'customerVaName' => substr($customerName, 0, 50),
            'callbackUrl' => url($callbackPath),
            'returnUrl' => url($returnPath),
            'signature' => $signature,
            'expiryPeriod' => 1440,
        ];

        $map = [
            'QRIS' => 'DQ',
            'MANDIRIVA' => 'M2',
            'BRIVA' => 'BR',
            'BNIVA' => 'I1',
            'BCAVA' => 'BC',
            'PERMATAVA' => 'BT',
        ];
        $methodKey = strtoupper((string) $method);
        $payload['paymentMethod'] = $map[$methodKey] ?? 'DQ';

        $client = new Client();
        $response = $client->post($baseUrl, ['json' => $payload, 'verify' => true]);
        $body = json_decode($response->getBody(), true);

        if (!empty($body['paymentUrl'])) {
            return [
                'success' => true,
                'link' => $body['paymentUrl'],
                'reference' => $body['reference'] ?? $orderId,
                'order_id' => $orderId,
            ];
        }

        throw new \RuntimeException($body['statusMessage'] ?? 'Gagal mendapatkan payment URL dari Duitku');
    }

    /**
     * Check transaction status from Duitku API.
     *
     * @return array{success: bool, status_code?: string, status_message?: string, data?: array}
     */
    public function checkTransactionStatus(string $merchantOrderId): array
    {
        $merchantCode = trim((string) ($this->config->DUITKU_MERCHANT_CODE ?? ''));
        $apiKey = trim((string) ($this->config->DUITKU_API_KEY ?? ''));
        if (!$merchantCode || !$apiKey) {
            return ['success' => false, 'message' => 'Duitku config missing'];
        }

        $mode = strtolower((string) ($this->config->DUITKU_MODE ?? 'sandbox'));
        $baseUrl = in_array($mode, ['live', 'production'])
            ? 'https://passport.duitku.com/webapi/api/merchant/transactionStatus'
            : 'https://passport-sandbox.duitku.com/webapi/api/merchant/transactionStatus';

        $signature = md5($merchantCode . $merchantOrderId . $apiKey);

        try {
            $client = new Client();
            $response = $client->post($baseUrl, [
                'json' => [
                    'merchantCode'    => $merchantCode,
                    'merchantOrderId' => $merchantOrderId,
                    'signature'       => $signature,
                ],
                'timeout' => 10,
                'verify'  => true,
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $statusCode = (string) ($body['statusCode'] ?? '');

            return [
                'success'        => true,
                'status_code'    => $statusCode,
                'status_message' => $body['statusMessage'] ?? '',
                'is_paid'        => ($statusCode === '00'),
                'data'           => $body,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a Duitku webhook signature: md5(merchantCode + amount + merchantOrderId + apiKey).
     */
    public function verifyWebhook(array $body): bool
    {
        $merchantCode = trim((string) ($this->config->DUITKU_MERCHANT_CODE ?? ''));
        $apiKey = trim((string) ($this->config->DUITKU_API_KEY ?? ''));
        if (!$merchantCode || !$apiKey) {
            Log::warning('[Duitku] API key not configured for webhook verify');
            return false;
        }

        $signature = (string) ($body['signature'] ?? '');
        $amount = (string) ($body['amount'] ?? '');
        $merchantOrderId = (string) ($body['merchantOrderId'] ?? '');

        $expected = md5($merchantCode . $amount . $merchantOrderId . $apiKey);

        return hash_equals($expected, strtolower($signature));
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) >= 10 && $phone[0] === '0') {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}