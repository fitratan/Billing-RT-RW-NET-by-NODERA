<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\RegistrationRequest;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnTopupRequest;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookAndCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('TELEGRAM_BOT_TOKEN', '123456:TEST_BOT_TOKEN');
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);
    }

    public function test_telegram_webhook_get_endpoint_returns_status(): void
    {
        $response = $this->get('/webhook/telegram');
        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'status' => 'operational']);

        $apiResponse = $this->get('/api/webhook/telegram');
        $apiResponse->assertStatus(200);
        $apiResponse->assertJson(['ok' => true, 'status' => 'operational']);
    }

    public function test_telegram_webhook_works_on_panel_domain(): void
    {
        $response = $this->get('http://panel.nodera.id/webhook/telegram');
        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'status' => 'operational']);
    }

    public function test_telegram_webhook_handles_registration_approval_callback(): void
    {
        Package::factory()->create(['type' => 'subscription', 'monthly_price' => 100000]);
        $req = RegistrationRequest::factory()->create([
            'slug' => 'testisp',
            'company' => 'Test ISP Corp',
            'status' => 'pending',
            'duration' => 1,
        ]);

        $payload = [
            'update_id' => 1001,
            'callback_query' => [
                'id' => 'cb_123',
                'from' => ['id' => 112233, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 500,
                    'chat' => ['id' => -100123456, 'type' => 'supergroup'],
                ],
                'data' => 'reg_approve:' . $req->slug,
            ],
        ];

        $response = $this->postJson('/webhook/telegram', $payload);
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $req->refresh();
        $this->assertEquals('approved', $req->status);
        $this->assertDatabaseHas('tenants', ['slug' => 'testisp']);
    }

    public function test_telegram_webhook_handles_registration_rejection_callback(): void
    {
        $req = RegistrationRequest::factory()->create([
            'slug' => 'rejectisp',
            'company' => 'Reject ISP',
            'status' => 'pending',
        ]);

        $payload = [
            'update_id' => 1002,
            'callback_query' => [
                'id' => 'cb_124',
                'from' => ['id' => 112233, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 501,
                    'chat' => ['id' => -100123456, 'type' => 'supergroup'],
                ],
                'data' => 'reg_reject:' . $req->slug,
            ],
        ];

        $response = $this->postJson('/webhook/telegram', $payload);
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $req->refresh();
        $this->assertEquals('rejected', $req->status);
    }

    public function test_telegram_webhook_handles_vpn_topup_verify_callback(): void
    {
        $vpnUser = VpnUser::create([
            'name' => 'Test User',
            'email' => 'vpnuser@example.com',
            'phone' => '081234567890',
            'password' => bcrypt('password'),
            'saldo' => 50000,
            'is_active' => true,
        ]);
        $topup = VpnTopupRequest::create([
            'vpn_user_id' => $vpnUser->id,
            'invoice_number' => 'TOPUP-TEST-001',
            'amount' => 100000,
            'status' => 'pending',
            'bank_destination' => 'BCA',
        ]);

        $payload = [
            'update_id' => 1003,
            'callback_query' => [
                'id' => 'cb_125',
                'from' => ['id' => 112233, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 502,
                    'chat' => ['id' => -100123456, 'type' => 'supergroup'],
                ],
                'data' => 'vpn_topup_verify:' . $topup->id,
            ],
        ];

        $response = $this->postJson('/webhook/telegram', $payload);
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $topup->refresh();
        $vpnUser->refresh();

        $this->assertEquals('verified', $topup->status);
        $this->assertEquals(150000, $vpnUser->saldo);
    }

    public function test_telegram_webhook_handles_vpn_topup_reject_callback(): void
    {
        $vpnUser = VpnUser::create([
            'name' => 'Test User 2',
            'email' => 'vpnuser2@example.com',
            'phone' => '081234567891',
            'password' => bcrypt('password'),
            'saldo' => 50000,
            'is_active' => true,
        ]);
        $topup = VpnTopupRequest::create([
            'vpn_user_id' => $vpnUser->id,
            'invoice_number' => 'TOPUP-TEST-002',
            'amount' => 100000,
            'status' => 'pending',
            'bank_destination' => 'BCA',
        ]);

        $payload = [
            'update_id' => 1004,
            'callback_query' => [
                'id' => 'cb_126',
                'from' => ['id' => 112233, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 503,
                    'chat' => ['id' => -100123456, 'type' => 'supergroup'],
                ],
                'data' => 'vpn_topup_reject:' . $topup->id,
            ],
        ];

        $response = $this->postJson('/webhook/telegram', $payload);
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $topup->refresh();
        $vpnUser->refresh();

        $this->assertEquals('rejected', $topup->status);
        $this->assertEquals(50000, $vpnUser->saldo);
    }
}
