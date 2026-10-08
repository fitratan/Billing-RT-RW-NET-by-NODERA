<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackagePriceTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $this->actingAs($user);
        session(['tenant_id' => $tenant->id, 'admin_role' => 'admin', 'admin_name' => $user->name]);
        return $user;
    }

    private function superadminUser(): User
    {
        $user = User::factory()->create(['role' => 'superadmin', 'tenant_id' => null]);
        $this->actingAs($user);
        session()->forget('tenant_id');
        session(['admin_role' => 'superadmin', 'admin_name' => $user->name]);
        return $user;
    }

    public function test_add_package_saves_price(): void
    {
        $this->adminUser();
        $name = 'Paket Test Price Save ' . uniqid();

        $response = $this->post('/admin/billing/packages/add', [
            'name' => $name,
            'price' => '150.000',
            'profile_normal' => 'default',
            'profile_isolir' => 'isolir',
        ]);

        $this->assertDatabaseHas('packages', ['name' => $name, 'price' => 150000]);
    }

    public function test_update_package_saves_price(): void
    {
        $this->adminUser();
        $pkg = Package::factory()->create(['tenant_id' => session('tenant_id')]);

        $this->post('/admin/billing/packages/update/' . $pkg->id, [
            'name' => $pkg->name,
            'price' => '250.000',
            'profile_normal' => 'default',
            'profile_isolir' => 'isolir',
        ]);

        $this->assertEquals(250000, (float) $pkg->fresh()->price);
    }

    public function test_voucher_package_saves_price(): void
    {
        $this->adminUser();
        $name = 'Voucher Test ' . uniqid();

        $this->post('/admin/voucher-packages/add', [
            'name' => $name,
            'price' => '75000',
            'duration_days' => '30',
        ]);

        $this->assertDatabaseHas('voucher_packages', ['name' => $name, 'price' => 75000]);
    }

    public function test_vpn_package_saves_price(): void
    {
        $this->superadminUser();
        $name = 'VPN Paket Test ' . uniqid();

        $this->post('/superadmin/vpn-paket/add', [
            'name' => $name,
            'port_count' => 1,
            'price' => '15000',
            'duration_days' => 30,
            'type' => 'REMOT',
            'protocol' => ['L2TP'],
        ]);

        $this->assertDatabaseHas('vpn_packages', ['name' => $name, 'price' => 15000]);
    }

    public function test_superadmin_subscription_paket_saves_monthly_price(): void
    {
        $this->superadminUser();
        $name = 'Sub Paket ' . uniqid();

        $this->post('/superadmin/paket/add', [
            'name' => $name,
            'monthly_price' => '125000',
            'max_customers' => '500',
            'duration_options' => '1,6,12',
        ]);

        $this->assertDatabaseHas('packages', [
            'name' => $name,
            'monthly_price' => 125000,
            'type' => 'subscription',
            'tenant_id' => null,
        ]);
    }
}