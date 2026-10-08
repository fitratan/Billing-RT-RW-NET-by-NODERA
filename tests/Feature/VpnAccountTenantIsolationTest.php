<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VpnAccountTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vpn_user_can_view_accounts_even_if_tenant_session_exists(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'isptest', 'is_active' => true]);

        $vpnUser = VpnUser::create([
            'name' => 'John VPN',
            'email' => 'john@vpn.com',
            'phone' => '08123456789',
            'password' => bcrypt('secret123'),
            'saldo' => 50000,
            'is_active' => true,
        ]);

        $server = VpnServer::create([
            'name' => 'SG Server',
            'host' => '1.2.3.4',
            'api_port' => 8728,
            'api_user' => 'admin',
            'api_pass' => 'pass',
            'active' => true,
        ]);

        // Akun VPN dibuat tanpa tenant_id (global/apex)
        $account = VpnAccount::withoutGlobalScopes()->create([
            'vpn_user_id' => $vpnUser->id,
            'server_id' => $server->id,
            'vpn_username' => 'john_remote_1',
            'vpn_password' => 'secret123',
            'protocol' => 'l2tp',
            'type' => 'remote',
            'package' => 'Paket 1 Port',
            'status' => 'ACTIVE',
            'ports' => [8291],
            'tenant_id' => null,
            'expires_at' => now()->addDays(30),
        ]);

        // Request ke panel.airnetsolution.com/akun dengan session tenant_id aktif (mensimulasikan sisa session tenant di PWA)
        $response = $this->actingAs($vpnUser, 'vpn')
            ->withSession(['tenant_id' => $tenant->id])
            ->get('http://panel.airnetsolution.com/akun');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Vpn/Akun')
                ->has('accounts', 1)
                ->where('accounts.0.vpn_username', 'john_remote_1')
        );

        // Pastikan relasi model $vpnUser->accounts juga mengembalikan akun
        $this->assertCount(1, $vpnUser->accounts);
    }
}
