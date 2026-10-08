<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Package;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($this->superAdmin);
    }

    public function test_index_lists_registrations(): void
    {
        RegistrationRequest::factory()->count(3)->create();
        $response = $this->get('/superadmin/registrasi');
        $response->assertStatus(200);
    }

    public function test_can_approve_registration(): void
    {
        Package::factory()->create(['type' => 'subscription', 'monthly_price' => 100000]);
        $req = RegistrationRequest::factory()->create([
            'status' => 'pending',
            'duration' => 1,
        ]);

        $response = $this->post("/superadmin/registrasi/approve/{$req->id}");
        $response->assertRedirect('/superadmin/registrasi');

        $req->refresh();
        $this->assertEquals('approved', $req->status);
        $this->assertDatabaseHas('tenants', ['slug' => $req->slug]);
    }

    public function test_can_reject_registration(): void
    {
        $req = RegistrationRequest::factory()->create(['status' => 'pending']);

        $response = $this->post("/superadmin/registrasi/reject/{$req->id}");
        $response->assertRedirect('/superadmin/registrasi');

        $req->refresh();
        $this->assertEquals('rejected', $req->status);
    }

    public function test_cannot_approve_already_processed(): void
    {
        $req = RegistrationRequest::factory()->create(['status' => 'approved']);

        $response = $this->post("/superadmin/registrasi/approve/{$req->id}");
        $response->assertRedirect('/superadmin/registrasi');
        $response->assertSessionHas('error');
    }

    public function test_approved_registration_preserves_package_quota(): void
    {
        $pkg = Package::factory()->create([
            'name' => 'Pro 1000',
            'type' => 'subscription',
            'monthly_price' => 250000,
            'max_customers' => 1000,
            'max_routers' => 10,
        ]);

        $req = RegistrationRequest::factory()->create([
            'status' => 'pending',
            'package_id' => $pkg->id,
            'duration' => 6,
        ]);

        $response = $this->post("/superadmin/registrasi/approve/{$req->id}");
        $response->assertRedirect('/superadmin/registrasi');

        $tenant = \App\Models\Tenant::where('slug', $req->slug)->first();
        $this->assertNotNull($tenant);
        $this->assertEquals(1000, $tenant->max_customers);
        $this->assertEquals(10, $tenant->max_routers);
        $this->assertEquals('Pro 1000', $tenant->package_name);
        $this->assertEquals($pkg->id, $tenant->settings['package_id']);
    }
}
