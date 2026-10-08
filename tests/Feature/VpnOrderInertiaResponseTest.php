<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VpnOrderInertiaResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_post_with_unreachable_vpn_server_receives_inertia_error_redirect(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'isporder', 'is_active' => true]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $server = VpnServer::create([
            'name' => 'Server SG Test',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_user' => 'admin',
            'api_pass' => 'wrongpass',
            'active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/admin/vpn/create', [
                'server_id' => $server->id,
                'vpn_username' => 'testuser_vpn',
                'vpn_password' => 'secret123',
                'protocol' => 'l2tp',
                'ports' => [8291, 8728],
            ]);

        // Must receive a redirect (302) with error flash message, NOT a 500 JSON response
        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    public function test_vpn_portal_member_insufficient_saldo_is_blocked_with_friendly_message(): void
    {
        $vpnUser = \App\Models\VpnUser::create([
            'name' => 'Member Test',
            'email' => 'member@test.com',
            'phone' => '081234567890',
            'password' => bcrypt('secret123'),
            'saldo' => 5000,
            'is_active' => true,
        ]);

        $server = VpnServer::create([
            'name' => 'Server SG Test 2',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_user' => 'admin',
            'api_pass' => 'pass',
            'active' => true,
        ]);

        $pkg = \App\Models\VpnPackage::create([
            'name' => 'Paket 1 Port',
            'port_count' => 1,
            'duration_days' => 30,
            'price' => 15000,
            'protocol' => ['l2tp'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($vpnUser, 'vpn')
            ->post('/vpn/akun/order', [
                'server_id' => $server->id,
                'package_id' => $pkg->id,
                'vpn_username' => 'vpn_user_01',
                'vpn_password' => 'secret123',
                'protocol' => 'l2tp',
                'ports' => [8291],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Saldo tidak mencukupi', session('error'));
    }
}
