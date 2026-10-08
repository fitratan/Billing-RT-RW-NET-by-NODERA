<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnUser;
use App\Services\BookkeepingProvisioner;
use App\Services\MikhmonProvisioner;
use App\Services\SubdomainValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdomainGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_subdomain_handles_long_user_names_without_infinite_loop()
    {
        // Name that caused production 30s timeout: "Mikhael William Sasiang"
        $subdomain = MikhmonProvisioner::makeSubdomain('Mikhael William Sasiang');

        $this->assertNotEmpty($subdomain);
        $this->assertLessThanOrEqual(30, strlen($subdomain));
        $this->assertStringStartsWith('hotspot-', $subdomain);

        $check = SubdomainValidationService::checkAvailability($subdomain);
        $this->assertTrue($check['available']);
    }

    public function test_bookkeeping_make_subdomain_handles_long_user_names()
    {
        $subdomain = BookkeepingProvisioner::makeSubdomain('Super Extra Extremely Long Store Name');

        $this->assertNotEmpty($subdomain);
        $this->assertLessThanOrEqual(30, strlen($subdomain));
        $this->assertStringStartsWith('kas-', $subdomain);

        $check = SubdomainValidationService::checkAvailability($subdomain);
        $this->assertTrue($check['available']);
    }

    public function test_vpn_mikhmon_order_page_loads_instantly_for_long_name_user()
    {
        $vpnUser = VpnUser::create([
            'name' => 'Mikhael William Sasiang',
            'username' => 'mikhael_william_sasiang',
            'email' => 'mikhael@example.com',
            'phone' => '08123456789',
            'password' => bcrypt('password123'),
            'saldo' => 50000,
        ]);

        $response = $this->actingAs($vpnUser, 'vpn')
            ->get('/vpn/mikhmon/order');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vpn/MikhmonOrder')
            ->has('suggested')
        );
    }
}
