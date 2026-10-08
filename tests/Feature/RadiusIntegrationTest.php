<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantRadiusSetting;
use App\Models\User;
use App\Services\RadiusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RadiusIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_radius_setting_encryption_and_defaults()
    {
        $tenant = Tenant::factory()->create();

        $setting = TenantRadiusSetting::create([
            'tenant_id' => $tenant->id,
            'radius_mode' => 'remote_db',
            'is_active' => true,
            'remote_db_host' => '103.1.2.3',
            'remote_db_pass' => 'supersecretpass123',
            'nas_ip' => '10.0.0.1',
            'nas_secret' => 'radsecret456',
        ]);

        $this->assertEquals('supersecretpass123', $setting->remote_db_pass);
        $this->assertEquals('radsecret456', $setting->nas_secret);

        // Check raw database storage is encrypted
        $raw = DB::table('tenant_radius_settings')->where('id', $setting->id)->first();
        $this->assertNotEquals('supersecretpass123', $raw->remote_db_pass);
        $this->assertNotEquals('radsecret456', $raw->nas_secret);
    }

    public function test_radius_service_sync_and_isolation()
    {
        $tenant = Tenant::factory()->create();
        session(['tenant_id' => $tenant->id]);

        $setting = TenantRadiusSetting::create([
            'tenant_id' => $tenant->id,
            'radius_mode' => 'local_db',
            'is_active' => true,
        ]);

        $package = Package::create([
            'tenant_id' => $tenant->id,
            'name' => '20M-VIP',
            'price' => 150000,
            'profile_normal' => '20M/20M',
            'profile_isolir' => '512k/512k',
            'type' => 'pppoe',
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'John Doe',
            'pppoe_username' => 'johndoe_pppoe',
            'portal_password' => 'secret123',
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $radiusService = app(RadiusService::class);

        // 1. Sync Customer
        $this->assertTrue($radiusService->syncCustomer($customer, $package));

        $radcheck = DB::table('radcheck')->where('username', 'johndoe_pppoe')->first();
        $this->assertNotNull($radcheck);
        $this->assertEquals('secret123', $radcheck->value);

        $radusergroup = DB::table('radusergroup')->where('username', 'johndoe_pppoe')->first();
        $this->assertNotNull($radusergroup);
        $this->assertEquals('20M-VIP', $radusergroup->groupname);

        // 2. Isolate Customer
        $customer->status = 'isolated';
        $customer->save();

        $this->assertTrue($radiusService->isolateCustomer($customer));

        $radusergroupIsolated = DB::table('radusergroup')->where('username', 'johndoe_pppoe')->first();
        $this->assertEquals('ISOLATED', $radusergroupIsolated->groupname);

        // 3. Unisolate Customer
        $customer->status = 'active';
        $customer->save();

        $this->assertTrue($radiusService->unisolateCustomer($customer));

        $radusergroupRestored = DB::table('radusergroup')->where('username', 'johndoe_pppoe')->first();
        $this->assertEquals('20M-VIP', $radusergroupRestored->groupname);

        // 4. Remove Customer
        $this->assertTrue($radiusService->removeCustomer('johndoe_pppoe', $tenant->id));

        $this->assertNull(DB::table('radcheck')->where('username', 'johndoe_pppoe')->first());
        $this->assertNull(DB::table('radusergroup')->where('username', 'johndoe_pppoe')->first());
    }

    public function test_admin_can_access_radius_page_and_save_settings()
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/admin/radius');

        $response->assertStatus(200);

        $saveResponse = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->post('/admin/radius/settings', [
                'radius_mode' => 'local_db',
                'is_active' => true,
                'remote_db_driver' => 'mysql',
                'auto_sync_on_create' => true,
                'auto_coa_on_isolate' => true,
            ]);

        $saveResponse->assertRedirect();

        $this->assertDatabaseHas('tenant_radius_settings', [
            'tenant_id' => $tenant->id,
            'radius_mode' => 'local_db',
            'is_active' => true,
        ]);
    }
}
