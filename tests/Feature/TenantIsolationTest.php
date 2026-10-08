<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private BankAccount $bankA;
    private BankAccount $bankB;
    private VpnAccount $vpnA;
    private VpnAccount $vpnB;

    /**
     * Create all fixtures WITHOUT any tenant session active,
     * so the auto-fill hook (correctly) does not interfere.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // No tenant session — create both tenants freely
        $this->tenantA = Tenant::factory()->create(['slug' => 'isolasi-a']);
        $this->tenantB = Tenant::factory()->create(['slug' => 'isolasi-b']);

        $this->userA = User::factory()->create([
            'role' => 'admin', 'tenant_id' => $this->tenantA->id, 'is_active' => true,
        ]);
        $this->userB = User::factory()->create([
            'role' => 'admin', 'tenant_id' => $this->tenantB->id, 'is_active' => true,
        ]);

        $this->bankA = BankAccount::create([
            'bank_name' => 'BCA', 'account_number' => '001A', 'account_name' => 'Toko A',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->bankB = BankAccount::create([
            'bank_name' => 'BRI', 'account_number' => '001B', 'account_name' => 'Toko B',
            'tenant_id' => $this->tenantB->id,
        ]);

        $server = VpnServer::create([
            'name' => 'Server', 'host' => '10.0.0.1', 'api_user' => 'api', 'api_pass' => 'x',
        ]);
        $this->vpnA = VpnAccount::create([
            'server_id' => $server->id, 'vpn_username' => 'user-a', 'vpn_password' => 'x',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->vpnB = VpnAccount::create([
            'server_id' => $server->id, 'vpn_username' => 'user-b', 'vpn_password' => 'x',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /** Helper: switch the session to a tenant admin context */
    private function switchToTenant(Tenant $tenant, User $admin): void
    {
        session([
            'tenant_id'     => $tenant->id,
            'tenant_slug'   => $tenant->slug,
            'tenant_name'   => $tenant->name,
            'admin_role'    => 'admin',
            'admin_logged_in'=> true,
        ]);
        $this->actingAs($admin);
    }

    // ─── Models with TenantAware (global scope) ───────────────────

    public function test_user_isolation(): void
    {
        $this->switchToTenant($this->tenantA, $this->userA);
        $visible = User::pluck('id');
        $this->assertTrue($visible->contains($this->userA->id), 'own user visible');
        $this->assertFalse($visible->contains($this->userB->id), 'other user hidden');
    }

    public function test_bank_account_isolation(): void
    {
        $this->switchToTenant($this->tenantA, $this->userA);
        $visible = BankAccount::pluck('id');
        $this->assertTrue($visible->contains($this->bankA->id), 'own bank visible');
        $this->assertFalse($visible->contains($this->bankB->id), 'other bank hidden');
    }

    public function test_vpn_account_isolation(): void
    {
        $this->switchToTenant($this->tenantA, $this->userA);
        $visible = VpnAccount::pluck('id');
        $this->assertTrue($visible->contains($this->vpnA->id));
        $this->assertFalse($visible->contains($this->vpnB->id));
    }

    public function test_tenant_addon_isolation(): void
    {
        \App\Models\Addon::create(['name' => 'Addon A', 'slug' => 'addon-a', 'is_active' => true]);
        \App\Models\Addon::create(['name' => 'Addon B', 'slug' => 'addon-b', 'is_active' => true]);

        $taA = TenantAddon::create(['tenant_id' => $this->tenantA->id, 'addon_id' => 1]);
        $taB = TenantAddon::create(['tenant_id' => $this->tenantB->id, 'addon_id' => 2]);

        $this->switchToTenant($this->tenantA, $this->userA);
        $visible = TenantAddon::pluck('id');
        $this->assertTrue($visible->contains($taA->id));
        $this->assertFalse($visible->contains($taB->id));
    }

    // ─── Superadmin bypass ────────────────────────────────────────

    public function test_superadmin_bypasses_scope(): void
    {
        $super = User::factory()->create(['role' => 'superadmin']);
        session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
        session(['admin_role' => 'superadmin', 'admin_logged_in' => true]);
        $this->actingAs($super);

        // superadmin sees all users (both tenants + self)
        $this->assertEquals(3, User::count());
    }

    // ─── Superadmin TIDAK boleh unscoped saat berada di subdomain tenant ──
    //
    // Regresi: dulu apply() melewati scope kalau session admin_role = superadmin,
    // walau request attribute sudah set ke tenant subdomain → data SEMUA tenant
    // muncul campur di halaman tenant (bocor antar tenant saat login superadmin).

    public function test_superadmin_on_tenant_subdomain_is_scoped(): void
    {
        $super = User::factory()->create(['role' => 'superadmin']);
        session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
        session(['admin_role' => 'superadmin', 'admin_logged_in' => true]);
        $this->actingAs($super);

        // Simulasikan TenantDetectionMiddleware di subdomain tenant A
        request()->attributes->set('tenant_id', $this->tenantA->id);

        $visible = User::pluck('id');
        $this->assertTrue($visible->contains($this->userA->id), 'tenant A user visible');
        $this->assertFalse($visible->contains($this->userB->id), 'tenant B user hidden vs superadmin');
        $this->assertFalse($visible->contains($super->id), 'superadmin (tanpa tenant) tidak muncul di scope tenant A');
    }

    // Superadmin di panel apex TIDAK boleh ikut tenggelam ke session tenant basi
    // (mis. sebelumnya mendarat subdomain tenant) — panel harus tetap melihat semua.

    public function test_superadmin_panel_ignores_stale_session_tenant(): void
    {
        $super = User::factory()->create(['role' => 'superadmin']);
        // Session tenant basi tersisa dari kunjungan subdomain sebelumnya
        session([
            'admin_role' => 'superadmin',
            'admin_logged_in' => true,
            'tenant_id' => $this->tenantA->id,
            'tenant_slug' => $this->tenantA->slug,
        ]);
        $this->actingAs($super);
        request()->attributes->set('tenant_id', null); // apex/panel

        // superadmin tetap unscoped: semua user terlihat (2 tenant + superadmin)
        $this->assertEquals(3, User::count());
    }

    // ─── No tenant context = no scope = sees all ──────────────────

    public function test_no_context_sees_all(): void
    {
        $visible = User::pluck('id');
        $this->assertTrue($visible->contains($this->userA->id));
        $this->assertTrue($visible->contains($this->userB->id));
    }

    // ─── Auto-fill creating hook ──────────────────────────────────

    public function test_auto_fill_stamps_tenant_id_on_create(): void
    {
        $this->switchToTenant($this->tenantA, $this->userA);
        $bank = BankAccount::create(['bank_name' => 'Mandiri', 'account_number' => '003', 'account_name' => 'Auto']);
        $this->assertEquals($this->tenantA->id, $bank->fresh()->tenant_id);
    }

    // ─── VPN store query restricted to global bank accounts ───────

    public function test_vpn_store_shows_only_global_banks(): void
    {
        $global = BankAccount::create(['bank_name' => 'Platform', 'account_number' => '000', 'account_name' => 'Global']); // tenant_id null
        $tenantBank = BankAccount::create(['bank_name' => 'Tenant', 'account_number' => '001', 'account_name' => 'T', 'tenant_id' => $this->tenantA->id]);

        $visible = BankAccount::whereNull('tenant_id')->where('is_active', true)->orderBy('sort_order')->pluck('id');
        $this->assertTrue($visible->contains($global->id));
        $this->assertFalse($visible->contains($tenantBank->id));
    }
}
