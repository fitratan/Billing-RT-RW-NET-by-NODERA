<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::setConfig('midtrans', [
            'MIDTRANS_SERVER_KEY' => 'SB-Mid-server-DUMMY_KEY',
            'MIDTRANS_CLIENT_KEY' => 'SB-Mid-client-DUMMY_KEY',
            'MIDTRANS_MODE'       => 'sandbox',
        ]);
    }

    public function test_midtrans_service_resolves_configuration(): void
    {
        $service = new MidtransService();

        $this->assertTrue($service->isConfigured());
        $this->assertSame('SB-Mid-server-DUMMY_KEY', $service->getServerKey());
        $this->assertSame('SB-Mid-client-DUMMY_KEY', $service->getClientKey());
        $this->assertFalse($service->isProduction());
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v1/transactions', $service->getSnapBaseUrl());
    }

    public function test_signature_verification_succeeds_for_valid_hash(): void
    {
        $service = new MidtransService();

        $orderId = 'INV-101-202';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $serverKey = 'SB-Mid-server-DUMMY_KEY';

        $validSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $this->assertTrue($service->verifySignature($orderId, $statusCode, $grossAmount, $validSignature));
        $this->assertFalse($service->verifySignature($orderId, $statusCode, $grossAmount, 'invalid-signature-hash'));
    }

    public function test_resolve_enabled_payments(): void
    {
        $service = new MidtransService();

        $this->assertNull($service->resolveEnabledPayments('midtrans'));
        $this->assertNull($service->resolveEnabledPayments('midtrans:all'));
        $this->assertSame(['qris', 'gopay', 'shopeepay'], $service->resolveEnabledPayments('midtrans:qris'));
        $this->assertSame(['bca_va', 'bni_va', 'bri_va', 'echannel', 'permata_va', 'other_va'], $service->resolveEnabledPayments('midtrans:va'));
        $this->assertSame(['indomaret', 'alfamart'], $service->resolveEnabledPayments('midtrans:cstore'));
    }
}
