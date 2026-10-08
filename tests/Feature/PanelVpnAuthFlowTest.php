<?php

namespace Tests\Feature;

use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelVpnAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_register_page_loads(): void
    {
        $response = $this->get('http://panel.airnetsolution.com/register');
        $response->assertStatus(200);
    }

    public function test_panel_login_page_loads(): void
    {
        $response = $this->get('http://panel.airnetsolution.com/login');
        $response->assertStatus(200);
    }

    public function test_panel_register_creates_vpn_user_and_redirects_to_dashboard(): void
    {
        $email = 'kouame' . uniqid() . '@example.com';

        $response = $this->post('http://panel.airnetsolution.com/register', [
            'name' => 'Jean Kouame',
            'email' => $email,
            'phone' => '0701020304',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('vpn_users', [
            'email' => $email,
            'name' => 'Jean Kouame',
        ]);
    }

    public function test_panel_login_authenticates_vpn_user(): void
    {
        $user = VpnUser::create([
            'name' => 'Marc Dupont',
            'email' => 'marc@example.com',
            'phone' => '0709080706',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'saldo' => 5000,
        ]);

        $response = $this->post('http://panel.airnetsolution.com/login', [
            'email' => 'marc@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
    }

    public function test_panel_dashboard_accessible_when_authenticated_as_vpn_user(): void
    {
        $user = VpnUser::create([
            'name' => 'Marc Dupont',
            'email' => 'marc2@example.com',
            'phone' => '0709080707',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'saldo' => 5000,
        ]);

        $response = $this->actingAs($user, 'vpn')
            ->withSession(['vpn_user_id' => $user->id, 'vpn_user_name' => $user->name])
            ->get('http://panel.airnetsolution.com/dashboard');

        $response->assertStatus(200);
    }
}
