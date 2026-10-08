<?php

namespace Tests\Feature;

use App\Models\ArisanMember;
use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArisanAuthTest extends TestCase
{
    use RefreshDatabase;

    protected ArisanSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $vpnUser = VpnUser::factory()->create();
        $this->subscription = ArisanSubscription::create([
            'vpn_user_id' => $vpnUser->id,
            'subdomain' => 'mawar',
            'business_name' => 'Arisan Mawar Berkah',
            'price' => 10000,
            'order_date' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
            'admin_password_hash' => Hash::make('adminsecret123'),
        ]);
    }

    public function test_admin_login_page_renders_successfully(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/login");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Admin/Login')
            ->where('business_name', 'Arisan Mawar Berkah')
        );
    }

    public function test_admin_can_login_with_valid_password(): void
    {
        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/login", [
            'admin_password' => 'adminsecret123',
        ]);

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/admin/dashboard");
        $this->assertEquals($this->subscription->id, session('arisan_admin_id'));
    }

    public function test_admin_cannot_login_with_invalid_password(): void
    {
        $response = $this->from("/arisan-app/{$this->subscription->subdomain}/admin/login")
            ->post("/arisan-app/{$this->subscription->subdomain}/admin/login", [
                'admin_password' => 'wrongpassword',
            ]);

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/admin/login");
        $response->assertSessionHasErrors('admin_password');
        $this->assertNull(session('arisan_admin_id'));
    }

    public function test_admin_can_logout(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/logout");

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/admin/login");
        $this->assertNull(session('arisan_admin_id'));
    }

    public function test_member_login_page_renders_successfully(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/login");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Member/Login')
            ->where('business_name', 'Arisan Mawar Berkah')
        );
    }

    public function test_member_can_login_with_phone_and_pin(): void
    {
        $member = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Siti Aminah',
            'phone_number' => '081234567890',
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
        ]);

        // Test login with normalized format 6281234567890
        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/member/login", [
            'phone_number' => '6281234567890',
            'pin' => '1234',
        ]);

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/card");
        $this->assertEquals($member->id, session('arisan_member_id'));
    }

    public function test_member_cannot_login_with_invalid_pin(): void
    {
        ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Siti Aminah',
            'phone_number' => '081234567890',
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $response = $this->from("/arisan-app/{$this->subscription->subdomain}/member/login")
            ->post("/arisan-app/{$this->subscription->subdomain}/member/login", [
                'phone_number' => '081234567890',
                'pin' => '9999',
            ]);

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/login");
        $response->assertSessionHasErrors('phone_number');
        $this->assertNull(session('arisan_member_id'));
    }

    public function test_member_cannot_login_if_not_registered(): void
    {
        $response = $this->from("/arisan-app/{$this->subscription->subdomain}/member/login")
            ->post("/arisan-app/{$this->subscription->subdomain}/member/login", [
                'phone_number' => '089999999999',
                'pin' => '1234',
            ]);

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/login");
        $response->assertSessionHasErrors('phone_number');
        $this->assertNull(session('arisan_member_id'));
    }

    public function test_member_can_login_via_magic_token(): void
    {
        $member = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Dewi',
            'phone_number' => '081299998888',
            'pin_hash' => Hash::make('1234'),
            'magic_token' => 'magic123token',
            'is_active' => true,
        ]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/autologin?token=magic123token");
        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/card");
        $this->assertEquals($member->id, session('arisan_member_id'));
    }

    public function test_member_autologin_fails_with_invalid_token(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/autologin?token=invalid_token_xyz");
        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/login");
        $this->assertNull(session('arisan_member_id'));
    }

    public function test_member_can_logout(): void
    {
        $member = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Dewi',
            'phone_number' => '081299998888',
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
        ]);

        session(['arisan_member_id' => $member->id]);

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/member/logout");

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/login");
        $this->assertNull(session('arisan_member_id'));
    }

    public function test_middleware_blocks_expired_subscription(): void
    {
        $expiredSub = ArisanSubscription::create([
            'vpn_user_id' => $this->subscription->vpn_user_id,
            'subdomain' => 'arisan-expired',
            'business_name' => 'Arisan Expired',
            'price' => 10000,
            'order_date' => now()->subDays(40),
            'expires_at' => now()->subDays(10),
            'status' => 'ACTIVE',
            'admin_password_hash' => Hash::make('123'),
        ]);

        $response = $this->get("/arisan-app/{$expiredSub->subdomain}/admin/login");
        $response->assertStatus(403);
    }

    public function test_middleware_blocks_suspended_subscription(): void
    {
        $suspendedSub = ArisanSubscription::create([
            'vpn_user_id' => $this->subscription->vpn_user_id,
            'subdomain' => 'arisan-suspended',
            'business_name' => 'Arisan Suspended',
            'price' => 10000,
            'order_date' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'SUSPENDED',
            'admin_password_hash' => Hash::make('123'),
        ]);

        $response = $this->get("/arisan-app/{$suspendedSub->subdomain}/admin/login");
        $response->assertStatus(403);
    }

    public function test_middleware_returns_404_for_non_existent_tenant(): void
    {
        $response = $this->get('/arisan-app/non-existent-arisan-xyz/admin/login');
        $response->assertStatus(404);
    }
}
