<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $serverKey = 'SB-Mid-server-DUMMY_KEY';
    private string $clientKey = 'SB-Mid-client-DUMMY_KEY';

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::setConfig('midtrans', [
            'MIDTRANS_SERVER_KEY' => $this->serverKey,
            'MIDTRANS_CLIENT_KEY' => $this->clientKey,
            'MIDTRANS_MODE'       => 'sandbox',
        ]);
    }

    public function test_can_process_midtrans_payment_for_invoice(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Ahmad Midtrans',
            'phone' => '081299998888',
        ]);

        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-2026-MID-001',
            'amount'         => 250000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(7),
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token'        => 'snap-token-abc-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-abc-123',
            ], 201),
        ]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->processMidtrans($invoice, 'midtrans');

        $this->assertNotEmpty($result['payment_url']);
        $this->assertNotEmpty($result['token']);
        $this->assertSame('snap-token-abc-123', $result['token']);

        $this->assertDatabaseHas('payment_transactions', [
            'invoice_id' => $invoice->id,
            'method'     => 'midtrans',
            'status'     => 'pending',
        ]);
    }

    public function test_portal_can_initiate_midtrans_payment(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Budi Customer',
            'phone' => '08123456789',
        ]);

        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-2026-PORTAL-MID',
            'amount'         => 180000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(5),
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token'        => 'snap-token-portal-456',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-portal-456',
            ], 201),
        ]);

        $response = $this->withSession(['customer_id' => $customer->id])
            ->post('/portal/processPayment', [
                'invoice_id' => $invoice->id,
                'method'     => 'midtrans:all',
            ]);

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-portal-456');
    }

    public function test_webhook_verifies_signature_and_settles_invoice(): void
    {
        $customer = Customer::factory()->create();

        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-2026-WEBHOOK-001',
            'amount'         => 100000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(7),
        ]);

        $transaction = PaymentTransaction::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'tenant_id'   => null,
            'amount'      => 100000,
            'method'      => 'midtrans',
            'gateway'     => 'midtrans',
            'status'      => 'pending',
        ]);

        $orderId = 'INV-' . $invoice->id . '-' . $transaction->id;
        $statusCode = '200';
        $grossAmount = '100000.00';
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        $payload = [
            'order_id'           => $orderId,
            'status_code'        => $statusCode,
            'gross_amount'       => $grossAmount,
            'signature_key'      => $signature,
            'transaction_status' => 'settlement',
            'payment_type'       => 'qris',
            'transaction_id'     => 'midtrans-trans-uuid-999',
        ];

        $response = $this->postJson('/webhook/midtrans', $payload);

        $response->assertStatus(200);

        $invoice->refresh();
        $this->assertTrue((bool) $invoice->paid);
        $this->assertSame('paid', $invoice->status);

        $transaction->refresh();
        $this->assertSame('success', $transaction->status);
        $this->assertNotNull($transaction->paid_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $customer = Customer::factory()->create();

        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-2026-FORGED-001',
            'amount'         => 100000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(7),
        ]);

        $transaction = PaymentTransaction::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'tenant_id'   => null,
            'amount'      => 100000,
            'method'      => 'midtrans',
            'gateway'     => 'midtrans',
            'status'      => 'pending',
        ]);

        $payload = [
            'order_id'           => 'INV-' . $invoice->id . '-' . $transaction->id,
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'forged-invalid-signature',
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson('/webhook/midtrans', $payload);

        $response->assertStatus(401);

        $invoice->refresh();
        $this->assertFalse((bool) $invoice->paid);
        $this->assertSame('pending', $invoice->status);
    }
}
