<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($this->superAdmin);
    }

    public function test_index_lists_tenants(): void
    {
        Tenant::factory()->count(3)->create();
        $response = $this->get('/superadmin/tenants');
        $response->assertStatus(200);
    }

    public function test_can_create_tenant(): void
    {
        $package = \App\Models\Package::factory()->create(['type' => 'subscription', 'max_customers' => 1000, 'monthly_price' => 100000]);

        $response = $this->post('/superadmin/tenants/store', [
            'name' => 'Test ISP',
            'slug' => 'test-isp',
            'email' => 'admin@test-isp.com',
            'package_id' => $package->id,
            'duration' => 1,
        ]);
        $response->assertRedirect('/superadmin/tenants');
        $this->assertDatabaseHas('tenants', ['slug' => 'test-isp']);
    }

    public function test_can_delete_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $response = $this->post("/superadmin/tenants/delete/{$tenant->id}");
        $response->assertRedirect('/superadmin/tenants');
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_bulk_extend(): void
    {
        $tenants = Tenant::factory()->count(3)->create(['expired_at' => now()->addMonth()]);
        $ids = $tenants->pluck('id')->implode(',');

        $response = $this->post('/superadmin/tenants/bulk-extend', [
            'ids' => $ids,
            'months' => 3,
        ]);
        $response->assertRedirect('/superadmin/tenants');
    }

    public function test_search_tenants(): void
    {
        Tenant::factory()->create(['name' => 'MyISP']);
        Tenant::factory()->create(['name' => 'OtherISP']);

        $response = $this->get('/superadmin/tenants?search=MyISP');
        $response->assertStatus(200);
    }
}
