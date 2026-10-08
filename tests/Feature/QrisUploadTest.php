<?php

namespace Tests\Feature;

use App\Http\Controllers\QRISController;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrisUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_upload_and_display_global_qris(): void
    {
        Storage::fake('public');

        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'tenant_id' => null,
        ]);

        $file = UploadedFile::fake()->image('qris_sample.png', 400, 400);

        $response = $this->actingAs($superadmin)
            ->withSession(['admin_role' => 'superadmin', 'admin_id' => $superadmin->id])
            ->post('/superadmin/qris/upload', [
                'qris_image' => $file,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect('/superadmin/qris');

        // Check global payment gateway record
        $globalGateway = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', 'manual')
            ->first();

        $this->assertNotNull($globalGateway);
        $this->assertNotNull($globalGateway->config_json['qris_image_path'] ?? $globalGateway->config_json['qris_image_base64']);

        // Check QRISController index passes the resolved URL
        $indexResponse = $this->actingAs($superadmin)
            ->withSession(['admin_role' => 'superadmin', 'admin_id' => $superadmin->id])
            ->get('/superadmin/qris');

        $indexResponse->assertStatus(200);
        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Superadmin/Qris')
            ->where('configured', true)
            ->has('qrisImage')
        );
    }

    public function test_webhook_secret_is_permanent_and_cannot_be_changed_by_user(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'tenant_id' => null,
        ]);

        $originalSecret = 'sec_fixed_permanent_secret_token_123';

        PaymentGateway::withoutGlobalScopes()->create([
            'gateway'     => 'manual',
            'tenant_id'   => null,
            'is_active'   => true,
            'config_json' => [
                'webhook_secret'      => $originalSecret,
                'enable_dynamic_qris' => true,
                'qris_text'           => 'Static Test',
            ],
        ]);

        // Attempt to maliciously or accidentally overwrite webhook_secret
        $response = $this->actingAs($superadmin)
            ->withSession(['admin_role' => 'superadmin', 'admin_id' => $superadmin->id])
            ->post('/superadmin/qris/save-settings', [
                'webhook_secret' => 'sec_hacked_secret_token_999',
                'qris_text'      => 'Updated Text',
            ]);

        $response->assertStatus(302);

        $gateway = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', 'manual')
            ->first();

        // Secret token MUST NOT change!
        $this->assertEquals($originalSecret, $gateway->config_json['webhook_secret']);
        $this->assertEquals('Updated Text', $gateway->config_json['qris_text']);
    }

    public function test_sync_legacy_qris_command_populates_missing_secrets(): void
    {
        PaymentGateway::withoutGlobalScopes()->create([
            'gateway'     => 'manual',
            'tenant_id'   => null,
            'is_active'   => true,
            'config_json' => [
                'qris_text' => 'Legacy without secret',
            ],
        ]);

        $this->artisan('qris:sync-legacy')->assertSuccessful();

        $gateway = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', 'manual')
            ->first();

        $this->assertNotEmpty($gateway->config_json['webhook_secret']);
        $this->assertStringStartsWith('sec_', $gateway->config_json['webhook_secret']);
    }
}

