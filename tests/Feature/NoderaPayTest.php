<?php

namespace Tests\Feature;

use App\Models\NoderaPaySubscription;
use App\Models\NoderaPayTransaction;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoderaPayTest extends TestCase
{
    use RefreshDatabase;

    // Valid static QRIS string for testing
    private string $validStaticQris = '00020101021126600014ID.GOPAY.WWW01189360089800012345670211GOPAY1234560303UMI5204481453033605802ID5914NODERA NETWORK6007JAKARTA61051234563044C81';

    private function createVpnUser(int $saldo = 50000): VpnUser
    {
        return VpnUser::create([
            'username'  => 'vpnuser_' . uniqid(),
            'name'      => 'Tester User',
            'email'     => 'tester_' . uniqid() . '@nodera.id',
            'password'  => bcrypt('password123'),
            'saldo'     => $saldo,
            'is_active' => true,
        ]);
    }

    public function test_can_order_standalone_nodera_pay()
    {
        $user = $this->createVpnUser(50000);

        $response = $this->actingAs($user, 'vpn')->post('http://billing.example.com/noderapay/order', [
            'package_type'    => 'standalone',
            'name'            => 'Hotspot Warkop',
            'qris_raw_string' => $this->validStaticQris,
            'webhook_url'     => 'https://example.com/webhook',
        ]);

        $response->assertRedirect();

        $sub = NoderaPaySubscription::where('vpn_user_id', $user->id)->first();
        $this->assertNotNull($sub);
        $this->assertEquals('Hotspot Warkop', $sub->name);
        $this->assertEquals('standalone', $sub->package_type);
        $this->assertEquals('NODERA NETWORK', $sub->merchant_name);
        $this->assertEquals('JAKARTA', $sub->merchant_city);
        $this->assertEquals('ACTIVE', $sub->status);
        $this->assertEquals(20000, $sub->price);

        // Saldo should be deducted: 50000 - 20000 = 30000
        $this->assertEquals(30000, (float) $user->fresh()->total_saldo);
    }

    public function test_can_order_all_in_bundle_with_mikhmon_and_vpn()
    {
        $server = \App\Models\VpnServer::create([
            'name'          => 'SG-Direct-1',
            'location'      => 'Singapore',
            'host'          => 'sg1.example.com',
            'server_ip'     => '103.11.41.43',
            'server_domain' => 'sg1.example.com',
            'api_port'      => 8728,
            'api_user'      => 'admin',
            'api_pass'      => 'secret',
            'active'        => true,
        ]);

        $user = $this->createVpnUser(50000);

        $response = $this->actingAs($user, 'vpn')->post('http://billing.example.com/noderapay/order', [
            'package_type'    => 'bundle',
            'name'            => 'Hotspot All In Kafe',
            'qris_raw_string' => $this->validStaticQris,
            'subdomain'       => 'kafe' . rand(100, 999),
            'vpn_server_id'   => $server->id,
        ]);

        $response->assertRedirect();

        $sub = NoderaPaySubscription::where('vpn_user_id', $user->id)->first();
        $this->assertNotNull($sub);
        $this->assertEquals('bundle', $sub->package_type);
        $this->assertEquals(30000, $sub->price);
        $this->assertNotNull($sub->mikhmon_subscription_id);
        $this->assertNotNull($sub->vpn_account_id);

        // Saldo should be deducted: 50000 - 30000 = 20000
        $this->assertEquals(20000, (float) $user->fresh()->total_saldo);
    }

    public function test_public_api_create_dynamic_qris_and_verify_notification()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        $user = $this->createVpnUser(50000);

        $sub = NoderaPaySubscription::create([
            'vpn_user_id'     => $user->id,
            'package_type'    => 'standalone',
            'name'            => 'Hotspot Test',
            'qris_raw_string' => $this->validStaticQris,
            'merchant_name'   => 'WARKOP BERKAH',
            'webhook_url'     => 'https://example.com/webhook',
            'api_key'         => 'np_live_testkey123',
            'secret_key'      => 'sec_testsecret456',
            'price'           => 20000,
            'status'          => 'ACTIVE',
            'expires_at'      => now()->addDays(30),
        ]);

        // 1. External App (Mikhmon) requests dynamic QRIS
        $createRes = $this->withHeaders([
            'X-Api-Key' => 'np_live_testkey123',
        ])->postJson('/api/v1/noderapay/create-qris', [
            'order_id' => 'VCH-999',
            'amount'   => 5000,
        ]);

        $createRes->assertStatus(200);
        $createRes->assertJsonStructure([
            'success',
            'order_id',
            'amount',
            'unique_code',
            'total_amount',
            'qr_string',
        ]);

        $totalAmount = $createRes->json('total_amount');
        $this->assertGreaterThanOrEqual(5001, $totalAmount);

        // 2. Check pending status
        $checkRes = $this->withHeaders([
            'X-Api-Key' => 'np_live_testkey123',
        ])->getJson('/api/v1/noderapay/check/VCH-999');

        $checkRes->assertStatus(200);
        $this->assertEquals('PENDING', $checkRes->json('status'));

        // 3. Android APK reports bank notification
        $reportRes = $this->withHeaders([
            'X-Device-Key' => 'sec_testsecret456',
        ])->postJson('/api/v1/noderapay/report-notification', [
            'bank'      => 'BCA',
            'amount'    => $totalAmount,
            'raw_text'  => "Transfer masuk Rp " . number_format($totalAmount, 0, ',', '.') . " dari BUDI",
            'timestamp' => time(),
        ]);

        $reportRes->assertStatus(200);
        $this->assertTrue($reportRes->json('success'));
        $this->assertEquals('VCH-999', $reportRes->json('order_id'));

        // 4. Verify transaction marked as paid
        $tx = NoderaPayTransaction::where('order_id', 'VCH-999')->first();
        $this->assertNotNull($tx);
        $this->assertEquals('paid', $tx->status);
        $this->assertNotNull($tx->paid_at);
        $this->assertEquals('success', $tx->webhook_status);
        $this->assertEquals(200, $tx->webhook_response_code);
    }

    public function test_macrodroid_webhook_with_raw_text_parsing()
    {
        $user = $this->createVpnUser(50000);
        $sub = NoderaPaySubscription::create([
            'vpn_user_id'     => $user->id,
            'package_type'    => 'standalone',
            'name'            => 'Warkop MacroDroid',
            'qris_raw_string' => $this->validStaticQris,
            'merchant_name'   => 'WARKOP NODERA',
            'merchant_city'   => 'SURABAYA',
            'api_key'         => 'np_live_macrotst1',
            'secret_key'      => 'sec_macrotestkey999',
            'price'           => 20000,
            'status'          => 'ACTIVE',
            'expires_at'      => now()->addDays(30),
        ]);

        // Create transaction: 10.000 + unique code
        $createRes = $this->withHeaders([
            'X-Api-Key' => $sub->api_key,
        ])->postJson('/api/v1/noderapay/create-qris', [
            'order_id' => 'MACRO-101',
            'amount'   => 10000,
        ]);

        $createRes->assertStatus(200);
        $totalAmount = (int) $createRes->json('total_amount');

        // MacroDroid calls GET /api/v1/noderapay/webhook/{secret_key}?text=...
        $rawText = "QRIS masuk Rp " . number_format($totalAmount, 0, ',', '.') . " dari BUDI SANTOSO ref: 99123";
        $macroRes = $this->getJson("/api/v1/noderapay/webhook/{$sub->secret_key}?text=" . urlencode($rawText));

        $macroRes->assertStatus(200);
        $this->assertTrue($macroRes->json('success'));
        $this->assertEquals('MACRO-101', $macroRes->json('order_id'));

        $tx = NoderaPayTransaction::where('order_id', 'MACRO-101')->first();
        $this->assertNotNull($tx);
        $this->assertEquals('paid', $tx->status);
    }

    public function test_create_qris_returns_422_when_qris_unconfigured()
    {
        $user = $this->createVpnUser(50000);
        $sub = NoderaPaySubscription::create([
            'vpn_user_id'     => $user->id,
            'package_type'    => 'standalone',
            'name'            => 'No QRIS Account',
            'qris_raw_string' => null,
            'merchant_name'   => 'NO QRIS SHOP',
            'api_key'         => 'np_live_noqris123',
            'secret_key'      => 'sec_noqris456',
            'price'           => 20000,
            'status'          => 'ACTIVE',
            'expires_at'      => now()->addDays(30),
        ]);

        $createRes = $this->withHeaders([
            'X-Api-Key' => 'np_live_noqris123',
        ])->postJson('/api/v1/noderapay/create-qris', [
            'order_id' => 'VCR-NO-QRIS-1',
            'amount'   => 5000,
        ]);

        $createRes->assertStatus(422);
        $createRes->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('QRIS belum dikonfigurasi', $createRes->json('message'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (glob(public_path('mikhmon-kafe*')) as $dir) {
            if (is_dir($dir)) {
                \Illuminate\Support\Facades\File::deleteDirectory($dir);
            }
        }
    }
}
