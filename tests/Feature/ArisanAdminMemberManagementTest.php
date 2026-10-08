<?php

namespace Tests\Feature;

use App\Models\ArisanGroup;
use App\Models\ArisanGroupMember;
use App\Models\ArisanMember;
use App\Models\ArisanSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArisanAdminMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_middleware_protects_admin_routes_from_unauthorized_visitors(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);

        // Attempting to access admin dashboard without session
        $response = $this->get("/arisan-app/{$sub->subdomain}/admin");
        $response->assertRedirect("/arisan-app/{$sub->subdomain}/admin/login");

        // Attempting to access groups without session
        $response = $this->get("/arisan-app/{$sub->subdomain}/admin/groups");
        $response->assertRedirect("/arisan-app/{$sub->subdomain}/admin/login");

        // Attempting to access members without session
        $response = $this->get("/arisan-app/{$sub->subdomain}/admin/members");
        $response->assertRedirect("/arisan-app/{$sub->subdomain}/admin/login");
    }

    public function test_admin_can_access_dashboard(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        $response = $this->get("/arisan-app/{$sub->subdomain}/admin");
        $response->assertOk();
        $response->assertSee($sub->business_name, false);
    }

    public function test_admin_can_create_group_and_periods_are_auto_generated(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        $response = $this->post("/arisan-app/{$sub->subdomain}/admin/groups", [
            'name' => 'Kloter Gold 1',
            'period_type' => 'MONTHLY',
            'dues_amount' => 500000,
            'total_slots' => 5,
            'admin_fee_per_period' => 25000,
            'start_date' => now()->toDateString(),
            'due_day' => 10,
            'draw_day' => 15,
        ]);

        $response->assertRedirect();

        $group = ArisanGroup::where('subscription_id', $sub->id)
            ->where('name', 'Kloter Gold 1')
            ->first();

        $this->assertNotNull($group);
        $this->assertEquals(5, $group->total_slots);
        $this->assertEquals(500000, $group->dues_amount);

        // Verify that 5 periods were auto-generated
        $this->assertCount(5, $group->periods);
        $this->assertEquals(1, $group->periods->first()->period_number);
        $this->assertEquals(5, $group->periods->last()->period_number);
    }

    public function test_admin_can_create_member_and_assign_to_slot(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        // Create Member
        $response = $this->post("/arisan-app/{$sub->subdomain}/admin/members", [
            'name' => 'Siti Aminah',
            'phone_number' => '081234567890',
            'pin' => '9988',
            'address_notes' => 'Blok B No. 12',
        ]);

        $response->assertRedirect();

        $member = ArisanMember::where('subscription_id', $sub->id)
            ->where('name', 'Siti Aminah')
            ->first();

        $this->assertNotNull($member);
        $this->assertTrue(Hash::check('9988', $member->pin_hash));
        $this->assertNotNull($member->magic_token);

        // Create Group
        $group = ArisanGroup::create([
            'subscription_id' => $sub->id,
            'name' => 'Kloter Mawar',
            'dues_amount' => 100000,
            'total_slots' => 4,
            'period_type' => 'MONTHLY',
        ]);
        $group->generatePeriods();

        // Assign Member to Slot 1
        $assignResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/groups/{$group->id}/assign-slot", [
            'slot_number' => 1,
            'member_id' => $member->id,
        ]);

        $assignResponse->assertRedirect();
        $this->assertDatabaseHas('arisan_group_members', [
            'group_id' => $group->id,
            'member_id' => $member->id,
            'slot_number' => 1,
        ]);

        // Verify slot matrix in show page
        $showResponse = $this->get("/arisan-app/{$sub->subdomain}/admin/groups/{$group->id}");
        $showResponse->assertOk();
        $showResponse->assertSee('Siti Aminah');
    }

    public function test_admin_can_reset_member_pin(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        $member = ArisanMember::create([
            'subscription_id' => $sub->id,
            'name' => 'Budi Santoso',
            'phone_number' => '085678901234',
            'pin_hash' => Hash::make('1111'),
            'magic_token' => 'old-token',
        ]);

        $response = $this->post("/arisan-app/{$sub->subdomain}/admin/members/{$member->id}/reset-pin", [
            'new_pin' => '7777',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('7777', $member->fresh()->pin_hash));
    }

    public function test_admin_can_regenerate_magic_token(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        $member = ArisanMember::create([
            'subscription_id' => $sub->id,
            'name' => 'Dewi Lestari',
            'phone_number' => '081399887766',
            'pin_hash' => Hash::make('1234'),
            'magic_token' => 'initial-token-12345',
        ]);

        $response = $this->post("/arisan-app/{$sub->subdomain}/admin/members/{$member->id}/regenerate-token");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNotEquals('initial-token-12345', $member->fresh()->magic_token);
    }

    public function test_admin_can_remove_member_from_slot(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        $group = ArisanGroup::create([
            'subscription_id' => $sub->id,
            'name' => 'Kloter Melati',
            'dues_amount' => 100000,
            'total_slots' => 3,
            'period_type' => 'MONTHLY',
        ]);
        $group->generatePeriods();

        $member = ArisanMember::create([
            'subscription_id' => $sub->id,
            'name' => 'Wati',
            'phone_number' => '081299990000',
            'pin_hash' => Hash::make('1234'),
            'magic_token' => 'token-wati',
        ]);

        $groupMember = ArisanGroupMember::create([
            'group_id' => $group->id,
            'member_id' => $member->id,
            'slot_number' => 2,
            'has_won' => false,
        ]);

        $response = $this->delete("/arisan-app/{$sub->subdomain}/admin/groups/{$group->id}/slots/{$groupMember->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('arisan_group_members', [
            'id' => $groupMember->id,
        ]);
    }

    public function test_generate_wa_share_endpoint_returns_json_with_valid_text(): void
    {
        $sub = ArisanSubscription::factory()->create(['subdomain' => 'arisan-berkah']);
        session(['arisan_admin_id' => $sub->id]);

        $member = ArisanMember::create([
            'subscription_id' => $sub->id,
            'name' => 'Siti',
            'phone_number' => '08123456789',
            'pin_hash' => Hash::make('1234'),
            'magic_token' => 'magic-token-xyz',
        ]);

        $response = $this->get("/arisan-app/{$sub->subdomain}/admin/members/{$member->id}/wa-share");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'text',
            'url',
        ]);

        $json = $response->json();
        $this->assertStringContainsString('AKUN PORTAL ARISAN', $json['text']);
        $this->assertStringContainsString('magic-token-xyz', $json['text']);
        $this->assertStringContainsString('https://wa.me/628123456789', $json['url']);
    }
}
