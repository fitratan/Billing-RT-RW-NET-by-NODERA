<?php

namespace Tests\Feature;

use App\Models\ArisanCashflow;
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArisanPaymentAndReminderTest extends TestCase
{
    use RefreshDatabase;

    protected ArisanSubscription $subscription;
    protected ArisanGroup $group;
    protected ArisanMember $member1;
    protected ArisanMember $member2;
    protected ArisanGroupMember $slot1;
    protected ArisanGroupMember $slot2;
    protected ArisanPeriod $period1;
    protected ArisanPeriod $period2;
    protected ArisanPayment $payment1;
    protected ArisanPayment $payment2;

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
            'total_slots' => 2,
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

        $this->payment1 = ArisanPayment::create([
            'period_id' => $this->period1->id,
            'group_member_id' => $this->slot1->id,
            'amount' => 100000,
            'status' => 'UNPAID',
        ]);

        $this->payment2 = ArisanPayment::create([
            'period_id' => $this->period1->id,
            'group_member_id' => $this->slot2->id,
            'amount' => 100000,
            'status' => 'PENDING_VERIFICATION',
            'proof_image' => 'arisan/proofs/test_proof.jpg',
        ]);
    }

    public function test_admin_can_browse_payments_and_period_navigation(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/payments?group_id={$this->group->id}&period_id={$this->period1->id}");

        $response->assertStatus(200);
        $response->assertSee('Kloter Melati 1');
        $response->assertSee('Siti Nurhaliza');
        $response->assertSee('Dewi Persik');
        $response->assertSee('Putaran 1');
    }

    public function test_admin_can_verify_payment_and_auto_record_cashflow(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/payments/{$this->payment1->id}/verify", [
            'payment_method' => 'BCA',
        ]);

        $response->assertSessionHas('success');
        $this->payment1->refresh();
        $this->assertEquals('PAID', $this->payment1->status);
        $this->assertEquals('BCA', $this->payment1->payment_method);
        $this->assertNotNull($this->payment1->payment_date);
        $this->assertNotNull($this->payment1->verified_by_admin_at);

        // Cashflow check
        $this->assertDatabaseHas('arisan_cashflows', [
            'subscription_id' => $this->subscription->id,
            'group_id' => $this->group->id,
            'type' => 'IN',
            'category' => 'IURAN',
            'amount' => 100000,
        ]);
    }

    public function test_admin_can_reject_invalid_payment_proof(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/payments/{$this->payment2->id}/reject", [
            'note' => 'Bukti buram dan tidak terbaca',
        ]);

        $response->assertSessionHas('success');
        $this->payment2->refresh();
        $this->assertEquals('UNPAID', $this->payment2->status);
        $this->assertNull($this->payment2->proof_image);
        $this->assertStringContainsString('Bukti buram', $this->payment2->notes);
    }

    public function test_admin_can_record_advance_payment(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/payments/advance", [
            'group_member_id' => $this->slot1->id,
            'period_ids' => [$this->period1->id, $this->period2->id],
            'payment_method' => 'TUNAI',
        ]);

        $response->assertSessionHas('success');

        $this->payment1->refresh();
        $this->assertEquals('PAID', $this->payment1->status);

        $paymentPeriod2 = ArisanPayment::where('period_id', $this->period2->id)
            ->where('group_member_id', $this->slot1->id)
            ->first();

        $this->assertNotNull($paymentPeriod2);
        $this->assertEquals('PAID', $paymentPeriod2->status);
        $this->assertEquals('TUNAI', $paymentPeriod2->payment_method);
    }

    public function test_reminder_and_receipt_wa_text_contain_no_emojis(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        ArisanPaymentSetting::create([
            'subscription_id' => $this->subscription->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Pengelola Arisan',
        ]);

        // Reminder text
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/payments/{$this->payment1->id}/wa-reminder");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('PENGINGAT IURAN ARISAN', $data['text']);
        $this->assertStringContainsString('Siti Nurhaliza', $data['text']);
        $this->assertStringContainsString('100.000', $data['text']);
        $this->assertStringContainsString('BCA - 1234567890 a.n Pengelola Arisan', $data['text']);
        $this->assertStringContainsString('token-siti-12345', $data['text']);

        // Check for NO EMOJIS in text
        $this->assertEquals(0, preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F1E0}-\x{1F1FF}]/u', $data['text']));

        // Mark paid and check receipt text
        $this->payment1->update(['status' => 'PAID', 'payment_date' => now()]);

        $receiptResponse = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/payments/{$this->payment1->id}/wa-receipt");
        $receiptResponse->assertStatus(200);
        $receiptData = $receiptResponse->json();
        $this->assertTrue($receiptData['success']);
        $this->assertStringContainsString('TANDA TERIMA IURAN ARISAN', $receiptData['text']);
        $this->assertStringContainsString('Siti Nurhaliza', $receiptData['text']);
        $this->assertStringContainsString('Lunas', $receiptData['text']);

        // Check for NO EMOJIS in receipt text
        $this->assertEquals(0, preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F1E0}-\x{1F1FF}]/u', $receiptData['text']));
    }

    public function test_admin_can_manage_bank_accounts_and_qris_settings(): void
    {
        Storage::fake('public');
        session(['arisan_admin_id' => $this->subscription->id]);

        // Add bank account
        $response = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/settings/payments", [
            'bank_name' => 'Bank Mandiri',
            'account_number' => '987654321',
            'account_holder' => 'Bendahara Arisan',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('arisan_payment_settings', [
            'subscription_id' => $this->subscription->id,
            'bank_name' => 'Bank Mandiri',
            'account_number' => '987654321',
        ]);

        $setting = ArisanPaymentSetting::where('subscription_id', $this->subscription->id)->first();

        // Update QRIS
        $qrisImage = UploadedFile::fake()->image('qris.png');
        $qrisResponse = $this->post("/arisan-app/{$this->subscription->subdomain}/admin/settings/payments/qris", [
            'qris_image' => $qrisImage,
            'instructions' => 'Mohon sertakan nama dan nomor slot di berita transfer.',
        ]);

        $qrisResponse->assertSessionHas('success');
        $setting->refresh();
        $this->assertNotNull($setting->qris_image_path);
        $this->assertEquals('Mohon sertakan nama dan nomor slot di berita transfer.', $setting->instructions);

        // Delete bank account
        $deleteResponse = $this->delete("/arisan-app/{$this->subscription->subdomain}/admin/settings/payments/{$setting->id}");
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('arisan_payment_settings', ['id' => $setting->id]);
    }
}
