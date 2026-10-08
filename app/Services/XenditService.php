<?php

namespace App\Services;

use App\Models\PaymentGateway;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class XenditService
{
    private object $config;

    public function __construct(?string $tenantId = null)
    {
        $query = PaymentGateway::where('gateway', 'xendit');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $this->config = (object) ($query->first()?->config_json ?? []);
    }

    public function isConfigured(): bool
    {
        return !empty($this->config->XENDIT_API_KEY) || !empty($this->config->XENDIT_SECRET_KEY);
    }

    public function isActive(string $gateway = 'xendit'): bool
    {
        return PaymentGateway::where('gateway', $gateway)->where('is_active', true)->exists();
    }

    /**
     * Create a Xendit Invoice (checkout link).
     *
     * @param array $data invoice, customer + order params
     * @return array{success: bool, link: string, reference: string, order_id: string, message?: string}
     */
    public function createInvoice(array $data): array
    {
        $invoice = $data['invoice'];
        $customer = $data['customer'];
        $method = $data['method'] ?? 'xendit';
        $prefix = strtoupper($data['order_prefix'] ?? 'INV');

        $apiKey = trim((string) ($this->config->XENDIT_API_KEY ?? ''));
        if (!$apiKey) {
            throw new \RuntimeException('Xendit API Key belum diatur.');
        }

        $orderId = $prefix . '-' . $invoice->id . '-' . ($data['transaction_id'] ?? time());

        $itemName = trim((string) ($data['item_name'] ?? '')) ?: ('Pembayaran Tagihan #' . ($invoice->invoice_number ?? $invoice->id));
        $description = trim((string) ($data['description'] ?? '')) ?: $itemName;
        $returnPath = $data['return_path'] ?? '/portal/dashboard';

        $payload = [
            'external_id' => $orderId,
            'amount' => (int) $invoice->amount,
            'description' => substr($description, 0, 120),
            'invoice_duration' => 86400,
            'customer' => [
                'given_names' => $customer->name ?? 'Pelanggan',
                'email' => $customer->email ?? '',
                'mobile_number' => $this->normalizePhone($customer->phone ?? ''),
            ],
            'success_redirect_url' => url($returnPath),
            'failure_redirect_url' => url($returnPath),
            'currency' => 'IDR',
            'items' => [
                [
                    'name' => substr($itemName, 0, 100),
                    'quantity' => 1,
                    'price' => (int) $invoice->amount,
                ],
            ],
        ];

        if ($method !== 'xendit') {
            $map = [
                'QRIS' => ['QRIS'],
                'MANDIRIVA' => ['MANDIRI'],
                'BRIVA' => ['BRI'],
                'BNIVA' => ['BNI'],
                'BCAVA' => ['BCA'],
                'PERMATAVA' => ['PERMATA'],
            ];
            if (isset($map[$method])) {
                $payload['payment_methods'] = $map[$method];
            }
        }

        $auth = base64_encode($apiKey . ':');
        $client = new Client();
        $response = $client->post('https://api.xendit.co/v2/invoices', [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $body = json_decode($response->getBody(), true);

        return [
            'success' => true,
            'link' => $body['invoice_url'] ?? '',
            'reference' => $body['id'] ?? $orderId,
            'order_id' => $orderId,
        ];
    }

    /**
     * Get Xendit invoice status by invoice ID.
     *
     * @return array{success: bool, status?: string, is_paid?: bool, data?: array, message?: string}
     */
    public function getInvoiceStatus(string $invoiceId): array
    {
        $apiKey = trim((string) ($this->config->XENDIT_API_KEY ?? ''));
        if (!$apiKey) {
            return ['success' => false, 'message' => 'Xendit API Key not configured'];
        }

        $auth = base64_encode($apiKey . ':');
        try {
            $client = new Client();
            $response = $client->get('https://api.xendit.co/v2/invoices/' . $invoiceId, [
                'headers' => [
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 10,
                'verify'  => true,
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $status = strtoupper((string) ($body['status'] ?? ''));

            return [
                'success' => true,
                'status'  => $status,
                'is_paid' => in_array($status, ['PAID', 'SETTLED']),
                'data'    => $body,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a Xendit webhook using the x-callback-token header.
     */
    public function verifyWebhook(string $receivedToken): bool
    {
        $expected = trim((string) ($this->config->XENDIT_CALLBACK_TOKEN ?? ''));
        if (!$expected) {
            Log::warning('[Xendit] Callback token not configured');
            return false;
        }
        return hash_equals($expected, $receivedToken);
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