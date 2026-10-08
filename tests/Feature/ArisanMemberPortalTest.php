<?php

namespace Tests\Feature;

use App\Models\ArisanDraw;
use App\Models\ArisanGroup;
use App\Models\ArisanGroupMember;
use App\Models\ArisanMember;
use App\Models\ArisanPayment;
use App\Models\ArisanPaymentSetting;
use App\Models\ArisanPeriod;
use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArisanMemberPortalTest extends TestCase
{
    use RefreshDatabase;

    protected ArisanSubscription $subscription;
    protected ArisanGroup $group;
    protected ArisanMember $member;
    protected ArisanGroupMember $slot;
    protected ArisanPeriod $period;
    protected ArisanPayment $payment;

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
            'admin_password_hash' => Hash::make('adminsecret'),
        ]);

        $this->group = ArisanGroup::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Kloter Gold 1 Juta',
            'dues_amount' => 100000,
            'total_slots' => 5,
            'status' => 'ACTIVE',
            'period_type' => 'MONTHLY',
            'start_date' => now()->toDateString(),
            'due_day' => 10,
            'draw_day' => 15,
        ]);

        $this->member = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Siti Aminah',
            'phone_number' => '081234567890',
            'pin_hash' => Hash::make('1234'),
            'magic_token' => 'magictesttoken',
            'is_active' => true,
        ]);

        $this->slot = ArisanGroupMember::create([
            'group_id' => $this->group->id,
            'member_id' => $this->member->id,
            'slot_number' => 1,
            'has_won' => false,
        ]);

        $this->period = ArisanPeriod::create([
            'group_id' => $this->group->id,
            'period_number' => 1,
            'period_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'COLLECTING',
        ]);

        $this->payment = ArisanPayment::create([
            'period_id' => $this->period->id,
            'group_member_id' => $this->slot->id,
            'amount' => 100000,
            'status' => 'UNPAID',
        ]);
    }

    public function test_unauthorized_visitor_redirected_to_member_login(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/card");

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/login");
    }

    public function test_member_can_view_digital_arisan_card(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/card");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Member/Card')
            ->has('member')
            ->where('member.name', 'Siti Aminah')
            ->has('slots', 1)
            ->where('slots.0.group.name', 'Kloter Gold 1 Juta')
            ->where('slots.0.slot_number', 1)
        );
    }

    public function test_member_can_view_card_and_upload_transfer_proof(): void
    {
        Storage::fake('public');
        session(['arisan_member_id' => $this->member->id]);

        // 1. View Card
        $cardResponse = $this->get("/arisan-app/{$this->subscription->subdomain}/member/card");
        $cardResponse->assertStatus(200);

        // 2. Upload Proof
        $file = UploadedFile::fake()->image('bukti_transfer.jpg');
        $uploadResponse = $this->post("/arisan-app/{$this->subscription->subdomain}/member/payments/{$this->payment->id}/upload", [
            'proof_image' => $file,
            'payment_method' => 'BCA',
            'notes' => 'Transfer via BCA Mobile',
        ]);

        $uploadResponse->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/history");
        $uploadResponse->assertSessionHas('success');

        $freshPayment = $this->payment->fresh();
        $this->assertEquals('PENDING_VERIFICATION', $freshPayment->status);
        $this->assertEquals('BCA', $freshPayment->payment_method);
        $this->assertEquals('Transfer via BCA Mobile', $freshPayment->notes);
        $this->assertNotNull($freshPayment->proof_image);
        Storage::disk('public')->assertExists($freshPayment->proof_image);
    }

    public function test_member_can_view_payment_history(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/history");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Member/History')
            ->has('payments.data')
        );
    }

    public function test_member_can_view_transparency_board(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        // Add a second member to the group
        $member2 = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Budi Santoso',
            'phone_number' => '082222222222',
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $slot2 = ArisanGroupMember::create([
            'group_id' => $this->group->id,
            'member_id' => $member2->id,
            'slot_number' => 2,
            'has_won' => true,
            'won_period_id' => $this->period->id,
        ]);

        ArisanDraw::create([
            'period_id' => $this->period->id,
            'winning_group_member_id' => $slot2->id,
            'prize_amount' => 500000,
            'draw_timestamp' => now(),
            'draw_seed_hash' => hash('sha256', 'seed123'),
        ]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/transparency?group_id={$this->group->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Member/Transparency')
            ->has('slotsInGroup', 2)
            ->has('pastDraws', 1)
            ->where('pastDraws.0.prize_amount', 500000)
        );
    }

    public function test_member_can_view_pay_page_and_see_bank_settings(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        ArisanPaymentSetting::create([
            'subscription_id' => $this->subscription->id,
            'bank_name' => 'Bank BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Admin Arisan',
        ]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/payments/{$this->payment->id}/pay");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Member/Pay')
            ->has('paymentSettings', 1)
            ->where('paymentSettings.0.bank_name', 'Bank BCA')
            ->where('paymentSettings.0.account_number', '1234567890')
        );
    }

    public function test_member_can_change_pin_with_valid_old_pin(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/member/profile/pin", [
            'old_pin' => '1234',
            'new_pin' => '9876',
            'new_pin_confirmation' => '9876',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('9876', $this->member->fresh()->pin_hash));
    }

    public function test_member_cannot_change_pin_with_invalid_old_pin(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        $response = $this->from("/arisan-app/{$this->subscription->subdomain}/member/profile")
            ->post("/arisan-app/{$this->subscription->subdomain}/member/profile/pin", [
                'old_pin' => '0000',
                'new_pin' => '9876',
                'new_pin_confirmation' => '9876',
            ]);

        $response->assertRedirect("/arisan-app/{$this->subscription->subdomain}/member/profile");
        $response->assertSessionHasErrors('old_pin');
        $this->assertFalse(Hash::check('9876', $this->member->fresh()->pin_hash));
    }

    public function test_member_cannot_access_other_member_payment(): void
    {
        $otherMember = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Other Member',
            'phone_number' => '089999999999',
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
        ]);

        session(['arisan_member_id' => $otherMember->id]);

        // Attempt to access Siti Aminah's payment
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/payments/{$this->payment->id}/pay");
        $response->assertStatus(403);
    }
}
