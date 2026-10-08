<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'My ISP',
            'slug' => 'myisp',
            'is_active' => true,
            'expired_at' => now()->addYear(),
        ]);

        User::create([
            'name' => 'Tenant Admin',
            'username' => 'admin_myisp',
            'email' => 'tenant@myisp.local',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        return $tenant;
    }

    private function superadmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@nodera.id',
            'password' => bcrypt('secret'),
            'role' => 'superadmin',
            'tenant_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_credentials_via_app_login_are_rejected(): void
    {
        $this->seedTenant();
        $this->superadmin();

        $response = $this->post('/login', [
            'email' => 'admin@nodera.id',
            'password' => 'secret',
        ]);

        // Superadmin TIDAK boleh login lewat login app (/login) — pintu itu
        // khusus admin tenant. Harus lewat /nodera/superadmin/login.
        $this->assertNotSame(url('/superadmin'), $response->headers->get('Location'));
        $this->assertTrue($response->isRedirect());
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('admin_role'));
    }

    public function test_superadmin_login_via_dedicated_door_redirects_to_superadmin_dashboard(): void
    {
        $this->seedTenant();
        $this->superadmin();

        $response = $this->post('/nodera/superadmin/login', [
            'email' => 'admin@nodera.id',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/superadmin');
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('tenant_slug'));
        $this->assertEquals('superadmin', session('admin_role'));
    }

    public function test_superadmin_door_clears_stale_tenant_session_from_previous_subdomain_visit(): void
    {
        $tenant = $this->seedTenant();
        $this->superadmin();

        // Simulate a previous visit on a tenant subdomain that left tenant context behind
        session([
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
        ]);

        $response = $this->post('/nodera/superadmin/login', [
            'email' => 'admin@nodera.id',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/superadmin');
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('tenant_slug'));
        $this->assertEquals('superadmin', session('admin_role'));
    }

    public function test_superadmin_door_clears_stale_admin_role_from_previous_impersonation(): void
    {
        $tenant = $this->seedTenant();
        $this->superadmin();

        // Simulate leftover session from a previous impersonation (login-as)
        session([
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'impersonating' => true,
            'admin_role' => 'admin',
            'admin_logged_in' => true,
        ]);

        $response = $this->post('/nodera/superadmin/login', [
            'email' => 'admin@nodera.id',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/superadmin');
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('impersonating'));
        $this->assertEquals('superadmin', session('admin_role'));
    }

    public function test_superadmin_door_ignores_url_intended_that_points_to_tenant_dashboard(): void
    {
        $this->seedTenant();
        $this->superadmin();

        // url.intended can be left behind when the guest middleware previously
        // sent the user to /login from a protected tenant page.
        session(['url.intended' => 'http://localhost/dashboard']);

        $response = $this->post('/nodera/superadmin/login', [
            'email' => 'admin@nodera.id',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/superadmin');
    }

    public function test_logged_in_superadmin_visiting_login_page_is_redirected_to_superadmin(): void
    {
        $this->seedTenant();
        $user = $this->superadmin();
        $this->actingAs($user);

        $response = $this->get('/login');

        $response->assertRedirect('/superadmin');
    }

    public function test_logged_in_admin_visiting_login_page_is_redirected_to_tenant_dashboard(): void
    {
        $tenant = $this->seedTenant();
        $admin = User::where('role', 'admin')->where('tenant_id', $tenant->id)->first();
        $this->actingAs($admin);

        $response = $this->get('/login');

        $response->assertRedirect('/dashboard');
    }
}
