<?php

namespace Tests\Feature;

use App\Models\ArisanCashflow;
use App\Models\ArisanDraw;
use App\Models\ArisanGroup;
use App\Models\ArisanGroupMember;
use App\Models\ArisanMember;
use App\Models\ArisanPayment;
use App\Models\ArisanPeriod;
use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArisanDrawLogicTest extends TestCase
{
    use RefreshDatabase;

    protected ArisanSubscription $subscription;
    protected ArisanGroup $group;
    protected ArisanMember $member1;
    protected ArisanMember $member2;
    protected ArisanMember $member3;
    protected ArisanGroupMember $slot1;
    protected ArisanGroupMember $slot2;
    protected ArisanGroupMember $slot3;
    protected ArisanPeriod $period1;
    protected ArisanPeriod $period2;

    protected function setUp(): void
    {
        parent::setUp();

        $vpnUser = VpnUser::factory()->create();
        $this->subscription = ArisanSubscription::create([
            'vpn_user_id' => $vpnUser->id,
            'subdomain' => 'mawar-indah',
            'business_name' => 'Arisan Mawar Indah',
            'price' => 10000,
            'status' => 'ACTIVE',
        ]);

        $this->group = ArisanGroup::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Kloter Melati 1',
            'period_type' => 'MONTHLY',
            'dues_amount' => 100000,
            'total_slots' => 3,
            'admin_fee_per_period' => 10000,
            'status' => 'ACTIVE',
        ]);

        $this->member1 = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Siti Nurhaliza',
            'phone_number' => '081234567890',
            'pin_hash' => bcrypt('1234'),
            'magic_token' => 'token-siti-12345',
        ]);

        $this->member2 = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Dewi Persik',
            'phone_number' => '089876543210',
            'pin_hash' => bcrypt('4321'),
            'magic_token' => 'token-dewi-67890',
        ]);

        $this->member3 = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Rina Nose',
            'phone_number' => '081122334455',
            'pin_hash' => bcrypt('5678'),
            'magic_token' => 'token-rina-11223',
        ]);

        $this->slot1 = ArisanGroupMember::create([
            'group_id' => $this->group->id,
            'member_id' => $this->member1->id,
            'slot_number' => 1,
            'has_won' => false,
        ]);

        $this->slot2 = ArisanGroupMember::create([
            'group_id' => $this->group->id,
            'member_id' => $this->member2->id,
            'slot_number' => 2,
            'has_won' => false,
        ]);

        $this->slot3 = ArisanGroupMember::create([
            'group_id' => $this->group->id,
            'member_id' => $this->member3->id,
            'slot_number' => 3,
            'has_won' => false,
        ]);

        $this->period1 = ArisanPeriod::create([
            'group_id' => $this->group->id,
            'period_number' => 1,
            'period_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'draw_date' => now()->addDays(10)->toDateString(),
            'status' => 'COLLECTING',
        ]);

        $this->period2 = ArisanPeriod::create([
            'group_id' => $this->group->id,
            'period_number' => 2,
            'period_date' => now()->addMonth()->toDateString(),
            'due_date' => now()->addMonth()->addDays(5)->toDateString(),
            'draw_date' => now()->addMonth()->addDays(10)->toDateString(),
            'status' => 'COLLECTING',
        ]);
    }

    public function test_admin_can_view_draw_dashboard_and_eligible_candidates(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/draws?group_id={$this->group->id}&period_id={$this->period1->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Admin/Draws')
            ->has('candidates', 3)
            ->where('currentGroup.name', 'Kloter Melati 1')
            ->where('currentPeriod.period_number', 1)
        );
    }

    public function test_draw_marks_winner_and_cannot_win_twice(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        // Execute draw for period 1, slot1 wins
        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period1->id}/execute", [
            'winner_slot_id' => $this->slot1->id,
            'prize_amount' => 290000,
        ]);

        $response->assertSessionHas('success');

        $this->slot1->refresh();
        $this->assertTrue((bool) $this->slot1->has_won);
        $this->assertEquals($this->period1->id, $this->slot1->won_period_id);

        $this->period1->refresh();
        $this->assertEquals('DRAWN', $this->period1->status);

        $this->assertDatabaseHas('arisan_draws', [
            'period_id' => $this->period1->id,
            'winning_group_member_id' => $this->slot1->id,
            'prize_amount' => 290000,
            'disbursement_status' => 'PENDING',
        ]);

        // Try to draw slot1 again in period 2 -> should fail
        $responsePeriod2 = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period2->id}/execute", [
            'winner_slot_id' => $this->slot1->id,
            'prize_amount' => 290000,
        ]);

        $responsePeriod2->assertSessionHas('error');
    }

    public function test_draw_cannot_be_executed_twice_for_same_period(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        // First execution
        $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period1->id}/execute", [
            'winner_slot_id' => $this->slot1->id,
            'prize_amount' => 290000,
        ]);

        // Second execution for the same period with a different slot -> should fail
        $secondResponse = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period1->id}/execute", [
            'winner_slot_id' => $this->slot2->id,
            'prize_amount' => 290000,
        ]);

        $secondResponse->assertSessionHas('error');
        $this->assertEquals(1, ArisanDraw::where('period_id', $this->period1->id)->count());
    }

    public function test_draw_automatically_records_out_cashflow(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period1->id}/execute", [
            'winning_group_member_id' => $this->slot2->id,
            'prize_amount' => 290000,
        ]);

        $this->assertDatabaseHas('arisan_cashflows', [
            'subscription_id' => $this->subscription->id,
            'group_id' => $this->group->id,
            'type' => 'OUT',
            'category' => 'PENCAIRAN_PEMENANG',
            'amount' => 290000,
        ]);

        $cashflow = ArisanCashflow::where('subscription_id', $this->subscription->id)
            ->where('category', 'PENCAIRAN_PEMENANG')
            ->first();

        $this->assertNotNull($cashflow);
        $this->assertStringContainsString('Dewi Persik', $cashflow->description);
        $this->assertStringContainsString('Kloter Melati 1', $cashflow->description);
        $this->assertStringContainsString('#1', $cashflow->description);
    }

    public function test_draw_wa_share_text_contains_no_emojis(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period1->id}/execute", [
            'winner_slot_id' => $this->slot1->id,
            'prize_amount' => 290000,
        ]);

        $draw = ArisanDraw::where('period_id', $this->period1->id)->first();
        $this->assertNotNull($draw);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$draw->id}/wa-share");
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('HASIL UNDIAN ARISAN Arisan Mawar Indah', $data['text']);
        $this->assertStringContainsString('Kloter: Kloter Melati 1', $data['text']);
        $this->assertStringContainsString('Putaran: 1', $data['text']);
        $this->assertStringContainsString('Pemenang: Siti Nurhaliza (Slot #1)', $data['text']);
        $this->assertStringContainsString('Nominal: Rp 290.000', $data['text']);

        // Strictly NO EMOJIS check
        $this->assertEquals(0, preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F1E0}-\x{1F1FF}]/u', $data['text']));
    }

    public function test_admin_can_update_disbursement_status_and_proof(): void
    {
        Storage::fake('public');
        session(['arisan_admin_id' => $this->subscription->id]);

        $draw = ArisanDraw::create([
            'period_id' => $this->period1->id,
            'winning_group_member_id' => $this->slot1->id,
            'prize_amount' => 290000,
            'draw_timestamp' => now(),
            'draw_seed_hash' => hash('sha256', 'seed-test'),
            'disbursement_status' => 'PENDING',
        ]);

        $proofFile = UploadedFile::fake()->image('bukti_transfer_pemenang.jpg');

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$draw->id}/disbursement", [
            'disbursement_status' => 'TRANSFERRED',
            'disbursement_proof' => $proofFile,
        ]);

        $response->assertSessionHas('success');
        $draw->refresh();
        $this->assertEquals('TRANSFERRED', $draw->disbursement_status);
        $this->assertNotNull($draw->disbursement_proof);
        Storage::disk('public')->assertExists($draw->disbursement_proof);
    }

    public function test_cashflow_ledger_calculates_balance_and_supports_manual_entry(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        // 1. Create initial cashflow entries
        ArisanCashflow::create([
            'subscription_id' => $this->subscription->id,
            'group_id' => $this->group->id,
            'type' => 'IN',
            'category' => 'IURAN',
            'amount' => 300000,
            'transaction_date' => now()->toDateString(),
            'description' => 'Iuran Putaran 1',
        ]);

        ArisanCashflow::create([
            'subscription_id' => $this->subscription->id,
            'group_id' => $this->group->id,
            'type' => 'OUT',
            'category' => 'PENCAIRAN_PEMENANG',
            'amount' => 290000,
            'transaction_date' => now()->toDateString(),
            'description' => 'Pencairan Pemenang 1',
        ]);

        // 2. Index view should show Saldo Kas = 10.000
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/cashflow");
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Admin/Cashflow')
            ->where('totalIn', 300000)
            ->where('totalOut', 290000)
            ->where('netBalance', 10000)
        );

        // 3. Admin manual entry
        $storeResponse = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/cashflow", [
            'group_id' => $this->group->id,
            'type' => 'IN',
            'category' => 'KAS_DARURAT',
            'amount' => 50000,
            'transaction_date' => now()->toDateString(),
            'description' => 'Sumbangan Kas Darurat',
        ]);

        $storeResponse->assertSessionHas('success');
        $this->assertDatabaseHas('arisan_cashflows', [
            'subscription_id' => $this->subscription->id,
            'category' => 'KAS_DARURAT',
            'amount' => 50000,
        ]);

        $manualCashflow = ArisanCashflow::where('category', 'KAS_DARURAT')->first();

        // 4. Delete manual entry
        $deleteResponse = $this->delete("/arisan-app/{$this->subscription->subdomain}/admin/cashflow/{$manualCashflow->id}");
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('arisan_cashflows', ['id' => $manualCashflow->id]);
    }

    public function test_paid_only_filter_on_draw_candidates(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        // Slot 1 is paid, Slot 2 and 3 are unpaid
        ArisanPayment::create([
            'period_id' => $this->period1->id,
            'group_member_id' => $this->slot1->id,
            'amount' => 100000,
            'status' => 'PAID',
        ]);
        ArisanPayment::create([
            'period_id' => $this->period1->id,
            'group_member_id' => $this->slot2->id,
            'amount' => 100000,
            'status' => 'UNPAID',
        ]);

        // Request with paid_only=1
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/draws?group_id={$this->group->id}&period_id={$this->period1->id}&paid_only=1");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Admin/Draws')
            ->has('candidates', 1)
            ->where('candidates.0.member.name', 'Siti Nurhaliza')
        );
    }

    public function test_cannot_draw_slot_belonging_to_another_group(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $group2 = ArisanGroup::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Kloter Melati 2',
            'dues_amount' => 50000,
            'total_slots' => 2,
        ]);
        $slotOtherGroup = ArisanGroupMember::create([
            'group_id' => $group2->id,
            'member_id' => $this->member1->id,
            'slot_number' => 1,
            'has_won' => false,
        ]);

        // Try to draw slotOtherGroup in period1 (which belongs to group 1)
        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/draws/{$this->period1->id}/execute", [
            'winner_slot_id' => $slotOtherGroup->id,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_cashflow_ledger_filters_by_group_type_and_date(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $group2 = ArisanGroup::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Kloter Anggrek',
            'dues_amount' => 50000,
        ]);

        ArisanCashflow::create([
            'subscription_id' => $this->subscription->id,
            'group_id' => $this->group->id,
            'type' => 'IN',
            'category' => 'IURAN',
            'amount' => 100000,
            'transaction_date' => '2026-09-01',
            'description' => 'Iuran Melati September',
        ]);

        ArisanCashflow::create([
            'subscription_id' => $this->subscription->id,
            'group_id' => $group2->id,
            'type' => 'IN',
            'category' => 'IURAN',
            'amount' => 50000,
            'transaction_date' => '2026-09-05',
            'description' => 'Iuran Anggrek September',
        ]);

        ArisanCashflow::create([
            'subscription_id' => $this->subscription->id,
            'group_id' => $this->group->id,
            'type' => 'OUT',
            'category' => 'BIAYA_ADMIN',
            'amount' => 10000,
            'transaction_date' => '2026-09-10',
            'description' => 'Beban Administrasi Kloter',
        ]);

        // Filter by group 1 and type OUT
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/cashflow?group_id={$this->group->id}&type=OUT");
        $response->assertStatus(200);
        $response->assertSee('Beban Administrasi Kloter');
        $response->assertDontSee('Iuran Melati September');
        $response->assertDontSee('Iuran Anggrek September');
    }
}
