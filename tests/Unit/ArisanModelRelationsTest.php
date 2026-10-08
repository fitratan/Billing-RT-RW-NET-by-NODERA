<?php

namespace Tests\Unit;

use App\Models\ArisanCashflow;
use App\Models\ArisanDraw;
use App\Models\ArisanGroup;
use App\Models\ArisanGroupMember;
use App\Models\ArisanMember;
use App\Models\ArisanPayment;
use App\Models\ArisanPaymentSetting;
use App\Models\ArisanPeriod;
use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArisanModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_has_groups_and_members(): void
    {
        $user = VpnUser::factory()->create();
        $sub = ArisanSubscription::create([
            'vpn_user_id' => $user->id,
            'subdomain' => 'test-arisan',
            'business_name' => 'Arisan Test',
            'price' => 10000,
            'status' => 'ACTIVE',
            'order_date' => now(),
            'expires_at' => now()->addDays(30),
            'auto_renew' => true,
            'saldo_deducted' => 10000,
        ]);

        $group = ArisanGroup::create([
            'subscription_id' => $sub->id,
            'name' => 'Kloter 1',
            'dues_amount' => 100000,
            'total_slots' => 5,
            'start_date' => '2026-09-01',
        ]);

        $member = ArisanMember::create([
            'subscription_id' => $sub->id,
            'name' => 'Siti',
            'phone_number' => '08123456789',
            'pin_hash' => bcrypt('1234'),
            'magic_token' => \Illuminate\Support\Str::random(32),
            'is_active' => true,
        ]);

        $slot = ArisanGroupMember::create([
            'group_id' => $group->id,
            'member_id' => $member->id,
            'slot_number' => 1,
            'has_won' => false,
        ]);

        // Subscription relations & casts
        $this->assertEquals($user->id, $sub->vpnUser->id);
        $this->assertEquals($user->id, $sub->user->id);
        $this->assertCount(1, $sub->groups);
        $this->assertCount(1, $sub->members);
        $this->assertIsFloat($sub->price);
        $this->assertIsFloat($sub->saldo_deducted);
        $this->assertIsBool($sub->auto_renew);
        $this->assertInstanceOf(Carbon::class, $sub->order_date);
        $this->assertInstanceOf(Carbon::class, $sub->expires_at);
        $this->assertFalse($sub->isExpired());
        $this->assertNotEmpty($sub->url);

        // Group relations & casts
        $this->assertEquals($sub->id, $group->subscription->id);
        $this->assertCount(1, $group->groupMembers);
        $this->assertCount(1, $group->members);
        $this->assertEquals('Siti', $group->members->first()->name);
        $this->assertEquals(1, $group->members->first()->pivot->slot_number);
        $this->assertIsFloat($group->dues_amount);
        $this->assertIsInt($group->total_slots);

        // Member relations & helpers
        $this->assertEquals($sub->id, $member->subscription->id);
        $this->assertCount(1, $member->groups);
        $this->assertEquals('Kloter 1', $member->groups->first()->name);
        $this->assertIsBool($member->is_active);
        $this->assertEquals('628123456789', $member->formatted_phone);

        $oldToken = $member->magic_token;
        $newToken = $member->generateMagicToken();
        $this->assertNotEmpty($newToken);
        $this->assertEquals($newToken, $member->fresh()->magic_token);
        $this->assertNotEquals($oldToken, $newToken);

        // Group Member relations & casts
        $this->assertEquals($group->id, $slot->group->id);
        $this->assertEquals($member->id, $slot->member->id);
        $this->assertIsInt($slot->slot_number);
        $this->assertIsBool($slot->has_won);
    }

    public function test_group_generate_periods_and_period_relations(): void
    {
        $user = VpnUser::factory()->create();
        $sub = ArisanSubscription::create([
            'vpn_user_id' => $user->id,
            'subdomain' => 'arisan-berkah',
            'business_name' => 'Arisan Berkah',
            'price' => 10000,
            'status' => 'ACTIVE',
        ]);

        $group = ArisanGroup::create([
            'subscription_id' => $sub->id,
            'name' => 'Kloter Berkah',
            'period_type' => 'MONTHLY',
            'dues_amount' => 50000,
            'total_slots' => 3,
            'admin_fee_per_period' => 5000,
            'start_date' => '2026-09-01',
            'due_day' => 10,
            'draw_day' => 15,
        ]);

        $member = ArisanMember::create([
            'subscription_id' => $sub->id,
            'name' => 'Budi',
            'phone_number' => '08987654321',
            'pin_hash' => bcrypt('1234'),
        ]);

        $slot = ArisanGroupMember::create([
            'group_id' => $group->id,
            'member_id' => $member->id,
            'slot_number' => 1,
        ]);

        $periods = $group->generatePeriods();
        $this->assertCount(3, $periods);
        $this->assertCount(3, $group->periods);

        $period1 = $periods->first();
        $this->assertEquals(1, $period1->period_number);
        $this->assertEquals('2026-09-01', $period1->period_date->toDateString());
        $this->assertEquals('2026-09-10', $period1->due_date->toDateString());
        $this->assertEquals('2026-09-15', $period1->draw_date->toDateString());
        $this->assertEquals('COLLECTING', $period1->status);
        $this->assertEquals($group->id, $period1->group->id);

        // Check isAllPaid initially false
        $this->assertFalse($period1->isAllPaid());
        $this->assertEquals(0, $period1->paid_count);

        // Create Payment
        $payment = ArisanPayment::create([
            'period_id' => $period1->id,
            'group_member_id' => $slot->id,
            'amount' => 50000,
            'status' => 'PAID',
            'payment_date' => now(),
            'payment_method' => 'BCA',
            'verified_by_admin_at' => now(),
        ]);

        $this->assertEquals($period1->id, $payment->period->id);
        $this->assertEquals($slot->id, $payment->groupMember->id);
        $this->assertIsFloat($payment->amount);
        $this->assertInstanceOf(Carbon::class, $payment->payment_date);

        // Re-check period payment status
        $this->assertTrue($period1->fresh()->isAllPaid());
        $this->assertEquals(1, $period1->fresh()->paid_count);
        $this->assertCount(1, $slot->payments);

        // Create Draw
        $draw = ArisanDraw::create([
            'period_id' => $period1->id,
            'winning_group_member_id' => $slot->id,
            'prize_amount' => 145000,
            'draw_timestamp' => now(),
            'draw_seed_hash' => 'hash123',
            'disbursement_status' => 'PENDING',
        ]);

        $this->assertEquals($period1->id, $draw->period->id);
        $this->assertEquals($slot->id, $draw->winningGroupMember->id);
        $this->assertIsFloat($draw->prize_amount);
        $this->assertInstanceOf(Carbon::class, $draw->draw_timestamp);
        $this->assertEquals($draw->id, $period1->fresh()->draw->id);
        $this->assertCount(1, $slot->draws);

        // Set slot won period
        $slot->update([
            'has_won' => true,
            'won_period_id' => $period1->id,
        ]);
        $this->assertEquals($period1->id, $slot->fresh()->wonPeriod->id);
    }

    public function test_cashflows_and_payment_settings_relations(): void
    {
        $user = VpnUser::factory()->create();
        $sub = ArisanSubscription::create([
            'vpn_user_id' => $user->id,
            'subdomain' => 'arisan-makmur',
            'business_name' => 'Arisan Makmur',
            'price' => 10000,
            'status' => 'ACTIVE',
        ]);

        $group = ArisanGroup::create([
            'subscription_id' => $sub->id,
            'name' => 'Kloter Makmur 1',
            'dues_amount' => 20000,
            'total_slots' => 10,
        ]);

        $cashflow = ArisanCashflow::create([
            'subscription_id' => $sub->id,
            'group_id' => $group->id,
            'type' => 'IN',
            'category' => 'IURAN',
            'amount' => 200000,
            'transaction_date' => '2026-09-11',
            'description' => 'Iuran Kloter 1 Periode 1',
        ]);

        $this->assertEquals($sub->id, $cashflow->subscription->id);
        $this->assertEquals($group->id, $cashflow->group->id);
        $this->assertIsFloat($cashflow->amount);
        $this->assertInstanceOf(Carbon::class, $cashflow->transaction_date);
        $this->assertCount(1, $sub->cashflows);
        $this->assertCount(1, $group->cashflows);

        $setting = ArisanPaymentSetting::create([
            'subscription_id' => $sub->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Ibu Siti',
            'instructions' => 'Transfer tepat nominal',
        ]);

        $this->assertEquals($sub->id, $setting->subscription->id);
        $this->assertCount(1, $sub->paymentSettings);
    }
}
