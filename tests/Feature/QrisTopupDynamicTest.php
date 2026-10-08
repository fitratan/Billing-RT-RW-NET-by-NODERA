<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\VpnTopupRequest;
use App\Models\VpnTransaction;
use App\Models\VpnUser;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrisTopupDynamicTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_settle_topup_request_and_increment_saldo(): void
    {
        $user = VpnUser::create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => bcrypt('secret123'),
            'saldo' => 5000,
        ]);

        $topup = VpnTopupRequest::create([
            'vpn_user_id' => $user->id,
            'invoice_number' => 'TOP/202609/0001/9999',
            'amount' => 50000,
            'unique_code' => 123,
            'total_amount' => 50123,
            'bank_destination' => 'QRIS',
            'status' => 'pending',
        ]);

        $settled = VpnTopupRequest::settleTopup($topup, 50123, 'Auto-settled via test');

        $this->assertTrue($settled);

        $topup->refresh();
        $this->assertEquals('verified', $topup->status);
        $this->assertEquals(50123, $topup->amount_received);

        $user->refresh();
        $this->assertEquals(55000, (float) $user->total_saldo);

        $this->assertDatabaseHas('vpn_transactions', [
            'vpn_user_id' => $user->id,
            'type' => 'topup',
            'amount' => 50000,
        ]);
    }

    public function test_qris_callback_matches_and_settles_topup(): void
    {
        $user = VpnUser::create([
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret123'),
            'saldo' => 10000,
        ]);

        $topup = VpnTopupRequest::create([
            'vpn_user_id' => $user->id,
            'invoice_number' => 'TOP/202609/0002/8888',
            'amount' => 100000,
            'unique_code' => 456,
            'total_amount' => 100456,
            'bank_destination' => 'QRIS',
            'status' => 'pending',
        ]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->handleQrisCallback([
            'amount' => 100456,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals($topup->invoice_number, $result['invoice']);

        $topup->refresh();
        $this->assertEquals('verified', $topup->status);

        $user->refresh();
        $this->assertEquals(110000, (float) $user->total_saldo);
    }
}
