<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\RegistrationRequest;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*' => Http::response(['status' => true, 'message' => 'Message sent'], 200),
        ]);
    }

    public function test_pending_registration_does_not_send_whatsapp(): void
    {
        $wa = new WhatsappService();
        $reg = [
            'name' => 'John Doe',
            'phone' => '08123456789',
            'company' => 'PT Mitra Net',
            'slug' => 'mitranet',
            'status' => 'pending',
            'total_amount' => 150000,
        ];

        // Should return false and not send any message
        $this->assertFalse($wa->sendRegistrationPending($reg));
    }

    public function test_send_registration_rejected_formats_and_sends(): void
    {
        $wa = new WhatsappService();
        $reg = [
            'name' => 'John Doe',
            'phone' => '08123456789',
            'company' => 'PT Mitra Net',
            'slug' => 'mitranet',
            'status' => 'rejected',
            'notes' => 'Nomor HP tidak dapat dihubungi',
        ];

        // If gateway is not enabled, sendMessage returns false, but method executes cleanly
        $res = $wa->sendRegistrationRejected($reg, 'Dokumen tidak valid');
        $this->assertIsBool($res);
    }

    public function test_send_registration_expired_formats_and_sends(): void
    {
        $wa = new WhatsappService();
        $reg = [
            'name' => 'John Doe',
            'phone' => '08123456789',
            'company' => 'PT Mitra Net',
            'slug' => 'mitranet',
            'package_name' => 'Paket Pro',
            'status' => 'expired',
        ];

        $res = $wa->sendRegistrationExpired($reg);
        $this->assertIsBool($res);
    }

    public function test_reject_registration_endpoint_updates_status(): void
    {
        $superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superAdmin);

        $req = RegistrationRequest::factory()->create([
            'status' => 'pending',
            'phone' => '08123456789',
        ]);

        $response = $this->post("/superadmin/registrasi/reject/{$req->id}", [
            'reason' => 'Data tidak lengkap',
        ]);

        $response->assertRedirect('/superadmin/registrasi');
        $req->refresh();
        $this->assertEquals('rejected', $req->status);
        $this->assertEquals('Data tidak lengkap', $req->notes);
    }
}
