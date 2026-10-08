<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArisanEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regex pattern to detect emojis, pictographs, symbols, flags, etc.
     */
    protected string $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F018}-\x{1F270}\x{2388}-\x{23E8}\x{2B05}-\x{2B55}\x{E0020}-\x{E007F}]/u';

    /**
     * Full End-to-End Integration Test covering Steps A through L.
     */
    public function test_full_arisan_lifecycle_end_to_end(): void
    {
        Storage::fake('public');

        // =========================================================================
        // Step A: Order subscription in NODERA panel (/member/services/arisan/order)
        // =========================================================================
        $user = VpnUser::factory()->create(['saldo' => 50000]);
        $this->actingAs($user);

        // View order page
        $orderPageResponse = $this->get(route('member.arisan.order'));
        $orderPageResponse->assertStatus(200);

        // Submit order
        $orderResponse = $this->post(route('member.arisan.order.submit'), [
            'business_name' => 'Arisan Berkah Nusantara',
            'subdomain' => 'arisan-berkah-nusantara',
            'admin_password' => 'superadmin123',
            'auto_renew' => true,
        ]);

        $orderResponse->assertRedirect();

        // Check subscription created in database
        $this->assertDatabaseHas('arisan_subscriptions', [
            'subdomain' => 'arisan-berkah-nusantara',
            'business_name' => 'Arisan Berkah Nusantara',
            'status' => 'ACTIVE',
            'auto_renew' => true,
        ]);

        $sub = ArisanSubscription::where('subdomain', 'arisan-berkah-nusantara')->firstOrFail();
        $this->assertTrue(Hash::check('superadmin123', $sub->admin_password_hash));

        // Check user balance deducted (Rp 50.000 - Rp 10.000 = Rp 40.000)
        $this->assertEquals(40000, $user->fresh()->saldo);

        // =========================================================================
        // Step B: Admin login with password (/arisan-app/{subdomain}/admin/login)
        // =========================================================================
        $adminLoginPage = $this->get("/arisan-app/{$sub->subdomain}/admin/login");
        $adminLoginPage->assertStatus(200);
        $adminLoginPage->assertSee('Arisan Berkah Nusantara');

        $adminLoginResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/login", [
            'admin_password' => 'superadmin123',
        ]);

        $adminLoginResponse->assertRedirect("/arisan-app/{$sub->subdomain}/admin/dashboard");
        $this->assertEquals($sub->id, session('arisan_admin_id'));

        // Visit Admin Dashboard
        $dashboardResponse = $this->get("/arisan-app/{$sub->subdomain}/admin/dashboard");
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Arisan Berkah Nusantara');

        // =========================================================================
        // Step C: Create group / kloter (/arisan-app/{subdomain}/admin/groups) and auto-generate periods
        // =========================================================================
        $createGroupResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/groups", [
            'name' => 'Kloter Sakura 1',
            'period_type' => 'MONTHLY',
            'dues_amount' => 150000,
            'total_slots' => 2,
            'admin_fee_per_period' => 10000,
            'start_date' => now()->toDateString(),
            'due_day' => 10,
            'draw_day' => 15,
        ]);

        $createGroupResponse->assertRedirect();

        $group = ArisanGroup::where('subscription_id', $sub->id)
            ->where('name', 'Kloter Sakura 1')
            ->firstOrFail();

        $this->assertEquals(2, $group->total_slots);
        $this->assertEquals(150000, $group->dues_amount);
        $this->assertEquals(10000, $group->admin_fee_per_period);

        // Check periods auto-generated
        $this->assertCount(2, $group->periods);
        $period1 = $group->periods()->where('period_number', 1)->firstOrFail();
        $period2 = $group->periods()->where('period_number', 2)->firstOrFail();
        $this->assertEquals('COLLECTING', $period1->status);

        // =========================================================================
        // Step D: Create members, verify magic_token and default PIN (/arisan-app/{subdomain}/admin/members)
        // =========================================================================
        // Member 1
        $createMember1Response = $this->post("/arisan-app/{$sub->subdomain}/admin/members", [
            'name' => 'Fatimah Az-Zahra',
            'phone_number' => '081234567890',
            'pin' => '1122',
            'address_notes' => 'Komplek Melati Blok A1',
        ]);
        $createMember1Response->assertRedirect();

        // Member 2
        $createMember2Response = $this->post("/arisan-app/{$sub->subdomain}/admin/members", [
            'name' => 'Zubaidah',
            'phone_number' => '087712345678',
            'address_notes' => 'Komplek Melati Blok A2',
        ]);
        $createMember2Response->assertRedirect();

        $member1 = ArisanMember::where('subscription_id', $sub->id)->where('name', 'Fatimah Az-Zahra')->firstOrFail();
        $member2 = ArisanMember::where('subscription_id', $sub->id)->where('name', 'Zubaidah')->firstOrFail();

        // Verify magic_token and PINs
        $this->assertNotEmpty($member1->magic_token);
        $this->assertTrue(Hash::check('1122', $member1->pin_hash));

        $this->assertNotEmpty($member2->magic_token);
        // Default PIN for member 2 (last 4 digits of phone 5678)
        $this->assertTrue(Hash::check('5678', $member2->pin_hash));

        // =========================================================================
        // Step E: Assign members to slots in group (/arisan-app/{subdomain}/admin/groups/{group}/assign-slot)
        // =========================================================================
        $assignSlot1Response = $this->post("/arisan-app/{$sub->subdomain}/admin/groups/{$group->id}/assign-slot", [
            'member_id' => $member1->id,
            'slot_number' => 1,
        ]);
        $assignSlot1Response->assertRedirect();

        $assignSlot2Response = $this->post("/arisan-app/{$sub->subdomain}/admin/groups/{$group->id}/assign-slot", [
            'member_id' => $member2->id,
            'slot_number' => 2,
        ]);
        $assignSlot2Response->assertRedirect();

        $slot1 = ArisanGroupMember::where('group_id', $group->id)->where('slot_number', 1)->firstOrFail();
        $slot2 = ArisanGroupMember::where('group_id', $group->id)->where('slot_number', 2)->firstOrFail();

        $this->assertEquals($member1->id, $slot1->member_id);
        $this->assertEquals($member2->id, $slot2->member_id);
        $this->assertFalse((bool) $slot1->has_won);
        $this->assertFalse((bool) $slot2->has_won);

        // Verify auto-generated payments for period 1 & period 2
        $payment1Period1 = ArisanPayment::where('period_id', $period1->id)->where('group_member_id', $slot1->id)->firstOrFail();
        $payment2Period1 = ArisanPayment::where('period_id', $period1->id)->where('group_member_id', $slot2->id)->firstOrFail();

        $this->assertEquals('UNPAID', $payment1Period1->status);
        $this->assertEquals('UNPAID', $payment2Period1->status);

        // =========================================================================
        // Step F: Set up bank account and QRIS in admin settings (/arisan-app/{subdomain}/admin/settings/payments)
        // =========================================================================
        $storeBankResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/settings/payments", [
            'bank_name' => 'Bank Mandiri Syariah',
            'account_number' => '1400012345678',
            'account_holder' => 'Pengurus Arisan Berkah',
        ]);
        $storeBankResponse->assertRedirect();

        $qrisImage = UploadedFile::fake()->image('qris_official.png');
        $storeQrisResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/settings/payments/qris", [
            'qris_image' => $qrisImage,
            'instructions' => 'Harap cantumkan nama & nomor slot di keterangan transfer.',
        ]);
        $storeQrisResponse->assertRedirect();

        $paymentSetting = ArisanPaymentSetting::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertEquals('Bank Mandiri Syariah', $paymentSetting->bank_name);
        $this->assertEquals('1400012345678', $paymentSetting->account_number);
        $this->assertNotNull($paymentSetting->qris_image_path);
        Storage::disk('public')->assertExists($paymentSetting->qris_image_path);

        // =========================================================================
        // Step G: Member magic login (/arisan-app/{subdomain}/member/autologin?token=...) and member card view (/member/card)
        // =========================================================================
        $magicLoginResponse = $this->get("/arisan-app/{$sub->subdomain}/member/autologin?token={$member1->magic_token}");
        $magicLoginResponse->assertRedirect("/arisan-app/{$sub->subdomain}/member/card");
        $this->assertEquals($member1->id, session('arisan_member_id'));

        $memberCardResponse = $this->get("/arisan-app/{$sub->subdomain}/member/card");
        $memberCardResponse->assertStatus(200);
        $memberCardResponse->assertInertia(fn (Assert $page) => $page
            ->component('Arisan/Member/Card')
            ->has('member')
            ->where('member.name', 'Fatimah Az-Zahra')
            ->has('slots', 1)
            ->where('slots.0.group.name', 'Kloter Sakura 1')
            ->where('slots.0.slot_number', 1)
        );

        // =========================================================================
        // Step H: Member upload payment proof (/arisan-app/{subdomain}/member/payments/{payment}/upload)
        // =========================================================================
        $proofImage = UploadedFile::fake()->image('bukti_transfer_fatimah.jpg');
        $uploadProofResponse = $this->post("/arisan-app/{$sub->subdomain}/member/payments/{$payment1Period1->id}/upload", [
            'proof_image' => $proofImage,
            'payment_method' => 'Bank Mandiri Syariah',
            'notes' => 'Transfer iuran putaran 1 lunas via Livin',
        ]);

        $uploadProofResponse->assertRedirect("/arisan-app/{$sub->subdomain}/member/history");
        $payment1Period1->refresh();

        $this->assertEquals('PENDING_VERIFICATION', $payment1Period1->status);
        $this->assertEquals('Bank Mandiri Syariah', $payment1Period1->payment_method);
        $this->assertNotNull($payment1Period1->proof_image);
        Storage::disk('public')->assertExists($payment1Period1->proof_image);

        // =========================================================================
        // Step I: Admin verify payment as PAID (/arisan-app/{subdomain}/admin/payments/{payment}/verify) and assert cashflow IN is recorded
        // =========================================================================
        session(['arisan_admin_id' => $sub->id]);

        $verifyResponse1 = $this->post("/arisan-app/{$sub->subdomain}/admin/payments/{$payment1Period1->id}/verify", [
            'payment_method' => 'Bank Mandiri Syariah',
        ]);
        $verifyResponse1->assertRedirect();
        $payment1Period1->refresh();
        $this->assertEquals('PAID', $payment1Period1->status);
        $this->assertNotNull($payment1Period1->verified_by_admin_at);

        // Verify cashflow IN is recorded
        $this->assertDatabaseHas('arisan_cashflows', [
            'subscription_id' => $sub->id,
            'group_id' => $group->id,
            'type' => 'IN',
            'category' => 'IURAN',
            'amount' => 150000,
        ]);

        // Also verify payment for member2 so both slots are paid
        $verifyResponse2 = $this->post("/arisan-app/{$sub->subdomain}/admin/payments/{$payment2Period1->id}/verify", [
            'payment_method' => 'TUNAI',
        ]);
        $verifyResponse2->assertRedirect();
        $payment2Period1->refresh();
        $this->assertEquals('PAID', $payment2Period1->status);

        // Cashflow IN total should now be 300,000 (150,000 x 2)
        $totalIn = ArisanCashflow::where('subscription_id', $sub->id)->where('type', 'IN')->sum('amount');
        $this->assertEquals(300000, $totalIn);

        // =========================================================================
        // Step J: Admin execute digital draw (/arisan-app/{subdomain}/admin/draws/{period}/execute), assert winner slot marked has_won=true, cashflow OUT recorded, and cannot be drawn twice
        // =========================================================================
        // Total prize = (2 slots * 150000) - 10000 admin fee = 290000
        $expectedPrize = 290000;

        $drawResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/draws/{$period1->id}/execute", [
            'winner_slot_id' => $slot1->id,
            'prize_amount' => $expectedPrize,
        ]);
        $drawResponse->assertRedirect();

        $slot1->refresh();
        $period1->refresh();

        $this->assertTrue((bool) $slot1->has_won);
        $this->assertEquals($period1->id, $slot1->won_period_id);
        $this->assertEquals('DRAWN', $period1->status);

        $draw = ArisanDraw::where('period_id', $period1->id)->firstOrFail();
        $this->assertEquals($slot1->id, $draw->winning_group_member_id);
        $this->assertEquals($expectedPrize, $draw->prize_amount);
        $this->assertNotEmpty($draw->draw_seed_hash);

        // Assert Cashflow OUT recorded
        $this->assertDatabaseHas('arisan_cashflows', [
            'subscription_id' => $sub->id,
            'group_id' => $group->id,
            'type' => 'OUT',
            'category' => 'PENCAIRAN_PEMENANG',
            'amount' => $expectedPrize,
        ]);

        // Attempting to draw period 1 again must fail
        $drawAgainResponse = $this->post("/arisan-app/{$sub->subdomain}/admin/draws/{$period1->id}/execute", [
            'winner_slot_id' => $slot2->id,
            'prize_amount' => $expectedPrize,
        ]);
        $drawAgainResponse->assertSessionHas('error');

        // Attempting to draw slot1 again in period 2 must fail because slot1 already won
        $drawSlot1Period2 = $this->post("/arisan-app/{$sub->subdomain}/admin/draws/{$period2->id}/execute", [
            'winner_slot_id' => $slot1->id,
            'prize_amount' => $expectedPrize,
        ]);
        $drawSlot1Period2->assertSessionHas('error');

        // =========================================================================
        // Step K: Verify all WhatsApp text templates contain strictly NO EMOJIS
        // =========================================================================
        // 1. Member Account Share Text
        $waShareResponse = $this->get("/arisan-app/{$sub->subdomain}/admin/members/{$member1->id}/wa-share");
        $waShareResponse->assertStatus(200);
        $waShareData = $waShareResponse->json();
        $this->assertTrue($waShareData['success']);
        $this->assertStringContainsString('AKUN PORTAL ARISAN', $waShareData['text']);
        $this->assertStringContainsString($member1->name, $waShareData['text']);
        $this->assertStringContainsString($member1->magic_token, $waShareData['text']);
        $this->assertDoesNotMatchRegularExpression($this->emojiPattern, $waShareData['text'], 'Member WA share must not contain emojis');

        // 2. Payment Reminder Text (for unpaid period 2)
        $payment1Period2 = ArisanPayment::where('period_id', $period2->id)->where('group_member_id', $slot1->id)->firstOrFail();
        $waReminderResponse = $this->get("/arisan-app/{$sub->subdomain}/admin/payments/{$payment1Period2->id}/wa-reminder");
        $waReminderResponse->assertStatus(200);
        $waReminderData = $waReminderResponse->json();
        $this->assertTrue($waReminderData['success']);
        $this->assertStringContainsString('PENGINGAT IURAN ARISAN', $waReminderData['text']);
        $this->assertStringContainsString($member1->name, $waReminderData['text']);
        $this->assertStringContainsString('150.000', $waReminderData['text']);
        $this->assertDoesNotMatchRegularExpression($this->emojiPattern, $waReminderData['text'], 'Payment reminder WA must not contain emojis');

        // 3. Payment Receipt Text (for paid period 1)
        $waReceiptResponse = $this->get("/arisan-app/{$sub->subdomain}/admin/payments/{$payment1Period1->id}/wa-receipt");
        $waReceiptResponse->assertStatus(200);
        $waReceiptData = $waReceiptResponse->json();
        $this->assertTrue($waReceiptData['success']);
        $this->assertStringContainsString('TANDA TERIMA IURAN ARISAN', $waReceiptData['text']);
        $this->assertStringContainsString($member1->name, $waReceiptData['text']);
        $this->assertStringContainsString('Lunas', $waReceiptData['text']);
        $this->assertDoesNotMatchRegularExpression($this->emojiPattern, $waReceiptData['text'], 'Payment receipt WA must not contain emojis');

        // 4. Draw Group Announcement Text
        $waDrawResponse = $this->get("/arisan-app/{$sub->subdomain}/admin/draws/{$draw->id}/wa-share");
        $waDrawResponse->assertStatus(200);
        $waDrawData = $waDrawResponse->json();
        $this->assertTrue($waDrawData['success']);
        $this->assertStringContainsString('HASIL UNDIAN ARISAN', $waDrawData['text']);
        $this->assertStringContainsString($member1->name, $waDrawData['text']);
        $this->assertStringContainsString('290.000', $waDrawData['text']);
        $this->assertDoesNotMatchRegularExpression($this->emojiPattern, $waDrawData['text'], 'Draw announcement WA must not contain emojis');

        // =========================================================================
        // Step L: PWA manifest (/arisan-app/{subdomain}/manifest.json) returns valid tenant metadata
        // =========================================================================
        $manifestResponse = $this->get("/arisan-app/{$sub->subdomain}/manifest.json");
        $manifestResponse->assertStatus(200);
        $this->assertStringContainsString('application/manifest+json', $manifestResponse->headers->get('Content-Type') ?? '');

        $manifestData = $manifestResponse->json();
        $this->assertEquals('Arisan Berkah Nusantara', $manifestData['name']);
        $this->assertEquals('standalone', $manifestData['display']);
        $this->assertEquals('#059669', $manifestData['theme_color']);
        $this->assertEquals('#0f172a', $manifestData['background_color']);
        $this->assertEquals("/arisan-app/{$sub->subdomain}/member/login", $manifestData['start_url']);
        $this->assertEquals("/arisan-app/{$sub->subdomain}/", $manifestData['scope']);
        $this->assertIsArray($manifestData['icons']);
        $this->assertNotEmpty($manifestData['icons']);
    }
}
